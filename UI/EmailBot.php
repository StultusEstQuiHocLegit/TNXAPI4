<?php
require_once('../config.php');
require_once('header.php');

$view = $_GET['view'] ?? 'inbox';
$view = ($view === 'sent') ? 'sent' : 'inbox';
$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';
$availableEmails = $_SESSION['ConnectEmailList'] ?? [];
$currentEmail = $_SESSION['ConnectEmail'] ?? '';

function highlightSQL($sql) {
    // Keywords to highlight with "sql-highlight-keyword"
    $keywordClass1 = ['SELECT', 'INSERT INTO', 'UPDATE', 'DELETE'];

    // Keywords to highlight with "sql-highlight-table"
    $keywordClass2 = [
        'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'LIMIT',
        'VALUES', 'SET', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'ON',
        'AND', 'OR', 'NOT', 'IN', 'AS'
    ];

    // First highlight keywords with sql-highlight-keyword
    foreach ($keywordClass1 as $keyword) {
        $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/i';
        $sql = preg_replace_callback($pattern, function ($matches) {
            return '<span class="sql-highlight-keyword">' . strtoupper($matches[1]) . '</span>';
        }, $sql);
    }

    // Then highlight keywords with sql-highlight-table
    foreach ($keywordClass2 as $keyword) {
        $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/i';
        $sql = preg_replace_callback($pattern, function ($matches) {
            return '<span class="sql-highlight-table">' . strtoupper($matches[1]) . '</span>';
        }, $sql);
    }

    // Highlight table names (assuming table names come right after FROM, INTO, UPDATE, DELETE)
    $tablePattern = '/\b(?:FROM|INTO|UPDATE|DELETE\s+FROM)\s+[`\'"]?([a-zA-Z0-9_]+)[`\'"]?/i';
    $sql = preg_replace_callback($tablePattern, function ($matches) {
        return str_replace(
            $matches[1],
            '<span class="sql-highlight-keyword">' . $matches[1] . '</span>',
            $matches[0]
        );
    }, $sql);

    return $sql;
}

$emptyInboxMessages = [
    "No emails found. Inbox so empty, it echoes.",
    "All clear, not even spam wants to visit.",
    "No emails, TRAMANN AI recommends enjoying the silence.",
    "Still scanning the void... nope, nothing.",
    "Crickets. Not even a 'Hi from mom'.",
    "So empty, black holes are jealous.",
    "Inbox status: abandoned space station.",
    "Inbox has left the chat.",
    "You outsmarted the system, no emails today.",
    "No mail, Internet turtles still on the way.",
    "Zero. Zilch. Nada.",
    "Inbox is blanker than your high school essays.",
    "Mailbox empty. Maybe check under the doormat?",
    "Zero emails, must be a mutation.",
    "Nothing here, even space has more content.",
    "No emails, aliens abducted your inbox.",
    "Inbox is sleeping, come back later.",
    "You’ve got... no mail.",
    "Email count is below sea level.",
    "Inbox was here... then Cthulhu took it.",
    "Even spam went elsewhere.",
    "No emails, inbox is undead.",
    "Your inbox is sparkling clean, like it never existed.",
    "Inbox is achieving inner peace.",
    "Inbox is cleaner than a freshly formatted SSD.",
    "You’ve reached inbox nirvana. Total emptiness.",
    "Even your imaginary friend didn’t send a message.",
    "Your inbox has achieved stealth mode.",
    "All systems online. All emails offline.",
    "The Matrix has no messages for you.",
    "console.log('No mail. Carry on.');",
    "Your inbox is playing hide and seek... and is winning.",
    "No emails, must be a stealth update.",
    "Inbox is stuck in a parallel universe.",
    "Your inbox passed a Turing test, by disappearing.",
    "Nothing here, Captain, sensors show zero activity.",
    "It’s like the emails knew you were coming.",
    "Emails have rage-quit.",
    "No emails. Even your clone didn’t write.",
    "Your inbox took a vow of silence.",
    "Digital monks would envy this level of quiet.",
    "inbox = [] // still true.",
    "The inbox gods demand a sacrifice (of productivity).",
    "All quiet on the email front.",
    "Your inbox joined a monastery in the cloud.",
    "Nothing here but digital tumbleweeds."
];

// Pick a random one
$RandomEmptyInboxMessage = $emptyInboxMessages[array_rand($emptyInboxMessages)];

// Function to get relative time
function getRelativeTime($timestamp) {
    $now = new DateTime();
    $date = new DateTime($timestamp);
    $diff = $now->diff($date);
    
    if ($diff->days === 0) {
        return '(today)';
    } elseif ($diff->days === 1) {
        return '(yesterday)';
    } elseif ($diff->days < 7) {
        return '(' . $date->format('l') . ')'; // Returns day name (Monday, Tuesday, etc.)
    } else {
        return '(' . $date->format('M j') . ')'; // Returns "Jan 15" format
    }
}

// Returns title like "30 days ago" or "in 5 days" based on difference by day boundaries
function getRelativeTitle($timestamp) {
    $now = new DateTime();
    // Normalize both dates to midnight for day-based difference
    $nowMidnight = new DateTime($now->format('Y-m-d'));
    $dateMidnight = new DateTime((new DateTime($timestamp))->format('Y-m-d'));

    // Calculate difference in days (signed)
    $diffDays = (int)$dateMidnight->diff($nowMidnight)->format('%r%a');

    if ($diffDays === 0) {
        return 'today'; // Title for today
    }

    $absDays = abs($diffDays);
    $dayWord = $absDays === 1 ? 'day' : 'days';

    if ($diffDays < 0) {
        // Date is in future
        return "in $absDays $dayWord";
    } else {
        // Date is in past
        return "$absDays $dayWord ago";
    }
}

