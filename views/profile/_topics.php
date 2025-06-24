<h6 class="mb-3">Recente Forum Topics</h6>
<?php if (empty($topics)): ?>
    <p class="text-muted">Nog geen topics gestart.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($topics as $topic): ?>
            <a href="/forum/topic?id=<?php echo $topic['id']; ?>" 
               class="list-group-item list-group-item-action"
               hx-get="/forum/topic?id=<?php echo $topic['id']; ?>"
               hx-target="#main-content"
               hx-push-url="true">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?php echo htmlspecialchars($topic['title']); ?></h6>
                    <small class="text-muted"><?php echo $topic['views']; ?> views</small>
                </div>
                <small class="text-muted">
                    <?php echo date('d-m-Y', strtotime($topic['created_at'])); ?>
                </small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?> 