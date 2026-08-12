<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/access/dbconfig.php';

$current_page = 'verify-email';
$page_title = 'Verify Email - HiveNest Matrix';
$page_description = 'Verify your HiveNest account email address.';
$page_keywords = 'verify email, hivenest account';

$status = 'error';
$message = 'Verification link is invalid or expired.';
$token = (string) ($_GET['token'] ?? '');

if ($token !== '' && preg_match('/^[A-Za-z0-9_-]{32,}$/', $token)) {
    $db = hivenest_db();
    if ($db) {
        try {
            $hash = hash('sha256', $token);
            $db->beginTransaction();

            $stmt = $db->prepare(
                "SELECT v.id, v.customer_id, v.email, c.email AS current_email
                 FROM customer_email_verifications v
                 JOIN customers c ON c.id = v.customer_id
                 WHERE v.token_hash = :hash
                   AND v.consumed_at IS NULL
                   AND v.expires_at > NOW()
                   AND c.status = 'active'
                 LIMIT 1"
            );
            $stmt->execute(['hash' => $hash]);
            $row = $stmt->fetch();

            if ($row && strtolower((string) $row['email']) === strtolower((string) $row['current_email'])) {
                $updateCustomer = $db->prepare('UPDATE customers SET email_verified = 1, updated_at = NOW() WHERE id = :id');
                $updateCustomer->execute(['id' => (int) $row['customer_id']]);

                $consume = $db->prepare('UPDATE customer_email_verifications SET consumed_at = NOW() WHERE customer_id = :customer_id AND consumed_at IS NULL');
                $consume->execute(['customer_id' => (int) $row['customer_id']]);

                if ((int) ($_SESSION['customer_id'] ?? 0) === (int) $row['customer_id']) {
                    $_SESSION['customer_email_verified'] = 1;
                }

                $db->commit();
                $status = 'success';
                $message = 'Email verified. You can now continue checkout.';
            } else {
                $db->rollBack();
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Email verification failed: ' . $e->getMessage());
            $message = 'Verification could not be completed. Please request a new link.';
        }
    } else {
        $message = 'Customer database is unavailable. Please try again shortly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'utilities/head.php'; ?>
</head>
<body>
<?php include 'utilities/nav.php'; ?>
<?php include 'utilities/mobile-menu.php'; ?>

<section class="section" style="min-height:70vh;display:flex;align-items:center;">
    <div class="container">
        <div class="cyber-card" style="max-width:760px;margin:0 auto;text-align:center;">
            <i class="fas <?php echo $status === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>" style="font-size:4rem;color:<?php echo $status === 'success' ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-pink)'; ?>;margin-bottom:1rem;"></i>
            <h1 style="color:var(--cyber-neon-cyan);">EMAIL VERIFICATION</h1>
            <p class="hero-subtitle"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:2rem;">
                <a class="btn btn-primary" href="checkout.php">CONTINUE CHECKOUT</a>
                <a class="btn btn-secondary" href="auth.php?mode=login">ACCESS PORTAL</a>
            </div>
        </div>
    </div>
</section>

<?php include 'utilities/footer.php'; ?>
<?php include 'utilities/scripts.php'; ?>
</body>
</html>
