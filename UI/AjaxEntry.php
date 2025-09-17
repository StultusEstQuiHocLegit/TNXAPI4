<?php
session_start();
require_once('../config.php');
require_once('../SETUP/DatabaseTablesStructure.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';

// Helper function to check if user has access to an entry
function checkEntryAccess($pdo, $table, $idpk) {
    // Validate table name to prevent SQL injection
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    
    // Validate idpk
    if (!is_numeric($idpk)) {
        return false;
    }
    
    try {
        // Check if the entry exists and belongs to the user's admin
        $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
        $stmt->bindParam(':idpk', $idpk, PDO::PARAM_INT);
        $stmt->bindParam(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Database error in checkEntryAccess: " . $e->getMessage());
        return false;
    }
}

// Helper function to get all files for an entry
function getEntryFiles($uploadDir, $idpk) {
    $files = glob($uploadDir . $idpk . "_*.*");
    $fileList = [];
    $usedNumbers = []; // Track used numbers regardless of extension
    
    foreach ($files as $file) {
        $fileName = basename($file);
        if (preg_match('/^' . $idpk . '_(\d+)\.(.+)$/', $fileName, $matches)) {
            $number = (int)$matches[1];
            $usedNumbers[] = $number; // Track this number as used
            $fileList[] = [
                'number' => $number,
                'extension' => $matches[2],
                'fullName' => $fileName,
                'path' => $file
            ];
        }
    }
    
    // Sort by number
    usort($fileList, function($a, $b) {
        return $a['number'] - $b['number'];
    });
    
    // Find the next available number
    $nextNumber = 0;
    while (in_array($nextNumber, $usedNumbers)) {
        $nextNumber++;
    }
    
    return [
        'files' => $fileList,
        'nextNumber' => $nextNumber
    ];
}

// Helper function to renumber files
function renumberFiles($uploadDir, $idpk) {
    $result = getEntryFiles($uploadDir, $idpk);
    $files = $result['files'];
    $newNumber = 0;
    
    foreach ($files as $file) {
        if ($file['number'] !== $newNumber) {
            $oldPath = $file['path'];
            $newName = $idpk . "_" . $newNumber . "." . $file['extension'];
            $newPath = $uploadDir . $newName;
            rename($oldPath, $newPath);
        }
        $newNumber++;
    }
}






































// Handle file removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_file') {
    $table = $_POST['table'];
    $idpk = $_POST['idpk'];
    $fileName = $_POST['file_name'];

    // Prepare Stargate payload
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "
            \$uploadDir = './UPLOADS/$table/';
            \$filePath = \$uploadDir . '$fileName';

            if (file_exists(\$filePath) && strpos('$fileName', '$idpk' . '_') === 0) {
                if (unlink(\$filePath)) {
                    function getEntryFiles(\$uploadDir, \$idpk) {
                        \$files = glob(\$uploadDir . \$idpk . '_*.*');
                        \$fileList = [];
                        \$usedNumbers = [];
                        foreach (\$files as \$file) {
                            \$fileName = basename(\$file);
                            if (preg_match('/^' . \$idpk . '_(\d+)\\.(.+)\$/', \$fileName, \$matches)) {
                                \$number = (int)\$matches[1];
                                \$usedNumbers[] = \$number;
                                \$fileList[] = [
                                    'number' => \$number,
                                    'extension' => \$matches[2],
                                    'fullName' => \$fileName,
                                    'path' => \$file
                                ];
                            }
                        }
                        usort(\$fileList, function(\$a, \$b) {
                            return \$a['number'] - \$b['number'];
                        });
                        \$nextNumber = 0;
                        while (in_array(\$nextNumber, \$usedNumbers)) {
                            \$nextNumber++;
                        }
                        return ['files' => \$fileList, 'nextNumber' => \$nextNumber];
                    }

                    function renumberFiles(\$uploadDir, \$idpk) {
                        \$result = getEntryFiles(\$uploadDir, \$idpk);
                        \$files = \$result['files'];
                        \$newNumber = 0;
                        foreach (\$files as \$file) {
                            if (\$file['number'] !== \$newNumber) {
                                \$oldPath = \$file['path'];
                                \$newName = \$idpk . '_' . \$newNumber . '.' . \$file['extension'];
                                \$newPath = \$uploadDir . \$newName;
                                rename(\$oldPath, \$newPath);
                            }
                            \$newNumber++;
                        }
                    }

                    renumberFiles(\$uploadDir, '$idpk');
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to remove file']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'File not found or access denied']);
            }
        "
    ];

    // Build Stargate endpoint
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    // cURL call to Stargate
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Decode response and return result
    $responseData = json_decode($response, true);

    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        $remoteMessage = $responseData['message'];
        $decodedMessage = is_string($remoteMessage) ? json_decode($remoteMessage, true) : $remoteMessage;

        if (isset($decodedMessage['success']) && $decodedMessage['success'] === true) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $decodedMessage['error'] ?? 'Remote reported failure']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Remote file removal failed']);
    }
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_file') {
//     $table = $_POST['table'];
//     $idpk = $_POST['idpk'];
//     $fileName = $_POST['file_name'];
//     
//     // First check if user has access to this entry
//     if (!checkEntryAccess($pdo, $table, $idpk)) {
//         echo json_encode(['success' => false, 'error' => 'Access denied']);
//         exit;
//     }
//     
//     $uploadDir = "../UPLOADS/TDB/$table/";
//     $filePath = $uploadDir . $fileName;
//     
//     // Verify the file exists and belongs to this entry
//     if (file_exists($filePath) && strpos($fileName, $idpk . '_') === 0) {
//         if (unlink($filePath)) {
//             // Renumber remaining files
//             renumberFiles($uploadDir, $idpk);
//             echo json_encode(['success' => true]);
//         } else {
//             echo json_encode(['success' => false, 'error' => 'Failed to remove file']);
//         }
//     } else {
//         echo json_encode(['success' => false, 'error' => 'File not found or access denied']);
//     }
//     exit;
// }

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $table = $_POST['table'];
    $idpk = $_POST['idpk'];

    // Create a temporary location to hold the uploaded file before forwarding
    $tempPath = '../UPLOADS/tmp/' . mt_rand(1000000000, 9999999999) . '_' . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file to temp location']);
        exit;
    }

    // Read the file content to pass to Stargate as base64
    $fileContent = file_get_contents($tempPath);
    $base64File = base64_encode($fileContent);
    $originalFileName = basename($file['name']);

    // Build payload for Stargate
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "
            \$uploadDir = './UPLOADS/$table/';
            if (!file_exists(\$uploadDir)) {
                mkdir(\$uploadDir, 0777, true);
            }

            \$idpk = '$idpk';
            \$extension = strtolower(pathinfo('$originalFileName', PATHINFO_EXTENSION));

            function getEntryFiles(\$uploadDir, \$idpk) {
                \$files = glob(\$uploadDir . \$idpk . '_*.*');
                \$fileList = [];
                \$usedNumbers = [];
                foreach (\$files as \$file) {
                    \$fileName = basename(\$file);
                    if (preg_match('/^' . \$idpk . '_(\\d+)\\.(.+)\$/', \$fileName, \$matches)) {
                        \$number = (int)\$matches[1];
                        \$usedNumbers[] = \$number;
                        \$fileList[] = [
                            'number' => \$number,
                            'extension' => \$matches[2],
                            'fullName' => \$fileName,
                            'path' => \$file
                        ];
                    }
                }
                usort(\$fileList, function(\$a, \$b) {
                    return \$a['number'] - \$b['number'];
                });
                \$nextNumber = 0;
                while (in_array(\$nextNumber, \$usedNumbers)) {
                    \$nextNumber++;
                }
                return ['files' => \$fileList, 'nextNumber' => \$nextNumber];
            }

            \$result = getEntryFiles(\$uploadDir, \$idpk);
            \$nextNumber = \$result['nextNumber'];
            \$newFileName = \$idpk . '_' . \$nextNumber . '.' . \$extension;
            \$newFilePath = \$uploadDir . \$newFileName;

            \$base64Content = '" . $base64File . "';
            \$binaryData = base64_decode(\$base64Content);

            if (file_put_contents(\$newFilePath, \$binaryData) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save file on server']);
            }
        "
    ];

    // Prepare nexus endpoint
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    // Send cURL request to Stargate
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Remove temporary file
    if (file_exists($tempPath)) {
        unlink($tempPath);
    }

    // Handle response
    $responseData = json_decode($response, true);

    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        $remoteMessage = $responseData['message'];
        $decodedMessage = is_string($remoteMessage) ? json_decode($remoteMessage, true) : $remoteMessage;

        if (isset($decodedMessage['success']) && $decodedMessage['success'] === true) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $decodedMessage['error'] ?? 'Remote save failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Remote upload failed']);
    }

    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
