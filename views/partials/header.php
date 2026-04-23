<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?? 'Cashirak POS' ?></title>
    <!-- Bootstrap RTL + Icons (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6f9; }
        .item-btn { font-size: 1.4rem; padding: 1.5rem 0.5rem; margin: 0.3rem; width: 150px; height: 130px; }
        .cart-container { background: white; padding: 1.5rem; border-radius: 20px; min-height: 85vh; }
        .cart-item { font-size: 1.2rem; border-bottom: 1px dashed #ddd; padding: 10px 0; }
        .total-box { background: #e9ecef; padding: 15px; border-radius: 12px; font-size: 2rem; font-weight: bold; }
        .quick-actions .btn { font-size: 1.1rem; padding: 0.8rem; }
        #searchInput { border-radius: 30px; padding: 12px 20px; border: 1px solid #ddd; font-size: 1.2rem; }
    </style>
</head>
<body class="p-3">
