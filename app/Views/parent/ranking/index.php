<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="mb-4"><h1 class="h2 mb-1">Ranking</h1><p class="text-secondary mb-0">Parent-only ranking berdasarkan earned points, bukan current balance.</p></header>
<form action="<?= route_to('parent.ranking') ?>" method="get" class="card border-0 shadow-sm mb-4"><div class="card-body d-flex flex-column flex-md-row align-items-md-end gap-3">
    <div><label for="period" class="form-label">Tempoh</label><select id="period" name="period" class="form-select"><option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option><option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option><option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option></select></div>
    <div><label for="date" class="form-label">Tarikh rujukan</label><input id="date" name="date" type="date" class="form-control" value="<?= esc($dateInput) ?>"></div>
    <button class="btn btn-primary" type="submit">Kira ranking</button>
</div></form>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 class="h4 mb-0"><?= esc(ucfirst($period)) ?> · <?= esc($ranking['start_date']) ?><?= $ranking['start_date'] !== $ranking['end_date'] ? ' hingga ' . esc($ranking['end_date']) : '' ?></h2><span class="text-secondary"><?= esc($ranking['total_completed_tasks']) ?> / <?= esc($ranking['total_scheduled_tasks']) ?> tasks selesai</span></div>
<?php if ($ranking['rows'] === []): ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Tiada Child yang layak untuk ranking.</div></div>
<?php else: ?>
    <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Rank</th><th>Child</th><th>Earned</th><th>Tasks</th><th>Completion</th><th>Perfect</th><th>Streak</th></tr></thead>
        <tbody><?php foreach ($ranking['rows'] as $row): ?><tr>
            <td class="h5">#<?= esc($row['rank']) ?></td><td class="fw-semibold"><?= esc($row['name']) ?></td><td>⭐ <?= esc($row['earned_points']) ?></td><td><?= esc($row['completed_tasks']) ?> / <?= esc($row['scheduled_tasks']) ?></td><td><?= esc($row['completion_percentage']) ?>%</td><td><?= $period === 'daily' ? ($row['is_perfect_day'] ? '✓' : '—') : esc($row['perfect_days']) ?></td><td>🔥 <?= esc($row['current_streak']) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div></div>
<?php endif ?>
<?= $this->endSection() ?>
