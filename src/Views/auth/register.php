<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Registreren</h2>

                    <form action="/auth/register" method="POST">
                        <?= View::csrfField() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Gebruikersnaam</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= View::e($_POST['username'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mailadres</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= View::e($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Wachtwoord</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="8" required>
                            <div class="form-text">
                                Minimaal 8 karakters
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Bevestig wachtwoord</label>
                            <input type="password" class="form-control" id="password_confirm"
                                   name="password_confirm" minlength="8" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Registreren</button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">Al een account?
                            <a href="/auth/login" class="text-decoration-none">Log hier in</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
