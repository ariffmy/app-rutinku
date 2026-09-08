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
                    <tr>
                        <td><?= esc($redemption['child_name']) ?></td>
                        <td><?= esc($redemption['reward_title']) ?></td>
                        <td><?= esc($redemption['points_used']) ?></td>
                        <td><span class="badge <?= ui_redemption_badge($redemption['status']) ?>"><?= esc(ui_label('redemption', $redemption['status'])) ?></span></td>
                        <td><?= esc(ui_datetime($redemption['requested_at'])) ?></td>
                        <td class="text-end">
                            <?php if ($redemption['status'] === 'pending'): ?>
                                <div class="d-inline-flex gap-2">
                                    <form action="<?= route_to('parent.reward-redemptions.approve', $redemption['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-success btn-sm" type="submit">Luluskan</button></form>
                                    <form action="<?= route_to('parent.reward-redemptions.reject', $redemption['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit">Tolak</button></form>
                                </div>
                            <?php elseif ($redemption['status'] === 'approved'): ?>
                                <form action="<?= route_to('parent.reward-redemptions.complete', $redemption['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Tandakan selesai</button></form>
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
                <div class="col-12 col-md-6 col-xl-4"><article class="card reward-card border-0 shadow-sm h-100<?= $reward['is_active'] ? '' : ' is-inactive' ?>">
                    <?php if ($url = ui_image_url($reward['image'] ?? null, false)): ?><img src="<?= esc($url) ?>" alt="<?= esc($reward['title']) ?>" class="reward-card-image" loading="lazy"><?php else: ?><div class="reward-placeholder" aria-label="Tiada gambar rujukan"><?= ui_reward_category_icon($reward['category'] ?? null) ?></div><?php endif ?>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <h3 class="h5 mb-0"><?= esc($reward['title']) ?></h3>
                            <span class="badge <?= $reward['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $reward['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span>
                        </div>
                        <p><span class="badge text-bg-light border"><?= esc($reward['category'] ?? 'Lain-lain') ?></span></p>
                        <?php if (! empty($reward['description'])): ?><p class="text-secondary reward-description"><?= esc($reward['description']) ?></p><?php endif ?>
                        <p class="h5 text-primary"><?= ui_icon('star') ?> <?= esc($reward['points_required']) ?> Mata</p>
                        <p class="small text-secondary"><strong>Had:</strong> <?= esc(ui_reward_limit($reward['redemption_limit'] ?? null)) ?></p>
                        <div class="d-flex flex-wrap gap-2 mt-auto">
                            <a href="<?= route_to('parent.rewards.edit', $reward['id']) ?>" class="btn btn-outline-primary">Sunting</a>
                            <?php if ($reward['is_active']): ?><form action="<?= route_to('parent.rewards.archive', $reward['id']) ?>" method="post" data-confirm-message="Nyahaktifkan ganjaran ini?"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Nyahaktif</button></form><?php endif ?>
                        </div>
                    </div>
                </article></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
