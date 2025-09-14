<?php
require_once('../config.php');

// helper to find a preview image for a table entry
function getImagePath(string $remoteHost, string $table, $idpk): ?string {
    $base = "https://{$remoteHost}/STARGATE/UPLOADS/{$table}/{$idpk}_0";

    foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
        $url = "$base.$ext";
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $url;
        }
    }

    return null;
}

function getImagePaths(string $remoteHost, string $table, $idpk, int $max = 8): array {
    $paths = [];
    for ($i = 0; $i < $max; $i++) {
        $base = "https://{$remoteHost}/STARGATE/UPLOADS/{$table}/{$idpk}_{$i}";
        foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
            $url = "$base.$ext";
            $ch  = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 200 && $httpCode < 300) {
                $paths[] = $url;
                break;
            }
        }
    }
    return $paths;
}

function getRelativeDayText(string $dateStr): string {
    $ts = strtotime($dateStr);
    if ($ts === false) return '';
    $startToday = strtotime('today');
    $diffDays = (int)floor(($ts - $startToday) / 86400);
    if (abs($diffDays) > 365) return '';
    if ($diffDays === 0) return '(today)';
    if ($diffDays === -1) return '(yesterday)';
    if ($diffDays === 1) return '(tomorrow)';
    if ($diffDays < 0) return '(' . abs($diffDays) . ' days ago)';
    return '(in ' . $diffDays . ' days)';
}

function formatPreviewValue($value, array $meta, string $currencyCode = ''): string {
    $val = (string)$value;
    $isNeg  = is_numeric($val) && (float)$val < 0;
    $isZero = is_numeric($val) && (float)$val == 0;
    $isPrice = !empty($meta['price']);
    $relative = '';
    if (!empty($meta['type']) && strpos($meta['type'], 'date') !== false) {
        $relative = getRelativeDayText($val);
    }
    if ($isPrice && $currencyCode !== '') {
        $val .= ' ' . $currencyCode;
    }
    $valEsc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    $valHtml = $valEsc;
    if ($isNeg) $valHtml = '<span style="color:red;">' . $valHtml . '</span>';
    if ($isPrice) $valHtml = '<b>' . $valHtml . '</b>';
    if ($relative) $valHtml .= ' ' . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8');

    $labelEsc = htmlspecialchars(($meta['label'] ?? $meta['field']) . ': ', ENT_QUOTES, 'UTF-8');
    $style = $isZero ? ' style="opacity:0.3;"' : '';

    if (in_array($meta['type'], ['text', 'textarea'])) {
        return '<div class="tooltip-text-clamp"' . $style . '>' . $valHtml . '</div>';
    }

    return '<div' . $style . '>' . $labelEsc . $valHtml . '</div>';
}

function formatDetailValue($value, array $meta, string $currencyCode = ''): string {
    $val = (string)$value;
    $isNeg  = is_numeric($val) && (float)$val < 0;
    $isZero = is_numeric($val) && (float)$val == 0;
    $isPrice = !empty($meta['price']);
    $relative = '';
    if (!empty($meta['type']) && strpos($meta['type'], 'date') !== false) {
        $relative = getRelativeDayText($val);
    }
    if ($isPrice && $currencyCode !== '') {
        $val .= ' ' . $currencyCode;
    }
    $valEsc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    $valHtml = $valEsc;
    if ($isNeg) $valHtml = '<span style="color:red;">' . $valHtml . '</span>';
    if ($isPrice) $valHtml = '<b>' . $valHtml . '</b>';
    if ($relative) $valHtml .= ' ' . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8');

    $labelEsc = htmlspecialchars($meta['label'] ?? $meta['field'], ENT_QUOTES, 'UTF-8');
    $style = $isPrice ? ' style="font-size:1.2rem;"' : '';
    if ($isZero) {
        $style = trim($style) ? rtrim($style, '"') . ';opacity:0.3;"' : ' style="opacity:0.3;"';
    }

    if (!empty($meta['type']) && preg_match('/text|varchar/i', $meta['type'])) {
        $labelHtml = '<strong>' . $labelEsc . '</strong>';
        return '<div class="detail-field"' . $style . '>' . $labelHtml . '<br>' . $valHtml . '</div>';
    }

    $labelHtml = '<strong>' . $labelEsc . ':</strong>';
    return '<div class="detail-field"' . $style . '>' . $labelHtml . ' ' . $valHtml . '</div>';
}

/**
 * Check if two words are similar based on prefix, suffix or overall
 * similarity. If 60% or more of the characters match, the words are
 * considered similar.
 */
function wordsAreSimilar(string $a, string $b): bool {
    $a = mb_strtolower($a, 'UTF-8');
    $b = mb_strtolower($b, 'UTF-8');
    $lenA = mb_strlen($a, 'UTF-8');
    $lenB = mb_strlen($b, 'UTF-8');
    $minLen = min($lenA, $lenB);
    $maxLen = max($lenA, $lenB);

    if ((str_starts_with($a, $b) || str_starts_with($b, $a) ||
         str_ends_with($a, $b)   || str_ends_with($b, $a)) &&
        $minLen / $maxLen >= 0.6) {
        return true;
    }

    similar_text($a, $b, $percent);
    return $percent >= 60;
}

/**
 * Extract most relevant keywords from the provided search entries.
 *
 * This function removes duplicates per entry
 * and only returns words that appear in more than one entry to improve relevance.
 */
function extractTopKeywords(array $entries, int $limit = 20): array {

    $counts = [];
    foreach ($entries as $entry) {
        $seen = [];
        foreach ($entry['search'] as $val) {
            $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($val));
            foreach ($words as $w) {
                $w = trim($w);
                if ($w === '' || mb_strlen($w, 'UTF-8') < 10) continue;
                if (isset($seen[$w])) continue;
                $seen[$w] = true;
                if (!isset($counts[$w])) $counts[$w] = 0;
                $counts[$w]++;
            }
        }
    }

    foreach ($counts as $k => $c) {
        if ($c < 2) unset($counts[$k]);
    }

    arsort($counts);
    $filtered = [];
    foreach (array_keys($counts) as $word) {
        $skip = false;
        foreach ($filtered as $existing) {
            if (wordsAreSimilar($word, $existing)) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $filtered[] = $word;
            if (count($filtered) >= $limit) break;
        }
    }
    return $filtered;
}

function collectSearchFields(array $tablesData, array $structures): array {
    $entries = [];
    foreach ($tablesData as $tableName => $info) {
        foreach ($info['rows'] as $row) {
            $search = [];
            $search[] = (string)($row['idpk'] ?? '');
            if ($info['humanPrimary'] && isset($row[$info['humanPrimary']])) {
                $search[] = (string)$row[$info['humanPrimary']];
            }
            $fieldsMeta = $structures[$tableName]['allFields'] ?? [];
            foreach ($fieldsMeta as $f => $meta) {
                if (isset($row[$f])) {
                    $search[] = (string)$row[$f];
                }
            }
            $entries[] = ['search' => $search];
        }
    }
    return $entries;
}

function buildCardHtml(string $tableName, array $row, array $structures, string $remoteHost, string $currencyCode, int $company): string {
    $display = $structures[$tableName]['humanPrimary'] && isset($row[$structures[$tableName]['humanPrimary']])
        ? $row[$structures[$tableName]['humanPrimary']]
        : ($row['idpk'] ?? '');
    $img = null;
    if (isset($row['__img_ext'])) {
        $img = "https://{$remoteHost}/STARGATE/UPLOADS/{$tableName}/" . ($row['idpk'] ?? '') . "_0." . $row['__img_ext'];
    } else {
        $img = getImagePath($remoteHost, $tableName, $row['idpk'] ?? '');
    }
    $link = '?company=' . urlencode($company) . '&table=' . urlencode($tableName) . '&idpk=' . urlencode($row['idpk']);
    $infoHtml = '';
    $priceHtml = '';
    $priceVal = null;
    $previewMap = $structures[$tableName]['previewFields'] ?? [];
    $humanPrimary = $structures[$tableName]['humanPrimary'] ?? null;
    foreach ($previewMap as $field => $meta) {
        if ($field === 'idpk' || ($humanPrimary && $field === $humanPrimary)) continue;
        if (!isset($row[$field])) continue;
        $val = $row[$field];
        if ($val === '' || $val === null) {
            if (!empty($meta['price'])) {
                $val = '0.00';
            } else {
                continue;
            }
        }
        $meta['field'] = $field;
        $formatted = formatPreviewValue($val, $meta, $currencyCode);
        if (!empty($meta['price'])) {
            if ($priceVal === null && is_numeric($val)) {
                $priceVal = $val;
            }
            $priceHtml .= $formatted;
        } else {
            $infoHtml .= $formatted;
        }
    }
    $infoOnlyHtml = $infoHtml;
    $infoHtml .= $priceHtml;

    ob_start();
    ?>
    <a class="shop-card" href="<?php echo htmlspecialchars($link); ?>"
       data-info-html="<?php echo htmlspecialchars($infoOnlyHtml, ENT_QUOTES, 'UTF-8'); ?>"
       data-price-html="<?php echo htmlspecialchars($priceHtml, ENT_QUOTES, 'UTF-8'); ?>"
       data-table="<?php echo htmlspecialchars($tableName); ?>"
       data-id="<?php echo htmlspecialchars($row['idpk'] ?? ''); ?>"
       data-name="<?php echo htmlspecialchars((string)$display); ?>"
       data-price="<?php echo htmlspecialchars($priceVal ?? ''); ?>">
        <?php if ($img): ?>
            <div class="img-wrap">
                <img src="<?php echo htmlspecialchars($img); ?>" loading="lazy">
                <div class="overlay"><?php echo htmlspecialchars(strtoupper((string)$display)); ?> (<?php echo htmlspecialchars($row['idpk'] ?? ''); ?>)</div>
            </div>
        <?php else: ?>
            <div class="info-top"><?php echo htmlspecialchars(strtoupper((string)$display)); ?> (<?php echo htmlspecialchars($row['idpk'] ?? ''); ?>)</div>
        <?php endif; ?>
        <?php if ($infoHtml): ?>
            <div class="info"><?php echo $infoHtml; ?></div>
        <?php endif; ?>
    </a>
    <?php
    return trim(ob_get_clean());
}

