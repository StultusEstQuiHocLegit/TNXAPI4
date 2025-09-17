<?php
require_once('../config.php');
require_once('header.php');

// require_once('../ConfigExternal.php');

function emptyToString($value) {
    return trim($value) === '' ? '' : $value;
}

$error   = '';
$success = '';

// Fetch current DB settings
$stmt = $pdo->prepare("
    SELECT
        DbHost,
        DbPort,
        DbName,
        DbUser,
        DbPassword,
        DbDriver,
        DbUseTRAMANNDB
    FROM admins
    WHERE idpk = ?
");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize defaults if empty
$dbHost     = $settings['DbHost']     ?? '';
$dbPort     = $settings['DbPort']     ?? 3306;
$dbName     = $settings['DbName']     ?? '';
$dbUser     = $settings['DbUser']     ?? '';
$dbPassword = $settings['DbPassword'] ?? '';
$dbDriver   = $settings['DbDriver']   ?? 'mysql';
$useTRAMANDB = $settings['DbUseTRAMANNDB'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_databases'])) {
    // Sanitize inputs
    $dbHost     = emptyToString($_POST['db_host']);
    $dbPort     = filter_input(INPUT_POST, 'db_port', FILTER_VALIDATE_INT) ?: 3306;
    $dbName     = emptyToString($_POST['db_name']);
    $dbUser     = emptyToString($_POST['db_user']);
    $dbPassword = $_POST['db_password'] ?? '';
    $dbDriver   = in_array($_POST['db_driver'], ['mysql','pgsql','sqlsrv'], true)
                  ? $_POST['db_driver']
                  : 'mysql';
    $useTRAMANDB = isset($_POST['use_tramandb']) ? 1 : 0;

    // Update in database
    $stmt = $pdo->prepare("
        UPDATE admins
        SET
            DbHost     = ?,
            DbPort     = ?,
            DbName     = ?,
            DbUser     = ?,
            DbPassword = ?,
            DbDriver   = ?,
            DbUseTRAMANNDB = ?
        WHERE idpk = ?
    ");
    $ok = $stmt->execute([
        $dbHost,
        $dbPort,
        $dbName,
        $dbUser,
        $dbPassword,
        $dbDriver,
        $useTRAMANDB,
        $_SESSION['user_id']
    ]);

    if ($ok) {
        $success = 'Database settings updated successfully.';
    } else {
        $error = 'Error updating database settings. Please try again.';
    }
}
?>

<div class="container" style="max-width: 500px; margin: auto;">
    <h1 class="text-center">🧬 DATABASES SETUP</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group" style="margin-bottom: 2rem;">
            <label class="toggle-label" style="font-size: 1.2rem;">
                <input type="checkbox" name="use_tramandb" id="use_tramandb" <?php echo $useTRAMANDB ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
                <span class="toggle-text">use TRAMANN TDB databases</span>
            </label>
        </div>

        <div id="tramandb_settings" style="<?php echo $useTRAMANDB ? '' : 'display: none;'; ?>">
            <div class="menu-nav">
                <a href="#" class="menu-item" onclick="showTableFields('TDBcarts')">
                    <span class="menu-emoji">🧬</span>
                    <span class="menu-title">TDBcarts</span>
                </a>
                <a href="#" class="menu-item" onclick="showTableFields('TDBtransaction')">
                    <span class="menu-emoji">🧬</span>
                    <span class="menu-title">TDBtransaction</span>
                </a>
                <a href="#" class="menu-item" onclick="showTableFields('TDBProductsAndServices')">
                    <span class="menu-emoji">🧬</span>
                    <span class="menu-title">TDBProductsAndServices</span>
                </a>
                <a href="#" class="menu-item" onclick="showTableFields('TDBSuppliersAndCustomers')">
                    <span class="menu-emoji">🧬</span>
                    <span class="menu-title">TDBSuppliersAndCustomers</span>
                </a>
            </div>

            <div id="table_fields" style="margin-top: 2rem; display: none;">
                <div id="TDBcarts" class="table-fields" style="display: none;">
                    <h3>TDBcarts</h3>
                    <ul>
                        <li>idpk <span style="opacity: 0.6;">(int)</span> <span style="opacity: 0.3;">auto increment, primary key</span></li>
                        <li>TimestampCreation <span style="opacity: 0.6;">(timestamp)</span></li>
                        <li>IdpkOfAdmin <span style="opacity: 0.6;">(int)</span></li>
                        <li>IdpkOfSupplierOrCustomer <span style="opacity: 0.6;">(int)</span></li>
                        <li>DeliveryType <span style="opacity: 0.6;">(varchar(250))</span> <span style="opacity: 0.3;">for example: "standard", "express", "overnight", "pickup", "temperature-controlled", ...</span></li>
                        <li>WishedIdealDeliveryOrPickUpTime <span style="opacity: 0.6;">(datetime)</span></li>
                        <li>CommentsNotesSpecialRequests <span style="opacity: 0.6;">(text)</span></li>
                    </ul>
                </div>

                <div id="TDBtransaction" class="table-fields" style="display: none;">
                    <h3>TDBtransaction</h3>
                    <ul>
                        <li>idpk <span style="opacity: 0.6;">(int)</span> <span style="opacity: 0.3;">auto increment, primary key</span></li>
                        <li>TimestampCreation <span style="opacity: 0.6;">(timestamp)</span></li>
                        <li>IdpkOfAdmin <span style="opacity: 0.6;">(int)</span></li>
                        <li>IdpkOfSupplierOrCustomer <span style="opacity: 0.6;">(int)</span></li>
                        <li>IdpkOfCart <span style="opacity: 0.6;">(int)</span></li>
                        <li>IdpkOfProductOrService <span style="opacity: 0.6;">(int)</span></li>
                        <li>quantity <span style="opacity: 0.6;">(int)</span></li>
                        <li>NetPriceTotal <span style="opacity: 0.6;">(decimal(10,2))</span> <span style="opacity: 0.3;">positive if the user sold something, negative if he bought something, (total = unit price * quantity)</span></li>
                        <li>TaxesTotal <span style="opacity: 0.6;">(decimal(10,2))</span> <span style="opacity: 0.3;">(total = unit tax amount * quantity)</span></li>
                        <li>CurrencyCode <span style="opacity: 0.6;">(varchar(3))</span> <span style="opacity: 0.3;">three letter currency code based on ISO 4217</span></li>
                        <li>state <span style="opacity: 0.6;">(varchar(250))</span> <span style="opacity: 0.3;">for example: "draft", "offer", "payment-pending", "payment-made-delivery-pending", "payment-pending-delivery-made", "completed", "disputed", "dispute-accepted", "dispute-rejected", "refunded", "items-returned", "cancelled", ...</span></li>
                        <li>CommentsNotesSpecialRequests <span style="opacity: 0.6;">(text)</span></li>
                    </ul>
                </div>

                <div id="TDBProductsAndServices" class="table-fields" style="display: none;">
                    <h3>TDBProductsAndServices</h3>
                    <ul>
                        <li>idpk <span style="opacity: 0.6;">(int)</span> <span style="opacity: 0.3;">auto increment, primary key</span></li>
                        <li>TimestampCreation <span style="opacity: 0.6;">(timestamp)</span></li>
                        <li>IdpkOfAdmin <span style="opacity: 0.6;">(int)</span></li>
                        <li>name <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>categories <span style="opacity: 0.6;">(varchar(250))</span> <span style="opacity: 0.3;">for example: "SomeMainCategory/FirstSubcategory/SecondSubcategory, AnotherMainCategoryIfNeeded, YetAnotherOne/AndSomeSubcategoryToo"</span></li>
                        <li>KeywordsForSearch <span style="opacity: 0.6;">(text)</span></li>
                        <li>ShortDescription <span style="opacity: 0.6;">(text)</span></li>
                        <li>LongDescription <span style="opacity: 0.6;">(text)</span></li>
                        <li>WeightInKg <span style="opacity: 0.6;">(decimal(10,5))</span></li>
                        <li>DimensionsLengthInMm <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>DimensionsWidthInMm <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>DimensionsHeightInMm <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>NetPriceInCurrencyOfAdmin <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>TaxesInPercent <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>VariableCostsOrPurchasingPriceInCurrencyOfAdmin <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>ProductionCoefficientInLaborHours <span style="opacity: 0.6;">(decimal(10,2))</span></li>
                        <li>ManageInventory <span style="opacity: 0.6;">(boolean)</span> <span style="opacity: 0.3;">true if the product should be managed in inventory</span></li>
                        <li>InventoryAvailable <span style="opacity: 0.6;">(int)</span></li>
                        <li>InventoryInProductionOrReordered <span style="opacity: 0.6;">(int)</span></li>
                        <li>InventoryMinimumLevel <span style="opacity: 0.6;">(int)</span></li>
                        <li>InventoryLocation <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>PersonalNotes <span style="opacity: 0.6;">(text)</span></li>
                        <li>state <span style="opacity: 0.6;">(varchar(250))</span> <span style="opacity: 0.3;">for example: "active", "inactive", "archived", "only for internal purposes", ...</span></li>
                    </ul>
                </div>

                <div id="TDBSuppliersAndCustomers" class="table-fields" style="display: none;">
                    <h3>TDBSuppliersAndCustomers</h3>
                    <ul>
                        <li>idpk <span style="opacity: 0.6;">(int)</span> <span style="opacity: 0.3;">auto increment, primary key</span></li>
                        <li>TimestampCreation <span style="opacity: 0.6;">(timestamp)</span></li>
                        <li>IdpkOfAdmin <span style="opacity: 0.6;">(int)</span></li>
                        <li>CompanyName <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>email <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>PhoneNumber <span style="opacity: 0.6;">(bigint)</span> <span style="opacity: 0.3;">for example: "0123456789"</span></li>
                        <li>street <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>HouseNumber <span style="opacity: 0.6;">(int)</span></li>
                        <li>ZIPCode <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>city <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>country <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>IBAN <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>VATID <span style="opacity: 0.6;">(varchar(250))</span></li>
                        <li>PersonalNotesInGeneral <span style="opacity: 0.6;">(text)</span></li>
                        <li>PersonalNotesBusinessRelationships <span style="opacity: 0.6;">(text)</span></li>
                    </ul>
                </div>
            </div>
            <br><br>
        </div>

        <div id="custom_db_settings">
            <div class="form-group">
                <label for="db_host">database hostname/IP</label>
                <input
                    type="text"
                    id="db_host"
                    name="db_host"
                    class="form-control"
                    placeholder="www.example.com"
                    value="<?php echo htmlspecialchars($dbHost); ?>"
                >
            </div>

            <div class="form-group">
                <label for="db_port">database port</label>
                <input
                    type="number"
                    id="db_port"
                    name="db_port"
                    class="form-control"
                    placeholder="3306"
                    value="<?php echo htmlspecialchars($dbPort); ?>"
                >
            </div>

            <div class="form-group">
                <label for="db_name">database name</label>
                <input
                    type="text"
                    id="db_name"
                    name="db_name"
                    class="form-control"
                    placeholder="hitchhikers_guide"
                    value="<?php echo htmlspecialchars($dbName); ?>"
                >
            </div>

            <div class="form-group">
                <label for="db_user">database username</label>
                <input
                    type="text"
                    id="db_user"
                    name="db_user"
                    class="form-control"
                    placeholder="sudoMakeMeASandwich"
                    value="<?php echo htmlspecialchars($dbUser); ?>"
                >
            </div>

            <div class="form-group">
                <label for="db_password">database password</label>
                <input
                    type="password"
                    id="db_password"
                    name="db_password"
                    class="form-control"
                    placeholder="¯\_(ツ)_/¯"
                    value="<?php echo htmlspecialchars($dbPassword); ?>"
                >
            </div>

            <div class="form-group">
                <label for="db_driver">database driver</label>
                <select
                    id="db_driver"
                    name="db_driver"
                    class="form-control"
                >
                    <option value="mysql"  <?php echo $dbDriver === 'mysql'  ? 'selected' : ''; ?>>MySQL</option>
                    <option value="pgsql"  <?php echo $dbDriver === 'pgsql'  ? 'selected' : ''; ?>>PostgreSQL</option>
                    <option value="sqlsrv" <?php echo $dbDriver === 'sqlsrv' ? 'selected' : ''; ?>>SQL Server</option>
                </select>
            </div>
        </div>

        <button type="submit" name="update_databases" class="btn btn-primary" style="margin-top: 2rem;">
            ↗️ SAVE SETTINGS
        </button>
    </form>
</div>

<?php require_once('footer.php'); ?>

<style>
  /* only allow vertical resizing */
  textarea {
    resize: vertical;
  }

  .toggle-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
  }

  .toggle-slider {
    position: relative;
    width: 60px;
    height: 30px;
    background-color: var(--border-color);
    border-radius: 15px;
    margin-right: 15px;
    transition: background-color 0.3s;
  }

  .toggle-slider:before {
    content: '';
    position: absolute;
    width: 26px;
    height: 26px;
    left: 2px;
    top: 2px;
    background-color: white;
    border-radius: 50%;
    transition: transform 0.3s;
  }

  input[type="checkbox"] {
    display: none;
  }

  input[type="checkbox"]:checked + .toggle-slider {
    background-color: var(--primary-color);
  }

  input[type="checkbox"]:checked + .toggle-slider:before {
    transform: translateX(30px);
  }

  .table-fields {
    background-color: var(--input-bg);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
  }

  .table-fields h3 {
    margin-bottom: 1rem;
    color: var(--primary-color);
  }

  .table-fields ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .table-fields li {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
    font-family: monospace;
    font-size: 0.9rem;
  }

  .table-fields li:last-child {
    border-bottom: none;
  }
</style>

<script>
document.getElementById('use_tramandb').addEventListener('change', function() {
    const tramandbSettings = document.getElementById('tramandb_settings');
    tramandbSettings.style.display = this.checked ? 'block' : 'none';
});

function showTableFields(tableId) {
    // Hide all table fields
    document.querySelectorAll('.table-fields').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show the selected table fields
    document.getElementById(tableId).style.display = 'block';
    document.getElementById('table_fields').style.display = 'block';
}
</script>
