<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row">
        <div class="col-md-8">
            <!-- Product details -->
            <div class="card mb-4">
                <?php if ($product['image']): ?>
                    <img src="<?= htmlspecialchars($product['image']) ?>" 
                         class="card-img-top" 
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         style="max-height: 400px; object-fit: contain;">
                <?php endif; ?>
                <div class="card-body">
                    <h1 class="card-title h3"><?= htmlspecialchars($product['name']) ?></h1>
                    <p class="card-text">
                        <strong>Categorie:</strong> <?= htmlspecialchars($product['category']) ?><br>
                        <strong>Staat:</strong> <?= htmlspecialchars($product['state']) ?><br>
                        <strong>Prijs:</strong> €<?= number_format($product['price'], 2) ?>
                    </p>
                    <p class="card-text">
                        <strong>Beschrijving:</strong><br>
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>
                    <p class="card-text">
                        <small class="text-muted">
                            Verkoper: <?= htmlspecialchars($product['username']) ?><br>
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
                                    <h3 class="h6 mb-0"><?= htmlspecialchars($review['username']) ?></h3>
                                    <small class="text-muted">
                                        <?= date('d-m-Y H:i', strtotime($review['created_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($review['review'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" action="/add_review" class="mt-4">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <div class="mb-3">
                                <label for="review" class="form-label">Schrijf een review</label>
                                <textarea name="review" id="review" class="form-control" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Review plaatsen</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mt-3">
                            <a href="/login">Log in</a> om een review te plaatsen.
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
                            <form method="POST" action="/add_favorite" class="mb-3">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-heart"></i> Toevoegen aan favorieten
                                </button>
                            </form>
                            <a href="/messages?user=<?= $product['user_id'] ?>&product=<?= $product['id'] ?>" 
                               class="btn btn-primary w-100">
                                <i class="bi bi-chat"></i> Contact opnemen
                            </a>
                        <?php else: ?>
                            <a href="/edit_product/<?= $product['id'] ?>" class="btn btn-outline-primary w-100 mb-3">
                                <i class="bi bi-pencil"></i> Product bewerken
                            </a>
                            <form method="POST" action="/delete_product/<?= $product['id'] ?>" 
                                  onsubmit="return confirm('Weet je zeker dat je dit product wilt verwijderen?');">
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-trash"></i> Product verwijderen
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-3">
                            <a href="/login">Log in</a> om contact op te nemen of het product toe te voegen aan je favorieten.
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
                    <h4 class="h6"><?= htmlspecialchars($product['username']) ?></h4>
                    <a href="/profile/<?= $product['user_id'] ?>" class="btn btn-outline-primary btn-sm">
                        Bekijk profiel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 