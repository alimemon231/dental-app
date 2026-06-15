<?php
/**
 * GET /admin-order/item-list.php
 * Fetch a simple list of all active items to populate dropdown selectors.
 */
require_once __DIR__ . '/../../includes/Auth.php';

$db = new Database();
$auth = new Auth($db);

// Require basic authentication
$auth->requireAuth();

// Simple SQL query to get only the fields required by your JS code
$sql = "SELECT id, name, price 
        FROM items 
        ORDER BY name ASC";

// Execute query with zero parameters
$items = $db->query($sql);

// Return JSON payload directly via your API helper class
Api::success($items, "Items loaded successfully.");