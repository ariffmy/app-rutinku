<section class="card border-0 shadow-sm mb-4" aria-labelledby="reward-goal-heading">
    <div class="card-body">
        <h2 class="h5" id="reward-goal-heading">Sasaran ganjaran</h2>
        <?php if ($rewardGoal !== null): ?>
            <?php
            $required = (int) $rewardGoal['points_required'];
            $percentage = min(100, max(0, (int) floor($balance * 100 / max(1, $required))));
            $remaining = max(0, $required - $balance);
            ?>
            <div data-reward-goal data-required="<?= $required ?>" aria-live="polite">
                <h3 class="h4"><?= esc($rewardGoal['reward_title']) ?></h3>
                <p><span data-goal-balance><?= esc($balance) ?></span> / <?= $required ?> mata</p>
                <progress class="w-100" data-goal-progress max="100" value="<?= $percentage ?>" aria-label="Kemajuan sasaran ganjaran"><?= $percentage ?>%</progress>
                <p class="mb-1" data-goal-percentage><?= $percentage ?>%</p>
                <p><span data-goal-remaining><?= $remaining ?></span> mata lagi</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="<?= route_to('child.rewards') ?>">Lihat ganjaran</a>
                <form method="post" action="<?= route_to('child.reward-goals.cancel', $rewardGoal['id']) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit">Batalkan sasaran</button></form>
            </div>
        <?php else: ?>
            <p>Pilih ganjaran yang ingin anda capai.</p>
            <a class="btn btn-outline-primary" href="<?= route_to('child.rewards') ?>">Pilih sasaran</a>
        <?php endif ?>
    </div>
</section>
