<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $family, 'profile' => $profile, 'balance' => $balance, 'headerDate' => $progress['date']]) ?>
<h2 class="h4 mb-3">Kemajuan hari ini</h2>

<section class="card child-compact-card border-0 shadow-sm mb-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-end gap-2 mb-2">
            <div><span class="h4 fw-bold mb-0"><?= esc($progress['completed_count']) ?></span><span class="small text-secondary"> / <?= esc($progress['total_count']) ?> tugasan</span></div>
            <strong class="h5 text-primary mb-0"><?= esc($progress['completion_percentage']) ?>%</strong>
        </div>
        <div class="progress child-summary-progress rounded-pill" role="progressbar" aria-label="Kemajuan hari ini" aria-valuenow="<?= esc($progress['completion_percentage']) ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= esc($progress['completion_percentage']) ?>%"></div>
        </div>
    </div>
</section>

<section class="card child-compact-card border-0 shadow-sm mb-2 bg-primary text-white">
    <div class="card-body">
        <p class="small mb-1 opacity-75">Baki mata</p>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2"><p class="h4 fw-bold mb-0"><?= ui_icon('star') ?> <?= esc($balance) ?></p><p class="small fw-semibold mb-0"><?= ui_icon('fire') ?> <?= esc($currentStreak) ?> hari berturut-turut</p></div>
    </div>
</section>

<div class="row g-2">
    <div class="col-12 col-sm-6">
        <section class="card child-compact-card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="small text-secondary mb-1">Tugasan wajib selesai</p>
                <p class="h5 mb-0"><?= esc($progress['required_completed_count']) ?> / <?= esc($progress['required_total_count']) ?></p>
            </div>
        </section>
    </div>
    <div class="col-12 col-sm-6">
        <section class="card child-compact-card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="small text-secondary mb-1">Mata tugasan hari ini</p>
                <p class="h5 mb-1"><?= ui_icon('star') ?> <?= esc($progress['completed_snapshot_points']) ?></p>
                <p class="small text-secondary mb-0">Baki di atas dikira terus daripada rekod transaksi.</p>
            </div>
        </section>
    </div>
</div>

<section class="mt-4" aria-labelledby="point-history-heading">
    <h2 id="point-history-heading" class="h4 mb-3">Sejarah mata</h2>
    <?php if ($pointHistory === []): ?>
        <div class="card child-compact-card border-0 shadow-sm"><div class="card-body small text-secondary">Belum ada transaksi mata.</div></div>
    <?php else: ?>
        <div class="card child-compact-card border-0 shadow-sm overflow-hidden">
            <div class="list-group list-group-flush">
                <?php foreach ($pointHistory as $transaction): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-2 p-2">
                        <div>
                            <div class="fw-semibold"><?= esc(ui_point_description($transaction)) ?></div>
                            <div class="small text-secondary"><?= esc(ui_date($transaction['transaction_date'])) ?> · <span class="badge <?= ui_transaction_badge($transaction) ?>"><?= esc(ui_label('transaction', $transaction['type'])) ?></span></div>
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
