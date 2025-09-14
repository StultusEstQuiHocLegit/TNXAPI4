<?php
session_start();
require_once('../config.php');
require_once('../SETUP/DatabaseTablesStructure.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';

$table  = $_POST['table']  ?? '';
$idpk   = $_POST['idpk']   ?? '';
$column = $_POST['column'] ?? '';
$value  = $_POST['value']  ?? '';

if ($table === '' || $idpk === '' || $column === '') {
    echo json_encode(['success' => false, 'error' => 'missing parameters']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !isset($DatabaseTablesStructure[$table])) {
    echo json_encode(['success' => false, 'error' => 'invalid table']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $column) || !isset($DatabaseTablesStructure[$table][$column])) {
    echo json_encode(['success' => false, 'error' => 'invalid column']);
    exit;
}

if (!is_numeric($idpk)) {
    echo json_encode(['success' => false, 'error' => 'invalid idpk']);
    exit;
}

function checkEntryAccess($pdo, $table, $idpk) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
    if (!is_numeric($idpk)) return false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
        $stmt->bindParam(':idpk', $idpk, PDO::PARAM_INT);
        $stmt->bindParam(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Database error in checkEntryAccess: ' . $e->getMessage());
        return false;
    }
}

if (!checkEntryAccess($pdo, $table, $idpk)) {
    echo json_encode(['success' => false, 'error' => 'access denied']);
    exit;
}

$fieldInfo = $DatabaseTablesStructure[$table][$column] ?? [];
$dbType    = strtoupper($fieldInfo['DBType'] ?? '');

function sanitizeValue($value, $dbType) {
    $dbType = strtoupper($dbType);
    if (strpos($dbType, 'INT') !== false) {
        if (preg_match('/-?\d+/', $value, $m)) {
            return (int)$m[0];
        }
        return 0;
    }
    if (strpos($dbType, 'DECIMAL') !== false || strpos($dbType, 'NUMERIC') !== false || strpos($dbType, 'FLOAT') !== false || strpos($dbType, 'DOUBLE') !== false) {
        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $m)) {
            return (float)$m[0];
        }
        return 0;
    }
    if (strpos($dbType, 'DATE') !== false || strpos($dbType, 'TIME') !== false) {
        $dt = date_create($value);
        if ($dt) {
            if (strpos($dbType, 'DATE') !== false && strpos($dbType, 'TIME') !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
            if (strpos($dbType, 'DATE') !== false) {
                return $dt->format('Y-m-d');
            }
            return $dt->format('H:i:s');
        }
        return null;
    }
    return trim($value);
}

$sanitizedValue = sanitizeValue($value, $dbType);

$params = [
    ':val'       => $sanitizedValue,
    ':idpk'      => $idpk,
    ':adminIdpk' => $_SESSION['IdpkOfAdmin'] ?? 0,
];

$query = "UPDATE `$table` SET `$column` = :val WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk";

$payload = [
    'APIKey' => $TRAMANNAPIAPIKey,
    'message' => '$stmt = $pdo->prepare(' . json_encode($query) . ');'
               . ' $stmt->execute(' . var_export($params, true) . ');'
               . ' echo json_encode(["success" => true]);'
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
curl_close($ch);

$responseData = json_decode($response, true);
if (isset($responseData['status']) && $responseData['status'] === 'success') {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $responseData['message'] ?? 'Unknown error']);
}
