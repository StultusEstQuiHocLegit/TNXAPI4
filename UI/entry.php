<?php
require_once('../config.php');
require_once('header.php');

require_once('../SETUP/DatabaseTablesStructure.php');

$table = isset($_GET['table']) ? trim($_GET['table']) : null;
$idpk  = isset($_GET['idpk'])  ? trim($_GET['idpk'])  : null;

$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';

// Define all tables, also later for LINKED ENTRIES
// $allTables = ['TDBtransaction', 'TDBProductsAndServices', 'TDBSuppliersAndCustomers', 'TDBcarts'];
$allTables = array_keys($DatabaseTablesStructure);
// Get table structure
// function getFormFieldsFor(string $table): array {
// 
//     switch ($table) {
//         case 'TDBcarts':
//             return [
//                 'IdpkOfSupplierOrCustomer' => ['type' => 'number', 'label' => 'supplier or customer idpk', 'LinkedToWhatTable' => 'TDBSuppliersAndCustomers', 'LinkedToWhatFieldThere' => 'idpk', 'IsLinkedTableGeneralTable' => '1'],
//                 'DeliveryType' => ['type' => 'text', 'label' => 'delivery type', 'placeholder' => 'for example: "standard", "express", "overnight", "pickup", "temperature-controlled", ...'],
//                 'WishedIdealDeliveryOrPickUpTime' => ['type' => 'datetime-local', 'label' => 'wished delivery/pickup time'],
//                 'CommentsNotesSpecialRequests' => ['type' => 'textarea', 'label' => 'comments, notes, special requests, ...']
//             ];
//             break;
//         case 'TDBtransaction':
//             return [
//                 'IdpkOfSupplierOrCustomer' => ['type' => 'number', 'label' => 'supplier or customer idpk', 'LinkedToWhatTable' => 'TDBSuppliersAndCustomers', 'LinkedToWhatFieldThere' => 'idpk', 'IsLinkedTableGeneralTable' => '1'],
//                 'IdpkOfCart' => ['type' => 'number', 'label' => 'cart id', 'LinkedToWhatTable' => 'TDBcarts', 'LinkedToWhatFieldThere' => 'idpk'],
//                 'IdpkOfProductOrService' => ['type' => 'number', 'label' => 'product or service idpk', 'LinkedToWhatTable' => 'TDBProductsAndServices', 'LinkedToWhatFieldThere' => 'idpk', 'IsLinkedTableGeneralTable' => '1'],
//                 'quantity' => ['type' => 'number', 'label' => 'quantity'],
//                 'NetPriceTotal' => ['type' => 'number', 'step' => '0.01', 'label' => 'net price total', 'placeholder' => 'positive if sold something, negative for buying, total = unit price * quantity'],
//                 'TaxesTotal' => ['type' => 'number', 'step' => '0.01', 'label' => 'taxes total', 'placeholder' => 'total = unit tax amount * quantity'],
//                 'CurrencyCode' => ['type' => 'text', 'maxlength' => '3', 'label' => 'currency code (ISO 4217)'],
//                 'state' => ['type' => 'text', 'label' => 'state', 'placeholder' => 'for example: "draft", "offer", "payment-pending", "payment-made-delivery-pending", "payment-pending-delivery-made", "completed", "disputed", "dispute-accepted", "dispute-rejected", "refunded", "items-returned", "cancelled", ...'],
//                 'CommentsNotesSpecialRequests' => ['type' => 'textarea', 'label' => 'comments, notes, special requests, ...']
//             ];
//             break;
//         case 'TDBProductsAndServices':
//             return [
//                 'name' => ['type' => 'text', 'label' => 'name', 'HumanPrimary' => '1'],
//                 'categories' => ['type' => 'text', 'label' => 'categories', 'placeholder' => 'for example: "SomeMainCategory/FirstSubcategory/SecondSubcategory, AnotherMainCategoryIfNeeded, YetAnotherOne/AndSomeSubcategoryToo"'],
//                 'KeywordsForSearch' => ['type' => 'textarea', 'label' => 'keywords for search'],
//                 'ShortDescription' => ['type' => 'textarea', 'label' => 'short description'],
//                 'LongDescription' => ['type' => 'textarea', 'label' => 'long description'],
//                 'WeightInKg' => ['type' => 'number', 'step' => '0.00001', 'label' => 'weight (kg)'],
//                 'DimensionsLengthInMm' => ['type' => 'number', 'step' => '0.01', 'label' => 'length (mm)'],
//                 'DimensionsWidthInMm' => ['type' => 'number', 'step' => '0.01', 'label' => 'width (mm)'],
//                 'DimensionsHeightInMm' => ['type' => 'number', 'step' => '0.01', 'label' => 'height (mm)'],
//                 'NetPriceInCurrencyOfAdmin' => ['type' => 'number', 'step' => '0.01', 'label' => 'net price'],
//                 'TaxesInPercent' => ['type' => 'number', 'step' => '0.01', 'label' => 'taxes (%)'],
//                 'VariableCostsOrPurchasingPriceInCurrencyOfAdmin' => ['type' => 'number', 'step' => '0.01', 'label' => 'variable costs/purchasing price'],
//                 'ProductionCoefficientInLaborHours' => ['type' => 'number', 'step' => '0.01', 'label' => 'production coefficient (hours)'],
//                 'ManageInventory' => ['type' => 'checkbox', 'label' => 'manage inventory'],
//                 'InventoryAvailable' => ['type' => 'number', 'label' => 'available inventory', 'OnlyShowIfThisIsTrue' => 'ManageInventory'],
//                 'InventoryInProductionOrReordered' => ['type' => 'number', 'label' => 'inventory in production/reordered', 'OnlyShowIfThisIsTrue' => 'ManageInventory'],
//                 'InventoryMinimumLevel' => ['type' => 'number', 'label' => 'minimum inventory level', 'OnlyShowIfThisIsTrue' => 'ManageInventory'],
//                 'InventoryLocation' => ['type' => 'text', 'label' => 'inventory location'],
//                 'PersonalNotes' => ['type' => 'textarea', 'label' => 'personal notes'],
//                 'state' => ['type' => 'text', 'label' => 'state', 'placeholder' => 'for example: "active", "inactive", "archived", "only for internal purposes", ...']
//             ];
//             break;
//         case 'TDBSuppliersAndCustomers':
//             return [
//                 'CompanyName' => ['type' => 'text', 'label' => 'company name', 'HumanPrimary' => '1'],
//                 'email' => ['type' => 'email', 'label' => 'email'],
//                 'PhoneNumber' => ['type' => 'number', 'label' => 'phone number', 'placeholder' => '0123456789'],
//                 'street' => ['type' => 'text', 'label' => 'street'],
//                 'HouseNumber' => ['type' => 'number', 'label' => 'house number'],
//                 'ZIPCode' => ['type' => 'text', 'label' => 'zip code'],
//                 'city' => ['type' => 'text', 'label' => 'city'],
//                 'country' => ['type' => 'text', 'label' => 'country'],
//                 'IBAN' => ['type' => 'text', 'label' => 'IBAN'],
//                 'VATID' => ['type' => 'text', 'label' => 'VATID'],
//                 'PersonalNotesInGeneral' => ['type' => 'textarea', 'label' => 'general notes'],
//                 'PersonalNotesBusinessRelationships' => ['type' => 'textarea', 'label' => 'business relationship notes']
//             ];
//             break;
//         default:
//             return [];
//     }
// }

function getFormFieldsFor(string $table): array {
    global $DatabaseTablesStructure;

    if (!isset($DatabaseTablesStructure[$table])) {
        return [];
    }

    $fields = $DatabaseTablesStructure[$table];

    $formFields = [];

    foreach ($fields as $fieldName => $fieldConfig) {
        // Exclude fields marked 'SystemOnly' = '1'
        if (isset($fieldConfig['SystemOnly']) && $fieldConfig['SystemOnly'] === '1') {
            continue;
        }

        // Build form field config with keys you want to keep
        // Remove DBType and DBMarks if you want only UI-related properties
        $formFields[$fieldName] = $fieldConfig;

        // Optionally remove DBType and DBMarks for the form config if you want cleaner output:
        unset($formFields[$fieldName]['DBType'], $formFields[$fieldName]['DBMarks']);
    }

    return $formFields;
}

$formFields = getFormFieldsFor($table);

// pull out the “human primary” fields to always show them first
$primaryFields = [];
$otherFields   = [];

foreach ($formFields as $name => $cfg) {
    if (!empty($cfg['HumanPrimary'])) {
        $primaryFields[$name] = $cfg;
    } else {
        $otherFields[$name] = $cfg;
    }
}

// merge back: primary ones first, then the rest
$formFields = $primaryFields + $otherFields;

// Load existing data if we have table and idpk
$existingData = [];
if ($table && $idpk) {
    try {
        // /////////////////////////////////////////////////////////////////////////// new approach with remote servers, leveraging stargate infrastructure
        // send over to ../API/nexus.php the following:
        // $payload = [
        //     'APIKey' => $TRAMANNAPIAPIKey,
        //     'message' => $dbCmd['message'],  // assuming $dbCmd is ['message' => '...php code...']
        // ];
        // then we get back from there:
        // echo json_encode([
        //     "status" => "success", (or "status" => "error",)
        //     "message" => SomeResponseContentIfNeededInHere, (if in the original payload message, there should have been some echoing of results)
        // ]);

        $payload = [
            'APIKey' => $TRAMANNAPIAPIKey,
            'message' => "\$stmt = \$pdo->prepare(\"SELECT * FROM `$table` WHERE idpk = :idpk\");
                          \$stmt->execute([':idpk' => $idpk]);
                          \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
                          echo json_encode(\$result);"
        ];

        // Log to console: masked payload
        $maskedPayload = $payload;
        $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
        $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
        if ($maskedJson === false) {
            $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
        }
        // echo "<script>console.log('⟪TRAMANN API CALL PAYLOAD⟫ ' + " . json_encode($maskedJson) . ");</script>";

        // Build full URL to nexus.php
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

        // Log raw response
        // echo "<script>console.log('⟪TRAMANN API RAW RESPONSE⟫ ' + " . json_encode($response) . ");</script>";

        // Handle JSON result
        $responseData = json_decode($response, true);
        // echo "<script>console.log('⟪TRAMANN API PARSED RESPONSE⟫', " . json_encode($responseData, JSON_UNESCAPED_UNICODE) . ");</script>";

        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            $existingData = $responseData['message'];
            // echo "<script>console.log('⟪TRAMANN API FINAL DATA⟫', " . json_encode($existingData, JSON_UNESCAPED_UNICODE) . ");</script>";
        }



        // // wrap the SELECT in a try/catch to handle missing table or bad query
        // $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
        // $stmt->bindParam(':idpk', $idpk, PDO::PARAM_INT);
        // $stmt->bindParam(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
        // $stmt->execute();
        // $existingData = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // if no row returned, throw to our catch block
        if (!$existingData) {
            throw new Exception("not found");
        }
    } catch (\PDOException|\Exception $e) {
        // friendly error message + return link
        printf(
            'We are very sorry, but there couldn’t be found any record.<br><br><a href="./index.php">◀️ RETURN</a>'
        );
        exit;
    }
}

