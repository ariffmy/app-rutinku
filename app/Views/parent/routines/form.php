<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php
$dayNames = [1 => 'Isnin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Khamis', 5 => 'Jumaat', 6 => 'Sabtu', 7 => 'Ahad'];
$selectedDays = (array) (old('days') ?? ($routine['days'] ?? []));
$selectedChild = old('child_user_id') ?? ($routine['child_user_id'] ?? '');
$selectedChild = is_scalar($selectedChild) ? (string) $selectedChild : '';
$active = old('is_active') !== null ? (bool) old('is_active') : (bool) ($routine['is_active'] ?? true);
?>
<div class="d-flex align-items-center justify-content-between gap-3 mb-4">
    <div>
        <a href="<?= route_to('parent.routines') ?>" class="small text-decoration-none">← Rutin</a>
        <h1 class="h2 mb-1"><?= $routine ? 'Sunting rutin' : 'Rutin baharu' ?></h1>
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
                <label for="child_user_id" class="form-label">Anak</label>
                <select id="child_user_id" name="child_user_id" class="form-select" required<?= ! $routine ? ' aria-describedby="child-selection-help"' : '' ?>>
                    <option value="">Pilih Anak</option>
                    <?php if (! $routine && $children !== []): ?>
                        <option value="all" <?= $selectedChild === 'all' ? 'selected' : '' ?>>Semua anak</option>
                    <?php endif ?>
                    <?php foreach ($children as $child): ?>
                        <option value="<?= esc($child['id']) ?>" <?= $selectedChild === (string) $child['id'] ? 'selected' : '' ?>><?= esc($child['name']) ?></option>
                    <?php endforeach ?>
                </select>
                <?php if (! $routine): ?>
                    <div id="child-selection-help" class="form-text"><?= $children === []
                        ? 'Tiada anak aktif. Tambah atau aktifkan anak dahulu.'
                        : 'Semua anak: cipta salinan rutin untuk setiap anak aktif dalam keluarga. Tugasan dan perubahan selepas ini diurus berasingan bagi setiap anak.' ?></div>
                <?php endif ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="name" class="form-label">Nama rutin</label>
                <input id="name" name="name" class="form-control" maxlength="120" required value="<?= esc(old('name') ?? ($routine['name'] ?? '')) ?>" placeholder="Contoh: Rutin Pagi">
            </div>
            <div class="col-12">
                <fieldset>
                    <legend class="form-label">Hari rutin</legend>
                    <p class="small text-secondary">Jadual utama untuk semua tugasan dalam rutin ini. Tugasan tidak akan muncul di luar hari yang dipilih.</p>
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
                    <label for="is_active" class="form-check-label">Rutin aktif</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white border-0 px-4 pb-4">
        <button type="submit" class="btn btn-primary">Simpan rutin</button>
    </div>
</form>

<?php if ($routine): ?>
    <section aria-labelledby="tasks-heading">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h2 id="tasks-heading" class="h4 mb-1">Tugasan</h2><p class="small text-secondary mb-0">Semua tugasan mengikut hari rutin. Jadual khas hanya mengehadkan hari tersebut.</p></div>
            <a href="<?= route_to('parent.routine-tasks.new', $routine['id']) ?>" class="btn btn-primary">Tambah tugasan</a>
        </div>
        <?php if ($routine['tasks'] === []): ?>
            <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Belum ada tugasan dalam rutin ini.</div></div>
        <?php else: ?>
            <div class="vstack gap-2">
                <?php foreach ($routine['tasks'] as $task): ?>
                    <article class="card border-0 shadow-sm">
                        <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-3">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h5 mb-0"><?= esc($task['title']) ?></h3>
                                    <span class="badge <?= $task['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $task['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span>
                                    <?php if (! $task['is_required']): ?><span class="badge text-bg-light border">Pilihan</span><?php endif ?>
                                </div>
                                <div class="small text-secondary"><?= esc(ui_task_time($task)) ?> · <?= esc($task['points']) ?> mata</div>
                                <div class="small text-secondary"><?= esc(ui_task_schedule($task)) ?></div>
                            </div>
                            <div class="d-flex flex-wrap align-items-start gap-2 task-row-actions">
                                <a href="<?= route_to('parent.routine-tasks.edit', $task['id']) ?>" class="btn btn-outline-primary">Sunting</a>
                                <?php if ($task['is_active']): ?>
                                <form action="<?= route_to('parent.routine-tasks.delete', $task['id']) ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger">Nyahaktif</button>
                                </form>
                                <?php else: ?>
                                    <span class="small text-secondary align-self-center">Sunting untuk aktifkan semula</span>
                                <?php endif ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
