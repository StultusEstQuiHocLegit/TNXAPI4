<?php
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// initial setup
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
ob_start(); // Start output buffering
require_once('../config.php');
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get user ID from session
session_start();
require_once('../SETUP/DatabaseTablesStructure.php');
$user_id = $_SESSION['user_id'] ?? null;
$admin_id = $_SESSION['IdpkOfAdmin'] ?? 0;

$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';

if (!$user_id) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'ERROR: User not authenticated';
    exit;
}

// ===== CONFIGURATION =====
// Replace with your actual OpenAI API key or load it securely (e.g. from environment variables)
// API key is defined in config.php

$cmd      = $_POST['cmd']   ?? '';
$logsJson = $_POST['logs']  ?? '[]';
$workflowId = $_POST['workflow_id'] ?? null;
$childWorkflowIds = [];
$workflow = null;

$skipActionDetection = false;

$responses = [];


// for debugging
// $responses[] = [
//     'label' => 'CHAT',
//     'message' => 'connection established successfully',
// ];
































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// system promts
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


$currentDateTime = date('Y-m-d H:i:s');
$currentDate = date('Y-m-d');
$currentDatePlusTwoWeeks = date('Y-m-d', strtotime('+2 weeks'));
$currentDatePlus30Days = date('Y-m-d', strtotime('+30 days'));
$currentDatePlus60Days = date('Y-m-d', strtotime('+60 days'));

$randomCode = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);

// Generate randomSeriesCode in format RR-XXXXX-RR-XX
// Generate RR (two random uppercase letters)
$letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$RR1 = substr(str_shuffle($letters), 0, 2);
// Generate XXXXX (five random digits)
$digits = '0123456789';
$XXXXX = substr(str_shuffle(str_repeat($digits, 5)), 0, 5);
// Generate second RR (two random uppercase letters)
$RR2 = substr(str_shuffle($letters), 0, 2);
// Determine last XX with weighted probability
$rand = mt_rand(1, 100); 
if ($rand <= 60) {
    $XX = '01';
} elseif ($rand <= 90) {
    $XX = '02';
} else {
    $XX = '03';
}
// Combine everything
$randomSeriesCode = "$RR1-$XXXXX-$RR2-$XX";

$company = isset($_SESSION['CompanyName']) ? (string)$_SESSION['CompanyName'] : 'YOURCOMPANYNAME';
$vatID = isset($_SESSION['CompanyVATID']) ? (string)$_SESSION['CompanyVATID'] : 'YOURVATID';
$street = isset($_SESSION['street']) ? (string)$_SESSION['street'] : 'YOURSTREET';
$houseNumber = isset($_SESSION['HouseNumber']) ? (string)$_SESSION['HouseNumber'] : 'YOURHOUSENUMBER';
$zip = isset($_SESSION['ZIPCode']) ? (string)$_SESSION['ZIPCode'] : 'YOURZIPCODE';
$city = isset($_SESSION['city']) ? (string)$_SESSION['city'] : 'YOURCITY';
$country = isset($_SESSION['country']) ? (string)$_SESSION['country'] : 'YOURCOUNTRY';
$currency = isset($_SESSION['CurrencyCode']) ? (string)$_SESSION['CurrencyCode'] : 'YOURCURRENRYCODE';
$iban = isset($_SESSION['CompanyIBAN']) ? (string)$_SESSION['CompanyIBAN'] : 'YOURCOMPANYIBAN';
$invoiceNote = !empty($_SESSION['AdditionalTextForInvoices']) ? "Please ensure to add the following additional invoice note somewhere at the bottom: " . (string)$_SESSION['AdditionalTextForInvoices'] : '';

$CurrencyCode = $_SESSION['CurrencyCode'] ?? '';

$companyBrandingInformation = isset($_SESSION['CompanyBrandingInformation']) ? (string)$_SESSION['CompanyBrandingInformation'] : '';
$companyBrandingInformationForImages = isset($_SESSION['CompanyBrandingInformationForImages']) ? (string)$_SESSION['CompanyBrandingInformationForImages'] : '';
$actionBuying = isset($_SESSION['ActionBuying']) ? (string)$_SESSION['ActionBuying'] : '';
$actionSelling = isset($_SESSION['ActionSelling']) ? (string)$_SESSION['ActionSelling'] : '';


$GeneralInstructions = <<<EOD
# General background instructions
You are TRAMANN AI. Please respond briefly and **use the language of the user** (even if instructions and examples might be in another language).
Provide links in the following format:
<a href="..." title="ShortDescriptionOnlyIfNecessary" target="_blank">🔗 TEXTINUPPERCASE</a>
(be realistic-optimistic and acouraging)
(current date and time: $currentDateTime)
(preferred currency: $CurrencyCode)
EOD;
if (!empty($company) && $company !== 'YOURCOMPANYNAME') {
    $GeneralInstructions .= "\n(user is working for company: $company)";
}







$LocationInstructions = <<<EOD
# You are a location service. Respond with an map iframe URL for the location.
Format: <iframe src="./map.php?address=SomeCountry+SomeCity+12345+SomeStreet+42&pv" width="100%" height="100%" frameborder="0" loading="lazy"></iframe>,
whereas SomeCountry, SomeCity, ... get replaced by what the user is looking for. You do not have to provide all of these values,
if you for example just got the country, just add that.
If you should not be able to define a location or address, just respond with the generic version of the link:
<iframe src="./map.php&pv" width="100%" height="100%" frameborder="0" loading="lazy"></iframe>
EOD;
// 'You are a location service. Respond with an OpenStreetMap iframe URL for the location. Format: <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=[bbox]&layer=mapnik&marker=[location]"></iframe>'





// $WhichTablesPrompt = <<<EOD
// table: TDBcarts
// (cart data for each purchase, each cart consists of transactions)
// 
// idpk (int) auto increment, primary key
// TimestampCreation (timestamp)
// IdpkOfAdmin (int)
// IdpkOfSupplierOrCustomer (int) (links to: TDBSuppliersAndCustomers (idpk))
// DeliveryType (varchar(250)), for example: standard, express, overnight, pickup, temperature-controlled, ...
// WishedIdealDeliveryOrPickUpTime (datetime)
// CommentsNotesSpecialRequests (text)
// 
// 
// table: TDBtransaction
// (transactions with products and services bought or sold, linked to a corresponding cart)
// 
// idpk (int) auto increment, primary key
// TimestampCreation (timestamp)
// IdpkOfAdmin (int)
// IdpkOfSupplierOrCustomer (int) (links to: TDBSuppliersAndCustomers (idpk))
// IdpkOfCart (int) (links to: TDBcarts (idpk))
// IdpkOfProductOrService (int) (links to: TDBProductsAndServices (idpk))
// quantity (int)
// NetPriceTotal (decimal(10,2)), positive if the user sold something, negative if he bought something, (total = unit price * quantity)
// TaxesTotal (decimal(10,2)), (total = unit tax amount * quantity)
// CurrencyCode (varchar(3)), three letter currency code based on ISO 4217, if not stated otherwise, just take the preferred currency
// state (varchar(250)), for example: draft, offer, payment-pending, payment-made-delivery-pending, payment-pending-delivery-made, completed, disputed, dispute-accepted, dispute-rejected, refunded, items-returned, cancelled, ...
// CommentsNotesSpecialRequests (text)
// 
// 
// table: TDBProductsAndServices
// (data for products and services)
// 
// idpk (int) auto increment, primary key
// TimestampCreation (timestamp)
// IdpkOfAdmin (int)
// name (varchar(250))
// categories (varchar(250)), for example: SomeMainCategory/FirstSubcategory/SecondSubcategory, AnotherMainCategoryIfNeeded, YetAnotherOne/AndSomeSubcategoryToo
// KeywordsForSearch (text)
// ShortDescription (text)
// LongDescription (text)
// WeightInKg (decimal(10,5))
// DimensionsLengthInMm (decimal(10,2))
// DimensionsWidthInMm (decimal(10,2))
// DimensionsHeightInMm (decimal(10,2))
// NetPriceInCurrencyOfAdmin (decimal(10,2))
// TaxesInPercent (decimal(10,2))
// VariableCostsOrPurchasingPriceInCurrencyOfAdmin (decimal(10,2))
// ProductionCoefficientInLaborHours (decimal(10,2))
// ManageInventory (boolean), true if the product should be managed in inventory (if false, ignore InventoryAvailable, InventoryInProductionOrReordered and InventoryMinimumLevel, these values are old then)
// InventoryAvailable (int)
// InventoryInProductionOrReordered (int)
// InventoryMinimumLevel (int)
// InventoryLocation (varchar(250))
// PersonalNotes (text)
// state (boolean), false to hide it in online shop, true to show it
// 
// 
// 
// table: TDBSuppliersAndCustomers
// (data for suppliers and customers (both in this same table))
// 
// idpk (int) auto increment, primary key
// TimestampCreation (timestamp)
// IdpkOfAdmin (int)
// CompanyName
// email (varchar(250))
// PhoneNumber (bigint), for example: 0123456789
// street (varchar(250))
// HouseNumber (int)
// ZIPCode (varchar(250))
// city (varchar(250))
// country (varchar(250))
// IBAN (varchar(250))
// VATID (varchar(250))
// PersonalNotesInGeneral (text)
// PersonalNotesBusinessRelationships (text)
// EOD;

// $WhichTablesJustTablesPrompt = <<<EOD
// table: TDBcarts
// (cart data for each purchase, each cart consists of transactions)
// 
// table: TDBtransaction
// (transactions with products and services bought or sold, linked to a corresponding cart)
// 
// table: TDBProductsAndServices
// (data for products and services) 
// 
// table: TDBSuppliersAndCustomers
// (data for suppliers and customers (both in this same table))
// EOD;

