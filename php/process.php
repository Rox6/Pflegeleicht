<?php
// Contact form processing script
// Enable error reporting for development (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load database connection
require __DIR__ . '/db.php';

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
$SMTP_USER = 'info@570875270.swh.strato-hosting.eu';
$SMTP_PASS = 'ProbandoProbando123!';  
$SMTP_PORT = 587;

$MAIL_FROM      = $SMTP_USER;
$MAIL_FROM_NAME = 'PflegeLeicht';
$MAIL_TO_ADMIN  = 'info@570875270.swh.strato-hosting.eu';

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
    <style>
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
        .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header { background: #0f3159; color: white; padding: 20px; text-align: center; }
        .header h2 { margin: 0; color: #D2691E; }
        .content { padding: 30px; }
        .contact-section { background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .contact-section h3 { color: #0f3159; margin-bottom: 15px; border-bottom: 2px solid #D2691E; padding-bottom: 5px; }
        .contact-detail { display: flex; margin: 10px 0; }
        .contact-label { font-weight: bold; min-width: 80px; color: #0f3159; }
        .contact-value { flex: 1; }
        .message-section { background: #fff; border-left: 4px solid #D2691E; padding: 20px; margin: 20px 0; }
        .message-section h3 { color: #0f3159; margin-bottom: 15px; }
        .message-text { background: #f9f9f9; padding: 15px; border-radius: 5px; font-style: italic; }
        .footer { background: #0f3159; color: white; padding: 15px; text-align: center; font-size: 14px; }
        .action-buttons { text-align: center; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-email { background: #D2691E; color: white; }
        .btn-phone { background: #2563eb; color: white; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <h2>Neue Kontaktanfrage</h2>
            <p>Ein neuer Kunde möchte Kontakt aufnehmen</p>
        </div>

        <div class='content'>
            <div class='contact-section'>
                <h3>👤 Kontaktdaten</h3>
                <div class='contact-detail'>
                    <span class='contact-label'>Name:</span>
                    <span class='contact-value'><strong>$anrede $first_name $last_name</strong></span>
                </div>
                <div class='contact-detail'>
                    <span class='contact-label'>E-Mail:</span>
                    <span class='contact-value'>$email</span>
                </div>
                <div class='contact-detail'>
                    <span class='contact-label'>Telefon:</span>
                    <span class='contact-value'>$phone</span>
                </div>
                <div class='contact-detail'>
                    <span class='contact-label'>Anliegen:</span>
                    <span class='contact-value'><strong>$concern</strong></span>
                </div>
            </div>

            <div class='message-section'>
                <h3>💬 Nachricht vom Kunden</h3>
                <div class='message-text'>$message</div>
            </div>

            <div class='action-buttons'>
                <a href='mailto:$email' class='btn btn-email'>📧 E-Mail antworten</a>
                <a href='tel:$phone' class='btn btn-phone'>📞 Anrufen</a>
            </div>

            <p><strong>⏰ Bitte setzen Sie sich zeitnah mit dem Kunden in Verbindung.</strong></p>
        </div>

        <div class='footer'>
            <p>PflegeLeicht - Automatische Benachrichtigung</p>
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
// Create mobile-optimized HTML version of user email
$bodyUserHtml = "
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='format-detection' content='telephone=no'>
    <style>
        /* Reset styles for email clients */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif !important;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #dddddd;
        }

        .header {
            background-color: #0f3159;
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            max-width: 150px;
            height: auto;
            display: block;
            margin: 0 auto 15px auto;
            border: 0;
        }

        .brand-text h2 {
            color: #D2691E !important;
            margin: 10px 0 5px 0;
            font-size: 24px;
            font-weight: bold;
        }

        .brand-text p {
            color: #ffffff;
            margin: 0;
            font-size: 14px;
        }

        .header-title {
            font-size: 20px;
            color: #D2691E !important;
            margin: 20px 0 0 0;
            font-weight: bold;
        }

        .content {
            padding: 30px 20px;
            background-color: #ffffff;
        }

        .greeting {
            font-size: 24px;
            color: #0f3159;
            margin-bottom: 20px;
            text-align: center;
            font-weight: normal;
        }

        .message-box {
            background-color: #f8fafc;
            padding: 20px;
            border-left: 4px solid #D2691E;
            margin: 20px 0;
            font-size: 16px;
        }

        .message-box p {
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .message-box p:last-child {
            margin-bottom: 0;
        }

        .signature {
            text-align: center;
            margin-top: 25px;
            font-style: italic;
            color: #666666;
            font-size: 16px;
        }

        .footer {
            background-color: #0f3159;
            color: #ffffff;
            padding: 25px 20px;
            text-align: center;
        }

        .company-name {
            color: #D2691E !important;
            margin-bottom: 15px;
            font-size: 20px;
            font-weight: bold;
        }

        .tagline {
            font-size: 16px;
            margin-bottom: 20px;
            color: #ffffff;
        }

        .divider {
            height: 2px;
            background-color: #D2691E;
            margin: 15px 0;
        }

        .contact-info {
            text-align: left;
            margin-top: 15px;
        }

        .contact-item {
            margin-bottom: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .contact-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .contact-icon {
            display: inline-block;
            width: 20px;
            color: #D2691E;
            font-size: 16px;
        }

        .contact-label {
            font-weight: bold;
            color: #D2691E;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .contact-value {
            color: #ffffff;
            font-size: 14px;
            margin-left: 25px;
        }

        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
            }

            .header {
                padding: 20px 15px !important;
            }

            .content {
                padding: 20px 15px !important;
            }

            .greeting {
                font-size: 20px !important;
            }

            .message-box {
                padding: 15px !important;
                font-size: 14px !important;
            }

            .footer {
                padding: 20px 15px !important;
            }

            .logo {
                max-width: 120px !important;
            }

            .brand-text h2 {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <!-- Company logo with fallback -->
            <img src='data:image/png;base64," . file_get_contents(__DIR__ . "/../statics/img/logo_base64.txt") . "' alt='PflegeLeicht Logo' class='logo'>

            <!-- Always visible brand text -->
            <div class='brand-text'>
                <h2>PflegeLeicht</h2>
                <p>Wir machen Pflege einfach</p>
            </div>

            <h1 class='header-title'>Bestätigung Ihrer Anfrage</h1>
        </div>

        <div class='content'>
            <h2 class='greeting'>Hallo {$anrede} {$last_name}! 👋</h2>

            <div class='message-box'>
                <p><strong>Vielen Dank für Ihre Anfrage!</strong></p>
                <p>Wir werden uns schnellstmöglich mit Ihnen in Verbindung setzen und Ihnen gerne bei Ihrem Anliegen behilflich sein.</p>
                <p>Unser Team steht Ihnen für alle Fragen rund um die Pflege zur Verfügung.</p>
            </div>

            <div class='signature'>
                Mit freundlichen Grüßen<br>
                <strong>Ihr PflegeLeicht Team</strong>
            </div>
        </div>

        <div class='footer'>
            <h3 class='company-name'>PflegeLeicht</h3>
            <p class='tagline'>Wir machen Pflege einfach</p>

            <div class='divider'></div>

            <div class='contact-info'>
                <div class='contact-item'>
                    <span class='contact-icon'>🌐</span>
                    <div class='contact-label'>Website</div>
                    <div class='contact-value'>www.pflegeleicht.team</div>
                </div>

                <div class='contact-item'>
                    <span class='contact-icon'>📧</span>
                    <div class='contact-label'>E-Mail</div>
                    <div class='contact-value'>info@pflegeleicht.team</div>
                </div>

                <div class='contact-item'>
                    <span class='contact-icon'>📞</span>
                    <div class='contact-label'>Telefon</div>
                    <div class='contact-value'>+49 (0) 6195 3044299</div>
                </div>

                <div class='contact-item'>
                    <span class='contact-icon'>📍</span>
                    <div class='contact-label'>Adresse</div>
                    <div class='contact-value'>Am Marktplatz 5<br>65779 Kelkheim (Taunus)</div>
                </div>
            </div>
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
    $MAIL_TO_ADMIN,'PflegeLeicht'
  );
}


// ================== FINAL REDIRECT ==================
header('Location: /index.html?ok=1#kontakt');
exit;
