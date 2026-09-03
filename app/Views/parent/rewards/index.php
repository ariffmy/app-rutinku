<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
    <div><h1 class="h2 mb-1">Ganjaran</h1><p class="text-secondary mb-0">Urus katalog keluarga dan permohonan daripada Anak.</p></div>
    <a href="<?= route_to('parent.rewards.new') ?>" class="btn btn-primary">Tambah ganjaran</a>
</header>

<section class="mb-5" aria-labelledby="redemptions-heading">
    <h2 id="redemptions-heading" class="h4 mb-3">Permohonan ganjaran</h2>
    <?php if ($redemptions === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada permohonan ganjaran.</div></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Anak</th><th>Ganjaran</th><th>Mata</th><th>Status</th><th>Diminta</th><th class="text-end">Tindakan</th></tr></thead>
                <tbody>
                <?php foreach ($redemptions as $redemption): ?>
                    <?php $badge = $redemption['status'] === 'approved' ? 'success' : ($redemption['status'] === 'rejected' ? 'danger' : 'warning'); ?>
                    <tr>
                        <td><?= esc($redemption['child_name']) ?></td>
                        <td><?= esc($redemption['reward_title']) ?></td>
                        <td><?= esc($redemption['points_used']) ?></td>
                        <td><span class="badge text-bg-<?= $badge ?>"><?= esc(ui_label('redemption', $redemption['status'])) ?></span></td>
                        <td><?= esc(ui_datetime($redemption['requested_at'])) ?></td>
                        <td class="text-end">
                            <?php if ($redemption['status'] === 'pending'): ?>
                                <div class="d-inline-flex gap-2">
                                    <form action="<?= route_to('parent.reward-redemptions.approve', $redemption['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-success btn-sm" type="submit">Luluskan</button></form>
                                    <form action="<?= route_to('parent.reward-redemptions.reject', $redemption['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit">Tolak</button></form>
                                </div>
                            <?php else: ?>—<?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div></div>
    <?php endif ?>
</section>

<section aria-labelledby="catalogue-heading">
    <h2 id="catalogue-heading" class="h4 mb-3">Katalog <?= esc($family['name']) ?></h2>
    <?php if ($rewards === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada ganjaran.</div></div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($rewards as $reward): ?>
                <div class="col-12 col-md-6 col-xl-4"><article class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <h3 class="h5 mb-0"><?= esc($reward['title']) ?></h3>
                            <span class="badge <?= $reward['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $reward['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span>
                        </div>
                        <?php if ($reward['description']): ?><p class="text-secondary"><?= esc($reward['description']) ?></p><?php endif ?>
                        <p class="h5 text-primary">⭐ <?= esc($reward['points_required']) ?></p>
                        <div class="d-flex gap-2">
                            <a href="<?= route_to('parent.rewards.edit', $reward['id']) ?>" class="btn btn-outline-primary">Sunting</a>
                            <?php if ($reward['is_active']): ?><form action="<?= route_to('parent.rewards.archive', $reward['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Nyahaktif</button></form><?php endif ?>
                        </div>
                    </div>
                </article></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
