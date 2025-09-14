<?php
ob_start(); // Start output buffering
require_once('../config.php');
require_once('header.php');

$error = '';
$success = '';

// Fetch current communication settings
$stmt = $pdo->prepare("
    SELECT ConnectEmail, ConnectPassword, ConnectServerName, ConnectPort, ConnectEncryption
    FROM admins
    WHERE idpk = ?
");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize defaults if empty
$connectEmail      = $settings['ConnectEmail']      ?? '';
$connectPassword   = $settings['ConnectPassword']   ?? '';
$connectServerName = $settings['ConnectServerName'] ?? '';
$connectPort       = $settings['ConnectPort']       ?? 587;
$connectEncryption = $settings['ConnectEncryption'] ?? 'tls';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_communication'])) {
    // Raw inputs (allow multiple entries separated by delimiters)
    $connectEmail      = $_POST['connect_email'] ?? '';
    $connectPassword   = $_POST['connect_password'] ?? '';
    $connectServerName = htmlspecialchars($_POST['connect_server_name'], ENT_QUOTES, 'UTF-8');
    $connectPort       = filter_input(INPUT_POST, 'connect_port', FILTER_VALIDATE_INT);
    $connectEncryption = $_POST['connect_encryption'] ?? '';

    // Update in database
    $stmt = $pdo->prepare("
        UPDATE admins
        SET
            ConnectEmail       = ?,
            ConnectPassword    = ?,
            ConnectServerName  = ?,
            ConnectPort        = ?,
            ConnectEncryption  = ?
        WHERE idpk = ?
    ");
    $ok = $stmt->execute([
        $connectEmail,
        $connectPassword,
        $connectServerName,
        $connectPort,
        $connectEncryption,
        $_SESSION['user_id']
    ]);
    if ($ok) {
        // Refresh session variables using same parsing logic as header.php
        $_SESSION['ConnectEmailFull']    = $connectEmail;
        $_SESSION['ConnectPasswordFull'] = $connectPassword;
        $emailPairs = parseEmailPairs($connectEmail, $connectPassword);
        $_SESSION['ConnectEmailList'] = array_keys($emailPairs);
        $selectedEmail = $_SESSION['ConnectEmailList'][0] ?? '';
        if (!empty($_COOKIE['MainEmailForSending']) && in_array($_COOKIE['MainEmailForSending'], $_SESSION['ConnectEmailList'], true)) {
            $selectedEmail = $_COOKIE['MainEmailForSending'];
        }
        if ($selectedEmail) {
            setcookie('MainEmailForSending', $selectedEmail, time() + 10 * 365 * 24 * 60 * 60, '/');
        }
        $_SESSION['ConnectEmail']    = $selectedEmail;
        $_SESSION['ConnectPassword'] = $emailPairs[$selectedEmail] ?? '';

        $success = 'Communication settings updated successfully.';
    } else {
        $error = 'Error updating settings. Please try again.';
    }
}
?>

<div class="container" style="max-width: 800px; margin: auto;">
    <h1 class="text-center">✉️ COMMUNICATION SETUP</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="connect_email">SMTP email(s) (username(s))</label>
            <input
                type="text"
                id="connect_email"
                name="connect_email"
                class="form-control"
                title="main@email.com | second@email.com | ..."
                placeholder="main@email.com | second@email.com | ..."
                value="<?php echo htmlspecialchars($connectEmail); ?>"
            >
        </div>

        <div class="form-group">
            <label for="connect_password">SMTP password(s)</label>
            <input
                type="text"
                id="connect_password"
                name="connect_password"
                class="form-control"
                title="mainpassword | secondpassword | ..."
                placeholder="mainpassword | secondpassword | ..."
                value="<?php echo htmlspecialchars($connectPassword); ?>"
            >
        </div>

        <div class="form-group">
            <label for="connect_server_name">SMTP server</label>
            <input
                type="text"
                id="connect_server_name"
                name="connect_server_name"
                class="form-control"
                placeholder="mail.quantumrealm.lab"
                value="<?php echo htmlspecialchars($connectServerName); ?>"
            >
        </div>

        <div class="form-group">
            <label for="connect_port">SMTP port</label>
            <input
                type="number"
                id="connect_port"
                name="connect_port"
                class="form-control"
                value="<?php echo htmlspecialchars($connectPort); ?>"
            >
        </div>

        <div class="form-group">
            <label for="connect_encryption">encryption</label>
            <select
                id="connect_encryption"
                name="connect_encryption"
                class="form-control"
            >
                <option value="none" <?php echo $connectEncryption === 'none' ? 'selected' : ''; ?>>None</option>
                <option value="ssl"  <?php echo $connectEncryption === 'ssl'  ? 'selected' : ''; ?>>SSL</option>
                <option value="tls"  <?php echo $connectEncryption === 'tls'  ? 'selected' : ''; ?>>TLS</option>
            </select>
        </div>

        <button type="submit" name="update_communication" class="btn btn-primary">
            ↗️ SAVE SETTINGS
        </button>
    </form>
</div>

<?php require_once('footer.php'); ?>
