<?php
/**
 * GET api/patient/list.php
 * Lists patients with pagination, search by name, and strict office scopes.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force active session verification
$auth->requireAuth();

// 1. Pagination Config parameters matching your template rules
$limit  = max((int)($_GET['limit'] ?? 20), 1);
$page   = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 2. Extract inputs from client request payload (Aligned directly with assets/js/patients.js)
$searchName = trim($_GET['search'] ?? '');
$requestedOffice = trim($_GET['clinic_id'] ?? ''); // Aligned key to catch 'clinic_id' data from jQuery
$currentUserId = $_SESSION['user_id'] ?? null;

if (!$currentUserId) {
    Api::error('User session context not found.', 401);
    exit;
}

try {
    // 3. Find all valid offices assigned to this logged-in user to prevent data leaks
    $assignedOfficesSql = "SELECT office_id FROM `office_users` WHERE user_id = ?";
    $assignedRows = $db->query($assignedOfficesSql, [$currentUserId]);
    $allowedOfficeIds = array_map(function($row) { return (int)($row['office_id'] ?? $row['id']); }, $assignedRows);

    if (empty($allowedOfficeIds)) {
        // User belongs to no offices, immediately return empty set safely
        Api::success([
            "records" => [],
            "meta" => [
                "offset" => $offset,
                "display" => "0-0 of 0",
                "total" => 0,
                "limit" => $limit,
                "pages" => 1,
                "current" => $page
            ]
        ]);
        exit;
    }

    // 4. Determine target office restriction filter arrays
    $targetOfficeIds = [];

    if ($requestedOffice === 'all' || $requestedOffice === '') {
        // Target all offices that the user has permission to access (Matches frontend blank option filter)
        $targetOfficeIds = $allowedOfficeIds;
    } elseif ((int)$requestedOffice > 0) {
        // Target a single specific office, but verify the user actually belongs to it
        $targetId = (int)$requestedOffice;
        if (in_array($targetId, $allowedOfficeIds)) {
            $targetOfficeIds = [$targetId];
        } else {
            Api::error('Access Denied to requested office location.', 403);
            exit;
        }
    } else {
        // Default Fallback: Use current active selection from user's active session
        $sessionOfficeId = (int)($_SESSION['office_id'] ?? 0);
        if ($sessionOfficeId > 0 && in_array($sessionOfficeId, $allowedOfficeIds)) {
            $targetOfficeIds = [$sessionOfficeId];
        } else {
            // Fall back to their first allowed office if session is unset/stale
            $targetOfficeIds = [$allowedOfficeIds[0]];
        }
    }

    // 5. Build Dynamic SQL components
    $whereClauses = [];
    $queryParams  = [];

    // Filter by target offices (Using safe PDO placeholders for the array)
    $placeholders = implode(',', array_fill(0, count($targetOfficeIds), '?'));
    $whereClauses[] = "p.office_id IN ($placeholders)";
    foreach ($targetOfficeIds as $id) {
        $queryParams[] = $id;
    }

    // Filter by text search matching patient names
    if ($searchName !== '') {
        $whereClauses[] = "p.name LIKE ?";
        $queryParams[]  = '%' . $searchName . '%';
    }

    // Combine clauses
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);

    // 6. Execute Count Query using combined criteria
    $countSql = "SELECT COUNT(*) as total FROM `patient` p $whereSql";
    $countRow = $db->queryOne($countSql, $queryParams);
    $totalCount = (int)($countRow['total'] ?? 0);

    // 7. Execute Records Query with pagination limits
    // JOINing offices table ensures your patients.js has access to 'office_name'
    $recordsSql = "SELECT 
                        p.id, 
                        p.name, 
                        p.dob, 
                        p.mobile, 
                        p.mobile AS phone, -- Fallback mapping to handle JS variable structure safely
                        p.email, 
                        p.address, 
                        p.office_id,
                        o.office_name
                   FROM `patient` p
                   INNER JOIN `offices` o ON p.office_id = o.id
                   $whereSql
                   ORDER BY p.id DESC
                   LIMIT ? OFFSET ?";

    // Add pagination integers to parameter array list binding structures
    $queryParams[] = $limit;
    $queryParams[] = $offset;

    // Use queryAll or standard multi-row query collection execution wrapper
    $records = $db->query($recordsSql, $queryParams);

    // 8. Prepare Metadata Output structural configurations matching your js script expectations
    $rangeStart = $totalCount ? ($offset + 1) : 0;
    $rangeEnd   = min($offset + $limit, $totalCount);
    $totalPages = ceil($totalCount / $limit);

    $responseData = [
        "records" => $records,
        "meta" => [
            "offset"      => (int)$offset,
            "display"     => "$rangeStart-$rangeEnd of $totalCount",
            "total"       => (int)$totalCount,
            "limit"       => $limit,
            "pages"       => (int)$totalPages,
            "current"     => $page
        ]
    ];

    Api::success($responseData, 'Patients list retrieved successfully.');

} catch (Exception $e) {
    Api::error('Database retrieval failed: ' . $e->getMessage());
}