// get the company idpk from the URL parameters
$company = isset($_GET['company']) ? (int)$_GET['company'] : null;

$errorMsg = '';
$tablesData = [];
$currencyCode = '';
$darkModeShop = 0;
$connectEmail = '';
$CompanyIBAN = '';
$ShopShowRandomProductCards = 1;
$ShopShowSuggestionCards = 1;
$ShopAllowToAddCommentsNotesSpecialRequests = 1;
$ShopAllowToAddAdditionalNotes = 1;
$ShopTargetCustomerEntity = 0;
$selectedTable = isset($_GET['table']) ? $_GET['table'] : null;
$selectedIdpk  = isset($_GET['idpk']) ? $_GET['idpk'] : null;
$isAjax        = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$isSuggest     = isset($_GET['suggest']) && $_GET['suggest'] === '1';
$isSendOrder   = isset($_GET['send']) && $_GET['send'] === '1';

if ($company) {
    try {
        // fetch stargate credentials for the admin
        $stmt = $pdo->prepare("SELECT CompanyName, APIKey, TimestampCreation, DbHost, CurrencyCode, DarkmodeShop, PhoneNumberWork, street, HouseNumber, ZIPCode, city, country, ConnectEmail, email, CompanyIBAN, ShopShowRandomProductCards, ShopShowSuggestionCards, ShopAllowToAddCommentsNotesSpecialRequests, ShopAllowToAddAdditionalNotes, ShopTargetCustomerEntity FROM admins WHERE idpk = ?");
        $stmt->execute([$company]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $CompanyName = $admin['CompanyName'];
            $TRAMANNAPIKey = $admin['APIKey'];
            $PersonalKey   = $admin['TimestampCreation'];
            $remoteHost    = $admin['DbHost'];
            $currencyCode  = $admin['CurrencyCode'] ?? '';
            $darkModeShop  = (int)($admin['DarkmodeShop'] ?? 0);
            $phoneNumberWork = $admin['PhoneNumberWork'] ?? '';
            $street         = $admin['street'] ?? '';
            $houseNumber    = $admin['HouseNumber'] ?? '';
            $zipCode        = $admin['ZIPCode'] ?? '';
            $city           = $admin['city'] ?? '';
            $country        = $admin['country'] ?? '';
            $connectEmail   = $admin['ConnectEmail'] ?? '';
            if ($connectEmail) {
                $connectEmail = preg_split('/[|,\s]+/', trim($connectEmail))[0] ?? '';
            }
            $CompanyIBAN    = $admin['CompanyIBAN'] ?? '';
            $ShopShowRandomProductCards = (int)($admin['ShopShowRandomProductCards'] ?? 1);
            $ShopShowSuggestionCards = (int)($admin['ShopShowSuggestionCards'] ?? 1);
            $ShopAllowToAddCommentsNotesSpecialRequests = (int)($admin['ShopAllowToAddCommentsNotesSpecialRequests'] ?? 1);
            $ShopAllowToAddAdditionalNotes = (int)($admin['ShopAllowToAddAdditionalNotes'] ?? 1);
            $ShopTargetCustomerEntity = (int)($admin['ShopTargetCustomerEntity'] ?? 0);
            if (!$connectEmail) {
                $connectEmail = $admin['email'] ?? '';
            }

            $stargateUrl = "https://{$remoteHost}/STARGATE/stargate.php";

            // -----------------------------------------------------------------
            // determine table structure on THIS server (not the remote one)
            $stmtTables = $pdo->prepare(
                "SELECT idpk, name FROM tables WHERE IdpkOfAdmin = ? ORDER BY idpk"
            );
            $stmtTables->execute([$company]);
            $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

            // check once if the columns table has the optional ShowWholeEntryToPublicMarker field
            $hasPublicMarker = false;
            try {
                $check = $pdo->query("SHOW COLUMNS FROM `columns` LIKE 'ShowWholeEntryToPublicMarker'");
                $hasPublicMarker = ($check->fetch() !== false);
            } catch (Exception $e) {
                $hasPublicMarker = false;
            }

            $structures = [];
            foreach ($tables as $tbl) {
                $tId  = $tbl['idpk'];
                $tName = $tbl['name'];
                $colSql = "SELECT name, label, type, price, ShowInPreviewCard, ShowToPublic, HumanPrimary";
                if ($hasPublicMarker) {
                    $colSql .= ", ShowWholeEntryToPublicMarker";
                }
                $colSql .= " FROM columns WHERE IdpkOfTable = ? ORDER BY idpk";
                $stmtCols = $pdo->prepare($colSql);
                $stmtCols->execute([$tId]);
                $cols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

                $publicCols   = [];
                $humanPrimary = null;
                $previewFields = [];
                $allFields    = [];
                $publicMarker = null;
                foreach ($cols as $col) {
                    if ((int)($col['ShowWholeEntryToPublicMarker'] ?? 0) === 1 && !$publicMarker) {
                        $publicMarker = $col['name'];
                    }
                    if ((int)$col['ShowToPublic'] === 1) {
                        $publicCols[] = $col['name'];
                        if ((int)$col['HumanPrimary'] === 1) {
                            $humanPrimary = $col['name'];
                        }
                        $allFields[$col['name']] = [
                            'label' => $col['label'] ?? $col['name'],
                            'type'  => $col['type'] ?? '',
                            'price' => !empty($col['price'])
                        ];
                        if ((int)$col['ShowInPreviewCard'] === 1
                            && (int)$col['HumanPrimary'] !== 1
                            && $col['name'] !== 'idpk') {
                            $previewFields[$col['name']] = [
                                'label' => $col['label'] ?? $col['name'],
                                'type'  => $col['type'] ?? '',
                                'price' => !empty($col['price'])
                            ];
                        }
                    }
                }

                if (!empty($publicCols)) {
                    if (!$humanPrimary && !in_array('idpk', $publicCols)) {
                        $publicCols[] = 'idpk';
                    }
                    $structures[$tName] = [
                        'columns'       => $publicCols,
                        'humanPrimary'  => $humanPrimary,
                        'previewFields' => $previewFields,
                        'allFields'     => $allFields,
                        'publicMarker'  => $publicMarker
                    ];
                }
            }
            
            // build remote code to fetch data for these tables and also check
            // for a preview image on the remote server. Doing the filesystem
            // checks remotely avoids dozens of HTTP requests from this script
            // which previously used curl HEAD requests for each entry.
            $remoteCode = '$result = []; $exts = ["jpg","jpeg","png","gif","webp"];' . "\n";
            foreach ($structures as $tName => $info) {
                $colList = implode(',', array_map(fn($c) => "`{$c}`", $info['columns']));
                $order   = $info['humanPrimary'] ? $info['humanPrimary'] : 'idpk';
                $hp      = $info['humanPrimary'] ? "'{$info['humanPrimary']}'" : 'null';
                $whereClause = '';
                if (!empty($info['publicMarker'])) {
                    $marker = $info['publicMarker'];
                    $whereClause = " WHERE LOWER(CAST(`{$marker}` AS CHAR)) IN ('1','true')";
                }
                $remoteCode .=
                    "\$stmt = \$pdo->prepare(\"SELECT $colList FROM `$tName`$whereClause ORDER BY `$order`\");" . "\n" .
                    "\$stmt->execute();" . "\n" .
                    "\$tmp = \$stmt->fetchAll(PDO::FETCH_ASSOC);" . "\n" .
                    "\$rows = [];" . "\n" .
                    "foreach (\$tmp as \$r) {" . "\n" .
                    "    \$id = \$r['idpk'];" . "\n" .
                    "    \$extFound = '';" . "\n" .
                    "    foreach (\$exts as \$e) {" . "\n" .
                    "        if (file_exists(__DIR__ . '/UPLOADS/$tName/' . \$id . '_0.' . \$e)) { \$extFound = \$e; break; }" . "\n" .
                    "    }" . "\n" .
                    "    if (\$extFound) { \$r['__img_ext'] = \$extFound; }" . "\n" .
                    "    \$rows[] = \$r;" . "\n" .
                    "}" . "\n" .
                    "\$result['$tName'] = ['humanPrimary' => $hp, 'rows' => \$rows];" . "\n";
            }
            $remoteCode .= 'echo json_encode($result);';

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
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false) {
                $decoded = json_decode($response, true);
                if ($decoded && isset($decoded['status']) && $decoded['status'] === 'success') {
                    $tablesData = $decoded['message'];
                } else {
                    $errorMsg = 'We are very sorry, but there was no data found.';
                }
            } else {
                $errorMsg = $curlError ?: 'Curl error';
            }
        } else {
            $errorMsg = 'We are very sorry, but there was no data found.';
        }
    } catch (Exception $e) {
        $errorMsg = 'We are very sorry, but there was a problem retrieving the data.';
    }
}

if ($isSendOrder && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $message = $payload['message'] ?? '';
    $customerEmail = isset($payload['customerEmail']) ? filter_var($payload['customerEmail'], FILTER_VALIDATE_EMAIL) : '';
    $sent = false;
    if ($connectEmail && $message) {
        $headers = "Content-Type: text/plain; charset=utf-8\r\n";
        $sent = mail($connectEmail, 'TRAMANN PROJECTS TNX API - SHOP - new order', $message, $headers);
        if ($sent && $customerEmail) {
            mail($customerEmail, 'TRAMANN PROJECTS TNX API - SHOP - your order copy', $message, $headers);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => $sent]);
    exit;
}

