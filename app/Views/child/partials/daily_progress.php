<section class="card border-0 shadow-sm mb-4" aria-labelledby="daily-progress-heading" data-daily-progress>
    <div class="card-body" aria-live="polite">
        <h2 class="h5" id="daily-progress-heading">Hari Ini</h2>
        <p><span data-daily-completed><?= esc($dailyProgress['completed_count']) ?></span> / <span data-daily-total><?= esc($dailyProgress['total_count']) ?></span> rutin selesai</p>
        <p data-daily-percentage><?= esc($dailyProgress['percentage']) ?>%</p>
        <progress class="w-100" data-daily-bar max="100" value="<?= esc($dailyProgress['percentage']) ?>" aria-label="Kemajuan rutin wajib hari ini"><?= esc($dailyProgress['percentage']) ?>%</progress>
    </div>
</section>
