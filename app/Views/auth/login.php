<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffbc0b">
    <title><?= esc($title) ?> · RutinKu</title>
    <link href="<?= base_url('assets/vendor/bootstrap-5.3.3.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/buttons.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/fontawesome/css/fontawesome.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/fontawesome/css/solid.min.css') ?>" rel="stylesheet">
</head>
<body class="auth-shell d-flex align-items-center min-vh-100 py-4">
<main class="container">
    <section class="auth-content mx-auto">
            <p class="brand-mark mb-2">RutinKu</p>
            <h1 class="h3 mb-2">Selamat kembali</h1>
            <p class="text-secondary mb-4">Log masuk sebagai Ibu bapa atau Anak menggunakan e-mel sendiri.</p>

            <?php if (session('error')): ?>
                <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
            <?php endif ?>
            <?php if (session('success')): ?>
                <div class="alert alert-success" role="alert"><?= esc(session('success')) ?></div>
            <?php endif ?>
            <?php if (session('errors')): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach (session('errors') as $error): ?>
                        <div><?= esc($error) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form action="<?= route_to('parent.login.attempt') ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mel</label>
                    <input id="email" name="email" type="email" class="form-control form-control-lg" value="<?= esc(old('email')) ?>" autocomplete="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Kata laluan</label>
                    <div class="input-group input-group-lg">
                        <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Tunjukkan kata laluan"><i class="fa-solid fa-eye" data-password-toggle-icon aria-hidden="true"></i><span class="visually-hidden" data-password-toggle-label>Tunjukkan kata laluan</span></button>
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input id="remember_me" name="remember_me" value="1" type="checkbox" class="form-check-input" <?= old('remember_me') === '1' ? 'checked' : '' ?>>
                    <label for="remember_me" class="form-check-label">Ingat saya selama 30 hari</label>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Log masuk</button>
            </form>
            <p class="small text-secondary text-center mb-0 mt-4">Akaun akan dibawa ke dashboard mengikut peranan masing-masing.</p>
    </section>
</main>
<script defer src="<?= ui_asset_url('assets/js/password-toggle.js') ?>"></script>
</body>
</html>
