<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Forum</h1>
        <?php if ($this->isAdmin()): ?>
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
                                <a href="/forum/category?id=<?php echo $category['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php echo $category['topic_count']; ?> topics
                                    &bull;
                                    <?php echo $category['reply_count']; ?> reacties
                                </small>
                                <a href="/forum/new_topic?category_id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
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