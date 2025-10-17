<?php
/**
 * Email Subscription System - Admin Logout
 */

session_start();
session_destroy();
header('Location: login.php?logout=1');
exit();
?>