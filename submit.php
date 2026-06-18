<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.pursuitbusiness.com.au');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$cfg = require __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['type'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$type = $data['type']; // 'contact' or 'newsletter'

// ── Sanitise ──────────────────────────────────────────────────────────────────
function clean($v) {
    return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
}

/**
 * Send an email via authenticated SMTP. Returns true on success.
 * $replyTo: optional [email, name] to set a Reply-To header.
 */
function sendMail(array $cfg, string $subject, string $body, ?array $replyTo = null): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = $cfg['smtp_secure']; // 'ssl' (465) or 'tls' (587)
        $mail->Port       = (int)$cfg['smtp_port'];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($cfg['to_email']);
        if ($replyTo && filter_var($replyTo[0], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo[0], $replyTo[1] ?? '');
        }

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('submit.php mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Contact form ──────────────────────────────────────────────────────────────
if ($type === 'contact') {
    $fname    = clean($data['fname']    ?? '');
    $lname    = clean($data['lname']    ?? '');
    $email    = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone    = clean($data['phone']    ?? '');
    $business = clean($data['business'] ?? '');
    $service  = clean($data['service']  ?? '');
    $message  = clean($data['message']  ?? '');

    if (!$fname || !$lname || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$service || !$message) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $subject = "New Enquiry: $fname $lname — $service";
    $body = "You have a new enquiry from your website.\n\n"
          . "Name:     $fname $lname\n"
          . "Email:    $email\n"
          . ($phone    ? "Phone:    $phone\n"    : '')
          . ($business ? "Business: $business\n" : '')
          . "Service:  $service\n\n"
          . "Message:\n$message\n";

    $sent = sendMail($cfg, $subject, $body, [$email, "$fname $lname"]);

    echo json_encode(['ok' => $sent]);
    exit;
}

// ── Newsletter form ───────────────────────────────────────────────────────────
if ($type === 'newsletter') {
    $name  = clean($data['name']  ?? '');
    $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $subject = "New Newsletter Signup: $name";
    $body = "New newsletter subscriber:\n\nName:  $name\nEmail: $email\n";

    $sent = sendMail($cfg, $subject, $body, [$email, $name]);

    echo json_encode(['ok' => $sent]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown type']);
