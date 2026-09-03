<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <a href="<?= route_to('parent.dashboard') ?>" class="small text-decoration-none">← Papan Pemuka</a>
        <h1 class="h2 mb-1">Peranti <?= esc($child->name) ?></h1>
        <p class="text-secondary mb-0">Satu peranti dipercayai boleh diaktifkan untuk anak ini.</p>
    </div>
</div>

<?php if (session('errors')): ?>
    <div class="alert alert-danger" role="alert">
        <?php foreach (session('errors') as $error): ?>
            <div><?= esc($error) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<section class="card border-0 shadow-sm mb-4" aria-labelledby="setup-heading">
    <div class="card-body p-4">
        <h2 id="setup-heading" class="h4">Sediakan Peranti Ini</h2>
        <p class="text-secondary">Buka halaman ini pada telefon <?= esc($child->name) ?>. Selepas persediaan, sesi Ibu bapa pada pelayar ini akan ditutup dan Mod Anak akan dibuka.</p>
        <form action="<?= route_to('parent.child.devices.setup', $child->id) ?>" method="post" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md">
                <label for="device_name" class="form-label">Nama peranti</label>
                <input id="device_name" name="device_name" type="text" maxlength="120" class="form-control" value="<?= esc(old('device_name')) ?>" placeholder="Contoh: Telefon Adam">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary w-100">Sediakan Peranti Ini</button>
            </div>
        </form>
    </div>
</section>

<section aria-labelledby="device-list-heading">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 id="device-list-heading" class="h4 mb-0">Sejarah peranti</h2>
        <form action="<?= route_to('parent.child.devices.reset', $child->id) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">Tetapkan Semula Peranti</button>
        </form>
    </div>

    <?php if ($devices === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada peranti disediakan.</div></div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($devices as $device): ?>
                <?php $active = (bool) $device['is_trusted'] && $device['revoked_at'] === null && strtotime((string) $device['expires_at']) > time(); ?>
                <div class="col-12">
                    <article class="card border-0 shadow-sm">
                        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <h3 class="h5 mb-0"><?= esc($device['device_name'] ?: 'Peranti tanpa nama') ?></h3>
                                    <span class="badge <?= $active ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $active ? 'Dipercayai' : 'Tidak aktif' ?></span>
                                </div>
                                <dl class="row small text-secondary mb-0">
                                    <dt class="col-5 col-sm-3">Jenis</dt><dd class="col-7 col-sm-9"><?= esc(ui_label('device', $device['device_type'])) ?></dd>
                                    <dt class="col-5 col-sm-3">Disediakan</dt><dd class="col-7 col-sm-9"><?= esc($device['created_at']) ?></dd>
                                    <dt class="col-5 col-sm-3">Tamat tempoh</dt><dd class="col-7 col-sm-9"><?= esc($device['expires_at']) ?></dd>
                                    <dt class="col-5 col-sm-3">Kali terakhir digunakan</dt><dd class="col-7 col-sm-9"><?= esc($device['last_used_at'] ?: 'Belum digunakan') ?></dd>
                                </dl>
                            </div>
                            <?php if ($active): ?>
                                <form action="<?= route_to('parent.child.devices.revoke', $child->id, $device['id']) ?>" method="post" class="align-self-md-center">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger">Batalkan Akses Peranti</button>
                                </form>
                            <?php endif ?>
                        </div>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
