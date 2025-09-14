<?php
include_once '../config.php';

session_start();

header('Content-Type: application/json'); // Ensure the response is in JSON format

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');  // Disable outputting errors directly





// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// preparations

// currently, 1200 requests per 60 seconds (please mind that all of these requests can also be in the first second, all at once)
function isRateLimited($identifier, $limit = 1200, $window = 60) {
    $rateLimitDir = __DIR__ . '/rate_limit_logs';
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }

    $file = $rateLimitDir . '/' . md5($identifier) . '.json';

    $currentTime = time();
    $windowStart = $currentTime - $window;

    $requests = [];

    if (file_exists($file)) {
        $requests = json_decode(file_get_contents($file), true);
        $requests = array_filter($requests, function ($timestamp) use ($windowStart) {
            return $timestamp >= $windowStart;
        });
    }

    if (count($requests) >= $limit) {
        return true; // Rate limit exceeded
    }

    $requests[] = $currentTime;
    file_put_contents($file, json_encode($requests));

    return false;
}




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the input
    // 1) Get the Content-Type header
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // 2) Attempt to parse JSON if appropriate
    $rawInput = file_get_contents('php://input');
    $parsed   = null;
    if (stripos($contentType, 'application/json') !== false) {
        $parsed = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid JSON payload: ' . json_last_error_msg()
            ]);
            exit;
        }
    }
    
    // 3) Merge into a single $data array
    //    If JSON was valid, use it; otherwise fall back to $_REQUEST (POST/GET)
    $data = is_array($parsed) ? $parsed : $_REQUEST;

    // Validate the input
    if (empty($data['APIKey']) || empty($data['message'])) {        echo json_encode([
            "status" => "error",
            "message" => "We are very sorry, but there is a problem with missing required parameters (APIKey or or message)."
        ]);
        exit;
    }

    // Extract parameters
    $apiKey        = $data['APIKey'];
    $contentMessage = $data['message'];

    $MakeLogEntry = $data['MakeLogEntry'] ?? null;

    try {
        // Try to find API key in the admins table
        $query = "SELECT idpk, APIKey, TimestampCreation, DbHost FROM admins WHERE APIKey = :apiKey LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':apiKey', $apiKey, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $requestingAgentIdpk = $result['idpk'];
            $requestingAgentAdminOrUser = 'admin';
            $requestingAgentAdminIdpk = $result['idpk'];

            // Additional data from admin
            $TRAMANNAPIKey = $result['APIKey'];
            $PersonalKey = $result['TimestampCreation'];
            $mysqlDbServer = $result['DbHost']; // this is something like: www.example.com
        } else {
            // Not found in admins, try users table
             $query = "
                SELECT 
                    users.idpk AS userIdpk,
                    users.IdpkOfAdmin,
                    admins.APIKey,
                    admins.TimestampCreation,
                    admins.DbHost
                FROM users
                JOIN admins ON users.IdpkOfAdmin = admins.idpk
                WHERE users.APIKey = :apiKey
                LIMIT 1
            ";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':apiKey', $apiKey, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $requestingAgentIdpk = $result['userIdpk'];
                $requestingAgentAdminOrUser = 'user';
                $requestingAgentAdminIdpk = $result['IdpkOfAdmin'];
            
                // Admin data associated with user
                $TRAMANNAPIKey = $result['APIKey'];
                $PersonalKey = $result['TimestampCreation'];
                $mysqlDbServer = $result['DbHost']; // this is something like: www.example.com
            
            } else {
                // Not found in either table
                echo json_encode([
                    "status" => "error",
                    "message" => "We are very sorry, but there is a problem with the provided APIKey. Please check your - ⚙️ ACCOUNT - for the correct API key."
                ]);
                exit;
            }
        }

        $OtherStargateLocation = "https://" . $mysqlDbServer . "/STARGATE/stargate.php";
        // Now it should be: https://www.example.com/STARGATE/stargate.php

        // GeneralKey for all
        // GeneralKey for all (defined in config.php)

        // Rate limit check using the user ID or API key
        if (isRateLimited($requestingAgentIdpk)) {
            echo json_encode([
                "status" => "error",
                "message" => "We are very sorry, but the servers are experiencing high load at the moment, please try again later."
            ]);
            exit;
        }

        // // Define allowed actions and tables
        // $allowedActions = ['SELECT', 'INSERT INTO', 'UPDATE', 'DELETE', 'SEARCH'];
        // $allowedTables = [
        //     'TDBcarts',
        //     'TDBtransaction',
        //     'TDBProductsAndServices',
        //     'TDBSuppliersAndCustomers'
        // ];



        // // Normalize whitespace
        // $normalized = preg_replace('/\s+/', ' ', strtoupper($contentMessage));
// 
        // // Match action
        // preg_match('/\b(SELECT|INSERT INTO|UPDATE|DELETE)\b/', $normalized, $actionMatch);
        // $action = $actionMatch[1] ?? null;
// 
        // // Match table name
        // preg_match('/FROM\s+(`?\w+`?)/i', $contentMessage, $fromMatch);
        // $table = $fromMatch[1] ?? null;
// 
        // // Fallback for INSERT INTO or UPDATE syntax
        // if (!$table) {
        //     preg_match('/(?:INSERT INTO|UPDATE)\s+(`?\w+`?)/i', $contentMessage, $altMatch);
        //     $table = $altMatch[1] ?? null;
        // }
