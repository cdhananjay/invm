<?php
/**
 * Index Page - Redirects to dashboard or login
 */

require_once 'config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>
