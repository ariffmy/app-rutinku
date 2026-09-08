<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $catalogue['family'], 'profile' => $profile, 'balance' => $catalogue['balance'], 'headerDate' => date('Y-m-d')]) ?>
<h2 class="h4 mb-3">Ganjaran</h2>

<?php if ($catalogue['rewards'] === []): ?>
    <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 text-center"><div class="display-5 mb-2"><?= ui_icon('gift') ?></div><h2 class="h4">Belum ada ganjaran</h2><p class="text-secondary mb-0">Ibu bapa akan menambah ganjaran di sini.</p></div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($catalogue['rewards'] as $reward): ?>
            <div class="col-12 col-sm-6"><article class="card reward-card border-0 shadow-sm rounded-4 h-100">
                <?php if ($url = ui_image_url($reward['image'] ?? null, true)): ?><img src="<?= esc($url) ?>" alt="<?= esc($reward['title']) ?>" class="reward-card-image" loading="lazy"><?php else: ?><div class="reward-placeholder" aria-label="Tiada gambar rujukan"><?= ui_reward_category_icon($reward['category'] ?? null) ?></div><?php endif ?>
                <div class="card-body d-flex flex-column">
                <h2 class="h4"><?= esc($reward['title']) ?></h2>
                <p><span class="badge text-bg-light border"><?= esc($reward['category'] ?? 'Lain-lain') ?></span></p>
                <?php if (! empty($reward['description'])): ?><p class="text-secondary reward-description"><?= esc($reward['description']) ?></p><?php endif ?>
                <p class="h5 text-primary"><?= ui_icon('star') ?> <?= esc($reward['points_required']) ?> Mata</p>
                <p class="small text-secondary"><strong>Had:</strong> <?= esc(ui_reward_limit($reward['redemption_limit'] ?? null)) ?></p>
                <div class="mt-auto">
                <?php if ($reward['has_pending_request']): ?>
                    <button class="btn btn-warning w-100" disabled>Menunggu Ibu bapa</button>
                <?php elseif ($reward['limit_message']): ?>
                    <button class="btn btn-outline-secondary w-100" disabled><?= esc($reward['limit_message']) ?></button>
                <?php elseif (! $reward['can_afford']): ?>
                    <button class="btn btn-outline-secondary w-100" disabled>Mata belum cukup</button>
                <?php else: ?>
                    <form action="<?= route_to('child.rewards.redeem', $reward['id']) ?>" method="post"><?= csrf_field() ?><button class="btn btn-primary w-100" type="submit">Tebus ganjaran</button></form>
                <?php endif ?>
                </div>
            </div></article></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if ($catalogue['redemptions'] !== []): ?>
    <section class="mt-4" aria-labelledby="request-history-heading"><h2 id="request-history-heading" class="h4 mb-3">Permohonan saya</h2><div class="card border-0 shadow-sm rounded-4 overflow-hidden"><div class="list-group list-group-flush">
        <?php foreach ($catalogue['redemptions'] as $redemption): ?>
            <div class="list-group-item d-flex justify-content-between gap-3 p-3"><div><div class="fw-semibold"><?= esc($redemption['reward_title']) ?></div><div class="small text-secondary"><?= esc(ui_datetime($redemption['requested_at'])) ?></div><?php if ($redemption['status'] === 'pending'): ?><form action="<?= route_to('child.reward-redemptions.cancel', $redemption['id']) ?>" method="post" class="mt-2" data-confirm-message="Batalkan permohonan ganjaran ini?"><?= csrf_field() ?><button class="btn btn-outline-danger btn-sm" type="submit">Batalkan</button></form><?php endif ?></div><span class="badge align-self-start <?= ui_redemption_badge($redemption['status']) ?>"><?= esc(ui_label('redemption', $redemption['status'])) ?></span></div>
        <?php endforeach ?>
    </div></div></section>
<?php endif ?>
<?= $this->endSection() ?>
