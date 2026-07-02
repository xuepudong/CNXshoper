<?php
/**
 * 结账页面 — 填写信息 + 选择支付方式
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_start_safe();

$id      = (int)get_param('id');
$product = get_product($id, true);

if (!$product) {
    header('Location: /');
    exit;
}

$channels = get_active_channels();
if (empty($channels)) {
    // 没有可用支付渠道时，引导联系客服
    $no_channel = true;
}

$site_name  = setting('site_name', '慧学教育');
$site_phone = setting('site_phone', '');
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    abort_csrf();

    $buyer_name   = post('buyer_name');
    $buyer_phone  = post('buyer_phone');
    $buyer_email  = post('buyer_email');
    $buyer_remark = post('buyer_remark');
    $channel_code = post('channel');

    // 验证
    if (mb_strlen($buyer_name) < 2) {
        $errors['buyer_name'] = '请输入正确的姓名';
    }
    if (!preg_match('/^1[3-9]\d{9}$/', $buyer_phone)) {
        $errors['buyer_phone'] = '请输入正确的手机号';
    }
    if (!empty($buyer_email) && !filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
        $errors['buyer_email'] = '邮箱格式不正确';
    }
    if (!$channel_code || !get_channel($channel_code)) {
        $errors['channel'] = '请选择支付方式';
    }

    if (empty($errors)) {
        // 生成订单
        $order_no = generate_order_no();
        DB::insert(
            'INSERT INTO orders (order_no, product_id, product_name, amount, channel,
                buyer_name, buyer_phone, buyer_email, buyer_remark, status, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())',
            [
                $order_no,
                $product['id'],
                $product['name'],
                $product['price'],
                $channel_code,
                $buyer_name,
                $buyer_phone,
                $buyer_email,
                $buyer_remark,
                client_ip(),
            ]
        );

        // 跳转到支付页
        redirect('/pay.php?order=' . urlencode($order_no));
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>提交报名 — <?= h($site_name) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="/" class="site-logo">
      <div class="logo-icon">📚</div>
      <?= h($site_name) ?>
    </a>
    <?php if ($site_phone): ?>
    <div class="header-contact">📞 <span><?= h($site_phone) ?></span></div>
    <?php endif; ?>
  </div>
</header>

<div class="checkout-wrap">

  <?php if (!empty($no_channel)): ?>
  <div style="text-align:center;padding:60px 20px;">
    <div style="font-size:48px;margin-bottom:16px;">🔧</div>
    <h3>支付渠道维护中</h3>
    <p style="color:#7f8c8d;margin-top:8px;">请联系客服完成报名</p>
    <?php if ($site_phone): ?>
      <p style="margin-top:16px;font-size:18px;font-weight:700;color:#1a5276;"><?= h($site_phone) ?></p>
    <?php endif; ?>
    <a href="/" style="display:inline-block;margin-top:20px;color:#1a5276;">← 返回首页</a>
  </div>
  <?php else: ?>

  <form id="checkoutForm" method="post" action="/checkout.php?id=<?= $product['id'] ?>">
    <?= csrf_field() ?>

    <!-- 订单信息 -->
    <div class="checkout-card">
      <div class="checkout-card-head">
        <span class="step-num">1</span> 确认报名项目
      </div>
      <div class="checkout-card-body">
        <div class="order-summary-row">
          <span class="label">报名项目</span>
          <span class="value"><?= h($product['name']) ?></span>
        </div>
        <?php if ($product['subtitle']): ?>
        <div class="order-summary-row">
          <span class="label">简介</span>
          <span class="value" style="font-size:13px;color:#7f8c8d;"><?= h($product['subtitle']) ?></span>
        </div>
        <?php endif; ?>
        <div class="order-summary-row">
          <span class="label">应付金额</span>
          <span class="value price">¥<?= number_format((float)$product['price'], 2) ?></span>
        </div>
      </div>
    </div>

    <!-- 报名人信息 -->
    <div class="checkout-card">
      <div class="checkout-card-head">
        <span class="step-num">2</span> 填写报名信息
      </div>
      <div class="checkout-card-body">
        <div class="form-group">
          <label>姓名 <span class="req">*</span></label>
          <input type="text" name="buyer_name" class="form-control"
                 value="<?= h($_POST['buyer_name'] ?? '') ?>"
                 placeholder="请输入真实姓名" maxlength="50">
          <?php if (!empty($errors['buyer_name'])): ?>
            <div style="color:#e74c3c;font-size:12px;margin-top:4px;"><?= h($errors['buyer_name']) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>手机号 <span class="req">*</span></label>
          <input type="tel" name="buyer_phone" class="form-control"
                 value="<?= h($_POST['buyer_phone'] ?? '') ?>"
                 placeholder="报名后工作人员将联系此号码" maxlength="11">
          <?php if (!empty($errors['buyer_phone'])): ?>
            <div style="color:#e74c3c;font-size:12px;margin-top:4px;"><?= h($errors['buyer_phone']) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>邮箱 <span style="color:#7f8c8d;font-size:12px;">（选填，用于接收缴费凭证）</span></label>
          <input type="email" name="buyer_email" class="form-control"
                 value="<?= h($_POST['buyer_email'] ?? '') ?>"
                 placeholder="example@email.com" maxlength="100">
          <?php if (!empty($errors['buyer_email'])): ?>
            <div style="color:#e74c3c;font-size:12px;margin-top:4px;"><?= h($errors['buyer_email']) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>备注 <span style="color:#7f8c8d;font-size:12px;">（选填，如学历背景、报名班型等）</span></label>
          <textarea name="buyer_remark" class="form-control" rows="2"
                    placeholder="可填写任何需要告知的信息"
                    maxlength="200"><?= h($_POST['buyer_remark'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- 支付方式 -->
    <div class="checkout-card">
      <div class="checkout-card-head">
        <span class="step-num">3</span> 选择支付方式
      </div>
      <div class="checkout-card-body">
        <?php if (!empty($errors['channel'])): ?>
          <div style="color:#e74c3c;font-size:12px;margin-bottom:12px;"><?= h($errors['channel']) ?></div>
        <?php endif; ?>

        <div class="channel-list">
          <?php foreach ($channels as $i => $ch):
            $active = ($i === 0 || (isset($_POST['channel']) && $_POST['channel'] === $ch['code'])) ? 'active' : '';
            $descs = [
                'alipay' => '支持余额宝、花呗等方式付款',
                'wechat' => '使用微信扫码安全付款',
                'tenpay' => '财付通/QQ钱包快速付款',
            ];
          ?>
          <label class="channel-item <?= $active ?>">
            <input type="radio" name="channel" value="<?= h($ch['code']) ?>"
                   <?= ($active === 'active') ? 'checked' : '' ?>>
            <div class="channel-icon <?= h($ch['code']) ?>">
              <?= match($ch['code']) {
                  'alipay' => '支',
                  'wechat' => '微',
                  'tenpay' => '财',
                  default  => '付',
              } ?>
            </div>
            <div class="channel-info">
              <div class="channel-name"><?= h($ch['name']) ?></div>
              <div class="channel-desc"><?= h($descs[$ch['code']] ?? '安全快捷支付') ?></div>
            </div>
            <div class="channel-check"></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-submit">
      确认提交，去支付 ¥<?= number_format((float)$product['price'], 2) ?>
    </button>
  </form>

  <div style="text-align:center;margin-top:16px;font-size:12px;color:#bdc3c7;">
    提交即视为同意服务协议 · 已报名用户不可重复提交
  </div>

  <?php endif; ?>
</div>

<footer class="site-footer">
  <p>© <?= date('Y') ?> <?= h($site_name) ?></p>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