// handle public marker field and reordering
$publicMarkerFieldName = null;
$hasShowToPublic = false;
foreach ($formFields as $name => $cfg) {
    if (!empty($cfg['ShowToPublic'])) {
        $hasShowToPublic = true;
    }
    if (!empty($cfg['ShowWholeEntryToPublicMarker'])) {
        $publicMarkerFieldName = $name;
        $publicMarkerConfig = $cfg;
        unset($formFields[$name]);
    }
}
if ($publicMarkerFieldName !== null) {
    $formFields[$publicMarkerFieldName] = $publicMarkerConfig;
}

$entryIsPublic = false;
if ($publicMarkerFieldName !== null) {
    $markerVal = $existingData[$publicMarkerFieldName] ?? '';
    $markerValLower = strtolower((string)$markerVal);
    if ($markerValLower === '1' || $markerValLower === 'true') {
        $entryIsPublic = true;
    }
} elseif ($hasShowToPublic) {
    $entryIsPublic = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_entry'])) {
    $idpk = filter_input(INPUT_GET, 'idpk', FILTER_VALIDATE_INT);

    if ($idpk) {
        $payload = [
            'APIKey' => $TRAMANNAPIAPIKey,
            'message' => "
                \$idpk = $idpk;
                \$stmt = \$pdo->prepare(\"DELETE FROM `$table` WHERE idpk = :idpk\");
                \$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
                \$stmt->execute();

                \$uploadDir = \"./UPLOADS/$table/\";
                \$deletedFiles = [];

                if (is_dir(\$uploadDir)) {
                    \$files = glob(\$uploadDir . \$idpk . \"_*.*\");
                    foreach (\$files as \$file) {
                        if (is_file(\$file)) {
                            unlink(\$file);
                            \$deletedFiles[] = basename(\$file);
                        }
                    }
                }

                echo json_encode(['deleted' => true, 'filesRemoved' => \$deletedFiles]);
                "
        ];

        // Log to console: masked payload
        $maskedPayload = $payload;
        $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
        $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
        if ($maskedJson === false) {
            $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
        }
        echo "<script>console.log('⟪TRAMANN API CALL PAYLOAD⟫ ' + " . json_encode($maskedJson) . ");</script>";

        // Build full URL to nexus.php
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

        // Log raw response
        echo "<script>console.log('⟪TRAMANN API RAW RESPONSE⟫ ' + " . json_encode($response) . ");</script>";

        // Handle JSON result
        $responseData = json_decode($response, true);
        echo "<script>console.log('⟪TRAMANN API PARSED RESPONSE⟫', " . json_encode($responseData, JSON_UNESCAPED_UNICODE) . ");</script>";

        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            $deleteResult = $responseData['message'];
            echo "<script>console.log('⟪TRAMANN API FINAL DELETE RESULT⟫', " . json_encode($deleteResult, JSON_UNESCAPED_UNICODE) . ");</script>";

            if (!empty($deleteResult['deleted'])) {
                echo "<script>alert('Entry and attachments removed successfully.'); window.location.href = './index.php';</script>";
                exit;
            } else {
                $error = "We are very sorry, but there was an error in removing the entry.";
            }
        } else {
            $error = "We are very sorry, but the API did not respond with success.";
        }
    } else {
        $error = "We are very sorry, but the entry idpk is invalid.";
    }
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_entry'])) {
//     $idpk = filter_input(INPUT_GET, 'idpk', FILTER_VALIDATE_INT);
//     $adminIdpk = $_SESSION['IdpkOfAdmin'];
// 
//     if ($idpk && $adminIdpk) {
//         $stmt = $pdo->prepare("DELETE FROM `$table` WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
//         $success = $stmt->execute([
//             ':idpk' => $idpk,
//             ':adminIdpk' => $adminIdpk
//         ]);
// 
//         if ($success) {
//             // Delete attachments associated with this entry
//             $uploadDir = "../UPLOADS/TDB/$table/";
//             if (is_dir($uploadDir)) {
//                 $files = glob($uploadDir . $idpk . "_*.*"); // find all files that start with "$idpk_"
//                 foreach ($files as $file) {
//                     if (is_file($file)) {
//                         unlink($file); // delete the file
//                     }
//                 }
//             }
// 
//             echo "<script>alert('Entry removed successfully.'); window.location.href = './index.php';</script>";
//             exit;
//         } else {
//             $error = "We are very sorry, but there was an error in removing the entry.";
//         }
//     } else {
//         $error = "We are very sorry, but you are not authorized to remove this entry.";
//     }
// }

// /////////////////////////////////////////////////////////////////////////// old, we now use AJAX for saving
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_entry']) || isset($_POST['update_and_return']))) {
//     try {
//         // Start building the update query
//         $updateFields = [];
//         $params = [];
// 
//         // Make sure every checkbox in $formFields is in $_POST,
//         // even when it’s unchecked, so we can map it to 0 below:
//         $checkboxNames = array_keys(
//             array_filter($formFields, function($cfg) {
//                 return isset($cfg['type']) && $cfg['type'] === 'checkbox';
//             })
//         );
//         foreach ($checkboxNames as $chk) {
//             if (!array_key_exists($chk, $_POST)) {
//                 $_POST[$chk] = '';
//             }
//         }
//         
//         // Get all form fields and build the update query
//         foreach ($_POST as $key => $value) {
//             // skip our two buttons and the internal Idpk
//             if (in_array($key, ['update_entry','update_and_return','Idpk'], true)) {
//                 continue;
//             }
//         
//                 $updateFields[] = "$key = :$key";
//                 
//             // ————————————————————————————————————
//             // 1) Checkbox? map "on" -> 1, everything else -> 0
//             if (in_array($key, $checkboxNames, true)) {
//                 $params[":$key"] = ($value === 'on' ? 1 : 0);
//                 continue;
//             }
//             
//             // ————————————————————————————————————
//             // 2) Date/time fields: empty -> NULL; otherwise reformat
//             if (
//                 isset($formFields[$key]['type']) 
//                 && in_array($formFields[$key]['type'], ['date','time','datetime-local','month','week'], true)
//             ) {
//                 if ($value === '') {
//                     $params[":$key"] = null;
//                 } else {
//                     $dt = new DateTime($value);
//                     switch ($formFields[$key]['type']) {
//                         case 'date':
//                             $params[":$key"] = $dt->format('Y-m-d');
//                             break;
//                         case 'time':
//                             $params[":$key"] = $dt->format('H:i:s');
//                             break;
//                         default: // 'datetime-local', 'month', 'week'
//                             $params[":$key"] = $dt->format('Y-m-d H:i:s');
//                     }
//                 }
//                 continue;
//             }
//         
//             // 3) Numeric fields: empty -> 0
//             if (in_array($key, [
//                 'WeightInKg','DimensionsLengthInMm','DimensionsWidthInMm',
//                 'DimensionsHeightInMm','NetPriceInCurrencyOfAdmin','TaxesInPercent',
//                 'VariableCostsOrPurchasingPriceInCurrencyOfAdmin',
//                 'ProductionCoefficientInLaborHours','InventoryAvailable',
//                 'InventoryInProductionOrReordered','InventoryMinimumLevel',
//                 'NetPriceTotal','TaxesTotal','quantity',
//                 'IdpkOfSupplierOrCustomer','IdpkOfCart','IdpkOfProductOrService',
//                 'HouseNumber','PhoneNumber'
//             ], true)) {
//                 $params[":$key"] = ($value === '' ? 0 : $value);
//             } else {
//                 // everything else stays as‐is
//                 $params[":$key"] = $value;
//             }
//         }
//         
//         // Add the idpk and IdpkOfAdmin to the parameters
//         $params[':idpk'] = $idpk;
//         $params[':adminIdpk'] = $_SESSION['IdpkOfAdmin'];
//         
//         // Build and execute the update query with IdpkOfAdmin check
//         $updateQuery = "UPDATE $table SET " . implode(', ', $updateFields) . " WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk";
//         $stmt = $pdo->prepare($updateQuery);
//         $stmt->execute($params);
// 
//         // // Prepare rollback SQL using original values
//         // $selectOld = $pdo->prepare("SELECT * FROM $table WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
//         // $selectOld->bindParam(':idpk', $idpk, PDO::PARAM_INT);
//         // $selectOld->bindParam(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
//         // $selectOld->execute();
//         // $oldData = $selectOld->fetch(PDO::FETCH_ASSOC);
// // 
//         // // Generate rollback SQL
//         // $rollbackParts = [];
//         // foreach ($params as $param => $value) {
//         //     $col = ltrim($param, ':');
//         //     if (isset($oldData[$col])) {
//         //         $orig = $oldData[$col];
//         //         $escaped = is_null($orig) ? 'NULL' : $pdo->quote($orig);
//         //         $rollbackParts[] = "$col = $escaped";
//         //     }
//         // }
//         // $rollbackSQL = "UPDATE $table SET " . implode(', ', $rollbackParts) . " WHERE idpk = $idpk";
// // 
//         // // Interpolate the executed SQL (optional: for easier readability)
//         // function interpolateQuery($query, $params, $pdo) {
//         //     foreach ($params as $key => $val) {
//         //         if (is_string($val)) {
//         //             $val = $pdo->quote($val);
//         //         } elseif (is_null($val)) {
//         //             $val = 'NULL';
//         //         }
//         //         $query = str_replace($key, $val, $query);
//         //     }
//         //     return $query;
//         // }
//         // $executedSQL = interpolateQuery($updateQuery, $params, $pdo);
// // 
//         // // Insert into logs
//         // $logSql = "
//         //     INSERT INTO logs (TimestampCreation, IdpkOfCreator, IsAdmin, IdpkOfAdmin, ExecutedSQL, RollbackSQL)
//         //     VALUES (CURRENT_TIMESTAMP, :creatorId, :isAdmin, :adminId, :executedSQL, :rollbackSQL)
//         // ";
//         // $logStmt = $pdo->prepare($logSql);
//         // $logStmt->bindParam(':creatorId', $_SESSION['user_id'], PDO::PARAM_INT);
//         // $logStmt->bindParam(':isAdmin', $_SESSION['IsAdmin'], PDO::PARAM_BOOL);
//         // $logStmt->bindParam(':adminId', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
//         // $logStmt->bindParam(':executedSQL', $executedSQL, PDO::PARAM_STR);
//         // $logStmt->bindParam(':rollbackSQL', $rollbackSQL, PDO::PARAM_STR);
//         // $logStmt->execute();
//         
//         // Redirect based on which button was clicked
//         if (isset($_POST['update_and_return'])) {
//             echo "<script>window.location.href = './index.php';</script>";
//         } else {
//             echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "?table=" . urlencode($table) . "&idpk=" . urlencode($idpk) . "';</script>";
//         }
//         exit;
//     } catch (PDOException $e) {
//         $error = "Error updating entry: " . $e->getMessage();
//     }
// }



// Ensure $idpk is properly sanitized or cast if needed
$currentId = $idpk;
$companyId = $_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '');

// Function to check if an entry exists with given id in the current table, remotely via Stargate
function entryExistsRemote(string $table, $id) {
    global $TRAMANNAPIAPIKey;

    $payload = [
        'APIKey' => $TRAMANNAPIAPIKey,
        'message' => "\$stmt = \$pdo->prepare(\"SELECT 1 FROM `$table` WHERE idpk = :id\");
                      \$stmt->execute([':id' => $id]);
                      echo json_encode(\$stmt->fetchColumn() !== false);"
    ];

    // Log masked payload
    $maskedPayload = $payload;
    $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
    $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
    if ($maskedJson === false) {
        $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
    }
    // echo "<script>console.log('⟪ENTRY EXISTS PAYLOAD⟫ ' + " . json_encode($maskedJson) . ");</script>";

    // Build URL to nexus.php
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

    // echo "<script>console.log('⟪ENTRY EXISTS RESPONSE⟫ ' + " . json_encode($response) . ");</script>";

    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        return $responseData['message'] === true;
    }

    return false;
}

