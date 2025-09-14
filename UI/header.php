<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1) Determine if the current user is an admin
$isAdmin = isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'] === 1;

// 2) List pages that only admins may view
$adminOnlyPages = [
    'SetupCommunication.php',
    'SetupCompany.php',
    'SetupDatabases.php',
    'SetupDatabasesConnection.php',
    'SetupFiles.php',
    'SetupStargate.php',
    'SetupShop.php',
    'team.php'
];

// 3) Check if we're on one of those pages
$currentPage = basename($_SERVER['PHP_SELF']);

// 4) If this is an admin-only page but the user is NOT an admin, block access
if (in_array($currentPage, $adminOnlyPages, true) && ! $isAdmin) {
    // Option A: Redirect non-admins back to, say, index.php
    header('Location: index.php');
    exit();

    // Option B: Or, show a 403 Forbidden message instead:
    // http_response_code(403);
    // echo '<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>';
    // exit();
}

// Helper: check if someone is already logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// --- 1) AUTO‐LOGIN VIA COOKIE IF NOT ALREADY LOGGED IN ---
if (!isLoggedIn() && isset($_COOKIE['LoginToken'])) {
    require_once('../config.php');
    $token = $_COOKIE['LoginToken'];

    // 1a) Try to auto‐login as an admin
    $stmt = $pdo->prepare("
        SELECT *
        FROM admins
        WHERE LoginToken = ?
          AND LoginTokenExpiry > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        // Found a matching admin
        $_SESSION['user_id']   = (int)$admin['idpk'];
        $_SESSION['user_role'] = 'admin';
        // Keep the entire $admin row for later use
        $user = $admin;
    } else {
        // 1b) Not an admin → try to auto‐login as a normal user
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE LoginToken = ?
              AND LoginTokenExpiry > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $normalUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($normalUser) {
            // Found a matching user
            $_SESSION['user_id']   = (int)$normalUser['idpk'];
            $_SESSION['user_role'] = 'user';
            // Keep the entire users‐row for later use
            $user = $normalUser;
        }
    }
}

// --- 2) REDIRECT “NOT LOGGED IN” USERS OFF PROTECTED PAGES ---
$allowedPages = [
    'login.php',
    'CreateAccount.php',
    'logout.php',
    'ResetPassword.php',
    'ForgotPassword.php',
    'LandingPage.php',
    'map.php',
];
$currentPage = basename($_SERVER['PHP_SELF']);

if (!isLoggedIn() && !in_array($currentPage, $allowedPages, true)) {
    header('Location: login.php');
    exit();
}

// --- 3) IF LOGGED IN, FETCH ALL COLUMNS INTO $user AND SET SESSION VARS ---
$darkMode = false;

// Ensure tooltip-related variables exist even when not logged in
$humanPrimaryMap = [];
$previewFieldsMap = [];
$ExtendedBaseDirectoryCode = '';

