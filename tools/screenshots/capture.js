/**
 * Cashirak POS — screenshot capture
 *
 * Drives the running app with Playwright and saves raw screenshots to
 * docs/screenshots/. Meant to be run by build.sh against a demo copy:
 *
 *   node tools/screenshots/capture.js install   # before seeding (setup wizard)
 *   node tools/screenshots/capture.js license   # after seeding, before license cache
 *   node tools/screenshots/capture.js app       # everything else
 *
 * Env: BASE_URL (default http://127.0.0.1:8000)
 * Note: the last "app" step submits the shift-close form (closes the shift).
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT = path.resolve(__dirname, '../../docs/screenshots');
const ADMIN = ['admin', 'admin1234'];
const CASHIER = ['cashier', 'cashier123'];
const DESKTOP = { width: 1440, height: 900 };
fs.mkdirSync(OUT, { recursive: true });

async function shot(page, name, opts = {}) {
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.mouse.move(0, 0);
  await page.waitForTimeout(350);
  await page.screenshot({ path: path.join(OUT, `${name}.png`), ...opts });
  console.log('saved', name);
}

async function open(browser, viewport = DESKTOP) {
  const ctx = await browser.newContext({ viewport, deviceScaleFactor: 2, locale: 'ar-SD' });
  await ctx.addInitScript(() => {
    window.print = () => {};
    window.open = () => null; // receipts are captured from print.php directly
    // Bootstrap enables smooth scrolling; keep scroll jumps instant so shots are stable.
    document.addEventListener('DOMContentLoaded', () => { document.documentElement.style.scrollBehavior = 'auto'; });
  });
  return [ctx, await ctx.newPage()];
}

async function login(page, [user, pass]) {
  await page.goto(`${BASE}/login.php`);
  await page.fill('input[name=username]', user);
  await page.fill('input[name=password]', pass);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
}

async function tap(page, name, times = 1) {
  for (let i = 0; i < times; i++) await page.locator(`#itemsContainer .item-btn[data-name="${name}"]`).click();
}

const phases = {
  // ---------- Setup wizard (fresh copy, nothing installed) ----------
  async install(browser) {
    const [ctx, page] = await open(browser);
    await page.goto(`${BASE}/install.php`);
    await page.fill('input[name=cafe_title]', 'كافتيريا النيلين');
    await shot(page, 'setup-1-shop');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await page.fill('input[name=admin_username]', 'admin');
    await page.fill('input[name=admin_password]', 'admin1234');
    await page.fill('input[name=admin_password_confirm]', 'admin1234');
    await shot(page, 'setup-2-admin');
    await ctx.close();
  },

  // ---------- License activation screen ----------
  async license(browser) {
    const [ctx, page] = await open(browser);
    await page.goto(`${BASE}/license.php`);
    await shot(page, 'license');
    await ctx.close();
  },

  async app(browser) {
    let [ctx, page] = await open(browser);

    // Login
    await page.goto(`${BASE}/login.php`);
    await page.fill('input[name=username]', CASHIER[0]);
    await page.fill('input[name=password]', CASHIER[1]);
    await shot(page, 'login');

    // POS
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await page.waitForSelector('#itemsContainer .item-btn');
    await shot(page, 'pos-empty');
    await tap(page, 'شاي بلبن', 2);
    await tap(page, 'سندويتش فول', 2);
    await tap(page, 'قهوة جبنة');
    await tap(page, 'عصير ليمون');
    await tap(page, 'لقيمات', 3);
    await page.click('.pay-method-btn[data-value="بنكك"]');
    await shot(page, 'pos-cart');

    await page.click('#categoryTabs .cat-tab:text-is("مشروبات باردة")');
    await shot(page, 'pos-category');

    await page.click('#categoryTabs .cat-tab[data-category="all"]');
    await page.fill('#searchInput', '6251234500210'); // barcode scan
    await shot(page, 'pos-barcode');
    await page.fill('#searchInput', '');

    await page.click('#helpBtn');
    await page.waitForSelector('#helpModal.show');
    await shot(page, 'pos-help');
    await page.keyboard.press('Escape');

    // Order history
    await page.goto(`${BASE}/history.php`);
    await shot(page, 'history');

    // Pick the order with the most lines for receipt + return screens
    const orderId = await page.$$eval('table tbody tr', (rows) => {
      let best = null, bestN = -1;
      for (const r of rows) {
        const id = (r.innerText.match(/#(\d+)/) || [])[1];
        const nums = [...r.cells].map((c) => parseInt(c.innerText, 10)).filter((n) => !isNaN(n) && n < 20);
        const n = nums.length ? Math.max(...nums) : 0;
        if (id && n > bestN) { best = id; bestN = n; }
      }
      return best;
    });

    // Returns
    await page.goto(`${BASE}/returns.php?order_id=${orderId}`);
    const qty = page.locator('input[name^="return_qty"]').first();
    if (await qty.count()) await qty.fill('1');
    const reason = page.locator('input[name=reason]');
    if (await reason.count()) await reason.fill('الصنف وصل بارداً');
    await shot(page, 'returns');
    await ctx.close();

    // Receipt
    [ctx, page] = await open(browser, { width: 380, height: 300 });
    await login(page, CASHIER);
    await page.goto(`${BASE}/print.php?id=${orderId}`);
    await shot(page, 'receipt', { fullPage: true });
    await ctx.close();

    // Tablet
    [ctx, page] = await open(browser, { width: 1180, height: 820 });
    await login(page, CASHIER);
    await page.waitForSelector('#itemsContainer .item-btn');
    await tap(page, 'شاورما', 2);
    await tap(page, 'كوكاكولا', 2);
    await tap(page, 'باسطة');
    await shot(page, 'pos-tablet');
    await ctx.close();

    // Phone
    [ctx, page] = await open(browser, { width: 390, height: 844 });
    await login(page, CASHIER);
    await page.waitForSelector('#itemsContainer .item-btn');
    await tap(page, 'شاي سادة', 2);
    await tap(page, 'سندويتش طعمية');
    await shot(page, 'pos-mobile');
    await ctx.close();

    // ---------- Admin ----------
    [ctx, page] = await open(browser);
    await login(page, ADMIN);
    await page.goto(`${BASE}/admin.php`);
    await shot(page, 'admin-dashboard');

    await page.click('button[data-bs-target="#addItemModal"]');
    await page.waitForSelector('#addItemModal.show');
    const m = '#addItemModal';
    await page.fill(`${m} input[name=name]`, 'شاي بالنعناع');
    await page.fill(`${m} input[name=price]`, '700');
    await page.fill(`${m} input[name=cost_price]`, '250');
    await page.fill(`${m} input[name=barcode]`, '6251234500333');
    await page.selectOption(`${m} select[name=category_id]`, { label: 'مشروبات ساخنة' });
    await shot(page, 'admin-add-item');
    await page.keyboard.press('Escape');

    for (const [url, name] of [
      ['admin/categories.php', 'admin-categories'],
      ['admin/payment-methods.php', 'admin-payment-methods'],
      ['admin/reports.php', 'admin-reports'],
      ['admin/expenses.php', 'admin-expenses'],
      ['shift-history.php', 'admin-shift-history'],
      ['admin/users.php', 'admin-users'],
      ['admin/settings.php', 'admin-settings'],
      ['admin/backup.php', 'admin-backup'],
    ]) {
      await page.goto(`${BASE}/${url}`);
      await shot(page, name);
      if (name === 'admin-reports') await shot(page, 'admin-reports-full', { fullPage: true });
    }

    // Shift details of the most recent closed shift
    await page.goto(`${BASE}/shift-history.php`);
    const detailsHref = await page.locator('a[href*="shift-details.php"]').first().getAttribute('href');
    await page.goto(new URL(detailsHref, `${BASE}/shift-history.php`).href);
    await shot(page, 'admin-shift-details');

    // Shift close: reconciliation form, then the closed summary
    await page.goto(`${BASE}/shift-close.php`);
    const expected = await page.evaluate(() => {
      const m = document.body.innerText.match(/المتوقع[^\d]*([\d,]+(?:\.\d+)?)/);
      return m ? Math.round(parseFloat(m[1].replace(/,/g, ''))) : 0;
    });
    await page.fill('input[name=actual_cash]', String(expected ? expected - 500 : 100000));
    await page.fill('textarea[name=close_note]', 'نقص 500 جنيه في الفكة');
    await shot(page, 'shift-close');
    await shot(page, 'shift-close-full', { fullPage: true });
    page.on('dialog', (d) => d.accept());
    await page.locator('form:has(input[name=actual_cash]) button[type=submit]').click();
    if (await page.waitForSelector('#confirmModal.show', { timeout: 1500 }).catch(() => null)) {
      await page.click('#confirmModalOkBtn');
    }
    await page.waitForLoadState('load');
    await page.waitForTimeout(500);
    await shot(page, 'shift-closed');
    await ctx.close();
  },
};

(async () => {
  const phase = process.argv[2] || 'app';
  if (!phases[phase]) throw new Error(`unknown phase "${phase}" (install | license | app)`);
  const browser = await chromium.launch();
  try {
    await phases[phase](browser);
  } finally {
    await browser.close();
  }
})();
