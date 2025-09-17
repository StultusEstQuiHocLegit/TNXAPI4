<?php
require_once('../config.php');

$error = '';

// Function to generate random API key
function generateApiKey($length = 300) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < $length; $i++) {
        $apiKey .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password    = trim($_POST['password'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $darkMode    = isset($_POST['darkmode']) ? 1 : 0;

    if ($email && $password !== '' && $companyName !== '') {
        $stmt = $pdo->prepare("SELECT idpk FROM admins WHERE email = ?");
        $stmt->execute([$email]);

        if (!$stmt->fetch()) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $dummyToken     = '';
            $dummyExpiry    = '2000-01-01 00:00:00';

            // <<<— Notice: no "-- comments" inside this string; only real SQL.
            $stmt = $pdo->prepare("
                INSERT INTO admins (
                    email,
                    password,
                    LoginToken,
                    LoginTokenExpiry,
                    CompanyName,
                    FirstName,
                    LastName,
                    PhoneNumberPrivate,
                    PhoneNumberWork,
                    street,
                    HouseNumber,
                    ZIPCode,
                    city,
                    country,
                    IBAN,
                    CurrencyCode,
                    APIKey,
                    darkmode,
                    DarkmodeShop,
                    ShopShowRandomProductCards,
                    ShopShowSuggestionCards,
                    ShopAllowToAddCommentsNotesSpecialRequests,
                    ShopAllowToAddAdditionalNotes,
                    ShopTargetCustomerEntity,
                    PersonalNotes,
                    CompanyVATID,
                    CompanyOpeningHours,
                    CompanyShortDescription,
                    CompanyLongDescription,
                    CompanyIBAN,
                    AccentColorForPDFCreation,
                    AdditionalTextForInvoices,
                    CompanyPaymentDetails,
                    CompanyDeliveryReceiptDetails,
                    UsefulLinks,
                    CompanyBrandingInformation,
                    CompanyBrandingInformationForImages,
                    TimestampCreation,
                    ConnectEmail,
                    ConnectPassword,
                    ConnectServerName,
                    ConnectPort,
                    ConnectEncryption,
                    ConnectEmailWritingInstructions,
                    DbUseTRAMANNDB,
                    DbHost,
                    DbPort,
                    DbName,
                    DbUser,
                    DbPassword,
                    DbDriver,
                    FileProtocol,
                    FileServer,
                    FilePort,
                    FileUser,
                    FilePassword,
                    FileBasePath,
                    ActionBuying,
                    ActionSelling
                ) VALUES (
                    -- 1-7
                    ?, ?, ?, ?, ?, ?, ?,
                    -- 8-17 (contact + APIKey)
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    -- 18-25 (UI/shop settings)
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    -- 26-30 (company info basics)
                    ?, ?, ?, ?, ?,
                    -- 31 (accent color)
                    ?,
                    -- 32-37 (invoice/payment/delivery/useful/branding)
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    -- 38 (timestamp)
                    NOW(),
                    -- 39-44 (mail connect)
                    ?, ?, ?, ?, ?, ?,
                    -- 45 (TRAMANN flag)
                    ?,
                    -- 46-51 (db access)
                    ?, ?, ?, ?, ?, ?,
                    -- 52-59 (file + actions)
                    ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            // Now supply exactly 58 values in the same left-to-right order
            $params = [
                // 1–7: required vars
                $email,            // email
                $hashedPassword,   // password
                $dummyToken,       // LoginToken
                $dummyExpiry,      // LoginTokenExpiry
                $companyName,      // CompanyName
                '',                // FirstName
                '',                // LastName

                // 8–17: personal/contact defaults
                '',                // PhoneNumberPrivate
                '',                // PhoneNumberWork
                '',                // street
                '',                // HouseNumber
                '',                // ZIPCode
                '',                // city
                '',                // country
                '',                // IBAN
                'USD',             // CurrencyCode
                generateApiKey(),  // APIKey

                // 18–25: UI / shop defaults
                $darkMode,         // darkmode
                $darkMode,         // DarkmodeShop
                1,                 // ShopShowRandomProductCards
                1,                 // ShopShowSuggestionCards
                1,                 // ShopAllowToAddCommentsNotesSpecialRequests
                1,                 // ShopAllowToAddAdditionalNotes
                0,                 // ShopTargetCustomerEntity
                '',                // PersonalNotes

                // 26–30: company-info defaults
                '',                // CompanyVATID
                '',                // CompanyOpeningHours
                '',                // CompanyShortDescription
                '',                // CompanyLongDescription
                '',                // CompanyIBAN

                // 31: accent color
                '#2980b9',       // AccentColorForPDFCreation

                // 32–37: invoice/payment/delivery/useful/branding
                '',                // AdditionalTextForInvoices
                '',                // CompanyPaymentDetails
                '',                // CompanyDeliveryReceiptDetails
                '',                // UsefulLinks
                '',                // CompanyBrandingInformation
                '',                // CompanyBrandingInformationForImages

                // (NOW() for TimestampCreation—no placeholder here)

                // 39–44: mail-connect
                $email,            // ConnectEmail
                '',                // ConnectPassword
                '',                // ConnectServerName
                '',                // ConnectPort
                '',                // ConnectEncryption
                '',                // ConnectEmailWritingInstructions

                // 45: TRAMANN-DB flag
                0,                 // DbUseTRAMANNDB

                // 46–51: DB-access defaults
                '',                // DbHost
                '',                // DbPort
                '',                // DbName
                '',                // DbUser
                '',                // DbPassword
                '',                // DbDriver

                // 52–59: file-access defaults
                '',                // FileProtocol
                '',                // FileServer
                '',                // FilePort
                '',                // FileUser
                '',                // FilePassword
                '',                // FileBasePath
                '',                // ActionBuying
                ''                 // ActionSelling
            ];

            if ($stmt->execute($params)) {
                header('Location: login.php');
                exit;
            } else {
                $error = 'Error creating account. Please try again later.';
            }
        } else {
            $error = 'An account with this email already exists.';
        }
    } else {
        $error = 'Please fill in all fields correctly.';
    }
}

require_once('header.php');
?>

<div class="container">
    <h1 class="text-center">⚙️ CREATE BUSINESS ADMIN ACCOUNT</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">email</label>
            <input type="email" id="email" name="email" placeholder="with this you login" required>
        </div>

        <div class="form-group">
            <label for="password">password</label>
            <input type="password" id="password" name="password" placeholder="easy to remember but hard to guess" required>
        </div>

        <div class="form-group">
            <label for="company_name">company name</label>
            <input type="text" id="company_name" name="company_name" placeholder="ACME LLC" required>
        </div>

        <div class="form-group">
            <label class="toggle-label">
                <input type="checkbox" name="darkmode" id="darkmode">
                <span class="toggle-slider"></span>
                <span class="toggle-text">Come to the dark side, we have cookies!</span>
            </label>
        </div>

        <button type="submit">⚙️ CREATE BUSINESS ADMIN ACCOUNT</button>

        <div class="text-center mt-3">
            <a href="login.php">🗝️ LOGIN INSTEAD</a>
        </div>
    </form>
</div>

<style>
.toggle-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.toggle-slider {
    position: relative;
    width: 50px;
    height: 24px;
    background-color: var(--border-color);
    border-radius: 12px;
    margin-right: 10px;
    transition: background-color 0.3s;
}

.toggle-slider:before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
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
    transform: translateX(26px);
}

#darkmode:not(:checked) ~ #themePreview .preview-dark,
#darkmode:checked ~ #themePreview .preview-light {
    opacity: 0.5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('darkmode');
    const prefersDark = localStorage.getItem('darkmode') === '1';
    checkbox.checked = prefersDark;
    document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : '');
    checkbox.addEventListener('change', function() {
        localStorage.setItem('darkmode', this.checked ? '1' : '0');
        document.documentElement.setAttribute('data-theme', this.checked ? 'dark' : '');
    });
});
</script>

<?php require_once('footer.php'); ?>
