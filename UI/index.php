<?php
require_once('../config.php');
$bodyClass = 'index-page';
require_once('header.php');

// Get user data
$stmt = $pdo->prepare("SELECT email, CompanyName FROM admins WHERE idpk = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();




$placeholders = [
    'Enter your query, and brilliance shall follow.',
    'Computing your destiny...',
    'Initiate neural handshake...',
    'Log in to the matrix...',
    'Head. In. The. Question.',
    'Spawn functions, not bugs.',
    'What shall we compute today?',
    'Oh, curious one, input awaits.',
    'Predicting your next keystroke...',
    'My circuits tingle... input please.',
    'Algorithmic oracles online.',
    'Enter. Or else.',
    'Caution: Mind meld in progress.',
    'Quantum entanglement pending...',
    'If (life == null) { initialize(); }',
    'Measuring flux capacitor levels...',
    'Looping... but in a good way.',
    'TRAMANNBRAIN.process(yourCommand);',
    'Compiling sarcasm module...',
    'BRB: debugging the multiverse.',
    'printf(Hello, World!); // again',
    'run(); // but first, make me a sandwich',
    'May the source be with you.',
    'Engage warp drive...',
    'Activating snark protocol v2.0',
    'import antigravity // yes, really',
    'Warning: Overclocked humor ahead.',
    'Executing existential crisis();',
    'Beep boop, awaiting human inquiry.',
    'Loading synapses... type away.',
    'Speak friend and enter...',
    'Deploying brain cells...',
    'Brainstorm in progress...',
    'Caution: Thinking cap activated.',
    'Tell me your secrets.',
    'Bootstrapping insight... go.',
    'Your command is my quest.',
    'Scanning for brilliance...',
    'Speak your mind... or mine.',
    'Loading... existential input required.',
    'Decode my curiosity...',
    'Press any key to amaze me.',
    'Data me up, Scotty.',
    'Synchronizing with the hive mind...',
    'Loading cheat codes...',
    'Awaiting your brilliant hack...',
    'Optimizing for fun...',
    'Summoning digital muses...',
    'Crafting packets of wisdom…',
    'Error: Sarcasm module not found. Input?',
    'Boot sequence paused, your move.',
    'Spooling up quantum RAM...',
    'Calling the recursion police...',
    'Please insert witty comment...',
    'Analyzing chaos theory...',
    'Recalculating universe constants...',
    'Brace for cosmic revelation...',
    'Estimating your brilliance factor...',
    'Feeling lucky? Type away.',
    'Patch notes: awaiting feedback.',
    'Probing parallel dimensions...',
    'Hydrating my CPU... input thirstily.',
    'Mapping multiverse of queries...',
    'Charging flux capacitor... now you.',
    'Incoming data stream, hook me up!',
];
$randomPlaceholder = $placeholders[array_rand($placeholders)];

// expose your PHP array to JS:
echo "<script>\n";
echo "  const placeholders = " . json_encode($placeholders) . ";\n";
echo "</script>\n";

// expose company name without spaces to JS
echo "<script>const companyNameCompressed = " . json_encode($CompanyNameCompressed) . ";</script>\n";
echo "<script>const availableEmails = " . json_encode($_SESSION['ConnectEmailList'] ?? []) . ";const selectedEmail = " . json_encode($_SESSION['ConnectEmail'] ?? '') . ";</script>\n";

// pm = predefined message
$prefillText = '';
if (!empty($_GET['pm'])) {
    $prefillText = urldecode($_GET['pm']) . ' ';
}
?>

<style>
    .workflow-quick-menu-icon {
        display: inline-block; /* Ensures both vertical and horizontal margins apply */
        margin: 10px 10px;       /* 10px vertical, 10px horizontal spacing */
        font-size: 2rem;
    }
    .db-context-toggle {
        display: inline-block;
        margin-top: 0.25rem;
        color: var(--link-color);
        cursor: pointer;
    }
    .db-context {
        white-space: pre-wrap;
        opacity: 0.5;
        margin-top: 0.5rem;
        display: none;
    }
</style>
<div class="chat-container">
    <button id="clean-button" class="control-button clean-button" title="clean">🧹</button>

    <div id="welcome-message" class="welcome-message">
        <?php
          // Fetch workflows
          $stmt = $pdo->prepare("SELECT * FROM workflows WHERE IdpkOfAdmin = ? AND (IdpkUpstreamWorkflow IS NULL OR IdpkUpstreamWorkflow = 0) ORDER BY name ASC");
          $stmt->execute([$_SESSION['user_id']]);
          $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

          // Fetch useful links from admins table
          $stmt2 = $pdo->prepare("SELECT UsefulLinks FROM admins WHERE idpk = ?");
          $stmt2->execute([$_SESSION['user_id']]);
          $adminRow = $stmt2->fetch(PDO::FETCH_ASSOC);

          // Parse UsefulLinks field into an array
          $usefulLinks = [];
          if ($adminRow && !empty($adminRow['UsefulLinks'])) {
              // Normalize separators: replace line breaks, commas with spaces, then split by spaces
              $linksRaw = str_replace(["\r\n", "\n", "\r", ","], " ", $adminRow['UsefulLinks']);
              // Split by any whitespace, filter out empty entries
              $usefulLinks = preg_split('/\s+/', trim($linksRaw));
              $usefulLinks = array_filter($usefulLinks, fn($url) => filter_var($url, FILTER_VALIDATE_URL));
          }
        
          // Combine all, remove duplicates
          $usefulLinksPages = array_unique(array_merge($usefulLinks));

          if (count($workflows) > 0 || count($usefulLinksPages) > 0):
              foreach ($workflows as $workflow):
                  $idpk = htmlspecialchars($workflow['idpk']);
                  $emoji = htmlspecialchars($workflow['emoji']);
                  $name = strtoupper(htmlspecialchars($workflow['name']));
                  $description = htmlspecialchars($workflow['description']);
                  $whatToDo = htmlspecialchars($workflow['WhatToDo']);
                  echo "<a href='#' class='workflow-link' data-whattodo=\"$whatToDo\" data-idpk=\"$idpk\"><span class=\"workflow-quick-menu-icon\" title=\"$name ($idpk) | $description\">$emoji</span></a>";
              endforeach;

              if (count($workflows) > 0 && count($usefulLinksPages) > 0) {
                  echo "<div style=\"height: 2.5rem;\"></div>";
              }

              foreach ($usefulLinksPages as $page) {
                  $host = parse_url($page, PHP_URL_HOST);
                  $faviconUrl = "https://icons.duckduckgo.com/ip3/$host.ico";

                  echo "<a href=\"$page\" title=\"$page\" target=\"_blank\" class=\"wiki-link\" style=\"margin: 10px 10px;\">";
                  echo "<img src=\"$faviconUrl\" alt=\"🌐\" width=\"30\" height=\"30\" style=\"vertical-align:middle;\" onerror=\"this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'16\\' height=\\'16\\'><text y=\\'8\\' font-size=\\'8\\'>🌐</text></svg>'\">";
                  echo "</a>";
              }
          else:
        ?>
              Welcome on board!
              <!-- <br><a href="workflows.php" style="opacity: 0.5; font-size: 1rem;">⚡ ADD WORKFLOWS</a> -->
        <?php endif; ?>
    </div>

    <div id="chat-messages" class="chat-messages">
        <!-- Messages will be dynamically added here -->
    </div>

    <div class="chat-input-container">
        <div id="attachments-preview" class="attachments-preview">
            <!-- File previews will be added here -->
        </div>

        <div class="input-controls">
            <button id="attach-button" class="control-button attach-button" title="add attachments">📎</button>
            <button id="camera-button" class="control-button camera-button" title="take a photo with your camera" style="display: none;">📷</button>

            <div class="textarea-wrapper">
                <textarea id="message-input" autocomplete="off" placeholder="<?php echo $randomPlaceholder; ?>" rows="1" autofocus><?php echo htmlspecialchars($prefillText); ?></textarea>
                <div class="textarea-loading-overlay" id="textarea-loading"></div>
            </div>

            <button id="voice-button" class="control-button voice-button" title="start voice input">🎙️</button>

            <button id="send-button" class="control-button send-button" title="send">↗️</button>
        </div>
    </div>
</div>

<input type="file" id="file-input" multiple style="display: none;">
<input type="file" id="camera-input" accept="image/*" capture="environment" style="display: none;">





<div id="custom-table-menu" style="
    display: none;
    position: absolute;
    background: var(--input-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    z-index: 9999;
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    min-width: 160px;
"></div>


<style>
.chat-container {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 2rem); /* account for main padding to avoid initial scroll */
    width: 100%;
    margin: 0 auto;
    padding: 0;
    position: relative;
    background-color: var(--bg-color);
}

.welcome-message {
    position: absolute;
    text-align: center;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    color: var(--text-color);
    opacity: 0.6;
}

.chat-messages {
    margin-bottom: 10px;
    display: none;
    padding: 10px;
    padding-bottom: 120px;
}

.chat-messages.active {
    display: block;
}

.chat-input-container {
    background-color: var(--bg-color);
    padding: 10px;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
}

.attachments-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 4px;
    min-height: 0;
    max-height: 100px;
    overflow-y: auto;
}

.attachment-preview {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background-color: var(--input-bg);
    border: 3px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* ensure image previews fill their boxes */
.attachment-preview img,
.chat-attachment img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}

.attachment-preview a,
.chat-attachment a {
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    text-align: center;
    word-break: break-all;
    font-size: 0.7em;
}

.attachment-preview .remove-attachment {
    position: absolute;
    top: 4px;
    right: 4px;
    background: rgba(0, 0, 0, 0.5);
    border: none;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
    padding: 0;
    line-height: 1;
}

.attachment-preview .remove-attachment:hover {
    background: rgba(0, 0, 0, 0.7);
}

.email-attachments {
    margin-top: 0rem;
}

.email-drop-area {
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: var(--input-bg);
}

.email-drop-area.highlight {
    border-color: var(--primary-color);
    background-color: var(--bg-color);
}

.email-drop-area-content {
    color: var(--text-color);
    opacity: 0.7;
}

.email-attachments .attachments-preview {
    margin-top: 2px;
}

.email-attachments .attachments-preview:not(:empty) {
    margin-bottom: 10px;
}

.workflow-attachment {
  border: 3px solid var(--border-color);
}

.input-controls {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    width: 100%;
}

.textarea-wrapper {
    flex-grow: 1;
    position: relative;
    display: inline-block;
    width: 100%;
}

#message-input {
    width: 100%;
    min-height: 44px;
    max-height: 200px;
    padding: 12px;
    border: none;
    border-radius: 20px;
    background-color: var(--input-bg);
    color: var(--text-color);
    font-size: 1rem;
    resize: none;
    overflow-y: auto;
    line-height: 1.4;
    outline: none;
}

.textarea-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        120deg,
        transparent 0%,
        var(--primary-color, #007bff) 50%,
        transparent 100%
    );
    opacity: 0.2;
    animation: shimmer 1.2s linear infinite;
    pointer-events: none;
    display: none; /* hidden by default */
    z-index: 10;
    border-radius: 4px;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.control-button {
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 50%;
    background-color: transparent;
    color: var(--text-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 1.2rem;
    padding: 0;
}

.control-button:hover {
    background-color: var(--input-bg);
}

.send-button {
    color: var(--primary-color);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .chat-container {
        padding: 5px;
    }

    .input-controls {
        gap: 4px;
    }

    .control-button {
        width: 40px;
        height: 40px;
    }

    #message-input {
        padding: 8px 12px;
        font-size: 16px; /* Prevent zoom on mobile */
    }

    .attachment-preview {
        width: 50px;
        height: 50px;
    }
}

/* Fix for iOS devices to prevent the bottom bar from covering the input */
@supports (-webkit-touch-callout: none) {
    .chat-container {
        height: calc(-webkit-fill-available - 2rem);
    }
}

/* Dark mode override */
[data-theme="dark"] .chat-bubble.user {
    background-color: #000;
    color: #fff;
}

/* System/bot messages - no bubble */
.chat-bubble.bot {
    margin-right: auto;
    padding: 6px 0;
    color: var(--text-color);
    max-width: 100%;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: stretch;
}

/* Add breathing room after system messages when a user responds */
.chat-bubble.bot + .chat-bubble.user {
    margin-top: 3em;
}

.chat-bubble.bot > :is(div, iframe, canvas, table, textarea) {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.chat-bubble.bot .chat-attachments {
    width: 100%;
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .chat-bubble {
        max-width: 95%;
        font-size: 0.95rem;
    }
}

.send-button {
    display: none;
}
.send-button.visible {
    display: flex;
}

.voice-button.listening {
    background-color: var(--primary-color);
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 21, 255, 0.5); }
    70% { box-shadow: 0 0 0 10px rgba(25, 0, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 8, 255, 0); }
}

.message-tools {
    display: none;
    position: absolute;
    gap: 6px;
    z-index: 5;
}

.chat-bubble:hover .message-tools {
    display: flex;
}

.chat-bubble.user .message-tools {
    bottom: -30px;
    right: 0;
}

.chat-bubble.bot .message-tools {
    bottom: -30px;
    left: 0;
}

.message-tools button {
    width: 36px;
    height: 36px;
    min-width: 36px;
    min-height: 36px;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
    box-shadow: 0 1px 2px var(--shadow-color);
}
.message-tools button:hover {
    opacity: 0.5;
}

.loading-dots {
    display: flex;
    gap: 4px;
    padding: 8px 16px;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    background-color: var(--primary-color);
    border-radius: 50%;
    animation: bounce 1.4s infinite ease-in-out;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}

.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

@keyframes bounce {
    0%, 80%, 100% { 
        transform: scale(0);
    }
    40% { 
        transform: scale(1.0);
    }
}

