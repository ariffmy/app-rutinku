<?= $this->extend('layouts/parent') ?>

<?= $this->section('content') ?>
<header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div><h1 class="h2 mb-1">Anak-anak</h1><p class="text-secondary mb-0">Urus akaun, profil, kedudukan dan peranti dipercayai Anak.</p></div>
    <a href="<?= route_to('parent.children.new') ?>" class="btn btn-primary">Tambah Anak</a>
</header>
<div class="row g-3">
    <?php foreach ($children as $child): ?>
        <div class="col-12 col-md-6 col-xl-4"><article class="card h-100 border-0 shadow-sm"><div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="avatar-placeholder" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($child['name'], 0, 1))) ?></div>
                <div><h2 class="h5 mb-1"><?= esc($child['name']) ?></h2><span class="badge <?= $child['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $child['is_active'] ? 'Aktif' : 'Tidak aktif' ?></span></div>
            </div>
            <dl class="row small mb-3"><dt class="col-6">Tarikh lahir</dt><dd class="col-6 text-end"><?= esc(ui_date($child['date_of_birth'] ?: null)) ?></dd><dt class="col-6">Kedudukan</dt><dd class="col-6 text-end"><?= $child['is_ranking_eligible'] ? 'Layak' : 'Tidak layak' ?></dd></dl>
            <div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="<?= route_to('parent.children.edit', $child['id']) ?>">Sunting profil</a><a class="btn btn-outline-secondary btn-sm" href="<?= route_to('parent.child.devices', $child['id']) ?>">Peranti</a></div>
        </div></article></div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
