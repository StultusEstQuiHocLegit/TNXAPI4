<?php
session_start();
require_once('../config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';

function checkEntryAccess($pdo, $table, $idpk) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    if (!is_numeric($idpk)) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE idpk = :idpk AND IdpkOfAdmin = :admin");
        $stmt->bindParam(':idpk', $idpk, PDO::PARAM_INT);
        $stmt->bindParam(':admin', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Database error in checkEntryAccess: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $table = $_POST['table'] ?? '';
    $idpk  = $_POST['idpk'] ?? '';
    $file  = $_POST['file'] ?? '';
    $content = $_POST['content'] ?? '';

    if (!checkEntryAccess($pdo, $table, $idpk)) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    if (!preg_match('/^' . preg_quote($idpk, '/') . '_\d+\.[a-zA-Z0-9]+$/', $file)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file name']);
        exit;
    }

    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "
            \$uploadDir = './UPLOADS/$table/';
            if (!file_exists(\$uploadDir)) { mkdir(\$uploadDir, 0777, true); }
            \$filePath = \$uploadDir . " . json_encode($file) . ";
            \$data = base64_decode(" . json_encode(base64_encode($content)) . ");
            if (file_put_contents(\$filePath, \$data) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'write failed']);
            }
        "
    ];

    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        $msg = is_string($responseData['message']) ? json_decode($responseData['message'], true) : $responseData['message'];
        if (isset($msg['success']) && $msg['success']) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $msg['error'] ?? 'remote error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $curlError ?: 'remote failure']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'invalid request']);
