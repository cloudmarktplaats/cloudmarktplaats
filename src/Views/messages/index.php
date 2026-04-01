<?php use App\Core\View; ?>
<div class="container">
    <div class="row">
        <!-- Gesprekken lijst -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Gesprekken</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($conversations as $conv): ?>
                        <a href="/message/conversation/<?= $conv['other_user_id'] ?>"
                           class="list-group-item list-group-item-action <?= $selected_user_id == $conv['other_user_id'] ? 'active' : '' ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= View::e($conv['other_username']) ?></h6>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="badge bg-danger"><?= $conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-1 text-truncate">
                                <?= View::e($conv['last_message']) ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Berichten weergave -->
        <div class="col-md-8">
            <?php if ($selected_user_id && $other_user): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Gesprek met <?= View::e($other_user['username']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="messages-container" style="height: 400px; overflow-y: auto;">
                            <?php foreach ($messages as $message): ?>
                                <div class="message <?= $message['sender_id'] == $_SESSION['user_id'] ? 'text-end' : '' ?> mb-3">
                                    <div class="message-content d-inline-block p-3 rounded <?= $message['sender_id'] == $_SESSION['user_id'] ? 'bg-primary text-white' : 'bg-light' ?>" style="max-width: 70%;">
                                        <p class="mb-0"><?= nl2br(View::e($message['message'])) ?></p>
                                        <small class="text-muted">
                                            <?= date('d-m-Y H:i', strtotime($message['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Bericht versturen formulier -->
                        <form method="POST" action="/message/send" class="mt-3">
                            <?= View::csrfField() ?>
                            <input type="hidden" name="receiver_id" value="<?= $selected_user_id ?>">
                            <div class="input-group">
                                <select name="product_id" class="form-select" style="max-width: 200px;">
                                    <option value="">Selecteer product (optioneel)</option>
                                    <?php foreach ($user_products as $product): ?>
                                        <option value="<?= $product['id'] ?>">
                                            <?= View::e($product['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="message" class="form-control" placeholder="Type je bericht..." required>
                                <button type="submit" class="btn btn-primary">Versturen</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center">
                        <p class="mb-0">Selecteer een gesprek om berichten te bekijken.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Scroll naar laatste bericht
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
});
</script>
