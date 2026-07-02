<?php
/**
 * 支付核心：签名、验签、下单请求
 *
 * 纯 PHP 实现（依赖 openssl / curl 扩展，宝塔 PHP 8.2 默认开启），
 * 不需要 composer / vendor。
 *
 *   - 支付宝：电脑网站支付 alipay.trade.page.pay，RSA2(SHA256) 签名与验签
 *   - 微信：  Native V2 统一下单，MD5 签名与验签
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/* =====================================================
 * 通用工具
 * ===================================================== */

/**
 * 发送 HTTP POST 请求
 * @param string $url
 * @param string $body      原始请求体（表单串或 XML）
 * @param array  $headers   额外请求头
 * @param array  $cert      可选双向证书 ['cert'=>证书路径, 'key'=>私钥路径]（微信退款必需）
 * @return string|false     响应体，失败返回 false
 */
function http_post_raw(string $url, string $body, array $headers = [], array $cert = []): string|false
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    // 双向证书（微信退款等安全接口要求 PEM 客户端证书）
    if (!empty($cert['cert']) && !empty($cert['key'])) {
        $opts[CURLOPT_SSLCERT] = $cert['cert'];
        $opts[CURLOPT_SSLKEY]  = $cert['key'];
        $opts[CURLOPT_SSLCERTTYPE] = 'PEM';
        $opts[CURLOPT_SSLKEYTYPE]  = 'PEM';
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    if ($resp === false) {
        error_log('[http_post_raw] curl error: ' . curl_error($ch) . ' url=' . $url);
    }
    curl_close($ch);
    return $resp;
}

/* =====================================================
 * 支付宝：电脑网站支付（RSA2）
 * ===================================================== */

/**
 * 将 PEM 私钥内容（可能不含头尾行）规整为 openssl 可用的 PEM 格式
 */
function alipay_format_key(string $key, bool $is_public): string
{
    $key = trim($key);
    // 已是完整 PEM，直接返回
    if (str_contains($key, '-----BEGIN')) {
        return $key;
    }
    // 去除可能存在的空白，按 64 字符换行重排
    $key = preg_replace('/\s+/', '', $key);
    $wrapped = chunk_split($key, 64, "\n");
    if ($is_public) {
        return "-----BEGIN PUBLIC KEY-----\n{$wrapped}-----END PUBLIC KEY-----";
    }
    return "-----BEGIN PRIVATE KEY-----\n{$wrapped}-----END PRIVATE KEY-----";
}

/**
 * 构造待签名字符串：按参数名 ASCII 升序，key=value 用 & 连接（不含空值/sign）
 *
 * 支付宝有个不对称约定：
 *   - 发起请求签名时，sign_type 要算进待签串（官方 SDK getSignContent 只跳空值，不跳 sign_type）
 *   - 验证异步通知时，sign_type 要排除（官方 rsaCheckV1 会 unset sign_type）
 * 用 $exclude_sign_type 区分两种场景，否则两边算出的串不一致 → invalid-signature。
 */
function alipay_build_sign_content(array $params, bool $exclude_sign_type = false): string
{
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null || $k === 'sign') {
            continue;
        }
        if ($exclude_sign_type && $k === 'sign_type') {
            continue;
        }
        $pairs[] = $k . '=' . $v;
    }
    return implode('&', $pairs);
}

/**
 * RSA2 签名，返回 base64
 */
function alipay_sign(array $params, string $private_key): string
{
    // 请求签名：包含 sign_type（与网关验签口径一致）
    $content = alipay_build_sign_content($params, false);
    $pem     = alipay_format_key($private_key, false);
    $res     = openssl_pkey_get_private($pem);
    if ($res === false) {
        error_log('[alipay_sign] 私钥无效: ' . openssl_error_string());
        return '';
    }
    $signature = '';
    openssl_sign($content, $signature, $res, OPENSSL_ALGO_SHA256);
    return base64_encode($signature);
}

/**
 * 验证支付宝异步通知签名
 * @param array  $params            $_POST 全部参数（含 sign / sign_type）
 * @param string $alipay_public_key 支付宝公钥
 */
