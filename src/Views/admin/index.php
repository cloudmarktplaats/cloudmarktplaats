<?php use App\Core\View; ?>
<h1>Admin Dashboard</h1>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= (int) $stats['total_products'] ?></h3>
                <p class="text-muted">Producten</p>
                <?php if ($stats['pending_products'] > 0): ?>
                <span class="badge bg-warning"><?= (int) $stats['pending_products'] ?> wachtend</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= (int) $stats['total_users'] ?></h3>
                <p class="text-muted">Gebruikers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= (int) $stats['total_reviews'] ?></h3>
                <p class="text-muted">Reviews</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recente Producten</h5>
                <a href="/admin/products" class="btn btn-sm btn-outline-primary">Alle bekijken</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Naam</th><th>Verkoper</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_products as $p): ?>
                    <tr>
                        <td><?= View::e($p['name']) ?></td>
                        <td><?= View::e($p['username']) ?></td>
                        <td><?= $p['approved'] ? '<span class="badge bg-success">Goedgekeurd</span>' : '<span class="badge bg-warning">Wachtend</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recente Gebruikers</h5>
                <a href="/admin/users" class="btn btn-sm btn-outline-primary">Alle bekijken</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Gebruiker</th><th>Email</th><th>Rol</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td><?= View::e($u['username']) ?></td>
                        <td><?= View::e($u['email']) ?></td>
                        <td><?= $u['role'] === 'admin' ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">User</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
