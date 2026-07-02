<?php
/**
 * 订单管理
 */

$page_title  = '订单管理';
$active_menu = 'orders';

require_once __DIR__ . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/payment.php';

// 懒清理：进入订单页时先关闭超时未支付订单
expire_stale_orders();

// ── 手动标记已支付（测试/对账用）/ 清除异常标记 / 真退款 ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    abort_csrf();
    $order_id = (int)post('order_id');
    $action   = post('action');

    if ($order_id > 0 && $action === 'clear_anomaly') {
        // 管理员已人工核对异常到账，清除标记
        DB::execute('UPDATE orders SET is_anomalous=0, updated_at=NOW() WHERE id=?', [$order_id]);
        flash('success', '异常标记已清除');
    } elseif ($order_id > 0 && $action === 'refund') {
        // ── 真退款：调支付宝/微信接口把钱退回买家 ──
        do_refund($order_id);
    } else {
        $new_status = (int)post('new_status');
        if ($order_id > 0 && in_array($new_status, [1, 2, 3], true)) {
            $set = ['status=?', 'updated_at=NOW()'];
            $params = [$new_status];
            if ($new_status === 1) {
                $set[] = 'pay_time=NOW()';
            }
            $params[] = $order_id;
            DB::execute('UPDATE orders SET ' . implode(',', $set) . ' WHERE id=?', $params);
            flash('success', '订单状态已更新');
        }
    }
    redirect('/admin/orders.php' . (get_param('status') !== '' ? '?status=' . (int)get_param('status') : ''));
}

/**
 * 执行真退款：校验 → 按渠道调接口 → 成功后累计已退金额、写退款字段、全退则置状态3
 * 结果通过 flash 提示。
 */
function do_refund(int $order_id): void
{
    $order = DB::queryOne('SELECT * FROM orders WHERE id=?', [$order_id]);
    if (!$order) {
        flash('danger', '订单不存在');
        return;
    }
    if ((int)$order['status'] !== 1) {
        flash('danger', '仅「已支付」订单可退款');
        return;
    }

    $amount   = (float)$order['amount'];
    $refunded = (float)($order['refunded_amount'] ?? 0);
    $can_max  = round($amount - $refunded, 2);   // 剩余可退
    if ($can_max <= 0) {
        flash('danger', '该订单已全额退款，无可退余额');
        return;
    }

    // 退款金额：留空或 <=0 视为全额退剩余
    $req = trim((string)post('refund_amount'));
    $refund_amt = ($req === '') ? $can_max : round((float)$req, 2);
    if ($refund_amt <= 0) {
        flash('danger', '退款金额必须大于 0');
        return;
    }
    if ($refund_amt > $can_max) {
        flash('danger', '退款金额超过可退余额（最多可退 ¥' . number_format($can_max, 2) . '）');
        return;
    }

    $cfg = channel_config($order['channel']);
    if (empty($cfg)) {
        flash('danger', '支付渠道未配置，无法退款');
        return;
    }

    // 商户退款单号：订单号 + R + 时间尾，保证同一订单多次部分退款唯一
    $refund_no = 'R' . $order['order_no'] . date('His');

    if ($order['channel'] === 'alipay') {
        $res = alipay_refund($cfg, $order['order_no'], $refund_amt, $refund_no);
    } elseif ($order['channel'] === 'wechat') {
        $res = wechat_refund($cfg, $order['order_no'], $amount, $refund_amt, $refund_no);
    } else {
        flash('danger', '该渠道（' . $order['channel'] . '）暂不支持在线退款');
        return;
    }

    if (empty($res['success'])) {
        flash('danger', '退款失败：' . ($res['error'] ?? '未知错误'));
        return;
    }

    // 成功：累计已退金额，全额退完则状态置 3（已退款）
    $new_refunded = round($refunded + $refund_amt, 2);
    $new_status   = ($new_refunded >= $amount - 0.001) ? 3 : (int)$order['status'];
    DB::execute(
        'UPDATE orders SET refunded_amount=?, refund_no=?, refund_time=NOW(),
         refund_data=?, status=?, updated_at=NOW() WHERE id=?',
        [$new_refunded, $refund_no, ($res['raw'] ?? ''), $new_status, $order_id]
    );

    $tip = '退款成功 ¥' . number_format($refund_amt, 2);
    if ($new_status !== 3) {
        $tip .= '（部分退款，累计已退 ¥' . number_format($new_refunded, 2) . ' / ¥' . number_format($amount, 2) . '）';
    }
    flash('success', $tip);
}