// Initialize
$prevId = null;
$nextId = null;

// Fetch previous ID
$payloadPrev = [
    'APIKey' => $TRAMANNAPIAPIKey,
    'message' => "\$stmt = \$pdo->prepare(\"SELECT MAX(idpk) FROM `$table` WHERE idpk < :id\");
                  \$stmt->execute([':id' => $currentId]);
                  echo json_encode(\$stmt->fetchColumn());"
];

// Fetch next ID
$payloadNext = [
    'APIKey' => $TRAMANNAPIAPIKey,
    'message' => "\$stmt = \$pdo->prepare(\"SELECT MIN(idpk) FROM `$table` WHERE idpk > :id\");
                  \$stmt->execute([':id' => $currentId]);
                  echo json_encode(\$stmt->fetchColumn());"
];

function fetchRemoteId($payload, $label) {
    global $TRAMANNAPIAPIKey;

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

    // echo "<script>console.log('⟪$label RAW⟫ ' + " . json_encode($response) . ");</script>";

    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        return json_decode($responseData['message'], true);
    }

    return null;
}

// Apply remote fetch logic
$prevId = fetchRemoteId($payloadPrev, 'PREV ID');
if ($prevId !== null && !entryExistsRemote($table, $prevId)) {
    $prevId = null;
}

$nextId = fetchRemoteId($payloadNext, 'NEXT ID');
if ($nextId !== null && !entryExistsRemote($table, $nextId)) {
    $nextId = null;
}

// // Function to check if an entry exists with given id in the current table
// function entryExists(PDO $pdo, string $table, $id) {
//     $stmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE `idpk` = :id AND IdpkOfAdmin = :adminIdpk LIMIT 1");
//     $stmt->execute([
//         ':id' => $id,
//         ':adminIdpk' => $_SESSION['IdpkOfAdmin']
//     ]);
//     return $stmt->fetchColumn() !== false;
// }
// 
// // Get previous and next IDs relative to currentId
// // Assuming idpk is numeric for ordering. Adjust if string.
// $prevId = null;
// $nextId = null;
// 
// // Get previous idpk (max idpk < currentId)
// $stmtPrev = $pdo->prepare("SELECT MAX(`idpk`) FROM `$table` WHERE `idpk` < :id AND IdpkOfAdmin = :adminIdpk");
// $stmtPrev->bindValue(':id', $currentId, PDO::PARAM_INT);
// $stmtPrev->bindValue(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
// $stmtPrev->execute();
// $prevId = $stmtPrev->fetchColumn();
// if ($prevId !== false && !entryExists($pdo, $table, $prevId)) {
//     $prevId = null; // Just in case
// }
// 
// // Get next idpk (min idpk > currentId)
// $stmtNext = $pdo->prepare("SELECT MIN(`idpk`) FROM `$table` WHERE `idpk` > :id AND IdpkOfAdmin = :adminIdpk");
// $stmtNext->bindValue(':id', $currentId, PDO::PARAM_INT);
// $stmtNext->bindValue(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
// $stmtNext->execute();
// $nextId = $stmtNext->fetchColumn();
// if ($nextId !== false && !entryExists($pdo, $table, $nextId)) {
//     $nextId = null; // Just in case
// }

// Build URLs for prev/next if exist
$prevLink = $prevId !== null ? "./entry.php?table=" . urlencode($table) . "&idpk=" . urlencode($prevId) : null;
$nextLink = $nextId !== null ? "./entry.php?table=" . urlencode($table) . "&idpk=" . urlencode($nextId) : null;
?>
<script>
const allFieldConfigs = {
<?php
foreach ($allTables as $t) {
    echo json_encode($t) . ': ' . json_encode(getFormFieldsFor($t)) . ",\n";
}
?>
};
</script>

