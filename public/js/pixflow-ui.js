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

            root.innerHTML = `
                <div class="pp-lightbox-backdrop" data-pp-lightbox-close tabindex="-1"></div>
                <div class="pp-lightbox-panel" role="dialog" aria-modal="true" aria-label="Full size image">
                    <button type="button" class="pp-lightbox-close" data-pp-lightbox-close aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                    <div class="pp-lightbox-stage">
                        <img src="${escapeHtml(url)}" alt="${escapeHtml(alt)}" class="pp-lightbox-img">
                    </div>
                    ${caption ? `<p class="pp-lightbox-caption">${escapeHtml(caption)}</p>` : ''}
                    <p class="pp-lightbox-dimensions" data-pp-lightbox-dimensions></p>
                </div>`;

            const img = root.querySelector('.pp-lightbox-img');
            const dimEl = root.querySelector('[data-pp-lightbox-dimensions]');
            const setDimensions = () => {
                if (!img || !dimEl || !img.naturalWidth) return;
                dimEl.textContent = `${img.naturalWidth} × ${img.naturalHeight}px`;
            };
            if (img) {
                if (img.complete) setDimensions();
                else img.addEventListener('load', setDimensions);
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
                });
            });
        },

        init() {
            this.flashFromPage();
            this.bindConfirmForms();
            this.bindLightbox();
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
