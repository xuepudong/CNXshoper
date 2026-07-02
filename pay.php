<?php
/**
 * 支付跳转页 — 根据渠道展示支付二维码或跳转
 * 实际支付 SDK 需在此接入；当前为配置引导页面
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payment.php';

session_start_safe();

$order_no = get_param('order');
if (empty($order_no)) {
    redirect('/');
}

$order = DB::queryOne('SELECT * FROM orders WHERE order_no = ?', [$order_no]);
if (!$order) {
    redirect('/');
}

// ── 轮询接口：前端扫码后每 3 秒查一次支付状态 ──────────────
if (get_param('action') === 'status') {
    header('Content-Type: application/json');
    echo json_encode(['paid' => (int)$order['status'] === 1]);
    exit;
}

// 已支付，跳转到结果页
if ((int)$order['status'] === 1) {
    redirect('/result.php?order=' . urlencode($order_no));
}

$channel = DB::queryOne(
    'SELECT * FROM payment_channels WHERE code = ?',
    [$order['channel']]
);

$site_name = setting('site_name', '慧学教育');

// 支付过期时间（分钟）
$expire_minutes = (int)setting('order_expire', '30');
$expire_time    = strtotime($order['created_at']) + $expire_minutes * 60;
$remaining_secs = max(0, $expire_time - time());

// ── 生成支付参数（需按渠道接入真实 SDK）──────────────────
$pay_url    = '';
$pay_qrcode = '';
$pay_error  = '';

if ($channel && (int)$channel['status'] === 1) {
    $cfg = json_decode($channel['config'] ?? '{}', true) ?: [];

    switch ($order['channel']) {
        case 'alipay':
            if (empty($cfg['app_id']) || empty($cfg['private_key'])) {
                $pay_error = '支付宝未完成配置，请联系管理员。';
            } else {
                // 生成电脑网站支付跳转链接（RSA2 签名）
                $pay_url = alipay_page_pay_url(
                    $cfg,
                    $order_no,
                    (float)$order['amount'],
                    $order['product_name']
                );
                if ($pay_url === '') {
                    $pay_error = '支付宝签名失败，请检查应用私钥配置。';
                }
            }
            break;

        case 'wechat':
            if (empty($cfg['mch_id']) || empty($cfg['api_key'])) {
                $pay_error = '微信支付未完成配置，请联系管理员。';
            } else {
                // Native V2 统一下单，拿到 code_url 后前端渲染二维码
                $wx = wechat_native_unifiedorder(
                    $cfg,
                    $order_no,
                    (float)$order['amount'],
                    $order['product_name'],
                    client_ip()
                );
                if ($wx['success']) {
                    $pay_qrcode = $wx['code_url'];  // weixin:// 文本，交给前端生成二维码
                } else {
                    $pay_error = '微信下单失败：' . $wx['error'];
                }
            }
            break;

        case 'tenpay':
            if (empty($cfg['partner']) || empty($cfg['key'])) {
                $pay_error = '财付通未完成配置，请联系管理员。';
            } else {
                $pay_error = '财付通渠道待接入 SDK，请在后台完善配置后重启服务。';
            }
            break;

        default:
            $pay_error = '不支持的支付方式';
    }
} else {
    $pay_error = '该支付渠道暂时不可用，请返回选择其他方式。';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>支付 — <?= h($site_name) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .pay-wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
    .pay-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 16px rgba(0,0,0,.1); overflow: hidden; }
    .pay-head { background: var(--primary); color: #fff; padding: 20px 24px; }
    .pay-head h2 { font-size: 18px; margin-bottom: 4px; }
    .pay-head p  { font-size: 13px; opacity: .8; }
    .pay-body { padding: 28px 24px; text-align: center; }
    .pay-amount { font-size: 42px; font-weight: 800; color: var(--accent); margin: 16px 0; }
    .pay-amount small { font-size: 20px; }
    .pay-info-list { text-align: left; border-top: 1px dashed var(--border); margin-top: 20px; padding-top: 16px; }
    .pay-info-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; }
    .pay-info-row .lbl { color: var(--text-muted); }
    .countdown { background: #fff8e1; border: 1px solid #fcd5a0; border-radius: 6px; padding: 10px 16px; margin: 16px 0; font-size: 14px; color: #9a7d0a; }
    .countdown span { font-weight: 700; color: var(--accent); }
    .back-link { display: block; text-align: center; margin-top: 20px; font-size: 13px; color: var(--text-muted); }
    .qr-placeholder { width: 200px; height: 200px; background: #f0f2f5; border-radius: 8px; margin: 16px auto; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); font-size: 40px; }
  </style>
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="/" class="site-logo"><div class="logo-icon">📚</div><?= h($site_name) ?></a>
  </div>
</header>

<div class="pay-wrap">
  <div class="pay-card">
    <div class="pay-head">
      <h2>
        <?= match($order['channel']) {
            'alipay' => '🔵 支付宝支付',
            'wechat' => '🟢 微信支付',
            'tenpay' => '🟡 财付通支付',
            default  => '💳 在线支付',
        } ?>
      </h2>
      <p>订单号：<?= h($order['order_no']) ?></p>
    </div>

    <div class="pay-body">
      <div style="font-size:14px;color:var(--text-muted);">应付金额</div>
      <div class="pay-amount"><small>¥</small><?= number_format((float)$order['amount'], 2) ?></div>

      <?php if ($remaining_secs > 0): ?>
      <div class="countdown">
        ⏱ 请在 <span id="countdown"><?= floor($remaining_secs/60) ?>分<?= $remaining_secs%60 ?>秒</span> 内完成支付
      </div>
      <?php else: ?>
      <div style="background:#fdecea;border:1px solid #f5b7b1;border-radius:6px;padding:10px 16px;color:#c0392b;font-size:14px;">
        订单已超时，请重新报名
      </div>
      <?php endif; ?>

      <?php if ($pay_error): ?>
        <div style="background:#fdecea;border:1px solid #f5b7b1;border-radius:8px;padding:16px;margin:16px 0;text-align:left;">
          <strong style="color:#c0392b;">⚠️ 支付渠道提示</strong>
          <p style="margin-top:6px;font-size:13px;color:#7f8c8d;"><?= h($pay_error) ?></p>
          <p style="margin-top:8px;font-size:13px;color:#7f8c8d;">
            您的报名信息已记录，订单号：<strong><?= h($order['order_no']) ?></strong><br>
            请截图保存并联系我们的工作人员手动确认支付。
          </p>
          <?php $phone = setting('site_phone'); if ($phone): ?>
            <p style="margin-top:8px;font-size:14px;"><strong style="color:#1a5276;">📞 <?= h($phone) ?></strong></p>
          <?php endif; ?>
        </div>
      <?php elseif ($pay_url): ?>
        <a href="<?= h($pay_url) ?>" class="btn-buy" style="display:block;text-align:center;">
          点击前往支付
        </a>
      <?php elseif ($pay_qrcode): ?>
        <div style="margin:16px auto;">
          <div id="wx-qrcode" data-code="<?= h($pay_qrcode) ?>"
               style="width:220px;height:220px;margin:0 auto;padding:10px;background:#fff;border:1px solid var(--border);border-radius:8px;"></div>
          <p style="margin-top:8px;font-size:13px;color:var(--text-muted);">请使用<strong>微信</strong>扫描二维码完成支付</p>
        </div>
      <?php endif; ?>

      <div class="pay-info-list">
        <div class="pay-info-row"><span class="lbl">商品名称</span><span><?= h($order['product_name']) ?></span></div>
        <div class="pay-info-row"><span class="lbl">报名人</span><span><?= h($order['buyer_name']) ?></span></div>
        <div class="pay-info-row"><span class="lbl">联系电话</span><span><?= h($order['buyer_phone']) ?></span></div>
        <div class="pay-info-row"><span class="lbl">支付方式</span><span><?= h($channel['name'] ?? $order['channel']) ?></span></div>
      </div>
    </div>
  </div>
  <a href="/" class="back-link">← 返回首页</a>
</div>

<footer class="site-footer">
  <p>© <?= date('Y') ?> <?= h($site_name) ?></p>
</footer>

<script>
// 倒计时
(function() {
  var el = document.getElementById('countdown');
  if (!el) return;
  var secs = <?= (int)$remaining_secs ?>;
  var timer = setInterval(function() {
    secs--;
    if (secs <= 0) {
      clearInterval(timer);
      el.textContent = '已超时';
      el.style.color = '#c0392b';
      return;
    }
    var m = Math.floor(secs / 60);
    var s = secs % 60;
    el.textContent = m + '分' + (s < 10 ? '0' : '') + s + '秒';
  }, 1000);
})();
</script>
<script src="/assets/js/main.js"></script>
<?php if ($pay_qrcode): ?>
<!-- 本地离线二维码库，不依赖外部 CDN，避免把订单信息发往第三方 -->
<script src="/assets/js/qrcode.min.js"></script>
<script>
(function () {
  var box = document.getElementById('wx-qrcode');
  if (box && window.QRCode) {
    new QRCode(box, {
      text: box.getAttribute('data-code'),
      width: 200,
      height: 200,
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  // 轮询订单状态：支付成功后自动跳转结果页
  var orderNo = <?= json_encode($order_no) ?>;
  var timer = setInterval(function () {
    fetch('/pay.php?action=status&order=' + encodeURIComponent(orderNo), {
      headers: { 'X-Requested-With': 'fetch' }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.paid) {
          clearInterval(timer);
          window.location.href = '/result.php?order=' + encodeURIComponent(orderNo);
        }
      })
      .catch(function () { /* 忽略单次失败，下轮重试 */ });
  }, 3000);
})();
</script>
<?php endif; ?>
</body>
</html>
