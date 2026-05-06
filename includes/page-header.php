<?php
/**
 * includes/page-header.php
 * Include at the top of every protected page.
 *
 * Usage:
 *   $pageTitle     = 'Patients';         // required — <title> and breadcrumb
 *   $activePage    = 'patients';         // required — matches nav-item href slug
 *   $breadcrumbs   = [                   // optional
 *       ['label' => 'Clinic', 'url' => '#'],
 *       ['label' => 'Patients'],          // last item has no url (current)
 *   ];
 *   require_once __DIR__ . '/../includes/page-header.php';
 *
 * Auth is automatically checked here.
 */

require_once __DIR__ . '/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();
$currentUser = $auth->user();

// Defaults
$pageTitle = $pageTitle ?? 'Page';
$activePage = $activePage ?? '';
$breadcrumbs = $breadcrumbs ?? [];

// Nav structure — add/remove items here for all pages
$navSections = "";
$office_name = "";

if ($currentUser['role'] == 'admin') {
  $navSections = [
    'Main' => [
      ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'href' => '/dashboard.php', 'key' => 'dashboard'],
    ],
    'Offices Settings' => [
      ['label' => 'Offices Setting', 'icon' => 'fa-hospital', 'href' => '/offices.php', 'key' => 'offices'],
      ['label' => 'Budget Setting', 'icon' => 'fa-file-invoice-dollar', 'href' => '/monthly_budget.php', 'key' => 'budget'],
    ],
    'Pre-auth Settings' => [
      ['label' => 'Pre-Auth Records', 'icon' => 'fa-clipboard-check', 'href' => '/adm-pre-auth.php', 'key' => 'pre_auth'],
      ['label' => 'Procedure Settings', 'icon' => 'fa-tooth', 'href' => '/procedures.php', 'key' => 'procedures'],
      ['label' => 'Insurance Settings', 'icon' => 'fa-shield-halved', 'href' => '/insurance.php', 'key' => 'insurance'],
    ],

    'User Settings' => [
      ['label' => 'Doctor Setting', 'icon' => 'fa-stethoscope', 'href' => '/doctors.php', 'key' => 'doctors'],
      ['label' => 'Staff Setting', 'icon' => 'fa-users-gear', 'href' => '/staff.php', 'key' => 'staff'],
      ['label' => 'Management Settings', 'icon' => 'fa-user-tie', 'href' => '/management.php', 'key' => 'management'],
    ],

    'Supply Settings' => [
      ['label' => 'Supply Items', 'icon' => 'fa-box', 'href' => '/items.php', 'key' => 'items'],
      ['label' => 'Supply Categories', 'icon' => 'fa-boxes', 'href' => '/categories.php', 'key' => 'categories'],

    ],

  ];

} else if ($currentUser['role'] == 'staff') {
  $navSections = [

    '' => [
      ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'href' => '/dashboard.php', 'key' => 'dashboard'],
    ],

    'Preauth Settings' => [
      ['label' => 'Add Record', 'icon' => 'fa-plus-circle', 'href' => '/emp-pre-auth.php', 'key' => 'order'],
      ['label' => 'Verified Record', 'icon' => 'fa-check-double', 'href' => '/emp-done-patient.php', 'key' => 'dashboard'],
      ['label' => 'Add Appointments', 'icon' => 'fa-calendar-plus', 'href' => '/emp-book-appointment.php', 'key' => 'dashboard'],
    ],

    'Supply Settings' => [
      ['label' => 'Order History', 'icon' => 'fa-shopping-bag', 'href' => '/emp-order.php', 'key' => 'order'],
      ['label' => 'Supply Items', 'icon' => 'fa-pills', 'href' => '/emp-items.php', 'key' => 'dashboard'],
    ],

  ];

  $office_name = $auth->officeName($currentUser['id']);
} else if ($currentUser['role'] == 'doctor') {
  $navSections = [
    'Main' => [
      ['label' => 'Dashboard', 'icon' => 'fa-gauge', 'href' => '/dashboard.php', 'key' => 'dashboard'],
    ],
    'Clinic' => [
      ['label' => 'Orders', 'icon' => 'fa-shopping-bag', 'href' => '/doc-order.php', 'key' => 'order'],
      ['label' => 'Items', 'icon' => 'fa-pills', 'href' => '/emp-items.php', 'key' => 'dashboard'],
    ],

  ];

  $office_name = $auth->officeName($currentUser['id']);
}else if ($currentUser['role'] == 'm-staff') {
    // Management Staff Navigation
    $navSections = [
        'Approvals' => [
            ['label' => 'Pre-Auth Requests', 'icon' => 'fa-clipboard-list', 'href' => '/m-pre-auth.php', 'key' => 'm_pre_auth'],
        ],
    ];
    // M-Staff sees all offices, so we leave office_name blank or set to a generic label
    $office_name = "Central Management";
}