//     $file = $_FILES['file'];
//     $table = $_POST['table'];
//     $idpk = $_POST['idpk'];
//     
//     // First check if user has access to this entry
//     if (!checkEntryAccess($pdo, $table, $idpk)) {
//         echo json_encode(['success' => false, 'error' => 'Access denied']);
//         exit;
//     }
//     
//     // Set upload directory
//     $uploadDir = "../UPLOADS/TDB/$table/";
//     
//     // Create directory if it doesn't exist
//     if (!file_exists($uploadDir)) {
//         mkdir($uploadDir, 0777, true);
//     }
//     
//     // Get file extension
//     $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
//     
//     // Get all existing files and find the next number
//     $result = getEntryFiles($uploadDir, $idpk);
//     $nextNumber = $result['nextNumber'];
//     
//     // Generate new filename
//     $newFileName = $idpk . "_" . $nextNumber . "." . $extension;
//     $newFilePath = $uploadDir . $newFileName;
//     
//     // Move uploaded file
//     if (move_uploaded_file($file['tmp_name'], $newFilePath)) {
//         echo json_encode(['success' => true]);
//     } else {
//         echo json_encode(['success' => false, 'error' => 'Failed to save file']);
//     }
//     exit;
// }

// Handle file reordering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder_files') {
    $table = $_POST['table'];
    $idpk = $_POST['idpk'];
    $files = json_decode($_POST['files'], true);
    
    if (!is_array($files)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file list']);
        exit;
    }
    
    // Prepare Stargate payload
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "
            \$uploadDir = './UPLOADS/$table/';
            \$tempDir = \$uploadDir . 'temp_' . uniqid() . '/';
            mkdir(\$tempDir, 0777, true);

            \$files = " . var_export($files, true) . ";

            foreach (\$files as \$index => \$fileName) {
                \$oldPath = \$uploadDir . \$fileName;
                \$extension = pathinfo(\$fileName, PATHINFO_EXTENSION);
                \$newName = '$idpk' . '_' . \$index . '.' . \$extension;
                \$tempPath = \$tempDir . \$newName;

                if (file_exists(\$oldPath)) {
                    rename(\$oldPath, \$tempPath);
                }
            }

            \$tempFiles = glob(\$tempDir . '*.*');
            foreach (\$tempFiles as \$tempFile) {
                \$newName = basename(\$tempFile);
                rename(\$tempFile, \$uploadDir . \$newName);
            }

            rmdir(\$tempDir);

            echo json_encode(['success' => true]);
        "
    ];

    // Call Stargate
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Parse and return result
    $responseData = json_decode($response, true);

    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        $remoteMessage = $responseData['message'];

        if (is_string($remoteMessage)) {
            $decodedMessage = json_decode($remoteMessage, true);
        } else {
            $decodedMessage = $remoteMessage;
        }

        if (isset($decodedMessage['success']) && $decodedMessage['success'] === true) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $decodedMessage['error'] ?? 'Remote reported failure']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Remote reorder failed']);
    }
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder_files') {
//     $table = $_POST['table'];
//     $idpk = $_POST['idpk'];
//     $files = json_decode($_POST['files'], true);
//     
//     // First check if user has access to this entry
//     if (!checkEntryAccess($pdo, $table, $idpk)) {
//         echo json_encode(['success' => false, 'error' => 'Access denied']);
//         exit;
//     }
//     
//     if (!is_array($files)) {
//         echo json_encode(['success' => false, 'error' => 'Invalid file list']);
//         exit;
//     }
//     
//     $uploadDir = "../UPLOADS/TDB/$table/";
//     
//     try {
//         // Create temporary directory for the reordering process
//         $tempDir = $uploadDir . 'temp_' . uniqid() . '/';
//         mkdir($tempDir, 0777, true);
//         
//         // First move all files to temp directory with new names
//         foreach ($files as $index => $fileName) {
//             $oldPath = $uploadDir . $fileName;
//             $extension = pathinfo($fileName, PATHINFO_EXTENSION);
//             $newName = $idpk . "_" . $index . "." . $extension;
//             $tempPath = $tempDir . $newName;
//             
//             if (file_exists($oldPath)) {
//                 rename($oldPath, $tempPath);
//             }
//         }
//         
//         // Then move all files back to the main directory
//         $tempFiles = glob($tempDir . '*.*');
//         foreach ($tempFiles as $tempFile) {
//             $newName = basename($tempFile);
//             rename($tempFile, $uploadDir . $newName);
//         }
//         
//         // Clean up temp directory
//         rmdir($tempDir);
//         
//         echo json_encode(['success' => true]);
//     } catch (Exception $e) {
//         // If anything fails, try to restore the original state
//         if (file_exists($tempDir)) {
//             $tempFiles = glob($tempDir . '*.*');
//             foreach ($tempFiles as $tempFile) {
//                 $newName = basename($tempFile);
//                 if (file_exists($uploadDir . $newName)) {
//                     unlink($uploadDir . $newName);
//                 }
//                 rename($tempFile, $uploadDir . $newName);
//             }
//             rmdir($tempDir);
//         }
//         echo json_encode(['success' => false, 'error' => 'Failed to reorder files']);
//     }
//     exit;
// }




















