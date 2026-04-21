<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="mb-3">Welkom bij Cloudmarkplaats</h1>
                    <p class="lead">Lees onze voorwaarden en ons privacybeleid en bevestig dat je akkoord gaat om het platform te gebruiken.</p>

                    <hr>

                    <h2 class="h4 mt-4">Algemene Voorwaarden</h2>
                    <div class="border rounded p-3" style="max-height: 40vh; overflow-y: auto; white-space: pre-wrap;">
                        <?= View::e($tos['content'] ?? '') ?>
                    </div>

                    <h2 class="h4 mt-4">Privacybeleid</h2>
                    <div class="border rounded p-3" style="max-height: 40vh; overflow-y: auto; white-space: pre-wrap;">
                        <?= View::e($privacy['content'] ?? '') ?>
                    </div>

                    <form action="/legal/accept" method="POST" class="mt-4">
                        <?= View::csrfField() ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="accept" value="1" id="accept" required>
                            <label class="form-check-label" for="accept">
                                Ik heb de Algemene Voorwaarden (v<?= (int) ($tos['version'] ?? 0) ?>)
                                en het Privacybeleid (v<?= (int) ($privacy['version'] ?? 0) ?>) gelezen en ga akkoord.
                            </label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Accepteren en doorgaan</button>
                            <a href="/auth/logout" class="btn btn-outline-secondary">Uitloggen</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
