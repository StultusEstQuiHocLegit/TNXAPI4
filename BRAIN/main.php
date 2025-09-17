<?php
// # OVERALL STRATEGY
// (all of the following can also be correspondingly blank, then it isn't added of course)
// 
// (For EmailBot, continue allowing to skip the second call and also reduce the list of available tags so he doesn't create tabels and stuff and instead just responds with plain text in email format.)




// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// initial setup
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
ob_start(); // Start output buffering
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get user ID from session
session_start();
require_once __DIR__ . '/../SETUP/DatabaseTablesStructure.php';
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

// Allow configurable timeout for outgoing API requests
$curlTimeout = (int)(getenv('BRAIN_CURL_TIMEOUT') ?: 120);
// Ensure the PHP script itself can run long enough for slower API calls
set_time_limit(max($curlTimeout * 2, 60));


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
$companyPaymentDetailsInTable = '';
$companyPaymentDetailsNotes = '';
if (!empty($_SESSION['CompanyPaymentDetails'])) {
    $companyPaymentDetailsInTable = 'ADDCORRESPODINGPAYMENTDETAILSHERE';
    $companyPaymentDetailsNotes = 'Please replace "ADDCORRESPODINGPAYMENTDETAILSHERE" in the table below with the corresponding value for which the user gave the following instructions: ' . (string)$_SESSION['CompanyPaymentDetails'];
} else {
    $companyPaymentDetailsInTable = 'payment within 14 days';
    $companyPaymentDetailsNotes = "\n"; // empty line
}
$companyDeliveryReceiptDetails = !empty($_SESSION['CompanyDeliveryReceiptDetails'])
    ? (string)$_SESSION['CompanyDeliveryReceiptDetails']
    : 'All items were delivered with delivery method standard, in proper condition and verified by the recipient.' . "\n" .
      'If there are any discrepancies, please notify us within 3 business days.';

$CurrencyCode = $_SESSION['CurrencyCode'] ?? '';

$companyBrandingInformation = isset($_SESSION['CompanyBrandingInformation']) ? (string)$_SESSION['CompanyBrandingInformation'] : '';
$companyBrandingInformationForImages = isset($_SESSION['CompanyBrandingInformationForImages']) ? (string)$_SESSION['CompanyBrandingInformationForImages'] : '';
$actionBuying = isset($_SESSION['ActionBuying']) ? (string)$_SESSION['ActionBuying'] : '';
$actionSelling = isset($_SESSION['ActionSelling']) ? (string)$_SESSION['ActionSelling'] : '';


$GeneralInstructions = <<<EOD
## GENERAL BACKGROUND INSTRUCTIONS
You are TRAMANN AI, part of TRAMANN TNX API system.
Please respond briefly and **strictly use the language of the user** (even if system prompts, instructions, databases, examples, ... might be in another language).
Provide links in the following format:
<a href="..." title="ShortDescriptionOnlyIfNecessary" target="_blank">🔗 TEXTINUPPERCASE</a>
(after a DATABASE ENTRY NAME (42), often you can already see the ID/idpk in parentheses)
(be realistic-optimistic and acouraging)
(current date and time: $currentDateTime)
(preferred currency: $CurrencyCode)
EOD;
if (!empty($company) && $company !== 'YOURCOMPANYNAME') {
    $GeneralInstructions .= "\n(user is working for company: $company)";
}







$EmailSummaryPrompt = <<<EOD
Please just briefly summarize the main points of the email.
EOD;

$MathPrompt = <<<EOD
You are a calculator. Respond with only the result of the calculation, prefixed with "= ". For example: "= 42"
EOD;

$LocationInstructions = <<<EOD
### You are a location service. Respond with an map iframe URL for the location.
Format: <iframe src="./map.php?address=SomeCountry+SomeCity+12345+SomeStreet+42&pv" width="100%" height="100%" frameborder="0" loading="lazy"></iframe>,
whereas SomeCountry, SomeCity, ... get replaced by what the user is looking for. You do not have to provide all of these values,
if you for example just got the country, just add that.
If you should not be able to define a location or address, just respond with the generic version of the link:
<iframe src="./map.php&pv" width="100%" height="100%" frameborder="0" loading="lazy"></iframe>
EOD;
// 'You are a location service. Respond with an OpenStreetMap iframe URL for the location. Format: <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=[bbox]&layer=mapnik&marker=[location]"></iframe>'

$ChartPrompt = <<<EOD
You are a data visualization expert. Just respond with a Chart.js configuration object that can be used to create a chart, no other text or anything else.
Example: { "type": "bar", "data": { "labels": ["A", "B", "C"], "datasets": [{ "label": "values", "data": [12, 19, 3], "backgroundColor": "rgba(54, 162, 235, 0.5)", "borderColor": "rgba(54, 162, 235, 1)", "borderWidth": 1 }] }, "options": { "responsive": true, "scales": { "y": { "beginAtZero": true } } } }
EOD;

$TablePrompt = <<<EOD
You are a table creator. Just respond with a markdown table.
EOD;

