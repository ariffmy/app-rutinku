<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="mb-4">
    <p class="text-uppercase text-secondary small fw-semibold mb-1"><?= esc($family['name']) ?></p>
    <h1 class="h2 mb-1">Hai, <?= esc($currentUser->name) ?></h1>
    <p class="text-secondary mb-0">Pantau kemajuan keluarga dan urus rutin, ganjaran serta peranti.</p>
</header>

<section class="parent-summary-grid mb-4" aria-label="Ringkasan hari ini">
    <div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Ganjaran Menunggu Kelulusan</p><p class="h2 mb-0"><?= esc($pendingRewards) ?></p></div></div>
    <div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Pendahulu Hari Ini</p><p class="h5 mb-0"><?= isset($todayRanking['rows'][0]) ? esc($todayRanking['rows'][0]['name']) : '—' ?></p></div></div>
</section>

<section class="mb-5" aria-labelledby="children-heading">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 id="children-heading" class="h4 mb-0">Anak-anak</h2>
        <a href="<?= route_to('parent.children') ?>" class="btn btn-primary">Lihat semua</a>
    </div>
    <?php if ($children === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada anak dicipta.</div></div>
    <?php else: ?>
        <div class="parent-children-row">
            <?php foreach ($children as $child): ?>
                <div class="parent-child-column">
                    <article class="card parent-child-card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <?= ui_avatar($child['avatar'] ?? null, false) ?>
                            <div class="min-w-0">
                                <h3 class="h5 mb-1 text-truncate"><?= esc($child['name']) ?></h3>
                                <p class="parent-child-stars mb-0"><?= ui_icon('star', 'Bintang') ?> <span><?= esc($child['balance']) ?></span></p>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>

<section class="mb-5" aria-labelledby="family-progress-heading">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3"><h2 id="family-progress-heading" class="h4 mb-0">Kemajuan Keluarga</h2><a href="<?= route_to('parent.ranking') ?>" class="btn btn-primary d-inline-flex align-items-center">Lihat kedudukan</a></div>
    <?php if ($todayRanking['rows'] === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Tiada Anak yang layak untuk kedudukan hari ini.</div></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Anak</th><th>Tugasan</th><th>Mata Diperoleh</th></tr></thead><tbody>
            <?php foreach ($todayRanking['rows'] as $row): ?><tr><td class="fw-semibold"><?= esc($row['name']) ?></td><td><?= esc($row['completed_tasks']) ?> / <?= esc($row['scheduled_tasks']) ?></td><td><?= ui_icon('star') ?> <?= esc($row['earned_points']) ?></td></tr><?php endforeach ?>
        </tbody></table></div></div>
    <?php endif ?>
</section>

<section aria-labelledby="activity-heading">
    <h2 id="activity-heading" class="h4 mb-3">Log aktiviti anak</h2>
    <p class="small text-secondary">20 aktiviti terkini keluarga. Waktu Malaysia.</p>
    <div class="card border-0 shadow-sm">
        <?php if ($activities === []): ?>
            <div class="card-body text-secondary">Belum ada aktiviti anak direkodkan.</div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($activities as $activity): ?>
                    <li class="list-group-item p-3 d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div><div class="fw-semibold"><?= esc($activity['child_name']) ?></div><div><?= esc($activity['description']) ?></div><div class="small text-secondary"><?= esc($activity['detail']) ?></div></div>
                        <time class="small text-secondary flex-shrink-0"><?= esc(ui_datetime($activity['at'])) ?></time>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
