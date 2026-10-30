<?php
// marketplace/config/mailer.php
// Central email-sending helper using PHPMailer + SMTP

require_once __DIR__ . '/constants.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer (no composer)
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

/**
 * Send an HTML email via SMTP.
 *
 * @param string $to        Recipient email
 * @param string $subject   Email subject
 * @param string $htmlBody  HTML body
 * @param string $toName    Recipient name (optional)
 * @param string $altBody   Plain text fallback (optional)
 * @return array            ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $htmlBody, $toName = '', $altBody = '') {
    // Validate recipient
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid recipient email address'];
    }

    // Check config
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $user = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $pass = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $from = defined('SMTP_FROM') ? SMTP_FROM : $user;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('SITE_NAME') ? SITE_NAME : 'MarketPlace');

    if (empty($user) || empty($pass)) {
        // Fall back to logging only
        logEmail($to, $subject, 'SKIPPED - SMTP credentials not configured');
        return ['success' => false, 'message' => 'SMTP credentials not configured in .env'];
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP transport
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS on port 587
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        // From / To
        $mail->setFrom($from, $fromName);
        $mail->addReplyTo($from, $fromName);
        $mail->addAddress($to, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

        $mail->send();

        logEmail($to, $subject, 'SENT');
        return ['success' => true, 'message' => 'Email sent'];

    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        logEmail($to, $subject, 'FAILED - ' . $err);
        error_log('sendEmail error to ' . $to . ': ' . $err);
        return ['success' => false, 'message' => 'Failed: ' . $err];
    }
}

/**
 * Load an email template from email-templates/ and replace placeholders.
 * Placeholders are in the form {{NAME}}.
 */
function renderEmailTemplate($templateName, $vars = []) {
    $path = __DIR__ . '/../email-templates/' . $templateName . '.html';
    if (!file_exists($path)) {
        return '';
    }
    $html = file_get_contents($path);

    // Defaults
    $vars = array_merge([
        'SITE_NAME'   => defined('SITE_NAME') ? SITE_NAME : 'MarketPlace',
        'SITE_URL'    => defined('SITE_URL') ? SITE_URL : '',
        'YEAR'        => date('Y'),
        'CONTACT_LINK'=> (defined('SITE_URL') ? SITE_URL : '') . '/contact.php',
    ], $vars);

    foreach ($vars as $key => $value) {
        $html = str_replace('{{' . $key . '}}', $value, $html);
    }

    return $html;
}

/**
 * Log every email attempt to logs/email.log
 */
function logEmail($to, $subject, $status) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $line = date('Y-m-d H:i:s') . " | TO: {$to} | SUBJECT: {$subject} | {$status}\n";
    @file_put_contents($logDir . '/email.log', $line, FILE_APPEND);
}