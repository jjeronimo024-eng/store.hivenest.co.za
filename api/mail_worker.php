<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../utilities/mail_delivery.php';

if (PHP_SAPI !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required.']);
        exit;
    }
    $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $authorization, $match) ? trim($match[1]) : '';
    $expected = hivenest_mail_env('MAIL_WORKER_TOKEN');
    if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Valid mail worker token required.']);
        exit;
    }
}

$result = hivenest_mail_process_queue((int)($_GET['limit'] ?? 25));
echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_SLASHES);
