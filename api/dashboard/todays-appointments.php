<?php
/**
 * GET api/dashboard/todays-appointments.php
 * Returns today's appointment list with patient + doctor names.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();

$today = date('Y-m-d');

$appointments = $db->query(
    "SELECT
        a.id,
        a.appointment_time   AS time,
        a.status,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
     FROM appointments a
     LEFT JOIN patients p ON p.id = a.patient_id
     LEFT JOIN users    u ON u.id = a.doctor_id
     WHERE a.appointment_date = ?
       AND a.deleted_at IS NULL
     ORDER BY a.appointment_time ASC
     LIMIT 10",
    [$today]
);

Api::success($appointments);