function parseAddressList($addresses) {
    $result = [];
    if (is_array($addresses)) {
        foreach ($addresses as $addr) {
            $personal = !empty($addr->personal) ? mb_decode_mimeheader($addr->personal) : '';
            $email = ($addr->mailbox ?? '') . '@' . ($addr->host ?? '');
            if ($personal) {
                $result[] = "$email ($personal)";
            } else {
                $result[] = $email;
            }
        }
    }
    return $result;
}

function fetchBodyBySubtype($inbox, $msgno, $structure, $partNumber, $subtype) {
    if ($structure->type == 0 && strtolower($structure->subtype) === $subtype) {
        $partNum = $partNumber === '' ? 1 : $partNumber;
        $body = imap_fetchbody($inbox, $msgno, $partNum);
        switch ($structure->encoding) {
            case 3:
                $body = base64_decode($body);
                break;
            case 4:
                $body = quoted_printable_decode($body);
                break;
        }
        return $body;
    }

    if (!empty($structure->parts)) {
        foreach ($structure->parts as $index => $sub) {
            $prefix = $partNumber === '' ? '' : $partNumber . '.';
            $result = fetchBodyBySubtype($inbox, $msgno, $sub, $prefix . ($index + 1), $subtype);
            if ($result !== '') {
                return $result;
            }
        }
    }

    return '';
}