if ($isSuggest) {

    // collect random entry cards if enabled
    $searchEntries = $tablesData ? collectSearchFields($tablesData, $structures) : [];
    $entryHtmls = [];
    if ($tablesData && $ShopShowRandomProductCards) {
        foreach ($tablesData as $tName => $info) {
            foreach ($info['rows'] as $row) {
                $entryHtmls[] = buildCardHtml($tName, $row, $structures, $remoteHost, $currencyCode, $company);
            }
        }
    }
    $totalEntries = count($entryHtmls);
    $entryLimit = $ShopShowSuggestionCards ? 10 : 30;
    $entryCount = 0;
    if ($ShopShowRandomProductCards) {
        shuffle($entryHtmls);
        $entryCount = min($entryLimit, $totalEntries);
        $entryHtmls = array_slice($entryHtmls, 0, $entryCount);
    }

    // collect suggestion keywords if enabled
    $keywords = [];
    if ($ShopShowSuggestionCards) {
        $keywordLimit = $ShopShowRandomProductCards ? 20 : 30;
        $keywords = extractTopKeywords($searchEntries, $keywordLimit);
        shuffle($keywords);
    }

    $combined = [];
    if ($ShopShowRandomProductCards && $ShopShowSuggestionCards) {
        $i = 0; // keyword index
        $j = 0; // entry index
        $eStreak = 0;
        $kStreak = 0;
        $hasKeywords = count($keywords) > 0;
        $hasEntries  = $entryCount > 0;

        while ($i < count($keywords) || $j < $entryCount) {
            if ($hasKeywords && $hasEntries) {
                if ($eStreak >= 3) {
                    $choice = 'word';
                } elseif ($kStreak >= 6) {
                    $choice = 'entry';
                } else {
                    $choice = rand(0, 1) ? 'word' : 'entry';
                }
            } elseif ($hasEntries) {
                $choice = 'entry';
            } else {
                $choice = 'word';
            }

        if ($choice === 'word' && $i < count($keywords)) {
                $combined[] = ['type' => 'word', 'value' => $keywords[$i++]];
                $kStreak++;
                $eStreak = 0;
            } elseif ($choice === 'entry' && $j < $entryCount) {
                $combined[] = ['type' => 'entry', 'html' => $entryHtmls[$j++]];
                $eStreak++;
                $kStreak = 0;
            } else {
                if ($i < count($keywords)) {
                    $combined[] = ['type' => 'word', 'value' => $keywords[$i++]];
                    $kStreak++;
                    $eStreak = 0;
                } elseif ($j < $entryCount) {
                    $combined[] = ['type' => 'entry', 'html' => $entryHtmls[$j++]];
                    $eStreak++;
                    $kStreak = 0;
                }
            }
        }
    } elseif ($ShopShowRandomProductCards) {
        foreach ($entryHtmls as $html) {
            $combined[] = ['type' => 'entry', 'html' => $html];
        }
    } elseif ($ShopShowSuggestionCards) {
        foreach ($keywords as $word) {
            $combined[] = ['type' => 'word', 'value' => $word];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($combined);
    exit;
}

?>
<?php if (!$isAjax): ?>
<!DOCTYPE html>
<html lang="en" <?php echo $darkModeShop ? 'data-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <title>TRAMANN PROJECTS TNX API</title>
    <link rel="stylesheet" type="text/css" href="../UI/style.css">
    <link rel="icon" type="image/png" href="../logos/favicon.png">
    <style>
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .shop-card {
            position: relative;
            display: block;
            border: 3px solid var(--bg-color);
            border-radius: 12px;
            overflow: hidden;
            background-color: var(--border-color);
            color: var(--text-color);
            text-decoration: none;
            box-shadow: 0 0 10px var(--shadow-color);
            transition: box-shadow 0.3s;
        }
        .shop-card:hover {
            box-shadow: 0 0 10px var(--primary-hover);
        }
        .shop-card .img-wrap {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
        }
        .shop-card img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .shop-card .overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(0,0,0,0.3);
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            padding: 2px 4px;
        }
        .shop-card .info {
            background-color: var(--border-color);
            color: var(--text-color);
            font-size: 11px;
            padding: 4px;
            word-break: break-word;
        }
        .shop-card .info-top {
            background-color: var(--border-color);
            color: var(--text-color);
            font-weight: bold;
            font-size: 12px;
            padding: 4px;
            word-break: break-word;
            text-align: center;
        }
        .cart-icon {
            position: fixed;
            top: 10px;
            right: 10px;
            width: 48px;
            height: 48px;
            font-size: 1.5rem;
            border-radius: 50%;
            border: 3px solid var(--bg-color);
            background-color: var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 0 10px var(--shadow-color);
            cursor: pointer;
            transition: box-shadow 0.3s;
            z-index: 1002;
        }
        .cart-icon:hover,
        .cart-icon.glow {
            box-shadow: 0 0 10px var(--primary-hover);
        }
        .cart-icon-hidden {
            display: none;
        }
        .customer-field { margin-top:10px; }
        .customer-input { opacity:0.3; width:100%; }
        .customer-input:focus, .customer-input.has-value { opacity:1; }
        textarea.customer-input { opacity:0.5; resize: vertical; }
        textarea.customer-input:focus, textarea.customer-input.has-value { opacity:1; }
        #customer-fields .customer-input { opacity:1; }
        #customer-fields textarea.customer-input { opacity:0.5; }
        #customer-fields textarea.customer-input:focus,
        #customer-fields textarea.customer-input.has-value { opacity:1; }
        .cart-item { margin-bottom:10px; }
        input.cart-qty { text-align:left; width:80px; font-weight:bold; }
        .cart-table {
            width:100%;
            table-layout:fixed;
        }
        .cart-table td:nth-child(1) { width:55%; }
        .cart-table td:nth-child(2) { width:35%; }
        .cart-table td:nth-child(3) { width:10%; text-align:right; }
        #payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 250px));
            gap: 10px;
            justify-content: center;
        }
        .pay-option {
            cursor: pointer;
            border: 3px solid var(--bg-color);
            border-radius: 12px;
            padding: 10px;
            background-color: var(--border-color);
            color: var(--text-color);
            box-shadow: 0 0 10px var(--shadow-color);
            transition: box-shadow 0.3s;
            opacity: 0.5;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 200px;
            height: 150px;
        }
        .pay-option:hover {
            box-shadow: 0 0 10px var(--primary-hover);
        }
        .big-card {
            border: 3px solid var(--bg-color);
            border-radius: 12px;
            padding: 10px;
            background-color: var(--border-color);
            color: var(--text-color);
            box-shadow: 0 0 10px var(--shadow-color);
            width: 100%;
            margin: 30px auto;
            width: 100%;
            max-width: 500px;
        }
        @media (min-width: 768px) {
            .big-card {
                max-width: 1200px;
            }
        }
        .big-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .image-viewer {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
        }
        .image-viewer img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 12px;
        }
        .thumbs {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 5px;
            flex-wrap: wrap;
        }
        .thumbs .thumb-wrap {
            position: relative;
            width: 60px;
            padding-top: 60px;
            overflow: hidden;
            border-radius: 8px;
            flex: 0 0 60px;
        }
        .thumbs img.thumb {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            cursor: pointer;
            opacity: 1;
        }
        .thumbs img.thumb.active {
            opacity: 0.5;
        }
        .detail-field {
            margin-top: 15px;
        }
        .detail-field strong {
            font-weight: bold;
        }
        .right-section > .detail-field:first-child {
            margin-top: 0;
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
        #overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow-y: auto;
            background-color: var(--bg-color);
            padding: 20px;
            z-index: 1000;
            display: none;
        }
        #message-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: none;
            justify-content: center;
            align-items: center;
            background-color: var(--bg-color);
            z-index: 2000;
            padding: 20px;
        }
        #message-overlay .alert-box {
            border: 3px solid var(--bg-color);
            border-radius: 12px;
            background-color: var(--border-color);
            color: var(--text-color);
            padding: 20px;
            box-shadow: 0 0 10px var(--shadow-color);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .price-preview {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .big-card-header .price-preview {
            margin-bottom: 0;
        }
        .loading-dots {
            display: flex;
            justify-content: center;
            gap: 4px;
            padding: 8px 16px;
            margin-top: 1rem;
        }
        .loading-dots.left {
            justify-content: flex-start;
        }
        .loading-dots span {
            width: 8px;
            height: 8px;
            background-color: var(--primary-color);
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out;
        }
        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }
        h1.text-center {
            margin: 0;
            padding: 1rem 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5ch;
        }
        h1.text-center img {
            height: 1em;
            width: auto;
        }
        .container.centered {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin-bottom: 0;
        }
        #search-wrapper {
            background-color: var(--bg-color);
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        #mode-toggle {
            display: flex;
            gap: 4px;
            cursor: pointer;
            background-color: var(--input-bg);
            border: 2px solid var(--border-color);
            border-radius: 30px;
            padding: 4px;
            align-items: center;
        }
        #mode-toggle span {
            font-size: 1rem;
            opacity: 0.3;
            transition: opacity 0.2s, background-color 0.2s;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            line-height: 1;
        }
        #mode-toggle span.active {
            opacity: 1;
            background-color: var(--border-color);
            color: var(--bg-color);
        }
        #search-input {
            padding: 10px;
            width: 500px;
            height: 48px;
            box-sizing: border-box;
        }
        .control-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            font-size: 1.5rem;
            border-radius: 12px;
            border: 3px solid var(--bg-color);
            background-color: var(--border-color);
            box-shadow: 0 0 10px var(--shadow-color);
            cursor: pointer;
            transition: box-shadow 0.3s;
            box-sizing: border-box;
        }
        .control-button:hover {
            background-color: var(--input-bg);
        }
        .send-button {
            color: var(--primary-color);
            display: none;
        }
        .send-button.visible {
            display: flex;
        }
        @media (max-width: 600px) {
            #search-input {
                width: 100%;
            }
        }
        #suggestions {
            margin: 2rem 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            width: 100%;
        }
        .suggest-card {
            position: relative;
            display: block;
            border: 3px solid var(--bg-color);
            border-radius: 12px;
            overflow: hidden;
            background-color: var(--border-color);
            color: var(--text-color);
            text-decoration: none;
            box-shadow: 0 0 10px var(--shadow-color);
            transition: box-shadow 0.3s;
        }
        .suggest-card:hover {
            box-shadow: 0 0 10px var(--primary-hover);
        }
        .suggest-card .deco {
            position: relative;
            width: 100%;
            padding-top: 100%;
            border-bottom: 3px solid var(--bg-color);
            border-radius: 12px 12px 0 0;
        }
        .suggest-card .label {
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }
        #page-footer {
            margin-top: 1rem;
            padding-bottom: 1rem;
            opacity: 0.3;
        }
        #page-footer a {
            margin-right: 20px;
        }
        #page-footer a:last-child {
            margin-right: 0;
        }
        #page-footer .company-info span {
            margin-right: 20px;
        }
        #page-footer .company-info span:last-child {
            margin-right: 0;
        }
    </style>
