<?php
// cleanup.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Conectar a la base de datos
require __DIR__ . '/db.php';

try {
    // Borrar registros con más de 1 día
    $sql = "DELETE FROM contact_requests
            WHERE created_at < (NOW() - INTERVAL 1 DAY)";
    $rows = $pdo->exec($sql);

    // Log simple en archivo (lo puedes revisar en tu webspace)
    $logFile = __DIR__ . '/cleanup.log';
    $msg = date('Y-m-d H:i:s') . " - Cleanup ejecutado, filas borradas: $rows\n";
    file_put_contents($logFile, $msg, FILE_APPEND);

    echo "Cleanup OK, filas borradas: $rows";

} catch (Throwable $e) {
    $logFile = __DIR__ . '/cleanup.log';
    $msg = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $msg, FILE_APPEND);

    echo "Error en cleanup: " . $e->getMessage();
}
