<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/refs.php';

function patient_id_for(int $userId): ?int {
    $st = db()->prepare('SELECT id FROM patients WHERE user_id=?'); $st->execute([$userId]);
    $r = $st->fetchColumn(); return $r === false ? null : (int)$r;
}
function doctor_id_for(int $userId): ?int {
    $st = db()->prepare('SELECT id FROM doctors WHERE user_id=?'); $st->execute([$userId]);
    $r = $st->fetchColumn(); return $r === false ? null : (int)$r;
}
function all_patients(): array {
    return db()->query('SELECT p.id, u.full_name FROM patients p JOIN users u ON u.id=p.user_id ORDER BY u.full_name')->fetchAll();
}
function options(array $rows, string $label = 'full_name'): string {
    $o = '';
    foreach ($rows as $r) $o .= '<option value="' . (int)$r['id'] . '">' . h($r[$label]) . '</option>';
    return $o;
}
