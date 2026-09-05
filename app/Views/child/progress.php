<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $family, 'profile' => $profile, 'balance' => $balance, 'headerDate' => $progress['date']]) ?>
<h2 class="h4 mb-3">Kemajuan hari ini</h2>

<section class="card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div><span class="display-5 fw-bold"><?= esc($progress['completed_count']) ?></span><span class="text-secondary"> / <?= esc($progress['total_count']) ?> tugasan</span></div>
            <span class="h4 text-primary mb-1"><?= esc($progress['completion_percentage']) ?>%</span>
        </div>
        <div class="progress rounded-pill" role="progressbar" aria-label="Kemajuan hari ini" aria-valuenow="<?= esc($progress['completion_percentage']) ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= esc($progress['completion_percentage']) ?>%"></div>
        </div>
    </div>
</section>

<section class="card border-0 shadow-sm rounded-4 mb-3 bg-primary text-white">
    <div class="card-body p-4">
        <p class="mb-1 opacity-75">Baki mata</p>
        <div class="d-flex justify-content-between align-items-end gap-3"><p class="display-5 fw-bold mb-0"><?= ui_icon('star') ?> <?= esc($balance) ?></p><p class="h4 mb-2"><?= ui_icon('fire') ?> <?= esc($currentStreak) ?> hari berturut-turut</p></div>
    </div>
</section>

<div class="row g-3">
    <div class="col-12 col-sm-6">
        <section class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <p class="text-secondary mb-1">Tugasan wajib selesai</p>
                <p class="h2 mb-0"><?= esc($progress['required_completed_count']) ?> / <?= esc($progress['required_total_count']) ?></p>
            </div>
        </section>
    </div>
    <div class="col-12 col-sm-6">
        <section class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <p class="text-secondary mb-1">Mata tugasan hari ini</p>
                <p class="h2 mb-1"><?= ui_icon('star') ?> <?= esc($progress['completed_snapshot_points']) ?></p>
                <p class="small text-secondary mb-0">Baki di atas dikira terus daripada rekod transaksi.</p>
            </div>
        </section>
    </div>
</div>

<section class="mt-4" aria-labelledby="point-history-heading">
    <h2 id="point-history-heading" class="h4 mb-3">Sejarah mata</h2>
    <?php if ($pointHistory === []): ?>
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body text-secondary">Belum ada transaksi mata.</div></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="list-group list-group-flush">
                <?php foreach ($pointHistory as $transaction): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-3 p-3">
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