</head>
<body>
<?php endif; ?>

<a id="cart-icon" class="cart-icon cart-icon-hidden" title="shopping cart">🛒</a>



<?php if (!$isAjax): ?>
<div class="container<?php echo (!$ShopShowRandomProductCards && !$ShopShowSuggestionCards) ? ' centered' : ''; ?>" style="width: 90%; margin: 0 auto 15rem;">
<?php endif; ?>
    <?php if (!$company || $errorMsg): ?>
        <!-- <h1 class="text-center">🌐 SHOP</h1> -->
        <div style="display:flex;justify-content:center;align-items:center;height:50vh;">
            <?php echo htmlspecialchars($errorMsg ?: 'We are very sorry, but there was no data found.'); ?>
        </div>
    <?php else: ?>
        <!-- <h1 class="text-center">🌐 SHOP - <?php echo htmlspecialchars($CompanyName); ?></h1> -->
        <?php if (!$isAjax): ?>
        <?php
            $logoPath = '';
            foreach (['jpg', 'jpeg', 'png', 'gif', 'svg'] as $ext) {
                $candidate = '../UPLOADS/logos/' . $company . '.' . $ext;
                if (file_exists($candidate)) {
                    $logoPath = $candidate;
                    break;
                }
            }
        ?>
        <h1 class="text-center">
            <?php if ($logoPath): ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="logo">
            <?php endif; ?>
            <?php echo htmlspecialchars(strtoupper($CompanyName)); ?>
        </h1>
        <?php endif; ?>
        <?php if ($isAjax && $selectedTable && $selectedIdpk && isset($tablesData[$selectedTable])): ?>
            <?php
                $info = $tablesData[$selectedTable];
                $rowData = null;
                foreach ($info['rows'] as $r) {
                    if ((string)$r['idpk'] === (string)$selectedIdpk) { $rowData = $r; break; }
                }
            ?>
            <?php if ($rowData): ?>
                <?php
                    $fields = $structures[$selectedTable]['allFields'] ?? [];
                    $display = $info['humanPrimary'] && isset($rowData[$info['humanPrimary']])
                        ? $rowData[$info['humanPrimary']]
                        : null;
                    $images = getImagePaths($remoteHost, $selectedTable, $rowData['idpk'] ?? '');
                    $hasPrice = false;
                    $priceVal = null;
                    foreach ($fields as $f => $meta) {
                        if (!empty($meta['price']) && isset($rowData[$f]) && $rowData[$f] !== '') {
                            $hasPrice = true;
                            if ($priceVal === null && is_numeric($rowData[$f])) {
                                $priceVal = $rowData[$f];
                            }
                        }
                    }
                ?>
                <div class="big-card">
                    <div class="big-card-header">
                        <a href="?company=<?php echo urlencode($company); ?>" class="return-link"><strong>◀️ RETURN</strong></a>
                        <?php if ($hasPrice): ?>
                            <a href="#" class="add-to-cart" data-table="<?php echo htmlspecialchars($selectedTable); ?>" data-id="<?php echo htmlspecialchars($rowData['idpk'] ?? ''); ?>" data-name="<?php echo htmlspecialchars((string)$display); ?>" data-price="<?php echo htmlspecialchars($priceVal ?? ''); ?>"><strong>🛒 ADD TO CART</strong></a>
                        <?php endif; ?>
                    </div>
                    <div class="entry-layout">
                        <div class="left-section">
                            <h1 style="text-align:center">🟦 <?php echo htmlspecialchars(strtoupper($display ? $display : 'ENTRY')); ?> (<?php echo htmlspecialchars($rowData['idpk'] ?? ''); ?>)</h1>
                            <?php if (!empty($images)): ?>
                                <div class="image-viewer">
                                    <img id="main-image" src="<?php echo htmlspecialchars($images[0]); ?>" loading="lazy">
                                </div>
                                <?php if (count($images) > 1): ?>
                                <div class="thumbs">
                                    <?php foreach ($images as $idx => $imgUrl): ?>
                                        <div class="thumb-wrap">
                                            <img class="thumb<?php echo $idx === 0 ? ' active' : ''; ?>" src="<?php echo htmlspecialchars($imgUrl); ?>" data-large="<?php echo htmlspecialchars($imgUrl); ?>" loading="lazy">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="right-section">
                            <?php
                                $priceHtml = '';
                                $detailHtml = '';
                                foreach ($fields as $field => $meta) {
                                    if ($field === 'idpk' || $field === $info['humanPrimary']) continue;
                                    if (!isset($rowData[$field])) continue;
                                    $val = $rowData[$field];
                                    if ($val === '' || $val === null) {
                                        if (!empty($meta['price'])) {
                                            $val = '0.00';
                                        } else {
                                            continue;
                                        }
                                    }
                                    $meta['field'] = $field;
                                    $formatted = formatDetailValue($val, $meta, $currencyCode);
                                    if (!empty($meta['price'])) {
                                        $priceHtml .= $formatted;
                                    } else {
                                        $detailHtml .= $formatted;
                                    }
                                }
                                echo $priceHtml . $detailHtml;
                            ?>
                        </div>
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function(){
                    const main = document.getElementById('main-image');
                    document.querySelectorAll('.thumb').forEach(t => {
                        t.addEventListener('mouseover', () => {
                            main.src = t.dataset.large;
                            document.querySelectorAll('.thumb').forEach(o => o.style.opacity = '1');
                            t.style.opacity = '0.5';
                        });
                        t.addEventListener('click', () => {
                            main.src = t.dataset.large;
                            document.querySelectorAll('.thumb').forEach(o => o.style.opacity = '1');
                            t.style.opacity = '0.5';
                        });
                    });
                });
                </script>
            <?php else: ?>
                <div>Entry not found.</div>
            <?php endif; ?>
        <?php else: ?>
            <?php
                $searchEntries = [];
                foreach ($tablesData as $tableName => $info) {
                    foreach ($info['rows'] as $row) {
                        $cardHtml = buildCardHtml($tableName, $row, $structures, $remoteHost, $currencyCode, $company);
                        $fieldsMeta = $structures[$tableName]['allFields'] ?? [];
                        $searchFields = [];
                        $searchFields[] = (string)($row['idpk'] ?? '');
                        if ($info['humanPrimary'] && isset($row[$info['humanPrimary']])) {
                            $searchFields[] = (string)$row[$info['humanPrimary']];
                        }
                        foreach ($fieldsMeta as $f => $meta) {
                            if (isset($row[$f])) {
                                $searchFields[] = (string)$row[$f];
                            }
                        }
                        $searchEntries[] = [
                            'html' => $cardHtml,
                            'search' => $searchFields
                        ];
                    }
                }
                $searchJson   = json_encode($searchEntries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $keywords = [];
                if ($ShopShowSuggestionCards) {
                    $tmpKeywords = extractTopKeywords($searchEntries);
                    shuffle($tmpKeywords);
                    $limit = $ShopShowRandomProductCards ? 20 : 30;
                    $keywords = array_slice($tmpKeywords, 0, $limit);
                }
                $keywordsJson = json_encode($keywords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            ?>
            <div id="search-wrapper">
                <div id="mode-toggle" title="you are currently in search mode, click to switch to SUPPORT BOT and have a chat">
                    <span id="toggle-search" class="active">🔍</span>
                    <span id="toggle-bot">🤖</span>
                </div>
                <input id="search-input" type="text" placeholder="search or type a question">
                <button id="send-button" class="control-button send-button" title="send">↗️</button>
            </div>
            <div id="suggestions"></div>
            <div id="search-results" class="shop-grid"></div>
            <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
            <script>
            var allEntries  = <?php echo $searchJson; ?>;
            var cardLookup = {};
            allEntries.forEach(function(e){
                var div=document.createElement('div');
                div.innerHTML=e.html;
                var el=div.firstElementChild;
                if(el && el.dataset.table && el.dataset.id){
                    cardLookup[el.dataset.table+'|'+el.dataset.id]=el.outerHTML;
                }
            });
            var suggestions = <?php echo $keywordsJson; ?>;
            </script>
        <?php endif; ?>
    <?php endif; ?>
<?php if (!$isAjax): ?>
</div>
<?php if ($company && empty($errorMsg)): ?>
<div id="page-footer">
    <br>
    <?php
        $infoParts = [];
        if (!empty($CompanyName)) {
            $infoParts[] = '<span><strong><a href="index.php?company=' . urlencode($company) . '">⭐ ' . strtoupper(htmlspecialchars($CompanyName)) . '</a></strong></span>';
        }
        $addrParts = [];
        if ($street || $houseNumber) {
            $addrParts[] = trim(($houseNumber ? $houseNumber . ' ' : '') . $street);
        }
        if ($city) $addrParts[] = $city;
        if ($zipCode) $addrParts[] = $zipCode;
        if ($country) $addrParts[] = $country;
        $address = implode(', ', array_filter($addrParts));
        if ($address) {
            $mapAddress   = urlencode($address);
            $addressUpper = mb_strtoupper($address);
            $infoParts[]  = '<span>📍 <a href="../UI/map.php?address=' . $mapAddress . '" target="_blank">' . htmlspecialchars($addressUpper) . '</a></span>';
        }
        if (!empty($connectEmail)) {
            $mailtoParams = '?subject=Hi&body=Hi,%0D%0A%0D%0A%0D%0A[ContentOfYourMessage]%0D%0A%0D%0A%0D%0A%0D%0AWith best regards,%0D%0A[YourName]';
            $mailtoLink   = 'mailto:' . $connectEmail . $mailtoParams;
            $infoParts[]  = '<span>✉️ <a href="' . htmlspecialchars($mailtoLink) . '">' . htmlspecialchars($connectEmail) . '</a></span>';
        }
        if (!empty($phoneNumberWork)) {
            $infoParts[] = '<span>📞 <a href="tel:' . htmlspecialchars($phoneNumberWork) . '" class="footer-phone">' . htmlspecialchars($phoneNumberWork) . '</a></span>';
        }
        if ($infoParts) {
            echo '<div class="company-info" style="text-align:center;margin-bottom:20px;">' . implode('', $infoParts) . '</div>';
        }
    ?>
    <div style="text-align:center; font-size: 0.8rem;">
        <a href="../index.php" style="opacity:0.4;margin-right:20px;">👑 TRAMANN PROJECTS</a>
        <a href="../imprint.php" style="opacity:0.3;margin-right:20px;">🖋️ IMPRINT</a>
        <a href="../DataSecurity.php" style="opacity:0.3;">🔒 DATA SECURITY</a>
    </div>
</div>
<?php endif; ?>
<div id="overlay"><div id="overlay-content"></div></div>
<div id="message-overlay"><div class="alert-box"><h3 id="message-title"></h3><p id="message-text"></p></div></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const container = document.querySelector('.container');
    const overlay   = document.getElementById('overlay');
    const content   = document.getElementById('overlay-content');
    const searchInp = document.getElementById('search-input');
    const results   = document.getElementById('search-results');
    const sugDiv    = document.getElementById('suggestions');
    const modeToggle = document.getElementById('mode-toggle');
    const searchIcon = document.getElementById('toggle-search');
    const botIcon = document.getElementById('toggle-bot');
    const sendButton = document.getElementById('send-button');
    let currentMode = 'search';
    let lastSearchHtml = '';
    let lastSearchQuery = '';
    const footer    = document.getElementById('page-footer');
    const cartIcon  = document.getElementById('cart-icon');
    const companyId = <?php echo json_encode($company); ?>;
    const currency  = <?php echo json_encode($currencyCode); ?>;
    const companyName = <?php echo json_encode($CompanyName ?? ''); ?>;
    const companyIBAN = <?php echo json_encode($CompanyIBAN ?? ''); ?>;
    const selectedTable = <?php echo json_encode($selectedTable); ?>;
    const selectedIdpk  = <?php echo json_encode($selectedIdpk); ?>;
    const params = new URLSearchParams(window.location.search);
    const autoSearch = params.get('autosearch');
    const allowItemComments = <?php echo json_encode((bool)$ShopAllowToAddCommentsNotesSpecialRequests); ?>;
    const allowAdditionalNotes = <?php echo json_encode((bool)$ShopAllowToAddAdditionalNotes); ?>;
    const targetCustomerEntity = <?php echo json_encode((int)$ShopTargetCustomerEntity); ?>;
    const centerOnInit = <?php echo json_encode(!$ShopShowRandomProductCards && !$ShopShowSuggestionCards); ?>;
    const hasSuggestions = Array.isArray(suggestions) && suggestions.length > 0;
    const supportHistoryKey = 'TRAMANNPROJECTS_SHOP_' + companyId + '_SupportBotHistory';

    function renderMarkdown(text){
        if(window.marked && window.DOMPurify){
            return DOMPurify.sanitize(marked.parse(text));
        }
        return text.replace(/</g,'&lt;');
    }

    function updateMode(mode){
        currentMode = mode;
        if(mode === 'search'){
            searchIcon.classList.add('active');
            botIcon.classList.remove('active');
            modeToggle.title = 'you are currently in search mode, click to switch to SUPPORT BOT and have a chat';
            searchInp.placeholder = 'search or type a question';
            results.classList.add('shop-grid');
            searchInp.value = lastSearchQuery;
            if(lastSearchHtml){
                results.innerHTML = lastSearchHtml;
                attachCardHandlers();
            } else {
                results.innerHTML = '';
            }
            if(sugDiv && hasSuggestions && searchInp.value.trim().length === 0){
                sugDiv.style.display = '';
            }
        } else {
            lastSearchHtml = results.innerHTML;
            lastSearchQuery = searchInp.value;
            searchIcon.classList.remove('active');
            botIcon.classList.add('active');
            modeToggle.title = 'you are currently chatting with SUPPORT BOT, click to switch to search mode';
            searchInp.placeholder = 'How can we help you?';
            results.classList.remove('shop-grid');
            if(sugDiv){
                sugDiv.style.display = 'none';
            }
            const history = loadSupportHistory();
            if(history.length > 0){
                renderSupportHistory(history);
            } else {
                results.innerHTML = '';
            }
        }
    }

    function shouldSwitchToBot(text){
        if(/[.!?]/.test(text)) return true;
        return text.trim().split(/\s+/).length >= 3;
    }

    function runSupport(){
        const q = searchInp.value.trim();
        if(q.length === 0){
            results.innerHTML = '';
            return;
        }
        const history = loadSupportHistory();
        const display = [{q:q,a:'...',entries:[]}, ...history].slice(0,10);
        renderSupportHistory(display);

        const fd = new FormData();
        fd.append('question', q);
        fd.append('history', JSON.stringify(history));
        fd.append('company', companyId);

        fetch('../BRAIN/SupportBot.php', {method:'POST', body: fd})
            .then(r => r.text())
            .then(t => {
                let data;
                try {
                    data = JSON.parse(t);
                } catch (e) {
                    console.error('⟪INVALID JSON FROM SUPPORT BOT⟫ ', t);
                    data = { message: 'We are very sorry, but there was an error.', entries: [] };
                }
                console.log('⟪RAW SEARCH TERMS⟫ ', data.search_terms);
                console.log('⟪RAW SEARCH RESULTS⟫ ', data.search_results);
                console.log('⟪RAW FINAL RESPONSE⟫', data.message);
                display[0].a = data.message || 'We are very sorry, but there was an error.';
                display[0].entries = Array.isArray(data.entries) ? data.entries : [];
                saveSupportHistory(display);
                renderSupportHistory(display);
            })
            .catch(err => {
                console.error(err);
                display[0].a = 'We are very sorry, but there was an error.';
                renderSupportHistory(display);
            });
    }

    function setCookie(name, value){
        const d = new Date();
        d.setTime(d.getTime() + 10*365*24*60*60*1000);
        document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/';
    }
    function setCookieHours(name, value, hours){
        const d = new Date();
        d.setTime(d.getTime() + hours*60*60*1000);
        document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/';
    }
    function getCookie(name){
        const v = document.cookie.split('; ').find(r => r.startsWith(name + '='));
        return v ? decodeURIComponent(v.split('=')[1]) : null;
    }
    function deleteCookie(name){
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
    }
    function loadSupportHistory(){
        const raw = getCookie(supportHistoryKey);
        if(!raw) return [];
        try { return JSON.parse(raw); } catch(e){ return []; }
    }
    function saveSupportHistory(arr){
        setCookieHours(supportHistoryKey, JSON.stringify(arr.slice(0,10)), 48);
    }
    function renderSupportHistory(arr){
        results.innerHTML = '';
        const frag = document.createDocumentFragment();
        arr.forEach((p, idx) => {
            const qDiv = document.createElement('div');
            qDiv.textContent = p.q;
            qDiv.style.textAlign = 'right';
            qDiv.style.fontStyle = 'italic';
            qDiv.style.display = 'block';
            const qOp = idx === 0 ? 0.5 : 0.3;
            qDiv.style.opacity = qOp;
            qDiv.dataset.baseOpacity = qOp;
            qDiv.classList.add('support-question');
            frag.appendChild(qDiv);

            const aDiv = document.createElement('div');
            if(p.a === '...'){
                aDiv.innerHTML = '<div class="loading-dots left"><span></span><span></span><span></span></div>';
            } else {
                aDiv.innerHTML = renderMarkdown(p.a);
            }
            aDiv.style.textAlign = 'left';
            aDiv.style.display = 'block';
            const aOp = idx === 0 ? 1 : 0.7;
            aDiv.style.opacity = aOp;
            aDiv.dataset.baseOpacity = aOp;
            aDiv.classList.add('support-answer');
            frag.appendChild(aDiv);

            if(Array.isArray(p.entries)){
                const spacer = document.createElement('div');
                spacer.style.height = '1em';
                frag.appendChild(spacer);

                const cardsDiv = document.createElement('div');
                cardsDiv.classList.add('shop-grid');
                p.entries.forEach(ent => {
                    const key = (ent.table || '') + '|' + (ent.idpk || '');
                    const html = cardLookup[key];
                    if(html){
                        const d = document.createElement('div');
                        d.innerHTML = html;
                        cardsDiv.appendChild(d.firstElementChild);
                    }
                });
                frag.appendChild(cardsDiv);
            }

            const sp = document.createElement('div');
            sp.style.height = '5em';
            frag.appendChild(sp);
        });
        results.appendChild(frag);
        attachCardHandlers();
    }
    let clickedEl = null;
    results.addEventListener('mouseover', ev => {
        const t = ev.target;
        if(t.dataset.baseOpacity){
            if(clickedEl && clickedEl !== t){
                clickedEl.style.opacity = clickedEl.dataset.baseOpacity;
                clickedEl = null;
            }
            t.style.opacity = '1';
        }
    });
    results.addEventListener('mouseout', ev => {
        const t = ev.target;
        if(t.dataset.baseOpacity && t !== clickedEl){
            t.style.opacity = t.dataset.baseOpacity;
        }
    });
    document.addEventListener('click', ev => {
        const t = ev.target;
        if(clickedEl && clickedEl !== t){
            clickedEl.style.opacity = clickedEl.dataset.baseOpacity;
            clickedEl = null;
        }
        if(results.contains(t) && t.dataset.baseOpacity){
            t.style.opacity = '1';
            clickedEl = t;
        }
    });
    if (targetCustomerEntity === 2) {
        setCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer', 'false');
    } else if (targetCustomerEntity === 3) {
        setCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer', 'true');
    }
    function itemCookies(){
        const prefix = 'TRAMANNPROJECTS_SHOP_' + companyId + '_';
        return document.cookie.split('; ').filter(c => c.startsWith(prefix));
    }
    function refreshCartIcon(glow=false){
        if(itemCookies().length > 0){
            cartIcon.classList.remove('cart-icon-hidden');
            if(glow){
                cartIcon.classList.add('glow');
                setTimeout(()=>cartIcon.classList.remove('glow'),1000);
            }
        } else {
            cartIcon.classList.add('cart-icon-hidden');
        }
    }

    refreshCartIcon();
    let paymentMethod = 'bank';
    let previousContent = '';
    let cartKeyHandler;

    function setupThumbHandlers(){
        const main = document.getElementById('main-image');
        if(!main) return;
        document.querySelectorAll('.thumb').forEach(t => {
            t.addEventListener('mouseover', () => {
                main.src = t.dataset.large;
                document.querySelectorAll('.thumb').forEach(o => o.style.opacity = '1');
                t.style.opacity = '0.5';
            });
            t.addEventListener('click', () => {
                main.src = t.dataset.large;
                document.querySelectorAll('.thumb').forEach(o => o.style.opacity = '1');
                t.style.opacity = '0.5';
            });
        });
    }

    function cardClickHandler(e){
        e.preventDefault();
        const card = this;
        const name = card.dataset.name || '';
        const display = name.toUpperCase();
        const img = card.querySelector('img') ? card.querySelector('img').src : '';
        const infoHtml = card.dataset.infoHtml || (card.querySelector('.info') ? card.querySelector('.info').innerHTML : '');
        const priceHtml = card.dataset.priceHtml || '';
        const idpk = card.dataset.id || '';
        const table = card.dataset.table || '';
        const price = card.dataset.price || '';

        overlay.style.display = 'block';
        container.style.display = 'none';
        if(footer) footer.style.display = 'none';
        window.scrollTo(0,0);

        const hasPrice = priceHtml && priceHtml.trim() !== '';
        let skeleton = '<div class="big-card">';
        skeleton += '<div class="big-card-header"><a href="#" class="return-link"><strong>◀️ RETURN</strong></a>';
        if(hasPrice){
            skeleton += '<a href="#" class="add-to-cart" data-table="'+table+'" data-id="'+idpk+'" data-name="'+name+'" data-price="'+price+'"><strong>🛒 ADD TO CART</strong></a>';
        }
        skeleton += '</div>';
        skeleton += '<div class="entry-layout">';
        skeleton += '<div class="left-section">';
        skeleton += '<h1 style="text-align:center">🟦 ' + (display || 'ENTRY') + ' (' + idpk + ')</h1>';
        if(img){
            skeleton += '<div class="image-viewer"><img id="main-image" src="' + img + '" loading="lazy"></div>';
        }
        skeleton += '</div>'; // left-section
        skeleton += '<div class="right-section">';
        if(hasPrice){
            skeleton += '<div class="price-preview">' + priceHtml + '</div>';
        }
        skeleton += (infoHtml || '');
        skeleton += '<div class="loading-dots"><span></span><span></span><span></span></div>';
        skeleton += '</div>';
        skeleton += '</div></div>'; // entry-layout & big-card

        content.innerHTML = skeleton;

        fetch(card.href + '&ajax=1').then(r => r.text()).then(html => {
            content.innerHTML = html;
            setupThumbHandlers();
        });
    }

    function openEntryFromUrl(table, idpk){
        overlay.style.display = 'block';
        container.style.display = 'none';
        if(footer) footer.style.display = 'none';
        window.scrollTo(0,0);
        content.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';
        fetch('?company=' + companyId + '&table=' + encodeURIComponent(table) + '&idpk=' + encodeURIComponent(idpk) + '&ajax=1')
            .then(r => r.text())
            .then(html => {
                content.innerHTML = html;
                setupThumbHandlers();
            });
    }

    function attachCardHandlers(){
        const containers = [results];
        if(sugDiv) containers.push(sugDiv);
        containers.forEach(cont => {
            cont.querySelectorAll('.shop-card').forEach(card => {
                if(card.dataset.bound) return;
                card.dataset.bound = '1';
                card.addEventListener('click', cardClickHandler);
            });
        });
    }

    function toggleOpacity(el){
        if(el.type === 'checkbox') return;
        if(el.value) el.classList.add('has-value');
        else el.classList.remove('has-value');
    }

    function buildCart(){
        previousContent = '';
        if(overlay.style.display === 'block' && content.innerHTML.trim() !== ''){
            previousContent = content.innerHTML;
        }
        overlay.style.display = 'block';
        container.style.display = 'none';
        if(footer) footer.style.display = 'none';
        window.scrollTo(0,0);
        cartIcon.classList.add('cart-icon-hidden');

        const items = itemCookies().map(c => {
            const [k,v] = c.split('=');
            let obj = {};
            try { obj = JSON.parse(decodeURIComponent(v)); } catch(e) {}
            obj.cookie = k;
            return obj;
        }).sort((a,b) => (a.name||'').localeCompare(b.name||''));

        let html = '<div class="big-card">';
        html += '<div class="big-card-header"><a href="#" class="return-link"><strong>◀️ RETURN</strong></a><div id="cart-total" class="price-preview" title="total price of current shopping cart"></div></div>';
        html += '<div id="cart-items"><br><br>';
        items.forEach(it => {
            const qty = it.quantity || 1;
            const subtotal = (it.price||0)*qty;
            html += '<div class="cart-item" data-cookie="'+it.cookie+'" data-price="'+(it.price||0)+'" data-id="'+(it.id||'')+'">';
            html += '<table class="cart-table"><tr>';
            html += '<td><strong><a href="?company='+companyId+'&table='+encodeURIComponent(it.table||'')+'&idpk='+encodeURIComponent(it.id||'')+'">🟦 '+ (it.name||'').toUpperCase() +' ('+(it.id||'')+')</a></strong></td>';
            html += '<td><input type="number" class="cart-qty" min="1" title="quantity" value="'+qty+'"> x <span title="price per unit">'+(it.price||0)+' '+currency+'</span> = <span class="item-total" title="total price of line">'+subtotal.toFixed(2)+' '+currency+'</span></td>';
            html += '<td><span class="remove-item" title="remove" style="opacity:0.3;cursor:pointer;">❌</span></td>';
            html += '</tr>';
            if(allowItemComments){
                html += '<tr><td colspan="3"><textarea rows="1" class="item-comment customer-input" placeholder="comments, notes, special requests, ..." style="width:100%;margin-top:4px;">'+(it.comments||'')+'</textarea></td></tr>';
            }
            html += '</table>';
            html += '</div><br>';
        });
        html += '</div><br><br><br><br><br>';

        const defs = [
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer',t:'checkbox',l:'business customer'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_FirstName',t:'text',l:'first name',g:'person'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_LastName',t:'text',l:'last name',g:'person'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_CompanyName',t:'text',l:'company name',g:'business'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_street',t:'text',l:'street'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_HouseNumber',t:'text',l:'house number'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_city',t:'text',l:'city'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_ZIPCode',t:'text',l:'ZIP code'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_country',t:'text',l:'country'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressAndDeliveryAddressAreDifferent',t:'checkbox',l:'invoice address and delivery address are different'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededstreet',t:'text',l:'invoice address street',g:'invoice'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededHouseNumber',t:'text',l:'invoice address house number',g:'invoice'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededcity',t:'text',l:'invoice address city',g:'invoice'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededZIPCode',t:'text',l:'invoice address ZIP code',g:'invoice'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededcountry',t:'text',l:'invoice address country',g:'invoice'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_EmailAddress',t:'email',l:'email'},
            {n:'TRAMANNPROJECTS_SHOP_CUSTOMER_AdditionalNotes',t:'textarea',l:'additional notes'}
        ];
        if(!allowAdditionalNotes){
            const idx = defs.findIndex(d => d.n === 'TRAMANNPROJECTS_SHOP_CUSTOMER_AdditionalNotes');
            if(idx !== -1) defs.splice(idx,1);
        }
        if(targetCustomerEntity === 2 || targetCustomerEntity === 3){
            const idx = defs.findIndex(d => d.n === 'TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer');
            if(idx !== -1) defs.splice(idx,1);
        }
        html += '<div id="customer-fields">';
        defs.forEach(d=>{
            let val = getCookie(d.n);
            if(val === null){
                if(d.n === 'TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer' && targetCustomerEntity === 1){
                    val = 'true';
                    setCookie(d.n, 'true');
                } else {
                    val = '';
                }
            }
            let extra = '';
            if(d.t==='checkbox'){
                extra = val==='true' ? ' checked' : '';
                html += '<div class="customer-field"'+(d.g?' data-group="'+d.g+'"':'')+'><label>'+d.l+'</label><input type="checkbox" class="customer-input" data-name="'+d.n+'"'+extra+'></div>';
            } else if(d.t==='textarea'){
                html += '<div class="customer-field"'+(d.g?' data-group="'+d.g+'"':'')+'><label>'+d.l+'</label><textarea rows="2" class="customer-input" data-name="'+d.n+'" placeholder="'+d.l.toLowerCase()+'">'+val+'</textarea></div>';
            } else if(d.t==='email'){
                html += '<div class="customer-field"'+(d.g?' data-group="'+d.g+'"':'')+'><label>'+d.l+'</label><input type="email" class="customer-input" data-name="'+d.n+'" placeholder="'+d.l.toLowerCase()+'" value="'+val+'"></div>';
            } else {
                html += '<div class="customer-field"'+(d.g?' data-group="'+d.g+'"':'')+'><label>'+d.l+'</label><input type="text" class="customer-input" data-name="'+d.n+'" placeholder="'+d.l.toLowerCase()+'" value="'+val+'"></div>';
            }
        });
        html += '</div><br><br><br><br><br>';

        html += '<div id="payment-options" style="margin-top:10px;">';
        html += '<div class="pay-option" data-method="bank" style="opacity:1;"><strong>BANK TRANSFER FOR PAYMENT</strong><span id="bank-transfer-note" style="opacity:0.5;'+(getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer')==='true' ? '' : 'display:none;')+'">(an invoice will also be sent later)</span></div>';
        html += '<div class="pay-option" data-method="invoice"><strong>INVOICE ME <span style="opacity:0.5;">(ONLY FOR REGISTERED CUSTOMERS)</span></strong></div>';
        html += '</div>';
        html += '<br><div id="bank-info" style="margin-top:10px;font-weight:bold;font-size:1.5rem;text-align:center;color:var(--accent-color)">Please transfer <span id="bank-total"></span> to '+companyIBAN+' ('+companyName+').</div>';
        html += '<br><br><br><br><br>';

        html += '<button id="buy-btn" style="margin-top:10px;margin-bottom:100px;opacity:0.3;">'
            + (paymentMethod === 'bank' ? '🛒 PAYMENT MADE' : '🛒 BUY')
            + '</button>';
        html += '</div>';
        content.innerHTML = html;
        bindCartEvents();
        toggleCustomerFields();
        updateTotals();
        buildBuyButton();
    }

    function updateTotals(){
        let total = 0;
        document.querySelectorAll('.cart-item').forEach(div => {
            const price = parseFloat(div.dataset.price || '0');
            const qty = parseInt(div.querySelector('.cart-qty').value) || 1;
            const subtotal = price * qty;
            div.querySelector('.item-total').textContent = subtotal.toFixed(2) + ' ' + currency;
            total += subtotal;
            const name = div.dataset.cookie;
            let data = JSON.parse(getCookie(name) || '{}');
            data.quantity = qty;
            setCookie(name, JSON.stringify(data));
        });
        const totalDiv = document.getElementById('cart-total');
        const bankTotal = document.getElementById('bank-total');
        if(totalDiv) totalDiv.innerHTML = '<b>' + total.toFixed(2) + ' ' + currency + '</b>';
        if(bankTotal) bankTotal.innerHTML = '<b>' + total.toFixed(2) + ' ' + currency + '</b>';
    }

    function bindCartEvents(){
        document.querySelectorAll('.cart-qty').forEach(inp => {
            inp.addEventListener('change', () => { updateTotals(); buildBuyButton(); });
        });
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const parent = btn.closest('.cart-item');
                deleteCookie(parent.dataset.cookie);
                parent.remove();
                updateTotals();
                buildBuyButton();
            });
        });
        if(allowItemComments){
            document.querySelectorAll('.item-comment').forEach(inp => {
                toggleOpacity(inp);
                inp.addEventListener('focus', () => { inp.rows = 2; });
                inp.addEventListener('blur', () => { inp.rows = 1; });
                inp.addEventListener('input', () => {
                    const parent = inp.closest('.cart-item');
                    let data = JSON.parse(getCookie(parent.dataset.cookie) || '{}');
                    data.comments = inp.value;
                    setCookie(parent.dataset.cookie, JSON.stringify(data));
                    toggleOpacity(inp);
                });
            });
        }
        document.querySelectorAll('#customer-fields .customer-input').forEach(inp => {
            toggleOpacity(inp);
            inp.addEventListener('input', () => {
                const name = inp.dataset.name;
                if(inp.type === 'checkbox'){ setCookie(name, inp.checked ? 'true':'false'); }
                else { setCookie(name, inp.value); }
                toggleCustomerFields();
                toggleOpacity(inp);
                buildBuyButton();
            });
            if(inp.type === 'checkbox'){
                inp.addEventListener('change', () => {
                    setCookie(inp.dataset.name, inp.checked ? 'true':'false');
                    toggleCustomerFields();
                    buildBuyButton();
                });
            }
        });
        const notes = document.querySelector('textarea[data-name="TRAMANNPROJECTS_SHOP_CUSTOMER_AdditionalNotes"]');
        if(notes){
            notes.addEventListener('focus', () => { notes.rows = 4; });
            notes.addEventListener('blur', () => { notes.rows = 2; });
        }
        document.querySelectorAll('.pay-option').forEach(opt => {
            opt.addEventListener('click', () => {
                document.querySelectorAll('.pay-option').forEach(o => o.style.opacity='0.5');
                opt.style.opacity='1';
                paymentMethod = opt.dataset.method;
                toggleBankInfo();
                buildBuyButton();
            });
        });
        toggleBankInfo();
        bindCartNavigation();
    }

    function bindCartNavigation(){
        if(cartKeyHandler){
            document.removeEventListener('keydown', cartKeyHandler);
        }
        const isVisible = el => el.offsetParent !== null;
        const focusNext = (current, reverse=false) => {
            const inputs = Array.from(document.querySelectorAll('#cart-items .cart-qty, #cart-items .item-comment, #customer-fields .customer-input')).filter(isVisible);
            const index = inputs.indexOf(current);
            if(index !== -1){
                const next = reverse ? (inputs[index-1] || inputs[inputs.length-1]) : (inputs[index+1] || inputs[0]);
                if(next){
                    next.focus();
                    if(next.select) next.select();
                }
            }
        };
        cartKeyHandler = function(e){
            const target = e.target;
            const navKeys = ['ArrowDown','ArrowRight','ArrowUp','ArrowLeft','Enter'];
            const selector = '#cart-items .cart-qty, #cart-items .item-comment, #customer-fields .customer-input';
            if(!document.activeElement || !document.activeElement.matches(selector)){
                if(navKeys.includes(e.key)){
                    const first = Array.from(document.querySelectorAll(selector)).filter(isVisible)[0];
                    if(first){
                        first.focus();
                        if(first.select) first.select();
                        e.preventDefault();
                    }
                }
                return;
            }
            if(e.ctrlKey || e.metaKey || e.altKey) return;
            if(e.key === 'Enter'){
                const type = target.type || target.tagName.toLowerCase();
                if(type === 'textarea'){
                    return;
                } else if(type === 'checkbox'){
                    e.preventDefault();
                    target.checked = !target.checked;
                    target.dispatchEvent(new Event('change', {bubbles:true}));
                } else {
                    e.preventDefault();
                    focusNext(target);
                }
            } else if(['ArrowDown','ArrowRight'].includes(e.key)){
                e.preventDefault();
                focusNext(target);
            } else if(['ArrowUp','ArrowLeft'].includes(e.key)){
                e.preventDefault();
                focusNext(target, true);
            }
        };
        document.addEventListener('keydown', cartKeyHandler);
    }

    function updateBankTransferNote(){
        const note = document.getElementById('bank-transfer-note');
        const isBiz = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer') === 'true';
        if(note){
            note.style.display = isBiz ? 'inline' : 'none';
        }
    }

    function toggleBankInfo(){
        const info = document.getElementById('bank-info');
        if(info){
            info.style.display = paymentMethod === 'bank' ? 'block' : 'none';
        }
    }

    function toggleCustomerFields(){
        const isBiz = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer') === 'true';
        document.querySelectorAll('[data-group="person"]').forEach(el => { el.style.display = isBiz ? 'none':'block'; });
        document.querySelectorAll('[data-group="business"]').forEach(el => { el.style.display = isBiz ? 'block':'none'; });
        const diff = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressAndDeliveryAddressAreDifferent') === 'true';
        document.querySelectorAll('[data-group="invoice"]').forEach(el => { el.style.display = diff ? 'block':'none'; });
        updateBankTransferNote();
    }

    function requiredCustomerFilled(){
        if(itemCookies().length === 0) return false;
        const isBiz = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer') === 'true';
        const diff = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressAndDeliveryAddressAreDifferent') === 'true';
        if(isBiz){
            if(!getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_CompanyName')) return false;
        } else {
            if(!getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_FirstName') || !getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_LastName')) return false;
        }
        const baseFields = ['TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_street','TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_HouseNumber','TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_city','TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_ZIPCode','TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_country'];
        for(const f of baseFields){ if(!getCookie(f)) return false; }
        if(diff){
            const inv = ['TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededstreet','TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededHouseNumber','TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededcity','TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededZIPCode','TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededcountry'];
            for(const f of inv){ if(!getCookie(f)) return false; }
        }
        return true;
    }

    function buildMailBody(){
        const items = itemCookies().map(c => {
            const [k,v] = c.split('=');
            let o={}; try{o=JSON.parse(decodeURIComponent(v));}catch(e){}
            let line = (o.name||'').toUpperCase() + ' (' + (o.id||'') + ') - ' + (o.quantity||1) + 'x';
            if(allowItemComments && o.comments) line += ' - additional notes: ' + o.comments;
            return line;
        }).join(',\n');
        let body = '|#| GENERAL INFORMATION |#|\nA new order was submitted in the shop.\n';
        const isBiz = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_IsBusinessCustomer') === 'true';
        if(paymentMethod === 'bank'){
            body += 'Payment was made using bank transfer, please check this before proceding.';
            if(isBiz) body += ' (as a business customer, an invoice is requested too)';
        } else {
            body += 'Payment still has to be made, for that, an invoice is requested.';
        }
        body += '\n\n\n|#| ITEMS BOUGHT |#|\n' + items + '\n\n\n|#| CUSTOMER DETAILS |#|\n';
        let cust = '';
        if(isBiz){
            cust += 'is a business customer,\ncompany name: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_CompanyName')||'none') + ',\n';
        } else {
            cust += 'is a privat person,\nfirst name: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_FirstName')||'none') + ',\nlast name: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_LastName')||'none') + ',\n';
        }
        cust += 'house number: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_HouseNumber')||'none') + ',\n';
        cust += 'street: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_street')||'none') + ',\n';
        cust += 'city: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_city')||'none') + ',\n';
        cust += 'ZIP code: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_ZIPCode')||'none') + ',\n';
        cust += 'country: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_DeliveryAddress_country')||'none') + ',\n';
        const email = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_EmailAddress') || 'none';
        cust += 'email: ' + email + ',\n';
        const diff = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressAndDeliveryAddressAreDifferent') === 'true';
        if(diff){
            cust += 'invoice address and delivery address are different and below there follows the invoice address,\n';
            cust += 'house number: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededHouseNumber')||'none') + ',\n';
            cust += 'street: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededstreet')||'none') + ',\n';
            cust += 'city: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededcity')||'none') + ',\n';
            cust += 'ZIP code: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededZIPCode')||'none') + ',\n';
            cust += 'country: ' + (getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_InvoiceAddressIfNeededcountry')||'none') + ',\n';
        } else {
            cust += 'invoice address and delivery address are the same,\n';
        }
        if(allowAdditionalNotes){
            const notes = getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_AdditionalNotes') || 'none';
            cust += 'additional notes: ' + notes;
        }
        body += cust;
        return body;
    }

    function removeItemCookies(){
        itemCookies().forEach(c => deleteCookie(c.split('=')[0]));
        refreshCartIcon();
    }

    function showOrderAlert(success){
        overlay.style.display = 'none';
        container.style.display = 'none';
        if(footer) footer.style.display = 'none';
        if(cartIcon) cartIcon.classList.add('cart-icon-hidden');
        const box   = document.getElementById('message-overlay');
        const title = document.getElementById('message-title');
        const text  = document.getElementById('message-text');
        if(success){
            title.textContent = '✅ SUCCESS';
            text.textContent  = 'Your order was sent successfully.';
        } else {
            title.textContent = '❌ ERROR';
            text.textContent  = 'We are very sorry, but there was an error in processing your order, please try again.';
        }
        box.style.display = 'flex';
        setTimeout(() => {
            if(success){
                window.location.reload();
            } else {
                box.style.display = 'none';
                buildCart();
            }
        }, 1200);
    }

    function sendOrder(){
        const btn = document.getElementById('buy-btn');
        if(!btn) return;
        btn.disabled = true;
        fetch('?company=<?php echo urlencode($company); ?>&send=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                message: buildMailBody(),
                customerEmail: getCookie('TRAMANNPROJECTS_SHOP_CUSTOMER_EmailAddress') || ''
            })
        })
        .then(r => r.json())
        .then(d => {
            if(d.success){
                removeItemCookies();
                showOrderAlert(true);
            } else {
                showOrderAlert(false);
            }
        })
        .catch(() => showOrderAlert(false))
        .finally(() => { btn.disabled = false; });
    }

    function buildBuyButton(){
        const btn = document.getElementById('buy-btn');
        if(!btn) return;
        btn.textContent = paymentMethod === 'bank' ? '🛒 PAYMENT MADE' : '🛒 BUY';
        if(requiredCustomerFilled()){
            btn.style.opacity = '1';
            btn.onclick = function(){ sendOrder(); };
        } else {
            btn.style.opacity = '0.3';
            btn.onclick = function(ev){ ev.preventDefault(); };
        }
    }

    function hashWord(str){
        let h = 0;
        for(let i=0; i < str.length; i++){
            h = Math.imul(31, h) + str.charCodeAt(i);
            h |= 0;
        }
        return h >>> 0;
    }

    function gradientForWord(word){
        const lightPalettes = [
            ['#ff9a9e','#fad0c4','#fbc2eb','#a1c4fd','#c2e9fb'],
            ['#d4fc79','#96e6a1','#84fab0','#8fd3f4','#a6c0fe'],
            ['#f6d365','#fda085','#fbc2eb','#a1c4fd','#d4fc79']
        ];
        const darkPalettes  = [
            ['#663399','#6b5b95','#333333','#34495e','#5d4e60'],
            ['#2c3e50','#1a252f','#4b6587','#5d4e60','#40394a'],
            ['#40394a','#2f2f2f','#36454f','#5a3d5c','#3b3b3b']
        ];
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const palettes = isDark ? darkPalettes : lightPalettes;
        const hash = hashWord(word.toLowerCase());
        const colors = palettes[hash % palettes.length];
        const positions = [
            'circle at 25% 25%',
            'circle at 75% 25%',
            'circle at 25% 75%',
            'circle at 75% 75%'
        ];
        const layers = colors.map((col, i) => `radial-gradient(${positions[i % positions.length]}, ${col}, transparent 70%)`);
        layers.push(`linear-gradient(135deg, ${colors[0]}, ${colors[colors.length - 1]})`);
        return layers.join(', ');
    }

    function renderSuggestions(){
        if(!sugDiv) return;
        if(!hasSuggestions){
            sugDiv.style.display = 'none';
            return;
        }
        sugDiv.innerHTML = '';
        suggestions.forEach(item => {
            if(typeof item === 'string'){ item = {type:'word', value:item}; }
            if(item.type === 'word'){
                const word = item.value;
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'suggest-card';
                const deco = document.createElement('div');
                deco.className = 'deco';
                deco.style.background = gradientForWord(word);
                const label = document.createElement('div');
                label.className = 'label';
                label.textContent = word.toUpperCase();
                a.appendChild(deco);
                a.appendChild(label);
                a.addEventListener('click', ev => {
                    ev.preventDefault();
                    runSearch(true, word);
                });
                sugDiv.appendChild(a);
            } else if(item.type === 'entry'){
                const div = document.createElement('div');
                div.innerHTML = item.html;
                sugDiv.appendChild(div.firstElementChild);
            }
        });
        attachCardHandlers();
    }

    function runSearch(force = false, query = null){
        if(currentMode !== 'search') return;
        const rawQ = (query !== null ? query : searchInp.value).trim();
        const q = rawQ.toLowerCase();
        results.innerHTML = '';
        lastSearchQuery = rawQ;
        lastSearchHtml = '';

        if(q.length === 0){
            if(centerOnInit) container.classList.add('centered');
            if(sugDiv && hasSuggestions) sugDiv.style.display = '';
            return;
        }

        if(sugDiv && hasSuggestions) sugDiv.style.display = 'none';
        if(q.length < 3 && !force){
            if(centerOnInit) container.classList.add('centered');
            return;
        }

        const matches = [];
        allEntries.forEach(entry => {
            let best = null;
            entry.search.forEach((field, idx) => {
                const val = String(field).toLowerCase();
                const pos = val.indexOf(q);
                if(pos === -1) return;
                let s = idx * 10 + (pos === 0 ? 1 : 2);
                if(val === q) s -= 1;
                if(best === null || s < best) best = s;
            });
            if(best !== null){
                matches.push({score: best, html: entry.html});
            }
        });

        if(centerOnInit){
            if(matches.length > 0){
                container.classList.remove('centered');
            } else {
                container.classList.add('centered');
            }
        }

        matches.sort((a,b) => a.score - b.score);

        if(matches.length === 0){
            const msg = document.createElement('div');
            msg.style.textAlign = 'center';
            msg.style.gridColumn = '1 / -1';
            msg.textContent = `We are very sorry, but we couldn't find anything for "${rawQ}", please check spelling, try other options or switch to the SUPPORT BOT.`;
            results.appendChild(msg);
            lastSearchHtml = results.innerHTML;
            return;
        }

        const frag = document.createDocumentFragment();
        matches.forEach(m => {
            const div = document.createElement('div');
            div.innerHTML = m.html;
            frag.appendChild(div.firstElementChild);
        });
        results.appendChild(frag);
        attachCardHandlers();
        lastSearchHtml = results.innerHTML;
    }

    let timer;
    if(searchInp){
        searchInp.focus();
        searchInp.select();
        sendButton.classList.toggle('visible', searchInp.value.trim().length > 0);
        searchInp.addEventListener('input', () => {
            clearTimeout(timer);
            const val = searchInp.value;
            sendButton.classList.toggle('visible', val.trim().length > 0);
            if(currentMode === 'search'){
                if(shouldSwitchToBot(val)){
                    updateMode('bot');
                    return;
                }
                timer = setTimeout(runSearch, 400);
            }
        });
        searchInp.addEventListener('keydown', ev => {
            if(ev.key === 'Enter'){
                ev.preventDefault();
                clearTimeout(timer);
                if(currentMode === 'search'){
                    runSearch(true);
                } else {
                    runSupport();
                }
            }
        });
    }
    modeToggle.addEventListener('click', () => {
        clearTimeout(timer);
        updateMode(currentMode === 'search' ? 'bot' : 'search');
    });
    sendButton.addEventListener('click', () => {
        clearTimeout(timer);
        if(currentMode === 'search'){
            runSearch(true);
        } else {
            runSupport();
        }
    });
    renderSuggestions();
    runSearch();
    fetch('?company=<?php echo urlencode($company); ?>&suggest=1')
        .then(r => r.json())
        .then(data => { suggestions = data; renderSuggestions(); })
        .catch(() => {});

    function closeOverlay(){
        if(previousContent){
            content.innerHTML = previousContent;
            previousContent = '';
            overlay.style.display = 'block';
            container.style.display = 'none';
            if(footer) footer.style.display = 'none';
            setupThumbHandlers();
        } else {
            overlay.style.display = 'none';
            content.innerHTML = '';
            container.style.display = '';
            if(footer) footer.style.display = '';
        }
        refreshCartIcon();
        if(cartKeyHandler){
            document.removeEventListener('keydown', cartKeyHandler);
            cartKeyHandler = null;
        }
    }

    document.addEventListener('click', function(ev){
        if(ev.target.closest('.return-link')){
            ev.preventDefault();
            closeOverlay();
        } else if(ev.target.closest('.add-to-cart')){
            ev.preventDefault();
            const btn = ev.target.closest('.add-to-cart');
            const table = btn.dataset.table;
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            const price = parseFloat(btn.dataset.price || '0');
            const key = 'TRAMANNPROJECTS_SHOP_' + companyId + '_' + table + '_' + id;
            let data = {};
            try { data = JSON.parse(getCookie(key) || '{}'); } catch(e) {}
            data.table = table;
            data.id = id;
            data.name = name;
            data.price = price;
            data.quantity = (data.quantity || 0) + 1;
            if(allowItemComments){
                data.comments = data.comments || '';
            }
            setCookie(key, JSON.stringify(data));
            refreshCartIcon(true);
        } else if(ev.target.closest('#cart-icon')){
            ev.preventDefault();
            ev.stopPropagation();
            buildCart();
        }
    });

    document.addEventListener('keydown', function(ev){
        if(ev.key === 'Escape' && overlay.style.display !== 'none'){
            ev.preventDefault();
            closeOverlay();
        }
    });

    overlay.addEventListener('click', function(ev){
        if(!ev.target.closest('.big-card')){
            closeOverlay();
        }
    });
    document.querySelectorAll('.footer-phone').forEach(function(a){
        a.addEventListener('click', function(ev){
            var cleaned = a.href.replace(/^tel:/, '');
            if(!confirm('Do you want to call ' + cleaned + '?')){
                ev.preventDefault();
            }
        });
    });

     if(autoSearch || (selectedTable && selectedIdpk)){
        if(autoSearch && searchInp){
            searchInp.value = autoSearch;
            runSearch(true);
            params.delete('autosearch');
        }
        if(selectedTable && selectedIdpk){
            openEntryFromUrl(selectedTable, selectedIdpk);
            params.delete('table');
            params.delete('idpk');
        }
        const newQuery = params.toString();
        const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
        window.history.replaceState({}, '', newUrl);
    }
});
</script>
</body>
</html>
<?php endif; ?>
