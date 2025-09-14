<?php
require_once('../config.php');
require_once('header.php');

$message = '';
$error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (
        $email &&
        filter_var($email, FILTER_VALIDATE_EMAIL) &&
        preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)
    ) {
        mail(
            'hi@tnxapi.com',
            'TRAMANN TNX API - new person on the onboarding list',
            'A new person joined the onboarding list: ' . $email
        );
        $message = 'Thanks, your email has been sent, your onboarding will start within the next 48 hours.';
        $_POST = [];
    } else {
        $error = 'Please enter a valid email address.';
    }
}
?>
<style>
main {
    justify-content: flex-start;
}
/* Container styling to keep content centered and narrow on large screens */
.landing-container {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
    padding: 2rem 1rem;
}

/* Tagline beneath the heading */
.tagline {
    display: block;
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 1rem;
}
.taglineSub {
    display: block;
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 1rem;
    opacity: 0.3;
}

/* Onboarding form: email input and action link */
.onboard-form {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 1rem 0 2rem;
}
.onboard-form input[type=email] {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    min-width: 220px;
}
.onboard-form a.button-link {
    padding: 0.5rem 1rem;
    background: var(--link-color);
    color: white;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
    opacity: 0.3;
    transition: opacity 0.2s;
}
.onboard-form a.button-link:hover {
    opacity: 0.8;
}
.success-message {
    color: var(--link-color);
    font-weight: bold;
}
.error-message {
    color: red;
    font-weight: bold;
}

/* Accent color helper */
.accent {
    color: var(--accent-color);
}

/* Security section list: center container, left-align content */
.security-list {
    display: inline-block;
    text-align: left;
}

/* Centered link helper */
.code-link {
    text-align: center;
    width: 100%;
}

/* Wrapper for the diagram so it can be scaled as a single unit */
.diagram-wrapper{
    width: 100%;
    display: flex;
    justify-content: center;
}

/* inner element (no change at desktop) */
.diagram-inner{
    display: inline-block; /* shrink-wrap to content */
}

/* Grid layout for the top part of the diagram.
   Five columns: box, arrow, box, arrow, box.
   Stretch items so boxes share the same height. */
.diagram-top {
    display: grid;
    grid-template-columns: 1fr 80px 1fr 80px 1fr;
    align-items: stretch; /* make boxes equal height (match tallest) */
    justify-items: center;
    gap: 0.5rem;
}

/* Generic diagram box styling */
.diagram-box {
    border: 2px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem 1rem 1rem;
    position: relative;
    min-width: 180px;

    /* Make inner layout vertical and allow pushing items to the bottom */
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;

    /* Ensure the box stretches to the grid row height */
    height: 100%;
}
.box-title {
    position: absolute;
    top: -3.5rem;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-color);
    padding: 0 0.5rem;
    font-weight: bold;
    opacity: 0.5;
}

