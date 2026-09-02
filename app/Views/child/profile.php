<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<header class="text-center mb-4">
    <div class="avatar-placeholder mx-auto mb-3" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($child->name, 0, 1))) ?></div>
    <h1 class="h2 mb-1"><?= esc($child->name) ?></h1>
    <p class="text-secondary mb-0"><?= esc($family['name']) ?></p>
</header>
<section class="row g-3 mb-4" aria-label="Ringkasan profile">
    <div class="col-6"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center"><p class="small text-secondary mb-1">Points</p><p class="h3 mb-0">⭐ <?= esc($balance) ?></p></div></div></div>
    <div class="col-6"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center"><p class="small text-secondary mb-1">Streak</p><p class="h3 mb-0">🔥 <?= esc($streak) ?></p></div></div></div>
</section>
<section class="card border-0 shadow-sm"><div class="card-body">
    <h2 class="h5">Tentang saya</h2>
    <dl class="row mb-0"><dt class="col-5">Tarikh lahir</dt><dd class="col-7 text-end"><?= esc($profile['date_of_birth'] ?? '—') ?></dd><dt class="col-5">Peranti</dt><dd class="col-7 text-end"><?= esc($device['device_name'] ?: 'Trusted device') ?></dd></dl>
</div></section>
<?= $this->endSection() ?>