$userInitial = strtoupper(substr($currentUser['name'] ?? '?', 0, 1));
$userName = htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES);
$userRole = htmlspecialchars($currentUser['role'] ?? '', ENT_QUOTES);
?>


<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <a href="/dashboard.php" class="sidebar-logo">
    <div class="sidebar-logo-icon">
      <img src="assets/images/logo.jpg" alt="">
      <?php if ($office_name != "") {

        ?>
        <h2 class="sidebar-logo-text"><?php echo $office_name; ?></h2>
        <?php
      } ?>
    </div>
  </a>
  <nav class="sidebar-nav">
    <?php foreach ($navSections as $sectionLabel => $items): ?>
      <div class="sidebar-nav-section">
        <div class="sidebar-nav-label"><?= htmlspecialchars($sectionLabel) ?></div>
        <?php foreach ($items as $item): ?>
          <a href="<?= $item['href'] ?>" class="nav-item <?= ($activePage === $item['key']) ? 'active' : '' ?>">
            <i class="fa-solid <?= $item['icon'] ?>"></i>
            <?= htmlspecialchars($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="dropdown">
      <div class="sidebar-user" data-toggle="dropdown">
        <div class="user-avatar"><?= $userInitial ?></div>
        <div class="user-info">
          <span class="user-name"><?= $userName ?></span>
          <span class="user-role"><?= ucfirst($userRole) ?></span>
        </div>
        <i class="fa-solid fa-chevron-up" style="font-size:0.7rem;color:var(--color-sidebar-text)"></i>
      </div>
      <div class="dropdown-menu" style="bottom:100%;top:auto;margin-bottom:var(--sp-2)">
        <a href="/pages/profile.php" class="dropdown-item"><i class="fa-regular fa-user"></i> My Profile</a>
        <a href="/pages/change-password.php" class="dropdown-item"><i class="fa-solid fa-key"></i> Change Password</a>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item danger" onclick="App.auth.logout()">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
      </div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- TOPBAR -->
<header class="topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" id="sidebar-toggle"><i class="fa-solid fa-bars"></i></button>
    <div class="topbar-breadcrumb">
      <a href="/dashboard.php">Home</a>
      <?php foreach ($breadcrumbs as $crumb): ?>
        <span class="sep">/</span>
        <?php if (!empty($crumb['url'])): ?>
          <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
        <?php else: ?>
          <span class="current"><?= htmlspecialchars($crumb['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (empty($breadcrumbs)): ?>
        <span class="sep">/</span>
        <span class="current"><?= htmlspecialchars($pageTitle) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="topbar-right">
    <button class="topbar-icon-btn" title="Notifications"><i class="fa-regular fa-bell"></i></button>
    <div class="dropdown">
      <button class="topbar-icon-btn" data-toggle="dropdown"><i class="fa-regular fa-circle-user"></i></button>
      <div class="dropdown-menu">
        <a href="/pages/profile.php" class="dropdown-item"><i class="fa-regular fa-user"></i> Profile</a>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item danger" onclick="App.auth.logout()">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
      </div>
    </div>
  </div>
</header>