/* Interaction tools stacked vertically like other boxes */
.interaction-tools {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

/* Node styling: emoji with label and optional tooltip */
.node {
    position: relative;
    margin: 0.5rem;
    text-align: center;
    font-size: 2rem;
    cursor: pointer;
    transition: transform 0.2s;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.node:hover,
.node.active-node {
    transform: scale(1.2);
}
.node-label {
    font-size: 0.8rem;
    margin-top: 0.25rem;
}
/* Tooltip styling for nodes, appears on click */
.node-tooltip {
    position: absolute;
    background: var(--input-bg);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    width: 300px;
    white-space: normal;
    text-align: justify;
    z-index: 10;
    pointer-events: none;
}

/* Push stargates to the bottom inside their boxes */
.node.stargate {
    margin-top: auto; /* occupies remaining vertical space above, pinning to bottom padding */
}

/* Arrow container styling */
.arrow {
    color: var(--border-color);
    opacity: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.arrow.horizontal { flex-direction: column; }
.arrow.vertical   { flex-direction: column; }
.arrow .arrow-label {
    font-size: 0.8rem;
    white-space: nowrap;
    margin-bottom: 0.2rem;
}
.arrow svg { overflow: visible; }
.arrow.active {
    color: var(--link-color);
    opacity: 1;
}

/* Bottom "executing code" arrow row:
   - Same 5-column grid for perfect centering between the two rightmost boxes.
   - Pull the arrow up so its line sits level with the STARGATE centers. */
.bottom-exec-row {
    display: grid;
    grid-template-columns: 1fr 80px 1fr 80px 1fr;
    align-items: end;       /* place the arrow at the bottom of this row */
    justify-items: center;
    gap: 0.5rem;
    /* Offset accounts for box padding, STARGATE label and icon height so the arrow sits level */
    margin-top: calc(-3.05rem - 28px);
}

h3 {
  padding-bottom: 0.5rem;
}

/* Responsive adjustments for small screens */
@media (max-width: 600px) {
    .onboard-form { flex-direction: column; }

    /* Scale the entire diagram instead of stacking boxes */
    .diagram-wrapper{
        /* remove: position, left, transform */
    }

    .diagram-inner{
        transform: scale(0.6);
        transform-origin: top center;
    }
}
</style>
<div class="landing-container">
    <h1 style="opacity:0.5; font-size: 4rem;">TNX API</h1>
    <!-- <span class="tagline">Speak with your database in your language!</span> -->
    <!-- <span class="tagline">AI + your database = <span class="accent">your most powerful administrative employee!</span></span> -->
    <span class="tagline"><span class="accent">Do more of what you love</span> while our AI system handles the administrativ stuff!</span>
    <span class="taglineSub">We connect AI with your business data to create your most powerful administrative employee!</span>

    <?php if ($message): ?>
        <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
    <?php elseif ($error): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <br><br>
    <form method="post" class="onboard-form">
        <input type="email" name="email" placeholder="come@andjoin.us" required pattern="[^@\s]+@[^@\s]+\.[^@\s]+">
        <a href="#" class="button-link" title="unlock darkmode   ; )">✨ JOIN ONBOARDING LIST</a>
    </form>

    <br><br><br><br><br>
    <h3>🛠️ HOW IT WORKS</h3>

    <br><br><br>

    <!-- Diagram container: keeps layout intact while allowing responsive scaling -->
    <div class="diagram-wrapper">
    <div class="diagram-inner">

    <!-- Diagram top: three boxes and two horizontal arrows (connected & crafts/checks) -->
    <div class="diagram-top">
        <!-- Left column: Interaction tools box with four nodes stacked vertically -->
        <div class="diagram-box interaction-tools">
            <div class="box-title">INTERACTION TOOLS</div>
            <div class="node" data-arrows="arrow1" data-tooltip="Chat with your data as you would chat with a fellow colleague, but a very wise one, for example: Please create a chart showing all sales of last month by cost conter.">
                💬
                <div class="node-label">AI CHAT</div>
            </div>
            <div class="node" data-arrows="arrow1" data-tooltip="Edit entries directly using our smart forms.">
                🟦
                <div class="node-label">ENTRY EDIT</div>
            </div>
            <div class="node" data-arrows="arrow1" data-tooltip="Connect your email and thus let others ask questions directly to your data too, for example: What is the inventory level of item PI-3141-592?">
                ✉️
                <div class="node-label">EMAILBOT</div>
            </div>
            <div class="node" data-arrows="arrow1" data-tooltip="Allow others to view complete datasets (for example: your products and services) and also to place orders.">
                🌐
                <div class="node-label">SHOP</div>
            </div>
        </div>

        <!-- Horizontal arrow linking interaction tools to MAIN SYSTEM -->
        <div class="arrow horizontal" id="arrow1">
            <span class="arrow-label">connected</span>
            <svg width="80" height="16" viewBox="0 0 80 16">
                <line x1="8" y1="8" x2="72" y2="8" stroke="currentColor" stroke-width="2" />
                <polygon points="8,0 0,8 8,16" fill="currentColor" />
                <polygon points="72,0 80,8 72,16" fill="currentColor" />
            </svg>
        </div>

        <!-- Middle column: MAIN SYSTEM box -->
        <div class="diagram-box">
            <div class="box-title">MAIN SYSTEM</div>

            <!-- AI brain node -->
            <div class="node" data-arrows="arrow1 arrow2" data-tooltip="Structures, visualizes and modifies data, translating between humans and machines (including by writing code).">
                🧠
                <div class="node-label">AI BRAIN</div>
            </div>

            <!-- Vertical arrow: crafts code -->
            <div class="arrow vertical" id="arrow2">
                <span class="arrow-label">crafts code</span>
                <svg width="16" height="60" viewBox="0 0 16 60">
                    <line x1="8" y1="0" x2="8" y2="52" stroke="currentColor" stroke-width="2" />
                    <polygon points="0,52 8,60 16,52" fill="currentColor" />
                </svg>
            </div>

            <!-- API nexus node -->
            <div class="node" data-arrows="arrow1 arrow2 arrow3 arrow4" data-tooltip="Checks and prepares code from the AI BRAIN for execution.">
                🕸️
                <div class="node-label">API NEXUS</div>
            </div>

            <!-- Vertical arrow: checks -->
            <div class="arrow vertical" id="arrow3">
                <span class="arrow-label">checks</span>
                <svg width="16" height="60" viewBox="0 0 16 60">
                    <line x1="8" y1="0" x2="8" y2="52" stroke="currentColor" stroke-width="2" />
                    <polygon points="0,52 8,60 16,52" fill="currentColor" />
                </svg>
            </div>

            <!-- Stargate pinned to bottom of MAIN SYSTEM box -->
            <div class="node stargate" data-arrows="arrow3 arrow5" data-tooltip="Sends code to your server and receives responses, which API NEXUS processes and returns to the corresponding interaction tool.">
                🕳️
                <div class="node-label">STARGATE</div>
            </div>
        </div>

        <!-- Horizontal arrow linking TNX API to YOUR SERVER -->
        <div class="arrow horizontal" id="arrow4">
            <span class="arrow-label">connected</span>
            <svg width="80" height="16" viewBox="0 0 80 16">
                <line x1="8" y1="8" x2="72" y2="8" stroke="currentColor" stroke-width="2" />
                <polygon points="8,0 0,8 8,16" fill="currentColor" />
                <polygon points="72,0 80,8 72,16" fill="currentColor" />
            </svg>
        </div>

        <!-- Right column: YOUR SERVER box (stretches to match TNX API height) -->
        <div class="diagram-box">
            <div class="box-title">YOUR SERVER</div>

            <div class="node" data-arrows="arrow4" data-tooltip="Handles databases and also file attachments, while they remain on your own dedicated server, ensuring both safety and the possibility to add other codes for interaction too.">
                🧬
                <div class="node-label">YOUR DATA</div>
            </div>

            <div style="opacity: 0.3;" class="node" data-arrows="" data-tooltip="As this is your own dedicated server, you can also add any other tools and codes you can imagine.">
                🔧
                <div class="node-label">OTHER TOOLS</div>
            </div>

            <!-- Stargate pinned to bottom of YOUR SERVER box -->
            <div class="node stargate" data-arrows="arrow5" data-tooltip="Receives code, executes it on your server, and sends back the responses.">
                🕳️
                <div class="node-label">STARGATE</div>
            </div>
        </div>
    </div>

    <!-- Bottom row: horizontal arrow connecting the two stargates (executing code) -->
    <div class="bottom-exec-row">
        <div></div>
        <div></div>
        <div></div>
        <div class="arrow horizontal" id="arrow5">
            <span class="arrow-label">executing code</span>
            <svg width="80" height="16" viewBox="0 0 80 16">
                <line x1="0" y1="8" x2="72" y2="8" stroke="currentColor" stroke-width="2" />
                <polygon points="72,0 80,8 72,16" fill="currentColor" />
            </svg>
        </div>
        <div></div>
    </div>

  </div> <!-- end diagram-inner -->  
  </div> <!-- end diagram-wrapper -->

    <br><br><br>

    <br><br><br><br><br>
    <h3>🌱 EVERYTHING BECOMES POSSIBLE</h3>
    <div class="security-list">
        <div>While many other business software is broken or solutions you wish for just don't exist, with our system granting you your own dedicated server, everything becomes possible. You can change, modifiy and adjust everything exactly tailored to your specific needs and add every custom code solution imaginable!</div>
    </div>

    <br><br><br><br><br>
    <h3>🤝 WE ARE OPEN SOURCE</h3>
    <div class="security-list">
        <div>Basically the same advantages as above, but now even better   XD.</div>
        <div class="code-link">
            <a href="https://github.com/StultusEstQuiHocLegit/TNXAPI2" target="_blank" rel="noopener noreferrer">⚙️ SEE OUR CODE</a>
        </div>
    </div>

    <br><br><br><br><br>
    <h3>🔧 ALL COMMON DATABASE ACTIONS <span class="accent">AND MORE</span></h3>
    <p>INSERT INTO, UPDATE, DELETE, SELECT, <span class="accent">SEARCH, GET CONTEXT</span></p>
    <p>Getting your data ready for <span class="accent">AI</span>!</p>

    <br><br><br><br><br>
    <h3>🛡️ SECURITY CONSTANTLY IN MIND</h3>
    <div class="security-list">
        <div><strong>-</strong> data on your own dedicated server <span style="opacity:0.5;">(can also be your legacy server)</span></div>
        <div><strong>-</strong> log files are created automatically for every AI action</div>
        <div><strong>-</strong> regular backups on your server possible</div>
        <div><strong>-</strong> different access rights for admin accounts and user accounts</div>
    </div>

    <br><br><br><br><br>
    <h3>💡 EXAMPLES OF WHAT OUR SYSTEM CAN DO FOR YOU</h3>
    <div class="security-list">
        <div><strong>-</strong> create charts and editable tables for data visualization</div>
        <div><strong>-</strong> write PDFs <span style="opacity:0.5;">(invoices, offers, delivery receipts, reports, contracts, legal documents, ...)</span></div>
        <div><strong>-</strong> handle uploads <span style="opacity:0.5;">(extract important information, classify and enter it into the database)</span></div>
        <div><strong>-</strong> code like an expert <span style="opacity:0.5;">(adjust specific entries or bulk update entire tables in seconds)</span></div>
    </div>

    <br><br><br><br><br>
    <span style="font-size: 2rem; font-style: italic;">"We take care of your organizational business stuff, so you can concentrate on building great products and services for great people."</span>

    <br><br><br>
    <br><br><br><br><br>
    <!-- Onboarding form at the bottom as well -->
    <form method="post" class="onboard-form">
        <input type="email" name="email" placeholder="come@andjoin.us" required pattern="[^@\s]+@[^@\s]+\.[^@\s]+">
        <a href="#" class="button-link" title="unlock darkmode   ; )">✨ JOIN ONBOARDING LIST</a>
    </form>
</div>

<script>
// Enhance interactivity: update button opacity when email input becomes valid,
// submit via the link, and show tooltips with connection highlighting.
document.addEventListener('DOMContentLoaded', function() {
    // const firstInput = document.querySelector('.onboard-form input[type="email"]');
    // if (firstInput) {
    //     firstInput.focus();
    //     firstInput.select();
    // }
    document.querySelectorAll('.onboard-form').forEach(form => {
        const emailInput = form.querySelector('input[type="email"]');
        const link = form.querySelector('.button-link');
        function updateOpacity() {
            link.style.opacity = emailInput.checkValidity() ? '1' : '0.3';
        }
        updateOpacity();
        emailInput.addEventListener('input', updateOpacity);
        emailInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                if (emailInput.checkValidity()) {
                    form.submit();
                    form.reset();
                    updateOpacity();
                }
            }
        });
        link.addEventListener('click', function(e){
            e.preventDefault();
            if (emailInput.checkValidity()) {
                form.submit();
                form.reset();
                updateOpacity();
            }
        });
    });

    // Clear arrow highlights and tooltips when clicking elsewhere
    function clearHighlights() {
        document.querySelectorAll('.arrow').forEach(a => a.classList.remove('active'));
        document.querySelectorAll('.node-tooltip').forEach(t => t.remove());
        document.querySelectorAll('.node').forEach(n => n.classList.remove('active-node'));
    }
    document.addEventListener('click', clearHighlights);

    // When a node is clicked, highlight connected arrows and show tooltip
    document.querySelectorAll('.node').forEach(node => {
        node.addEventListener('click', function(e) {
            e.stopPropagation();
            const isActive = this.classList.contains('active-node');
            clearHighlights();
            if (isActive) return;
            const ids = (this.dataset.arrows || '').split(' ');
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('active');
            });
            const tooltip = document.createElement('div');
            tooltip.className = 'node-tooltip';
            tooltip.textContent = this.dataset.tooltip;
            document.body.appendChild(tooltip);
            const rect = this.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltipRect.width / 2}px`;
            tooltip.style.top = `${window.scrollY + rect.top - tooltipRect.height - 8}px`;
            this.classList.add('active-node');
        });
    });
});
</script>
<?php require_once('footer.php'); ?>
