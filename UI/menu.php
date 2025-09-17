<?php
require_once('../config.php');
require_once('header.php');
?>

<div class="container menu-container" style="text-align: center;">
    <h1 class="text-center">🧭 TRAMANN PROJECTS TNX API <span id="additionalIcon"></span></h1>
    
    <nav class="menu-nav">
        <a href="index.php" class="menu-item" title="talk with the brain, interact with the brain, work with the brain (maybe sometimes you should   ; ) )">
            <div style="font-size: 2.5rem;">🧠</div>
            <span class="menu-title">BRAIN</span>
        </a>
        <a href="EmailBot.php" class="menu-item" title="automate your email">
            <div style="font-size: 2.5rem;">🤖</div>
            <span class="menu-title">EMAIL BOT</span>
        </a>
        <a href="workflows.php" class="menu-item" title="create your own workflows, automate your tasks and make your life easier">
            <div style="font-size: 2.5rem;">⚡</div>
            <span class="menu-title">WORKFLOWS</span>
        </a>
        <a href="logs.php" class="menu-item" title="browse log files of your past API actions, debug processes, reverse mistakes and track system actions">
            <div style="font-size: 2.5rem;">📜</div>
            <span class="menu-title">LOG FILES</span>
        </a>
        <!-- <a href="TRAMANNAPIConnectionHelper.php" class="menu-item" title="play around with the TRAMANN API">
            <div style="font-size: 2.5rem;">🕸️</div>
            <span class="menu-title">TRAMANN API</span>
        </a> -->

        <!-- only for admins-->
        <?php if ($_SESSION['IsAdmin']): ?>
            <a href="team.php" class="menu-item" title="create accounts for your team members">
                <div style="font-size: 2.5rem;">👥</div>
                <span class="menu-title">TEAM</span>
            </a>
            <a href="SetupCompany.php" class="menu-item" title="configure your general company settings and information">
                <div style="font-size: 2.5rem;">🏭</div>
                <span class="menu-title">COMPANY SETUP</span>
            </a>
            <a href="SetupCommunication.php" class="menu-item" title="connect with your email communication tools">
                <div style="font-size: 2.5rem;">✉️</div>
                <span class="menu-title">COMMUNICATION SETUP</span>
            </a>
            <a href="SetupStargate.php" class="menu-item" title="connect with your server">
                <div style="font-size: 2.5rem;">🕳️</div>
                <span class="menu-title">STARGATE SETUP</span>
            </a>
            <a href="SetupDatabases.php" class="menu-item" title="define the structure of your databases tables">
                <div style="font-size: 2.5rem;">🧬</div>
                <span class="menu-title">DATABASES SETUP</span>
            </a>
            <!-- <a href="SetupDatabasesConnection.php" class="menu-item" title="connect with your databases and define their structure">
                <div style="font-size: 2.5rem;">🧬</div>
                <span class="menu-title">DATABASES SETUP</span>
            </a> -->
            <!-- <a href="SetupFiles.php" class="menu-item" title="connect with your files (images, documents, ...)">
                <div style="font-size: 2.5rem;">☁️</div>
                <span class="menu-title">FILES SETUP</span>
            </a> -->
            <a href="SetupShop.php" class="menu-item" title="launch and manage your own online shop based on your database data">
                <div style="font-size: 2.5rem;">🌐</div>
                <span class="menu-title">SHOP SETUP</span>
            </a>
        <?php endif; ?>

        <a href="account.php" class="menu-item" title="manage your account and your settings (of course we also got a darkmode   ; ) )">
            <div style="font-size: 2.5rem;">⚙️</div>
            <span class="menu-title">ACCOUNT</span>
        </a>
        <!-- <div class="menu-spacer"></div> -->
        <!-- <a href="mailto:hi@tnxapi.com?subject=TNX API - Hi  : )&body=Hi,%0D%0A%0D%0A%0D%0A[ContentOfYourMessage]%0D%0A%0D%0A%0D%0A%0D%0AWith best regards,%0D%0A[YourName]" class="menu-item" title="always at your service   : )" style="opacity: 0.5;">
            <div style="font-size: 2.5rem;">✉️</div>
            <span class="menu-title">CONTACT US   : )</span>
        </a> -->
        <a href="./contact.php" class="menu-item" title="always at your service   : )" style="opacity: 0.5;">
            <div style="font-size: 2.5rem;">✉️</div>
            <span class="menu-title">CONTACT US   : )</span>
        </a>
        <a href="../index.php" class="menu-item" title="view our other projects and learn about us" style="opacity: 0.5;">
            <div style="font-size: 2.5rem;">👑</div>
            <span class="menu-title">TRAMANN PROJECTS</span>
        </a>
        <!-- <div class="menu-spacer"></div> -->
        <a href="../imprint.php" class="menu-item" title="legal stuff" style="opacity: 0.2;">
            <div style="font-size: 2.5rem;">🖋️</div>
            <span class="menu-title">IMPRINT</span>
        </a>
        <a href="../DataSecurity.php" class="menu-item" title="more legal stuff" style="opacity: 0.2;">
            <div style="font-size: 2.5rem;">🔒</div>
            <span class="menu-title">DATA SECURITY</span>
        </a>
    </nav>
    <br><br><br><br><br>
    <span style="opacity: 0.2;">
        <?php
            $segments = [];

            $namePieces = [];
            if (!empty($user['FirstName'])) {
                $namePieces[] = htmlspecialchars($user['FirstName']);
            }
            if (!empty($user['LastName'])) {
                $namePieces[] = htmlspecialchars($user['LastName']);
            }
            if ($namePieces) {
                $name = implode(' ', $namePieces);
                if (!empty($user['idpk'])) {
                    $name .= ' (' . htmlspecialchars($user['idpk']) . ')';
                }
                $segments[] = $name;
            }

            $companyName = $_SESSION['CompanyName'] ?? '';
            if (!empty($companyName)) {
                $company = htmlspecialchars($companyName);
                $companyId = $_SESSION['IdpkOfAdmin'] ?? '';
                if (!empty($companyId)) {
                    $company .= ' (' . htmlspecialchars($companyId) . ')';
                }
                $segments[] = $company;
            }

            $segments[] = 'TRAMANN PROJECTS TNX API';

            echo implode(' | ', $segments) . ' | ';
        ?>
        Stultus est, qui hoc legit.
    </span>
