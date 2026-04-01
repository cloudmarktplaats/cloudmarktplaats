<?php use App\Core\View; ?>
<h1>Gebruikers Beheren</h1>

<form class="row g-2 mb-3" method="GET" action="/admin/users">
    <div class="col-auto">
        <input type="text" class="form-control" name="search" placeholder="Zoek gebruiker..." value="<?= View::e($_GET['search'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <select class="form-select" name="role">
            <option value="">Alle rollen</option>
            <option value="user" <?= ($current_role ?? '') === 'user' ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= ($current_role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary" type="submit">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>ID</th><th>Gebruiker</th><th>Email</th><th>Rol</th><th>Lid sinds</th><th>Acties</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int) $u['id'] ?></td>
            <td><?= View::e($u['username']) ?></td>
            <td><?= View::e($u['email']) ?></td>
            <td><?= $u['role'] === 'admin' ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">User</span>' ?></td>
            <td><?= View::e($u['created_at']) ?></td>
            <td>
                <form method="POST" action="/admin/users/toggle-admin/<?= (int) $u['id'] ?>" class="d-inline">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-outline-primary">
                        <?= $u['role'] === 'admin' ? 'Maak User' : 'Maak Admin' ?>
                    </button>
                </form>
                <form method="POST" action="/admin/users/delete/<?= (int) $u['id'] ?>" class="d-inline" onsubmit="return confirm('Weet je het zeker?')">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-danger">Verwijderen</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
