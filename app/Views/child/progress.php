<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<header class="pt-2 mb-4">
    <p class="text-uppercase small text-secondary fw-semibold mb-1"><?= esc($family['name']) ?></p>
    <h1 class="display-6 fw-bold mb-1">Progress hari ini</h1>
    <p class="text-secondary mb-0"><?= esc($child->name) ?> · <?= esc($progress['date']) ?></p>
</header>

<section class="card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div><span class="display-5 fw-bold"><?= esc($progress['completed_count']) ?></span><span class="text-secondary"> / <?= esc($progress['total_count']) ?> task</span></div>
            <span class="h4 text-primary mb-1"><?= esc($progress['completion_percentage']) ?>%</span>
        </div>
        <div class="progress rounded-pill" role="progressbar" aria-label="Progress hari ini" aria-valuenow="<?= esc($progress['completion_percentage']) ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= esc($progress['completion_percentage']) ?>%"></div>
        </div>
    </div>
</section>

<section class="card border-0 shadow-sm rounded-4 mb-3 bg-primary text-white">
    <div class="card-body p-4">
        <p class="mb-1 opacity-75">Baki points</p>
        <div class="d-flex justify-content-between align-items-end gap-3"><p class="display-5 fw-bold mb-0">⭐ <?= esc($balance) ?></p><p class="h4 mb-2">🔥 <?= esc($currentStreak) ?> day streak</p></div>
    </div>
</section>

<div class="row g-3">
    <div class="col-12 col-sm-6">
        <section class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <p class="text-secondary mb-1">Task wajib selesai</p>
                <p class="h2 mb-0"><?= esc($progress['required_completed_count']) ?> / <?= esc($progress['required_total_count']) ?></p>
            </div>
        </section>
    </div>
    <div class="col-12 col-sm-6">
        <section class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <p class="text-secondary mb-1">Points task hari ini</p>
                <p class="h2 mb-1">⭐ <?= esc($progress['completed_snapshot_points']) ?></p>
                <p class="small text-secondary mb-0">Baki di atas dikira terus daripada ledger.</p>
            </div>
        </section>
    </div>
</div>

<section class="mt-4" aria-labelledby="point-history-heading">
    <h2 id="point-history-heading" class="h4 mb-3">Sejarah points</h2>
    <?php if ($pointHistory === []): ?>
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body text-secondary">Belum ada transaksi points.</div></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="list-group list-group-flush">
                <?php foreach ($pointHistory as $transaction): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-3 p-3">
                        <div>
                            <div class="fw-semibold"><?= esc($transaction['description'] ?: ucfirst($transaction['type'])) ?></div>
                            <div class="small text-secondary"><?= esc($transaction['transaction_date']) ?> · <?= esc(ucfirst($transaction['type'])) ?></div>
                        </div>
                        <span class="fw-bold <?= (int) $transaction['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= (int) $transaction['points'] > 0 ? '+' : '' ?><?= esc($transaction['points']) ?>
                        </span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
