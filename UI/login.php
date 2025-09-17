<?php
require_once('../config.php');
session_start();

// If already logged in, redirect immediately
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Get & sanitize inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password']; // leave raw for password_verify()

    if ($email && $password) {
        // We'll track which table we found the user in, and the fetched row
        $table = null;
        $userRow = null;

        // 2) Try to find in admins table first
        $stmt = $pdo->prepare("SELECT idpk, password FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($password, $row['password'])) {
            // Found in admins and password matches
            $table = 'admins';
            $userRow = $row;
            $role = 'admin';
        } else {
            if ($row && password_verify($password, $row['password'])) {
                // Found in users and password matches
                $table = 'users';
                $userRow = $row;
                $role = 'user';
            }
        }

        if ($table !== null) {
            // 4) We have a successful login (either admin or user)

            // a) Save user ID and role into session
            $_SESSION['user_id'] = $userRow['idpk'];
            $_SESSION['user_role'] = $role;

            // b) Generate a long‐lived login token & expiry (10 years out)
            $token = bin2hex(random_bytes(64));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 years'));

            // c) Update the correct table's token columns
            $updateSql = "UPDATE {$table} 
                          SET LoginToken = :token, 
                              LoginTokenExpiry = :expiry 
                          WHERE idpk = :idpk";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':token'  => $token,
                ':expiry' => $expiry,
                ':idpk'   => $userRow['idpk']
            ]);

            // d) Set a secure, HttpOnly cookie for the token (valid for 30 days)
            setcookie(
                "LoginToken",
                $token,
                time() + (86400 * 30),   // 30 days
                "/",                     // path
                "",                      // domain (empty = current)
                false,                   // secure only? (switch to true if using HTTPS)
                true                     // HttpOnly
            );

            // e) Redirect to dashboard (you can branch here if you want
            //    admins to go somewhere else)
            header('Location: index.php');
            exit();
        } else {
            // 5) Neither admins nor users matched
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

// At this point, either showing the form or there was a validation error.
// Only include header after possible redirects.
require_once('header.php');
?>

<div class="container">
    <h1 class="text-center">🗝️ LOGIN</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">email</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">🗝️ LOGIN</button>

        <div class="text-center mt-3">
            <a href="ForgotPassword.php">❔ FORGOT PASSWORD</a>
            <br>
            <a href="CreateAccount.php">⚙️ CREATE BUSINESS ADMIN ACCOUNT</a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (localStorage.getItem('darkmode') === '1') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
});
</script>

<?php require_once('footer.php'); ?>