<div class="container entry-edit-container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <a href="./index.php" title="back to the future (well, sort of)"><strong>◀️ RETURN</strong></a><a href="#"  id="createNewEntry" title="create a new entry in the table <?php echo htmlspecialchars($table); ?>" style="opacity: 0.5;">➕ NEW</a><span id="SavingIndicator" title="saving status of the current entry" style="opacity: 0.3;">✔️ saved</span>
    </div><br>
    <div class="header-section">
        <h1 class="text-center" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
            <?php if ($prevLink): ?>
                <a href="<?php echo $prevLink; ?>" 
                   style="opacity: 0.2; margin-right: 5px;" 
                   title="previous entry: ENTRY <?php echo htmlspecialchars($prevId); ?>">
                   &#x25C0;  <!-- ◀ -->
                </a>
            <?php endif; ?>

            <!-- Input field with de-emphasized style -->
            <input id="entrySearchInput" type="text" title="click and enter something to search for other entries in the table <?php echo htmlspecialchars($table); ?>" value="🟦 ENTRY <?php echo htmlspecialchars($currentId); ?>" style="font-weight: bold; text-align: center; font-size: 1.2rem;" onfocus="this.select()">

            <!-- 🟦 ENTRY <?php // echo htmlspecialchars($currentId); ?> -->
            
            <?php if ($nextLink): ?>
                <a href="<?php echo $nextLink; ?>" 
                   style="opacity: 0.2; margin-left: 5px;" 
                   title="next entry: ENTRY <?php echo htmlspecialchars($nextId); ?>">
                   &#x25B6;  <!-- ▶ -->
                </a>
            <?php endif; ?>
        </h1>
        <div class="table-name text-center">(from table <?php echo htmlspecialchars($table); ?>)</div>
    </div>

    <span id="search-results" class="search-results" tabindex="0"></span>

    <!-- <div class="header-section">
        <h1 class="text-center">🟦 ENTRY <?php // echo htmlspecialchars($idpk); ?></h1>
        <div class="table-name text-center">(from table <?php // echo htmlspecialchars($table); ?>)</div>
    </div> -->
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

    <?php if (!$table || !$idpk): ?>
        <div class="alert alert-info">No entry found.</div>
    <?php endif; ?>

    <?php if ($table && $idpk): ?>
    <form method="post" action="">
            <div class="entry-layout">
            <div class="left-section"></div>
            <div class="right-section">
            <div id="attachments-group" class="form-group">
            <label for="file_upload">attachments <span class="public-eye" title="only images are public and thus shown in the shop" style="display: <?= $entryIsPublic ? 'inline' : 'none' ?>;">(👁️)</span></label>
            <div id="drop-area" class="drop-area">
                <div class="drop-area-content">
                    drag and drop your files here or click to select
                    <input type="file" id="file-input" multiple style="display: none;">
                </div>
            </div>
            <div id="preview-container" class="preview-container">
                <?php
                if ($table && $idpk) {
                    $payload = [
                        'APIKey' => $TRAMANNAPIAPIKey,
                        'message' => "
                            \$uploadDir = './UPLOADS/$table/';
                            \$files = [];
                            if (file_exists(\$uploadDir)) {
                                \$rawFiles = glob(\$uploadDir . $idpk . '_*.*');
                                usort(\$rawFiles, function(\$a, \$b) {
                                    preg_match('/_(\d+)\./', \$a, \$matchesA);
                                    preg_match('/_(\d+)\./', \$b, \$matchesB);
                                    return (int)\$matchesA[1] - (int)\$matchesB[1];
                                });
                                foreach (\$rawFiles as \$file) {
                                    \$fileName = basename(\$file);
                                    \$extension = strtolower(pathinfo(\$fileName, PATHINFO_EXTENSION));
                                    \$files[] = [
                                        'name' => \$fileName,
                                        'ext' => \$extension,
                                        'isImage' => in_array(\$extension, ['jpg', 'jpeg', 'png', 'gif', 'svg']),
                                        'url' => \$uploadDir . \$fileName
                                    ];
                                }
                            }
                            echo json_encode(\$files);
                        "
                    ];
                
                    // Masked console log
                    $maskedPayload = $payload;
                    $maskedPayload['APIKey'] = 'HiddenForLog';
                    // echo "<script>console.log('⟪STARGATE PAYLOAD⟫ ' + " . json_encode(json_encode($maskedPayload)) . ");</script>";
                
                    // Stargate endpoint
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
                
                    // echo "<script>console.log('⟪STARGATE RAW⟫ ' + " . json_encode($response) . ");</script>";
                
                    $parsed = json_decode($response, true);
                    // echo "<script>console.log('⟪STARGATE PARSED⟫', " . json_encode($parsed) . ");</script>";
                
                    if (isset($parsed['status']) && $parsed['status'] === 'success') {
                        $files = $parsed['message'];
                        // echo "<script>console.log('⟪FILES⟫', " . json_encode($files) . ");</script>";
                    
                        foreach ($files as $file) {
                            $fileName = htmlspecialchars($file['name']);
                            $fileUrl = htmlspecialchars($file['url']) . '?v=' . time();
                            $isImage = $file['isImage'];
                        
                            // echo '<div class="preview-item' . ($isImage ? ' image-preview' : '') . '" data-filename="' . $fileName . '">';
                            // echo '<a href="' . $ExtendedBaseDirectoryCode . $fileUrl . '" target="_blank" class="preview-link">';
                            $viewer = './FileViewer.php?table=' . urlencode($table) . '&idpk=' . urlencode($idpk) . '&file=' . urlencode($fileName) . '&OriginEntry';
                            echo '<div class="preview-item' . ($isImage ? ' image-preview' : '') . '" data-filename="' . $fileName . '">';
                            echo '<a href="' . $viewer . '" class="preview-link">';
                            if ($isImage) {
                                echo '<img src="' . $ExtendedBaseDirectoryCode . '' . $fileUrl . '" alt="' . $fileName . '">';
                            } else {
                                echo '<span class="file-name">' . $fileName . '</span>';
                            }
                            echo '</a>';
                            echo '<button type="button" class="remove-btn" title="remove" onclick="removeFile(\'' . $fileName . '\')">×</button>';
                            echo '</div>';
                        }
                    } else {
                        // echo "<script>console.error('⟪STARGATE FAILED⟫', " . json_encode($parsed) . ");</script>";
                    }
                }
                // if ($table && $idpk) {
                //     $uploadDir = "../UPLOADS/TDB/$table/";
                //     if (file_exists($uploadDir)) {
                //         $files = glob($uploadDir . $idpk . "_*.*");
                //         // Sort files by their number
                //         usort($files, function($a, $b) {
                //             preg_match('/_(\d+)\./', $a, $matchesA);
                //             preg_match('/_(\d+)\./', $b, $matchesB);
                //             return (int)$matchesA[1] - (int)$matchesB[1];
                //         });
                //         
                //         foreach ($files as $file) {
                //             $fileName = basename($file);
                //             $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                //             $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
                //             
                //             echo '<div class="preview-item' . ($isImage ? ' image-preview' : '') . '" data-filename="' . htmlspecialchars($fileName) . '">';
                //             echo '<a href="' . $uploadDir . $fileName . '?v=' . time() . '" target="_blank" class="preview-link">';
                //             if ($isImage) {
                //                 echo '<img src="' . $uploadDir . $fileName . '?v=' . time() . '" alt="' . htmlspecialchars($fileName) . '">';
                //             } else {
                //                 echo '<span class="file-name">' . htmlspecialchars($fileName) . '</span>';
                //             }
                //             echo '</a>';
                //             echo '<button type="button" class="remove-btn" onclick="removeFile(\'' . htmlspecialchars($fileName) . '\')">×</button>';
                //             echo '</div>';
                //         }
                //     }
                // }
                ?>
            </div>
        </div>
        <?php foreach ($formFields as $fieldName => $fieldConfig):
            // check if this field has a dependency
            $dep = $fieldConfig['OnlyShowIfThisIsTrue'] ?? null;
            // if so, see if it should be shown initially
            $show = true;
            if ($dep) {
                $show = !empty($existingData[$dep]);
            }
        ?>
            <div 
              class="form-group<?php echo !empty($fieldConfig['HumanPrimary']) ? ' human-primary' : ''; ?>"
              <?php if ($dep): ?>
                data-depends="<?= $dep ?>"
                style="display: <?= $show ? 'block' : 'none' ?>;"
              <?php endif; ?>
            >
                <label for="<?php echo htmlspecialchars($fieldName); ?>">
                    <?php echo htmlspecialchars($fieldConfig['label']); ?>
                    <?php if (!empty($fieldConfig['ShowToPublic'])): ?>
                        <span class="public-eye" title="this value is public and thus shown in the shop" style="display: <?= $entryIsPublic ? 'inline' : 'none' ?>;">👁️</span>
                    <?php endif; ?>
                </label>
                <?php if ($fieldConfig['type'] === 'textarea'): ?>
                    <textarea 
                        id="<?php echo htmlspecialchars($fieldName); ?>"
                        name="<?php echo htmlspecialchars($fieldName); ?>"
                        rows="3"
                        class="form-control"
                        <?php echo isset($fieldConfig['placeholder']) ? 'title="' . htmlspecialchars($fieldConfig['placeholder']) . '"' : ''; ?>
                        <?php echo !empty($fieldConfig['price']) ? 'style="font-weight: bold;"' : ''; ?>
                    ><?php echo htmlspecialchars($existingData[$fieldName] ?? ''); ?></textarea>
                <?php elseif ($fieldConfig['type'] === 'checkbox'): ?>
                    <input 
                        type="checkbox"
                        id="<?php echo htmlspecialchars($fieldName); ?>"
                        name="<?php echo htmlspecialchars($fieldName); ?>"
                        class="form-control"
                        <?php echo ($existingData[$fieldName] ?? false) ? 'checked' : ''; ?>
                        <?php echo isset($fieldConfig['placeholder']) ? 'title="' . htmlspecialchars($fieldConfig['placeholder']) . '"' : ''; ?>
                    >
                <?php else: ?>
                    <input 
                        type="<?php 
                            echo !empty($fieldConfig['LinkedToWhatTable']) 
                                ? 'text' 
                                : htmlspecialchars($fieldConfig['type']); 
                        ?>"
                        id="<?php echo htmlspecialchars($fieldName); ?>"
                        name="<?php echo htmlspecialchars($fieldName); ?>"
                        value="<?php echo htmlspecialchars($existingData[$fieldName] ?? ''); ?>"
                        <?php echo isset($fieldConfig['step']) ? 'step="' . htmlspecialchars($fieldConfig['step']) . '"' : ''; ?>
                        <?php echo isset($fieldConfig['maxlength']) ? 'maxlength="' . htmlspecialchars($fieldConfig['maxlength']) . '"' : ''; ?>
                        <?php echo ($fieldConfig['type'] === 'number') ? 'onfocus="this.select()"' : ''; ?>
                        <?php if (!empty($fieldConfig['LinkedToWhatTable']) && !empty($fieldConfig['LinkedToWhatFieldThere'])): ?>
                            data-linked-table="<?php echo htmlspecialchars($fieldConfig['LinkedToWhatTable']); ?>"
                            data-linked-field="<?php echo htmlspecialchars($fieldConfig['LinkedToWhatFieldThere']); ?>"
                            data-field-name="<?php echo htmlspecialchars($fieldName); ?>"
                        <?php endif; ?>
                        class="form-control"
                        <?php echo !empty($fieldConfig['price']) ? 'style="font-weight: bold;"' : ''; ?>
                        <?php echo isset($fieldConfig['placeholder']) ? 'title="' . htmlspecialchars($fieldConfig['placeholder']) . '"' : ''; ?>
                    >
                    <div class="contact-link" data-field-name="<?php echo htmlspecialchars($fieldName); ?>"></div>
                <?php endif; ?>
                <?php if ($fieldName === $publicMarkerFieldName): ?>
                    <div class="public-page-link" style="display: <?= $entryIsPublic ? 'block' : 'none' ?>;">
                        <a href="../SHOP/index.php?company=<?php echo urlencode($companyId); ?>&table=<?php echo urlencode($table); ?>&idpk=<?php echo urlencode($currentId); ?>" target="_blank" title="entry is public and thus shown in the shop">👁️ PUBLIC PAGE</a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($fieldConfig['LinkedToWhatTable']) && !empty($fieldConfig['LinkedToWhatFieldThere'])): ?>
                    <div class="linked-entry-link" data-field-name="<?php echo htmlspecialchars($fieldName); ?>"></div>
                    <div class="linked-entry-search-results search-results fixed-height" data-field-name="<?php echo htmlspecialchars($fieldName); ?>" style="display: none;"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <!-- <br>
        <button type="submit" name="update_and_return" class="btn btn-primary">
            ↗️ SAVE AND RETURN
        </button>
        
        <br><br>
        <button type="submit" name="update_entry" class="btn btn-primary" style="opacity: 0.3;">
            ↗️ SAVE
        </button> -->

        <?php
            $reverseLinks = [];

            foreach ($allTables as $tbl) {
                if ($tbl === $table) continue;

                $fields = getFormFieldsFor($tbl);

                foreach ($fields as $fieldName => $cfg) {
                    if (
                        !empty($cfg['LinkedToWhatTable']) &&
                        !empty($cfg['LinkedToWhatFieldThere']) &&
                        $cfg['LinkedToWhatTable'] === $table &&
                        $cfg['LinkedToWhatFieldThere'] === 'idpk' &&
                        empty($cfg['IsLinkedTableGeneralTable']) // Only if not a general table
                    ) {
                        // Prepare remote Stargate message
                        $query = "\$stmt = \$pdo->prepare(\"SELECT idpk FROM `$tbl` WHERE `$fieldName` = :idpk\");
                                  \$stmt->execute([':idpk' => $currentId]);
                                  echo json_encode(\$stmt->fetchAll(PDO::FETCH_ASSOC));";
                    
                        $payload = [
                            'APIKey' => $TRAMANNAPIAPIKey,
                            'message' => $query
                        ];
                    
                        // Build Nexus URL
                        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host     = $_SERVER['HTTP_HOST'];
                        $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
                        if ($basePath === DIRECTORY_SEPARATOR) {
                            $basePath = '';
                        }
                        $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);
                    
                        // Send request
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
                            $results = is_array($responseData['message']) ? $responseData['message'] : json_decode($responseData['message'], true);
                        
                            // Determine HumanPrimary field for this table
                            $linkedConfig = getFormFieldsFor($tbl);
                            $humanField = null;
                            foreach ($linkedConfig as $fName => $fConf) {
                                if (!empty($fConf['HumanPrimary'])) {
                                    $humanField = $fName;
                                    break;
                                }
                            }
                        
                            foreach ($results as $row) {
                                $labelText = 'ENTRY ' . $row['idpk'];
                            
                                if ($humanField) {
                                    $queryLabel = "\$stmt = \$pdo->prepare(\"SELECT `$humanField` FROM `$tbl` WHERE idpk = :id\");
                                                    \$stmt->execute([':id' => " . $row['idpk'] . "]);
                                                    echo json_encode(\$stmt->fetch(PDO::FETCH_ASSOC));";
                                
                                    $payloadLabel = [
                                        'APIKey' => $TRAMANNAPIAPIKey,
                                        'message' => $queryLabel
                                    ];
                                
                                    $chLabel = curl_init($nexusUrl);
                                    curl_setopt_array($chLabel, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_POST           => true,
                                        CURLOPT_POSTFIELDS     => json_encode($payloadLabel),
                                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                                        CURLOPT_TIMEOUT        => 30
                                    ]);
                                    $responseLabel = curl_exec($chLabel);
                                    curl_close($chLabel);
                                
                                    $responseLabelData = json_decode($responseLabel, true);
                                    if (isset($responseLabelData['status']) && $responseLabelData['status'] === 'success') {
                                        $labelRow = json_decode($responseLabelData['message'], true);
                                        if ($labelRow && !empty($labelRow[$humanField])) {
                                            $labelText = strtoupper($labelRow[$humanField]) . ' (' . $row['idpk'] . ')';
                                        }
                                    }
                                }
                            
                                $url = './entry.php?table=' . urlencode($tbl) . '&idpk=' . urlencode($row['idpk']);
                                $title = 'title="ENTRY ' . htmlspecialchars($row['idpk']) . ' FROM table ' . htmlspecialchars($tbl) . '"';
                                $reverseLinks[] = "<a href=\"$url\" $title>🟦 $labelText <span style=\"opacity: 0.5;\">(FROM table $tbl)</span></a>";
                            }
                        }
                    }
                }
            }

            // foreach ($allTables as $tbl) {
            //     if ($tbl === $table) continue;
            // 
            //     $fields = getFormFieldsFor($tbl);
            // 
            //     foreach ($fields as $fieldName => $cfg) {
            //         if (
            //             !empty($cfg['LinkedToWhatTable']) &&
            //             !empty($cfg['LinkedToWhatFieldThere']) &&
            //             $cfg['LinkedToWhatTable'] === $table &&
            //             $cfg['LinkedToWhatFieldThere'] === 'idpk' &&
            //             empty($cfg['IsLinkedTableGeneralTable']) // Only if not a general table
            //         ) {
            //             $stmt = $pdo->prepare("SELECT idpk FROM `$tbl` WHERE `$fieldName` = :idpk AND IdpkOfAdmin = :adminIdpk");
            //             $stmt->bindParam(':idpk', $idpk, PDO::PARAM_INT);
            //             $stmt->bindParam(':adminIdpk', $_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);
            //             $stmt->execute();
            //             $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            //         
            //             // Determine HumanPrimary field for this table
            //             $linkedConfig = getFormFieldsFor($tbl);
            //             $humanField = null;
            //             foreach ($linkedConfig as $fName => $fConf) {
            //                 if (!empty($fConf['HumanPrimary'])) {
            //                     $humanField = $fName;
            //                     break;
            //                 }
            //             }
            //         
            //             foreach ($results as $row) {
            //                 $labelText = 'ENTRY ' . $row['idpk'];
            //             
            //                 if ($humanField) {
            //                     $stmtLabel = $pdo->prepare("SELECT `$humanField` FROM `$tbl` WHERE `idpk` = :id");
            //                     $stmtLabel->execute([':id' => $row['idpk']]);
            //                     $labelRow = $stmtLabel->fetch(PDO::FETCH_ASSOC);
            //                 
            //                     if ($labelRow && !empty($labelRow[$humanField])) {
            //                         $labelText = strtoupper($labelRow[$humanField]) . ' (' . $row['idpk'] . ')';
            //                     }
            //                 }
            //             
            //                 $url = './entry.php?table=' . urlencode($tbl) . '&idpk=' . urlencode($row['idpk']);
            //                 $title = 'title="ENTRY ' . htmlspecialchars($row['idpk']) . ' FROM table ' . htmlspecialchars($tbl) . '"';
            //             
            //                 $reverseLinks[] = "<a href=\"$url\" $title>🟦 $labelText <span style=\"opacity: 0.5;\">(FROM table $tbl)</span></a>";
            //             }
            //         }
            //     }
            // }
        
            if (!empty($reverseLinks)) {
                echo '<div id="linkedEntries"><h3 class="text-center">🔗 LINKED ENTRIES</h3><br>';
                foreach ($reverseLinks as $link) {
                    echo "$link<br>";
                }
                echo '</div>';
            }
        ?>

        <div id="removeButton" style="opacity: 0.2; text-align: center;"><a href="#">❌ REMOVE</a></div>
        </div>
        </div>
    </form>
    <?php endif; ?>
