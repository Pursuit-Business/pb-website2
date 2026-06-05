<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://www.pursuitbusiness.com.au');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

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

$to      = 'hello@pursuitbusiness.com.au';
$from    = 'noreply@pursuitbusiness.com.au';
$leads   = __DIR__ . '/leads.csv';

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

    $headers = "From: PBS Website <$from>\r\n"
             . "Reply-To: $email\r\n"
             . "X-Mailer: PHP/" . phpversion();

    $sent = mail($to, $subject, $body, $headers);

    // Append to CSV log
    $csvRow = [
        date('Y-m-d H:i:s'),
        'contact',
        $fname . ' ' . $lname,
        $email,
        $phone,
        $business,
        $service,
        str_replace(["\r", "\n"], ' ', $message),
    ];
    $fp = fopen($leads, 'a');
    if ($fp) {
        if (filesize($leads) === 0) {
            fputcsv($fp, ['Timestamp', 'Type', 'Name', 'Email', 'Phone', 'Business', 'Service', 'Message']);
        }
        fputcsv($fp, $csvRow);
        fclose($fp);
    }

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

    $headers = "From: PBS Website <$from>\r\n"
             . "X-Mailer: PHP/" . phpversion();

    $sent = mail($to, $subject, $body, $headers);

    $csvRow = [date('Y-m-d H:i:s'), 'newsletter', $name, $email, '', '', '', ''];
    $fp = fopen($leads, 'a');
    if ($fp) {
        if (filesize($leads) === 0) {
            fputcsv($fp, ['Timestamp', 'Type', 'Name', 'Email', 'Phone', 'Business', 'Service', 'Message']);
        }
        fputcsv($fp, $csvRow);
        fclose($fp);
    }

    echo json_encode(['ok' => $sent]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown type']);
