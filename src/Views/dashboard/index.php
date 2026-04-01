<?php use App\Core\View; ?>
<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Welkom, <?= View::e($user['username']) ?></h5>
                    <hr>
                    <div class="list-group list-group-flush">
                        <a href="/dashboard" class="list-group-item list-group-item-action active">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a href="/product/add" class="list-group-item list-group-item-action">
                            <i class="bi bi-plus-circle"></i> Hardware Toevoegen
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
                        <a href="/profile" class="list-group-item list-group-item-action">
                            <i class="bi bi-person"></i> Profiel
                        </a>
                        <a href="/auth/logout" class="list-group-item list-group-item-action text-danger">
                            <i class="bi bi-box-arrow-right"></i> Uitloggen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Mijn Hardware -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Mijn Hardware</h5>
                    <a href="/product/add" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle"></i> Hardware Toevoegen
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-hdd-rack display-1 text-muted"></i>
                            <p class="mt-3">Je hebt nog geen hardware toegevoegd.</p>
                            <a href="/product/add" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Hardware Toevoegen
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?= View::e($product['image_url']) ?>"
                                                 class="card-img-top" alt="<?= View::e($product['name']) ?>">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= View::e($product['name']) ?></h6>
                                            <p class="card-text text-muted small">
                                                <?= View::e($product['category']) ?> •
                                                <?= View::e($product['state']) ?>
                                            </p>
                                            <p class="card-text fw-bold">€<?= number_format($product['price'], 2) ?></p>
                                            <div class="btn-group w-100">
                                                <a href="/product/edit?id=<?= $product['id'] ?>"
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-pencil"></i> Bewerken
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal<?= $product['id'] ?>">
                                                    <i class="bi bi-trash"></i> Verwijderen
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Verwijder Modal -->
                                    <div class="modal fade" id="deleteModal<?= $product['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Hardware verwijderen</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Weet je zeker dat je deze hardware wilt verwijderen?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Annuleren</button>
                                                    <form action="/product/delete" method="POST" class="d-inline">
                                                        <?= View::csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                                        <button type="submit" class="btn btn-danger">Verwijderen</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recente Berichten -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recente Berichten</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_messages)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-envelope display-1 text-muted"></i>
                            <p class="mt-3">Je hebt nog geen berichten ontvangen.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_messages as $message): ?>
                                <a href="/messages/view?id=<?= $message['id'] ?>"
                                   class="list-group-item list-group-item-action <?= $message['read_at'] ? '' : 'fw-bold' ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= View::e($message['subject']) ?></h6>
                                        <small class="text-muted">
                                            <?= date('d-m-Y H:i', strtotime($message['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 text-muted">
                                        Van: <?= View::e($message['sender_username']) ?>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="/messages" class="btn btn-outline-primary btn-sm">
                                Alle berichten bekijken
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
