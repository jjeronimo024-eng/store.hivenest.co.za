<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (session_status() === PHP_SESSION_NONE) {
    session_name('HIVENEST_ADMIN');
    session_start();
}
require_once __DIR__ . '/../access/dbconfig.php';
require_once __DIR__ . '/../utilities/crm_permissions.php';

function hivenest_crm_refunds_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function hivenest_crm_refunds_env(string $key, string $default = ''): string
{
    $path = __DIR__ . '/../Backend/.env';
    foreach (is_readable($path) ? (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) === $key) return trim(trim($value), "\"'");
    }
    return $default;
}
function hivenest_crm_refunds_b64(string $value): string|false
{
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode(strtr($value, '-_', '+/'), true);
}
function hivenest_crm_refunds_admin(PDO $db): array
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = preg_match('/Bearer\s+(.+)/i', $header, $match) ? trim($match[1]) : '';
    if ($token === '') return [];
    $parts = explode('.', $token);
    if (count($parts) !== 3) return [];
    [$head, $body, $signature] = $parts;
    $headJson = hivenest_crm_refunds_b64($head);
    $bodyJson = hivenest_crm_refunds_b64($body);
    $jwtHead = $headJson === false ? null : json_decode($headJson, true);
    $payload = $bodyJson === false ? null : json_decode($bodyJson, true);
    $secret = hivenest_crm_refunds_env('JWT_SECRET_KEY');
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    if (!is_array($jwtHead) || !is_array($payload) || $secret === ''
        || ($jwtHead['alg'] ?? '') !== hivenest_crm_refunds_env('JWT_ALGORITHM', 'HS256')
        || ($payload['user_type'] ?? '') !== 'admin'
        || (!empty($payload['exp']) && (int)$payload['exp'] < time())
        || !hash_equals($expected, $signature)
    ) return [];
    $stmt = $db->prepare('SELECT id,username,email,role FROM admin_users WHERE id=:id AND is_active=1 LIMIT 1');
    $stmt->execute(['id' => (int)($payload['sub'] ?? 0)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
function hivenest_crm_refunds_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function hivenest_crm_refunds_paypal(string $method, string $path, ?array $payload = null, array $headers = []): array
{
    if (!function_exists('curl_init')) return ['ok' => false, 'status' => 503, 'error' => 'PHP cURL is unavailable.'];
    $client = hivenest_crm_refunds_env('PAYPAL_CLIENT_ID');
    $secret = hivenest_crm_refunds_env('PAYPAL_CLIENT_SECRET');
    $mode = strtolower(hivenest_crm_refunds_env('PAYPAL_MODE', 'sandbox'));
    $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    if ($client === '' || $secret === '') return ['ok' => false, 'status' => 503, 'error' => 'PayPal credentials are not configured.'];

    $auth = curl_init($base . '/v1/oauth2/token');
    curl_setopt_array($auth, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $client . ':' . $secret,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
        CURLOPT_TIMEOUT => 25,
    ]);
    $authBody = curl_exec($auth);
    $authStatus = (int)curl_getinfo($auth, CURLINFO_HTTP_CODE);
    $authError = curl_error($auth);
    curl_close($auth);
    $authData = is_string($authBody) ? json_decode($authBody, true) : null;
    if ($authError !== '' || $authStatus !== 200 || !is_array($authData) || empty($authData['access_token'])) {
        return ['ok' => false, 'status' => 502, 'error' => 'Unable to authenticate with PayPal.'];
    }

    $request = curl_init($base . $path);
    $requestHeaders = array_merge([
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $authData['access_token'],
        'Prefer: return=representation',
    ], $headers);
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_POSTFIELDS => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 35,
    ]);
    $body = curl_exec($request);
    $status = (int)curl_getinfo($request, CURLINFO_HTTP_CODE);
    $error = curl_error($request);
    curl_close($request);
    $data = is_string($body) && $body !== '' ? json_decode($body, true) : [];
    return [
        'ok' => $error === '' && $status >= 200 && $status < 300 && is_array($data),
        'status' => $status ?: 502,
        'data' => is_array($data) ? $data : [],
        'error' => $error !== '' ? 'PayPal connection failed.' : null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hivenest_crm_refunds_out(405, ['ok' => false, 'error' => 'POST required.']);
}
$db = hivenest_db();
if (!$db) hivenest_crm_refunds_out(503, ['ok' => false, 'error' => 'CRM database is unavailable.']);
$admin = hivenest_crm_refunds_admin($db);
if (!$admin) hivenest_crm_refunds_out(401, ['ok' => false, 'error' => 'Bearer administrator authentication required.']);
if (!hivenest_crm_role_allows($admin, 'refund.issue')) {
    hivenest_crm_refunds_out(403, ['ok' => false, 'error' => 'Only administrators may issue refunds.']);
}
$table = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_refunds'");
if ((int)$table->fetchColumn() !== 1) {
    hivenest_crm_refunds_out(503, ['ok' => false, 'error' => 'Refund ledger is not installed. Import Database/payment_refunds.sql.']);
}
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) hivenest_crm_refunds_out(400, ['ok' => false, 'error' => 'Invalid JSON input.']);
$orderId = (int)($input['order_id'] ?? 0);
$amount = round((float)($input['amount'] ?? 0), 2);
$reason = trim((string)($input['reason'] ?? ''));
if ($orderId <= 0 || $amount <= 0 || strlen($reason) < 10 || strlen($reason) > 500) {
    hivenest_crm_refunds_out(422, ['ok' => false, 'error' => 'Choose a positive refund amount and provide a reason of 10 to 500 characters.']);
}

