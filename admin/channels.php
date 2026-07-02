<?php
/**
 * 支付渠道管理
 */

$page_title  = '支付渠道配置';
$active_menu = 'channels';

require_once __DIR__ . '/includes/header.php';

// 各渠道必备字段（即使 DB 里 config 尚无此键，也强制显示/保存，方便老库补齐新字段如微信退款证书）
$required_fields = [
    'alipay' => ['app_id','private_key','alipay_public_key','sandbox','notify_url','return_url'],
    'wechat' => ['mch_id','api_key','app_id','notify_url','cert_path','key_path'],
    'tenpay' => ['partner','key','notify_url','return_url'],
];

// ── 保存渠道配置 ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    abort_csrf();

    $channel_id = (int)post('channel_id');
    $status     = (int)post('status');

    // 提取该渠道的所有配置字段
    $channel = DB::queryOne('SELECT * FROM payment_channels WHERE id=?', [$channel_id]);
    if ($channel) {
        $old_cfg = json_decode($channel['config'] ?? '{}', true) ?: [];
        // 已有键 ∪ 该渠道必备键：保证新增字段（如微信 key_path）也能保存
        $fields  = array_unique(array_merge(
            array_keys($old_cfg),
            $required_fields[$channel['code']] ?? []
        ));
        $new_cfg = [];
        foreach ($fields as $field) {
            $new_cfg[$field] = post('cfg_' . $field);
        }
        DB::execute(
            'UPDATE payment_channels SET status=?, config=? WHERE id=?',
            [$status, json_encode($new_cfg, JSON_UNESCAPED_UNICODE), $channel_id]
        );

        // 可选：如果是 Ajax 开关请求
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        flash('success', '渠道配置已保存');
    }
    redirect('/admin/channels.php');
}

$channels = DB::query('SELECT * FROM payment_channels ORDER BY sort_order ASC');


// 各渠道的字段说明
$field_labels = [
    // 支付宝
    'app_id'           => ['label' => 'App ID', 'type' => 'text', 'placeholder' => '支付宝开放平台 AppID'],
    'private_key'      => ['label' => '应用私钥', 'type' => 'textarea', 'placeholder' => 'RSA2 私钥内容（不含头尾行）'],
    'alipay_public_key'=> ['label' => '支付宝公钥', 'type' => 'textarea', 'placeholder' => '在支付宝开放平台获取'],
    'sandbox'          => ['label' => '沙箱模式', 'type' => 'select', 'options' => ['0'=>'关闭（正式）','1'=>'开启（测试）']],
    'notify_url'       => ['label' => '异步通知地址', 'type' => 'text', 'placeholder' => 'https://yourdomain.com/notify/alipay.php'],
    'return_url'       => ['label' => '同步跳转地址', 'type' => 'text', 'placeholder' => 'https://yourdomain.com/result.php'],
    // 微信
    'mch_id'           => ['label' => '商户号 (mch_id)', 'type' => 'text', 'placeholder' => '微信支付商户号'],
    'api_key'          => ['label' => 'API 密钥 (V2)', 'type' => 'text', 'placeholder' => '32位字符串'],
    'api_v3_key'       => ['label' => 'API V3 密钥', 'type' => 'text', 'placeholder' => '32位字符串'],
    'cert_path'        => ['label' => '退款证书 apiclient_cert.pem 路径', 'type' => 'text', 'placeholder' => '服务器绝对路径，如 /www/certs/apiclient_cert.pem（仅退款需要）'],
    'key_path'         => ['label' => '退款私钥 apiclient_key.pem 路径', 'type' => 'text', 'placeholder' => '服务器绝对路径，如 /www/certs/apiclient_key.pem（仅退款需要）'],
    // 财付通
    'partner'          => ['label' => '商户号 (partner)', 'type' => 'text', 'placeholder' => '财付通商户号'],
    'key'              => ['label' => 'API 密钥', 'type' => 'text', 'placeholder' => '财付通 API Key'],
];
?>

<div class="alert alert-info">
  💡 <strong>接入说明：</strong>本系统已内置支付逻辑（纯 PHP，无需安装 SDK 或 composer），
  填写下方参数并<strong>开启渠道</strong>即可使用。
  支付宝为电脑网站支付（RSA2 加签），微信为 Native 扫码支付（APIv2 密钥）。
  <strong>收款</strong>无需证书；<strong>微信退款</strong>需在微信商户平台下载 API 证书（apiclient_cert.pem / apiclient_key.pem）上传到服务器，并在下方填写绝对路径。
  两者都需将下方「回调地址」登记到对应平台的商户后台。
