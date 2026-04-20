<?php use App\Core\View; ?>
<div class="container">
    <div class="row mb-4">
        <div class="col-md-3">
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="/product">
                        <div class="mb-3">
                            <label for="category" class="form-label">Categorie</label>
                            <select name="category" id="category" class="form-select">
                                <option value="">Alle categorieën</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['name'] ?>" <?= $current_category === $cat['name'] ? 'selected' : '' ?>>
                                        <?= View::e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="sort" class="form-label">Sorteren op</label>
                            <select name="sort" id="sort" class="form-select">
                                <option value="newest" <?= $current_sort === 'newest' ? 'selected' : '' ?>>Nieuwste eerst</option>
                                <option value="oldest" <?= $current_sort === 'oldest' ? 'selected' : '' ?>>Oudste eerst</option>
                                <option value="price_asc" <?= $current_sort === 'price_asc' ? 'selected' : '' ?>>Prijs (laag naar hoog)</option>
                                <option value="price_desc" <?= $current_sort === 'price_desc' ? 'selected' : '' ?>>Prijs (hoog naar laag)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Toepassen</button>
                    </form>
        </div>
    </div>
</div>

        <div class="col-md-9">
            <!-- Zoekbalk -->
            <form method="GET" action="/product" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Zoek producten..." value="<?= View::e($current_search ?? '') ?>">
                    <button type="submit" class="btn btn-primary">Zoeken</button>
                </div>
            </form>

            <!-- Producten lijst -->
            <div class="product-grid">
                <?php if (empty($products)): ?>
                    <div class="alert alert-info">
                        Geen producten gevonden.
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="card product-card">
                            <?php if ($product['image']): ?>
                                <img src="<?= View::e($product['image']) ?>"
                                     class="card-img-top product-image"
                                     alt="<?= View::e($product['name']) ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= View::e($product['name']) ?></h5>
                                <p class="card-text">
                                    <strong>Categorie:</strong> <?= View::e($product['category']) ?><br>
                                    <strong>Staat:</strong> <?= View::e($product['state']) ?><br>
                                    <strong>Prijs:</strong> €<?= number_format($product['price'], 2) ?>
                                </p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Verkoper: <?= View::e($product['username']) ?>
                                    </small>
                                </p>
                                <a href="/product/view/<?= $product['id'] ?>" class="btn btn-primary">
                                    Bekijk Product
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