function fetchPlainTextBody($inbox, $msgno, $structure, $partNumber = '') {
    $plain = fetchBodyBySubtype($inbox, $msgno, $structure, $partNumber, 'plain');
    if ($plain !== '') {
        return $plain;
    }

    $html = fetchBodyBySubtype($inbox, $msgno, $structure, $partNumber, 'html');
    if ($html !== '') {
        $html = preg_replace('/<br\s*\/?\>/i', "\n", $html);
        $html = preg_replace('/<p\b[^>]*>/i', "\n\n", $html);
        $html = strip_tags($html);
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return '';
}

function fetchAttachments($inbox, $msgno, $structure, $partNumber = '') {
    $attachments = [];

    if (!empty($structure->parts)) {
        foreach ($structure->parts as $index => $sub) {
            $prefix = $partNumber === '' ? '' : $partNumber . '.';
            $partNum = $prefix . ($index + 1);

            $isAttachment = false;
            $filename = '';

            if ($sub->ifdparameters) {
                foreach ($sub->dparameters as $dp) {
                    if (strtolower($dp->attribute) === 'filename') {
                        $isAttachment = true;
                        $filename = $dp->value;
                        break;
                    }
                }
            }
            if (!$filename && $sub->ifparameters) {
                foreach ($sub->parameters as $p) {
                    if (strtolower($p->attribute) === 'name') {
                        $isAttachment = true;
                        $filename = $p->value;
                        break;
                    }
                }
            }

            if ($isAttachment) {
                $attBody = imap_fetchbody($inbox, $msgno, $partNum);
                switch ($sub->encoding) {
                    case 3:
                        $attBody = base64_decode($attBody);
                        break;
                    case 4:
                        $attBody = quoted_printable_decode($attBody);
                        break;
                }

                $mimeMajor = [
                    0 => 'text',
                    1 => 'multipart',
                    2 => 'message',
                    3 => 'application',
                    4 => 'audio',
                    5 => 'image',
                    6 => 'video',
                    7 => 'other'
                ];
                $mimeType = ($mimeMajor[$sub->type] ?? 'application') . '/' . strtolower($sub->subtype ?? 'octet-stream');

                $attachments[] = [
                    'name' => $filename,
                    'mime' => $mimeType,
                    'dataURL' => 'data:' . $mimeType . ';base64,' . base64_encode($attBody)
                ];
            } else {
                $attachments = array_merge($attachments, fetchAttachments($inbox, $msgno, $sub, $partNum));
            }
        }
    }

    return $attachments;
}

































// ///////////////////////////////// whole part was moved to header.php
// // Session connection data
// $ConnectEmail = $_SESSION['ConnectEmail'] ?? '';
// $ConnectPassword = $_SESSION['ConnectPassword'] ?? '';
// $ConnectServerName = $_SESSION['ConnectServerName'] ?? '';
// $ConnectPort = $_SESSION['ConnectPort'] ?? 993;
// $ConnectEncryption = $_SESSION['ConnectEncryption'] ?? 'ssl';
// 
// // Normalize encryption type
// $ConnectEncryption = strtolower($ConnectEncryption);
// 
// // If user selected TLS but the port is 993, override to SSL
// if ($ConnectEncryption === 'tls' && $ConnectPort == 993) {
//     $ConnectEncryption = 'ssl';
// }
// 
// // If invalid encryption, default to ssl
// if (!in_array($ConnectEncryption, ['ssl', 'tls'])) {
//     $ConnectEncryption = 'ssl';
// }
// 
// $isAdmin = $_SESSION['IsAdmin'] ?? 0;
// 
// $emails = [];
// $connectionSuccess = false;
// 
// // Try connecting to the mail server
// $imapServerString = "{" . $ConnectServerName . ":" . $ConnectPort . "/imap/" . strtolower($ConnectEncryption) . "}INBOX";
// 
// // DEBUG to browser
// echo "<script>console.log(" . json_encode([
//     'serverString' => $imapServerString,
//     'email' => $ConnectEmail,
//     'port' => $ConnectPort,
//     'encryption' => $ConnectEncryption,
// ]) . ");</script>";

if ($ConnectEmail && $ConnectPassword && $ConnectServerName) {
    $inbox = @imap_open($imapServerString, $ConnectEmail, $ConnectPassword);

    if (!$inbox) {
        $imapError = imap_last_error();
        echo "<script>console.error(" . json_encode("IMAP Error: $imapError") . ");</script>";
        error_log("IMAP Connection Error: " . $imapError); // Also log it server-side
    }

    if ($inbox) {
        $connectionSuccess = true;

        // Get emails sorted by newest unread first
        $unreadUids = imap_search($inbox, 'UNSEEN', SE_UID) ?: [];
        $readUids = imap_search($inbox, 'SEEN', SE_UID) ?: [];

        rsort($unreadUids); // newest first
        rsort($readUids);   // newest first

        $allUids = array_merge($unreadUids, $readUids);
        $emailsMeta = [];
        foreach ($allUids as $uid) {
            $msgno = imap_msgno($inbox, $uid);
            $header = imap_headerinfo($inbox, $msgno);
            $subject = mb_decode_mimeheader($header->subject ?? '(no subject)');
            $senderEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;

            if ($view === 'inbox') {
                if (strcasecmp($senderEmail, $ConnectEmail) === 0) {
                    continue; // skip self-sent emails in inbox
                }
            } elseif ($view === 'sent') {
                if (strcasecmp($senderEmail, $ConnectEmail) !== 0 || stripos($subject, 'YOU SENT:') !== 0) {
                    continue; // only show self-sent copies
                }
            }

            $emailsMeta[] = [
                'uid' => $uid,
                'msgno' => $msgno,
                'header' => $header,
                'subject' => $subject,
                'senderEmail' => $senderEmail,
                'isUnread' => in_array($uid, $unreadUids)
            ];
        }

        if ($emailsMeta) {
            // Pagination
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $emailsPerPage = 10;
            $totalEmails = count($emailsMeta);
            $totalPages = max(1, ceil($totalEmails / $emailsPerPage));
            if ($page > $totalPages) { $page = $totalPages; }
            $offset = ($page - 1) * $emailsPerPage;
            $pagedMeta = array_slice($emailsMeta, $offset, $emailsPerPage);

            foreach ($pagedMeta as $meta) {
                $uid = $meta['uid'];
                $msgno = $meta['msgno'];
                $header = $meta['header'];
                $subject = $meta['subject'];
                if ($view === 'sent') {
                    $subject = preg_replace('/^YOU SENT:\\s*/i', '', $subject);
                }
                $senderEmail = $meta['senderEmail'];
                $isUnread = $meta['isUnread'];

                if ($view !== 'sent' && $isUnread && strcasecmp($senderEmail, $ConnectEmail) === 0) {
                    imap_setflag_full($inbox, $uid, '\\Seen', ST_UID);
                    $isUnread = false;
                }

                $structure = imap_fetchstructure($inbox, $msgno);
                $decodedBody = fetchPlainTextBody($inbox, $msgno, $structure);
                $attachmentsList = fetchAttachments($inbox, $msgno, $structure);

                if ($view === 'sent') {
                    $rawHeader = imap_fetchheader($inbox, $msgno);
                    $origTo = [];
                    $origCc = [];
                    if (preg_match('/^X-Original-To:\s*(.+)$/mi', $rawHeader, $m)) {
                        $origTo = array_map('trim', explode(',', $m[1]));
                    }
                    if (preg_match('/^X-Original-Cc:\s*(.+)$/mi', $rawHeader, $m)) {
                        $origCc = array_map('trim', explode(',', $m[1]));
                    }
                    $emails[] = [
                        'UID' => $uid,
                        'Sender' => implode(', ', $origTo),
                        'SenderName' => '',
                        'To' => $origTo,
                        'Cc' => $origCc,
                        'Bcc' => [],
                        'Subject' => $subject,
                        'Body' => $decodedBody,
                        'Attachments' => $attachmentsList,
                        'formatted_time' => date('Y-m-d H:i:s', $header->udate),
                        'IsUnread' => false
                    ];
                } else {
                    $emails[] = [
                        'UID' => $uid,
                        'Sender' => $senderEmail,
                        'SenderName' => !empty($header->from[0]->personal) ? mb_decode_mimeheader($header->from[0]->personal) : '',
                        'To' => parseAddressList($header->to ?? []),
                        'Cc' => parseAddressList($header->cc ?? []),
                        'Bcc' => parseAddressList($header->bcc ?? []),
                        'Subject' => $subject,
                        'Body' => $decodedBody,
                        'Attachments' => $attachmentsList,
                        'formatted_time' => date('Y-m-d H:i:s', $header->udate),
                        'IsUnread' => $isUnread
                    ];
                }
            }
        } else {
            $error = "No emails found.";
        }

        imap_close($inbox);
    } else {
        if ($isAdmin) {
            $error = "Missing connection details.<br>Please check the <a href='SetupCommunication.php'>✉️ COMMUNICATION SETUP</a>.";
        } else {
            $error = "Missing connection details. Please contact your admin and tell him to check the communication setup.";
        }
    }
} else {
    if ($isAdmin) {
        $error = "Missing connection details.<br>Please check the <a href='SetupCommunication.php'>✉️ COMMUNICATION SETUP</a>.";
    } else {
        $error = "Missing connection details. Please contact your admin and tell him to check the communication setup.";
    }
    $connectionSuccess = false;
}

function markAsRead($inbox, $uid) {
    // Set the \Seen flag on the message with given UID
    return imap_setflag_full($inbox, $uid, "\\Seen", ST_UID);
}

function markAsUnread($inbox, $uid) {
    // Remove the \Seen flag on the message with given UID
    return imap_clearflag_full($inbox, $uid, "\\Seen", ST_UID);
}

?>





































<div class="containerWithoutBorder" style="max-width: 800px; margin: auto;">
    <h1 class="text-center">🤖 EMAIL BOT</h1>

    <div class="view-selector">
        <?php if (count($availableEmails) > 1): ?>
            <select id="email-switcher">
                <?php foreach ($availableEmails as $email): ?>
                    <option value="<?php echo htmlspecialchars($email); ?>"<?php echo $email === $currentEmail ? ' selected' : ''; ?>><?php echo htmlspecialchars($email); ?></option>
                <?php endforeach; ?>
            </select>
            <script>
                document.getElementById('email-switcher').addEventListener('change', function(){
                    var e=this.value;
                    var d=new Date();
                    d.setTime(d.getTime()+10*365*24*60*60*1000);
                    document.cookie='MainEmailForSending='+encodeURIComponent(e)+';expires='+d.toUTCString()+';path=/';
                    location.reload();
                });
            </script>
        <?php endif; ?>
        <a class="view-box<?php echo $view === 'inbox' ? ' selected' : ''; ?>" href="EmailBot.php?view=inbox" title="your email inbox">📯 INBOX</a>
        <a class="view-box" href="index.php?pm=<?php echo urlencode('Please write an email about'); ?>" title="write an email">🪶 WRITE</a>
        <a class="view-box<?php echo $view === 'sent' ? ' selected' : ''; ?>" href="EmailBot.php?view=sent" title="emails you have sent">📜 SENT</a>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="inbox-container">
        <?php if (!$connectionSuccess): ?>
            <br><br>
            <div class="container">
                <!-- <form method="POST"> -->
                    <!-- <label for="manual_emails">You can also paste email content below for manual processing:</label> -->
                    <!-- <textarea name="manual_emails" id="manual_emails" rows="8" style="width:100%; overflow-y: auto; overflow-x: hidden; resize: vertical;"></textarea> -->
                    <!-- <br><br> -->
                    <!-- <button id="LinkSubmit">🪄 FINISH</button> -->
                    <!-- <button type="submit">🪄 FINISH</button> -->
                <!-- </form> -->
            </div>
        <?php elseif (empty($emails)): ?>
            <div class="alert alert-info"><?php echo $RandomEmptyInboxMessage; ?></div>
        <?php else: ?>
            <div class="email-list">
                <?php foreach ($emails as $email): ?>
                    <div class="email-block" data-uid="<?php echo htmlspecialchars($email['UID']); ?>" style="<?php echo ($email['IsUnread'] || $view === 'sent') ? '' : 'opacity: 0.5;'; ?>">
                        <div class="email-header">
                            <span class="email-time" title="<?php echo htmlspecialchars(getRelativeTitle($email['formatted_time'])); ?>">
                                <?php 
                                echo htmlspecialchars($email['formatted_time']);
                                echo ' ' . getRelativeTime($email['formatted_time']);
                                ?>
                            </span>
                            <div class="email-actions">
                                <?php if ($view !== 'sent'): ?>
                                    <span class="email-type" style="<?php echo $email['IsUnread'] ? '' : 'opacity: 0.5;'; ?>" title="<?php echo $email['IsUnread'] ? 'unread yet, click to mark as read' : 'already read, click to mark as unread again'; ?>"><?php echo $email['IsUnread'] ? 'new' : 'old'; ?></span>
                                    <?php if ($email['Body']): ?>
                                        <!-- <form method="POST" class="finish-form">
                                            <input type="hidden" name="content_body" value="<?php echo htmlspecialchars($email['Body']); ?>">
                                            <button type="submit" class="finish-button" style="<?php echo $email['IsUnread'] ? '' : 'opacity: 0.5;'; ?>">🪄 FINISH</button>
                                        </form>-->
                                        <button id="LinkSubmit" class="finish-button" style="<?php echo $email['IsUnread'] ? '' : 'opacity: 0.5;'; ?>">🪄 FINISH</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="email-content">
                            <div class="small-box">
                                <!-- <strong style="color: var(--accent-color);"><?php // echo htmlspecialchars($email['Sender']); ?> <span style="opacity: 0.7;">(<?php // echo htmlspecialchars($email['SenderName']); ?>)</span></strong> -->
                                 <?php
                                    $senderName = trim($email['SenderName']);
                                    $pmText = 'Please write an email to ' . $email['Sender'];
                                    if ($senderName !== '') {
                                        $pmText .= ' (' . $senderName . ')';
                                    }
                                    $pmText .= ' about';
                                
                                    // Collect all recipients (To, Cc, Bcc) excluding the user himself
                                    $recipients = [];
                                    foreach (['To', 'Cc', 'Bcc'] as $field) {
                                        if (!empty($email[$field]) && is_array($email[$field])) {
                                            foreach ($email[$field] as $recipient) {
                                                // Extract email from possible "email (name)" string using regex
                                                if (preg_match('/^([^ ]+)(?:\s*\(.*\))?$/', $recipient, $matches)) {
                                                    $emailOnly = $matches[1];
                                                } else {
                                                    $emailOnly = $recipient;
                                                }
                                                if (strtolower($emailOnly) !== strtolower($ConnectEmail)) {
                                                    $recipients[$field][] = '<a href="index.php?pm=' . urlencode('Please write an email to ' . $recipient . ' about') . '">' . htmlspecialchars($recipient) . '</a>';
                                                }
                                            }
                                        }
                                    }

                                // Prepare recipients for top line in sent view
                                    $toLine = isset($recipients['To']) ? implode(' | ', $recipients['To']) : '';
                                ?>
                                <strong>
                                    <?php if ($view === 'sent' && $toLine !== ''): ?>
                                        <?php echo $toLine; ?>
                                    <?php else: ?>
                                        <a href="index.php?pm=<?php echo urlencode($pmText); ?>">
                                            <?php echo htmlspecialchars($email['Sender']); ?>
                                            <?php if ($senderName !== ''): ?>
                                                <span style="opacity: 0.7;">(<?php echo htmlspecialchars($senderName); ?>)</span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endif; ?>
                                </strong>

                                <?php
                                    // Build an array of "FIELD: recipient1, recipient2" strings for only non-empty fields
                                    $parts = [];
                                    $fieldsForParts = ($view === 'sent') ? ['Cc', 'Bcc'] : ['To', 'Cc', 'Bcc'];
                                    foreach ($fieldsForParts as $field) {
                                        if (!empty($recipients[$field])) {
                                            $fullListHtml = implode(', ', $recipients[$field]);
                                            $parts[] = sprintf(
                                                '%s: <span title="%s">%s</span>',
                                                htmlspecialchars(strtoupper($field)),
                                                htmlspecialchars(strip_tags($fullListHtml)),
                                                $fullListHtml
                                            );
                                        }
                                    }

                                    if (!empty($parts)) {
                                        // Now join all parts with " | "
                                        echo '<div>' . implode(' | ', $parts) . '</div>';
                                    }
                                ?>

                                <?php
                                // Truncate subject to 100 chars and add tooltip with full subject
                                if (!empty($email['Subject'])) {
                                    $fullSubject = $email['Subject'];
                                    $truncatedSubject = mb_strlen($fullSubject) > 100 ? mb_substr($fullSubject, 0, 100) . '...' : $fullSubject;
                                    ?>
                                    <br>
                                    <strong title="<?php echo htmlspecialchars($fullSubject); ?>">
                                        <?php echo htmlspecialchars($truncatedSubject); ?>
                                    </strong>
                                <?php
                                }
                                ?>
                            </div>
                            <?php if ($email['Body']): ?>
                                <div class="small-box">
                                    <pre><?php echo htmlspecialchars($email['Body']); ?></pre>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($email['Attachments'])): ?>
                                <div class="email-attachments">
                                    <?php foreach ($email['Attachments'] as $att): ?>
                                        <div class="chat-attachment" title="<?php echo htmlspecialchars($att['name']); ?>">
                                            <a href="<?php echo htmlspecialchars($att['dataURL']); ?>" target="_blank">
                                                <?php if (strpos($att['mime'], 'image/') === 0): ?>
                                                    <img src="<?php echo htmlspecialchars($att['dataURL']); ?>" alt="<?php echo htmlspecialchars($att['name']); ?>">
                                                <?php else: ?>
                                                    <span><?php echo htmlspecialchars($att['name']); ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- <div class="email-content">
                            <div class="small-box">
                                <strong>executed</strong>
                                <pre><?php // echo highlightSQL(htmlspecialchars($email['Sender'])); ?></pre>
                            </div>
                            <?php // if ($email['Body']): ?>
                                <div class="small-box-less-important" style="opacity: 0.5;">
                                    <strong>rollback</strong>
                                    <pre><?php // echo highlightSQL(htmlspecialchars($email['Body'])); ?></pre>
                                </div>
                            <?php // endif; ?>
                        </div> -->
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-left">
                        <?php if ($page > 1): ?>
                            <a href="?view=<?php echo $view; ?>&page=<?php echo $page - 1; ?>">◀️ PAGE <?php echo $page - 1; ?></a>
                        <?php else: ?>
                            <span class="placeholder"></span>
                        <?php endif; ?>
                    </div>
                        
                    <div class="pagination-center">
                        <span class="page-info">page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    </div>
                        
                    <div class="pagination-right">
                        <?php if ($page < $totalPages): ?>
                            <a href="?view=<?php echo $view; ?>&page=<?php echo $page + 1; ?>">▶️ PAGE <?php echo $page + 1; ?></a>
                        <?php else: ?>
                            <span class="placeholder"></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div id="FinishingBlock" class="email-block" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="#" id="LinkBack" title="Back, back, baaaaack!!!" style="opacity: 0.7;">◀️ RETURN</a><a href="#"  id="LinkRetry" title="reboot the universe" style="opacity: 0.5;">🔁 RETRY</a><a href="#" id="LinkFinalFinish" title="nailed it"><strong>🪄 FINISH</strong></a>
        </div><br>
        <div class="email-content">
            <div class="small-box">
                <strong>response</strong>
                <textarea id="response" class="FinishingBlockEditableFields"></textarea>
            </div>
            <div class="small-box-less-important" style="opacity: 0.5; display: none;">
                <strong>code for execution</strong>
                <textarea id="CodeForExecution" class="FinishingBlockEditableFields"></textarea>
            </div>
        </div>
    </div>
    <div id="ContextContainer" style="display: none;"></div>