// 
        // // Match IDPK
        // // Try to extract actual IDPK assignment from within the message
        // if (preg_match('/\$idpk\s*=\s*(\d+);/', $contentMessage, $idpkAssignMatch)) {
        //     $idpk = $idpkAssignMatch[1];
        // } else {
        //     $idpk = null;
        // }

        // This line ensures that every SQL block explicitly includes the necessary IdpkOfAdmin = :adminIdpk clause.
        // if (!preg_match('/\bIdpkOfAdmin\s*=\s*:adminIdpk\b/i', $contentMessage)) {
        //     echo json_encode([
        //         "status" => "error",
        //         "message" => "We are very sorry, but there was a critical error in the structure of the TRAMANN API command."
        //     ]);
        //     exit;
        // }



        // // Validate action
        // if (!$action || !in_array($action, $allowedActions)) {
        //     echo json_encode([
        //         "status" => "error",
        //         "message" => "We are very sorry, but the provided action could not be executed. Possible actions are: " . implode(', ', $allowedActions)
        //     ]);
        //     exit;
        // }
// 
        // // Validate table
        // if (!$table || !in_array(str_replace('`', '', $table), $allowedTables)) {
        //     echo json_encode([
        //         "status" => "error",
        //         "message" => "We are very sorry, but the provided table could not be found. Possible tables are: " . implode(', ', $allowedTables)
        //     ]);
        //     exit;
        // }

        // // Validate idpk format (just the format for now), only for SELECT, UPDATE, DELETE
        // if (in_array($action, ['SELECT', 'UPDATE', 'DELETE']) && !preg_match('/^\d+$/', $idpk)) {
        //         echo json_encode([
        //             "status" => "error",
        //             "message" => "We are very sorry, but the provided idpk is in an invalid format. It must be a single positive integer."
        //         ]);
        //         exit;
        // }

        // // Apply SQL blacklist
        // $blacklist = ['DROP', 'ALTER', 'UNION'];
        // // $blacklist = ['DROP', 'ALTER', 'EXEC', 'UNION', 'LOAD_FILE', 'INTO OUTFILE', 'xp_'];
        // // $blacklist = [
        // //    'DROP', 'ALTER', 'EXEC', 'UNION', 'LOAD_FILE', 'INTO OUTFILE', 'xp_',
        // //    'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open',
        // //    'popen', 'assert', 'base64_decode', 'base64_encode',
        // //    'curl_exec', 'file_get_contents', 'fopen', 'fwrite', 'unlink',
        // //    'glob', 'scandir', 'mkdir', 'rmdir', 'copy', 'move_uploaded_file',
        // //    'chmod', 'chown', 'ini_set', 'dl', 'putenv', 'apache_setenv',
        // //    'highlight_file', 'phpinfo', 'exit', 'die', 'include', 'require', 'include_once', 'require_once'
        // // ];
        // foreach ($blacklist as $forbidden) {
        //     if (stripos($contentMessage, $forbidden) !== false) {
        //         echo json_encode([
        //             "status" => "error",
        //             "message" => "We are very sorry, but the TRAMANN API detected forbidden SQL keyword or syntax and exited execution to prevent further damage."
        //         ]);
        //         exit;
        //     }
        // }

        // // Make sure to always have the admin idpk as a check, add it if it shouldn't be there yet
        // // Extract the SQL statement from $contentMessage
        // preg_match('/->prepare\("([^"]+)"\)/', $contentMessage, $matches);
// 
        // if (!empty($matches[1])) {
        //     $sql = $matches[1];
        // 
        //     // Check if it contains a WHERE clause
        //     if (stripos($sql, 'where') !== false) {
        //         // Check if the admin filter is already present
        //         if (stripos($sql, 'IdpkOfAdmin') === false) {
        //             // Append the admin check to the WHERE clause
        //             $sql = preg_replace(
        //                 '/\bWHERE\b/i',
        //                 'WHERE IdpkOfAdmin = :adminIdpk AND',
        //                 $sql
        //             );
        //         }
        //     } else {
        //         // If no WHERE clause, add one
        //         $sql .= ' WHERE IdpkOfAdmin = :adminIdpk';
        //     }
        // 
        //     // Replace original query in $contentMessage
        //     $contentMessage = str_replace($matches[1], $sql, $contentMessage);
        // }
