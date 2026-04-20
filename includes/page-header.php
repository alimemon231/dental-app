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

$db   = new Database();
$auth = new Auth($db);
$auth->requireAuth();
$currentUser = $auth->user();

// Defaults
$pageTitle   = $pageTitle   ?? 'Page';
$activePage  = $activePage  ?? '';
$breadcrumbs = $breadcrumbs ?? [];

// Nav structure — add/remove items here for all pages
$navSections = [
    'Main' => [
        ['label' => 'Dashboard',    'icon' => 'fa-gauge',              'href' => '/dashboard.php',               'key' => 'dashboard'],
    ],
    'Clinic' => [
        ['label' => 'Dental Offices',     'icon' => 'fa-hospital',    'href' => '/offices.php',          'key' => 'offices'],
        ['label' => 'Manage Doctors', 'icon' => 'fa-stethoscope',    'href' => '/doctors.php.php',      'key' => 'doctors'],
        ['label' => 'Manage Staff',   'icon' => 'fa-users',       'href' => '/staff.php',        'key' => 'staff'],
        ['label' => 'Prescriptions','icon' => 'fa-pills', 'href' => '/items.php',     'key' => 'items'],
    ],

];

$userInitial  = strtoupper(substr($currentUser['name'] ?? '?', 0, 1));
$userName     = htmlspecialchars($currentUser['name']  ?? '', ENT_QUOTES);
$userRole     = htmlspecialchars($currentUser['role']  ?? '', ENT_QUOTES);
?>


  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <a href="/dashboard.php" class="sidebar-logo">
      <div class="sidebar-logo-icon">
        <img src="assets/images/logo.jpg" alt="">
      </div>
    </a>
    <nav class="sidebar-nav">
      <?php foreach ($navSections as $sectionLabel => $items): ?>
        <div class="sidebar-nav-section">
          <div class="sidebar-nav-label"><?= htmlspecialchars($sectionLabel) ?></div>
          <?php foreach ($items as $item): ?>
            <a href="<?= $item['href'] ?>"
               class="nav-item <?= ($activePage === $item['key']) ? 'active' : '' ?>">
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

