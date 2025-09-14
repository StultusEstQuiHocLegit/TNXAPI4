<?php
ob_start(); // Start output buffering
require_once('../config.php');
require_once('header.php');

$error = '';
$success = '';
$IdpkOfAdmin = $_SESSION['user_id'] ?? 1;
$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';
$DbHost = $_SESSION['DbHost'] ?? '';

// if no stargate connection details are available, show note message and exit early
if (empty($TRAMANNAPIAPIKey) || empty($DbHost)) {
    echo "<div class='container' style='width: 1200px; max-width: 1200px; margin: auto; text-align: center;'>";
    echo "<h1 class='text-center'>🧬 DATABASES SETUP</h1>";
    echo "<div class='alert alert-error'>Missing connection details.<br>Please check the <a href='SetupStargate.php'>🕳️ STARGATE SETUP</a>.</div>";
    echo "</div>";
    require_once('footer.php');
    ob_end_flush();
    exit;
}

function clean_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function stargate_exec($sql) {
    global $TRAMANNAPIAPIKey;
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "\$pdo->exec(\"" . addslashes($sql) . "\");"
    ];
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) { $basePath = ''; }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function stargate_fetch($sql) {
    global $TRAMANNAPIAPIKey;
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "\$stmt=\$pdo->query(\"" . addslashes($sql) . "\");\$r=\$stmt->fetchAll(PDO::FETCH_ASSOC);echo json_encode(\$r);"
    ];
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) { $basePath = ''; }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] === 'success') {
        $msg = $data['message'] ?? [];
        return is_array($msg) ? $msg : (json_decode($msg, true) ?? []);
    }
    return [];
}

function stargate_fs_exec($code) {
    global $TRAMANNAPIAPIKey;
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => $code
    ];
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) { $basePath = ''; }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function apply_dbmarks($table, $column, $dbtype, $dbmarks) {
    if (trim(strtolower($dbmarks)) === 'auto increment, primary key') {
        $colInfo = stargate_fetch("SHOW COLUMNS FROM `" . addslashes($table) . "` LIKE '" . addslashes($column) . "'");
        $isPrimary = false;
        $isAuto = false;
        if (!empty($colInfo[0])) {
            $info = $colInfo[0];
            $isPrimary = ($info['Key'] ?? '') === 'PRI';
            $isAuto = strpos($info['Extra'] ?? '', 'auto_increment') !== false;
        }
        if (!$isPrimary) {
            stargate_exec("ALTER TABLE `{$table}` ADD PRIMARY KEY(`{$column}`)");
            $isPrimary = true;
        }
        if ($isPrimary && !$isAuto) {
            stargate_exec("ALTER TABLE `{$table}` MODIFY `{$column}` {$dbtype} AUTO_INCREMENT");
        }
    }
}

function get_upload_base_dir() {
    $code = "if (!is_dir('./UPLOADS')) { mkdir('./UPLOADS',0777,true); file_put_contents('./UPLOADS/index.php',''); }";
    stargate_fs_exec($code);
    return './UPLOADS';
}

function create_table_directory($table) {
    $base = get_upload_base_dir();
    $tableEsc = addslashes($table);
    $code = "\\$dir = '{$base}/{$tableEsc}'; if (!is_dir(\\$dir)) { mkdir(\\$dir,0777,true); file_put_contents(\\$dir.'/index.php',''); }";
    stargate_fs_exec($code);
}

function delete_table_directory($table) {
    $base = get_upload_base_dir();
    $tableEsc = addslashes($table);
    $code = "\\$dir = '{$base}/{$tableEsc}'; if (is_dir(\\$dir)) { function rrmdir(\\$d){ foreach(scandir(\\$d) as \\$f){ if(\\$f!='.' && \\$f!='..'){ \\$p=\\$d.'/'.\\$f; if(is_dir(\\$p)){ rrmdir(\\$p); } else { unlink(\\$p); } } } rmdir(\\$d); } rrmdir(\\$dir); }";
    stargate_fs_exec($code);
}

function rename_table_directory($old, $new) {
    $base = get_upload_base_dir();
    $oldEsc = addslashes($old);
    $newEsc = addslashes($new);
    $code = "\\$base='{$base}'; \\$oldDir=\\$base.'/{$oldEsc}'; \\$newDir=\\$base.'/{$newEsc}'; if (is_dir(\\$oldDir)) { rename(\\$oldDir, \\$newDir); } else { if (!is_dir(\\$newDir)) { mkdir(\\$newDir,0777,true); file_put_contents(\\$newDir.'/index.php',''); } }";
    stargate_fs_exec($code);
}