// 
        // // Ensure bindParam for :adminIdpk is present
        // if (strpos($contentMessage, 'bindParam(\':adminIdpk\'') === false) {
        //     // Insert bindParam line before $stmt->execute();
        //     $bindLine = "\$stmt->bindParam(':adminIdpk', \$_SESSION['IdpkOfAdmin'], PDO::PARAM_INT);";
        // 
        //     // Find location of $stmt->execute(); and inject before it
        //     $contentMessage = preg_replace(
        //         '/(\$stmt->execute\s*\(\s*\)\s*;)/',
        //         $bindLine . "\n" . '$1',
        //         $contentMessage,
        //         1
        //     );
        // }




    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error",
            "message" => "We are very sorry, but there is a problem with the database query: " . $e->getMessage()
        ]);
        exit;
    }























































    
    

    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// handle content
    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // Clean markdown code block wrapping (like ```php ... ```)
    // $contentMessage = preg_replace('/^```(?:php)?|```$/m', '', $contentMessage);
    if (preg_match('/^```(?:php)?\s*(.*?)\s*```$/s', $contentMessage, $matches)) {
        $contentMessage = trim($matches[1]);
    }
    $contentMessage = preg_replace('/^\s*<\?php\s*|\?>\s*$/', '', $contentMessage);
    $contentMessage = trim($contentMessage);

    ob_start(); // Start output buffering

    try {
        // Define variables expected in the code block
        $idpk = isset($idpk) ? (int)$idpk : null;
        $_SESSION['IdpkOfAdmin'] = $requestingAgentAdminIdpk;

        // // Execute the sanitized and validated code
        // eval($contentMessage); // NOTE: only do this if $contentMessage is trusted and validated
        // $responseContent = ob_get_clean();

        // The two lines above (local eval()) or the following lines (remote stargate eval())

        // Prepare data to send
        $data = [
            "GeneralKey"    => $GeneralKey,
            "PersonalKey"   => $PersonalKey,
            "TRAMANNAPIKey" => $TRAMANNAPIKey,
            "message"       => $contentMessage // was originally eval'ed
        ];
    
        // Send to Stargate
        $ch = curl_init($OtherStargateLocation);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    
        if ($response === false) {
            throw new Exception("Curl error: " . $error);
        }
    
        // Decode remote JSON response to get only the message part
        $responseDecoded = json_decode($response, true);
            
        if ($responseDecoded !== null && isset($responseDecoded['message'])) {
            $responseContent = $responseDecoded['message'];
        } else {
            $responseContent = $response; // fallback if JSON decode fails
        }

    } catch (Throwable $e) {
        ob_end_clean(); // Clear output buffer
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Execution failed: " . $e->getMessage()
        ]);
        exit;
    }

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// create log file entry
    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    $executedSQL = $contentMessage;
    $rollbackSQL = null; // You could define rollback logic based on $action

    // only log AI database actions
    if ($MakeLogEntry !== null) {
        // if ($rollbackSQL !== null) {
            $isAdmin = ($requestingAgentAdminOrUser === 'admin') ? 1 : 0;
            // Insert into logs
            try {
                // 1) Clean up logs older than 35 days for this creator
                $deleteSql = "
                    DELETE FROM logs
                     WHERE TimestampCreation < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 35 DAY)
                       AND IdpkOfCreator    = :creatorId
                ";
                $deleteStmt = $pdo->prepare($deleteSql);
                $deleteStmt->bindParam(':creatorId', $requestingAgentIdpk, PDO::PARAM_INT);
                $deleteStmt->execute();

                // 2) Insert the new log entry
                $logSql = "
                    INSERT INTO logs
                        (TimestampCreation, IdpkOfCreator, IsAdmin, IdpkOfAdmin, ExecutedSQL, RollbackSQL)
                    VALUES
                        (CURRENT_TIMESTAMP, :creatorId, :isAdmin, :adminId, :executedSQL, :rollbackSQL)
                ";
                $logStmt = $pdo->prepare($logSql);
                $logStmt->bindParam(':creatorId',   $requestingAgentIdpk,    PDO::PARAM_INT);
                $logStmt->bindParam(':isAdmin',     $isAdmin,                 PDO::PARAM_BOOL);
                $logStmt->bindParam(':adminId',   $requestingAgentAdminIdpk,    PDO::PARAM_INT);
                $logStmt->bindParam(':executedSQL', $executedSQL, PDO::PARAM_STR);
                $logStmt->bindParam(':rollbackSQL', $rollbackSQL, PDO::PARAM_STR);
                $logStmt->execute();
            } catch (\PDOException $e) {
                // If logging fails, you can choose to ignore or handle it
                error_log("Failed to write into logs: " . $e->getMessage());
            }
        // }
    }
    








    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// return the response
    // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // Return the response
    echo json_encode([
        "status" => "success",
        // "RequestingAgentIdpk" => $requestingAgentIdpk,
        // "RequestingAgentAdminOrUser" => $requestingAgentAdminOrUser,
        // "RequestingAgentAdminIdpk" => $requestingAgentAdminIdpk,
        "message" => $responseContent,
        "ExecutedSQL" => $executedSQL,
    ]);
    exit;
        




























































    



    

} else {
    // Handle unsupported request methods
    echo json_encode([
        "status" => "error",
        "message" => "We are very sorry, but there is a problem with our TRAMANN API. Please try again later or contact us."
    ]);
    exit;
}
?>

































































































































































































































































<?php
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// copy of previous version
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



