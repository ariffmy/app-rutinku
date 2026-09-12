<section class="card child-summary-card border-0 shadow-sm mb-3" aria-labelledby="reward-goal-heading">
    <div class="card-body">
        <h2 class="h6 mb-2" id="reward-goal-heading">Sasaran ganjaran</h2>
        <?php if ($rewardGoal !== null): ?>
            <?php
            $required = (int) $rewardGoal['points_required'];
            $percentage = min(100, max(0, (int) floor($balance * 100 / max(1, $required))));
            $remaining = max(0, $required - $balance);
            ?>
            <div data-reward-goal data-required="<?= $required ?>" aria-live="polite">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-1 mb-2"><h3 class="h6 mb-0"><?= esc($rewardGoal['reward_title']) ?></h3><span class="small text-secondary"><span data-goal-balance><?= esc($balance) ?></span> / <?= $required ?> mata</span></div>
                <progress class="w-100 child-summary-progress" data-goal-progress max="100" value="<?= $percentage ?>" aria-label="Kemajuan sasaran ganjaran"><?= $percentage ?>%</progress>
                <div class="d-flex justify-content-between small mt-1 mb-2"><strong data-goal-percentage><?= $percentage ?>%</strong><span><span data-goal-remaining><?= $remaining ?></span> mata lagi</span></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= route_to('child.rewards') ?>">Lihat ganjaran</a>
                <form method="post" action="<?= route_to('child.reward-goals.cancel', $rewardGoal['id']) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">Batalkan sasaran</button></form>
            </div>
        <?php else: ?>
            <p class="small text-secondary mb-2">Pilih ganjaran yang ingin anda capai.</p>
            <a class="btn btn-sm btn-outline-primary" href="<?= route_to('child.rewards') ?>">Pilih sasaran</a>
        <?php endif ?>
    </div>
</section>
