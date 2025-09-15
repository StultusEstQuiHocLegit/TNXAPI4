<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
session_start();

// -----------------------------------------------------------------------------
// helper functions copied from main.php
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
    if (!empty($response['output_text']) && is_string($response['output_text'])) {
        return trim($response['output_text']);
    }
    $buf = '';
    foreach ($response['output'] ?? [] as $item) {
        foreach (($item['content'] ?? []) as $part) {
            if (($part['type'] ?? '') === 'output_text' && isset($part['text'])) {
                $buf .= $part['text'];
            } elseif (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                $buf .= $part['text'];
            }
        }
    }
    return trim($buf);
}

function truncateText(string $text, int $max): string {
    return mb_substr($text, 0, $max);
}

function pairsToText(array $pairs): string {
    $lines = [];
    foreach ($pairs as $p) {
        $q = $p['q'] ?? '';
        $a = $p['a'] ?? '';
        $lines[] = "Q: $q\nA: $a";
    }
    return implode("\n--\n", $lines);
}

function sanitizeSearchTerms(array $terms): array {
    $safe = [];
    foreach ($terms as $t) {
        $t = trim($t);
        if ($t === '' || mb_strlen($t) > 50) continue;
        if (strpbrk($t, "'\"\\;#") !== false) continue;
        $lower = mb_strtolower($t);
        $bad = ['select','insert','update','delete','drop','truncate','alter','create','where','union','--','/*','*/'];
        $mal = false;
        foreach ($bad as $b) {
            if (strpos($lower, $b) !== false) { $mal = true; break; }
        }
        if (!$mal) $safe[] = $t;
    }
    return array_slice($safe, 0, 3);
}

function getVisibleStructures(int $companyId, PDO $pdo): array {
    $stmtTables = $pdo->prepare("SELECT idpk, name FROM tables WHERE IdpkOfAdmin = ? ORDER BY idpk");
    $stmtTables->execute([$companyId]);
    $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);
    $structures = [];
    foreach ($tables as $tbl) {
        $tId = $tbl['idpk'];
        $tName = $tbl['name'];
        $stmtCols = $pdo->prepare("SELECT name FROM columns WHERE IdpkOfTable = ? AND ShowToPublic = 1 ORDER BY idpk");
        $stmtCols->execute([$tId]);
        $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($cols)) {
            if (!in_array('idpk', $cols)) $cols[] = 'idpk';
            $structures[$tName] = $cols;
        }
    }
    return $structures;
}

function performSearchSingle(string $table, string $field, array $terms, int $companyId, PDO $pdo, string $GeneralKey, array $structures): array {
    if (empty($terms) || !$companyId || !isset($structures[$table])) return ['', []];
    if (!in_array($field, $structures[$table])) return ['', []];

    $stmt = $pdo->prepare("SELECT APIKey, TimestampCreation, DbHost FROM admins WHERE idpk = ?");
    $stmt->execute([$companyId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) return ['', []];

    $TRAMANNAPIKey = $admin['APIKey'];
    $PersonalKey    = $admin['TimestampCreation'];
    $remoteHost     = $admin['DbHost'];
    if (!$remoteHost) return ['', []];
    $stargateUrl = "https://{$remoteHost}/STARGATE/stargate.php";

    $cols = $structures[$table];
    $colsCode  = var_export($cols, true);
    $termsCode = var_export($terms, true);
    $tableCode = addslashes($table);
    $fieldCode = addslashes($field);
    $remoteCode = <<<'PHP'
$terms = __TERMS__;
$cols  = __COLS__;
$table = '__TABLE__';
$field = '__FIELD__';
$res = [];
foreach ($terms as $term) {
    $colList = '`' . implode('`,`', $cols) . '`';
    $like = '%' . $term . '%';
    $sql = "SELECT $colList FROM `$table` WHERE `$field` LIKE ? LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $row['__term'] = $term;
        $res[] = $row;
    }
}
echo json_encode($res);
PHP;
    $remoteCode = str_replace('__TERMS__', $termsCode, $remoteCode);
    $remoteCode = str_replace('__COLS__', $colsCode, $remoteCode);
    $remoteCode = str_replace('__TABLE__', $tableCode, $remoteCode);
    $remoteCode = str_replace('__FIELD__', $fieldCode, $remoteCode);

    $payload = [
        'GeneralKey'    => $GeneralKey,
        'PersonalKey'   => $PersonalKey,
        'TRAMANNAPIKey' => $TRAMANNAPIKey,
        'message'       => $remoteCode
    ];

    $ch = curl_init($stargateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) return ['', []];

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') return ['', []];
    $messageData = $decoded['message'] ?? null;
    if (is_string($messageData)) {
        $inner = json_decode($messageData, true);
    } elseif (is_array($messageData)) {
        $inner = $messageData;
    } else {
        $inner = null;
    }
    if (!is_array($inner)) return ['', []];

    $lines = [];
    $allowed = [];
    foreach ($inner as $row) {
        $term = $row['__term'] ?? '';
        unset($row['__term']);
        $id = $row['idpk'] ?? '';
        if ($id !== '') {
            $allowed[$table][] = (string)$id;
        }
        $lines[] = $term . ' | ' . $table . ' | ' . $id . ' | ' . json_encode($row);
    }
    if (isset($allowed[$table])) {
        $allowed[$table] = array_values(array_unique($allowed[$table]));
    }
    $text = implode("\n", $lines);
    return [truncateText($text, 50000), $allowed];
}