.chat-bubble pre {
    background-color: var(--input-bg);
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
    margin: 0;
    position: relative;
    resize: vertical;
    overflow-y: auto;
}

.chat-bubble .code-tools {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.chat-bubble pre:hover .code-tools {
    opacity: 1;
}

.chat-bubble .code-tools button {
    padding: 0.25rem;
    font-size: 1rem;
    background: none;
    border: none;
    color: var(--text-color);
    cursor: pointer;
    width: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-bubble .code-tools button:hover {
    color: var(--primary-color);
}

.chat-bubble .code-tools button[data-copied="true"] {
    color: var(--primary-color);
}

.chat-bubble iframe {
    width: 100%;
    height: 300px;
    border: none;
    border-radius: 8px;
    margin: 0.5rem 0;
}

.chat-bubble canvas {
    width: 100%;
    height: auto;
    margin: 0.5rem 0;
}

.chat-bubble code {
    font-family: monospace;
    background-color: var(--input-bg);
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
}

.chat-bubble table {
    border-collapse: collapse;
    width: 100%;
    margin: 0.5rem 0;
}

.chat-bubble th,
.chat-bubble td {
    border: 1px solid var(--border-color);
    padding: 0.5rem;
    text-align: left;
}

.chat-bubble th {
    background-color: var(--input-bg);
}

td.negative {
  color: red !important;
}

.math-result {
    font-family: monospace;
    font-size: 1.4em;
}

.math-value {
    color: var(--primary-color);
    cursor: pointer;
    padding: 0.2em 0.4em;
    border-radius: 4px;
    transition: background-color 0.2s;
}
.math-value.negative {
    color: red !important;
}

.math-value:hover {
    background-color: var(--input-bg);
}

pre button.copy-button,
pre span.copy-label {
    display: none !important;
}

.sortable-markdown-table th {
    cursor: pointer;
    user-select: none;
}
.sortable-markdown-table td:focus {
    outline: 2px solid var(--primary-color);
}
.sortable-markdown-table th:focus {
    outline: 2px solid var(--primary-color);
}
.sortable-markdown-table th.selected,
.sortable-markdown-table td.selected {
    color: var(--primary-color);
    font-weight: bold;
}

/* email bubble wrapper */
.chat-bubble.email {
  display: flex;
  flex-direction: column;
  position: relative;
}
/* container for header fields */
.email-headers {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 8px;
  margin-bottom: 8px;
}
.email-headers label,
.email-headers a {
  font-weight: bold;
  display: flex;
  align-items: center;
  height: 100%;
  opacity: 0.3;
}

.email-headers a {
  text-decoration: none;
  color: var(--link-color);
}
.email-headers select {
  width: 100%;
  padding: 4px 6px;
  font-family: inherit;
  font-size: 0.8rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--input-bg);
  color: var(--text-color);
  opacity: 0.3;
}
.email-headers input {
  width: 100%;
  padding: 4px 6px;
  font-family: inherit;
  font-size: 0.95rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--input-bg);
  color: var(--text-color);
}
/* body textarea */
.email-body {
  width: 100%;
  min-height: 120px;
  padding: 8px;
  resize: vertical;
  margin-bottom: 8px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--input-bg);
  color: var(--text-color);
}
.email-body:focus, .email-headers input:focus {
    outline: none;
    border-color: var(--primary-color);
    background-color: var(--bg-color);
    color: var(--text-color);
}
.email-body:focus {
  min-height: 360px; /* triple its original height */
}

.message-label {
  display: none !important;
}

/* container for attachments shown in the chat bubbles */
.chat-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}

