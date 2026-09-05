<?= $this->extend('layouts/parent') ?>
<?= $this->section('content') ?>
<?php
$value = static fn (string $key, mixed $default = '') => old($key) ?? ($task[$key] ?? $default);
$frequency = $value('schedule_type', 'inherit');
$days = old('repeat_days') ?? explode(',', (string) ($task['repeat_days'] ?? ''));
$days = is_array($days) ? $days : [];
$owner = (new \App\Models\UserModel())->find((int) $routine['child_user_id']);
$savedTime = (string) $value('task_time');
$selectedHour = (string) (old('task_hour') ?? ($savedTime === '' ? '' : substr($savedTime, 0, 2)));
$selectedMinute = (string) (old('task_minute') ?? ($savedTime === '' ? '00' : substr($savedTime, 3, 2)));
$isGroup = ! empty($routine['group_token']);
?>
<div class="task-editor">
<a href="<?= route_to('parent.routines.edit', $routine['id']) ?>">← Rutin: <?= esc($routine['name']) ?></a>
<h1 class="h3 mt-3 mb-4"><?= $task ? 'Sunting tugasan' : 'Tambah tugasan' ?></h1>
<p class="text-secondary">Tugasan untuk <?= esc($routine['name']) ?></p>
<div class="alert alert-light border">Hari rutin: <strong><?= esc(implode(', ', array_map(static fn ($day) => [1 => 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu', 'Ahad'][(int) $day], $routine['days']))) ?></strong>. Semua tugasan hanya muncul pada hari ini.</div>
<?php if (session('errors')): ?><div class="alert alert-danger" role="alert"><?php foreach (session('errors') as $error): ?><div><?= esc($error) ?></div><?php endforeach ?></div><?php endif ?>
<form action="<?= esc($action) ?>" method="post" class="task-editor-card" data-task-form>
<?= csrf_field() ?>
<div class="task-name-row"><span class="task-clock" aria-hidden="true"><?= ui_icon('clock') ?></span><div class="flex-grow-1">
<label for="title" class="form-label">Nama tugasan</label><input id="title" name="title" class="form-control" maxlength="160" required value="<?= esc($value('title')) ?>" placeholder="Contoh: Gosok gigi">
</div></div>
<details class="task-ideas"><summary><?= ui_icon('lightbulb') ?> Idea tugasan</summary><div class="task-chips mt-2">
<?php foreach ([['Gosok gigi', 5, 5], ['Kemas katil', 5, 5], ['Baca buku', 15, 10], ['Siapkan kerja sekolah', 30, 20], ['Bantu ibu bapa', 15, 10]] as [$idea, $minutes, $stars]): ?>
<button type="button" data-idea="<?= esc($idea) ?>" data-minutes="<?= $minutes ?>" data-stars="<?= $stars ?>"><?= esc($idea) ?></button>
<?php endforeach ?></div></details>
<fieldset class="task-section"><legend>Bila?</legend><div class="row g-2"><div class="col-8"><label for="task_hour" class="form-label">Masa mula (pilihan)</label>
<select id="task_hour" name="task_hour" class="form-select"><option value="">Bila-bila masa</option>
<?php for ($hour = 0; $hour < 24; $hour++): $hourValue = sprintf('%02d', $hour); ?><option value="<?= $hourValue ?>" <?= $selectedHour === $hourValue ? 'selected' : '' ?>><?= esc(ui_time($hourValue . ':00')) ?></option><?php endfor ?></select></div>
<div class="col-4"><label for="task_minute" class="form-label">Minit</label><select id="task_minute" name="task_minute" class="form-select">
<?php for ($minute = 0; $minute < 60; $minute++): $minuteValue = sprintf('%02d', $minute); ?><option value="<?= $minuteValue ?>" <?= $selectedMinute === $minuteValue ? 'selected' : '' ?>><?= $minuteValue ?></option><?php endfor ?></select></div></div>
<output class="task-time-preview" data-time-preview aria-live="polite"></output></fieldset>
<fieldset class="task-section"><legend>Berapa lama?</legend><div class="task-chips task-duration">
<?php foreach ([5 => '5 min', 15 => '15 min', 30 => '30 min', 60 => '1 jam', 120 => '2 jam'] as $minutes => $label): ?><button type="button" data-duration="<?= $minutes ?>" aria-pressed="false"><?= $label ?></button><?php endforeach ?></div>
<label for="duration_minutes" class="form-label mt-3">Tempoh lain (minit)</label><input id="duration_minutes" name="duration_minutes" type="number" min="1" max="1440" required class="form-control" value="<?= esc($value('duration_minutes', 15)) ?>"></fieldset>
<fieldset class="task-section"><legend>Jadual tugasan</legend><div class="task-chips task-frequency">
<?php foreach (['inherit' => 'Ikut rutin', 'daily' => 'Ikut rutin mulai tarikh', 'once' => 'Sekali sahaja', 'weekly' => 'Hari tertentu', 'monthly' => 'Bulanan'] as $type => $label): ?><label><input type="radio" name="schedule_type" value="<?= $type ?>" <?= $frequency === $type ? 'checked' : '' ?>><span><?= $label ?></span></label><?php endforeach ?></div>
<div data-date-section class="mt-3"><label for="start_date" class="form-label">Tarikh mula / tarikh tugasan sekali</label><input id="start_date" name="start_date" type="date" class="form-control" value="<?= esc($value('start_date', date('Y-m-d'))) ?>"></div>
<div data-weekly-section class="mt-3"><p class="mb-2">Hari ulangan mingguan</p><div class="task-chips task-frequency">
<?php foreach ([1 => 'Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'] as $day => $label): ?><label><input type="checkbox" name="repeat_days[]" value="<?= $day ?>" <?= in_array((string) $day, array_map('strval', $days), true) ? 'checked' : '' ?>><span><?= $label ?></span></label><?php endforeach ?></div></div>
<p class="small text-secondary mt-3">“Ikut rutin” menggunakan semua hari rutin tanpa perlu memilih hari sekali lagi. Pilihan lain hanya mengehadkan tugasan dalam hari rutin. Tarikh sekali mesti jatuh pada hari rutin. Bulanan: tarikh di luar hari rutin atau bulan tanpa tarikh berkenaan dilangkau, bukan dipindahkan.</p></fieldset>
<fieldset class="task-section"><legend>Berapa bintang? <?= ui_icon('star') ?></legend><label for="points" class="form-label">Mata ganjaran</label>
<input id="points" name="points" type="number" min="0" max="10000" required class="form-control" value="<?= esc($value('points', 10)) ?>"><input type="range" min="0" max="10000" step="1" class="task-points-range" data-points-range aria-label="Laraskan mata ganjaran" value="<?= esc($value('points', 10)) ?>"><div class="task-chips task-adjust">
<?php foreach ([-100, -10, -1, 1, 10, 100] as $amount): ?><button type="button" data-points-change="<?= $amount ?>" aria-label="<?= $amount < 0 ? 'Kurangkan' : 'Tambah' ?> <?= abs($amount) ?> mata"><?= $amount > 0 ? '+' : '' ?><?= $amount ?></button><?php endforeach ?></div></fieldset>
<?php if (! $isGroup): ?><fieldset class="task-section"><legend>Untuk siapa?</legend><label for="assign_to" class="form-label">Pilih anak</label><select id="assign_to" name="assign_to" class="form-select"><option value="current"><?= esc($owner->name) ?></option>
<?php if (! $task): ?><option value="all" <?= old('assign_to') === 'all' ? 'selected' : '' ?>>Semua anak aktif</option><?php endif ?></select>
<p class="small text-secondary mt-2"><?= $task ? 'Suntingan hanya mengubah tugasan anak ini.' : 'Semua anak: salinan berasingan dalam rutin bernama sama. Rutin akan disalin jika belum ada. Markah dan suntingan setiap anak berasingan.' ?></p></fieldset><?php endif ?>
<details class="task-section"><summary>Tetapan tambahan</summary>
<?php foreach (['is_required' => 'Tugasan wajib', 'is_active' => 'Tugasan aktif'] as $key => $label): ?><input type="hidden" name="<?= $key ?>" value="0"><div class="form-check form-switch mt-3"><input id="<?= $key ?>" name="<?= $key ?>" value="1" type="checkbox" class="form-check-input" <?= $value($key, 1) ? 'checked' : '' ?>><label for="<?= $key ?>" class="form-check-label"><?= $label ?></label></div><?php endforeach ?></details>
<div class="task-save"><button type="submit" class="btn btn-primary w-100">Simpan tugasan</button></div>
</form></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/task-form.css') ?>">
<script defer src="<?= base_url('assets/js/task-form.js') ?>"></script>
<?= $this->endSection() ?>
