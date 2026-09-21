<?php
/**
 * contact.php — receives the website form submission and emails it via SMTP.
 *   • Reliable Gmail/inbox delivery (authenticated SMTP, not PHP mail()).
 *   • Optional Google reCAPTCHA v3 verification (spam protection).
 *   • Self-contained — NO PHPMailer or other library to upload.
 *
 * ┌─ SETUP ───────────────────────────────────────────────────────────────┐
 * │ 1) Fill the SMTP block below (Gmail App Password recommended).          │
 * │ 2) (Optional) Fill the reCAPTCHA block to enable spam protection.       │
 * │ 3) Upload next to index.html in public_html. Done.                      │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 *  GMAIL (recommended):
 *   - Turn ON 2-Step Verification on the Gmail account.
 *   - Google Account → Security → App passwords → Mail → Generate.
 *   - Put that 16-char password in SMTP_PASS.
 *
 *  HOSTINGER MAILBOX (matches your domain, cleanest SPF):
 *   - hPanel → Emails → create info@yourdomain.com
 *   - SMTP_HOST=smtp.hostinger.com, SMTP_PORT=465, SMTP_SECURE=ssl
 */

// ════════════════════════════ SMTP CONFIG ═══════════════════════════════
$TO       = 'growthpixelagency@gmail.com';   // where enquiries are delivered
$TO_NAME  = 'Dr. Jaydeep Dalvi';

$SMTP_HOST   = 'smtp.gmail.com';   // gmail: smtp.gmail.com | hostinger: smtp.hostinger.com
$SMTP_PORT   = 587;                // gmail: 587 (tls) or 465 (ssl) | hostinger: 465 (ssl)
$SMTP_SECURE = 'tls';              // 'tls' for 587, 'ssl' for 465
$SMTP_USER   = 'YOUR_EMAIL@gmail.com';        // ← CHANGE
$SMTP_PASS   = 'YOUR_16_CHAR_APP_PASSWORD';   // ← CHANGE

$FROM_EMAIL  = $SMTP_USER;         // Gmail rewrites From to the authenticated user anyway
$FROM_NAME   = 'Website Enquiry';

// ════════════════════════ reCAPTCHA (optional) ══════════════════════════
// Leave RECAPTCHA_SECRET empty to DISABLE. To enable Google reCAPTCHA v3:
//   1) https://www.google.com/recaptcha/admin → register your domain (v3).
//   2) Put the SECRET key here, and the SITE key in index.html:
//        <script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>
//        <script>window.RECAPTCHA_SITE_KEY='YOUR_SITE_KEY';</script>
//      (add both just before </body>, or in the page <head>).
$RECAPTCHA_SECRET     = '';    // ← paste v3 SECRET key to turn on
$RECAPTCHA_MIN_SCORE  = 0.5;   // v3 score threshold (0.0 bot … 1.0 human)
// ═════════════════════════════════════════════════════════════════════════

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Accept JSON (what the site sends) or normal form-encoded data
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

function field($d, $k) { return isset($d[$k]) ? trim((string) $d[$k]) : ''; }

$name    = field($data, 'Name');
$phone   = field($data, 'Phone');
$email   = field($data, 'Email');
$message = field($data, 'Message');
$honey   = field($data, '_honey'); // optional hidden anti-spam field

// Silent spam trap
if ($honey !== '') { echo json_encode(['success' => true]); exit; }

// Validation
if ($name === '' || $phone === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// ── reCAPTCHA verification (only if a secret is configured) ──────────────
if ($RECAPTCHA_SECRET !== '') {
    $token = field($data, 'g-recaptcha-response');
    if ($token === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Captcha missing. Please retry.']);
        exit;
    }
    $verify = @file_get_contents('https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
        'secret'   => $RECAPTCHA_SECRET,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]));
    $vr = $verify ? json_decode($verify, true) : null;
    $ok = is_array($vr) && !empty($vr['success']);
    if ($ok && isset($vr['score'])) { $ok = ($vr['score'] >= $RECAPTCHA_MIN_SCORE); } // v3 score gate
    if (!$ok) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Captcha failed. Please try again.']);
        exit;
    }
}

// ── Build + send ─────────────────────────────────────────────────────────
$subject = 'New website enquiry - Dr. Jaydeep Dalvi';
$body  = "You have a new enquiry from the website:\n\n";
$body .= "Name:    {$name}\n";
$body .= "Phone:   {$phone}\n";
$body .= "Email:   " . ($email !== '' ? $email : '(not provided)') . "\n\n";
$body .= "Message:\n{$message}\n";

$replyTo = ($email !== '') ? ['email' => $email, 'name' => $name] : null;

try {
    smtp_send(
        $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USER, $SMTP_PASS,
        ['email' => $FROM_EMAIL, 'name' => $FROM_NAME],
        ['email' => $TO,         'name' => $TO_NAME],
        $subject, $body, $replyTo
    );
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('contact.php SMTP error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Mail server error. Please try again later.']);
}

// ───────────────────────── Minimal SMTP client ──────────────────────────
// Plain PHP sockets. STARTTLS (587/tls) or implicit SSL (465/ssl), AUTH LOGIN.
function smtp_send($host, $port, $secure, $user, $pass, $from, $to, $subject, $body, $replyTo = null) {
    $secure = strtolower($secure);
    $remote = ($secure === 'ssl') ? "ssl://{$host}:{$port}" : "{$host}:{$port}";
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
    ]]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { throw new Exception("Connect failed: {$errstr} ({$errno})"); }
    stream_set_timeout($fp, 20);

    $expect = function ($codes) use ($fp) {
        $codes = (array) $codes;
        $line = ''; $dataAcc = '';
        do {
            $line = fgets($fp, 515);
            if ($line === false) { throw new Exception('SMTP read timeout/closed'); }
            $dataAcc .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($dataAcc, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new Exception('Unexpected SMTP reply: ' . trim($dataAcc));
        }
        return $dataAcc;
    };
    $send = function ($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

    $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $expect(220);
    $send('EHLO ' . $hostName); $expect(250);

    if ($secure === 'tls') {
        $send('STARTTLS'); $expect(220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            throw new Exception('STARTTLS negotiation failed');
        }
        $send('EHLO ' . $hostName); $expect(250);
    }

    $send('AUTH LOGIN'); $expect(334);
    $send(base64_encode($user)); $expect(334);
    $send(base64_encode($pass)); $expect(235);

    $send('MAIL FROM:<' . $from['email'] . '>'); $expect(250);
    $send('RCPT TO:<' . $to['email'] . '>');      $expect([250, 251]);
    $send('DATA'); $expect(354);

    $enc = function ($t) { return '=?UTF-8?B?' . base64_encode($t) . '?='; };
    $headers  = 'From: ' . $enc($from['name']) . ' <' . $from['email'] . ">\r\n";
    $headers .= 'To: ' . $enc($to['name']) . ' <' . $to['email'] . ">\r\n";
    if ($replyTo) {
        $headers .= 'Reply-To: ' . $enc($replyTo['name']) . ' <' . $replyTo['email'] . ">\r\n";
    }
    $headers .= 'Subject: ' . $enc($subject) . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";

    $payload = $headers . "\r\n" . chunk_split(base64_encode($body));
    fwrite($fp, $payload . "\r\n.\r\n");
    $expect(250);

    $send('QUIT');
    fclose($fp);
    return true;
}