</div>











































<style>
.sql-highlight-keyword {
    color: var(--accent-color) !important;
    font-weight: bold;
}

.sql-highlight-table {
    font-weight: bold;
}

.inbox-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.email-block {
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 1rem;
    padding: 1rem;
    box-shadow: 0 2px 4px var(--shadow-color);
}

.email-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
}

.email-time {
    color: var(--text-color);
    opacity: 0.7;
}

.email-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.finish-form {
    margin: 0;
    padding: 0;
}

.finish-button {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: opacity 0.3s;
    min-width: 60px;
    text-align: center;
}

.finish-button:hover {
    opacity: 0.8;
}

.email-type {
    background-color: var(--border-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.5rem;
}

.email-content {
    font-family: monospace;
    font-size: 0.9rem;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.email-content a {
    word-break: break-word;
    overflow-wrap: anywhere;
}

.small-box, .small-box-less-important {
    margin-bottom: 1rem;
}

.small-box pre, .small-box-less-important pre {
    background-color: var(--bg-color);
    padding: 0.5rem;
    border-radius: 4px;
    overflow-y: auto;
    max-height: 15em;
    line-height: 1.4em;
    white-space: pre-wrap;
    word-break: break-word;
    cursor: default;
    transition: max-height 0.3s ease;
}

.small-box pre.expandable, .small-box-less-important pre.expandable {
    cursor: pointer;
}

.small-box pre.expanded, .small-box-less-important pre.expanded {
    max-height: none;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
    text-align: center;
}

.pagination-left,
.pagination-center,
.pagination-right {
    flex: 1;
}

.pagination-left,
.pagination-right {
    text-align: left;
}

.pagination-center {
    text-align: center;
}

.pagination-right {
    text-align: right;
}

.placeholder {
    visibility: hidden;
}

.page-info {
    color: var(--text-color);
    opacity: 0.7;
}

.alert-info {
    background-color: rgba(33, 150, 243, 0.1);
    border: 1px solid #2196f3;
    color: #2196f3;
    padding: 1rem;
    border-radius: 4px;
    text-align: center;
}

.alert-error {
    background-color: rgba(244, 67, 54, 0.1);
    border: 1px solid #f44336;
    color: #f44336;
    padding: 1rem;
    border-radius: 4px;
    text-align: center;
    margin-bottom: 1rem;
}

.FinishingBlockEditableFields {
    background-color: var(--bg-color);
    height: 15em;
    overflow-y: auto;   /* Enables vertical scrolling */
    overflow-x: hidden; /* Hides horizontal scrolling */
    resize: vertical;   /* Optional: allows user to resize vertically only */
    width: 100%;        /* Optional: makes the textarea take full width */
    box-sizing: border-box; /* Optional: ensures padding doesn't affect width */
    transition: height 0.2s ease;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.email-attachments {
    margin-top: 0rem;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.chat-attachment {
    position: relative;
    width: 100px;
    height: 100px;
    border-radius: 8px;
    background-color: var(--input-bg);
    border: 3px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

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

.chat-attachment img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}

.chat-attachment span {
    display: inline-block;
    font-size: 0.7rem;
    word-break: break-all;
    max-width: 100px;
}

#ContextContainer {
    margin-top: 2rem;
    padding-top: 1rem;
}

.view-selector {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

#email-switcher {
    max-width: 200px;
    flex: 0 1 200px;
}

.view-box {
    padding: 5px 10px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--input-bg);
    text-decoration: none;
    color: var(--link-color);
    opacity: 0.5;
}

.view-box.selected {
    opacity: 1;
}
</style>


































<script>
const TRAMANN_API_API_KEY = <?= json_encode($TRAMANNAPIAPIKey) ?>;

document.addEventListener('DOMContentLoaded', function () {
    let lastPayload = null;

    // Highlight READ emails on hover and selection
    const emailBlocks = document.querySelectorAll('.email-block');
    let selectedBlock = null;

    emailBlocks.forEach(block => {
        block.addEventListener('mouseenter', () => {
            if (selectedBlock && selectedBlock !== block && selectedBlock.style.opacity === '0.7') {
                selectedBlock.style.opacity = '0.5';
                selectedBlock = null;
            }
            if (block.style.opacity === '0.5') {
                block.style.opacity = '0.7';
            }
        });

        block.addEventListener('mouseleave', () => {
            if (block.style.opacity === '0.7' && selectedBlock !== block) {
                block.style.opacity = '0.5';
            }
        });

        block.addEventListener('click', (e) => {
            if (block.style.opacity === '0.5' || block.style.opacity === '0.7') {
                if (selectedBlock && selectedBlock !== block && selectedBlock.style.opacity === '0.7') {
                    selectedBlock.style.opacity = '0.5';
                }
                selectedBlock = block;
                block.style.opacity = '0.7';
                e.stopPropagation();
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (selectedBlock && !selectedBlock.contains(e.target)) {
            if (selectedBlock.style.opacity === '0.7') {
                selectedBlock.style.opacity = '0.5';
            }
            selectedBlock = null;
        }
    });

    document.querySelectorAll('.small-box pre, .small-box-less-important pre').forEach(pre => {
        if (pre.scrollHeight > pre.clientHeight) {
            pre.classList.add('expandable');
            pre.addEventListener('click', function () {
                this.classList.toggle('expanded');
            });
        }
    });





    document.querySelectorAll('.email-type').forEach(typeSpan => {
      typeSpan.style.cursor = 'pointer';
        
      typeSpan.addEventListener('click', () => {
        const emailBlock = typeSpan.closest('.email-block');
        const finishButton = emailBlock.querySelector('.finish-button');
        const uid = emailBlock.dataset.uid;
        
        const isUnread = typeSpan.textContent.trim().toLowerCase() === 'new';
        const targetState = isUnread ? 'read' : 'unread';
        
        fetch('AjaxEmailBot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ uid, targetState })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (targetState === 'read') {
              typeSpan.textContent = 'old';
              typeSpan.title = 'already read, click to mark as unread again';
              emailBlock.style.opacity = '0.5';
              if (finishButton) finishButton.style.opacity = '0.5';
            } else {
              typeSpan.textContent = 'new';
              typeSpan.title = 'unread yet, click to mark as read';
              emailBlock.style.opacity = '1';
              if (finishButton) finishButton.style.opacity = '1';
            }
          } else {
            alert('Failed to update email status');
          }
        })
        .catch(() => alert('Network error'));
      });
    });





    function sendToBrain(payload) {
      const responseTextarea = document.getElementById('response');
      const codeTextarea = document.getElementById('CodeForExecution');
      const codeBox = codeTextarea.closest('.small-box-less-important');

      // Start typing animation
      let typingInterval = null;
      if (responseTextarea) {
        let states = ['.', '. .', '. . .', '', '.', '. .'];
        let index = 0;
        responseTextarea.value = states[index];
        typingInterval = setInterval(() => {
          index = (index + 1) % states.length;
          responseTextarea.value = states[index];
        }, 400);
      }

      fetch('../BRAIN/main.php', {
        method: 'POST',
        body: payload
      })
        .then(response => response.json())
        .then(data => {
          if (typingInterval) {
            clearInterval(typingInterval);
            typingInterval = null;
          }

          if (data && Array.isArray(data) && data[0]) {
            const label = data[0].label?.toUpperCase();
            const baseLabel = label ? label.split('[')[0] : '';
            const responseTitle = document.querySelector('#response')?.closest('.small-box')?.querySelector('strong');

            const linkFinalFinish = document.getElementById("LinkFinalFinish");
            const linkBack = document.getElementById("LinkBack");

            if (baseLabel === 'CHATDB') {
              if (linkFinalFinish) {
                linkFinalFinish.dataset.originalText = linkFinalFinish.textContent;
                linkFinalFinish.textContent = '🪄 EXECUTE';
                linkFinalFinish.dataset.chatdb = "true";
              }
            }
              
            // Replace title and adjust textarea if label is SPAM or SUMMARY
            if (baseLabel === 'SPAM' || baseLabel === 'SUMMARY') {
              if (responseTitle) responseTitle.textContent = baseLabel.toLowerCase();
              if (responseTextarea) {
                responseTextarea.readOnly = true;
                responseTextarea.style.cursor = 'pointer';
              }
              if (codeTextarea) {
                codeTextarea.readOnly = true;
                codeTextarea.style.cursor = 'pointer';
              }
            }
          
            // Fill response textarea
            if (responseTextarea && data[0].message) {
              responseTextarea.value = data[0].message;
            }
          
            // Fill code textarea
            if (codeTextarea) {
              const code = data[0].code?.trim();
              if (code && code !== '' && code !== '0' && code !== 'null') {
                codeTextarea.value = code;
                if (codeBox) codeBox.style.display = 'block';
              } else {
                codeTextarea.value = '';
                if (codeBox) codeBox.style.display = 'none';
              }
            }
          }
        })
        .catch(error => {
          if (typingInterval) {
            clearInterval(typingInterval);
            typingInterval = null;
          }
          console.error('Error:', error);
        });
    }





    // Variables for main blocks and buttons
    const linkSubmitButtons = document.querySelectorAll("#LinkSubmit, .finish-button");
    const inboxContainer = document.querySelector(".inbox-container");
    const finishingBlock = document.getElementById("FinishingBlock");
    const linkBack = document.getElementById("LinkBack");
    const linkRetry = document.getElementById("LinkRetry");
    const linkFinalFinish = document.getElementById("LinkFinalFinish");

    // Store email info and attachments then redirect to index.php
    function storeEmailBotDataAndRedirect(emailBlock) {
      const body = emailBlock?.querySelector('pre')?.innerText || '';
      const subject = emailBlock?.querySelector('strong[title]')?.getAttribute('title') || '';
      const time = emailBlock?.querySelector('.email-time')?.getAttribute('title') || '';
      const senderFull = emailBlock?.querySelector('.small-box strong a')?.innerText || '';
      const senderMatch = senderFull.match(/^(.+?)\s*\((.+?)\)$/);
      const senderEmail = senderMatch ? senderMatch[1].trim() : senderFull.trim();
      const senderName = senderMatch ? senderMatch[2] : '';

      const to = senderEmail;
      const cc = emailBlock?.querySelector('span[title^="CC:"]')?.getAttribute('title')?.replace('CC: ', '') || '';
      const bcc = emailBlock?.querySelector('span[title^="BCC:"]')?.getAttribute('title')?.replace('BCC: ', '') || '';

      const infoParts = ['EMAIL'];
      if (senderName) infoParts.push(`RECIPIENTNAME: ${senderName}`);
      if (to) infoParts.push(`TO: ${to}`);
      if (cc) infoParts.push(`CC: ${cc}`);
      if (bcc) infoParts.push(`BCC: ${bcc}`);
      if (subject) infoParts.push(`SUBJECT: Re: ${subject}`);
      if (body) infoParts.push(`BODY (this is to what we wish to respond right now): ${body}`);
      if (time) infoParts.push(`DATE AND TIME: ${time}`);
      const infoText = infoParts.join(' | ');

      const attachments = [];
      emailBlock?.querySelectorAll('.email-attachments .chat-attachment').forEach(att => {
        const link = att.querySelector('a');
        if (link) {
          const dataURL = link.getAttribute('href');
          const name = att.getAttribute('title') || '';
          const mimeMatch = dataURL.match(/^data:(.*?);/);
          const mime = mimeMatch ? mimeMatch[1] : '';
          attachments.push({ dataURL, name, mime });
        }
      });

      try {
        localStorage.setItem('EmailBotData', JSON.stringify({
          botFlag: true,
          infoText,
          attachments
        }));
      } catch (err) {
        console.error('Failed to store EmailBot data', err);
      }

      const uid = emailBlock?.dataset.uid;
      if (uid) {
        fetch('AjaxEmailBot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ uid, targetState: 'read' })
        }).finally(() => {
          window.location.href = './index.php';
        });
      } else {
        window.location.href = './index.php';
      }
    }

    // Click handlers for all FINISH buttons
    linkSubmitButtons.forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const emailBlock = btn.closest('.email-block');
        storeEmailBotDataAndRedirect(emailBlock);
      });
    });

    // Back button: hide finishing, show inbox, remove context
    if (linkBack) {
      linkBack.addEventListener("click", function (e) {
        e.preventDefault();
        if (finishingBlock) finishingBlock.style.display = "none";
        if (inboxContainer) inboxContainer.style.display = "block";

        const contextualEmail = document.getElementById("ContextEmailBlock");
        if (contextualEmail) contextualEmail.remove();

        const contextContainer = document.getElementById("ContextContainer");
        if (contextContainer) contextContainer.style.display = "none";
      });
    }

    // Retry button: same effect as FINISH buttons (no email context)
    if (linkRetry) {
      linkRetry.addEventListener("click", function (e) {
        e.preventDefault();
        // Do not reset the context block, just resend the last payload
        if (lastPayload) {
          sendToBrain(lastPayload);
        } else {
          alert("We are very sorry, but there was no previous email data to retry.");
        }
      });
    }

    // Final finish reloads page
    // if (linkFinalFinish) {
    //   linkFinalFinish.addEventListener("click", function (e) {
    //     e.preventDefault();
