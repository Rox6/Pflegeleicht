<?php
// Contact form processing script
// Enable error reporting for development (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load database connection
require __DIR__ . '/db.php';
$cfg = require __DIR__ . '/config.enc.php';

// Only allow POST requests
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

// Honeypot spam protection
$company = trim($_POST['company'] ?? '');
if ($company !== '') {
  header('Location: /index.html?ok=1#kontakt');
  exit;
}

// Helper functions
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

// Extract form fields
$anrede     = $pick('anrede');
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

// Form validation
$errors = [];
if ($anrede     === '') $errors[] = 'Anrede ist erforderlich.';
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

// Insert into database
try {
  $sql = "INSERT INTO contact_requests
          (anrede, first_name, last_name, phone, email, concern, message, privacy)
          VALUES (:anrede, :first_name, :last_name, :phone, :email, :concern, :message, :privacy)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':anrede'     => $anrede,
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

// ================== EMAIL PROCESSING ==================
require __DIR__ . '/PHPMailer.php';
require __DIR__ . '/SMTP.php';
require __DIR__ . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$SMTP_HOST = 'smtp.strato.de';
$SMTP_USER = 'info@570903987.swh.strato-hosting.eu';
$SMTP_PASS = $cfg['SMTP_PASS'];  
$SMTP_PORT = 587;

$MAIL_FROM      = $SMTP_USER;
$MAIL_FROM_NAME = 'PflegeLeicht';
$MAIL_TO_ADMIN  = 'info@570903987.swh.strato-hosting.eu';

// Logo-URL 
$logoURL = 'http://570903987.swh.strato-hosting.eu/statics/img/logo.png';

// Email content templates
$subjectAdmin = 'Neue Kontaktanfrage von ' . $anrede . ' ' . $last_name;

$bodyAdminTxt = "Guten Tag,\n\nSie haben eine neue Kontaktanfrage über Ihre Website erhalten.\n\n" .
"KONTAKTDATEN:\n" .
"Name: $anrede $first_name $last_name\n" .
"E-Mail: $email\n" .
"Telefon: $phone\n" .
"Anliegen: $concern\n\n" .
"NACHRICHT:\n$message\n\n" .
"Bitte setzen Sie sich zeitnah mit dem Kunden in Verbindung.\n\n" .
"Mit freundlichen Grüßen\nIhr PflegeLeicht System";

// Create professional HTML version for admin email
$bodyAdminHtml = "
<html>
<head>
    <meta charset='UTF-8'>
</head>
<body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
    <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>

        <!-- Header with Logo -->
        <div style='background-color: #ffffff; padding: 30px; text-align: center; border-bottom: 3px solid #e0e0e0;'>
            <img src='{$logoURL}' alt='Logo' style='max-width:200px;height:auto;'>
        </div>

        <!-- Alert Banner -->
        <div style='background-color: #4a90e2; color: white; padding: 20px; text-align: center;'>
            <h2 style='margin: 0; font-size: 22px;'>Neue Kontaktanfrage</h2>
            <p style='margin: 5px 0 0 0; font-size: 14px;'>Ein neuer Kunde möchte Kontakt aufnehmen</p>
        </div>

        <!-- Content -->
        <div style='padding: 30px;'>
            <!-- Contact Details Section -->
            <div style='background: #f9f9f9; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #4a90e2;'>
                <h3 style='color: #333333; margin: 0 0 15px 0; font-size: 18px;'>Kontaktdaten</h3>
                <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                    Name: $anrede $first_name $last_name
                </p>
                <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                    E-Mail: <a href='mailto:$email' style='color: #4a90e2; text-decoration: none;'>$email</a>
                </p>
                <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                    Telefon: <a href='tel:$phone' style='color: #4a90e2; text-decoration: none;'>$phone</a>
                </p>
                <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                    Anliegen: $concern
                </p>
            </div>

            <!-- Message Section -->
            <div style='background: #ffffff; border-left: 4px solid #4a90e2; padding: 20px; margin: 20px 0; border: 1px solid #e0e0e0; border-radius: 6px;'>
                <h3 style='color: #333333; margin: 0 0 15px 0; font-size: 18px;'>Nachricht vom Kunden</h3>
                <div style='background: #f9f9f9; padding: 15px; border-radius: 4px; color: #555555; line-height: 1.6;'>$message</div>
            </div>

            <!-- Action Buttons -->
            <div style='text-align: center; margin: 30px 0;'>
                <a href='mailto:$email' style='display: inline-block; padding: 12px 24px; margin: 5px; background-color: #4a90e2; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>E-Mail antworten</a>
                <a href='tel:$phone' style='display: inline-block; padding: 12px 24px; margin: 5px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Anrufen</a>
            </div>

            <div style='background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 6px; margin-top: 20px;'>
                <p style='margin: 0; color: #856404; text-align: center;'>⏰ Bitte setzen Sie sich zeitnah mit dem Kunden in Verbindung.</p>
            </div>
        </div>

        <!-- Footer -->
        <div style='background-color: #f7f7f7; color: #999999; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0; font-size: 12px;'>
            <p style='margin: 0;'>PflegeLeicht - Automatische Benachrichtigung</p>
            <p style='margin: 10px 0 0 0;'>© 2025 PflegeLeicht GmbH. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>
";

$subjectUser = 'Ihre Anfrage wurde erhalten';
$bodyUserTxt = "Hallo {$anrede} {$last_name},\n\nvielen Dank für Ihre Anfrage, wir werden uns schnellstmöglich mit Ihnen in Verbindung setzen.\n\nMit freundlichen Grüßen\n\nPflegeLeicht - Wir machen Pflege einfach\n\n" .
"🌐 www.pflegeleicht.team\n" .
"📧 info@pflegeleicht.team\n" .
"📞 +49 (0) 6195 3044299\n" .
"📍 Am Marktplatz 5, 65779 Kelkheim (Taunus), Deutschland\n";
// Create simple HTML email version
$bodyUserHtml = "
<html>
<head>
    <meta charset='UTF-8'>
</head>
<body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>

    <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>

        <!-- Header with Logo -->
        <div style='background-color: #ffffff; padding: 30px; text-align: center; border-bottom: 3px solid #e0e0e0;'>
            <img src='{$logoURL}' alt='Logo' style='max-width:200px;height:auto;'>
        </div>

        <!-- Content -->
        <div style='padding: 40px 30px;'>
            <h2 style='color: #333333; text-align: center; margin-bottom: 10px; font-size: 24px;'>Bestätigung Ihrer Anfrage</h2>

            <p style='color: #666666; text-align: center; margin-bottom: 30px; font-size: 16px;'>Hallo {$anrede} {$last_name},</p>

            <div style='background-color: #f9f9f9; padding: 25px; border-radius: 6px; margin: 25px 0; border-left: 4px solid #4a90e2;'>
                <p style='color: #333333; margin: 0 0 15px 0; line-height: 1.6;'><strong>Vielen Dank für Ihre Anfrage!</strong></p>
                <p style='color: #555555; margin: 0; line-height: 1.6;'>Wir haben Ihre Nachricht erhalten und werden uns schnellstmöglich mit Ihnen in Verbindung setzen. Gerne stehen wir Ihnen für alle Fragen rund um die Pflege zur Verfügung.</p>
            </div>

            <div style='text-align: center; margin-top: 30px; color: #666666;'>
                <p style='margin: 5px 0;'>Mit freundlichen Grüßen</p>
                <p style='margin: 5px 0;'><strong>Ihr PflegeLeicht Team</strong></p>
            </div>
        </div>

        <!-- Footer with contact info -->
        <div style='background-color: #f7f7f7; color: #555555; padding: 30px; border-top: 1px solid #e0e0e0; text-align: center;'>
            <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                Website: www.pflegeleicht.team
            </p>
            <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                E-Mail: info@pflegeleicht.team
            </p>
            <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                Telefon: +49 (0) 6195 3044299
            </p>
            <p style='margin: 10px 0; color: #555555; font-size: 14px; line-height: 1.8;'>
                Adresse: Am Marktplatz 5, 65779 Kelkheim (Taunus), Deutschland
            </p>
            <p style='margin: 20px 0 0 0; color: #999999; font-size: 12px;'>
                © 2025 PflegeLeicht GmbH. Alle Rechte vorbehalten.
            </p>
        </div>
    </div>
</body>
</html>
";

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
    $m->SMTPDebug  = 0; // Important: disable debug output in production

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

// Send notification to admin
sendMailStrato($SMTP_HOST,$SMTP_PORT,$SMTP_USER,$SMTP_PASS,
  $MAIL_FROM,$MAIL_FROM_NAME,
  $MAIL_TO_ADMIN,'Admin',
  $subjectAdmin,$bodyAdminHtml,$bodyAdminTxt,
  $email,$first_name.' '.$last_name
);

// Send confirmation to user
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
  sendMailStrato($SMTP_HOST,$SMTP_PORT,$SMTP_USER,$SMTP_PASS,
    $MAIL_FROM,$MAIL_FROM_NAME,
    $email,$first_name.' '.$last_name,
    $subjectUser,$bodyUserHtml,$bodyUserTxt,
    'info@570903987.swh.strato-hosting.eu', 'PflegeLeicht'
  );
}


// ================== FINAL REDIRECT ==================
header('Location: /index.html?ok=1#kontakt');
exit;