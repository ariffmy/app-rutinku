<?= $this->extend('layouts/child') ?>
<?= $this->section('content') ?>
<?php
$dob = $profile['date_of_birth'] ?? null;
$birth = ui_date($dob) !== '—' ? new \DateTimeImmutable($dob, new \DateTimeZone(app_timezone())) : null;
$today = new \DateTimeImmutable('today', new \DateTimeZone(app_timezone()));
$age = $birth && $birth <= $today ? $birth->diff($today)->y . ' tahun' : '—';
?>
<header class="text-center mb-4">
    <div class="d-flex justify-content-center mb-3"><?= ui_avatar($profile['avatar'] ?? null) ?></div>
    <h1 class="h2 mb-1"><?= esc($child->name) ?></h1>
    <p class="text-secondary"><?= esc($family['name']) ?></p>
    <p class="h4"><?= ui_icon('star') ?> <?= esc($balance) ?> mata</p>
</header>
<section class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h2 class="h5">Tentang saya</h2>
    <dl class="row mb-0">
        <dt class="col-5">Tarikh lahir</dt><dd class="col-7 text-end"><?= esc(ui_date($dob)) ?></dd>
        <dt class="col-5">Umur</dt><dd class="col-7 text-end"><?= esc($age) ?></dd>
        <dt class="col-5">Ibu bapa</dt><dd class="col-7 text-end"><?= esc(implode(', ', array_column($parents, 'name')) ?: '—') ?></dd>
    </dl>
</div></section>
<section class="card border-0 shadow-sm"><div class="card-body">
    <h2 class="h5">Gambar saya</h2>
    <form action="<?= route_to('child.profile.update') ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <fieldset class="mb-3"><legend class="fs-6">Pilih avatar</legend><div class="d-flex flex-wrap gap-3">
            <label><input type="radio" name="avatar" value="" checked> Kekalkan gambar</label>
            <?php foreach (ui_avatar_options() as $key => $label): ?>
                <label class="avatar-choice"><input type="radio" name="avatar" value="<?= esc($key) ?>"> <?= ui_icon($key) ?> <?= esc($label) ?></label>
            <?php endforeach ?>
        </div></fieldset>
        <label for="photo" class="form-label">Atau muat naik gambar sendiri</label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control" aria-describedby="photo-help">
        <p id="photo-help" class="form-text">JPG, PNG atau WebP, maksimum 4 MB / 12 megapiksel. Gambar dimuat naik menggantikan pilihan avatar.</p>
        <button class="btn btn-primary" type="submit">Simpan gambar</button>
    </form>
</div></section>
<?= $this->endSection() ?>