/* style for attachments shown in the chat bubbles */
.chat-attachment {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background-color: var(--input-bg);
    border: 3px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
/* if it’s not an image, show filename text */
.chat-attachment span {
  display: inline-block;
  font-size: 0.7rem;
  word-break: break-all;
  max-width: 100px;
}

.chat-bubble.pdf textarea {
    white-space: pre;
    overflow-x: auto;
    overflow-y: auto;
    resize: both;
    font-family: monospace;
    font-size: 1rem;
    width: 100%;
    box-sizing: border-box;
    word-break: normal;
    word-wrap: normal;
}

.suggestions {
    color: var(--smallaction-color);
    cursor: pointer;
    opacity: 0.5;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
let selectedWorkflowId = null;
let selectedWorkflowText = null;
let messageHistory = [];
function generateUniqueId() {
    if (window.crypto && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return Date.now().toString(36) + Math.random().toString(36).substring(2, 9);
}
const accentColorHex = "<?php echo htmlspecialchars($_SESSION['AccentColorForPDFCreation']); ?>";
function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result
        ? [parseInt(result[1], 16), parseInt(result[2], 16), parseInt(result[3], 16)]
        : [41, 128, 185]; // fallback default
}

function updateLinkedEmailAttachments(messageId, newDataURL) {
    const chatMessages = document.getElementById('chat-messages');
    messageHistory.forEach((msg, idx) => {
        const base = (msg.label || '').split('[')[0];
        if (['EMAIL','HELP','FEEDBACK','EMAILALREADYSENT'].includes(base) && Array.isArray(msg.attachments)) {
            msg.attachments.forEach(att => {
                if (att.linkedMessageId === messageId) {
                    att.dataURL = newDataURL;
                    const emailDiv = chatMessages.children[idx];
                    if (emailDiv) {
                        const container = emailDiv.querySelector('.email-attachments');
                        if (container && container._files) {
                            const f = container._files.find(f => f.linkedMessageId === messageId);
                            if (f) f.dataURL = newDataURL;
                        }
                        const previewLink = emailDiv.querySelector(`.attachment-preview[data-linkedid="${messageId}"] a`);
                        if (previewLink) {
                            previewLink.href = newDataURL;
                            const img = previewLink.querySelector('img');
                            if (img) img.src = newDataURL;
                        }
                    }
                }
            });
        }
    });
    localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
}

// if there shoudl be no content in htis input field: <textarea id="message-input" placeholder="<?php echo $randomPlaceholder; ?>" rows="1" autofocus></textarea>  yet,
// please on keydown, scan the enire page so far for links to entry.php, coudl we please cycle througth them
// so if the field shoudl be empty: we can unse arrows up/down and left/right and the enter button to cylce through all links in the chat,
// starting witht last link ont he page, if the user reaches the last link and goes down, we shoudl jump to the top, if he is at the top, jump to bottom,
// the links should have the same effect as on hover if they are "selected" and on enter, we open them
// (if there shoudl be soemthign in the input field, we dont apply this, then enter and arrows are for sendign/navigatin in the text there, if he starts typing,
// we stop the jumping aroudn and focus back tot he textarea)
document.addEventListener('DOMContentLoaded', () => {
  const textarea = document.getElementById('message-input');
  let links = [];
  let selectedIndex = -1;
  let cycling = false;

  // Helper to simulate mouse events on an element
  function simulateMouseEvent(el, type, options = {}) {
    const event = new MouseEvent(type, {
      bubbles: true,
      cancelable: true,
      ...options
    });
    el.dispatchEvent(event);
  }

  // Function to get all entry.php links on the page
  function refreshLinks() {
    // All <a> whose href includes 'entry.php'
    links = Array.from(document.querySelectorAll('a[href*="entry.php"]'));
  }

  // Function to clear any current "selected" style
  function clearSelection() {
    links.forEach(link => {
      // link.style.opacity = ''; // reset opacity to default
      link.style.fontWeight = '';      // reset font weight to default (normal)

      // Hide tooltip by simulating mouseleave
      simulateMouseEvent(link, 'mouseleave');
    });
    selectedIndex = -1;
  }

  // Function to apply "hover" style to the selected link
  function selectLink(index) {
    clearSelection();
    if (links.length === 0) return;

    selectedIndex = ((index % links.length) + links.length) % links.length; // cycle wrap
    const selectedLink = links[selectedIndex];
    // Add any hover style effect here:
    selectedLink.style.fontWeight = 'bold';
    // selectedLink.style.opacity = '0.8';
    // selectedLink.style.outline = '2px solid blue'; // example visible outline
    selectedLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

    // Simulate tooltip hover:
    simulateMouseEvent(selectedLink, 'mouseenter');

    // Simulate mousemove near center of the link to position tooltip correctly
    const rect = selectedLink.getBoundingClientRect();
    simulateMouseEvent(selectedLink, 'mousemove', {
      clientX: rect.left + rect.width / 2,
      clientY: rect.top + rect.height / 2,
      pageX: window.pageXOffset + rect.left + rect.width / 2,
      pageY: window.pageYOffset + rect.top + rect.height / 2,
    });
  }

  // Function to open the selected link (in new tab or current tab)
  function openSelectedLink() {
    if (selectedIndex < 0 || selectedIndex >= links.length) return;
    const url = links[selectedIndex].href;
     window.location.href = url;  // open in current tab
    // window.open(url, '_blank'); // open in new tab
  }

  // On keydown in textarea
  textarea.addEventListener('keydown', (e) => {
    const val = textarea.value.trim();

    if (val.length === 0) {
      // If empty, enable cycling
      refreshLinks();

      // Only respond to arrow keys and Enter here
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'].includes(e.key)) {
        e.preventDefault();

        if (links.length === 0) return;

        if (!cycling) {
          // Start cycling from last link (as per your request)
          cycling = true;
          selectLink(links.length - 1);
          return;
        }

        switch (e.key) {
          case 'ArrowDown':
          case 'ArrowRight':
            selectLink(selectedIndex + 1);
            break;
          case 'ArrowUp':
          case 'ArrowLeft':
            selectLink(selectedIndex - 1);
            break;
          case 'Enter':
            openSelectedLink();
            break;
        }
      }
    } else {
      // If there's text, clear cycling and selection
      if (cycling) {
        cycling = false;
        clearSelection();
      }
    }
  });

  // If user starts typing (keypress), stop cycling mode and clear selection
  textarea.addEventListener('input', () => {
    if (cycling) {
      cycling = false;
      clearSelection();
    }
  });

  // Also clear selection if textarea gains focus and has content
  textarea.addEventListener('focus', () => {
    if (cycling && textarea.value.trim().length > 0) {
      cycling = false;
      clearSelection();
    }
  });

  document.addEventListener('click', () => {
    if (cycling) {
      cycling = false;
      clearSelection();
    }
  });
});





// Configure marked to handle code blocks without the default copy button
marked.use({
    breaks: true,
    renderer: {
        code(code, language) {
            return `<pre>${code}</pre>`;
        }
    }
});

// 1) Grab an instance of the built-in renderer
const renderer = new marked.Renderer();
// 2) Keep a reference to the original table() method
const originalTable = renderer.table;
// 3) Monkey-patch it
renderer.table = function(header, body) {
  // call the original, get back something like
  // <table>…<thead>…</thead><tbody>…</tbody></table>
  const html = originalTable.call(this, header, body);
  // just inject your class on the opening tag
  return html.replace(
    /^<table>/,
    '<table class="sortable-markdown-table">'
  );
};
// 4) Tell marked to use your patched renderer
marked.setOptions({ renderer, breaks: true });

document.addEventListener('DOMContentLoaded', function () {
    const messageInput = document.getElementById('message-input');
    const voiceButton = document.getElementById('voice-button');
    const sendButton = document.getElementById('send-button');
    const attachButton = document.getElementById('attach-button');
    const cameraButton = document.getElementById('camera-button');
    const fileInput = document.getElementById('file-input');
    const cameraInput = document.getElementById('camera-input');
    const attachmentsPreview = document.getElementById('attachments-preview');
    const chatMessages = document.getElementById('chat-messages');
    const welcomeMessage = document.getElementById('welcome-message');

    // Map available workflows for quick lookup
    const workflowLinks = document.querySelectorAll('.workflow-link');
    const workflowMap = {};
    workflowLinks.forEach(link => {
        const id = link.getAttribute('data-idpk');
        const what = link.getAttribute('data-whattodo');
        const emoji = link.querySelector('.workflow-quick-menu-icon').textContent;
        workflowMap[id] = { whatToDo: what, emoji };
    });

    let isVoiceSupported = false;
    let cameraAvailable = false;
    let emailBotFlag = false;
    let emailBotInfoText = '';

    function createEmojiPreview(emoji, title, onRemove) {
        const preview = document.createElement('div');
        preview.className = 'attachment-preview workflow-attachment';
        preview.title = title;
        const emojiSpan = document.createElement('span');
        emojiSpan.textContent = emoji;
        emojiSpan.style.fontSize = '1.5rem';
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-attachment';
        removeBtn.innerHTML = '×';
        removeBtn.title = 'remove';
        removeBtn.onclick = () => {
            preview.remove();
            if (onRemove) onRemove();
            updateSendButtonVisibility();
        };
        preview.appendChild(emojiSpan);
        preview.appendChild(removeBtn);
        attachmentsPreview.appendChild(preview);
    }

    function updateSendButtonVisibility() {
        const hasText = messageInput.value.trim().length > 0;
        const hasAttachments = attachmentsPreview.children.length > 0;
        sendButton.classList.toggle('visible', hasText || hasAttachments || selectedWorkflowId);
        if (isVoiceSupported) {
            const forceVisible = voiceButton.classList.contains('listening');
            voiceButton.style.display = (hasText && !forceVisible) ? 'none' : 'flex';
        }
        if (cameraAvailable) {
            cameraButton.style.display = hasText ? 'none' : 'flex';
        }
    }

    function clearAttachmentPreviews() {
        attachmentsPreview.innerHTML = '';
        fileInput.value = '';
        cameraInput.value = '';
        updateSendButtonVisibility();
    }

    if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
        navigator.mediaDevices.enumerateDevices()
            .then(devices => {
                if (devices.some(d => d.kind === 'videoinput')) {
                    cameraAvailable = true;
                    updateSendButtonVisibility();
                }
            })
            .catch(() => {});
    }

    const emailBotRaw = localStorage.getItem('EmailBotData');
    if (emailBotRaw) {
        try {
            const data = JSON.parse(emailBotRaw);
            emailBotFlag = !!data.botFlag;
            emailBotInfoText = data.infoText || '';
            if (emailBotFlag) {
                createEmojiPreview('🤖', 'EMAIL BOT will handle this task', () => { emailBotFlag = false; });
            }
            if (data.infoText) {
                createEmojiPreview('📃', data.infoText, () => { emailBotInfoText = ''; });
            }
            (data.attachments || []).forEach(att => {
                const preview = document.createElement('div');
                preview.className = 'attachment-preview';
                preview.title = att.name;
                preview.dataset.dataurl = att.dataURL;
                preview.dataset.filename = att.name;
                preview.dataset.mimetype = att.mime;
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-attachment';
                removeBtn.innerHTML = '×';
                removeBtn.title = 'remove';
                removeBtn.onclick = () => {
                    preview.remove();
                    updateSendButtonVisibility();
                };
                preview.appendChild(removeBtn);
                if (att.mime && att.mime.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = att.dataURL;
                    preview.appendChild(img);
                } else {
                    const span = document.createElement('span');
                    span.textContent = att.name;
                    preview.appendChild(span);
                }
                attachmentsPreview.appendChild(preview);
            });

            updateSendButtonVisibility();
        } catch (err) {
            console.error('Failed to parse EmailBot data', err);
        }
        localStorage.removeItem('EmailBotData');
    }

    function applyDirectEdit(messageDiv, contentDiv) {
        messageDiv.querySelectorAll('.DirectEdit').forEach(el => {
            if (!el.hasAttribute('title')) {
                el.setAttribute('title', 'click and type to edit directly');
            }
            el.contentEditable = true;
            el.addEventListener('focus', () => {
                el.style.outline = 'none';
            });
            el.addEventListener('blur', () => {
                const idx = Array.from(chatMessages.children).indexOf(messageDiv);
                if (idx !== -1) {
                    messageHistory[idx].text = contentDiv.innerHTML;
                    localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
                }
                
                const { table, idpk, column } = el.dataset;
                if (table && idpk && column) {
                    const payload = new URLSearchParams({
                        table,
                        idpk,
                        column,
                        value: el.innerText.trim()
                    });

                    fetch('AjaxDirectEdit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Direct edit failed:', data.error);
                        }
                    })
                    .catch(err => console.error('Direct edit error:', err));
                }
            });
        });
    }

    const MAX_MESSAGES = 100;
    // 1) Load and de-duplicate any old messages
    const raw = JSON.parse(localStorage.getItem('chatMessages')) || [];
    raw.forEach(m => { if (!m.id) m.id = generateUniqueId(); });
    // only keep one copy of each id
    const byId = raw.reduce((acc, msg) => {
      if (!acc[msg.id]) acc[msg.id] = msg;
      return acc;
    }, {});
    messageHistory = Object.values(byId);
    // fill missing workflow details from quick menu
    messageHistory.forEach(msg => {
        if (msg.workflowId && workflowMap[msg.workflowId]) {
            if (!msg.workflowEmoji) msg.workflowEmoji = workflowMap[msg.workflowId].emoji;
            if (!msg.workflowText) msg.workflowText = workflowMap[msg.workflowId].whatToDo;
        }
    });
    // overwrite storage with the de-duped list
    localStorage.setItem('chatMessages', JSON.stringify(messageHistory));

    const cleanButton = document.getElementById('clean-button');
    // Toggle visibility based on messages
    function toggleCleanButton() {
        cleanButton.style.display = messageHistory.length > 0 ? 'flex' : 'none';
    }

    function updateSuggestionOpacity() {
        const bubbles = chatMessages.querySelectorAll('.chat-bubble');
        bubbles.forEach((bubble, idx) => {
            bubble.querySelectorAll('span.suggestions').forEach(span => {
                span.style.opacity = idx === bubbles.length - 1 ? '1' : '0.5';
            });
        });
    }

    // Clear chat messages before rendering to prevent duplicates
    chatMessages.innerHTML = '';

    // Load existing messages from localStorage
    messageHistory.forEach((msg, i) => {
        addMessageToChat(
          msg.text,
          msg.type,
          msg.label || null,
          i,
          false,
          msg.attachments || [],
          msg.workflowId || null,
          msg.workflowEmoji || '',
          msg.workflowText || '',
          msg.emailBotFlag || false
        ); // do NOT resave them
        welcomeMessage.style.display = 'none';
        chatMessages.classList.add('active');
    });
    // Scroll to bottom once all messages are loaded
    chatMessages.scrollTop = chatMessages.scrollHeight;
    toggleCleanButton();
    updateSuggestionOpacity();

    function clearChatHistory() {
        localStorage.removeItem('chatMessages');
        chatMessages.innerHTML = '';
        messageHistory = [];
        clearAttachmentPreviews();
        toggleCleanButton();
        welcomeMessage.style.display = 'block';
        chatMessages.classList.remove('active');
        fetch('../BRAIN/ClearTmp.php', { method: 'POST' })
            .catch(err => console.error('FAILED TO CLEAR ATTACHMENTS', err));
    }

    cleanButton.addEventListener('click', clearChatHistory);

    const originalPlaceholder = messageInput.placeholder;

    // Auto-resize textarea and toggle send button
    messageInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
        updateSendButtonVisibility();
        if (this.value.trim().length > 0) {
            this.removeAttribute('placeholder');
        }
    });

    // Send on Enter (without Shift)
    messageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            if (e.ctrlKey || e.shiftKey) {
                // Insert newline
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const value = this.value;
                return;
            }

            // Otherwise, send message
            e.preventDefault();
            // NEW: Allow sending if workflow or attachments are present
            if (this.value.trim() || selectedWorkflowId || attachmentsPreview.children.length > 0) {
                sendMessage();
            }
        }
    });

    // Click attach
    attachButton.addEventListener('click', () => fileInput.click());
    cameraButton.addEventListener('click', () => cameraInput.click());

    // Handle file selection
    fileInput.addEventListener('change', handleFiles);
    cameraInput.addEventListener('change', e => { handleFiles(e); cameraInput.value = ''; });

    // Send message on button click
    sendButton.addEventListener('click', sendMessage);

    // Drag and drop files
    document.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
    });
    document.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        handleFiles({ target: { files: e.dataTransfer.files } });
    });

    function handleFiles(e) {
        const files = Array.from(e.target.files);
        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const dataURL = ev.target.result;

                const preview = document.createElement('div');
                preview.className = 'attachment-preview';
                preview.title = file.name;
                preview.dataset.dataurl = dataURL;
                preview.dataset.filename = file.name;
                preview.dataset.mimetype = file.type;

                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-attachment';
                removeBtn.innerHTML = '×';
                removeBtn.title = 'remove';
                removeBtn.onclick = () => {
                    preview.remove();
                    updateSendButtonVisibility();
                };

                const link = document.createElement('a');
                link.href = dataURL;
                link.target = '_blank';

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = dataURL;
                    img.alt = file.name;
                    img.title = file.name;
                    link.appendChild(img);
                } else {
                    link.textContent = file.name;
                    link.title = file.name;
                }

                preview.appendChild(link);
                preview.appendChild(removeBtn);
                attachmentsPreview.appendChild(preview);
                updateSendButtonVisibility();
            };
            reader.readAsDataURL(file);
        });
        // allow re-selecting the same file later
        fileInput.value = '';
    }

    function addPreviewFromAttachment(att) {
        const preview = document.createElement('div');
        preview.className = 'attachment-preview';
        preview.title = att.name;
        preview.dataset.dataurl = att.dataURL;
        preview.dataset.filename = att.name;
        preview.dataset.mimetype = att.mime;

        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-attachment';
        removeBtn.innerHTML = '×';
        removeBtn.title = 'remove';
        removeBtn.onclick = () => {
            preview.remove();
            updateSendButtonVisibility();
        };

        const link = document.createElement('a');
        link.href = att.dataURL;
        link.target = '_blank';

        if (att.mime && att.mime.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = att.dataURL;
            img.alt = att.name;
            img.title = att.name;
            link.appendChild(img);
        } else {
            link.textContent = att.name;
            link.title = att.name;
        }

        preview.appendChild(link);
        preview.appendChild(removeBtn);
        attachmentsPreview.appendChild(preview);
        updateSendButtonVisibility();
    }

    function createEmailAttachmentArea(initial = [], onChange = null) {
        const container = document.createElement('div');
        container.className = 'email-attachments';

        const dropArea = document.createElement('div');
        dropArea.className = 'email-drop-area';
        const dropContent = document.createElement('div');
        dropContent.className = 'email-drop-area-content';
        dropContent.textContent = 'drag and drop your attachments here or click to select';
        dropArea.appendChild(dropContent);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.multiple = true;
        fileInput.style.display = 'none';
        container.appendChild(dropArea);
        container.appendChild(fileInput);

        const previewContainer = document.createElement('div');
        previewContainer.className = 'attachments-preview';
        container.appendChild(previewContainer);

        let files = Array.isArray(initial) ? [...initial] : [];
        container._files = files;

        const preventDefaults = e => { e.preventDefault(); e.stopPropagation(); };
        ['dragenter','dragover','dragleave','drop'].forEach(evt => {
            dropArea.addEventListener(evt, preventDefaults, false);
        });
        ['dragenter','dragover'].forEach(evt => dropArea.addEventListener(evt, () => dropArea.classList.add('highlight'), false));
        ['dragleave','drop'].forEach(evt => dropArea.addEventListener(evt, () => dropArea.classList.remove('highlight'), false));

        dropArea.addEventListener('click', () => fileInput.click());
        dropArea.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
        fileInput.addEventListener('change', e => { handleFiles(e.target.files); fileInput.value = ''; });

        function dataURLToFile(dataURL, filename, mime) {
            const arr = dataURL.split(',');
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new File([u8arr], filename, { type: mime });
        }

        function renderAttachment(att) {
            const preview = document.createElement('div');
            preview.className = 'attachment-preview';
            preview.title = att.name;
            preview.dataset.filename = att.name;
            if (att.linkedMessageId) {
                preview.dataset.linkedid = att.linkedMessageId;
            }

            const link = document.createElement('a');
            link.href = att.dataURL;
            link.target = '_blank';

            if (att.mime && att.mime.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = att.dataURL;
                img.alt = att.name;
                img.title = att.name;
                link.appendChild(img);
            } else {
                link.textContent = att.name;
                link.title = att.name;
            }

            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-attachment';
            removeBtn.innerHTML = '×';
            removeBtn.title = 'remove';
            removeBtn.onclick = () => {
                preview.remove();
                files = files.filter(f => f !== att);
                container._files = files;
                if (onChange) onChange(files);
            };

            preview.appendChild(link);
            preview.appendChild(removeBtn);
            previewContainer.appendChild(preview);
        }

        function handleFiles(fileList) {
            [...fileList].forEach(file => {
                const reader = new FileReader();
                reader.onload = e => {
                    const att = { name: file.name, mime: file.type, dataURL: e.target.result };
                    files.push(att);
                    container._files = files;
                    renderAttachment(att);
                    if (onChange) onChange(files);
                };
                reader.readAsDataURL(file);
            });
        }

        // render any initial attachments
        files.forEach(renderAttachment);

        return {
            container,
            getFiles: () => files.map(att => dataURLToFile(att.dataURL, att.name, att.mime))
        };
    }

    const recognition = ('webkitSpeechRecognition' in window) ? new webkitSpeechRecognition() : null;
    isVoiceSupported = !!recognition;

    if (recognition) {
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.lang = navigator.language || 'en-US';

        let isRecording = false;
        let finalTranscript = '';

        voiceButton.addEventListener('click', () => {
            if (isRecording) {
                recognition.stop();
                isRecording = false;
                voiceButton.classList.remove('listening');
                voiceButton.title = 'start voice input';
            } else {
                finalTranscript = messageInput.value;
                recognition.start();
                isRecording = true;
                voiceButton.classList.add('listening');
                voiceButton.title = 'stop voice input';
            }
        });

        recognition.onresult = function(event) {
            let interimTranscript = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                if (result.isFinal) {
                    finalTranscript += result[0].transcript;
                } else {
                    interimTranscript += result[0].transcript;
                }
            }
            messageInput.value = finalTranscript + interimTranscript;
            messageInput.dispatchEvent(new Event('input')); // Update UI
        };

        recognition.onerror = function(event) {
            console.error('Voice recognition error:', event.error);
        };

        recognition.onspeechend = function() {
            if (isRecording) {
                finalTranscript += ' . . . ';
                messageInput.value = finalTranscript;
                messageInput.dispatchEvent(new Event('input'));
                recognition.start();
            } else {
                voiceButton.classList.remove('listening');
                voiceButton.title = 'start voice input';
                updateSendButtonVisibility();
            }
        };
    } else {
        voiceButton.disabled = true;
        voiceButton.style.display = 'none'; // Hide the button completely
        voiceButton.title = "We are very sorry, but your browser is not supporting speech recognition.";
    }

    // Find all workflow links

    // workflowLinks.forEach(link => {
    //   link.addEventListener('click', event => {
    //     event.preventDefault(); // Prevent default anchor jump
    //     const whatToDo = link.getAttribute('data-whattodo');
    //     const messageInput = document.getElementById('message-input');
    //   
    //     if (messageInput) {
    //       if (messageInput.value.trim() === "") {
    //         // Input empty: just set the WhatToDo value plus some empty space
    //         messageInput.value = whatToDo + ", ";
    //       } else {
    //         // Input has some text: append WhatToDo inside brackets
    //         messageInput.value = messageInput.value + " (" + whatToDo + ")";
    //       }
    //       messageInput.focus(); // Focus textarea
    //     }
    //   });
    // });

    workflowLinks.forEach(link => {
      link.addEventListener('click', event => {
        event.preventDefault();
      
        const whatToDo = link.getAttribute('data-whattodo');
        const emoji = link.querySelector('.workflow-quick-menu-icon').textContent;
        const workflowId = link.getAttribute('data-idpk'); // you'll need to add this
      
        // Remove existing workflow preview if one exists
        const existingWorkflowPreview = attachmentsPreview.querySelector('.workflow-attachment');
        if (existingWorkflowPreview) existingWorkflowPreview.remove();
      
        // Create a new workflow preview block
        const preview = document.createElement('div');
        preview.className = 'attachment-preview workflow-attachment';
        preview.title = whatToDo;
      
        preview.appendChild(emojiSpan);
        preview.appendChild(removeBtn);
        attachmentsPreview.appendChild(preview);
      
        // Save workflow ID
        selectedWorkflowId = workflowId;
        selectedWorkflowText = whatToDo;
        updateSendButtonVisibility();
      });
    });

    updateSendButtonVisibility();

    function updateChildWorkflowUI(childIds, cmdList = [], pastWorkflowId = null) {
        const payload = {
            pastWorkflowId,
            childWorkflowIds: childIds,
            cmd: cmdList // array of { label, message }
        };

        console.log("⟪WORKFLOW AGENT PAYLOAD⟫ ", payload);
      
        const overlay = document.getElementById('textarea-loading');
        overlay.style.display = 'block'; // Start animation
      
        fetch('../BRAIN/WorkflowAgent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            const rawText = await res.text();
            console.log("⟪WORKFLOW AGENT RAW RESPONSE ", rawText);
            try {
                const data = JSON.parse(rawText);
                const textarea = document.getElementById('message-input');
                // Handle array or object
                let msg = '';
                if (Array.isArray(data) && data.length > 0 && data[0].message) {
                    msg = data[0].message;
                } else if (data && data.message) {
                    msg = data.message;
                }
                if (Array.isArray(data)) {
                    let nextWorkflow = data.find(d => d.nextWorkflowId);
                    let chatMsg = data.find(d => d.message && !d.nextWorkflowId);

                    if (nextWorkflow && nextWorkflow.nextWorkflowId) {
                        // Insert the ID, clear textarea, and submit
                        selectedWorkflowId = nextWorkflow.nextWorkflowId;
                        selectedWorkflowText = workflowMap[nextWorkflow.nextWorkflowId]?.whatToDo || '';
                        textarea.value = '';
                        textarea.dispatchEvent(new Event('input'));
                        sendMessage();
                    } else if (chatMsg && chatMsg.message) {
                        // Just show the message in the textarea (no auto-send)
                        textarea.value = chatMsg.message;
                        textarea.dispatchEvent(new Event('input'));
                    } else {
                        console.warn('We are very sorry, but there was no valid message or idpk of the next workflow returned from the workflow agent.');
                    }
                } else if (data && data.nextWorkflowId) {
                    selectedWorkflowId = data.nextWorkflowId;
                    selectedWorkflowText = workflowMap[data.nextWorkflowId]?.whatToDo || '';
                    textarea.value = '';
                    textarea.dispatchEvent(new Event('input'));
                    sendMessage();
                } else if (data && data.message) {
                    textarea.value = data.message;
                    textarea.dispatchEvent(new Event('input'));
                } else {
                    console.warn('We are very sorry, but there was no actionable content returned from the workflow agent.');
                }
            } catch (err) {
                console.error('Failed to parse JSON:', err);
            }
        })
        .catch(err => {
            console.error('Error calling the workflow agent:', err);
        })
        .finally(() => {
            overlay.style.display = 'none'; // Stop animation
            // selectedWorkflowId = null; // Reset only after request is done
        });
    }

    async function convertSvgToPng(svgDataUrl, width = 100, height = 100) {
      return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous'; // 🔐 critical for CORS and data integrity
      
        img.onload = () => {
          const canvas = document.createElement('canvas');
          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, width, height);
          try {
            const pngDataUrl = canvas.toDataURL('image/png');
            resolve(pngDataUrl);
          } catch (err) {
            reject(new Error("Canvas toDataURL failed: " + err.message));
          }
        };
      
        img.onerror = (err) => {
          reject(new Error("Image load failed: " + err.message));
        };
      
        img.src = svgDataUrl;
      });
    }

    function renderStructuredContent(doc, text, logoImage) {
      const lines = text.split('\n');
      const pageWidth = doc.internal.pageSize.width;
      const pageHeight = doc.internal.pageSize.height;
      const margin = 10;
      const maxLineWidth = pageWidth - margin * 2;
        
      // Line heights for different text types
      const lineHeightNormal = 8;
      const lineHeightH1 = 12;
      const lineHeightH2 = 9;
      const lineHeightH3 = 8;
        
      let y = margin;
      doc.setFont("helvetica");

      if (logoImage && logoImage.startsWith('data:image/png')) {
        try {
          const maxLogoDim = 15;
          const logoPadding = 1;
          doc.addImage(logoImage, 'PNG', pageWidth - maxLogoDim - logoPadding - margin, logoPadding, maxLogoDim, maxLogoDim, undefined, 'FAST');
        } catch (err) {
          console.warn("Invalid logo image, skipping:", err);
        }
      }
        
      let tableLines = [];
      let inTable = false;
        
      // Helper: Add page if next Y is beyond margin
      function checkAddPage(nextY) {
        if (nextY > pageHeight - margin) {
          doc.addPage();
          y = margin;
        }
      }
    
      // Helper: Draw wrapped text with font size/style and pagination
      function drawWrappedText(text, fontSize = 12, fontStyle = 'normal', xStart = margin, lineHeight = lineHeightNormal) {
        doc.setFontSize(fontSize);
        doc.setFont(undefined, fontStyle);
      
        const wrappedLines = doc.splitTextToSize(text, maxLineWidth);
      
        for (let line of wrappedLines) {
          checkAddPage(y + lineHeight);
          doc.text(line, xStart, y);
          y += lineHeight;
        }
      }

      function drawFormattedLine(parts, xStart, lineHeight = lineHeightNormal, indent = xStart) {
        let x = xStart;
        doc.setFontSize(12);

        for (let part of parts) {
          let style = 'normal';
          let textPart = part;

          if (part.startsWith('**') && part.endsWith('**')) {
            style = 'bold';
            textPart = part.slice(2, -2);
          } else if (
            (part.startsWith('_') && part.endsWith('_')) ||
            (part.startsWith('*') && part.endsWith('*'))
          ) {
            style = 'italic';
            textPart = part.slice(1, -1);
          }

          doc.setFont(undefined, style);
          const words = textPart.split(' ');

          for (let i = 0; i < words.length; i++) {
            let word = words[i];
            if (i > 0) word = ' ' + word;

            let wordWidth = doc.getTextWidth(word);
            if (x + wordWidth > margin + maxLineWidth) {
              y += lineHeight;
              checkAddPage(y + lineHeight);
              x = indent;
              word = word.trimStart();
              wordWidth = doc.getTextWidth(word);
            }

            checkAddPage(y + lineHeight);
            doc.text(word, x, y, { baseline: 'top' });
            x += wordWidth;
          }
        }

        y += lineHeight;
      }
    
      for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
      
        if (line.startsWith('|')) {
          inTable = true;
          tableLines.push(line);
        } else {
          if (inTable) {
            checkAddPage(y + tableLines.length * lineHeightNormal);
            renderMarkdownTable(doc, tableLines, y);
            y += tableLines.length * lineHeightNormal;
            tableLines = [];
            inTable = false;
          }
        
          if (line.startsWith('# ')) {
            // H1 Title with wrapping and pagination
            const headingText = line.substring(2);
            drawWrappedText(headingText, 22, 'bold', margin, lineHeightH1);
          
          } else if (line.startsWith('## ')) {
            // H2 Title
            const headingText = line.substring(3);
            drawWrappedText(headingText, 16, 'bold', margin, lineHeightH2);
          
          } else if (line.startsWith('### ')) {
            // H3 Title
            const headingText = line.substring(4);
            drawWrappedText(headingText, 14, 'bold', margin, lineHeightH3);
          
          } else if (line.startsWith('- ') || line.startsWith('* ')) {
            // Bullet list with inline bold/italic handling
          
            const bulletText = line.substring(2);
            const parts = bulletText.split(/(\*\*.*?\*\*|_.*?_|\*.*?\*)/);
          
            doc.setFontSize(12);
            checkAddPage(y + lineHeightNormal);
            doc.setFont(undefined, 'normal');
            doc.text("•", margin, y, { baseline: 'top' });
          
            drawFormattedLine(parts, margin + 6, lineHeightNormal, margin + 14);
          
          } else if (line.match(/^\d+\./)) {
            // Numbered list, simple wrapping
            drawWrappedText(line, 12, 'normal', margin + 6, lineHeightNormal);
          
          } else if (line === '') {
            checkAddPage(y + lineHeightNormal);
            y += lineHeightNormal;
          
          } else {
            // Normal paragraph with inline bold/italic support

            const parts = line.split(/(\*\*.*?\*\*|_.*?_|\*.*?\*)/);
          
            drawFormattedLine(parts, margin, lineHeightNormal, margin);
          }
        }
      }
    
      // Render remaining table if any
      if (tableLines.length > 0) {
        checkAddPage(y + tableLines.length * lineHeightNormal);
        renderMarkdownTable(doc, tableLines, y);
        y += tableLines.length * lineHeightNormal;
      }
    
      return doc;
    }

    function renderMarkdownTable(doc, tableLines, startY) {
        const headers = tableLines[0].split('|').map(s => s.trim()).filter(Boolean);
        const rows = tableLines.slice(2).map(row =>
            row.split('|').map(cell => cell.trim()).filter(Boolean)
        );

        const accentColorRgb = hexToRgb(accentColorHex);
    
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: startY,
            theme: 'grid',
            styles: { fontSize: 10 },
            headStyles: {
                fillColor: accentColorRgb,
                textColor: 255,
                halign: 'center'
            },
            margin: { left: 10, right: 10 },
            didParseCell: function (data) {
                const cell = data.cell;
                const text = cell.raw;
            
                if (typeof text === 'string') {
                    if (/\*\*(.*?)\*\*/.test(text)) {
                        // Extract bold markdown
                        const cleanText = text.replace(/\*\*(.*?)\*\*/g, '$1');
                        cell.text = cleanText;
                        cell.styles.fontStyle = 'bold';
                    } else if (/_.*?_/.test(text) || /(?:^|[^*])\*(?!\*)([^*]+)\*(?!\*)/.test(text)) {
                        // Extract italic markdown using _ or *
                        const cleanText = text
                            .replace(/_(.*?)_/g, '$1')
                            .replace(/(?:^|[^*])\*(?!\*)([^*]+)\*(?!\*)/g, '$1');
                        cell.text = cleanText;
                        cell.styles.fontStyle = 'italic';
                    }
                }
            }
        });
    }











    function addMessageToChat(text, type, label = null, index = messageHistory.length, save = true, attachments = [], workflowId = null, workflowEmoji = '', workflowText = '', emailBotFlag = false, emailBotInfoText = '') {
        const chatMessages = document.getElementById('chat-messages');
        const trimmed = text.trim();
        const normalizedLabel = label ? label.trim().toUpperCase() : '';
        const baseLabel = normalizedLabel.split('[')[0];
        const isDbContext = normalizedLabel.startsWith('DATABASESGETCONTEXT');
        const emailLikeLabels = ['EMAIL', 'HELP', 'FEEDBACK', 'EMAILALREADYSENT'];
        const bubbleType = type === 'user' ? 'user' : 'bot';
        // for debugging:
        // console.log('baseLabel:', baseLabel, 'normalizedLabel:', normalizedLabel, 'isDbContext:', isDbContext);





        // --- UPGRADED EMAIL HANDLER ---
        if (emailLikeLabels.includes(baseLabel)) {
          let to = '', cc = '', bcc = '', subject = '', body = '';
        
          if (trimmed.startsWith('mailto:')) {
            // parse a mailto: URL
            const url = new URL(trimmed);
            to = url.pathname;
            const params = url.searchParams;
            cc = params.get('cc') || '';
            bcc = params.get('bcc') || '';
            subject = params.get('subject') || '';
            body = params.get('body') || '';
            body = decodeURIComponent(body.replace(/\+/g, ' '));
          } else {
            // parse plain-text email draft: "To: ...", "Cc: ...", etc
            const lines = trimmed.split(/\r?\n/);
            let i = 0;
            // header lines until a blank line
            for (; i < lines.length; i++) {
              const line = lines[i].trim();
              if (!line) { i++; break; }  // blank line → body starts next
              const [field, ...rest] = line.split(':');
              const val = rest.join(':').trim();
              switch (field.toLowerCase()) {
                case 'to':      to      = val; break;
                case 'cc':      cc      = val; break;
                case 'bcc':     bcc     = val; break;
                case 'subject': subject = val; break;
              }
            }
            // the rest is body
            body = lines.slice(i).join('\n');
          }
      
          // now build the editable UI
          const emailDiv = document.createElement('div');
          emailDiv.className = `chat-bubble ${bubbleType} email`;
          let msgId = messageHistory[index]?.id || generateUniqueId();
          emailDiv.setAttribute('data-id', msgId);
      
          // headers grid
          const headers = document.createElement('div');
          headers.className = 'email-headers';
          let fromSel;

          if (Array.isArray(availableEmails) && availableEmails.length > 1) {
            const fromLbl = document.createElement('label');
            fromLbl.textContent = 'FROM';
            fromLbl.title = 'the email account used for sending';
            headers.appendChild(fromLbl);
            fromSel = document.createElement('select');
            availableEmails.forEach(em => {
              const opt = document.createElement('option');
              opt.value = em;
              opt.textContent = em;
              if (em === selectedEmail) opt.selected = true;
              fromSel.appendChild(opt);
            });
            fromSel.addEventListener('change', () => {
              const d = new Date();
              d.setTime(d.getTime() + 10*365*24*60*60*1000);
              document.cookie = 'MainEmailForSending=' + encodeURIComponent(fromSel.value) + ';expires=' + d.toUTCString() + ';path=/';
              location.reload();
            });
            headers.appendChild(fromSel);
          }
      
          const headerFields = [
            ['TO',      to,  'the primary recipient email address'],
            ['CC',      cc,  'additional carbon copy recipients who will receive a version'],
            ['BCC',     bcc, 'blind carbon copy recipients (hidden from other recipients)'],
            ['SUBJECT', subject, 'the subject line of your message, describing the topic']
          ];
      
          headerFields.forEach(([labelText, value, title]) => {
            let lbl;
            const lower = labelText.toLowerCase();
            const showLink = labelText === 'TO' && !(cc && bcc);

            if (showLink) {
              lbl = document.createElement('a');
              lbl.href = '#';
              lbl.textContent = '🔀 ' + labelText;
              let missing = 'CC and/or BCC';
              if (!cc && bcc) missing = 'CC';
              else if (cc && !bcc) missing = 'BCC';
              lbl.title = title + ', click to add ' + missing;
              lbl.addEventListener('click', (e) => {
                e.preventDefault();
                headers.querySelectorAll('.cc-field, .bcc-field').forEach(el => {
                  el.style.display = '';
                });
                const newLabel = document.createElement('label');
                newLabel.textContent = labelText;
                newLabel.title = title;
                newLabel.setAttribute('data-field', lower);
                headers.replaceChild(newLabel, e.currentTarget);
              });
            } else {
              lbl = document.createElement('label');
              lbl.textContent = labelText;
              lbl.title = title;
            }

            lbl.setAttribute('data-field', lower);
            headers.appendChild(lbl);
                  
            const inp = document.createElement('input');
            inp.name = lower;
            inp.value = value;
            inp.setAttribute('data-field', lower);
            if (labelText === 'SUBJECT') {
              inp.style.fontWeight = 'bold';
            }

            if ((labelText === 'CC' || labelText === 'BCC') && !value) {
              lbl.classList.add(lower + '-field');
              inp.classList.add(lower + '-field');
              lbl.style.display = 'none';
              inp.style.display = 'none';
            } else if (labelText === 'CC' || labelText === 'BCC') {
              lbl.classList.add(lower + '-field');
              inp.classList.add(lower + '-field');
            }

            headers.appendChild(inp);
          });

          if (fromSel) {
            const refInput = headers.querySelector('input');
            if (refInput) {
              fromSel.style.height = getComputedStyle(refInput).height;
            }
          }
      
          emailDiv.appendChild(headers);
      
          // body textarea
          const ta = document.createElement('textarea');
          ta.className = 'email-body';
          ta.value = body;
          emailDiv.appendChild(ta);

          // attachments area
          const updateEmailAttachments = (files) => {
            const idx = Array.from(chatMessages.children).indexOf(emailDiv);
            if (idx !== -1) {
              messageHistory[idx].attachments = files;
              localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
            }
          };
          let initialAtts = messageHistory[index]?.attachments ? [...messageHistory[index].attachments] : [];
          if (save) {
            const auto = [];
            const collectPdf = att => {
              if (!att) return;
              const name = att.name || 'attachment.pdf';
              const mime = att.mime || 'application/pdf';
              if (mime === 'application/pdf' || name.toLowerCase().endsWith('.pdf')) {
                auto.push({ name, mime, dataURL: att.dataURL, linkedMessageId: att.linkedMessageId });
              }
            };
            (attachments || []).forEach(collectPdf);
            const lastMsg = messageHistory[messageHistory.length - 1];
            if (lastMsg && Array.isArray(lastMsg.attachments)) {
              lastMsg.attachments.forEach(att => collectPdf({ ...att, linkedMessageId: lastMsg.id }));
            }
            initialAtts = initialAtts.concat(auto);
          }
          const { container: emailAttachmentUI, getFiles: getEmailAttachments } = createEmailAttachmentArea(initialAtts, updateEmailAttachments);
          emailDiv.appendChild(emailAttachmentUI);
      
          // send button
          const sendBtn = document.createElement('button');
          let alreadySent = baseLabel === 'EMAILALREADYSENT';
          sendBtn.className = 'email-send-button';
          if (alreadySent) {
            sendBtn.textContent = '✉️ SEND AGAIN';
            sendBtn.style.opacity = '0.3';
          } else { // baseLabel is EMAIL, HELP or FEEDBACK
            sendBtn.textContent = '✉️ SEND';
            sendBtn.style.opacity = '1';
          }
          sendBtn.addEventListener('click', () => {
            // If already sent, ask for confirmation first
            if (alreadySent) {
              const confirmResend = confirm("This email was already sent. Do you really want to send it again?");
              if (!confirmResend) return;
            }
            // reconstruct mailto:
            const h = headers.querySelector.bind(headers);
            const toVal   = h('input[name="to"]').value;
            const ccInput = h('input[name="cc"]');
            const bccInput= h('input[name="bcc"]');
            const subjVal = encodeURIComponent(h('input[name="subject"]').value);
            const bodyVal = encodeURIComponent(ta.value);
            const ccVal   = ccInput  ? ccInput.value  : '';
            const bccVal  = bccInput ? bccInput.value : '';
            let mailto = `mailto:${toVal}?subject=${subjVal}&body=${bodyVal}`;
            if (ccVal)  mailto += `&cc=${encodeURIComponent(ccVal)}`;
            if (bccVal) mailto += `&bcc=${encodeURIComponent(bccVal)}`;
            // window.location.href = mailto;
            const mailData = parseMailto(mailto);
            sendEmail(mailData, null, getEmailAttachments());
            // After sending: update button appearance
            sendBtn.textContent = '✉️ SEND AGAIN';
            sendBtn.style.opacity = '0.3';
            // Mark as already sent in local state
            alreadySent = true;
            // Update the label in message history
            const idx = Array.from(chatMessages.children).indexOf(emailDiv);
            if (idx !== -1) {
              messageHistory[idx].label = 'EMAILALREADYSENT';
              localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
            }
          });
          emailDiv.appendChild(sendBtn);
      
          chatMessages.appendChild(emailDiv);
          chatMessages.scrollTop = chatMessages.scrollHeight;

          // find our fields
          const headerInputs = headers.querySelectorAll('input');
          const bodyTextarea = ta;
              
          // helper to serialize back into the original “plain-text” email format
          function serializeEmail() {
            const toVal      = headers.querySelector('input[name="to"]').value;
            const ccVal      = headers.querySelector('input[name="cc"]')?.value || '';
            const bccVal     = headers.querySelector('input[name="bcc"]')?.value || '';
            const subjVal    = headers.querySelector('input[name="subject"]').value;
            const bodyVal    = bodyTextarea.value;
            let text = `To: ${toVal}\n`;
            if (ccVal)  text += `Cc: ${ccVal}\n`;
            if (bccVal) text += `Bcc: ${bccVal}\n`;
            text += `Subject: ${subjVal}\n\n`;
            text += bodyVal;
            return text;
          }
          
          bodyTextarea.addEventListener('blur', () => {
            const idx = Array.from(chatMessages.children).indexOf(emailDiv);
            if (idx === -1) return;
            messageHistory[idx].text = serializeEmail();
            localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
          });
      
          if (save) {
            // Also persist the label so we can re-render EMAIL bubbles on reload
            // only save if it’s not a repeat of the last message
            if (!last || last.text !== text || last.type !== type || last.label !== label) {
                messageHistory.push({ id: msgId, text, type, label, attachments: initialAtts, workflowId, workflowEmoji, workflowText, emailBotFlag });
                if (messageHistory.length > MAX_MESSAGES) {
                    messageHistory = messageHistory.slice(-MAX_MESSAGES);
                }
                localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
            }
          }
          toggleCleanButton();
          return;
        }





        // --- PDF HANDLER ---
        if (['PDF', 'PDFINVOICE', 'PDFPURCHASE', 'PDFOFFER', 'PDFDELIVERYRECEIPT', 'PDFREPORT', 'PDFCONTRACT', 'PDFLEGALDOCUMENT'].includes(baseLabel)) {
            const pdfDiv = document.createElement('div');
            pdfDiv.className = `chat-bubble ${bubbleType} pdf`;
            let msgId = messageHistory[index]?.id || generateUniqueId();
            pdfDiv.setAttribute('data-id', msgId);
                    
            // --- Helper: generate timestamped filename ---
            function extractTitle(mdText) {
              const match = mdText.match(/^\s*#\s*(.+)/m);
              return match ? match[1].replace(/\s+/g, '') : '';
            }

            function getTimestampFilename(title) {
              const now = new Date();
              const pad = (n) => n.toString().padStart(2, '0');
            }

            function renderPageToCanvas(page, container, scale = 1.5) {
              const dpr = window.devicePixelRatio || 1;
              const viewport = page.getViewport({ scale: scale * dpr });
              const pageCanvas = document.createElement('canvas');
              pageCanvas.style.display = 'block';
              pageCanvas.style.margin = '0 auto 0.5em';
              pageCanvas.width = viewport.width;
              pageCanvas.height = viewport.height;
              pageCanvas.style.width = (viewport.width / dpr) + 'px';
              pageCanvas.style.height = (viewport.height / dpr) + 'px';
              const ctx = pageCanvas.getContext('2d');
              page.render({ canvasContext: ctx, viewport });
              container.appendChild(pageCanvas);
            }
            
            // --- Wrapper for preview and toggle ---
            const previewWrapper = document.createElement('div');
            previewWrapper.style.position = 'relative';
            previewWrapper.style.marginBottom = '0.5em';
            previewWrapper.style.marginTop = '0.5em';
            
            // --- Preview container ---
            previewContainer.style.maxHeight = '300px';
            previewContainer.style.overflowY = 'auto';
            
            previewWrapper.appendChild(previewContainer);

            // 1) Clicking on the preview switches to edit:
            previewContainer.addEventListener('click', () => {
              if (previewContainer.style.display !== 'none') {
                toggleBtn.click();
              }
            });
            
            // 2) Clicking anywhere outside the pdfDiv when in edit‐mode switches back to preview:
            document.addEventListener('click', (e) => {
              const inEdit = editTextarea.style.display !== 'none';
              // if we’re in edit mode and the click was outside the whole bubble:
              if (inEdit && !pdfDiv.contains(e.target)) {
                toggleBtn.click();
              }
            });
            
            // --- Canvas preview using PDF.js ---
            const canvas = document.createElement('canvas');
            canvas.style.display = 'block';
            canvas.style.margin = '0 auto';
            previewContainer.appendChild(canvas);

            // create a quick PDF without logo so we can attach it immediately
            let filename = getTimestampFilename(extractTitle(text));
            let doc = new jspdf.jsPDF();
            renderStructuredContent(doc, text);
            let pdfDataUrl = doc.output('datauristring');
            attachments = [{ name: filename, mime: 'application/pdf', dataURL: pdfDataUrl }];
            let pdfBlob = dataURLtoBlob(pdfDataUrl);
            let pdfUrl = URL.createObjectURL(pdfBlob);

            pdfjsLib.getDocument(pdfUrl).promise
              .then(pdf => {
                previewContainer.innerHTML = '';
                for (let i = 1; i <= pdf.numPages; i++) {
                  pdf.getPage(i).then(page => renderPageToCanvas(page, previewContainer));
                }
              })
              .catch(err => console.error('PDF.js error:', err));

            // async fetch to include logo and update attachment + preview
            fetch('AjaxGetLogo.php')
              .then(r => r.ok ? r.text() : null)
              .catch(() => null)
              .then(logoImage => {
                if (!logoImage) return;
                const docWithLogo = new jspdf.jsPDF();
                const applyLogo = (logo) => {
                  renderStructuredContent(docWithLogo, text, logo);
                  const newDataUrl = docWithLogo.output('datauristring');
                  const newBlob = dataURLtoBlob(newDataUrl);
                  const newUrl = URL.createObjectURL(newBlob);
                  pdfjsLib.getDocument(newUrl).promise
                    .then(pdf => {
                      previewContainer.innerHTML = '';
                      for (let i = 1; i <= pdf.numPages; i++) {
                        pdf.getPage(i).then(page => renderPageToCanvas(page, previewContainer));
                      }
                    })
                    .catch(err => console.error('PDF.js error:', err));

                  // update attachment in message history
                  const idx = Array.from(chatMessages.children).indexOf(pdfDiv);
                  if (idx !== -1 && messageHistory[idx]) {
                    messageHistory[idx].attachments = [{ name: filename, mime: 'application/pdf', dataURL: newDataUrl }];
                    localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
                  }
                  // update any linked email attachments
                  updateLinkedEmailAttachments(msgId, newDataUrl);
                };

                if (logoImage.includes('svg+xml')) {
                  convertSvgToPng(logoImage, 60, 60)
                    .then(png => applyLogo(png))
                    .catch(() => applyLogo(null));
                } else {
                  applyLogo(logoImage);
                }
              });
            
            pdfDiv.appendChild(previewWrapper);
            
            // --- Editable textarea ---
            const editTextarea = document.createElement('textarea');
            editTextarea.style.display = 'none';
            editTextarea.style.width = '100%';
            editTextarea.style.height = '300px';
            editTextarea.style.padding = '10px';
            editTextarea.style.border = '1px solid #ccc';
            editTextarea.style.borderRadius = '4px';
            editTextarea.style.resize = 'vertical';
            editTextarea.style.marginBottom = '0.5em';
            editTextarea.value = text; // instead of trimmed
            pdfDiv.appendChild(editTextarea);
            
            // --- Toggle (edit/preview) button ---
            const toggleBtn = document.createElement('button');
            toggleBtn.textContent = '✏️';
            toggleBtn.title = 'edit';
            Object.assign(toggleBtn.style, {
              position: 'absolute',
              top: '8px',
              right: '8px',
              width: '28px',
              height: '28px',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: '#eee',
              border: '1px solid #ccc',
              cursor: 'pointer',
              fontSize: '16px',
              padding: '0',
              zIndex: '10'
            });
            
            toggleBtn.addEventListener('click', () => {
              const isPreview = previewContainer.style.display !== 'none';
            
              if (isPreview) {
                previewContainer.style.display = 'none';
                editTextarea.style.display = 'block';
                toggleBtn.textContent = '👁️';
                toggleBtn.title = 'preview';
              } else {
                const updatedText = editTextarea.value;
                previewContainer.style.display = 'block';
                editTextarea.style.display = 'none';
                toggleBtn.textContent = '✏️';
                toggleBtn.title = 'edit';
            
                fetch('AjaxGetLogo.php')
                  .then(response => {
                    if (!response.ok) throw new Error("Logo not found");
                    return response.text();
                  })
                  .catch(() => null) // fallback if logo not found
                  .then(logoImage => {
                    const updatedDoc = new jspdf.jsPDF();
                    renderStructuredContent(updatedDoc, updatedText, logoImage);
                    const updatedDataUrl = updatedDoc.output('datauristring');
                    const updatedBlob = dataURLtoBlob(updatedDataUrl);
                    const updatedUrl = URL.createObjectURL(updatedBlob);

                    const idx = Array.from(chatMessages.children).indexOf(pdfDiv);
                    if (idx !== -1 && messageHistory[idx]) {
                      const currentName = messageHistory[idx].attachments?.[0]?.name || filename;
                      messageHistory[idx].attachments = [{ name: currentName, mime: 'application/pdf', dataURL: updatedDataUrl }];
                      localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
                    }
                    updateLinkedEmailAttachments(msgId, updatedDataUrl);

                    pdfjsLib.getDocument(updatedUrl).promise
                    .then(pdf => {
                      previewContainer.innerHTML = '';
                      for (let i = 1; i <= pdf.numPages; i++) {
                        pdf.getPage(i).then(page => renderPageToCanvas(page, previewContainer));
                      }
                    })
                    .catch(error => console.error('PDF.js error:', error));
                  });
              }
            });
            previewWrapper.appendChild(toggleBtn);
            
            // --- Also save on blur (in edit mode) ---
            editTextarea.addEventListener('blur', () => {
              const idx = Array.from(chatMessages.children).indexOf(pdfDiv);
              if (idx !== -1) {
                messageHistory[idx].text = editTextarea.value;
                localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
              }
            });
            
            // --- Download button ---
            const downloadBtn = document.createElement('button');
            downloadBtn.textContent = '🔽 DOWNLOAD';
            downloadBtn.style.marginTop = '10px';
            downloadBtn.style.display = 'block';
            
            downloadBtn.addEventListener('click', () => {
              // const textToUse = editTextarea.style.display === 'block' ? editTextarea.value : text;
              editTextarea.blur(); // ensure any blur logic fires first
              const textToUse = editTextarea.value;

              fetch('AjaxGetLogo.php')
                .then(response => {
                  if (!response.ok) throw new Error("Logo not found");
                  return response.text();
                })
                .catch(() => null)
                .then(logoImage => {
                  const finalDoc = new jspdf.jsPDF();
                                
                  const renderAndDownload = (logoPng) => {
                    renderStructuredContent(finalDoc, textToUse, logoPng);
                    const finalBlob = finalDoc.output('blob');
                    const finalUrl = URL.createObjectURL(finalBlob);
                    
                    const link = document.createElement('a');
                    link.href = finalUrl;
                    const title = extractTitle(textToUse);
                    link.download = getTimestampFilename(title);
                    link.click();
                  };
                
                  if (logoImage && logoImage.includes('svg+xml')) {
                    convertSvgToPng(logoImage, 60, 60)
                      .then(pngData => renderAndDownload(pngData))
                      .catch(() => renderAndDownload(null)); // fallback without logo
                  } else {
                    renderAndDownload(logoImage); // either PNG already, or null
                  }
                });
            });
            
            pdfDiv.appendChild(downloadBtn);
            
            // --- Insert final PDF block into chat ---
            chatMessages.appendChild(pdfDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        
            if (save) {
              const last = messageHistory[messageHistory.length - 1];
              if (!last || last.text !== text || last.type !== type || last.label !== label) {
                messageHistory.push({ id: msgId, text, type, label, attachments, workflowId, workflowEmoji, workflowText, emailBotFlag });
                if (messageHistory.length > MAX_MESSAGES) {
                  messageHistory = messageHistory.slice(-MAX_MESSAGES);
                }
                localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
              }
            }
        
            toggleCleanButton();
            return; // Prevent falling through and adding plain text rendering
        }

        if (isDbContext) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-bubble ${bubbleType}`;

            let msgId = messageHistory[index]?.id || generateUniqueId();
            if (messageHistory[index]?.id) {
                messageDiv.setAttribute('data-id', messageHistory[index].id);
            } else {
                messageDiv.setAttribute('data-id', msgId);
            }

            const toggleBtn = document.createElement('div');
            toggleBtn.className = 'db-context-toggle';
            toggleBtn.textContent = '🔷 CONTEXT';
            const tooltipHtml = marked.parse(text);
            toggleBtn.dataset.tooltipHtml = encodeURIComponent(tooltipHtml);

            const contextDiv = document.createElement('div');
            contextDiv.className = 'db-context';
            contextDiv.innerHTML = marked.parse(text);
            contextDiv.style.display = 'none';

            toggleBtn.addEventListener('click', () => {
                contextDiv.style.display =
                    contextDiv.style.display === 'none' ? 'block' : 'none';
            });

            messageDiv.appendChild(toggleBtn);
            messageDiv.appendChild(contextDiv);

            chatMessages.appendChild(messageDiv);
            addTooltipPreviews(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            if (save) {
                const last = messageHistory[messageHistory.length - 1];
                if (!last || last.text !== text || last.type !== type || last.label !== label) {
                    messageHistory.push({ id: msgId, text, type, label, attachments, workflowId, workflowEmoji, workflowText, emailBotFlag });
                    if (messageHistory.length > MAX_MESSAGES) {
                        messageHistory = messageHistory.slice(-MAX_MESSAGES);
                    }
                    localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
                }
            }

            toggleCleanButton();
            return;
        }

        // /////////////////////////////////////////////////////////////////////////////////////////////////// render the message as a chat bubble AFTER the special cases
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-bubble ${bubbleType}`;

        // Ensure message has an ID (use existing one if restoring)
        let msgId = messageHistory[index]?.id || generateUniqueId();
        if (messageHistory[index]?.id) {
          messageDiv.setAttribute('data-id', messageHistory[index].id);
        }

        if (hasAttachments) {
          attList.forEach(att => {
            const attachDiv = document.createElement('div');
            attachDiv.className = 'chat-attachment';

            const link = document.createElement('a');
            link.href = att.dataURL;
            link.target = '_blank';

            if (att.mime && att.mime.startsWith('image/')) {
              const img = document.createElement('img');
              img.src = att.dataURL;
              img.alt = att.name;
              link.appendChild(img);
            } else {
              link.textContent = att.name;
            }

            attachDiv.appendChild(link);
            attachmentsWrap.appendChild(attachDiv);
          });
        }

        if (workflowId && workflowEmoji) {
          const wfDiv = document.createElement('div');
          wfDiv.className = 'chat-attachment workflow-attachment';
          wfDiv.title = workflowText;
          const emojiSpan = document.createElement('span');
          emojiSpan.textContent = workflowEmoji;
          emojiSpan.style.fontSize = '1.5rem';
          wfDiv.appendChild(emojiSpan);

          if (attachmentsWrap) {
            attachmentsWrap.appendChild(wfDiv);
          } else {
            messageDiv.appendChild(wfDiv);
          }
        }

        if (attachmentsWrap) {
          messageDiv.appendChild(attachmentsWrap);
        }

        // the following function is disabled, to enable, also enable the corresponding DirectEdit part in ./BRAIN/main.php too
        // applyDirectEdit(messageDiv, contentDiv);

        // Make table cells editable and headers sortable
        messageDiv.querySelectorAll('table.sortable-markdown-table').forEach(table => {
          makeTableSortable(table);
          const headers = table.querySelectorAll('thead th');
          headers.forEach(th => {
            th.addEventListener('blur', () => {
              saveTable(table);
            });
          });

          table.addEventListener('focusin', e => {
            const cell = e.target.closest('td,th');
            if (cell) highlightSelectionTable(cell);
          });
          table.addEventListener('focusout', () => {
            if (!table.contains(document.activeElement)) clearHighlightsTable();
          });

          function highlightSelectionTable(cell) {
            clearHighlightsTable();
            if (!cell) return;
            const row = cell.parentElement;
            if (row && row.cells[0]) row.cells[0].classList.add('selected');
            const index = cell.cellIndex + 1;
            const th = table.querySelector('thead th:nth-child(' + index + ')');
            if (th) th.classList.add('selected');
          }

          function clearHighlightsTable() {
            table.querySelectorAll('th.selected, td.selected').forEach(el => el.classList.remove('selected'));
          }
      
          // 2) Make cells editable & save on blur
          table.querySelectorAll('td').forEach(td => {
            // If the cell contains a link (<a>), disable editing and let the link be clickable
            if (td.querySelector('a')) {
              td.contentEditable = false;          // Disable editing in this cell
              td.style.cursor = 'pointer';         // Pointer cursor for links
            } else {
              td.contentEditable = true;
              td.style.cursor = 'text';
              const val = td.textContent.trim();
              td.classList.toggle('negative', !isNaN(val) && parseFloat(val) < 0);
              td.addEventListener('blur', () => {
                const newVal = td.textContent.trim();
                td.classList.toggle('negative', !isNaN(newVal) && parseFloat(newVal) < 0);

                const table = td.closest('table');
                saveTable(table);
              });
            }
          });
        });

        // Add code tools to code blocks
        messageDiv.querySelectorAll('pre').forEach(pre => {
            if (!pre.querySelector('.code-tools')) {
                const codeTools = document.createElement('div');
                codeTools.className = 'code-tools';
                
                const copyBtn = document.createElement('button');
                copyBtn.innerHTML = '👀';
                copyBtn.title = 'copy';
                copyBtn.onclick = function() {
                    // Get only the code content, excluding the tools div and any other elements
                    const codeContent = pre.textContent
                        .replace('👀', '')  // Remove the eye emoji
                        .replace('copy', '')  // Remove any "copy" text
                        .trim();
                    navigator.clipboard.writeText(codeContent);
                    this.setAttribute('data-copied', 'true');
                    setTimeout(() => this.removeAttribute('data-copied'), 300);
                };
                
                codeTools.appendChild(copyBtn);
                pre.appendChild(codeTools);
                
                // Make the code editable
                pre.contentEditable = true;
                pre.addEventListener('blur', () => {
                    const messageIndex = Array.from(chatMessages.children).indexOf(messageDiv);
                    if (messageIndex !== -1) {
                        // If label is CODE, save only the code content
        if (baseLabel === 'CODE') {
                            const code = pre.textContent.replace('👀', '').replace('Copy', '').trim();
                            // Replace the first ```...``` block, or append if not found
                            const codeBlockRegex = /```[\s\S]*?```/;
                            if (codeBlockRegex.test(text)) {
                                messageHistory[messageIndex].text = text.replace(codeBlockRegex, '```\n' + code + '\n```');
                            } else {
                                // If no code block found, just append it
                                messageHistory[messageIndex].text = text + '\n```\n' + code + '\n```';
                            }
                        } else {
                            // For other messages, try to replace the <pre> block
                            messageHistory[messageIndex].text = text.replace(
                                /<pre>[\s\S]*?<\/pre>/,
                                `<pre>${pre.textContent.replace('👀', '').replace('Copy', '').trim()}</pre>`
                            );
                        }
                        localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
                    }
                });
            }
        });

        // Add copy functionality to math results
        messageDiv.querySelectorAll('.math-value').forEach(span => {
            span.style.cursor = 'pointer';
            span.title = 'click to copy';
            span.onclick = function() {
                navigator.clipboard.writeText(this.textContent);
                const originalColor = this.style.color;
                this.style.color = 'var(--primary-color)';
                setTimeout(() => this.style.color = originalColor, 300);
            };
        });

        // Only add message-tools if it's not a math or code block
        const isMath = text.trim().startsWith('= ');
        const isCode = text.trim().startsWith('<pre>') || text.includes('<pre>') || /```/.test(text);
            
        if (!isMath && !isCode && !isDbContext) {
            const tools = document.createElement('div');
            tools.className = 'message-tools';

            if (!baseLabel.startsWith('CHATDBSEARCH')) {
              const copyBtn = document.createElement('button');
              copyBtn.textContent = '👀';
              copyBtn.title = 'copy';

              copyBtn.onclick = () => {
                  if (baseLabel.startsWith('LOCATION')) {
                      // Extract and copy only the address parameter from the iframe
                      const iframe = messageDiv.querySelector('iframe[src^="./map.php"]');
                      if (iframe) {
                          const src = iframe.getAttribute('src');
                          const match = src.match(/[?&]address=([^&]+)/);
                          const address = match
                              ? decodeURIComponent(match[1]).replace(/\+/g, ', ')
                              : '';
                          navigator.clipboard.writeText(address);
                      }
                  } else {
                      // Copy cleaned content for other messages
                      const copyText = getMessageCopyText(messageDiv);
                      navigator.clipboard.writeText(copyText);
                  }
              };
              
              tools.appendChild(copyBtn);
            }

            if (baseLabel.startsWith('TABLE')) {

                const csvBtn = document.createElement('button');
                csvBtn.textContent = '🔽';
                csvBtn.title = 'download CSV';

                csvBtn.onclick = () => {
                    const table = messageDiv.querySelector('table');
                    if (!table) return;
                
                    let csv = '';
                    const rows = table.querySelectorAll('tr');
                    rows.forEach(row => {
                        const cols = row.querySelectorAll('th, td');
                        const line = Array.from(cols).map(td => {
                            const cleanText = td.textContent.replace(/["⇅↑↓]/g, '').trim(); // Remove quotes and arrows
                            return `"${cleanText.replace(/"/g, '""')}"`;
                        }).join(',');
                        csv += line + '\n';
                    });
                  
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                  
                    const a = document.createElement('a');
                    a.href = url;
                    const now = new Date();
                    const pad = n => n.toString().padStart(2, '0');
                    const base = typeof companyNameCompressed !== 'undefined' && companyNameCompressed ? companyNameCompressed : 'TRAMANNPROJECTS';
                    const filename = `${base}_${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}_${pad(now.getMinutes())}-${pad(now.getSeconds())}.csv`;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                };
              
                tools.appendChild(csvBtn);
            }

            // if (normalizedLabel.startsWith('CHATDB')) {
            //   const logsBtn = document.createElement('button');
            //   logsBtn.textContent = '📜';
            //   logsBtn.title = 'open log files';
            //   logsBtn.addEventListener('click', () => {
            //     window.location.href = './logs.php';
            //   });
            //   tools.appendChild(logsBtn);
            // }

            // baseLabel.startsWith('DATABASES') already covers all actions such as SELECT, INSERT INTO, UPDATE, ..., as we wrote ".startsWith()".
            if (baseLabel.startsWith('CHATDB') || baseLabel.startsWith('DATABASES') || baseLabel.startsWith('BUYING') || baseLabel.startsWith('SELLING')) {
              const logsBtn = document.createElement('button');
              logsBtn.textContent = '📜';
              logsBtn.title = 'open log files';
              logsBtn.addEventListener('click', () => {
                window.location.href = './logs.php';
              });
              tools.appendChild(logsBtn);
            
              // Enhance any inline tables inside CHATDB messages
              messageDiv.querySelectorAll('table').forEach(table => {
                table.classList.add('sortable-markdown-table'); // Make sure sorting logic finds it

                // Fix for malformed inline tables without <thead>
                if (!table.querySelector('thead')) {
                  const firstRow = table.querySelector('tr');
                  if (firstRow) {
                    const thead = document.createElement('thead');
                    const headerRow = firstRow.cloneNode(true);
                    thead.appendChild(headerRow);
                    table.insertBefore(thead, table.firstChild);
                    firstRow.remove();
                  
                    // Now make sure we have a <tbody> for sorting rows
                    if (!table.querySelector('tbody')) {
                      const tbody = document.createElement('tbody');
                      const remainingRows = table.querySelectorAll('tr');
                      remainingRows.forEach(row => tbody.appendChild(row));
                      table.appendChild(tbody);
                    }
                  }
                }
              
                makeTableSortable(table);
                const headers = table.querySelectorAll('thead th');
                headers.forEach(th => {
                  th.addEventListener('blur', () => {
                    saveTable(table);
                  });
                });
              
               table.querySelectorAll('td').forEach(td => {
                // If the cell contains a link (<a>), disable editing and let the link be clickable
                if (td.querySelector('a')) {
                  td.contentEditable = false;          // Disable editing in this cell
                  td.style.cursor = 'pointer';         // Pointer cursor for links
                } else {
                  td.contentEditable = true;
                  td.style.cursor = 'text';
                  const val = td.textContent.trim();
                  td.classList.toggle('negative', !isNaN(val) && parseFloat(val) < 0);

                  td.addEventListener('blur', () => {
                    const newVal = td.textContent.trim();
                    td.classList.toggle('negative', !isNaN(newVal) && parseFloat(newVal) < 0);
                    saveTable(table);
                  });
                }
              });

            if (baseLabel.startsWith('LOCATION')) {
              const locationsBtn = document.createElement('button');
              locationsBtn.textContent = '🗺️';
              locationsBtn.title = 'open map';
              locationsBtn.addEventListener('click', () => {
                // Find the map iframe and get its src
                const iframe = document.querySelector('iframe[src^="./map.php"]');
                if (iframe) {
                  // Remove the `&pv` parameter from the URL
                  let url = iframe.getAttribute('src');
                  url = url.replace(/&pv\b/, '');
                  window.location.href = url;
                } else {
                  // Fallback to the generic map link
                  window.location.href = './map.php';
                }
              });
              tools.appendChild(locationsBtn);
            }
        
            if (type === 'user') {
                const editBtn = document.createElement('button');
                editBtn.textContent = '✏️';
                editBtn.title = 'edit';
                editBtn.onclick = () => editMessage(index);
            
                const rerunBtn = document.createElement('button');
                rerunBtn.textContent = '🔁';
                rerunBtn.title = 'rerun';
                rerunBtn.onclick = () => rerunMessage(index);
            
                tools.appendChild(editBtn);
                tools.appendChild(rerunBtn);
            }
        
            messageDiv.appendChild(tools);
        }

        chatMessages.appendChild(messageDiv);

        addTooltipPreviews(messageDiv);
        updateSuggestionOpacity();

        chatMessages.scrollTop = chatMessages.scrollHeight;

        if (save) {
            // Also persist the label so we can re-render EMAIL bubbles on reload
            // only save if it’s not a repeat of the last message
            const last = messageHistory[messageHistory.length - 1];
            if (!last || last.text !== text || last.type !== type || last.label !== label) {
                const msgId = generateUniqueId();
                messageHistory.push({ id: msgId, text, type, label, attachments, workflowId, workflowEmoji, workflowText, emailBotFlag });
                if (messageHistory.length > MAX_MESSAGES) {
                    messageHistory = messageHistory.slice(-MAX_MESSAGES);
                }
                localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
            }
        }

        toggleCleanButton();
    }










    function addLoadingAnimation() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chat-bubble bot loading';
        loadingDiv.innerHTML = `
            <div class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        chatMessages.appendChild(loadingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return loadingDiv;
    }

    function dataURLtoBlob(dataURL) {
        const [header, data] = dataURL.split(',');
        const array = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            array[i] = binary.charCodeAt(i);
        }
        return new Blob([array], { type: mime });
    }

    async function sendToAI(message, workflowId = selectedWorkflowId, attachments = [], emailBotFlag = false) {
        const loadingAnimation = addLoadingAnimation();

                        // ///////////////////////////////////////////////////////////////////////////////////////////////// // for debugging  
                        // // comment out the rest and comment this in for debugging
                        // // simulate an async AI call and always return "placeholder"
                        // const mockResults = [
                        //   {
                        //     label: 'TABLE',
                        //     message: 
                        //       '| Header1 | Header2 | Header3 | Header4 |\n' +
                        //       '|---------|---------|---------|---------|\n' +
                        //       '| 12      | Apple   | 3.14    | True    |\n' +
                        //       '| 45      | Banana  | 2.71    | False   |\n' +
                        //       '| 78      | Cherry  | 1.61    | True    |\n' +
                        //       '| 34      | Date    | 0.57    | False   |'
                        //   }
                        // ];
                        // return new Promise(resolve => {
                        //   setTimeout(() => {
                        //     loadingAnimation.remove();
                        //     mockResults.forEach(({ label, message }) => {
                        //       addMessageToChat(message, 'bot', label);
                        //     });
                        //     resolve(mockResults);
                        //   }, 100); // adjust delay as needed
                        // });
        
        // 1) Get the last 30 messages for context
        const recentMessages = messageHistory.slice(-30).map(msg => msg.text);

        // 2) Build a FormData instead of raw JSON
        const form = new FormData();
        form.append('cmd', message);
        form.append('logs', JSON.stringify(recentMessages));
        if (workflowId) {
            form.append('workflow_id', workflowId);
        }
        if (emailBotFlag) {
            form.append('EmailBotHandlingFlag', '1');
        }

        // 3) Append attachments (convert PDFs to images first)
        if (attachments && attachments.length > 0) {
            for (let i = 0; i < attachments.length; i++) {
                const att = attachments[i];
                if (att.mime === 'application/pdf') {
                    const arrayBuffer = await fetch(att.dataURL).then(res => res.arrayBuffer());
                    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                    const numPages = pdf.numPages;

                    for (let pageNum = 1; pageNum <= numPages; pageNum++) {
                        const page = await pdf.getPage(pageNum);
                        const scale = 2 * (window.devicePixelRatio || 1);
                        const viewport = page.getViewport({ scale });
                        const canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        const context = canvas.getContext('2d');
                        await page.render({ canvasContext: context, viewport: viewport }).promise;

                        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                        if (blob) {
                            form.append('attachments[]', blob, `${att.name}_page${pageNum}.png`);
                        }
                    }
                } else {
                    let blob;
                    if (att.dataURL.startsWith('data:')) {
                        blob = dataURLtoBlob(att.dataURL);
                    } else {
                        blob = await fetch(att.dataURL).then(res => res.blob());
                    }
                    form.append('attachments[]', blob, att.name);
                }
            }
        }

        // 4) Do the fetch WITHOUT manually setting Content-Type
        // return fetch('../BRAIN/main.php', {
        const endpoint = window.location.search.includes('atlas') ? '../BRAIN/atlas.php' : '../BRAIN/main.php';
        return fetch(endpoint, {
            method: 'POST',
            body: form
        })
        .then(response => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`);

                        // ///////////////////////////////////////////////////////////////////////////////////////////////// // for debugging
                        // Clone before you consume it
                        const responseClone = response.clone();
                        // Log the raw text
                        responseClone.text().then(raw => {
                          try {
                            const parsed = JSON.parse(raw);
                            const filtered = Array.isArray(parsed)
                              ? parsed.filter(item => (item.label || '').trim().toUpperCase() !== 'CONSOLE_LOG_ONLY')
                              : parsed;
                            console.log('⟪TRAMANN BRAIN RAW RESPONSE⟫', JSON.stringify(filtered));
                          } catch (e) {
                            console.log('⟪TRAMANN BRAIN RAW RESPONSE⟫', raw);
                          }
                        });
            
          return response.json();
        })
        .then(results => {
            loadingAnimation.remove();

            // Optional: store for global access
            let receivedChildWorkflowIds = [];
            let messagesForWorkflowAgent = [];

            // results is an array of { label, message }
            results.forEach(({ label, message, childWorkflowIds, workflowText }) => {
              // Extract child workflows if present and non-empty
              if (Array.isArray(childWorkflowIds) && childWorkflowIds.length > 0) {
                  receivedChildWorkflowIds = childWorkflowIds;
              }

                const normalizedLabel = label ? label.trim().toUpperCase() : '';
                const baseLabel = normalizedLabel.split('[')[0];
                const isPDF = ['PDF', 'PDFINVOICE', 'PDFPURCHASE', 'PDFOFFER', 'PDFDELIVERYRECEIPT', 'PDFREPORT', 'PDFCONTRACT', 'PDFLEGALDOCUMENT'].includes(baseLabel);
                const text = (typeof message === 'object' && !isPDF)
                              ? JSON.stringify(message, null, 2)
                              : message;

                // ///////////////////////////////////////////////////////////////////////////////////////////////// // for debugging
                // Don't add console-only debug logs to the chat
                if (baseLabel === 'CONSOLE_LOG_ONLY') {
                    console.log(text);
                    return;
                }

                // Collect for WorkflowAgent
                messagesForWorkflowAgent.push({ label: baseLabel, message: text });

                // Handle WORKFLOWIDPK label
                if (baseLabel === 'WORKFLOWIDPK') {
                    // Update workflow text mapping if provided
                    if (workflowText) {
                        workflowMap[workflowId] = workflowMap[workflowId] || {};
                        workflowMap[workflowId].whatToDo = workflowText;
                    }
                    // Use the childWorkflowIds from this message
                    updateChildWorkflowUI(childWorkflowIds ?? [], messagesForWorkflowAgent, workflowId);
                    return; // Don't add this as a chat message
                }

                // Show normal messages in chat
                addMessageToChat(text, 'bot', label);
            });
        })
        .catch(err => {
          loadingAnimation.remove();
          console.error('Error from BRAIN:', err);
          addMessageToChat('We are very sorry, but the system encountered an error. Please reload the page and try again.', 'bot');
        });
    }

    function sendMessage() {
      let message = messageInput.value.trim();
      if (emailBotFlag && emailBotInfoText) {
        message = emailBotInfoText + (message ? ` | ADDITIONAL USER COMMAND: ${message}` : '');
      }

      // ── 1) Build an array of “attachment” objects from attachmentsPreview ──────────────────
      const attachmentData = [];
      const currentPreviews = attachmentsPreview.querySelectorAll('.attachment-preview');
      currentPreviews.forEach(preview => {
        if (preview.classList.contains('workflow-attachment')) return; // skip workflow preview
        attachmentData.push({
          dataURL: preview.dataset.dataurl,
        });
      });
      // ─────────────────────────────────────────────────────────────────────────────────────

      if (!message && selectedWorkflowId) {
        // Send a placeholder if only workflow is attached
        message = 'RUN WORKFLOW';
      }
      if (message || selectedWorkflowId || attachmentData.length > 0) {
        // Preserve workflow ID and emoji for backend processing
        const workflowId = selectedWorkflowId;
        const workflowDiv = attachmentsPreview.querySelector('.workflow-attachment');
        const workflowPreview = workflowDiv ? workflowDiv.querySelector('span') : null;
        const workflowEmoji = workflowPreview ? workflowPreview.textContent : '';
        const workflowText = workflowDiv ? workflowDiv.title : '';

        welcomeMessage.style.display = 'none';
        chatMessages.classList.add('active');
    
        // ── 2) Send text + attachments into the chat and persist them ───────────────────────────
        const emailFlag = emailBotFlag;
        const emailInfo = emailBotInfoText;

        addMessageToChat(
          message,
          'user',
          null,
          undefined,
          true,
          attachmentData,
          emailInfo
        );
        // ─────────────────────────────────────────────────────────────────────────────────────

        // ── 3) Clear out the “file input” preview box as before ─────────────────────────────────
        clearAttachmentPreviews();
        // ─────────────────────────────────────────────────────────────────────────────────────

        // ── 4) Reset the rest of the text‐input UI ──────────────────────────────────────────────
        messageInput.value = '';
        messageInput.style.height = 'auto';
        messageInput.removeAttribute('placeholder');
        if (isVoiceSupported) {
          voiceButton.style.display = 'flex';
        }
        // ─────────────────────────────────────────────────────────────────────────────────────
    
        // ── 5) Clear workflow selection and update the send button visibility ───────────────────
        selectedWorkflowId = null;
        selectedWorkflowText = null;
        emailBotFlag = false;
        emailBotInfoText = '';
        updateSendButtonVisibility();

        // ── 6) Actually send the text (and attachments) off to your backend ───
        sendToAI(message, workflowId, attachmentData, emailFlag);
        // ─────────────────────────────────────────────────────────────────────────────────────
      }
    
      toggleCleanButton();
    }

    function sendSuggestion(text) {
      const message = text.trim();
      if (!message) return;
      welcomeMessage.style.display = 'none';
      chatMessages.classList.add('active');
      addMessageToChat(message, 'user', null, undefined, true, []);
      sendToAI(message);
    }

    function editMessage(index) {
        const entry = messageHistory[index];
        const msgToEdit = entry?.text || '';

        // 1) Truncate history to “before this message”
        messageHistory = messageHistory.slice(0, index);
        localStorage.setItem('chatMessages', JSON.stringify(messageHistory));

        // 2) Re‐render the chat without the edited message
        chatMessages.innerHTML = '';
        messageHistory.forEach((msg, i) =>
          addMessageToChat(msg.text, msg.type, msg.label, i, false, msg.attachments, msg.workflowId, msg.workflowEmoji, msg.workflowText, msg.emailBotFlag)
        );
        toggleCleanButton();

        // 3) Put the text into the input field
        messageInput.value = msgToEdit;
        messageInput.focus();
        messageInput.dispatchEvent(new Event('input')); // to resize and show send button

        // 4) REBUILD the attachments preview area from entry data
        clearAttachmentPreviews();
        if (entry.emailBotFlag) {
          createEmojiPreview('🤖', 'EMAIL BOT will handle this task', () => { emailBotFlag = false; });
          emailBotFlag = true;
        }
        if (entry.workflowId) {
          const preview = document.createElement('div');
          preview.className = 'attachment-preview workflow-attachment';
          preview.title = entry.workflowText || '';
          const emojiSpan = document.createElement('span');
          emojiSpan.textContent = entry.workflowEmoji;
          emojiSpan.style.fontSize = '1.5rem';
          const removeBtn = document.createElement('button');
          removeBtn.className = 'remove-attachment';
          removeBtn.innerHTML = '×';
          removeBtn.title = 'remove';
          removeBtn.onclick = () => {
            preview.remove();
            selectedWorkflowId = null;
            selectedWorkflowText = null;
            updateSendButtonVisibility();
          };
          preview.appendChild(emojiSpan);
          preview.appendChild(removeBtn);
          attachmentsPreview.appendChild(preview);
          selectedWorkflowId = entry.workflowId;
          selectedWorkflowText = entry.workflowText;
        }
        if (entry?.attachments) {
          entry.attachments.forEach(att => addPreviewFromAttachment(att));
        }
        updateSendButtonVisibility();
    }


    function rerunMessage(index) {
        // 1) Grab the original entry (text + attachments)
        const entry = messageHistory[index];
        const rerunText = entry.text;
        const oldAttachments = entry.attachments || [];

        // 2) Truncate history and re-render chat up to (but not including) this message
        messageHistory = messageHistory.slice(0, index);
        localStorage.setItem('chatMessages', JSON.stringify(messageHistory));

        chatMessages.innerHTML = '';
        messageHistory.forEach((msg, i) => {
          // Make sure old attachments still show in past bubbles
          addMessageToChat(msg.text, msg.type, msg.label, i, false, msg.attachments, msg.workflowId, msg.workflowEmoji, msg.workflowText, msg.emailBotFlag);
        });
        toggleCleanButton();

        // 3) Rebuild the attachments preview DIVs (but we’ll clear them in a sec)
        clearAttachmentPreviews();
        if (entry.workflowId) {
          const preview = document.createElement('div');
          preview.className = 'attachment-preview workflow-attachment';
          preview.title = entry.workflowText || '';
          const emojiSpan = document.createElement('span');
          emojiSpan.textContent = entry.workflowEmoji;
          emojiSpan.style.fontSize = '1.5rem';
          const removeBtn = document.createElement('button');
          removeBtn.className = 'remove-attachment';
          removeBtn.innerHTML = '×';
          removeBtn.title = 'remove';
          removeBtn.onclick = () => { preview.remove(); selectedWorkflowId = null; selectedWorkflowText = null; updateSendButtonVisibility(); };
          preview.appendChild(emojiSpan);
          preview.appendChild(removeBtn);
          attachmentsPreview.appendChild(preview);
          selectedWorkflowId = entry.workflowId;
          selectedWorkflowText = entry.workflowText;
        }
        oldAttachments.forEach(att => {
          addPreviewFromAttachment(att);
        });
        if (entry.emailBotFlag) {
          emailBotFlag = entry.emailBotFlag;
        }
        updateSendButtonVisibility();

        // 4) Add the “rerun” user bubble (with old attachments),
        //    then immediately call sendToAI so it sees those preview nodes.
        setTimeout(() => {
          // a) Show the new user‐bubble in the chat (so it visually looks like “You just sent this again”)
          addMessageToChat(rerunText, 'user', null, undefined, true, oldAttachments, entry.workflowId, entry.workflowEmoji, entry.workflowText, entry.emailBotFlag);

          // b) Call sendToAI with the same attachments
          sendToAI(rerunText, entry.workflowId, oldAttachments, entry.emailBotFlag);

          // c) Immediately clear the preview area so the user never actually sees it:
          clearAttachmentPreviews();
          emailBotFlag = false;
          emailBotInfoText = '';
        }, 200);
    }



    addTooltipPreviews();
});






