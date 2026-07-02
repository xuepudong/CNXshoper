<?php
/**
 * 商品/课程管理
 */

$page_title  = '商品/课程管理';
$active_menu = 'products';

require_once __DIR__ . '/includes/header.php';

$action = get_param('action');
$edit_id = (int)get_param('id');

// ── 保存商品 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    abort_csrf();

    $name           = post('name');
    $subtitle       = post('subtitle');
    $description    = post('description');
    $price          = (float)post('price');
    $original_price = post('original_price') !== '' ? (float)post('original_price') : null;
    $category_id    = (int)post('category_id') ?: null;
    $status         = (int)post('status');
    $sort_order     = (int)post('sort_order');
    $sales          = (int)post('sales');
    $features_raw   = post('features');

    // 特色标签：每行一个，最多6个
    $features = array_filter(array_slice(
        array_map('trim', explode("\n", $features_raw)),
        0, 6
    ));
    $features_json = json_encode(array_values($features), JSON_UNESCAPED_UNICODE);

    // 图片上传
    $image = post('current_image');
    if (!empty($_FILES['image']['tmp_name'])) {
        $file = $_FILES['image'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed, true) && $file['size'] <= UPLOAD_MAX_SIZE) {
            $filename = uniqid('prod_', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                // 删除旧图片
                if ($image && file_exists(UPLOAD_DIR . $image)) {
                    @unlink(UPLOAD_DIR . $image);
                }
                $image = $filename;
            }
        }
    }

    $errors = [];
    if (mb_strlen(trim($name)) < 2) {
        $errors[] = '商品名称至少2个字符';
    }
    if ($price <= 0) {
        $errors[] = '价格必须大于0';
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            DB::execute(
                'UPDATE products SET category_id=?, name=?, subtitle=?, description=?,
                 price=?, original_price=?, image=?, features=?, status=?,
                 sort_order=?, sales=?, updated_at=NOW() WHERE id=?',
                [$category_id, $name, $subtitle, $description, $price, $original_price,
                 $image, $features_json, $status, $sort_order, $sales, $edit_id]
            );
            flash('success', '商品已更新');
        } else {
            DB::insert(
                'INSERT INTO products (category_id, name, subtitle, description,
                 price, original_price, image, features, status, sort_order, sales, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$category_id, $name, $subtitle, $description, $price, $original_price,
                 $image, $features_json, $status, $sort_order, $sales]
            );
            flash('success', '商品已创建');
        }
        redirect('/admin/products.php');
    }
}

// ── 删除商品 ──────────────────────────────────────────
if ($action === 'delete' && $edit_id > 0) {
    abort_csrf();
    $row = DB::queryOne('SELECT image FROM products WHERE id=?', [$edit_id]);
    if ($row) {
        if (!empty($row['image']) && file_exists(UPLOAD_DIR . $row['image'])) {
            @unlink(UPLOAD_DIR . $row['image']);
        }
        DB::execute('DELETE FROM products WHERE id=?', [$edit_id]);
    }
    flash('success', '商品已删除');
    redirect('/admin/products.php');
}

// ── 加载编辑数据 ──────────────────────────────────────
$edit_product = null;
if ($action === 'edit' && $edit_id > 0) {
    $edit_product = get_product($edit_id, false);
    if (!$edit_product) {
        flash('danger', '商品不存在');
        redirect('/admin/products.php');
    }
}
$show_form = ($action === 'add' || $action === 'edit');

$categories = DB::query('SELECT id, name FROM categories ORDER BY sort_order ASC');
$products   = get_products(false);
?>

<?php if (!$show_form): ?>
<!-- 商品列表 -->
<div class="card">
  <div class="card-header">
    <span class="card-title">商品列表</span>
    <a href="/admin/products.php?action=add" class="btn btn-primary btn-sm">➕ 新增课程</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>课程名称</th>
          <th>分类</th>
          <th>价格</th>
          <th>排序</th>
          <th>状态</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:#bdc3c7;">暂无商品，<a href="/admin/products.php?action=add">立即创建</a></td></tr>
        <?php else: ?>
        <?php foreach ($products as $p): ?>
        <tr>
          <td style="color:#bdc3c7;font-size:12px;"><?= (int)$p['id'] ?></td>
          <td>
            <div style="font-weight:600;"><?= h($p['name']) ?></div>
            <?php if ($p['subtitle']): ?>
              <div style="font-size:12px;color:#7f8c8d;"><?= h(mb_substr($p['subtitle'],0,30)) ?></div>
            <?php endif; ?>
          </td>
          <td><?= h($p['category_name'] ?? '—') ?></td>
          <td style="color:var(--accent);font-weight:600;">¥<?= number_format((float)$p['price'],2) ?></td>
          <td><?= (int)$p['sort_order'] ?></td>
          <td>
            <?php if ((int)$p['status'] === 1): ?>
              <span class="badge badge-success">上架</span>
            <?php else: ?>
              <span class="badge badge-secondary">下架</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/admin/products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">编辑</a>
            <a href="/admin/products.php?action=delete&id=<?= $p['id'] ?>&csrf_token=<?= csrf_token() ?>"
               class="btn btn-danger btn-sm"
               data-confirm="确定删除「<?= h($p['name']) ?>」？此操作不可恢复。">删除</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- 编辑/新增表单 -->
