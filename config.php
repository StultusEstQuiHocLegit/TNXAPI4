<?php

$mysqlDbServer = "PLACEHOLDER_FOR_SECRET_DB_SERVER";
$mysqlDbName = "PLACEHOLDER_FOR_SECRET_DB_NAME";
$mysqlDbUser = "PLACEHOLDER_FOR_SECRET_DB_USER";
$mysqlDbPassword = "PLACEHOLDER_FOR_SECRET_DB_PASSWORD";

// Shared secrets
$GeneralKey = "PLACEHOLDER_FOR_GENERAL_KEY";
$apiKey = "PLACEHOLDER_FOR_THE_SECRET_API_KEY";



// show errors
ini_set("error_reporting", E_ALL); // Show all errors
ini_set("display_errors", 1);

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

// define contribution for TRAMANN PORT
$ContributionForTRAMANNPORT = "3"; // in percent

?>