function alipay_verify(array $params, string $alipay_public_key): bool
{
    if (empty($params['sign'])) {
        return false;
    }
    $sign    = $params['sign'];
    // 验证异步通知：排除 sign_type（与支付宝 rsaCheckV1 口径一致）
    $content = alipay_build_sign_content($params, true);
    $pem     = alipay_format_key($alipay_public_key, true);
    $res     = openssl_pkey_get_public($pem);
    if ($res === false) {
        error_log('[alipay_verify] 公钥无效: ' . openssl_error_string());
        return false;
    }
    $ok = openssl_verify($content, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
    return $ok === 1;
}

/**
 * 生成电脑网站支付跳转 URL（GET 方式提交到支付宝网关）
 *
 * @param array  $cfg      渠道配置（app_id / private_key / sandbox / notify_url / return_url）
 * @param string $order_no 商户订单号
 * @param float  $amount   金额（元）
 * @param string $subject  订单标题
 * @return string 跳转 URL
 */
function alipay_page_pay_url(array $cfg, string $order_no, float $amount, string $subject): string
{
    $gateway = !empty($cfg['sandbox']) && (string)$cfg['sandbox'] === '1'
        ? 'https://openapi-sandbox.dl.alipaydev.com/gateway.do'
        : 'https://openapi.alipay.com/gateway.do';

    $biz = [
        'out_trade_no' => $order_no,
        'product_code' => 'FAST_INSTANT_TRADE_PAY',
        'total_amount' => number_format($amount, 2, '.', ''),
        'subject'      => $subject,
    ];

    $params = [
        'app_id'      => $cfg['app_id'],
        'method'      => 'alipay.trade.page.pay',
        'format'      => 'JSON',
        'charset'     => 'utf-8',
        'sign_type'   => 'RSA2',
        'timestamp'   => date('Y-m-d H:i:s'),
        'version'     => '1.0',
        'notify_url'  => $cfg['notify_url'] ?? '',
        'return_url'  => $cfg['return_url'] ?? '',
        'biz_content' => json_encode($biz, JSON_UNESCAPED_UNICODE),
    ];

    $params['sign'] = alipay_sign($params, $cfg['private_key']);

    // 网关以 GET 参数接收
    return $gateway . '?' . http_build_query($params);
}

/**
 * 支付宝退款 alipay.trade.refund（RSA2，支持部分退款）
 *
 * @param array  $cfg          渠道配置（app_id/private_key/alipay_public_key/sandbox）
 * @param string $order_no     商户订单号（out_trade_no）
 * @param float  $refund_amt   本次退款金额（元）
 * @param string $refund_no    退款请求号（同一笔交易多次部分退款需唯一）
 * @param string $reason       退款原因
 * @return array{success:bool, refund_fee?:float, raw?:string, error?:string}
 */
function alipay_refund(array $cfg, string $order_no, float $refund_amt, string $refund_no, string $reason = '后台退款'): array
{
    $gateway = !empty($cfg['sandbox']) && (string)$cfg['sandbox'] === '1'
        ? 'https://openapi-sandbox.dl.alipaydev.com/gateway.do'
        : 'https://openapi.alipay.com/gateway.do';

    $biz = [
        'out_trade_no'   => $order_no,
        'refund_amount'  => number_format($refund_amt, 2, '.', ''),
        'out_request_no' => $refund_no,
        'refund_reason'  => $reason,
    ];

    $params = [
        'app_id'      => $cfg['app_id'],
        'method'      => 'alipay.trade.refund',
        'format'      => 'JSON',
        'charset'     => 'utf-8',
        'sign_type'   => 'RSA2',
        'timestamp'   => date('Y-m-d H:i:s'),
        'version'     => '1.0',
        'biz_content' => json_encode($biz, JSON_UNESCAPED_UNICODE),
    ];
    $params['sign'] = alipay_sign($params, $cfg['private_key']);

    $resp = http_post_raw($gateway, http_build_query($params), [
        'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
    ]);
    if ($resp === false) {
        return ['success' => false, 'error' => '网络请求失败，请稍后重试'];
    }

    $json = json_decode($resp, true);
    // 响应节点名固定为 alipay_trade_refund_response
    $node = $json['alipay_trade_refund_response'] ?? null;
    if (!is_array($node)) {
        return ['success' => false, 'error' => '退款响应解析失败', 'raw' => $resp];
    }
    // code=10000 为成功
    if (($node['code'] ?? '') !== '10000') {
        $msg = ($node['sub_msg'] ?? $node['msg'] ?? '退款失败');
        error_log('[alipay_refund] 失败 code=' . ($node['code'] ?? '') . ' sub=' . ($node['sub_code'] ?? '') . ' msg=' . $msg);
        return ['success' => false, 'error' => $msg, 'raw' => $resp];
    }
    // fund_change=Y 表示本次确实发生资金退回；重复退款 fund_change 可能为 N
    return [
        'success'    => true,
        'refund_fee' => (float)($node['refund_fee'] ?? $refund_amt),
        'raw'        => $resp,
    ];
}

/* =====================================================
 * 微信支付：Native V2 统一下单（MD5）
 * ===================================================== */

/** 数组转 XML（微信 V2 请求体） */
function wechat_array_to_xml(array $data): string
{
    $xml = '<xml>';
    foreach ($data as $k => $v) {
        // 数值直接写，字符串用 CDATA 包裹
        if (is_numeric($v)) {
            $xml .= "<{$k}>{$v}</{$k}>";
        } else {
            $xml .= "<{$k}><![CDATA[{$v}]]></{$k}>";
        }
    }
    return $xml . '</xml>';
}

/** XML 转数组（微信 V2 响应/回调体）；默认禁用外部实体防 XXE
 *
 * 手动遍历并强制 (string) 转换：微信 V2 响应是单层扁平结构，
 * 空标签 <x></x> 必须归一化为 ''（而非 json 链路产生的空数组 []），
 * 否则 wechat_sign 里 `$v===''` 判断失效，空字段混入待签串导致验签失败。
 */
function wechat_xml_to_array(string $xml): array
{
    if (trim($xml) === '') {
        return [];
    }
    // libxml 2.9+ 默认不加载外部实体；这里不使用 LIBXML_NOENT（它会启用实体替换）
    $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($obj === false) {
        return [];
    }
    $arr = [];
    foreach ($obj as $k => $v) {
        $arr[(string)$k] = (string)$v;
    }
    return $arr;
}

/**
 * 微信 V2 签名（MD5），返回大写十六进制
 * @param array  $params 业务参数（不含 sign）
 * @param string $api_key 商户 API 密钥(V2)
 */
function wechat_sign(array $params, string $api_key): string
{
    ksort($params);
    $pairs = [];
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null || $k === 'sign') {
            continue;
        }
        $pairs[] = $k . '=' . $v;
    }
    $str = implode('&', $pairs) . '&key=' . $api_key;
    return strtoupper(md5($str));
}

