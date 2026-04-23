<?php
require_once __DIR__.'/../app/helpers.php';
use Middleware\AuthMiddleware;
use Models\Item;
use Models\Category;
use Models\Shift;
use Models\OrderItem;
use Core\Auth;

AuthMiddleware::handle('process_order');

$user = Auth::user();
$shift_id = Shift::getActiveOrOpen($user['id']);
$categories = Category::all();
$items = Item::all(); // لجلب كل الأصناف (مع التصنيف) مرة واحدة
$csrf = \Core\Security::generateCSRFToken();
$popularItems = OrderItem::bestSellers($shift_id, 5);
$pageTitle = 'الكاشير - Cashirak V2';

include __DIR__.'/../views/partials/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge bg-success p-3">وردية #<?= $shift_id ?></span>
        <div>
            <span class="me-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['username']) ?></span>
            <a href="history.php" class="btn btn-sm btn-outline-info me-2"><i class="bi bi-clock-history"></i> سجل الفواتير</a>
            <?php if(Auth::hasPermission('manage_items')): ?>
            <a href="admin.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-speedometer2"></i> الإدارة</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> خروج</a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-8">
            <!-- أزرار سريعة ديناميكية -->
            <div class="quick-actions mb-3 d-flex gap-2 flex-wrap">
                <?php if (!empty($popularItems)): ?>
                    <?php foreach ($popularItems as $pop): ?>
                        <?php 
                        $price = 0;
                        foreach ($items as $item) {
                            if ($item['name'] === $pop['name']) {
                                $price = $item['price'];
                                break;
                            }
                        }
                        if ($price == 0) continue;
                        ?>
                        <button class="btn btn-outline-success" onclick="addToCart('<?= htmlspecialchars($pop['name']) ?>', <?= $price ?>)">
                            🔥 <?= htmlspecialchars($pop['name']) ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted">لا توجد مبيعات سابقة في هذه الوردية</span>
                <?php endif; ?>
            </div>

            <!-- علامات تبويب التصنيفات -->
            <ul class="nav nav-tabs mb-3" id="categoryTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-category="all" href="#">الكل</a>
                </li>
                <?php foreach($categories as $cat): ?>
                <li class="nav-item">
                    <a class="nav-link" data-category="<?= $cat['id'] ?>" href="#"><?= htmlspecialchars($cat['name']) ?></a>
                </li>
                <?php endforeach; ?>
            </ul>

            <h4><i class="bi bi-grid"></i> الأصناف</h4>
            <div class="mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 بحث عن صنف..." onkeyup="filterItems()">
            </div>
            <div class="d-flex flex-wrap" id="itemsContainer">
                <?php foreach($items as $item): ?>
                <button class="btn btn-outline-primary item-btn" 
                        data-name="<?= htmlspecialchars($item['name']) ?>" 
                        data-category="<?= $item['category_id'] ?? '' ?>"
                        onclick="addToCart('<?= htmlspecialchars($item['name']) ?>', <?= $item['price'] ?>)">
                    <span class="mt-2"><?= htmlspecialchars($item['name']) ?></span>
                    <span class="badge bg-primary mt-1"><?= $item['price'] ?> SDG</span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-md-4">
            <div class="cart-container">
                <h4><i class="bi bi-cart3"></i> السلة</h4>
                <div id="cart-items" style="min-height: 350px;"></div>
                <hr>
                <div class="total-box text-center mb-3"><span id="total-amount">0</span> SDG</div>
                <button class="btn btn-success w-100 py-3 mb-2" onclick="checkout()"><i class="bi bi-printer-fill"></i> حساب وطباعة</button>
                <button class="btn btn-outline-danger w-100 py-2" onclick="clearCart()"><i class="bi bi-trash"></i> مسح السلة</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrf ?>';
let cart = {};
let currentCategory = 'all';

// تبديل التصنيف
document.querySelectorAll('#categoryTabs .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('#categoryTabs .nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentCategory = this.dataset.category;
        filterItems();
    });
});

function addToCart(name, price) {
    cart[name] = cart[name] ? {...cart[name], qty: cart[name].qty+1} : {name, price, qty:1};
    renderCart();
}
function addPopular(name, price) { addToCart(name, price); }
function removeItem(name) {
    if(cart[name].qty > 1) cart[name].qty--; else delete cart[name];
    renderCart();
}
function renderCart() {
    let html = '', total = 0;
    for(let key in cart) {
        const item = cart[key];
        const subtotal = item.price * item.qty;
        total += subtotal;
        html += `<div class="cart-item d-flex justify-content-between">
            <span>${item.name} x ${item.qty}</span>
            <span>${subtotal} SDG <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeItem('${item.name}')"><i class="bi bi-dash"></i></button></span>
        </div>`;
    }
    document.getElementById('cart-items').innerHTML = html || '<p class="text-muted text-center mt-5"><i class="bi bi-basket"></i> السلة فارغة</p>';
    document.getElementById('total-amount').innerText = total;
}
function checkout() {
    if(Object.keys(cart).length === 0) { alert('السلة فارغة'); return; }
    const items = Object.values(cart);
    const total = items.reduce((sum, i) => sum + (i.price * i.qty), 0);
    fetch('order.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN},
        body: JSON.stringify({ items, total, shift_id: <?= $shift_id ?> })
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            printReceipt(items, total);
            cart = {}; renderCart();
        }
    });
}
function printReceipt(items, total) {
    const printWindow = window.open('', '_blank', 'width=300,height=400');
    let itemsHtml = items.map(i => `${i.name} x${i.qty} ... ${i.price * i.qty} SDG`).join('<br>');
    printWindow.document.write(`
        <html dir="rtl"><head><title>فاتورة</title></head>
        <body style="font-family:monospace;text-align:center;">
            <h2>Cashirak POS</h2><hr>${itemsHtml}<hr><h3>الإجمالي: ${total} SDG</h3>
            <p>شكراً لزيارتكم</p>
            <script>window.print(); setTimeout(()=>window.close(),1000);<\/script>
        </body></html>
    `);
    printWindow.document.close();
}
function clearCart() { cart = {}; renderCart(); }
function filterItems() {
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
    const container = document.getElementById('itemsContainer');
    const buttons = container.getElementsByClassName('item-btn');
    for (let btn of buttons) {
        const itemName = btn.getAttribute('data-name') || '';
        const itemCat = btn.getAttribute('data-category') || '';
        const matchesSearch = itemName.toLowerCase().includes(searchTerm);
        const matchesCat = (currentCategory === 'all') || (itemCat === currentCategory);
        btn.style.display = (matchesSearch && matchesCat) ? '' : 'none';
    }
}
renderCart();
</script>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
