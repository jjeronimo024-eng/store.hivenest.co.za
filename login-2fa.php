<?php
declare(strict_types=1);

/**
 * Legacy 2FA login page — redirected to login.php which now handles
 * two-factor authentication inline via the challenge-token API flow.
 *
 * This file is kept as a safe redirect for any bookmarks or legacy links.
 */

require_once __DIR__ . '/utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();

// Clear any stale 2FA session state from the legacy flow
unset($_SESSION['2fa_customer_id']);

// If already authenticated, go straight to the account dashboard
if ((int)($_SESSION['customer_id'] ?? 0) > 0) {
    header('Location: /account.php');
} else {
    header('Location: /login.php');
}
exit;