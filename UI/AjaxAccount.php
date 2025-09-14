<?php
session_start();
require_once('../config.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Handle image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_image') {
    $userId = $_POST['user_id'];
    $isAdmin = $_POST['is_admin'] === '1';
    $extension = $_POST['extension'];

    // Set upload directory based on user type
    $uploadDir = $isAdmin ? '../UPLOADS/logos/' : '../UPLOADS/ProfilePictures/';

    // Remove the image
    $filePath = $uploadDir . $userId . '.' . $extension;
    if (file_exists($filePath) && unlink($filePath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to remove image']);
    }
    exit;
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $userId = $_POST['user_id'];
    $isAdmin = $_POST['is_admin'] === '1';

    // Validate file type (now also allowing SVG)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid file type. Only JPG, PNG, GIF and SVG are allowed.'
        ]);
        exit;
    }

    // Set upload directory based on user type
    $uploadDir = $isAdmin ? '../UPLOADS/logos/' : '../UPLOADS/ProfilePictures/';

    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Remove any existing images for this user (now including SVG)
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    foreach ($allowedExtensions as $ext) {
        $oldFile = $uploadDir . $userId . '.' . $ext;
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    // Save new image
    $newFileName = $uploadDir . $userId . '.' . $extension;
    if (move_uploaded_file($file['tmp_name'], $newFileName)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save image']);
    }
    exit;
}

// If we get here, the request wasn't handled
echo json_encode(['success' => false, 'error' => 'Invalid request']); 