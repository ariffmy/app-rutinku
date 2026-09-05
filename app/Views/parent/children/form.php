<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php $editing = $child !== null; ?>
<header class="mb-4"><h1 class="h2 mb-1"><?= $editing ? 'Sunting Anak' : 'Tambah Anak' ?></h1><p class="text-secondary mb-0">Anak tidak memerlukan kata laluan atau log masuk sendiri. Ibu bapa menyediakan peranti dipercayai secara berasingan.</p></header>
<?php if (session('errors')): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= $editing ? route_to('parent.children.update', $child->id) : route_to('parent.children.create') ?>" method="post" enctype="multipart/form-data" class="card border-0 shadow-sm"><div class="card-body">
    <?= csrf_field() ?>
    <section class="mb-4" aria-labelledby="child-photo-heading">
        <h2 id="child-photo-heading" class="h5">Gambar anak</h2>
        <div class="mb-3"><?= ui_avatar($profile['avatar'] ?? null, false) ?></div>
        <fieldset class="mb-3"><legend class="fs-6">Pilih avatar</legend><div class="d-flex flex-wrap gap-3">
            <label><input type="radio" name="avatar" value="" <?= ! old('avatar') ? 'checked' : '' ?>> Kekalkan gambar</label>
            <?php foreach (ui_avatar_options() as $key => $label): ?>
                <label class="avatar-choice"><input type="radio" name="avatar" value="<?= esc($key) ?>" <?= old('avatar') === $key ? 'checked' : '' ?>> <?= ui_icon($key) ?> <?= esc($label) ?></label>
            <?php endforeach ?>
        </div></fieldset>
        <label for="photo" class="form-label">Atau muat naik gambar sendiri</label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control" aria-describedby="photo-help">
        <p id="photo-help" class="form-text">JPG, PNG atau WebP, maksimum 4 MB / 12 megapiksel. Gambar dimuat naik menggantikan pilihan avatar. Gambar ini turut digunakan dalam profil dan dashboard anak.</p>
    </section>
    <div class="mb-3"><label for="name" class="form-label">Nama</label><input id="name" name="name" class="form-control" required maxlength="120" value="<?= esc(old('name') ?? ($child->name ?? '')) ?>"></div>
    <div class="mb-3"><label for="date_of_birth" class="form-label">Tarikh lahir</label><input id="date_of_birth" name="date_of_birth" type="date" class="form-control" value="<?= esc(old('date_of_birth') ?? ($profile['date_of_birth'] ?? '')) ?>"></div>
    <div class="mb-3"><label for="is_ranking_eligible" class="form-label">Kelayakan kedudukan</label><select id="is_ranking_eligible" name="is_ranking_eligible" class="form-select"><option value="1" <?= (string) (old('is_ranking_eligible') ?? ($profile['is_ranking_eligible'] ?? 1)) === '1' ? 'selected' : '' ?>>Layak</option><option value="0" <?= (string) (old('is_ranking_eligible') ?? ($profile['is_ranking_eligible'] ?? 1)) === '0' ? 'selected' : '' ?>>Tidak layak</option></select></div>
    <?php if ($editing): ?><div class="mb-3"><label for="is_active" class="form-label">Status akaun</label><select id="is_active" name="is_active" class="form-select"><option value="1" <?= (string) (old('is_active') ?? (int) $child->is_active) === '1' ? 'selected' : '' ?>>Aktif</option><option value="0" <?= (string) (old('is_active') ?? (int) $child->is_active) === '0' ? 'selected' : '' ?>>Tidak aktif</option></select><div class="form-text">Nyahaktif mengekalkan semua sejarah tugasan, mata dan ganjaran.</div></div><?php endif ?>
    <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Simpan</button><a href="<?= route_to('parent.children') ?>" class="btn btn-outline-secondary">Batal</a></div>
</div></form>
<?= $this->endSection() ?>
