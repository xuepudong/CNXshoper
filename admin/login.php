<?php
/**
 * 管理后台登录
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/config.php';

session_start_safe();

// 已登录直接跳转
if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/index.php');
}

$error   = '';
$expired = !empty($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    abort_csrf();
    $username = post('username');
    $password = post('password');

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } elseif (!admin_login($username, $password)) {
        // 简单防暴力：增加延迟
        sleep(1);
        $error = '用户名或密码错误';
    } else {
        redirect('/admin/index.php');
    }
}

$site_name = setting('site_name', '慧学教育');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>管理员登录 — <?= h($site_name) ?></title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">

<div class="login-box">
  <div class="login-logo">
    <div class="logo-icon">📚</div>
    <h1><?= h($site_name) ?></h1>
    <p>管理后台</p>
  </div>

  <?php if ($expired): ?>
    <div class="alert alert-warning">会话已过期，请重新登录</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/admin/login.php" class="needs-loading">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label">用户名</label>
      <input type="text" name="username" class="form-control"
             value="<?= h(post('username')) ?>"
             placeholder="请输入管理员用户名"
             autocomplete="username" maxlength="50">
    </div>

    <div class="form-group">
      <label class="form-label">密码</label>
      <input type="password" name="password" class="form-control"
             placeholder="请输入密码"
             autocomplete="current-password" maxlength="100">
    </div>

    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;">
      登 录
    </button>
  </form>

  <div style="text-align:center;margin-top:20px;font-size:12px;color:#bdc3c7;">
    首次使用请运行 install.php 初始化
  </div>
</div>

<script src="/assets/js/admin.js"></script>
</body>
</html>
