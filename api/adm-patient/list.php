<?php
/**
 * GET api/adm-patient/list.php
 * Administrative global patient directory retrieval with pagination and search routing filters.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);

// Force active administrative session verification
$auth->requireAuth();

// 1. Pagination Config parameters matching your template rules
$limit  = max((int)($_GET['limit'] ?? 20), 1);
$page   = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

// 2. Extract inputs from client request payload (Aligned directly with assets/js/admin-patients.js)
$searchName = trim($_GET['search'] ?? '');
$requestedOffice = trim($_GET['clinic_id'] ?? ''); // Selected office filter variable
$currentUserId = $_SESSION['user_id'] ?? null;

if (!$currentUserId) {
    Api::error('User session context not found.', 401);
    exit;
}

try {
    // 3. Build Dynamic SQL components
    $whereClauses = [];
    $queryParams  = [];

    // Filter by specific office ONLY if chosen by the admin; otherwise, pull globally from all offices
    if ($requestedOffice !== 'all' && $requestedOffice !== '') {
        $whereClauses[] = "p.office_id = ?";
        $queryParams[]  = (int)$requestedOffice;
    }

    // Filter by text search matching patient names
    if ($searchName !== '') {
        $whereClauses[] = "p.name LIKE ?";
        $queryParams[]  = '%' . $searchName . '%';
    }

    // Combine clauses together dynamically
    $whereSql = "";
    if (!empty($whereClauses)) {
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    }

    // 4. Execute Count Query using combined criteria
    $countSql = "SELECT COUNT(*) as total FROM `patient` p $whereSql";
    $countRow = $db->queryOne($countSql, $queryParams);
    $totalCount = (int)($countRow['total'] ?? 0);

    // 5. Execute Records Query with pagination limits
    // JOINing offices table ensures your data loop has access to 'office_name'
    $recordsSql = "SELECT 
                        p.id, 
                        p.name, 
                        p.dob, 
                        p.mobile, 
                        p.mobile AS phone, -- Fallback mapping to handle JS view template layouts safely
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

    // 6. Prepare Metadata Output structural configurations matching your js script expectations
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

    Api::success($responseData, 'Administrative patients list retrieved successfully.');

} catch (Exception $e) {
    Api::error('Administrative database retrieval failed: ' . $e->getMessage());
}