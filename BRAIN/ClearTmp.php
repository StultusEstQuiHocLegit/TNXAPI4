<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['IdpkOfAdmin'])) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}
$user_id  = $_SESSION['user_id'];
$admin_id = $_SESSION['IdpkOfAdmin'];
$dir = __DIR__ . '/tmp/' . $admin_id . '_' . $user_id;
if (is_dir($dir)) {
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($dir);
}
echo json_encode(['status' => 'ok']);
