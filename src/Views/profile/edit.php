<?php use App\Core\View; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Profiel Bewerken</h2>
                </div>
                <div class="card-body">
                    <form action="/profile/edit" method="POST">
                        <?= View::csrfField() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Gebruikersnaam *</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= View::e($user['username']) ?>" required>
                            <div class="form-text">Minimaal 3 tekens.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= View::e($user['email']) ?>" required>
                        </div>

                        <hr>

                        <h5 class="mb-3">Wachtwoord Wijzigen</h5>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Huidig Wachtwoord</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <div class="form-text">Laat leeg om het wachtwoord niet te wijzigen.</div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nieuw Wachtwoord</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <div class="form-text">Minimaal 6 tekens.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Bevestig Nieuw Wachtwoord</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/profile" class="btn btn-secondary">Terug naar Profiel</a>
                            <button type="submit" class="btn btn-primary">Wijzigingen Opslaan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
