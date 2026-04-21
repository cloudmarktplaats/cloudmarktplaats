<?php use App\Core\View; ?>
<div id="cookie-banner"
     class="position-fixed bottom-0 start-0 end-0 p-3 bg-dark text-light shadow-lg"
     style="z-index: 1050; display: none;"
     role="alert"
     aria-live="polite">
    <div class="container d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2">
        <div class="flex-grow-1 small">
            Deze site gebruikt uitsluitend strict-functionele cookies (sessie, beveiliging).
            Meer info in ons <a href="/legal/privacy" class="text-warning">privacybeleid</a>.
        </div>
        <button type="button"
                id="cookie-banner-dismiss"
                class="btn btn-warning btn-sm flex-shrink-0">
            Begrepen
        </button>
    </div>
</div>
<script src="/assets/js/cookie-banner.js" defer></script>