// 
    //     const responseTitle = document.querySelector('#response')?.closest('.small-box')?.querySelector('strong');
    //     const label = responseTitle?.textContent?.trim().toUpperCase();
// 
    //     if (label === 'SPAM' || label === 'SUMMARY') {
    //       // Also mark the message as read before redirecting
    //       const emailBlock = document.getElementById("ContextEmailBlock");
    //       const uid = emailBlock?.dataset.uid;
// 
    //       if (uid) {
    //         fetch('AjaxEmailBot.php', {
    //           method: 'POST',
    //           headers: { 'Content-Type': 'application/json' },
    //           body: JSON.stringify({ uid, targetState: 'read' })
    //         })
    //         .finally(() => {
    //           window.location.href = './EmailBot.php';
    //         });
    //       } else {
    //         window.location.href = './EmailBot.php';
    //       }
    //     } else {
    //       // Later implement the sending logic here
    //       const emailBlock = document.getElementById("ContextEmailBlock");
    //       const uid = emailBlock?.dataset.uid;
// 
    //       if (uid) {
    //         fetch('AjaxEmailBot.php', {
    //           method: 'POST',
    //           headers: { 'Content-Type': 'application/json' },
    //           body: JSON.stringify({ uid, targetState: 'read' })
    //         })
    //         .finally(() => {
    //           window.location.href = './EmailBot.php';
    //         });
    //       } else {
    //         window.location.href = './EmailBot.php';
    //       }
    //     }
    //   });
    // }
    
    if (linkFinalFinish) {
      linkFinalFinish.addEventListener("click", function (e) {
        e.preventDefault();
      
        const label = document.querySelector('#response')?.closest('.small-box')?.querySelector('strong')?.textContent?.trim().toUpperCase();
        const baseLabel = label ? label.split('[')[0] : '';
        const isChatDB = linkFinalFinish.dataset.chatdb === "true";
      
        const emailBlock = document.getElementById("ContextEmailBlock");
        const uid = emailBlock?.dataset.uid;
      
        function openMailClientFromContext() {
          const subjectRaw = emailBlock?.querySelector('strong[title]')?.getAttribute('title') || 'Message';
          const subject = `Re: ${subjectRaw}`;
          const responseTextarea = document.getElementById('response');
          const body = responseTextarea?.value || '';
        
          const to = emailBlock?.querySelector('span[title^="TO:"]')?.getAttribute('title')?.replace('TO: ', '') || '';
          const cc = emailBlock?.querySelector('span[title^="CC:"]')?.getAttribute('title')?.replace('CC: ', '') || '';
          const bcc = emailBlock?.querySelector('span[title^="BCC:"]')?.getAttribute('title')?.replace('BCC: ', '') || '';
        
          let mailtoLink = `mailto:${encodeURIComponent(to)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
          if (cc) mailtoLink += `&cc=${encodeURIComponent(cc)}`;
          if (bcc) mailtoLink += `&bcc=${encodeURIComponent(bcc)}`;
        
          // window.location.href = mailtoLink;
          const mailData = parseMailto(mailtoLink);
          sendEmail(mailData);
        }
      
        if (isChatDB) {
          // === EXECUTE MODE ===
          const codeToSend = document.getElementById("CodeForExecution")?.value;
        
          // Change button text to EXECUTING or disable it to prevent double clicks (optional)
          linkFinalFinish.textContent = '⏳ EXECUTING...';
        
          fetch('../API/nexus.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              APIKey: TRAMANN_API_API_KEY,
              message: codeToSend
            })
          })
          .then(res => res.json())
          .then(nexusResponse => {
              let message = 'We are very sorry, but the processing of the code by the TRAMANN API failed, please try again.';
              if (Array.isArray(nexusResponse)) {
                const item = nexusResponse.find(r => r.label?.toUpperCase().split('[')[0] === 'CHATDB');
                if (item) message = item.message || message;
              } else if (nexusResponse.message) {
                message = nexusResponse.message;
              }
            
              const responseTextarea = document.getElementById('response');
              if (responseTextarea) {
                responseTextarea.value = message;
              }
            
              // Reset button text back to FINISH, remove chatdb flag
              if (linkFinalFinish.dataset.originalText) {
                linkFinalFinish.textContent = linkFinalFinish.dataset.originalText;
              } else {
                linkFinalFinish.textContent = '🪄 FINISH';
              }
              delete linkFinalFinish.dataset.chatdb;
            })
          .catch(err => {
            alert("Execution error: " + err.message);
            // Reset button text on error too
            if (linkFinalFinish.dataset.originalText) {
              linkFinalFinish.textContent = linkFinalFinish.dataset.originalText;
            } else {
              linkFinalFinish.textContent = '🪄 FINISH';
            }
            delete linkFinalFinish.dataset.chatdb;
          });
        
        } else {
          // === NOT CHATDB ===
          storeEmailBotDataAndRedirect(emailBlock);
        }
      });
    }





    const textareas = document.querySelectorAll('.FinishingBlockEditableFields');
    const originalHeights = new Map();

    textareas.forEach(textarea => {
      const originalHeight = getComputedStyle(textarea).height;
      originalHeights.set(textarea, originalHeight);

      textarea.addEventListener('click', function (e) {
        e.stopPropagation(); // Prevent this click from bubbling up

        // Collapse all other expanded textareas
        textareas.forEach(other => {
          if (other !== textarea) {
            other.style.height = originalHeights.get(other);
            other.classList.remove('expanded-textarea');
          }
        });

        // Toggle this one
        if (textarea.classList.contains('expanded-textarea')) {
          textarea.style.height = originalHeights.get(textarea);
          textarea.classList.remove('expanded-textarea');
        } else {
          textarea.style.height = `calc(${originalHeight} + 10em)`;
          textarea.classList.add('expanded-textarea');
        }
      });
    });

    // Collapse all when clicking outside of any textarea
    document.body.addEventListener('click', (event) => {
      // If the click is inside any .FinishingBlockEditableFields, do nothing
      if ([...textareas].some(textarea => textarea.contains(event.target))) {
        return;
      }

      // Otherwise collapse all
      textareas.forEach(textarea => {
        textarea.style.height = originalHeights.get(textarea);
        textarea.classList.remove('expanded-textarea');
      });
    });
});
</script>

<?php require_once('FooterEmail.php'); ?>
<?php require_once('footer.php'); ?>
