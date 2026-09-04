<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<?php $dayNames = [1 => 'Isn', 2 => 'Sel', 3 => 'Rab', 4 => 'Kha', 5 => 'Jum', 6 => 'Sab', 7 => 'Ahd']; ?>
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Rutin</h1>
        <p class="text-secondary mb-0">Jadual mingguan berulang untuk setiap Anak.</p>
    </div>
    <a href="<?= route_to('parent.routines.new') ?>" class="btn btn-primary">Tambah rutin</a>
</div>

<form method="get" action="<?= route_to('parent.routines') ?>" class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
        <div class="flex-grow-1">
            <label for="child" class="form-label">Tapis mengikut Anak</label>
            <select id="child" name="child" class="form-select">
                <option value="">Semua Anak</option>
                <?php foreach ($children as $child): ?>
                    <option value="<?= esc($child['id']) ?>" <?= $selectedChildId === (int) $child['id'] ? 'selected' : '' ?>><?= esc($child['name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline-primary">Tapis</button>
    </div>
</form>

<?php if ($routines === []): ?>
    <div class="card border-0 shadow-sm"><div class="card-body p-4 text-center text-secondary">Belum ada rutin. Cipta rutin pertama untuk bermula.</div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($routines as $routine): ?>
            <div class="col-12 col-lg-6">
                <article class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <div>
                                <p class="text-secondary small mb-1"><?= esc($routine['child_name']) ?></p>
                                <h2 class="h4 mb-0"><?= esc($routine['name']) ?></h2>
                                <span class="badge mt-2 <?= ! empty($routine['group_token']) ? 'text-bg-warning' : 'text-bg-light border' ?>"><?= ! empty($routine['group_token']) ? 'Semua anak · sunting bersama' : (($routine['assignment_scope'] ?? 'legacy') === 'individual' ? 'Individu' : 'Rekod lama · asal tidak direkodkan') ?></span>
                            </div>
                            <span class="badge align-self-start <?= $routine['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $routine['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <?php foreach ($routine['days'] as $day): ?>
                                <span class="badge text-bg-light border"><?= esc($dayNames[$day]) ?></span>
                            <?php endforeach ?>
                        </div>
                        <div class="small text-secondary mb-3">
                            <?= $routine['start_time'] ? esc(ui_time($routine['start_time'])) : 'Tiada masa mula' ?> · <?= esc($routine['task_count']) ?> tugasan
                        </div>
                        <a href="<?= route_to('parent.routines.edit', $routine['id']) ?>" class="btn btn-outline-primary">Sunting rutin</a>
                    </div>
                </article>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