</div>







<style>
  /* only allow vertical resizing */
  textarea {
    resize: vertical;
  }

  .alert-info {
      background-color: rgba(33, 150, 243, 0.1);
      border: 1px solid #2196f3;
      color: #2196f3;
      padding: 1rem;
      border-radius: 4px;
      text-align: center;
  }

  .header-section {
      margin-bottom: 2rem;
  }

  .table-name {
      color: var(--text-color);
      opacity: 0.7;
      font-size: 1.1rem;
      margin-top: -0.5rem;
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

  textarea.form-control {
      min-height: 100px;
      resize: vertical;
  }

  input[type="checkbox"].form-control {
      width: 1.5rem;
      height: 1.5rem;
      margin: 0;
      padding: 0;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      background-color: var(--input-bg);
      cursor: pointer;
      position: relative;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
  }

  input[type="checkbox"].form-control:checked {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
  }

  input[type="checkbox"].form-control:checked::after {
      content: '✓';
      position: absolute;
      color: white;
      font-size: 1rem;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
  }

  input[type="checkbox"].form-control:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
  }

  .form-control.negative-value {
    color: red !important;
  }

  .btn-primary {
      margin-top: 1rem;
      width: 100%;
  }

  .btn-primary + .btn-primary {
      margin-top: 0.5rem;
  }

  .drop-area {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: var(--input-bg);
  }

  .drop-area:hover {
    border-color: var(--primary-color);
    background-color: var(--bg-color);
  }

  .drop-area-content {
    color: var(--text-color);
    opacity: 0.7;
  }

  .preview-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
    min-height: 50px;
  }

  .preview-item {
    position: relative;
    width: 100px;
    height: 100px;
    border: 3px solid var(--border-color);
    border-radius: 8px;
    background-color: var(--input-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: move;
    transition: transform 0.2s, box-shadow 0.2s;
    user-select: none;
  }

  .preview-item:first-child {
    border: 3px solid var(--primary-color);
  }

  .preview-item.sortable-ghost {
    opacity: 0.4;
    background: var(--primary-color);
  }

  .preview-item.sortable-chosen {
    box-shadow: 0 0 10px rgba(0,0,0,0.2);
  }

  .preview-item.image-preview {
    padding: 0;
  }

  .preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .preview-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    text-decoration: none;
    color: inherit;
    text-align: center;
    padding: 4px;
    cursor: pointer;
  }

  .preview-item.image-preview .preview-link {
    padding: 0;
  }

  .preview-link:hover {
    opacity: 0.8;
  }

  .file-name {
    font-size: 0.7em;
    color: var(--link-color);
    word-break: break-all;
    text-align: center;
  }

  button.remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    background-color: rgba(0, 0, 0, 0.5);
    border: none;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    padding: 0;
    line-height: 1;
    z-index: 1;
  }

  button.remove-btn:hover {
    background-color: rgba(0, 0, 0, 0.7);
  }

  .form-control,
  .form-group label,
  .drop-area {
    transition: opacity 0.2s ease;
  }

  /* Visually indent dependent fields */
  .form-group[data-depends] {
    margin-left: 10px;
    border-left: 2px dashed var(--border-color);
    padding-left: 10px;
  }

  .human-primary .form-control {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--primary-color);
  }

  .linked-entry-link {
    text-align: right;
    margin-top: 4px;
  }

  .public-page-link {
    text-align: right;
    margin-top: 4px;
    opacity: 0.3;
  }

  .contact-link {
    font-size: 0.8rem;
    text-align: right;
    margin-top: 4px;
    opacity: 0.3;
  }

  .public-eye {
    float: right;
    opacity: 0.2;
    font-size: 0.8rem;
  }

  input + div[id$="_note"] {
    margin-top: 0.25rem;
  }

  .search-results {
    display: none;
    background-color: var(--input-bg);
    padding: 5px;
    border-radius: 4px;
    overflow-y: auto;
    min-height: 300px;
    max-height: 600px;
    line-height: 1.4em;
    cursor: pointer;
    transition: max-height 0.3s ease;
    resize: vertical;
    text-align: left;
  }
  .container.entry-edit-container {
    width: 100%;
    margin: auto;
    max-width: 500px;
  }
  @media (min-width: 768px) {
    .container.entry-edit-container {
      max-width: 1200px;
    }
  }
  .entry-layout {
    display: block;
  }
  .left-section,
  .right-section {
    margin-bottom: 1rem;
    padding-right: 10px;
    scrollbar-gutter: stable;
  }
  @media (min-width: 768px) {
    .entry-layout {
      display: flex;
      gap: 20px;
      align-items: flex-start;
    }
    .entry-layout > .left-section,
    .entry-layout > .right-section {
      flex: 1;
      max-height: 80vh;
      overflow-y: auto;
    }
  }

  .linked-entries-section {
    margin-top: 2rem;
    display: block;
  }

   #linkedEntries {
    margin-top: 5rem;
  }

  #removeButton {
    margin-top: 10rem;
  }

  
  /* For exact/fixed height */
  .search-results.fixed-height {
    height: 200px;
    min-height: 200px;
    max-height: 400px;
  }
</style>

<!-- Add Sortable.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<script>
    function debounceInput(fn, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), wait);
        };
    }
    
    function initLinkedFieldSearches() {
        const searchFields = <?php
            $fields = getFormFieldsFor($table);
            echo json_encode($fields);
        ?>;
    
        document.querySelectorAll('input[data-linked-table]').forEach(input => {
            // One debounce per input
            const debounced = debounceInput(() => loadLinkedLabel(input), 300);
        
            // Trigger once
            loadLinkedLabel(input);
        
            // Trigger on input
            input.addEventListener('input', debounced);
        });
    }

    function loadLinkedLabel(input) {
        const linkedTable = input.dataset.linkedTable;
        const linkedField = input.dataset.linkedField;
        const fieldName = input.dataset.fieldName;
        const value = input.value.trim();

        // console.log(`[loadLinkedLabel] Input: "${value}" for "${fieldName}"`);

        const linkContainer = document.querySelector(`.linked-entry-link[data-field-name="${fieldName}"]`);
        const searchContainer = document.querySelector(`.linked-entry-search-results[data-field-name="${fieldName}"]`);

        if (!linkContainer || !searchContainer) {
            // console.warn(`[loadLinkedLabel] One or both containers missing for "${fieldName}"`);
            return;
        }

        // Clear both
        linkContainer.innerHTML = '';
        linkContainer.style.display = 'none';
        searchContainer.innerHTML = '';
        searchContainer.style.display = 'none';

        if (value === '' || value === '0') {
            // console.log('[loadLinkedLabel] Empty/zero input, nothing to do');
            return;
        }

        const linkedConfig = allFieldConfigs[linkedTable] || {};

        // Non-numeric: search
        if (!/^\d+$/.test(value)) {
            // console.log('[loadLinkedLabel] Non-numeric input, showing search');

            const formData = new FormData();
            formData.append('action', 'search');
            formData.append('query', value);
            formData.append('table', linkedTable);
            formData.append('idpk', '');
            formData.append('searchFields', JSON.stringify(linkedConfig));

            fetch('AjaxEntry.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                // console.log('[loadLinkedLabel] Search result:', data);

                if (data.success) {
                    if (!data.results || data.results.length === 0) {
                        searchContainer.innerHTML = 'No results found.';
                    } else {
                        searchContainer.innerHTML = data.results.map(r => {
                            return `
                                    <div style="margin-bottom: 0.5em;">
                                        <a href="${r.url}" data-idpk="${r.idpk}" class="search-select" data-field-name="${fieldName}">
                                            ${r.label}
                                        </a>
                                    </div>
                                `;
                        }).join('');

                        searchContainer.querySelectorAll('a.search-select').forEach(link => {
                            link.addEventListener('click', function (e) {
                                e.preventDefault();
                            
                                const idpk = this.dataset.idpk;
                                const fieldName = this.dataset.fieldName;
                            
                                const targetInput = document.querySelector(`input[data-field-name="${fieldName}"]`);
                                if (!targetInput) {
                                    console.warn(`[search-select] No input found for field "${fieldName}"`);
                                    return;
                                }
                            
                                targetInput.value = idpk;
                                // console.log(`[search-select] Selected idpk: ${idpk} for ${fieldName}`);
                            
                                // Trigger the label reload
                                loadLinkedLabel(targetInput);
                            
                                // Hide search results
                                searchContainer.style.display = 'none';
                            });
                        });

                        addTooltipPreviews(searchContainer);
                    }
                } else {
                    searchContainer.innerHTML = 'Search error.';
                }

                searchContainer.style.display = 'block';
            })
            .catch(err => {
                console.error('[loadLinkedLabel] Search error:', err);
                searchContainer.innerHTML = 'Network error.';
                searchContainer.style.display = 'block';
            });

            return; // Do NOT check linked entry
        }

        // Numeric input: show direct link
        // console.log('[loadLinkedLabel] Numeric input, checking direct linked entry');

        const formData = new FormData();
        formData.append('action', 'checkLinkedEntry');
        formData.append('linkedId', value);
        formData.append('linkedTable', linkedTable);
        formData.append('linkedField', linkedField);
        formData.append('searchFields', JSON.stringify(linkedConfig));

        fetch('AjaxEntry.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            // console.log('[loadLinkedLabel] Linked entry response:', data);

            if (data.success && data.found) {
                linkContainer.innerHTML = `<a href="${data.url}" ${data.target}>${data.label}</a>`;
            } else {
                linkContainer.innerHTML = '<span style="color: red;">not found</span>';
            }

            linkContainer.style.display = 'block';
            addTooltipPreviews(linkContainer);
        })
        .catch(err => {
            console.error('[loadLinkedLabel] Linked entry error:', err);
            linkContainer.innerHTML = '<span style="color: red;">error</span>';
            linkContainer.style.display = 'block';
        });
    }



