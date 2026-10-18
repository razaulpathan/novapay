<?php
// masteragent/logout.php - Head Agent Logout Handler
require_once '../db.php';
session_destroy();
header("Location: ../login.php");
exit;
