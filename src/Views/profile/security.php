<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-4">Beveiliging</h1>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Wachtwoord</h2>
                    <?php if (!empty($user_row['password'])): ?>
                        <p class="mb-0 text-success">✓ Wachtwoord ingesteld — je kunt inloggen met gebruikersnaam + wachtwoord.</p>
                    <?php else: ?>
                        <p class="mb-0 text-muted">Geen wachtwoord ingesteld. Je logt in via OAuth of je wallet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">OAuth-providers</h2>

                    <?php foreach (['google' => 'Google', 'github' => 'GitHub'] as $provider => $label): ?>
                        <?php
                        $linked = null;
                        foreach ($oauth as $link) {
                            if ($link['provider'] === $provider) {
                                $linked = $link;
                                break;
                            }
                        }
                        ?>
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <div>
                                <strong><?= View::e($label) ?></strong>
                                <?php if ($linked): ?>
                                    <span class="text-muted ms-2">(<?= View::e($linked['email'] ?? '—') ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($linked): ?>
                                <?php if ($auth_methods_count > 1): ?>
                                    <form method="POST" action="/profile/security/oauth/<?= View::e($provider) ?>/unlink" class="m-0">
                                        <?= View::csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Ontkoppelen</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">(enige inlogmethode)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="/auth/oauth/<?= View::e($provider) ?>" class="btn btn-sm btn-outline-primary">Koppelen</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Wallets</h2>

                    <?php if (empty($wallets)): ?>
                        <p class="text-muted">Nog geen wallets gekoppeld. Gebruik de MetaMask/WalletConnect-knoppen op de loginpagina om een wallet te koppelen aan dit account.</p>
                    <?php else: ?>
                        <?php foreach ($wallets as $w): ?>
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div>
                                    <code><?= View::e(substr($w['address'], 0, 10) . '...' . substr($w['address'], -6)) ?></code>
                                    <span class="text-muted ms-2">chain <?= View::e($w['chain_id']) ?></span>
                                </div>
                                <?php if ($auth_methods_count > 1): ?>
                                    <form method="POST" action="/profile/security/wallet/<?= (int) $w['id'] ?>/unlink" class="m-0">
                                        <?= View::csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Ontkoppelen</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">(enige inlogmethode)</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
