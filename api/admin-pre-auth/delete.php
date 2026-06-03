<?php
/**
 * API: Delete Itemized Pre-Auth Line & Automatic Orphaned Case Cleanup
 * Path: /api/admin-pre-auth/delete.php
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// 1. Administrative Scope Guard
if (!$auth->hasRole('admin') && !$auth->hasRole('management')) {
    Api::error('Unauthorized administrative access credential clear failure.', 403);
    exit;
}

if (Api::method() !== 'POST') { 
    Api::error('Method not allowed.', 405); 
    exit; 
}

// 2. Capture and Validate Target Pre-Auth Row ID
$preAuthId = (int)($_POST['id'] ?? 0);

if ($preAuthId <= 0) {
    Api::error('Invalid request parameters. Target item identification key is required.');
    exit;
}

try {
    // 3. Fetch the record first to capture its parent case_id reference context
    $sqlFetch = "SELECT id, case_id FROM `pre-auth` WHERE id = ? LIMIT 1";
    $targetRecord = $db->queryOne($sqlFetch, [$preAuthId]);

    if (!$targetRecord) {
        Api::error('Target pre-authorization record row not found.', 404);
        exit;
    }

    $caseId = (int)$targetRecord['case_id'];

    // Start Transaction block to guarantee cascading calculation atomicity
    $db->beginTransaction();

    // 4. Remove the individual target itemized row
    $db->query("DELETE FROM `pre-auth` WHERE id = ? LIMIT 1", [$preAuthId]);

    $messageAppendix = "";

    // 5. Check if the parent Case Envelope is now completely empty
    if ($caseId > 0) {
        $sqlCheckRemaining = "SELECT COUNT(*) as active_count FROM `pre-auth` WHERE case_id = ?";
        $remainingResult = $db->queryOne($sqlCheckRemaining, [$caseId]);
        $remainingCount = (int)($remainingResult['active_count'] ?? 0);

        // If no more sibling item lines remain under this case envelope, purge the envelope too
        if ($remainingCount === 0) {
            $db->query("DELETE FROM `pre_auth_cases` WHERE id = ? LIMIT 1", [$caseId]);
            $messageAppendix = " Additionally, empty parent case container account was safely dismantled.";
        } else {
            $messageAppendix = " ({$remainingCount} itemized line item(s) remain attached to master case timeline).";
        }
    }

    // Safely write ledger changes to storage system permanently
    $db->commit();

    // 6. Return Operational Success Status Meta
    Api::success([
        'deleted_pre_auth_id' => $preAuthId,
        'parent_case_id'      => $caseId,
        'case_purged'         => ($remainingCount === 0)
    ], "Pre-Authorization record has been permanently removed." . $messageAppendix);

} catch (Exception $e) {
    // Instantly roll back database changes on exceptions to defend database structural integrity
    $db->rollBack();
    Api::error('Global admin database management tier pipeline runtime error: ' . $e->getMessage(), 500);
}