// ── 筛选 ──────────────────────────────────────────────
$filter_status  = get_param('status', '');
$filter_keyword = get_param('q', '');
$filter_anomaly = get_param('anomaly', '');
$page           = max(1, (int)get_param('page', '1'));
$per_page       = 20;

$where  = ['1=1'];
$params = [];

if ($filter_status !== '') {
    $where[]  = 'status = ?';
    $params[] = (int)$filter_status;
}

if ($filter_anomaly === '1') {
    $where[] = 'is_anomalous = 1';
}

if ($filter_keyword !== '') {
    $where[]  = '(order_no LIKE ? OR buyer_name LIKE ? OR buyer_phone LIKE ? OR product_name LIKE ?)';
    $kw = '%' . $filter_keyword . '%';
    $params = array_merge($params, [$kw, $kw, $kw, $kw]);
}

$where_sql = implode(' AND ', $where);

$total = (int)(DB::queryOne(
    "SELECT COUNT(*) AS n FROM orders WHERE $where_sql", $params
)['n'] ?? 0);

$total_pages = (int)ceil($total / $per_page);
$offset      = ($page - 1) * $per_page;

$orders = DB::query(
    "SELECT * FROM orders WHERE $where_sql ORDER BY id DESC LIMIT $per_page OFFSET $offset",
    $params
);

// ── 汇总 ──────────────────────────────────────────────
$summary = DB::queryOne(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM orders WHERE status=1"
);
$anomaly_count = (int)(DB::queryOne(
    'SELECT COUNT(*) AS n FROM orders WHERE is_anomalous=1'
)['n'] ?? 0);
?>

