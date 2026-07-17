(function () {
    'use strict';

    const ICONS = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info',
    };

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function normalizeOptions(input, defaults) {
        if (typeof input === 'string') {
            return { ...defaults, message: input };
        }
        return { ...defaults, ...input };
    }

    const PP = {
        toast(message, options = {}) {
            const opts = normalizeOptions(message, {
                type: 'info',
                title: null,
                duration: 5000,
            });
            if (typeof message === 'string') {
                opts.message = message;
            }

            const host = document.getElementById('ppToastHost');
            if (!host) return;

            const el = document.createElement('div');
            el.className = `pp-toast pp-toast-${opts.type}`;
            el.setAttribute('role', 'alert');
            el.innerHTML = `
                <span class="pp-toast-icon material-symbols-outlined">${ICONS[opts.type] || ICONS.info}</span>
                <div class="pp-toast-body">
                    ${opts.title ? `<p class="pp-toast-title">${escapeHtml(opts.title)}</p>` : ''}
                    <p class="pp-toast-message">${escapeHtml(opts.message)}</p>
                </div>
                <button type="button" class="pp-toast-close" aria-label="Dismiss">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>`;

            const remove = () => {
                el.classList.add('pp-toast-leave');
                setTimeout(() => el.remove(), 220);
            };

            el.querySelector('.pp-toast-close')?.addEventListener('click', remove);
            host.appendChild(el);
            requestAnimationFrame(() => el.classList.add('pp-toast-show'));

            if (opts.duration > 0) {
                setTimeout(remove, opts.duration);
            }
        },

        alert(options) {
            const opts = normalizeOptions(options, {
                title: 'Notice',
                message: '',
                confirmText: 'OK',
                type: 'info',
            });

            return new Promise((resolve) => {
                this._openDialog({
                    ...opts,
                    cancelText: null,
                    onConfirm: () => resolve(true),
                    onCancel: () => resolve(true),
                });
            });
        },

        confirm(options) {
            const opts = normalizeOptions(options, {
                title: 'Please confirm',
                message: 'Are you sure?',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                variant: 'default',
                type: 'warning',
            });

            return new Promise((resolve) => {
                this._openDialog({
                    ...opts,
                    onConfirm: () => resolve(true),
                    onCancel: () => resolve(false),
                });
            });
        },

        _openDialog(opts) {
            const root = document.getElementById('ppDialogRoot');
            if (!root) return;

            const variant = opts.variant === 'danger' ? 'danger' : 'default';
            const icon = ICONS[opts.type] || ICONS.warning;

            root.innerHTML = `
                <div class="pp-dialog-backdrop" data-pp-dialog-close></div>
                <div class="pp-dialog-panel pp-dialog-${variant}" role="dialog" aria-modal="true" aria-labelledby="ppDialogTitle">
                    <div class="pp-dialog-icon-wrap pp-dialog-icon-${opts.type || 'warning'}">
                        <span class="material-symbols-outlined">${icon}</span>
                    </div>
                    <h3 id="ppDialogTitle" class="pp-dialog-title">${escapeHtml(opts.title)}</h3>
                    <p class="pp-dialog-message">${escapeHtml(opts.message)}</p>
                    <div class="pp-dialog-actions">
                        ${opts.cancelText ? `<button type="button" class="pp-btn-secondary pp-dialog-cancel">${escapeHtml(opts.cancelText)}</button>` : ''}
                        <button type="button" class="pp-btn-primary pp-dialog-confirm ${variant === 'danger' ? 'pp-btn-danger' : ''}">${escapeHtml(opts.confirmText)}</button>
                    </div>
                </div>`;

            root.classList.remove('hidden');
            requestAnimationFrame(() => root.classList.add('is-open'));
            document.body.classList.add('pp-modal-open');

            const close = (result) => {
                root.classList.remove('is-open');
                document.body.classList.remove('pp-modal-open');
                setTimeout(() => {
                    root.classList.add('hidden');
                    root.innerHTML = '';
                }, 200);
                if (result) {
                    opts.onConfirm?.();
                } else {
                    opts.onCancel?.();
                }
            };

            root.querySelector('.pp-dialog-confirm')?.addEventListener('click', () => close(true));
            root.querySelector('.pp-dialog-cancel')?.addEventListener('click', () => close(false));
            root.querySelector('[data-pp-dialog-close]')?.addEventListener('click', () => close(false));

            const onKey = (e) => {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', onKey);
                    close(false);
                }
            };
            document.addEventListener('keydown', onKey);

            root.querySelector('.pp-dialog-confirm')?.focus();
        },

        flashFromPage() {
            const node = document.getElementById('ppFlashData');
            if (!node) return;

            try {
                const data = JSON.parse(node.textContent || '{}');
                if (data.status) this.toast(data.status, { type: 'success', title: 'Success' });
                if (data.error) this.toast(data.error, { type: 'error', title: 'Error', duration: 7000 });
                if (data.warning) this.toast(data.warning, { type: 'warning', title: 'Warning', duration: 6000 });
            } catch (_) {}
        },

        bindConfirmForms() {
            document.addEventListener('submit', async (event) => {
                const form = event.target.closest('form[data-pp-confirm]');
                if (!form || form.dataset.ppConfirmed === '1') {
                    return;
                }

                event.preventDefault();

                const message = form.dataset.ppConfirm || 'Are you sure?';
                const title = form.dataset.ppConfirmTitle || 'Please confirm';
                const variant = form.dataset.ppConfirmVariant || 'default';
                const confirmText = form.dataset.ppConfirmButton || 'Confirm';

                const ok = await this.confirm({
                    title,
                    message,
                    variant,
                    confirmText,
                    type: variant === 'danger' ? 'error' : 'warning',
                });

                if (ok) {
                    form.dataset.ppConfirmed = '1';
                    form.requestSubmit();
                }
            }, true);
        },

        openLightbox(url, options = {}) {
            const root = document.getElementById('ppLightboxRoot');
            if (!root || !url) return;

            const alt = options.alt || '';
            const caption = options.caption || '';
            const isVideo = options.type === 'video'
                || /\.(mp4|webm|ogg|mov)(\?|$)/i.test(url)
                || (typeof options.mime === 'string' && options.mime.startsWith('video/'));

            const mediaHtml = isVideo
                ? `<video src="${escapeHtml(url)}" class="pp-lightbox-video" controls autoplay playsinline preload="metadata"></video>`
                : `<img src="${escapeHtml(url)}" alt="${escapeHtml(alt)}" class="pp-lightbox-img">`;

            root.innerHTML = `
                <div class="pp-lightbox-backdrop" data-pp-lightbox-close tabindex="-1"></div>
                <div class="pp-lightbox-panel" role="dialog" aria-modal="true" aria-label="${isVideo ? 'Video player' : 'Full size image'}">
                    <button type="button" class="pp-lightbox-close" data-pp-lightbox-close aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                    <div class="pp-lightbox-stage">
                        ${mediaHtml}
                    </div>
                    ${caption ? `<p class="pp-lightbox-caption">${escapeHtml(caption)}</p>` : ''}
                    <p class="pp-lightbox-dimensions" data-pp-lightbox-dimensions></p>
                </div>`;

            const dimEl = root.querySelector('[data-pp-lightbox-dimensions]');
            if (isVideo) {
                const video = root.querySelector('.pp-lightbox-video');
                const setDimensions = () => {
                    if (!video || !dimEl || !video.videoWidth) return;
                    dimEl.textContent = `${video.videoWidth} × ${video.videoHeight}px`;
                };
                if (video) {
                    video.addEventListener('loadedmetadata', setDimensions);
                    video.addEventListener('error', () => {
                        if (dimEl) dimEl.textContent = 'This video could not be played in the browser.';
                    });
                }
            } else {
                const img = root.querySelector('.pp-lightbox-img');
                const setDimensions = () => {
                    if (!img || !dimEl || !img.naturalWidth) return;
                    dimEl.textContent = `${img.naturalWidth} × ${img.naturalHeight}px`;
                };
                if (img) {
                    if (img.complete) setDimensions();
                    else img.addEventListener('load', setDimensions);
                }
            }

            const close = () => this.closeLightbox();

            root.classList.remove('hidden');
            root.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(() => root.classList.add('is-open'));
            document.body.classList.add('pp-modal-open');

            root.querySelectorAll('[data-pp-lightbox-close]').forEach((el) => {
                el.addEventListener('click', close);
            });

            const onKey = (e) => {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', onKey);
                    close();
                }
            };
            document.addEventListener('keydown', onKey);

            root._ppLightboxKeyHandler = onKey;
            root.querySelector('.pp-lightbox-close')?.focus();
        },

        closeLightbox() {
            const root = document.getElementById('ppLightboxRoot');
            if (!root || root.classList.contains('hidden')) return;

            if (root._ppLightboxKeyHandler) {
                document.removeEventListener('keydown', root._ppLightboxKeyHandler);
                root._ppLightboxKeyHandler = null;
            }

            root.querySelectorAll('video').forEach((video) => {
                try {
                    video.pause();
                    video.removeAttribute('src');
                    video.load();
                } catch (e) { /* ignore */ }
            });

            root.classList.remove('is-open');
            root.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('pp-modal-open');

            setTimeout(() => {
                root.classList.add('hidden');
                root.innerHTML = '';
            }, 200);
        },

        bindLightbox() {
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-pp-lightbox]');
                if (!trigger) return;

                if (trigger.closest('a[href]:not([href="#"])') && !trigger.dataset.ppLightbox) {
                    return;
                }

                const url = trigger.dataset.ppLightbox
                    || (trigger.tagName === 'IMG' ? trigger.currentSrc || trigger.src : null);

                if (!url) return;

                event.preventDefault();
                event.stopPropagation();

                this.openLightbox(url, {
                    alt: trigger.dataset.ppLightboxAlt || trigger.getAttribute('alt') || '',
                    caption: trigger.dataset.ppLightboxCaption || '',
                    type: trigger.dataset.ppLightboxType || '',
                    mime: trigger.dataset.ppLightboxMime || '',
                });
            });
        },

        init() {
            this.flashFromPage();
            this.bindConfirmForms();
            this.bindLightbox();
            this.bindReveals();
            this.bindAutoMobilePanels();
            this.bindPaginationNav();
        },

        bindPaginationNav() {
            const appBase = (document.querySelector('meta[name="app-base"]')?.content || '').replace(/\/$/, '');

            const resolveAppUrl = (url) => {
                if (!url) return url;
                try {
                    const resolved = new URL(url, window.location.href);
                    if (appBase) {
                        const base = new URL(appBase);
                        if (resolved.origin === base.origin) {
                            const basePath = base.pathname.replace(/\/$/, '') || '';
                            if (basePath
                                && resolved.pathname !== basePath
                                && !resolved.pathname.startsWith(`${basePath}/`)) {
                                resolved.pathname = `${basePath}${resolved.pathname}`;
                            }
                        }
                    }
                    return resolved.toString();
                } catch (e) {
                    return url;
                }
            };

            const scrollToList = (list) => {
                if (!list) return;
                const topbar = document.querySelector('.pp-topbar');
                const offset = (topbar?.offsetHeight || 0) + 12;
                const y = list.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
            };

            const navigate = async (url, pagRoot, listTarget) => {
                const list = listTarget ? document.querySelector(listTarget) : null;
                const targetUrl = resolveAppUrl(url);

                if (!list || !pagRoot) {
                    window.location.href = targetUrl;
                    return;
                }

                pagRoot.classList.add('is-busy');

                try {
                    const res = await fetch(targetUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'text/html',
                        },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error('Pagination request failed');

                    const html = await res.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const incomingList = doc.querySelector(listTarget);
                    const incomingPag = doc.querySelector(`[data-pp-pagination][data-pp-list-target="${listTarget}"]`);

                    if (!incomingList) throw new Error('List not found in response');

                    list.replaceChildren(...Array.from(incomingList.children).map((child) => document.importNode(child, true)));

                    if (incomingPag) {
                        pagRoot.replaceWith(document.importNode(incomingPag, true));
                    }

                    if (window.history?.pushState) {
                        window.history.pushState({ ppPagination: true }, '', targetUrl);
                    }

                    scrollToList(list);
                    document.dispatchEvent(new CustomEvent('pp:list-updated'));
                    this.bindReveals();
                } catch (err) {
                    console.error(err);
                    window.location.href = targetUrl;
                }
            };

            document.addEventListener('click', (event) => {
                const link = event.target.closest('.pp-pagination-nav a');
                if (!link) return;

                const pagRoot = link.closest('[data-pp-pagination]');
                const listTarget = pagRoot?.getAttribute('data-pp-list-target');
                if (!pagRoot || !listTarget) return;

                event.preventDefault();
                navigate(link.href, pagRoot, listTarget);
            });

            document.addEventListener('change', (event) => {
                const select = event.target.closest('.pp-pagination-perpage select');
                if (!select) return;

                const form = select.closest('form');
                const pagRoot = select.closest('[data-pp-pagination]');
                const listTarget = pagRoot?.getAttribute('data-pp-list-target');
                if (!form || !pagRoot || !listTarget) return;

                event.preventDefault();

                const url = new URL(form.action || window.location.href, window.location.href);
                const params = new URLSearchParams(new FormData(form));
                url.search = params.toString();

                ['page', 'pending_page', 'reviewed_page'].forEach((key) => url.searchParams.delete(key));

                navigate(url.toString(), pagRoot, listTarget);
            });

            window.addEventListener('popstate', () => {
                window.location.reload();
            });
        },

        bindAutoMobilePanels() {
            const isDesktop = () => window.matchMedia('(min-width: 1280px)').matches;

            const activate = (page, panelId, { scroll = true } = {}) => {
                if (!page || !panelId) return;
                const radio = page.querySelector(`.pp-auto-panel-radio[value="${CSS.escape(panelId)}"]`);
                if (radio) {
                    radio.checked = true;
                }
                page.querySelectorAll('[data-pp-auto-panel-id]').forEach((panel) => {
                    panel.classList.toggle('is-open', panel.getAttribute('data-pp-auto-panel-id') === panelId);
                });
                page.querySelectorAll('.pp-auto-mbar-btn').forEach((btn) => {
                    const input = btn.querySelector('.pp-auto-panel-radio');
                    const active = input && input.value === panelId;
                    btn.classList.toggle('is-active', !!active);
                });
                if (scroll && !isDesktop()) {
                    page.querySelector(`[data-pp-auto-panel-id="${panelId}"]`)
                        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };

            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-pp-auto-panel]');
                if (!trigger) return;
                // Ignore radios themselves (label click already checks them)
                if (trigger.matches('.pp-auto-panel-radio')) return;
                const page = trigger.closest('.pp-automation-page');
                if (!page || isDesktop()) return;
                const panelId = trigger.getAttribute('data-pp-auto-panel');
                if (!panelId) return;
                e.preventDefault();
                activate(page, panelId);
            });

            document.addEventListener('change', (e) => {
                const radio = e.target.closest?.('.pp-auto-panel-radio');
                if (!radio) return;
                const page = radio.closest('.pp-automation-page');
                if (!page || isDesktop()) return;
                activate(page, radio.value, { scroll: true });
            });
        },

        bindReveals() {
            const nodes = Array.from(document.querySelectorAll('.pp-reveal:not(.is-visible)'));
            if (!nodes.length) return;

            const reveal = (el) => el.classList.add('is-visible');

            // Safari / iOS often fails to fire IO for above-the-fold opacity:0 nodes.
            const inView = (el) => {
                const r = el.getBoundingClientRect();
                const vh = window.innerHeight || document.documentElement.clientHeight || 0;
                const vw = window.innerWidth || document.documentElement.clientWidth || 0;
                return r.bottom > 0 && r.right > 0 && r.top < vh && r.left < vw;
            };

            nodes.filter(inView).forEach(reveal);

            const remaining = nodes.filter((el) => !el.classList.contains('is-visible'));
            if (!remaining.length) return;

            if (!('IntersectionObserver' in window)) {
                remaining.forEach(reveal);
                return;
            }

            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting && entry.intersectionRatio <= 0) return;
                    reveal(entry.target);
                    io.unobserve(entry.target);
                });
            }, { threshold: [0, 0.01, 0.1], rootMargin: '80px 0px 80px 0px' });

            remaining.forEach((el) => io.observe(el));

            // Fallback if Safari never reports intersections
            window.setTimeout(() => {
                remaining.forEach((el) => {
                    if (!el.classList.contains('is-visible')) reveal(el);
                });
            }, 600);
        },
    };

    window.PP = PP;
    window.PixFlow = PP;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => PP.init());
    } else {
        PP.init();
    }
})();
