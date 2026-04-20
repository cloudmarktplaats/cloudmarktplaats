<?php use App\Core\View; ?>
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <!-- Product details -->
            <div class="card mb-4">
                <?php if ($product['image']): ?>
                    <img src="<?= View::e($product['image']) ?>"
                         class="card-img-top"
                         alt="<?= View::e($product['name']) ?>"
                         style="max-height: 400px; object-fit: contain;">
                <?php endif; ?>
                <div class="card-body">
                    <h1 class="card-title h3"><?= View::e($product['name']) ?></h1>
                    <p class="card-text">
                        <strong>Categorie:</strong> <?= View::e($product['category']) ?><br>
                        <strong>Staat:</strong> <?= View::e($product['state']) ?><br>
                        <strong>Prijs:</strong> €<?= number_format($product['price'], 2) ?>
                    </p>
                    <p class="card-text">
                        <strong>Beschrijving:</strong><br>
                        <?= nl2br(View::e($product['description'])) ?>
                    </p>
                    <p class="card-text">
                        <small class="text-muted">
                            Verkoper: <?= View::e($product['username']) ?><br>
                            Geplaatst op: <?= date('d-m-Y H:i', strtotime($product['created_at'])) ?>
                        </small>
                    </p>
                </div>
            </div>

            <!-- Reviews -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h5 mb-0">Reviews</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted">Nog geen reviews voor dit product.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h3 class="h6 mb-0"><?= View::e($review['username']) ?></h3>
                                    <small class="text-muted">
                                        <?= date('d-m-Y H:i', strtotime($review['created_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-0"><?= nl2br(View::e($review['review'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- TODO: Phase 2 review system -->
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <p class="text-muted mt-3">
                            <a href="/auth/login">Log in</a> om een review te plaatsen.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Acties -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_id'] !== $product['user_id']): ?>
                            <!-- TODO: Phase 2 favorite system -->
                            <a href="/message/conversation/<?= $product['user_id'] ?>"
                               class="btn btn-primary w-100">
                                <i class="bi bi-chat"></i> Contact opnemen
                            </a>
                        <?php else: ?>
                            <a href="/product/edit/<?= $product['id'] ?>" class="btn btn-outline-primary w-100 mb-3">
                                <i class="bi bi-pencil"></i> Product bewerken
                            </a>
                            <form method="POST" action="/product/delete/<?= $product['id'] ?>"
                                  onsubmit="return confirm('Weet je zeker dat je dit product wilt verwijderen?');">
                                <?= View::csrfField() ?>
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-trash"></i> Product verwijderen
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-3">
                            <a href="/auth/login">Log in</a> om contact op te nemen of het product toe te voegen aan je favorieten.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Verkoper informatie -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Verkoper</h3>
                </div>
                <div class="card-body">
                    <h4 class="h6"><?= View::e($product['username']) ?></h4>
                    <a href="/profile/view/<?= $product['user_id'] ?>" class="btn btn-outline-primary btn-sm">
                        Bekijk profiel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