</div>

<?php foreach ($channels as $ch):
  $cfg = json_decode($ch['config'] ?? '{}', true) ?: [];
  // 已有键 ∪ 必备键：确保新增字段（如微信退款证书 key_path）也在界面显示
  foreach ($required_fields[$ch['code']] ?? [] as $rf) {
      if (!array_key_exists($rf, $cfg)) {
          $cfg[$rf] = '';
      }
  }
  $icons = ['alipay'=>'🔵','wechat'=>'🟢','tenpay'=>'🟡'];
  $icon  = $icons[$ch['code']] ?? '💳';
?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:20px;"><?= $icon ?></span>
      <span class="card-title"><?= h($ch['name']) ?></span>
      <?php if ((int)$ch['status'] === 1): ?>
        <span class="badge badge-success">已启用</span>
      <?php else: ?>
        <span class="badge badge-secondary">已关闭</span>
      <?php endif; ?>
    </div>
    <button class="btn btn-outline btn-sm channel-toggle-config"
            data-target="cfg-<?= $ch['code'] ?>">
      展开/收起配置
    </button>
  </div>

  <div id="cfg-<?= $ch['code'] ?>" style="<?= (int)$ch['status'] === 1 ? '' : '' ?>">
    <div class="card-body">
      <form method="post" action="/admin/channels.php" class="needs-loading">
        <?= csrf_field() ?>
        <input type="hidden" name="channel_id" value="<?= $ch['id'] ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">

          <div class="form-group" style="grid-column:1/-1;display:flex;align-items:center;gap:16px;
               background:#f8f9fb;border-radius:8px;padding:14px 16px;">
            <span style="font-size:14px;font-weight:600;">启用该渠道</span>
            <label class="toggle">
              <input type="checkbox" name="status" value="1"
                     <?= (int)$ch['status'] === 1 ? 'checked' : '' ?>>
              <div class="toggle-track"></div>
            </label>
            <span style="font-size:13px;color:#7f8c8d;">
              关闭后前台不显示此支付方式
            </span>
          </div>

          <?php foreach ($cfg as $field => $val):
            $meta = $field_labels[$field] ?? ['label'=>$field,'type'=>'text','placeholder'=>''];
            $field_type = $meta['type'] ?? 'text';
          ?>
          <div class="form-group" style="<?= $field_type === 'textarea' ? 'grid-column:1/-1;' : '' ?>">
            <label class="form-label"><?= h($meta['label']) ?></label>
            <?php if ($field_type === 'textarea'): ?>
              <textarea name="cfg_<?= h($field) ?>" class="form-control" rows="3"
                        placeholder="<?= h($meta['placeholder'] ?? '') ?>"><?= h($val) ?></textarea>
            <?php elseif ($field_type === 'select'): ?>
              <select name="cfg_<?= h($field) ?>" class="form-select">
                <?php foreach ($meta['options'] as $k => $v): ?>
                  <option value="<?= h($k) ?>" <?= $val == $k ? 'selected' : '' ?>><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text" name="cfg_<?= h($field) ?>" class="form-control"
                     value="<?= h($val) ?>"
                     placeholder="<?= h($meta['placeholder'] ?? '') ?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

        </div>

        <button type="submit" class="btn btn-primary">保存 <?= h($ch['name']) ?> 配置</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- 回调地址参考 -->
<div class="card">
  <div class="card-header"><span class="card-title">回调地址参考</span></div>
  <div class="card-body">
    <p style="font-size:13px;color:#7f8c8d;margin-bottom:12px;">将以下地址填写到对应平台的异步通知配置中：</p>
    <?php $base = APP_URL; ?>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php foreach ([
          '支付宝异步通知' => $base . '/notify/alipay.php',
          '微信支付回调'   => $base . '/notify/wechat.php',
          '支付结果跳转'   => $base . '/result.php',
      ] as $label => $url): ?>
      <div style="display:flex;align-items:center;gap:10px;background:#f8f9fb;border-radius:6px;padding:10px 14px;">
        <span style="font-size:13px;color:#7f8c8d;min-width:120px;"><?= h($label) ?></span>
        <code style="font-size:13px;color:#1a5276;flex:1;"><?= h($url) ?></code>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
