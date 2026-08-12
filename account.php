<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: /login.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Account</title>
</head>
<body>
    <h1>Welcome to your account</h1>
    <p>You are logged in as <?php echo $_SESSION['customer_email']; ?>.</p>
    <a href="/2fa-enrolment.php">Set up Two-Factor Authentication</a>
    <br>
    <a href="/api/customer-auth.php?action=logout">Logout</a>
</body>
</html>