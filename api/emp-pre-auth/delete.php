<?php
/**
 * API: Delete Itemized Pre-Auth Line & Automatic Orphaned Case Cleanup (Employee Scope)
 * Path: /api/emp-pre-auth/delete.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Employee Scope Role Guard
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor')) {
    Api::error('Unauthorized access template credential clearance failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate Session Workspace Context
$preAuthId       = (int)($_POST['id'] ?? 0);
$sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);

if ($preAuthId <= 0) {
    Api::error('Invalid request parameters. Target item identification key is required.');
    exit;
}

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established in session.', 400);
    exit;
}

try {
    // 3. Fetch record to capture parent case context AND verify clinic ownership scope boundaries
    // We join pre_auth_cases to safely ensure this pre-auth child row belongs to the employee's active clinic session
    $sqlFetch = "SELECT pa.id, pa.status, pa.case_id 
                 FROM `pre-auth` pa
                 INNER JOIN `pre_auth_cases` pac ON pa.case_id = pac.id
                 WHERE pa.id = ? AND pac.office_id = ? LIMIT 1";
                 
    $targetRecord = $db->queryOne($sqlFetch, [$preAuthId, $sessionOfficeId]);

    if (!$targetRecord) {
        Api::error('Target pre-authorization record not found or workspace access validation denied.', 404);
        exit;
    }

    // 4. STRICT WORKFLOW STATUS SECURE CHECK for Employees
    // Staff/Doctors are only permitted to delete rows if the operational tracking status is still raw/unchanged ('Requested')
    if (strtolower($targetRecord['status']) !== 'requested') {
        Api::error('Cannot delete a record that has already advanced to the operational state: ' . $targetRecord['status'] . '.', 400);
        exit;
    }

    $caseId = (int)$targetRecord['case_id'];

    // Start ACID-compliant transaction block to guarantee cascade validation atomicity
    $db->beginTransaction();

    // 5. Remove the individual target itemized row safely
    $db->query("DELETE FROM `pre-auth` WHERE id = ? LIMIT 1", [$preAuthId]);

    $messageAppendix = "";
    $remainingCount = 0;

    // 6. Sibling Evaluation: Check if the parent Case Envelope is now completely empty
    if ($caseId > 0) {
        $sqlCheckRemaining = "SELECT COUNT(*) as active_count FROM `pre-auth` WHERE case_id = ?";
        $remainingResult = $db->queryOne($sqlCheckRemaining, [$caseId]);
        $remainingCount = (int)($remainingResult['active_count'] ?? 0);

        // If no more procedural rows remain under this case folder container, dismantle the container envelope
        if ($remainingCount === 0) {
            $db->query("DELETE FROM `pre_auth_cases` WHERE id = ? AND office_id = ? LIMIT 1", [$caseId, $sessionOfficeId]);
            $messageAppendix = " Additionally, empty parent case container was safely dismantled.";
        } else {
            $messageAppendix = " ({$remainingCount} itemized procedure lines remain attached to case portfolio timeline).";
        }
    }

    // Safely write changes to the ledger layers permanently
    $db->commit();

    // 7. Return Operational Success Payload Matrix
    Api::success([
        'deleted_pre_auth_id' => $preAuthId,
        'parent_case_id'      => $caseId,
        'case_purged'         => ($remainingCount === 0)
    ], "Pre-Authorization record line successfully deleted." . $messageAppendix);

} catch (Exception $e) {
    // Instantly restore previous state settings on errors to protect database relational reference paths
    $db->rollBack();
    Api::error('Database transactional processing failure: ' . $e->getMessage(), 500);
}