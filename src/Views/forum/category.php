<?php use App\Core\View; ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/forum">Forum</a></li>
            <li class="breadcrumb-item active"><?= View::e($category['name']) ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= View::e($category['name']) ?></h1>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/forum/new_topic?category_id=<?= $category['id'] ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nieuw Topic
            </a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header bg-light">
            <div class="row">
                <div class="col-md-6">Topic</div>
                <div class="col-md-2">Auteur</div>
                <div class="col-md-2">Reacties</div>
                <div class="col-md-2">Laatste reactie</div>
            </div>
        </div>
        <div class="list-group list-group-flush">
            <?php if (empty($topics)): ?>
                <div class="list-group-item text-center py-4">
                    <p class="text-muted mb-0">Nog geen topics in deze categorie.</p>
                </div>
            <?php else: ?>
                <?php foreach ($topics as $topic): ?>
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-1">
                                    <a href="/forum/topic?id=<?= $topic['id'] ?>" class="text-decoration-none">
                                        <?= View::e($topic['title']) ?>
                                    </a>
                                </h5>
                                <small class="text-muted">
                                    <i class="bi bi-eye"></i> <?= $topic['views'] ?> weergaven
                                </small>
                            </div>
                            <div class="col-md-2">
                                <small><?= View::e($topic['author_name']) ?></small>
                            </div>
                            <div class="col-md-2">
                                <small><?= $topic['reply_count'] ?> reacties</small>
                            </div>
                            <div class="col-md-2">
                                <?php if ($topic['last_reply_date']): ?>
                                    <small>
                                        Door <?= View::e($topic['last_reply_author']) ?><br>
                                        <?= date('d-m-Y H:i', strtotime($topic['last_reply_date'])) ?>
                                    </small>
                                <?php else: ?>
                                    <small>Geen reacties</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
