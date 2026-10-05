/**
 * Cashirak POS — marketing images
 *
 * Turns the raw screenshots in docs/screenshots/ into presentation-ready
 * images in docs/showcase/: framed feature shots, device shots, a hero
 * banner and a GitHub/social preview card. Pure HTML/CSS + Playwright.
 *
 *   node tools/screenshots/showcase.js
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const ROOT = path.resolve(__dirname, '../..');
const RAW = path.join(ROOT, 'docs/screenshots');
const OUT = path.join(ROOT, 'docs/showcase');
fs.mkdirSync(OUT, { recursive: true });

const MIME = { '.woff2': 'font/woff2', '.svg': 'image/svg+xml', '.png': 'image/png' };
const b64 = (file) => `data:${MIME[path.extname(file)]};base64,${fs.readFileSync(file).toString('base64')}`;
const shot = (name) => b64(path.join(RAW, `${name}.png`));
const font = (name) => b64(path.join(__dirname, 'fonts', name));
const ICON = b64(path.join(ROOT, 'installer_assets/icon_source.svg'));

const BASE_CSS = `
@font-face { font-family: Cairo; src: url(${font('cairo-arabic.woff2')}) format('woff2'); font-weight: 200 1000;
  unicode-range: U+0600-06FF, U+0750-077F, U+0870-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FEFC; }
@font-face { font-family: Cairo; src: url(${font('cairo-latin.woff2')}) format('woff2'); font-weight: 200 1000;
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2212; }
* { box-sizing: border-box; margin: 0; }
html, body { width: 100%; height: 100%; overflow: hidden; }
body {
  font-family: Cairo, 'Noto Sans Arabic', sans-serif; color: #fff; position: relative;
  background:
    radial-gradient(900px 600px at 85% -10%, rgba(59,130,246,.45), transparent 60%),
    radial-gradient(800px 600px at 0% 110%, rgba(52,211,153,.22), transparent 60%),
    linear-gradient(135deg, #0b1224 0%, #0f172a 40%, #1e3a8a 100%);
}
body::before { /* dotted texture */
  content: ''; position: absolute; inset: 0; opacity: .16;
  background-image: radial-gradient(rgba(255,255,255,.55) 1px, transparent 1px); background-size: 26px 26px;
  -webkit-mask-image: linear-gradient(180deg, #000, transparent 75%); mask-image: linear-gradient(180deg, #000, transparent 75%);
}
.brand { display: inline-flex; align-items: center; gap: 12px; padding: 7px 20px 7px 8px; border-radius: 999px;
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); font-weight: 700; font-size: 20px; }
.brand img { width: 38px; height: 38px; border-radius: 10px; box-shadow: 0 6px 18px rgba(37,99,235,.5); }
.win { background: #fff; border-radius: 16px; overflow: hidden;
  box-shadow: 0 40px 80px -20px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.12); }
