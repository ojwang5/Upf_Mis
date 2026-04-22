<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$user = current_user();
$page = $page ?? '';
$page_title = $page_title ?? '';
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
        <div><strong><?= e($user['full_name']) ?></strong></div>
        <div class="muted"><?= e(ucfirst($user['role'])) ?><?= $user['branch_name'] ? ' — ' . e($user['branch_name']) : '' ?></div>
      </div>
      <a class="btn btn-ghost" href="/logout.php">Logout</a>
    <?php endif; ?>
  </div>
</header>
<?php if ($user): ?>
<div class="app">
  <nav class="sidebar">
    <a href="/" class="<?= $page==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="/employees.php" class="<?= $page==='employees'?'active':'' ?>">Employees</a>
    <a href="/daily-status.php" class="<?= $page==='daily'?'active':'' ?>">Daily Status</a>
    <a href="/reports.php" class="<?= $page==='reports'?'active':'' ?>">Reports</a>
    <a href="/history.php" class="<?= $page==='history'?'active':'' ?>">History</a>
  </nav>
  <main class="content">
<?php endif; ?>
