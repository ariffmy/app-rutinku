<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php $active = old('is_active') !== null ? (bool) old('is_active') : (bool) ($reward['is_active'] ?? true); ?>
<header class="mb-4"><a href="<?= route_to('parent.rewards') ?>" class="text-decoration-none">← Ganjaran</a><h1 class="h2 mt-2"><?= esc($title) ?></h1></header>
<?php if (session('errors')): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= esc($action) ?>" method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
    <?= csrf_field() ?>
    <div class="card-body p-4"><div class="row g-3">
        <div class="col-12"><label for="title" class="form-label">Nama ganjaran</label><input id="title" name="title" class="form-control" maxlength="160" required value="<?= esc(old('title') ?? ($reward['title'] ?? '')) ?>"></div>
        <div class="col-12"><label for="category" class="form-label">Kategori</label><input id="category" name="category" class="form-control" maxlength="80" list="reward-categories" value="<?= esc(old('category') ?? ($reward['category'] ?? 'Lain-lain')) ?>"><datalist id="reward-categories"><option value="Aktiviti"><option value="Permainan"><option value="Makanan"><option value="Hadiah"><option value="Lain-lain"></datalist></div>
        <div class="col-12 col-md-6"><label for="points_required" class="form-label">Mata diperlukan</label><input id="points_required" name="points_required" type="number" min="1" max="1000000" class="form-control" required value="<?= esc(old('points_required') ?? ($reward['points_required'] ?? 1)) ?>"></div>
        <div class="col-12 col-md-6"><label for="image_upload" class="form-label">Gambar rujukan (pilihan)</label><input id="image_upload" name="image_upload" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"><div class="form-text">JPG, PNG atau WebP, maksimum 4 MB / 12 megapiksel. Kosongkan untuk kekalkan gambar sedia ada.</div><?php if ($url = ui_image_url($reward['image'] ?? null)): ?><img src="<?= esc($url) ?>" alt="Gambar ganjaran semasa" class="reward-image mt-2"><?php endif ?></div>
        <div class="col-12"><input type="hidden" name="is_active" value="0"><div class="form-check form-switch"><input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" <?= $active ? 'checked' : '' ?>><label for="is_active" class="form-check-label">Ganjaran aktif</label></div></div>
    </div></div>
    <div class="card-footer bg-white border-0 px-4 pb-4"><button class="btn btn-primary" type="submit">Simpan ganjaran</button></div>
</form>
<?= $this->endSection() ?>
