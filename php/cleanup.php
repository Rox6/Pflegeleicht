<?php
// Database cleanup script - removes contact requests older than 30 days
// This script should be run automatically via cron job or manually

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connect to database
require __DIR__ . '/db.php';

// Configuration
$DAYS_TO_KEEP = 30; // Keep records for 30 days
$logFile = __DIR__ . '/cleanup.log';

try {
    // First, check how many records will be deleted
    $checkSql = "SELECT COUNT(*) as count FROM contact_requests
                 WHERE created_at < (NOW() - INTERVAL $DAYS_TO_KEEP DAY)";
    $stmt = $pdo->query($checkSql);
    $oldRecords = $stmt->fetch()['count'];

    // Delete records older than specified days
    $sql = "DELETE FROM contact_requests
            WHERE created_at < (NOW() - INTERVAL $DAYS_TO_KEEP DAY)";
    $rows = $pdo->exec($sql);

    // Enhanced logging with more details
    $msg = date('Y-m-d H:i:s') . " - Cleanup executed successfully\n";
    $msg .= "  - Records older than $DAYS_TO_KEEP days: $oldRecords\n";
    $msg .= "  - Records deleted: $rows\n";
    $msg .= "  - Script executed from: " . ($_SERVER['REQUEST_URI'] ?? 'CLI') . "\n";
    $msg .= "  - User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n\n";

    file_put_contents($logFile, $msg, FILE_APPEND);

    // Response for web/CLI
    echo "✅ Cleanup completed successfully!\n";
    echo "📊 Records deleted: $rows\n";
    echo "📅 Keeping records from last $DAYS_TO_KEEP days\n";
    echo "📝 Check cleanup.log for details\n";

    // Optional: Clean up old log files (keep last 100 lines)
    if (file_exists($logFile)) {
        $logContent = file($logFile);
        if (count($logContent) > 100) {
            $recentLogs = array_slice($logContent, -100);
            file_put_contents($logFile, implode('', $recentLogs));
        }
    }

} catch (Throwable $e) {
    $errorMsg = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    $errorMsg .= "  - File: " . $e->getFile() . "\n";
    $errorMsg .= "  - Line: " . $e->getLine() . "\n\n";

    file_put_contents($logFile, $errorMsg, FILE_APPEND);

    echo "❌ Cleanup error: " . $e->getMessage() . "\n";
    echo "📝 Check cleanup.log for details\n";
}
