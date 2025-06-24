<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/forum">Forum</a></li>
            <li class="breadcrumb-item">
                <a href="/forum/category?id=<?= $category['id'] ?>">
                    <?= htmlspecialchars($category['name']) ?>
                </a>
            </li>
            <li class="breadcrumb-item active">Nieuw Topic</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header bg-light">
            <h1 class="h3 mb-0">Nieuw Topic in <?= htmlspecialchars($category['name']) ?></h1>
        </div>
        <div class="card-body">
            <form action="/forum/new_topic?category_id=<?= $category['id'] ?>" method="POST">
                <div class="mb-3">
                    <label for="title" class="form-label">Titel</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="mb-3">
                    <label for="content" class="form-label">Inhoud</label>
                    <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Topic aanmaken</button>
                    <a href="/forum/category?id=<?= $category['id'] ?>" class="btn btn-secondary">Annuleren</a>
                </div>
            </form>
        </div>
    </div>
</div> 