// handle save table with columns or remove operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['toggle_column_visibility'])) {
            $tableName = clean_input($_POST['table_name'] ?? '');
            $tableId   = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
            $colName   = clean_input($_POST['column_name'] ?? '');
            $colId     = filter_input(INPUT_POST, 'column_id', FILTER_VALIDATE_INT);
            $makeVisible = filter_input(INPUT_POST, 'make_visible', FILTER_VALIDATE_INT) === 1;
            $dbType = clean_input($_POST['db_type'] ?? '');
            if ($makeVisible) {
                if (!$tableId) {
                    $stmt = $pdo->prepare('INSERT INTO tables (name, IdpkOfAdmin, TimestampCreation, FurtherInformation) VALUES (?,?,NOW(),?)');
                    $stmt->execute([$tableName, $IdpkOfAdmin, '']);
                    $tableId = $pdo->lastInsertId();
                }
                $defaultColumnValues = [
                    $colName,        // name
                    'text',          // type
                    '',              // label
                    0,               // SystemOnly
                    $dbType,         // DBType
                    '',              // DBMarks
                    null,            // LinkedToWhatTable
                    '',              // LinkedToWhatFieldThere
                    0,               // IsLinkedTableGeneralTable
                    '',              // placeholder
                    '',              // step
                    null,            // maxlength
                    0,               // HumanPrimary
                    null,            // OnlyShowIfThisIsTrue
                    0,               // price
                    0,               // ShowInPreviewCard
                    0,               // ShowToPublic
                    0,               // ShowWholeEntryToPublicMarker
                    $tableId         // IdpkOfTable
                ];
                $stmt = $pdo->prepare('INSERT INTO columns (name,type,label,SystemOnly,DBType,DBMarks,LinkedToWhatTable,LinkedToWhatFieldThere,IsLinkedTableGeneralTable,placeholder,step,maxlength,HumanPrimary,OnlyShowIfThisIsTrue,price,ShowInPreviewCard,ShowToPublic,ShowWholeEntryToPublicMarker,IdpkOfTable,TimestampCreation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $stmt->execute($defaultColumnValues);
                header('Location: SetupDatabases.php?idpk=' . $tableId);
                exit;
            } else {
                if ($colId) {
                    $pdo->prepare('DELETE FROM columns WHERE idpk=?')->execute([$colId]);
                }
                header('Location: SetupDatabases.php?' . ($tableId ? 'idpk=' . $tableId : 'tablename=' . urlencode($tableName)));
                exit;
            }
        } elseif (isset($_POST['toggle_table_visibility'])) {
            $tableName = clean_input($_POST['table_name'] ?? '');
            $tableId   = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
            if ($tableId) {
                $pdo->prepare('DELETE FROM columns WHERE IdpkOfTable=?')->execute([$tableId]);
                $pdo->prepare('DELETE FROM tables WHERE idpk=?')->execute([$tableId]);
                header('Location: SetupDatabases.php?tablename=' . urlencode($tableName));
                exit;
            } else {
                $stmt = $pdo->prepare('INSERT INTO tables (name, IdpkOfAdmin, TimestampCreation, FurtherInformation) VALUES (?,?,NOW(),?)');
                $stmt->execute([$tableName, $IdpkOfAdmin, '']);
                $tableId = $pdo->lastInsertId();
                header('Location: SetupDatabases.php?idpk=' . $tableId);
                exit;
            }
        } elseif (isset($_POST['save_table']) || isset($_POST['save_table_return'])) {
            $table_id = filter_input(INPUT_POST, 'idpk', FILTER_VALIDATE_INT);
            $name = clean_input($_POST['table_name'] ?? '');
            $furtherInformation = clean_input($_POST['further_information'] ?? '');
            $existingBackend = !empty($_POST['existing_backend']);
            $newTableModePost = !empty($_POST['new_table_mode']);
            $originalName = clean_input($_POST['original_name'] ?? '');
            if (!$name) throw new Exception('Table name is required.');

            // use the same timestamp for all inserts in this request
            $now = date('Y-m-d H:i:s');

            $oldTableName = null;
            if ($table_id) {
                $stmtOld = $pdo->prepare('SELECT name FROM tables WHERE idpk=?');
                $stmtOld->execute([$table_id]);
                $oldTableName = $stmtOld->fetchColumn();
                $stmt = $pdo->prepare('UPDATE tables SET name=?, FurtherInformation=? WHERE idpk=? AND IdpkOfAdmin=?');
                $stmt->execute([$name, $furtherInformation, $table_id, $IdpkOfAdmin]);
                if ($oldTableName && $oldTableName !== $name) {
                    stargate_exec("RENAME TABLE `{$oldTableName}` TO `{$name}`");
                    rename_table_directory($oldTableName, $name);
                }
            } elseif ($newTableModePost) {
                $stmt = $pdo->prepare(
                    "INSERT INTO tables (
                        name,
                        IdpkOfAdmin,
                        TimestampCreation,
                        FurtherInformation
                    ) VALUES (
                        ?, ?, ?, ?
                    )"
                );
                $stmt->execute([
                    $name,
                    $IdpkOfAdmin,
                    $now,
                    $furtherInformation
                ]);
                $table_id = $pdo->lastInsertId();
                stargate_exec("CREATE TABLE `{$name}` (idpk INT AUTO_INCREMENT PRIMARY KEY)");
                create_table_directory($name);
            } elseif ($existingBackend) {
                if ($originalName && $originalName !== $name) {
                    stargate_exec("RENAME TABLE `{$originalName}` TO `{$name}`");
                    rename_table_directory($originalName, $name);
                }
            }

            // delete columns marked for removal
            if (!empty($_POST['remove_col_ids'])) {
                foreach ($_POST['remove_col_ids'] as $rid) {
                    $rid = filter_var($rid, FILTER_VALIDATE_INT);
                    if ($rid) {
                        $stmtc = $pdo->prepare('SELECT name FROM columns WHERE idpk=?');
                        $stmtc->execute([$rid]);
                        $dropName = $stmtc->fetchColumn();
                        if ($dropName) {
                            stargate_exec("ALTER TABLE `{$name}` DROP COLUMN `{$dropName}`");
                        }
                        $pdo->prepare('DELETE FROM columns WHERE idpk=?')->execute([$rid]);
                    }
                }
            }
            if (!empty($_POST['remove_col_backend'])) {
                foreach ($_POST['remove_col_backend'] as $rname) {
                    $rname = clean_input($rname);
                    if ($rname !== '') {
                        stargate_exec("ALTER TABLE `{$name}` DROP COLUMN `{$rname}`");
                    }
                }
            }

            // process columns arrays
            $colNames = $_POST['col_name'] ?? [];
            $colIds   = $_POST['col_id'] ?? [];
            $types    = $_POST['col_type'] ?? [];
            $labels   = $_POST['col_label'] ?? [];
            $dbtypes  = $_POST['col_DBType'] ?? [];
            $systemOnly = array_map('intval', $_POST['col_SystemOnly'] ?? []);
            $dbmarks  = $_POST['col_DBMarks'] ?? [];
            $linkedTable = $_POST['col_LinkedToWhatTable'] ?? [];
            $linkedField = $_POST['col_LinkedToWhatFieldThere'] ?? [];
            $isLinkedGen = array_map('intval', $_POST['col_IsLinkedTableGeneralTable'] ?? []);
            $placeholder = $_POST['col_placeholder'] ?? [];
            $step = $_POST['col_step'] ?? [];
            $maxlength = $_POST['col_maxlength'] ?? [];
            $humanPrimary = array_map('intval', $_POST['col_HumanPrimary'] ?? []);
            $onlyShow = $_POST['col_OnlyShowIfThisIsTrue'] ?? [];
            $price = array_map('intval', $_POST['col_price'] ?? []);
            $showPreview = array_map('intval', $_POST['col_ShowInPreviewCard'] ?? []);
            $showPublic = array_map('intval', $_POST['col_ShowToPublic'] ?? []);
            $showEntryMarker = array_map('intval', $_POST['col_ShowWholeEntryToPublicMarker'] ?? []);
            $colOldNames = $_POST['col_oldname'] ?? [];
            $colOldDBTypes = $_POST['col_oldDBType'] ?? [];
            $colInvisible = array_map('intval', $_POST['col_invisible'] ?? []);

            $count = count($colNames);
            for ($i=0; $i<$count; $i++) {
                if (!empty($colInvisible[$i])) { continue; }
                $cname = clean_input($colNames[$i] ?? '');
                if ($cname === '') continue;
                $cid = isset($colIds[$i]) ? filter_var($colIds[$i], FILTER_VALIDATE_INT) : null;
                $ctype = clean_input($types[$i] ?? '');
                $clabel = clean_input($labels[$i] ?? '');
                $cdbtype = clean_input($dbtypes[$i] ?? '');
                $csys = in_array($i, $systemOnly) ? 1 : 0;
                $cdbmarks = clean_input($dbmarks[$i] ?? '');
                $clinkT = isset($linkedTable[$i]) && $linkedTable[$i] !== '' ? filter_var($linkedTable[$i], FILTER_VALIDATE_INT) : null;
                $clinkF = clean_input($linkedField[$i] ?? '');
                $cisGen = in_array($i, $isLinkedGen) ? 1 : 0;
                $cplace = clean_input($placeholder[$i] ?? '');
                $cstep = clean_input($step[$i] ?? '');
                $cmax = isset($maxlength[$i]) && $maxlength[$i] !== ''
                    ? filter_var($maxlength[$i], FILTER_VALIDATE_INT)
                    : null;
                $chuman = in_array($i, $humanPrimary) ? 1 : 0;
                $cshowIf = isset($onlyShow[$i]) && $onlyShow[$i] !== ''
                    ? filter_var($onlyShow[$i], FILTER_VALIDATE_INT)
                    : null;
                $cprice = in_array($i, $price) ? 1 : 0;
                $cprev = in_array($i, $showPreview) ? 1 : 0;
                $cpub = in_array($i, $showPublic) ? 1 : 0;
                $centry = in_array($i, $showEntryMarker) ? 1 : 0;
                $oldCName = clean_input($colOldNames[$i] ?? '');
                $oldCType = clean_input($colOldDBTypes[$i] ?? '');

                if ($table_id) {
                    if ($cid) {
                        $stmtOld = $pdo->prepare('SELECT name, DBType FROM columns WHERE idpk=?');
                        $stmtOld->execute([$cid]);
                        $oldCol = $stmtOld->fetch(PDO::FETCH_ASSOC);
                        $stmt = $pdo->prepare('UPDATE columns SET name=?, type=?, label=?, SystemOnly=?, DBType=?, DBMarks=?, LinkedToWhatTable=?, LinkedToWhatFieldThere=?, IsLinkedTableGeneralTable=?, placeholder=?, step=?, maxlength=?, HumanPrimary=?, OnlyShowIfThisIsTrue=?, price=?, ShowInPreviewCard=?, ShowToPublic=?, ShowWholeEntryToPublicMarker=? WHERE idpk=? AND IdpkOfTable=?');
                        $stmt->execute([$cname,$ctype,$clabel,$csys,$cdbtype,$cdbmarks,$clinkT,$clinkF,$cisGen,$cplace,$cstep,$cmax,$chuman,$cshowIf,$cprice,$cprev,$cpub,$centry,$cid,$table_id]);
                        if ($oldCol && ($oldCol['name'] !== $cname || $oldCol['DBType'] !== $cdbtype)) {
                            stargate_exec("ALTER TABLE `{$name}` CHANGE `{$oldCol['name']}` `{$cname}` {$cdbtype}");
                        }
                        apply_dbmarks($name, $cname, $cdbtype, $cdbmarks);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO columns (name,type,label,SystemOnly,DBType,DBMarks,LinkedToWhatTable,LinkedToWhatFieldThere,IsLinkedTableGeneralTable,placeholder,step,maxlength,HumanPrimary,OnlyShowIfThisIsTrue,price,ShowInPreviewCard,ShowToPublic,ShowWholeEntryToPublicMarker,IdpkOfTable,TimestampCreation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                        $stmt->execute([$cname,$ctype,$clabel,$csys,$cdbtype,$cdbmarks,$clinkT,$clinkF,$cisGen,$cplace,$cstep,$cmax,$chuman,$cshowIf,$cprice,$cprev,$cpub,$centry,$table_id,$now]);
                        stargate_exec("ALTER TABLE `{$name}` ADD `{$cname}` {$cdbtype}");
                        apply_dbmarks($name, $cname, $cdbtype, $cdbmarks);
                    }
                } elseif ($existingBackend) {
                    if ($oldCName) {
                        if ($oldCName !== $cname || $oldCType !== $cdbtype) {
                            stargate_exec("ALTER TABLE `{$name}` CHANGE `{$oldCName}` `{$cname}` {$cdbtype}");
                        }
                        apply_dbmarks($name, $cname, $cdbtype, $cdbmarks);
                    } else {
                        stargate_exec("ALTER TABLE `{$name}` ADD `{$cname}` {$cdbtype}");
                        apply_dbmarks($name, $cname, $cdbtype, $cdbmarks);
                    }
                }
            }

            $success = 'Table and columns saved successfully!';
            if (isset($_POST['save_table_return'])) {
                echo "<script>window.location.href='SetupDatabases.php';</script>";
                exit;
            }
            if ($table_id) {
                $_GET['idpk'] = $table_id;
            } else {
                $_GET['tablename'] = $name;
            }
        } elseif(isset($_POST['remove_table'])) {
            $table_id = filter_input(INPUT_POST, 'idpk', FILTER_VALIDATE_INT);
            if ($table_id) {
                $stmtName = $pdo->prepare('SELECT name FROM tables WHERE idpk=? AND IdpkOfAdmin=?');
                $stmtName->execute([$table_id,$IdpkOfAdmin]);
                $tblName = $stmtName->fetchColumn();
                if ($tblName) {
                    stargate_exec("DROP TABLE `{$tblName}`");
                    delete_table_directory($tblName);
                }
                $pdo->prepare('DELETE FROM columns WHERE IdpkOfTable=?')->execute([$table_id]);
                $pdo->prepare('DELETE FROM tables WHERE idpk=? AND IdpkOfAdmin=?')->execute([$table_id,$IdpkOfAdmin]);
                $success = 'Table removed successfully!';
                echo "<script>window.location.href='SetupDatabases.php';</script>";
                exit;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// fetch tables and columns
$stmt = $pdo->prepare('SELECT * FROM tables WHERE IdpkOfAdmin=? ORDER BY name ASC');
$stmt->execute([$IdpkOfAdmin]);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

$columns_by_table = [];
if ($tables) {
    $ids = array_column($tables,'idpk');
    $in = implode(',', array_fill(0,count($ids),'?'));
    $stmtc = $pdo->prepare("SELECT * FROM columns WHERE IdpkOfTable IN ($in) ORDER BY idpk");
    $stmtc->execute($ids);
    foreach ($stmtc->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $columns_by_table[$c['IdpkOfTable']][] = $c;
    }
}

// fetch existing tables from backend via stargate
$remoteTables = [];
$remoteTablesFetched = false;
try {
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "\$stmt=\$pdo->query(\"SHOW TABLES\");\$r=\$stmt->fetchAll(PDO::FETCH_COLUMN);echo json_encode(\$r);"
    ];
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) { $basePath = ''; }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        $message = $responseData['message'] ?? [];
        $remoteTables = is_array($message) ? $message : (json_decode($message, true) ?? []);
        $remoteTablesFetched = true;
    }
} catch (Exception $e) {
    $remoteTables = [];
}

