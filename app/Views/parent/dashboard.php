<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="mb-4">
    <p class="text-uppercase text-secondary small fw-semibold mb-1"><?= esc($family['name']) ?></p>
    <h1 class="h2 mb-1">Hai, <?= esc($currentUser->name) ?></h1>
    <p class="text-secondary mb-0">Pantau kemajuan keluarga dan urus rutin, ganjaran serta peranti.</p>
</header>

<section class="row g-3 mb-4" aria-label="Ringkasan hari ini">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Tugasan Hari Ini</p><p class="h2 mb-0"><?= esc($todayRanking['total_scheduled_tasks']) ?></p></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Tugasan Selesai</p><p class="h2 mb-0"><?= esc($todayRanking['total_completed_tasks']) ?></p></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Ganjaran Menunggu Kelulusan</p><p class="h2 mb-0"><?= esc($pendingRewards) ?></p></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Pendahulu Hari Ini</p><p class="h5 mb-0"><?= isset($todayRanking['rows'][0]) ? esc($todayRanking['rows'][0]['name']) : '—' ?></p></div></div></div>
</section>

<section class="mb-5" aria-labelledby="family-progress-heading">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3"><h2 id="family-progress-heading" class="h4 mb-0">Kemajuan Keluarga</h2><a href="<?= route_to('parent.ranking') ?>" class="btn btn-primary d-inline-flex align-items-center">Lihat kedudukan</a></div>
    <?php if ($todayRanking['rows'] === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Tiada Anak yang layak untuk kedudukan hari ini.</div></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Anak</th><th>Tugasan</th><th>Penyelesaian</th><th>Mata Diperoleh</th><th>Hari Berturut-turut</th></tr></thead><tbody>
            <?php foreach ($todayRanking['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['name']) ?></td><td><?= esc($row['completed_tasks']) ?> / <?= esc($row['scheduled_tasks']) ?></td><td><?= esc($row['completion_percentage']) ?>%</td><td>⭐ <?= esc($row['earned_points']) ?></td><td>🔥 <?= esc($row['current_streak']) ?></td></tr><?php endforeach ?>
        </tbody></table></div></div>
    <?php endif ?>
</section>

<section aria-labelledby="children-heading">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 id="children-heading" class="h4 mb-0">Anak-anak</h2>
        <div><span class="badge text-bg-light border me-2"><?= count($children) ?> ahli</span><a href="<?= route_to('parent.children') ?>" class="btn btn-primary d-inline-flex align-items-center">Urus Anak</a></div>
    </div>
    <div class="row g-3">
        <?php foreach ($children as $child): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-placeholder" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($child['name'], 0, 1))) ?></div>
                        <div>
                            <h3 class="h5 mb-1"><?= esc($child['name']) ?></h3>
                            <span class="badge <?= $child['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $child['is_active'] ? 'Aktif' : 'Tidak aktif' ?>
                            </span>
                            <div class="mt-2">
                                <a href="<?= route_to('parent.child.devices', $child['id']) ?>" class="btn btn-primary d-inline-flex align-items-center">Urus peranti</a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach ?>
    </div>
</section>
<?= $this->endSection() ?>
