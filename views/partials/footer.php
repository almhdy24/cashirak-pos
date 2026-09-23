</div> <!-- /container-fluid -->

<footer class="border-top mt-5 py-2 text-center text-muted" style="font-size:.78rem; background:#f8f9fa;" dir="rtl">
    كاشيراك POS v<?= defined('CASHIRAK_VERSION') ? CASHIRAK_VERSION : '1.0.0' ?>
    &nbsp;·&nbsp;
    <a href="https://almhdy24.com" target="_blank" class="text-decoration-none text-muted">Elmahdi Dev</a>
    &nbsp;·&nbsp; © <?= date('Y') ?>
</footer>

<!-- Shared confirmation modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> تأكيد
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p id="confirmModalBody" class="mb-0 text-muted" style="font-size:.9rem;"></p>
            </div>
            <div class="modal-footer border-0 pt-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmModalOkBtn">تأكيد</button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/bootstrap.bundle.min.js"></script>
<script>
(function () {
    window.posConfirm = function (msg, onOk, okLabel, okClass) {
        const el = document.getElementById('confirmModal');
        if (!el) { if (confirm(msg)) onOk(); return; }
        document.getElementById('confirmModalBody').textContent = msg;
        const btn = document.getElementById('confirmModalOkBtn');
        btn.textContent  = okLabel || 'تأكيد';
        btn.className    = 'btn btn-sm ' + (okClass || 'btn-danger');
        const modal      = bootstrap.Modal.getOrCreateInstance(el);
        btn.onclick      = () => { modal.hide(); onOk(); };
        el.addEventListener('hidden.bs.modal', () => { btn.onclick = null; }, { once: true });
        modal.show();
    };

    // Auto-intercept any form with data-confirm attribute
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form');
        if (!form) return;
        const msg = form.dataset.confirm;
        if (!msg) return;
        if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        posConfirm(msg, () => {
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    }, true);
})();
</script>
</body>
</html>
