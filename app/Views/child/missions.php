<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $family, 'profile' => $profile, 'balance' => $balance, 'headerDate' => date('Y-m-d')]) ?>
<header class="mb-3"><h2 class="h4 mb-1">Misi Minggu Ini</h2><p class="text-secondary mb-0">Kemajuan dikira daripada rutin yang telah disahkan.</p></header>

<?php if ($missions === []): ?>
    <div class="card border-0 shadow-sm rounded-4"><div class="card-body text-secondary">Belum ada misi untuk minggu ini.</div></div>
<?php else: ?><div class="row g-3">
    <?php foreach ($missions as $mission): ?>
        <div class="col-12 col-sm-6"><article class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between gap-3 mb-2"><h3 class="h5 mb-0"><?= ui_icon($mission['mission_type'] === 'perfect_day_count' ? 'crown' : 'book') ?> <?= esc($mission['title']) ?></h3><?php if ($mission['status'] === 'completed'): ?><span class="badge text-bg-success">Misi Selesai</span><?php endif ?></div>
            <?php if ($mission['description']): ?><p class="text-secondary"><?= esc($mission['description']) ?></p><?php endif ?>
            <p class="h4 mb-2"><?= esc($mission['progress']) ?> / <?= esc($mission['target_value']) ?></p>
            <div class="progress rounded-pill mb-3" role="progressbar" aria-label="Kemajuan <?= esc($mission['title']) ?>" aria-valuenow="<?= esc($mission['percentage']) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= esc($mission['percentage']) ?>%"></div></div>
            <p class="fw-semibold text-primary mb-0">Bonus +<?= esc($mission['bonus_points']) ?> mata</p>
            <?php if ($mission['status'] === 'completed'): ?><p class="text-success fw-semibold mt-2 mb-0">+<?= esc($mission['bonus_points']) ?> mata</p><?php endif ?>
        </div></article></div>
    <?php endforeach ?>
</div><?php endif ?>
<?= $this->endSection() ?>
