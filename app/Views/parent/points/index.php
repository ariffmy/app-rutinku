<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="mb-4">
    <h1 class="h2 mb-1">Mata</h1>
    <p class="text-secondary mb-0">Lihat rekod transaksi dan rekodkan pelarasan untuk Anak dalam keluarga anda.</p>
</header>

<?php if (session('errors')): ?>
    <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<?php if ($children === []): ?>
    <div class="alert alert-info">Tiada Anak aktif dalam keluarga ini.</div>
<?php else: ?>
    <form method="get" action="<?= route_to('parent.points') ?>" class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row align-items-md-end gap-3">
            <div class="flex-grow-1">
                <label for="child-filter" class="form-label">Anak</label>
                <select id="child-filter" name="child" class="form-select">
                    <?php foreach ($children as $child): ?>
                        <option value="<?= esc($child['id']) ?>" <?= (int) $child['id'] === (int) $selectedChildId ? 'selected' : '' ?>><?= esc($child['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <button class="btn btn-outline-primary" type="submit">Lihat akaun</button>
        </div>
    </form>

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <section class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <p class="text-secondary mb-1">Baki semasa</p>
                    <p class="display-5 fw-bold text-primary mb-0">⭐ <?= esc($account['balance']) ?></p>
                </div>
            </section>

            <form action="<?= route_to('parent.points.adjust') ?>" method="post" class="card border-0 shadow-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="child_user_id" value="<?= esc($selectedChildId) ?>">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Pelarasan manual</h2>
                    <div class="mb-3">
                        <label for="points" class="form-label">Mata</label>
                        <input id="points" name="points" type="number" min="-1000000" max="1000000" step="1" class="form-control" required value="<?= esc(old('points') ?? '') ?>" placeholder="Contoh: 10 atau -5">
                        <div class="form-text">Nombor positif menambah; nombor negatif menolak.</div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Sebab</label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" maxlength="500" required><?= esc(old('reason') ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Rekod pelarasan</button>
                </div>
            </form>
        </div>

        <div class="col-12 col-lg-8">
            <section aria-labelledby="ledger-heading">
                <h2 id="ledger-heading" class="h4 mb-3">Rekod transaksi terkini</h2>
                <?php if ($account['history'] === []): ?>
                    <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada transaksi mata.</div></div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm overflow-hidden">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Tarikh</th><th>Jenis</th><th>Penerangan</th><th class="text-end">Mata</th></tr></thead>
                                <tbody>
                                <?php foreach ($account['history'] as $transaction): ?>
                                    <tr>
                                        <td><?= esc($transaction['transaction_date']) ?></td>
                                        <td><span class="badge text-bg-light border"><?= esc(ui_label('transaction', $transaction['type'])) ?></span></td>
                                        <td><?= esc(ui_point_description($transaction)) ?></td>
                                        <td class="text-end fw-semibold <?= (int) $transaction['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= (int) $transaction['points'] > 0 ? '+' : '' ?><?= esc($transaction['points']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif ?>
            </section>
        </div>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
