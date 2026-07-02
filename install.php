<?php
/**
 * 一键安装脚本
 * 1. 创建数据表
 * 2. 设置管理员账号密码
 * 安装完成后请删除本文件！
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';  // h() 等辅助函数

$step    = $_GET['step'] ?? '1';
$message = '';
$error   = '';

// 检查锁文件
$lock_file = __DIR__ . '/includes/.installed';
if (file_exists($lock_file) && $step !== 'done') {
    $already_installed = true;
}

// ── 执行安装 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';

    if (mb_strlen($admin_user) < 3) {
        $error = '管理员用户名至少3个字符';
    } elseif (mb_strlen($admin_pass) < 8) {
        $error = '管理员密码至少8位';
    } else {
        try {
            // 连接数据库（不指定 charset 以便建库）
            $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // 创建数据库（若不存在）
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
                        DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            // 执行 SQL 脚本（整体多语句执行；脚本内含分号的文本值不适合手动拆分）
            $sql = file_get_contents(__DIR__ . '/install.sql');
            $pdo->exec($sql);

            // 设置管理员账号密码
            $hashed = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare(
                "INSERT INTO settings (`key`,`value`) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE `value`=?"
            );
            $stmt->execute(['admin_username', $admin_user, $admin_user]);
            $stmt->execute(['admin_password', $hashed, $hashed]);

            // 写锁文件
            file_put_contents($lock_file, date('Y-m-d H:i:s') . " installed\n");

            $step = 'done';
            $message = '安装成功！';
        } catch (PDOException $e) {
            $error = '数据库错误：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>系统安装</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-page">

<div class="login-box" style="max-width:520px;">
  <div class="login-logo">
    <div class="logo-icon">🛠️</div>
    <h1>收款系统安装向导</h1>
    <p>PHP <?= PHP_VERSION ?></p>
  </div>

  <?php if (!empty($already_installed)): ?>
    <div class="alert alert-warning">
      系统已安装。如需重新安装，请删除 <code>includes/.installed</code> 文件。
    </div>
    <a href="/admin/login.php" class="btn btn-primary" style="width:100%;">前往登录 →</a>

  <?php elseif ($step === 'done'): ?>
    <div class="alert alert-success">
      🎉 安装成功！请立即删除根目录下的 <code>install.php</code> 和 <code>install.sql</code> 文件以确保安全。
    </div>
    <a href="/admin/login.php" class="btn btn-primary" style="width:100%;">前往登录 →</a>

  <?php else: ?>
    <!-- 环境检查 -->
    <div style="background:#f8f9fb;border-radius:8px;padding:16px;margin-bottom:20px;font-size:13px;">
      <strong style="display:block;margin-bottom:8px;">环境检查</strong>
      <?php
        $checks = [
            'PHP ≥ 8.2'       => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO MySQL 扩展'  => extension_loaded('pdo_mysql'),
            'uploads 目录可写' => is_writable(__DIR__ . '/uploads/products'),
            'includes 目录可写' => is_writable(__DIR__ . '/includes'),
        ];
        foreach ($checks as $label => $ok):
      ?>
        <div style="display:flex;justify-content:space-between;padding:3px 0;">
          <span><?= h($label) ?></span>
          <span style="color:<?= $ok ? '#1e8449' : '#c0392b' ?>;font-weight:600;">
            <?= $ok ? '✓ 通过' : '✗ 不满足' ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-info" style="font-size:12px;">
      安装前请确认已在 <code>includes/config.php</code> 中正确填写数据库连接信息。
    </div>

    <form method="post" action="/install.php">
      <div class="form-group">
        <label class="form-label">设置管理员用户名</label>
        <input type="text" name="admin_user" class="form-control"
               value="<?= h($_POST['admin_user'] ?? 'admin') ?>" placeholder="admin" required>
      </div>
      <div class="form-group">
        <label class="form-label">设置管理员密码</label>
        <input type="password" name="admin_pass" class="form-control"
               placeholder="至少8位" required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">开始安装</button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
