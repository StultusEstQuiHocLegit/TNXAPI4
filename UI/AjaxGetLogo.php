<?php
session_start();
$uploadDir = $_SESSION['IsAdmin'] ? '../UPLOADS/logos/' : '../UPLOADS/ProfilePictures/';
$IdpkOfAdminForLogoRetrieval = $_SESSION['IdpkOfAdmin'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

foreach ($allowedExtensions as $ext) {
    $imagePath = $uploadDir . $IdpkOfAdminForLogoRetrieval . '.' . $ext;
    if (file_exists($imagePath)) {
        $mime = mime_content_type($imagePath);
        $base64 = base64_encode(file_get_contents($imagePath));
        echo 'data:' . $mime . ';base64,' . $base64;
        exit;
    }
}
http_response_code(404);
?>