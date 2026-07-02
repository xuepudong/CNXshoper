<?php
/**
 * 站点设置
 */

$page_title  = '站点设置';
$active_menu = 'settings';

require_once __DIR__ . '/includes/header.php';

// ── 保存设置 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    abort_csrf();

    $tab = post('tab', 'basic');

    if ($tab === 'basic') {
        $fields = ['site_name','site_subtitle','site_phone','site_email','site_address','site_icp','site_notice','order_prefix','order_expire'];
        foreach ($fields as $f) {
            $val = post($f);
            DB::execute(
                'INSERT INTO settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?',
                [$f, $val, $val]
            );
        }
        flash('success', '基本设置已保存');
    }

    if ($tab === 'password') {
        $new_username = post('new_username');
        $new_password = post('new_password');
        $confirm_pass = post('confirm_password');

        $errors = [];
        if (mb_strlen($new_username) < 3) {
            $errors[] = '用户名至少3个字符';
        }
        if (!empty($new_password)) {
            if (mb_strlen($new_password) < 8) {
                $errors[] = '密码至少8位';
            }
            if ($new_password !== $confirm_pass) {
                $errors[] = '两次密码不一致';
            }
        }

        if (empty($errors)) {
            DB::execute(
                'INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?',
                ['admin_username', $new_username, $new_username]
            );
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                DB::execute(
                    'INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?',
                    ['admin_password', $hashed, $hashed]
                );
            }
            flash('success', '账号信息已更新，请重新登录');
            redirect('/admin/logout.php');
        } else {
            flash('danger', implode('；', $errors));
        }
    }

    redirect('/admin/settings.php?tab=' . $tab);
}

$s   = settings_all();
$tab = get_param('tab', 'basic');
?>

<!-- Tab 导航 -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:0;">
  <?php foreach (['basic'=>'基本信息','password'=>'账号安全'] as $k=>$v): ?>
  <a href="/admin/settings.php?tab=<?= $k ?>"
     style="padding:10px 20px;font-size:14px;font-weight:600;border-radius:6px 6px 0 0;
            text-decoration:none;
            <?= $tab === $k
                ? 'background:var(--white);color:var(--primary);border:2px solid var(--border);border-bottom:2px solid var(--white);margin-bottom:-2px;'
                : 'color:var(--text-muted);' ?>">
    <?= $v ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'basic'): ?>
<!-- 基本设置 -->
<div class="card">
  <div class="card-header"><span class="card-title">基本信息</span></div>
  <div class="card-body">
    <form method="post" action="/admin/settings.php?tab=basic" class="needs-loading">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="basic">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label class="form-label">网站名称 <span class="req">*</span></label>
          <input type="text" name="site_name" class="form-control"
                 value="<?= h($s['site_name'] ?? '') ?>" placeholder="如：慧学教育" maxlength="50">
          <div class="form-hint">显示在页面标题、Logo 等位置</div>
        </div>
        <div class="form-group">
          <label class="form-label">网站副标题</label>
          <input type="text" name="site_subtitle" class="form-control"
                 value="<?= h($s['site_subtitle'] ?? '') ?>" placeholder="如：专业·务实·成就未来" maxlength="80">
        </div>
        <div class="form-group">
          <label class="form-label">咨询热线</label>
          <input type="text" name="site_phone" class="form-control"
                 value="<?= h($s['site_phone'] ?? '') ?>" placeholder="400-000-0000" maxlength="30">
        </div>
        <div class="form-group">
          <label class="form-label">联系邮箱</label>
          <input type="email" name="site_email" class="form-control"
                 value="<?= h($s['site_email'] ?? '') ?>" placeholder="service@example.com" maxlength="100">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">公司地址</label>
          <input type="text" name="site_address" class="form-control"
                 value="<?= h($s['site_address'] ?? '') ?>" placeholder="可选，显示在页脚" maxlength="200">
        </div>
        <div class="form-group">
          <label class="form-label">ICP 备案号</label>
          <input type="text" name="site_icp" class="form-control"
                 value="<?= h($s['site_icp'] ?? '') ?>" placeholder="京ICP备XXXXXXXX号" maxlength="50">
          <div class="form-hint">显示在页脚并链接到工信部</div>
        </div>
        <div class="form-group">
          <label class="form-label">订单号前缀</label>
          <input type="text" name="order_prefix" class="form-control"
                 value="<?= h($s['order_prefix'] ?? 'HX') ?>" placeholder="HX" maxlength="6">
          <div class="form-hint">订单号开头字母，建议公司缩写</div>
        </div>
        <div class="form-group">
          <label class="form-label">订单有效期（分钟）</label>
          <input type="number" name="order_expire" class="form-control" min="5" max="1440"
                 value="<?= h($s['order_expire'] ?? '30') ?>">
          <div class="form-hint">超时未支付的订单将显示过期提示</div>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">报名须知 / 公告</label>
          <textarea name="site_notice" class="form-control" rows="3"
                    placeholder="显示在首页横幅下方，如：报名成功后工作人员将在1个工作日内联系您"
                    maxlength="300"><?= h($s['site_notice'] ?? '') ?></textarea>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg">保存设置</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'password'): ?>
<!-- 账号安全 -->
<div class="card">
  <div class="card-header"><span class="card-title">修改管理员账号</span></div>
  <div class="card-body" style="max-width:460px;">
    <div class="alert alert-warning">
      ⚠️ 修改密码后将自动退出，需要重新登录。
    </div>
    <form method="post" action="/admin/settings.php?tab=password" class="needs-loading">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="password">

      <div class="form-group">
        <label class="form-label">用户名</label>
        <input type="text" name="new_username" class="form-control"
               value="<?= h($s['admin_username'] ?? 'admin') ?>"
               autocomplete="username" maxlength="50">
      </div>
      <div class="form-group">
        <label class="form-label">新密码</label>
        <input type="password" name="new_password" class="form-control"
               placeholder="至少8位，不修改密码请留空"
               autocomplete="new-password" maxlength="100">
      </div>
      <div class="form-group">
        <label class="form-label">确认新密码</label>
        <input type="password" name="confirm_password" class="form-control"
               placeholder="再次输入新密码"
               autocomplete="new-password" maxlength="100">
      </div>

      <button type="submit" class="btn btn-primary">更新账号</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
