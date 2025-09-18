<?php
$host = "database-5018641644.webspace-host.com";
$user = "dbu747924";
$pass = "ProbandoProbando!";
$db   = "dbs14775351";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
echo "Conexión exitosa!";
?>
