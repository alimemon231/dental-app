<?php
/**
 * GET api/dashboard/stats.php
 * Returns summary counts for the dashboard stat cards.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$today = date('Y-m-d');

/* ---- Patients ---- */
$totalPatients = $db->count('patients', ['deleted_at' => null]);

/* ---- Today's appointments ---- */
$todaysAppointments = $db->count('appointments', [
    'appointment_date' => $today,
    'deleted_at'       => null,
]);

$pendingAppointments = $db->count('appointments', [
    'appointment_date' => $today,
    'status'           => 'pending',
    'deleted_at'       => null,
]);

/* ---- Low stock (items below reorder level) ---- */
$lowStockItems = $db->queryOne(
    "SELECT COUNT(*) as cnt FROM products WHERE quantity <= reorder_level AND deleted_at IS NULL"
);
$lowStockCount = (int)($lowStockItems['cnt'] ?? 0);

/* ---- Revenue this month ---- */
$monthStart   = date('Y-m-01');
$monthEnd     = date('Y-m-t');
$revenueRow   = $db->queryOne(
    "SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments
     WHERE payment_date BETWEEN ? AND ? AND status = 'completed'",
    [$monthStart, $monthEnd]
);
$revenueThisMonth = (float)($revenueRow['total'] ?? 0);

Api::success([
    'total_patients'       => $totalPatients,
    'todays_appointments'  => $todaysAppointments,
    'pending_appointments' => $pendingAppointments,
    'low_stock_count'      => $lowStockCount,
    'revenue_this_month'   => $revenueThisMonth,
]);
