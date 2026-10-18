<?php
// agent/logout.php - Promoter Sign Out
require_once '../db.php';

session_destroy();
header("Location: login.php");
exit;
?>

