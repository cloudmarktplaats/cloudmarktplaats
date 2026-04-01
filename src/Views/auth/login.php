<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Inloggen</h2>

                    <form action="/auth/login" method="POST">
                        <?= View::csrfField() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Gebruikersnaam</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= View::e($_POST['username'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Wachtwoord</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Inloggen</button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">Nog geen account?
                            <a href="/auth/register" class="text-decoration-none">Registreer hier</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
