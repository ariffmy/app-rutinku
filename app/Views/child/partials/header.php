<?php $headerDate = new \DateTimeImmutable($headerDate ?? 'today', new \DateTimeZone(app_timezone())); ?>
<header class="pt-2 mb-4 child-today-header">
    <?= ui_avatar($profile['avatar'] ?? null) ?>
    <div class="child-today-greeting">
        <p class="text-uppercase small text-secondary fw-semibold mb-1"><?= esc($family['name']) ?></p>
        <h1 class="h2 fw-bold mb-1">Hai, <?= esc($child->name) ?></h1>
        <p class="fw-semibold text-primary mb-1"><?= ui_icon('star') ?> <span data-balance><?= esc($balance) ?></span> mata</p>
    </div>
    <div class="child-date-badge">
        <span class="child-date-day" data-today-day><?= esc([1 => 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu', 'Ahad'][(int) $headerDate->format('N')]) ?></span>
        <span data-today-date><?= esc(ui_date($headerDate->format('Y-m-d'))) ?></span>
    </div>
</header>
