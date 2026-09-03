<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php
$required = old('is_required') !== null ? (bool) old('is_required') : (bool) ($task['is_required'] ?? true);
$active = old('is_active') !== null ? (bool) old('is_active') : (bool) ($task['is_active'] ?? true);
?>
<div class="mb-4">
    <a href="<?= route_to('parent.routines.edit', $routine['id']) ?>" class="small text-decoration-none">← <?= esc($routine['name']) ?></a>
    <h1 class="h2 mb-1"><?= $task ? 'Sunting tugasan' : 'Tugasan baharu' ?></h1>
    <p class="text-secondary mb-0">Tugasan untuk <?= esc($routine['name']) ?></p>
</div>

<?php if (session('errors')): ?>
    <div class="alert alert-danger" role="alert"><?php foreach (session('errors') as $error): ?><div><?= esc($error) ?></div><?php endforeach ?></div>
<?php endif ?>

<form action="<?= esc($action) ?>" method="post" class="card border-0 shadow-sm">
    <?= csrf_field() ?>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-12">
                <label for="title" class="form-label">Tajuk tugasan</label>
                <input id="title" name="title" class="form-control" maxlength="160" required value="<?= esc(old('title') ?? ($task['title'] ?? '')) ?>" placeholder="Contoh: Gosok Gigi">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Penerangan</label>
                <textarea id="description" name="description" class="form-control" rows="3" maxlength="5000"><?= esc(old('description') ?? ($task['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-6 col-md-4">
                <label for="task_time" class="form-label">Masa</label>
                <input id="task_time" name="task_time" type="time" class="form-control" value="<?= esc(old('task_time') ?? (! empty($task['task_time']) ? substr($task['task_time'], 0, 5) : '')) ?>">
            </div>
            <div class="col-6 col-md-4">
                <label for="points" class="form-label">Mata</label>
                <input id="points" name="points" type="number" min="0" max="10000" class="form-control" required value="<?= esc(old('points') ?? ($task['points'] ?? 0)) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="sort_order" class="form-label">Susunan</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="form-control" required value="<?= esc(old('sort_order') ?? ($task['sort_order'] ?? 0)) ?>">
            </div>
            <div class="col-12 col-sm-6">
                <input type="hidden" name="is_required" value="0">
                <div class="form-check form-switch">
                    <input id="is_required" name="is_required" value="1" type="checkbox" class="form-check-input" <?= $required ? 'checked' : '' ?>>
                    <label for="is_required" class="form-check-label">Tugasan wajib</label>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" <?= $active ? 'checked' : '' ?>>
                    <label for="is_active" class="form-check-label">Tugasan aktif</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white border-0 px-4 pb-4">
        <button type="submit" class="btn btn-primary">Simpan tugasan</button>
    </div>
</form>
<?= $this->endSection() ?>
