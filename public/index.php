<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Item;
use Models\Category;
use Models\Shift;
use Models\OrderItem;
use Core\Auth;
use Core\Security;
use Core\DB;

AuthMiddleware::handle('process_order');

$user          = Auth::user();
$shift_id      = Shift::getActiveOrOpen($user['id']);
$categories    = Category::all();
$items         = Item::all();
$csrf          = Security::generateCSRFToken();
$popularItems  = OrderItem::bestSellers($shift_id, 5);
$currency      = getSetting('currency', 'SDG');

// Payment methods from DB (fallback to hardcoded if table missing)
try {
    $payMethods = DB::conn()
        ->query("SELECT name FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id")
        ->fetchAll(\PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $payMethods = ['كاش', 'بنكك', 'ماي كاشي', 'تحويل بنكي'];
}
if (empty($payMethods)) {
    $payMethods = ['كاش'];
}

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - نقطة البيع';
include __DIR__ . '/../views/partials/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <span class="badge bg-success px-3 py-2" style="border-radius:20px; font-size:.82rem;">
        <i class="bi bi-clock-history"></i> الوردية #<?= $shift_id ?>
    </span>
    <span class="badge bg-warning text-dark" id="offlineBadge" style="border-radius:20px; display:none; font-size:.82rem;">
        <i class="bi bi-wifi-off"></i> <span id="offlineCount">0</span> طلب معلق
    </span>
    <div class="ms-auto d-flex gap-2">
        <button id="fullscreenBtn" class="btn btn-sm btn-outline-secondary" title="شاشة كاملة">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>
        <button id="helpBtn" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-question-circle"></i> مساعدة
        </button>
    </div>
</div>

<div class="row g-3">
    <!-- ── Products panel ──────────────────────────────────────────── -->
    <div class="col-lg-8 col-md-7">

        <div id="popularContainer"></div>

        <div id="categoryTabs" class="mb-3">
            <button class="cat-tab active" data-category="all">
                <i class="bi bi-grid-3x3-gap-fill"></i> الكل
            </button>
            <?php foreach ($categories as $cat): ?>
            <button class="cat-tab" data-category="<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="search-wrap mb-2">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="form-control"
                   placeholder="بحث بالاسم أو الباركود..." autocomplete="off">
        </div>
        <div class="text-muted mb-3" style="font-size:.73rem; padding-right:.25rem;">
            <kbd>F2</kbd> للتركيز &nbsp;·&nbsp; <kbd>Enter</kbd> للإضافة &nbsp;·&nbsp; <kbd>Esc</kbd> مسح السلة
        </div>

        <div id="barcodeNotFound" class="alert alert-warning py-2" style="display:none;">
            <i class="bi bi-upc-scan"></i> <span id="barcodeNotFoundMsg"></span>
        </div>

        <div id="itemsContainer"></div>
    </div>

    <!-- ── Cart panel ──────────────────────────────────────────────── -->
    <div class="col-lg-4 col-md-5">
        <div class="cart-container">

            <div class="cart-header d-flex align-items-center">
                <h5><i class="bi bi-bag-check text-primary"></i> الطلب الحالي</h5>
                <span class="badge bg-primary rounded-pill ms-auto" id="cart-count">0</span>
            </div>

            <div id="cart-items"></div>

            <div class="total-box">
                <div class="total-label">الإجمالي</div>
                <div class="total-value">
                    <span id="total-amount">0</span>
                    <span class="total-currency"><?= htmlspecialchars($currency) ?></span>
                </div>
            </div>

            <div>
                <label class="form-label"><i class="bi bi-credit-card"></i> طريقة الدفع</label>
                <?php if (count($payMethods) <= 6): ?>
                <div class="pay-methods-wrap">
                    <?php foreach ($payMethods as $i => $pm): ?>
                    <button type="button" class="pay-method-btn<?= $i === 0 ? ' selected' : '' ?>"
                            data-value="<?= htmlspecialchars($pm) ?>">
                        <?= htmlspecialchars($pm) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="payment_method" value="<?= htmlspecialchars($payMethods[0]) ?>">
                <?php else: ?>
                <select id="payment_method" class="form-select">
                    <?php foreach ($payMethods as $pm): ?>
                    <option value="<?= htmlspecialchars($pm) ?>"><?= htmlspecialchars($pm) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <button class="btn btn-success btn-lg w-100" id="checkoutBtn"
                    style="font-size:1rem; font-weight:700; padding:.75rem; letter-spacing:.01em;">
                <i class="bi bi-printer-fill"></i> إتمام البيع وطباعة
            </button>
            <button class="btn btn-outline-danger w-100" id="clearCartBtn">
                <i class="bi bi-trash3"></i> مسح السلة
            </button>

        </div>
    </div>
</div>
</div>

<!-- Help modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-info-circle"></i> تعليمات سريعة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>اختصارات لوحة المفاتيح:</h6>
                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between">
                        <kbd>F2</kbd> <span>التركيز على مربع البحث</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <kbd>Enter</kbd> <span>إضافة أول صنف في النتائج / إنهاء الطلب إذا كانت السلة ممتلئة</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <kbd>Esc</kbd> <span>مسح السلة بالكامل</span>
                    </li>
                </ul>
                <h6>الباركود:</h6>
                <p class="small text-muted">
                    وجّه الماسح نحو الباركود — سيضاف الصنف تلقائياً للسلة.
                    إذا لم يُعرف الباركود، ستظهر رسالة تنبيه.
                </p>
                <div class="alert alert-info mt-2 small">
                    <i class="bi bi-plug"></i> النظام يعمل بدون إنترنت — جميع البيانات محلية.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">فهمت</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>

<div id="posToastContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const CSRF_TOKEN = <?= json_encode($csrf) ?>;
    const SHIFT_ID   = <?= (int)$shift_id ?>;
    const CURRENCY   = <?= json_encode($currency) ?>;
    const ALL_ITEMS  = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;
    const POPULAR    = <?= json_encode($popularItems, JSON_UNESCAPED_UNICODE) ?>;

    if (ALL_ITEMS.length === 0) {
        document.getElementById('itemsContainer').innerHTML =
            '<div class="alert alert-warning">⚠️ لا توجد أصناف. أضف أصنافاً من لوحة الإدارة.</div>';
    }

    let cart            = new Map();
    let currentCategory = 'all';

    // ── Render popular (best-sellers) ────────────────────────────────────────
    function renderPopular() {
        const container = document.getElementById('popularContainer');
        if (!container || !POPULAR.length) return;
        let html = '';
        POPULAR.forEach(pop => {
            const item = ALL_ITEMS.find(i => i.name === pop.name);
            if (item) {
                html += `<button class="pop-btn"
                                 data-name="${escapeHtml(item.name)}"
                                 data-price="${item.price}"
                                 data-id="${item.id}">
                            <i class="bi bi-star-fill" style="color:#f59e0b;font-size:.75rem;"></i>
                            ${escapeHtml(item.name)}
                         </button>`;
            }
        });
        container.innerHTML = html;
    }

    // ── Render item grid ─────────────────────────────────────────────────────
    function renderItems() {
        const container = document.getElementById('itemsContainer');
        if (!container) return;
        if (!ALL_ITEMS.length) {
            container.innerHTML = '<div class="alert alert-warning">لا توجد أصناف. أضف أصنافاً من لوحة الإدارة.</div>';
            return;
        }
        let html = '';
        ALL_ITEMS.forEach(item => {
            const oos = item.stock !== null && item.stock !== undefined && parseInt(item.stock) <= 0;
            html += `<button class="item-btn${oos ? ' out-of-stock' : ''}"
                             data-name="${escapeHtml(item.name)}"
                             data-price="${item.price}"
                             data-id="${item.id}"
                             data-barcode="${escapeHtml(item.barcode || '')}"
                             data-category="${item.category_id || ''}"
                             ${oos ? 'disabled' : ''}>
                        <span class="item-name">${escapeHtml(item.name)}</span>
                        <span class="item-price">${item.price} ${CURRENCY}</span>
                     </button>`;
        });
        container.innerHTML = html;
        filterItems();
    }

    // ── Cart operations ──────────────────────────────────────────────────────
    function addToCart(name, price, item_id) {
        const entry = cart.get(name);
        cart.set(name, entry
            ? { ...entry, qty: entry.qty + 1 }
            : { name, price: parseFloat(price), qty: 1, item_id: item_id ? parseInt(item_id) : null });
        renderCart();
        if (item_id) {
            const btn = document.querySelector(`#itemsContainer .item-btn[data-id="${parseInt(item_id)}"]`);
            if (btn) { btn.classList.remove('just-added'); void btn.offsetWidth; btn.classList.add('just-added'); }
        }
    }

    function removeItem(name) {
        const entry = cart.get(name);
        if (!entry) return;
        if (entry.qty > 1) {
            cart.set(name, { ...entry, qty: entry.qty - 1 });
        } else {
            cart.delete(name);
        }
        renderCart();
    }

    function clearCart() {
        cart.clear();
        renderCart();
    }

    function renderCart() {
        let html = '', total = 0, count = 0;
        for (const [, item] of cart.entries()) {
            const sub = item.price * item.qty;
            total += sub;
            count += item.qty;
            html += `<div class="cart-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="cart-item-name">${escapeHtml(item.name)}</span>
                            <span class="cart-item-sub">${sub.toFixed(0)} ${CURRENCY}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <button class="btn btn-sm btn-outline-danger qty-btn qty-dec"
                                    data-cart-name="${escapeHtml(item.name)}">−</button>
                            <span class="qty-val">${item.qty}</span>
                            <button class="btn btn-sm btn-outline-primary qty-btn qty-inc"
                                    data-cart-name="${escapeHtml(item.name)}">+</button>
                            <small class="text-muted" style="font-size:.75rem;">${item.price} × ${item.qty}</small>
                        </div>
                     </div>`;
        }
        const cartDiv  = document.getElementById('cart-items');
        const totalEl  = document.getElementById('total-amount');
        const countEl  = document.getElementById('cart-count');
        if (cartDiv) cartDiv.innerHTML = html ||
            '<p class="text-muted text-center py-5 mb-0" style="font-size:.9rem;">' +
            '<i class="bi bi-bag" style="font-size:2rem;display:block;opacity:.25;margin-bottom:8px;"></i>' +
            'السلة فارغة</p>';
        if (totalEl) totalEl.textContent = total.toFixed(0);
        if (countEl) countEl.textContent = count;
    }

    // ── Pending order queue (offline resilience) ─────────────────────────────
    let pending = JSON.parse(localStorage.getItem('pending_orders') || '[]');

    function updateOfflineBadge() {
        const badge = document.getElementById('offlineBadge');
        const count = document.getElementById('offlineCount');
        if (badge) badge.style.display = pending.length > 0 ? '' : 'none';
        if (count) count.textContent = pending.length;
    }

    function savePending(order) {
        pending.push(order);
        localStorage.setItem('pending_orders', JSON.stringify(pending));
        updateOfflineBadge();
        processQueue();
    }

    async function processQueue() {
        if (!pending.length) return;
        const order = pending[0];
        try {
            const res = await fetch('order.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body:    JSON.stringify(order),
            });
            const data = await res.json();
            if (data.status === 'success') {
                pending.shift();
                localStorage.setItem('pending_orders', JSON.stringify(pending));
                updateOfflineBadge();
                showToast('تم تسجيل الطلب وطباعة الفاتورة', 'success');
                const win = window.open(`print.php?id=${data.order_id}`, '_blank', 'width=400,height=600');
                if (win) win.focus();
                processQueue();
            } else {
                showToast(data.message || 'حدث خطأ أثناء إرسال الطلب', 'danger');
            }
        } catch (err) {
            if (pending.length > 0) updateOfflineBadge();
        }
    }

    // ── Checkout ─────────────────────────────────────────────────────────────
    function checkout() {
        if (cart.size === 0) { showToast('السلة فارغة', 'warning'); return; }

        const items = Array.from(cart.values()).map(i => ({
            name: i.name, qty: i.qty, price: i.price, item_id: i.item_id || null,
        }));
        const total          = items.reduce((s, i) => s + i.price * i.qty, 0);
        const payment_method = document.getElementById('payment_method')?.value || 'كاش';

        // Idempotency key: one UUID per checkout attempt (survives page reload via localStorage)
        const idemKey = crypto.randomUUID ? crypto.randomUUID() : (
            'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            })
        );

        savePending({ items, total, shift_id: SHIFT_ID, payment_method, idempotency_key: idemKey });
        cart.clear();
        renderCart();
    }

    // ── Search + barcode ─────────────────────────────────────────────────────
    function filterItems() {
        const term = document.getElementById('searchInput').value.trim().toLowerCase();
        document.querySelectorAll('#itemsContainer .item-btn').forEach(btn => {
            const name    = (btn.dataset.name    || '').toLowerCase();
            const barcode = (btn.dataset.barcode || '').toLowerCase();
            const cat     = btn.dataset.category || '';
            const matchSearch = name.includes(term) || barcode.includes(term);
            const matchCat    = currentCategory === 'all' || cat === currentCategory;
            btn.style.display = (matchSearch && matchCat) ? '' : 'none';
        });
    }

    // On Enter in search: barcode scanner adds first exact barcode match or first visible item
    document.getElementById('searchInput')?.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const term = e.target.value.trim();
        if (!term) return;

        hideBarcodeNotFound();

        // Exact barcode match (scanner sends exact code + Enter)
        const byBarcode = ALL_ITEMS.find(i => i.barcode && i.barcode === term);
        if (byBarcode) {
            addToCart(byBarcode.name, byBarcode.price, byBarcode.id);
            e.target.value = '';
            filterItems();
            return;
        }

        // First visible button
        const first = document.querySelector('#itemsContainer .item-btn:not([style*="none"])');
        if (first) {
            addToCart(first.dataset.name, parseFloat(first.dataset.price), parseInt(first.dataset.id || '0'));
            e.target.value = '';
            filterItems();
            return;
        }

        // Nothing found
        showBarcodeNotFound(term);
    });

    function showBarcodeNotFound(code) {
        const el = document.getElementById('barcodeNotFound');
        const msg = document.getElementById('barcodeNotFoundMsg');
        if (el && msg) {
            msg.textContent = `"${code}" — لم يُعثر على صنف بهذا الرمز أو الاسم`;
            el.style.display = '';
            setTimeout(() => { el.style.display = 'none'; }, 3000);
        }
    }

    function hideBarcodeNotFound() {
        const el = document.getElementById('barcodeNotFound');
        if (el) el.style.display = 'none';
    }

    document.getElementById('searchInput')?.addEventListener('input', () => {
        hideBarcodeNotFound();
        filterItems();
    });

    // Category tabs
    document.querySelectorAll('#categoryTabs .cat-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#categoryTabs .cat-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentCategory = tab.dataset.category;
            filterItems();
        });
    });

    // Payment method buttons
    document.querySelectorAll('.pay-method-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pay-method-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            const hidden = document.getElementById('payment_method');
            if (hidden) hidden.value = btn.dataset.value;
        });
    });

    // ── Keyboard shortcuts ───────────────────────────────────────────────────
    document.addEventListener('keydown', e => {
        const tag = document.activeElement?.tagName;
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') {
            // Allow Esc to clear cart even from search
            if (e.key === 'Escape') { e.preventDefault(); clearCart(); }
            return;
        }
        if (e.key === 'Enter')  { e.preventDefault(); checkout(); }
        if (e.key === 'F2')     { e.preventDefault(); document.getElementById('searchInput')?.focus(); }
        if (e.key === 'Escape') { e.preventDefault(); clearCart(); }
    });

    // Fullscreen
    document.getElementById('fullscreenBtn')?.addEventListener('click', () => {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
        else document.exitFullscreen();
    });

    // Help modal
    const helpModalEl = document.getElementById('helpModal');
    const helpModal   = helpModalEl && typeof bootstrap !== 'undefined'
                        ? new bootstrap.Modal(helpModalEl) : null;
    document.getElementById('helpBtn')?.addEventListener('click', () => {
        if (helpModal) helpModal.show();
    });

    // HTML escape
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[m]));
    }

    // Toast notification
    function showToast(msg, type = 'success') {
        const container = document.getElementById('posToastContainer');
        if (!container) return;
        const icons = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill',
                        danger: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
        const toast = document.createElement('div');
        toast.className = `pos-toast toast-${type}`;
        toast.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i> ${escapeHtml(msg)}`;
        container.appendChild(toast);
        requestAnimationFrame(() => { requestAnimationFrame(() => { toast.classList.add('show'); }); });
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, 2800);
    }

    // ── Event delegation (replaces inline onclick — avoids JSON injection) ─────
    document.getElementById('itemsContainer')?.addEventListener('click', e => {
        const btn = e.target.closest('.item-btn');
        if (!btn) return;
        addToCart(btn.dataset.name, parseFloat(btn.dataset.price), parseInt(btn.dataset.id || '0'));
    });

    document.getElementById('popularContainer')?.addEventListener('click', e => {
        const btn = e.target.closest('.pop-btn');
        if (!btn) return;
        addToCart(btn.dataset.name, parseFloat(btn.dataset.price), parseInt(btn.dataset.id || '0'));
    });

    document.getElementById('cart-items')?.addEventListener('click', e => {
        const dec = e.target.closest('.qty-dec');
        const inc = e.target.closest('.qty-inc');
        if (dec) { removeItem(dec.dataset.cartName); return; }
        if (inc) {
            const entry = cart.get(inc.dataset.cartName);
            if (entry) { cart.set(inc.dataset.cartName, { ...entry, qty: entry.qty + 1 }); renderCart(); }
        }
    });

    // Button wiring
    document.getElementById('checkoutBtn')?.addEventListener('click', checkout);
    document.getElementById('clearCartBtn')?.addEventListener('click', clearCart);

    // Init
    renderPopular();
    renderItems();
    renderCart();
    updateOfflineBadge();
    processQueue();   // Retry any queued orders from a previous session
});
</script>
