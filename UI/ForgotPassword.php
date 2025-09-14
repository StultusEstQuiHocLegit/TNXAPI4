<?php
require_once('../config.php');
require_once('header.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if ($email) {
        // 1) See if email exists in admins
        $stmt = $pdo->prepare("SELECT idpk FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminRow) {
            // Found in admins
            $accountType   = 'admin';
            $accountUserId = (int)$adminRow['idpk'];
        } else {
            // 2) Otherwise, see if it exists in users
            $stmt = $pdo->prepare("SELECT idpk FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow) {
                $accountType   = 'user';
                $accountUserId = (int)$userRow['idpk'];
            } else {
                // Not found in either table; we still show the generic success message below
                $accountType = null;
            }
        }

        if ($accountType !== null) {
            // 3) Generate reset token & expiry
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 4) Insert into PasswordResets
            //    Assume your PasswordResets table has columns:
            //      id (auto‐increment), email, token, expiry, role, user_id
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO PasswordResets 
                        (email, token, expiry, role, user_id) 
                    VALUES 
                        (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $email,
                    $token,
                    $expiry,
                    $accountType,
                    $accountUserId
                ]);

                // 5) Send the reset email
                $resetLink = "https://" . $_SERVER['HTTP_HOST'] 
                           . dirname($_SERVER['PHP_SELF']) 
                           . "/ResetPassword.php?token=" . $token;

                $to      = $email;
                $subject = "Password Reset Request";
                $message = "
Hello,

You (or someone else) have requested to reset your password. 
If this was you, click the link below to reset it. This link will expire in 1 hour:

$resetLink

If you did not request a password reset, please ignore this email.
";
                $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n"
                         . "MIME-Version: 1.0\r\n"
                         . "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($to, $subject, $message, $headers)) {
                    $success = 'Password reset instructions have been sent to your email.';
                } else {
                    $error = 'Error sending email. Please try again later.';
                }
            } catch (Exception $e) {
                // Don’t reveal details—just generic error
                $error = 'An error occurred. Please try again later.';
            }
        } else {
            // 6) Email not found in either table, but we still pretend it worked
            $success = 'If your email exists in our system, you will receive password reset instructions.';
        }

    } else {
        $error = 'Please enter a valid email address.';
    }
}
?>

<div class="container">
    <h1 class="text-center">❔ FORGOT PASSWORD</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">email</label>
            <input type="email" id="email" name="email" required>
        </div>

        <button type="submit">✉️ SEND RESET LINK</button>

        <div class="text-center mt-3">
            <a href="login.php">🗝️ BACK TO LOGIN</a>
        </div>
    </form>
</div>

<?php require_once('footer.php'); ?>
