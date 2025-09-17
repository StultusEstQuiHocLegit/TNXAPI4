<?php
session_start();
require_once('../config.php');

// Only attempt to clear a token if we know who is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    // Dummy values to overwrite the token fields
    $dummyToken  = '';
    $dummyExpiry = '2000-01-01 00:00:00';

    if ($_SESSION['user_role'] === 'admin') {
        // Logged-in as admin → clear admins table
        $stmt = $pdo->prepare("
            UPDATE admins
            SET LoginToken = ?, LoginTokenExpiry = ?
            WHERE idpk = ?
        ");
        $stmt->execute([
            $dummyToken,
            $dummyExpiry,
            $_SESSION['user_id']
        ]);

    } else {
        // Logged-in as normal user → clear users table
        $stmt = $pdo->prepare("
            UPDATE users
            SET LoginToken = ?, LoginTokenExpiry = ?
            WHERE idpk = ?
        ");
        $stmt->execute([
            $dummyToken,
            $dummyExpiry,
            $_SESSION['user_id']
        ]);
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Expire the same cookie name you set at login ("LoginToken")
setcookie(
    'LoginToken',   // must match the cookie name used on login
    '',
    time() - 3600,  // set expiration in the past
    '/',            // path
    '',             // domain (empty = current)
    false,          // secure flag (set to true if using HTTPS)
    true            // HttpOnly
);

header('Location: login.php');
exit();

?>