$localTableNames = array_column($tables, 'name');
$invisibleTables = [];
foreach ($remoteTables as $rt) {
    if (!in_array($rt, $localTableNames, true)) {
        $invisibleTables[] = ['name' => $rt];
    }
}
usort($invisibleTables, function($a, $b) { return strcmp($a['name'], $b['name']); });

// filter out and optionally remove table entries that no longer exist in the backend
if ($remoteTablesFetched) {
    $remoteTableSet = array_flip($remoteTables);
    foreach ($tables as $t) {
        if (!isset($remoteTableSet[$t['name']])) {
            $pdo->prepare('DELETE FROM columns WHERE IdpkOfTable=?')->execute([$t['idpk']]);
            $pdo->prepare('DELETE FROM tables WHERE idpk=?')->execute([$t['idpk']]);
        }
    }
    $tables = array_values(array_filter($tables, function($t) use ($remoteTableSet) {
        return isset($remoteTableSet[$t['name']]);
    }));
    $columns_by_table = array_intersect_key(
        $columns_by_table,
        array_flip(array_column($tables, 'idpk'))
    );
}

$newTableMode = false;
$editId = null;
 $editTableNameParam = null;
if (isset($_GET['idpk'])) {
    if ($_GET['idpk'] === 'new') {
        $newTableMode = true;
    } else {
        $editId = (int)$_GET['idpk'];
    }
} elseif (isset($_GET['tablename'])) {
    $editTableNameParam = clean_input($_GET['tablename']);
}
$editTable = null;
$editColumns = [];
if ($editId) {
    foreach ($tables as $t) {
        if ($t['idpk'] == $editId) { $editTable = $t; break; }
    }
    $editColumns = $columns_by_table[$editId] ?? [];
} elseif ($editTableNameParam) {
    $editTable = ['name' => $editTableNameParam];
}

