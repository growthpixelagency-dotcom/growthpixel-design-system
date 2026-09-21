<?php
/**
 * contact.php — receives the website form submission and emails it.
 *
 * SETUP:
 *   1. Upload this file to the SAME folder as your website (the .html file),
 *      on hosting that supports PHP (standard cPanel / shared hosting does).
 *   2. Set $TO below to the inbox that should receive entries.
 *   3. Done — the forms already POST to "contact.php".
 *
 * No third-party service, no activation, no access key.
 */

header('Content-Type: application/json');

// ---- Where submissions are emailed ----
$TO = 'growthpixelagency@gmail.com';

// Only accept POST
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
$honey   = field($data, '_honey'); // hidden anti-spam field

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

// Build the email
$subject = 'New website enquiry - Dr. Jaydeep Dalvi';
$body  = "You have a new enquiry from the website:\n\n";
$body .= "Name:    {$name}\n";
$body .= "Phone:   {$phone}\n";
$body .= "Email:   " . ($email !== '' ? $email : '(not provided)') . "\n\n";
$body .= "Message:\n{$message}\n";

$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$headers  = "From: Website Enquiry <no-reply@{$host}>\r\n";
if ($email !== '') {
    $headers .= "Reply-To: {$name} <{$email}>\r\n";
}
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($TO, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mail server error. Please try again later.']);
}
