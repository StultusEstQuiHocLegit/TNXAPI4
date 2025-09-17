<?php
require_once('../config.php');
require_once('header.php');

$error   = '';
$success = '';

// Fetch current company settings
$stmt = $pdo->prepare("
    SELECT
        CompanyName,
        CompanyBrandingInformation,
        CompanyBrandingInformationForImages,
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
        PhoneNumberWork,
        street,
        HouseNumber,
        ZIPCode,
        city,
        country,
        CurrencyCode,
        ActionBuying,
        ActionSelling
    FROM admins
    WHERE idpk = ?
");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize defaults if empty
$companyName                      = $settings['CompanyName']                          ?? '';
$companyBrandingInfo              = $settings['CompanyBrandingInformation']           ?? '';
$companyBrandingInfoForImages     = $settings['CompanyBrandingInformationForImages']  ?? '';
$companyVATID                     = $settings['CompanyVATID']                         ?? '';
$companyOpeningHours              = $settings['CompanyOpeningHours']                  ?? '';
$companyShortDescription          = $settings['CompanyShortDescription']              ?? '';
$companyLongDescription           = $settings['CompanyLongDescription']               ?? '';
$accentColorForPDFCreation        = $settings['AccentColorForPDFCreation']            ?? '#2980b9';
$additionalTextForInvoices        = $settings['AdditionalTextForInvoices']            ?? '';
$companyPaymentDetails            = $settings['CompanyPaymentDetails']                ?? '';
$companyDeliveryReceiptDetails    = $settings['CompanyDeliveryReceiptDetails']        ?? '';
$usefulLinks                      = $settings['UsefulLinks']                          ?? '';
$company_iban                     = $settings['CompanyIBAN']                          ?? '';
$phoneNumberWork                  = $settings['PhoneNumberWork']                      ?? '';
$street                           = $settings['street']                               ?? '';
$houseNumber                      = $settings['HouseNumber']                          ?? '';
$zipCode                          = $settings['ZIPCode']                              ?? '';
$city                             = $settings['city']                                 ?? '';
$country                          = $settings['country']                              ?? '';
$currencyCode                     = $settings['CurrencyCode']                         ?? '';
$actionBuying                     = $settings['ActionBuying']                         ?? '';
$actionSelling                    = $settings['ActionSelling']                        ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {
    // Sanitize inputs
    $companyName                  = htmlspecialchars($_POST['company_name'], ENT_QUOTES, 'UTF-8');
    $companyBrandingInfo          = htmlspecialchars($_POST['company_branding_info'], ENT_QUOTES, 'UTF-8');
    $companyBrandingInfoForImages = htmlspecialchars($_POST['company_branding_for_images'], ENT_QUOTES, 'UTF-8');
    $companyVATID                 = htmlspecialchars($_POST['company_vat_id'], ENT_QUOTES, 'UTF-8');
    $companyOpeningHours          = htmlspecialchars($_POST['company_opening_hours'], ENT_QUOTES, 'UTF-8');
    $companyShortDescription      = htmlspecialchars($_POST['company_short_description'], ENT_QUOTES, 'UTF-8');
    $companyLongDescription       = htmlspecialchars($_POST['company_long_description'], ENT_QUOTES, 'UTF-8');
    $accentColorForPDFCreation    = htmlspecialchars($_POST['accent_color_for_pdf'], ENT_QUOTES, 'UTF-8');
    $additionalTextForInvoices    = htmlspecialchars($_POST['additional_text_for_invoices'], ENT_QUOTES, 'UTF-8');
    $companyPaymentDetails        = htmlspecialchars($_POST['company_payment_details'] ?? '', ENT_QUOTES, 'UTF-8');
    $companyDeliveryReceiptDetails= htmlspecialchars($_POST['company_delivery_receipt_details'] ?? '', ENT_QUOTES, 'UTF-8');
    $usefulLinks                  = htmlspecialchars($_POST['useful_links'], ENT_QUOTES, 'UTF-8');
    $company_iban                 = htmlspecialchars($_POST['company_iban'], ENT_QUOTES, 'UTF-8');
    $phoneNumberWork              = !empty($_POST['phone_number_work']) ? (int)$_POST['phone_number_work'] : null;
    $street                       = htmlspecialchars($_POST['street'] ?? '', ENT_QUOTES, 'UTF-8');
    $houseNumber                  = !empty($_POST['house_number']) ? (int)$_POST['house_number'] : null;
    $zipCode                      = htmlspecialchars($_POST['zip_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $city                         = htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES, 'UTF-8');
    $country                      = htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES, 'UTF-8');
    $currencyCode                 = htmlspecialchars($_POST['currency_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $actionBuying                 = htmlspecialchars($_POST['action_buying'] ?? '', ENT_QUOTES, 'UTF-8');
    $actionSelling                = htmlspecialchars($_POST['action_selling'] ?? '', ENT_QUOTES, 'UTF-8');

    // Update in database
    $stmt = $pdo->prepare("
        UPDATE admins
        SET
            CompanyName                         = ?,
            CompanyBrandingInformation          = ?,
            CompanyBrandingInformationForImages = ?,
            CompanyVATID                        = ?,
            CompanyOpeningHours                 = ?,
            CompanyShortDescription             = ?,
            CompanyLongDescription              = ?,
            AccentColorForPDFCreation           = ?,
            AdditionalTextForInvoices           = ?,
            CompanyPaymentDetails               = ?,
            CompanyDeliveryReceiptDetails       = ?,
            UsefulLinks                         = ?,
            CompanyIBAN                         = ?,
            PhoneNumberWork                     = ?,
            street                              = ?,
            HouseNumber                         = ?,
            ZIPCode                             = ?,
            city                                = ?,
            country                             = ?,
            CurrencyCode                        = ?,
            ActionBuying                        = ?,
            ActionSelling                       = ?
        WHERE idpk = ?
    ");
    $ok = $stmt->execute([
        $companyName,
        $companyBrandingInfo,
        $companyBrandingInfoForImages,
        $companyVATID,
        $companyOpeningHours,
        $companyShortDescription,
        $companyLongDescription,
        $accentColorForPDFCreation,
        $additionalTextForInvoices,
        $companyPaymentDetails,
        $companyDeliveryReceiptDetails,
        $usefulLinks,
        $company_iban,
        $phoneNumberWork,
        $street,
        $houseNumber,
        $zipCode,
        $city,
        $country,
        $currencyCode,
        $actionBuying,
        $actionSelling,
        $_SESSION['user_id']
    ]);

    if ($ok) {
        $success = 'Company settings updated successfully.';
    } else {
        $error = 'Error updating company settings. Please try again.';
    }
}
?>

<div class="container" style="max-width: 800px; margin: auto;">
    <h1 class="text-center">🏭 COMPANY SETUP</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="company_name">company name</label>
            <input
                type="text"
                id="company_name"
                name="company_name"
                class="form-control"
                placeholder="ACME LLC"
                value="<?php echo htmlspecialchars($companyName); ?>"
            >
        </div>

        <div class="form-group">
            <label for="company_branding_info">branding information</label>
            <textarea
                id="company_branding_info"
                name="company_branding_info"
                class="form-control"
                placeholder="please enter general informations about your brand in here, so that it can be considered in each newly created marketing content (for example:  brand tone (professional, playful, friendly, ...), visual style (clean, minimal, bold, detailed, ...), shapes (round, sharp, ...), theme (dark, light, ...), preferred fonts, colors, imagery style, language style (formal, casual, funny, ...), target audience, ...) ..."
                rows="6"
            ><?php echo htmlspecialchars($companyBrandingInfo); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_branding_for_images">branding information for images</label>
            <textarea
                id="company_branding_for_images"
                name="company_branding_for_images"
                class="form-control"
                placeholder="... and in here especially for creating images"
                rows="4"
            ><?php echo htmlspecialchars($companyBrandingInfoForImages); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_vat_id">VAT ID</label>
            <input
                type="text"
                id="company_vat_id"
                name="company_vat_id"
                class="form-control"
                placeholder="e.g. DE123456789"
                value="<?php echo htmlspecialchars($companyVATID); ?>"
            >
        </div>

        <div class="form-group">
            <label for="company_opening_hours">opening hours</label>
            <textarea
                id="company_opening_hours"
                name="company_opening_hours"
                class="form-control"
                placeholder="e.g. Monday-Friday: 9:00-18:00, Saturday: 10:00-14:00"
                rows="4"
            ><?php echo htmlspecialchars($companyOpeningHours); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_short_description">short description</label>
            <textarea
                id="company_short_description"
                name="company_short_description"
                class="form-control"
                placeholder="A brief description of your company (max 2-3 sentences)"
                rows="3"
            ><?php echo htmlspecialchars($companyShortDescription); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_long_description">long description</label>
            <textarea
                id="company_long_description"
                name="company_long_description"
                class="form-control"
                placeholder="A detailed description of your company, services, and values"
                rows="6"
            ><?php echo htmlspecialchars($companyLongDescription); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_iban">company IBAN</label>
            <input
                type="text"
                id="company_iban"
                name="company_iban"
                class="form-control"
                placeholder="a long number on your banking card"
                value="<?php echo htmlspecialchars($company_iban); ?>"
            >
        </div>

        <div class="form-group">
            <label for="phone_number_work">work phone number</label>
            <input
                type="number"
                id="phone_number_work"
                name="phone_number_work"
                class="form-control"
                placeholder="ring, ring, ring"
                value="<?php echo isset($phoneNumberWork) ? htmlspecialchars($phoneNumberWork) : ''; ?>"
            >
        </div>

        <div class="form-group">
            <label for="street">street</label>
            <input
                type="text"
                id="street"
                name="street"
                class="form-control"
                placeholder="Mordor Avenue"
                value="<?php echo htmlspecialchars($street); ?>"
            >
        </div>

        <div class="form-group">
            <label for="house_number">house number</label>
            <input
                type="number"
                id="house_number"
                name="house_number"
                class="form-control"
                placeholder="42"
                value="<?php echo isset($houseNumber) ? htmlspecialchars($houseNumber) : ''; ?>"
            >
        </div>

        <div class="form-group">
            <label for="zip_code">ZIP code</label>
            <input
                type="text"
                id="zip_code"
                name="zip_code"
                class="form-control"
                placeholder="introduced in 1963"
                value="<?php echo htmlspecialchars($zipCode); ?>"
            >
        </div>

        <div class="form-group">
            <label for="city">city</label>
            <input
                type="text"
                id="city"
                name="city"
                class="form-control"
                placeholder="big city life..."
                value="<?php echo htmlspecialchars($city); ?>"
            >
        </div>

        <div class="form-group">
            <label for="country">country</label>
            <input
                type="text"
                id="country"
                name="country"
                class="form-control"
                placeholder="Anybody from Wakanda?"
                value="<?php echo htmlspecialchars($country); ?>"
            >
        </div>

        <div class="form-group">
            <label for="currency_code">currency</label>
            <select id="currency_code" name="currency_code" class="form-control">
                <?php
                $currencies = [
                    "AED" => "AED - UAE Dirham", "AFN" => "AFN - Afghan Afghani", "ALL" => "ALL - Albanian Lek",
                    "AMD" => "AMD - Armenian Dram", "ANG" => "ANG - Netherlands Antillean Guilder",
                    "AOA" => "AOA - Angolan Kwanza", "ARS" => "ARS - Argentine Peso",
                    "AUD" => "AUD - Australian Dollar", "AWG" => "AWG - Aruban Florin",
                    "AZN" => "AZN - Azerbaijani Manat", "BAM" => "BAM - Bosnia-Herzegovina Convertible Mark",
                    "BBD" => "BBD - Barbadian Dollar", "BDT" => "BDT - Bangladeshi Taka",
                    "BGN" => "BGN - Bulgarian Lev", "BHD" => "BHD - Bahraini Dinar",
                    "BIF" => "BIF - Burundian Franc", "BMD" => "BMD - Bermudan Dollar",
                    "BND" => "BND - Brunei Dollar", "BOB" => "BOB - Bolivian Boliviano",
                    "BRL" => "BRL - Brazilian Real", "BSD" => "BSD - Bahamian Dollar",
                    "BTC" => "BTC - Bitcoin", "BTN" => "BTN - Bhutanese Ngultrum",
                    "BWP" => "BWP - Botswanan Pula", "BYN" => "BYN - Belarusian Ruble",
                    "BZD" => "BZD - Belize Dollar", "CAD" => "CAD - Canadian Dollar",
                    "CDF" => "CDF - Congolese Franc", "CHF" => "CHF - Swiss Franc",
                    "CLF" => "CLF - Chilean Unit of Account (UF)", "CLP" => "CLP - Chilean Peso",
                    "CNH" => "CNH - Chinese Yuan (Offshore)", "CNY" => "CNY - Chinese Yuan",
                    "COP" => "COP - Colombian Peso", "CRC" => "CRC - Costa Rican Colón",
                    "CUC" => "CUC - Cuban Convertible Peso", "CUP" => "CUP - Cuban Peso",
                    "CVE" => "CVE - Cape Verdean Escudo", "CZK" => "CZK - Czech Republic Koruna",
                    "DJF" => "DJF - Djiboutian Franc", "DKK" => "DKK - Danish Krone",
                    "DOP" => "DOP - Dominican Peso", "DZD" => "DZD - Algerian Dinar",
                    "EGP" => "EGP - Egyptian Pound", "ERN" => "ERN - Eritrean Nakfa",
                    "ETB" => "ETB - Ethiopian Birr", "EUR" => "EUR - Euro",
                    "FJD" => "FJD - Fijian Dollar", "FKP" => "FKP - Falkland Islands Pound",
                    "GBP" => "GBP - British Pound Sterling", "GEL" => "GEL - Georgian Lari",
                    "GGP" => "GGP - Guernsey Pound", "GHS" => "GHS - Ghanaian Cedi",
                    "GIP" => "GIP - Gibraltar Pound", "GMD" => "GMD - Gambian Dalasi",
                    "GNF" => "GNF - Guinean Franc", "GTQ" => "GTQ - Guatemalan Quetzal",
                    "GYD" => "GYD - Guyanaese Dollar", "HKD" => "HKD - Hong Kong Dollar",
                    "HNL" => "HNL - Honduran Lempira", "HRK" => "HRK - Croatian Kuna",
                    "HTG" => "HTG - Haitian Gourde", "HUF" => "HUF - Hungarian Forint",
                    "IDR" => "IDR - Indonesian Rupiah", "ILS" => "ILS - Israeli New Shekel",
                    "IMP" => "IMP - Manx pound", "INR" => "INR - Indian Rupee",
                    "IQD" => "IQD - Iraqi Dinar", "IRR" => "IRR - Iranian Rial",
                    "ISK" => "ISK - Icelandic Króna", "JEP" => "JEP - Jersey Pound",
                    "JMD" => "JMD - Jamaican Dollar", "JOD" => "JOD - Jordanian Dinar",
                    "JPY" => "JPY - Japanese Yen", "KES" => "KES - Kenyan Shilling",
                    "KGS" => "KGS - Kyrgystani Som", "KHR" => "KHR - Cambodian Riel",
                    "KMF" => "KMF - Comorian Franc", "KPW" => "KPW - North Korean Won",
                    "KRW" => "KRW - South Korean Won", "KWD" => "KWD - Kuwaiti Dinar",
                    "KYD" => "KYD - Cayman Islands Dollar", "KZT" => "KZT - Kazakhstani Tenge",
                    "LAK" => "LAK - Laotian Kip", "LBP" => "LBP - Lebanese Pound",
                    "LKR" => "LKR - Sri Lankan Rupee", "LRD" => "LRD - Liberian Dollar",
                    "LSL" => "LSL - Lesotho Loti", "LYD" => "LYD - Libyan Dinar",
                    "MAD" => "MAD - Moroccan Dirham", "MDL" => "MDL - Moldovan Leu",
                    "MGA" => "MGA - Malagasy Ariary", "MKD" => "MKD - Macedonian Denar",
                    "MMK" => "MMK - Myanma Kyat", "MNT" => "MNT - Mongolian Tugrik",
                    "MOP" => "MOP - Macanese Pataca", "MRU" => "MRU - Mauritanian Ouguiya",
                    "MUR" => "MUR - Mauritian Rupee", "MVR" => "MVR - Maldivian Rufiyaa",
                    "MWK" => "MWK - Malawian Kwacha", "MXN" => "MXN - Mexican Peso",
                    "MYR" => "MYR - Malaysian Ringgit", "MZN" => "MZN - Mozambican Metical",
                    "NAD" => "NAD - Namibian Dollar", "NGN" => "NGN - Nigerian Naira",
                    "NIO" => "NIO - Nicaraguan Córdoba", "NOK" => "NOK - Norwegian Krone",
                    "NPR" => "NPR - Nepalese Rupee", "NZD" => "NZD - New Zealand Dollar",
                    "OMR" => "OMR - Omani Rial", "PAB" => "PAB - Panamanian Balboa",
                    "PEN" => "PEN - Peruvian Nuevo Sol", "PGK" => "PGK - Papua New Guinean Kina",
                    "PHP" => "PHP - Philippine Peso", "PKR" => "PKR - Pakistani Rupee",
                    "PLN" => "PLN - Polish Złoty", "PYG" => "PYG - Paraguayan Guarani",
                    "QAR" => "QAR - Qatari Rial", "RON" => "RON - Romanian Leu",
                    "RSD" => "RSD - Serbian Dinar", "RUB" => "RUB - Russian Ruble",
                    "RWF" => "RWF - Rwandan Franc", "SAR" => "SAR - Saudi Riyal",
                    "SBD" => "SBD - Solomon Islands Dollar", "SCR" => "SCR - Seychellois Rupee",
                    "SDG" => "SDG - Sudanese Pound", "SEK" => "SEK - Swedish Krona",
                    "SGD" => "SGD - Singapore Dollar", "SHP" => "SHP - Saint Helena Pound",
                    "SLL" => "SLL - Sierra Leonean Leone", "SOS" => "SOS - Somali Shilling",
                    "SRD" => "SRD - Surinamese Dollar", "SSP" => "SSP - South Sudanese Pound",
                    "STN" => "STN - São Tomé and Príncipe Dobra", "SVC" => "SVC - Salvadoran Colón",
                    "SYP" => "SYP - Syrian Pound", "SZL" => "SZL - Swazi Lilangeni",
                    "THB" => "THB - Thai Baht", "TJS" => "TJS - Tajikistani Somoni",
                    "TMT" => "TMT - Turkmenistani Manat", "TND" => "TND - Tunisian Dinar",
                    "TOP" => "TOP - Tongan Paʻanga", "TRY" => "TRY - Turkish Lira",
                    "TTD" => "TTD - Trinidad and Tobago Dollar", "TWD" => "TWD - New Taiwan Dollar",
                    "TZS" => "TZS - Tanzanian Shilling", "UAH" => "UAH - Ukrainian Hryvnia",
                    "UGX" => "UGX - Ugandan Shilling", "USD" => "USD - United States Dollar",
                    "UYU" => "UYU - Uruguayan Peso", "UZS" => "UZS - Uzbekistan Som",
                    "VES" => "VES - Venezuelan Bolívar Soberano", "VND" => "VND - Vietnamese Dong",
                    "VUV" => "VUV - Vanuatu Vatu", "WST" => "WST - Samoan Tala",
                    "XAF" => "XAF - CFA Franc BEAC", "XAG" => "XAG - Silver Ounce",
                    "XAU" => "XAU - Gold Ounce", "XCD" => "XCD - East Caribbean Dollar",
                    "XDR" => "XDR - Special Drawing Rights", "XOF" => "XOF - CFA Franc BCEAO",
                    "XPD" => "XPD - Palladium Ounce", "XPF" => "XPF - CFP Franc",
                    "XPT" => "XPT - Platinum Ounce", "YER" => "YER - Yemeni Rial",
                    "ZAR" => "ZAR - South African Rand", "ZMW" => "ZMW - Zambian Kwacha",
                    "ZWL" => "ZWL - Zimbabwean Dollar"
                ];

                foreach ($currencies as $code => $name) {
                    $selected = ($code === $currencyCode) ? 'selected' : '';
                    echo "<option value=\"$code\" $selected>$name</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="action_buying">buying notes</label>
            <textarea
                id="action_buying"
                name="action_buying"
                class="form-control"
                placeholder="will be added while handling invoices, delivery receipts and direct database interactions, so you can describe here: steps to buy something, which database tables to update,..."
                rows="4"
            ><?php echo htmlspecialchars($actionBuying); ?></textarea>
        </div>

        <div class="form-group">
            <label for="action_selling">selling notes</label>
            <textarea
                id="action_selling"
                name="action_selling"
                class="form-control"
                placeholder="will be added while handling invoices, delivery receipts and direct database interactions, so you can describe here: steps to sell something, which database tables to update,..."
                rows="4"
            ><?php echo htmlspecialchars($actionSelling); ?></textarea>
        </div>

        <div class="form-group">
            <label for="accent_color_for_pdf">accent color for PDF creation</label>
            <input
                type="color"
                id="accent_color_for_pdf"
                name="accent_color_for_pdf"
                class="form-control"
                value="<?php echo htmlspecialchars($accentColorForPDFCreation); ?>"
            >
        </div>

        <div class="form-group">
            <label for="additional_text_for_invoices">additional text for invoices</label>
            <textarea
                id="additional_text_for_invoices"
                name="additional_text_for_invoices"
                class="form-control"
                placeholder="Any additional text you want to appear on your invoices (terms, notes, etc.)"
                rows="4"
            ><?php echo htmlspecialchars($additionalTextForInvoices); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_payment_details">payment details (on invoices)</label>
            <textarea
                id="company_payment_details"
                name="company_payment_details"
                class="form-control"
                placeholder="for example: 50% upfront, 50% on delivery, new customers -> 100% upfront and established ones -> 14 days, ... (default:  payment within 14 days)"
                rows="4"
            ><?php echo htmlspecialchars($companyPaymentDetails); ?></textarea>
        </div>

        <div class="form-group">
            <label for="company_delivery_receipt_details">delivery receipt details</label>
            <textarea
                id="company_delivery_receipt_details"
                name="company_delivery_receipt_details"
                class="form-control"
                placeholder="any notes at the bottom of delivery receipts, for example your inspection and return policy, ... (default: notify discrepancies within 3 business days)"
                rows="4"
            ><?php echo htmlspecialchars($companyDeliveryReceiptDetails); ?></textarea>
        </div>

        <div class="form-group">
            <label for="useful_links">useful links</label>
            <textarea
                id="useful_links"
                name="useful_links"
                class="form-control"
                placeholder="www.tramann-projects.com, www.tnxapi.com, www.SomeUsefulLinkToShowAtTheStartingPage.com, ..."
                rows="4"
            ><?php echo htmlspecialchars($usefulLinks); ?></textarea>
        </div>

        <button type="submit" name="update_company" class="btn btn-primary">
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
</style>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea').forEach(textarea => {
      // record the initial rows value
      const initialRows = textarea.rows || 2;
      textarea.dataset.initialRows = initialRows;

      textarea.addEventListener('focus', () => {
        textarea.rows = initialRows + 10;
      });

      textarea.addEventListener('blur', () => {
        textarea.rows = textarea.dataset.initialRows;
      });
    });
  });
</script>
