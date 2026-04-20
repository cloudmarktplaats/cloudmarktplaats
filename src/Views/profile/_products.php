<?php use App\Core\View; ?>
<h6 class="mb-3">Recente Producten</h6>
<?php if (empty($products)): ?>
    <p class="text-muted">Nog geen producten geplaatst.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($products as $product): ?>
            <a href="/product/view/<?= $product['id'] ?>"
               class="list-group-item list-group-item-action"
               hx-get="/product/view/<?= $product['id'] ?>"
               hx-target="#main-content"
               hx-push-url="true">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?= View::e($product['name']) ?></h6>
                    <small class="text-muted">€<?= number_format($product['price'], 2) ?></small>
                </div>
                <small class="text-muted">
                    <?= date('d-m-Y', strtotime($product['created_at'])) ?>
                </small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
