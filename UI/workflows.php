<?php
require_once('../config.php');
require_once('header.php');

$error = '';
$success = '';

// Handle form submissions for workflows
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_workflow']) || isset($_POST['edit_workflow'])) {
        // Sanitize inputs
        $IdpkOfAdmin = $_SESSION['user_id']; // assuming admin is current logged in user
        $IdpkOfCreator = $_SESSION['user_id']; // example, can be different
        $IdpkUpstreamWorkflow = filter_input(INPUT_POST, 'IdpkUpstreamWorkflow', FILTER_VALIDATE_INT);
        if ($IdpkUpstreamWorkflow === false) {
            $IdpkUpstreamWorkflow = null;
        }

        $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $emoji = htmlspecialchars($_POST['emoji'] ?? '', ENT_QUOTES, 'UTF-8');
        $ActionType = htmlspecialchars($_POST['ActionType'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $WhatToDo = $_POST['WhatToDo'] ?? ''; // allow rich text or plain text
        $SelectOneOfTheDownstreamWorkflows = isset($_POST['SelectOneOfTheDownstreamWorkflows']) ? 1 : 0;

        // Check required fields
        if ($name) {
            if (isset($_POST['add_workflow'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO workflows (
                        IdpkOfAdmin,
                        IdpkOfCreator,
                        IdpkUpstreamWorkflow,
                        name,
                        emoji,
                        ActionType,
                        description,
                        WhatToDo,
                        SelectOneOfTheDownstreamWorkflows,
                        TimestampCreation
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                    )
                ");

                $success = null;
                $error = null;

                if ($stmt->execute([
                    $IdpkOfAdmin,
                    $IdpkOfCreator,
                    $IdpkUpstreamWorkflow,
                    $name,
                    $emoji,
                    $ActionType,
                    $description,
                    $WhatToDo,
                    $SelectOneOfTheDownstreamWorkflows
                ])) {
                    $success = 'Workflow added successfully!';
                } else {
                    $error = 'Error adding workflow. Please try again.';
                }
            } else {
                // Edit workflow
                $workflowId = filter_input(INPUT_POST, 'workflow_id', FILTER_VALIDATE_INT);
                if (!$workflowId) {
                    $error = 'Invalid workflow ID.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE workflows SET
                            IdpkOfAdmin = ?,
                            IdpkOfCreator = ?,
                            IdpkUpstreamWorkflow = ?,
                            name = ?,
                            emoji = ?,
                            ActionType = ?,
                            description = ?,
                            WhatToDo = ?,
                            SelectOneOfTheDownstreamWorkflows = ?
                        WHERE idpk = ? AND IdpkOfAdmin = ?
                    ");

                    if ($stmt->execute([
                        $IdpkOfAdmin,
                        $IdpkOfCreator,
                        $IdpkUpstreamWorkflow,
                        $name,
                        $emoji,
                        $ActionType,
                        $description,
                        $WhatToDo,
                        $SelectOneOfTheDownstreamWorkflows,
                        $workflowId,
                        $IdpkOfAdmin
                    ])) {
                        $success = 'Workflow updated successfully!';
                    } else {
                        $error = 'Error updating workflow. Please try again.';
                    }
                }
            }
        } else {
            $error = 'Please fill in the workflow name.';
        }
    }

    if (isset($_POST['remove_workflow'])) {
        $workflowId = filter_input(INPUT_POST, 'workflow_id', FILTER_VALIDATE_INT);
        $IdpkOfAdmin = $_SESSION['user_id'];

        if ($workflowId) {
            $stmt = $pdo->prepare("DELETE FROM workflows WHERE idpk = ? AND IdpkOfAdmin = ?");
            if ($stmt->execute([$workflowId, $IdpkOfAdmin])) {
                $success = 'Workflow removed successfully!';
            } else {
                $error = 'Error removing workflow.';
            }
        } else {
            $error = 'Invalid workflow idpk for removal.';
        }
    }
}

// Fetch all workflows for the current admin
$stmt = $pdo->prepare("
    SELECT * FROM workflows
    WHERE IdpkOfAdmin = ?
    ORDER BY name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildTree($workflows, $parentId = null) {
    $tree = [];
    foreach ($workflows as $workflow) {
        if ($workflow['IdpkUpstreamWorkflow'] == $parentId) {
            $children = buildTree($workflows, $workflow['idpk']);
            $tree[] = [
                'data' => $workflow,
                'children' => $children
            ];
        }
    }
    return $tree;
}

$workflowTree = buildTree($workflows);
?>

<div class="container" style="max-width: 500px; margin: auto; text-align: center;">
    <h1 class="text-center">⚡ WORKFLOWS</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div id="menuNav" class="menu-nav">
        <!-- Add new workflow button -->
        <a href="#" class="menu-item" style="border: 3px solid var(--primary-color);" onclick="showAddWorkflowForm()" title="Add a new workflow">
            <div style="font-size: 2.5rem;">➕</div>
            <span class="menu-title">ADD WORKFLOW</span>
        </a>

        <!-- Display existing workflows -->
        <?php
            function renderWorkflowNode($node, $depth = 0) {
                if (!$node['data']) return;
            
                ?>
                <a href="#" class="menu-item" 
                   style="display: block; position: relative; <?php echo $depth > 0 ? 'opacity: 0.7;' : ''; ?>" 
                   onclick="showEditWorkflowForm(<?php echo htmlspecialchars(json_encode($node['data'])); ?>)" 
                   title="<?php echo htmlspecialchars($node['data']['name']) . ' (' . htmlspecialchars($node['data']['idpk']) . ') | ' . htmlspecialchars($node['data']['description']); ?>">
                    <div style="font-size: 2.5rem;"><?php echo htmlspecialchars($node['data']['emoji']); ?></div>
                    <span class="menu-title">
                        <?php echo htmlspecialchars($node['data']['name']) . ' (' . htmlspecialchars($node['data']['idpk']) . ')'; ?>
                    </span>
                    <?php if ($depth != 0): ?>
                        <span title="the depth level of this workflow is <?php echo -$depth; ?>" style="position: absolute; top: 4px; right: 8px; font-size: 0.9rem; opacity: 0.5;">
                            <?php echo -$depth; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php
                foreach ($node['children'] as $child) {
                    renderWorkflowNode($child, $depth + 1);
                }
            }
        ?>

        
        <?php foreach ($workflowTree as $entry): ?>
            <?php renderWorkflowNode($entry); ?>
        <?php endforeach; ?>
    </div>

    <style>
        .form-group label {
            text-align: left;
        }
    </style>

    <!-- Add/Edit Workflow Form (hidden by default) -->
    <div id="workflowForm" style="display: none; max-width: 500px; margin: 2rem auto;">
        <form method="POST" action="" id="workflowFormElement">
            <input type="hidden" name="workflow_id" id="workflowId">

            <div id="workflowIdDisplay" style="opacity: 0.5; display: none; margin-bottom: 0.5rem;"></div>

            <div class="form-group">
                <label for="IdpkUpstreamWorkflow">upstream workflow idpk (optional)</label>
                <input type="number" id="IdpkUpstreamWorkflow" name="IdpkUpstreamWorkflow" min="1" placeholder="leave blank if you are a rebel">
            </div>

            <div class="form-group">
                <label for="name">name</label>
                <input type="text" id="name" name="name" placeholder="seize world domination" required>
            </div>

            <div class="form-group">
                <label for="emoji">emoji</label>
                <input type="text" id="emoji" name="emoji" placeholder="👑" required>
            </div>

<!--
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// //////////////////////////////////////// IMPORTANT NOTE: the following action types also appear in main.php, changes here have to also been made there
// //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// it was hidden to be deactived as we are now following a new BRAIN AI approach
-->

            <div class="form-group" style="display:none;">
                <label for="ActionType">action type</label>
                <select id="ActionType" name="ActionType">
                    <option value="">multiple or undefined</option>
                    <option value="math">math</option>
                    <option value="location">location</option>
                    <option value="chart">chart</option>
                    <option value="table">table</option>
                    <option value="code">code</option>
                    <option value="email">email</option>
                    <option value="pdf">pdf</option>
                    <option value="pdfinvoic">pdfinvoic</option>
                    <option value="pdfoffer">pdfoffer</option>
                    <option value="pdfdeliveryreceipt">pdfdeliveryreceipt</option>
                    <option value="pdfreport">pdfreport</option>
                    <option value="pdfcontract">pdfcontract</option>
                    <option value="pdflegaldocument">pdflegaldocument</option>
                    <option value="databasesselect">databasesselect</option>
                    <option value="databasesinsertinto">databasesinsertinto</option>
                    <option value="databasesupdate">databasesupdate</option>
                    <option value="databasesdelete">databasesdelete</option>
                    <option value="databasessearch">databasessearch</option>
                    <option value="databasesgetcontext">databasesgetcontext</option>
                    <option value="databasesattachmenthandling">databasesattachmenthandling</option>
                    <option value="buying">buying</option>
                    <option value="selling">selling</option>
                    <option value="marketingtexting">marketingtexting</option>
                    <option value="chat">chat</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">description</label>
                <input type="text" id="description" name="description" placeholder="top secret mission to take over the world" required>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="SelectOneOfTheDownstreamWorkflows" name="SelectOneOfTheDownstreamWorkflows">
                    check to just select one of the downstream workflows
                </label>
            </div>

            <div class="form-group">
                <label for="WhatToDo">what to do</label>
                <textarea id="WhatToDo" name="WhatToDo" rows="5" placeholder="hack yourself into the military database, get all the codes required, then launch the missiles"></textarea>
            </div>

            <button type="submit" name="add_workflow" id="submitButton">↗️ ADD WORKFLOW</button>

            <br><br><br><br><br>
            <button type="button" onclick="hideWorkflowForm()" style="opacity: 0.2;">✖️ CANCEL</button>
        </form>

        <br><br><br><br><br><br><br><br><br><br>
        <div id="removeButton" style="opacity: 0.2; text-align: center;"><a href="#">❌ REMOVE</a></div>
    </div>
</div>

<?php require_once('footer.php'); ?>





<script>
    function toggleWhatToDoField() {
        const checkbox = document.getElementById("SelectOneOfTheDownstreamWorkflows");
        const whatToDoField = document.getElementById("WhatToDo").closest(".form-group");
        if (!checkbox || !whatToDoField) return;
        
        if (checkbox.checked) {
            whatToDoField.style.display = "none";
        } else {
            whatToDoField.style.display = "";
        }
    }

    function showAddWorkflowForm() {
        document.getElementById('workflowIdDisplay').style.display = 'none';
        document.getElementById('workflowIdDisplay').textContent = '';
        document.getElementById('menuNav').style.display = 'none';
        document.getElementById('workflowForm').style.display = 'block';
        document.getElementById('workflowId').value = '';
        document.getElementById('workflowFormElement').reset();
        document.getElementById('submitButton').name = 'add_workflow';
        document.getElementById('submitButton').textContent = '↗️ ADD WORKFLOW';

        toggleWhatToDoField();
    }

    function showEditWorkflowForm(workflow) {
        document.getElementById('workflowIdDisplay').style.display = 'block';
        document.getElementById('workflowIdDisplay').textContent = `(idpk: ${workflow.idpk})`;

        document.getElementById('menuNav').style.display = 'none';
        document.getElementById('workflowForm').style.display = 'block';

        document.getElementById('workflowId').value = workflow.idpk || '';
        document.getElementById('name').value = workflow.name || '';
        document.getElementById('emoji').value = workflow.emoji || '';
        document.getElementById('ActionType').value = workflow.ActionType || '';
        document.getElementById('description').value = workflow.description || '';
        document.getElementById('WhatToDo').value = workflow.WhatToDo || '';
        document.getElementById('SelectOneOfTheDownstreamWorkflows').checked = (workflow.SelectOneOfTheDownstreamWorkflows == 1);
        document.getElementById('IdpkUpstreamWorkflow').value = workflow.IdpkUpstreamWorkflow || '';

        document.getElementById('submitButton').name = 'edit_workflow';
        document.getElementById('submitButton').textContent = '↗️ UPDATE WORKFLOW';

        toggleWhatToDoField();
    }

    function hideWorkflowForm() {
        document.getElementById('workflowForm').style.display = 'none';
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
        const workflowId = document.getElementById('workflowId').value;
        if (!workflowId) {
            alert('Please select a workflow to remove first.');
            return;
        }
        if (confirm('Are you sure you want to remove this workflow?')) {
            // Change form submit button to remove
            const form = document.getElementById('workflowFormElement');

            // Add a hidden input to indicate remove action
            let removeInput = document.getElementById('removeInput');
            if (!removeInput) {
                removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_workflow';
                removeInput.id = 'removeInput';
                form.appendChild(removeInput);
            }

            // Remove add/edit submit button name so it won't trigger those
            document.getElementById('submitButton').name = '';

            // Submit form to trigger PHP removal
            form.submit();
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        const checkbox = document.getElementById("SelectOneOfTheDownstreamWorkflows");
        if (checkbox) {
            checkbox.addEventListener("change", toggleWhatToDoField);
        }
    });
</script>
