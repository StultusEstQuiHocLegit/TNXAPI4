<?php

// Load database configuration from session
$mysqlDbServer   = $_SESSION['DbHost'] ?? '';
$mysqlDbPort     = $_SESSION['DbPort'] ?? ''; // Optional
$mysqlDbName     = $_SESSION['DbName'] ?? '';
$mysqlDbUser     = $_SESSION['DbUser'] ?? '';
$mysqlDbPassword = $_SESSION['DbPassword'] ?? '';
$mysqlDbDriver   = $_SESSION['DbDriver'] ?? 'mysql';

// Construct DSN
$dsn = "$mysqlDbDriver:host=$mysqlDbServer";
if (!empty($mysqlDbPort)) {
    $dsn .= ";port=$mysqlDbPort";
}
$dsn .= ";dbname=$mysqlDbName;charset=utf8mb4";

// Show all errors
ini_set("error_reporting", E_ALL);
ini_set("display_errors", 1);

try {
    // Create a new PDO instance
    $conn = new PDO($dsn, $mysqlDbUser, $mysqlDbPassword);

    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $configLoaded = true; // Configuration loaded successfully
} catch (PDOException $e) {
    // Handle connection error
    printf("Connection to database couldn't be made: %s\n", $e->getMessage());
    echo "<meta http-equiv=refresh content=3>";
    echo "<br><br>Please send this error reporting to an administrator so we can fix the problem.";
    exit();
}

?>