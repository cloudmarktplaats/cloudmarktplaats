<?php require_once 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h3 class="card-title"><?php echo htmlspecialchars($user['username']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="mb-0">
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Statistieken</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Producten
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['products']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Forum Topics
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['topics']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Forum Reacties
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['replies']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Favorieten
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['favorites']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recente Activiteit</h5>
                    <a href="/profile/edit" class="btn btn-primary btn-sm" 
                       hx-get="/profile/edit" 
                       hx-target="#main-content" 
                       hx-push-url="true">
                        <i class="fas fa-edit"></i> Profiel Bewerken
                    </a>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" 
                               hx-get="/profile/products" 
                               hx-target="#activity-content"
                               hx-trigger="click"
                               href="#products" 
                               role="tab">
                                Producten
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               hx-get="/profile/topics" 
                               hx-target="#activity-content"
                               hx-trigger="click"
                               href="#topics" 
                               role="tab">
                                Forum Topics
                            </a>
                        </li>
                    </ul>
                    
                    <div id="activity-content" hx-get="/profile/products" hx-trigger="load">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Laden...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 