<!-- Hero Section -->
<section class="hero-section-home d-flex align-items-center position-relative">
    <div class="container position-relative z-2">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="display-3 fw-bold mb-3 text-white">Hardware Trading for IT Professionals</h1>
                <p class="lead text-white-50 mb-4">Connect with specialists and find datacenter equipment from experts who understand the value.</p>
                <div class="d-flex gap-3 mb-4">
                    <a href="/auth/register" class="btn btn-lg btn-cta me-2">Join Now</a>
                    <a href="/products" class="btn btn-lg btn-outline-light">Browse Hardware</a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <img src="/assets/img/server-hero.png" alt="Server Hardware" class="img-fluid hero-img">
            </div>
        </div>
    </div>
    <div class="hero-skew"></div>
</section>

<!-- Categorieën sectie -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">Browse by Category</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php
            // Vaste lijst met categorieën en iconen
            $all_categories = [
                ["name" => "Servers", "icon" => "bi-database"],
                ["name" => "Networking", "icon" => "bi-hdd-network"],
                ["name" => "Storage", "icon" => "bi-hdd"],
                ["name" => "Components", "icon" => "bi-cpu"],
                ["name" => "Workstations", "icon" => "bi-pc"],
                ["name" => "Software", "icon" => "bi-box"],
                ["name" => "Peripherals", "icon" => "bi-keyboard"],
                ["name" => "Other", "icon" => "bi-three-dots"],
            ];
            // Maak een map van de database-tellingen
            $counts = [];
            if (!empty($popular_categories)) {
                foreach ($popular_categories as $cat) {
                    $counts[$cat['category']] = $cat['product_count'];
                }
            }
            ?>
            <?php foreach ($all_categories as $cat): ?>
                <div class="col">
                    <a href="/products?category=<?= urlencode($cat['name']) ?>" class="text-decoration-none">
                        <div class="category-card h-100">
                            <span class="category-icon">
                                <i class="bi <?= $cat['icon'] ?>"></i>
                            </span>
                            <div class="category-title">
                                <?= htmlspecialchars($cat['name']) ?>
                            </div>
                            <div class="category-listings">
                                <?= isset($counts[$cat['name']]) ? number_format($counts[$cat['name']]) : '0' ?> listings
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Listings Section -->
<section class="featured-listings-section py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Featured Listings</h2>
            <a href="/products" class="btn btn-outline-primary btn-lg px-4">View All</a>
        </div>
        <div class="row g-4">
            <!-- Card 1 -->
            <div class="col-md-4">
                <div class="featured-card h-100">
                    <div class="featured-img-wrap">
                        <img src="/assets/img/dell-r740.jpg" alt="Dell PowerEdge R740" class="featured-img">
                        <span class="badge badge-featured">Featured</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                        <span class="badge badge-category">Server</span>
                        <span class="badge badge-status">Used - Like New</span>
                    </div>
                    <h4 class="fw-bold mb-1">Dell PowerEdge R740 Server</h4>
                    <div class="mb-2 text-muted">2x Intel Xeon Gold 6130, 64GB RAM, 4x 1.2TB SAS</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-4 fw-bold">€2,450</span>
                        <a href="#" class="btn btn-cta">View Details</a>
                    </div>
                    <div class="d-flex align-items-center mt-3">
                        <img src="/assets/img/avatar1.png" alt="DataCenterPro" class="seller-avatar me-2">
                        <span class="text-muted small">Listed by <span class="text-turquoise">DataCenterPro <i class="bi bi-patch-check-fill"></i></span></span>
                    </div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-md-4">
                <div class="featured-card h-100">
                    <div class="featured-img-wrap">
                        <img src="/assets/img/cisco-9300.jpg" alt="Cisco Catalyst Switch" class="featured-img">
                        <span class="badge badge-featured">Featured</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                        <span class="badge badge-category">Networking</span>
                        <span class="badge badge-status">Used</span>
                    </div>
                    <h4 class="fw-bold mb-1">Cisco Catalyst 9300 Switch</h4>
                    <div class="mb-2 text-muted">48-port Gigabit, PoE+, 4x 10G SFP+, Network Advantage</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-4 fw-bold">€1,875</span>
                        <a href="#" class="btn btn-cta">View Details</a>
                    </div>
                    <div class="d-flex align-items-center mt-3">
                        <img src="/assets/img/avatar2.png" alt="NetworkGear" class="seller-avatar me-2">
                        <span class="text-muted small">Listed by <span class="text-turquoise">NetworkGear <i class="bi bi-patch-check-fill"></i></span></span>
                    </div>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="col-md-4">
                <div class="featured-card h-100">
                    <div class="featured-img-wrap">
                        <img src="/assets/img/netapp-fas2750.jpg" alt="NetApp Storage Array" class="featured-img">
                        <span class="badge badge-new">New</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                        <span class="badge badge-category">Storage</span>
                        <span class="badge badge-status">Used</span>
                    </div>
                    <h4 class="fw-bold mb-1">NetApp FAS2750 Storage Array</h4>
                    <div class="mb-2 text-muted">24x 1.8TB 10K SAS, Dual Controllers, 10GbE</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-4 fw-bold">€7,200</span>
                        <a href="#" class="btn btn-cta">View Details</a>
                    </div>
                    <div class="d-flex align-items-center mt-3">
                        <img src="/assets/img/avatar3.png" alt="StorageSolutions" class="seller-avatar me-2">
                        <span class="text-muted small">Listed by <span class="text-turquoise">StorageSolutions <i class="bi bi-patch-check-fill"></i></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Recente hardware -->
    <h2 class="mb-4">Recente Hardware</h2>
    <div class="product-grid mb-5">
        <?php if (empty($recent_products)): ?>
            <div class="alert alert-info">
                Er is nog geen hardware beschikbaar.
            </div>
        <?php else: ?>
            <?php foreach ($recent_products as $product): ?>
                <div class="card h-100 product-card">
                    <?php if ($product['image']): ?>
                        <img src="<?= htmlspecialchars($product['image']) ?>"
                             class="card-img-top product-image"
                             alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                        <p class="card-text text-muted">
                            <small>
                                <i class="bi bi-tag"></i> <?= htmlspecialchars($product['category']) ?>
                                <br>
                                <i class="bi bi-circle"></i> <?= htmlspecialchars($product['state']) ?>
                            </small>
                        </p>
                        <p class="card-text"><?= htmlspecialchars($product['specs']) ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h5 mb-0">€<?= number_format($product['price'], 2) ?></span>
                            <a href="/product/view?id=<?= $product['id'] ?>" class="btn btn-primary">
                                Details
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <small class="text-muted">
                            Geplaatst door <?= htmlspecialchars($product['username']) ?> op
                            <?= date('d-m-Y', strtotime($product['created_at'])) ?>
                        </small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Tech Feeds -->
    <h2 class="mb-4 mt-5">Tech Feeds</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Laatste Tech Nieuws</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <!-- Hier komen de RSS feeds -->
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Voorbeeld Tech Nieuws</h6>
                                <small class="text-muted">3 dagen geleden</small>
                            </div>
                            <p class="mb-1">Korte beschrijving van het nieuws...</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Forum Discussies</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <!-- Hier komen de forum topics -->
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Voorbeeld Discussie</h6>
                                <small class="text-muted">5 reacties</small>
                            </div>
                            <p class="mb-1">Laatste reactie door gebruiker...</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 