// -----------------------------------------------------------------------------
$question = $_POST['question'] ?? '';
$historyJson = $_POST['history'] ?? '[]';
$companyId = (int)($_POST['company'] ?? 0);

$CompanyName = '';
$connectEmail = '';
$address = '';
if ($companyId) {
    try {
        $stmtInfo = $pdo->prepare("SELECT CompanyName, street, HouseNumber, ZIPCode, city, country, ConnectEmail, email FROM admins WHERE idpk = ?");
        $stmtInfo->execute([$companyId]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        if ($info) {
            $CompanyName = $info['CompanyName'] ?? '';
            $street       = $info['street'] ?? '';
            $houseNumber  = $info['HouseNumber'] ?? '';
            $zipCode      = $info['ZIPCode'] ?? '';
            $city         = $info['city'] ?? '';
            $country      = $info['country'] ?? '';
            $connectEmail = $info['ConnectEmail'] ?? '';
            if ($connectEmail) {
                $connectEmail = preg_split('/[|,\\s]+/', trim($connectEmail))[0] ?? '';
            }
            if (!$connectEmail) {
                $connectEmail = $info['email'] ?? '';
            }
            $addrParts = [];
            if ($street || $houseNumber) {
                $addrParts[] = trim(($houseNumber ? $houseNumber . ' ' : '') . $street);
            }
            if ($city)    $addrParts[] = $city;
            if ($zipCode) $addrParts[] = $zipCode;
            if ($country) $addrParts[] = $country;
            $address = implode(', ', array_filter($addrParts));
        }
    } catch (Exception $e) {
        // ignore
    }
}

$question = truncateText($question, 3000);
$history = json_decode($historyJson, true);
if (!is_array($history)) $history = [];
$history = array_slice($history, 0, 10);
// gather visible table structures
$structures = getVisibleStructures($companyId, $pdo);
$structureText = '';
foreach ($structures as $t => $cols) {
    $structureText .= "table: $t\nfields: " . implode(', ', $cols) . "\n\n";
}
$tablesCount = count($structures);
$singleTable = ($tablesCount === 1);
$onlyTable = $singleTable ? array_key_first($structures) : '';

// ----------------------------- first call ------------------------------------
$contextPairs = array_slice($history, 0, 3);
$ctxText = truncateText(pairsToText($contextPairs), 50000);
if ($tablesCount > 1) {
    $PromptSearch = <<<EOD
# You are an search assistant.
You are given our shop database tables and their visible columns.
Choose one table and one field to search based on the user's question and generate about three concise search terms.
Respond **strictly with just a JSON object**: {"table":"table_name","field":"column_name","terms":["..."]}.
Only use the tables and fields listed below.

# STRUCTURE
$structureText
EOD;
} elseif ($singleTable) {
    $fieldsList = implode(', ', $structures[$onlyTable]);
    $PromptSearch = <<<EOD
# You are an search assistant.
You are given the fields of our shop database table "$onlyTable".
Choose the most relevant field to search and generate about three concise search terms.
Respond **strictly with just a JSON object**: {"field":"field_name","terms":["..."]}.
Only use the listed fields.

# STRUCTURE
Fields: $fieldsList
EOD;
} else {
    $PromptSearch = <<<EOD
# You are an search assistant.
Generate about three concise search terms related to the user's question.

Return them **strictly as just a JSON array of strings**.
EOD;
}

$messages1 = [
    ['role' => 'system', 'content' => $PromptSearch],
    ['role' => 'user',   'content' => "USER QUESTION:\n$question\n\nRECENT CHAT:\n$ctxText"]
];
$payload1 = [
    'model' => 'gpt-4.1-mini',
    'input' => convertMessagesToInput($messages1),
    'temperature' => 0.3,
];
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload1),
]);
$resp1 = curl_exec($ch);
curl_close($ch);
$terms = [];
$table = '';
$field = '';
if ($resp1 !== false) {
    $dec1 = json_decode($resp1, true);
    if (is_array($dec1)) {
        $txt = extractTextFromResponse($dec1);
        $tmp = json_decode($txt, true);
        if (is_array($tmp)) {
            $terms = $tmp['terms'] ?? $tmp['search_terms'] ?? [];
            $table = $tmp['table'] ?? '';
            $field = $tmp['field'] ?? '';
        } else {
            $terms = array_filter(array_map('trim', preg_split('/[\n,]+/', $txt)));
        }
    }
}
if ($singleTable) $table = $onlyTable;
$terms = sanitizeSearchTerms(is_array($terms) ? array_slice($terms, 0, 3) : []);
if (!$singleTable && !isset($structures[$table])) $table = '';
if ($table !== '' && !in_array($field, $structures[$table] ?? [])) $field = '';

