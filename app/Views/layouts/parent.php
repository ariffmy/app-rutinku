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
<nav class="navbar navbar-expand bg-white border-bottom sticky-top">
    <div class="container-fluid container-xl">
        <a class="navbar-brand fw-bold text-primary" href="<?= route_to('parent.dashboard') ?>">RutinKu</a>
        <div class="d-flex align-items-center gap-1 ms-2">
            <a href="<?= route_to('parent.dashboard') ?>" class="btn btn-link btn-sm text-decoration-none">Dashboard</a>
            <a href="<?= route_to('parent.children') ?>" class="btn btn-link btn-sm text-decoration-none">Children</a>
            <a href="<?= route_to('parent.routines') ?>" class="btn btn-link btn-sm text-decoration-none">Routines</a>
            <a href="<?= route_to('parent.points') ?>" class="btn btn-link btn-sm text-decoration-none">Points</a>
            <a href="<?= route_to('parent.rewards') ?>" class="btn btn-link btn-sm text-decoration-none">Rewards</a>
            <a href="<?= route_to('parent.ranking') ?>" class="btn btn-link btn-sm text-decoration-none">Ranking</a>
            <a href="<?= route_to('parent.reports') ?>" class="btn btn-link btn-sm text-decoration-none">Reports</a>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm ms-auto me-2" data-install-app hidden>Pasang app</button>
        <form action="<?= route_to('parent.logout') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">Log keluar</button>
        </form>
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