if (isLoggedIn()) {
    require_once('../config.php');

    if ($_SESSION['user_role'] === 'admin') {
        // a) If we didn't already fetch $admin above, grab it now
        if (!isset($user) || !isset($user['idpk']) || $user['idpk'] !== $_SESSION['user_id']) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM admins
                WHERE idpk = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $user = $admin;
        }

        // b) Populate session fields (now that $user contains ALL admin columns)
        $_SESSION['IsAdmin']      = 1;
        $_SESSION['IdpkOfAdmin']  = (int)$user['idpk'];

        $_SESSION['CompanyName']  = $user['CompanyName'] ?? '';
        $CompanyNameCompressed = str_replace(' ', '', $_SESSION['CompanyName']);

        $_SESSION['street']  = $user['street'] ?? '';
        $_SESSION['HouseNumber']  = $user['HouseNumber'] ?? '';
        $_SESSION['ZIPCode']  = $user['ZIPCode'] ?? '';
        $_SESSION['city']  = $user['city'] ?? '';
        $_SESSION['country']  = $user['country'] ?? '';
        $_SESSION['CurrencyCode']  = $user['CurrencyCode'] ?? '';
        $_SESSION['CompanyVATID']  = $user['CompanyVATID'] ?? '';
        $_SESSION['CompanyOpeningHours']  = $user['CompanyOpeningHours'] ?? '';
        $_SESSION['CompanyShortDescription']  = $user['CompanyShortDescription'] ?? '';
        $_SESSION['CompanyLongDescription']  = $user['CompanyLongDescription'] ?? '';
        $_SESSION['CompanyIBAN']  = $user['CompanyIBAN'] ?? '';
        $_SESSION['AccentColorForPDFCreation']  = $user['AccentColorForPDFCreation'] ?? '#2980b9';
        $_SESSION['AdditionalTextForInvoices']  = $user['AdditionalTextForInvoices'] ?? '';
        $_SESSION['CompanyPaymentDetails']      = $user['CompanyPaymentDetails'] ?? '';
        $_SESSION['CompanyDeliveryReceiptDetails'] = $user['CompanyDeliveryReceiptDetails'] ?? '';
        $_SESSION['CompanyBrandingInformation']  = $user['CompanyBrandingInformation'] ?? '';
        $_SESSION['CompanyBrandingInformationForImages']  = $user['CompanyBrandingInformationForImages'] ?? '';

        $_SESSION['ActionBuying']  = $user['ActionBuying'] ?? '';
        $_SESSION['ActionSelling'] = $user['ActionSelling'] ?? '';

        $_SESSION['ConnectEmailWritingInstructions'] = $user['ConnectEmailWritingInstructions'] ?? '';
        $_SESSION['TRAMANNAPIAPIKey']                = $user['APIKey'] ?? '';
        $_SESSION['TimestampCreation']                = $user['TimestampCreation'] ?? '';
        $darkMode = (bool)$user['darkmode'] ?? '';

        $_SESSION['ConnectEmailFull']  = $user['ConnectEmail'] ?? '';
        $_SESSION['ConnectPasswordFull']  = $user['ConnectPassword'] ?? '';
        $_SESSION['ConnectServerName']  = $user['ConnectServerName'] ?? '';
        $_SESSION['ConnectPort ']  = $user['ConnectPort '] ?? '';
        $_SESSION['ConnectEncryption']  = $user['ConnectEncryption'] ?? '';

        $_SESSION['DbHost']  = $user['DbHost'] ?? '';
        $_SESSION['DbPort']  = $user['DbPort'] ?? '';
        $_SESSION['DbName']  = $user['DbName'] ?? '';
        $_SESSION['DbUser']  = $user['DbUser'] ?? '';
        $_SESSION['DbPassword']  = $user['DbPassword'] ?? '';
        $_SESSION['DbDriver']  = $user['DbDriver'] ?? 'mysql';
        $_SESSION['DbUseTRAMANNDB']  = $user['DbUseTRAMANNDB'] ?? 0;

    } else {
        // ROLE = 'user'
        // a) If we didn’t already fetch $normalUser above, grab it now
        if (!isset($user) || $user['idpk'] !== $_SESSION['user_id']) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE idpk = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $normalUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $user = $normalUser;
        }

        // b) We need that user’s parent admin row (so we can pull admin columns too)
        $parentAdminId = (int)$user['IdpkOfAdmin'];
        $stmt = $pdo->prepare("
            SELECT *
            FROM admins
            WHERE idpk = ?
            LIMIT 1
        ");
        $stmt->execute([$parentAdminId]);
        $parentAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

        // c) Populate session fields
        $_SESSION['IsAdmin']      = 0;
        $_SESSION['IdpkOfAdmin']  = $parentAdminId;

        $_SESSION['CompanyName']  = $parentAdmin['CompanyName'] ?? '';
        $CompanyNameCompressed = str_replace(' ', '', $_SESSION['CompanyName']);

        $_SESSION['street']  = $parentAdmin['street'] ?? '';
        $_SESSION['HouseNumber']  = $parentAdmin['HouseNumber'] ?? '';
        $_SESSION['ZIPCode']  = $parentAdmin['ZIPCode'] ?? '';
        $_SESSION['city']  = $parentAdmin['city'] ?? '';
        $_SESSION['country']  = $parentAdmin['country'] ?? '';
        $_SESSION['CurrencyCode']  = $parentAdmin['CurrencyCode'] ?? '';
        $_SESSION['CompanyVATID']  = $parentAdmin['CompanyVATID'] ?? '';
        $_SESSION['CompanyOpeningHours']  = $parentAdmin['CompanyOpeningHours'] ?? '';
        $_SESSION['CompanyShortDescription']  = $parentAdmin['CompanyShortDescription'] ?? '';
        $_SESSION['CompanyLongDescription']  = $parentAdmin['CompanyLongDescription'] ?? '';
        $_SESSION['CompanyIBAN']  = $parentAdmin['CompanyIBAN'] ?? '';
        $_SESSION['AccentColorForPDFCreation']  = $parentAdmin['AccentColorForPDFCreation'] ?? '#2980b9';
        $_SESSION['AdditionalTextForInvoices']  = $parentAdmin['AdditionalTextForInvoices'] ?? '';
        $_SESSION['CompanyPaymentDetails']      = $parentAdmin['CompanyPaymentDetails'] ?? '';
        $_SESSION['CompanyDeliveryReceiptDetails'] = $parentAdmin['CompanyDeliveryReceiptDetails'] ?? '';
        $_SESSION['CompanyBrandingInformation']  = $parentAdmin['CompanyBrandingInformation'] ?? '';
        $_SESSION['CompanyBrandingInformationForImages']  = $parentAdmin['CompanyBrandingInformationForImages'] ?? '';

        $_SESSION['ActionBuying']  = $parentAdmin['ActionBuying'] ?? '';
        $_SESSION['ActionSelling'] = $parentAdmin['ActionSelling'] ?? '';

        $_SESSION['ConnectEmailWritingInstructions'] = $parentAdmin['ConnectEmailWritingInstructions'] ?? '';
        $_SESSION['TRAMANNAPIAPIKey']                = $parentAdmin['APIKey'] ?? '';
        $_SESSION['TimestampCreation']                = $parentAdmin['TimestampCreation'] ?? '';
        $darkMode = (bool)$parentAdmin['darkmode'] ?? '';

        $_SESSION['ConnectEmailFull']  = $user['ConnectEmail'] ?? '';
        $_SESSION['ConnectPasswordFull']  = $user['ConnectPassword'] ?? '';
        $_SESSION['ConnectServerName']  = $parentAdmin['ConnectServerName'] ?? '';
        $_SESSION['ConnectPort ']  = $parentAdmin['ConnectPort '] ?? '';
        $_SESSION['ConnectEncryption']  = $parentAdmin['ConnectEncryption'] ?? '';

        $_SESSION['DbHost']  = $parentAdmin['DbHost'] ?? '';
        $_SESSION['DbPort']  = $parentAdmin['DbPort'] ?? '';
        $_SESSION['DbName']  = $parentAdmin['DbName'] ?? '';
        $_SESSION['DbUser']  = $parentAdmin['DbUser'] ?? '';
        $_SESSION['DbPassword']  = $parentAdmin['DbPassword'] ?? '';
        $_SESSION['DbDriver']  = $parentAdmin['DbDriver'] ?? 'mysql';
        $_SESSION['DbUseTRAMANNDB']  = $parentAdmin['DbUseTRAMANNDB'] ?? 0;
    }

    $BaseDirectoryCode   = $_SESSION['DbHost'] ?? ''; // this is something like: www.example.com
    $ExtendedBaseDirectoryCode = "https://" . $BaseDirectoryCode . "/STARGATE/";

    require_once('../SETUP/DatabaseTablesStructure.php');
    $humanPrimaryMap = [];
    foreach ($DatabaseTablesStructure as $tbl => $cols) {
        foreach ($cols as $colName => $cfg) {
            if (!empty($cfg['HumanPrimary'])) {
                $humanPrimaryMap[$tbl] = $colName;
                break;
            }
        }
    }

    $previewFieldsMap = [];
    foreach ($DatabaseTablesStructure as $tbl => $cols) {
        foreach ($cols as $colName => $cfg) {
            if (!empty($cfg['ShowInPreviewCard'])) {
                $previewFieldsMap[$tbl][$colName] = [
                    'label' => $cfg['label'] ?? $colName,
                    'type'  => $cfg['type']  ?? '',
                    'price' => !empty($cfg['price'])
                ];
            }
        }
    }
}







