<?php
// index.php - Application Router Entrypoint
ob_start();
require_once 'db.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: home.php");
    }
} else {
    header("Location: login.php");
}
exit;

