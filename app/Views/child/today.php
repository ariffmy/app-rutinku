<?= $this->extend('layouts/child') ?>
<?= $this->section('content') ?>
<header class="pt-2 mb-4 d-flex align-items-center gap-3">
    <?= ui_avatar($profile['avatar'] ?? null) ?>
    <div>
        <p class="text-uppercase small text-secondary fw-semibold mb-1"><?= esc($family['name']) ?></p>
        <h1 class="h2 fw-bold mb-1">Hai, <?= esc($child->name) ?></h1>
        <p class="fw-semibold text-primary mb-1"><?= ui_icon('star') ?> <span data-balance><?= esc($balance) ?></span> mata</p>
        <p class="small text-secondary mb-0" data-today-date><?= esc(ui_date($schedule['date'])) ?></p>
    </div>
</header>
<p class="small text-secondary mb-1">Tugasan dalam satu jam sebelum atau selepas jam peranti ini (telefon atau PC), berdasarkan masa mula. Tugasan tanpa masa sentiasa dipaparkan.</p>
<p class="small text-secondary" data-filter-clock>Memuatkan waktu peranti…</p>
<div data-task-notice role="status" aria-live="polite"></div>
<div data-child-tasks data-csrf-name="<?= esc(csrf_token()) ?>" data-csrf-hash="<?= esc(csrf_hash()) ?>">
    <section class="mb-4" aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="h4">Belum selesai</h2>
        <p data-empty-pending class="text-secondary" hidden>Tiada tugasan dalam waktu ini.</p>
        <div class="row g-3" data-pending></div>
    </section>
    <section class="mb-4" aria-labelledby="completed-heading">
        <h2 id="completed-heading" class="h4">Sudah selesai</h2>
        <p data-empty-completed class="text-secondary" hidden>Belum ada tugasan selesai dalam waktu ini.</p>
        <div class="row g-3" data-completed></div>
    </section>
    <div data-task-source>
        <?php foreach ($schedule['routines'] as $routine): ?>
            <?php foreach ($routine['tasks'] as $task): ?>
                <article class="col-12 col-md-6" data-task data-time="<?= esc($task['task_time'] ?? '') ?>" data-completed="<?= $task['is_completed'] ? '1' : '0' ?>" data-id="<?= esc($task['id']) ?>">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <p class="small text-secondary mb-1"><?= esc($routine['name']) ?></p>
                        <h3 class="h5" data-task-title><?= esc($task['title']) ?></h3>
                        <p class="small text-secondary"><?= esc(ui_task_time($task)) ?></p>
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span><?= ui_icon('star') ?> <?= esc($task['points']) ?> mata</span>
                            <form method="post" action="<?= route_to($task['is_completed'] ? 'child.tasks.undo' : 'child.tasks.complete', $task['id']) ?>" data-task-form data-complete-url="<?= route_to('child.tasks.complete', $task['id']) ?>" data-undo-url="<?= route_to('child.tasks.undo', $task['id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary" data-task-button><?= $task['is_completed'] ? 'Batal selesai' : 'Sudah' ?></button>
                            </form>
                        </div>
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
