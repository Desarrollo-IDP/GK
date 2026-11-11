<script src="../js/main.js"></script>
<!-- Modal de confirmación (eliminar, etc.) -->
<div id="appConfirmOverlay" class="app-confirm-overlay" aria-hidden="true">
    <div class="app-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle">
        <div class="app-confirm-icon-wrap">
            <i class="fas fa-trash-can" aria-hidden="true"></i>
        </div>
        <h2 id="appConfirmTitle" class="app-confirm-title">Confirmar</h2>
        <p id="appConfirmMessage" class="app-confirm-message"></p>
        <div class="app-confirm-actions">
            <button type="button" id="appConfirmCancel" class="app-confirm-btn-cancel">Cancelar</button>
            <button type="button" id="appConfirmOk" class="app-confirm-btn-danger">Eliminar</button>
        </div>
    </div>
</div>
<!-- Modal de notificación -->
<div id="appAlertOverlay" class="app-alert-overlay" aria-hidden="true">
    <div class="app-alert-modal" role="alertdialog" aria-modal="true" aria-labelledby="appAlertTitle" aria-describedby="appAlertMessage">
        <div class="app-alert-icon-wrap">
            <i id="appAlertIcon" class="fas fa-circle-exclamation" aria-hidden="true"></i>
        </div>
        <h2 id="appAlertTitle" class="app-alert-title">Mensaje</h2>
        <p id="appAlertMessage" class="app-alert-message"></p>
        <div class="app-alert-actions">
            <button type="button" id="appAlertBtn" class="app-alert-btn app-alert-btn-primary">Aceptar</button>
        </div>
    </div>
</div>
<script>
(function() {
    var overlay = document.getElementById('appAlertOverlay');
    var iconEl = document.getElementById('appAlertIcon');
    var titleEl = document.getElementById('appAlertTitle');
    var msgEl = document.getElementById('appAlertMessage');
    var btn = document.getElementById('appAlertBtn');
    function closeAlert() {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
    }
    if (btn) btn.addEventListener('click', closeAlert);
    if (overlay) overlay.addEventListener('click', function(e) { if (e.target === overlay) closeAlert(); });
    window.showAlert = function(message, type) {
        type = type || 'error';
        var titles = { error: 'Error', success: 'Éxito', warning: 'Advertencia' };
        var icons = { error: 'fa-circle-exclamation', success: 'fa-circle-check', warning: 'fa-triangle-exclamation' };
        if (titleEl) titleEl.textContent = titles[type] || titles.error;
        if (msgEl) msgEl.textContent = message || '';
        if (iconEl) { iconEl.className = 'fas ' + (icons[type] || icons.error); }
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
    };
})();
(function() {
    var overlay = document.getElementById('appConfirmOverlay');
    var titleEl = document.getElementById('appConfirmTitle');
    var msgEl = document.getElementById('appConfirmMessage');
    var btnCancel = document.getElementById('appConfirmCancel');
    var btnOk = document.getElementById('appConfirmOk');
    var currentCallback = null;
    function closeConfirm() {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        currentCallback = null;
    }
    if (btnCancel) btnCancel.addEventListener('click', closeConfirm);
    if (btnOk) btnOk.addEventListener('click', function() {
        if (typeof currentCallback === 'function') currentCallback();
        closeConfirm();
    });
    if (overlay) overlay.addEventListener('click', function(e) { if (e.target === overlay) closeConfirm(); });
    window.showConfirm = function(message, onConfirm, options) {
        options = options || {};
        if (titleEl) titleEl.textContent = options.title || 'Confirmar';
        if (msgEl) msgEl.textContent = message || '';
        if (btnOk) btnOk.textContent = options.okText || 'Eliminar';
        currentCallback = onConfirm;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
    };
})();
</script>
</body>
</html>