<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="mb-4"><a href="<?= route_to('parent.missions') ?>" class="text-decoration-none">← Misi</a><h1 class="h2 mt-2">Tambah Misi</h1></header>
<?php if (session('errors')): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>

<form action="<?= route_to('parent.missions.create') ?>" method="post" class="card border-0 shadow-sm">
    <?= csrf_field() ?>
    <div class="card-body p-4"><div class="row g-3">
        <div class="col-12"><label for="title" class="form-label">Nama misi</label><input id="title" name="title" maxlength="160" required class="form-control" value="<?= esc(old('title') ?? '') ?>" placeholder="Contoh: Rajin Membaca"></div>
        <div class="col-12"><label for="description" class="form-label">Penerangan</label><textarea id="description" name="description" maxlength="1000" rows="3" class="form-control" placeholder="Contoh: Selesaikan rutin membaca 4 kali"><?= esc(old('description') ?? '') ?></textarea></div>
        <div class="col-12 col-md-6"><label for="mission_type" class="form-label">Jenis misi</label><select id="mission_type" name="mission_type" required class="form-select"><option value="routine_completion_count" <?= old('mission_type') === 'routine_completion_count' ? 'selected' : '' ?>>Jumlah rutin selesai</option><option value="perfect_day_count" <?= old('mission_type') === 'perfect_day_count' ? 'selected' : '' ?>>Jumlah Perfect Day</option></select></div>
        <div class="col-6 col-md-3"><label for="target_value" class="form-label">Target</label><input id="target_value" name="target_value" type="number" min="1" max="1000" required class="form-control" value="<?= esc(old('target_value') ?? 1) ?>"></div>
        <div class="col-6 col-md-3"><label for="bonus_points" class="form-label">Bonus mata</label><input id="bonus_points" name="bonus_points" type="number" min="0" max="1000000" required class="form-control" value="<?= esc(old('bonus_points') ?? 0) ?>"></div>
        <div class="col-6"><label for="start_date" class="form-label">Tarikh mula</label><input id="start_date" name="start_date" type="date" required class="form-control" value="<?= esc(old('start_date') ?? $defaultStart) ?>"></div>
        <div class="col-6"><label for="end_date" class="form-label">Tarikh tamat</label><input id="end_date" name="end_date" type="date" required class="form-control" value="<?= esc(old('end_date') ?? $defaultEnd) ?>"><div class="form-text">Tempoh maksimum tujuh hari.</div></div>
        <fieldset class="col-12"><legend class="h6">Tetapkan kepada Anak</legend>
            <?php $selected = (array) (old('child_ids') ?? []); ?>
            <?php if ($children === []): ?><p class="text-secondary">Tiada Anak aktif.</p><?php endif ?>
            <?php foreach ($children as $child): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="child_ids[]" value="<?= esc($child['id']) ?>" id="child-<?= esc($child['id']) ?>" <?= in_array((string) $child['id'], array_map('strval', $selected), true) ? 'checked' : '' ?>><label class="form-check-label" for="child-<?= esc($child['id']) ?>"><?= esc($child['name']) ?></label></div><?php endforeach ?>
        </fieldset>
    </div></div>
    <div class="card-footer bg-white border-0 p-4 pt-0"><button class="btn btn-primary" type="submit" <?= $children === [] ? 'disabled' : '' ?>>Simpan misi</button></div>
</form>
<?= $this->endSection() ?>
