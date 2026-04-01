<?php use App\Core\View; ?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Forum</h1>
        <?php if (isset($is_admin) && $is_admin): ?>
            <a href="/forum/new_category" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nieuwe Categorie
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($categories)): ?>
        <div class="alert alert-info">
            Er zijn nog geen forum categorieën beschikbaar.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="/forum/category?id=<?= $category['id'] ?>" class="text-decoration-none">
                                    <?= View::e($category['name']) ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted">
                                <?= View::e($category['description']) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?= $category['topic_count'] ?> topics
                                    &bull;
                                    <?= $category['reply_count'] ?> reacties
                                </small>
                                <a href="/forum/new_topic?category_id=<?= $category['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus"></i> Nieuw Topic
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
