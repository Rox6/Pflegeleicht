<?php
// === DEBUG mientras pruebas (luego puedes quitarlo) ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Carga PDO ($pdo)
require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// Honeypot (si se llena, ignoramos y aparentamos OK)
$company = trim($_POST['company'] ?? '');
if ($company !== '') {
  header('Location: /index.html?ok=1#kontakt');
  exit;
}

// --------------- Helpers ---------------
$pick = function(...$keys) {
  foreach ($keys as $k) {
    if (isset($_POST[$k]) && $_POST[$k] !== '') return trim((string)$_POST[$k]);
  }
  return '';
};

// Intenta recuperar múltiples ocurrencias de una misma clave del cuerpo crudo
function get_multi_values_from_raw($key) {
  $raw = file_get_contents('php://input');           // cuerpo application/x-www-form-urlencoded
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

// --------------- Lectura de campos ---------------
// Primero, los nombres si vienen "bien" nombrados
$first_name = $pick('first_name', 'vorname');
$last_name  = $pick('last_name',  'nachname');

// Si NO vienen, tu formulario envía dos inputs con el MISMO name="name".
// PHP se queda solo con el último, así que leemos el crudo y tomamos ambas ocurrencias en orden.
if ($first_name === '' || $last_name === '') {
  $dup = get_multi_values_from_raw('name'); // [Vorname, Nachname] si todo va bien
  if (count($dup) >= 2) {
    if ($first_name === '') $first_name = trim($dup[0]);
    if ($last_name  === '') $last_name  = trim($dup[1]);
  } elseif (count($dup) === 1 && $first_name === '' && $last_name === '') {
    // Si solo vino un "name", intenta dividir por el último espacio
    $full = trim($dup[0]);
    if (strpos($full, ' ') !== false) {
      $parts = preg_split('/\s+/', $full);
      $last  = array_pop($parts);
      $first = implode(' ', $parts);
      $first_name = trim($first);
      $last_name  = trim($last);
    } else {
      // No se puede dividir: guárdalo como first_name y deja last_name vacío (fallará validación)
      $first_name = $full;
    }
  }
}

// Resto de campos
$phone   = $pick('phone', 'telefon', 'telefonnummer');
$email   = $pick('email', 'e_mail', 'email_adresse');
$concern = $pick('concern', 'ihr_anliegen', 'anliegen');
$message = $pick('message', 'nachricht');
$privacy = (isset($_POST['privacy']) && (string)$_POST['privacy'] === '1') ? 1 : 0;

// --------------- Validaciones ---------------
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
  header('Location: ' . '/index.html?err=' . $msg . '#kontakt');
  exit;
}

// --------------- Insert ---------------
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

  error_log('CONTACT OK id=' . $pdo->lastInsertId() . ' email=' . $email);
  header('Location: /index.html?ok=1#kontakt');
  exit;

} catch (Throwable $e) {
  error_log('DB ERROR: ' . $e->getMessage());
  $msg = rawurlencode('DB-Fehler. Bitte später erneut versuchen.');
  header('Location: ' . '/index.html?err=' . $msg . '#kontakt');
  exit;
}
