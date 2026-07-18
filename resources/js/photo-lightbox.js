// Fullscreen foto-lightbox met zoom. Geregistreerd op de Alpine die Livewire
// meelevert (alpine:init vuurt vóór Alpine start).
//
// Zoom is bewust twee gescheiden paden op één element:
//   - Mobiel: native browser-pinch-zoom (touch raakt het transform NIET; touch
//     stuurt alleen swipe-navigatie in fit-stand).
//   - Desktop: een zelf-beheerde scale/translate-transform via muis + wiel.
document.addEventListener('alpine:init', () => {
    Alpine.data('photoLightbox', (photos) => ({
        photos,
        open: false,
        index: 0,
        scale: 1,
        tx: 0,
        ty: 0,
        trigger: null,   // thumbnail om focus aan terug te geven
        dragStart: null, // desktop pan
        swipeStart: null, // mobiel swipe

        show(i, event) {
            this.trigger = event?.currentTarget ?? null;
            this.index = i;
            this.resetZoom();
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.dialog?.focus());
        },

        close() {
            this.open = false;
            document.body.style.overflow = '';
            this.trigger?.focus();
        },

        get current() {
            return this.photos[this.index] ?? null;
        },

        get hasMultiple() {
            return this.photos.length > 1;
        },

        next() {
            this.index = (this.index + 1) % this.photos.length;
            this.resetZoom();
        },

        prev() {
            this.index = (this.index - 1 + this.photos.length) % this.photos.length;
            this.resetZoom();
        },

        resetZoom() {
            this.scale = 1;
            this.tx = 0;
            this.ty = 0;
        },

        // Desktop: scroll-wiel zoomt 1x–4x.
        onWheel(e) {
            e.preventDefault();
            this.scale = Math.min(4, Math.max(1, this.scale + (e.deltaY < 0 ? 0.25 : -0.25)));
            if (this.scale === 1) {
                this.tx = 0;
                this.ty = 0;
            }
        },

        // Desktop: dubbelklik schakelt fit <-> 2x.
        toggleZoom() {
            if (this.scale > 1) {
                this.resetZoom();
            } else {
                this.scale = 2;
            }
        },

        onPointerDown(e) {
            if (e.pointerType === 'touch') {
                this.swipeStart = { x: e.clientX };
                return;
            }
            if (this.scale > 1) {
                this.dragStart = { x: e.clientX - this.tx, y: e.clientY - this.ty };
            }
        },

        onPointerMove(e) {
            if (this.dragStart && e.pointerType !== 'touch') {
                this.tx = e.clientX - this.dragStart.x;
                this.ty = e.clientY - this.dragStart.y;
            }
        },

        onPointerUp(e) {
            // Eén-vinger swipe navigeert alleen in fit-stand; twee-vinger pinch
            // is native (visual viewport) en raakt dit niet.
            if (e.pointerType === 'touch' && this.swipeStart && this.scale === 1 && this.hasMultiple) {
                const dx = e.clientX - this.swipeStart.x;
                if (Math.abs(dx) > 50) {
                    dx < 0 ? this.next() : this.prev();
                }
            }
            this.swipeStart = null;
            this.dragStart = null;
        },

        onKey(e) {
            if (!this.open) return;
            if (e.key === 'Escape') this.close();
            else if (e.key === 'ArrowRight') this.next();
            else if (e.key === 'ArrowLeft') this.prev();
        },
    }));
});
