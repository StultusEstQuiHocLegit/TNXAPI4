<?php
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// initial setup
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
ob_start(); // Start output buffering
require_once('../config.php');
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get user ID from session
session_start();
$user_id = $_SESSION['user_id'] ?? null;

$TRAMANNAPIAPIKey = $_SESSION['TRAMANNAPIAPIKey'] ?? '';

if (!$user_id) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'ERROR: User not authenticated';
    exit;
}

// ===== CONFIGURATION =====
// Replace with your actual OpenAI API key or load it securely (e.g. from environment variables)
// API key is defined in config.php































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// main query
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function convertMessagesToInput(array $messages): array {
    return array_map(function ($m) {
        $content = $m['content'] ?? '';
        if (is_string($content)) {
            $content = [['type' => 'input_text', 'text' => $content]];
        } elseif (is_array($content)) {
            $content = array_map(function ($part) {
                if (($part['type'] ?? '') === 'text') {
                    $part['type'] = 'input_text';
                } elseif (($part['type'] ?? '') === 'image_url') {
                    $part['type'] = 'input_image';
                }
                return $part;
            }, $content);
        }
        return [
            'role' => $m['role'] ?? 'user',
            'content' => $content
        ];
    }, $messages);
}

function extractTextFromResponse(array $response): string {
    $text = '';
    foreach ($response['output'][0]['content'] ?? [] as $part) {
        if (($part['type'] ?? '') === 'output_text') {
            $text .= $part['text'];
        }
    }
    return trim($text);
}

function runOpenAICall($payload, $apiKey) {
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('OpenAI API call failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        throw new Exception("OpenAI API returned status $status: $response");
    }

    return json_decode($response, true);
}





// Get raw POST data and decode JSON (assuming Content-Type: application/json)
$input = json_decode(file_get_contents('php://input'), true);

// Extract variables safely
$cmdList = $input['cmd'] ?? null; // Expecting array of objects {label, message}
$pastWorkflowId = $input['pastWorkflowId'] ?? null;

if (!is_array($cmdList) || count($cmdList) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid command list provided']);
    exit;
}

// Optionally: combine all messages into one string for your AI or DB logic
$combinedCmdText = '';
foreach ($cmdList as $cmdItem) {
    if (isset($cmdItem['label'], $cmdItem['message'])) {
        $combinedCmdText .= "[{$cmdItem['label']}] {$cmdItem['message']}\n";
    }
}

// Now you can use $combinedCmdText instead of $cmd string in your logic

// Your DB logic stays mostly the same
$workflowEntries = [];
$children = [];
$past = null;

try {
    if ($pastWorkflowId) {
        // Fetch past workflow and children (same as before)
        $stmt = $pdo->prepare("SELECT * FROM workflows WHERE idpk = ? AND IdpkOfAdmin = ? LIMIT 1");
        $stmt->execute([$pastWorkflowId, $user_id]);
        $past = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($past) $workflowEntries[] = $past;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

if ($past && $combinedCmdText !== '') {
    $messages = [
        ['role' => 'system', 'content' => 'You are a validation agent that evaluates whether a given response answers the original question well. Please jsut answer in plain text, nothing else, briefly and in the language of the user.'],
        ['role' => 'user', 'content' => "Question: {$past['WhatToDo']}\n\nAnswer: $combinedCmdText\n\nDoes this answer the question well? Reply with just YES or NO, nothing else, no other output but these exact two words, YES or NO."]
    ];

    $payload = [
        'model' => 'gpt-4.1-mini',
        // 'model' => 'gpt-4.1', // smarter
        'input' => convertMessagesToInput($messages),
        'temperature' => 0.2,
        'max_output_tokens' => 500,
    ];

    $result = runOpenAICall($payload, $apiKey);
    $aiReply = strtolower(extractTextFromResponse($result));

    if (str_starts_with($aiReply, 'NO')) {
        $responses[] = [
            'label' => 'CHAT',
            'message' => "Let's retry the previous task, it seems the response wasn't complete:\n\n" . ($past['WhatToDo'] ?? '')
        ];
    } else {
        $next = $children[0] ?? null;
        if ($next) {
            $responses[] = [
                'label' => 'CHAT',
                'message' => "Next step: {$next['WhatToDo']}",
            ];
        } else {
            $responses[] = [
                'label' => 'CHAT',
                // 'message' => "Workflow completed, no more child workflows found."
            ];
        }
    }
} else {
    // If no evaluation, just echo combined command text or last command
    $responses[] = [
        'label' => 'CHAT',
        'message' => "We are very sorry, but there was an error in processing the workflow. Please try again."
    ];
}







































// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// responding
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
ob_clean(); // clean all previous warnings or errors
// Send JSON
header('Content-Type: application/json');
echo json_encode($responses, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
exit;

ob_end_flush(); // Flush the output buffer
?>
