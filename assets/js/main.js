/* 前台交互脚本 */

(function () {
  'use strict';

  // ── 支付方式选择 ─────────────────────────────────────
  document.querySelectorAll('.channel-item').forEach(function (item) {
    item.addEventListener('click', function () {
      document.querySelectorAll('.channel-item').forEach(function (el) {
        el.classList.remove('active');
      });
      this.classList.add('active');
      var radio = this.querySelector('input[type=radio]');
      if (radio) { radio.checked = true; }
    });
  });

  // ── 表单提交防重复 ────────────────────────────────────
  var checkoutForm = document.getElementById('checkoutForm');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function (e) {
      var channel = document.querySelector('input[name=channel]:checked');
      if (!channel) {
        e.preventDefault();
        showToast('请选择支付方式', 'warning');
        return;
      }
      var btn = this.querySelector('.btn-submit');
      if (btn) {
        btn.disabled = true;
        btn.textContent = '提交中…';
      }
    });
  }

  // ── 平滑滚动 ──────────────────────────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ── Toast 提示 ────────────────────────────────────────
  window.showToast = function (msg, type) {
    var toast = document.createElement('div');
    toast.className = 'site-toast site-toast-' + (type || 'info');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function () { toast.classList.add('show'); }, 10);
    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  };

  // 注入 toast 样式（避免额外 CSS 请求）
  var toastStyle = document.createElement('style');
  toastStyle.textContent = [
    '.site-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);',
    'background:#2c3e50;color:#fff;padding:10px 24px;border-radius:24px;font-size:14px;',
    'opacity:0;transition:all .3s;z-index:9999;white-space:nowrap;pointer-events:none;}',
    '.site-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}',
    '.site-toast-warning{background:#e67e22;}',
    '.site-toast-success{background:#1e8449;}',
    '.site-toast-danger{background:#c0392b;}'
  ].join('');
  document.head.appendChild(toastStyle);

})();
