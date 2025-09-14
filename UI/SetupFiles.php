<?php
require_once('../config.php');
require_once('header.php');

$error   = '';
$success = '';

// Fetch current file access settings
$stmt = $pdo->prepare("
    SELECT
        FileProtocol,
        FileServer,
        FilePort,
        FileUser,
        FilePassword,
        FileBasePath
    FROM admins
    WHERE idpk = ?
");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize defaults if empty
$fileProtocol  = $settings['FileProtocol']  ?? 'sftp';
$fileServer    = $settings['FileServer']    ?? '';
$filePort      = $settings['FilePort']      ?? 22;
$fileUser      = $settings['FileUser']      ?? '';
$filePassword  = $settings['FilePassword']  ?? '';
$fileBasePath  = $settings['FileBasePath']  ?? '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_files'])) {
    // Sanitize inputs
    $fileProtocol  = in_array($_POST['file_protocol'], ['ftp','sftp','ftps'], true)
                     ? $_POST['file_protocol']
                     : 'sftp';
    $fileServer    = htmlspecialchars($_POST['file_server'], ENT_QUOTES, 'UTF-8');
    $filePort      = filter_input(INPUT_POST, 'file_port', FILTER_VALIDATE_INT) ?: ($fileProtocol === 'ftp' ? 21 : 22);
    $fileUser      = htmlspecialchars($_POST['file_user'], ENT_QUOTES, 'UTF-8');
    $filePassword  = $_POST['file_password'] ?? '';
    $fileBasePath  = rtrim(htmlspecialchars($_POST['file_base_path'], ENT_QUOTES, 'UTF-8'), '/');

    // Update in database
    $stmt = $pdo->prepare("
        UPDATE admins
        SET
            FileProtocol = ?,
            FileServer   = ?,
            FilePort     = ?,
            FileUser     = ?,
            FilePassword = ?,
            FileBasePath = ?
        WHERE idpk = ?
    ");
    $ok = $stmt->execute([
        $fileProtocol,
        $fileServer,
        $filePort,
        $fileUser,
        $filePassword,
        $fileBasePath,
        $_SESSION['user_id']
    ]);

    if ($ok) {
        $success = 'File access settings updated successfully.';
    } else {
        $error = 'Error updating file settings. Please try again.';
    }
}
?>

<div class="container" style="max-width: 500px; margin: auto;">
    <h1 class="text-center">☁️ FILES SETUP</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="file_protocol">protocol</label>
            <select
                id="file_protocol"
                name="file_protocol"
                class="form-control"
            >
                <option value="ftp"  <?php echo $fileProtocol === 'ftp'  ? 'selected' : ''; ?>>FTP</option>
                <option value="sftp" <?php echo $fileProtocol === 'sftp' ? 'selected' : ''; ?>>SFTP</option>
                <option value="ftps" <?php echo $fileProtocol === 'ftps' ? 'selected' : ''; ?>>FTPS</option>
            </select>
        </div>

        <div class="form-group">
            <label for="file_server">server hostname/IP</label>
            <input
                type="text"
                id="file_server"
                name="file_server"
                class="form-control"
                placeholder="files.example.com"
                value="<?php echo htmlspecialchars($fileServer); ?>"
            >
        </div>

        <div class="form-group">
            <label for="file_port">port</label>
            <input
                type="number"
                id="file_port"
                name="file_port"
                class="form-control"
                placeholder="22"
                value="<?php echo htmlspecialchars($filePort); ?>"
            >
        </div>

        <div class="form-group">
            <label for="file_user">FTP/SFTP username</label>
            <input
                type="text"
                id="file_user"
                name="file_user"
                class="form-control"
                placeholder="neo"
                value="<?php echo htmlspecialchars($fileUser); ?>"
            >
        </div>

        <div class="form-group">
            <label for="file_password">password</label>
            <input
                type="password"
                id="file_password"
                name="file_password"
                class="form-control"
                placeholder="12345678?"
                value="<?php echo htmlspecialchars($filePassword); ?>"
            >
        </div>

        <div class="form-group">
            <label for="file_base_path">base Path</label>
            <input
                type="text"
                id="file_base_path"
                name="file_base_path"
                class="form-control"
                placeholder="/var/www/adventure_time/treasure"
                value="<?php echo htmlspecialchars($fileBasePath); ?>"
            >
        </div>

        <button type="submit" name="update_files" class="btn btn-primary">
            ↗️ SAVE SETTINGS
        </button>
    </form>
</div>

<?php require_once('footer.php'); ?>
