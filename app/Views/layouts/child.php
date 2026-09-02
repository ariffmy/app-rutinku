<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
<body class="child-shell">
<main class="container py-3 pb-5">
    <div class="text-end mb-2"><button type="button" class="btn btn-outline-primary btn-sm" data-install-app hidden>Pasang app</button></div>
    <?php if (session('success')): ?>
        <div class="alert alert-success" role="status"><?= esc(session('success')) ?></div>
    <?php endif ?>
    <?php if (session('error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
    <?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
<nav class="child-nav fixed-bottom bg-white border-top" aria-label="Navigasi Child">
    <div class="container d-flex justify-content-around py-2">
        <a class="nav-link <?= ($activeNav ?? '') === 'today' ? 'active fw-semibold' : '' ?>" href="<?= route_to('child.today') ?>">Today</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'rewards' ? 'active fw-semibold' : '' ?>" href="<?= route_to('child.rewards') ?>">Rewards</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'progress' ? 'active fw-semibold' : '' ?>" href="<?= route_to('child.progress') ?>">Progress</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'profile' ? 'active fw-semibold' : '' ?>" href="<?= route_to('child.profile') ?>">Profile</a>
    </div>
</nav>
<script defer src="<?= base_url('assets/js/app.js') ?>" data-service-worker-url="<?= base_url('service-worker.js') ?>"></script>
</body>
</html>
