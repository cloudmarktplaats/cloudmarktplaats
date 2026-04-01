<?php use App\Core\View; ?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/forum">Forum</a></li>
            <li class="breadcrumb-item">
                <a href="/forum/category?id=<?= $topic['category_id'] ?>">
                    <?= View::e($topic['category_name']) ?>
                </a>
            </li>
            <li class="breadcrumb-item active"><?= View::e($topic['title']) ?></li>
        </ol>
    </nav>

    <!-- Topic -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0"><?= View::e($topic['title']) ?></h1>
                <small class="text-muted">
                    <i class="bi bi-eye"></i> <?= $topic['views'] ?> weergaven
                </small>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center">
                    <div class="mb-2">
                        <i class="bi bi-person-circle fs-1"></i>
                    </div>
                    <div class="small">
                        <strong><?= View::e($topic['author_name']) ?></strong>
                    </div>
                    <div class="small text-muted">
                        <?= date('d-m-Y H:i', strtotime($topic['created_at'])) ?>
                    </div>
                </div>
                <div class="col-md-10">
                    <div class="topic-content">
                        <?= nl2br(View::e($topic['content'])) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reacties -->
    <h3 class="mb-3">Reacties (<?= count($replies) ?>)</h3>

    <?php if (empty($replies)): ?>
        <div class="alert alert-info">
            Nog geen reacties op dit topic.
        </div>
    <?php else: ?>
        <?php foreach ($replies as $reply): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <div class="mb-2">
                                <i class="bi bi-person-circle fs-1"></i>
                            </div>
                            <div class="small">
                                <strong><?= View::e($reply['username']) ?></strong>
                            </div>
                            <div class="small text-muted">
                                <?= date('d-m-Y H:i', strtotime($reply['created_at'])) ?>
                            </div>
                        </div>
                        <div class="col-md-10">
                            <div class="reply-content">
                                <?= nl2br(View::e($reply['content'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Reactie formulier -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h3 class="h5 mb-0">Reageren</h3>
            </div>
            <div class="card-body">
                <form action="/forum/reply" method="POST">
                    <?= View::csrfField() ?>
                    <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>">
                    <div class="mb-3">
                        <label for="content" class="form-label">Jouw reactie</label>
                        <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Reactie plaatsen</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <a href="/auth/login">Log in</a> om te kunnen reageren.
        </div>
    <?php endif; ?>
</div>
