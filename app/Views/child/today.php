<?= $this->extend('layouts/child') ?>
<?= $this->section('content') ?>
<?= view('child/partials/header', ['child' => $child, 'family' => $family, 'profile' => $profile, 'balance' => $balance, 'headerDate' => $schedule['date']]) ?>
<div data-task-notice role="status" aria-live="polite"></div>
<div data-child-tasks data-csrf-name="<?= esc(csrf_token()) ?>" data-csrf-hash="<?= esc(csrf_hash()) ?>">
    <section class="mb-4" aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="h4" hidden>Belum selesai</h2>
        <div data-empty-pending class="card border-0 shadow-sm" hidden><div class="card-body p-4 text-secondary">Tiada tugasan dalam waktu ini.</div></div>
        <div class="row g-3" data-pending></div>
    </section>
    <section class="mb-4" aria-labelledby="completed-heading" data-completed-section hidden>
        <h2 id="completed-heading" class="h4">Sudah selesai</h2>
        <div class="row g-3" data-completed></div>
    </section>
    <div data-task-source>
        <?php foreach ($schedule['routines'] as $routine): ?>
            <?php foreach ($routine['tasks'] as $task): ?>
                <article class="col-12 col-md-6" data-task data-time="<?= esc($task['task_time'] ?? '') ?>" data-duration="<?= esc($task['duration_minutes'] ?? 15) ?>" data-completed="<?= $task['is_completed'] ? '1' : '0' ?>" data-id="<?= esc($task['id']) ?>">
                    <div class="card child-task-card border-0 shadow-sm h-100"><div class="card-body">
                        <div class="child-task-details">
                            <h3 class="h6 mb-1" data-task-title><?= esc($task['title']) ?></h3>
                            <span class="task-stars" role="img" aria-label="<?= esc($task['points']) ?> bintang"><?= ui_icon('star') ?><span class="task-stars-count" aria-hidden="true"><?= esc($task['points']) ?></span></span>
                        </div>
                            <form method="post" action="<?= route_to($task['is_completed'] ? 'child.tasks.undo' : 'child.tasks.complete', $task['id']) ?>" data-task-form data-complete-url="<?= route_to('child.tasks.complete', $task['id']) ?>" data-undo-url="<?= route_to('child.tasks.undo', $task['id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary" data-task-button><?= $task['is_completed'] ? 'Batal selesai' : 'Sudah' ?></button>
                            </form>
                    </div></div>
                </article>
            <?php endforeach ?>
        <?php endforeach ?>
    </div>
</div>
<noscript><p class="alert alert-warning">Aktifkan JavaScript untuk penapisan waktu telefon, pengesahan dan kemas kini segera.</p></noscript>
<dialog id="task-confirm" aria-labelledby="confirm-title">
    <h2 id="confirm-title" class="h4">Pasti ke sudah?</h2>
    <p data-confirm-task></p>
    <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-secondary" data-confirm-cancel>Belum lagi</button><button type="button" class="btn btn-primary" data-confirm-yes>Ya, sudah!</button></div>
</dialog>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script defer src="<?= ui_asset_url('assets/js/child-today.js') ?>"></script>
<?= $this->endSection() ?>
