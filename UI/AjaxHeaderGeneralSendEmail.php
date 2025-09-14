<?php
session_start();
require_once('../config.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Read data depending on request type
if (!empty($_FILES['attachments'])) {
    $data = json_decode($_POST['mailData'] ?? '{}', true);
} else {
    $data = json_decode(file_get_contents('php://input'), true);
}

$ConnectEmail = $_SESSION['ConnectEmail'] ?? '';
$ConnectPassword = $_SESSION['ConnectPassword'] ?? '';
$ConnectServerName = $_SESSION['ConnectServerName'] ?? '';
$ConnectPort = $_SESSION['ConnectPort'] ?? '';
$ConnectEncryption = $_SESSION['ConnectEncryption'] ?? '';

if (!$ConnectEmail) {
    echo json_encode(['success' => false, 'error' => 'Missing sender email']);
    exit;
}

$to = $data['to'] ?? '';
$subject = $data['subject'] ?? '';
$body = $data['body'] ?? '';
$cc = $data['cc'] ?? '';
$bcc = $data['bcc'] ?? '';

$attachments = [];
if (!empty($_FILES['attachments'])) {
    $files = $_FILES['attachments'];
    if (is_array($files['tmp_name'])) {
        for ($i = 0, $n = count($files['tmp_name']); $i < $n; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $attachments[] = [
                    'name' => basename($files['name'][$i]),
                    'type' => $files['type'][$i] ?: 'application/octet-stream',
                    'content' => file_get_contents($files['tmp_name'][$i])
                ];
            }
        }
    } elseif ($files['error'] === UPLOAD_ERR_OK) {
        $attachments[] = [
            'name' => basename($files['name']),
            'type' => $files['type'] ?: 'application/octet-stream',
            'content' => file_get_contents($files['tmp_name'])
        ];
    }
}

// Basic validation
if (!$to) {
    echo json_encode(['success' => false, 'error' => 'Recipient email required']);
    exit;
}

// Ensure server mailer is available before attempting to send
$sendmailPath = ini_get('sendmail_path');
if ($sendmailPath && !file_exists(strtok($sendmailPath, ' '))) {
    echo json_encode(['success' => false, 'error' => 'Server mailer not configured']);
    exit;
}

// Prepare headers
$headers = "From: $ConnectEmail\r\n";

if ($cc) {
    $headers .= "Cc: $cc\r\n";
}
if ($bcc) {
    $headers .= "Bcc: $bcc\r\n";
}
$headers .= "MIME-Version: 1.0\r\n";

$messageBody = '';
if (!empty($attachments)) {
    $boundary = '==Multipart_Boundary_x' . md5(time()) . 'x';
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $messageBody .= "--$boundary\r\n";
    $messageBody .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $messageBody .= $body . "\r\n";

    foreach ($attachments as $att) {
        $messageBody .= "--$boundary\r\n";
        $messageBody .= "Content-Type: {$att['type']}; name=\"{$att['name']}\"\r\n";
        $messageBody .= "Content-Transfer-Encoding: base64\r\n";
        $messageBody .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n\r\n";
        $messageBody .= chunk_split(base64_encode($att['content'])) . "\r\n";
    }
    $messageBody .= "--$boundary--";
} else {
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $messageBody = $body;
}

// Helper: send a copy to the sender and try to mark it as read
function sendCopyToSelf($email, $subject, $messageBody, $headers, $server, $port, $encryption, $password, $originalTo, $originalCc) {
    $copySubject = 'YOU SENT: ' . $subject;
    $copyHeaders = preg_replace('/^Cc:.*\r\n/m', '', $headers);
    $copyHeaders = preg_replace('/^Bcc:.*\r\n/m', '', $copyHeaders);
    $cleanTo = str_replace(["\r", "\n"], '', $originalTo);
    $cleanCc = str_replace(["\r", "\n"], '', $originalCc);
    if ($cleanTo) {
        $copyHeaders .= "X-Original-To: $cleanTo\r\n";
    }
    if ($cleanCc) {
        $copyHeaders .= "X-Original-Cc: $cleanCc\r\n";
    }
    $sent = @mail($email, $copySubject, $messageBody, $copyHeaders, '-f' . escapeshellarg($email));
    if ($sent && $password && $server && $port) {
        $baseMailbox = "{" . $server . ":" . $port . "/imap/" . strtolower($encryption) . "}";
        $imapInbox = @imap_open($baseMailbox . 'INBOX', $email, $password);
        if ($imapInbox) {
            $escapedSubject = addcslashes($copySubject, "\\\"");
            for ($i = 0; $i < 3; $i++) {
                $emails = @imap_search($imapInbox, 'UNSEEN SUBJECT "' . $escapedSubject . '" FROM "' . $email . '"', SE_UID);
                if ($emails) {
                    @imap_setflag_full($imapInbox, implode(',', $emails), '\Seen', ST_UID);
                    break;
                }
                sleep(1);
            }
            imap_close($imapInbox);
        }
    }
    return $sent;
}

// Send mail using PHP's mail() with explicit envelope sender
// Adding the fifth parameter ensures the sender is correctly set,
// which many servers require in order to accept the message.
// Track whether we managed to store the mail in the user's mailbox in any way
$savedToSent = false;
$copiedToSelf = false;
if (mail($to, $subject, $messageBody, $headers, '-f' . escapeshellarg($ConnectEmail))) {
    if ($ConnectPassword && $ConnectServerName && $ConnectPort) {
        $baseMailbox = "{" . $ConnectServerName . ":" . $ConnectPort . "/imap/" . strtolower($ConnectEncryption) . "}";
        $imap = @imap_open($baseMailbox . 'INBOX', $ConnectEmail, $ConnectPassword);
        if ($imap) {
            $sentMailbox = $baseMailbox . 'SENT';
            $mailboxes = @imap_getmailboxes($imap, $baseMailbox, '*');
            if ($mailboxes) {
                foreach ($mailboxes as $mbox) {
                    $decoded = imap_utf7_decode($mbox->name);
                    $parts = explode('}', $decoded);
                    $shortName = end($parts);
                    if (preg_match('/sent/i', $shortName)) {
                        $sentMailbox = $mbox->name;
                        break;
                    }
                }
            }
            $rawMessage = "To: $to\r\nSubject: $subject\r\n" . $headers . "\r\n" . $messageBody;
            if (@imap_append($imap, $sentMailbox, $rawMessage)) {
                $savedToSent = true;
            }
            imap_close($imap);
        }
    }
    if (
        !$savedToSent &&
        strcasecmp(trim($to), trim($ConnectEmail)) !== 0
    ) {
        $copiedToSelf = sendCopyToSelf(
            $ConnectEmail,
            $subject,
            $messageBody,
            $headers,
            $ConnectServerName,
            $ConnectPort,
            $ConnectEncryption,
            $ConnectPassword,
            $to,
            $cc
        );
    }
    echo json_encode(['success' => true, 'savedToSent' => $savedToSent, 'copiedToSelf' => $copiedToSelf]);
} else {
    $error = error_get_last()['message'] ?? '';
    if (!$error) {
        $error = 'mail() failed';
        if ($sendmailPath) {
            $error .= " (sendmail_path: $sendmailPath)";
        }
    }
    echo json_encode(['success' => false, 'error' => $error]);
}
