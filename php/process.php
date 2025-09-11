<?php
// DEBUG visible mientras probamos
ini_set('display_errors', 1);
error_reporting(E_ALL);


error_log("HIT process.php method=" . ($_SERVER['REQUEST_METHOD'] ?? ''));

require __DIR__ . '/db.php'; 


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}


if (!empty($_POST['company'] ?? '')) {
  header('Location: /index.html?ok=1#kontakt'); exit;
}

// Campos del formulario
$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$email   = trim($_POST['email']   ?? '');
$concern = trim($_POST['concern'] ?? '');
$message = trim($_POST['message'] ?? '');
$privacy = isset($_POST['privacy']) ? 1 : 0;

// Validaciones mínimas
if ($name === '' || $phone === '' || $email === '' || $concern === '') {
  header('Location: /index.html?err=' . rawurlencode('Bitte alle Pflichtfelder ausfüllen.') . '#kontakt'); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: /index.html?err=' . rawurlencode('Ungültige E-Mail-Adresse.') . '#kontakt'); exit;
}

// Insertar
try {
  $sql = "INSERT INTO contact_requests (name, phone, email, concern, message, privacy)
          VALUES (:name, :phone, :email, :concern, :message, :privacy)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':name'    => $name,
    ':phone'   => $phone,
    ':email'   => $email,
    ':concern' => $concern,
    ':message' => $message,
    ':privacy' => $privacy,
  ]);
  error_log("INSERT OK id=" . $pdo->lastInsertId());
} catch (Throwable $e) {
  // Muestra motivo en la URL y en la consola
  error_log("DB ERROR: " . $e->getMessage());
  header('Location: /index.html?err=' . rawurlencode('DB-Fehler: ' . $e->getMessage()) . '#kontakt'); exit;
}

// Éxito → vuelve con bandera ok
header('Location: /index.html?ok=1#kontakt');
exit;
