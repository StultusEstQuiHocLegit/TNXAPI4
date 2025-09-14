<?php
// // ################################ the cron job should call this script at: https://www.example.com/STARGATE/BACKUPS/CreateBackup.php?key=A7kR9ZpXq6M1tLwJ3vNcBdGfUy82oQeHiYsTx4Cm
// (the key must be exactly the same as in the $secureKey variable below)



$mysqlDbServer = "################################ REPLACETHISWITHYOURACTUALSERVERPROBABLYLOCALHOST";
$mysqlDbName = "################################ REPLACETHISWITHYOURACTUALDATABASENAME";
$mysqlDbUser = "################################ REPLACETHISWITHYOURACTUALUSERNAME";
$mysqlDbPassword = "################################ REPLACETHISWITHYOURACTUALPASSWORD";


error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');  // Disable outputting errors directly

try {
    // Create a new PDO instance
    $dsn = "mysql:host=$mysqlDbServer;dbname=$mysqlDbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $mysqlDbUser, $mysqlDbPassword);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $configLoaded = true; // Configuration loaded successfully
} catch (PDOException $e) {
    // Handle connection error
    printf("Connection to database couldn't be made: %s\n", $e->getMessage());
    echo "<meta http-equiv=refresh content=3>";
    echo "<br><br>Please send this error reporting to an administrator so we can fix the problem.";
    exit();
}


// === Configuration ===
// Define your secure key (make sure this is stored securely in production) which will be sent as a GET parameter
$secureKey = "A7kR9ZpXq6M1tLwJ3vNcBdGfUy82oQeHiYsTx4Cm"; // ################################ please replace this with a strong random key in the same format
$MinimalTimeCadence = 48 * 3600; // 48 hours in seconds  // ################################ you can adjust this, this is just our recommandation
$NumberOfBackupsInTotalAtEveryGivenTime = 3;             // ################################ you can adjust this, this is just our recommandation
$databases = true;                                       // ################################ you can adjust this, this is just our recommandation
$attachments = true;                                     // ################################ you can adjust this, this is just our recommandation






















// 1) Ensure key is correct (otherwise exit 403)
if (!isset($_GET['key']) || !hash_equals($secureKey, (string)$_GET['key'])) {
    http_response_code(403);
    exit('Forbidden');
}

// Utility: recursive directory copy
function copyDir(string $src, string $dst): void {
    if (!is_dir($src)) {
        throw new RuntimeException("Source directory does not exist: $src");
    }
    if (!is_dir($dst) && !mkdir($dst, 0755, true)) {
        throw new RuntimeException("Failed to create destination directory: $dst");
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $targetPath = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true)) {
                throw new RuntimeException("Failed to create subdirectory: $targetPath");
            }
        } else {
            if (!copy($item->getPathname(), $targetPath)) {
                throw new RuntimeException("Failed to copy file to: $targetPath");
            }
        }
    }
}

// Utility: recursive directory delete
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

// 2) Enforce MinimalTimeCadence using ./LastBackupTimestamp.txt
$baseDir      = __DIR__;
$tsFile       = $baseDir . DIRECTORY_SEPARATOR . 'LastBackupTimestamp.txt';
$now          = time();
$lastTs       = null;

if (is_file($tsFile)) {
    $raw = trim((string)@file_get_contents($tsFile));
    if ($raw !== '' && ctype_digit($raw)) {
        $lastTs = (int)$raw;
    }
}
if ($lastTs !== null && ($now - $lastTs) < $MinimalTimeCadence) {
    // Too soon since last backup; exit silently
    exit();
}

// 3) Create backup folder
$backupDirName = 'BACKUP_' . $now;
$backupDirPath = $baseDir . DIRECTORY_SEPARATOR . $backupDirName;

if (!mkdir($backupDirPath, 0755, true)) {
    http_response_code(500);
    exit('Failed to create backup directory');
}

// 4) If $databases = true -> dump MySQL to databases.sql using mysqldump
if ($databases === true) {
    $sqlPath = $backupDirPath . DIRECTORY_SEPARATOR . 'databases.sql';

    // Build mysqldump command securely; stream stdout directly to file
    $cmd = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --default-character-set=utf8mb4 --routines --events --single-transaction %s',
        escapeshellarg($mysqlDbServer),
        escapeshellarg($mysqlDbUser),
        escapeshellarg($mysqlDbPassword),
        escapeshellarg($mysqlDbName)
    );

    $descriptors = [
        1 => ['file', $sqlPath, 'w'], // stdout -> file
        2 => ['pipe', 'w'],           // stderr -> pipe (capture errors)
    ];
    $process = @proc_open($cmd, $descriptors, $pipes, $baseDir, null);
    if (!is_resource($process)) {
        http_response_code(500);
        exit('Failed to start mysqldump process');
    }
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status !== 0) {
        // Clean up partial backup on failure
        @unlink($sqlPath);
        rrmdir($backupDirPath);
        http_response_code(500);
        exit('mysqldump failed: ' . htmlspecialchars($stderr ?? 'unknown error', ENT_QUOTES));
    }
}

// 5) If $attachments = true -> copy ../UPLOADS/ into BACKUP_{ts}/UPLOADS/
if ($attachments === true) {
    $uploadsSource = realpath($baseDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'UPLOADS');
    $uploadsDest   = $backupDirPath . DIRECTORY_SEPARATOR . 'UPLOADS';

    if ($uploadsSource !== false && is_dir($uploadsSource)) {
        try {
            copyDir($uploadsSource, $uploadsDest);
        } catch (Throwable $t) {
            // Clean up partial backup on failure
            rrmdir($backupDirPath);
            http_response_code(500);
            exit('Failed to copy UPLOADS: ' . htmlspecialchars($t->getMessage(), ENT_QUOTES));
        }
    } else {
        // If the source doesn't exist, create an empty UPLOADS/ to reflect intent
        if (!mkdir($uploadsDest, 0755, true)) {
            rrmdir($backupDirPath);
            http_response_code(500);
            exit('UPLOADS directory not found and could not create destination');
        }
    }
}

// 6) Keep only the newest N backups (delete oldest if over limit)
$all = glob($baseDir . DIRECTORY_SEPARATOR . 'BACKUP_*', GLOB_ONLYDIR);
$backups = [];
if ($all !== false) {
    foreach ($all as $dir) {
        // Expect format BACKUP_{timestamp}
        $base = basename($dir);
        $parts = explode('_', $base, 2);
        $ts = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : filemtime($dir);
        $backups[] = ['path' => $dir, 'ts' => $ts];
    }
    // Sort newest first
    usort($backups, function($a, $b) { return $b['ts'] <=> $a['ts']; });

    if (count($backups) > $NumberOfBackupsInTotalAtEveryGivenTime) {
        $toDelete = array_slice($backups, $NumberOfBackupsInTotalAtEveryGivenTime);
        foreach ($toDelete as $old) {
            rrmdir($old['path']);
        }
    }
}

// 7) Overwrite LastBackupTimestamp.txt with current timestamp
if (@file_put_contents($tsFile, (string)$now, LOCK_EX) === false) {
    // Not fatal for the backup itself, but signal a server error
    http_response_code(500);
    exit('Backup created, but failed to update LastBackupTimestamp.txt');
}

// Optional: minimal output (or stay silent)
echo "Backup successful.";



?>
