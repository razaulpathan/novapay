<?php
// upimenger/index.php - Route user to correct page
require_once '../db.php';

if (isAdmin()) {
    header("Location: addupi.php");
} else {
    header("Location: login.php");
}
exit;
?>
