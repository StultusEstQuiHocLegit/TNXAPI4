<?php
session_start();
require_once('../config.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$uid = $input['uid'] ?? null;
$targetState = $input['targetState'] ?? null;

if (!$uid || !in_array($targetState, ['read', 'unread'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Fetch your IMAP connection info from session
$ConnectEmail = $_SESSION['ConnectEmail'] ?? '';
$ConnectPassword = $_SESSION['ConnectPassword'] ?? '';
$ConnectServerName = $_SESSION['ConnectServerName'] ?? '';
$ConnectPort = $_SESSION['ConnectPort'] ?? 993;
$ConnectEncryption = $_SESSION['ConnectEncryption'] ?? 'ssl';
$ConnectEncryption = strtolower($ConnectEncryption);
if ($ConnectEncryption === 'tls' && $ConnectPort == 993) {
    $ConnectEncryption = 'ssl';
}
if (!in_array($ConnectEncryption, ['ssl', 'tls'])) {
    $ConnectEncryption = 'ssl';
}

$imapServerString = "{" . $ConnectServerName . ":" . $ConnectPort . "/imap/" . strtolower($ConnectEncryption) . "}INBOX";

// Open IMAP connection
$inbox = @imap_open($imapServerString, $ConnectEmail, $ConnectPassword);

if (!$inbox) {
    echo json_encode(['success' => false, 'error' => 'Failed to connect to mailbox']);
    exit;
}

if ($targetState === 'read') {
    $result = imap_setflag_full($inbox, $uid, "\\Seen", ST_UID);
} else {
    $result = imap_clearflag_full($inbox, $uid, "\\Seen", ST_UID);
}

imap_close($inbox);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update flag']);
}