<?php
/**
 * API: Fetch Payment Liability List with Calculated Totals & Procedure Names
 * Path: /emp-payments/list.php
 * Aggregates: Total Paid (Payment) and Total Refunded (Refund) per Master Record.
 */

require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();

// Ensure user has permission to view billing
if (!$auth->hasRole('staff') && !$auth->hasRole('doctor') && !$auth->hasRole('admin')) {
    Api::error('Unauthorized access.', 403);
    exit;
}

$sessionOfficeId = (int) ($_SESSION['office_id'] ?? 0);

if ($sessionOfficeId <= 0) {
    Api::error('Active clinic location scope not established.', 400);
    exit;
}


    // We use LEFT JOIN + GROUP BY to calculate running totals for each payment case.
    // Filtered to Active and Completed statuses so closed invoices do not disappear.
    $sql = "SELECT 
                p.id AS payment_id,
                p.patient_id,
                p.provider_id,
                p.total_amount,
                p.treatment_ids,
                p.payment_date,
                p.status,
                pat.name AS patient_name,
                doc.name AS provider_name,
                /* COALESCE ensures 0 instead of NULL if no transactions exist */
                COALESCE(SUM(CASE WHEN pt.payment_type = 'Payment' THEN pt.amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN pt.payment_type = 'Refund' THEN pt.amount ELSE 0 END), 0) AS total_refunded
            FROM `payments` p
            INNER JOIN `patient` pat ON p.patient_id = pat.id
            LEFT JOIN `users` doc ON p.provider_id = doc.user_id
            LEFT JOIN `payment_transactions` pt ON p.id = pt.payment_id
            WHERE p.office_id = ? 
              AND p.status = 'Active'
            GROUP BY p.id
            ORDER BY p.id DESC";

    $records = $db->query($sql, [$sessionOfficeId]);

    if (empty($records)) {
        Api::success([], 'Success');
        exit;
    }

    // Collect all unique procedure IDs to resolve their names in one query
    $allProcedureIds = [];
    foreach ($records as $row) {
        if (!empty($row['treatment_ids'])) {
            $ids = explode(',', $row['treatment_ids']);
            foreach ($ids as $id) {
                $allProcedureIds[] = (int) $id;
            }
        }
    }

    // Fetch unique procedure names
    $procNames = [];
    if (!empty($allProcedureIds)) {
        $uniqueIds = array_values(array_unique($allProcedureIds)); // array_values resets array keys cleanly
        
        // Use a simple loop format or build a safe parameterized sequence
        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));

        // Pass explicitly re-indexed flat values straight into your PDO mapping wrapper
        $procRows = $db->query("SELECT id, name FROM procedures WHERE id IN ($placeholders)", $uniqueIds) ?: [];
        foreach ($procRows as $pr) {
            $procNames[(int)$pr['id']] = $pr['name'];
        }
    }

    // Format the data for the frontend
    $finalList = [];
    foreach ($records as $row) {
        $totalPaid = (float) $row['total_paid'];
        $totalRefunded = (float) $row['total_refunded'];

        // Calculate the net paid amount first
        $netPaid = (float) $row['total_paid'] - (float) $row['total_refunded'];

        // Calculate balance
        $balance = (float) $row['total_amount'] - $netPaid;

        // Now, handle the categorization for your frontend
        $balanceType = 'Due';
        if ($balance < 0) {
            $balanceType = 'Credit'; // The patient has extra money (overpaid)
        } elseif ($balance == 0) {
            $balanceType = 'Settled'; // Paid in full
        } else {
            $balanceType = 'Due';    // Money still owed
        }



        // Explode comma-separated string back into an array and resolve actual procedure names
        $treatmentNames = [];
        $ids = !empty($row['treatment_ids']) ? explode(',', $row['treatment_ids']) : [];
        foreach ($ids as $id) {
            $treatmentNames[] = $procNames[$id] ?? "Unknown Procedure (#{$id})";
        }

        $finalList[] = [
            'id' => (int) $row['payment_id'],
            'patient_name' => $row['patient_name'],
            'provider_name' => $row['provider_name'] ?? 'Unassigned',
            'treatment_names' => implode(', ', $treatmentNames), // Comma-separated name string for the front-end
            'total_amount' => (float) $row['total_amount'],
            'total_paid' => $netPaid,
            'balance_due' => $balance,
            'status' => $row['status'],
            'payment_date' => $row['payment_date'],
            'balance_type' => $balanceType
        ];
    }

    Api::success($finalList, 'Success');

