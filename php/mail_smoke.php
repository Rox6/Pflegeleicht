<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/PHPMailer.php';
require __DIR__ . '/SMTP.php';
require __DIR__ . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$SMTP_HOST = 'smtp.strato.de';
$SMTP_USER = 'info@570875270.swh.strato-hosting.eu';
$SMTP_PASS = 'ProbandoProbando123!';          
$SMTP_PORT = 587; // si falla, probamos 465 más abajo

try {
  $m = new PHPMailer(true);
  $m->isSMTP();
  $m->Host = $SMTP_HOST;
  $m->SMTPAuth = true;
  $m->Username = $SMTP_USER;
  $m->Password = $SMTP_PASS;
  $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
  $m->Port = $SMTP_PORT;

  // DEBUG en pantalla para ver exactamente qué pasa (temporal)
  $m->SMTPDebug = 2;
  $m->Debugoutput = 'html';

  $m->setFrom($SMTP_USER, 'SmokeTest');
  $m->addAddress($SMTP_USER, 'Yo');
  $m->Subject = 'Smoke test PHPMailer';
  $m->Body    = 'Hola, test SMTP desde Strato.';
  $m->AltBody = 'Hola, test SMTP.';

  $m->send();
  echo "<p>OK enviado</p>";
} catch (Exception $e) {
  echo "<p>ERROR: ".$e->getMessage()."</p>";
}
