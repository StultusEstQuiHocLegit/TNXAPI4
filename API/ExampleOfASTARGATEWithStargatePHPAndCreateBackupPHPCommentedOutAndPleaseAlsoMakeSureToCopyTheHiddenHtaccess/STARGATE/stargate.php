<?php
// // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// // ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// stargate partner
// // //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 
// // ################################ Place this script at: www.example.com/STARGATE/stargate.php
// 
// 
// 
// $mysqlDbServer = "################################ REPLACETHISWITHYOURACTUALSERVERPROBABLYLOCALHOST";
// $mysqlDbName = "################################ REPLACETHISWITHYOURACTUALDATABASENAME";
// $mysqlDbUser = "################################ REPLACETHISWITHYOURACTUALUSERNAME";
// $mysqlDbPassword = "################################ REPLACETHISWITHYOURACTUALPASSWORD";
// 
// 
// 
// error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
// ini_set('display_errors', '0');  // Disable outputting errors directly
// 
// try {
//     // Create a new PDO instance
//     $dsn = "mysql:host=$mysqlDbServer;dbname=$mysqlDbName;charset=utf8mb4";
//     $pdo = new PDO($dsn, $mysqlDbUser, $mysqlDbPassword);
//     
//     // Set the PDO error mode to exception
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// 
//     $configLoaded = true; // Configuration loaded successfully
// } catch (PDOException $e) {
//     // Handle connection error
//     printf("Connection to database couldn't be made: %s\n", $e->getMessage());
//     echo "<meta http-equiv=refresh content=3>";
//     echo "<br><br>Please send this error reporting to an administrator so we can fix the problem.";
//     exit();
// }
// 
// 
// // Expected GeneralKey
// $ExpectedGeneralKey = "kK4G3nXeAT5oF7vJRyZ0BcC2pWLdmt9sXu1EibHqOzQNMYxgRjKhlPAU6TVaIDwS89feCMGnWzUpbvXaRk7LJt0qEF3YiyBdNhrxgoP5Slm26csWQZnjMfHYRuX9OVGCDLBteT3pNaAIzEwb5yTRhFosPXqK1gUdmcM48xv6JZnLOV9sN02rpBQAYXgeEuWtKljC7IHMfKB35dRgNAUTzhYPwoFbO60iE9MqcrSyXZKxgCLvDW1Jnf4Et7pHaQVoB58slmYGdcOaqW29XkTUhMJReZCybnrItgFwPQ3dLEs5jocKVBlYGiRMXmAuzWH70pNaCx92T4tvqLdfkEbgY1OiUSZJnXPhrAwM7LV63NWBm";
// $ExpectedPersonalKey = "################################ REPLACETHISWITHYOURACTUALPERSONALKEY";
// $ExpectedTRAMANNAPIKey = "################################ REPLACETHISWITHYOURACTUALTRAMANNAPIKEY";
// 
// // Get POST data
// $GeneralKey    = $_POST['GeneralKey']    ?? '';
// $PersonalKey   = $_POST['PersonalKey']   ?? '';
// $TRAMANNAPIKey = $_POST['TRAMANNAPIKey'] ?? '';
// $rawMessage    = $_POST['message']       ?? '';
// 
// // Validate keys (you can customize this check)
// if ($GeneralKey !== $ExpectedGeneralKey || $PersonalKey !== $ExpectedPersonalKey || $TRAMANNAPIKey !== $ExpectedTRAMANNAPIKey) {
//     echo json_encode([
//         "status" => "error",
//         "message" => "We are very sorry, but there was an error in the authentification."
//     ]);
//     exit;
// }
// 
// // If valid
// // Strip code block wrappers
// $code = $rawMessage;
// if (preg_match('/^```(?:php)?\s*(.*?)\s*```$/s', $rawMessage, $matches)) {
//     $code = $matches[1];
// }
// $code = trim($code);
// 
// // Execute code
// try {
//     ob_start();
//     eval($code);
//     $output = ob_get_clean();
// 
//     // Try to decode output if it's JSON
//     $outputDecoded = json_decode($output, true);
//     $messageToSend = $outputDecoded !== null ? $outputDecoded : $output;
// 
//     header('Content-Type: application/json');
//     echo json_encode([
//         'status'      => 'success',
//         'message'     => $messageToSend
//     ]);
// } catch (Throwable $e) {
//     ob_end_clean();
//     http_response_code(500);
//     echo json_encode([
//         'status'  => 'error',
//         'message' => 'Execution failed: ' . $e->getMessage()
//     ]);
// }







?>