<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/db.php';

try {
  // PRUEBA: 10 minutos (en prod vuelve a 1 día)
  $sql = "DELETE FROM contact_requests
          WHERE created_at < (NOW() - INTERVAL 30 Days)";
  $rows = $pdo->exec($sql);

  // Log
  $logFile = __DIR__ . '/cleanup.log';
  file_put_contents($logFile,
      date('Y-m-d H:i:s')." - Deleted: $rows\n", FILE_APPEND);

  http_response_code(204); // No Content
} catch (Throwable $e) {
  http_response_code(500);
  error_log('cleanup local ERROR: '.$e->getMessage());
  echo "Error: ".$e->getMessage();
}