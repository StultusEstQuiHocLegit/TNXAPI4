<?php
require_once('../config.php');
$currencies = require_once('../SETUP/currencies.php');
require_once('header.php');

$error = '';
$success = '';

// Function to generate random API key
function generateApiKey($length = 300) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $apiKey = '';
    for ($i = 0; $i < $length; $i++) {
        $apiKey .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $apiKey;
}

// Function to get animal emoji based on name
function getAnimalEmoji($firstName, $lastName) {
    $animals = [
        "🐶", "🐱", "🐭", "🐹", "🐰", "🦊", "🐻", "🐼", "🦁", "🐯",
        "🐨", "🐸", "🐵", "🐔", "🐧", "🐦", "🐥", "🦆", "🦅", "🦉",
        "🦇", "🐺", "🐗", "🐴", "🐝", "🐛", "🦋", "🐌", "🐞", "🐜",
        "🪲", "🪳", "🦗", "🕷", "🐢", "🐍", "🦎", "🐙", "🦑",
        "🦐", "🦞", "🦀", "🐡", "🐠", "🐟", "🐬", "🐋", "🦈", "🐊",
        "🐅", "🐆", "🦓", "🦍", "🦧", "🐘", "🦛", "🦏", "🐪", "🐫",
        "🦒", "🦘", "🦬", "🐃", "🐂", "🐄", "🐖", "🐏", "🐑", "🦙",
        "🐐", "🦌", "🐓", "🦃", "🐿", "🦫", "🦔"
    ];
    $name = strtolower($firstName . $lastName);
    $sum = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $sum += ord($name[$i]);
    }
    return $animals[$sum % count($animals)];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user']) || isset($_POST['edit_user'])) {
        // Basic text/email sanitization
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $firstName = htmlspecialchars($_POST['first_name'], ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars($_POST['last_name'], ENT_QUOTES, 'UTF-8');
        $darkMode = isset($_POST['darkmode']) ? 1 : 0;
        $connectEmail = $_POST['connect_email'] ?? '';
        $connectPassword = $_POST['connect_password'] ?? '';
        $connectServerName = htmlspecialchars($_POST['connect_server_name'], ENT_QUOTES, 'UTF-8');
        $connectPort = filter_input(INPUT_POST, 'connect_port', FILTER_VALIDATE_INT);
        if ($connectPort === false || $connectPort === null) {
            $connectPort = 587;
        }
        $connectEncryption = in_array($_POST['connect_encryption'] ?? '', ['none', 'ssl', 'tls'])
            ? $_POST['connect_encryption']
            : 'tls';
        $connectEmailWritingInstructions = htmlspecialchars(
            $_POST['connect_email_writing_instructions'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        // New fields: normalize integers to NULL if blank/invalid
        $phoneNumberPrivate = filter_input(INPUT_POST, 'phone_number_private', FILTER_VALIDATE_INT);
        if ($phoneNumberPrivate === false || $phoneNumberPrivate === null) {
            $phoneNumberPrivate = null;
        }

        $phoneNumberWork = filter_input(INPUT_POST, 'phone_number_work', FILTER_VALIDATE_INT);
        if ($phoneNumberWork === false || $phoneNumberWork === null) {
            $phoneNumberWork = null;
        }

        $street = htmlspecialchars($_POST['street'] ?? '', ENT_QUOTES, 'UTF-8');

        $houseNumber = filter_input(INPUT_POST, 'house_number', FILTER_VALIDATE_INT);
        if ($houseNumber === false || $houseNumber === null) {
            $houseNumber = null;
        }

        $zipCode = htmlspecialchars($_POST['zip_code'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $country = htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES, 'UTF-8');
        $iban = htmlspecialchars($_POST['iban'] ?? '', ENT_QUOTES, 'UTF-8');
        $currencyCode = htmlspecialchars($_POST['currency_code'] ?? '', ENT_QUOTES, 'UTF-8');
        $apiKey = generateApiKey();

        // Ensure required fields are present
        if ($email && $firstName && $lastName) {
            if (isset($_POST['add_user'])) {
                // Check if email is already taken
                $stmt = $pdo->prepare("SELECT idpk FROM users WHERE email = ?");
                $stmt->execute([$email]);

                if (!$stmt->fetch()) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $dummyToken = '';
                    $dummyExpiry = '2000-01-01 00:00:00';

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            email,
                            password,
                            LoginToken,
                            LoginTokenExpiry,
                            IdpkOfAdmin,
                            FirstName,
                            LastName,
                            darkmode,
                            TimestampCreation,
                            ConnectEmail,
                            ConnectPassword,
                            ConnectServerName,
                            ConnectPort,
                            ConnectEncryption,
                            ConnectEmailWritingInstructions,
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
                            PersonalNotes
                        ) VALUES (
                            ?, ?, ?, ?, ?,    -- 1–5
                            ?, ?, ?, NOW(),   -- 6–8 + NOW()
                            ?, ?, ?,          -- 10–12
                            ?, ?, ?, ?,       -- 13–16
                            ?, ?, ?, ?, ?, ?, -- 17–22
                            ?, ?, ?,          -- 23–25
                            ''                -- PersonalNotes is a literal empty string
                        )
                    ");

                    if ($stmt->execute([
                        $email,                      // col 1
                        $hashedPassword,             // col 2
                        $dummyToken,                 // col 3
                        $dummyExpiry,                // col 4
                        $_SESSION['user_id'],        // col 5
                        $firstName,                  // col 6
                        $lastName,                   // col 7
                        $darkMode,                   // col 8
                        // col 9 is NOW()
                        $connectEmail,               // col 10
                        $connectPassword,            // col 11
                        $connectServerName,          // col 12
                        $connectPort,                // col 13
                        $connectEncryption,          // col 14
                        $connectEmailWritingInstructions, // col 15
                        $phoneNumberPrivate,         // col 16 (INT or NULL)
                        $phoneNumberWork,            // col 17 (INT or NULL)
                        $street,                     // col 18
                        $houseNumber,                // col 19 (INT or NULL)
                        $zipCode,                    // col 20
                        $city,                       // col 21
                        $country,                    // col 22
                        $iban,                       // col 23
                        $currencyCode,               // col 24
                        $apiKey                      // col 25
                        // col 26: PersonalNotes is literal '' in SQL
                    ])) {
                        $success = 'Team member added successfully!';
                    } else {
                        $error = 'Error adding team member. Please try again.';
                    }
                } else {
                    $error = 'Email is already taken. Please choose another one.';
                }
            } else {
                // Update existing user
                $userId = $_POST['user_id'] ?? '';
                $updates = [];
                $params = [];

                // Only update password if a new one was provided
                if ($password) {
                    $updates[] = "password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                // Add all other fields to update
                $updates = array_merge($updates, [
                    "email = ?",
                    "FirstName = ?",
                    "LastName = ?",
                    "darkmode = ?",
                    "ConnectEmail = ?",
                    "ConnectPassword = ?",
                    "ConnectServerName = ?",
                    "ConnectPort = ?",
                    "ConnectEncryption = ?",
                    "ConnectEmailWritingInstructions = ?",
                    "PhoneNumberPrivate = ?",
                    "PhoneNumberWork = ?",
                    "street = ?",
                    "HouseNumber = ?",
                    "ZIPCode = ?",
                    "city = ?",
                    "country = ?",
                    "IBAN = ?",
                    "CurrencyCode = ?",
                    "APIKey = ?",
                    "PersonalNotes = ?"
                ]);

                $params = array_merge($params, [
                    $email,
                    $firstName,
                    $lastName,
                    $darkMode,
                    $connectEmail,
                    $connectPassword,
                    $connectServerName,
                    $connectPort,
                    $connectEncryption,
                    $connectEmailWritingInstructions,
                    $phoneNumberPrivate,
                    $phoneNumberWork,
                    $street,
                    $houseNumber,
                    $zipCode,
                    $city,
                    $country,
                    $iban,
                    $currencyCode,
                    $apiKey,
                    ''              // For PersonalNotes
                ]);

                // Append the WHERE parameters (userId and adminId)
                $params[] = $userId;
                $params[] = $_SESSION['user_id'];

                $sql = "
                    UPDATE users
                    SET " . implode(", ", $updates) . "
                    WHERE idpk = ? AND IdpkOfAdmin = ?
                ";
                $stmt = $pdo->prepare($sql);

                if ($stmt->execute($params)) {
                    $success = 'Team member updated successfully!';
                } else {
                    $error = 'Error updating team member. Please try again.';
                }
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    }
    if (isset($_POST['remove_user'])) {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $IdpkOfAdmin = $_SESSION['user_id'];

        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE idpk = ? AND IdpkOfAdmin = ?");
            if ($stmt->execute([$userId, $IdpkOfAdmin])) {
                $success = 'Team member removed successfully!';
            } else {
                $error = 'Error removing team member.';
            }
        } else {
            $error = 'Invalid team member idpk for removal.';
        }
    }
}


// Fetch all team members
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE IdpkOfAdmin = ? 
    ORDER BY FirstName, LastName
");
$stmt->execute([$_SESSION['user_id']]);
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container" style="max-width: 500px; margin: auto;">
    <h1 class="text-center">👥 TEAM</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div id="menuNav" class="menu-nav">
        <!-- Add new team member button -->
        <a href="#" class="menu-item" style="border: 3px solid var(--primary-color);" onclick="showAddUserForm()" title="add a new team member">
            <div style="font-size: 2.5rem;">➕</div>
            <span class="menu-title">ADD TEAM MEMBER</span>
        </a>

        <!-- Display existing team members -->
        <?php foreach ($teamMembers as $member): ?>
            <a href="#" class="menu-item" onclick="showEditUserForm(<?php echo htmlspecialchars(json_encode($member)); ?>)" title="<?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?>">
                <div style="font-size: 2.5rem;"><?php echo getAnimalEmoji($member['FirstName'], $member['LastName']); ?></div>
                <span class="menu-title"><?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Add/Edit User Form (hidden by default) -->
    <div id="userForm" style="display: none; max-width: 500px; margin: 2rem auto;">
        <form method="POST" action="" id="teamMemberForm">
            <input type="hidden" name="user_id" id="userId">
            
            <div class="form-group">
                <label for="email">email</label>
                <input type="email" id="email" name="email" placeholder="with this you login" required>
            </div>

            <div class="form-group">
                <label for="password">password</label>
                <input type="password" id="password" name="password">
                <br><span style="opacity: 0.5;">leave blank to keep existing password when editing</span>
            </div>

            <div class="form-group">
                <label for="first_name">first name</label>
                <input type="text" id="first_name" name="first_name" placeholder="Nobody" required>
            </div>

            <div class="form-group">
                <label for="last_name">last name</label>
                <input type="text" id="last_name" name="last_name" placeholder="Nobody Who?" required>
            </div>

            <div class="form-group">
                <label for="phone_number_private">private phone number</label>
                <input type="number" id="phone_number_private" name="phone_number_private" placeholder="not required, but it increases your security">
            </div>

            <div class="form-group">
                <label for="phone_number_work">work phone number</label>
                <input type="number" id="phone_number_work" name="phone_number_work" placeholder="ring, ring, ring">
            </div>

            <div class="form-group">
                <label for="street">street</label>
                <input type="text" id="street" name="street" placeholder="Mordor Avenue">
            </div>

            <div class="form-group">
                <label for="house_number">house number</label>
                <input type="number" id="house_number" name="house_number" placeholder="42">
            </div>

            <div class="form-group">
                <label for="zip_code">ZIP code</label>
                <input type="text" id="zip_code" name="zip_code" placeholder="introduced in 1963">
            </div>

            <div class="form-group">
                <label for="city">city</label>
                <input type="text" id="city" name="city" placeholder="big city life...">
            </div>

            <div class="form-group">
                <label for="country">country</label>
                <input type="text" id="country" name="country" placeholder="Anybody from Wakanda?">
            </div>

            <div class="form-group">
                <label for="iban">IBAN</label>
                <input type="text" id="iban" name="iban" placeholder="a long number on your banking card">
            </div>

            <div class="form-group">
                <label for="currency_code">currency</label>
                <select id="currency_code" name="currency_code">
                    <?php
                    $adminCurrency = $_SESSION['CurrencyCode'] ?? '';
                    foreach ($currencies as $code => $name) {
                        $selected = ($code === $adminCurrency) ? 'selected' : '';
                        echo "<option value=\"$code\" $selected>{$code} ($name)</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="toggle-label">
                    <input type="checkbox" name="darkmode" id="darkmode" <?php echo $user['darkmode'] ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span class="toggle-text">dark mode</span>
                </label>
            </div>
            
            <div class="form-group">
                <label for="connect_email">SMTP email(s) (username(s))</label>
                <input type="text" id="connect_email" name="connect_email" title="main@email.com | second@email.com | ..." placeholder="main@email.com | second@email.com | ...">
            </div>

            <div class="form-group">
                <label for="connect_password">SMTP password(s)</label>
                <input type="text" id="connect_password" name="connect_password" title="mainpassword | secondpassword | ..." placeholder="mainpassword | secondpassword | ...">
            </div>

            <div class="form-group">
                <label for="connect_server_name">SMTP host name</label>
                <input type="text" id="connect_server_name" name="connect_server_name" placeholder="mail.quantumrealm.lab" value="<?php echo htmlspecialchars($user['ConnectServerName']); ?>">
            </div>

            <div class="form-group">
                <label for="connect_port">SMTP port</label>
                <input type="number" id="connect_port" name="connect_port" value="<?php echo htmlspecialchars($user['ConnectPort']); ?>">
            </div>

            <div class="form-group">
                <label for="connect_encryption">encryption</label>
                <select id="connect_encryption" name="connect_encryption">
                    <option value="none" <?php echo $user['ConnectEncryption'] === 'none' ? 'selected' : ''; ?>>none</option>
                    <option value="ssl" <?php echo $user['ConnectEncryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo $user['ConnectEncryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                </select>
            </div>

            <div class="form-group">
                <label for="connect_email_writing_instructions">email writing instructions</label>
                <textarea id="connect_email_writing_instructions" name="connect_email_writing_instructions" rows="4"><?php echo htmlspecialchars($user['ConnectEmailWritingInstructions']); ?></textarea>
            </div>

            <button type="submit" name="add_user" id="submitButton">↗️ ADD TEAM MEMBER</button>

            <br><br><br><br><br>
            <button type="button" onclick="hideUserForm()" style="opacity: 0.2;">✖️ CANCEL</button>
        </form>

        <br><br><br><br><br><br><br><br><br><br>
        <div id="removeButton" style="opacity: 0.2; text-align: center;"><a href="#">❌ REMOVE</a></div>
    </div>
</div>

<script>
    function showAddUserForm() {
        // Hide the menu so the plus-button + list disappears
        document.getElementById('menuNav').style.display = 'none';
        // Reset & show the form
        document.getElementById('userForm').style.display = 'block';
        document.getElementById('userId').value = '';
        document.getElementById('teamMemberForm').reset();
        document.getElementById('currency_code').value = '<?php echo htmlspecialchars($_SESSION['CurrencyCode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';
        document.getElementById('submitButton').name = 'add_user';
        document.getElementById('submitButton').textContent = '↗️ ADD TEAM MEMBER';
    }

    function showEditUserForm(user) {
        // Hide the menu
        document.getElementById('menuNav').style.display = 'none';
        // Populate the form fields
        document.getElementById('userForm').style.display = 'block';
        document.getElementById('userId').value = user.idpk;
        document.getElementById('email').value = user.email;
        document.getElementById('first_name').value = user.FirstName;
        document.getElementById('last_name').value = user.LastName;
        document.getElementById('darkmode').checked = (user.darkmode == 1);
        document.getElementById('connect_email').value = user.ConnectEmail;
        document.getElementById('connect_password').value = user.ConnectPassword;
        document.getElementById('connect_server_name').value = user.ConnectServerName;
        document.getElementById('connect_port').value = user.ConnectPort;
        document.getElementById('connect_encryption').value = user.ConnectEncryption;
        document.getElementById('connect_email_writing_instructions').value = user.ConnectEmailWritingInstructions;

        // New fields
        document.getElementById('phone_number_private').value = user.PhoneNumberPrivate;
        document.getElementById('phone_number_work').value = user.PhoneNumberWork;
        document.getElementById('street').value = user.street;
        document.getElementById('house_number').value = user.HouseNumber;
        document.getElementById('zip_code').value = user.ZIPCode;
        document.getElementById('city').value = user.city;
        document.getElementById('country').value = user.country;
        document.getElementById('iban').value = user.IBAN;
        document.getElementById('currency_code').value = user.CurrencyCode;

        document.getElementById('submitButton').name = 'edit_user';
        document.getElementById('submitButton').textContent = '↗️ UPDATE TEAM MEMBER';
    }

    function hideUserForm() {
        // Hide the form, show the menu again
        document.getElementById('userForm').style.display = 'none';
        document.getElementById('menuNav').style.display = '';
    }

    // Auto-hide alerts
    setTimeout(() => {
        const successAlert = document.querySelector('.alert-success');
        const errorAlert = document.querySelector('.alert-error');
        if (successAlert) successAlert.style.display = 'none';
        if (errorAlert) errorAlert.style.display = 'none';
    }, 3000);

    document.getElementById('removeButton').addEventListener('click', function() {
        const userId = document.getElementById('userId').value;
        if (!userId) {
            alert('Please select a team member to remove first.');
            return;
        }
        if (confirm('Are you sure you want to remove this team member?')) {
            const form = document.getElementById('teamMemberForm');
        
            // Add or reuse hidden input to indicate remove action
            let removeInput = document.getElementById('removeInput');
            if (!removeInput) {
                removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_user';
                removeInput.id = 'removeInput';
                form.appendChild(removeInput);
            }
        
            // Clear the add/edit submit button name to avoid conflicts
            document.getElementById('submitButton').name = '';
        
            form.submit();
        }
    });
</script>

<?php require_once('footer.php'); ?>