$refundId = 0;
$requestId = 'HN-REF-' . hivenest_crm_refunds_uuid();
try {
    $db->beginTransaction();
    $paymentStmt = $db->prepare("
        SELECT pgt.*, o.order_number, o.payment_status
        FROM payment_gateway_transactions pgt
        INNER JOIN orders o ON o.id=pgt.order_id
        WHERE pgt.order_id=:order_id AND pgt.gateway='paypal'
        ORDER BY pgt.id DESC LIMIT 1
        FOR UPDATE
    ");
    $paymentStmt->execute(['order_id' => $orderId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment || trim((string)$payment['gateway_capture_id']) === '') {
        throw new DomainException('No captured PayPal payment exists for this order.');
    }
    if (!in_array((string)$payment['payment_status'], ['paid', 'partially_refunded'], true)) {
        throw new DomainException('This order is not eligible for another refund.');
    }
    $sum = $db->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM payment_refunds
        WHERE payment_transaction_id=:transaction_id
          AND status IN ('requested','pending','completed')
    ");
    $sum->execute(['transaction_id' => (int)$payment['id']]);
    $refunded = round((float)$sum->fetchColumn(), 2);
    $remaining = round((float)$payment['amount'] - $refunded, 2);
    if ($amount > $remaining + 0.001) {
        throw new DomainException('Refund exceeds the remaining refundable amount of ' . (string)$payment['currency'] . ' ' . number_format($remaining, 2, '.', '') . '.');
    }
    $uuid = hivenest_crm_refunds_uuid();
    $insert = $db->prepare("
        INSERT INTO payment_refunds
            (uuid,order_id,payment_transaction_id,customer_id,admin_user_id,gateway,gateway_capture_id,
             request_id,amount,currency,reason,status)
        VALUES
            (:uuid,:order_id,:transaction_id,:customer_id,:admin_id,'paypal',:capture_id,
             :request_id,:amount,:currency,:reason,'requested')
    ");
    $insert->execute([
        'uuid' => $uuid,
        'order_id' => $orderId,
        'transaction_id' => (int)$payment['id'],
        'customer_id' => (int)$payment['customer_id'],
        'admin_id' => (int)$admin['id'],
        'capture_id' => (string)$payment['gateway_capture_id'],
        'request_id' => $requestId,
        'amount' => $amount,
        'currency' => (string)$payment['currency'],
        'reason' => $reason,
    ]);
    $refundId = (int)$db->lastInsertId();
    $db->commit();

    $paypal = hivenest_crm_refunds_paypal(
        'POST',
        '/v2/payments/captures/' . rawurlencode((string)$payment['gateway_capture_id']) . '/refund',
        ['amount' => ['value' => number_format($amount, 2, '.', ''), 'currency_code' => (string)$payment['currency']]],
        ['PayPal-Request-Id: ' . $requestId]
    );
    $provider = $paypal['data'] ?? [];
    $providerStatus = strtoupper((string)($provider['status'] ?? ''));
    $providerRefundId = trim((string)($provider['id'] ?? ''));
    if (empty($paypal['ok']) || $providerRefundId === '' || !in_array($providerStatus, ['COMPLETED', 'PENDING'], true)) {
        $detail = (string)($paypal['error'] ?? ($provider['message'] ?? 'PayPal rejected the refund.'));
        $db->prepare("
            UPDATE payment_refunds
            SET status='failed',provider_response=:response,error_message=:error
            WHERE id=:id
        ")->execute([
            'response' => json_encode($provider, JSON_UNESCAPED_SLASHES),
            'error' => $detail,
            'id' => $refundId,
        ]);
        hivenest_crm_refunds_out((int)($paypal['status'] ?? 502), ['ok' => false, 'error' => $detail]);
    }
    $localStatus = $providerStatus === 'COMPLETED' ? 'completed' : 'pending';
    $db->prepare("
        UPDATE payment_refunds
        SET status=:status,provider_refund_id=:provider_id,provider_response=:response,
            error_message=NULL,completed_at=CASE WHEN :completed='completed' THEN NOW() ELSE completed_at END
        WHERE id=:id
    ")->execute([
        'status' => $localStatus,
        'provider_id' => $providerRefundId,
        'response' => json_encode($provider, JSON_UNESCAPED_SLASHES),
        'completed' => $localStatus,
        'id' => $refundId,
    ]);
    if ($localStatus === 'completed') {
        $completed = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_refunds WHERE payment_transaction_id=:id AND status='completed'");
        $completed->execute(['id' => (int)$payment['id']]);
        $completedTotal = round((float)$completed->fetchColumn(), 2);
        $fullyRefunded = $completedTotal + 0.001 >= (float)$payment['amount'];
        $db->prepare("
            UPDATE orders
            SET payment_status=:payment_status,order_status=:order_status,provisioning_status='manual_review'
            WHERE id=:id
        ")->execute([
            'payment_status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
            'order_status' => $fullyRefunded ? 'refunded' : 'processing',
            'id' => $orderId,
        ]);
    }
    hivenest_crm_refunds_out(200, [
        'ok' => true,
        'refund_id' => $providerRefundId,
        'status' => $localStatus,
        'message' => $localStatus === 'completed'
            ? 'PayPal refund completed. Service impact requires manual review.'
            : 'PayPal accepted the refund and it is pending.',
    ]);
} catch (DomainException $e) {
    if ($db->inTransaction()) $db->rollBack();
    hivenest_crm_refunds_out(409, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if ($refundId > 0) {
        try {
            $db->prepare("UPDATE payment_refunds SET status='manual_review',error_message=:error WHERE id=:id")
                ->execute(['error' => 'Refund processing interrupted; verify the PayPal request before retrying.', 'id' => $refundId]);
        } catch (Throwable) {
        }
    }
    error_log('CRM PayPal refund failed: ' . $e->getMessage());
    hivenest_crm_refunds_out(500, ['ok' => false, 'error' => 'Refund processing failed. Verify PayPal before retrying.']);
}
