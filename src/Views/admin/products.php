<?php use App\Core\View; ?>
<h1>Producten Beheren</h1>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $current_status === 'pending' ? 'active' : '' ?>" href="/admin/products?status=pending">Wachtend</a></li>
    <li class="nav-item"><a class="nav-link <?= $current_status === 'approved' ? 'active' : '' ?>" href="/admin/products?status=approved">Goedgekeurd</a></li>
    <li class="nav-item"><a class="nav-link <?= $current_status === 'all' ? 'active' : '' ?>" href="/admin/products?status=all">Alles</a></li>
</ul>

<?php if (empty($products)): ?>
<p class="text-muted">Geen producten gevonden.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Naam</th><th>Categorie</th><th>Prijs</th><th>Verkoper</th><th>Status</th><th>Acties</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><a href="/product/view/<?= (int) $p['id'] ?>"><?= View::e($p['name']) ?></a></td>
            <td><?= View::e($p['category']) ?></td>
            <td>&euro;<?= number_format((float) $p['price'], 2, ',', '.') ?></td>
            <td><?= View::e($p['username']) ?></td>
            <td><?= $p['approved'] ? '<span class="badge bg-success">Goedgekeurd</span>' : '<span class="badge bg-warning">Wachtend</span>' ?></td>
            <td>
                <?php if (!$p['approved']): ?>
                <form method="POST" action="/admin/products/approve/<?= (int) $p['id'] ?>" class="d-inline">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-success">Goedkeuren</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="/admin/products/delete/<?= (int) $p['id'] ?>" class="d-inline" onsubmit="return confirm('Weet je het zeker?')">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-danger">Verwijderen</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