/**
 * 验证微信 V2 回调签名
 * @param array  $data    回调解析出的数组（含 sign）
 * @param string $api_key 商户 API 密钥(V2)
 */
function wechat_verify(array $data, string $api_key): bool
{
    if (empty($data['sign'])) {
        return false;
    }
    $expected = wechat_sign($data, $api_key);
    return hash_equals($expected, (string)$data['sign']);
}

/**
 * Native V2 统一下单，返回 code_url（供前端生成二维码）
 *
 * @param array  $cfg      渠道配置（app_id / mch_id / api_key / notify_url）
 * @param string $order_no 商户订单号
 * @param float  $amount   金额（元）
 * @param string $body      商品描述
 * @param string $client_ip 终端 IP
 * @return array{success:bool, code_url?:string, error?:string}
 */
function wechat_native_unifiedorder(array $cfg, string $order_no, float $amount, string $body, string $client_ip): array
{
    $params = [
        'appid'            => $cfg['app_id'],
        'mch_id'           => $cfg['mch_id'],
        'nonce_str'        => bin2hex(random_bytes(16)),
        'body'             => mb_substr($body, 0, 120),
        'out_trade_no'     => $order_no,
        'total_fee'        => (int) round($amount * 100),  // 单位：分
        'spbill_create_ip' => $client_ip,
        'notify_url'       => $cfg['notify_url'] ?? '',
        'trade_type'       => 'NATIVE',
    ];
    $params['sign'] = wechat_sign($params, $cfg['api_key']);

    $resp = http_post_raw(
        'https://api.mch.weixin.qq.com/pay/unifiedorder',
        wechat_array_to_xml($params),
        ['Content-Type: text/xml; charset=utf-8']
    );
    if ($resp === false) {
        return ['success' => false, 'error' => '网络请求失败，请稍后重试'];
    }

    $result = wechat_xml_to_array($resp);

    if (($result['return_code'] ?? '') !== 'SUCCESS') {
        return ['success' => false, 'error' => $result['return_msg'] ?? '通信失败'];
    }
    if (($result['result_code'] ?? '') !== 'SUCCESS') {
        error_log('[wechat_unifiedorder] ' . ($result['err_code'] ?? '') . ':' . ($result['err_code_des'] ?? ''));
        return ['success' => false, 'error' => $result['err_code_des'] ?? '下单失败'];
    }
    // 校验应答签名
    if (!wechat_verify($result, $cfg['api_key'])) {
        return ['success' => false, 'error' => '应答签名校验失败'];
    }
    if (empty($result['code_url'])) {
        return ['success' => false, 'error' => '未获取到支付二维码'];
    }
    return ['success' => true, 'code_url' => $result['code_url']];
}

