<?php use App\Core\View; ?>
<h6 class="mb-3">Recente Forum Topics</h6>
<?php if (empty($topics)): ?>
    <p class="text-muted">Nog geen topics gestart.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($topics as $topic): ?>
            <a href="/forum/topic?id=<?= $topic['id'] ?>"
               class="list-group-item list-group-item-action"
               hx-get="/forum/topic?id=<?= $topic['id'] ?>"
               hx-target="#main-content"
               hx-push-url="true">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?= View::e($topic['title']) ?></h6>
                    <small class="text-muted"><?= $topic['views'] ?> views</small>
                </div>
                <small class="text-muted">
                    <?= date('d-m-Y', strtotime($topic['created_at'])) ?>
                </small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
