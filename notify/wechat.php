<?php
/**
 * 微信支付回调接收（Native V2，XML + MD5 验签）
 *
 * 幂等与异常到账策略同支付宝回调：
 *   原子 UPDATE ... WHERE status=0；影响 0 行且订单已取消(status=2) 则标记 is_anomalous。
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment.php';

/** 统一的 XML 响应（微信要求返回 SUCCESS/FAIL 决定是否重推） */
function wx_reply(string $code, string $msg): never
{
    header('Content-Type: text/xml; charset=utf-8');
    echo '<xml><return_code><![CDATA[' . $code . ']]></return_code>'
       . '<return_msg><![CDATA[' . $msg . ']]></return_msg></xml>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$cfg = channel_config('wechat');
if (empty($cfg['mch_id']) || empty($cfg['api_key'])) {
    error_log('[WechatPay Notify] 渠道未配置');
    wx_reply('FAIL', '未配置');
}

// 读取原始请求体
$raw = file_get_contents('php://input');
if (empty($raw)) {
    wx_reply('FAIL', '空报文');
}

$data = wechat_xml_to_array($raw);

// ── 1. 通信标识与验签（MD5）──────────────────────────
if (($data['return_code'] ?? '') !== 'SUCCESS' || ($data['result_code'] ?? '') !== 'SUCCESS') {
    error_log('[WechatPay Notify] 支付未成功 return=' . ($data['return_code'] ?? '') . ' result=' . ($data['result_code'] ?? ''));
    wx_reply('SUCCESS', 'OK');  // 非成功通知也告知已收，避免重推
}
if (!wechat_verify($data, $cfg['api_key'])) {
    error_log('[WechatPay Notify] 签名验证失败 out_trade_no=' . ($data['out_trade_no'] ?? ''));
    wx_reply('FAIL', '签名失败');
}
// 校验商户号，防止串号
if (($data['mch_id'] ?? '') !== $cfg['mch_id']) {
    error_log('[WechatPay Notify] mch_id 不匹配');
    wx_reply('FAIL', '商户号不匹配');
}

// ── 2. 业务处理 ──────────────────────────────────────
$out_trade_no   = $data['out_trade_no']   ?? '';
$transaction_id = $data['transaction_id'] ?? '';
$amount_total   = (int)($data['total_fee'] ?? 0) / 100;  // 分转元

error_log('[WechatPay Notify] out_trade_no=' . $out_trade_no . ' txn=' . $transaction_id);

if ($out_trade_no) {
    $order = DB::queryOne(
        'SELECT id, amount, status FROM orders WHERE order_no = ?',
        [$out_trade_no]
    );

    if ($order) {
        if (abs($amount_total - (float)$order['amount']) >= 0.01) {
            error_log('[WechatPay Notify] 金额不一致：expected=' . $order['amount'] . ' got=' . $amount_total);
            wx_reply('SUCCESS', 'OK');  // 阻止重推，留人工核对
        }

        $affected = DB::execute(
            'UPDATE orders SET status=1, trade_no=?, pay_time=NOW(),
             notify_data=?, updated_at=NOW() WHERE order_no = ? AND status=0',
            [$transaction_id, $raw, $out_trade_no]
        );

        if ($affected === 1) {
            error_log('[WechatPay Notify] 订单已入账 ' . $out_trade_no);
        } else {
            $fresh = DB::queryOne('SELECT status FROM orders WHERE order_no = ?', [$out_trade_no]);
            if ($fresh && (int)$fresh['status'] === 2) {
                DB::execute(
                    'UPDATE orders SET is_anomalous=1, trade_no=?, notify_data=?, updated_at=NOW()
                     WHERE order_no = ? AND is_anomalous=0',
                    [$transaction_id, $raw, $out_trade_no]
                );
                error_log('[WechatPay Notify] 异常到账：订单已取消却收到付款，已标记 ' . $out_trade_no);
            }
        }
    } else {
        error_log('[WechatPay Notify] 订单不存在 ' . $out_trade_no);
    }
}

wx_reply('SUCCESS', 'OK');