/**
 * 微信退款 secapi/pay/refund（V2，MD5，需双向证书，支持部分退款）
 *
 * @param array  $cfg         渠道配置（app_id/mch_id/api_key/cert_path/key_path）
 * @param string $order_no    商户订单号（out_trade_no）
 * @param float  $total_amt   原订单总金额（元）—— 微信退款必须带原单总额
 * @param float  $refund_amt  本次退款金额（元）
 * @param string $refund_no   商户退款单号（out_refund_no，需唯一）
 * @return array{success:bool, refund_id?:string, raw?:string, error?:string}
 */
function wechat_refund(array $cfg, string $order_no, float $total_amt, float $refund_amt, string $refund_no): array
{
    // 双向证书校验：微信退款硬性要求，缺证书直接拦下并给出明确提示
    $cert = $cfg['cert_path'] ?? '';
    $key  = $cfg['key_path']  ?? '';
    if ($cert === '' || $key === '') {
        return ['success' => false, 'error' => '微信退款需要 API 证书，请先在渠道配置里填写证书路径（cert_path/key_path）'];
    }
    if (!is_file($cert) || !is_file($key)) {
        return ['success' => false, 'error' => '微信 API 证书文件不存在，请检查服务器上的证书路径是否正确'];
    }

    $params = [
        'appid'         => $cfg['app_id'],
        'mch_id'        => $cfg['mch_id'],
        'nonce_str'     => bin2hex(random_bytes(16)),
        'out_trade_no'  => $order_no,
        'out_refund_no' => $refund_no,
        'total_fee'     => (int) round($total_amt * 100),   // 原单总额，单位：分
        'refund_fee'    => (int) round($refund_amt * 100),  // 本次退款，单位：分
    ];
    $params['sign'] = wechat_sign($params, $cfg['api_key']);

    $resp = http_post_raw(
        'https://api.mch.weixin.qq.com/secapi/pay/refund',
        wechat_array_to_xml($params),
        ['Content-Type: text/xml; charset=utf-8'],
        ['cert' => $cert, 'key' => $key]
    );
    if ($resp === false) {
        return ['success' => false, 'error' => '网络请求失败，请检查证书或稍后重试'];
    }

    $result = wechat_xml_to_array($resp);

    if (($result['return_code'] ?? '') !== 'SUCCESS') {
        return ['success' => false, 'error' => $result['return_msg'] ?? '通信失败', 'raw' => $resp];
    }
    if (($result['result_code'] ?? '') !== 'SUCCESS') {
        error_log('[wechat_refund] ' . ($result['err_code'] ?? '') . ':' . ($result['err_code_des'] ?? ''));
        return ['success' => false, 'error' => $result['err_code_des'] ?? '退款失败', 'raw' => $resp];
    }
    // 校验应答签名
    if (!wechat_verify($result, $cfg['api_key'])) {
        return ['success' => false, 'error' => '应答签名校验失败', 'raw' => $resp];
    }
    return [
        'success'   => true,
        'refund_id' => $result['refund_id'] ?? '',
        'raw'       => $resp,
    ];
}
