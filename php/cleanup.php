<?php
// php/cleanup_prepend.php
// Se ejecuta al inicio de TODA petición PHP (si auto_prepend está activo)

try {
    // Asegúrate de que la ruta apunta bien a tu db.php
    require __DIR__ . '/db.php'; // debe definir $pdo

    // Archivo bandera para ejecutar máximo 1 vez cada 24h
    $flag = __DIR__ . '/last_cleanup.flag';
    $now  = time();
    $last = is_file($flag) ? filemtime($flag) : 0;

    // ¿Han pasado 24h?
    if ($last === 0 || ($now - $last) >= 24 * 3600) {
        // Lock para evitar que múltiples peticiones limpien a la vez
        $fp = @fopen($flag, 'c+');
        if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
            try {
                // Borrar > 30 días (ajusta el plazo a tu política)
                // Importante: tener índice en created_at
                $pdo->exec("
                    DELETE FROM contact_requests
                    WHERE created_at < (NOW() - INTERVAL 30 DAY)
                    LIMIT 2000
                ");

                // Marca timestamp
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string)$now);

                // Log opcional sin datos personales
                // file_put_contents(__DIR__.'/cleanup.log',
                //     date('Y-m-d H:i:s')." cleanup ok\n", FILE_APPEND);
            } catch (Throwable $e) {
                error_log('cleanup_prepend ERROR: '.$e->getMessage());
            } finally {
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        } elseif ($fp) {
            fclose($fp);
        }
    }
} catch (Throwable $e) {
    // Nunca rompas la petición del usuario
    error_log('cleanup_prepend bootstrap ERROR: '.$e->getMessage());
}
