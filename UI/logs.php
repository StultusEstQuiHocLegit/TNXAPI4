<?php
require_once('../config.php');
require_once('header.php');

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

// Handle rollback action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rollback_sql'])) {
    $rollbackSql = $_POST['rollback_sql'];
    $isAdmin = $_SESSION['IsAdmin'] ?? 0;
    $userId = $_SESSION['user_id'] ?? 0;
    $adminId = $_SESSION['IdpkOfAdmin'] ?? 0;

    try {
        // Execute the rollback SQL
        $stmt = $pdo->prepare($rollbackSql);
        $stmt->execute();

        // Get the original SQL that was executed
        $originalSqlQuery = "SELECT ExecutedSQL FROM logs WHERE RollbackSQL = :rollbackSql LIMIT 1";
        $originalSql = $originalSqlStmt->fetchColumn();

        // Log the rollback action
        $logSql = "
            INSERT INTO logs
                (TimestampCreation, IdpkOfCreator, IsAdmin, IdpkOfAdmin, ExecutedSQL, RollbackSQL)
            VALUES
                (CURRENT_TIMESTAMP, :creatorId, :isAdmin, :adminId, :executedSQL, :rollbackSQL)
        ";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->bindParam(':creatorId', $userId, PDO::PARAM_INT);
        $logStmt->bindParam(':isAdmin', $isAdmin, PDO::PARAM_BOOL);
        $logStmt->bindParam(':adminId', $adminId, PDO::PARAM_INT);
        $logStmt->bindParam(':executedSQL', $rollbackSql, PDO::PARAM_STR);
        $logStmt->bindParam(':rollbackSQL', $originalSql, PDO::PARAM_STR);
        $logStmt->execute();

        // Use JavaScript to redirect instead of PHP header
        echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    } catch (PDOException $e) {
        $error = "Error executing rollback: " . $e->getMessage();
    }
}

// Get current page number from URL parameter, default to 1
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$logsPerPage = 100;
$offset = ($page - 1) * $logsPerPage;

// Get user's admin status and ID
$isAdmin = $_SESSION['IsAdmin'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;
$adminId = $_SESSION['IdpkOfAdmin'] ?? 0;

// Prepare the query based on user permissions
$query = "
    SELECT 
        l.*,
        DATE_FORMAT(l.TimestampCreation, '%Y-%m-%d %H:%i:%s') as formatted_time
    FROM logs l
    WHERE " . ($isAdmin ? "l.IdpkOfAdmin = :adminId" : "l.IdpkOfCreator = :userId") . "
    ORDER BY l.TimestampCreation DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
if ($isAdmin) {
    $stmt->bindParam(':adminId', $adminId, PDO::PARAM_INT);
} else {
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
}
$stmt->bindParam(':limit', $logsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM logs
    WHERE " . ($isAdmin ? "IdpkOfAdmin = :adminId" : "IdpkOfCreator = :userId")
;
$countStmt = $pdo->prepare($countQuery);
if ($isAdmin) {
    $countStmt->bindParam(':adminId', $adminId, PDO::PARAM_INT);
} else {
    $countStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
}
$countStmt->execute();
$totalLogs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalLogs / $logsPerPage);
?>

<div class="containerWithoutBorder" style="max-width: 500px; margin: auto;">
    <h1 class="text-center">📜 LOG FILES</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="logs-container">
        <?php if (empty($logs)): ?>
            <div class="alert alert-info">No logs found.</div>
        <?php else: ?>
            <div class="logs-list">
                <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <?php $isDbAction = preg_match('/\$pdo/i', $log['ExecutedSQL'] . ($log['RollbackSQL'] ?? '')); ?>
                        <div class="log-header">
                            <span class="log-time" title="<?php echo htmlspecialchars(getRelativeTitle($log['formatted_time'])); ?>">
                                <?php
                                echo htmlspecialchars($log['formatted_time']);
                                echo ' ' . getRelativeTime($log['formatted_time']);
                                ?>
                            </span>
                            <div class="log-actions">
                                <?php if ($isDbAction): ?>
                                    <span class="log-type" title="database action was performed">DB</span>
                                <?php endif; ?>
                                <span class="log-type" title="rights of performing account"><?php echo $log['IsAdmin'] ? 'admin' : 'user'; ?></span>
                                <?php if ($log['RollbackSQL']): ?>
                                    <form method="POST" class="undo-form" onsubmit="return confirm('Are you sure you want to undo this action?');">
                                        <input type="hidden" name="rollback_sql" value="<?php echo htmlspecialchars($log['RollbackSQL']); ?>">
                                        <button type="submit" class="undo-button">⏮ UNDO</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="log-content">
                            <div class="log-sql">
                                <strong>executed</strong>
                                <pre><?php echo highlightSQL(htmlspecialchars($log['ExecutedSQL'])); ?></pre>
                            </div>
                            <?php if ($log['RollbackSQL']): ?>
                                <div class="log-rollback" style="opacity: 0.5;">
                                    <strong>rollback</strong>
                                    <pre><?php echo highlightSQL(htmlspecialchars($log['RollbackSQL'])); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-left">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">◀️ page <?php echo $page - 1; ?></a>
                        <?php else: ?>
                            <span class="placeholder"></span>
                        <?php endif; ?>
                    </div>
                        
                    <div class="pagination-center">
                        <span class="page-info">page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    </div>
                        
                    <div class="pagination-right">
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">▶️ page <?php echo $page + 1; ?></a>
                        <?php else: ?>
                            <span class="placeholder"></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.sql-highlight-keyword {
    color: var(--accent-color) !important;
    font-weight: bold;
}

.sql-highlight-table {
    font-weight: bold;
}

.logs-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

.log-entry {
    background-color: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 1rem;
    padding: 1rem;
    box-shadow: 0 2px 4px var(--shadow-color);
}

.log-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
}

.log-time {
    color: var(--text-color);
    opacity: 0.7;
}

.log-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.undo-button:hover {
    opacity: 0.8;
}

.log-type {
    background-color: var(--border-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.5rem;
}

.log-content {
    font-family: monospace;
    font-size: 0.9rem;
}

.log-sql, .log-rollback {
    margin-bottom: 1rem;
}

.log-sql pre, .log-rollback pre {
    background-color: var(--bg-color);
    padding: 0.5rem;
    border-radius: 4px;
    overflow-y: auto;
    max-height: 7em;
    line-height: 1.4em;
    white-space: pre-wrap;
    word-break: break-word;
    cursor: pointer;
    transition: max-height 0.3s ease;
}

.log-sql pre.expanded, .log-rollback pre.expanded {
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
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.log-sql pre, .log-rollback pre').forEach(pre => {
        pre.addEventListener('click', function () {
            this.classList.toggle('expanded');
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
