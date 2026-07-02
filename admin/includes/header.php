<?php
/**
 * 管理后台公共头部
 * $page_title 和 $active_menu 由各页面预先定义
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

admin_check();

$page_title  = $page_title  ?? '控制台';
$active_menu = $active_menu ?? '';
$site_name   = setting('site_name', '慧学教育');

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf" content="<?= csrf_token() ?>">
  <title><?= h($page_title) ?> — <?= h($site_name) ?> 管理后台</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

<!-- 侧边栏 -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">📚</div>
    <?= h($site_name) ?>
  </div>

  <nav class="sidebar-menu">
    <div class="menu-label">主导航</div>

    <a class="menu-item <?= $active_menu === 'dashboard' ? 'active' : '' ?>" href="/admin/index.php">
      <span class="menu-icon">📊</span> 控制台
    </a>
    <a class="menu-item <?= $active_menu === 'orders' ? 'active' : '' ?>" href="/admin/orders.php">
      <span class="menu-icon">📋</span> 订单管理
    </a>

    <div class="menu-label" style="margin-top:8px;">内容管理</div>
    <a class="menu-item <?= $active_menu === 'products' ? 'active' : '' ?>" href="/admin/products.php">
      <span class="menu-icon">🎓</span> 商品/课程
    </a>
    <a class="menu-item <?= $active_menu === 'channels' ? 'active' : '' ?>" href="/admin/channels.php">
      <span class="menu-icon">💳</span> 支付渠道
    </a>

    <div class="menu-label" style="margin-top:8px;">系统</div>
    <a class="menu-item <?= $active_menu === 'settings' ? 'active' : '' ?>" href="/admin/settings.php">
      <span class="menu-icon">⚙️</span> 站点设置
    </a>
    <a class="menu-item" href="/" target="_blank">
      <span class="menu-icon">🔗</span> 查看前台
    </a>
  </nav>
</aside>

<!-- 顶部栏 -->
<header class="admin-header">
  <div style="display:flex;align-items:center;gap:12px;">
    <button id="menuToggle" style="background:none;border:none;font-size:22px;cursor:pointer;display:none;color:#7f8c8d;"
            aria-label="菜单">☰</button>
    <div class="page-title"><?= h($page_title) ?></div>
  </div>
  <div class="header-right">
    <div style="font-size:13px;color:#7f8c8d;">
      👤 <?= h(admin_username()) ?>
    </div>
    <a href="/admin/logout.php" class="logout-btn">退出登录</a>
  </div>
</header>

<!-- 主内容开始 -->
<main class="admin-content">

<?php if ($flash): ?>
<div class="alert alert-<?= h($flash['type']) ?>" style="margin-bottom:16px;">
  <?= h($flash['message']) ?>
</div>
<?php endif; ?>
