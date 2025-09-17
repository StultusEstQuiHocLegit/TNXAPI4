<?php
require_once('../config.php');
require_once('header.php');
?>
<style>
.code {
    width: 100%;
    max-width: 100%;
    background-color: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    font-family: Consolas, monospace;
    font-size: 0.9rem;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-word;
    box-shadow: 0 2px 4px var(--shadow-color);
    margin-top: 1rem;
}

.code pre {
    margin: 0;
    padding: 0;
    background: none;
    border: none;
}

textarea {
    resize: vertical;
    overflow-x: hidden;
    overflow-y: auto;
}
</style>
<?php


// echo "<div class='container' style='max-width: 500px; margin: auto;'>";
//     echo "<h1 class='text-center'>🕸️ TRAMANN API</h1>";
// 
// 
// 
// 
// 
//     $apiKey = $user['APIKey'];
//     echo "<input type='hidden' id='userAPIKey' value='" . htmlspecialchars($user['APIKey'], ENT_QUOTES) . "'>";
// 
//     echo "<select name='contentAction' id='contentAction' required>";
//     echo "<option value='SELECT'>SELECT</option>";
//     echo "<option value='INSERT INTO'>INSERT INTO</option>";
//     echo "<option value='UPDATE'>UPDATE</option>";
//     echo "<option value='DELETE'>DELETE</option>";
//     echo "<option value='SEARCH'>SEARCH</option>";
//     echo "</select>";
// 
//     echo "<br><br>";
//     echo "<select name='contentTable' id='contentTable' required>";
//     echo "<option value='TDBcarts'>TDBcarts</option>";
//     echo "<option value='TDBtransaction'>TDBtransaction</option>";
//     echo "<option value='TDBProductsAndServices'>TDBProductsAndServices</option>";
//     echo "<option value='TDBSuppliersAndCustomers'>TDBSuppliersAndCustomers</option>";
//     echo "</select>";
// 
//     echo "<br><br>";
//     echo "<textarea id='contentFields' name='contentFields' placeholder='fields, for example: FirstField |#| SecondField |#| ..., (use - |#| - to separate)' rows='2'></textarea>";
//     echo "<br><span style='opacity: 0.3;'>separator:<span id='separator'> |#| </span><a href='#' id='copySeparatorLink' onclick=\"copyText(event, 'separator')\">👀 COPY</a></span>";
// 
//     echo "<br><br>";
//     echo "<textarea id='contentValues' name='contentValues' placeholder='values, for example: FirstValue |#| SecondValue |#| ..., (use - |#| - to separate)' rows='2'></textarea>";
// 
//     echo "<br><br>";
//     echo "<textarea id='contentIdpk' name='contentIdpk' placeholder='idpk' rows='1'></textarea>";
// 
//     echo "<br><br>";
//     echo "<div style='display: flex; justify-content: center; align-items: center; gap: 20px; margin-top: 20px;'>";
//     echo "<a href='#' id='runCodeLink' onclick=\"runCode()\"><button>🕷️ RUN CODE</button></a>";
//     echo "<a href='#' id='clearLink' onclick=\"clearInputs()\" style='display: none; opacity: 0.5;'>🧹 CLEAR</a>";
//     echo "</div>";
// 
// 
// 
// 
// 
// 
//     echo "<br><br><br><br><br>";
//     echo "<span style='float: left;'>TRAMANN API response:</span>";
//     echo "<div id='results' class='code' style='height: 100px;'></div>";
// 
// 
// 
// 
//     echo "<br><br><br><br><br>";
// 
//     echo "<a href='#' id='copyCodeLink' onclick=\"copyText(event, 'data')\" style='float: right;'>👀 COPY</a>";
//     echo "<div class='code' style='height: 200px;'>";
//         // build data here
//         echo '<pre id="data"></pre>';
//     echo "</div>";
// 
// 
// 
// 
//     echo "<br><br>";
// 
//     echo "<a href='#' id='copyCodeLink' onclick=\"copyText(event, 'fullCode')\" style='float: right;'>👀 COPY</a>";
//     echo "<div class='code'>";
//         // build fullCode here
//         echo '<pre id="fullCode"></pre>';
//     echo "</div>";
// 
// 
// echo "</div>";

















