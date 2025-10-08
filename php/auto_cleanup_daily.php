<?php
// Daily automatic database cleanup
// This script runs when someone visits the website (max once per day)

// Suppress any output to avoid interfering with page display
ob_start();

try {
    // Configuration
    $cleanupFile = __DIR__ . '/last_cleanup.txt';
    $today = date('Y-m-d');

    // Check when was the last cleanup
    $lastCleanupDate = '';
    if (file_exists($cleanupFile)) {
        $lastCleanupDate = trim(@file_get_contents($cleanupFile));
    }

    // Only run cleanup if it hasn't been run today
    if ($lastCleanupDate !== $today) {
        // Connect to database
        require_once __DIR__ . '/db.php';

        // Check how many old records exist before cleanup
        $checkSql = "SELECT COUNT(*) as count FROM contact_requests
                     WHERE created_at < (NOW() - INTERVAL 30 DAY)";
        $stmt = $pdo->query($checkSql);
        $oldRecords = $stmt->fetch()['count'];

        // Only run cleanup if there are old records to delete
        if ($oldRecords > 0) {
            // Delete records older than 30 days
            $cleanupSql = "DELETE FROM contact_requests
                          WHERE created_at < (NOW() - INTERVAL 1 DAY)";
            $deletedRows = $pdo->exec($cleanupSql);

            // Log the automatic cleanup
            $logFile = __DIR__ . '/cleanup.log';
            $msg = date('Y-m-d H:i:s') . " - DAILY AUTO CLEANUP executed\n";
            $msg .= "  - Records older than 30 days: $oldRecords\n";
            $msg .= "  - Records deleted: $deletedRows\n";
            $msg .= "  - Triggered by website visit\n";
            $msg .= "  - Last cleanup was: " . ($lastCleanupDate ?: 'Never') . "\n\n";
            file_put_contents($logFile, $msg, FILE_APPEND);
        }

        // Save today's date to prevent multiple runs today
        file_put_contents($cleanupFile, $today);
    }

} catch (Exception $e) {
    // Log any errors but don't interfere with page display
    $logFile = __DIR__ . '/cleanup.log';
    $msg = date('Y-m-d H:i:s') . " - DAILY AUTO CLEANUP ERROR: " . $e->getMessage() . "\n\n";
    @file_put_contents($logFile, $msg, FILE_APPEND);
}

// Clear any output to avoid interfering with the page
ob_end_clean();
?>