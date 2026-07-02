<?php
/**
 * 管理后台首页 — 数据概览
 */

$page_title  = '控制台';
$active_menu = 'dashboard';

require_once __DIR__ . '/includes/header.php';

// 懒清理：将超时未支付订单置为已取消，保证统计口径准确
expire_stale_orders();

// ── 统计数据 ──────────────────────────────────────────
$total_orders   = DB::queryOne('SELECT COUNT(*) AS n FROM orders')['n'] ?? 0;
$paid_orders    = DB::queryOne('SELECT COUNT(*) AS n FROM orders WHERE status=1')['n'] ?? 0;
$total_revenue  = DB::queryOne('SELECT COALESCE(SUM(amount),0) AS s FROM orders WHERE status=1')['s'] ?? 0;
$today_revenue  = DB::queryOne(
    'SELECT COALESCE(SUM(amount),0) AS s FROM orders WHERE status=1 AND DATE(pay_time)=CURDATE()'
)['s'] ?? 0;
$pending_orders = DB::queryOne('SELECT COUNT(*) AS n FROM orders WHERE status=0')['n'] ?? 0;
$product_count  = DB::queryOne('SELECT COUNT(*) AS n FROM products WHERE status=1')['n'] ?? 0;

// 最近7天收入（折线图数据）
$week_data = DB::query(
    'SELECT DATE(pay_time) AS d, SUM(amount) AS total
     FROM orders
     WHERE status=1 AND pay_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(pay_time)
     ORDER BY d ASC'
);

// 最近10笔订单
$recent_orders = DB::query(
    'SELECT order_no, product_name, amount, channel, buyer_name, buyer_phone, status, created_at
     FROM orders ORDER BY id DESC LIMIT 10'
);
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📦</div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format((int)$total_orders) ?></div>
      <div class="stat-label">累计订单</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format((int)$paid_orders) ?></div>
      <div class="stat-label">已支付订单</div>
    </div>
  </div>
  <div class="stat-card accent">
    <div class="stat-icon">💰</div>
    <div class="stat-body">
      <div class="stat-value">¥<?= number_format((float)$total_revenue, 0) ?></div>
      <div class="stat-label">累计收款</div>
    </div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon">📅</div>
    <div class="stat-body">
      <div class="stat-value">¥<?= number_format((float)$today_revenue, 0) ?></div>
      <div class="stat-label">今日收款</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

  <!-- 最近订单 -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">最近订单</span>
      <a href="/admin/orders.php" class="btn btn-outline btn-sm">查看全部</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>订单号</th>
            <th>课程</th>
            <th>金额</th>
            <th>姓名</th>
            <th>渠道</th>
            <th>状态</th>
            <th>时间</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_orders)): ?>
          <tr><td colspan="7" style="text-align:center;color:#bdc3c7;padding:24px;">暂无订单</td></tr>
          <?php else: ?>
          <?php foreach ($recent_orders as $o): ?>
          <tr>
            <td style="font-family:monospace;font-size:12px;"><?= h(substr($o['order_no'], -10)) ?></td>
            <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= h($o['product_name']) ?>"><?= h(mb_substr($o['product_name'], 0, 12)) ?></td>
            <td style="color:var(--accent);font-weight:600;">¥<?= number_format((float)$o['amount'], 2) ?></td>
            <td><?= h($o['buyer_name']) ?></td>
            <td><?= h($o['channel']) ?></td>
            <td><?= order_status_badge((int)$o['status']) ?></td>
            <td style="color:#7f8c8d;font-size:12px;"><?= h(substr($o['created_at'], 5, 11)) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 右侧 -->
  <div>
    <!-- 待处理提示 -->
    <?php if ($pending_orders > 0): ?>
    <div class="alert alert-warning">
      ⚠️ 有 <strong><?= (int)$pending_orders ?></strong> 笔订单待支付，
      <a href="/admin/orders.php?status=0">查看详情</a>
    </div>
    <?php endif; ?>

    <!-- 快捷操作 -->
    <div class="card">
      <div class="card-header"><span class="card-title">快捷操作</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
        <a href="/admin/products.php?action=add" class="btn btn-primary">➕ 新增课程</a>
        <a href="/admin/orders.php" class="btn btn-outline">📋 查看订单</a>
        <a href="/admin/channels.php" class="btn btn-outline">💳 配置支付</a>
        <a href="/admin/settings.php" class="btn btn-outline">⚙️ 站点设置</a>
      </div>
    </div>

    <!-- 上架商品数 -->
    <div class="card" style="margin-top:20px;">
      <div class="card-header"><span class="card-title">商品状态</span></div>
      <div class="card-body">
        <div style="text-align:center;">
          <div style="font-size:36px;font-weight:800;color:var(--primary);"><?= (int)$product_count ?></div>
          <div style="font-size:13px;color:#7f8c8d;">当前上架课程</div>
        </div>
        <a href="/admin/products.php" class="btn btn-outline" style="width:100%;margin-top:12px;text-align:center;">管理课程</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
