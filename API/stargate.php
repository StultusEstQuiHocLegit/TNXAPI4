<?php
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// stargate home
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

require_once('../config.php');
require_once('../UI/header.php');

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');  // Disable outputting errors directly

$mysqlDbServer   = $_SESSION['DbHost'] ?? ''; // this is something like: www.example.com
$OtherStargateLocation = "https://" . $mysqlDbServer . "/STARGATE/stargate.php";
// Now it should be: https://www.example.com/STARGATE/stargate.php

// GeneralKey for all
// GeneralKey for all (defined in config.php)

// PersonalKey for TimestampCreation of corresponding admin
$PersonalKey = $_SESSION['TimestampCreation'];

// TRAMANNAPIKey of corresponding admin
$TRAMANNAPIKey = $_SESSION['TRAMANNAPIAPIKey'];

// Message
$message = 'echo "SomeTest";';







// Prepare data
$data = [
    "GeneralKey"    => $GeneralKey,
    "PersonalKey"   => $PersonalKey,
    "TRAMANNAPIKey" => $TRAMANNAPIKey,
    "message"       => $message
];

$ch = curl_init($OtherStargateLocation);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true); // <<< ADD THIS
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [ // <<< OPTIONAL, but good
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;

// For debugging
// echo "<br><br>$GeneralKey";
// echo "<br><br>$PersonalKey";
// echo "<br><br>$TRAMANNAPIKey";



?>











