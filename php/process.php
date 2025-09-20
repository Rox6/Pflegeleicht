<?php
// === DEBUG mientras pruebas (luego puedes quitarlo) ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Carga PDO ($pdo)
require __DIR__ . '/db.php';

// Bloquea métodos que no sean POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// Honeypot
$company = trim($_POST['company'] ?? '');
if ($company !== '') {
  header('Location: /index.html?ok=1#kontakt');
  exit;
}

// Helpers
$pick = function(...$keys) {
  foreach ($keys as $k) {
    if (isset($_POST[$k]) && $_POST[$k] !== '') return trim((string)$_POST[$k]);
  }
  return '';
};

function get_multi_values_from_raw($key) {
  $raw = file_get_contents('php://input');
  $vals = [];
  if ($raw !== false && $raw !== '') {
    foreach (explode('&', $raw) as $pair) {
      if ($pair === '') continue;
      $kv = explode('=', $pair, 2);
      $k  = urldecode($kv[0] ?? '');
      $v  = urldecode($kv[1] ?? '');
      if ($k === $key) $vals[] = $v;
    }
  }
  return $vals;
}

// Campos
$first_name = $pick('first_name', 'vorname');
$last_name  = $pick('last_name',  'nachname');

if ($first_name === '' || $last_name === '') {
  $dup = get_multi_values_from_raw('name'); 
  if (count($dup) >= 2) {
    if ($first_name === '') $first_name = trim($dup[0]);
    if ($last_name  === '') $last_name  = trim($dup[1]);
  } elseif (count($dup) === 1 && $first_name === '' && $last_name === '') {
    $full = trim($dup[0]);
    if (strpos($full, ' ') !== false) {
      $parts = preg_split('/\s+/', $full);
      $last  = array_pop($parts);
      $first = implode(' ', $parts);
      $first_name = trim($first);
      $last_name  = trim($last);
    } else {
      $first_name = $full;
    }
  }
}

$phone   = $pick('phone', 'telefon', 'telefonnummer');
$email   = $pick('email', 'e_mail', 'email_adresse');
$concern = $pick('concern', 'ihr_anliegen', 'anliegen');
$message = $pick('message', 'nachricht');
$privacy = (isset($_POST['privacy']) && (string)$_POST['privacy'] === '1') ? 1 : 0;

// Validaciones
$errors = [];
if ($first_name === '') $errors[] = 'Vorname ist erforderlich.';
if ($last_name  === '') $errors[] = 'Nachname ist erforderlich.';
if ($phone      === '') $errors[] = 'Telefon ist erforderlich.';
if ($email      === '') $errors[] = 'E-Mail ist erforderlich.';
if ($concern    === '') $errors[] = 'Anliegen ist erforderlich.';
if ($message    === '') $errors[] = 'Nachricht ist erforderlich.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ungültige E-Mail-Adresse.';
if ($privacy !== 1) $errors[] = 'Bitte Datenschutzerklärung bestätigen.';

if ($errors) {
  $msg = rawurlencode(implode(' ', $errors));
  header('Location: /index.html?err=' . $msg . '#kontakt');
  exit;
}

// Insertar en BD
try {
  $sql = "INSERT INTO contact_requests
          (first_name, last_name, phone, email, concern, message, privacy)
          VALUES (:first_name, :last_name, :phone, :email, :concern, :message, :privacy)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':first_name' => $first_name,
    ':last_name'  => $last_name,
    ':phone'      => $phone,
    ':email'      => $email,
    ':concern'    => $concern,
    ':message'    => $message,
    ':privacy'    => $privacy,
  ]);
  $insert_id = $pdo->lastInsertId();
  error_log('CONTACT OK id=' . $insert_id . ' email=' . $email);
} catch (Throwable $e) {
  error_log('DB ERROR: ' . $e->getMessage());
  $msg = rawurlencode('DB-Fehler. Bitte später erneut versuchen.');
  header('Location: /index.html?err=' . $msg . '#kontakt');
  exit;
}

// ================== EMAIL ==================
require __DIR__ . '/PHPMailer.php';
require __DIR__ . '/SMTP.php';
require __DIR__ . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$SMTP_HOST = 'smtp.strato.de';
$SMTP_USER = 'info@570875270.swh.strato-hosting.eu';
$SMTP_PASS = 'ProbandoProbando123!';  // <-- cambia
$SMTP_PORT = 587;

$MAIL_FROM      = $SMTP_USER;
$MAIL_FROM_NAME = 'PflegeLeicht';
$MAIL_TO_ADMIN  = 'info@570875270.swh.strato-hosting.eu';

// Contenidos
$subjectAdmin = 'Neue Kontaktanfrage';
$bodyAdminTxt = "ID: $insert_id\nVorname: $first_name\nNachname: $last_name\nTelefon: $phone\nE-Mail: $email\nAnliegen: $concern\n\nNachricht:\n$message\n";
$bodyAdminHtml = nl2br(htmlspecialchars($bodyAdminTxt));

$subjectUser = 'Ihre Anfrage wurde erhalten';
$bodyUserTxt = "Guten Tag {$first_name} {$last_name},\n\nvielen Dank für Ihre Nachricht. Wir haben Ihre Anfrage erhalten und melden uns zeitnah.\n\nFreundliche Grüße\nPflegeLeicht Team\n";
$bodyUserHtml = nl2br(htmlspecialchars($bodyUserTxt));

function sendMailStrato($host,$port,$user,$pass,$from,$fromName,$to,$toName,$subject,$html,$text,$replyTo=null,$replyName=null) {
  $m = new PHPMailer(true);
  try {
    $m->isSMTP();
    $m->Host       = $host;
    $m->SMTPAuth   = true;
    $m->Username   = $user;
    $m->Password   = $pass;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = $port;
    $m->CharSet    = 'UTF-8';
    $m->SMTPDebug  = 0; // importante: no mostrar debug en producción

    $m->setFrom($from, $fromName);
    $m->addAddress($to, $toName ?: $to);
    if ($replyTo) $m->addReplyTo($replyTo, $replyName ?: $replyTo);

    $m->isHTML(true);
    $m->Subject = $subject;
    $m->Body    = $html;
    $m->AltBody = $text;

    $m->send();
    return true;
  } catch (Exception $e) {
    error_log('MAIL ERROR: '.$m->ErrorInfo);
    return false;
  }
}

// Enviar admin
sendMailStrato($SMTP_HOST,$SMTP_PORT,$SMTP_USER,$SMTP_PASS,
  $MAIL_FROM,$MAIL_FROM_NAME,
  $MAIL_TO_ADMIN,'Admin',
  $subjectAdmin,$bodyAdminHtml,$bodyAdminTxt,
  $email,$first_name.' '.$last_name
);

// Enviar usuario
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
  sendMailStrato($SMTP_HOST,$SMTP_PORT,$SMTP_USER,$SMTP_PASS,
    $MAIL_FROM,$MAIL_FROM_NAME,
    $email,$first_name.' '.$last_name,
    $subjectUser,$bodyUserHtml,$bodyUserTxt,
    $MAIL_TO_ADMIN,'PflegeLeicht'
  );
}

// ================== REDIRECCIÓN FINAL ==================
header('Location: /index.html?ok=1#kontakt');
exit;