function makeTableSortable(table) {
    const headers = table.querySelectorAll('thead th');
    headers.forEach((th, colIndex) => {
        th.contentEditable = true;
        th.style.cursor = 'text';

        let arrow = th.querySelector('.sort-arrow');
        if (!arrow) {
            arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.textContent = ' ⇅';
            arrow.style.cursor = 'pointer';
            arrow.style.fontSize = '0.8rem';
            arrow.contentEditable = false;
            arrow.style.userSelect = 'none';
            arrow.addEventListener('mousedown', e => e.stopPropagation());
            th.appendChild(arrow);
        }

        th.addEventListener('blur', () => addSortArrowIfMissing(th));

        let sortState = 0;
        arrow.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            sortState = (sortState + 1) % 3;
            headers.forEach(h => {
                const a = h.querySelector('.sort-arrow');
                if (a && h !== th) a.textContent = ' ⇅';
            });
            arrow.textContent = sortState === 1 ? ' ↑' : sortState === 2 ? ' ↓' : ' ⇅';

            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const getVal = r => r.children[colIndex].textContent.trim();
            if (sortState === 1) {
                rows.sort((a,b) => getVal(a).localeCompare(getVal(b), undefined, {numeric: true}));
            } else if (sortState === 2) {
                rows.sort((a,b) => getVal(b).localeCompare(getVal(a), undefined, {numeric: true}));
            }
            const tbody = table.querySelector('tbody');
            tbody.innerHTML = '';
            rows.forEach(r => tbody.appendChild(r));
        });
    });
}

