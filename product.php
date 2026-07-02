<?php
/**
 * 商品详情页
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_start_safe();

$id      = (int)get_param('id');
$product = get_product($id, true);

if (!$product) {
    http_response_code(404);
    header('Location: /');
    exit;
}

$features = [];
if (!empty($product['features'])) {
    $features = json_decode($product['features'], true) ?: [];
}

$site_name  = setting('site_name', '慧学教育');
$site_phone = setting('site_phone', '');

$card_colors = ['#1a5276','#1e8449','#7d6608','#6c3483','#0e6655','#154360'];
$color = $card_colors[$product['id'] % count($card_colors)];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($product['name']) ?> — <?= h($site_name) ?></title>
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

<div class="container product-detail">
  <!-- 面包屑 -->
  <div style="margin-bottom:16px;font-size:13px;color:#7f8c8d;">
    <a href="/">首页</a> <span style="margin:0 6px;">/</span>
    <span><?= h($product['name']) ?></span>
  </div>

  <div class="detail-grid">
    <!-- 左：图片 + 描述 -->
    <div>
      <?php if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])): ?>
        <img class="detail-img" src="<?= UPLOAD_URL . h($product['image']) ?>" alt="<?= h($product['name']) ?>">
      <?php else: ?>
        <div class="detail-img" style="height:260px;background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;border-radius:8px;">
          <?= h($product['name']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($product['description'])): ?>
      <div class="detail-desc">
        <h4>课程介绍</h4>
        <?= $product['description'] /* 已在后台过滤，允许基本HTML */ ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 右：购买区 -->
    <div>
      <div class="detail-category"><?= h($product['category_name'] ?? '') ?></div>
      <h1 class="detail-title"><?= h($product['name']) ?></h1>
      <?php if ($product['subtitle']): ?>
        <p class="detail-subtitle"><?= h($product['subtitle']) ?></p>
      <?php endif; ?>

      <?php if (!empty($features)): ?>
      <div class="detail-features">
        <?php foreach ($features as $f): ?>
          <span class="detail-feat">✓ <?= h($f) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="detail-price-box">
        <div class="price-label">报名价格</div>
        <div class="price-main"><sup>¥</sup><?= number_format((float)$product['price'], 2) ?></div>
        <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
          <div class="price-orig">原价 ¥<?= number_format((float)$product['original_price'], 2) ?></div>
        <?php endif; ?>
        <div style="margin-top:8px;font-size:12px;color:#7f8c8d;">已有 <?= (int)$product['sales'] ?> 人报名</div>
      </div>

      <a href="/checkout.php?id=<?= $product['id'] ?>">
        <button class="btn-buy">立即报名缴费</button>
      </a>

      <div class="security-tips">
        <span>🔒 安全支付</span>
        <span>📱 支持手机端</span>
        <span>💬 客服保障</span>
      </div>

      <?php if ($site_phone): ?>
      <div style="margin-top:16px;text-align:center;font-size:13px;color:#7f8c8d;background:#f8f9fb;border-radius:8px;padding:12px;">
        有疑问？拨打咨询热线：<strong style="color:#1a5276;"><?= h($site_phone) ?></strong>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<footer class="site-footer">
  <p>© <?= date('Y') ?> <?= h($site_name) ?>
  <?php $icp = setting('site_icp'); if ($icp): ?> | <a href="https://beian.miit.gov.cn/" target="_blank"><?= h($icp) ?></a><?php endif; ?>
  </p>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
