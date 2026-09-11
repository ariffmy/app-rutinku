<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div><h1 class="h2 mb-1">Misi Minggu Ini</h1><p class="text-secondary mb-0">Cabaran ringkas untuk Anak dalam <?= esc($family['name']) ?>.</p></div>
    <a href="<?= route_to('parent.missions.new') ?>" class="btn btn-primary">Tambah Misi</a>
</header>

<?php if ($missions === []): ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada misi mingguan.</div></div>
<?php else: ?><div class="row g-3">
    <?php foreach ($missions as $mission): ?>
        <div class="col-12 col-lg-6"><article class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between gap-3"><h2 class="h5"><?= esc($mission['title']) ?></h2><span class="badge <?= $mission['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $mission['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span></div>
            <?php if ($mission['description']): ?><p class="text-secondary"><?= esc($mission['description']) ?></p><?php endif ?>
            <p class="mb-1"><strong>Target:</strong> <?= esc($mission['target_value']) ?></p>
            <p class="mb-1"><strong>Bonus:</strong> +<?= esc($mission['bonus_points']) ?> mata</p>
            <p class="small text-secondary"><?= esc(ui_date($mission['start_date'])) ?> – <?= esc(ui_date($mission['end_date'])) ?></p>
            <div class="border-top pt-3 mt-3">
                <?php foreach ($mission['assignments'] as $assignment): ?>
                    <div class="d-flex justify-content-between gap-3 mb-2"><span><?= esc($assignment['child_name']) ?></span><span><?= esc($assignment['progress']) ?> / <?= esc($mission['target_value']) ?> · <?= esc(match ($assignment['status']) { 'completed' => 'Selesai', 'expired' => 'Tamat', 'cancelled' => 'Dibatalkan', default => 'Aktif' }) ?></span></div>
                <?php endforeach ?>
            </div>
        </div></article></div>
    <?php endforeach ?>
</div><?php endif ?>
<?= $this->endSection() ?>
