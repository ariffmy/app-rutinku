<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $family, 'profile' => $profile, 'balance' => $balance, 'headerDate' => date('Y-m-d')]) ?>
<header class="mb-3">
    <h2 class="h4 mb-1">Pencapaian</h2>
    <p class="text-secondary mb-0">Milestone daripada rutin dan kemajuan sebenar anda.</p>
</header>

<div class="row g-3">
    <?php foreach ($achievements as $achievement): ?>
        <div class="col-12 col-sm-6">
            <article class="card border-0 shadow-sm rounded-4 h-100 <?= $achievement['is_unlocked'] ? '' : 'opacity-75' ?>">
                <div class="card-body p-4 d-flex gap-3">
                    <div class="h3 text-primary mb-0" aria-hidden="true"><?= ui_icon($achievement['icon']) ?></div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="h5 mb-0"><?= esc($achievement['name']) ?></h3>
                            <span class="badge <?= $achievement['is_unlocked'] ? 'text-bg-success' : 'text-bg-light border' ?>">
                                <?= $achievement['is_unlocked'] ? 'Dibuka' : 'Dikunci' ?>
                            </span>
                        </div>
                        <p class="text-secondary mb-1"><?= esc($achievement['description']) ?></p>
                        <?php if ($achievement['is_unlocked']): ?>
                            <small class="text-secondary">Dicapai <?= esc(ui_date(substr((string) $achievement['earned_at'], 0, 10))) ?></small>
                        <?php endif ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