?>
<script>
    function copyText(event, elementId) {
        event.preventDefault(); // Prevent default link behavior
        // Get the text content of the element
        const text = document.getElementById(elementId).innerText;
        // Copy to clipboard
        navigator.clipboard.writeText(text).then(() => {
            // Change the link text to "COPIED"
            const copyLink = event.target;
            copyLink.innerHTML = '✔️ COPIED';

            // Optionally reset back to "COPY" after a short delay
            setTimeout(() => {
                copyLink.innerHTML = '👀 COPY';
            }, 3000);
        }).catch(err => {
            console.error('Failed to copy text:', err);
        });
    }







    // Utility to set a cookie
    function setCookie(name, value, days = 3650) { // 10 years
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/';
    }

    // Utility to get a cookie
    function getCookie(name) {
        return document.cookie.split('; ').reduce((r, v) => {
            const parts = v.split('=');
            return parts[0] === name ? decodeURIComponent(parts[1]) : r
        }, '');
    }

    // Save input changes to cookies
    function saveToCookies(id) {
        const value = document.getElementById(id).value;
        setCookie('TRAMANNAPIConnectionHelper_' + id, value);
    }

    // Restore from cookies on load
    function restoreFromCookies() {
        ['contentAction', 'contentTable', 'contentFields', 'contentValues', 'contentIdpk'].forEach(id => {
            const saved = getCookie('TRAMANNAPIConnectionHelper_' + id);
            if (saved) document.getElementById(id).value = saved;
        });
    }

    // Listen to input changes and save to cookies
    ['contentAction', 'contentTable', 'contentFields', 'contentValues', 'contentIdpk'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => {
            saveToCookies(id);
            updateDataCode();
            toggleClearButtonVisibility();
        });
    });

    function updateDataCode() {
        const apiKey = document.getElementById('userAPIKey').value;
        const action = document.getElementById('contentAction').value;
        const table = document.getElementById('contentTable').value;
        const fields = document.getElementById('contentFields').value;
        const values = document.getElementById('contentValues').value;
        const idpk = document.getElementById('contentIdpk').value;

        const data = {
            APIKey: apiKey,
            action: action,
            table: table,
            fields: fields,
            values: values,
            idpk: idpk
        };

        // Convert to PHP-style code preview
        let phpCode = "$data = [\n";
        for (let key in data) {
            phpCode += `    "${key}" => "${data[key]}",\n`;
        }
        phpCode += "];";

        console.log(data);

        document.getElementById('data').innerText = phpCode;












// Build full PHP example code
let fullPhpCode = `\u003C?php
    // TRAMANN PROJECTS - TRAMANN TNX API - TRAMANN API
    // official, version 3.4



    // Define the API endpoint
    $apiUrl = "https://www.tramann-projects.com/API/nexus.php";

    // Prepare the data
    $data = [
        "APIKey" => "${data.APIKey || ''}",
        "action" => "${data.action || ''}",
        "table" => "${data.table || ''}",
        "fields" => "${data.fields || ''}",
        "values" => "${data.values || ''}",
        "idpk" => "${data.idpk || ''}"
    ];

    // Initialize cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    // Execute the request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        echo "cURL error: " . curl_error($ch);
    } else {
        // Decode and handle the response
        $decodedResponseFromTRAMANNAPI = json_decode($response, true);

        echo "TRAMANN API response:\\n";
        echo json_encode($decodedResponseFromTRAMANNAPI, JSON_PRETTY_PRINT);
    }

    // Close cURL
    curl_close($ch);
?>`;

        document.getElementById('fullCode').innerText = fullPhpCode;

    }






    function runCode() {
        const apiKey = document.getElementById('userAPIKey').value;
        const action = document.getElementById('contentAction').value;
        const table = document.getElementById('contentTable').value;
        const fields = document.getElementById('contentFields').value;
        const values = document.getElementById('contentValues').value;
        const idpk = document.getElementById('contentIdpk').value;

        const data = {
            APIKey: apiKey,
            action: action,
            table: table,
            fields: fields,
            values: values,
            idpk: idpk
        };

        fetch("../API/nexus.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            // Display the result in the #results div
            document.getElementById('results').innerText = JSON.stringify(result, null, 4);
        })
        .catch(error => {
            document.getElementById('results').innerText = "Error: " + error;
        });
    }

    function clearInputs() {
        const fieldIds = ['contentFields', 'contentValues', 'contentIdpk'];

        fieldIds.forEach(id => {
            // Clear input
            document.getElementById(id).value = '';

            // Save empty value to cookies
            setCookie('TRAMANNAPIConnectionHelper_' + id, '');
        });

        // Clear displayed response
        document.getElementById('results').innerText = '';

        // Update code output and hide clear button
        updateDataCode();
        toggleClearButtonVisibility();
    }

    function toggleClearButtonVisibility() {
        const fields = ['contentFields', 'contentValues', 'contentIdpk'];
        const hasContent = fields.some(id => document.getElementById(id).value.trim() !== '');
        const clearLink = document.getElementById('clearLink');

        clearLink.style.display = hasContent ? 'inline-block' : 'none';
    }



    // Initial update on load
    window.onload = function() {
        restoreFromCookies();
        updateDataCode();
        toggleClearButtonVisibility();
        // runCode();
    };
</script>

<?php require_once('footer.php'); ?> 