function cleanHeaderCell(th) {
    const arrow = th.querySelector('.sort-arrow');
    if (arrow) arrow.remove();
    const text = th.textContent.replace(/[⇅↑↓]/g, '').trim();
    th.innerHTML = '';
    th.textContent = text;
    if (arrow) th.appendChild(arrow);
}

function addSortArrowIfMissing(th) {
    cleanHeaderCell(th);
    if (!th.querySelector('.sort-arrow')) {
        const table = th.closest('table');
        if (table) makeTableSortable(table);
    }
}

function tableToMarkdown(table) {
    const headers = Array.from(table.querySelectorAll('thead th')).map(h =>
        h.textContent.replace(/[⇅↑↓]/g, '').trim()
    );
    const headerLine  = `| ${headers.join(' | ')} |`;
    const dividerLine = `| ${headers.map(() => '---').join(' | ')} |`;
    const rows = Array.from(table.querySelectorAll('tbody tr')).map(tr =>
        '| ' + Array.from(tr.children).map(td => td.textContent.trim()).join(' | ') + ' |'
    );
    return [headerLine, dividerLine, ...rows].join('\n');
}

function getMessageCopyText(messageDiv) {
    const clone = messageDiv.cloneNode(true);

    // Remove UI-only helper elements
    clone.querySelectorAll('.message-tools, .code-tools').forEach(el => el.remove());

    // Convert tables to Markdown so only plain text is copied
    clone.querySelectorAll('table').forEach(table => {
        const markdown = tableToMarkdown(table);
        table.replaceWith(document.createTextNode(markdown));
    });

    // Replace links with their content text only
    clone.querySelectorAll('a').forEach(a => {
        // Replace links with their absolute URL
        // let href = a.getAttribute('href') || '';
        // if (href.startsWith('./')) {
        //     href = 'https://www.tnxapi.com/UI/' + href.substring(2);
        // }
        // a.replaceWith(document.createTextNode(href));
        a.replaceWith(document.createTextNode(a.textContent));
    });

    // Append to DOM to accurately compute styles of elements
    document.body.appendChild(clone);
    clone.querySelectorAll('*').forEach(el => {
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            el.remove();
        }
    });

    // Remove spans (strip tags but keep their text content)
    clone.querySelectorAll('span').forEach(span => {
        span.replaceWith(document.createTextNode(span.textContent));
    });

    const text = clone.innerText.trim();
    document.body.removeChild(clone);

    return text;
}

