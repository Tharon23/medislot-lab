<?php
// Object refs: base64url of the integer id. (IDOR #3 substrate: decode only, no ownership check.)
function ref_encode(int $id): string { return rtrim(strtr(base64_encode((string)$id), '+/', '-_'), '='); }
function ref_decode(string $ref): int { return (int)base64_decode(strtr($ref, '-_', '+/')); }
