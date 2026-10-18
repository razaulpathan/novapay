<?php
// upimenger/deleteupi.php - Delete UPI ID Script
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM rotating_upis WHERE id = ?");
        $stmt->execute([$id]);
        
        // If we deleted the currently active UPI ID, we reset settings to let the system auto-pick the next one
        $current_active_id = getSetting('current_active_upi_id') ?: 0;
        if ($current_active_id == $id) {
            updateSetting('current_active_upi_id', 0);
            updateSetting('current_upi_order_count', 0);
        }
    } catch (PDOException $e) {
        // Suppress or log error
    }
}

header("Location: addupi.php");
exit;