// Handle create request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $table = $_POST['table'] ?? '';

    // Basic security checks
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        echo json_encode(['success' => false, 'error' => 'invalid table']);
        exit;
    }

    // Stargate API payload
    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "\$stmt = \$pdo->prepare(\"INSERT INTO `$table` () VALUES ()\");
                      \$stmt->execute();
                      \$newIdpk = \$pdo->lastInsertId();
                      echo json_encode(['newIdpk' => \$newIdpk]);"
    ];

    // Masked payload log
    $maskedPayload = $payload;
    $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
    $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
    if ($maskedJson === false) {
        $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
    }

    // Build URL
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    // cURL to Stargate
    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Parse and log
    $responseData = json_decode($response, true);

    // Final output
    echo $response;
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
//     $table = $_POST['table'] ?? '';
// 
//     // Basic security checks
//     if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
//         echo json_encode(['success' => false, 'error' => 'invalid table']);
//         exit;
//     }
// 
//     $adminIdpk = $_SESSION['IdpkOfAdmin'] ?? null;
//     if (!$adminIdpk) {
//         echo json_encode(['success' => false, 'error' => 'unauthorized']);
//         exit;
//     }
// 
//     try {
//         // Create the new row with at least IdpkOfAdmin
//         $stmt = $pdo->prepare("INSERT INTO `$table` (`IdpkOfAdmin`) VALUES (:adminIdpk)");
//         $stmt->execute([':adminIdpk' => $adminIdpk]);
//         $newIdpk = $pdo->lastInsertId();
// 
//         echo json_encode([
//             'success' => true,
//             'newIdpk' => $newIdpk
//         ]);
//         exit;
//     } catch (PDOException $e) {
//         error_log("Create error: " . $e->getMessage());
//         echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
//         exit;
//     }
// }

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $table = $_POST['table'] ?? '';
    $idpk = $_POST['idpk'] ?? '';
    $fieldsConfig = json_decode($_POST['searchFieldsUpdating'] ?? '[]', true);

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        echo json_encode(['success' => false, 'error' => 'invalid table']);
        exit;
    }
    if (!is_numeric($idpk)) {
        echo json_encode(['success' => false, 'error' => 'invalid idpk']);
        exit;
    }

    try {
        $checkboxNames = array_keys(array_filter($fieldsConfig, fn($cfg) => $cfg['type'] === 'checkbox'));
        foreach ($checkboxNames as $chk) {
            if (!array_key_exists($chk, $_POST)) {
                $_POST[$chk] = '';
            }
        }

        $updateFields = [];
        $params = [];

        foreach ($_POST as $key => $value) {
            if (in_array($key, ['action','table','idpk','searchFields', 'searchFieldsUpdating'], true)) continue;

            $updateFields[] = "`$key` = :$key";

            if (in_array($key, $checkboxNames, true)) {
                $params[":$key"] = ($value === 'on' ? 1 : 0);
                continue;
            }

            if (isset($fieldsConfig[$key]['type'])) {
                $type = $fieldsConfig[$key]['type'];

                if (in_array($type, ['date','time','datetime-local','month','week'], true)) {
                    if ($value === '') {
                        $params[":$key"] = null;
                    } else {
                        $dt = new DateTime($value);
                        switch ($type) {
                            case 'date': $params[":$key"] = $dt->format('Y-m-d'); break;
                            case 'time': $params[":$key"] = $dt->format('H:i:s'); break;
                            default: $params[":$key"] = $dt->format('Y-m-d H:i:s');
                        }
                    }
                    continue;
                }

                if (in_array($type, ['number', 'range', 'int', 'float', 'decimal'], true)) {
                    $params[":$key"] = ($value === '') ? null : $value;
                    continue;
                }
            }

            $params[":$key"] = $value;
        }

        $params[':idpk'] = $idpk;
        $updateQuery = "UPDATE `$table` SET " . implode(', ', $updateFields) . " WHERE idpk = :idpk";

        // Stargate payload
        $payload = [
            'APIKey' => $TRAMANNAPIAPIKey,
            'message' => '$stmt = $pdo->prepare(' . json_encode($updateQuery) . ');
                          $stmt->execute(' . var_export($params, true) . ');
                          echo json_encode(["success" => true]);'
        ];

        $maskedPayload = $payload;
        $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
        $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
        if ($maskedJson === false) {
            $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
        }

        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
        if ($basePath === DIRECTORY_SEPARATOR) {
            $basePath = '';
        }
        $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

        $ch = curl_init($nexusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $responseData['message'] ?? 'Unknown error']);
        }
        exit;

    } catch (Exception $e) {
        error_log("Stargate update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
        exit;
    }
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
//     $table = $_POST['table'] ?? '';
//     $idpk = $_POST['idpk'] ?? '';
//     $fieldsConfig = json_decode($_POST['searchFieldsUpdating'] ?? '[]', true);
// 
//     if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
//         echo json_encode(['success' => false, 'error' => 'invalid table']);
//         exit;
//     }
//     if (!is_numeric($idpk)) {
//         echo json_encode(['success' => false, 'error' => 'invalid idpk']);
//         exit;
//     }
//     if (!checkEntryAccess($pdo, $table, $idpk)) {
//         echo json_encode(['success' => false, 'error' => 'access denied']);
//         exit;
//     }
// 
//     try {
//         $checkboxNames = array_keys(array_filter($fieldsConfig, fn($cfg) => $cfg['type'] === 'checkbox'));
//         foreach ($checkboxNames as $chk) {
//             if (!array_key_exists($chk, $_POST)) {
//                 $_POST[$chk] = '';
//             }
//         }
// 
//         $updateFields = [];
//         $params = [];
// 
//         foreach ($_POST as $key => $value) {
//             if (in_array($key, ['action','table','idpk','searchFields', 'searchFieldsUpdating'], true)) continue;
// 
//             $updateFields[] = "$key = :$key";
// 
//             if (in_array($key, $checkboxNames, true)) {
//                 $params[":$key"] = ($value === 'on' ? 1 : 0);
//                 continue;
//             }
// 
//             if (isset($fieldsConfig[$key]['type'])) {
//                 $type = $fieldsConfig[$key]['type'];
// 
//                 // Date/time types
//                 if (in_array($type, ['date','time','datetime-local','month','week'], true)) {
//                     if ($value === '') {
//                         $params[":$key"] = null;
//                     } else {
//                         $dt = new DateTime($value);
//                         switch ($type) {
//                             case 'date': $params[":$key"] = $dt->format('Y-m-d'); break;
//                             case 'time': $params[":$key"] = $dt->format('H:i:s'); break;
//                             default: $params[":$key"] = $dt->format('Y-m-d H:i:s');
//                         }
//                     }
//                     continue;
//                 }
//             
//                 // Numeric types
//                 if (in_array($type, ['number', 'range', 'int', 'float', 'decimal'], true)) {
//                     $params[":$key"] = ($value === '') ? null : $value;
//                     continue;
//                 }
//             }
// 
//             // Default
//             $params[":$key"] = $value;
//         }
// 
//         $params[':idpk'] = $idpk;
//         $params[':adminIdpk'] = $_SESSION['IdpkOfAdmin'];
// 
//         $updateQuery = "UPDATE $table SET " . implode(', ', $updateFields) . " WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk";
//         $stmt = $pdo->prepare($updateQuery);
//         $stmt->execute($params);
// 
//         echo json_encode(['success' => true]);
//         exit;
//     } catch (PDOException $e) {
//         error_log("Update error: " . $e->getMessage());
//         echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
//         exit;
//     }
// }

// Handle search request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'search') {
    $table = $_POST['table'] ?? '';
    $idpk = $_POST['idpk'] ?? '';
    $query = trim($_POST['query'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        echo json_encode(['success' => false, 'error' => 'Invalid table name']);
        exit;
    }
    if ($idpk !== '' && !is_numeric($idpk)) {
        echo json_encode(['success' => false, 'error' => 'Invalid idpk']);
        exit;
    }

    $fieldsConfig = json_decode($_POST['searchFields'] ?? '[]', true);
    if (!is_array($fieldsConfig) || empty($fieldsConfig) || $query === '') {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $searchFields = [];
    $humanField = null;
    foreach ($fieldsConfig as $fieldName => $config) {
        if (($config['HumanPrimary'] ?? '') === '1') {
            $humanField = $fieldName;
        }
        if (in_array(($config['type'] ?? ''), ['text', 'textarea', 'datetime-local'])) {
            $searchFields[] = $fieldName;
        }
    }

    if (empty($searchFields)) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $escapedFields = array_filter($searchFields, fn($f) => preg_match('/^[a-zA-Z0-9_]+$/', $f));
    $whereClause = implode(' OR ', array_map(fn($f) => "$f LIKE :search", $escapedFields));
    $relevanceExpr = implode(' + ', array_map(fn($f) =>
        "(CASE WHEN $f LIKE :exact THEN 3 WHEN $f LIKE :start THEN 2 WHEN $f LIKE :search THEN 1 ELSE 0 END)", $escapedFields));

    $exact = $query;
    $start = $query . '%';
    $search = '%' . $query . '%';

    $message = <<<PHP
\$stmt = \$pdo->prepare("SELECT *, ($relevanceExpr) AS relevance FROM `$table` WHERE ($whereClause) ORDER BY relevance DESC LIMIT 50");
\$stmt->bindValue(':search', '$search', PDO::PARAM_STR);
\$stmt->bindValue(':exact', '$exact', PDO::PARAM_STR);
\$stmt->bindValue(':start', '$start', PDO::PARAM_STR);
\$stmt->execute();
\$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(\$results);
PHP;

    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => $message
    ];

    $maskedPayload = $payload;
    $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
    $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
    if ($maskedJson === false) {
        $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
    }

    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // file_put_contents('debug-search.log', "Raw CURL Response:\n" . $response . "\nCURL Error:\n" . $curlError . "\n", FILE_APPEND);

    $responseData = json_decode($response, true);

    if (($responseData['status'] ?? '') === 'success') {
        $rawResults = is_array($responseData['message']) ? $responseData['message'] : json_decode($responseData['message'], true);
        $linkedResults = [];

        foreach ($rawResults as $row) {
            $linkedId = $row['idpk'] ?? null;
            if ($linkedId === null) continue;

            $labelText = "🟦 ENTRY " . htmlspecialchars($linkedId);
            if ($humanField !== null && isset($row[$humanField])) {
                $labelText = "🟦 " . strtoupper($row[$humanField]) . " (" . htmlspecialchars($linkedId) . ")";
            }

            $linkedResults[] = [
                'url' => './entry.php?table=' . urlencode($table) . '&idpk=' . urlencode($linkedId),
                'label' => $labelText,
                'target' => '_self',
                'idpk' => $linkedId
            ];
        }

        echo json_encode(['success' => true, 'results' => $linkedResults]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Search failed']);
    }
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
//     $table = $_POST['table'] ?? '';
//     $idpk = $_POST['idpk'] ?? '';
//     $query = $_POST['query'] ?? '';
// 
//     if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
//         echo json_encode(['success' => false, 'error' => 'Invalid table name']);
//         exit;
//     }
//     if ($idpk !== '' && !is_numeric($idpk)) {
//         echo json_encode(['success' => false, 'error' => 'Invalid idpk']);
//         exit;
//     }
//     if ($idpk !== '' && !checkEntryAccess($pdo, $table, $idpk)) {
//         echo json_encode(['success' => false, 'error' => 'Access denied']);
//         exit;
//     }
//     
//     $query = trim($query);
//     if ($query === '') {
//         echo json_encode(['success' => true, 'results' => []]);
//         exit;
//     }
// 
//     try {
//         $fieldsConfig = json_decode($_POST['searchFields'] ?? '[]', true);
// 
//         if (!is_array($fieldsConfig) || empty($fieldsConfig)) {
//             echo json_encode(['success' => true, 'results' => []]);
//             exit;
//         }
// 
//         // Extract searchable fields for WHERE clause
//         $searchFields = [];
//         $humanField = null;
// 
//         foreach ($fieldsConfig as $fieldName => $config) {
//             if (isset($config['HumanPrimary']) && $config['HumanPrimary'] == '1') {
//                 $humanField = $fieldName;
//             }
//             if (isset($config['type']) && in_array($config['type'], ['text', 'textarea', 'datetime-local'])) {
//                 $searchFields[] = $fieldName;
//             }
//         }
// 
//         $searchTerm = '%' . $query . '%';
//             
//         if (!is_array($searchFields) || empty($searchFields)) {
//             echo json_encode(['success' => true, 'results' => []]);
//             exit;
//         }
//         
//         $whereParts = [];
//         foreach ($searchFields as $field) {
//             if (preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
//                 $whereParts[] = "$field LIKE :search";
//             }
//         }
//         $whereClause = implode(' OR ', $whereParts);
// 
//         // Build a simple relevance scoring expression
//         $relevanceParts = [];
//         foreach ($searchFields as $field) {
//             if (preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
//                 $relevanceParts[] = "(CASE WHEN $field LIKE :exact THEN 3 WHEN $field LIKE :start THEN 2 WHEN $field LIKE :search THEN 1 ELSE 0 END)";
//             }
//         }
//         $relevanceScore = implode(' + ', $relevanceParts);
//         
//         $sql = "SELECT *, ($relevanceScore) AS relevance 
//             FROM $table 
//             WHERE IdpkOfAdmin = :adminIdpk AND ($whereClause)
//             ORDER BY relevance DESC 
//             LIMIT 50";
//         $stmt = $pdo->prepare($sql);
//         $stmt->bindParam(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
//         $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
//         $exact = $query;
//         $start = $query . '%';
//         $stmt->bindParam(':exact', $exact, PDO::PARAM_STR);
//         $stmt->bindParam(':start', $start, PDO::PARAM_STR);
//         $stmt->execute();
// 
//         $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
//         $linkedResults = [];
//             
//         foreach ($rawResults as $row) {
//             $linkedId = $row['idpk'];  
//             $linkedTable = $table;
// 
//             $labelText = "🟦 ENTRY " . htmlspecialchars($linkedId);
// 
//             if ($humanField !== null && isset($row[$humanField])) {
//                 $labelText = "🟦 " . strtoupper($row[$humanField]) . " (" . htmlspecialchars($linkedId) . ")";
//             }
//         
//             $linkUrl = "./entry.php?table=" . urlencode($linkedTable) . "&idpk=" . urlencode($linkedId);
//             $target = '_self';
//         
//             $linkedResults[] = [
//                 'url' => $linkUrl,
//                 'label' => $labelText,
//                 'target' => $target,
//                 'idpk' => $linkedId, // Add this
//             ];
//         }
//         
//         echo json_encode(['success' => true, 'results' => $linkedResults]);
//         exit;
//     } catch (PDOException $e) {
//         // Log error silently, do NOT alert user
//         error_log("Search query error: " . $e->getMessage());
// 
//         // Return a generic failure response without detailed error to the client
//         echo json_encode(['success' => false, 'error' => 'Search failed']);
//     }
//     exit;
// }

// Handle check for linked entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'checkLinkedEntry') {
    $linkedTable = $_POST['linkedTable'] ?? '';
    $linkedField = $_POST['linkedField'] ?? '';
    $linkedId = $_POST['linkedId'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $linkedTable) || !preg_match('/^[a-zA-Z0-9_]+$/', $linkedField)) {
        echo json_encode(['success' => false]);
        exit;
    }
    if (!is_numeric($linkedId)) {
        echo json_encode(['success' => false]);
        exit;
    }

    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "\$stmt = \$pdo->prepare(\"SELECT * FROM `$linkedTable` WHERE `$linkedField` = :id LIMIT 1\");
                      \$stmt->execute([':id' => $linkedId]);
                      \$row = \$stmt->fetch(PDO::FETCH_ASSOC);
                      echo json_encode(\$row);"
    ];

    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
    if ($basePath === DIRECTORY_SEPARATOR) {
        $basePath = '';
    }
    $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

    $ch = curl_init($nexusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $responseData = is_string($response) ? json_decode($response, true) : $response;

    if (!isset($responseData['status']) || $responseData['status'] !== 'success') {
        echo json_encode(['success' => false]);
        exit;
    }

    $row = is_array($responseData['message']) ? $responseData['message'] : json_decode($responseData['message'], true);

    if (!$row) {
        echo json_encode(['success' => true, 'found' => false]);
        exit;
    }

    $linkedConfig = json_decode($_POST['searchFields'] ?? '{}', true);
    $humanField = null;
    foreach ($linkedConfig as $f => $conf) {
        if (!empty($conf['HumanPrimary'])) {
            $humanField = $f;
            break;
        }
    }

    $label = "🟦 ENTRY " . htmlspecialchars($linkedId);
    if ($humanField && isset($row[$humanField])) {
        $label = "🟦 " . strtoupper($row[$humanField]) . " (" . htmlspecialchars($linkedId) . ")";
    }

    $preview = [];
    if (isset($DatabaseTablesStructure[$linkedTable])) {
        foreach ($DatabaseTablesStructure[$linkedTable] as $fname => $cfg) {
            if (
                !empty($cfg['ShowInPreviewCard']) &&
                array_key_exists($fname, $row) &&
                $fname !== 'idpk' &&
                $fname !== $humanField
            ) {
                $val = $row[$fname];

                if (!empty($cfg['LinkedToWhatTable']) && !empty($cfg['LinkedToWhatFieldThere']) && $val !== '') {
                    $otherTbl   = $cfg['LinkedToWhatTable'];
                    $otherField = $cfg['LinkedToWhatFieldThere'];
                    $humanCol   = null;

                    if (isset($DatabaseTablesStructure[$otherTbl])) {
                        foreach ($DatabaseTablesStructure[$otherTbl] as $fn => $c) {
                            if (!empty($c['HumanPrimary'])) {
                                $humanCol = $fn;
                                break;
                            }
                        }
                    }

                    if ($humanCol && is_numeric($val)) {
                        $query = "\$stmt = \$pdo->prepare(\"SELECT `$humanCol` FROM `$otherTbl` WHERE `$otherField` = :id LIMIT 1\");
                                   \$stmt->execute([':id' => $val]);
                                   echo json_encode(\$stmt->fetch(PDO::FETCH_ASSOC));";

                        $payloadHuman = [
                            'APIKey' => $TRAMANNAPIAPIKey,
                            'message' => $query
                        ];

                        $chH = curl_init($nexusUrl);
                        curl_setopt_array($chH, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => json_encode($payloadHuman),
                            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                            CURLOPT_TIMEOUT        => 30
                        ]);
                        $resp = curl_exec($chH);
                        curl_close($chH);

                        $respData = json_decode($resp, true);
                        if (isset($respData['status']) && $respData['status'] === 'success') {
                            $msg = $respData['message'];
                            $labelRow = is_array($msg) ? $msg : json_decode($msg, true);
                            if (is_array($labelRow) && isset($labelRow[$humanCol]) && $labelRow[$humanCol] !== '' && $labelRow[$humanCol] !== null) {
                                $val .= ' (' . $labelRow[$humanCol] . ')';
                            }
                        }
                    }
                }

                $preview[] = [
                    'field' => $fname,
                    'label' => $cfg['label'] ?? $fname,
                    'type'  => $cfg['type'] ?? '',
                    'value' => $val
                ];
            }
        }
    }

    $linkUrl = "./entry.php?table=" . urlencode($linkedTable) . "&idpk=" . urlencode($linkedId);
    $target = (isset($linkedConfig['IsLinkedTableGeneralTable']) && $linkedConfig['IsLinkedTableGeneralTable'] == 1)
        ? 'target="_blank"' : 'target="_self"';

    echo json_encode([
        'success' => true,
        'found'   => true,
        'label'   => $label,
        'preview' => $preview,
        'url'     => $linkUrl,
        'target'  => $target,
    ]);
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'checkLinkedEntry') {
//     $linkedTable = $_POST['linkedTable'] ?? '';
//     $linkedField = $_POST['linkedField'] ?? '';
//     $linkedId = $_POST['linkedId'] ?? '';
// 
//     if (!preg_match('/^[a-zA-Z0-9_]+$/', $linkedTable) || !preg_match('/^[a-zA-Z0-9_]+$/', $linkedField)) {
//         echo json_encode(['success' => false]);
//         exit;
//     }
//     if (!is_numeric($linkedId)) {
//         echo json_encode(['success' => false]);
//         exit;
//     }
// 
//     try {
//         $stmt = $pdo->prepare("SELECT * FROM `$linkedTable` WHERE `$linkedField` = :id AND IdpkOfAdmin = :adminIdpk LIMIT 1");
//         $stmt->execute([
//             ':id' => $linkedId,
//             ':adminIdpk' => $_SESSION['IdpkOfAdmin'],
//         ]);
//         $row = $stmt->fetch(PDO::FETCH_ASSOC);
// 
//         if (!$row) {
//             echo json_encode(['success' => true, 'found' => false]);
//             exit;
//         }
// 
//         // Find human-readable field
//         $linkedConfig = json_decode($_POST['searchFields'] ?? '{}', true);
//         $humanField = null;
//         foreach ($linkedConfig as $f => $conf) {
//             if (!empty($conf['HumanPrimary'])) {
//                 $humanField = $f;
//                 break;
//             }
//         }
// 
//         $label = "🟦 ENTRY " . htmlspecialchars($linkedId);
//         if ($humanField && isset($row[$humanField])) {
//             $label = "🟦 " . strtoupper($row[$humanField]) . " (" . htmlspecialchars($linkedId) . ")";
//         }
// 
//         $linkUrl = "./entry.php?table=" . urlencode($linkedTable) . "&idpk=" . urlencode($linkedId);
//         $target = (isset($linkedConfig['IsLinkedTableGeneralTable']) && $linkedConfig['IsLinkedTableGeneralTable'] == 1)
//             ? 'target="_blank"' : 'target="_self"';
// 
//         echo json_encode([
//             'success' => true,
//             'found' => true,
//             'label' => $label,
//             'url' => $linkUrl,
//             'target' => $target,
//         ]);
//     } catch (Exception $e) {
//         echo json_encode(['success' => false]);
//     }
//     exit;
// }

// If we get here, the request wasn't handled
echo json_encode(['success' => false, 'error' => 'Invalid request']); 