<?php use App\Core\View; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="mb-0">Nieuwe Categorie</h2>
                </div>
                <div class="card-body">
                    <form action="/forum/new_category" method="POST">
                        <?= View::csrfField() ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Naam *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Geef een duidelijke naam voor de categorie.</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Beschrijving</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            <div class="form-text">Beschrijf het doel van deze categorie.</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/forum" class="btn btn-secondary">Terug naar Forum</a>
                            <button type="submit" class="btn btn-primary">Categorie Aanmaken</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