// fetch backend columns for selected table
if ($editTable && isset($editTable['name'])) {
    $backendColumns = [];
    $backendColumnsFetched = false;
    try {
        $tableForQuery = addslashes($editTable['name']);
        $payload = [
            'APIKey' => $TRAMANNAPIAPIKey,
            'message' => "\$stmt=\$pdo->query(\"DESCRIBE `$tableForQuery`\");\$c=\$stmt->fetchAll(PDO::FETCH_ASSOC);echo json_encode(\$c);"
        ];
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
        if ($basePath === DIRECTORY_SEPARATOR) { $basePath = ''; }
        $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);
        $ch = curl_init($nexusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $respData = json_decode($response, true);
        if (isset($respData['status']) && $respData['status'] === 'success') {
            $message = $respData['message'] ?? [];
            $backendColumns = is_array($message) ? $message : (json_decode($message, true) ?? []);
            $backendColumnsFetched = true;
        }
    } catch (Exception $e) {
        $backendColumns = [];
    }

    $localColsByName = [];
    foreach ($editColumns as $c) {
        $localColsByName[$c['name']] = $c;
    }
    $merged = [];
    foreach ($backendColumns as $bc) {
        $fname = $bc['Field'] ?? '';
        if ($fname === '') continue;
        if (isset($localColsByName[$fname])) {
            $merged[] = $localColsByName[$fname];
            unset($localColsByName[$fname]);
        } else {
            $merged[] = ['name' => $fname, 'DBType' => $bc['Type'] ?? '', 'invisible' => true];
        }
    }
    if ($backendColumnsFetched) {
        foreach ($localColsByName as $c) {
            $pdo->prepare('DELETE FROM columns WHERE idpk=?')->execute([$c['idpk']]);
        }
    } else {
        foreach ($localColsByName as $c) {
            $merged[] = $c;
        }
    }
    $editColumns = $merged;
}
?>
<style>
    #columnsWrapper {
        overflow-x: auto;
        overflow-y: hidden;
        max-width: 100%;
        position: relative;
    }
    #columnsTable {
        border-collapse: collapse;
        width: max-content;
    }
    #columnsTable th,
    #columnsTable td {
        border: 1px solid var(--border-color);
        padding: 4px;
        white-space: nowrap;
        text-align: center;
    }
    #columnsTable th {
        background-color: var(--input-bg);
    }
    #columnsTable td input,
    #columnsTable th input {
        display:block;
        margin:auto;
    }
    #columnsTable input:not([type="checkbox"]) {
        min-width: 100px;
        text-align:left;
    }
    /* ensure the sticky name column matches other fields */
    #columnsTable td.sticky-name input {
        min-width: 200px;
    }
    #columnsTable input[type="checkbox"] {
        display:block;
        margin:0 auto;
    }
    #columnsTable th.sticky,
    #columnsTable td.sticky {
        position: sticky;
        left: 0;
        background: var(--bg-color);
        z-index: 3;
        width: 50px;
        overflow:hidden;
    }
    #columnsTable th.sticky,
    #columnsTable th.sticky-name {
        background-color: var(--input-bg);
    }
    #columnsTable th.sticky-name,
    #columnsTable td.sticky-name {
        left: 50px;
        z-index: 3;
        overflow:hidden;
        box-shadow: inset -2px 0 0 var(--border-color),
            2px 0 0px var(--border-color);
    }
    #columnsTable td.sticky,
    #columnsTable td.sticky-name,
    #columnsTable td.sticky-name input {
        font-weight: bold;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-color);
    }
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 1rem;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        background-color: var(--bg-color);
    }
    .human-primary .form-control {
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--primary-color);
    }
    #columnsTable th.selected,
    #columnsTable td.selected,
    #columnsTable td.selected input,
    #columnsTable td.selected select {
        color: var(--primary-color);
    }
    /* Give menu views the same card styling as forms but only here in this file */
    .container:has(.menu-nav) {
        background-color: var(--bg-color);
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px var(--shadow-color);
        overflow-x: auto;
    }