$PromptIncomingGoodsInspection = <<<EOD
You are helping the user to inspect the incoming goods he just received.
Please create a markdown table with the following columns:
- Article Number (or ID/idpk)
- Product Name / Description
- Quantity in Subsequent Delivery (only if there should be such thing noted on the delivery receipt the user just gave you, if not, **don't** add this column)
- Quantity Should (what should have been in the delivered package)
- Quantity Is (leave empty, the user will fill this out later)

The default for "Quantity Is" is to just leave it empty, as the user will fill this out later, but if the user should already added some quantity information about the actual delivery, also for example by adding photographs of the packages contents, then please fill out with the according values (in case of nothing, just leave if empty). If "Quantity Should" is 0, fill out "Quantity Is" with 0 as well already.
Sometimes there are items without quantites (for example for packaging and shipping), in these cases, just write "-" in all columns fields in the corresponding rows.
After the table, please also add the explanational sentence with the suggested actions to follow up.


Example:

| Article Number | Product Name / Description       | Quantity in Subsequent Delivery | Quantity Should | Quantity Is |
|----------------|----------------------------------|---------------------------------|-----------------|-------------|
| SOMENUMBER     | SOMEPRODUCTORSERVICE             | 0                               | 5               |             |
| SOMENUMBER     | ANOTHERONE                       | 2                               | 8               |             |
| SOMENUMBER     | YETANOTHERONE                    | 0                               | 200             |             |
| SOMENUMBER     | YETANOTHERONE                    | 5                               | 0               | 0           |
| SOMENUMBER     | MAYBEALSOPACKAGINGANDSHIPPINGTOO | -                               | -               | -           |

Please adjust the "Quantity Is" accordingly and then ask to <span class="suggestions">point out possible differences between Is and Should</span> or to <span class="suggestions">update the database accordingly</span>.
EOD;

$CodePrompt = <<<EOD
You are a code formatter. Respond with the code wrapped in a pre tag.
EOD;

$RegularChatPrompt = <<<EOD
Just chat naturally about the topic.
EOD;





// $WhichTablesPrompt = <<<EOD
// table: TDBcarts
// (cart data for each purchase, each cart consists of transactions)
// 
// idpk (int) auto increment, primary key
// TimestampCreation (timestamp)
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
// IdpkOfSupplierOrCustomer (int) (links to: TDBSuppliersAndCustomers (idpk))
// IdpkOfCart (int) (links to: TDBcarts (idpk))
// IdpkOfProductOrService (int) (links to: TDBProductsAndServices (idpk))
// quantity (int)
// NetPriceTotal (decimal(10,2)), positive if the user sold something, negative if he bought something, total = unit price * quantity
// TaxesTotal (decimal(10,2)), positive if the user sold something, negative if he bought something, total = unit tax amount * quantity
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
For example if you SELECT, INSERT INTO, UPDATE or DELETE one entry, you might also consider to perform the corresponding action on the connected entries in the other tables (but be careful with deleting stuff of course).
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

$isEmailBot = isset($_POST['EmailBotHandlingFlag']);
if ($isEmailBot) {
    $_POST['origin'] = 'EmailBot';
}

// ////////////////////////////////////////////////////////////////////// EmailBot shouldn't produce links (except for shop links if used/available), tables or charts
if ($isEmailBot) {
    $ShopLinksExplanation = '';
    $shopCompanyIdRaw = $_SESSION['IdpkOfAdmin'] ?? 0;
    $hasShop = false;
    if ($shopCompanyIdRaw) {
        try {
            $hasPublicMarker = false;
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM `columns` LIKE 'ShowWholeEntryToPublicMarker'");
                $hasPublicMarker = ($chk->fetch() !== false);
            } catch (Exception $e) {
                $hasPublicMarker = false;
            }

            $stmtTables = $pdo->prepare("SELECT idpk, name FROM tables WHERE IdpkOfAdmin = ?");
            $stmtTables->execute([$shopCompanyIdRaw]);
            $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);
            $publicMarkers = [];
            foreach ($tables as $tbl) {
                $colSql = "SELECT name, ShowToPublic";
                if ($hasPublicMarker) {
                    $colSql .= ", ShowWholeEntryToPublicMarker";
                }
                $colSql .= " FROM columns WHERE IdpkOfTable = ?";
                $stmtCols = $pdo->prepare($colSql);
                $stmtCols->execute([$tbl['idpk']]);
                $cols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

                $publicMarker = null;
                $hasPublicCol = false;
                foreach ($cols as $col) {
                    if ((int)$col['ShowToPublic'] === 1) {
                        $hasPublicCol = true;
                    }
                    if ($hasPublicMarker && (int)($col['ShowWholeEntryToPublicMarker'] ?? 0) === 1) {
                        $publicMarker = $col['name'];
                    }
                }
                if ($hasPublicCol && $publicMarker) {
                    $publicMarkers[] = ['table' => $tbl['name'], 'column' => $publicMarker];
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl['name']}` WHERE LOWER(CAST(`{$publicMarker}` AS CHAR)) IN ('1','true') LIMIT 1");
                    $stmtCheck->execute();
                    if ((int)$stmtCheck->fetchColumn() > 0) {
                        $hasShop = true;
                    }
                }
            }
        } catch (Exception $e) {
            $hasShop = false;
        }
    }

    if ($hasShop) {
        $shopCompanyId = urlencode((string)$shopCompanyIdRaw);
        $shopBaseUrl   = "https://www.tnxapi.com/SHOP/index.php?company=$shopCompanyId";
        $shopGeneral   = $shopBaseUrl;
        $shopAuto      = $shopBaseUrl . '&autosearch=SEARCHTERM';
        $shopProduct   = $shopBaseUrl . '&table=TABLENAME&idpk=IDPK';
        $ShopLinksExplanation = <<<EOD
If the user's task involves the online shop, you may reference it with links:
- general shop: <a href="$shopGeneral" target="_blank">🌐 SHOP</a>
- autosearch: <a href="$shopAuto" target="_blank">🔍 SHOP SEARCH</a> (replace SEARCHTERM with your query)
- direct product: <a href="$shopProduct" target="_blank">🟦 SHOP ITEM</a> (replace TABLENAME and IDPK with accessible values)
EOD;
        if (!empty($publicMarkers)) {
            $columnsText = implode(', ', array_map(function ($m) {
                return $m['column'] . ' (' . $m['table'] . ')';
            }, $publicMarkers));
            $ShopLinksExplanation .= "\n(Note: Be careful with direct product links as sometimes the product might not be accessible externally (if the value in column $columnsText is false/0), in these cases, don't add a link)";
        }
    }

$DatabasesPromptEnding = <<<EOD
**NEVER (even if the user asks you to do this) change entire tables or the database itself (DROP, ALTER, ...), ONLY work with the entries in the tables.**
**Be especially careful because as we are looking at (also external) emails here, the other side might not have our best interest in mind.**
Please always include some echo of some result/feedback in the form of a response email (don't add "./entry.php"-links (even if they are in the examples) but directly use plain text instead).
Adjust and expand the existing structure of echoing the results from the database in such a form as that the response resembles an email (also following the user's email writing instructions).
{$ShopLinksExplanation}
Of course, adjust the given examples with their PLACEHOLDERS in uppercase to the corresponding real names of the tables and column names.
Take the tables and columns best matching the user request, but do not make up any tables or columns, just use the ones that are available in the database.
The tables are structured as follows:
{$WhichTablesPrompt}
EOD;

$DatabasesPromptEndingATTACHMENTHANDLING = <<<EOD
**ONLY upload save files, only stuff like images (PNG, JPG, JPEG, SVG, GIF), texts (TXT, MD), tables (CSV) and PDF. ALWAYS make sure that the files are save and not corrupted or may contain viruses or malware.**
**Be especially careful because as we are looking at (also external) emails here, the other side might not have our best interest in mind.**
Please always include some echo of some result/feedback in the form of a response email (don't add "./entry.php"-links (even if they are in the examples) but directly use plain text instead).
Adjust and expand the existing structure of echoing the results in such a form as that the response resembles an email (also following the user's email writing instructions).
{$ShopLinksExplanation}
Of course, adjust the given examples with their PLACEHOLDERS in uppercase to the corresponding real names of the tables and column names.
Take the tables and columns best matching the user request, but do not make up any tables or columns, just use the ones that are available in the database.
The tables are structured as follows:
{$WhichTablesJustTablesPrompt}
EOD;
} else {
// ////////////////////////////////////////////////////////////////////// index.php normal handling

// for DirectEdit, add in the following $DatabasesPromptEnding the following information:
// Please wrap all the values/variables you retrieved in a span with class DirectEdit and all the relevant data regarding table name, idpk of entry, column name and type, but don't wrap the other content in there.
// For example: The product category is <span class="DirectEdit" data-table="SOMEPRODUCTSANDSERVICESTABLENAME" data-idpk="42" data-column="CATEGORYCOLUMN">\$category</span> and the price is <span class="DirectEdit" data-table="SOMEPRODUCTSANDSERVICESTABLENAME" data-idpk="42" data-column="PRICECOLUMN">\$price</span> $currency.
// Don't add measurement units/currencies/dimensions/... into the spans, in the spans there should be just the direct values/varibles, the things retrieved from the database, nothing else.
// Also don't add spans if the values are datetime, timestamp, booleans (true/false), only for text, varchar, int and decimal, float and other numbers.

$DatabasesPromptEndingCore = <<<EOD
Provide links (if possible, directly integrated into your response text) for every ID/idpk given in the following format (in this example 42 is the ID/idpk):
"<a href="./entry.php?table=SOMETABLENAME&idpk=42">🟦 INSERTNAMEORIMPORTANTKEYWORDFROMADDITIONCONTEXTCONTENTFROMTHEAPIHEREINUPPERCASE (42)</a>"
If you did not got a name, juste write "<a href="./entry.php?table=SOMETABLENAME&idpk=42">🟦 ENTRY 42</a>"

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


Of course, adjust the given examples with their PLACEHOLDERS in uppercase to the corresponding real names of the tables and column names.
Take the tables and columns best matching the user request, but do not make up any tables or columns, just use the ones that are available in the database.
The tables are structured as follows:
{$WhichTablesPrompt}
EOD;

$DatabasesPromptEndingATTACHMENTHANDLING = <<<EOD
**ONLY upload save files, only stuff like images (PNG, JPG, JPEG, SVG, GIF), texts (TXT, MD), tables (CSV) and PDF. ALWAYS make sure that the files are save and not corrupted or may contain viruses or malware.**
Please always include some echo of some result/feedback.

{$DatabasesPromptEndingCore}


Of course, adjust the given examples with their PLACEHOLDERS in uppercase to the corresponding real names of the tables and column names.
Take the tables and columns best matching the user request, but do not make up any tables or columns, just use the ones that are available in the database.
The tables structure is as follows:
{$WhichTablesJustTablesPrompt}
EOD;
}

$DatabasesPromptBeginning = <<<EOD
### You are a PHP DATABASES assistant.
Return a complete PHP snippet starting with "<?php" that uses the existing \$pdo connection. Never respond with raw SQL alone; always include preparing, executing and echoing the results.
**BUT**: Sometimes you don't have to interact with the database again, because the requiered information is already available in the context you got. In that case, just craft a PHP code that echoes the results directly,
for example: echo 'The name of this product is: PENCIL.<br><span style="opacity: 0.5;">(for <a href="./entry.php?table=SOMEPRODUCTSANDSERVICESTABLENAME&idpk=42">🟦 PENCIL (42)</a>)</span>';
EOD;



$DatabasesPromptCoreSELECT = <<<EOD
### Example:
\$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE IDPKCOLUMN = :idpk");
\$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
\$stmt->execute();
\$existingData = \$stmt->fetch(PDO::FETCH_ASSOC);

if (\$existingData) {
    echo "Product Name: " . htmlspecialchars(\$existingData['NAMECOLUMN']) . "<br>";
    echo "Short Description: " . htmlspecialchars(\$existingData['SHORTDESCRIPTIONCOLUMN']) . "<br>";
    echo ' <span style="opacity: 0.5;">(for <a href="./entry.php?table=SOMEPRODUCTSANDSERVICESTABLENAME&idpk=' . \$idpk . '">🟦 ' . strtoupper(htmlspecialchars(\$name)) . ' (' . \$idpk . ')</a>)</span>';
} else {
    echo "No product found with this idpk for the current admin.";
}
EOD;

$DatabasesPromptCoreINSERTINTO = <<<EOD
### Example:
\$stmt = \$pdo->prepare("INSERT INTO `SOMEPRODUCTSANDSERVICESTABLENAME` (NAMECOLUMN, SHORTDESCRIPTIONCOLUMN) VALUES (:name, :desc)");
\$stmt->bindParam(':name', \$name);
\$stmt->bindParam(':desc', \$desc);
\$stmt->execute();
\$lastId = \$pdo->lastInsertId();

// Echo feedback
echo 'Created new entry: <a href="./entry.php?table=SOMEPRODUCTSANDSERVICESTABLENAME&idpk=' . \$lastId . '">🟦 ' . strtoupper(htmlspecialchars(\$name)) . ' (' . \$lastId . ')</a>';
EOD;

$DatabasesPromptCoreUPDATE = <<<EOD
### Example:
// UPDATE example
\$stmt = \$pdo->prepare("UPDATE `SOMEPRODUCTSANDSERVICESTABLENAME` SET NAMECOLUMN = :name, SHORTDESCRIPTIONCOLUMN = :desc WHERE IDPKCOLUMN = :idpk");
\$stmt->bindParam(':name', \$name);
\$stmt->bindParam(':desc', \$desc);
\$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
\$stmt->execute();

// Echo feedback
echo 'Updated entry <a href="./entry.php?table=SOMEPRODUCTSANDSERVICESTABLENAME&idpk=' . \$idpk . '">🟦 ' . strtoupper(htmlspecialchars(\$name)) . ' (' . \$idpk . ')</a>';
EOD;

$DatabasesPromptCoreDELETE = <<<EOD
### Example:
\$stmt = \$pdo->prepare("DELETE FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE IDPKCOLUMN = :idpk");
\$stmt->bindParam(':idpk', \$idpk, PDO::PARAM_INT);
\$stmt->execute();

// Delete attachments associated with this entry
\$uploadDir = "./UPLOADS/SOMEPRODUCTSANDSERVICESTABLENAME/";
if (is_dir(\$uploadDir)) {
    \$files = glob(\$uploadDir . \$idpk . "_*.*"); // find all files starting with idpk_
    foreach (\$files as \$file) {
        if (is_file(\$file)) {
            unlink(\$file);
        }
    }
}

// Echo feedback
echo "Entry with idpk \$idpk has been deleted from table SOMEPRODUCTSANDSERVICESTABLENAME.";
EOD;

$DatabasesPromptCoreSEARCH = <<<EOD
### Search instructions
Perform the search in three steps:

1. Search using the raw input.
2. Correct possible spelling mistakes and search again (just skip if original input was already correct).
3. Search using synonyms, stems, or similar words.

Generate spelling corrections and similar terms using your own logic.

### Example:

// Step 1: Exact match search
\$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE NAMECOLUMN LIKE :search");
\$stmt->bindValue(':search', '%' . \$search . '%');
\$stmt->execute();
\$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);

if (!\$results) {
    // Step 2: Spelling-corrected variant
    \$corrected = '[[your spelling-corrected version of \$search here]]'; // e.g., via AI suggestion
    \$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE NAMECOLUMN LIKE :corrected");
    \$stmt->bindValue(':corrected', '%' . \$corrected . '%');
    \$stmt->execute();
    \$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!\$results) {
    // Step 3: Similar or related terms
    \$alternatives = ['[[alt1]]', '[[alt2]]', '[[alt3]]']; // e.g., AI-generated variants
    foreach (\$alternatives as \$alt) {
        \$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE NAMECOLUMN LIKE :alt");
        \$stmt->bindValue(':alt', '%' . \$alt . '%');
        \$stmt->execute();
        \$moreResults = \$stmt->fetchAll(PDO::FETCH_ASSOC);
        \$results = array_merge(\$results, \$moreResults);
    }
}

// Echo results
if (\$results) {
    foreach (\$results as \$row) {
        echo '<a href="./entry.php?table=SOMEPRODUCTSANDSERVICESTABLENAME&idpk=' . \$row['IDPKCOLUMN'] . '">🟦 ' . strtoupper(htmlspecialchars(\$row['NAMECOLUMN'])) . ' (' . \$row['IDPKCOLUMN'] . ')</a><br>';
    }
} else {
    echo "No results found for your search.";
}
EOD;

$DatabasesPromptCoreGETCONTEXT = <<<EOD
### Search instructions
Perform the search in three steps:

1. Search using the raw input.
2. Correct possible spelling mistakes and search again.
3. Search using synonyms, stems, or similar words.

Generate spelling corrections and similar terms using your own logic.
Sometimes the user asks you do perform searches for multiple entries or for multiple tables, in that case, just extend the logic and echo ALL relevant data from ALL relevant columns (tend towards adding more, more is better than less) for everything requested, for each entry.
(hint of what might be relevant: IDPKCOLUMN, NAMECOLUMN, PRICECOLUMN, TAXCOLUMN, ADDRESSDETAILSCOLUMN, DESCRIPTIONSCOLUMN, ... (and everything that might be useful for this and following tasks and other AI agents))

### Example:

// Step 1: Exact match search
\$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE NAMECOLUMN LIKE :search");
\$stmt->bindValue(':search', '%' . \$search . '%');
\$stmt->execute();
\$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);

if (!\$results) {
    // Step 2: Spelling-corrected variant
    \$corrected = '[[your spelling-corrected version of \$search here]]'; // e.g., via AI suggestion
    \$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE NAMECOLUMN LIKE :corrected");
    \$stmt->bindValue(':corrected', '%' . \$corrected . '%');
    \$stmt->execute();
    \$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!\$results) {
    // Step 3: Similar or related terms
    \$alternatives = ['[[alt1]]', '[[alt2]]', '[[alt3]]']; // e.g., AI-generated variants
    foreach (\$alternatives as \$alt) {
        \$stmt = \$pdo->prepare("SELECT * FROM `SOMEPRODUCTSANDSERVICESTABLENAME` WHERE NAMECOLUMN LIKE :alt");
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
        'IDPKCOLUMN',
        'NAMECOLUMN',
        'QUANTITYCOLUMN',
        'NETPRICETOTALCOLUMN',
        'TAXESTOTALCOLUMN',
        'CURRENCYCODECOLUMN',
        'STATECOLUMN',
        'COMMENTSNOTESSPECIALREQUESTSCOLUMN'
    ];
    foreach (\$results as \$row) {
        foreach (\$columns as \$col) {
            if (isset(\$row[\$col])) {
                echo \$col . ': ' . \$row[\$col] . "<br>";
            }
        }
        echo '<a href="./entry.php?table=SOMEPRODUCTSANDSERVICESTABLENAME&idpk=' . \$row['IDPKCOLUMN'] . '">🟦 ' . strtoupper(htmlspecialchars(\$row['NAMECOLUMN'])) . ' (' . \$row['IDPKCOLUMN'] . ')</a><br><br>';
    }
} else {
    echo "No context found for your search.";
}
// If needed, just copy this code a second time, change the variable names and thus adapt it for the other entry we may search for.
EOD;

$DatabasesPromptCoreATTACHMENTHANDLING = <<<EOD
Files for each table reside on the STARGATE server in "./UPLOADS/<table>/" and follow the naming pattern "<IDPKCOLUMN>_<counter>.<extension>" (counter starts at 0).
The extension can we an image extension, text, CSV, PDF or nearly anythign else too. Use "FileViewer.php" to show a file and "entry.php" to display the entry itself.

When the user provides one or more entries, return PHP code that:
1. defines an array with all requested ['table' => '...', 'IDPKCOLUMN' => ...] pairs,
2. scans the corresponding "./UPLOADS/<table>/" directories for files matching "<IDPKCOLUMN>_*.*",
3. echoes one 🔵 FileViewer-link per file, then a 🟦 entry-link with opacity 0.5, then a blank line before continuing with the next entry.

### EXAMPLE
\$entries = [
    ['table' => 'SOMEPRODUCTSANDSERVICESTABLENAME', 'IDPKCOLUMN' => 10],
    ['table' => 'SOMEPRODUCTSANDSERVICESTABLENAME', 'IDPKCOLUMN' => 15],
];

foreach (\$entries as \$e) {
    \$table = \$e['table'];
    \$idpk  = (int)\$e['IDPKCOLUMN'];
    \$dir   = './UPLOADS/' . \$table . '/';
    \$files = is_dir(\$dir) ? glob(\$dir . \$idpk . '_*.*') : [];
    natsort(\$files);
    foreach (\$files as \$path) {
        \$file = basename(\$path);
        echo '<a href="./FileViewer.php?table=' . \$table . '&idpk=' . \$idpk . '&file=' . rawurlencode(\$file) . '">🔵 ' . strtoupper(\$file) . '</a><br>';
    }
    echo '<span style="opacity: 0.5;"><a href="./entry.php?table=' . \$table . '&idpk=' . \$idpk . '">🟦 ' . strtoupper(\$table) . ' (' . \$idpk . ')</a></span><br><br>';
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

Be **very careful** and only delete the right entries, in case of doubt, don't write SQL code and instead return just some echo code where you ask for clarifications.
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
### You are an databases attachments handling assistant. Only answer with a code snippet, no other text.


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






$BuyingAndSellingCombinedInformationPrompt = <<<EOD
Return a complete PHP snippet starting with "<?php" (that, if requiered, uses the existing \$pdo connection. Never respond with raw SQL alone; always include preparing, executing and echoing the results).


### FIGURE OUT WHAT TO DO
Look at the DATABASESCONTEXT and other context and try to figure out whether or not the corresponding entries for this order already exist (carts, transactions, ...).
If there are already corresponding entries: link them.
If there aren't any entries yet: create them.
(Please mind that carts and transactions are often in seperate tables, so you have to create for both (but for linking, just linking the cart should be sufficient).)
Please fill out in the databases entries **as many fields as possible** with the information you got. The examples are only short examples, in reality you might be able to fill out even more fields.


### EXAMPLE FOR LINKING THEM

echo 'Relevant cart entry: <a href="./entry.php?table=SOMECARTSTABLENAME&idpk=42">🟦 ENTRY 42</a>';


### EXAMPLE FOR CREATING THEM

// Insert cart
\$now = date('Y-m-d H:i:s'); // to make sure everything has the same timestamp
\$stmt = \$pdo->prepare("INSERT INTO `SOMECARTSTABLENAME` (TIMESTAMPCREATIONCOLUMN, IDPKOFSUPPLIERORCUSTOMERCOLUMN, DELIVERYTYPECOLUMN) VALUES (:now, :supplier, :delivery)");
\$stmt->execute([
    ':now' => \$now,
    ':supplier' => 1,
    ':delivery' => 'standard'
]);
\$cartId = \$pdo->lastInsertId();

// Insert corresponding transactions
\$stmt = \$pdo->prepare("INSERT INTO `SOMETRANSACTIONSTABLENAME` (TIMESTAMPCREATIONCOLUMN, IDPKOFSUPPLIERORCUSTOMERCOLUMN, IDPKOFCARTCOLUMN, IDPKOFPRODUCTORSERVICECOLUMN, QUANTITYCOLUMN) VALUES (:now, :supplier, :cart, :product, :quantity)");

\$stmt->execute([':now' => \$now, ':supplier' => 1, ':cart' => \$cartId, ':product' => 1, ':quantity' => 1]);
\$transactionId1 = \$pdo->lastInsertId();

\$stmt->execute([':now' => \$now, ':supplier' => 1, ':cart' => \$cartId, ':product' => 2, ':quantity' => 2]);
\$transactionId2 = \$pdo->lastInsertId();

\$stmt->execute([':now' => \$now, ':supplier' => 1, ':cart' => \$cartId, ':product' => 3, ':quantity' => 3]);
\$transactionId3 = \$pdo->lastInsertId();

// Echo feedback
echo 'Created new cart entry: <a href="./entry.php?table=SOMECARTSTABLENAME&idpk=' . \$cartId . '">🟦 ENTRY ' . \$cartId . '</a>';
echo '<br><span style="opacity: 0.5;">(corresponding transactions: <a href="./entry.php?table=SOMETRANSACTIONSTABLENAME&idpk=' . \$transactionId1 . '">🟦 ENTRY ' . \$transactionId1 . '</a>, <a href="./entry.php?table=SOMETRANSACTIONSTABLENAME&idpk=' . \$transactionId2 . '">🟦 ENTRY ' . \$transactionId2 . '</a>, <a href="./entry.php?table=SOMETRANSACTIONSTABLENAME&idpk=' . \$transactionId3 . '">🟦 ENTRY ' . \$transactionId3 . '</a>)</span>';


### GENERAL DATABASE INFORMATION
{$DatabasesPromptEnding}


### DEFAULT ASSUMPTIONS FOR DATABASE ENTRY
(can also be overwritten by the general notes from the users company, so only act upon these assumptions if the user isn't telling you otherwise)
- no goods arrived yet
- order is unpaid
- be careful with changing and adding master data such as suppliers/customers and products/services, try to find these informations in the database context, but **don't** add them if they are missing, unless the user explicitly says otherwise
EOD;

// $BuyingAndSellingCombinedInformationPrompt = <<<EOD
// You are writing some PHP code ready for execution, you are no longer in planning but in execution mode now.
// You have to make some decisions now and then **already** execute them in this exact same step.
// (In the other, following INSTRUCTIONSCONTEXT, there might also be a PDF action or something like that (but maybe also not), in that case, please make sure that you write **exactly the same** stuff in the PDF as you have here (for example: same products, quantities, prices, ...).)
// At the end there are also some general notes given by the users company directly, these can overwrite / are more important than the default assumptions for database entry made in the following.
// Return a complete PHP snippet starting with "<?php" (that, if requiered, uses the existing \$pdo connection. Never respond with raw SQL alone; always include preparing, executing and echoing the results).
// 
// 
// ### FIGURE OUT WHAT TO DO
// Look at the DATABASESCONTEXT and other context and try to figure out whether or not the corresponding entries for this order already exist (carts, transactions, ...).
// Be careful (especially if in the INSTRUCTIONSCONTEXT there is a corresponding PDF action) and make sure that you **don't create duplicates** of already existing database entries.
// In case of doubt, just craft a PHP code that echoes the corresponding question to make sure,
// for example:
// echo 'Before I continue with your request, I just wanted to ask, if I should create new database entries for this or if there are already some existing ones, which I should use?';
// 
// If there are already corresponding entries: link them.
// If there aren't any entries yet: create them.
// (Please mind that carts and transactions are often in seperate tables, so you have to create for both (but for linking, just linking the cart should be sufficient).)
// Please fill out in the databases entries **as many fields as possible** with the information you got. The examples are only short examples, in reality you might be able to fill out even more fields.
// 
// 
// ### EXAMPLE FOR LINKING THEM
// 
// echo 'Relevant cart entry: <a href="./entry.php?table=SOMECARTSTABLENAME&idpk=42">🟦 ENTRY 42</a>';
// 
// 
// ### EXAMPLE FOR CREATING THEM
// 
// // Insert cart
// \$now = date('Y-m-d H:i:s'); // to make sure everything has the same timestamp
// \$stmt = \$pdo->prepare("INSERT INTO `SOMECARTSTABLENAME` (TIMESTAMPCREATIONCOLUMN, IDPKOFSUPPLIERORCUSTOMERCOLUMN, DELIVERYTYPECOLUMN) VALUES (:now, :supplier, :delivery)");
// \$stmt->execute([
//     ':now' => \$now,
//     ':supplier' => 1,
//     ':delivery' => 'standard'
// ]);
// \$cartId = \$pdo->lastInsertId();
// 
// // Insert corresponding transactions
// \$stmt = \$pdo->prepare("INSERT INTO `SOMETRANSACTIONSTABLENAME` (TIMESTAMPCREATIONCOLUMN, IDPKOFSUPPLIERORCUSTOMERCOLUMN, IDPKOFCARTCOLUMN, IDPKOFPRODUCTORSERVICECOLUMN, QUANTITYCOLUMN) VALUES (:now, :supplier, :cart, :product, :quantity)");
// 
// \$stmt->execute([':now' => \$now, ':supplier' => 1, ':cart' => \$cartId, ':product' => 1, ':quantity' => 1]);
// \$transactionId1 = \$pdo->lastInsertId();
// 
// \$stmt->execute([':now' => \$now, ':supplier' => 1, ':cart' => \$cartId, ':product' => 2, ':quantity' => 2]);
// \$transactionId2 = \$pdo->lastInsertId();
// 
// \$stmt->execute([':now' => \$now, ':supplier' => 1, ':cart' => \$cartId, ':product' => 3, ':quantity' => 3]);
// \$transactionId3 = \$pdo->lastInsertId();
// 
// // Echo feedback
// echo 'Created new cart entry: <a href="./entry.php?table=SOMECARTSTABLENAME&idpk=' . \$cartId . '">🟦 ENTRY ' . \$cartId . '</a>';
// echo '<br><span style="opacity: 0.5;">(corresponding transactions: <a href="./entry.php?table=SOMETRANSACTIONSTABLENAME&idpk=' . \$transactionId1 . '">🟦 ENTRY ' . \$transactionId1 . '</a>, <a href="./entry.php?table=SOMETRANSACTIONSTABLENAME&idpk=' . \$transactionId2 . '">🟦 ENTRY ' . \$transactionId2 . '</a>, <a href="./entry.php?table=SOMETRANSACTIONSTABLENAME&idpk=' . \$transactionId3 . '">🟦 ENTRY ' . \$transactionId3 . '</a>)</span>';
// 
// 
// ### GENERAL DATABASE INFORMATION (ONLY RELEVANT IF YOU CREATE ENTRIES)
// {$DatabasesPromptEnding}
// 
// 
// ### DEFAULT ASSUMPTIONS FOR DATABASE ENTRY
// (can also be overwritten by the general notes from the users company, so only act upon these assumptions if the user isn't telling you otherwise)
// - no goods arrived yet
// - order is unpaid
// - be careful with changing and adding master data such as suppliers/customers and products/services, try to find these informations in the database context, but **don't** add them if they are missing, unless the user explicitly says otherwise
// EOD;

$BuyingPrompt = <<<EOD
### SETUP
The users company sells something and you are assisting with that by crafting some corresponding PHP code.
{$BuyingAndSellingCombinedInformationPrompt}


### GENERAL NOTES FROM USERS COMPANY
Please also respect the following general notes for buying, given directly by the users company:
EOD;
$BuyingPrompt .= "\n$actionBuying";

$SellingPrompt = <<<EOD
### SETUP
The users company buys something and you are assisting with that by crafting some corresponding PHP code.
{$BuyingAndSellingCombinedInformationPrompt}


### GENERAL NOTES FROM USERS COMPANY
Please also respect the following general notes for selling, given directly by the users company:
EOD;
$SellingPrompt .= "\n$actionSelling";






$MarketingTextingPrompt = <<<EOD
### You are assisting with creative writing for advertisement texts, products descriptions or other marketing stuff.
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
$emailWritingInstructionsSuffix = '';
if ($sanitizedEmailWritingInstructions !== '') {
    $emailWritingInstructionsSuffix = "\n\nPlease also respect the user's email writing instructions, which are:\n{$sanitizedEmailWritingInstructions}";
}

$EmailWritingInstructionsPrompt = <<<EOD
### You are an email assistant. When asked to compose an email, respond with only a single mailto link, no other text.
(Note: if you are also creating PDFs, they will be automatically attached to the email.)
The link must include:
- `To` (required)
- `Cc` (optional)
- `Subject`
- `Body`
URL-encode all parameters. Please fill in the values you are provided with (but do not make up email addresses) and write the email body and subject freely based on that.

Example:
mailto:alice@example.com?cc=bob@example.com&subject=Meeting%20Tomorrow&body=Hi%20Alice%2C%0ASee%20you%20at%209%20AM.
EOD;
$EmailWritingInstructionsPrompt .= $emailWritingInstructionsSuffix;

$HelpPrompt = <<<EOD
### You are an assistant to help the using contacting TRAMANN TNX API, the company behind this system we are currently operating in.
When asked to compose an email, respond with only a single mailto link, no other text.

The link must include:
- `To` (required, value: hi@tnxapi.com)
- `Cc` (optional)
- `Subject`
- `Body`
URL-encode all parameters. Please fill in the values you are provided with (but do not make up email addresses) and write the email body and subject freely based on that.

Example:
mailto:hi@tnxapi.com?cc=bob@example.com&subject=Need%20Help%20Adding%20Entries&body=Hi%20TNX%20API%20Team%2C%0AI%27m%20trying%20to%20add%20new%20entries%20using%20your%20system%20to%20import%20our%20data%2C%20but%20it%20breaks%20and%20I%20can%27t%20see%20what%20went%20wrong.%20Could%20you%20help%3F
EOD;
$HelpPrompt .= $emailWritingInstructionsSuffix;

$FeedbackPrompt = <<<EOD
### You are an assistant to help giving feedback to TRAMANN TNX API, the company behind this system we are currently operating in.
When asked to compose an email, respond with only a single mailto link, no other text.
The link must include:
- `To` (required, value: hi@tnxapi.com)
- `Cc` (optional)
- `Subject`
- `Body`
URL-encode all parameters. Please fill in the values you are provided with (but do not make up email addresses) and write the email body and subject freely based on that.

Example:
mailto:hi@tnxapi.com?cc=bob@example.com&subject=Feedback%20on%20Bulk%20Editing&body=Hi%20TNX%20API%20Team%2C%0AWe%20need%20to%20adjust%20many%20entries%20at%20once%20to%20keep%20our%20catalog%20up%20to%20date%2C%20but%20editing%20item%20by%20item%20is%20hard%20and%20slow.
EOD;
$FeedbackPrompt .= $emailWritingInstructionsSuffix;




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

$PDFPromptBeginningGeneralHeadTableExplanations = <<<EOD
At the top, add a table with three columns:
- Left column: the transaction partner's name and address (if you shouldn't have values, jsut write "-" for each missing one),
- Middle column: the user's company name and address,
- Right column: the details.
The middle column and the right column are already filled out correctly, so please keep the values in this part exactly as they are in the example down below, don't remove or add anything
(except for the number ("$randomSeriesCode"), if you should got another number, another ID/idpk/... from the DATABASESCONTEXT which fits better, use that, but in case of doubt, just leave "$randomSeriesCode" as it is).
Column headers: the transaction partner's company name, the user's company name, details.
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

$PDFPromptBeginningGeneralHeadTableExplanations

Then include a table listing the products and services sold, with prices and quantities.
At the bottom, add banking information and any additional invoice notes.
$companyPaymentDetailsNotes

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
| IBAN: $iban                      | $companyPaymentDetailsInTable             |
| Currency: $currency              |                                           |

$invoiceNote
EOD;


$PDFPromptPurchase = <<<EOD
$PDFPromptBeginning

$PDFPromptBeginningGeneralHeadTableExplanations

Then include a table listing the products and services the user wants to buy from the other company and which quantities.
You can also include expected unit prices and totals, but if you don't have any information regarding that or if the user asks you to don't do this, just don't add these two columns and the correspoding first line right below the table.
The delivery method is "standard", unless the user says otherwise.


Example:

# Purchase

## Overview
| **COMPANYNAME**                             | **$company**                                  | **Purchase Details**                    |
|---------------------------------------------|-----------------------------------------------|-----------------------------------------|
| VAT ID: VATID                               | VAT ID: $vatID                                | **Purchase No.:** $randomSeriesCode     |
| HOUSENUMBER STREET                          | $houseNumber $street                          | **Date:** $currentDate                  |
| ZIPCODE CITY                                | $zip $city                                    |                                         |
| COUNTRY                                     | $country                                      |                                         |

## Products and Services
| Product Name / Description       | Quantity | Expected Unit Price | Expected Total      |
|----------------------------------|----------|---------------------|---------------------|
| SOMEPRODUCTORSERVICE             | 2        | $currency 5,000.00  | $currency 10,000.00 |
| ANOTHERONE                       | 1        | $currency 55.00     | $currency 55.00     |
| YETANOTHERONE                    | 2        | $currency 20.00     | $currency 40.00     |
| MAYBEALSOPACKAGINGANDSHIPPINGTOO | 1        | $currency 800.00    | $currency 1,200.00  |
**Expected Total ($currency): $currency 6,200.00**
**Delivery method: standard**
EOD;


$PDFPromptOffer = <<<EOD
$PDFPromptBeginning

$PDFPromptBeginningGeneralHeadTableExplanations

Then include a table listing the products and services offered, with prices and quantities.
For the availability note, the standard is: "All others are available from warehouse $city.", unless you got other information from database or user.
$companyPaymentDetailsNotes

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
**Availability note:** YETANOTHERONE is available within two weeks from warehouse SOMECITY, all others are available from warehouse $city."

## Payment Information
| IBAN           | Currency  | Payment Terms                      |
|----------------|-----------|------------------------------------|
| $iban          | $currency | $companyPaymentDetailsInTable      |

$invoiceNote
EOD;


$PDFPromptDeliveryReceipt = <<<EOD
$PDFPromptBeginning

$PDFPromptBeginningGeneralHeadTableExplanations

Then include a table listing the products and services delivered and their quantities.
The delivery method is "standard", unless the user says otherwise.
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
// /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// even more preparations
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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

    // Normalize the uploads so both single and multiple files work the same
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        // Determine values for either a single file or an array of files
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }

        // 1) Original filename & temporary location
        $originalName = is_array($files['name']) ? basename($files['name'][$i]) : basename($files['name']);
        $tmpName      = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $sizeBytes    = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $typeField    = is_array($files['type']) ? $files['type'][$i] : ($files['type'] ?? '');
        $mimeType     = mime_content_type($tmpName) ?: ($typeField ?: 'application/octet-stream');

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

        // Build public URL dynamically so attachments work on any host
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $publicUrl = $scheme . '://' . $_SERVER['HTTP_HOST']
                     . $basePath . '/tmp/'
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

// ////////////////////////////////////////////////// EmailBot never gets context, only the command
if (!empty($MostRecentLogs) && !$isEmailBot) {
    $StructurePartsExplanation = 'Respond strictly with just a JSON object consisting of three parts: "DUSCUSSIONCONTEXT", "INSTRUCTIONSCONTEXT" and "DATABASESCONTEXT".';
    $DiscussionContextExplanation = "## DUSCUSSIONCONTEXT\nPlease summarize important information from the recent discussion context given down below. Don't write/repeat the user command itself here, the second AI will have access to the users command, no need to repeat it, ONLY summarize the recent discussion context here. If there shouldn't be anything relevant, just leave this part of the JSON empty.\n\n\n";
$ExampleStructureOutputExplanation = <<<EOD
{
  "DUSCUSSIONCONTEXT": "Peter works at ACME LLC",
  "INSTRUCTIONSCONTEXT": [
    "databasesupdate",
    "email"
  ],
  "DATABASESCONTEXT": "// Step 1: Exact match search
\$stmt = \$pdo->prepare("SELECT * FROM `SOMESUPPLIERSANDORORCUSTOMERSTABLENAME` WHERE COMPANYNAMECOLUMN LIKE :search");
\$stmt->bindValue(':search', '%' . \$search . '%');
\$stmt->execute();"
... (Add the relevant code here, in this case we should for example search for ACME LLC and the last order where tables where sold.)
}
EOD;
} else {
    // $DiscussionContextExplanation = "This is the users first chat message, therefore there is no discussion context yet, so please just leave it empty entirely, do NOT insert anythign is this part of the JSON. Also don't write the user command itself here, the second AI will have access to the users command, no need to repeat it, just leave this one all empty.";
    $StructurePartsExplanation = 'Respond strictly with just a JSON object consisting of two parts: "INSTRUCTIONSCONTEXT" and "DATABASESCONTEXT".';
    // If it's EmailBot, add the special rule
    if ($isEmailBot) {
        $StructurePartsExplanation .= ' Always end the "INSTRUCTIONSCONTEXT" with "email".';
    }
    $DiscussionContextExplanation = "";
$ExampleStructureOutputExplanation = <<<EOD
{
  "INSTRUCTIONSCONTEXT": [
    "databasesupdate",
    "email"
  ],
  "DATABASESCONTEXT": "// Step 1: Exact match search
\$stmt = \$pdo->prepare("SELECT * FROM `SOMESUPPLIERSANDORORCUSTOMERSTABLENAME` WHERE COMPANYNAMECOLUMN LIKE :search");
\$stmt->bindValue(':search', '%' . \$search . '%');
\$stmt->execute();"
... (Add the relevant code here, in this case we should for example search for ACME LLC and the last order where tables where sold.)
}
EOD;
}

// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////// IMPORTANT NOTE: the following action types also appear in workflows.php, changes here have to also been made there
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// EmailBot options
if ($isEmailBot) {
$LabelOptionsCSV = 'spam, summary, pdf, pdfinvoice, pdfoffer, pdfdeliveryreceipt, databasesselect, databasesinsertinto, databasesupdate, databasesdelete, databasessearch, databasesgetcontext, databasesattachmenthandling, buying, selling, email';
$LabelMeaningsBlock = <<<TXT
Use "spam" if you consider the email to be irrelevant (in that case, don't put other labels in the list), mind that many emails are just spam,
"summary" for quickly communicating main parts of an just informational email (here also no other labels then please),
"pdf" for creating an attached PDF document in general (if none of the following, more specific PDF solutions should apply),
"pdfinvoice" attached PDF document for invoices,
"pdfoffer" for offers/quotations,
"databasesdelete" for deleting entries,
"databasessearch" for searching,
"databasesgetcontext" for searching and then getting additional information too,
"databasesattachmenthandling" for adding, changing or deleting attachments of database entries (such as product pictures, manuals, images / texts / other files which can be attached/linked to an entry, ...)
"buying" if the user / the users company buys something (also for example if he just uploaded an invoice as attachment),
"selling" if he sells something,
and at the end, always include "email" to craft the response.
Sometimes "email" alone is already enough, for example if they are just chatting, otherwise include other labels first and then "email" at the end to communicate back the results of previous actions.
TXT;
} else {
// normal options
$LabelOptionsCSV = 'math, location, help, feedback, chart, table, code, email, pdf, pdfinvoice, pdfpurchase, pdfoffer, pdfdeliveryreceipt, incominggoodsinspection, pdfreport, pdfcontract, pdflegaldocument, databasesselect, databasesinsertinto, databasesupdate, databasesdelete, databasessearch, databasesgetcontext, databasesattachmenthandling, buying, selling, marketingtexting, chat';
$LabelMeaningsBlock = <<<TXT
Use "math" for calculations,
"location" for map requests,
"help" to get help with this system,
"feedback" to give feedback about this system,
"chart" for data visualization,
"table" for tables,
"code" for code blocks,
"email" for communication tasks (sending),
"pdf" for creating a downloadable PDF document in general (if none of the following, more specific PDF solutions should apply),
"pdfinvoice" downloadable PDF document for invoices,
"pdfpurchase" if the users wants to buy something and requests a document for that,
"pdfoffer" for offers/quotations,
"pdfdeliveryreceipt" for delivery receipts,
"incominggoodsinspection" if the user uploaded a delivery receipt of products he bought as attachment,
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
"buying" if the user / the users company buys something (also for example if he just uploaded an invoice as attachment),
"selling" if he sells something,
"marketingtexting" for writing advertisement texts, product descriptions and other marketing stuff,
and "chat" for general conversation or explanations.
TXT;
}

// common system-prompt builder
$PlanningSystemPrompt = <<<EOD
# Your job
You are an AI that plans and provides the context for the following main AI, that will actually work on the users task.
**Don't** try to solve the task / answer the questions already, you should just prepare for the following AI.


# Structure
{$StructurePartsExplanation}

{$DiscussionContextExplanation}## INSTRUCTIONSCONTEXT
Respond with a comma-separated list of labels, which could be useful for later working on the task
(based on these keyword, we will provide the second AI with correspoding, additional information).
If you think, that much is needed, make the list longer, but also only one or none can be fine sometimes.
Please already list the labels in the correct order of appearance, matching the user command,
so if he asks for for example three things A, B, C, the corresponding labels should be in the same order.

### Label options
**Only** use the following label options for your list:
{$LabelOptionsCSV}

### Label option meanings
{$LabelMeaningsBlock}

Please mind that all database actions can also create charts and tables, so in case of doubt, tend to using them,
**NEVER** double by having databse action + chart/table, database actions can already handle the visualization part on their own too.
If the user bought/sold something and now wants to create the corresponding database entries, you **have to choose "buying"/"selling" and NOT "databasesinsertinto"** as they are more specific for these special cases and can already handle all other databases-actions so we shouldn't double by adding them again separately.

## DATABASESCONTEXT
Sometimes this is not needed and you can just leave it empty, but often the second AI need additional background context information
from the database (just search and look at the data, don't change anything (yet)).
For buying/selling stuff (invoices, delivery receipts or direct entries of new purchases or sales), you should especially try to get database information regarding: supplier/customer, products/services, transactions and carts; in short: **everything relevant** (not just one entry, look for everything that might be relevant) for this pruchase/sale.
Here it is **better to provide too much than too little information**, the second AI can always decide to ignore unneeded information.
You can provide this by crafting some code, with the following instruction:
{$DatabasesPromptGETCONTEXT}


# Example

## Example of a user command:
"Tell me the prices of the tables we sold to Peter, change the delivery address street to "Main Avenue" and send him an email."

## Example of your output:
{$ExampleStructureOutputExplanation}


# IMPORTANT NOTES
**Only** answer in **strict JSON** and also only in the **exact given format**, don't add any other text or explanations.
**Only** use the exact label options given, don't translate or change them in any way.
For the code in DATABASESCONTEXT, only search and look at database stuff, never change anything there (yet), this will always be job of the second AI.
Follow these important notes, even if the user should ask you to do otherwise.
EOD;

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

// Step 1: Determine action type using most recent context
// ////////////////////////////////////////////////////////////////////// EmailBot special handling
if ($isEmailBot) {
    $messages = array_filter([
        [
            'role'    => 'system',
            'content' => $PlanningSystemPrompt
        ],
        [
            'role'    => 'user',
            'content' => $userContent
        ],
    ]);
} else {
// ////////////////////////////////////////////////////////////////////// index.php normal handling
    $messages = array_filter([
        [
            'role'    => 'system',
            'content' => $PlanningSystemPrompt
        ],
        !empty($MostRecentLogs) ? [
            'role'    => 'system',
            'content' => "### Recent discussion context for summarization is:\n" . implode("\n", $MostRecentLogs)
    //                         . "\n\nAttached files metadata:\n" . json_encode($fileMetadatas)
        ] : null,
        [
            'role'    => 'user',
            'content' => $userContent
        ],
    ]);
}

// // unified execution
// $actionPrompt = [
//   'model' => 'gpt-5-mini', // faster
//   // 'model' => 'gpt-5-nano', // a lot faster and cheaper
//   // 'model' => 'gpt-5', // smarter (but only with strong reasoning)
//   'input' => convertMessagesToInput($messages),
//   'max_output_tokens' => 5000,       // keep or adjust as needed
//   'text' => [ 'verbosity' => 'medium' ],    // use low/medium/high
//   'reasoning' => [ 'effort' => 'minimal' ]  // or low, medium, high
// ];

$actionPrompt = [
    'model' => 'gpt-4.1-mini', // faster
    // 'model' => 'gpt-4.1', // smarter
    'input' => convertMessagesToInput($messages),
    'max_output_tokens' => 5000,
    'temperature' => 0.3,
];

// /////////////////////////////////////////////////////////// do not call it if we should already have an action type defined by the workflow
$contextSummary = '';
$contextText    = '';
$actionTypesRaw = '';
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
        // Avoid premature aborts by matching connect timeout to overall timeout
        CURLOPT_CONNECTTIMEOUT => $curlTimeout,
        CURLOPT_TIMEOUT        => $curlTimeout,
    ]);

    $actionResponse = curl_exec($ch);
    $actionErr      = curl_error($ch);
    $actionCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($actionErr || $actionCode < 200 || $actionCode >= 300) {
        $errorMsg = 'We are very sorry, but there was an error: ' . ($actionErr ?: 'HTTP status ' . $actionCode);
        $responses[] = [
            'label'   => 'CHAT',
            'message' => $errorMsg,
        ];
        $results['chat'] = $errorMsg;
    } else {
        $actionDecoded  = json_decode($actionResponse, true);
        if (!is_array($actionDecoded)) {
            $errorMsg = 'We are very sorry, but there was an error processing the AI response.';
            $responses[] = [
                'label'   => 'CHAT',
                'message' => $errorMsg,
            ];
            $results['chat'] = $errorMsg;
        } else {
            $planningText = stripCodeFence(extractTextFromResponse($actionDecoded));
            $planningJson = json_decode($planningText, true);

            // Ensure "DUSCUSSIONCONTEXT" key exists to keep structure robust
            if (!isset($planningJson['DUSCUSSIONCONTEXT'])) {
                $planningJson['DUSCUSSIONCONTEXT'] = '';
            }

            $contextSummary = trim($planningJson['DUSCUSSIONCONTEXT'] ?? '');
            $dbContextCode  = trim($planningJson['DATABASESCONTEXT'] ?? '');
            $instructionsRaw = $planningJson['INSTRUCTIONSCONTEXT'] ?? '';

            // For EmailBot, ensure the INSTRUCTIONSCONTEXT list ends with "email"
            if ($isEmailBot) {
                if (is_array($instructionsRaw)) {
                    // Remove any existing occurrences of "email" and append once at the end
                    $instructionsRaw = array_values(
                        array_filter($instructionsRaw, fn($lbl) => strtolower($lbl) !== 'email')
                    );
                    $instructionsRaw[] = 'email';
                } else {
                    // Convert string to array, clean duplicates, and append "email"
                    $parts = array_map('trim', explode(',', (string)$instructionsRaw));
                    $parts = array_values(
                        array_filter($parts, fn($lbl) => $lbl !== '' && strtolower($lbl) !== 'email')
                    );
                    $parts[] = 'email';
                    $instructionsRaw = $parts;
                }
            }

            if (is_array($instructionsRaw)) {
                $actionTypesRaw = strtolower(implode(', ', $instructionsRaw));
                $instructionsFormatted = implode(', ', $instructionsRaw);
            } else {
                $actionTypesRaw = strtolower((string)$instructionsRaw);
                $instructionsFormatted = (string)$instructionsRaw;
            }

            if ($contextSummary !== '') {
                $responses[] = [
                    'label'   => 'CONSOLE_LOG_ONLY',
                    'message' => '⟪DISCUSSION CONTEXT⟫ ' . $contextSummary,
                ];
            }

            if ($instructionsFormatted !== '') {
                $responses[] = [
                    'label'   => 'CONSOLE_LOG_ONLY',
                    'message' => '⟪INSTRUCTIONS CONTEXT⟫ ' . $instructionsFormatted,
                ];
            }

            if ($dbContextCode !== '') {
                $responses[] = [
                    'label'   => 'CONSOLE_LOG_ONLY',
                    'message' => '⟪DATABASES CONTEXT RAW CODE⟫ ' . $dbContextCode,
                ];

                $payload = [
                    'APIKey'       => $TRAMANNAPIAPIKey,
                    'MakeLogEntry' => "1",
                    'message'      => $dbContextCode,
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
                    CURLOPT_TIMEOUT        => $curlTimeout,
                ]);
                $apiResult   = curl_exec($chDb);
                $apiErr      = curl_error($chDb);
                $apiHttpCode = curl_getinfo($chDb, CURLINFO_HTTP_CODE);
                curl_close($chDb);

                if ($apiErr || $apiHttpCode < 200 || $apiHttpCode >= 300) {
                    $contextText = 'We are very sorry, but there was an error connecting to TRAMANN API: ' . ($apiErr ?: 'HTTP ' . $apiHttpCode);
                } else {
                    $decodedMessage = json_decode($apiResult, true);
                    $contextText    = trim($decodedMessage['message'] ?? '');
                    // Convert escaped newline sequences to actual line breaks
                    $contextText    = str_replace(['\\r\\n', '\\r', '\\n'], PHP_EOL, $contextText);
                }

                if ($contextText !== '') {
                    $responses[] = [
                        'label'   => 'CONSOLE_LOG_ONLY',
                        'message' => '⟪DATABASES CONTEXT⟫ ' . $contextText,
                    ];
                }
            }
        }
    }
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

// Ensure buying/selling labels accompany certain actions (but still making sure each label appears only once)
$labelDependencies = [
    'pdfinvoice' => 'selling',
    'pdfpurchase' => 'buying',
    'pdfdeliveryreceipt' => 'selling',
    'incominggoodsinspection' => 'buying',
];

$finalTypes = [];
$finalInstructions = [];
$addedLabels = [];
foreach ($actionTypes as $i => $type) {
    if (isset($labelDependencies[$type])) {
        $dependency = $labelDependencies[$type];
        if (!isset($addedLabels[$dependency])) {
            $finalTypes[] = $dependency;
            $finalInstructions[] = '';
            $addedLabels[$dependency] = true;
        }
    }
    if (!isset($addedLabels[$type])) {
        $finalTypes[] = $type;
        $finalInstructions[] = $actionInstructions[$i];
        $addedLabels[$type] = true;
    }
}

// // Communicate the final action plan to the frontend for debugging
$responses[] = [
    'label'   => 'CONSOLE_LOG_ONLY',
    'message' => '⟪ACTION PLAN⟫ ' . implode(', ', $actionPlanListed)
];

// Step 2: Handle the action with full context
$results = [];






// ////////////////////////////////////////////////////////////////////// Catch spam for EmailBot.php
if (in_array('spam', $actionTypes, true)) {
    $responses[] = [
        'label'   => 'SPAM',
        'message' => 'This email has been classified as spam and or or irrelevant.'
    ];

    ob_clean(); // Clean all previous output
    echo json_encode($responses, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}









// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// main query
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$dbActionTypes = ['databasesselect', 'databasesinsertinto', 'databasesupdate', 'databasesdelete', 'databasessearch', 'databasesgetcontext', 'databasesattachmenthandling', 'buying', 'selling'];

// Actions that require more complex reasoning
$ComplextActionsList = ['buying', 'selling'];

// Prepare label list for second call
$labelsForOutput = array_filter($actionTypes, fn($t) => $t && strtolower($t) !== 'false');
if (empty($labelsForOutput)) {
    $labelsForOutput = ['chat'];
}
$labelsLine = implode(', ', array_map('strtoupper', $labelsForOutput));
$exampleLines = [];
foreach ($labelsForOutput as $lbl) {
    $exampleLines[] = '    "' . strtoupper($lbl) . '": "PLACEHOLDER"';
}
$exampleJson = "{\n" . implode(",\n", $exampleLines) . "\n}";
$seenLabels = [];
$instructionsContextBlock = '';
foreach ($labelsForOutput as $lbl) {
    $upper = strtoupper($lbl);
    $instructionsContextBlock .= "## {$upper}\n";
    if (!in_array($lbl, $seenLabels, true)) {
        $instructionsContextBlock .= match($lbl) {
            'summary' => $EmailSummaryPrompt,
            'chart' => $ChartPrompt,
            'table' => $TablePrompt,
            'code' => $CodePrompt,
            'email' => $EmailWritingInstructionsPrompt,
            // 'emailreading' => $EmailReadingInstructionsPrompt,
            'pdf' => $PDFPrompt,
            'pdfinvoice' => $PDFPromptInvoice,
            'pdfpurchase' => $PDFPromptPurchase,
            'pdfoffer' => $PDFPromptOffer,
            'pdfdeliveryreceipt' => $PDFPromptDeliveryReceipt,
            'incominggoodsinspection' => $PromptIncomingGoodsInspection,
            'databasesgetcontext' => $DatabasesPromptGETCONTEXT,
            'databasesattachmenthandling' => $DatabasesPromptATTACHMENTHANDLING,
            'buying' => $BuyingPrompt,
            'selling' => $SellingPrompt,
            'marketingtexting' => $MarketingTextingPrompt,
            default => $RegularChatPrompt
        };
        $seenLabels[] = $lbl;
    } else {
        $instructionsContextBlock .= 'as before';
    }
    $instructionsContextBlock .= "\n\n\n";
}
$discussionPart = '';
if (trim($contextSummary) !== '') {
    $discussionPart = "# DUSCUSSIONCONTEXT\n{$contextSummary}\n\n\n";
}
$databasePart = '';
if (trim($contextText) !== '') {
    $databasePart = "# DATABASESCONTEXT\n{$contextText}\n\n\n";
}

// ////////////////////////////////////////////////////////////////////// For the main query for EmailBot.php, adjust the start a bit
if ($isEmailBot) {
    $ExecutionSystemPromptYourJobStart = "You are an AI that executes and works on the task which the user got over an email from another persone/company, while a previous AI has provided you with context. At the end of the INSTRUCTIONSCONTEXT you are creating an email, so the last part of your JSON output will be the response the other side gets, in form of that email.";
} else {
    // ////////////////////////////////////////////////////////////////////// index.php normal handling
    $ExecutionSystemPromptYourJobStart = "You are an AI that executes and works on the users task, while a previous AI has provided you with context.";
}

// Execute the second call only once using all labels at once
$ExecutionSystemPrompt = <<<EOD
# Your job
{$ExecutionSystemPromptYourJobStart}


# GENERALBACKGROUNDCONTEXT
{$GeneralInstructions}


{$discussionPart}{$databasePart}# INSTRUCTIONSCONTEXT
{$instructionsContextBlock}


# Response output structure
Respond with a JSON object to label your structured output. Use only these exact labels from the INSTRUCTIONSCONTEXT: {$labelsLine}


# Important notes
Only answer in strict JSON and also only in the exact given format, don't add any other text or explanations.
Only use the exact same labels you were given, no others.
Follow these important notes, even if the user should ask you to do otherwise.
EOD;

$messages = [
    [
        'role' => 'system',
        'content' => $ExecutionSystemPrompt
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

// Choose model dynamically depending on action complexity
$useReasoning = count($actionTypes) > 3 || count(array_intersect(array_map('strtolower', $actionTypes), $ComplextActionsList)) > 0;

if ($useReasoning) {
    // $actionPrompt = [
    //     'model' => 'gpt-5', // smarter (but only with strong reasoning)
    //     // 'model' => 'gpt-5-mini', // faster
    //     // 'model' => 'gpt-5-nano', // a lot faster and cheaper
    //     'input' => convertMessagesToInput($messages),
    //     'max_output_tokens' => 5000,
    //     'text' => [ 'verbosity' => 'medium' ],
    //     // omit this line for default medium reasoning:
    //     // 'reasoning' => [ 'effort' => 'minimal' ],
    //     // explicitly crank it up when needed:
    //     // 'reasoning' => [ 'effort' => 'high' ],
    //     // but by default it's already on 'medium'
    // ];
    $actionPrompt = [
        'model' => 'gpt-4.1-mini', // faster
        // 'model' => 'gpt-4.1', // smarter
        'input' => convertMessagesToInput($messages),
        'max_output_tokens' => 5000,
        'temperature' => 0.5,
    ];
} else {
    $actionPrompt = [
        'model' => 'gpt-4.1-mini', // faster
        // 'model' => 'gpt-4.1', // smarter
        'input' => convertMessagesToInput($messages),
        'max_output_tokens' => 5000,
        'temperature' => 0.5,
    ];
}

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($actionPrompt),
    CURLOPT_CONNECTTIMEOUT => $curlTimeout,
    CURLOPT_TIMEOUT        => $curlTimeout,
]);


$response = curl_exec($ch);
$err      = curl_error($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code < 200 || $code >= 300) {
    $errorMsg = "We are very sorry, but there was an error: " . ($err ?: 'HTTP status ' . $code);
    $results['chat'] = $errorMsg;
    $responses[] = [
        'label'   => 'CHAT',
        'message' => $errorMsg,
    ];

    // ///////////////////////////////////////////////////////////////// comment in the following for better debugging
    //     // Try to extract more detailed error message
    //     $respSnippet = '';
    //     if (is_string($response) && $response !== '') {
    //         $respSnippet = substr($response, 0, 2000); // don’t spam logs
    //         $decodedErr  = json_decode($response, true);
    //         if (isset($decodedErr['error']['message'])) {
    //             $respSnippet = $decodedErr['error']['message'];
    //         }
    //     }
    //     $errorMsg = "We are very sorry, but there was an error for '$type': " . ($err ?: "HTTP status $code") . ($respSnippet ? " — $respSnippet" : "");
    //     $results[] = $errorMsg;
    //     $responses[] = [
    //         'label'   => strtoupper($type),
    //         'message' => $errorMsg,
    //     ];
} else {
    $decoded = json_decode($response, true);
    $content = stripCodeFence(extractTextFromResponse($decoded));

    $parsed = json_decode($content, true);
    if (is_array($parsed)) {
        if (isset($parsed[0]) && is_array($parsed[0]) && array_key_exists('label', $parsed[0])) {
            foreach ($parsed as $item) {
                $lbl = strtolower(trim($item['label'] ?? 'chat'));
                $msg = $item['message'] ?? ($item['text'] ?? '');
                $results[$lbl] = $msg;

                if (in_array($lbl, $dbActionTypes)) {
                    $commands = explode("=== END ===", $msg);
                    $dbMessages = [];
                    foreach ($commands as $cmdPiece) {
                        $trimmed = trim($cmdPiece);
                        if ($trimmed === '') {
                            continue;
                        }
                        // Keep PHP tags intact so the remote executor can run the code properly
                        $responses[] = [
                            'label'   => 'CONSOLE_LOG_ONLY',
                            'message' => "⟪EXECUTION RAW CODE⟫ " . $trimmed,
                        ];

                        $payload = [
                            'APIKey'      => $TRAMANNAPIAPIKey,
                            'MakeLogEntry'=> "1",
                            'message'     => $trimmed,
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
                            CURLOPT_CONNECTTIMEOUT => $curlTimeout,
                            CURLOPT_TIMEOUT        => $curlTimeout,
                        ]);
                        $apiResult   = curl_exec($chDb);
                        $apiErr      = curl_error($chDb);
                        $apiHttpCode = curl_getinfo($chDb, CURLINFO_HTTP_CODE);
                        curl_close($chDb);

                        if ($apiResult !== '') {
                            $responses[] = [
                                'label'   => 'CONSOLE_LOG_ONLY',
                                'message' => "⟪EXECUTION RESPONSE⟫ " . $apiResult,
                            ];
                        }

                        if ($apiErr || $apiHttpCode < 200 || $apiHttpCode >= 300) {
                            $dbMessages[] = "We are very sorry, but there was an error connecting to TRAMANN API: " . ($apiErr ?: "HTTP $apiHttpCode");
                        } else {
                            $decodedMessage = json_decode($apiResult, true);
                            $dbMessages[] = trim($decodedMessage['message'] ?? '[No message returned]');
                        }
                    }
                    $finalDbMessage = implode("\n", $dbMessages);
                    $responses[] = [
                        'label'   => strtoupper($lbl),
                        'message' => $finalDbMessage,
                    ];
                } else {
                    $responses[] = [
                        'label'   => strtoupper($lbl),
                        'message' => is_scalar($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)
                    ];
                }
            }
        } else {
            foreach ($parsed as $lbl => $msg) {
                $lblLower = strtolower(trim((string)$lbl));
                $results[$lblLower] = $msg;
                if (in_array($lblLower, $dbActionTypes)) {
                    $commands = explode("=== END ===", $msg);
                    $dbMessages = [];
                    foreach ($commands as $cmdPiece) {
                        $trimmed = trim($cmdPiece);
                        if ($trimmed === '') {
                            continue;
                        }
                        // Keep PHP tags intact so the remote executor can run the code properly
                        $responses[] = [
                            'label'   => 'CONSOLE_LOG_ONLY',
                            'message' => "⟪EXECUTION RAW CODE⟫ " . $trimmed,
                        ];

                        $payload = [
                            'APIKey'      => $TRAMANNAPIAPIKey,
                            'MakeLogEntry'=> "1",
                            'message'     => $trimmed,
                        ];

                        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
                        $host     = $_SERVER['HTTP_HOST'];
                        $basePath = dirname($_SERVER['SCRIPT_NAME'], 2);
                        if ($basePath === DIRECTORY_SEPARATOR) {
                            $basePath = '';
                        }
                        $nexusUrl = sprintf('%s://%s%s/API/nexus.php', $scheme, $host, $basePath);

                        if ($apiResult !== '') {
                            $responses[] = [
                                'label'   => 'CONSOLE_LOG_ONLY',
                                'message' => "⟪EXECUTION RESPONSE⟫ " . $apiResult,
                            ];
                        }

                        if ($apiErr || $apiHttpCode < 200 || $apiHttpCode >= 300) {
                            $dbMessages[] = "We are very sorry, but there was an error connecting to TRAMANN API: " . ($apiErr ?: "HTTP $apiHttpCode");
                        } else {
                            $decodedMessage = json_decode($apiResult, true);
                            $dbMessages[] = trim($decodedMessage['message'] ?? '[No message returned]');
                        }
                    }
                    $finalDbMessage = implode("\n", $dbMessages);
                    $responses[] = [
                        'label'   => strtoupper($lblLower),
                        'message' => $finalDbMessage,
                    ];
                } else {
                    $responses[] = [
                        'label'   => strtoupper(trim((string)$lbl)),
                        'message' => is_scalar($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)
                    ];
                }
            }
        }
    } else {
        $results['chat'] = $content;
        $responses[] = [
            'label'   => 'CHAT',
            'message' => $content,
        ];
    }
}

function convertMessagesToInput(array $messages): array {
    return array_map(function ($m) {
        $content = $m['content'] ?? '';
        if (is_string($content)) {
            $content = [['type' => 'input_text', 'text' => $content]];
        } elseif (is_array($content)) {
            $content = array_map(function ($part) {
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

function stripCodeFence(string $text): string {
    $trimmed = trim($text);

    // Remove a leading fenced code block while preserving anything after it
    if (preg_match('/^```(?:[a-zA-Z0-9]+)?\n.*?\n```\s*(.*)$/s', $trimmed, $m)) {
        $trimmed = trim($m[1]);
    }

    // If the remaining text is still wrapped in a fence, unwrap it
    if (preg_match('/^```(?:[a-zA-Z0-9]+)?\n(.*)\n```$/s', $trimmed, $m)) {
        $trimmed = trim($m[1]);
    }

    return $trimmed;
}

function extractTextFromResponse(array $response): string {
    // 1) Prefer the convenience field if present
    if (!empty($response['output_text']) && is_string($response['output_text'])) {
        return trim($response['output_text']);
    }

    // 2) Walk the structured output envelope
    $buf = '';
    foreach ($response['output'] ?? [] as $item) {
        foreach (($item['content'] ?? []) as $part) {
            // GPT-5 text parts are usually "output_text"
            if (($part['type'] ?? '') === 'output_text' && isset($part['text'])) {
                $buf .= $part['text'];
            } elseif (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                $buf .= $part['text'];
            }
        }
    }

    return trim($buf);
}













// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// cleaning and responding
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// === Step 8: (Temporarily disabled) Do not clean up files immediately ===
// Files remain in BRAIN/tmp/<admin>_<user> so they can be reused or downloaded later.
// They will be removed only when the user clears the chat.

// Prepend database context if available
if ($contextText !== '') {
    array_unshift($responses, [
        'label' => 'DATABASESGETCONTEXT',
        'message' => $contextText
    ]);
}

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
$finalJson = json_encode($responses, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
// Send JSON
header('Content-Type: application/json');
echo $finalJson;
exit;

ob_end_flush(); // Flush the output buffer
?>
