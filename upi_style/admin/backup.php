<?php
// admin/backup.php - Database SQL Backup Exporter
require_once '../db.php';

// Custom auth check
if (!isAdmin()) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied.");
}

try {
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlDump = "-- NextPay Database SQL Dump\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Database: " . $db_name . "\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $sqlDump .= "-- --------------------------------------------------------\n";
        $sqlDump .= "-- Table structure for table `{$table}`\n";
        $sqlDump .= "-- --------------------------------------------------------\n";
        $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $sqlDump .= $createTableStmt['Create Table'] . ";\n\n";

        // Table data
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $sqlDump .= "-- Dumping data for table `{$table}`\n";
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $escapedColumns = array_map(function($col) { return "`{$col}`"; }, $columns);
                
                $values = array_values($row);
                $escapedValues = array_map(function($val) use ($pdo) {
                    if ($val === null) return 'NULL';
                    return $pdo->quote($val);
                }, $values);

                $sqlDump .= "INSERT INTO `{$table}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $escapedValues) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Set headers for download
    $filename = "NextPay_backup_" . date('Ymd_His') . ".sql";
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Content-Length: " . strlen($sqlDump));
    
    echo $sqlDump;
    exit;

} catch (PDOException $e) {
    die("Backup export failed: " . $e->getMessage());
}
?>

