<?php
ob_start();  // start buffering

require_once('../config.php');
require_once('header.php');

// Security checks: ensure the user has access to the TRAMANNDB and is an admin
if ($user['DbUseTRAMANNDB'] != 1) {
    // If DbUseTRAMANNDB is not enabled, abort
    exit;
}

// Retrieve the label from the query string
$label = isset($_GET['label']) ? strtoupper(trim($_GET['label'])) : '';

// If it doesn't start with CHATDBSEARCH, output nothing
if (strpos($label, 'CHATDBSEARCH') !== 0) {
    exit;
}

// Split the label by the “|#|” delimiter
$parts = array_map('trim', explode('|#|', $label));

// We expect the format:
// [0] => "CHATDBSEARCH"
// [1] => "TableName1"
// [2] => "42,23,1"
// [3] => "TableName2"
// [4] => "3,4"
// … and so on.
//
// We will iterate over table names at odd indexes (1, 3, 5, …) and their corresponding ID lists at even indexes (2, 4, 6, …).
//
$adminId = $_SESSION['IdpkOfAdmin'];

// Helper: check if a file extension is an image
function isImageExtension(string $ext): bool {
    $ext = strtolower($ext);
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
}

// Start the flex container
$outputHtml = '<span class="dbpreview-container">';

for ($i = 1; $i < count($parts); $i += 2) {
    $table = $parts[$i] ?? '';
    $rawIds = $parts[$i + 1] ?? '';
    if (!$table || !$rawIds) {
        continue;
    }

    // Sanitize table name (allow only alphanumeric and underscore)
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    if (empty($table)) {
        continue;
    }

    // Split IDs
    $idList = array_filter(array_map('trim', explode(',', $rawIds)), fn($id) => $id !== '');

    foreach ($idList as $idRaw) {
        $id = intval($idRaw);
        if ($id <= 0) {
            continue;
        }

        // Verify ownership
        $stmtCheck = $pdo->prepare("SELECT 1 FROM `$table` WHERE `idpk` = :idpk AND `IdpkOfAdmin` = :adminIdpk LIMIT 1");
        $stmtCheck->bindParam(':idpk', $id, PDO::PARAM_INT);
        $stmtCheck->bindParam(':adminIdpk', $adminId, PDO::PARAM_INT);
        $stmtCheck->execute();

        if (!$stmtCheck->fetchColumn()) {
            continue;
        }

        // Determine upload directory
        $uploadDir = realpath(__DIR__ . "/../UPLOADS/TDB/$table");
        if (!$uploadDir || !is_dir($uploadDir)) {
            // No directory → just show idpk
            $entryUrl = './entry.php?table=' . urlencode($table) . '&idpk=' . $id;
            $outputHtml .= '
                <span class="dbpreview-item image-dbpreview">
                    <a href="' . $entryUrl . '" class="dbpreview-link">
                        <span class="dbpreviewidpk-name">'
                            . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') .
                        '</span>
                    </a>
                </span>';
            continue;
        }

        // Find files matching "<id>_*.*"
        $pattern = $uploadDir . DIRECTORY_SEPARATOR . $id . "_*.*";
        $allFiles = glob($pattern);
        $imageFound = false;
        $imgUrl = '';

        if (!empty($allFiles)) {
            // Sort by the numeric part after the underscore
            usort($allFiles, function($a, $b) {
                preg_match('/_(\d+)\./', basename($a), $mA);
                preg_match('/_(\d+)\./', basename($b), $mB);
                $numA = isset($mA[1]) ? intval($mA[1]) : 0;
                $numB = isset($mB[1]) ? intval($mB[1]) : 0;
                return $numA <=> $numB;
            });

            // Pick the first valid image
            foreach ($allFiles as $filePath) {
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                if (isImageExtension($extension)) {
                    // Build a URL relative to webroot, with cache-buster
                    $relativePath = str_replace(realpath(__DIR__ . '/..'), '', $filePath);
                    $imgUrl = htmlspecialchars($relativePath . '?v=' . time(), ENT_QUOTES, 'UTF-8');
                    $imageFound = true;
                    break;
                }
            }
        }

        // Output each preview as a <span>
        if ($imageFound) {
            // Dynamically generate the entry.php link with the current $table and $id
            $entryUrl = './entry.php?table=' . urlencode($table) . '&idpk=' . $id;

            $outputHtml .= '
                <span class="dbpreview-item image-dbpreview">
                    <a href="' . $entryUrl . '" class="dbpreview-link">
                        <img src="' . $imgUrl . '">
                        <span class="dbpreviewidpk-name">' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '</span>
                    </a>
                </span>';
        } else {
            // No image found → just show the ID
            $outputHtml .= '
                <span class="dbpreview-item image-dbpreview">
                    <a href="' . $entryUrl . '" class="dbpreview-link">
                        <span class="dbpreviewidpk-name">' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '</span>
                    </a>
                </span>';
        }
    }
}

ob_clean();

$outputHtml .= '</span>';
echo $outputHtml;
?>
<style>
/* 1) The scrollable “two rows” container */
.dbpreview-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;

  /* Let it size itself up to two rows, then scroll. */
  height: auto;          
  max-height: 360px;     /* ≈ 3 × (100px image + padding/gaps) */
  overflow-y: auto;      /* scrollbar ONLY if content > max-height */

  /* Start with no resize handle; JS will turn on resize: vertical only when needed */
  resize: none;          
  margin: 0;
  padding: 4px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background-color: var(--input-bg);
}

.search-dbpreview-container {
  margin: 0;   /* ensure the wrapper adds no extra top/bottom gap */
  padding: 0;
}

/* If you’re still using <span> for .dbpreview-container instead of <div> */
.dbpreview-container {
  vertical-align: top; /* prevents inline-element baseline gaps */
}


.dbpreview-item.image-dbpreview {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0;
  width: 100px;
  overflow: visible;
}

.dbpreview-item.image-dbpreview img {
  width: 100%;
  height: 100px;
  object-fit: cover;
  display: block;
}

.dbpreviewidpk-name {
  margin-top: 1px;
  text-align: center;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-color);
}

.dbpreview-link {
  display: block;
  text-align: center;
  width: 100%;
  height: 100%;
  text-decoration: none;
  color: inherit;
}

.dbpreview-link:hover {
  opacity: 0.8;
}
</style>