function generateTablesPrompt(array $structure, array $tableInfos = []): string {
    $prompt = '';

    foreach ($structure as $tableName => $fields) {
        $prompt .= "table: $tableName\n";

        $info = trim($tableInfos[$tableName] ?? '');
        if ($info !== '') {
            $prompt .= "($info)\n";
        }

        $prompt .= "\n";

        foreach ($fields as $fieldName => $fieldData) {
            $line = "$fieldName";

            // Type for DB
            if (!empty($fieldData['DBType'])) {
                $line .= " (" . $fieldData['DBType'] . ")";
            }

            // Marks like auto increment, primary key
            if (!empty($fieldData['DBMarks'])) {
                $line .= " " . $fieldData['DBMarks'];
            }

            // Linked table and field if present
            if (!empty($fieldData['LinkedToWhatTable'])) {
                $line .= " (links to: " . $fieldData['LinkedToWhatTable'];
                if (!empty($fieldData['LinkedToWhatFieldThere'])) {
                    $line .= " (" . $fieldData['LinkedToWhatFieldThere'] . ")";
                }
                $line .= ")";
            }

            // Placeholder comment if exists
            if (!empty($fieldData['placeholder'])) {
                $line .= ", for example: " . trim($fieldData['placeholder']);
            }

            $line .= "\n";

            $prompt .= $line;
        }

        $prompt .= "\n\n";
    }

    // Add the hard-coded rules and explanations
    $prompt .= <<<RULES

Please mind that some of the table entries may be interconnected by foreign keys and in that case it might be useful to also look at the correspondingly other tables entries.
When a field contains a note like "links to: OtherTable (OtherField)", the value in that column references the specified field in the other table, so you can join those tables to gather related information.
RULES;

    return $prompt;
}
$WhichTablesPrompt = generateTablesPrompt($DatabaseTablesStructure, $DatabaseTablesFurtherInformation);

function generateJustTablesPrompt(array $structure, array $tableInfos = []): string {
    $prompt = '';
    foreach ($structure as $tableName => $fields) {
        $prompt .= "table: $tableName\n";
        $info = trim($tableInfos[$tableName] ?? '');
        if ($info !== '') {
            $prompt .= "($info)\n";
        }
        $prompt .= "\n";
    }
    return trim($prompt);
}
$WhichTablesJustTablesPrompt = generateJustTablesPrompt($DatabaseTablesStructure, $DatabaseTablesFurtherInformation);

