<?php
declare(strict_types=1);

require_once __DIR__ . '/utilities/customer_session.php';
hivenest_customer_session_configure();
session_start();
hivenest_customer_session_destroy();
header('Location: index.php');
exit;
