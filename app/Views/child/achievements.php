<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $family, 'profile' => $profile, 'balance' => $balance, 'headerDate' => date('Y-m-d')]) ?>
<header class="mb-3">
    <h2 class="h4 mb-1">Pencapaian</h2>
    <p class="text-secondary mb-0">Milestone daripada rutin dan kemajuan sebenar anda.</p>
</header>

<div class="row g-2">
    <?php foreach ($achievements as $achievement): ?>
        <div class="col-12 col-sm-6">
            <article class="card child-compact-card achievement-card border-0 shadow-sm h-100 <?= $achievement['is_unlocked'] ? '' : 'opacity-75' ?>">
                <div class="card-body d-flex gap-2">
                    <div class="achievement-icon text-primary" aria-hidden="true"><?= ui_icon($achievement['icon']) ?></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                            <h3 class="h6 mb-0"><?= esc($achievement['name']) ?></h3>
                            <span class="badge flex-shrink-0 <?= $achievement['is_unlocked'] ? 'text-bg-success' : 'text-bg-light border' ?>">
                                <?= $achievement['is_unlocked'] ? 'Dibuka' : 'Dikunci' ?>
                            </span>
                        </div>
                        <p class="small text-secondary mb-0"><?= esc($achievement['description']) ?></p>
                        <?php if ($achievement['is_unlocked']): ?>
                            <small class="text-secondary d-block mt-1">Dicapai <?= esc(ui_date(substr((string) $achievement['earned_at'], 0, 10))) ?></small>
                        <?php endif ?>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