.bar { height: 44px; background: #e9eef5; display: flex; align-items: center; gap: 8px; padding: 0 16px; direction: ltr; border-bottom: 1px solid #d8dfe9; }
.dot { width: 13px; height: 13px; border-radius: 50%; }
.url { margin-left: 14px; flex: 1; max-width: 460px; height: 28px; border-radius: 8px; background: #fff; color: #5b6575;
  font: 600 14px/28px Cairo, sans-serif; padding: 0 12px; border: 1px solid #d8dfe9; }
.win img { display: block; width: 100%; }
.head { position: absolute; top: 54px; left: 0; right: 0; text-align: center; }
.head h1 { font-size: 60px; font-weight: 800; line-height: 1.2; margin-top: 20px; text-shadow: 0 4px 24px rgba(0,0,0,.25); }
.head p { direction: ltr; font-size: 24px; color: #bfdbfe; margin-top: 6px; font-weight: 500; }
`;

const windowHtml = (img, url, cls = '') => `
  <div class="win ${cls}">
    <div class="bar"><span class="dot" style="background:#ff5f57"></span><span class="dot" style="background:#febc2e"></span>
      <span class="dot" style="background:#28c840"></span><span class="url">localhost:8000/${url}</span></div>
    <img src="${img}">
  </div>`;

const brandHtml = `<div class="brand"><img src="${ICON}">كاشيراك POS</div>`;
const page = (css, body) => `<!doctype html><html dir="rtl"><head><meta charset="utf-8"><style>${BASE_CSS}${css}</style></head><body>${body}</body></html>`;

// ---------- Feature frames: [screenshot, url, Arabic title, English subtitle] ----------
const FRAMES = [
  ['pos-cart',              'index.php',                'شاشة البيع',               'Tap items, pick a payment method, sell & print in one click'],
  ['admin-dashboard',       'admin.php',                'لوحة التحكم',              'Live shift numbers, menu, stock & best sellers in one place'],
  ['admin-reports',         'admin/reports.php',        'تقارير المبيعات',          'Today, last 7 days, payment methods, stock alerts & date ranges'],
  ['shift-close',           'shift-close.php',          'إغلاق الوردية ومطابقة الدرج', 'Cash reconciliation: expected vs. counted, with notes'],
  ['returns',               'returns.php',              'المرتجعات',                'Partial or full returns with reason and refund method'],
  ['pos-barcode',           'index.php',                'دعم قارئ الباركود',        'Scan a barcode — the item is found and added instantly'],
  ['pos-category',          'index.php',                'تصنيفات وبحث فوري',        'Filter the menu by category or search by name'],
  ['history',               'history.php',              'سجل فواتير الوردية',       'Reprint or cancel any order — every action is audited'],
  ['admin-expenses',        'admin/expenses.php',       'مصروفات الوردية',          'Record shift expenses — deducted from expected cash'],
  ['admin-shift-history',   'shift-history.php',        'تاريخ الورديات',           'Every closed shift with sales, orders and cash difference'],
  ['admin-shift-details',   'shift-details.php?id=6',   'تقرير وردية مفصّل',        'Payment breakdown, best sellers and reconciliation per shift'],
  ['admin-add-item',        'admin.php',                'الأصناف والمخزون',         'Price, cost, barcode and stock tracking per item'],
  ['admin-payment-methods', 'admin/payment-methods.php','طرق الدفع',                'Cash, Bankak, MyCashi, bank transfer — or add your own'],
  ['admin-users',           'admin/users.php',          'المستخدمون والصلاحيات',    'Admins and cashiers with fine-grained permissions'],
  ['admin-settings',        'admin/settings.php',       'إعدادات المحل والفاتورة',  'Shop name, currency, receipt footer and date format'],
  ['admin-backup',          'admin/backup.php',         'نسخ احتياطي واستعادة',      'One-click backup download and safe restore'],
  ['login',                 'login.php',                'دخول آمن',                 'Role-based login for admins and cashiers'],
  ['setup-1-shop',          'install.php',              'معالج تثبيت سهل',          'Guided first-run setup: requirements, shop, admin, license'],
];

const frameHtml = ([img, url, ar, en]) => page(`
  .stage { position: absolute; top: 290px; left: 50%; transform: translateX(-50%); width: 1300px; }`,
  `<div class="head">${brandHtml}<h1>${ar}</h1><p>${en}</p></div>
   <div class="stage">${windowHtml(shot(img), url)}</div>`);

// ---------- Receipt ----------
const receiptHtml = () => page(`
  .wrap { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; gap: 110px; padding: 0 120px; }
  .text { max-width: 640px; }
  h1 { font-size: 64px; font-weight: 800; line-height: 1.2; margin: 26px 0 10px; }
  p.ar { font-size: 26px; color: #dbeafe; line-height: 1.7; }
  p.en { direction: ltr; text-align: right; font-size: 22px; color: #93c5fd; margin-top: 10px; }
  ul { list-style: none; padding: 0; margin-top: 30px; display: grid; gap: 14px; font-size: 23px; }
  li { display: flex; gap: 12px; align-items: center; }
  li b { width: 34px; height: 34px; border-radius: 10px; background: rgba(52,211,153,.2); color: #6ee7b7; display: grid; place-items: center; font-size: 20px; }
  .paper { position: relative; width: 430px; background: #fff; padding: 18px 10px 24px; border-radius: 6px 6px 0 0; transform: rotate(-3deg);
    box-shadow: 0 50px 90px -20px rgba(0,0,0,.65); }
  .paper::after { content: ''; position: absolute; left: 0; right: 0; bottom: -14px; height: 14px;
    background: linear-gradient(135deg, #fff 50%, transparent 50%) 0 0/20px 14px repeat-x, linear-gradient(225deg, #fff 50%, transparent 50%) 10px 0/20px 14px repeat-x; }
  .paper img { width: 100%; display: block; }`,
  `<div class="wrap">
    <div class="text">
      ${brandHtml}
      <h1>فاتورة جاهزة للطباعة</h1>
      <p class="ar">بضغطة «إتمام البيع وطباعة» يُحفظ الطلب وتُطبع الفاتورة فوراً على أي طابعة — حرارية أو عادية.</p>
      <p class="en">One click saves the order and prints the receipt on any printer.</p>
      <ul><li><b>✓</b>اسم المحل وتذييل الفاتورة من الإعدادات</li><li><b>✓</b>رقم الفاتورة والتاريخ وطريقة الدفع</li><li><b>✓</b>إعادة طباعة أي فاتورة من السجل</li></ul>
    </div>
    <div class="paper"><img src="${shot('receipt')}"></div>
  </div>`);

// ---------- Devices (tablet + phone) ----------
const devicesHtml = () => page(`
  .tablet { position: absolute; top: 300px; left: 70px; width: 1010px; padding: 26px; border-radius: 46px;
    background: linear-gradient(145deg, #1f2937, #0b1220); box-shadow: 0 50px 100px -20px rgba(0,0,0,.7), inset 0 0 0 2px rgba(255,255,255,.08); }
  .phone { position: absolute; top: 250px; right: 120px; width: 340px; height: 700px; padding: 14px; border-radius: 52px; overflow: hidden;
    background: linear-gradient(145deg, #1f2937, #0b1220); box-shadow: 0 50px 100px -20px rgba(0,0,0,.75), inset 0 0 0 2px rgba(255,255,255,.1); }
  .tablet img { display: block; width: 100%; border-radius: 20px; }
  .phone img { display: block; width: 100%; border-radius: 40px; }`,
  `<div class="head">${brandHtml}<h1>على الكمبيوتر والتابلت والجوال</h1><p>Responsive, touch-friendly — works on PCs, tablets and phones</p></div>
   <div class="tablet"><img src="${shot('pos-tablet')}"></div>
   <div class="phone"><img src="${shot('pos-mobile')}"></div>`);

// ---------- Hero / social card ----------
const heroHtml = ({ w, h, scale }) => page(`
  .canvas { width: 1600px; height: 900px; transform: scale(${scale}); transform-origin: top left; position: absolute; top: 0; left: 0; overflow: hidden; }
  .text { position: absolute; top: 140px; right: 80px; width: 640px; }
  h1 { font-size: 96px; font-weight: 800; line-height: 1.05; margin-top: 30px; letter-spacing: -1px; }
  h1 span { background: linear-gradient(90deg, #34d399, #60a5fa); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .tag { font-size: 30px; color: #dbeafe; line-height: 1.6; margin-top: 18px; font-weight: 600; }
  .en { direction: ltr; text-align: right; font-size: 22px; color: #93c5fd; margin-top: 8px; }
  .chips { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
  .chip { padding: 9px 18px; border-radius: 999px; font-size: 20px; font-weight: 700; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); }
  .chip i { font-style: normal; color: #34d399; margin-left: 6px; }
  .back { position: absolute; top: 80px; left: -160px; width: 880px; opacity: .9; transform: perspective(2000px) rotateY(14deg) rotateX(4deg); }
  .front { position: absolute; top: 300px; left: 30px; width: 780px; transform: perspective(2000px) rotateY(10deg) rotateX(2deg); }
  .receipt { position: absolute; top: 520px; left: 640px; width: 220px; background: #fff; padding: 8px 4px 12px; border-radius: 4px;
    transform: rotate(5deg); box-shadow: 0 30px 60px -10px rgba(0,0,0,.6); }
  .receipt img { width: 100%; display: block; }`,
  `<div class="canvas">
    ${windowHtml(shot('admin-dashboard'), 'admin.php', 'back')}
    ${windowHtml(shot('pos-cart'), 'index.php', 'front')}
    <div class="receipt"><img src="${shot('receipt')}"></div>
    <div class="text">
      ${brandHtml}
      <h1>كاشيراك <span>POS</span></h1>
      <div class="tag">نظام نقاط بيع متكامل للمطاعم والكافيهات والبقالات — يعمل بالكامل بدون إنترنت.</div>
      <div class="en">Offline-first point of sale for small businesses · v1.0</div>
      <div class="chips">
        <span class="chip"><i>●</i>بدون إنترنت</span><span class="chip"><i>●</i>باركود ومخزون</span>
        <span class="chip"><i>●</i>ورديات ومطابقة الدرج</span><span class="chip"><i>●</i>تقارير ومرتجعات</span><span class="chip"><i>●</i>بنكك وماي كاشي</span>
      </div>
    </div>
  </div>`).replace('<body>', `<body style="width:${w}px;height:${h}px">`);

(async () => {
  const browser = await chromium.launch();
  const render = async (html, file, w = 1600, h = 1000, dsf = 1.5) => {
    const p = await browser.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: dsf });
    await p.setContent(html, { waitUntil: 'load' });
    await p.evaluate(() => document.fonts.ready);
    await p.screenshot({ path: path.join(OUT, file) });
    await p.close();
    console.log('saved', file);
  };

  await render(heroHtml({ w: 1600, h: 900, scale: 1 }), 'hero.png', 1600, 900);
  await render(heroHtml({ w: 1280, h: 640, scale: 0.8 }), 'social-preview.png', 1280, 640, 1);
  for (const f of FRAMES) await render(frameHtml(f), `${f[0]}.png`);
  await render(receiptHtml(), 'receipt.png');
  await render(devicesHtml(), 'devices.png');
  await browser.close();
})();