<!-- 筛选栏 -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-body" style="padding:14px 20px;">
    <form method="get" action="/admin/orders.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <div>
        <label style="font-size:12px;color:#7f8c8d;display:block;margin-bottom:4px;">状态</label>
        <select name="status" class="form-select" style="width:120px;" onchange="this.form.submit()">
          <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>全部</option>
          <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>待支付</option>
          <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>已支付</option>
          <option value="2" <?= $filter_status === '2' ? 'selected' : '' ?>>已取消</option>
          <option value="3" <?= $filter_status === '3' ? 'selected' : '' ?>>已退款</option>
        </select>
      </div>
      <div>
        <label style="font-size:12px;color:#7f8c8d;display:block;margin-bottom:4px;">搜索</label>
        <input type="text" name="q" class="form-control" style="width:220px;"
               value="<?= h($filter_keyword) ?>" placeholder="订单号/姓名/手机/课程">
      </div>
      <button type="submit" class="btn btn-primary">搜索</button>
      <?php if ($anomaly_count > 0): ?>
        <a href="/admin/orders.php?anomaly=1"
           class="btn <?= $filter_anomaly === '1' ? 'btn-danger' : 'btn-outline' ?>"
           style="<?= $filter_anomaly === '1' ? '' : 'color:#c0392b;border-color:#e6b0aa;' ?>">
          ⚠️ 到账异常 <?= $anomaly_count ?>
        </a>
      <?php endif; ?>
      <?php if ($filter_status !== '' || $filter_keyword !== '' || $filter_anomaly !== ''): ?>
        <a href="/admin/orders.php" class="btn btn-outline">清除筛选</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- 汇总 -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
  <div style="background:#e8f8f0;border-radius:8px;padding:12px 20px;font-size:14px;color:#1e8449;">
    已支付：<strong><?= number_format((int)$summary['cnt']) ?> 笔</strong>，
    共 <strong>¥<?= number_format((float)$summary['total'],2) ?></strong>
  </div>
  <div style="background:#fff8e1;border-radius:8px;padding:12px 20px;font-size:14px;color:#9a7d0a;">
    共 <strong><?= $total ?></strong> 笔订单（当前筛选）
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">订单列表</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>订单号</th>
          <th>课程</th>
          <th>金额</th>
          <th>报名人</th>
          <th>手机</th>
          <th>渠道</th>
          <th>状态</th>
          <th>创建时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
        <tr><td colspan="9" style="text-align:center;padding:32px;color:#bdc3c7;">暂无订单</td></tr>
        <?php else: ?>
        <?php foreach ($orders as $o): ?>
        <?php $is_anom = (int)($o['is_anomalous'] ?? 0) === 1; ?>
        <tr<?= $is_anom ? ' style="background:#fdecea;"' : '' ?>>
          <td style="font-family:monospace;font-size:12px;"><?= h($o['order_no']) ?></td>
          <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
              title="<?= h($o['product_name']) ?>"><?= h(mb_substr($o['product_name'],0,12)) ?></td>
          <td style="color:var(--accent);font-weight:600;">¥<?= number_format((float)$o['amount'],2) ?></td>
          <td><?= h($o['buyer_name']) ?></td>
          <td><?= h($o['buyer_phone']) ?></td>
          <td><?= h($o['channel']) ?></td>
          <td>
            <?= order_status_badge((int)$o['status']) ?>
            <?php if ($is_anom): ?>
              <span class="badge badge-danger" title="付款已到账，但订单当时非待支付态，请人工核对是否需退款或补单">⚠️ 到账异常</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#7f8c8d;white-space:nowrap;"><?= h(substr($o['created_at'],0,16)) ?></td>
          <td>
            <!-- 快捷改状态 -->
            <?php if ((int)$o['status'] === 0): ?>
            <form method="post" action="/admin/orders.php" style="display:inline;"
                  onsubmit="return confirm('确认手动标记此订单为已支付？')">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <input type="hidden" name="new_status" value="1">
              <button type="submit" class="btn btn-success btn-sm">标记已付</button>
            </form>
            <?php elseif ((int)$o['status'] === 1): ?>
            <?php
              $refunded = (float)($o['refunded_amount'] ?? 0);
              $can_max  = round((float)$o['amount'] - $refunded, 2);
            ?>
            <form method="post" action="/admin/orders.php" style="display:inline;"
                  onsubmit="var v=this.refund_amount.value.trim();
                            if(v!=='' && (isNaN(v)||parseFloat(v)<=0)){alert('请输入正确的退款金额');return false;}
                            if(parseFloat(v||<?= $can_max ?>)><?= $can_max ?>){alert('超过可退余额 ¥<?= number_format($can_max,2) ?>');return false;}
                            return confirm('确认对该订单退款 ¥'+(v===''?'<?= number_format($can_max,2) ?>（全部剩余）':v)+'？款项将退回买家原账户。');">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <input type="hidden" name="action" value="refund">
              <input type="number" name="refund_amount" step="0.01" min="0.01" max="<?= $can_max ?>"
                     placeholder="<?= number_format($can_max,2) ?>" title="留空=退全部剩余"
                     style="width:78px;padding:4px 6px;border:1px solid var(--border);border-radius:4px;font-size:12px;">
              <button type="submit" class="btn btn-danger btn-sm">退款</button>
            </form>
            <?php if ($refunded > 0): ?>
              <div style="font-size:11px;color:#c0392b;margin-top:3px;">已退 ¥<?= number_format($refunded,2) ?></div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($is_anom): ?>
            <form method="post" action="/admin/orders.php" style="display:inline;"
                  onsubmit="return confirm('确认已人工核对此笔到账异常（如已退款/补单）？将清除异常标记。')">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <input type="hidden" name="action" value="clear_anomaly">
              <button type="submit" class="btn btn-outline btn-sm">已处理</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php if (!empty($o['buyer_remark'])): ?>
        <tr style="background:#fafbfc;">
          <td colspan="9" style="font-size:12px;color:#7f8c8d;padding-top:4px;padding-left:24px;">
            💬 备注：<?= h($o['buyer_remark']) ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- 分页 -->
  <?php if ($total_pages > 1): ?>
  <div style="padding:16px 20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?status=<?= h($filter_status) ?>&q=<?= urlencode($filter_keyword) ?>&anomaly=<?= h($filter_anomaly) ?>&page=<?= $i ?>"
         class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
    <span style="font-size:12px;color:#7f8c8d;margin-left:8px;">共 <?= $total ?> 条</span>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