document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const previewContainer = document.getElementById('preview-container');
    const leftCol = document.querySelector(".entry-layout .left-section");
    const rightCol = document.querySelector(".entry-layout .right-section");
    if(leftCol && rightCol){
        const attach = document.getElementById("attachments-group");
        const linked = document.getElementById("linkedEntries");
        const removeBtn = document.getElementById("removeButton");

        document.querySelectorAll(".form-group.human-primary").forEach(el => leftCol.appendChild(el));
        if(attach) leftCol.appendChild(attach);
        document.querySelectorAll("form .form-group").forEach(el => { if(!leftCol.contains(el)) rightCol.appendChild(el); });

        const updateLayout = () => {
            if(window.matchMedia('(min-width: 768px)').matches){
                if(linked) leftCol.appendChild(linked);
                if(removeBtn) leftCol.appendChild(removeBtn);
            } else {
                if(linked) rightCol.appendChild(linked);
                if(removeBtn) rightCol.appendChild(removeBtn);
            }
        };

        updateLayout();
        window.addEventListener('resize', updateLayout);
    }

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    // Highlight drop area when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    // Handle dropped files
    dropArea.addEventListener('drop', handleDrop, false);
    
    // Handle click to upload
    dropArea.addEventListener('click', () => fileInput.click());
    
    // Handle file selection
    fileInput.addEventListener('change', handleFiles);

    function preventDefaults (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropArea.classList.add('highlight');
    }

    function unhighlight(e) {
        dropArea.classList.remove('highlight');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles({ target: { files: files } });
    }

    function handleFiles(e) {
        const files = Array.from(e.target.files);
        const uploads = files.map(file => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('table', '<?php echo htmlspecialchars($table); ?>');
            formData.append('idpk', '<?php echo htmlspecialchars($idpk); ?>');

            return fetch('AjaxEntry.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json());
        });

        Promise.all(uploads)
            .then(results => {
                const failed = results.find(r => !r.success);
                if (failed) {
                    alert('Error uploading file: ' + (failed.error || 'unknown error'));
                } else {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading file');
            });
    }


    document.querySelectorAll('[data-depends]').forEach(dependentEl => {
      const controllerName = dependentEl.getAttribute('data-depends');
      const controllerEl = document.getElementById(controllerName);
        
      if (!controllerEl) return; // skip if controller doesn't exist

      // Function to evaluate the controller's value
      const updateVisibility = () => {
        let shouldShow = false;

        if (controllerEl.type === 'checkbox') {
          shouldShow = controllerEl.checked;
        } else {
          shouldShow = controllerEl.value !== '';
        }

        dependentEl.style.display = shouldShow ? 'block' : 'none';
      };

      // Initial state
      updateVisibility();

      // Bind change event
      if (controllerEl.type === 'checkbox') {
        controllerEl.addEventListener('change', updateVisibility);
      } else {
        controllerEl.addEventListener('input', updateVisibility);
      }
    });




    // Helper to set opacity based on emptiness/checked state
    function updateOpacity(field) {
      const parent = field.closest('.form-group');
      const isPrimary = parent && parent.classList.contains('human-primary');

      let hasValue;

      if (field.type === 'checkbox') {
        hasValue = field.checked;
      } else {
        const val = field.value.trim();
        const num = parseFloat(val);
        hasValue = !(val === '' || (!isNaN(num) && num === 0));
      }

      const opacity = (hasValue || isPrimary) ? '1' : '0.5';
      field.style.opacity = opacity;

      const lbl = document.querySelector(`label[for="${field.id}"]`);
      if (lbl) lbl.style.opacity = opacity;
    }

    // Find all inputs/textareas/checkboxes
    document.querySelectorAll('.form-control').forEach(field => {
      // Initialize
      updateOpacity(field);

      // Always make it fully opaque when focused
      field.addEventListener('focus', () => {
        field.style.opacity = '1';
        const lbl = document.querySelector(`label[for="${field.id}"]`);
        if (lbl) lbl.style.opacity = '1';
      });

      // On blur or value change, re-check
      const checkAndUpdate = () => updateOpacity(field);
      field.addEventListener('blur', checkAndUpdate);
      field.addEventListener('input', checkAndUpdate);
      if (field.type === 'checkbox') {
        field.addEventListener('change', checkAndUpdate);
      }
    });

    // find the label for the attachments drop-area
    const attachLabel = document.querySelector('label[for="file_upload"]')
                       || dropArea.closest('.form-group').querySelector('label');

    function updateAttachmentsOpacity() {
      const hasFiles = previewContainer.children.length > 0;
      const op = hasFiles ? '1' : '0.5';
      dropArea.style.opacity = op;
      if (attachLabel) attachLabel.style.opacity = op;
    }

    // initialize attachments opacity on load
    updateAttachmentsOpacity();

    document.querySelectorAll('.form-control').forEach(field => {
      const fieldName = field.getAttribute('name');
      const fieldConfig = <?php echo json_encode($formFields); ?>;
      const dynamicPlaceholder = (fieldConfig[fieldName] && fieldConfig[fieldName].placeholder) || '';
        
      // Show placeholder only if field is empty on focus
      field.addEventListener('focus', () => {
        if (field.value.trim() === '') {
          field.setAttribute('placeholder', dynamicPlaceholder);
        }
      });
    
      // Remove placeholder if still empty on blur
      field.addEventListener('blur', () => {
        if (field.value.trim() === '') {
          field.removeAttribute('placeholder');
        }
      });
    
      // Optional: once user types something, remove placeholder immediately
      field.addEventListener('input', () => {
        if (field.value.trim() !== '') {
          field.removeAttribute('placeholder');
        } else {
          field.setAttribute('placeholder', dynamicPlaceholder);
        }
      });
    });

    // on dragenter/dragover/click -> full opacity
    ['dragenter','dragover','click'].forEach(evt => {
      dropArea.addEventListener(evt, () => {
        dropArea.style.opacity = '1';
        if (attachLabel) attachLabel.style.opacity = '1';
      });
    });

    // on dragleave/drop -> re-evaluate
    ['dragleave','drop'].forEach(evt => {
      dropArea.addEventListener(evt, updateAttachmentsOpacity);
    });

    // on real file-input change -> re-evaluate
    fileInput.addEventListener('change', updateAttachmentsOpacity);



    document.querySelectorAll('input[data-eval-expression="true"]').forEach(function (input) {
        const originalType = input.type;

        input.addEventListener('input', function () {
            const value = input.value;
            // If user types any math symbol or parentheses, switch to text
            if (/[+\-*/()]/.test(value)) {
                if (input.type !== 'text') {
                    try {
                        input.type = 'text';
                    } catch (e) {
                        // Some browsers (esp. Firefox) won't let you change input type on the fly
                        console.warn('Unable to change input type:', e);
                    }
                }
            } else if (/^\d*\.?\d*$/.test(value)) {
                // If they go back to numeric input, switch back (optional)
                try {
                    input.type = originalType;
                } catch (e) {}
            }
        });

        input.addEventListener('blur', function () {
            const expr = input.value.trim();
            if (!expr) return;

            try {
                if (!/^[0-9+\-*/().\s]+$/.test(expr)) throw 'Invalid';
                const result = Function('"use strict"; return (' + expr + ')')();

                if (!isNaN(result) && isFinite(result)) {
                    input.value = result;
                    // Try switching back to type="number" after resolving
                    try { input.type = 'number'; } catch (e) {}
                } else {
                    throw 'Not a number';
                }
            } catch (e) {
                alert('Invalid math expression');
                input.value = '';
                try { input.type = 'number'; } catch (e) {}
            }
        });
    });


    
    // const searchFields = <?php
    //     $fields = getFormFieldsFor($table);
    //     // Instead of filtering to just searchable field names, send all with full config
    //     echo json_encode($fields);
    // ?>;
// 
    // const debounce = (func, wait) => {
    //     let timeout;
    //     return (...args) => {
    //         clearTimeout(timeout);
    //         timeout = setTimeout(() => func.apply(this, args), wait);
    //     };
    // };
// 
    // const loadLinkedLabel = (input) => {
    //     const linkedTable = input.dataset.linkedTable;
    //     const linkedField = input.dataset.linkedField;
    //     const fieldName = input.dataset.fieldName;
    //     const value = input.value.trim();
// 
    //     const linkContainer = document.querySelector(`.linked-entry-link[data-field-name="${fieldName}"]`);
    //     if (!linkContainer) return;
// 
    //     // Skip processing if value is empty or 0
    //     if (value === '' || value === '0') {
    //         linkContainer.innerHTML = '';
    //         // Optionally reset class here if needed
    //         linkContainer.className = 'linked-entry-link'; // or whatever the default class should be
    //         return;
    //     }
// 
    //     if (!/^\d+$/.test(value)) {
    //         linkContainer.className = 'search-results fixed-height';
    //         linkContainer.style.display = 'block';
    //         
    //         const linkedTable = input.dataset.linkedTable;
    //         const linkedField = input.dataset.linkedField;
    //         const linkedConfig = allFieldConfigs[linkedTable] || {};
// 
    //         const formData = new FormData();
    //         formData.append('action', 'search');
    //         formData.append('query', value);
    //         formData.append('table', linkedTable);
    //         formData.append('idpk', ''); // If you have an idpk, add it here or leave empty
    //         formData.append('searchFields', JSON.stringify(linkedConfig));
// 
    //         fetch('AjaxEntry.php', {
    //             method: 'POST',
    //             body: formData
    //         })
    //         .then(res => res.json())
    //         .then(data => {
    //             if (data.success) {
    //                 if (!data.results || data.results.length === 0) {
    //                     linkContainer.innerHTML = 'We are very sorry, but we could not find any results for your search, please try again with different keywords.';
    //                 } else {
    //                     linkContainer.innerHTML = data.results.map(r => {
    //                         return `<div style="margin-bottom: 0.5em;"><a href="${r.url}" target="${r.idpk}">${r.label}</a></div>`;
    //                     }).join('');
    //                     addTooltipPreviews(linkContainer);
    //                 }
    //             } else {
    //                 linkContainer.innerHTML = 'We are very sorry, but an unexpected search error occurred while searching, please try again later.';
    //             }
    //         })
    //         .catch(() => {
    //             linkContainer.innerHTML = 'We are very sorry, but an unexpected network error occurred while searching, please try again later.';
    //         });
    //     
    //         return; // Stop further processing
    //     }
// 
    //     // If valid number, reset to original class or desired class for valid state
    //     linkContainer.className = 'linked-entry-link'; // reset class
    //     const formData = new FormData();
    //     formData.append('action', 'checkLinkedEntry');
    //     formData.append('linkedId', value);
    //     formData.append('linkedTable', linkedTable);
    //     formData.append('linkedField', linkedField);
    //     const linkedConfig = allFieldConfigs[linkedTable] || {};
    //     formData.append('searchFields', JSON.stringify(linkedConfig));
// 
    //     fetch('AjaxEntry.php', {
    //         method: 'POST',
    //         body: formData
    //     })
    //     .then(res => res.json())
    //     .then(data => {
    //         if (data.success && data.found) {
    //             linkContainer.innerHTML = `<a href="${data.url}" ${data.target}>${data.label}</a>`;
    //             // Re-apply tooltip previews to the new link(s)
    //             addTooltipPreviews(linkContainer);
    //         } else {
    //             linkContainer.innerHTML = '<span style="color: red;">not found</span>';
    //         }
    //     })
    //     .catch(() => {
    //         console.error("Fetch error:", err);
    //         linkContainer.innerHTML = '<span style="color: red;">error</span>';
    //     });
    // };
// 
    // document.querySelectorAll('input[data-linked-table]').forEach(input => {
    //     const debouncedSearch = debounce(() => loadLinkedLabel(input), 300);
// 
    //     // Trigger once on page load
    //     loadLinkedLabel(input);
// 
    //     // Trigger on user input
    //     input.addEventListener('input', debouncedSearch);
    // });



    initLinkedFieldSearches();



    addTooltipPreviews();
});

