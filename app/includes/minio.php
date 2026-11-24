<?php
// Minimal AWS SigV4 S3 client (path-style) for MinIO. No SDK.
require_once __DIR__ . '/config.php';

function minio_request(string $method, string $path, string $body = '', array $extra = [], string $query = ''): array {
    $u = parse_url(MINIO_ENDPOINT);
    $host = $u['host'] . ':' . $u['port'];
    $region = 'us-east-1';
    $amz = gmdate('Ymd\THis\Z');
    $date = substr($amz, 0, 8);
    $payloadHash = hash('sha256', $body);
    $uri = '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $headers = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amz];
    foreach ($extra as $k => $v) $headers[strtolower($k)] = $v;
    ksort($headers);
    $canonH = '';
    foreach ($headers as $k => $v) $canonH .= $k . ':' . trim($v) . "\n";
    $signed = implode(';', array_keys($headers));
    $canon = "$method\n$uri\n$query\n$canonH\n$signed\n$payloadHash";
    $scope = "$date/$region/s3/aws4_request";
    $sts = "AWS4-HMAC-SHA256\n$amz\n$scope\n" . hash('sha256', $canon);
    $k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $date, 'AWS4' . MINIO_PASS, true), true), true), true);
    $sig = hash_hmac('sha256', $sts, $k);
    $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . MINIO_USER . "/$scope, SignedHeaders=$signed, Signature=$sig";
    $h = [];
    foreach ($headers as $n => $v) $h[] = "$n: $v";
    $ch = curl_init(MINIO_ENDPOINT . $uri . ($query !== '' ? '?' . rtrim($query, '=') : ''));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code === 0) error_log("minio curl: " . curl_error($ch));
    curl_close($ch);
    return [$code, (string)$resp];
}

function minio_put_object(string $key, string $bytes, string $contentType = 'application/octet-stream'): bool {
    [$c] = minio_request('PUT', MINIO_BUCKET . '/' . $key, $bytes, ['content-type' => $contentType]);
    return $c === 200;
}

function minio_get_object(string $key): ?string {
    [$c, $b] = minio_request('GET', MINIO_BUCKET . '/' . $key);
    return $c === 200 ? $b : null;
}

// Idempotent: create bucket + INTENTIONAL (#11) public-read policy.
function minio_ensure_bucket(): void {
    minio_request('PUT', MINIO_BUCKET);
    $policy = json_encode([
        'Version' => '2012-10-17',
        'Statement' => [[
            'Effect' => 'Allow', 'Principal' => ['AWS' => ['*']],
            'Action' => ['s3:GetObject'],
            'Resource' => ['arn:aws:s3:::' . MINIO_BUCKET . '/*'],
        ]],
    ]);
    minio_request('PUT', MINIO_BUCKET, $policy, [], 'policy=');
}
