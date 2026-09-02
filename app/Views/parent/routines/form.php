<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php
$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$selectedDays = (array) (old('days') ?? ($routine['days'] ?? []));
$selectedChild = (int) (old('child_user_id') ?? ($routine['child_user_id'] ?? 0));
$active = old('is_active') !== null ? (bool) old('is_active') : (bool) ($routine['is_active'] ?? true);
?>
<div class="d-flex align-items-center justify-content-between gap-3 mb-4">
    <div>
        <a href="<?= route_to('parent.routines') ?>" class="small text-decoration-none">← Routines</a>
        <h1 class="h2 mb-1"><?= $routine ? 'Edit routine' : 'Routine baharu' ?></h1>
    </div>
    <?php if ($routine): ?>
        <form action="<?= route_to('parent.routines.delete', $routine['id']) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger">Padam / nyahaktif</button>
        </form>
    <?php endif ?>
</div>

<?php if (session('errors')): ?>
    <div class="alert alert-danger" role="alert"><?php foreach (session('errors') as $error): ?><div><?= esc($error) ?></div><?php endforeach ?></div>
<?php endif ?>

<form action="<?= esc($action) ?>" method="post" class="card border-0 shadow-sm mb-4">
    <?= csrf_field() ?>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="child_user_id" class="form-label">Child</label>
                <select id="child_user_id" name="child_user_id" class="form-select" required>
                    <option value="">Pilih Child</option>
                    <?php foreach ($children as $child): ?>
                        <option value="<?= esc($child['id']) ?>" <?= $selectedChild === (int) $child['id'] ? 'selected' : '' ?>><?= esc($child['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label for="name" class="form-label">Nama routine</label>
                <input id="name" name="name" class="form-control" maxlength="120" required value="<?= esc(old('name') ?? ($routine['name'] ?? '')) ?>" placeholder="Contoh: Morning Routine">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Penerangan</label>
                <textarea id="description" name="description" class="form-control" rows="3" maxlength="5000"><?= esc(old('description') ?? ($routine['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 col-md-4">
                <label for="type" class="form-label">Jenis</label>
                <input id="type" name="type" class="form-control" maxlength="50" value="<?= esc(old('type') ?? ($routine['type'] ?? '')) ?>" placeholder="Morning, School, Evening">
            </div>
            <div class="col-6 col-md-4">
                <label for="start_time" class="form-label">Masa mula</label>
                <input id="start_time" name="start_time" type="time" class="form-control" value="<?= esc(old('start_time') ?? (! empty($routine['start_time']) ? substr($routine['start_time'], 0, 5) : '')) ?>">
            </div>
            <div class="col-6 col-md-4">
                <label for="sort_order" class="form-label">Susunan</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="form-control" required value="<?= esc(old('sort_order') ?? ($routine['sort_order'] ?? 0)) ?>">
            </div>
            <div class="col-12">
                <fieldset>
                    <legend class="form-label">Hari</legend>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($dayNames as $day => $label): ?>
                            <input class="btn-check" type="checkbox" name="days[]" value="<?= $day ?>" id="day-<?= $day ?>" <?= in_array($day, array_map('intval', $selectedDays), true) ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="day-<?= $day ?>"><?= esc($label) ?></label>
                        <?php endforeach ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" <?= $active ? 'checked' : '' ?>>
                    <label for="is_active" class="form-check-label">Routine aktif</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white border-0 px-4 pb-4">
        <button type="submit" class="btn btn-primary">Simpan routine</button>
    </div>
</form>

<?php if ($routine): ?>
    <section aria-labelledby="tasks-heading">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h2 id="tasks-heading" class="h4 mb-1">Tasks</h2><p class="small text-secondary mb-0">Task aktif akan muncul pada hari routine dijadualkan.</p></div>
            <a href="<?= route_to('parent.routine-tasks.new', $routine['id']) ?>" class="btn btn-primary">Tambah task</a>
        </div>
        <?php if ($routine['tasks'] === []): ?>
            <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada task dalam routine ini.</div></div>
        <?php else: ?>
            <div class="vstack gap-2">
                <?php foreach ($routine['tasks'] as $task): ?>
                    <article class="card border-0 shadow-sm">
                        <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-3">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h5 mb-0"><?= esc($task['title']) ?></h3>
                                    <span class="badge <?= $task['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $task['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span>
                                    <?php if (! $task['is_required']): ?><span class="badge text-bg-light border">Optional</span><?php endif ?>
                                </div>
                                <div class="small text-secondary"><?= $task['task_time'] ? esc(substr($task['task_time'], 0, 5)) : 'Tiada masa' ?> · <?= esc($task['points']) ?> points</div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= route_to('parent.routine-tasks.edit', $task['id']) ?>" class="btn btn-outline-primary">Edit</a>
                                <form action="<?= route_to('parent.routine-tasks.delete', $task['id']) ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger">Padam / nyahaktif</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