<div style="margin-bottom:16px;">
  <a href="/admin/products.php" class="btn btn-outline btn-sm">← 返回列表</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?= h(implode('；', $errors)) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $edit_product ? '编辑课程' : '新增课程' ?></span>
  </div>
  <div class="card-body">
    <form method="post" action="/admin/products.php?<?= $edit_product ? 'action=edit&id='.$edit_id : 'action=add' ?>"
          enctype="multipart/form-data" class="needs-loading">
      <?= csrf_field() ?>
      <?php if ($edit_product): ?>
        <input type="hidden" name="current_image" value="<?= h($edit_product['image'] ?? '') ?>">
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">课程名称 <span class="req">*</span></label>
          <input type="text" name="name" class="form-control"
                 value="<?= h($_POST['name'] ?? $edit_product['name'] ?? '') ?>"
                 placeholder="如：国考笔试全程班" maxlength="100" required>
        </div>

        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">副标题</label>
          <input type="text" name="subtitle" class="form-control"
                 value="<?= h($_POST['subtitle'] ?? $edit_product['subtitle'] ?? '') ?>"
                 placeholder="简短的卖点描述，如：行测+申论双科强化，名师押题" maxlength="100">
        </div>

        <div class="form-group">
          <label class="form-label">所属分类</label>
          <select name="category_id" class="form-select">
            <option value="">— 不分类 —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"
                <?= ((int)($edit_product['category_id'] ?? 0) === (int)$cat['id'] ||
                     (int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                <?= h($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">状态</label>
          <select name="status" class="form-select">
            <option value="1" <?= (($_POST['status'] ?? $edit_product['status'] ?? 1) == 1) ? 'selected' : '' ?>>上架（前台显示）</option>
            <option value="0" <?= (($_POST['status'] ?? $edit_product['status'] ?? 1) == 0) ? 'selected' : '' ?>>下架（隐藏）</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">售价（元）<span class="req">*</span></label>
          <input type="number" name="price" class="form-control" step="0.01" min="0.01"
                 value="<?= h($_POST['price'] ?? $edit_product['price'] ?? '') ?>"
                 placeholder="实际收款金额" required>
        </div>

        <div class="form-group">
          <label class="form-label">划线价（元）</label>
          <input type="number" name="original_price" class="form-control" step="0.01" min="0"
                 value="<?= h($_POST['original_price'] ?? $edit_product['original_price'] ?? '') ?>"
                 placeholder="原价，填0或留空则不显示">
          <div class="form-hint">显示为删除线价格，突出优惠感</div>
        </div>

        <div class="form-group">
          <label class="form-label">排序序号</label>
          <input type="number" name="sort_order" class="form-control" min="0"
                 value="<?= h($_POST['sort_order'] ?? $edit_product['sort_order'] ?? 0) ?>">
          <div class="form-hint">数值越小越靠前</div>
        </div>

        <div class="form-group">
          <label class="form-label">虚拟销量</label>
          <input type="number" name="sales" class="form-control" min="0"
                 value="<?= h($_POST['sales'] ?? $edit_product['sales'] ?? 0) ?>">
          <div class="form-hint">前台显示「已报名 N 人」</div>
        </div>

        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">特色标签</label>
          <textarea name="features" class="form-control" rows="4"
                    placeholder="每行一个标签，最多6行，如：&#10;协议保障退费&#10;名师授课&#10;历年真题精讲"><?php
            $fv = $_POST['features'] ?? '';
            if (!$fv && !empty($edit_product['features'])) {
                $arr = json_decode($edit_product['features'], true) ?: [];
                $fv  = implode("\n", $arr);
            }
            echo h($fv);
          ?></textarea>
          <div class="form-hint">每行一个，显示在商品卡片上的小标签</div>
        </div>

        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">商品封面图</label>
          <input type="file" name="image" id="imageFile" class="form-control"
                 accept="image/jpeg,image/png,image/gif,image/webp">
          <div class="form-hint">支持 JPG/PNG/GIF/WebP，2MB以内；不上传则自动生成占位图</div>
          <?php if (!empty($edit_product['image']) && file_exists(UPLOAD_DIR . $edit_product['image'])): ?>
            <img id="imagePreview" src="<?= UPLOAD_URL . h($edit_product['image']) ?>"
                 class="img-preview" style="display:block;">
          <?php else: ?>
            <img id="imagePreview" class="img-preview">
          <?php endif; ?>
        </div>

        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">课程详情</label>
          <textarea name="description" class="form-control" rows="8"
                    placeholder="支持基本 HTML 标签（p、ul、li、strong、h3 等），可描述课程亮点、服务内容等"><?= h($_POST['description'] ?? $edit_product['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px;">
        <button type="submit" class="btn btn-primary btn-lg">保存课程</button>
        <a href="/admin/products.php" class="btn btn-outline btn-lg">取消</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
