<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#345995">
    <meta name="application-name" content="RutinKu">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?= esc($title ?? 'RutinKu') ?> · RutinKu</title>
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= base_url('assets/icons/icon-192.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/icons/apple-touch-icon.png') ?>">
    <link href="<?= base_url('assets/vendor/bootstrap-5.3.3.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="parent-shell">
<?php
$navigation = [
    ['Papan Pemuka', 'parent.dashboard', url_is('dashboard')],
    ['Anak-anak', 'parent.children', url_is('children*')],
    ['Rutin', 'parent.routines', url_is('routines*') || url_is('routine-tasks*')],
    ['Mata', 'parent.points', url_is('points*')],
    ['Ganjaran', 'parent.rewards', url_is('rewards*') || url_is('reward-redemptions*')],
    ['Kedudukan', 'parent.ranking', url_is('ranking*')],
    ['Laporan', 'parent.reports', url_is('reports*')],
];
?>
<nav class="parent-nav bg-white border-bottom sticky-top" aria-label="Navigasi Ibu bapa" data-parent-nav>
    <div class="container-xl parent-nav-layout">
        <a class="navbar-brand fw-bold text-primary" href="<?= route_to('parent.dashboard') ?>">RutinKu</a>
        <button type="button" class="btn btn-outline-primary parent-menu-toggle" aria-controls="parent-nav-panel" aria-expanded="false" data-parent-menu-toggle hidden>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            <span>Menu</span>
        </button>
        <div id="parent-nav-panel" class="parent-nav-panel">
            <ul class="parent-nav-links">
                <?php foreach ($navigation as [$label, $route, $active]): ?>
                    <li><a href="<?= route_to($route) ?>" class="parent-nav-link<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a></li>
                <?php endforeach ?>
            </ul>
            <div class="parent-nav-actions">
                <button type="button" class="btn btn-outline-primary btn-sm" data-install-app hidden>Pasang aplikasi</button>
                <form action="<?= route_to('parent.logout') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Log keluar</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container-xl py-4">
    <?php if (session('success')): ?>
        <div class="alert alert-success" role="alert"><?= esc(session('success')) ?></div>
    <?php endif ?>
    <?php if (session('error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
    <?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<script defer src="<?= base_url('assets/js/app.js') ?>" data-service-worker-url="<?= base_url('service-worker.js') ?>"></script>
</body>
</html>
