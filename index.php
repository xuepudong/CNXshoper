<?php
/**
 * 前台首页 — 商品列表
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_start_safe();

$site_name    = setting('site_name', '慧学教育');
$site_subtitle = setting('site_subtitle', '专业·务实·成就未来');
$site_phone   = setting('site_phone', '');
$site_notice  = setting('site_notice', '');

$products = get_products(true);

// 按分类分组
$grouped = [];
foreach ($products as $p) {
    $grouped[$p['category_name'] ?? '其他'][] = $p;
}

// 颜色配置（分类对应的卡片背景渐变）
$card_colors = [
    '#1a5276', '#1e8449', '#7d6608', '#6c3483', '#0e6655',
    '#154360', '#117864', '#4a235a', '#78281f'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($site_name) ?> — 报名缴费</title>
  <meta name="description" content="<?= h($site_subtitle) ?>">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- 顶部导航 -->
<header class="site-header">
  <div class="container">
    <a href="/" class="site-logo">
      <div class="logo-icon">📚</div>
      <?= h($site_name) ?>
    </a>
    <?php if ($site_phone): ?>
    <div class="header-contact">
      📞 咨询热线：<span><?= h($site_phone) ?></span>
    </div>
    <?php endif; ?>
  </div>
</header>

<!-- 横幅 -->
<section class="hero">
  <h1><?= h($site_name) ?></h1>
  <p><?= h($site_subtitle) ?></p>
  <?php if ($site_notice): ?>
  <div class="notice-bar">
    📢 <?= h($site_notice) ?>
  </div>
  <?php endif; ?>
</section>

<!-- 商品列表 -->
<div class="container">
  <?php if (empty($products)): ?>
    <div style="text-align:center;padding:60px 20px;color:#7f8c8d;">
      <div style="font-size:48px;margin-bottom:16px;">📋</div>
      <p>暂无上架商品，请稍后再来。</p>
    </div>
  <?php else: ?>

    <?php foreach ($grouped as $cat_name => $cat_products): ?>
    <div class="section-head">
      <h2><?= h($cat_name) ?></h2>
      <div class="line"></div>
    </div>

    <div class="products-grid">
      <?php foreach ($cat_products as $index => $product):
        $features = [];
        if (!empty($product['features'])) {
            $features = json_decode($product['features'], true) ?: [];
        }
        $color = $card_colors[$product['id'] % count($card_colors)];
      ?>
      <div class="product-card" onclick="location.href='/product.php?id=<?= $product['id'] ?>'">
        <?php if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])): ?>
          <img class="card-img" src="<?= UPLOAD_URL . h($product['image']) ?>" alt="<?= h($product['name']) ?>">
        <?php else: ?>
          <div class="card-img-placeholder" style="background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc);">
            <?= h(mb_substr($product['name'], 0, 6)) ?>
          </div>
        <?php endif; ?>

        <div class="card-body">
          <div class="category-tag"><?= h($product['category_name'] ?? '') ?></div>
          <h3><?= h($product['name']) ?></h3>
          <?php if ($product['subtitle']): ?>
            <div class="subtitle"><?= h($product['subtitle']) ?></div>
          <?php endif; ?>

          <?php if (!empty($features)): ?>
          <div class="features-tags">
            <?php foreach (array_slice($features, 0, 3) as $feat): ?>
              <span class="feat-tag"><?= h($feat) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="card-footer">
            <div class="price-block">
              <span class="price-now"><sup>¥</sup><?= number_format((float)$product['price'], 0) ?></span>
              <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                <span class="price-orig">¥<?= number_format((float)$product['original_price'], 0) ?></span>
              <?php endif; ?>
              <div class="sales-count">已报名 <?= (int)$product['sales'] ?> 人</div>
            </div>
            <button class="btn-enroll">立即报名</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>

<!-- 底部 -->
<footer class="site-footer">
  <p>© <?= date('Y') ?> <?= h($site_name) ?>
  <?php $icp = setting('site_icp'); if ($icp): ?>
    &nbsp;|&nbsp; <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><?= h($icp) ?></a>
  <?php endif; ?>
  </p>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
