<?php
require_once('../config.php');
require_once('header.php');

$error       = '';
$success     = '';
$validToken  = false;
$email       = '';
$accountType = '';    // 'admin' or 'user'
$accountUserId = null;

// 1) CHECK TOKEN WHEN THE PAGE IS LOADED VIA GET OR VIA POST
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Fetch the latest matching row for this token
    $stmt = $pdo->prepare("
        SELECT 
            email,
            role,
            user_id,
            expiry
        FROM PasswordResets
        WHERE token = ?
        ORDER BY TimestampCreation DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRow && strtotime($resetRow['expiry']) > time()) {
        // Token is valid (not expired)
        $validToken     = true;
        $email          = $resetRow['email'];
        $accountType    = $resetRow['role'];       // 'admin' or 'user'
        $accountUserId  = (int)$resetRow['user_id'];
    }
}

// 2) IF THE FORM IS SUBMITTED (POST) AND TOKEN IS STILL MARKED VALID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['token'])) {
    // We still have $_GET['token'], so re‐verify it before changing any password
    $postedToken = $_GET['token'];

    $stmt = $pdo->prepare("
        SELECT 
            email,
            role,
            user_id,
            expiry
        FROM PasswordResets
        WHERE token = ?
        ORDER BY TimestampCreation DESC
        LIMIT 1
    ");
    $stmt->execute([$postedToken]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRow && strtotime($resetRow['expiry']) > time()) {
        // Re‐confirm validity
        $validToken     = true;
        $email          = $resetRow['email'];
        $accountType    = $resetRow['role'];
        $accountUserId  = (int)$resetRow['user_id'];
    } else {
        $validToken = false;
    }

    if ($validToken) {
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($password && $confirmPassword) {
            if ($password === $confirmPassword) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Decide which table to update
                if ($accountType === 'admin') {
                    $updateSql = "UPDATE admins SET password = ? WHERE idpk = ?";
                } else {
                    $updateSql = "UPDATE users  SET password = ? WHERE idpk = ?";
                }

                $stmt = $pdo->prepare($updateSql);
                $ok   = $stmt->execute([$hashedPassword, $accountUserId]);

                if ($ok) {
                    // Delete only THIS token (so it can't be reused).
                    $stmt = $pdo->prepare("DELETE FROM PasswordResets WHERE token = ?");
                    $stmt->execute([$postedToken]);

                    $success = 'Password has been reset successfully. You can now login with your new password.';
                } else {
                    $error = 'Error updating password. Please try again.';
                }

            } else {
                $error = 'Passwords do not match. Please try again.';
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
    }
}
?>

<div class="container">
    <h1 class="text-center">❔ RESET PASSWORD</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
            <div class="text-center mt-3">
                <a href="login.php">🗝️ LOGIN</a>
            </div>
        </div>

    <?php elseif ($validToken): ?>
        <!-- Show the “new password” form -->
        <form 
            method="POST" 
            action="?token=<?php echo urlencode($_GET['token']); ?>">
            <div class="form-group">
                <label for="password">new password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit">✉️ SUBMIT</button>
        </form>

    <?php else: ?>
        <div class="alert alert-error">
            Invalid or expired reset token. Please request a new password reset.
            <div class="text-center mt-3">
                <a href="ForgotPassword.php">✉️ SEND NEW RESET LINK</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
