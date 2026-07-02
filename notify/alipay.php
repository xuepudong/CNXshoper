<?php
/**
 * 支付宝异步通知接收（电脑网站支付 RSA2）
 *
 * 幂等策略：原子条件更新 UPDATE ... WHERE status=0，
 *   - 影响行数=1 → 首次到账，触发后续副作用
 *   - 影响行数=0 → 重复回调或订单非待支付态，不重复处理
 * 异常到账：付款到账但订单已被取消(status=2) → 标记 is_anomalous 供后台人工核对
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment.php';

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$cfg = channel_config('alipay');
if (empty($cfg['app_id']) || empty($cfg['alipay_public_key'])) {
    error_log('[Alipay Notify] 渠道未配置');
    exit('fail');
}

$post_data = $_POST;

// ── 1. 验签（支付宝公钥 RSA2）──────────────────────────
if (!alipay_verify($post_data, $cfg['alipay_public_key'])) {
    error_log('[Alipay Notify] 签名验证失败 out_trade_no=' . ($post_data['out_trade_no'] ?? ''));
    exit('fail');
}

// 校验 app_id 一致，防止串号
if (($post_data['app_id'] ?? '') !== $cfg['app_id']) {
    error_log('[Alipay Notify] app_id 不匹配');
    exit('fail');
}

// ── 2. 处理业务逻辑 ──────────────────────────────────
$out_trade_no = $post_data['out_trade_no'] ?? '';
$trade_no     = $post_data['trade_no']     ?? '';
$trade_status = $post_data['trade_status'] ?? '';
$total_amount = $post_data['total_amount'] ?? '0';

error_log('[Alipay Notify] out_trade_no=' . $out_trade_no . ' status=' . $trade_status);

if ($out_trade_no && in_array($trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
    $order = DB::queryOne(
        'SELECT id, amount, status FROM orders WHERE order_no = ?',
        [$out_trade_no]
    );

    if ($order) {
        // 金额校验（单位：元）
        if (abs((float)$total_amount - (float)$order['amount']) >= 0.01) {
            error_log('[Alipay Notify] 金额不一致：expected=' . $order['amount'] . ' got=' . $total_amount);
            // 金额不符，仍需回 success 阻止重推，但不入账，留待人工核对
            echo 'success';
            exit;
        }

        $notify_json = json_encode($post_data, JSON_UNESCAPED_UNICODE);

        // 原子幂等更新：仅当仍为待支付时置为已支付
        $affected = DB::execute(
            'UPDATE orders SET status=1, trade_no=?, pay_time=NOW(),
             notify_data=?, updated_at=NOW() WHERE order_no = ? AND status=0',
            [$trade_no, $notify_json, $out_trade_no]
        );

        if ($affected === 1) {
            // 首次到账，可在此触发短信/通知等副作用
            error_log('[Alipay Notify] 订单已入账 ' . $out_trade_no);
        } else {
            // 影响 0 行：重复回调，或订单已被取消/退款。区分异常到账
            $fresh = DB::queryOne('SELECT status FROM orders WHERE order_no = ?', [$out_trade_no]);
            if ($fresh && (int)$fresh['status'] === 2) {
                // 已超时取消却收到付款 → 标记异常，写入流水与原始报文，保留 status 不变
                DB::execute(
                    'UPDATE orders SET is_anomalous=1, trade_no=?, notify_data=?, updated_at=NOW()
                     WHERE order_no = ? AND is_anomalous=0',
                    [$trade_no, $notify_json, $out_trade_no]
                );
                error_log('[Alipay Notify] 异常到账：订单已取消却收到付款，已标记 ' . $out_trade_no);
            }
        }
    } else {
        error_log('[Alipay Notify] 订单不存在 ' . $out_trade_no);
    }
}

echo 'success';
exit;