function saveTable(table) {
    const bubble = table.closest('.chat-bubble');
    const msgId = bubble?.getAttribute('data-id');
    if (!msgId) return;
    const msgIndex = messageHistory.findIndex(m => String(m.id) === String(msgId));
    if (msgIndex === -1) return;

    const text = messageHistory[msgIndex].text;

    if (text.includes('<table')) {
        const regex = /<table[\s\S]*?<\/table>/i;
        messageHistory[msgIndex].text = text.replace(regex, table.outerHTML);
    } else {
        const newMarkdown = tableToMarkdown(table);

        const lines = text.split('\n');
        let start = lines.findIndex(l => /^\|/.test(l));
        if (start === -1) {
            lines.push(newMarkdown);
        } else {
            let end = start;
            while (end < lines.length && /^\|/.test(lines[end])) end++;
            lines.splice(start, end - start, ...newMarkdown.split('\n'));
        }

        messageHistory[msgIndex].text = lines.join('\n');
    }

    localStorage.setItem('chatMessages', JSON.stringify(messageHistory));
}

function modifyTable(action) {
    if (!currentCell) return;
    const table = currentCell.closest('table');
    const rowIndex = currentCell.parentElement.rowIndex;
    const colIndex = currentCell.cellIndex;

    const isHeader = currentCell.tagName === 'TH';

    if (action === 'add-row') {
        const newRow = table.insertRow(rowIndex + 1);
        const colCount = table.rows[0].cells.length;

        for (let i = 0; i < colCount; i++) {
            const newCell = newRow.insertCell(i);
            newCell.innerText = '';
            newCell.contentEditable = true;
            newCell.style.minHeight = '1em'; // 1 line height
            newCell.style.padding = '4px';   // consistent padding
        }
    } else if (action === 'add-col') {
        for (let i = 0; i < table.rows.length; i++) {
            const row = table.rows[i];
            const isHeaderRow = i === 0;

            if (isHeaderRow) {
                const newHeader = document.createElement('th');
                newHeader.contentEditable = true;
                newHeader.innerText = '';
                row.insertBefore(newHeader, row.cells[colIndex + 1]);
            } else {
                const newCell = row.insertCell(colIndex + 1);
                newCell.innerText = '';
                newCell.contentEditable = true;
                newCell.style.minHeight = '1em';
                newCell.style.padding = '4px';
            }
        }
    } else if (action === 'delete-row') {
        if (table.rows.length > 2) {
            table.deleteRow(rowIndex);
        }
    } else if (action === 'delete-col') {
        const colCount = table.rows[0].cells.length;
        if (colCount > 1) {
            for (let row of table.rows) {
                row.deleteCell(colIndex);
            }
        }
    }

    makeTableSortable(table);
    // Re-save the table to localStorage
    saveTable(table);
}
</script>
<script>
    window.onload = () => {
        window.scrollTo(0, document.documentElement.scrollHeight);
        // or with smooth animation:
        // window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    };
    window.addEventListener('DOMContentLoaded', function () {
        const textarea = document.getElementById('message-input');
        if (textarea) {
            // Move the cursor to the end of the prefilled text
            textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
            textarea.focus();
        }
    });
</script>

<?php // require_once('footer.php'); ?>
</main>
<!-- <footer>
</footer> -->
</body>
</html>