</div>

<?php require_once('footer.php'); ?>

<script>
    // Function to dynamically load an icon and tooltip based on the current date and time
    function loadAdditionalIcon() {
        const iconElement = document.getElementById('additionalIcon');
        const currentDate = new Date();
        const hours = currentDate.getHours();
        const month = currentDate.getMonth() + 1; // JavaScript months are 0-based
        const day = currentDate.getDate();

        let iconHTML = '';
        let titleText = '';

        // Time-based icons
        if (hours >= 6 && hours < 9) {
            iconHTML = '☀️'; // Rising Sun
            titleText = 'good morning   : )';
        } else if (hours >= 21 || hours < 6) {
            iconHTML = '🌙'; // Moon
            titleText = 'good night   : )';
        } else {
            // Day-based themes
            const dateKey = `${month}-${day}`;
            const dayThemes = {
                '1-1': { icon: '🎉', title: 'happy new year   : )' },
                '1-2': { icon: '🚀', title: 'happy science fiction day   : )' },
                '1-6': { icon: '👑', title: 'happy epiphany   : )' },
                '1-14': { icon: '🪁', title: 'happy makar sankranti   : )' },
                '1-16': { icon: '🐉', title: 'happy appreciate a dragon day   : )' },
                '1-25': { icon: '🐇', title: 'happy lunar new year   : )' },
                '2-2': { icon: '🦫', title: 'once again it’s groundhog day   : )' },
                '2-9': { icon: '🍕', title: 'happy pizza day   : )' },
                '2-14': { icon: '❤️', title: 'happy valentine’s day   : )' },
                '2-20': { icon: '🪁', title: 'happy kite flying day   : )' },
                '2-27': { icon: '🐻‍❄️', title: 'roooaar, happy polar bear day   : )' },
                '3-14': { icon: '🥧', title: 'celebrate pi day   : )' },
                '3-17': { icon: '☘️', title: 'happy st. patrick’s day   : )' },
                '3-20': { icon: '🌞', title: 'happy first day of spring   : )' },
                '3-23': { icon: '🐸', title: 'happy world meteorological day   : )' },
                '4-1': { icon: '😂', title: 'happy april fools’ day   : )' },
                '4-20': { icon: '🍀', title: 'happy earth day eve   : )' },
                '4-22': { icon: '🔵', title: 'happy earth day   : )' },
                '4-26': { icon: '👽', title: 'they are coming, happy alien day   : )' },
                '4-30': { icon: '🎷', title: 'happy jazz day   : )' },
                '5-1': { icon: '🛠️', title: 'happy labor day   : )   (lyrics of today: Arise, wretched of the earth + Arise, convicts of hunger + Reason thunders in its volcano + This is the eruption of the end + Of the past let us wipe the slate clean + Masses, slaves, arise, arise + The world is about to change its foundation + We are nothing, let us be all|: This is the final struggle + Let us gather together, and tomorrow + The Internationale + Will be the human race :|There are no supreme saviors + Neither God, nor Caesar, nor tribune. + Producers, let us save ourselves + Decree on the common welfare + That the thief return his plunder, + That the spirit be pulled from its prison + Let us fan the forge ourselves + Strike the iron while it is hot|: This is the final struggle + Let us stand together, and tomorrow + The Internationale + Will be the human race :|The state represses and the law cheats + The tax bleeds the unfortunate + No duty is imposed on the rich’ + Rights of the poor’ is a hollow phrase + Enough languishing in custody + Equality wants other laws: + No rights without obligations, it says, + And as well, no obligations without rights|: This is the final struggle + Let us stand together, and tomorrow + The Internationale + Will be the human race :|Hideous in their self-glorification + Kings of the mine and rail + Have they ever done anything otherThen steal work? + Into the coffers of that lot, + What work creates has melted + In demanding that they give it back + The people want only its due.|: This is the final struggle + Let us stand together, and tomorrow + The Internationale + Will be the human race :|The kings make us drunk with their fumes, + Peace among ourselves, war to the tyrants! + Let the armies go on strike, + Guns in the air, and break ranks + If these cannibals insist + On making heroes of us, + Soon they will know our bullets + Are for our generals|: This is the final struggle + Let us stand together, and tomorrow + The Internationale + Will be the human race :|Laborers, peasants, we are + The great party of workers + The earth belongs only to men + The idle will go reside elsewhere + How much of our flesh they feed on, + But if the ravens and vultures + Disappear one of these days + The sun will still shine|: This is the final struggle + Let us stand together, and tomorrow + The Internationale + Will be the human race :|)' },
                '5-4': { icon: '🌌', title: 'may the fourth be with you   : )' },
                '5-5': { icon: '🎉', title: 'happy cinco de mayo   : )' },
                '5-25': { icon: '🐬', title: 'happy towel day and thanks for all the fish   : )' },
                '6-8': { icon: '🌊', title: 'happy world oceans day   : )' },
                '6-18': { icon: '🍉', title: 'happy picnic day   : )' },
                '6-21': { icon: '☀️', title: 'happy summer solstice   : )' },
                '6-30': { icon: '☄️', title: 'happy asteroid day   : )' },
                '7-4': { icon: '🎆', title: 'happy independence day   : )' },
                '7-20': { icon: '🌕', title: 'celebrating new frontiers, happy moon landing day   : )' },
                '7-30': { icon: '🤝', title: 'happy friendship day   : )' },
                '8-9': { icon: '📚', title: 'happy book lovers day   : )' },
                '8-12': { icon: '🌌', title: 'stay up tonight for the the perseid meteor shower viewing day   : )' },
                '9-12': { icon: '💻', title: 'happy programmers’ day   : )' },
                '9-19': { icon: '🏴‍☠️', title: 'talk like a pirate day   : )' },
                '9-21': { icon: '🕊️', title: 'happy international day of peace   : )' },
                '10-1': { icon: '🎼', title: 'happy music day   : )' },
                '10-4': { icon: '🐾', title: 'happy world animal day   : )' },
                '10-23': { icon: '⚗️', title: 'happy mole day   : )' },
                '10-31': { icon: '🎃', title: 'happy halloween   : )' },
                '11-1': { icon: '🕯️', title: 'happy all saints’ day   : )' },
                '11-07': { icon: '🎂', title: 'happy TRAMANN PROJECTS first release day   : )' },
                '11-11': { icon: '🎖️', title: 'veterans day   : )' },
                '11-13': { icon: '🤝', title: 'happy world kindness day   : )' },
                '11-24': { icon: '🦃', title: 'happy thanksgiving   : )' },
                '12-1': { icon: '⛄️', title: 'happy first day of winter   : )' },
                '12-4': { icon: '🛰️', title: 'happy world space exploration day   : )' },
                '12-8': { icon: '⏳', title: 'happy pretend to be a time traveler day   : )' },
                '12-10': { icon: '📜', title: 'happy human rights day   : )' },
                '12-24': { icon: '🎄', title: 'merry christmas eve   : )' },
                '12-25': { icon: '🎁', title: 'merry christmas   : )' },
                '12-31': { icon: '🎆', title: 'happy new year’s eve   : )' },
            };

            // Default day-time icon
            const defaultTheme = { icon: '⭐', title: 'have a great day   : )' };

            const selectedTheme = dayThemes[dateKey] || defaultTheme;
            iconHTML = selectedTheme.icon;
            titleText = selectedTheme.title;
        }

        // Set the selected icon and tooltip into the element
        iconElement.innerHTML = iconHTML;
        iconElement.title = titleText;
    }

    // Run the function on page load
    document.addEventListener('DOMContentLoaded', loadAdditionalIcon);
</script>
