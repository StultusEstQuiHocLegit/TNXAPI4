<?php
require_once('../config.php');
$currencies = require_once('../SETUP/currencies.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $firstName = htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $darkMode = isset($_POST['darkmode']) ? 1 : 0;
        $personalNotes = htmlspecialchars($_POST['personal_notes'] ?? '', ENT_QUOTES, 'UTF-8');
        $connectEmail = htmlspecialchars($_POST['connect_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $connectPassword = htmlspecialchars($_POST['connect_password'] ?? '', ENT_QUOTES, 'UTF-8');
        $connectEmailWritingInstructions = htmlspecialchars($_POST['connect_email_writing_instructions'] ?? '', ENT_QUOTES, 'UTF-8');
        $phoneNumberPrivate = !empty($_POST['phone_number_private']) ? (int)$_POST['phone_number_private'] : null;
        $phoneNumberWork = !empty($_POST['phone_number_work']) ? (int)$_POST['phone_number_work'] : null;
        $street = htmlspecialchars($_POST['street'] ?? '', ENT_QUOTES, 'UTF-8');
        $houseNumber = !empty($_POST['house_number']) ? (int)$_POST['house_number'] : null;
        $zipCode = htmlspecialchars($_POST['zip_code'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $country = htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES, 'UTF-8');
        $iban = htmlspecialchars($_POST['iban'] ?? '', ENT_QUOTES, 'UTF-8');
        $currencyCode = htmlspecialchars($_POST['currency_code'] ?? '', ENT_QUOTES, 'UTF-8');

        if ($email) {
            // Determine which table to use based on admin status
            $table = $_SESSION['IsAdmin'] ? 'admins' : 'users';
            
            // Check if email is already taken by another user in the appropriate table
            $stmt = $pdo->prepare("SELECT idpk FROM $table WHERE email = ? AND idpk != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if (!$stmt->fetch()) {
                // Base fields that are always updated
                $updateFields = [
                    "email = ?",
                    "FirstName = ?",
                    "LastName = ?",
                    "darkmode = ?",
                    "PersonalNotes = ?",
                    "ConnectEmail = ?",
                    "ConnectPassword = ?",
                    "ConnectEmailWritingInstructions = ?",
                    "PhoneNumberPrivate = ?"
                ];
                
                $params = [
                    $email,
                    $firstName,
                    $lastName,
                    $darkMode,
                    $personalNotes,
                    $connectEmail,
                    $connectPassword,
                    $connectEmailWritingInstructions,
                    $phoneNumberPrivate
                ];

                // Add fields for non-admin users
                if (!$_SESSION['IsAdmin']) {
                    $updateFields = array_merge($updateFields, [
                        "PhoneNumberWork = ?",
                        "street = ?",
                        "HouseNumber = ?",
                        "ZIPCode = ?",
                        "city = ?",
                        "country = ?"
                    ]);
                    
                    $params = array_merge($params, [
                        $phoneNumberWork,
                        $street,
                        $houseNumber,
                        $zipCode,
                        $city,
                        $country
                    ]);
                }

                // Add IBAN and CurrencyCode (these are always updated)
                $updateFields = array_merge($updateFields, [
                    "IBAN = ?",
                    "CurrencyCode = ?"
                ]);
                
                $params = array_merge($params, [
                    $iban,
                    $currencyCode,
                    $_SESSION['user_id']
                ]);

                $stmt = $pdo->prepare("UPDATE $table SET " . implode(", ", $updateFields) . " WHERE idpk = ?");
                
                if ($stmt->execute($params)) {
                    $success = 'Profile updated successfully';
                    $user['email'] = $email;
                    $user['FirstName'] = $firstName;
                    $user['LastName'] = $lastName;
                    $user['darkmode'] = $darkMode;
                    $user['PersonalNotes'] = $personalNotes;
                    $user['ConnectEmail'] = $connectEmail;
                    $user['ConnectPassword'] = $connectPassword;
                    $user['ConnectEmailWritingInstructions'] = $connectEmailWritingInstructions;
                    $user['PhoneNumberPrivate'] = $phoneNumberPrivate;
                    
                    if (!$_SESSION['IsAdmin']) {
                        $user['PhoneNumberWork'] = $phoneNumberWork;
                        $user['street'] = $street;
                        $user['HouseNumber'] = $houseNumber;
                        $user['ZIPCode'] = $zipCode;
                        $user['city'] = $city;
                        $user['country'] = $country;
                    }
                    
                    $user['IBAN'] = $iban;
                    $user['CurrencyCode'] = $currencyCode;
                } else {
                    $error = 'Error updating profile. Please try again.';
                }
            } else {
                $error = 'Email is already taken. Please choose another one.';
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    } elseif (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if ($currentPassword && $newPassword && $confirmPassword) {
            // Determine which table to use based on admin status
            $table = $_SESSION['IsAdmin'] ? 'admins' : 'users';
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM $table WHERE idpk = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentHash = $stmt->fetchColumn();

            if (password_verify($currentPassword, $currentHash)) {
                if ($newPassword === $confirmPassword) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE $table SET password = ? WHERE idpk = ?");
                    
                    if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                        $success = 'Password updated successfully.';
                    } else {
                        $error = 'Error updating password. Please try again.';
                    }
                } else {
                    $error = 'New passwords do not match.';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        } else {
            $error = 'Please fill in all password fields';
        }
    }
}
require_once('header.php');
?>

<div class="container" style="max-width: 800px; margin: auto;">
    <h1 class="text-center">⚙️ ACCOUNT</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="form-section">
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">email</label>
                <input type="email" id="email" name="email" placeholder="with this you login" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="profile_image"><?php echo $_SESSION['IsAdmin'] ? 'logo' : 'profile picture'; ?></label>
                <div id="drop-area" class="drop-area">
                    <div class="drop-area-content">
                        drag and drop your image here or click to select
                        <input type="file" id="file-input" accept="image/*,image/svg+xml,.svg" style="display: none;">
                    </div>
                </div>
                <div id="preview-container" class="preview-container">
                    <?php
                    $uploadDir = $_SESSION['IsAdmin'] ? '../UPLOADS/logos/' : '../UPLOADS/ProfilePictures/';
                    $userId = $user['idpk'];
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                    
                    foreach ($allowedExtensions as $ext) {
                        $imagePath = $uploadDir . $userId . '.' . $ext;
                        if (file_exists($imagePath)) {
                            echo '<div class="preview-item">';
                            echo '<a href="' . $imagePath . '?v=' . time() . '" target="_blank" class="preview-link">';
                            echo '<img src="' . $imagePath . '?v=' . time() . '" alt="Current image">';
                            echo '</a>';
                            echo '<button type="button" class="remove-btn" aria-label="remove image" onclick="removeImage(\'' . $ext . '\')"><span aria-hidden="true">&times;</span></button>';
                            echo '</div>';
                            break; // Only show one image
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="form-group">
                <label for="first_name">first name</label>
                <input type="text" id="first_name" name="first_name" placeholder="Nobody" value="<?php echo htmlspecialchars($user['FirstName']); ?>">
            </div>

            <div class="form-group">
                <label for="last_name">last name</label>
                <input type="text" id="last_name" name="last_name" placeholder="Nobody Who?" value="<?php echo htmlspecialchars($user['LastName']); ?>">
            </div>

            <div class="form-group">
                <label class="toggle-label">
                    <input type="checkbox" name="darkmode" id="darkmode" <?php echo $user['darkmode'] ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                    <span class="toggle-text">Come to the dark side, we have cookies!</span>
                </label>
            </div>

            <br><br>

            <div class="form-group">
                <label for="connect_email">email account(s) for sending</label>
                <input type="text" id="connect_email" name="connect_email" title="main@email.com | second@email.com | ..." placeholder="main@email.com | second@email.com | ..." value="<?php echo htmlspecialchars($user['ConnectEmail']); ?>" required>
            </div>

            <div class="form-group">
                <label for="connect_password">email password(s)</label>
                <input type="text" id="connect_password" name="connect_password" title="mainpassword | secondpassword | ..." placeholder="mainpassword | secondpassword | ..." value="<?php echo htmlspecialchars($user['ConnectPassword']); ?>">
            </div>

            <div class="form-group">
                <label for="connect_email_writing_instructions">email writing instructions</label>
                <textarea
                    id="connect_email_writing_instructions"
                    name="connect_email_writing_instructions"
                    class="form-control"
                    placeholder="explain how your emails should be written in general (for example: more formal or normal (xd), longer or always briefly, with or without emojis, ...)"
                    rows="10"
                ><?php echo htmlspecialchars($user['ConnectEmailWritingInstructions']); ?></textarea>
            </div>

            <br><br>

            <div class="form-group">
                <label for="personal_notes">personal notes</label>
                <textarea
                    id="personal_notes"
                    name="personal_notes"
                    class="form-control"
                    placeholder="repair scales, mow the lawn, seize world domination, ..."
                    rows="10"
                ><?php echo htmlspecialchars($user['PersonalNotes']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="phone_number_private">private phone number</label>
                <input type="number" id="phone_number_private" name="phone_number_private" placeholder="not required, but it increases your security" value="<?php echo isset($user['PhoneNumberPrivate']) ? htmlspecialchars($user['PhoneNumberPrivate']) : ''; ?>">
            </div>

            <?php if (!$_SESSION['IsAdmin']): ?>
                <div class="form-group">
                    <label for="phone_number_work">work phone number</label>
                    <input type="number" id="phone_number_work" name="phone_number_work" placeholder="ring, ring, ring" value="<?php echo isset($user['PhoneNumberWork']) ? htmlspecialchars($user['PhoneNumberWork']) : ''; ?>">
                </div>

                <br><br>

                <div class="form-group">
                    <label for="street">street</label>
                    <input type="text" id="street" name="street" placeholder="Mordor Avenue" value="<?php echo isset($user['street']) ? htmlspecialchars($user['street']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="house_number">house number</label>
                    <input type="number" id="house_number" name="house_number" placeholder="42" value="<?php echo isset($user['HouseNumber']) ? htmlspecialchars($user['HouseNumber']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="zip_code">ZIP code</label>
                    <input type="text" id="zip_code" name="zip_code" placeholder="introduced in 1963" value="<?php echo isset($user['ZIPCode']) ? htmlspecialchars($user['ZIPCode']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="city">city</label>
                    <input type="text" id="city" name="city" placeholder="big city life..." value="<?php echo isset($user['city']) ? htmlspecialchars($user['city']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="country">country</label>
                    <input type="text" id="country" name="country" placeholder="Anybody from Wakanda?" value="<?php echo isset($user['country']) ? htmlspecialchars($user['country']) : ''; ?>">
                </div>
            <?php endif; ?>

            <br><br>

            <div class="form-group">
                <label for="iban">IBAN</label>
                <input type="text" id="iban" name="iban" placeholder="a long number on your banking card" value="<?php echo isset($user['IBAN']) ? htmlspecialchars($user['IBAN']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="api_key">API key</label>
                <div class="api-key-container">
                    <input type="text" id="api_key" value="<?php echo htmlspecialchars($user['APIKey']); ?>" readonly>
                    <button type="button" id="copy_api_key" class="copy-btn">👀 COPY</button>
                </div>
            </div>

            <div class="form-group">
                <label for="currency_code">currency</label>
                <select id="currency_code" name="currency_code">
                    <?php
                    $currentCurrency = $user['CurrencyCode'] ?? '';
                    foreach ($currencies as $code => $name) {
                        $selected = ($code === $currentCurrency) ? 'selected' : '';
                        echo "<option value=\"$code\" $selected>{$code} ($name)</option>";
                    }
                    ?>
                </select>
            </div>

            <button type="submit" name="update_profile">↗️ SAVE</button>
        </form>
    </div>

    <br><br><br><br><br>
    <div class="form-section mt-3" style="opacity: 0.5;">
        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">current password</label>
                <input type="password" id="current_password" placeholder="just to make sure it is you" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">new password</label>
                <input type="password" id="new_password" placeholder="easy to remember but hard to guess" name="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">confirm new password</label>
                <input type="password" id="confirm_password" placeholder="type it again, think of it like a test" name="confirm_password" required>
            </div>

            <button type="submit" name="update_password">↗️ SAVE</button>
        </form>
    </div>

    <br><br><br><br><br><br><br><br><br><br>
    <div style="opacity: 0.2; text-align: center;"><a href="logout.php">🚪 LOGOUT</a></div>
</div>

<?php require_once('footer.php'); ?>

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

.drop-area {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: var(--input-bg);
}

.drop-area:hover {
    border-color: var(--primary-color);
    background-color: var(--bg-color);
}

.drop-area-content {
    color: var(--text-color);
    opacity: 0.7;
}

.preview-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.preview-item {
    position: relative;
    width: 100px;
    height: 100px;
    border-radius: 8px;
    overflow: hidden;
    background-color: var(--input-bg);
}

.preview-link {
    display: block;
    width: 100%;
    height: 100%;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}

.preview-link:hover {
    opacity: 0.8;
}

.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.preview-item button.remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    background-color: rgba(0, 0, 0, 0.5);
    border: none;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    padding: 0;
    line-height: 1;
    z-index: 1;
}

.preview-item button.remove-btn:hover {
    background-color: rgba(0, 0, 0, 0.7);
}

.api-key-container {
    display: flex;
    gap: 10px;
    align-items: center;
}

.api-key-container input {
    flex: 1;
    font-family: monospace;
}

.copy-btn {
    padding: 0.5rem 1rem;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
    width: auto;
}

.copy-btn:hover {
    background-color: var(--primary-hover);
}

.copy-btn.copied {
    background-color: var(--primary-color);
}
</style>

<script>
document.getElementById('darkmode').addEventListener('change', function() {
    document.documentElement.setAttribute('data-theme', this.checked ? 'dark' : '');
});

document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const previewContainer = document.getElementById('preview-container');

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    // Highlight drop area when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    // Handle dropped files
    dropArea.addEventListener('drop', handleDrop, false);
    
    // Handle click to upload
    dropArea.addEventListener('click', () => fileInput.click());
    
    // Handle file selection
    fileInput.addEventListener('change', handleFiles);

    function preventDefaults (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropArea.classList.add('highlight');
    }

    function unhighlight(e) {
        dropArea.classList.remove('highlight');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles({ target: { files: files } });
    }

    function handleFiles(e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
            formData.append('is_admin', '<?php echo $_SESSION['IsAdmin'] ? '1' : '0'; ?>');

            fetch('AjaxAccount.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error uploading image: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading image');
            });
        } else {
            alert('Please select an image file');
        }
    }
});

function removeImage(extension) {
    if (confirm('Are you sure you want to remove this image?')) {
        const formData = new FormData();
        formData.append('action', 'remove_image');
        formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
        formData.append('is_admin', '<?php echo $_SESSION['IsAdmin'] ? '1' : '0'; ?>');
        formData.append('extension', extension);

        fetch('AjaxAccount.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error removing image: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing image');
        });
    }
}

document.getElementById('copy_api_key').addEventListener('click', function() {
    const apiKey = document.getElementById('api_key').value;
    navigator.clipboard.writeText(apiKey).then(() => {
        const btn = this;
        btn.textContent = '✔️ COPIED';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = '👀 COPY';
            btn.classList.remove('copied');
        }, 3000);
    });
});

// Set the selected currency in the dropdown
document.getElementById('currency_code').value = '<?php echo htmlspecialchars($user['CurrencyCode']); ?>';
</script>

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