// -------------------------------- search -------------------------------------
$searchText = '';
$allowedIds = [];
if ($table !== '' && $field !== '' && !empty($terms)) {
    [$searchText, $allowedIds] = performSearchSingle($table, $field, $terms, $companyId, $pdo, $GeneralKey, $structures);
}

// ----------------------------- second call -----------------------------------
$historyText = truncateText(pairsToText($history), 50000);
$searchText = truncateText($searchText, 50000);

$currentDateTime = date('Y-m-d H:i:s');
// build company info lines for context
$companyInfoLines = [];
if ($CompanyName) $companyInfoLines[] = 'you are acting as the support bot for a company named: ' . $CompanyName;
if ($address) $companyInfoLines[] = 'address: ' . $address;
if ($connectEmail) $companyInfoLines[] = 'contact email: ' . $connectEmail;
$companyContext = '';
if (!empty($companyInfoLines)) {
    $companyContext = "\n" . implode("\n", $companyInfoLines);
}

if (trim($searchText) !== '') {
    $MentionSearchResults = " Use the search results when relevant.";
} else {
    $MentionSearchResults = "";
}

$PromptAnswerGeneralStarting = <<<EOD
# You are a helpful support bot.

## GENERAL BACKGROUND INFORMATION
You are TRAMANN AI, part of TRAMANN TNX API system.
Please respond briefly and **strictly use the language of the user** (even if system prompts, instructions, databases, examples, ... might be in another language).
(current date and time: $currentDateTime)$companyContext

# WHAT TO DO
Answer the users question.$MentionSearchResults

# HOW
Respond **strictly with just a JSON object** containing:
EOD;
if (!empty($CompanyName)) {
    $PromptAnswerGeneralStarting .= "\n(you are acting as the support bot for company: {$CompanyName})";
}

if ($singleTable) {
    $PromptAnswer = <<<EOD
{$PromptAnswerGeneralStarting}
"message": your answer as text,
"idpks": array of idpks sorted from most relevant to least (or an empty array).
EOD;
} else {
    $PromptAnswer = <<<EOD
{$PromptAnswerGeneralStarting}
"message": your answer as text,
"entries": [{"table":"table_name","idpk":123}, ...] sorted from most relevant to least.
Use empty array if no relevant entries.
EOD;
}

$userContent = "# USER QUESTION:\n$question\n";
  if (trim($historyText) !== '') {
      $userContent .= "\n# CHAT HISTORY:\n$historyText\n";
  }
  if (trim($searchText) !== '') {
      $userContent .= "\n# SEARCH RESULTS:\n$searchText\n";
  }

$messages2 = [
    ['role' => 'system', 'content' => $PromptAnswer],
    ['role' => 'user', 'content' => $userContent]
];
$payload2 = [
    'model' => 'gpt-4.1-mini',
    'input' => convertMessagesToInput($messages2),
    'temperature' => 0.3,
    'max_output_tokens' => 500,
];
$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload2),
]);
$resp2 = curl_exec($ch);
curl_close($ch);
$rawResponse = $resp2 === false ? '' : $resp2;
$message = 'We are very sorry, but there was an error.';
$entries = [];
if ($resp2 !== false) {
    $dec2 = json_decode($resp2, true);
    if (is_array($dec2)) {
        $tmp = extractTextFromResponse($dec2);
        $js = json_decode($tmp, true);
        if (is_array($js)) {
            $message = (string)($js['message'] ?? $js['answer'] ?? $tmp);
            if ($singleTable) {
                $ids = $js['idpks'] ?? [];
                if (is_array($ids)) {
                    foreach ($ids as $id) {
                        $id = trim((string)$id);
                        if ($id !== '' && ctype_digit($id) && in_array($id, $allowedIds[$table] ?? [])) {
                            $entries[] = ['table' => $table, 'idpk' => $id];
                        }
                    }
                }
            } else {
                $ents = $js['entries'] ?? [];
                if (is_array($ents)) {
                    foreach ($ents as $e) {
                        $t = $e['table'] ?? '';
                        $id = (string)($e['idpk'] ?? $e['id'] ?? '');
                        if (isset($structures[$t]) && ctype_digit($id) && in_array($id, $allowedIds[$t] ?? [])) {
                            $entries[] = ['table' => $t, 'idpk' => $id];
                        }
                    }
                }
            }
        } else {
            $message = $tmp;
        }
    }
}

echo json_encode([
    'message'       => $message,
    'entries'       => $entries,
    'search_terms'  => $terms,
    'search_results'=> $searchText,
    'raw_response'  => $rawResponse
]);