// include_once '../config.php';
// 
// session_start();
// 
// header('Content-Type: application/json'); // Ensure the response is in JSON format
// 
// 
// 
// 
// 
// // ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// preparations
// 
// // currently, 120 requests per 60 seconds (please mind that all of these requests can also be in the first second, all at once)
// function isRateLimited($identifier, $limit = 120, $window = 60) {
//     $rateLimitDir = __DIR__ . '/rate_limit_logs';
//     if (!is_dir($rateLimitDir)) {
//         mkdir($rateLimitDir, 0755, true);
//     }
// 
//     $file = $rateLimitDir . '/' . md5($identifier) . '.json';
// 
//     $currentTime = time();
//     $windowStart = $currentTime - $window;
// 
//     $requests = [];
// 
//     if (file_exists($file)) {
//         $requests = json_decode(file_get_contents($file), true);
//         $requests = array_filter($requests, function ($timestamp) use ($windowStart) {
//             return $timestamp >= $windowStart;
//         });
//     }
// 
//     if (count($requests) >= $limit) {
//         return true; // Rate limit exceeded
//     }
// 
//     $requests[] = $currentTime;
//     file_put_contents($file, json_encode($requests));
// 
//     return false;
// }
// 
// 
// 
// 
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // Retrieve the input
//     // 1) Get the Content-Type header
//     $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
//     
//     // 2) Attempt to parse JSON if appropriate
//     $rawInput = file_get_contents('php://input');
//     $parsed   = null;
//     if (stripos($contentType, 'application/json') !== false) {
//         $parsed = json_decode($rawInput, true);
//         if (json_last_error() !== JSON_ERROR_NONE) {
//             http_response_code(400);
//             echo json_encode([
//                 'status'  => 'error',
//                 'message' => 'Invalid JSON payload: ' . json_last_error_msg()
//             ]);
//             exit;
//         }
//     }
//     
//     // 3) Merge into a single $data array
//     //    If JSON was valid, use it; otherwise fall back to $_REQUEST (POST/GET)
//     $data = is_array($parsed) ? $parsed : $_REQUEST;
// 
//     // Validate the input
//     if (empty($data['APIKey']) || empty($data['action']) || empty($data['table'])) {        echo json_encode([
//             "status" => "error",
//             "message" => "We are very sorry, but there is a problem with missing required parameters (APIKey, action and or or table)."
//         ]);
//         exit;
//     }
// 
//     // Extract parameters
//     $apiKey        = $data['APIKey'];
//     $contentAction = strtoupper($data['action']);
//     $contentTable  = $data['table'];
//     $contentFields = $data['fields']  ?? null;
//     $contentValues = $data['values']  ?? null;
//     $contentIdpk   = $data['idpk']    ?? null;
// 
//     try {
//         // Try to find API key in the admins table
//         $query = "SELECT idpk FROM admins WHERE APIKey = :apiKey LIMIT 1";
//         $stmt = $pdo->prepare($query);
//         $stmt->bindParam(':apiKey', $apiKey, PDO::PARAM_STR);
//         $stmt->execute();
// 
//         $result = $stmt->fetch(PDO::FETCH_ASSOC);
// 
//         if ($result) {
//             $requestingAgentIdpk = $result['idpk'];
//             $requestingAgentAdminOrUser = 'admin';
//             $requestingAgentAdminIdpk = $result['idpk'];
//         } else {
//             // Not found in admins, try users table
//             $query = "SELECT idpk, IdpkOfAdmin FROM users WHERE APIKey = :apiKey LIMIT 1";
//             $stmt = $pdo->prepare($query);
//             $stmt->bindParam(':apiKey', $apiKey, PDO::PARAM_STR);
//             $stmt->execute();
//         
//             $result = $stmt->fetch(PDO::FETCH_ASSOC);
//         
//             if ($result) {
//                 $requestingAgentIdpk = $result['idpk'];
//                 $requestingAgentAdminOrUser = 'user';
//                 $requestingAgentAdminIdpk = $result['IdpkOfAdmin'];
//             } else {
//                 // Not found in either table
//                 echo json_encode([
//                     "status" => "error",
//                     "message" => "We are very sorry, but there is a problem with the provided APIKey. Please check your - ⚙️ ACCOUNT - for the correct API key."
//                 ]);
//                 exit;
//             }
//         }
// 
//         // Rate limit check using the user ID or API key
//         if (isRateLimited($requestingAgentIdpk)) {
//             echo json_encode([
//                 "status" => "error",
//                 "message" => "We are very sorry, but the servers are experiencing high load at the moment, please try again later."
//             ]);
//             exit;
//         }
// 
//         // Define allowed actions and tables
//         $allowedActions = ['SELECT', 'INSERT INTO', 'UPDATE', 'DELETE', 'SEARCH'];
//         $allowedTables = [
//             'TDBcarts',
//             'TDBtransaction',
//             'TDBProductsAndServices',
//             'TDBSuppliersAndCustomers'
//         ];
// 
//         // Validate action
//         if (!in_array(strtoupper($contentAction), $allowedActions)) {
//             echo json_encode([
//                 "status" => "error",
//                 "message" => "We are very sorry, but the provided action could not be executed. Possible actions are: " . implode(', ', $allowedActions)
//             ]);
//             exit;
//         }
// 
//         // Validate table
//         if (!in_array($contentTable, $allowedTables)) {
//             echo json_encode([
//                 "status" => "error",
//                 "message" => "We are very sorry, but the provided table could not be found. Possible tables are: " . implode(', ', $allowedTables)
//             ]);
//             exit;
//         }
// 
//         // Validate idpk format (just the format for now), only for SELECT, UPDATE, DELETE
//         if (in_array($contentAction, ['SELECT', 'UPDATE', 'DELETE'])) {
//             if (!preg_match('/^\d+$/', $contentIdpk)) {
//                 echo json_encode([
//                     "status" => "error",
//                     "message" => "We are very sorry, but the provided idpk is in an invalid format. It must be a single positive integer."
//                 ]);
//                 exit;
//             }
//         }
// 
//         
//         
//         // Extract values and fields in arrays
// 
//         // Separating by - |#| -
//         $delimiter = ' |#| ';
// 
//         // Convert to arrays
//         $fieldArray = array_map('trim', explode($delimiter, $contentFields));
//         $valueArray = array_map('trim', explode($delimiter, $contentValues));
// 
//         // If both fields and values are provided, ensure counts match
//         if (!empty($contentFields) && !empty($contentValues)) {
//             if (count($fieldArray) !== count($valueArray)) {
//                 echo json_encode([
//                     "status" => "error",
//                     "message" => "We are very sorry, but the number of fields and values does not match. Please check your input formatting."
//                 ]);
//                 exit;
//             }
//         
//             // Combine into key-value map
//             $fieldValueMap = array_combine($fieldArray, $valueArray);
//         } else {
//             $fieldValueMap = []; // Empty or not used
//         }
// 
// 
// 
//     } catch (PDOException $e) {
//         echo json_encode([
//             "status" => "error",
//             "message" => "We are very sorry, but there is a problem with the database query: " . $e->getMessage()
//         ]);
//         exit;
//     }
// 
//     // Normalize action for switch use (e.g., "INSERT INTO" → "INSERT INTO")
//     $normalizedAction = strtoupper($contentAction);
// 
//     $responseContent = null;
//     $rawResponseContent = null;
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// table structures
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 
//     $tableDefinitions = [
//         // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// TDBcarts
//         'TDBcarts' => [
//             'idpk'                              => 'int, auto increment, primary key',                    // s*
//             'TimestampCreation'                => 'timestamp',                                            // s*
//             'IdpkOfAdmin'                      => 'int',                                                  // s*
//             'IdpkOfSupplierOrCustomer'        => 'int',                                                  // s*
//             'DeliveryType'                    => 'varchar(250)',                                          // e* e.g., standard, express
//             'WishedIdealDeliveryOrPickUpTime' => 'datetime',                                             // e
//             'CommentsNotesSpecialRequests'    => 'text',                                                 // e
//         ],
//         
//         // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// TDBtransaction
//         'TDBtransaction' => [
//             'idpk'                              => 'int, auto increment, primary key',                    // s*
//             'TimestampCreation'                => 'timestamp',                                            // s*
//             'IdpkOfAdmin'                      => 'int',                                                  // s*
//             'IdpkOfSupplierOrCustomer'        => 'int',                                                  // s*
//             'IdpkOfCart'                       => 'int',                                                  // s*
//             'IdpkOfProductOrService'          => 'int',                                                  // s*
//             'quantity'                         => 'int',                                                  // e*
//             'NetPriceTotal'                    => 'decimal(10,2)',                                        // e*
//             'TaxesTotal'                       => 'decimal(10,2)',                                        // e*
//             'CurrencyCode'                     => 'varchar(3)',                                           // e* ISO 4217
//             'state'                            => 'varchar(250)',                                         // e* e.g., pending, completed, cancelled
//             'CommentsNotesSpecialRequests'    => 'text',                                                 // e
//         ],
//         
//         // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// TDBProductsAndServices
//         'TDBProductsAndServices' => [
//             'idpk'                                            => 'int, auto increment, primary key',      // s*
//             'TimestampCreation'                              => 'timestamp',                             // s*
//             'IdpkOfAdmin'                                    => 'int',                                   // s*
//             'name'                                           => 'varchar(250)',                           // e*
//             'categories'                                     => 'varchar(250)',                           // e
//             'KeywordsForSearch'                              => 'text',                                   // e
//             'ShortDescription'                               => 'text',                                   // e
//             'LongDescription'                                => 'text',                                   // e
//             'WeightInKg'                                     => 'decimal(10,5)',                           // e
//             'DimensionsLengthInMm'                           => 'decimal(10,2)',                           // e
//             'DimensionsWidthInMm'                            => 'decimal(10,2)',                           // e
//             'DimensionsHeightInMm'                           => 'decimal(10,2)',                           // e
//             'NetPriceInCurrencyOfAdmin'                      => 'decimal(10,2)',                           // e*
//             'TaxesInPercent'                                 => 'decimal(10,2)',                           // e*
//             'VariableCostsOrPurchasingPriceInCurrencyOfAdmin'=> 'decimal(10,2)',                           // e
//             'ProductionCoefficientInLaborHours'              => 'decimal(10,2)',                           // e
//             'ManageInventory'                                => 'boolean',                                // e* true = manage stock
//             'InventoryAvailable'                             => 'int',                                    // e
//             'InventoryInProductionOrReordered'               => 'int',                                    // e
//             'InventoryMinimumLevel'                          => 'int',                                    // e
//             'InventoryLocation'                              => 'varchar(250)',                           // e
//             'PersonalNotes'                                  => 'text',                                   // e
//             'state'                                          => 'varchar(250)',                           // e* e.g., active, inactive, archived
//         ],
//         
//         // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// TDBSuppliersAndCustomers
//         'TDBSuppliersAndCustomers' => [
//             'idpk'                                 => 'int, auto increment, primary key',                 // s*
//             'TimestampCreation'                   => 'timestamp',                                        // s*
//             'IdpkOfAdmin'                         => 'int',                                               // s*
//             'CompanyName'                         => 'varchar(250)',                                      // e*
//             'email'                               => 'varchar(250)',                                      // e
//             'PhoneNumber'                         => 'bigint',                                            // e
//             'street'                              => 'varchar(250)',                                      // e
//             'HouseNumber'                         => 'int',                                               // e
//             'ZIPCode'                             => 'varchar(250)',                                      // e
//             'city'                                => 'varchar(250)',                                      // e
//             'country'                             => 'varchar(250)',                                      // e
//             'IBAN'                                => 'varchar(250)',                                      // e
//             'VATID'                               => 'varchar(250)',                                      // e
//             'PersonalNotesInGeneral'             => 'text',                                              // e
//             'PersonalNotesBusinessRelationships'=> 'text',                                              // e
//         ],
//     ];
// 
//     $tableMarks = [
//         'TDBcarts' => [
//             'IdpkOfSupplierOrCustomer' => 'mark',
//         ],
//         'TDBtransaction' => [
//             'IdpkOfSupplierOrCustomer' => 'mark',
//             'IdpkOfCart'               => 'mark',
//             'IdpkOfProductOrService'  => 'mark',
//         ],
//         'TDBProductsAndServices' => [
//             'name' => 'mark',
//         ],
//         'TDBSuppliersAndCustomers' => [
//             'CompanyName' => 'mark',
//         ],
//     ];
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
//     
//     // Helper arrays derived from the above:
//     // $allowedSelectTables   = array_keys($tableDefinitions); // All tables
//     // $allowedInsertTables   = ['CalendarEvents','carts','CustomerRelationships','documents','ProductsAndServices','transactions'];
//     // $allowedUpdateTables   = ['CalendarEvents','carts','CustomerRelationships','documents','ExplorersAndCreators','ProductsAndServices','transactions'];
//     // $allowedDeleteTables   = ['CalendarEvents','CustomerRelationships','documents','transactions'];  // transactions only if state = 0
//     // $allowedSearchTables   = array_keys($tableDefinitions); // All tables
//     $allowedSelectTables   = array_keys($tableDefinitions); // All tables
//     $allowedInsertTables   = array_keys($tableDefinitions); // All tables
//     $allowedUpdateTables   = array_keys($tableDefinitions); // All tables
//     $allowedDeleteTables   = array_keys($tableDefinitions); // All tables
//     $allowedSearchTables   = array_keys($tableDefinitions); // All tables
// 
// 
//     // if they’ve named columns, check in general if they all even exist
//     if (! empty($fieldArray)) {
//         $validColumns = array_keys($tableDefinitions[$contentTable]);
//         foreach ($fieldArray as $col) {
//             if (! in_array($col, $validColumns, true)) {
//                 echo json_encode([
//                     'status'  => 'error',
//                     'message' => "We are very sorry, but the field '$col' is not existing for the table $contentTable."
//                 ]);
//                 exit;
//             }
//         }
//     }
// 
// 
// 
// 
// 
// 
//     // **
//     //  * Returns a SQL string with all named placeholders replaced by their PDO-quoted values.
//     //  *
//     //  * @param string $sql    The query with :placeholders
//     //  * @param array  $params An associative array [':name' => $value, ...]
//     //  * @param PDO    $pdo    Your PDO connection (for quoting)
//     //  * @return string
//     //  */
//     function interpolateQuery(string $sql, array $params, PDO $pdo): string
//     {
//         foreach ($params as $key => $val) {
//             // for NULLs
//             if (is_null($val)) {
//                 $quoted = 'NULL';
//             } else {
//                 $quoted = $pdo->quote($val);
//             }
//             $sql = str_replace($key, $quoted, $sql);
//         }
//         return $sql;
//     }
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
//     
//     
// 
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// handle content
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 
//     switch ($normalizedAction) {
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// SELECT
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//         case 'SELECT':
//             if (! in_array($contentTable, $allowedInsertTables, true)) {
//                 echo json_encode([
//                     "status"  => "error",
//                     "message" => "We are very sorry, but the SELECT action is not allowed on the table $contentTable."
//                 ]);
//                 exit;
//             }
//     
//             $columnsToSelect = '*';
//             if (!empty($fieldArray)) {
//                 $columnsToSelect = implode(", ", array_map(fn($col) => "`$col`", $fieldArray));
//             }
//         
//             $selectQuery = "SELECT $columnsToSelect FROM $contentTable WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk";
//             $stmt = $pdo->prepare($selectQuery);
//             $stmt->bindParam(':idpk', $contentIdpk, PDO::PARAM_INT);
//             $stmt->bindParam(':adminIdpk', $requestingAgentAdminIdpk, PDO::PARAM_INT);
//             $stmt->execute();
//             $fetchedRow = $stmt->fetch(PDO::FETCH_ASSOC);
//         
//             if (!$fetchedRow) {
//                 echo json_encode([
//                     "status" => "error",
//                     "message" => "We are very sorry, but there was no record found with idpk = $contentIdpk in $contentTable."
//                 ]);
//                 exit;
//             }
// 
//             // now “fill in” the SQL
//             $filledSelectSQL = interpolateQuery(
//                 $selectQuery,
//                 [':idpk' => $contentIdpk],
//                 $pdo
//             );
//         
//             $responseContent = "The following values had been found for idpk = $contentIdpk in table = $contentTable: " . json_encode($fetchedRow);
//             $responseAdditionalContent = null;
//             $rawResponseContent = $fetchedRow;
//             $ExecutionDataTable = $contentTable;
//             $ExecutionDataIdpk = $contentIdpk;
//             $executedSQL = $filledSelectSQL;
//             $rollbackSQL = null;
//             break;
//     
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// INSERT INTO
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//         case 'INSERT INTO':
//             if (! in_array($contentTable, $allowedInsertTables, true)) {
//                 echo json_encode([
//                     "status"  => "error",
//                     "message" => "We are very sorry, but the INSERT INTO action is not allowed on the table $contentTable."
//                 ]);
//                 exit;
//             }
// 
//             // enforce auto‐idpk by database, current timestamp, and admin‐id
//             // remove any user‐supplied values for these keys:
//             unset(
//                 $fieldValueMap['idpk'],
//                 $fieldValueMap['TimestampCreation'],
//                 $fieldValueMap['IdpkOfAdmin']
//             );
//             // inject our own values:
//             $fieldValueMap['TimestampCreation'] = date('Y-m-d H:i:s');            // current time
//             $fieldValueMap['IdpkOfAdmin']       = $requestingAgentAdminIdpk;      // (over-)write authorship
// 
//             // Clean up string 'NULL' and cast to real NULL
//             $fieldValueMap = array_map(function ($val) {
//                 if (is_string($val) && strtoupper(trim($val)) === 'NULL') {
//                     return null;
//                 }
//                 return $val;
//             }, $fieldValueMap);
//     
//             $columns = implode(", ", array_keys($fieldValueMap));
//             $placeholders = implode(", ", array_map(fn($k) => ':' . $k, array_keys($fieldValueMap)));
// 
//             $insertQuery = "INSERT INTO $contentTable ($columns) VALUES ($placeholders)";
//             $stmt = $pdo->prepare($insertQuery);
//             foreach ($fieldValueMap as $col => $val) {
//                 $stmt->bindValue(':' . $col, $val);
//             }
//             $stmt->execute();
// 
//             $lastId = $pdo->lastInsertId();
//             $responseContent = "Inserted into table = $contentTable with idpk = $lastId";
//             $responseAdditionalContent = null;
//             $rawResponseContent = "Inserted into $contentTable with idpk = $lastId";
//             $ExecutionDataTable = $contentTable;
//             $ExecutionDataIdpk = $lastId;
// 
//             // human-readable SQL:
//             $executedSQL = interpolateQuery(
//                 $insertQuery,
//                 // bind array must match what you bound above:
//                 array_combine(
//                     array_map(fn($c)=>":$c", array_keys($fieldValueMap)),
//                     array_values($fieldValueMap)
//                 ),
//                 $pdo
//             );
//         
//             // rollback stays the same
//             $rollbackSQL = "DELETE FROM $contentTable WHERE idpk = $lastId";
//             break;
//     
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// UPDATE
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//         case 'UPDATE':
//             if (! in_array($contentTable, $allowedUpdateTables, true)) {
//                 echo json_encode([
//                     "status"  => "error",
//                     "message" => "We are very sorry, but the UPDATE action is not allowed on the table $contentTable."
//                 ]);
//                 exit;
//             }
//     
//             // Fetch current values for rollback
//             $selectOld = $pdo->prepare("SELECT * FROM $contentTable WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
//             $selectOld->bindParam(':idpk', $contentIdpk, PDO::PARAM_INT);
//             $selectOld->bindParam(':adminIdpk',  $requestingAgentAdminIdpk, PDO::PARAM_INT);
//             $selectOld->execute();
//             $oldData = $selectOld->fetch(PDO::FETCH_ASSOC);
// 
//             if (!$oldData) {
//                 echo json_encode([
//                     "status" => "error",
//                     "message" => "We are very sorry, but there was no record found with idpk = $contentIdpk in $contentTable."
//                 ]);
//                 exit;
//             }
// 
//             $setClause = implode(", ", array_map(fn($col) => "$col = :$col", array_keys($fieldValueMap)));
//             $updateQuery = "UPDATE $contentTable SET $setClause WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk";
//             $stmt = $pdo->prepare($updateQuery);
//             foreach ($fieldValueMap as $col => $val) {
//                 $stmt->bindValue(':' . $col, $val);
//             }
//             $stmt->bindValue(':idpk', $contentIdpk, PDO::PARAM_INT);
//             $stmt->bindValue(':adminIdpk', $requestingAgentAdminIdpk, PDO::PARAM_INT);
//             $stmt->execute();
// 
//             // fill in both SET values and WHERE idpk
//             $executedSQL = interpolateQuery(
//                 $updateQuery,
//                 array_merge(
//                     // all your SET params:
//                     array_combine(
//                         array_map(fn($c)=>":$c", array_keys($fieldValueMap)),
//                         array_values($fieldValueMap)
//                     ),
//                     // plus the idpk
//                     [':idpk' => $contentIdpk]
//                 ),
//                 $pdo
//             );
//         
//             // your rollback generation stays as-is
//             $rollbackParts = [];
//             foreach ($fieldValueMap as $col => $_) {
//                 $orig = $oldData[$col];
//                 $escaped = is_null($orig) ? 'NULL' : $pdo->quote($orig);
//                 $rollbackParts[] = "$col = $escaped";
//             }
//             $rollbackSQL = "UPDATE $contentTable SET "
//                          . implode(", ", $rollbackParts)
//                          . " WHERE idpk = $contentIdpk";
//         
//             $responseContent = "Updated table = $contentTable where idpk = $contentIdpk";
//             $responseAdditionalContent = null;
//             $rawResponseContent = "Updated $contentTable where idpk = $contentIdpk";
//             $ExecutionDataTable = $contentTable;
//             $ExecutionDataIdpk = $contentIdpk;
//             break;
//     
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// DELETE
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//         case 'DELETE':
//             if (! in_array($contentTable, $allowedDeleteTables, true)) {
//                 echo json_encode([
//                     "status"  => "error",
//                     "message" => "We are very sorry, but the DELETE action is not allowed on the table $contentTable."
//                 ]);
//                 exit;
//             }
//     
//             // Fetch current row for rollback
//             $selectOld = $pdo->prepare("SELECT * FROM $contentTable WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk");
//             $selectOld->bindParam(':idpk', $contentIdpk, PDO::PARAM_INT);
//             $selectOld->bindParam(':adminIdpk', $requestingAgentAdminIdpk, PDO::PARAM_INT);
//             $selectOld->execute();
//             $oldData = $selectOld->fetch(PDO::FETCH_ASSOC);
// 
//             if (!$oldData) {
//                 echo json_encode([
//                     "status" => "error",
//                     "message" => "We are very sorry, but there was no record found with idpk = $contentIdpk in $contentTable."
//                 ]);
//                 exit;
//             }
// 
//             $deleteQuery = "DELETE FROM $contentTable WHERE idpk = :idpk AND IdpkOfAdmin = :adminIdpk";
//             $stmt = $pdo->prepare($deleteQuery);
//             $stmt->bindParam(':idpk', $contentIdpk, PDO::PARAM_INT);
//             $stmt->bindParam(':adminIdpk', $requestingAgentAdminIdpk, PDO::PARAM_INT);
//             $stmt->execute();
// 
//             // human-readable executed SQL:
//             $executedSQL = interpolateQuery(
//                 $deleteQuery,
//                 [':idpk' => $contentIdpk],
//                 $pdo
//             );
//         
//             // rollback still inserts the old row:
//             $columns = implode(", ", array_keys($oldData));
//             $values  = implode(", ", array_map(fn($v)=> is_null($v) ? 'NULL' : $pdo->quote($v), array_values($oldData)));
//             $rollbackSQL = "INSERT INTO $contentTable ($columns) VALUES ($values)";
//         
//             $responseContent = "Deleted from table = $contentTable where idpk = $contentIdpk";
//             $responseAdditionalContent = null;
//             $rawResponseContent = "Deleted from $contentTable where idpk = $contentIdpk";
//             $ExecutionDataTable = $contentTable;
//             $ExecutionDataIdpk = $contentIdpk;
//             break;
//     
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//     // /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// SEARCH
//     // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//         case 'SEARCH':
//             if (! in_array($contentTable, $allowedSearchTables, true)) {
//                 echo json_encode([
//                     "status"  => "error",
//                     "message" => "We are very sorry, but the SEARCH action is not allowed on the table $contentTable."
//                 ]);
//                 exit;
//             }
// 
//             if (empty($fieldValueMap)) {
//                 echo json_encode([
//                     "status"  => "error",
//                     "message" => "We are very sorry, but SEARCH requires fields and values."
//                 ]);
//                 exit;
//             }
//         
//             $conditions = [];
//             $scoreParts = [];
//             $searchParams = [];
// 
//             foreach ($fieldValueMap as $col => $val) {
//                 $ph = ":search_$col";
//                 $conditions[] = "$col LIKE $ph";
//                 $scoreParts[]  = "CASE WHEN $col LIKE $ph THEN 1 ELSE 0 END";
//                 $searchParams[$ph] = "%{$val}%";
//             }
//         
//             $searchQuery = "SELECT idpk, (".implode(" + ", $scoreParts).") AS match_score
//                             FROM $contentTable
//                             WHERE (".implode(" OR ", $conditions).")
//                                 AND IdpkOfAdmin = :adminIdpk
//                             ORDER BY match_score DESC";
//         
//             $stmt = $pdo->prepare($searchQuery);
//             foreach ($searchParams as $ph => $like) {
//                 $stmt->bindValue($ph, $like, PDO::PARAM_STR);
//             }
//             $stmt->bindValue(':adminIdpk', $requestingAgentAdminIdpk, PDO::PARAM_INT);
//             $stmt->execute();
//             $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
//         
//             if (!$results) {
//                 echo json_encode([
//                     "status"      => "success",
//                     "message"     => "No matches found.",
//                     "ExecutedSQL" => $searchQuery,
//                     "RollbackSQL" => null
//                 ]);
//                 exit;
//             }
//         
//             $matchedIdpks = array_column($results, 'idpk');
//             $responseContent = "The following idpks had been found matching the search in table = $contentTable: " . implode(", ", $matchedIdpks);
//             $responseAdditionalContent = null;
//             $rawResponseContent = implode(", ", $matchedIdpks);
//             $ExecutionDataTable = $contentTable;
//             $ExecutionDataIdpk = implode(", ", $matchedIdpks); // multiple idpks, so we just return them as a string
//         
//             // human-readable:
//             $executedSQL = interpolateQuery(
//                 $searchQuery,
//                 $searchParams,
//                 $pdo
//             );
//             $rollbackSQL = null;
//             break;
//     
//         default:
//             echo json_encode([
//                 "status"  => "error",
//                 "message" => "We are very sorry, but the provided request method is not supported."
//             ]);
//             exit;
//     }
// 
// 
// 
//     // only log mutating actions (INSERT / UPDATE / DELETE)
//     if ($rollbackSQL !== null) {
//         $isAdmin = ($requestingAgentAdminOrUser === 'admin') ? 1 : 0;
//         // Insert into logs
//         try {
//             // 1) Clean up logs older than 35 days for this creator
//             $deleteSql = "
//                 DELETE FROM logs
//                  WHERE TimestampCreation < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 35 DAY)
//                    AND IdpkOfCreator    = :creatorId
//             ";
//             $deleteStmt = $pdo->prepare($deleteSql);
//             $deleteStmt->bindParam(':creatorId', $requestingAgentIdpk, PDO::PARAM_INT);
//             $deleteStmt->execute();
// 
//             // 2) Insert the new log entry
//             $logSql = "
//                 INSERT INTO logs
//                     (TimestampCreation, IdpkOfCreator, IsAdmin, IdpkOfAdmin, ExecutedSQL, RollbackSQL)
//                 VALUES
//                     (CURRENT_TIMESTAMP, :creatorId, :isAdmin, :adminId, :executedSQL, :rollbackSQL)
//             ";
//             $logStmt = $pdo->prepare($logSql);
//             $logStmt->bindParam(':creatorId',   $requestingAgentIdpk,    PDO::PARAM_INT);
//             $logStmt->bindParam(':isAdmin',     $isAdmin,                 PDO::PARAM_BOOL);
//             $logStmt->bindParam(':adminId',   $requestingAgentAdminIdpk,    PDO::PARAM_INT);
//             $logStmt->bindParam(':executedSQL', $executedSQL, PDO::PARAM_STR);
//             $logStmt->bindParam(':rollbackSQL', $rollbackSQL, PDO::PARAM_STR);
//             $logStmt->execute();
//         } catch (\PDOException $e) {
//             // If logging fails, you can choose to ignore or handle it
//             error_log("Failed to write into logs: " . $e->getMessage());
//         }
//     }
// 
// 
// 
//     // Build responseAdditionalContent from mark fields
//     $responseAdditionalContent = null;
//     if (!empty($ExecutionDataTable) && !empty($ExecutionDataIdpk) && isset($tableMarks[$ExecutionDataTable])) {
//         $markFields = $tableMarks[$ExecutionDataTable];
//         $fieldList = array_keys($markFields);
// 
//         $query = "SELECT " . implode(", ", $fieldList) . " FROM $ExecutionDataTable WHERE idpk = :idpk";
//         $stmt = $pdo->prepare($query);
//         $stmt->bindValue(':idpk', $ExecutionDataIdpk, PDO::PARAM_INT);
//         $stmt->execute();
//         $markedValues = $stmt->fetch(PDO::FETCH_ASSOC);
// 
//         if ($markedValues) {
//             $responseAdditionalContent = "Additional content as context: " . json_encode($markedValues);
//         }
//     }
//     
// 
// 
//     // Return the response
//     echo json_encode([
//         "status" => "success",
//         // "RequestingAgentIdpk" => $requestingAgentIdpk,
//         // "RequestingAgentAdminOrUser" => $requestingAgentAdminOrUser,
//         // "RequestingAgentAdminIdpk" => $requestingAgentAdminIdpk,
//         "message" => $responseContent,
//         "messageAdditionalContent" => $responseAdditionalContent,
//         "RawResponseContent" => $rawResponseContent,
//         "ExecutedSQL" => $executedSQL,
//         "RollbackSQL" => $rollbackSQL,
//         "ExecutionDataAction" => $normalizedAction,
//         "ExecutionDataTable" => $ExecutionDataTable,
//         "ExecutionDataIdpk" => $ExecutionDataIdpk
//     ]);
//     exit;
//         
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
// 
//     
// 
// 
// 
//     
// 
// } else {
//     // Handle unsupported request methods
//     echo json_encode([
//         "status" => "error",
//         "message" => "We are very sorry, but there is a problem with our TRAMANN API. Please try again later or contact us."
//     ]);
//     exit;
// }
?>