if (!function_exists('parseEmailPairs')) {
    function parseEmailPairs($emailsRaw, $passwordsRaw) {
        $emailsArray = preg_split('/[|,\s]+/', trim($emailsRaw));
        $passwordArray = preg_split('/[|,\s]+/', trim($passwordsRaw));
        $pairs = [];
        $max = max(count($emailsArray), count($passwordArray));
        for ($i = 0; $i < $max; $i++) {
            $email = trim($emailsArray[$i] ?? '');
            $password = trim($passwordArray[$i] ?? '');
            if ($email && $password && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $pairs[$email] = $password;
            }
        }
        return $pairs;
    }
}

$rawEmails = $_SESSION['ConnectEmailFull'] ?? '';
$rawPasswords = $_SESSION['ConnectPasswordFull'] ?? '';
$emailPairs = parseEmailPairs($rawEmails, $rawPasswords);
$_SESSION['ConnectEmailList'] = array_keys($emailPairs);
$selectedEmail = $_SESSION['ConnectEmailList'][0] ?? '';
if (!empty($_COOKIE['MainEmailForSending']) && in_array($_COOKIE['MainEmailForSending'], $_SESSION['ConnectEmailList'], true)) {
    $selectedEmail = $_COOKIE['MainEmailForSending'];
}
if ($selectedEmail) {
    setcookie('MainEmailForSending', $selectedEmail, time() + 10 * 365 * 24 * 60 * 60, '/');
}
$_SESSION['ConnectEmail'] = $selectedEmail;
$_SESSION['ConnectPassword'] = $emailPairs[$selectedEmail] ?? '';

