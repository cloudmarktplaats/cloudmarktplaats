<?php use App\Core\View; ?>
<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hardware Toevoegen</h5>
                </div>
                <div class="card-body">
                    <form action="/product/add" method="POST" enctype="multipart/form-data">
                        <?= View::csrfField() ?>
                        <!-- Naam -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Naam *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Geef een duidelijke naam voor je hardware.</div>
                        </div>

                        <!-- Categorie -->
                        <div class="mb-3">
                            <label for="category" class="form-label">Categorie *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Kies een categorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= View::e($category) ?>">
                                        <?= View::e($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Staat -->
                        <div class="mb-3">
                            <label for="state" class="form-label">Staat *</label>
                            <select class="form-select" id="state" name="state" required>
                                <option value="">Kies de staat</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?= View::e($state) ?>">
                                        <?= View::e($state) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Prijs -->
                        <div class="mb-3">
                            <label for="price" class="form-label">Prijs (€) *</label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="number" class="form-control" id="price" name="price"
                                       step="0.01" min="0" required>
                            </div>
                        </div>

                        <!-- Specificaties -->
                        <div class="mb-3">
                            <label for="specs" class="form-label">Specificaties *</label>
                            <textarea class="form-control" id="specs" name="specs" rows="3" required></textarea>
                            <div class="form-text">
                                Beschrijf de technische specificaties van je hardware.
                            </div>
                        </div>

                        <!-- Beschrijving -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Beschrijving</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            <div class="form-text">
                                Extra informatie over de hardware, gebruik, geschiedenis, etc.
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="tags" name="tags">
                            <div class="form-text">
                                Voeg tags toe gescheiden door komma's (max <?= \App\Core\Config::get('MAX_PRODUCT_TAGS', 5) ?>).
                                Bijvoorbeeld: server, dell, rackmount
                            </div>
                        </div>

                        <!-- Afbeeldingen -->
                        <div class="mb-3">
                            <label for="images" class="form-label">Afbeeldingen</label>
                            <input type="file" class="form-control" id="images" name="images[]"
                                   accept="image/*" multiple>
                            <div class="form-text">
                                Upload maximaal <?= \App\Core\Config::get('MAX_PRODUCT_IMAGES', 5) ?> afbeeldingen (JPG, PNG, GIF).
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Hardware Toevoegen
                            </button>
                            <a href="/dashboard" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Terug naar Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
