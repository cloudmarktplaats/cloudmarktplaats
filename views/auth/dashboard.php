<?php require_once 'includes/header.php'; ?>

<div class="container">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Welkom, <?= htmlspecialchars($user['username']) ?>!</h5>
                    <p class="text-muted small">
                        Lid sinds <?= date('d-m-Y', strtotime($user['created_at'])) ?>
                    </p>
                    <hr>
                    <div class="list-group list-group-flush">
                        <a href="/product/add" class="list-group-item list-group-item-action">
                            <i class="bi bi-plus-circle"></i> Product toevoegen
                        </a>
                        <a href="/messages" class="list-group-item list-group-item-action">
                            <i class="bi bi-envelope"></i> Berichten
                            <?php if ($unread_messages > 0): ?>
                                <span class="badge bg-danger float-end"><?= $unread_messages ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/favorites" class="list-group-item list-group-item-action">
                            <i class="bi bi-heart"></i> Favorieten
                        </a>
                        <a href="/profile/edit" class="list-group-item list-group-item-action">
                            <i class="bi bi-pencil"></i> Profiel bewerken
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hoofdinhoud -->
        <div class="col-md-9">
            <!-- Mijn producten -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Mijn producten</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <p class="text-muted mb-0">Je hebt nog geen producten geplaatst.</p>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <?php if ($product['image']): ?>
                                            <img src="<?= htmlspecialchars($product['image']) ?>" 
                                                 class="card-img-top product-image" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                            <p class="card-text text-muted">
                                                <small>
                                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($product['category']) ?>
                                                    <br>
                                                    <i class="bi bi-circle"></i> <?= htmlspecialchars($product['state']) ?>
                                                </small>
                                            </p>
                                            <p class="card-text">€<?= number_format($product['price'], 2) ?></p>
                                            <div class="d-flex gap-2">
                                                <a href="/product/view?id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    Bekijk
                                                </a>
                                                <a href="/product/edit?id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    Bewerk
                                                </a>
                                                <form action="/product/delete" method="POST" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                            data-confirm="Weet je zeker dat je dit product wilt verwijderen?">
                                                        Verwijder
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mijn favorieten -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Mijn favorieten</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($favorites)): ?>
                        <p class="text-muted mb-0">Je hebt nog geen favorieten toegevoegd.</p>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php foreach ($favorites as $product): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <?php if ($product['image']): ?>
                                            <img src="<?= htmlspecialchars($product['image']) ?>" 
                                                 class="card-img-top product-image" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                                            <p class="card-text text-muted">
                                                <small>
                                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($product['category']) ?>
                                                    <br>
                                                    <i class="bi bi-circle"></i> <?= htmlspecialchars($product['state']) ?>
                                                </small>
                                            </p>
                                            <p class="card-text">€<?= number_format($product['price'], 2) ?></p>
                                            <div class="d-flex gap-2">
                                                <a href="/product/view?id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    Bekijk
                                                </a>
                                                <form action="/favorite/remove" method="POST" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Verwijder
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recente berichten -->
            <div class="card shadow-sm">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Recente berichten</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_messages)): ?>
                        <p class="text-muted mb-0">Je hebt nog geen berichten ontvangen.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_messages as $message): ?>
                                <a href="/messages/view?id=<?= $message['id'] ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?= htmlspecialchars($message['sender_username']) ?>
                                            <?php if (!$message['read_at']): ?>
                                                <span class="badge bg-primary">Nieuw</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= date('d-m-Y H:i', strtotime($message['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($message['message']) ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 