// Session connection data
$ConnectEmail = $_SESSION['ConnectEmail'] ?? '';
$ConnectPassword = $_SESSION['ConnectPassword'] ?? '';
$ConnectServerName = $_SESSION['ConnectServerName'] ?? '';
$ConnectPort = $_SESSION['ConnectPort'] ?? 993;
$ConnectEncryption = $_SESSION['ConnectEncryption'] ?? 'ssl';

// Normalize encryption type
$ConnectEncryption = strtolower($ConnectEncryption);

// If user selected TLS but the port is 993, override to SSL
if ($ConnectEncryption === 'tls' && $ConnectPort == 993) {
    $ConnectEncryption = 'ssl';
}

// If invalid encryption, default to ssl
if (!in_array($ConnectEncryption, ['ssl', 'tls'])) {
    $ConnectEncryption = 'ssl';
}

$isAdmin = $_SESSION['IsAdmin'] ?? 0;

$emails = [];
$connectionSuccess = false;

// Try connecting to the mail server
$imapServerString = "{" . $ConnectServerName . ":" . $ConnectPort . "/imap/" . strtolower($ConnectEncryption) . "}INBOX";

// DEBUG to browser
echo "<script>console.log(" . json_encode([
    'serverString' => $imapServerString,
    'email' => $ConnectEmail,
    'port' => $ConnectPort,
    'encryption' => $ConnectEncryption,
]) . ");</script>";

if ($ConnectEmail && $ConnectPassword && $ConnectServerName) {
    $inbox = @imap_open($imapServerString, $ConnectEmail, $ConnectPassword);

    if (!$inbox) {
        $imapError = imap_last_error();
        echo "<script>console.error(" . json_encode("IMAP Error: $imapError") . ");</script>";
        error_log("IMAP Connection Error: " . $imapError); // Also log it server-side
    }

    if ($inbox) {
        $connectionSuccess = true;
    }
}
?>