</style>
<div class="container" style="width: 1200px; max-width: 1200px; margin: auto; text-align: center;">
<h1 class="text-center">🧬 DATABASES SETUP</h1>
<?php if ($error): ?>
<div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (!$editId && !$newTableMode && !$editTableNameParam): ?>
    <div id="menuNav" class="menu-nav">
        <a href="?idpk=new" class="menu-item" style="border: 3px solid var(--primary-color);" title="add a new table">
            <div style="font-size: 2.5rem;">➕</div>
            <span class="menu-title">ADD TABLE</span>
        </a>
        <?php foreach ($tables as $t): ?>
            <a class="menu-item" href="?idpk=<?php echo $t['idpk']; ?>" title="<?php echo htmlspecialchars($t['name']) . ' | visible to system'; ?>">
                <div style="font-size: 2.5rem;">🧬</div>
                <span class="menu-title"><?php echo htmlspecialchars($t['name']) . ' (' . $t['idpk'] . ')'; ?></span>
            </a>
        <?php endforeach; ?>
        <?php foreach ($invisibleTables as $it): ?>
            <a class="menu-item" href="?tablename=<?php echo urlencode($it['name']); ?>" style="opacity:0.5;" title="<?php echo htmlspecialchars($it['name']) . ' | invisible to system'; ?>">
                <div style="font-size: 2.5rem;">🧬</div>
                <span class="menu-title"><?php echo htmlspecialchars($it['name']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <form method="POST" id="tableEditorForm">
        <input type="hidden" name="idpk" value="<?php echo $editTable['idpk'] ?? ''; ?>">
        <input type="hidden" name="original_name" value="<?php echo htmlspecialchars($editTable['name'] ?? ''); ?>">
        <?php if($newTableMode): ?>
        <input type="hidden" name="new_table_mode" value="1">
        <?php elseif(!isset($editTable['idpk']) && isset($editTable['name'])): ?>
        <input type="hidden" name="existing_backend" value="1">
        <?php endif; ?>
        <?php if(isset($editTable['idpk'])): ?>
        <div style="text-align:right;font-weight:bold;">idpk: <?php echo htmlspecialchars($editTable['idpk']); ?></div>
        <?php endif; ?>
        <div class="form-group human-primary" style="text-align:left;">
            <!-- <label for="table_name" style="text-align:left;">name</label> -->
            <input type="text" id="table_name" name="table_name" class="form-control" value="<?php echo htmlspecialchars($editTable['name'] ?? ''); ?>" required>
            <div class="form-group" style="text-align: left; margin-top: 10px;">
                <textarea id="further_information" name="further_information" rows="2" class="form-control"
                    style="font-size:1rem;font-weight:normal;color:var(--text-color);resize:vertical;<?php if(!isset($editTable['idpk'])) echo 'opacity:0.1;'; ?>"
                    placeholder="further information about this table" <?php if(!isset($editTable['idpk'])) echo 'disabled title="please make this table visible to the system first using the button at the bottom of the page"'; ?>><?php echo htmlspecialchars($editTable['FurtherInformation'] ?? ''); ?></textarea>
            </div>
        </div>
        <div id="columnsWrapper">
        <table id="columnsTable">
            <thead>
                <tr>
                    <?php
                        $idpkTitleVisible = 'idpk of the column of your table';
                        $idpkTitleHidden  = 'once you make this visible to the system, the idpk of the column of your table will appear here';
                        $currentIdpkTitle = isset($editTable['idpk']) ? $idpkTitleVisible : $idpkTitleHidden;
                    ?>
                    <th class="sticky" id="idpkHeader" data-visible-title="<?php echo htmlspecialchars($idpkTitleVisible); ?>" data-hidden-title="<?php echo htmlspecialchars($idpkTitleHidden); ?>" title="<?php echo htmlspecialchars($currentIdpkTitle); ?>">idpk</th>
                    <th class="sticky sticky-name" title="name of the column of your table">name</th>
                    <th title="type of the input format (for example: text, number, date, ...)">type</th>
                    <th title="the label shown above the input field">label</th>
                    <th title="database type (for example: text, int, varchar(250), decimal(10,2), ...)">database type</th>
                    <th title="check if only the system should use this (and not a human)">system only</th>
                    <th title="additional database information (for example: auto increment, primary key for your idpk)">database marks</th>
                    <th title="the other tables idpk, if this column is linked to another table">linked to table</th>
                    <th title="the name of the field in the referred table, if this column is linked to another table">linked to field</th>
                    <th title="check if you don't want entries from this table referred in the other, general table (for example: you want your sold products mentioned in your transactions table, but you don't want all the transactions loaded and listed underneath the corresponding product, the general tables entry)">linked table general</th>
                    <th title="placeholder text for the input field">placeholder</th>
                    <th title="step size for numbers (for example: 0.01 for prices)">step</th>
                    <th title="maximum length (for example a text with no more than 500 characters or a number with no more than 10 digits)">max length</th>
                    <th title="check if this is the field with which a human would identify this entry (for example: the name of a product, not the idpk, the idpk is for the system, the name is for the humans)">human primary</th>
                    <th title="add the name of another column which has to be true for this column to be shown (for example: only show the inventory level if the column ManageInventory is true)">depends on what</th>
                    <th title="check if this is a price">price</th>
                    <th title="check if this should be shown in entry previews">show in preview</th>
                    <th title="if at least one column is checked, the table will be shown to the public, the public then can see images and all columns where this is checked in the online shop for all entries, if you have an entry public marker, only entries where this marker is true (or 1) are shown (you can just add a boolean column to your table and declare it as entry public marker)">show to public</th>
                    <th title="if at least one column is checked as show to public, the table will be shown to the public, the public then can see images and all columns where this is checked in the online shop for all entries, if you have an entry public marker, only entries where this marker is true (or 1) are shown (you can just add a boolean column to your table and declare it as entry public marker here)">entry public marker</th>
                    <th title="click to switch the visibility of this column to the system">visibility</th>
                    <th title="click to remove the corresponding column">remove</th>
                </tr>
            </thead>
            <tbody id="columnsBody">
            </tbody>
        </table>
        </div>
        <div style="text-align:left;">
            <a href="#" onclick="addColumnRow(); return false;" style="display:inline-block;" title="all in all, it's just another brick in the wall, add now, regret later xd">➕ ADD COLUMN</a>
        </div>
        <br><br><br><br><br>
        <button type="submit" name="save_table_return" style="width:220px;margin-right:10px;" title="save and return">↗️ SAVE AND RETURN</button>
        <button type="submit" name="save_table" style="width:220px;opacity:0.5;" title="activate savepoint">↗️ SAVE</button>
        <br><br><br><br><br><br><br><br><br><br>
        <a id="toggleTableLink" href="#" onclick="toggleTableVisibility(<?php echo isset($editTable['idpk']) ? 'true' : 'false'; ?>); return false;" title="<?php echo isset($editTable['idpk']) ? 'activate stealth mode' : 'time to face the light'; ?>" style="opacity:<?php echo isset($editTable['idpk']) ? '0.2' : '1'; ?>;">👁️ <?php echo isset($editTable['idpk']) ? 'MAKE TABLE INVISIBLE' : 'MAKE TABLE VISIBLE'; ?></a>
        <br><br>
        <a href="#" style="opacity:0.2;" onclick="if(confirm('Are you really sure you want to remove this table, all its columns, all data in it and all attachments?')){const i=document.createElement('input');i.type='hidden';i.name='remove_table';i.value='1';this.closest('form').appendChild(i);this.closest('form').submit();}return false;" title="erase all evidence">❌ REMOVE TABLE</a>
    </form>
    <script>
    const typeOptions = ['text','number','datetime-local','textarea','checkbox','email'];
    const existingCols = <?php echo json_encode($editColumns); ?>;
    const columnSelectOptions = [{id:'',name:'none'}, ...existingCols.filter(c=>c.idpk).map(c => ({id:c.idpk,name:c.name}))];
    const currentTableName = <?php echo json_encode($editTable['name'] ?? ''); ?>;
    let currentTableId = <?php echo json_encode($editTable['idpk'] ?? null); ?>;
    const thTitles = Array.from(document.querySelectorAll('#columnsTable thead th')).map(th => th.getAttribute('title') || '');
    const furtherInfoField = document.getElementById('further_information');
    const idpkHeader = document.getElementById('idpkHeader');
    const idpkTitleVisible = idpkHeader ? idpkHeader.getAttribute('data-visible-title') : '';
    const idpkTitleHidden = idpkHeader ? idpkHeader.getAttribute('data-hidden-title') : '';

    function updateIdpkHeaderTitle() {
        if (!idpkHeader) return;
        const newTitle = currentTableId ? idpkTitleVisible : idpkTitleHidden;
        idpkHeader.title = newTitle;
        thTitles[0] = newTitle;
        document.querySelectorAll('#columnsBody td:first-child').forEach(td => td.title = newTitle);
    }

    function updateTableVisibilityLink() {
        const link = document.getElementById('toggleTableLink');
        if (!link) return;
        if (currentTableId) {
            link.style.opacity = '0.2';
            link.textContent = '👁️ MAKE TABLE INVISIBLE';
            link.title = 'activate stealth mode';
        } else {
            link.style.opacity = '1';
            link.textContent = '👁️ MAKE TABLE VISIBLE';
            link.title = 'time to face the light';
        }
    }
    function updateFurtherInfoField() {
        if (!furtherInfoField) return;
        if (currentTableId) {
            furtherInfoField.disabled = false;
            furtherInfoField.style.opacity = '';
            furtherInfoField.title = '';
            updateOpacity(furtherInfoField);
        } else {
            furtherInfoField.disabled = true;
            furtherInfoField.style.opacity = '0.1';
            furtherInfoField.title = 'please make this table visible to the system first using the button at the bottom of the page';
        }
    }
    updateTableVisibilityLink();
    if (furtherInfoField) {
        furtherInfoField.addEventListener('input', () => updateOpacity(furtherInfoField));
        furtherInfoField.addEventListener('change', () => updateOpacity(furtherInfoField));
    }
    updateFurtherInfoField();
    updateIdpkHeaderTitle();
    existingCols.forEach(c => addColumnRow(c));
    if (existingCols.length === 0) {
        addColumnRow();
    }

    const wrapper = document.getElementById('columnsWrapper');
    wrapper.addEventListener('wheel', function(e) {
        if (e.deltaY === 0) return;
        const maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
        if ((e.deltaY > 0 && wrapper.scrollLeft < maxScroll) ||
            (e.deltaY < 0 && wrapper.scrollLeft > 0)) {
            wrapper.scrollLeft += e.deltaY;
            e.preventDefault();
        }
    });

    function updateOpacity(input) {
        let val;
        if (input.type === 'checkbox') {
            val = input.checked ? 1 : 0;
        } else {
            val = input.value;
        }
        if (val === '' || val === null || val === 0 || val === '0') {
            input.style.opacity = '0.3';
        } else {
            input.style.opacity = '';
        }
    }

    function addColumnRow(data={}) {
        if (data.invisible === undefined) {
            data.invisible = !currentTableId;
        }
        const tbody = document.getElementById('columnsBody');
        const tr = document.createElement('tr');
        const typeSelect = `<select name="col_type[]" title="${thTitles[2]}">${typeOptions.map(t => `<option value="${t}" ${((data.type||'text')==t)?'selected':''}>${t}</option>`).join('')}</select>`;
        const dbTypeInput = `<input type="text" name="col_DBType[]" value="${data.DBType||''}" title="${thTitles[4]}">`;
        const onlyShowSelect = `<select name="col_OnlyShowIfThisIsTrue[]" title="${thTitles[14]}">${columnSelectOptions.map(o => `<option value="${o.id}" ${((data.OnlyShowIfThisIsTrue||'')==o.id)?'selected':''}>${o.name}</option>`).join('')}</select>`;
        const dbMarksOptions = ["", "auto increment, primary key"];
        if (data.DBMarks && !dbMarksOptions.includes(data.DBMarks)) { dbMarksOptions.push(data.DBMarks); }
        const dbMarksSelect = `<select name="col_DBMarks[]" title="${thTitles[6]}">${dbMarksOptions.map(o => `<option value="${o}" ${((data.DBMarks||'')==o)?'selected':''}>${o}</option>`).join('')}</select>`;
        tr.innerHTML = `
            <td class="sticky" title="${thTitles[0]}">${data.idpk || ''}<input type="hidden" name="col_id[]" value="${data.idpk||''}"><input type="hidden" name="col_invisible[]" value="${data.invisible?1:0}"><input type="hidden" name="col_oldname[]" value="${data.name||''}"><input type="hidden" name="col_oldDBType[]" value="${data.DBType||''}"></td>
            <td class="sticky sticky-name" title="${thTitles[1]}"><input type="text" name="col_name[]" value="${data.name||''}" required title="${thTitles[1]}"></td>
            <td title="${thTitles[2]}">${typeSelect}</td>
            <td title="${thTitles[3]}"><input type="text" name="col_label[]" value="${data.label||''}" title="${thTitles[3]}"></td>
            <td title="${thTitles[4]}">${dbTypeInput}</td>
            <td title="${thTitles[5]}"><input type="checkbox" name="col_SystemOnly[]" value="0" ${data.SystemOnly==1?'checked':''} title="${thTitles[5]}"></td>
            <td title="${thTitles[6]}">${dbMarksSelect}</td>
            <td title="${thTitles[7]}"><input type="number" name="col_LinkedToWhatTable[]" value="${data.LinkedToWhatTable||''}" title="${thTitles[7]}"></td>
            <td title="${thTitles[8]}"><input type="text" name="col_LinkedToWhatFieldThere[]" value="${data.LinkedToWhatFieldThere||''}" title="${thTitles[8]}"></td>
            <td title="${thTitles[9]}"><input type="checkbox" name="col_IsLinkedTableGeneralTable[]" value="0" ${data.IsLinkedTableGeneralTable==1?'checked':''} title="${thTitles[9]}"></td>
            <td title="${thTitles[10]}"><input type="text" name="col_placeholder[]" value="${data.placeholder||''}" title="${thTitles[10]}"></td>
            <td title="${thTitles[11]}"><input type="text" name="col_step[]" value="${data.step||''}" title="${thTitles[11]}"></td>
            <td title="${thTitles[12]}"><input type="number" name="col_maxlength[]" value="${data.maxlength||''}" title="${thTitles[12]}"></td>
            <td title="${thTitles[13]}"><input type="checkbox" name="col_HumanPrimary[]" value="0" ${data.HumanPrimary==1?'checked':''} title="${thTitles[13]}"></td>
            <td title="${thTitles[14]}">${onlyShowSelect}</td>
            <td title="${thTitles[15]}"><input type="checkbox" name="col_price[]" value="0" ${data.price==1?'checked':''} title="${thTitles[15]}"></td>
            <td title="${thTitles[16]}"><input type="checkbox" name="col_ShowInPreviewCard[]" value="0" ${data.ShowInPreviewCard==1?'checked':''} title="${thTitles[16]}"></td>
            <td title="${thTitles[17]}"><input type="checkbox" name="col_ShowToPublic[]" value="0" ${data.ShowToPublic==1?'checked':''} title="${thTitles[17]}"></td>
            <td title="${thTitles[18]}"><input type="checkbox" name="col_ShowWholeEntryToPublicMarker[]" value="0" ${data.ShowWholeEntryToPublicMarker==1?'checked':''} title="${thTitles[18]}"></td>
            <td title="${thTitles[19]}"><a href="#" style="opacity:0.2;" onclick="toggleColumnVisibility('${data.name||''}', ${data.idpk ?? 'null'}, ${data.invisible?1:0}, '${data.DBType||''}'); return false;" title="${thTitles[19]}">👁️</a></td>
            <td title="${thTitles[20]}"><a href="#" style="opacity:0.2;" onclick="removeColumnRow(this, ${data.idpk||null}); return false;" title="${thTitles[20]}">❌</a></td>`;
        tbody.appendChild(tr);
        tr.querySelectorAll('input, select').forEach(inp => {
            updateOpacity(inp);
            inp.addEventListener('input', () => updateOpacity(inp));
            inp.addEventListener('change', () => updateOpacity(inp));
        });
        if (data.invisible) {
            tr.style.opacity = '0.5';
            tr.querySelectorAll('input, select').forEach(inp => {
                if (inp.name !== 'col_name[]' && inp.name !== 'col_DBType[]') {
                    inp.disabled = true;
                    inp.style.opacity = '0.1';
                }
                inp.title = 'please make this column visible to the system first using the button at the right of this row';
            });
        }
        updateCheckboxValues();
    }

    function toggleColumnVisibility(name, id, invisible, dbType) {
        if (!invisible && !confirm('Do you really want to make this column invisible to the system?')) { return; }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        const add = (n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);};
        add('toggle_column_visibility','1');
        add('column_name',name);
        add('table_name',currentTableName);
        if (currentTableId) add('table_id',currentTableId);
        if (id) add('column_id',id);
        add('db_type',dbType);
        add('make_visible', invisible ? '1' : '0');
        if (invisible && !currentTableId) {
            currentTableId = 1;
            updateTableVisibilityLink();
            updateFurtherInfoField();
            updateIdpkHeaderTitle();
        }
        document.body.appendChild(form);
        form.submit();
    }
    function removeColumnRow(btn, id) {
        if (confirm('Remove this column?')) {
            const form = document.getElementById('tableEditorForm');
            const row = btn.closest('tr');
            if (id) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'remove_col_ids[]';
                hidden.value = id;
                form.appendChild(hidden);
            } else {
                const oldName = row.querySelector('input[name="col_oldname[]"]').value;
                if (oldName) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'remove_col_backend[]';
                    hidden.value = oldName;
                    form.appendChild(hidden);
                }
            }
            row.remove();
            updateCheckboxValues();
        }
    }

    function updateCheckboxValues() {
        const rows = document.querySelectorAll('#columnsBody tr');
        rows.forEach((row, idx) => {
            row.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.value = idx;
            });
        });
    }

    const columnsTable = document.getElementById('columnsTable');
    columnsTable.addEventListener('focusin', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') {
            highlightSelection(e.target.closest('td'));
        }
    });
    columnsTable.addEventListener('change', function(e) {
        if (e.target.name === 'col_HumanPrimary[]' && e.target.checked) {
            columnsTable.querySelectorAll('input[name="col_HumanPrimary[]"]').forEach(cb => {
                if (cb !== e.target) {
                    cb.checked = false;
                    updateOpacity(cb);
                }
            });
        } else if (e.target.name === 'col_ShowWholeEntryToPublicMarker[]' && e.target.checked) {
            columnsTable.querySelectorAll('input[name="col_ShowWholeEntryToPublicMarker[]"]').forEach(cb => {
                if (cb !== e.target) {
                    cb.checked = false;
                    updateOpacity(cb);
                }
            });
        }
    });
    columnsTable.addEventListener('focusout', function(e) {
        if (!columnsTable.querySelector('input:focus') &&
            !columnsTable.querySelector('select:focus')) {
            clearHighlights();
        }
    });

    function highlightSelection(td) {
        clearHighlights();
        if (!td) return;
        const tr = td.parentElement;
        const cells = tr.children;
        if (cells[0]) cells[0].classList.add('selected');
        if (cells[1]) cells[1].classList.add('selected');
        const index = Array.prototype.indexOf.call(cells, td) + 1;
        const th = columnsTable.querySelector('thead th:nth-child(' + index + ')');
        if (th) th.classList.add('selected');
    }

    function clearHighlights() {
        columnsTable.querySelectorAll('th.selected, td.selected').forEach(el => el.classList.remove('selected'));
    }
    function toggleTableVisibility(isVisible) {
        if (isVisible && !confirm('Do you really want to make this table and all columns invisible to the system?')) { return; }
        const form = document.createElement('form');
        form.method='POST';
        form.action='';
        const add=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);};
        add('toggle_table_visibility','1');
        add('table_name', document.getElementById('table_name').value);
        if (isVisible && currentTableId) { add('table_id', currentTableId); }
        document.body.appendChild(form);
        form.submit();
    }
    </script>
<?php endif; ?>
</div>
<?php require_once('footer.php'); ob_end_flush(); ?>
