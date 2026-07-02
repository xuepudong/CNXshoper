<?php
/**
 * 通用辅助函数
 */

require_once __DIR__ . '/db.php';

// ── 配置读取 ─────────────────────────────────────────

function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = DB::queryOne('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
        $cache[$key] = $row ? (string)$row['value'] : $default;
    }
    return $cache[$key];
}

function settings_all(): array
{
    $rows = DB::query('SELECT `key`, `value` FROM settings');
    $map  = [];
    foreach ($rows as $r) {
        $map[$r['key']] = $r['value'];
    }
    return $map;
}

// ── 安全 ─────────────────────────────────────────────

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals(csrf_token(), $token);
}

function abort_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        exit('请求已过期，请刷新页面重试。');
    }
}

// ── 输入处理 ─────────────────────────────────────────

function input(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}

function post(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

function get_param(string $key, string $default = ''): string
{
    return trim($_GET[$key] ?? $default);
}

// ── 订单号生成 ────────────────────────────────────────

function generate_order_no(): string
{
    $prefix = setting('order_prefix', 'HX');
    return $prefix . date('YmdHis') . strtoupper(substr(uniqid('', true), -6));
}

// ── 格式化金额 ────────────────────────────────────────

function format_price(float|string $price, bool $show_symbol = true): string
{
    $formatted = number_format((float)$price, 2);
    return $show_symbol ? '¥' . $formatted : $formatted;
}

// ── 订单状态 ──────────────────────────────────────────

function order_status_label(int $status): string
{
    return match ($status) {
        0 => '待支付',
        1 => '已支付',
        2 => '已取消',
        3 => '已退款',
        default => '未知',
    };
}

function order_status_badge(int $status): string
{
    $class = match ($status) {
        0 => 'warning',
        1 => 'success',
        2 => 'secondary',
        3 => 'info',
        default => 'dark',
    };
    return '<span class="badge badge-' . $class . '">' . order_status_label($status) . '</span>';
}

/**
 * 将超过有效期仍未支付的订单置为「已取消」
 * 懒清理：由后台首页、支付页等入口调用即可，无需额外定时任务
 * 返回被取消的订单数
 */
function expire_stale_orders(): int
{
    $minutes = max(1, (int)setting('order_expire', '30'));
    return DB::execute(
        'UPDATE orders
            SET status = 2, updated_at = NOW()
          WHERE status = 0
            AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)',
        [$minutes]
    );
}

// ── 支付渠道 ──────────────────────────────────────────

function get_active_channels(): array
{
    return DB::query(
        'SELECT id, name, code, icon FROM payment_channels WHERE status = 1 ORDER BY sort_order ASC'
    );
}

function get_channel(string $code): array|false
{
    return DB::queryOne(
        'SELECT * FROM payment_channels WHERE code = ? AND status = 1',
        [$code]
    );
}

function channel_config(string $code): array
{
    $row = DB::queryOne('SELECT config FROM payment_channels WHERE code = ?', [$code]);
    if (!$row || empty($row['config'])) {
        return [];
    }
    return json_decode($row['config'], true) ?: [];
}

// ── 商品 ──────────────────────────────────────────────

function get_products(bool $only_active = true): array
{
    $sql = 'SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id';
    if ($only_active) {
        $sql .= ' WHERE p.status = 1';
    }
    $sql .= ' ORDER BY p.sort_order ASC, p.id ASC';
    return DB::query($sql);
}

function get_product(int $id, bool $only_active = true): array|false
{
    $sql = 'SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ?';
    if ($only_active) {
        $sql .= ' AND p.status = 1';
    }
    return DB::queryOne($sql, [$id]);
}

// ── 客户端 IP ─────────────────────────────────────────

function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// ── 跳转 ──────────────────────────────────────────────

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ── 产品图片 ──────────────────────────────────────────

function product_image(array $product, string $size = 'normal'): string
{
    if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
        return UPLOAD_URL . h($product['image']);
    }
    // 按分类返回占位色
    $colors = ['1a5276', '1e8449', '7d6608', '6c3483', '0e6655'];
    $color  = $colors[$product['id'] % count($colors)];
    $label  = urlencode(mb_substr($product['name'], 0, 4));
    return 'https://placehold.co/400x260/' . $color . '/ffffff?text=' . $label;
}

// ── Flash 消息 ─────────────────────────────────────────

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = compact('type', 'message');
}

function get_flash(): array|null
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
