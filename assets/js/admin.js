/* 后台管理脚本 */

(function () {
  'use strict';

  // ── 移动端侧边栏 ──────────────────────────────────────
  var menuBtn = document.getElementById('menuToggle');
  var sidebar = document.querySelector('.sidebar');
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') &&
          !sidebar.contains(e.target) &&
          e.target !== menuBtn) {
        sidebar.classList.remove('open');
      }
    });
  }

  // ── 确认删除 ──────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm || '确定要执行此操作吗？')) {
        e.preventDefault();
        return false;
      }
    });
  });

  // ── 商品图片预览 ──────────────────────────────────────
  var imgInput   = document.getElementById('imageFile');
  var imgPreview = document.getElementById('imagePreview');
  if (imgInput && imgPreview) {
    imgInput.addEventListener('change', function () {
      var file = this.files[0];
      if (!file) { return; }
      if (!file.type.match(/image\//)) {
        alert('请上传图片文件（JPG、PNG、GIF）');
        this.value = '';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        alert('图片大小不能超过 2MB');
        this.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function (e) {
        imgPreview.src   = e.target.result;
        imgPreview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }

  // ── 开关状态即时保存（Ajax）──────────────────────────
  document.querySelectorAll('.ajax-toggle').forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      var url    = this.dataset.url;
      var params = new URLSearchParams();
      params.append('status', this.checked ? '1' : '0');
      params.append('csrf_token', document.querySelector('meta[name=csrf]') ?
        document.querySelector('meta[name=csrf]').content : '');

      fetch(url, { method: 'POST', body: params })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) { alert('操作失败：' + (data.message || '未知错误')); }
        })
        .catch(function () { alert('网络错误，请刷新页面重试'); });
    });
  });

  // ── 支付渠道配置折叠 ──────────────────────────────────
  document.querySelectorAll('.channel-toggle-config').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(this.dataset.target);
      if (target) { target.style.display = target.style.display === 'none' ? 'block' : 'none'; }
    });
  });

  // ── 表单提交 loading ─────────────────────────────────
  document.querySelectorAll('form.needs-loading').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = this.querySelector('[type=submit]');
      if (btn) { btn.disabled = true; btn.textContent = '保存中…'; }
    });
  });

})();
