<?php
declare(strict_types=1);

require_once __DIR__ . '/access/dbconfig.php';

$current_page = 'newsletter-confirm';
$page_title = 'Confirm Newsletter - HiveNest Matrix';
$page_description = 'Confirm your HiveNest newsletter subscription.';
$page_keywords = 'hivenest newsletter confirmation';

$confirmed = false;
$message = 'This confirmation link is invalid or has expired.';
$token = trim((string)($_GET['token'] ?? ''));

if ($token !== '' && preg_match('/^[A-Za-z0-9_-]{32,}$/', $token)) {
    $db = hivenest_db();
    if ($db) {
        try {
            $hash = hash('sha256', $token);
            $db->beginTransaction();
            $select = $db->prepare(
                "SELECT id
                 FROM newsletter_subscribers
                 WHERE confirmation_token_hash = :hash
                   AND status = 'pending'
                   AND confirmation_expires_at > UTC_TIMESTAMP()
                 LIMIT 1
                 FOR UPDATE"
            );
            $select->execute(['hash' => $hash]);
            $subscriberId = (int)($select->fetchColumn() ?: 0);

            if ($subscriberId > 0) {
                $update = $db->prepare(
                    "UPDATE newsletter_subscribers
                     SET status = 'active',
                         confirmed_at = UTC_TIMESTAMP(),
                         confirmation_token_hash = NULL,
                         confirmation_expires_at = NULL
                     WHERE id = :id"
                );
                $update->execute(['id' => $subscriberId]);
                $db->commit();
                $confirmed = true;
                $message = 'Your subscription is confirmed. Welcome to the HiveNest network.';
            } else {
                $db->rollBack();
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Newsletter confirmation failed: ' . $e->getMessage());
            $message = 'Confirmation could not be completed. Please try again shortly.';
        }
    } else {
        $message = 'Newsletter service is temporarily unavailable.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/utilities/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/utilities/nav.php'; ?>
<?php include __DIR__ . '/utilities/mobile-menu.php'; ?>

<section class="section" style="min-height:70vh;display:flex;align-items:center;">
    <div class="container">
        <div class="cyber-card" style="max-width:760px;margin:0 auto;text-align:center;">
            <i class="fas <?php echo $confirmed ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"
               style="font-size:4rem;color:<?php echo $confirmed ? 'var(--cyber-neon-green)' : 'var(--cyber-neon-pink)'; ?>;margin-bottom:1rem;"></i>
            <h1 style="color:var(--cyber-neon-cyan);">NEWSLETTER CONFIRMATION</h1>
            <p class="hero-subtitle"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <div style="margin-top:2rem;">
                <a class="btn btn-primary" href="index.php">RETURN HOME</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/utilities/footer.php'; ?>
<?php include __DIR__ . '/utilities/scripts.php'; ?>
</body>
</html>
