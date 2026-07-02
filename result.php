<?php
/**
 * 支付结果页
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_start_safe();

// 订单号来源：自家跳转用 order；支付宝 return_url 回跳用 out_trade_no
$order_no = get_param('order');
if ($order_no === '' || $order_no === null) {
    $order_no = get_param('out_trade_no');
}
$order    = $order_no ? DB::queryOne('SELECT * FROM orders WHERE order_no = ?', [$order_no]) : false;

$site_name = setting('site_name', '慧学教育');

$status = $order ? (int)$order['status'] : -1;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>支付结果 — <?= h($site_name) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="/" class="site-logo"><div class="logo-icon">📚</div><?= h($site_name) ?></a>
  </div>
</header>

<div class="result-wrap">
  <?php if (!$order): ?>
    <div class="result-icon fail">❓</div>
    <div class="result-title">订单不存在</div>
    <div class="result-subtitle">无法找到该订单信息</div>

  <?php elseif ($status === 1): ?>
    <div class="result-icon success">✅</div>
    <div class="result-title" style="color:#1e8449;">支付成功</div>
    <div class="result-subtitle">感谢您的报名！工作人员将在1个工作日内与您联系。</div>

    <div class="result-info">
      <div class="result-info-row"><span class="lbl">订单号</span><span class="val"><?= h($order['order_no']) ?></span></div>
      <div class="result-info-row"><span class="lbl">报名课程</span><span class="val"><?= h($order['product_name']) ?></span></div>
      <div class="result-info-row"><span class="lbl">支付金额</span><span class="val" style="color:var(--accent);">¥<?= number_format((float)$order['amount'], 2) ?></span></div>
      <div class="result-info-row"><span class="lbl">报名人</span><span class="val"><?= h($order['buyer_name']) ?></span></div>
      <div class="result-info-row"><span class="lbl">联系电话</span><span class="val"><?= h($order['buyer_phone']) ?></span></div>
      <div class="result-info-row"><span class="lbl">支付时间</span><span class="val"><?= h($order['pay_time']) ?></span></div>
    </div>

  <?php elseif ($status === 0): ?>
    <div class="result-icon pending">⏳</div>
    <div class="result-title" style="color:#b7770d;">等待支付</div>
    <div class="result-subtitle">订单已创建，请尽快完成支付。</div>

    <div class="result-info">
      <div class="result-info-row"><span class="lbl">订单号</span><span class="val"><?= h($order['order_no']) ?></span></div>
      <div class="result-info-row"><span class="lbl">报名课程</span><span class="val"><?= h($order['product_name']) ?></span></div>
      <div class="result-info-row"><span class="lbl">应付金额</span><span class="val" style="color:var(--accent);">¥<?= number_format((float)$order['amount'], 2) ?></span></div>
    </div>

    <a href="/pay.php?order=<?= urlencode($order['order_no']) ?>"
       style="display:block;background:var(--accent);color:#fff;padding:14px;border-radius:8px;text-align:center;font-weight:700;font-size:16px;margin-bottom:12px;">
      继续支付
    </a>

  <?php else: ?>
    <div class="result-icon fail">❌</div>
    <div class="result-title" style="color:#c0392b;">支付失败 / 已取消</div>
    <div class="result-subtitle">如有疑问请联系客服处理</div>
  <?php endif; ?>

  <a href="/" style="display:block;text-align:center;margin-top:20px;font-size:14px;color:var(--text-muted);">
    ← 返回首页继续报名
  </a>
</div>

<footer class="site-footer">
  <p>© <?= date('Y') ?> <?= h($site_name) ?></p>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
