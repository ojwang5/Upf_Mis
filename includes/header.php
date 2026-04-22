<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$user = current_user();
$page = $page ?? '';
$page_title = $page_title ?? '';

$nav_items = [
  ['key'=>'dashboard','href'=>'/','label'=>'Dashboard','icon'=>'<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>'],
  ['key'=>'employees','href'=>'/employees.php','label'=>'Employees','icon'=>'<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
  ['key'=>'daily','href'=>'/daily-status.php','label'=>'Daily Status','icon'=>'<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>'],
  ['key'=>'reports','href'=>'/reports.php','label'=>'Reports','icon'=>'<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>'],
  ['key'=>'history','href'=>'/history.php','label'=>'History','icon'=>'<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/><polyline points="12 7 12 12 15 15"/></svg>'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ? $page_title . ' — ' : '') ?><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<link rel="icon" type="image/jpeg" href="/assets/logo.jpg">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <img src="/assets/logo.jpg" alt="UPF" class="logo">
    <div class="brand">
      <div class="org"><?= e(APP_ORG) ?></div>
      <div class="sys"><?= e(APP_NAME) ?></div>
      <div class="motto"><?= e(APP_MOTTO) ?></div>
    </div>
    <?php if ($user): ?>
      <div class="user-info">
        <div class="name"><?= e($user['full_name']) ?></div>
        <div class="meta"><?= e(ucfirst($user['role'])) ?><?= $user['branch_name'] ? ' · ' . e($user['branch_name']) : '' ?></div>
      </div>
      <a class="btn btn-ghost btn-sm" href="/logout.php">Sign out</a>
    <?php endif; ?>
  </div>
</header>
<?php if ($user): ?>
<div class="app">
  <nav class="sidebar sidebar-wrap">
    <div class="nav-label">Menu</div>
    <?php foreach ($nav_items as $n): ?>
      <a href="<?= $n['href'] ?>" class="<?= $page===$n['key']?'active':'' ?>"><?= $n['icon'] ?><span><?= e($n['label']) ?></span></a>
    <?php endforeach; ?>
    <div class="sb-footer">v1.0 · <?= date('Y') ?></div>
  </nav>
  <main class="content">
<?php endif; ?>
