<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="mb-4">
    <p class="text-uppercase text-secondary small fw-semibold mb-1"><?= esc($report['family']['name']) ?></p>
    <h1 class="h2 mb-1">Reports</h1>
    <p class="text-secondary mb-0">Laporan progress keluarga mengikut hari, minggu atau bulan.</p>
</header>

<form action="<?= route_to('parent.reports') ?>" method="get" class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-end gap-3">
        <div>
            <label for="period" class="form-label">Tempoh</label>
            <select id="period" name="period" class="form-select">
                <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
                <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>
        <div>
            <label for="date" class="form-label">Tarikh rujukan</label>
            <input id="date" name="date" type="date" class="form-control" value="<?= esc($dateInput) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Jana laporan</button>
    </div>
</form>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <h2 class="h4 mb-0"><?= esc(ucfirst($period)) ?> · <?= esc($report['start_date']) ?><?= $report['start_date'] !== $report['end_date'] ? ' hingga ' . esc($report['end_date']) : '' ?></h2>
    <?php if ($report['is_future']): ?><span class="badge text-bg-light border">Tempoh akan datang</span><?php endif ?>
</div>

<section class="row g-3 mb-4" aria-label="Ringkasan laporan">
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Tasks Completed</p><p class="h3 mb-0"><?= esc($report['total_completed_tasks']) ?> / <?= esc($report['total_scheduled_tasks']) ?></p></div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Completion</p><p class="h3 mb-0"><?= esc($report['completion_percentage']) ?>%</p></div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Earned Points</p><p class="h3 mb-0">⭐ <?= esc($report['total_earned_points']) ?></p></div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="small text-secondary mb-1">Perfect Days</p><p class="h3 mb-0"><?= esc($report['total_perfect_days']) ?></p></div></div></div>
</section>

<section class="mb-4" aria-labelledby="child-report-heading">
    <h2 id="child-report-heading" class="h4 mb-3">Progress setiap Child</h2>
    <?php if ($report['rows'] === []): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Tiada Child aktif dalam family ini.</div></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Child</th><th>Tasks</th><th>Required</th><th>Completion</th><th>Earned</th><th>Perfect</th><th>Balance</th><th>Streak</th></tr></thead>
            <tbody><?php foreach ($report['rows'] as $row): ?><tr>
                <td class="fw-semibold"><?= esc($row['name']) ?></td>
                <td><?= esc($row['completed_tasks']) ?> / <?= esc($row['scheduled_tasks']) ?></td>
                <td><?= esc($row['required_completed_tasks']) ?> / <?= esc($row['required_scheduled_tasks']) ?></td>
                <td><?= esc($row['completion_percentage']) ?>%</td>
                <td>⭐ <?= esc($row['earned_points']) ?></td>
                <td><?= esc($row['perfect_days']) ?></td>
                <td><?= esc($row['current_balance']) ?></td>
                <td>🔥 <?= esc($row['current_streak']) ?></td>
            </tr><?php endforeach ?></tbody>
        </table></div></div>
    <?php endif ?>
</section>

<?php if ($period !== 'daily' && $report['daily_breakdown'] !== []): ?>
<section aria-labelledby="daily-breakdown-heading">
    <h2 id="daily-breakdown-heading" class="h4 mb-3">Pecahan harian</h2>
    <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Tarikh</th><th>Tasks</th><th>Completion</th><th>Earned</th><th>Perfect Child</th></tr></thead>
        <tbody><?php foreach ($report['daily_breakdown'] as $day): ?><tr>
            <td><?= esc($day['date']) ?></td><td><?= esc($day['completed_tasks']) ?> / <?= esc($day['scheduled_tasks']) ?></td><td><?= esc($day['completion_percentage']) ?>%</td><td>⭐ <?= esc($day['earned_points']) ?></td><td><?= esc($day['perfect_children']) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div></div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
