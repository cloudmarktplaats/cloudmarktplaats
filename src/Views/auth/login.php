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

                    <div class="text-center my-4">
                        <span class="text-muted">of</span>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="/auth/oauth/google" class="btn btn-outline-secondary">
                            <i class="bi bi-google me-2"></i>Inloggen met Google
                        </a>
                        <a href="/auth/oauth/github" class="btn btn-outline-dark">
                            <i class="bi bi-github me-2"></i>Inloggen met GitHub
                        </a>
                        <button type="button" id="web3-metamask" class="btn btn-outline-warning">
                            🦊 MetaMask
                        </button>
                        <button type="button" id="web3-walletconnect" class="btn btn-outline-primary">
                            <i class="bi bi-qr-code-scan me-2"></i>WalletConnect
                        </button>
                    </div>
                    <div id="web3-status" class="text-muted small mt-2 text-center"></div>

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
<script src="/assets/js/web3-login.js" defer></script>