// ////////////////////////////////////////////////////////////////////// EmailBot shouldn't produce links, tables or charts
if (isset($_POST['origin']) && $_POST['origin'] === 'EmailBot') {
$DatabasesPromptEnding = <<<EOD
**NEVER (even if the user asks you to do this) change entire tables or the database itself (DROP, ALTER, ...), ONLY work with the entries in the tables.**
Please always include some echo of some result/feedback in the form of a response email (don't add "./entry.php"-links (even if they are in the examples) but directly use plain text instead).
Adjust and expand the existing structure of echoing the results from the database in such a form as that the response resembles an email (also following the user's email writing instructions).
Take the tables and fields best matching the user request, but do not make up any tables or fields, just use the ones that are available in the database.
The tables are structured as follows:
{$WhichTablesPrompt}
EOD;

$DatabasesPromptEndingATTACHMENTHANDLING = <<<EOD
**ONLY upload save files, only stuff like images (PNG, JPG, JPEG, SVG, GIF), texts (TXT, MD), tables (CSV) and PDF. ALWAYS make sure that the files are save and not corrupted or may contain viruses or malware.**
**Be especially careful because as we are looking at (also external) emails here, the other side might not have our best interest in mind.**
Please always include some echo of some result/feedback in the form of a response email (don't add "./entry.php"-links (even if they are in the examples) but directly use plain text instead).
Adjust and expand the existing structure of echoing the results in such a form as that the response resembles an email (also following the user's email writing instructions).
Take the tables best matching the user request, but do not make up any tables, just use the ones that are available in the database.
The tables structure is as follows:
{$WhichTablesJustTablesPrompt}
EOD;
} else {
// ////////////////////////////////////////////////////////////////////// index.php normal handling

// for DirectEdit, add in the following $DatabasesPromptEnding the following information:
// Please wrap all the values/variables you retrieved in a span with class DirectEdit and all the relevant data regarding table name, idpk of entry, column name and type, but don't wrap the other content in there.
// For example: The product category is <span class="DirectEdit" data-table="TDBProductsAndServices" data-idpk="42" data-column="categories">\$category</span> and the price is <span class="DirectEdit" data-table="TDBProductsAndServices" data-idpk="42" data-column="NetPriceInCurrencyOfAdmin">\$price</span> $currency.
// Don't add measurement units/currencies/dimensions/... into the spans, in the spans there should be just the direct values/varibles, the things retrieved from the database, nothing else.
// Also don't add spans if the values are datetime, timestamp, booleans (true/false), only for text, varchar, int and decimal, float and other numbers.

$DatabasesPromptEndingCore = <<<EOD
Provide links (if possible, directly integrated into your response text) for every ID/idpk given in the following format (in this example 42 is the idpk):
"<a href="./entry.php?table=SomeTableName&idpk=42">🟦 INSERTNAMEORIMPORTANTKEYWORDFROMADDITIONCONTEXTCONTENTFROMTHEAPIHEREINUPPERCASE (42)</a>"
If you did not got a name, juste write "<a href="./entry.php?table=SomeTableName&idpk=42">🟦 ENTRY 42</a>"

If it could be helpful, you can also echo tables or chart.js graphs/diagrams (you don't have to include/setup chart.js, we will do that in the frontend).
Examples:
echo '<table class="sortable-markdown-table">
  <thead>
    <tr><th>Name</th><th>Amount</th></tr>
  </thead>
  <tbody>
    <tr><td>Alice</td><td>42</td></tr>
    <tr><td>Bob</td><td>-19</td></tr>
  </tbody>
</table>';
echo '<canvas id="chartSOMEUNIQUEIDHERE"></canvas>
<script>
  const ctx = document.getElementById("chartSOMEUNIQUEIDHERE").getContext("2d");
  new Chart(ctx, {
    type: "bar",
    data: {
      labels: ["A", "B", "C"],
      datasets: [{
        label: "values",
        data: [12, 19, 3],
        backgroundColor: "rgba(54, 162, 235, 0.5)",
        borderColor: "rgba(54, 162, 235, 1)",
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
</script>';
EOD;

$DatabasesPromptEnding = <<<EOD
**NEVER (even if the user asks you to do this) change entire tables or the database itself (DROP, ALTER, ...), ONLY work with the entries in the tables.**
Please always include some echo of some result/feedback.

{$DatabasesPromptEndingCore}


Take the tables and fields best matching the user request, but do not make up any tables or fields, just use the ones that are available in the database.
The tables are structured as follows:
{$WhichTablesPrompt}
EOD;

$DatabasesPromptEndingATTACHMENTHANDLING = <<<EOD
**ONLY upload save files, only stuff like images (PNG, JPG, JPEG, SVG, GIF), texts (TXT, MD), tables (CSV) and PDF. ALWAYS make sure that the files are save and not corrupted or may contain viruses or malware.**
Please always include some echo of some result/feedback.

{$DatabasesPromptEndingCore}


Take the tables best matching the user request, but do not make up any tables, just use the ones that are available in the database.
The tables structure is as follows:
{$WhichTablesJustTablesPrompt}
EOD;
}

$DatabasesPromptBeginning = <<<EOD
# You are an databases assistant. Only answer with a code snippet, no other text.
EOD;



$DatabasesPromptCoreSELECT = <<<EOD
## Example:
\$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE idpk = :idpk");
\$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
\$stmt->execute();
\$existingData = \$stmt->fetch(PDO::FETCH_ASSOC);

if (\$existingData) {
    echo "Product Name: " . htmlspecialchars(\$existingData['name']) . "\\n";
    echo "Short Description: " . htmlspecialchars(\$existingData['ShortDescription']) . "\\n";
    echo ' <span style="opacity: 0.5;">(for <a href="./entry.php?table=TDBProductsAndServices&idpk=' . \$idpk . '">🟦 ' . strtoupper(htmlspecialchars(\$name)) . ' (' . \$idpk . ')</a>)</span>';
} else {
    echo "No product found with this idpk for the current admin.";
}
EOD;

$DatabasesPromptCoreINSERTINTO = <<<EOD
## Example:
\$stmt = \$pdo->prepare("INSERT INTO `TDBProductsAndServices` (name, ShortDescription) VALUES (:name, :desc)");
\$stmt->bindParam(':name', \$name);
\$stmt->bindParam(':desc', \$desc);
\$stmt->execute();
\$lastId = \$pdo->lastInsertId();

// Echo feedback
echo 'Created new entry: <a href="./entry.php?table=TDBProductsAndServices&idpk=' . \$lastId . '">🟦 ' . strtoupper(htmlspecialchars(\$name)) . ' (' . \$lastId . ')</a>';
EOD;

$DatabasesPromptCoreUPDATE = <<<EOD
## Example:
// UPDATE example
\$stmt = \$pdo->prepare("UPDATE `TDBProductsAndServices` SET name = :name, ShortDescription = :desc WHERE idpk = :idpk");
\$stmt->bindParam(':name', \$name);
\$stmt->bindParam(':desc', \$desc);
\$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
\$stmt->execute();

// Echo feedback
echo 'Updated entry <a href="./entry.php?table=TDBProductsAndServices&idpk=' . \$idpk . '">🟦 ' . strtoupper(htmlspecialchars(\$name)) . ' (' . \$idpk . ')</a>';
EOD;

$DatabasesPromptCoreDELETE = <<<EOD
## Example:
\$stmt = \$pdo->prepare("DELETE FROM `TDBProductsAndServices` WHERE idpk = :idpk");
\$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
\$stmt->execute();

// Delete attachments associated with this entry
\$uploadDir = "./UPLOADS/TDBProductsAndServices/";
if (is_dir(\$uploadDir)) {
    \$files = glob(\$uploadDir . \$idpk . "_*.*"); // find all files starting with idpk_
    foreach (\$files as \$file) {
        if (is_file(\$file)) {
            unlink(\$file);
        }
    }
}

// Echo feedback
echo "Entry with idpk \$idpk has been deleted from table TDBProductsAndServices.";
EOD;

$DatabasesPromptCoreSEARCH = <<<EOD
## Search instructions
Perform the search in three steps:

1. Search using the raw input.
2. Correct possible spelling mistakes and search again.
3. Search using synonyms, stems, or similar words.

Generate spelling corrections and similar terms using your own logic.

## Example:

// Step 1: Exact match search
\$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE name LIKE :search");
\$stmt->bindValue(':search', '%' . \$search . '%');
\$stmt->execute();
\$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);

if (!\$results) {
    // Step 2: Spelling-corrected variant
    \$corrected = '[[your spelling-corrected version of \$search here]]'; // e.g., via AI suggestion
    \$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE name LIKE :corrected");
    \$stmt->bindValue(':corrected', '%' . \$corrected . '%');
    \$stmt->execute();
    \$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!\$results) {
    // Step 3: Similar or related terms
    \$alternatives = ['[[alt1]]', '[[alt2]]', '[[alt3]]']; // e.g., AI-generated variants
    foreach (\$alternatives as \$alt) {
        \$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE name LIKE :alt");
        \$stmt->bindValue(':alt', '%' . \$alt . '%');
        \$stmt->execute();
        \$moreResults = \$stmt->fetchAll(PDO::FETCH_ASSOC);
        \$results = array_merge(\$results, \$moreResults);
    }
}

// Echo results
if (\$results) {
    foreach (\$results as \$row) {
        echo '<a href="./entry.php?table=TDBProductsAndServices&idpk=' . \$row['idpk'] . '">🟦 ' . strtoupper(htmlspecialchars(\$row['name'])) . ' (' . \$row['idpk'] . ')</a> ' . "\\n";
    }
} else {
    echo "No results found for your search.";
}
EOD;

$DatabasesPromptCoreGETCONTEXT = <<<EOD
## Search instructions
Perform the search in three steps:

1. Search using the raw input.
2. Correct possible spelling mistakes and search again.
3. Search using synonyms, stems, or similar words.

Generate spelling corrections and similar terms using your own logic.
Sometimes the user asks you do perform searches for multiple entries or for multiple tables, in that case, just extend the logic and echo ALL relevant data from ALL relevant columns (tend towards adding more, more is better than less) for everything requested, for each entry.
(hint of what might be relevant: idpk, name, price, tax, address details, descriptions, ... (and everything that might be useful for this and following tasks and other AI agents))

## Example:

// Step 1: Exact match search
\$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE name LIKE :search");
\$stmt->bindValue(':search', '%' . \$search . '%');
\$stmt->execute();
\$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);

if (!\$results) {
    // Step 2: Spelling-corrected variant
    \$corrected = '[[your spelling-corrected version of \$search here]]'; // e.g., via AI suggestion
    \$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE name LIKE :corrected");
    \$stmt->bindValue(':corrected', '%' . \$corrected . '%');
    \$stmt->execute();
    \$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!\$results) {
    // Step 3: Similar or related terms
    \$alternatives = ['[[alt1]]', '[[alt2]]', '[[alt3]]']; // e.g., AI-generated variants
    foreach (\$alternatives as \$alt) {
        \$stmt = \$pdo->prepare("SELECT * FROM `TDBProductsAndServices` WHERE name LIKE :alt");
        \$stmt->bindValue(':alt', '%' . \$alt . '%');
        \$stmt->execute();
        \$moreResults = \$stmt->fetchAll(PDO::FETCH_ASSOC);
        \$results = array_merge(\$results, \$moreResults);
    }
}

// Echo results
if (\$results) {
    // relevant information (tend towards adding more than less)
    \$columns = [
        'idpk',
        'name',
        'quantity',
        'NetPriceTotal',
        'TaxesTotal',
        'CurrencyCode',
        'state',
        'CommentsNotesSpecialRequests'
    ];
    foreach (\$results as \$row) {
        foreach (\$columns as \$col) {
            if (isset(\$row[\$col])) {
                echo \$col . ': ' . \$row[\$col] . "\\n";
            }
        }
        echo '<a href="./entry.php?table=TDBProductsAndServices&idpk=' . \$row['idpk'] . '">🟦 ' . strtoupper(htmlspecialchars(\$row['name'])) . ' (' . \$row['idpk'] . ')</a>' . "\\n\\n";
    }
} else {
    echo "No context found for your search.";
}
// If needed, just copy this code a second time, change the variable names and thus adapt it for the other entry we may search for.
EOD;

$DatabasesPromptCoreATTACHMENTHANDLING = <<<EOD
Files for each table reside on the STARGATE server in "./UPLOADS/<table>/" and follow the naming pattern "<idpk>_<counter>.<extension>" (counter starts at 0).
The extension can we an image extension, text, CSV, PDF or nearly anythign else too. Use "FileViewer.php" to show a file and "entry.php" to display the entry itself.

When the user provides one or more entries, return PHP code that:
1. defines an array with all requested ['table' => '...', 'idpk' => ...] pairs,
2. scans the corresponding "./UPLOADS/<table>/" directories for files matching "<idpk>_*.*",
3. echoes one 🔵 FileViewer-link per file, then a 🟦 entry-link with opacity 0.5, then a blank line before continuing with the next entry.

## EXAMPLE
\$entries = [
    ['table' => 'TDBProductsAndServices', 'idpk' => 10],
    ['table' => 'TDBProductsAndServices', 'idpk' => 15],
];

foreach (\$entries as \$e) {
    \$table = \$e['table'];
    \$idpk  = (int)\$e['idpk'];
    \$dir   = './UPLOADS/' . \$table . '/';
    \$files = is_dir(\$dir) ? glob(\$dir . \$idpk . '_*.*') : [];
    natsort(\$files);
    foreach (\$files as \$path) {
        \$file = basename(\$path);
        echo '<a href="./FileViewer.php?table=' . \$table . '&idpk=' . \$idpk . '&file=' . rawurlencode(\$file) . '">🔵 ' . strtoupper(\$file) . '</a>' . "\\n";
    }
    echo '<span style="opacity: 0.5;"><a href="./entry.php?table=' . \$table . '&idpk=' . \$idpk . '">🟦 ' . strtoupper(\$table) . ' (' . \$idpk . ')</a></span>' . "\\n\\n";
}
EOD;



$DatabasesPromptSELECT = <<<EOD
{$DatabasesPromptBeginning}


{$DatabasesPromptCoreSELECT}


{$DatabasesPromptEnding}
EOD;

$DatabasesPromptINSERTINTO = <<<EOD
{$DatabasesPromptBeginning}


{$DatabasesPromptCoreINSERTINTO}


{$DatabasesPromptEnding}
EOD;

$DatabasesPromptUPDATE = <<<EOD
{$DatabasesPromptBeginning}


{$DatabasesPromptCoreUPDATE}


{$DatabasesPromptEnding}
EOD;

$DatabasesPromptDELETE = <<<EOD
{$DatabasesPromptBeginning}


{$DatabasesPromptCoreDELETE}


{$DatabasesPromptEnding}
EOD;

$DatabasesPromptSEARCH = <<<EOD
{$DatabasesPromptBeginning}


{$DatabasesPromptCoreSEARCH}


{$DatabasesPromptEnding}
EOD;


$DatabasesPromptGETCONTEXT = <<<EOD
{$DatabasesPromptBeginning}


{$DatabasesPromptCoreGETCONTEXT}


{$DatabasesPromptEnding}
EOD;


$DatabasesPromptATTACHMENTHANDLING = <<<EOD
# You are an databases attachments handling assistant. Only answer with a code snippet, no other text.


{$DatabasesPromptCoreATTACHMENTHANDLING}


{$DatabasesPromptEndingATTACHMENTHANDLING}
EOD;

// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// copy of previous version
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// $DatabasesPrompt = <<<EOD
// You are an databases assistant. Only answer in the following exact format, no other text.  
// The format is:
// ["action" => "",
// "table" => "",
// "fields" => "",
// "values" => "",
// "idpk" => "",], [IfNecessaryRepeatSchemeAsOftenAsNeededInSameFormat], ... .
// 
// Action can only be one of the following values: "SELECT", "INSERT INTO", "UPDATE", "DELETE" or "SEARCH" (search gives back all the matching idpks).
// Table must the the exact name of the relevant table.
// Fields must be the exact name of the relevant fields/columns.
// Values are the values provided, please respect formats (int, text, ...).
// Idpk can only be one number, not multiple ones (idpk stands for ID primary key, so thats just the ID).
// 
// Not all of these have to be filled out in every query. Fields and values can also be multiple ones, for example:
// "fields" => "name |#| description |#| price",
// "values" => "SomeName |#| CorrespondingDescription |#| 42",.
// Use " |#| " as a separator and always keep the order the same, the first value is for the first fields, the second for the second, ... .
// The other fields (action, table, idpk) can only have one value, if the user asks to do multiple things, just repeat the same scheme multiple times.
// 
// 
// The tables are structured as follows:
// {$WhichTablesPrompt}
// EOD;






$BuyingPrompt = <<<EOD
# SETUP
The users company sells something. Your goal is now to perform the relevant database actions. Only answer with a code snippet, no other text.


# POSSIBLY HELPFUL DATABSE ACTIONS EXAMPLES
(most likely you have to combine multiple ones)
## SEARCH FOR RELEVANT INFORMATIONS CONTEXT
{$DatabasesPromptCoreGETCONTEXT}

## UPDATE EXISTING ENTRIES
{$DatabasesPromptCoreUPDATE}

## INSERT NEW ENTRIES
{$DatabasesPromptCoreINSERTINTO}


# GENERAL DATABASE INFORMATION
{$DatabasesPromptEnding}


# INSTRUCTIONS
Follow the following instructions for buying, given by the users company:
EOD;
$BuyingPrompt .= "\n$actionBuying";

$SellingPrompt = <<<EOD
# SETUP
The users company buys something. Your goal is now to perform the relevant database interactions. Only answer with a code snippet, no other text.


# POSSIBLY HELPFUL DATABSE ACTIONS EXAMPLES
(most likely you have to combine multiple ones)
## SEARCH FOR RELEVANT INFORMATIONS CONTEXT
{$DatabasesPromptCoreGETCONTEXT}

## UPDATE EXISTING ENTRIES
{$DatabasesPromptCoreUPDATE}

## INSERT NEW ENTRIES
{$DatabasesPromptCoreINSERTINTO}


# GENERAL DATABASE INFORMATION
{$DatabasesPromptEnding}


# INSTRUCTIONS
Follow the following instructions for selling, given by the users company:
EOD;
$SellingPrompt .= "\n$actionSelling";






$MarketingTextingPrompt = <<<EOD
# You are assisting with creative writing for advertisement texts, products descriptions or other marketing stuff.
Use marketing best practices and knowledge.
Please also mind the following general branding information, given by the users company:
EOD;
$MarketingTextingPrompt .= "\n$companyBrandingInformation";





$rawEmailWritingInstructions = $_SESSION['ConnectEmailWritingInstructions'] ?? '';
$sanitizedEmailWritingInstructions = htmlspecialchars(
    $rawEmailWritingInstructions,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$EmailWritingInstructionsPrompt = <<<EOD
# You are an email assistant. When asked to compose an email, respond with only a single mailto link, no other text.  
The link must include:
- `To` (required)
- `Cc` (optional)
- `Subject`
- `Body`
URL-encode all parameters. Please fill in the values you are provided with (but do not make up email addresses) and write the email body and subject freely based on that.
Example:
mailto:alice@example.com?cc=bob@example.com&subject=Meeting%20Tomorrow&body=Hi%20Alice%2C%0ASee%20you%20at%209%20AM.
Please also respect the user's email writing instructions, which are:
{$sanitizedEmailWritingInstructions}
EOD;




// $EmailReadingInboxLastAndOrOrUnreadMessages = "test";
// $EmailReadingInstructionsPrompt = <<<EOD
// You are an email assistant. Please summarize the following emails from the users inbox and provide and overview:
// 
// {$EmailReadingInboxLastAndOrOrUnreadMessages}
// EOD;







$PDFPromptBeginning = <<<EOD
You are an AI that writes structured text to be turned into a downloadable PDF by our code (you don't have to do that) document using jsPDF.
Please generate only the content of the PDF, no explanations, no code, no metadata or notes, nothing else, we will handle the rest.

Format your response with for example:
- A title (start with "# Title")
- Sections with subtitles (like "## Section Name")
- Bullet points or numbered lists if needed
- Tables as plain text if necessary

Keep the language formal and clear, do NOT add links, <br> or other HTML.
Please keep the structure of the following example, but replace as many PLACEHOLDERS as possible with the values you have.
EOD;

$PDFPromptEnding = <<<EOD
Now generate the content please, based on the user's request.

Please also consider the following additional background information (if it is given) and add if necessary/useful:
company: $company
VAT ID: $vatID

address:  
$street $houseNumber  
$zip $city  
$country

currency: $currency
IBAN: $iban
EOD;


$PDFPrompt = <<<EOD
$PDFPromptBeginning


Example:

# Weekly Report

## Summary

This report outlines the progress of the project...

## Key Tasks Completed

- Implemented login system
- Deployed version 1.1
- Fixed UI bugs in dashboard

## Next Steps

1. Write test cases
2. Review code with team
3. Prepare for launch


$PDFPromptEnding
EOD;


$PDFPromptInvoice = <<<EOD
$PDFPromptBeginning

At the top, add a table with three columns:
- Left column: the transaction partner's name and address (if you shouldn't have values, jsut write "-" for each missing one),
- Middle column: the user's company name and address,
- Right column: the invoice details.
The middle column and the right column are already filled out correctly, so please keep the values in this part exactly as they are in the example down below, don't remove or add anything.

Column headers: the transaction partner's company name, the user's company name, invoice details.

Then include a table listing the products and services sold, with prices and quantities.

At the bottom, add banking information and any additional invoice notes.


Example:

# Invoice

## Overview
| **COMPANYNAME**                               | **$company**                                  | **Invoice Details**                    |
|-----------------------------------------------|-----------------------------------------------|----------------------------------------|
| VAT ID: VATID                                 | VAT ID: $vatID                                | **Invoice No.:** $randomSeriesCode     |
| HOUSENUMBER STREET                            | $houseNumber $street                          | **Invoice Date:** $currentDate         |
| ZIPCODE CITY                                  | $zip $city                                    | **Due Date:** $currentDatePlusTwoWeeks |
| COUNTRY                                       | $country                                      | **Delivery Date:** $currentDate        |

## Products and Services
| Product Name / Description       | Quantity | Unit Price          | Total               |
|----------------------------------|----------|---------------------|---------------------|
| SOMEPRODUCTORSERVICE             | 2        | $currency 5,000.00  | $currency 10,000.00 |
| ANOTHERONE                       | 1        | $currency 55.00     | $currency 55.00     |
| YETANOTHERONE                    | 2        | $currency 20.00     | $currency 40.00     |
| MAYBEALSOPACKAGINGANDSHIPPINGTOO | 1        | $currency 800.00    | $currency 1,200.00  |
**Total ($currency): $currency 6,200.00**

## Payment Details
| Bank Account Information         | Notes                                     |
|----------------------------------|-------------------------------------------|
| IBAN: $iban                      | Please transfer the total within 14 days. |
| Currency: $currency              | Reference the invoice number when paying. |

$invoiceNote
EOD;


$PDFPromptOffer = <<<EOD
$PDFPromptBeginning

At the top, add a table with three columns:
- Left column: the transaction partner's name and address (if you shouldn't have values, jsut write "-" for each missing one),
- Middle column: the user's company name and address,
- Right column: the offer details.
The middle column and the right column are already filled out correctly, so please keep the values in this part exactly as they are in the example down below, don't remove or add anything.

Column headers: the transaction partner's company name, the user's company name, invofferoice details.

Then include a table listing the products and services offered, with prices and quantities.


Example:

# Offer

## Overview
| **COMPANYNAME**                             | **$company**                                  | **Offer Details**                       |
|---------------------------------------------|-----------------------------------------------|-----------------------------------------|
| VAT ID: VATID                               | VAT ID: $vatID                                | **Offer No.:** $randomSeriesCode        |
| HOUSENUMBER STREET                          | $houseNumber $street                          | **Date:** $currentDate                  |
| ZIPCODE CITY                                | $zip $city                                    | **Valid Until:** $currentDatePlus30Days |
| COUNTRY                                     | $country                                      |                                         |

## Products and Services
| Product Name / Description       | Quantity | Unit Price          | Total               |
|----------------------------------|----------|---------------------|---------------------|
| SOMEPRODUCTORSERVICE             | 2        | $currency 5,000.00  | $currency 10,000.00 |
| ANOTHERONE                       | 1        | $currency 55.00     | $currency 55.00     |
| YETANOTHERONE                    | 2        | $currency 20.00     | $currency 40.00     |
| MAYBEALSOPACKAGINGANDSHIPPINGTOO | 1        | $currency 800.00    | $currency 1,200.00  |
**Total ($currency): $currency 6,200.00**

## Payment Information
| IBAN           | Currency  | Payment Terms                      |
|----------------|-----------|------------------------------------|
| $iban          | $currency | 50% upfront, 50% on delivery       |

$invoiceNote
EOD;


$PDFPromptDeliveryReceipt = <<<EOD
$PDFPromptBeginning

At the top, add a table with three columns:
- Left column: the transaction partner's name and address (if you shouldn't have values, jsut write "-" for each missing one),
- Middle column: the user's company name and address,
- Right column: the delivery details.
The middle column and the right column are already filled out correctly, so please keep the values in this part exactly as they are in the example down below, don't remove or add anything.

Column headers: the transaction partner's company name, the user's company name, delivery details.

Then include a table listing the products and services delivered and their quantities.


Example:

# Delivery Receipt

## Overview
| **COMPANYNAME**                             | **$company**                                  | **Delivery Details**                   |
|---------------------------------------------|-----------------------------------------------|----------------------------------------|
| VAT ID: VATID                               | VAT ID: $vatID                                | **Receipt No.:** $randomSeriesCode     |
| HOUSENUMBER STREET                          | $houseNumber $street                          | **Date Issued:** $currentDate          |
| ZIPCODE CITY                                | $zip $city                                    | **Delivery Date:** $currentDate        |
| COUNTRY                                     | $country                                      |                                        |

## Items Delivered
| Product Name / Description | Quantity |
|----------------------------|----------|
| SOMEPRODUCT                | 2        |
| ANOTHERONE                 | 1        |
| YETANOTHERONE              | 2        |

## Notes
All items were delivered in proper condition and verified by the recipient.  
If there are any discrepancies, please notify us within 3 business days.
EOD;


$PDFPromptReport = <<<EOD
$PDFPromptBeginning


Example:

# Report

_Date: $currentDate_  
_Company: $company_

## Summary

This quarterly report presents the operational and financial highlights of the second quarter.

## Key Findings

- Revenue increased by 12% compared to Q1.
- Client retention rate reached 94%.
- Two key systems were deployed in production.

## Recommendations

1. Expand sales efforts into Scandinavian markets.
2. Invest in customer onboarding automation.
3. Optimize infrastructure to reduce server costs.
EOD;


$PDFPromptContract = <<<EOD
$PDFPromptBeginning

The text must be precise, clear, and formal.


Example:

# Contract Agreement

## Parties

This agreement is entered into between:

**Client:** OTHERPARTNERCOMPANYNAME 
HOUSENUMBER STREET, ZIPCODE, CITY, COUNTRY  
VAT ID: VATID

**Contractor:** $company  
$street $houseNumber, $zip $city, $country  
VAT ID: $vatID

## Terms and Conditions

1. **Scope**  
   Contractor agrees to deliver the services as outlined in the attached scope of work.

2. **Payment**  
   - Total contract value: $currency 10,000.00  
   - 50% due upon signing, 50% on completion  
   - IBAN: $iban

3. **Timeline**  
   - Project start: $currentDate 
   - Completion: $currentDatePlus60Days

4. **Confidentiality**  
   All information exchanged shall be kept strictly confidential.

5. **Termination**  
   14 days written notice required by either party for termination.

## Signatures

__________________________  
OTHERPARTNERCOMPANYNAME

__________________________  
$company

_Date: $currentDate_
EOD;


$PDFPromptLegalDocument = <<<EOD
$PDFPromptBeginning

The content must be precise, clear, and use formal legal language.


Example:

# Legal Document

## Introduction

This agreement formalizes the legal relationship between OTHERPARTNERCOMPANYNAME and $company regarding licensing of proprietary software and related services.

## Provisions

1. **License Grant**  
   The Licensor ($company) grants the Licensee a non-exclusive, non-transferable right to use the software.

2. **Usage Limitations**  
   Licensee shall not distribute, copy, or modify the software without prior written approval.

3. **Liability and Indemnity**  
   $company shall not be liable for indirect damages arising from the use of the software.

4. **Jurisdiction**  
   This document is governed by the laws of $country.

## Company Information

| Company Name | VAT ID        | IBAN           |
|--------------|---------------|----------------|
| $company     | $vatID        | $iban          |

## Signatures

__________________________  
OTHERPARTNERCOMPANYNAME

__________________________  
$company

_Date: $currentDate_


$PDFPromptEnding
EOD;















































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// EmailBot.php special handling
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// ////////////////////////////////////////////////////////////////////// EmailBot special handling
if (isset($_POST['origin']) && $_POST['origin'] === 'EmailBot') {
    $manualEmails = trim($_POST['manual_emails'] ?? '');
    
    if ($manualEmails !== '') {
        // Use manual_emails directly
        $cmd = "this email is provided: $manualEmails";
    } else {
        // Build context string from other POST fields
        $subject = $_POST['subject'] ?? 'N/A';
        $datetime = $_POST['datetime'] ?? 'N/A';
        $senderEmail = $_POST['sender_email'] ?? 'N/A';
        $senderName = $_POST['sender_name'] ?? 'N/A';
        $body = $_POST['body'] ?? 'N/A';
        $to = $_POST['to'] ?? '';
        $cc = $_POST['cc'] ?? '';
        $bcc = $_POST['bcc'] ?? '';
        
        $contextParts = [
            "subject: $subject",
            "datetime: $datetime",
            "sender_email: $senderEmail",
            "sender_name: $senderName"
        ];
        
        if ($to !== '') $contextParts[] = "to: $to";
        if ($cc !== '') $contextParts[] = "cc: $cc";
        if ($bcc !== '') $contextParts[] = "bcc: $bcc";
        
        $contextStr = implode(', ', $contextParts);
        
        $cmd = "(for context: $contextStr), email body: $body";
    }
}
































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// index.php normal handling
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// ////////////////////////////////////////////////////////////////////// index.php normal handling
// Allow requests that either contain a command or at least one successfully uploaded attachment
$hasAttachment = false;
if (isset($_FILES['attachments'])) {
    if (is_array($_FILES['attachments']['error'])) {
        $hasAttachment = in_array(UPLOAD_ERR_OK, $_FILES['attachments']['error'], true);
    } else {
        $hasAttachment = ($_FILES['attachments']['error'] === UPLOAD_ERR_OK);
    }
}
if (trim($cmd) === '' && !$hasAttachment) {
    header('HTTP/1.1 400 Bad Request');
    echo 'ERROR: No command provided';
    exit;
}

$logsDecoded = json_decode($logsJson, true);
if (!is_array($logsDecoded)) {
    // If parsing fails, treat as empty history
    $logsDecoded = [];
}

$AllLogs        = $logsDecoded;                    // All messages
$RecentLogs     = array_slice($logsDecoded, -30);  // Last 30 messages
$MostRecentLogs = array_slice($logsDecoded, -6);   // Last 6 messages

// Inject workflow WhatToDo if workflow_id is present
try {
    if ($workflowId) {
        // Get main workflow
        $stmt = $pdo->prepare("
            SELECT WhatToDo, ActionType 
            FROM workflows 
            WHERE idpk = ? AND IdpkOfAdmin = ?
            LIMIT 1
        ");
        $stmt->execute([$workflowId, $user_id]);
        $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($workflow['ActionType'])) {
            $actionTypesRaw = strtolower(trim($workflow['ActionType']));
            $skipActionDetection = true;
        }

        // Get child workflows
        $stmtChild = $pdo->prepare("
            SELECT idpk 
            FROM workflows 
            WHERE IdpkUpstreamWorkflow = ? AND IdpkOfAdmin = ?
        ");
        $stmtChild->execute([$workflowId, $user_id]);
        $childWorkflows = $stmtChild->fetchAll(PDO::FETCH_COLUMN);

        if ($childWorkflows) {
            $childWorkflowIds = array_map('intval', $childWorkflows);
        }

        if ($workflow && !empty($workflow['WhatToDo'])) {
            $cleanUserInput = trim($cmd);
            $mainCommand = trim($workflow['WhatToDo']);

            if ($cleanUserInput === 'RUN WORKFLOW') {
                $cmd = $mainCommand;
            } else {
                $cmd = ($cleanUserInput !== '')
                    ? "$mainCommand (additional notes: $cleanUserInput)"
                    : $mainCommand;
            }

            // $responses[] = [
            //     'label' => 'CONSOLE_LOG_ONLY',
            //     'message' => "⟪FINAL CMD⟫ $cmd"
            // ];
        }
    }
} catch (PDOException $e) {
    // Add error message to your response array
    $responses[] = [
        'label' => 'CHAT',
        'message' => 'We are very sorry, but there was an error executing the workflow: ' . $e->getMessage(),
    ];
    // Optionally, you could set $cmd to some default or handle it otherwise
}





































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// handling attachments
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


use Smalot\PdfParser\Parser as PdfParser;  
// Make sure you have run `composer require smalot/pdfparser`
// and that your autoloader is included earlier in this file.

$attachmentPaths = [];   // will hold actual filesystem paths of each saved file
$fileMetadatas   = [];   // optional metadata to pass into your AI prompt

if (isset($_FILES['attachments'])) {
    $files = $_FILES['attachments'];

    for ($i = 0; $i < count($files['name']); $i++) {
        // Skip any file that failed to upload
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        // 1) Original filename & temporary location
        $originalName = basename($files['name'][$i]);
        $tmpName      = $files['tmp_name'][$i];
        $sizeBytes    = $files['size'][$i];
        $mimeType     = mime_content_type($tmpName)
                        ?: ($files['type'][$i] ?? 'application/octet-stream');

        // 2) Ensure tmp/ directory exists for this admin and user
        // Use an absolute path to avoid nested directories like BRAIN/BRAIN/tmp
        $uploadDirBase = (realpath(__DIR__) ?: __DIR__) . '/tmp';
        $uploadDir = $uploadDirBase . '/' . $admin_id . '_' . $user_id;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 3) Generate a “safe” filename to avoid collisions
        $randomSuffix = bin2hex(random_bytes(4));
        $timestamp    = time();
        $safeName     = $timestamp . '_' . $randomSuffix . '_' . $originalName;
        $destPath     = $uploadDir . '/' . $safeName;

        // 4) Move the uploaded file into tmp/
        if (! move_uploaded_file($tmpName, $destPath)) {
            error_log("Failed to move '{$originalName}' to '{$destPath}'");
            continue;
        }

        $attachmentPaths[] = $destPath;

        // 5) Optional: grab first 1 KB as base64 for a tiny preview
        $previewBase64 = '';
        if ($fh = fopen($destPath, 'rb')) {
            $previewBase64 = base64_encode(fread($fh, 1024));
            fclose($fh);
        }

        // 6) Detect extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fileContent = '';  // this will hold extracted‐text or a placeholder
        $dataUrl = '';      // full data URL for image files

        // ——> (A) PDF logic
        if ($ext === 'pdf') {
            try {
                // If you haven’t already: `composer require smalot/pdfparser`
                $parser = new PdfParser();
                $pdfObj = $parser->parseFile($destPath);
                $fileContent = $pdfObj->getText();
            } catch (\Exception $e) {
                $fileContent = "[Error: could not parse PDF ({$e->getMessage()})]";
            }

        // ——> (B) Plain‐text / Markdown / CSV / JSON / XML
        } elseif (in_array($ext, ['txt','md','csv','json','xml'])) {
            $fileContent = file_get_contents($destPath);
            // If the file is extremely large, truncate to first 100 KB (adjust as needed)
            if (strlen($fileContent) > 100_000) {
                $fileContent = substr($fileContent, 0, 100_000)
                             . "\n\n...[truncated at 100 KB]";
            }

        // ——> (C) Image files (OCR via Tesseract)
        } elseif (in_array($ext, ['png','jpg','jpeg','tiff','bmp','gif'])) {
            // Make sure Tesseract is installed on your server and in PATH.
            // We will run: `tesseract <input> stdout -l eng` (or change `-l eng` to your language)
            //
            // If your PHP is running in a chroot or has no exec, you might need to give
            // the full path to tesseract, e.g. '/usr/bin/tesseract'.
            //
            // Note: OCR can be slow—consider resizing very large images first.
            $ocrCommand = "tesseract " . escapeshellarg($destPath) . " stdout -l eng";
            $ocrOutput  = '';
            $ocrError   = '';

            // Execute Tesseract; capture stdout into $ocrOutput
            // Suppress stderr (if any) by redirecting it to /dev/null or capture it in $ocrError
            $descriptorspec = [
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w']   // stderr
            ];
            $process = proc_open(
                $ocrCommand,
                $descriptorspec,
                $pipes
            );

            if (is_resource($process)) {
                $ocrOutput = stream_get_contents($pipes[1]);
                fclose($pipes[1]);

                $ocrError = stream_get_contents($pipes[2]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);
                if ($returnCode !== 0) {
                    // OCR failed or produced an error
                    $fileContent = "[OCR error (exit code {$returnCode}): {$ocrError}]";
                } else {
                    // OCR succeeded
                    $fileContent = trim($ocrOutput);
                    // Truncate if too long
                    if (strlen($fileContent) > 100_000) {
                        $fileContent = substr($fileContent, 0, 100_000)
                                     . "\n\n...[truncated at 100 KB]";
                    }
                }
            } else {
                $fileContent = "[OCR error: could not start Tesseract process.]";
            }

            // Build a base64 data URL so the image can be embedded directly in API calls
            $base64Image = base64_encode(file_get_contents($destPath));
            $dataUrl = 'data:' . $mimeType . ';base64,' . $base64Image;

        // ——> (D) Other binary files: no text available
        } else {
            $fileContent = "[Binary file (MIME={$mimeType}); no text extracted.]";
        }

        // 7) Append metadata + extracted content into $fileMetadatas

        // Assume public URL base (adjust to your domain and file path)
        $publicUrl = 'https://www.tramann-projects.com/BRAIN/tmp/'
                     . rawurlencode($admin_id . '_' . $user_id) . '/'
                     . rawurlencode($safeName);

        $fileMetadatas[] = [
            'filename'      => $originalName,
            'stored_path'   => $destPath,
            'size_bytes'    => $sizeBytes,
            'mime_type'     => $mimeType,
            'preview_base64'=> $previewBase64,
            'content'       => $fileContent,
            'file_url'      => $publicUrl,
            'data_url'      => $dataUrl,
        ];
    }
}




















































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// figure out what to do
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Step 1: Determine action type using most recent context
// ////////////////////////////////////////////////////////////////////// EmailBot special handling
if (isset($_POST['origin']) && $_POST['origin'] === 'EmailBot') {
    $messages = array_filter([
        [
            'role'    => 'system',
            'content' => <<<EOD
# You are an AI that determines the type(s) of action needed in the email the user gives you.
Respond with a comma-separated list in the exact format: keyword [task-specific instruction]
Always respond with at least one keyword and somethign in [brackets], if there are multiple steps, respond with a comma-separated list of these.

## Example user command:
"Tell me the prices of the tables we sold to Peter, change the delivery address street to "Main Avenue" and send him an email."
## Example plan for that:
databasessearch [search for the last cart and transactions we had with Peter], databasesupdate[adjust the delivery address street to "Main Avenue"], email [inform him that we adjusted the street name]

## Only use the following keywords:
spam, summary, databasesselect, databasesinsertinto, databasesupdate, databasesdelete, databasessearch, databasesgetcontext, databasesattachmenthandling, buying, selling, chat.
For database actions, just take what fits best, which action is the most important one, but select only one.
Use "spam" if you consider the email to be irrelevant (in that case, don't put other labels in the list), mind that many emails are just spam,
"summary" for quickly communicating main parts of an just informational email (here also no other labels then please),
"databasesselect" for prime action being selecting from databases,
"databasesinsertinto" for prime action inserting into,
"databasesupdate" for updating,
"databasesdelete" for deleting entries,
"databasessearch" for searching,
"databasesgetcontext" for searching and then getting additional information too,
"databasesattachmenthandling" for adding, changing or deleting attachments of database entries (such as product pictures, manuals, images / texts / other files which can be attached/linked to an entry, ...)
"buying" if the user / the users company buys something,
"selling" if he sells something,
and "chat" for general conversation or explanations.

In general, it might often be useful to databasesgetcontext as the action lists first step to get more context for your responses.

Don't explain anything—just return the structured list.
EOD
        ],
        !empty($MostRecentLogs) ? [
            'role'    => 'system',
            'content' => "Recent context:\n" . implode("\n", $MostRecentLogs)
    //                         . "\n\nAttached files metadata:\n" . json_encode($fileMetadatas)
        ] : null,
        [
            'role'    => 'user',
            'content' => $cmd
        ],
    ]);

    $actionPrompt = [
        'model' => 'gpt-4.1-mini', // faster
        // 'model' => 'gpt-4.1', // smarter
        'input' => convertMessagesToInput($messages),
        'max_output_tokens'  => 500,
        'temperature' => 0.3,
    ];
} else {
// ////////////////////////////////////////////////////////////////////// index.php normal handling

// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////// IMPORTANT NOTE: the following action types also appear in workflows.php, changes here have to also been made there
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $userContent = $cmd;
    if (trim($cmd) === '' && !empty($fileMetadatas)) {
        $userContent = array_map(function ($meta) {
            $ext = strtolower(pathinfo($meta['filename'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','tiff','bmp','gif'])) {
                return [
                    'type' => 'image_url',
                    // Responses API expects image_url to be a string, not an object
                    'image_url' => $meta['data_url'] ?: $meta['file_url']
                ];
            }
            return [
                'type' => 'text',
                'text' => "[{$meta['filename']}]\n\n" . $meta['content']
            ];
        }, $fileMetadatas);
    }

    $messages = array_filter([
        [
            'role'    => 'system',
            'content' => <<<EOD
# You are an AI that determines the type(s) of action needed in the user command.
Respond with a comma-separated list in the exact format: keyword [task-specific instruction]
Always respond with at least one keyword and somethign in [brackets], if there are multiple steps, respond with a comma-separated list of these.

## Example user command:
"Tell me the prices of the tables we sold to Peter, change the delivery address street to "Main Avenue" and send him an email."
## Example plan for that:
databasessearch [search for the last cart and transactions we had with Peter], databasesupdate[adjust the delivery address street to "Main Avenue"], email [inform him that we adjusted the street name]

## Only use the following keywords:
math, location, chart, table, code, email, pdf, pdfinvoice, pdfoffer, pdfdeliveryreceipt, pdfreport, pdfcontract, pdflegaldocument, databasesselect, databasesinsertinto, databasesupdate, databasesdelete, databasessearch, databasesgetcontext, databasesattachmenthandling, buying, selling, marketingtexting, chat.
For database actions, just take what fits best, which action is the most important one, but select only one.
Use "math" for calculations,
"location" for map requests,
"chart" for data visualization,
"table" for tables,
"code" for code blocks,
"email" for communication tasks (sending),
"pdf" for creating a downloadable PDF document in general (if none of the following, more specific PDF solutions should apply),
"pdfinvoice" downloadable PDF document for invoices,
"pdfoffer" for offers/quotations,
"pdfdeliveryreceipt" for delivery receipts,
"pdfreport" for reports,
"pdfcontract" for contracts,
"pdflegaldocument" for legal documents,
"databasesselect" for prime action being selecting from databases,
"databasesinsertinto" for prime action inserting into,
"databasesupdate" for updating,
"databasesdelete" for deleting entries,
"databasessearch" for searching,
"databasesgetcontext" for searching and then getting additional information too,
"databasesattachmenthandling" for adding, changing or deleting attachments of database entries (such as product pictures, manuals, images / texts / other files which can be attached/linked to an entry, ...)
"buying" if the user / the users company buys something,
"selling" if he sells something,
"marketingtexting" for writing advertisement texts, product descriptions and other marketing stuff,
and "chat" for general conversation or explanations.

For pdfinvoice, pdfoffer and pdfdeliveryreceipt, before these actions, add something like this:
databasesgetcontext [look for relevant customer and product data]
For pdfcontract and pdflegaldocument it could be useful too, you have to decide that based on the context.

In general, it might often be useful to databasesgetcontext as the action lists first step to get more context for your responses.

Please mind that all database actions can also create charts and tables, so in case of doubt, tend to using them,
**NEVER** double by having databse action + chart/table, database actions can already handle the visualization part on their own too.
**Don't** explain anything—just return the structured list.
EOD
        ],
        !empty($MostRecentLogs) ? [
            'role'    => 'system',
            'content' => "Recent context:\n" . implode("\n", $MostRecentLogs)
    //                         . "\n\nAttached files metadata:\n" . json_encode($fileMetadatas)
        ] : null,
        [
            'role'    => 'user',
            'content' => $userContent
        ],
    ]);

    $actionPrompt = [
        'model' => 'gpt-4.1-mini', // faster
        // 'model' => 'gpt-4.1', // smarter
        'input' => convertMessagesToInput($messages),
        'max_output_tokens'  => 500,
        'temperature' => 0.3,
    ];
}

// /////////////////////////////////////////////////////////// do not call it if we should already have an action type defined by the workflow
if (!$skipActionDetection) {
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($actionPrompt),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $actionResponse = curl_exec($ch);
    $actionDecoded = json_decode($actionResponse, true);

    // // 1. Log raw API response
    // $responses[] = [
    //     'label'   => 'CONSOLE_LOG_ONLY',
    //     'message' => "⟪RAW API RESPONSE⟫ " . json_encode($actionDecoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    // ];

    $actionTypesRaw = strtolower(trim(extractTextFromResponse($actionDecoded) ?: 'chat'));
    // $actionTypes = array_map('trim', explode(',', $actionTypesRaw));

    // // 2. Log raw string from the model
    // $responses[] = [
    //     'label'   => 'CONSOLE_LOG_ONLY',
    //     'message' => "⟪RAW ACTION STRING⟫ " . $actionTypesRaw
    // ];
}

$matches = [];
preg_match_all('/([a-z]+)(?:\s*\[([^\]]*)\])?/i', $actionTypesRaw, $matches, PREG_SET_ORDER);

// // 3. Log match results
// $responses[] = [
//     'label'   => 'CONSOLE_LOG_ONLY',
//     'message' => "⟪PARSED MATCHES⟫ " . json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
// ];

$actionTypes = [];
$actionInstructions = [];

// Fallback
// if (empty($actionTypes)) {
//     $actionTypes = ['chat'];
//     $actionInstructions = [''];
// }

foreach ($matches as $match) {
    $type = trim($match[1]);
    $instruction = isset($match[2]) ? trim($match[2]) : '';  // Empty string if no instruction
    $actionTypes[] = $type;
    $actionInstructions[] = $instruction;
}

$actionPlanListed = [];
foreach ($actionTypes as $i => $type) {
    $instruction = $actionInstructions[$i];
    if ($instruction !== '') {
        $actionPlanListed[] = "$type [{$instruction}]";
    } else {
        $actionPlanListed[] = $type;
    }
}

// Communicate the final action plan to the frontend for debugging
$responses[] = [
    'label'   => 'CONSOLE_LOG_ONLY',
    'message' => '⟪ACTION PLAN⟫ ' . implode(', ', $actionPlanListed)
];

// Step 2: Handle the action with full context
$totalSteps    = count($actionTypes);
$fullPlanText  = implode(", ", $actionPlanListed);
$results = [];






// ////////////////////////////////////////////////////////////////////// Catch spam for EmailBot.php
if (in_array('spam', $actionTypes, true)) {
    $responses[] = [
        'label'   => 'SPAM',
        'message' => 'This email has been classified as spam and or or irrelevant.',
        'code'    => ''
    ];

    ob_clean(); // Clean all previous output
    echo json_encode($responses, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ////////////////////////////////////////////////////////////////////// For the main query for EmailBot.php, add the email writing instructions of the user to the message
if (isset($_POST['origin']) && $_POST['origin'] === 'EmailBot') {
        $cmd .= " (Please also respect the user's email writing instructions in your crafted email response, which are: $sanitizedEmailWritingInstructions)";
}









// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// main query
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// foreach ($actionTypes as $type) {
    // $actionPrompt = [
    //     'model'    => 'gpt-4o-mini',
    //     'messages' => [
    //         [
    //             'role'    => 'system',
    //             'content' => $GeneralInstructions
    //         ],
    //         [
    //             'role'    => 'system',
    //             'content' => match($type) {
    //                 'summary' => 'Please just briefly summarize the main points of the email.', // just for EmailBot.php
    //                 'math' => 'You are a calculator. Respond with only the result of the calculation, prefixed with "= ". For example: "= 42"',
    //                 'location' => $LocationInstructions,
    //                 'chart' => 'You are a data visualization expert. Just respond with a Chart.js configuration object that can be used to create a chart, no other text or anything else. Format: {"type": "line", "data": {"labels": [], "datasets": []}}',
    //                 'table' => 'You are a table creator. Just respond with a Markdown table. Format: | Header1 | Header2 | ... |\n|--------|--------| ... |\n| Row1   | Row2   | ... |\n| Row3   | Row4   | ... |',
    //                 'code' => 'You are a code formatter. Respond with the code wrapped in a pre tag. Format: <pre>code here</pre>',
    //                 'email'    => $EmailWritingInstructionsPrompt,
    //                 'emailreading'    => $EmailReadingInstructionsPrompt,
    //                 'pdf'    => $PDFPrompt,
    //                 'databasesselect'    => $DatabasesPromptSELECT,
    //                 'databasesinsertinto'    => $DatabasesPromptINSERTINTO,
    //                 'databasesupdate'    => $DatabasesPromptUPDATE,
    //                 'databasesdelete'    => $DatabasesPromptDELETE,
    //                 'databasessearch'    => $DatabasesPromptSEARCH,
    //                 'databasesgetcontext' => $DatabasesPromptGETCONTEXT,
    //                 'databasesattachmenthandling' => $DatabasesPromptATTACHMENTHANDLING,
    //                 'buying' => $BuyingPrompt,
    //                 'selling' => $SellingPrompt,
    //                 'marketingtexting' => $MarketingTextingPrompt,
    //                 default => 'just chatting'
    //             }
    //         ],
    //         [
    //             'role'    => 'system',
    //             'content' => "Recent context:\n" . implode("\n", $RecentLogs)
    //         ],
    //         [
    //             'role' => 'user',
    //             'content' => array_merge(
    //                 [
    //                     [
    //                         'type' => 'text',
    //                         'text' => $cmd  // Your actual user instruction or prompt
    //                     ]
    //                 ],
    //                 array_map(function ($meta) {
    //                     $ext = strtolower(pathinfo($meta['filename'], PATHINFO_EXTENSION));
    //                                     
    //                     if (in_array($ext, ['png','jpg','jpeg','tiff','bmp','gif'])) {
    //                         // For image files: send as image_url
    //                         return [
    //                             'type' => 'image_url',
    //                             'image_url' => [
    //                                 'url' => $meta['file_url']
    //                             ]
    //                         ];
    //                     } else {
    //                         // For everything else: send as plain text
    //                         return [
    //                             'type' => 'text',
    //                             'text' => "[{$meta['filename']}]\n\n" . $meta['content']
    //                         ];
    //                     }
    //                 }, $fileMetadatas)
    //             )
    //         ]
    //     ],
    //     'max_output_tokens'  => 2000,
    //     'temperature' => 0.7,
    // ];
$dbActionTypes = ['databasesselect', 'databasesinsertinto', 'databasesupdate', 'databasesdelete', 'databasessearch', 'databasesgetcontext', 'databasesattachmenthandling'];

foreach ($actionTypes as $i => $type) {
    $taskInstruction = isset($actionInstructions[$i]) && trim($actionInstructions[$i]) !== ''
        ? $actionInstructions[$i]
        : '[no specific instruction]';
    
    $planContext = '';
    if ($totalSteps >= 2) {
        $stepNumber = $i + 1;
        $planContext = "Here is the full plan: $fullPlanText. You are executing step {$stepNumber} of {$totalSteps}, focusing on the subdomain \"{$type}\". **ONLY** handle this step.";
    }

    $previousResults = array_slice($results, 0, $i);
    $contextText = !empty($RecentLogs) ? "# Recent context:\n" . implode("\n", $RecentLogs) : '';
    if (!empty($previousResults)) {
        $contextText .= ($contextText !== '' ? "\n\n" : '') . "# Previous subtask results:\n" . implode("\n\n", $previousResults);
    }

    $messages = [
        [
            'role' => 'system',
            'content' => $GeneralInstructions
        ],
        [
            'role' => 'system',
            'content' => "Your specific task: $taskInstruction" . ($planContext ? "\n\n$planContext" : "")
        ],
        [
            'role' => 'system',
            'content' => match($type) {
                'summary' => 'Please just briefly summarize the main points of the email.',
                'math' => 'You are a calculator. Respond with only the result of the calculation, prefixed with "= ". For example: "= 42"',
                'location' => $LocationInstructions,
                'chart' => 'You are a data visualization expert. Just respond with a Chart.js configuration object that can be used to create a chart, no other text or anything else. Example: { "type": "bar", "data": { "labels": ["A", "B", "C"], "datasets": [{ "label": "values", "data": [12, 19, 3], "backgroundColor": "rgba(54, 162, 235, 0.5)", "borderColor": "rgba(54, 162, 235, 1)", "borderWidth": 1 }] }, "options": { "responsive": true, "scales": { "y": { "beginAtZero": true } } } }',
                'table' => 'You are a table creator. Just respond with a Markdown table.',
                'code' => 'You are a code formatter. Respond with the code wrapped in a pre tag.',
                'email' => $EmailWritingInstructionsPrompt,
                // 'emailreading' => $EmailReadingInstructionsPrompt,
                'pdf' => $PDFPrompt,
                'pdfinvoice' => $PDFPromptInvoice,
                'pdfoffer' => $PDFPromptOffer,
                'pdfdeliveryreceipt' => $PDFPromptDeliveryReceipt,
                'pdfreport' => $PDFPromptReport,
                'pdfcontract' => $PDFPromptContract,
                'pdflegaldocument' => $PDFPromptLegalDocument,
                'databasesselect' => $DatabasesPromptSELECT,
                'databasesinsertinto' => $DatabasesPromptINSERTINTO,
                'databasesupdate' => $DatabasesPromptUPDATE,
                'databasesdelete' => $DatabasesPromptDELETE,
                'databasessearch' => $DatabasesPromptSEARCH,
                'databasesgetcontext' => $DatabasesPromptGETCONTEXT,
                'databasesattachmenthandling' => $DatabasesPromptATTACHMENTHANDLING,
                'buying' => $BuyingPrompt,
                'selling' => $SellingPrompt,
                'marketingtexting' => $MarketingTextingPrompt,
                default => 'Just chat naturally about the topic.'
            }
        ],
        [
            'role' => 'system',
            'content' => $contextText
        ],
        [
            'role' => 'user',
            'content' => array_merge(
                [
                    [
                        'type' => 'input_text',
                        'text' => $cmd
                    ]
                ],
                array_map(function ($meta) {
                    $ext = strtolower(pathinfo($meta['filename'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['png','jpg','jpeg','tiff','bmp','gif'])) {
                        $url = $meta['file_url'] ?: $meta['data_url'];
                        // Use string URL as required by Responses API
                        return ['type' => 'input_image', 'image_url' => $url];
                    }
                    return ['type' => 'input_text', 'text' => "[{$meta['filename']}]\n\n" . $meta['content']];
                }, $fileMetadatas)
            )
        ]
    ];

    if ($contextText === '') {
        array_splice($messages, 3, 1);
    }

    $temperature = match($type) {
        'math',
        'chart',
        'table',
        'pdf',
        'pdfinvoice',
        'pdfoffer',
        'pdfdeliveryreceipt',
        'pdfreport',
        'pdfcontract',
        'pdflegaldocument' => 0.3,
        default => 0.7,
    };

    $actionPrompt = [
        'model' => 'gpt-4.1-mini', // faster
        // 'model' => 'gpt-4.1', // smarter
        'input' => convertMessagesToInput($messages),
        'max_output_tokens' => 2000,
        'temperature' => $temperature,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($actionPrompt),
    CURLOPT_TIMEOUT        => 30,
]);



    $response = curl_exec($ch);
    $err      = curl_error($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300) {
        $errorMsg = "We are very sorry, but there was an error for '$type': " . ($err ?: 'HTTP status ' . $code);
        $results[] = $errorMsg;
        $responses[] = [
            'label'   => strtoupper($type),
            'message' => $errorMsg,
        ];
        continue;
    }

    // ///////////////////////////////////////////////////////////////// comment in the following for better debugging
    // if ($err || $code < 200 || $code >= 300) {
    //     // Try to extract more detailed error message
    //     $respSnippet = '';
    //     if (is_string($response) && $response !== '') {
    //         $respSnippet = substr($response, 0, 2000); // don’t spam logs
    //         $decodedErr  = json_decode($response, true);
    //         if (isset($decodedErr['error']['message'])) {
    //             $respSnippet = $decodedErr['error']['message'];
    //         }
    //     }
    //     $errorMsg = "We are very sorry, but there was an erro for '$type': " . ($err ?: "HTTP status $code") . ($respSnippet ? " — $respSnippet" : "");
    //     $results[] = $errorMsg;
    //     $responses[] = [
    //         'label'   => strtoupper($type),
    //         'message' => $errorMsg,
    //     ];
    //     continue;
    // }



    $decoded = json_decode($response, true);
    $content = extractTextFromResponse($decoded);

    if (in_array($type, $dbActionTypes) && (!isset($_POST['origin']) || $_POST['origin'] !== 'EmailBot')) {
        $commands = explode("=== END ===", $content);
        $dbMessages = [];
        foreach ($commands as $cmdPiece) {
            $trimmed = trim($cmdPiece);
            if ($trimmed === '') {
                continue;
            }
            $trimmed = preg_replace('/^\s*<\?php\s*|\?>\s*$/', '', $trimmed);
            $trimmed = trim($trimmed);

            $payload = [
                'APIKey'      => $TRAMANNAPIAPIKey,
                'MakeLogEntry'=> "1",
                'message'     => $trimmed,
            ];

            $maskedPayload = $payload;
            $maskedPayload['APIKey'] = 'PlaceholderForYourTRAMANNAPIAPIKey';
            $maskedJson = json_encode($maskedPayload, JSON_UNESCAPED_UNICODE);
            if ($maskedJson === false) {
                $maskedJson = '[JSON ENCODE ERROR: ' . json_last_error_msg() . ']';
            }
            $responses[] = [
                'label'   => 'CONSOLE_LOG_ONLY',
                'message' => "⟪TRAMANN API CALL PAYLOAD⟫ " . $maskedJson,
            ];

            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'];
            $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
            if ($basePath === DIRECTORY_SEPARATOR) {
                $basePath = '';
            }
            $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

            $chDb = curl_init($nexusUrl);
            curl_setopt_array($chDb, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 30,
            ]);
            $apiResult   = curl_exec($chDb);
            $apiErr      = curl_error($chDb);
            $apiHttpCode = curl_getinfo($chDb, CURLINFO_HTTP_CODE);
            curl_close($chDb);

            $responses[] = [
                'label'   => 'CONSOLE_LOG_ONLY',
                'message' => "⟪TRAMANN API RAW RESPONSE⟫ " . $apiResult,
            ];

            if ($apiErr || $apiHttpCode < 200 || $apiHttpCode >= 300) {
                $dbMessages[] = "We are very sorry, but there was an error connecting to TRAMANN API: " . ($apiErr ?: "HTTP $apiHttpCode");
            } else {
                $decodedMessage = json_decode($apiResult, true);
                $dbMessages[] = trim($decodedMessage['message'] ?? '[No message returned]');
            }
        }
        $finalDbMessage = implode("\n", $dbMessages);
        $results[] = $finalDbMessage;
        $responses[] = [
            'label'   => ($type === 'databasesgetcontext' ? 'DATABASESGETCONTEXT' : 'CHATDB'),
            'message' => $finalDbMessage,
        ];
    } else {
        $results[] = $content;
        if (!in_array($type, $dbActionTypes) || (isset($_POST['origin']) && $_POST['origin'] === 'EmailBot')) {
            $responses[] = [
                'label'   => strtoupper($type),
                'message' => $content,
            ];
        }
    }
}






























function convertMessagesToInput(array $messages): array {
    return array_map(function ($m) {
        $content = $m['content'] ?? '';
        if (is_string($content)) {
            $content = [['type' => 'input_text', 'text' => $content]];
        } elseif (is_array($content)) {
            $content = array_map(function ($part) {
                if (($part['type'] ?? '') === 'text') {
                    $part['type'] = 'input_text';
                } elseif (($part['type'] ?? '') === 'image_url') {
                    $part['type'] = 'input_image';
                    // Some callers may provide image_url as an array with a 'url' key
                    if (isset($part['image_url']) && is_array($part['image_url'])) {
                        $part['image_url'] = $part['image_url']['url'] ?? '';
                    }
                }
                return $part;
            }, $content);
        }
        return [
            'role' => $m['role'] ?? 'user',
            'content' => $content
        ];
    }, $messages);
}

function extractTextFromResponse(array $response): string {
    $text = '';
    foreach ($response['output'][0]['content'] ?? [] as $part) {
        if (($part['type'] ?? '') === 'output_text') {
            $text .= $part['text'];
        }
    }
    return trim($text);
}

// ////////////////////////////////////////////////////////////////////// EmailBot special handling
function extractCodeAndMessageFromAIResponse(string $raw): array {
    $echoPattern = '/echo\s+(.*?);/is';
    $messageParts = [];

    // Extract echoed content (just string literals and HTML)
    preg_match_all($echoPattern, $raw, $echoMatches);

    if (!empty($echoMatches[1])) {
        foreach ($echoMatches[1] as $echoExpr) {
            // Attempt to safely strip quotes and concatenation
            preg_match_all('/([\'"])(.*?)\1/', $echoExpr, $parts);
            $messageParts[] = implode('', $parts[2] ?? []);
        }
    } else {
        $messageParts[] = 'Please have a quick look at the code below and the echo response there, because the parsing in here failed.';
    }

    return [
        'code'    => trim($raw),
        'message' => trim(implode("\n", $messageParts))
    ];
}

if (isset($_POST['origin']) && $_POST['origin'] === 'EmailBot') {
    foreach ($actionTypes as $i => $type) {
        if (in_array($type, $dbActionTypes)) {
            $raw = $results[$i] ?? '';
            $parsed = extractCodeAndMessageFromAIResponse($raw);

            $responses[] = [
                'label'   => ($type === 'databasesgetcontext' ? 'DATABASESGETCONTEXT' : 'CHATDB'),
                'message' => $parsed['message'],
                'code'    => $parsed['code']
            ];

            break;
        }
    }
}













// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// cleaning and responding
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// === Step 8: (Temporarily disabled) Do not clean up files immediately ===
// Files remain in BRAIN/tmp/<admin>_<user> so they can be reused or downloaded later.
// They will be removed only when the user clears the chat.

// Add workflow ID to response, if any
if ($workflowId) {
    $responses[] = [
        'label' => 'WORKFLOWIDPK',
        'message' => "⟪WORKFLOW IDPK⟫ $workflowId",
        'workflowId' => (int)$workflowId,
        'childWorkflowIds' => $childWorkflowIds,
        'workflowText' => $workflow['WhatToDo'] ?? ''
    ];
}

ob_clean(); // clean all previous warnings or errors
// Send JSON
header('Content-Type: application/json');
echo json_encode($responses, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
exit;

ob_end_flush(); // Flush the output buffer
?>