<!DOCTYPE html>
<html lang="en" <?php echo $darkMode ? 'data-theme="dark"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRAMANN PROJECTS TNX API</title>
    <script>
        (function() {
            if (!document.documentElement.getAttribute('data-theme') && localStorage.getItem('darkmode') === '1') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="../logos/favicon.png">
</head>
<body<?php echo isset($bodyClass) ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES) . '"' : ''; ?>>
    <header>
        <nav>
            <a href="menu.php" class="menu-logo" title="TRAMANN PROJECTS TNX API" id="menuLogoLink">
                <img id="menuIcon" src="../logos/favicon.png" alt="TRAMANN">
            </a>
        </nav>
    </header>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to update logo based on theme
                function updateLogo() {
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    document.getElementById('menuIcon').src = isDark ? '../logos/TramannLogoWhite.png' : '../logos/favicon.png';
                }
            
                updateLogo();
            
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.attributeName === 'data-theme') {
                            updateLogo();
                        }
                    });
                });
            
                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-theme']
                });

                const logoLink = document.getElementById('menuLogoLink');
                const cameFromShop = document.referrer.includes('/SHOP/');
                if (logoLink && cameFromShop) {
                    const refUrl = new URL(document.referrer);
                    const company = refUrl.searchParams.get('company');
                    logoLink.href = '../SHOP/index.php' + (company ? `?company=${encodeURIComponent(company)}` : '');
                }
            
                // Add click‐and‐double‐click handler for logo
                <?php if (isLoggedIn()): ?>
                if (logoLink && !cameFromShop) {
                    let clickTimeout = null;
                
                    logoLink.addEventListener('click', function(e) {
                        e.preventDefault();
                    
                        // If current page is menu.php, go back in history on a single‐click
                        const currentPath = window.location.pathname.split('/').pop();
                        if (currentPath === 'menu.php') {
                            // Immediately go back instead of waiting for double‐click timing
                            window.history.back();
                            return;
                        }
                    
                        // Otherwise (if NOT on menu.php), handle single vs. double click
                        if (clickTimeout !== null) {
                            // Second click within 350ms → double‐click detected
                            clearTimeout(clickTimeout);
                            clickTimeout = null;
                            window.location.href = 'index.php';
                        } else {
                            // First click: wait 350ms to see if a second click follows
                            clickTimeout = setTimeout(() => {
                                window.location.href = 'menu.php'; // normal single‐click behavior
                                clickTimeout = null;
                            }, 350);
                        }
                    });
                }
                <?php endif; ?>
            });



            // Create one global tooltip div, styled and appended once
            const globalTooltip = document.createElement('div');
            globalTooltip.style.position = 'absolute';
            globalTooltip.style.display = 'none';
            globalTooltip.style.border = '3px solid var(--border-color)';
            globalTooltip.style.backgroundColor = 'var(--border-color)';
            globalTooltip.style.borderRadius = '12px';
            globalTooltip.style.zIndex = 9959;
            globalTooltip.style.overflow = 'hidden';
            globalTooltip.style.width = 'auto';
            globalTooltip.style.height = 'auto';
            globalTooltip.style.maxWidth = '300px';
            globalTooltip.style.pointerEvents = 'none';
            document.body.appendChild(globalTooltip);

            window.addEventListener('beforeunload', () => {
              globalTooltip.style.display = 'none';
            });

            // Cache to store image URL and preview info per link element
            const tooltipImageCache = new Map();
            const tooltipLabelCache = new Map();
            const tooltipPreviewCache = new Map();
            const humanPrimaryMap = <?php echo json_encode($humanPrimaryMap); ?>;
            const previewFieldsMap = <?php echo json_encode($previewFieldsMap); ?>;

            function fetchTooltipData(link, table, idpk) {
              if (tooltipLabelCache.has(link) && tooltipPreviewCache.has(link)) {
                return Promise.resolve({
                  label: tooltipLabelCache.get(link),
                  preview: tooltipPreviewCache.get(link)
                });
              }

              const humanField = humanPrimaryMap[table];
              const previewFields = previewFieldsMap[table] || {};
              const searchFields = {};
              if (humanField) searchFields[humanField] = { HumanPrimary: '1' };
              for (const f in previewFields) {
                if (f !== humanField && f !== 'idpk') {
                  searchFields[f] = { ...(searchFields[f] || {}), ShowInPreviewCard: '1' };
                }
              }

              const formData = new FormData();
              formData.append('action', 'checkLinkedEntry');
              formData.append('linkedId', idpk);
              formData.append('linkedTable', table);
              formData.append('linkedField', 'idpk');
              formData.append('searchFields', JSON.stringify(searchFields));

              return fetch('AjaxEntry.php', {
                method: 'POST',
                body: formData
              })
                .then(res => res.json())
                .then(data => {
                  let label = `ENTRY ${idpk}`;
                  let preview = [];
                  if (data.success && data.found) {
                    if (data.label) label = data.label.replace(/^🟦\s*/, '');
                    if (Array.isArray(data.preview)) preview = data.preview;
                  }
                  tooltipLabelCache.set(link, label);
                  tooltipPreviewCache.set(link, preview);
                  return { label, preview };
                })
                .catch(() => {
                  const fallback = { label: `ENTRY ${idpk}`, preview: [] };
                  tooltipLabelCache.set(link, fallback.label);
                  tooltipPreviewCache.set(link, fallback.preview);
                  return fallback;
                });
            }

            function addTooltipPreviews(container = document.body) {
              const entryLinks = container.querySelectorAll('a[href*="entry.php?table="]');
            
              entryLinks.forEach(link => {
                if (tooltipImageCache.has(link)) {
                  // Already processed this link; skip
                  return;
                }
            
                const url = new URL(link.href, window.location.origin);
                const table = url.searchParams.get("table");
                const idpk = url.searchParams.get("idpk");
                if (!table || !idpk) return;
            
                const extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                // const basePath = `../UPLOADS/TDB/${table}/${idpk}_0`;
                const ExtendedBaseDirectoryCodeUPLOADS = "<?php echo $ExtendedBaseDirectoryCode; ?>UPLOADS";
                const basePath = `${ExtendedBaseDirectoryCodeUPLOADS}/${table}/${idpk}_0`;
            
                // Try extensions async to find valid image URL
                // (async function tryExtensions() {
                //   for (const ext of extensions) {
                //     const imageUrl = `${basePath}.${ext}`;
                //     try {
                //       const response = await fetch(imageUrl, { method: 'HEAD' });
                //       if (response.ok && response.headers.get('Content-Type')?.startsWith('image')) {
                //         tooltipImageCache.set(link, imageUrl); // Cache the image URL
                //         break;
                //       }
                //     } catch (err) {
                //       // ignore errors and continue to next extension
                //     }
                //   }
                // })();

                (async function tryExtensions() {
                  for (const ext of extensions) {
                    const imageUrl = `${basePath}.${ext}`;
                    try {
                      const response = await fetch(imageUrl, { method: 'HEAD' });
                      if (response.ok && response.headers.get('Content-Type')?.startsWith('image')) {
                        tooltipImageCache.set(link, imageUrl); // Cache the image URL
                        break;
                      }
                    } catch (err) {
                      // ignore errors and continue to next extension
                    }
                  }
                })();

                // Prefetch tooltip data for faster display on hover
                fetchTooltipData(link, table, idpk);
            
                // Remove default tooltip title
                link.removeAttribute('title');
            
                // Add event listeners for tooltip show/move/hide
                link.addEventListener('mouseenter', (e) => {
                  const imgUrl = tooltipImageCache.get(link);
              
                  globalTooltip.innerHTML = '';
                  const wrapper = document.createElement('div');
                  wrapper.style.position = 'relative';

                  const overlay = document.createElement('div');
                  overlay.style.left = '0';
                  overlay.style.right = '0';
                  overlay.style.color = 'white';
                  overlay.style.fontWeight = 'bold';
                  overlay.style.fontSize = '12px';
                  overlay.style.textAlign = 'center';
                  overlay.style.padding = '2px 4px';
                  overlay.textContent = `ENTRY ${idpk}`;

                  if (imgUrl) {
                    globalTooltip.style.width = '300px';
                    globalTooltip.style.height = 'auto';
                    wrapper.style.width = '300px';
                    wrapper.style.height = '300px';

                    const img = new Image();
                    img.src = imgUrl;
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    img.style.display = 'block';
                    wrapper.appendChild(img);

                    overlay.style.position = 'absolute';
                    overlay.style.top = '0px';
                    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.3)';
                    wrapper.appendChild(overlay);
                  } else {
                    globalTooltip.style.width = 'auto';
                    globalTooltip.style.height = 'auto';
                    globalTooltip.style.maxWidth = '300px';
                    wrapper.style.padding = '4px';
                    overlay.style.position = 'relative';
                    overlay.style.backgroundColor = 'var(--border-color)';
                    overlay.style.color = 'var(--text-color)';
                    wrapper.appendChild(overlay);
                  }

                  const infoDiv = document.createElement('div');
                  infoDiv.style.backgroundColor = 'var(--border-color)';
                  infoDiv.style.color = 'var(--text-color)';
                  infoDiv.style.fontSize = '11px';
                  infoDiv.style.padding = '2px 4px';
                  infoDiv.style.wordBreak = 'break-word';

                  globalTooltip.appendChild(wrapper);
                  globalTooltip.appendChild(infoDiv);
                  globalTooltip.style.display = 'block';
                  positionTooltip(e);

                  const cachedLabel = tooltipLabelCache.get(link);
                  const cachedPreview = tooltipPreviewCache.get(link);
                  if (cachedLabel) {
                    overlay.textContent = cachedLabel;
                    infoDiv.innerHTML = formatPreview(cachedPreview || [], table);
                  } else {
                    fetchTooltipData(link, table, idpk).then(({label, preview}) => {
                      overlay.textContent = label;
                      infoDiv.innerHTML = formatPreview(preview, table);
                    });
                  }
                });
            
                link.addEventListener('mousemove', (e) => {
                  positionTooltip(e);
                });
            
                link.addEventListener('mouseleave', () => {
                  globalTooltip.style.display = 'none';
                });

                link.addEventListener('click', () => {
                  globalTooltip.style.display = 'none';
                });
              });

              const fileLinks = container.querySelectorAll('a[href*="FileViewer.php?table="]');

              fileLinks.forEach(link => {
                if (tooltipImageCache.has(link)) {
                  return;
                }

                const url = new URL(link.href, window.location.origin);
                const table = url.searchParams.get("table");
                const idpk = url.searchParams.get("idpk");
                const file = url.searchParams.get("file");
                if (!table || !idpk || !file) return;

                const extension = file.split('.').pop().toLowerCase();
                const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const isImage = imageExts.includes(extension);
                const ExtendedBaseDirectoryCodeUPLOADS = "<?php echo $ExtendedBaseDirectoryCode; ?>UPLOADS";
                const imageUrl = `${ExtendedBaseDirectoryCodeUPLOADS}/${table}/${file}`;
                tooltipImageCache.set(link, isImage ? imageUrl : '');

                fetchTooltipData(link, table, idpk);
                link.removeAttribute('title');

                link.addEventListener('mouseenter', (e) => {
                  const imgUrl = tooltipImageCache.get(link);

                  globalTooltip.innerHTML = '';
                  const wrapper = document.createElement('div');
                  wrapper.style.position = 'relative';

                  const overlay = document.createElement('div');
                  overlay.style.left = '0';
                  overlay.style.right = '0';
                  overlay.style.color = 'white';
                  overlay.style.fontWeight = 'bold';
                  overlay.style.fontSize = '12px';
                  overlay.style.textAlign = 'center';
                  overlay.style.padding = '2px 4px';

                  if (imgUrl) {
                    globalTooltip.style.width = '300px';
                    globalTooltip.style.height = 'auto';
                    wrapper.style.width = '300px';
                    wrapper.style.height = '300px';

                    const img = new Image();
                    img.src = imgUrl;
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    img.style.display = 'block';
                    wrapper.appendChild(img);

                    overlay.style.position = 'absolute';
                    overlay.style.top = '0px';
                    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.3)';
                    wrapper.appendChild(overlay);
                  } else {
                    globalTooltip.style.width = 'auto';
                    globalTooltip.style.height = 'auto';
                    globalTooltip.style.maxWidth = '300px';
                    wrapper.style.padding = '4px';
                    overlay.style.position = 'relative';
                    overlay.style.backgroundColor = 'var(--border-color)';
                    overlay.style.color = 'var(--text-color)';
                    wrapper.appendChild(overlay);
                  }

                  globalTooltip.appendChild(wrapper);
                  globalTooltip.style.display = 'block';
                  positionTooltip(e);

                  const cachedLabel = tooltipLabelCache.get(link);
                  const textPrefix = 'ATTACHMENT FOR ';
                  if (cachedLabel) {
                    overlay.textContent = textPrefix + cachedLabel;
                  } else {
                    fetchTooltipData(link, table, idpk).then(({label}) => {
                      overlay.textContent = textPrefix + label;
                    });
                  }
                });

                link.addEventListener('mousemove', (e) => {
                  positionTooltip(e);
                });

                link.addEventListener('mouseleave', () => {
                  globalTooltip.style.display = 'none';
                });

                link.addEventListener('click', () => {
                  globalTooltip.style.display = 'none';
                });
              });

              const contextLinks = container.querySelectorAll('.db-context-toggle[data-tooltip-html]');
              contextLinks.forEach(link => {
                link.removeAttribute('title');
                link.addEventListener('mouseenter', (e) => {
                  let html = decodeURIComponent(link.dataset.tooltipHtml || '');
                  html = html.replace(/<p>/gi, '').replace(/<\/p>/gi, '<br>');
                  const parts = html.split(/<br\s*\/?>/i);
                  if (parts.length > 15) {
                    html = parts.slice(0, 15).join('<br>') + '<br>...';
                  }
                  globalTooltip.innerHTML = '';
                  globalTooltip.style.width = 'auto';
                  globalTooltip.style.height = 'auto';
                  globalTooltip.style.maxWidth = '300px';
                  const wrapper = document.createElement('div');
                  wrapper.style.backgroundColor = 'var(--border-color)';
                  wrapper.style.color = 'var(--text-color)';
                  wrapper.style.fontSize = '11px';
                  wrapper.style.padding = '2px 4px';
                  wrapper.style.wordBreak = 'break-word';
                  wrapper.innerHTML = html;
                  globalTooltip.appendChild(wrapper);
                  globalTooltip.style.display = 'block';
                  positionTooltip(e);
                });
                link.addEventListener('mousemove', (e) => {
                  positionTooltip(e);
                });
                link.addEventListener('mouseleave', () => {
                  globalTooltip.style.display = 'none';
                });
                link.addEventListener('click', () => {
                  globalTooltip.style.display = 'none';
                });
              });
          
              function positionTooltip(e) {
                  const offset = 12;
                  const tooltipHeight = globalTooltip.offsetHeight || 0;
                  const tooltipWidth = globalTooltip.offsetWidth || 0;
                  let top = e.pageY - tooltipHeight - offset;
                  let left = e.pageX + 10;
                  if (top < window.scrollY) {
                    top = e.pageY + offset;
                  }
                  if (left + tooltipWidth > window.scrollX + window.innerWidth) {
                    left = e.pageX - tooltipWidth - 10;
                  }
                  globalTooltip.style.left = `${left}px`;
                  globalTooltip.style.top = `${top}px`;
                }

              function escapeHtml(str) {
                return str.replace(/&/g, '&amp;')
                          .replace(/</g, '&lt;')
                          .replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;')
                          .replace(/'/g, '&#39;');
              }

              function getRelativeDayText(dateStr) {
                const d = new Date(dateStr);
                if (isNaN(d)) return '';

                const now = new Date();
                const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const startDate  = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                const diffDays = Math.round((startDate - startToday) / 86400000);

                if (Math.abs(diffDays) > 365) return '';
                if (diffDays === 0) return '(today)';
                if (diffDays === -1) return '(yesterday)';
                if (diffDays === 1) return '(tomorrow)';
                if (diffDays < 0) return `(${Math.abs(diffDays)} days ago)`;
                return `(in ${diffDays} days)`;
              }

              function formatPreview(items, table) {
                const tableMap = previewFieldsMap[table] || {};
                return items.map(it => {
                  if (it.value === null || it.value === undefined || String(it.value) === '') {
                    return '';
                  }

                  let val = String(it.value);
                  const isNeg = !isNaN(parseFloat(val)) && parseFloat(val) < 0;
                  const isPrice = tableMap[it.field] && tableMap[it.field].price;

                  let relative = '';
                  if (it.type && it.type.includes('date')) {
                    relative = getRelativeDayText(val);
                  }

                  if (["text", "textarea"].includes(it.type)) {
                    const valEsc = escapeHtml(val);
                    let valHtml = valEsc;
                    if (isNeg) valHtml = `<span style="color:red;">${valHtml}</span>`;
                    if (isPrice) valHtml = `<b>${valHtml}</b>`;
                    if (relative) valHtml += ` ${escapeHtml(relative)}`;
                    return `<div class="tooltip-text-clamp">${valHtml}</div>`;
                  }

                  const labelEsc = escapeHtml(it.label + ": ");
                  const valEsc = escapeHtml(val);
                  let valHtml = valEsc;
                  if (isNeg) valHtml = `<span style="color:red;">${valHtml}</span>`;
                  if (isPrice) valHtml = `<b>${valHtml}</b>`;
                  if (relative) valHtml += ` ${escapeHtml(relative)}`;
                  return `<div>${labelEsc}${valHtml}</div>`;
                }).join("");
              }
            }

        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.DirectEdit').forEach(function (el) {
                    if (!el.hasAttribute('title')) {
                        el.setAttribute('title', 'click and type to edit directly');
                    }
                    el.contentEditable = true;
                    el.addEventListener('focus', function () {
                        this.style.outline = 'none';
                    });
                });
            });
        </script>
    <main>
