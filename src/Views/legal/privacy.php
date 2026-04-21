<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <article class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <nav class="mb-3">
                        <a href="/legal/privacy?lang=nl" class="me-3">Nederlands</a>
                        <a href="/legal/privacy?lang=en">English</a>
                    </nav>
                    <div class="legal-content" style="white-space: pre-wrap; font-family: inherit;">
                        <?= View::e($document['content'] ?? '') ?>
                    </div>
                    <p class="text-muted mt-4 mb-0">
                        Versie <?= (int) ($document['version'] ?? 0) ?>
                        — gepubliceerd <?= View::e($document['published_at'] ?? '') ?>
                    </p>
                </div>
            </article>
        </div>
    </div>
</div>