function removeFile(fileName) {
    if (confirm('Are you sure you want to remove this file?')) {
        const formData = new FormData();
        formData.append('action', 'remove_file');
        formData.append('table', '<?php echo htmlspecialchars($table); ?>');
        formData.append('idpk', '<?php echo htmlspecialchars($idpk); ?>');
        formData.append('file_name', fileName);

        fetch('AjaxEntry.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error removing file: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing file');
        });
    }
}

function describeDateDistance(inputEl, type) {
  const noteId = inputEl.id + '_note';
  let noteEl = document.getElementById(noteId);
  if (!noteEl) {
    noteEl = document.createElement('div');
    noteEl.id = noteId;
    noteEl.style.opacity = '0.3';
    noteEl.style.fontSize = '0.8em';
    inputEl.insertAdjacentElement('afterend', noteEl);
  }

  const val = inputEl.value;
  if (!val) {
    noteEl.textContent = '';
    noteEl.title = '';
    return;
  }

  const now = new Date();
  const todayMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  let inputDate;
  try {
    if (type === 'date') {
      const [y, m, d] = val.split('-').map(Number);
      inputDate = new Date(y, m - 1, d);
    } else if (type === 'datetime-local') {
      const [datePart] = val.split('T');
      const [y, m, d] = datePart.split('-').map(Number);
      inputDate = new Date(y, m - 1, d);
    } else {
      noteEl.textContent = '';
      noteEl.title = '';
      return;
    }
  } catch {
    noteEl.textContent = '';
    noteEl.title = '';
    return;
  }

  const msPerDay = 86400000;
  const diffDays = Math.floor((inputDate - todayMidnight) / msPerDay);
  const absDiff = Math.abs(diffDays);
  let label = '';

  function monthsBetween(a, b) {
    return (a.getFullYear() - b.getFullYear()) * 12 + (a.getMonth() - b.getMonth());
  }

  if (absDiff <= 366) {
    if (diffDays === -1) label = '(yesterday)';
    else if (diffDays === 0) label = '(today)';
    else if (diffDays === 1) label = '(tomorrow)';
    else if (Math.abs(diffDays) <= 7) {
      const weekday = inputDate.toLocaleDateString('en-US', { weekday: 'long' });
      label = diffDays < 0 ? `(last ${weekday})` : `(next ${weekday})`;
    } else if (Math.abs(diffDays) <= 14) {
      label = diffDays < 0 ? '(the week before last week)' : '(the week after next week)';
    } else {
      const diffMonths = monthsBetween(inputDate, todayMidnight);
      if (diffMonths === -1) label = '(last month)';
      else if (diffMonths === 1) label = '(next month)';
      else if (diffMonths < -1) label = `(${Math.abs(diffMonths)} months ago)`;
      else if (diffMonths > 1) label = `(in ${diffMonths} months)`;
      else {
        const diffWeeks = Math.round(Math.abs(diffDays) / 7);
        if (diffWeeks > 0) {
          const weekWord = diffWeeks === 1 ? 'week' : 'weeks';
          label = diffDays < 0
            ? `(${diffWeeks} ${weekWord} ago)`
            : `(in ${diffWeeks} ${weekWord})`;
        }
      }
    }
  }
}

document.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(el => {
  const type = el.type;
  // run immediately
  describeDateDistance(el, type);

  // update on input
  el.addEventListener('input', () => describeDateDistance(el, type));
});

document.querySelectorAll('input[type="number"]').forEach(input => {
  function updateNumberColor() {
    const val = parseFloat(input.value);
    if (!isNaN(val) && val < 0) {
      input.classList.add('negative-value');
    } else {
      input.classList.remove('negative-value');
    }
  }

  // Initialize on load
  updateNumberColor();

  // Re-check on change/input
  input.addEventListener('input', updateNumberColor);
});



// /////////////////////////////////////////////////////////////////////////////////////// update
function saveEntryViaAjax(table, idpk, formElement, searchFieldsUpdating) {
    const savingIndicator = document.getElementById('SavingIndicator');
    if (savingIndicator) {
        savingIndicator.style.opacity = '0.3';
        savingIndicator.textContent = '☁️ saving...';
    }

    const formData = new FormData(formElement);
    formData.append('action', 'update');
    formData.append('table', table);
    formData.append('idpk', idpk);
    formData.append('searchFieldsUpdating', JSON.stringify(searchFieldsUpdating));

    return fetch('AjaxEntry.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (savingIndicator) {
            if (data.success) {
                setTimeout(() => {
                    savingIndicator.textContent = '✔️ saved';
                    savingIndicator.style.opacity = '0.3';
                    
                    // Fade back to 0.5 opacity after 1.5 seconds if successful
                    setTimeout(() => {
                        savingIndicator.style.opacity = '0.3';
                    }, 1500);
                }, 300); // 0.3 seconds delay on "saving..."
            } else {
                savingIndicator.textContent = '❌ saving failed';
                savingIndicator.style.opacity = '1';
            }
            // Fade back to 0.5 opacity after 1.5 seconds if successful
            if (data.success) {
                setTimeout(() => {
                    savingIndicator.style.opacity = '0.3';
                }, 1500);
            }
        }

        if (data.success) {
            console.log('entry saved');
        } else {
            console.error('saving failed:', data.error);
        }
        return data;
    });
}

const table = <?= json_encode($table) ?>;
const idpk = <?= json_encode($idpk) ?>;
const searchFieldsUpdating = <?= json_encode($formFields) ?>;

const form = document.querySelector('form'); // or your specific form selector

// Debounce helper so it doesn't spam requests too fast
function debounce(func, wait) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

const debouncedSave = debounce(() => {
  saveEntryViaAjax(table, idpk, form, searchFieldsUpdating);
}, 700); // waits 700ms after last input

// Attach event listeners on inputs, selects, textareas
form.querySelectorAll('input, textarea, select').forEach(input => {
  if (input.type === 'checkbox' || input.tagName.toLowerCase() === 'select') {
    input.addEventListener('change', debouncedSave);
  } else {
    input.addEventListener('input', debouncedSave);
  }
});



// /////////////////////////////////////////////////////////////////////////////////////// create new entry
document.getElementById('createNewEntry').addEventListener('click', function (e) {
    e.preventDefault();
    
    const table = <?= json_encode($table) ?>;

    const formData = new FormData();
    formData.append('action', 'create');

    fetch('AjaxEntry.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success' && data.message?.newIdpk) {
            window.location.href = './entry.php?table=' + encodeURIComponent(table) + '&idpk=' + encodeURIComponent(data.message.newIdpk);
        } else {
            alert('Failed to create new entry: ' + (data.error || 'Unknown error'));
            console.error(data);
        }
    });
});



// /////////////////////////////////////////////////////////////////////////////////////// search
const searchFields = <?php
    $fields = getFormFieldsFor($table);
    // Instead of filtering to just searchable field names, send all with full config
    echo json_encode($fields);
?>;

const input = document.getElementById('entrySearchInput');
const results = document.getElementById('search-results');
const originalValue = input.value;
let debounceTimeout;
let displayTimeout;  // To delay showing results
let spacingRows;

function showResultsBox() {
    results.style.display = 'block';
}

function hideResultsBox() {
    results.style.display = 'none';
}

function sendSearchQuery(query, table, idpk, searchFields) {
    const formData = new FormData();
    formData.append('action', 'search');
    formData.append('query', query);
    formData.append('searchFields', JSON.stringify(searchFields));

    return fetch('AjaxEntry.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(text => {
        // console.log('RAW RESPONSE:', text);
        return JSON.parse(text);
    });
}

input.addEventListener('input', () => {
    clearTimeout(debounceTimeout);
    clearTimeout(displayTimeout);  // Cancel any pending display update
    
    debounceTimeout = setTimeout(() => {
        let value = input.value.trim();
        value = value.replace(/^🟦\s*ENTRY\s*/i, '');

        if (value) {
            showResultsBox();

            sendSearchQuery(value, '<?php echo htmlspecialchars($table); ?>', '<?php echo htmlspecialchars($idpk); ?>', searchFields)
                .then(data => {
                    if (data.success) {
                        if (!data.results || data.results.length === 0) {
                            // Wait 1 second before showing the message that no results were found"
                            displayTimeout = setTimeout(() => {
                                results.textContent = 'We are very sorry, but we could not find any results for your search, please try again with different keywords.';
                            }, 1000);
                        } else {
                            // Format results nicely (adjust as needed)
                            results.innerHTML = data.results.map(r => {
                              return `<div style="margin-bottom: 0.5em;"><a href="${r.url}" target="${r.target}">${r.label}</a></div>`;
                            }).join('');
                            addTooltipPreviews(results);
                        }
                    } else {
                        results.textContent = 'We are very sorry, but an unexpected search error occurred while searching, please try again later.';
                    }
                })
                .catch(() => {
                    results.textContent = 'We are very sorry, but an unexpected network error occurred while searching, please try again later.';
                });

        } else {
            hideResultsBox();
        }

        if (!spacingRows) {
            spacingRows = document.createElement('div');
            spacingRows.id = 'spacing-rows';
            spacingRows.innerHTML = '<br><br><br><br><br><hr><br>';
            results.insertAdjacentElement('afterend', spacingRows);
        }
    }, 300);
});

input.addEventListener('blur', () => {
    setTimeout(() => {
        if (!results.contains(document.activeElement)) {
            input.value = originalValue;
            results.textContent = '';
            hideResultsBox();
            if (spacingRows) {
                spacingRows.remove();
            }
        }
    }, 100);
});
</script>

