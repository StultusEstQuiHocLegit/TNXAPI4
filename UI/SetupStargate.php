<?php
require_once('../config.php');
require_once('header.php');

// require_once('../ConfigExternal.php');

function emptyToString($value) {
    return trim($value) === '' ? '' : $value;
}

$error   = '';
$success = '';

// Fetch current DB settings
$stmt = $pdo->prepare("
    SELECT
        DbHost
    FROM admins
    WHERE idpk = ?
");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize defaults if empty
$dbHost     = $settings['DbHost']     ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_databases'])) {
    // Sanitize inputs
    $dbHost     = emptyToString($_POST['db_host']);

    // Update in database
    $stmt = $pdo->prepare("
        UPDATE admins
        SET
            DbHost     = ?
        WHERE idpk = ?
    ");
    $ok = $stmt->execute([
        $dbHost,
        $_SESSION['user_id']
    ]);

    if ($ok) {
        $success = 'Database settings updated successfully.';
    } else {
        $error = 'Error updating database settings. Please try again.';
    }
}
?>

<div class="container" style="max-width: 800px; margin: auto;">
    <h1 class="text-center">🕳️ STARGATE SETUP</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div id="custom_db_settings">
            <div class="form-group">
                <label for="db_host">connection hostname/IP</label>
                <input
                    type="text"
                    id="db_host"
                    name="db_host"
                    class="form-control"
                    placeholder="www.example.com"
                    value="<?php echo htmlspecialchars($dbHost); ?>"
                >
            </div>
        </div>

        <button type="submit" name="update_databases" class="btn btn-primary" style="margin-top: 2rem;">
            ↗️ SAVE SETTINGS
        </button>

        <br><br><br><br><br>
        <pre style="margin-top: 2rem; font-family: monospace; max-width: 100%; overflow-x: auto;">
            <span title="in the htdocs of your server, there should be the following structure">/htdocs/</span>
            └── <span title="as main directory">STARGATE/</span>
                ├── <span title="to control external access">.htaccess</span>
                ├── <span title="central subdirector">index.php</span>
                ├── <span title="receives, executes and sends (some configuration is needed here)" style="color: var(--accent-color);">stargate.php</span>
                ├── <span title="security backups of your databases and the corresponding uploads">BACKUPS/</span>
                │   ├── <span title="this is called regulary with a cron job to create a backup (some configuration is needed here)" style="color: var(--accent-color);">CreateBackup.php</span>
                │   ├── <span title="central subdirector">index.php</span>
                │   ├── <span title="to make sure the backups is always made in the exact same time cadence">LastBackupTimestamp.txt</span>
                │   ├── <span title="a subfolder for every backup">BACKUP_SOMETIMESTAMPINHERE/</span>
                │   │   ├── <span title="backup of your uploads">UPLOADS/</span>
                │   │   │   └── <span title="everything, all subfolders and files">...</span>
                │   │   └── <span title="backup of your databases">databases.sql</span>
                │   ├── <span title="a subfolder for every backup">BACKUP_ANOTHERTIMESTAMPINHERE/</span>
                │   │   ├── <span title="backup of your uploads">UPLOADS/</span>
                │   │   │   └── <span title="everything, all subfolders and files">...</span>
                │   │   └── <span title="backup of your databases">databases.sql</span>
                │   └── <span title="a subfolder for every backup">.../</span>
                │   │   ├── <span title="backup of your uploads">UPLOADS/</span>
                │   │   │   └── <span title="everything, all subfolders and files">...</span>
                │   │   └── <span title="backup of your databases">databases.sql</span>
                ├── <span title="temporary, unstructured uploads">TMP/</span>
                │   └── <span title="central subdirector">index.php</span>
                └── <span title="uploads linked to your database tables entries">UPLOADS/</span>
                    ├── <span title="central subdirector">index.php</span>
                    ├── <span title="a subfolder for every table in your database">FirstTableName/</span>
                    │   └── <span title="central subdirector">index.php</span>
                    ├── <span title="a subfolder for every table in your database">SecondTableName/</span>
                    │   └── <span title="central subdirector">index.php</span>
                    └── <span title="a subfolder for every table in your database">.../</span>
                        └── <span title="central subdirector">index.php</span>
        </pre>
    </form>
</div>

<?php require_once('footer.php'); ?>
