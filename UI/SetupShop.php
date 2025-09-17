<?php
require_once('../config.php');
require_once('header.php');

// require_once('../ConfigExternal.php');

function emptyToString($value) {
    return trim($value) === '' ? '' : $value;
}

$error   = '';
$success = '';


// Fetch current shop settings
$stmt = $pdo->prepare(
    "SELECT
        DbHost,
        DarkmodeShop,
        ShopShowRandomProductCards,
        ShopShowSuggestionCards,
        ShopAllowToAddCommentsNotesSpecialRequests,
        ShopAllowToAddAdditionalNotes,
        ShopTargetCustomerEntity
    FROM admins
    WHERE idpk = ?"
);
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize defaults if empty
$dbHost     = $settings['DbHost']     ?? '';
$darkModeShop = (int)($settings['DarkmodeShop'] ?? 0);
$shopShowRandomProductCards = (int)($settings['ShopShowRandomProductCards'] ?? 1);
$shopShowSuggestionCards = (int)($settings['ShopShowSuggestionCards'] ?? 1);
$shopAllowToAddCommentsNotesSpecialRequests = (int)($settings['ShopAllowToAddCommentsNotesSpecialRequests'] ?? 1);
$shopAllowToAddAdditionalNotes = (int)($settings['ShopAllowToAddAdditionalNotes'] ?? 1);
$shopTargetCustomerEntity = (int)($settings['ShopTargetCustomerEntity'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_databases'])) {
    // Sanitize inputs
    $dbHost     = emptyToString($_POST['db_host']);

    // Update in database
    $stmt = $pdo->prepare("
        UPDATE admins
        SET
            DbHost     = ?
        WHERE idpk = ?
    ");
    $ok = $stmt->execute([
        $dbHost,
        $_SESSION['user_id']
    ]);

    if ($ok) {
        $success = 'Database settings updated successfully.';
    } else {
        $error = 'Error updating database settings. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop_settings'])) {
    $darkModeShop = isset($_POST['darkmodeshop']) ? 1 : 0;
    $shopShowRandomProductCards = isset($_POST['shopshowrandomproductcards']) ? 1 : 0;
    $shopShowSuggestionCards = isset($_POST['shopshowsuggestioncards']) ? 1 : 0;
    $shopAllowToAddCommentsNotesSpecialRequests = isset($_POST['shopallowtoaddcommentsnotesspecialrequests']) ? 1 : 0;
    $shopAllowToAddAdditionalNotes = isset($_POST['shopallowtoaddadditionalnotes']) ? 1 : 0;
    $shopTargetCustomerEntity = max(0, min(3, (int)($_POST['shoptargetcustomerentity'] ?? 0)));

    $stmt = $pdo->prepare(
        "UPDATE admins SET DarkmodeShop = ?, ShopShowRandomProductCards = ?, ShopShowSuggestionCards = ?, ShopAllowToAddCommentsNotesSpecialRequests = ?, ShopAllowToAddAdditionalNotes = ?, ShopTargetCustomerEntity = ? WHERE idpk = ?"
    );
    $ok = $stmt->execute([
        $darkModeShop,
        $shopShowRandomProductCards,
        $shopShowSuggestionCards,
        $shopAllowToAddCommentsNotesSpecialRequests,
        $shopAllowToAddAdditionalNotes,
        $shopTargetCustomerEntity,
        $_SESSION['user_id']
    ]);

    if ($ok) {
        $success = 'Shop settings updated successfully.';
    } else {
        $error = 'Error updating shop settings. Please try again.';
    }
}
?>

<div class="container" style="max-width: 800px; margin: auto;">
    <h1 class="text-center">🌐 SHOP SETUP</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <a href="../SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?>" target="_blank"><button class="btn btn-primary" style="margin-top: 2rem;">
        👁️ SHOW CURRENT VERSION
    </button></a><br><br><br><br><br>

    <form method="POST" action="" style="margin-top: 2rem;">
        <div class="form-group">
            <label for="darkmodeshop">darkmode in shop</label>
            <div class="toggle-label">
                <input type="checkbox" name="darkmodeshop" id="darkmodeshop" <?php echo $darkModeShop ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="shopshowrandomproductcards">show random entries cards <span style="opacity:0.5;">(on the start page)</span></label>
            <div class="toggle-label">
                <input type="checkbox" name="shopshowrandomproductcards" id="shopshowrandomproductcards" <?php echo $shopShowRandomProductCards ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="shopshowsuggestioncards">show suggestion cards <span style="opacity:0.5;">(on the start page)</span></label>
            <div class="toggle-label">
                <input type="checkbox" name="shopshowsuggestioncards" id="shopshowsuggestioncards" <?php echo $shopShowSuggestionCards ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="shopallowtoaddcommentsnotesspecialrequests">allow comments, notes, special requests, ... <span style="opacity:0.5;">(for each product and or or service)</span></label>
            <div class="toggle-label">
                <input type="checkbox" name="shopallowtoaddcommentsnotesspecialrequests" id="shopallowtoaddcommentsnotesspecialrequests" <?php echo $shopAllowToAddCommentsNotesSpecialRequests ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="shopallowtoaddadditionalnotes">allow additional notes <span style="opacity:0.5;">(for the whole cart)</span></label>
            <div class="toggle-label">
                <input type="checkbox" name="shopallowtoaddadditionalnotes" id="shopallowtoaddadditionalnotes" <?php echo $shopAllowToAddAdditionalNotes ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="shoptargetcustomerentity">target customer entity</label>
            <select name="shoptargetcustomerentity" id="shoptargetcustomerentity">
                <option value="0" <?php echo $shopTargetCustomerEntity === 0 ? 'selected' : ''; ?>>both, primarily consumers</option>
                <option value="1" <?php echo $shopTargetCustomerEntity === 1 ? 'selected' : ''; ?>>both, primarily businesses</option>
                <option value="2" <?php echo $shopTargetCustomerEntity === 2 ? 'selected' : ''; ?>>only consumers</option>
                <option value="3" <?php echo $shopTargetCustomerEntity === 3 ? 'selected' : ''; ?>>only businesses</option>
            </select>
        </div>

        <button type="submit" name="update_shop_settings" class="btn btn-primary">
            ↗️ SAVE SETTINGS
        </button>
    </form>

    <br><br><br><br><br>
    <span style="opacity: 0.5;">
        While the general link to your shop is:
        <span class="copy-link" style="cursor: pointer; font-style: italic; word-break: break-all; overflow-wrap: break-word; white-space: normal; display: inline-block; max-width: 100%;" title="click to copy" data-copy="https://www.tnxapi.com/SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?>">https://www.tnxapi.com/SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?></span>,
        you can link directly to certain entries with for example:
        <span class="copy-link" style="cursor: pointer; font-style: italic; word-break: break-all; overflow-wrap: break-word; white-space: normal; display: inline-block; max-width: 100%;" title="click to copy" data-copy="https://www.tnxapi.com/SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?>&table=SomeTableNameInHere&idpk=SomeIdpkInHere">https://www.tnxapi.com/SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?>&table=<span class="DirectEdit">SomeTableNameInHere</span>&idpk=<span class="DirectEdit">SomeIdpkInHere</span></span>
        or link to an overview by sending search parameters with aut automatically triggered, for example:
        <span class="copy-link" style="cursor: pointer; font-style: italic; word-break: break-all; overflow-wrap: break-word; white-space: normal; display: inline-block; max-width: 100%;" title="click to copy" data-copy="https://www.tnxapi.com/SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?>&autosearch=SomeSearchParameterInHere">https://www.tnxapi.com/SHOP/index.php?company=<?php echo urlencode($_SESSION['IdpkOfAdmin'] ?? ($_SESSION['user_id'] ?? '')); ?>&autosearch=<span class="DirectEdit">SomeSearchParameterInHere</span></span>
    </span>
    <script>
        document.querySelectorAll('.copy-link').forEach(function(span) {
            span.dataset.template = span.getAttribute('data-copy');

            function updateCopy() {
                let url = span.dataset.template;
                span.querySelectorAll('.DirectEdit').forEach(function(de) {
                    const placeholder = de.dataset.original;
                    const value = encodeURIComponent(de.textContent.trim()).replace(/%20/g, '+');
                    url = url.replace(placeholder, value);
                });
                span.setAttribute('data-copy', url);
            }

            span.querySelectorAll('.DirectEdit').forEach(function(editEl) {
                editEl.dataset.original = editEl.textContent;
                editEl.addEventListener('blur', updateCopy);
            });

            span.addEventListener('click', function() {
                updateCopy();
                navigator.clipboard.writeText(span.getAttribute('data-copy'));
            });

            span.querySelectorAll('.DirectEdit').forEach(function(editEl) {
                editEl.setAttribute('contenteditable', 'true');
                        
                editEl.addEventListener('focus', function(e) {
                    // Delay needed so focus happens before selection
                    setTimeout(() => {
                        const range = document.createRange();
                        range.selectNodeContents(editEl);
                        const sel = window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }, 0);
                });
            });
        });
    </script>
</div>

<?php require_once('footer.php'); ?>
