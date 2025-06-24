<div class="row mb-4">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-body">
                <h1 class="text-center mb-4">Welkom bij Cloudmarkplaats.nl</h1>
                <form action="/products/search" method="GET" class="mb-4">
                    <div class="input-group">
                        <input type="text" 
                               name="q" 
                               class="form-control" 
                               placeholder="Zoek naar hardware..."
                               hx-get="/products/search"
                               hx-trigger="keyup changed delay:500ms"
                               hx-target="#search-results">
                        <button class="btn btn-primary" type="submit">Zoeken</button>
                    </div>
                </form>
                <div id="search-results"></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Recente Producten</h2>
        <div class="row">
            <?php foreach ($recentProducts as $product): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?= htmlspecialchars($product['image']) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                            <p class="card-text">
                                <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...
                            </p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Verkoper: <?= htmlspecialchars($product['username']) ?>
                                </small>
                            </p>
                            <a href="/products/<?= $product['id'] ?>" class="btn btn-primary">
                                Bekijk Product
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h3>Populaire Tags</h3>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($popularTags as $tag): ?>
                        <a href="/products/tag/<?= urlencode($tag['tag']) ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <?= htmlspecialchars($tag['tag']) ?> 
                            <span class="badge bg-secondary"><?= $tag['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div> 