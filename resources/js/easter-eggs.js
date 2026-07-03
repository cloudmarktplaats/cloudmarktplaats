/*
 * Easter eggs — tasteful, on-brand, and never in the way.
 *
 *  1. A console note with ASCII art + a donation nudge (dev audience opens
 *     devtools; this is where an open-source project says hi).
 *  2. A subtle "matrix" glyph-rain behind any element carrying
 *     [data-matrix-rain] (used only on the 404 page). Faint, ink-coloured,
 *     paused when the tab is hidden, disabled under prefers-reduced-motion.
 *  3. The Konami code (↑↑↓↓←→←→ B A) turns the whole screen into a green
 *     matrix rain with an ASCII donation card. Hidden until you find it.
 */

const SPONSOR = 'https://github.com/sponsors/NickAldewereld';
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const GLYPHS = 'アカサタナハマヤラワ0123456789<>[]{}/\\|=+*·:.'.split('');

function consoleEgg() {
    const art = [
        '     .-~~~-.',
        '  .-~  cmp  ~-.     cloudmarktplaats.nl',
        ' (   o     o   )    open source · AGPL-3.0',
        "  `-.__~~~__.-'     geen trackers, wel kabels",
    ].join('\n');
    console.log('%c' + art, 'color:#D9480F;font-family:monospace;font-size:12px;line-height:1.25');
    console.log(
        "%cDraait dit ding op jouw homelab? Mooi. Hou 'm draaiend →%c " + SPONSOR,
        'color:#5A6167;font-family:monospace',
        'color:#1447CC;font-family:monospace'
    );
    console.log('%cPsst: probeer ↑↑↓↓←→←→ B A', 'color:#9AA1A6;font-family:monospace;font-size:11px');
}

/**
 * Attach a glyph-rain canvas to `host`. Returns a stop() function.
 * `color` + `alpha` keep it a faint background nod (404) or a loud
 * green takeover (Konami).
 */
function attachRain(host, { color = '#17191B', alpha = 0.06, fade = 0.08, speed = 1 } = {}) {
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none';
    host.appendChild(canvas);
    const ctx = canvas.getContext('2d');

    let cols = [];
    const font = 16;
    function resize() {
        canvas.width = host.clientWidth;
        canvas.height = host.clientHeight;
        cols = new Array(Math.ceil(canvas.width / font)).fill(0).map(
            () => Math.floor((Math.random() * canvas.height) / font)
        );
    }
    resize();
    const onResize = () => resize();
    window.addEventListener('resize', onResize);

    let raf = null;
    let running = true;
    function frame() {
        ctx.fillStyle = `rgba(245,246,246,${fade})`; // cmp-bg, trails fade out
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.font = `${font}px 'IBM Plex Mono', monospace`;
        for (let i = 0; i < cols.length; i++) {
            const g = GLYPHS[(Math.random() * GLYPHS.length) | 0];
            ctx.fillStyle = `rgba(${hexToRgb(color)},${alpha})`;
            ctx.fillText(g, i * font, cols[i] * font);
            if (cols[i] * font > canvas.height && Math.random() > 0.975) cols[i] = 0;
            cols[i] += speed;
        }
        if (running) raf = requestAnimationFrame(frame);
    }
    frame();

    const onVisibility = () => {
        running = !document.hidden;
        if (running && !raf) frame();
        if (!running && raf) {
            cancelAnimationFrame(raf);
            raf = null;
        }
    };
    document.addEventListener('visibilitychange', onVisibility);

    return function stop() {
        running = false;
        if (raf) cancelAnimationFrame(raf);
        window.removeEventListener('resize', onResize);
        document.removeEventListener('visibilitychange', onVisibility);
        canvas.remove();
    };
}

function hexToRgb(hex) {
    const n = parseInt(hex.replace('#', ''), 16);
    return `${(n >> 16) & 255},${(n >> 8) & 255},${n & 255}`;
}

function subtleRain() {
    if (reduceMotion) return;
    document.querySelectorAll('[data-matrix-rain]').forEach((host) => {
        host.style.position = host.style.position || 'absolute';
        attachRain(host, { color: '#17191B', alpha: 0.05, fade: 0.06, speed: 1 });
    });
}

function konami() {
    const seq = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
    let i = 0;
    let open = false;
    window.addEventListener('keydown', (e) => {
        if (open) return;
        const k = e.key.length === 1 ? e.key.toLowerCase() : e.key;
        i = k === seq[i] ? i + 1 : (k === seq[0] ? 1 : 0);
        if (i === seq.length) {
            i = 0;
            openTakeover();
        }
    });

    function openTakeover() {
        open = true;
        const overlay = document.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', 'Easter egg');
        overlay.style.cssText =
            'position:fixed;inset:0;z-index:9999;background:#05070a;overflow:hidden;' +
            'display:flex;align-items:center;justify-content:center;cursor:pointer';
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        let stop = () => {};
        if (!reduceMotion) {
            const rainHost = document.createElement('div');
            rainHost.style.cssText = 'position:absolute;inset:0';
            overlay.appendChild(rainHost);
            stop = attachRain(rainHost, { color: '#22c55e', alpha: 0.85, fade: 0.06, speed: 1.4 });
        }

        const card = document.createElement('div');
        card.style.cssText =
            'position:relative;z-index:1;max-width:420px;margin:0 20px;padding:24px;' +
            'border:2px solid #22c55e;background:rgba(5,7,10,.85);color:#22c55e;' +
            "font-family:'IBM Plex Mono',monospace;text-align:center;cursor:auto";
        card.innerHTML =
            '<pre style="margin:0 0 12px;font-size:12px;line-height:1.2">' +
            '  __\n' +
            ' /  \\__   je vond de\n' +
            " (      )  easter egg\n" +
            " \\__/\\_/   .-.-.-.-.\n" +
            '</pre>' +
            '<p style="margin:0 0 16px;color:#a7f3d0;font-size:13px">Deze marktplaats is gratis en open source. ' +
            'Als je ’m waardeert: een donatie houdt de servers warm.</p>' +
            '<a href="' + SPONSOR + '" target="_blank" rel="noopener" ' +
            'style="display:inline-block;border:1px solid #22c55e;color:#05070a;background:#22c55e;' +
            'padding:8px 16px;text-decoration:none;font-weight:600">♥ Sponsor cloudmarktplaats</a>' +
            '<p style="margin:14px 0 0;color:#4b5563;font-size:11px">klik ergens of Esc om te sluiten</p>';
        overlay.appendChild(card);

        function close() {
            stop();
            overlay.remove();
            document.body.style.overflow = '';
            open = false;
            document.removeEventListener('keydown', onEsc);
        }
        function onEsc(e) {
            if (e.key === 'Escape') close();
        }
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target === card.querySelector('pre')) close();
        });
        document.addEventListener('keydown', onEsc);
    }
}

function init() {
    try {
        consoleEgg();
        subtleRain();
        konami();
    } catch (_) {
        /* never let an easter egg break the page */
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
