<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php $editing = $child !== null; ?>
<header class="mb-4"><h1 class="h2 mb-1"><?= $editing ? 'Edit Child' : 'Tambah Child' ?></h1><p class="text-secondary mb-0">Child tidak menerima password atau public login. Parent menyediakan trusted device secara berasingan.</p></header>
<?php if (session('errors')): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= $editing ? route_to('parent.children.update', $child->id) : route_to('parent.children.create') ?>" method="post" class="card border-0 shadow-sm"><div class="card-body">
    <?= csrf_field() ?>
    <div class="mb-3"><label for="name" class="form-label">Nama</label><input id="name" name="name" class="form-control" required maxlength="120" value="<?= esc(old('name') ?? ($child->name ?? '')) ?>"></div>
    <div class="mb-3"><label for="date_of_birth" class="form-label">Tarikh lahir</label><input id="date_of_birth" name="date_of_birth" type="date" class="form-control" value="<?= esc(old('date_of_birth') ?? ($profile['date_of_birth'] ?? '')) ?>"></div>
    <div class="mb-3"><label for="is_ranking_eligible" class="form-label">Kelayakan ranking</label><select id="is_ranking_eligible" name="is_ranking_eligible" class="form-select"><option value="1" <?= (string) (old('is_ranking_eligible') ?? ($profile['is_ranking_eligible'] ?? 1)) === '1' ? 'selected' : '' ?>>Layak</option><option value="0" <?= (string) (old('is_ranking_eligible') ?? ($profile['is_ranking_eligible'] ?? 1)) === '0' ? 'selected' : '' ?>>Tidak layak</option></select></div>
    <?php if ($editing): ?><div class="mb-3"><label for="is_active" class="form-label">Status akaun</label><select id="is_active" name="is_active" class="form-select"><option value="1" <?= (string) (old('is_active') ?? (int) $child->is_active) === '1' ? 'selected' : '' ?>>Aktif</option><option value="0" <?= (string) (old('is_active') ?? (int) $child->is_active) === '0' ? 'selected' : '' ?>>Tidak aktif</option></select><div class="form-text">Nyahaktif mengekalkan semua sejarah tasks, points dan rewards.</div></div><?php endif ?>
    <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Simpan</button><a href="<?= route_to('parent.children') ?>" class="btn btn-outline-secondary">Batal</a></div>
</div></form>
<?= $this->endSection() ?>
