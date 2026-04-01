<?php use App\Core\View; ?>
<div class="container">
    <div class="row">
        <div class="col-md-4">
            <!-- Profiel informatie -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h4"><?= View::e($profile_user['username']) ?></h2>
                    <p class="card-text text-muted">
                        Lid sinds: <?= date('d-m-Y', strtotime($profile_user['created_at'])) ?>
                    </p>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $profile_user['id']): ?>
                        <a href="/messages?user=<?= $profile_user['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-chat"></i> Bericht sturen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Producten van de gebruiker -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Producten van <?= View::e($profile_user['username']) ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <p class="text-muted">Deze gebruiker heeft nog geen producten geplaatst.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($products as $product): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <?php if ($product['image']): ?>
                                            <img src="<?= View::e($product['image']) ?>"
                                                 class="card-img-top product-image"
                                                 alt="<?= View::e($product['name']) ?>">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= View::e($product['name']) ?></h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    €<?= number_format($product['price'], 2) ?>
                                                </small>
                                            </p>
                                            <a href="/product/<?= $product['id'] ?>"
                                               class="btn btn-outline-primary btn-sm w-100">
                                                Bekijk product
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews van de gebruiker -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Reviews van <?= View::e($profile_user['username']) ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted">Deze gebruiker heeft nog geen reviews geschreven.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($reviews as $review): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <a href="/product/<?= $review['product_id'] ?>" class="text-decoration-none">
                                                <?= View::e($review['product_name']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            <?= date('d-m-Y H:i', strtotime($review['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?= nl2br(View::e($review['review'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
