<section class="card child-summary-card border-0 shadow-sm mb-3" aria-labelledby="daily-progress-heading" data-daily-progress>
    <div class="card-body" aria-live="polite">
        <div class="d-flex align-items-end justify-content-between gap-3 mb-2">
            <div><h2 class="h6 mb-1" id="daily-progress-heading">Hari Ini</h2><p class="small text-secondary mb-0"><span data-daily-completed><?= esc($dailyProgress['completed_count']) ?></span> / <span data-daily-total><?= esc($dailyProgress['total_count']) ?></span> rutin selesai</p></div>
            <strong class="h5 mb-0" data-daily-percentage><?= esc($dailyProgress['percentage']) ?>%</strong>
        </div>
        <progress class="w-100 child-summary-progress" data-daily-bar max="100" value="<?= esc($dailyProgress['percentage']) ?>" aria-label="Kemajuan rutin wajib hari ini"><?= esc($dailyProgress['percentage']) ?>%</progress>
    </div>
</section>
