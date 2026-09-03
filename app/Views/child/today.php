<?= $this->extend('layouts/child') ?>

<?= $this->section('content') ?>
<header class="pt-2 mb-4">
    <p class="text-uppercase small text-secondary fw-semibold mb-1"><?= esc($family['name']) ?></p>
    <h1 class="display-6 fw-bold mb-1">Hai, <?= esc($child->name) ?></h1>
    <p class="text-secondary mb-1"><?= esc($schedule['completed_count']) ?> daripada <?= esc($schedule['total_count']) ?> tugasan selesai</p>
    <p class="fw-semibold text-primary mb-3">⭐ <?= esc($balance) ?> mata · 🔥 <?= esc($currentStreak) ?> hari berturut-turut</p>
    <div class="progress rounded-pill" role="progressbar" aria-label="Kemajuan hari ini" aria-valuenow="<?= esc($schedule['completion_percentage']) ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: <?= esc($schedule['completion_percentage']) ?>%"><?= esc($schedule['completion_percentage']) ?>%</div>
    </div>
</header>

<?php if ($schedule['routines'] === [] || $schedule['task_count'] === 0): ?>
    <section class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 text-center">
            <div class="display-5 mb-3" aria-hidden="true">☀️</div>
            <h2 class="h4">Tiada tugasan hari ini</h2>
            <p class="text-secondary mb-0">Nikmati hari anda!</p>
        </div>
    </section>
<?php else: ?>
    <div class="vstack gap-4">
        <?php foreach ($schedule['routines'] as $routine): ?>
            <?php if ($routine['tasks'] === []) { continue; } ?>
            <section aria-labelledby="routine-<?= esc($routine['id']) ?>">
                <div class="d-flex justify-content-between align-items-baseline mb-2 px-1">
                    <h2 id="routine-<?= esc($routine['id']) ?>" class="h4 mb-0"><?= esc($routine['name']) ?></h2>
                    <?php if ($routine['start_time']): ?><span class="small text-secondary"><?= esc(ui_time($routine['start_time'])) ?></span><?php endif ?>
                </div>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="list-group list-group-flush">
                        <?php foreach ($routine['tasks'] as $task): ?>
                            <div class="list-group-item child-task-row <?= $task['is_completed'] ? 'task-completed' : '' ?> d-flex align-items-center gap-3 p-3">
                                <form action="<?= route_to($task['is_completed'] ? 'child.tasks.undo' : 'child.tasks.complete', $task['id']) ?>" method="post" class="flex-shrink-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="task-toggle <?= $task['is_completed'] ? 'is-complete' : '' ?>" aria-label="<?= $task['is_completed'] ? 'Batalkan penyelesaian ' : 'Tandakan selesai ' ?><?= esc($task['title']) ?>">
                                        <?= $task['is_completed'] ? '✓' : '' ?>
                                    </button>
                                </form>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= esc($task['title']) ?></div>
                                    <div class="small text-secondary">
                                        <?= esc(ui_task_time($task)) ?>
                                        <?php if (! $task['is_required']): ?> · Pilihan<?php endif ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge rounded-pill text-bg-warning">⭐ <?= esc($task['points']) ?></span>
                                    <?php if ($task['is_completed']): ?><div class="small text-success mt-1">Selesai</div><?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </section>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