<script>
// /////////////////////////////////////////////////////////////////////////////////////// calculations
// can we please the moment the user starts enterign somethign intotan input type number, we save everythign he enters in JS, if he enters something thats e.g.: (3+3)/3, we on leaving hte field, change the fields value to the results of tha tequation?
// THATS IMPOSSIBLE, that swhy i want ot save everythginth euser enters into some JS elkement,s o i dont depend on raeadign hte acutyl field value, as soo as the use open a number fgield, we shoudl lstien to the keybord keys he preses and then recosntruct waht he enters from there

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('input[type="number"]:not([data-linked-table])').forEach(numberInput => {
        const originalType = numberInput.type;

        // When user focuses the number input, replace it with a text input overlay
        numberInput.addEventListener("focus", () => {
            numberInput.dataset.originalValue = numberInput.value;
            numberInput.dataset.originalTitle = numberInput.getAttribute("title") || "";

            if (numberInput.hasAttribute("inputmode")) {
                numberInput.dataset.originalInputmode = numberInput.getAttribute("inputmode") || "";
            } else {
                delete numberInput.dataset.originalInputmode;
            }

            try {
                numberInput.type = "text";
            } catch (error) {
                numberInput.setAttribute("type", "text");
            }
            numberInput.setAttribute("inputmode", "decimal");

            requestAnimationFrame(() => {
                if (typeof numberInput.select === "function") {
                    numberInput.select();
                }
            });
        });

        numberInput.addEventListener("blur", () => {
            const raw = numberInput.value.trim();
            const previousValue = numberInput.dataset.originalValue ?? "";
            const previousTitle = numberInput.dataset.originalTitle ?? "";
            let finalValue = previousValue;
            let finalTitle = previousTitle;
                    
            if (raw !== "") {
                try {
                    const result = Function('"use strict"; return (' + raw + ')')();
                
                    if (!isNaN(result)) {
                        const stepAttr = numberInput.getAttribute("step");
                        let precision = 0;
                        if (stepAttr && stepAttr.includes(".")) {
                            precision = stepAttr.split(".")[1].length;
                        }
                    
                        const rounded = precision > 0
                            ? Number(result).toFixed(precision)
                            : Math.round(result);
                    
                        finalValue = String(rounded);
                    
                        const wasExpression = raw !== String(result);
                        const wasRounded = precision > 0 && Number(rounded) !== Number(result);
                    
                        if (wasRounded && wasExpression) {
                            finalTitle = `rounded (${raw})`;
                        } else if (wasRounded) {
                            finalTitle = `rounded ${result}`;
                        } else if (wasExpression) {
                            finalTitle = raw;
                        } else {
                            finalTitle = "";
                        }
                    }
                } catch {
                    // Invalid expression, keep old value
                    finalValue = previousValue;
                    finalTitle = previousTitle;
                }
            } else {
                // Invalid expression, keep old value
                finalValue = previousValue;
                finalTitle = previousTitle;
            }
        
            numberInput.value = finalValue;

            if (finalTitle) {
                numberInput.setAttribute("title", finalTitle);
            } else {
                numberInput.removeAttribute("title");
            }

            try {
                numberInput.type = originalType;
            } catch (error) {
                numberInput.setAttribute("type", originalType);
            }

            if ("originalInputmode" in numberInput.dataset) {
                numberInput.setAttribute("inputmode", numberInput.dataset.originalInputmode);
                delete numberInput.dataset.originalInputmode;
            } else {
                numberInput.removeAttribute("inputmode");
            }

            delete numberInput.dataset.originalValue;
            delete numberInput.dataset.originalTitle;

            numberInput.dispatchEvent(new Event('input'));
        });
    });
});



document.getElementById('removeButton').addEventListener('click', function () {
    if (!confirm('Are you sure you want to remove this entry?')) {
        return;
    }

    const form = document.querySelector('form');

    // Create a hidden input to indicate deletion
    let removeInput = document.getElementById('removeInput');
    if (!removeInput) {
        removeInput = document.createElement('input');
        removeInput.type = 'hidden';
        removeInput.name = 'remove_entry';
        removeInput.id = 'removeInput';
        form.appendChild(removeInput);
    }

    // Clear other submit button names to avoid conflict
    document.querySelectorAll('button[type="submit"]').forEach(btn => btn.name = '');

    form.submit();
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const isVisible = el => el.offsetParent !== null;

    const focusNextInput = (current, reverse = false) => {
        const inputs = Array.from(document.querySelectorAll(
            'input.form-control:not([type=hidden]):not([style*="visibility: hidden"]), textarea.form-control'
        )).filter(isVisible);

        const index = inputs.indexOf(current);
        if (index !== -1) {
            let next;
            if (reverse) {
                next = inputs[index - 1] || inputs[inputs.length - 1]; // jump to last if at top
            } else {
                next = inputs[index + 1] || inputs[0]; // jump to first if at bottom
            }
            if (next) {
                next.focus();
                if (next.select) next.select();
            }
        }
    };

    document.addEventListener("keydown", e => {
        const target = e.target;

        const navKeys = ["ArrowDown", "ArrowRight", "ArrowUp", "ArrowLeft", "Enter"];

        // If no input is focused and a navigation key is pressed, focus the first input
        if (!document.activeElement || !document.activeElement.matches('input.form-control, textarea.form-control')) {
            if (navKeys.includes(e.key)) {
                const firstInput = Array.from(document.querySelectorAll(
                    'input.form-control:not([type=hidden]), textarea.form-control'
                )).find(el => el.offsetParent !== null);
                if (firstInput) {
                    firstInput.focus();
                    if (firstInput.select) firstInput.select();
                    e.preventDefault();
                    return; // Stop further handling this event
                }
            }
        }

        if (e.key === "Escape") {
            e.preventDefault();  // Prevent default immediately

            // Call saveEntryViaAjax and then trigger the back link only after save succeeds
            saveEntryViaAjax(table, idpk, form, searchFieldsUpdating).then(data => {
                if (data.success) {
                    const returnLink = document.querySelector('a[title*="back to the future"]');
                    if (returnLink) returnLink.click();
                } else {
                    alert('We are very sorry, but we were unable to save your changes. Please try again or leave manually over the link in the top right corner.');
                }
            });

            return; // Stop any further handling for Escape
        }

        // Skip if modifier keys are pressed
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        // Handle navigation keys
        if (["ArrowDown", "ArrowRight", "ArrowUp", "ArrowLeft", "Enter"].includes(e.key)) {
            if (target.matches('input.form-control, textarea.form-control')) {
                const type = target.getAttribute('type') || target.tagName.toLowerCase();

                if (e.key === "Enter") {
                    if (type === "textarea") {
                        return; // Let Enter insert a newline in textarea
                    } else if (type === "checkbox") {
                        e.preventDefault();
                        target.checked = !target.checked;
                        target.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    } else {
                        e.preventDefault();
                        focusNextInput(target);
                        return;
                    }
                }

                if (["ArrowDown", "ArrowRight"].includes(e.key)) {
                    e.preventDefault();
                    focusNextInput(target);
                } else if (["ArrowUp", "ArrowLeft"].includes(e.key)) {
                    e.preventDefault();
                    focusNextInput(target, true);
                }
            }
        }
    });

    // --- Textarea expand/contract logic ---
    const defaultTextareaHeight = "100px"; // adjust if your base textarea height is different

    let lastFocusedTextarea = null;

    document.querySelectorAll("textarea.form-control").forEach(textarea => {
        textarea.style.transition = "height 0.2s ease";
        textarea.style.height = defaultTextareaHeight;

        textarea.addEventListener("focus", () => {
            textarea.style.height = `calc(${defaultTextareaHeight} + 200px)`;
            lastFocusedTextarea = textarea;
        });

        textarea.addEventListener("blur", () => {
            textarea.style.height = defaultTextareaHeight;
        });
    });

    document.addEventListener("click", (e) => {
        if (
            lastFocusedTextarea &&
            !lastFocusedTextarea.contains(e.target) &&
            e.target !== lastFocusedTextarea
        ) {
            lastFocusedTextarea.style.height = defaultTextareaHeight;
            lastFocusedTextarea = null;
        }
    });

    /*
    const entryCard = document.querySelector('.entry-edit-container');
    document.addEventListener('click', (e) => {
        if (entryCard && !entryCard.contains(e.target)) {
            saveEntryViaAjax(table, idpk, form, searchFieldsUpdating).then(data => {
                if (data.success) {
                    const returnLink = document.querySelector('a[title*="back to the future"]');
                    if (returnLink) returnLink.click();
                } else {
                    alert('We are very sorry, but we were unable to save your changes. Please try again or leave manually over the link in the top left corner.');
                }
            });
        }
    });
    */
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const phoneRegex = /^\+?[0-9 ()-]{6,}$/;

    function isLikelyPhone(val) {
        if (!phoneRegex.test(val)) return false;
        const digits = val.replace(/[^0-9]/g, '');
        return digits.length >= 6 && digits.length <= 15;
    }

    function updateContactLink(input) {
        const container = document.querySelector(`.contact-link[data-field-name="${input.name}"]`);
        if (!container) return;

        const val = input.value.trim();
        container.innerHTML = '';
        container.style.display = 'none';
        if (!val) return;

        // Skip adding contact links when this field links to another entry
        if (input.dataset.linkedTable) {
            return;
        }

        if (emailRegex.test(val)) {
            const a = document.createElement('a');
            a.href = 'index.php?pm=' + encodeURIComponent('Please write an email to ' + val);
            a.textContent = '✉️ EMAIL';
            container.appendChild(a);
            container.style.display = 'block';
        } else if (isLikelyPhone(val)) {
            const cleaned = val.replace(/[^0-9+]/g, '');
            const a = document.createElement('a');
            a.href = 'tel:' + cleaned;
            a.textContent = '📞 PHONE';
            a.addEventListener('click', e => {
                if (!confirm('Do you want to call ' + cleaned + '?')) {
                    e.preventDefault();
                }
            });
            container.appendChild(a);
            container.style.display = 'block';
        }
    }

    document.querySelectorAll('input.form-control').forEach(input => {
        if (input.type === 'checkbox') return;
        updateContactLink(input);
        input.addEventListener('input', () => updateContactLink(input));
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const markerFieldId = <?php echo $publicMarkerFieldName ? json_encode($publicMarkerFieldName) : 'null'; ?>;
    if (!markerFieldId) return;

    const markerField = document.getElementById(markerFieldId);
    const publicPageLink = document.querySelector('.public-page-link');

    const updatePublicIndicators = () => {
        if (!markerField) return;
        let val;
        if (markerField.type === 'checkbox') {
            val = markerField.checked ? '1' : '';
        } else {
            val = markerField.value;
        }
        const isPublic = ['1', 'true'].includes(String(val).toLowerCase());
        if (publicPageLink) {
            publicPageLink.style.display = isPublic ? 'block' : 'none';
        }
        document.querySelectorAll('.public-eye').forEach(eye => {
            eye.style.display = isPublic ? 'inline' : 'none';
        });
    };

    updatePublicIndicators();
    const evt = markerField.type === 'checkbox' ? 'change' : 'input';
    markerField.addEventListener(evt, updatePublicIndicators);
});
</script>

<?php require_once('footer.php'); ?>
