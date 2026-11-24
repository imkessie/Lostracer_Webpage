/**
 * Lostracer UPH — Client-side interactivity module.
 * Handles live filtered feeds, encrypted real-time chat, route progress bar,
 * scroll chrome, button ripples, upload dropzones, post completion progress,
 * suggested hashtags, password strength, clipboard copy, lightbox zoom, and modals.
 */
(function () {
    'use strict';

    const state = {
        lastFocusedElement: null,
        pendingConfirmForm: null,
        lightboxPrevFocus: null,
        feedAbortController: null,
        isSubmittingForm: false,
    };

    const prefersReducedMotion = () =>
        window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeSelectorValue(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(String(value));
        }
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    /* =========================================================
       Top Progress Bar
       ========================================================= */
    const progressBar = {
        timer: null,
        start() {
            const el = document.getElementById('lt-progress');
            if (!el) return;
            window.clearTimeout(this.timer);
            el.style.width = '22%';
            el.classList.add('is-active');
            window.requestAnimationFrame(() => {
                if (el.classList.contains('is-active')) {
                    el.style.width = '68%';
                }
            });
        },
        done() {
            const el = document.getElementById('lt-progress');
            if (!el) return;
            el.style.width = '100%';
            window.clearTimeout(this.timer);
            this.timer = window.setTimeout(() => {
                el.classList.remove('is-active');
                window.setTimeout(() => {
                    if (!el.classList.contains('is-active')) {
                        el.style.width = '0%';
                    }
                }, 200);
            }, 160);
        },
    };

    /* =========================================================
       Toast Notifications & Flash Alerts
       ========================================================= */
    function ensureToastStack() {
        let stack = document.getElementById('toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'toast-stack';
            stack.className = 'toast-stack no-print';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'false');
            document.body.appendChild(stack);
        }
        return stack;
    }

    /**
     * Display a floating notification toast.
     * @param {string} message
     * @param {'info'|'success'|'error'} [variant]
     * @param {number} [duration]
     */
    function toast(message, variant = 'info', duration = 3600) {
        if (!message) return;
        const stack = ensureToastStack();
        const item = document.createElement('div');
        item.className = 'toast toast-' + variant;
        item.setAttribute('role', variant === 'error' ? 'alert' : 'status');

        const dot = document.createElement('span');
        dot.className = 'toast-dot';

        const text = document.createElement('span');
        text.className = 'toast-text';
        text.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'toast-close';
        closeBtn.setAttribute('aria-label', 'Dismiss notification');
        closeBtn.textContent = '\u00d7';

        const dismiss = () => {
            if (!item.parentElement) return;
            item.classList.add('is-leaving');
            window.setTimeout(() => item.remove(), 180);
        };

        closeBtn.addEventListener('click', dismiss);
        item.append(dot, text, closeBtn);
        stack.appendChild(item);

        if (duration > 0) {
            window.setTimeout(dismiss, duration);
        }
    }

    function dismissAlertElement(alertEl) {
        if (!alertEl || alertEl.dataset.dismissing === 'true') return;
        alertEl.dataset.dismissing = 'true';
        alertEl.style.transition = 'opacity 180ms ease, transform 180ms ease';
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateY(-4px)';
        window.setTimeout(() => alertEl.remove(), 180);
    }

    function initAlerts() {
        document.addEventListener('click', (event) => {
            const closeBtn = event.target.closest('[data-alert-close]');
            if (!closeBtn) return;
            dismissAlertElement(closeBtn.closest('.alert'));
        });

        document.querySelectorAll('.alert[data-auto-dismiss]').forEach((alertBox) => {
            const ms = parseInt(alertBox.dataset.autoDismiss || '5500', 10);
            if (ms > 0) {
                window.setTimeout(() => dismissAlertElement(alertBox), ms);
            }
        });
    }

    /* =========================================================
       Scroll Chrome (Topbar Elevation & Floating Back-to-Top)
       ========================================================= */
    function initScrollChrome() {
        const topbar = document.querySelector('nav.topbar');
        const backBtn = document.getElementById('back-to-top-btn');

        const syncScroll = () => {
            const y = window.scrollY || window.pageYOffset || 0;
            if (topbar) topbar.classList.toggle('is-scrolled', y > 10);
            if (backBtn) backBtn.classList.toggle('is-visible', y > 360);
        };

        window.addEventListener('scroll', syncScroll, { passive: true });
        syncScroll();

        if (backBtn) {
            backBtn.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                });
            });
        }
    }

    /* =========================================================
       Interactive Button Ripples
       ========================================================= */
    function initButtonRipples() {
        if (prefersReducedMotion()) return;

        document.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) return;
            const target = event.target.closest('.btn, a.hero-stat');
            if (!target || target.disabled) return;

            const rect = target.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const ripple = document.createElement('span');
            ripple.className = 'btn-ripple';
            ripple.style.width = size + 'px';
            ripple.style.height = size + 'px';
            ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';

            target.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
            window.setTimeout(() => ripple.remove(), 600);
        });
    }

    /* =========================================================
       Navigation & Mobile Menu
       ========================================================= */
    function initNavigation() {
        const toggleBtn = document.getElementById('nav-toggle-btn');
        const navMenu = document.getElementById('nav-menu');
        if (!toggleBtn || !navMenu) return;

        const setMenuOpen = (open) => {
            navMenu.classList.toggle('is-open', open);
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        toggleBtn.setAttribute('aria-expanded', 'false');
        toggleBtn.addEventListener('click', () => {
            setMenuOpen(!navMenu.classList.contains('is-open'));
        });

        document.addEventListener('click', (event) => {
            if (!navMenu.classList.contains('is-open')) return;
            const topbar = toggleBtn.closest('nav.topbar');
            if (topbar && !topbar.contains(event.target)) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && navMenu.classList.contains('is-open')) {
                setMenuOpen(false);
                toggleBtn.focus();
            }
        });
    }

    /* =========================================================
       Global Confirmation Modal
       ========================================================= */
    function closeConfirmModal() {
        const modal = document.getElementById('confirm-modal');
        const submitBtn = document.getElementById('confirm-modal-submit');
        if (!modal) return;
        modal.classList.remove('is-open');
        if (submitBtn) submitBtn.disabled = false;
        state.pendingConfirmForm = null;
        if (state.lastFocusedElement && typeof state.lastFocusedElement.focus === 'function') {
            state.lastFocusedElement.focus();
            state.lastFocusedElement = null;
        }
    }

    function openConfirmModal(form) {
        const modal = document.getElementById('confirm-modal');
        const titleEl = document.getElementById('confirm-modal-title');
        const msgEl = document.getElementById('confirm-modal-message');
        const submitBtn = document.getElementById('confirm-modal-submit');
        const cancelBtn = document.getElementById('confirm-modal-cancel');
        if (!modal || !titleEl || !msgEl || !submitBtn) return;

        state.lastFocusedElement = document.activeElement;
        state.pendingConfirmForm = form;
        titleEl.textContent = form.dataset.confirmTitle || 'Confirm Action';
        msgEl.textContent = form.dataset.confirm || 'Are you sure you want to continue?';
        submitBtn.className = 'btn ' + (form.dataset.confirmVariant === 'danger' ? 'btn-danger' : 'btn-primary');
        submitBtn.textContent = form.dataset.confirmLabel || 'Continue';
        submitBtn.disabled = false;

        modal.classList.add('is-open');
        (cancelBtn || submitBtn).focus();
    }

    function initConfirmModal() {
        const modal = document.getElementById('confirm-modal');
        const cancelBtn = document.getElementById('confirm-modal-cancel');
        const submitBtn = document.getElementById('confirm-modal-submit');
        if (!modal) return;

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
            if (form.dataset.confirmed === 'true') return;
            if (!form.checkValidity()) return;

            event.preventDefault();
            openConfirmModal(form);
        });

        if (cancelBtn) cancelBtn.addEventListener('click', closeConfirmModal);

        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeConfirmModal();
        });

        if (submitBtn) {
            submitBtn.addEventListener('click', () => {
                if (!state.pendingConfirmForm) return;
                const form = state.pendingConfirmForm;
                form.dataset.confirmed = 'true';
                state.isSubmittingForm = true;
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                progressBar.start();
                form.submit();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeConfirmModal();
            }
        });
    }

    /* =========================================================
       Form Submission & Verification Guards
       ========================================================= */
    function initFormGuards() {
        const unlockButton = (btn) => {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            delete btn.dataset.lockedSubmit;
        };

        const unlockForm = (form) => {
            if (!form) return;
            delete form.dataset.submitting;
            form.querySelectorAll('button[data-locked-submit="true"], input[data-locked-submit="true"]').forEach(unlockButton);
        };

        const restoreSubmitButtons = () => {
            document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
                delete form.dataset.submitting;
            });
            document.querySelectorAll('form button[data-locked-submit="true"], form input[data-locked-submit="true"]').forEach(unlockButton);
        };

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                state.isSubmittingForm = false;
                restoreSubmitButtons();
                progressBar.done();
            }
        });

        document.addEventListener('invalid', (event) => {
            const form = event.target && event.target.form;
            if (!form) return;
            unlockForm(form);
        }, true);

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.method.toUpperCase() !== 'POST') return;
            if (form.dataset.confirm || form.hasAttribute('data-chat-form') || event.defaultPrevented) return;

            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            state.isSubmittingForm = true;
            progressBar.start();
            const submitBtn = event.submitter || form.querySelector('button[type="submit"]:not([name])');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.classList.add('is-loading');
                submitBtn.dataset.lockedSubmit = 'true';
            }

            // Defer disabling until after form data serialization so named submitters (e.g. decision=approve) are still submitted.
            window.setTimeout(() => {
                if (form.dataset.submitting !== 'true') return;
                form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
                    if (!btn.disabled) {
                        btn.disabled = true;
                        btn.dataset.lockedSubmit = 'true';
                    }
                });
            }, 0);
        });

        // Require verification notes when Security selects Reject Claim + interactive hover tint
        document.querySelectorAll('form[data-verify-form]').forEach((form) => {
            const notesInput = form.querySelector('input[name="verification_notes"]');
            const card = form.closest('.claim-queue-card');

            form.querySelectorAll('button[name="decision"]').forEach((btn) => {
                const cls = btn.value === 'approve' ? 'is-approving' : 'is-rejecting';
                btn.addEventListener('mouseenter', () => card && card.classList.add(cls));
                btn.addEventListener('mouseleave', () => card && card.classList.remove('is-approving', 'is-rejecting'));
            });

            if (!notesInput) return;
            notesInput.addEventListener('input', () => notesInput.setCustomValidity(''));
            notesInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    notesInput.setCustomValidity('');
                }
            });

            form.addEventListener('click', (event) => {
                const btn = event.target.closest('button[name="decision"]');
                if (!btn) return;
                if (btn.value === 'approve') {
                    notesInput.setCustomValidity('');
                } else if (btn.value === 'reject' && notesInput.value.trim() === '') {
                    notesInput.setCustomValidity('Please provide a rejection reason so the claimant can review it.');
                    notesInput.reportValidity();
                    notesInput.focus();
                    event.preventDefault();
                }
            });
        });

        // Uppercase + trim helper for pickup codes
        document.querySelectorAll('[data-uppercase-trim]').forEach((input) => {
            input.addEventListener('input', () => {
                const pos = input.selectionStart;
                const cleaned = input.value.toUpperCase().replace(/\s+/g, '');
                if (input.value !== cleaned) {
                    input.value = cleaned;
                    if (pos !== null) input.setSelectionRange(pos, pos);
                }
            });
        });
    }

    /* =========================================================
       Auth Controls (Spotlight, Password Toggle, Strength & UPH Email Hint)
       ========================================================= */
    function evaluatePasswordStrength(pw) {
        if (!pw) return { level: 0, label: '' };
        let score = 0;
        if (pw.length >= 6) score += 1;
        if (pw.length >= 10 || /[0-9]/.test(pw)) score += 1;
        if (/[A-Z]/.test(pw) && /[^A-Za-z0-9]/.test(pw)) score += 1;
        else if (/[a-z]/.test(pw) && /[A-Z]/.test(pw) && /[0-9]/.test(pw)) score = 3;

        if (pw.length < 6) score = 1;
        const labels = { 1: 'Weak — add more characters or numbers', 2: 'Fair — mix uppercase or symbols', 3: 'Strong password' };
        return { level: Math.min(3, Math.max(1, score)), label: labels[Math.min(3, Math.max(1, score))] };
    }

    function initAuthControls() {
        // Subtle mouse-tracking spotlight on login/register cobalt panel
        const authVisual = document.querySelector('.auth-visual');
        if (authVisual && !prefersReducedMotion()) {
            authVisual.addEventListener('pointermove', (event) => {
                const rect = authVisual.getBoundingClientRect();
                const x = ((event.clientX - rect.left) / rect.width) * 100;
                const y = ((event.clientY - rect.top) / rect.height) * 100;
                authVisual.style.setProperty('--mx', x.toFixed(1) + '%');
                authVisual.style.setProperty('--my', y.toFixed(1) + '%');
            });
        }

        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-password-toggle]');
            if (!toggle) return;
            const targetId = toggle.dataset.passwordToggle;
            const input = targetId ? document.getElementById(targetId) : toggle.parentElement?.querySelector('input');
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Hide' : 'Show';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        });

        document.querySelectorAll('form[data-register-form], form[data-login-form]').forEach((form) => {
            const allowedRaw = form.dataset.allowedDomains || '';
            const allowedDomains = allowedRaw.split(',').map((d) => d.trim().toLowerCase()).filter(Boolean);
            const emailInput = form.querySelector('input[name="email"]');
            const emailFeedback = form.querySelector('[data-email-feedback]');

            if (emailInput && emailFeedback && allowedDomains.length > 0) {
                const checkEmailDomain = () => {
                    const value = emailInput.value.trim().toLowerCase();
                    if (!value || !value.includes('@')) {
                        emailFeedback.textContent = '';
                        emailFeedback.className = 'field-feedback';
                        return;
                    }
                    const domain = value.split('@').pop();
                    if (!domain) {
                        emailFeedback.textContent = '';
                        return;
                    }
                    if (allowedDomains.includes(domain)) {
                        emailFeedback.textContent = '';
                        emailFeedback.className = 'field-feedback';
                    } else if (domain.includes('.')) {
                        emailFeedback.textContent = 'Use an official UPH email (' + allowedDomains.map((d) => '@' + d).join(' or ') + ').';
                        emailFeedback.className = 'field-feedback is-error';
                    }
                };
                emailInput.addEventListener('input', checkEmailDomain);
                emailInput.addEventListener('blur', checkEmailDomain);
            }

            if (form.hasAttribute('data-register-form')) {
                const pw = form.querySelector('input[name="password"]');
                const confirmPw = form.querySelector('input[name="password_confirmation"]');
                const matchEl = form.querySelector('[data-password-match]');
                const strengthWrap = form.querySelector('[data-pw-strength-wrap]');
                const strengthBars = form.querySelector('[data-pw-bars]');
                const strengthLabel = form.querySelector('[data-pw-label]');

                if (pw && strengthWrap && strengthBars && strengthLabel) {
                    const syncStrength = () => {
                        const result = evaluatePasswordStrength(pw.value);
                        if (!result.level) {
                            strengthWrap.hidden = true;
                            strengthBars.setAttribute('data-level', '0');
                            strengthLabel.textContent = '';
                        } else {
                            strengthWrap.hidden = false;
                            strengthBars.setAttribute('data-level', String(result.level));
                            strengthLabel.textContent = result.label;
                        }
                    };
                    pw.addEventListener('input', syncStrength);
                    syncStrength();
                }

                if (pw && confirmPw && matchEl) {
                    const syncMatch = () => {
                        const a = pw.value;
                        const b = confirmPw.value;
                        if (!b) {
                            matchEl.textContent = '';
                            matchEl.className = 'field-feedback';
                            confirmPw.setCustomValidity('');
                            return;
                        }
                        if (a === b) {
                            matchEl.textContent = 'Passwords match.';
                            matchEl.className = 'field-feedback is-ok';
                            confirmPw.setCustomValidity('');
                        } else {
                            matchEl.textContent = 'Passwords do not match yet.';
                            matchEl.className = 'field-feedback is-error';
                            confirmPw.setCustomValidity('Password confirmation does not match.');
                        }
                    };
                    pw.addEventListener('input', syncMatch);
                    confirmPw.addEventListener('input', syncMatch);
                }
            }
        });
    }

    /* =========================================================
       Live Dashboard Feed (AJAX Tabs, Debounced Search, Filters, Pagination)
       ========================================================= */
    function initLiveFeed() {
        const heroTabs = document.getElementById('hero-tabs');
        const feedRegion = document.getElementById('feed-region');
        const toolbarMeta = document.getElementById('toolbar-meta');
        const resetSlot = document.getElementById('filter-reset-slot');
        const filterForm = document.getElementById('dashboard-filter-form');
        if (!heroTabs || !feedRegion || !filterForm || !window.fetch || !window.DOMParser) return;

        const searchInput = filterForm.querySelector('input[name="q"]');
        const scopeInput = filterForm.querySelector('input[name="scope"]');
        const typeInput = filterForm.querySelector('input[name="type"]');
        const catSelect = filterForm.querySelector('select[name="category"]');
        const bldSelect = filterForm.querySelector('select[name="building"]');
        const sortSelect = filterForm.querySelector('select[name="sort"]');

        const buildFormUrl = () => {
            const url = new URL(filterForm.action, window.location.origin);
            const params = new URLSearchParams(new FormData(filterForm));
            url.search = params.toString();
            return url.toString();
        };

        const syncControlsFromUrl = (targetUrl, syncSearchValue = false) => {
            const params = new URL(targetUrl, window.location.origin).searchParams;
            if (scopeInput) scopeInput.value = params.get('scope') || 'feed';
            if (typeInput) typeInput.value = params.get('type') || 'found';
            if (catSelect) catSelect.value = params.get('category') || '';
            if (bldSelect) bldSelect.value = params.get('building') || '';
            if (sortSelect) sortSelect.value = params.get('sort') || 'latest';
            if (syncSearchValue && searchInput) {
                searchInput.value = params.get('q') || '';
                searchInput.dispatchEvent(new Event('sync-clear'));
            }
        };

        const loadFeed = async (targetUrl, { pushHistory = true, syncSearchValue = true, scrollTop = false } = {}) => {
            if (state.feedAbortController) {
                state.feedAbortController.abort();
            }
            const controller = new AbortController();
            state.feedAbortController = controller;

            progressBar.start();
            feedRegion.classList.add('is-loading');

            try {
                const response = await fetch(targetUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (response.redirected && new URL(response.url).pathname === '/login') {
                    window.location.href = response.url;
                    return;
                }
                if (!response.ok) {
                    window.location.href = targetUrl;
                    return;
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextTabs = doc.getElementById('hero-tabs');
                const nextRegion = doc.getElementById('feed-region');
                const nextMeta = doc.getElementById('toolbar-meta');
                const nextReset = doc.getElementById('filter-reset-slot');
                if (!nextRegion || !nextTabs) {
                    window.location.href = targetUrl;
                    return;
                }

                heroTabs.innerHTML = nextTabs.innerHTML;
                if (toolbarMeta && nextMeta) toolbarMeta.innerHTML = nextMeta.innerHTML;
                if (resetSlot && nextReset) resetSlot.innerHTML = nextReset.innerHTML;
                feedRegion.innerHTML = nextRegion.innerHTML;

                syncControlsFromUrl(targetUrl, syncSearchValue);
                document.dispatchEvent(new CustomEvent('lostracer:feed-updated'));

                if (pushHistory && window.history && window.history.pushState) {
                    window.history.pushState({ dashboardUrl: targetUrl }, '', targetUrl);
                }

                if (scrollTop) {
                    const toolbar = filterForm.closest('.feed-toolbar');
                    if (toolbar) {
                        toolbar.scrollIntoView({
                            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                            block: 'nearest',
                        });
                    }
                }
            } catch (err) {
                if (err && err.name === 'AbortError') return;
                window.location.href = targetUrl;
            } finally {
                if (state.feedAbortController === controller) {
                    feedRegion.classList.remove('is-loading');
                    progressBar.done();
                    state.feedAbortController = null;
                }
            }
        };

        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            loadFeed(buildFormUrl(), { pushHistory: true, syncSearchValue: false });
        });

        [catSelect, bldSelect, sortSelect].forEach((sel) => {
            if (!sel) return;
            sel.addEventListener('change', () => {
                loadFeed(buildFormUrl(), { pushHistory: true, syncSearchValue: false });
            });
        });

        let debounceTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(() => {
                    loadFeed(buildFormUrl(), { pushHistory: true, syncSearchValue: false });
                }, 320);
            });
        }

        // Intercept clicks on Hero Tabs, Trending Tags, Reset button, Empty-state Reset, and Pagination
        document.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest(
                '#hero-tabs a.hero-stat, #toolbar-meta a.tag-pill, #filter-reset-slot a, #feed-region nav[aria-label="Pagination"] a, #feed-region .empty-state a.btn'
            );
            if (!link || !link.href) return;

            event.preventDefault();
            const isPagination = Boolean(link.closest('nav[aria-label="Pagination"]'));
            loadFeed(link.href, {
                pushHistory: true,
                syncSearchValue: true,
                scrollTop: isPagination,
            });
        });

        window.addEventListener('popstate', () => {
            loadFeed(window.location.href, { pushHistory: false, syncSearchValue: true });
        });
    }

    /* =========================================================
       Filters, Clear Buttons, Shortcuts & Scroll Jump Cards
       ========================================================= */
    function initFiltersAndShortcuts() {
        // Standalone auto-submit selects (Security Users, Audit Logs)
        document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
            if (select.closest('[data-live-filter-form]')) return;
            select.addEventListener('change', () => {
                if (select.form) {
                    progressBar.start();
                    select.form.submit();
                }
            });
        });

        // Clearable search inputs
        document.querySelectorAll('[data-search-clearable]').forEach((wrapper) => {
            const input = wrapper.querySelector('input[type="text"], input[type="search"]');
            const clearBtn = wrapper.querySelector('[data-clear-input]');
            if (!input || !clearBtn) return;

            const syncVisibility = () => {
                clearBtn.hidden = input.value.trim() === '';
            };

            input.addEventListener('input', syncVisibility);
            input.addEventListener('sync-clear', syncVisibility);
            clearBtn.addEventListener('click', () => {
                input.value = '';
                syncVisibility();
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            });
            syncVisibility();
        });

        // "/" shortcut focuses primary search box cleanly (Cmd/Ctrl+K handled by Command Palette)
        document.addEventListener('keydown', (event) => {
            const searchInput = document.querySelector('[data-shortcut-search]');
            if (!searchInput) return;

            const isSlash = event.key === '/' && !event.metaKey && !event.ctrlKey && !event.altKey;
            if (!isSlash) return;

            const target = event.target;
            if (target && (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable
            )) {
                return;
            }
            event.preventDefault();
            searchInput.focus();
            searchInput.select();
        });

        // Instant filter for Chat Conversations sidebar
        document.querySelectorAll('[data-thread-filter]').forEach((input) => {
            const container = document.querySelector('[data-thread-list]');
            const emptyFilterNote = document.querySelector('[data-thread-filter-empty]');
            if (!container) return;

            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                let visibleCount = 0;
                container.querySelectorAll('[data-thread-item]').forEach((row) => {
                    const haystack = (row.dataset.threadSearch || row.textContent || '').toLowerCase();
                    const matches = term === '' || haystack.includes(term);
                    row.style.display = matches ? '' : 'none';
                    if (matches) visibleCount += 1;
                });
                if (emptyFilterNote) {
                    emptyFilterNote.hidden = visibleCount > 0 || term === '';
                }
            });
        });

        // Clickable KPI cards smoothly scroll & flash target section
        const activateJump = (trigger) => {
            const selector = trigger.dataset.scrollTo;
            const target = selector ? document.querySelector(selector) : null;
            if (!target) return;
            target.scrollIntoView({
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                block: 'start',
            });
            target.classList.remove('is-flash-highlight');
            void target.offsetWidth;
            target.classList.add('is-flash-highlight');
        };

        document.addEventListener('click', (event) => {
            const card = event.target.closest('[data-scroll-to]');
            if (card) activateJump(card);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            const card = event.target.closest('[data-scroll-to]');
            if (card) {
                event.preventDefault();
                activateJump(card);
            }
        });
    }

    /* =========================================================
       Item Create / Edit Forms (Type Switch, Hashtags, Completion, Quick Tags)
       ========================================================= */
    function initItemForms() {
        const parseTags = (rawValue) => [
            ...new Set(
                (rawValue || '')
                    .split(',')
                    .map((t) => t.replace(/^#+/, '').trim().toLowerCase())
                    .filter(Boolean)
            ),
        ];

        // Toggle conditional "Others" category text field (Create & Edit Post)
        const categorySelect = document.getElementById('category_id');
        const customCategoryGroup = document.getElementById('custom-category-group');
        const customCategoryInput = document.getElementById('custom_category');
        if (categorySelect && customCategoryGroup && customCategoryInput) {
            const isOtherSelected = () => {
                const selectedOpt = categorySelect.options[categorySelect.selectedIndex];
                return Boolean(selectedOpt && selectedOpt.dataset.isOther === 'true');
            };

            const syncCustomCategoryUI = ({ focusInput = false } = {}) => {
                const showCustom = isOtherSelected();
                customCategoryGroup.hidden = !showCustom;
                customCategoryInput.required = showCustom;
                customCategoryInput.disabled = !showCustom;
                if (showCustom && focusInput) {
                    customCategoryInput.focus();
                }
            };

            categorySelect.addEventListener('change', () => syncCustomCategoryUI({ focusInput: true }));
            syncCustomCategoryUI({ focusInput: false });
        }

        // Post Type Switcher (Create Post)
        const radios = document.querySelectorAll('.type-radio');
        if (radios.length > 0) {
            const cards = document.querySelectorAll('.type-option-card');
            const foundBox = document.getElementById('found-only-fields');
            const foundNotice = document.getElementById('found-custody-notice');
            const photoReqLabel = document.getElementById('photo-requirement-label');
            const mediaStepNumber = document.getElementById('media-step-number');
            const custodyInput = document.getElementById('custody_location');
            const depositNoteInput = document.getElementById('deposit_note');

            const syncTypeUI = () => {
                const checked = document.querySelector('.type-radio:checked');
                const selected = checked ? checked.value : 'found';
                const isFound = selected === 'found';

                cards.forEach((card) => {
                    card.classList.remove('is-selected-found', 'is-selected-lost');
                    if (card.dataset.type === selected) {
                        card.classList.add(isFound ? 'is-selected-found' : 'is-selected-lost');
                    }
                });

                if (foundBox) foundBox.style.display = isFound ? 'block' : 'none';
                if (foundNotice) foundNotice.style.display = isFound ? 'flex' : 'none';
                if (mediaStepNumber) mediaStepNumber.textContent = isFound ? '4' : '3';
                if (photoReqLabel) {
                    photoReqLabel.textContent = isFound ? '(Required for Found Posts)' : '(Optional for Lost Posts)';
                }
                if (custodyInput) custodyInput.required = isFound;
                if (depositNoteInput) depositNoteInput.required = isFound;
            };

            radios.forEach((radio) => radio.addEventListener('change', syncTypeUI));
            syncTypeUI();
        }

        // Sync Suggested Tag Chips with #tags input
        const syncSuggestedChips = (tagsInput) => {
            const currentTags = parseTags(tagsInput.value);
            document.querySelectorAll('[data-suggest-tag]').forEach((chip) => {
                const tag = chip.dataset.suggestTag.toLowerCase();
                chip.classList.toggle('is-active', currentTags.includes(tag));
            });
        };

        document.addEventListener('click', (event) => {
            const chip = event.target.closest('[data-suggest-tag]');
            if (!chip) return;
            const targetSelector = chip.dataset.tagsTarget;
            const tagsInput = targetSelector ? document.querySelector(targetSelector) : null;
            if (!tagsInput) return;

            const tag = chip.dataset.suggestTag.toLowerCase();
            let list = parseTags(tagsInput.value);
            if (list.includes(tag)) {
                list = list.filter((t) => t !== tag);
            } else {
                if (list.length >= 5) {
                    toast('Maximum 5 hashtags allowed per post.', 'info', 2600);
                    return;
                }
                list.push(tag);
            }
            tagsInput.value = list.join(', ');
            tagsInput.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // Live Hashtag Parser & Counter (Create & Edit Post)
        document.querySelectorAll('[data-tags-input]').forEach((tagsInput) => {
            const previewSelector = tagsInput.dataset.tagsPreview;
            const previewEl = previewSelector ? document.querySelector(previewSelector) : null;
            if (!previewEl) return;

            const renderTags = () => {
                const unique = parseTags(tagsInput.value);
                const validCount = unique.length >= 1 && unique.length <= 5;

                tagsInput.setCustomValidity(
                    unique.length > 5 ? 'Maximum 5 hashtags allowed per post.' : ''
                );

                previewEl.innerHTML = '';
                const countBadge = document.createElement('span');
                countBadge.className = 'caption';
                countBadge.style.fontWeight = '600';
                countBadge.style.color = validCount ? 'var(--emerald-found)' : 'var(--danger)';
                countBadge.textContent = unique.length + '/5 tags';
                previewEl.appendChild(countBadge);

                unique.forEach((tag) => {
                    const pill = document.createElement('span');
                    pill.className = 'tag-pill';
                    pill.textContent = '#' + tag;
                    previewEl.appendChild(pill);
                });

                syncSuggestedChips(tagsInput);
            };

            tagsInput.addEventListener('input', renderTags);
            renderTags();
        });

        // Live Completion Progress Bar & Unsaved Changes Guard (Create Post)
        const createForm = document.querySelector('form[data-post-create-form]');
        if (createForm) {
            const progressFill = document.querySelector('[data-post-progress-fill]');
            const progressText = document.querySelector('[data-post-progress-text]');

            const computeProgress = () => {
                const isFound = (createForm.querySelector('.type-radio:checked')?.value || 'found') === 'found';
                const catSelectEl = createForm.querySelector('#category_id');
                const customCatEl = createForm.querySelector('#custom_category');
                const selectedCatOpt = catSelectEl?.options[catSelectEl.selectedIndex];
                const isOtherCat = Boolean(selectedCatOpt && selectedCatOpt.dataset.isOther === 'true');
                const hasValidCategory = Boolean(catSelectEl?.value) && (!isOtherCat || Boolean(customCatEl?.value.trim()));

                const checks = [
                    hasValidCategory,
                    Boolean(createForm.querySelector('#event_date')?.value),
                    Boolean(createForm.querySelector('#title')?.value.trim()),
                    Boolean(createForm.querySelector('#description')?.value.trim()),
                    Boolean(createForm.querySelector('#building')?.value),
                    Boolean(createForm.querySelector('#floor_detail')?.value.trim()),
                    parseTags(createForm.querySelector('#tags')?.value).length >= 1 &&
                        parseTags(createForm.querySelector('#tags')?.value).length <= 5,
                ];

                if (isFound) {
                    const photoInput = createForm.querySelector('#photo');
                    checks.push(
                        Boolean(createForm.querySelector('#custody_location')?.value),
                        Boolean(createForm.querySelector('#deposit_note')?.value.trim()),
                        Boolean(photoInput && photoInput.files && photoInput.files.length > 0)
                    );
                }

                const completed = checks.filter(Boolean).length;
                const pct = Math.round((completed / checks.length) * 100);
                if (progressFill) progressFill.style.width = pct + '%';
                if (progressText) {
                    progressText.textContent = pct === 100
                        ? '100% ready to publish'
                        : pct + '% completed (' + completed + '/' + checks.length + ')';
                    progressText.style.color = pct === 100 ? 'var(--emerald-found)' : 'var(--primary-navy)';
                }
            };

            let isDirty = false;
            createForm.addEventListener('input', () => {
                isDirty = true;
                computeProgress();
            });
            createForm.addEventListener('change', () => {
                isDirty = true;
                computeProgress();
            });
            window.addEventListener('beforeunload', (event) => {
                if (isDirty && !state.isSubmittingForm) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
            computeProgress();
        }

        // Live Character Counters & Autogrow Textareas
        document.querySelectorAll('textarea[data-char-counter], input[data-char-counter]').forEach((field) => {
            const max = parseInt(field.getAttribute('maxlength') || field.dataset.maxChars || '0', 10);
            if (!max) return;
            const counter = document.createElement('span');
            counter.className = 'caption char-counter';

            const updateCounter = () => {
                const len = field.value.length;
                counter.textContent = len + ' / ' + max;
                counter.style.color = len > max * 0.9 ? 'var(--warning-text)' : '';
            };

            field.insertAdjacentElement('afterend', counter);
            field.addEventListener('input', updateCounter);
            updateCounter();
        });

        document.querySelectorAll('textarea[data-autogrow]').forEach((ta) => {
            const resize = () => {
                ta.style.height = 'auto';
                ta.style.height = Math.min(160, ta.scrollHeight) + 'px';
            };
            ta.addEventListener('input', resize);
        });
    }

    /* =========================================================
       File Dropzones, Client Validation & Preview
       ========================================================= */
    function initFileUploads() {
        document.querySelectorAll('[data-file-dropzone]').forEach((zone) => {
            const input = zone.querySelector('input[type="file"]');
            if (!input) return;

            const previewWrap = zone.querySelector('[data-file-preview]');
            const previewImg = zone.querySelector('[data-file-img]');
            const metaRow = zone.querySelector('[data-file-meta]');
            const nameEl = zone.querySelector('[data-file-name]');
            const clearBtn = zone.querySelector('[data-file-clear]');
            const maxKb = parseInt(input.dataset.maxKb || '0', 10);

            const resetFileUI = () => {
                if (previewWrap) previewWrap.style.display = 'none';
                if (previewImg) {
                    previewImg.removeAttribute('src');
                    previewImg.style.display = 'none';
                }
                if (metaRow) metaRow.style.display = 'none';
                if (nameEl) nameEl.textContent = '';
            };

            const handleSelectedFile = () => {
                const file = input.files && input.files[0];
                if (!file) {
                    resetFileUI();
                    return;
                }

                if (maxKb > 0 && file.size > maxKb * 1024) {
                    const maxMb = (maxKb / 1024).toFixed(maxKb % 1024 === 0 ? 0 : 1);
                    toast('"' + file.name + '" exceeds the ' + maxMb + 'MB upload limit.', 'error', 4800);
                    input.value = '';
                    resetFileUI();
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }

                if (nameEl) nameEl.textContent = file.name + ' (' + formatBytes(file.size) + ')';
                if (metaRow) metaRow.style.display = 'flex';

                if (file.type && file.type.startsWith('image/') && previewWrap && previewImg) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewImg.src = e.target.result;
                        previewImg.style.display = 'block';
                        previewWrap.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else if (previewWrap) {
                    previewWrap.style.display = 'none';
                    if (previewImg) previewImg.style.display = 'none';
                }
            };

            input.addEventListener('change', handleSelectedFile);

            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    input.value = '';
                    resetFileUI();
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            ['dragenter', 'dragover'].forEach((evt) => {
                zone.addEventListener(evt, (event) => {
                    event.preventDefault();
                    zone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'drop'].forEach((evt) => {
                zone.addEventListener(evt, (event) => {
                    event.preventDefault();
                    zone.classList.remove('is-dragover');
                });
            });

            zone.addEventListener('drop', (event) => {
                const files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) return;
                if (typeof DataTransfer !== 'undefined') {
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    input.files = dt.files;
                }
                handleSelectedFile();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    /* =========================================================
       Image Lightbox with Click-to-Zoom (Non-Sensitive Photos Only)
       ========================================================= */
    function closeLightbox() {
        const modal = document.getElementById('lightbox-modal');
        const targetImg = document.getElementById('lightbox-image');
        if (!modal || !modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        if (targetImg) targetImg.classList.remove('is-zoomed');
        if (state.lightboxPrevFocus && typeof state.lightboxPrevFocus.focus === 'function') {
            state.lightboxPrevFocus.focus();
            state.lightboxPrevFocus = null;
        }
    }

    function initLightbox() {
        document.addEventListener('click', (event) => {
            const triggerImg = event.target.closest('img[data-lightbox]');
            if (triggerImg) {
                if (triggerImg.classList.contains('sensitive-blur')) return;
                const modal = document.getElementById('lightbox-modal');
                const targetImg = document.getElementById('lightbox-image');
                const captionEl = document.getElementById('lightbox-caption');
                const closeBtn = document.getElementById('lightbox-close');
                if (!modal || !targetImg) return;

                state.lightboxPrevFocus = document.activeElement;
                targetImg.classList.remove('is-zoomed');
                targetImg.src = triggerImg.currentSrc || triggerImg.src;
                targetImg.alt = triggerImg.alt || '';
                if (captionEl) {
                    captionEl.textContent = (triggerImg.alt ? triggerImg.alt + ' \u2022 ' : '') + 'Click image to zoom';
                }
                modal.classList.add('is-open');
                if (closeBtn) closeBtn.focus();
                return;
            }

            const modal = document.getElementById('lightbox-modal');
            if (!modal || !modal.classList.contains('is-open')) return;

            const zoomImg = event.target.closest('#lightbox-image');
            if (zoomImg) {
                zoomImg.classList.toggle('is-zoomed');
                return;
            }

            if (event.target === modal || event.target.closest('#lightbox-close')) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeLightbox();
        });
    }

    /* =========================================================
       Copy-to-Clipboard & Print Buttons
       ========================================================= */
    function initCopyAndPrintButtons() {
        document.addEventListener('click', async (event) => {
            const printBtn = event.target.closest('[data-print]');
            if (printBtn) {
                window.print();
                return;
            }

            const copyBtn = event.target.closest('[data-copy-text]');
            if (!copyBtn) return;

            const value = copyBtn.dataset.copyText || '';
            if (!value) return;

            let copied = false;
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(value);
                    copied = true;
                } catch {
                    copied = false;
                }
            }

            if (!copied) {
                const helper = document.createElement('textarea');
                helper.value = value;
                helper.setAttribute('readonly', '');
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                try {
                    copied = document.execCommand('copy');
                } catch {
                    copied = false;
                }
                helper.remove();
            }

            if (copied) {
                const originalLabel = copyBtn.dataset.originalLabel || copyBtn.textContent.trim();
                copyBtn.dataset.originalLabel = originalLabel;
                copyBtn.textContent = 'Copied!';
                copyBtn.classList.add('is-copied');
                toast(copyBtn.dataset.copyToast || 'Pickup code copied to clipboard.', 'success', 2400);
                window.setTimeout(() => {
                    copyBtn.textContent = originalLabel;
                    copyBtn.classList.remove('is-copied');
                }, 1800);
            } else {
                toast('Could not copy automatically. Please copy manually.', 'error');
            }
        });
    }

    /* =========================================================
       Live Encrypted In-App Chat (AJAX Send + Background Poll + Quick Chips)
       ========================================================= */
    function buildChatBubble(msg) {
        const wrapper = document.createElement('div');
        wrapper.className = msg.is_mine ? 'chat-bubble-sent' : 'chat-bubble-recv';
        if (msg.id) {
            wrapper.dataset.messageId = String(msg.id);
        }

        const meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.style.cssText = 'display: flex; justify-content: space-between; gap: 12px; font-size: 11px; margin-bottom: 3px;';

        const sender = document.createElement('strong');
        sender.textContent = msg.sender_name || (msg.is_mine ? 'You' : 'User');

        const time = document.createElement('span');
        time.textContent = msg.timestamp || '';

        const body = document.createElement('div');
        body.style.cssText = 'font-size: 13.5px; line-height: 1.45;';
        body.textContent = msg.body || '';

        meta.append(sender, time);
        wrapper.append(meta, body);
        return wrapper;
    }

    function updateNavUnreadBadge(totalUnread) {
        const navLink = document.querySelector('[data-chat-nav-link]');
        if (!navLink) return;
        let badge = navLink.querySelector('.nav-badge');
        if (totalUnread > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'nav-badge';
                navLink.appendChild(badge);
            }
            badge.textContent = String(totalUnread);
        } else if (badge) {
            badge.remove();
        }
    }

    function updateSidebarSnippet(threadKey, text) {
        if (!threadKey) return;
        const snippet = document.querySelector(
            '[data-thread-item][data-thread-key="' + escapeSelectorValue(threadKey) + '"] [data-thread-snippet]'
        );
        if (snippet) {
            snippet.textContent = text.length > 32 ? text.slice(0, 32) + '...' : text;
        }
    }

    function initLiveChat() {
        document.querySelectorAll('[data-chat-scroll]').forEach((box) => {
            box.scrollTop = box.scrollHeight;
        });

        // Quick message suggestion chips
        document.addEventListener('click', (event) => {
            const chip = event.target.closest('[data-chat-suggest]');
            if (!chip) return;
            const target = document.querySelector(chip.dataset.chatTarget || '#message_body, #chat-body');
            if (!target || target.readOnly) return;
            target.value = chip.dataset.chatSuggest || '';
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.focus();
        });

        const threadRoot = document.querySelector('[data-chat-thread]');
        if (!threadRoot) return;

        const scrollBox = threadRoot.querySelector('[data-chat-scroll]');
        const form = threadRoot.querySelector('form[data-chat-form]');
        const input = form ? form.querySelector('input[name="message_body"], textarea[name="message_body"]') : null;
        const sendBtn = form ? form.querySelector('button[type="submit"]') : null;
        let jumpBtn = null;

        const removeJumpBtn = () => {
            if (jumpBtn && jumpBtn.parentElement) {
                jumpBtn.remove();
            }
            jumpBtn = null;
        };

        const showJumpBtn = () => {
            if (!scrollBox || jumpBtn) return;
            jumpBtn = document.createElement('button');
            jumpBtn.type = 'button';
            jumpBtn.className = 'new-msg-jump';
            jumpBtn.textContent = '\u2193 New messages';
            jumpBtn.addEventListener('click', () => {
                scrollBox.scrollTo({
                    top: scrollBox.scrollHeight,
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                });
                removeJumpBtn();
            });
            scrollBox.appendChild(jumpBtn);
        };

        if (scrollBox) {
            scrollBox.addEventListener('scroll', () => {
                const atBottom = scrollBox.scrollHeight - scrollBox.scrollTop - scrollBox.clientHeight < 40;
                if (atBottom) removeJumpBtn();
            }, { passive: true });
        }

        const appendMessageIfNew = (msg, forceScroll = false) => {
            if (!scrollBox || !msg || !msg.id) return false;
            const existing = scrollBox.querySelector('[data-message-id="' + escapeSelectorValue(msg.id) + '"]');
            if (existing) return false;

            const emptyNote = scrollBox.querySelector('[data-chat-empty]');
            if (emptyNote) emptyNote.remove();

            const nearBottom = scrollBox.scrollHeight - scrollBox.scrollTop - scrollBox.clientHeight < 90;
            scrollBox.appendChild(buildChatBubble(msg));

            const currentMax = parseInt(threadRoot.dataset.lastMessageId || '0', 10);
            if (msg.id > currentMax) {
                threadRoot.dataset.lastMessageId = String(msg.id);
            }

            if (forceScroll || nearBottom) {
                scrollBox.scrollTop = scrollBox.scrollHeight;
                removeJumpBtn();
            } else {
                showJumpBtn();
            }
            return true;
        };

        if (input && form && input.tagName === 'TEXTAREA') {
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                }
            });
        }

        if (form && input) {
            form.addEventListener('submit', async (event) => {
                if (!window.fetch) return;
                const bodyText = input.value.trim();
                if (!bodyText) return;

                event.preventDefault();
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.classList.add('is-loading');
                }
                input.readOnly = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        toast(payload.message || 'Could not send message. Please try again.', 'error');
                        return;
                    }

                    if (payload.message) {
                        appendMessageIfNew(payload.message, true);
                        updateSidebarSnippet(threadRoot.dataset.threadKey, payload.message.body);
                    }
                    input.value = '';
                    if (input.tagName === 'TEXTAREA') {
                        input.style.height = 'auto';
                    }
                } catch {
                    toast('Network error while sending message.', 'error');
                } finally {
                    input.readOnly = false;
                    if (sendBtn) {
                        sendBtn.disabled = false;
                        sendBtn.classList.remove('is-loading');
                    }
                    input.focus();
                }
            });
        }

        const pollUrl = threadRoot.dataset.pollUrl;
        const itemId = threadRoot.dataset.itemId;
        const peerId = threadRoot.dataset.peerId;
        if (!pollUrl || !itemId || !peerId || threadRoot.dataset.terminal === 'true' || !window.fetch) {
            return;
        }

        let inFlight = false;
        const pollThread = async () => {
            if (inFlight || document.visibilityState !== 'visible') return;
            inFlight = true;
            try {
                const afterId = parseInt(threadRoot.dataset.lastMessageId || '0', 10);
                const url = new URL(pollUrl, window.location.origin);
                url.searchParams.set('item', itemId);
                url.searchParams.set('peer', peerId);
                if (afterId > 0) url.searchParams.set('after_id', String(afterId));

                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;

                const data = await response.json();
                if (Array.isArray(data.messages)) {
                    data.messages.forEach((msg) => {
                        const added = appendMessageIfNew(msg, false);
                        if (added) {
                            updateSidebarSnippet(threadRoot.dataset.threadKey, msg.body);
                        }
                    });
                }

                if (typeof data.unread_total === 'number') {
                    updateNavUnreadBadge(data.unread_total);
                }

                if (data.is_terminal && form) {
                    threadRoot.dataset.terminal = 'true';
                    form.outerHTML = '<div class="alert alert-info" style="margin-bottom: 0;"><div class="alert-body">This chat thread is now read-only because the item reached a final status.</div></div>';
                }
            } catch {
                // Silent backoff until next poll tick
            } finally {
                inFlight = false;
            }
        };

        const timerId = window.setInterval(pollThread, 5000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') pollThread();
        });
        window.addEventListener('beforeunload', () => window.clearInterval(timerId));
    }

    /* =========================================================
       Slide-Over Drawers, Quick Preview & Saved Watchlist
       ========================================================= */
    const WATCHLIST_KEY = 'lostracer-watchlist';
    const VIEW_MODE_KEY = 'lostracer-feed-view';

    function openDrawer(drawerId) {
        const el = document.getElementById(drawerId);
        if (!el) return;
        document.querySelectorAll('.drawer-backdrop.is-open').forEach((d) => d.classList.remove('is-open'));
        el.classList.add('is-open');
        const closeBtn = el.querySelector('.drawer-close-btn');
        if (closeBtn) closeBtn.focus();
    }

    function closeAllDrawers() {
        document.querySelectorAll('.drawer-backdrop.is-open').forEach((d) => d.classList.remove('is-open'));
    }

    function readWatchlist() {
        try {
            const raw = window.localStorage.getItem(WATCHLIST_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }

    function writeWatchlist(items) {
        try {
            window.localStorage.setItem(WATCHLIST_KEY, JSON.stringify(items.slice(0, 40)));
        } catch {
            // Storage unavailable
        }
    }

    function syncWatchlistUI() {
        const saved = readWatchlist();
        const savedIds = new Set(saved.map((entry) => String(entry.id)));

        document.querySelectorAll('[data-watchlist-count]').forEach((badge) => {
            badge.textContent = String(saved.length);
            badge.hidden = saved.length === 0;
        });

        document.querySelectorAll('[data-bookmark-btn]').forEach((btn) => {
            const id = btn.dataset.itemId;
            if (!id) return;
            const active = savedIds.has(String(id));
            btn.classList.toggle('is-bookmarked', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (btn.classList.contains('btn')) {
                btn.textContent = active ? 'Saved' : (btn.id === 'qv-bookmark-btn' ? 'Save' : 'Save Post');
            }
        });
    }

    function renderWatchlistDrawer() {
        const host = document.getElementById('watchlist-items');
        if (!host) return;
        const saved = readWatchlist();
        host.innerHTML = '';

        if (saved.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'caption';
            empty.style.cssText = 'text-align: center; padding: 36px 12px; margin: 0;';
            empty.textContent = 'No saved posts yet. Click the bookmark button on any post to keep track of it here.';
            host.appendChild(empty);
            return;
        }

        saved.forEach((entry) => {
            const row = document.createElement('div');
            row.className = 'watchlist-item-row';

            const info = document.createElement('div');
            info.style.minWidth = '0';
            info.style.flex = '1';

            const titleLink = document.createElement('a');
            titleLink.href = entry.url || '#';
            titleLink.style.cssText = 'display: block; font-weight: 700; font-size: 13.5px; color: var(--text-dark); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
            titleLink.textContent = entry.title || 'Post';

            const meta = document.createElement('span');
            meta.className = 'badge ' + (entry.type === 'found' ? 'badge-found-open' : 'badge-lost-open');
            meta.style.marginTop = '4px';
            meta.textContent = entry.type === 'found' ? 'Found' : 'Lost';

            info.append(titleLink, meta);

            const actions = document.createElement('div');
            actions.style.cssText = 'display: flex; gap: 6px; flex-shrink: 0;';

            const openBtn = document.createElement('a');
            openBtn.href = entry.url || '#';
            openBtn.className = 'btn btn-primary btn-sm';
            openBtn.textContent = 'Open';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline btn-sm';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
                const next = readWatchlist().filter((item) => String(item.id) !== String(entry.id));
                writeWatchlist(next);
                syncWatchlistUI();
                renderWatchlistDrawer();
            });

            actions.append(openBtn, removeBtn);
            row.append(info, actions);
            host.appendChild(row);
        });
    }

    function initWatchlistAndDrawers() {
        syncWatchlistUI();
        document.addEventListener('lostracer:feed-updated', syncWatchlistUI);

        document.addEventListener('click', (event) => {
            const closeBtn = event.target.closest('[data-drawer-close]');
            if (closeBtn) {
                const target = document.getElementById(closeBtn.dataset.drawerClose);
                if (target) target.classList.remove('is-open');
                return;
            }

            if (event.target.classList && event.target.classList.contains('drawer-backdrop')) {
                event.target.classList.remove('is-open');
                return;
            }

            const openWl = event.target.closest('[data-open-watchlist]');
            if (openWl) {
                renderWatchlistDrawer();
                openDrawer('watchlist-drawer');
                return;
            }

            const bookmarkBtn = event.target.closest('[data-bookmark-btn]');
            if (bookmarkBtn) {
                const id = bookmarkBtn.dataset.itemId;
                if (!id) return;
                const current = readWatchlist();
                const exists = current.some((entry) => String(entry.id) === String(id));
                let next;
                if (exists) {
                    next = current.filter((entry) => String(entry.id) !== String(id));
                    toast('Removed from Watchlist.', 'info', 2200);
                } else {
                    next = [{
                        id,
                        title: bookmarkBtn.dataset.itemTitle || 'Saved Post',
                        type: bookmarkBtn.dataset.itemType || 'found',
                        url: bookmarkBtn.dataset.itemUrl || '#',
                    }, ...current];
                    toast('Saved to Watchlist.', 'success', 2200);
                }
                writeWatchlist(next);
                syncWatchlistUI();
                renderWatchlistDrawer();
                return;
            }

            const quickViewBtn = event.target.closest('[data-quick-view]');
            if (quickViewBtn) {
                const card = quickViewBtn.closest('[data-qv]');
                if (!card) return;
                let data;
                try {
                    data = JSON.parse(card.dataset.qv || '{}');
                } catch {
                    return;
                }

                const isFound = data.type === 'found';
                const typeBadge = document.getElementById('qv-type-badge');
                const statusBadge = document.getElementById('qv-status-badge');
                const sensBadge = document.getElementById('qv-sensitive-badge');
                const mediaWrap = document.getElementById('qv-media-wrap');
                const img = document.getElementById('qv-img');
                const blurNote = document.getElementById('qv-blur-note');
                const custodyBox = document.getElementById('qv-custody-box');
                const custodyText = document.getElementById('qv-custody-text');
                const tagsBox = document.getElementById('qv-tags');
                const chatLink = document.getElementById('qv-chat-link');
                const openLink = document.getElementById('qv-open-link');
                const qvBookmark = document.getElementById('qv-bookmark-btn');

                if (typeBadge) {
                    typeBadge.className = 'badge ' + (isFound ? 'badge-found-open' : 'badge-lost-open');
                    typeBadge.textContent = isFound ? 'Found' : 'Lost';
                }
                if (statusBadge) {
                    const statusLabel = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Open';
                    statusBadge.className = 'badge ' + (
                        data.status === 'returned'
                            ? 'badge-returned'
                            : (data.status === 'claimed' ? 'badge-claimed' : (data.status === 'expired' ? 'badge-expired' : (isFound ? 'badge-found-open' : 'badge-lost-open')))
                    );
                    statusBadge.textContent = statusLabel;
                }
                if (sensBadge) sensBadge.hidden = !data.is_sensitive;

                if (mediaWrap && img) {
                    if (data.photo_url) {
                        mediaWrap.hidden = false;
                        img.src = data.photo_url;
                        img.alt = data.title || '';
                        img.className = data.is_blurred ? 'sensitive-blur' : '';
                        if (blurNote) blurNote.hidden = !data.is_blurred;
                    } else {
                        mediaWrap.hidden = true;
                        img.removeAttribute('src');
                    }
                }

                const setText = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = val || '-';
                };
                setText('qv-category', data.category);
                setText('qv-title', data.title);
                setText('qv-desc', data.description);
                setText('qv-location', [data.building, data.floor_detail].filter(Boolean).join(' \u2014 '));
                setText('qv-date', data.event_date);
                setText('qv-author', data.author);
                setText('qv-posted', data.created_human);

                if (custodyBox && custodyText) {
                    custodyBox.hidden = !data.custody_location;
                    custodyText.textContent = data.custody_location || '';
                }

                if (tagsBox) {
                    tagsBox.innerHTML = '';
                    (data.tags || []).forEach((tagName) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'tag-pill';
                        btn.textContent = '#' + tagName;
                        btn.addEventListener('click', () => {
                            closeAllDrawers();
                            const filterInput = document.querySelector('#dashboard-filter-form input[name="q"]');
                            const filterForm = document.getElementById('dashboard-filter-form');
                            if (filterInput && filterForm) {
                                filterInput.value = '#' + tagName;
                                filterInput.dispatchEvent(new Event('sync-clear'));
                                if (typeof filterForm.requestSubmit === 'function') {
                                    filterForm.requestSubmit();
                                } else {
                                    filterForm.submit();
                                }
                            }
                        });
                        tagsBox.appendChild(btn);
                    });
                }

                if (chatLink) {
                    if (data.chat_url) {
                        chatLink.hidden = false;
                        chatLink.href = data.chat_url;
                    } else {
                        chatLink.hidden = true;
                    }
                }
                if (openLink) openLink.href = data.show_url || '#';

                if (qvBookmark) {
                    qvBookmark.dataset.itemId = String(data.id || '');
                    qvBookmark.dataset.itemTitle = data.title || '';
                    qvBookmark.dataset.itemType = data.type || 'found';
                    qvBookmark.dataset.itemUrl = data.show_url || '#';
                    syncWatchlistUI();
                }

                openDrawer('quick-view-drawer');
            }
        });

        const clearBtn = document.getElementById('watchlist-clear-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                writeWatchlist([]);
                syncWatchlistUI();
                renderWatchlistDrawer();
                toast('Watchlist cleared.', 'info', 2000);
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAllDrawers();
        });
    }

    /* =========================================================
       Feed Layout Mode Switcher (Grid <-> List)
       ========================================================= */
    function applyFeedViewMode(mode) {
        const normalized = mode === 'list' ? 'list' : 'grid';
        const grid = document.querySelector('#feed-region .feed-grid');
        if (grid) {
            grid.classList.toggle('is-list-view', normalized === 'list');
        }
        document.querySelectorAll('[data-view-mode]').forEach((btn) => {
            const active = btn.dataset.viewMode === normalized;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function initViewModeSwitcher() {
        let savedMode = 'grid';
        try {
            savedMode = window.localStorage.getItem(VIEW_MODE_KEY) || 'grid';
        } catch {
            savedMode = 'grid';
        }
        applyFeedViewMode(savedMode);

        document.addEventListener('lostracer:feed-updated', () => {
            try {
                applyFeedViewMode(window.localStorage.getItem(VIEW_MODE_KEY) || 'grid');
            } catch {
                applyFeedViewMode('grid');
            }
        });

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-view-mode]');
            if (!btn) return;
            const mode = btn.dataset.viewMode === 'list' ? 'list' : 'grid';
            try {
                window.localStorage.setItem(VIEW_MODE_KEY, mode);
            } catch {
                // Storage unavailable
            }
            applyFeedViewMode(mode);
        });
    }

    /* =========================================================
       Command Palette (Cmd/Ctrl + K)
       ========================================================= */
    function initCommandPalette() {
        const modal = document.getElementById('command-palette-modal');
        const input = document.getElementById('cmdk-input');
        const emptyEl = document.getElementById('cmdk-empty');
        if (!modal || !input) return;

        const items = [...modal.querySelectorAll('[data-cmdk-item]')];
        let prevFocus = null;

        const visibleItems = () => items.filter((el) => !el.hidden);

        const highlightIndex = (index) => {
            const visible = visibleItems();
            items.forEach((el) => el.classList.remove('is-selected'));
            if (visible.length === 0) return;
            const bounded = ((index % visible.length) + visible.length) % visible.length;
            visible[bounded].classList.add('is-selected');
            visible[bounded].scrollIntoView({ block: 'nearest' });
        };

        const openPalette = () => {
            prevFocus = document.activeElement;
            input.value = '';
            items.forEach((el) => { el.hidden = false; });
            if (emptyEl) emptyEl.style.display = 'none';
            modal.classList.add('is-open');
            highlightIndex(0);
            input.focus();
        };

        const closePalette = () => {
            if (!modal.classList.contains('is-open')) return;
            modal.classList.remove('is-open');
            if (prevFocus && typeof prevFocus.focus === 'function') {
                prevFocus.focus();
                prevFocus = null;
            }
        };

        const runItemAction = (el) => {
            closePalette();
            const action = el.dataset.cmdkAction;
            if (action === 'toggle-theme') {
                const toggleBtn = document.querySelector('[data-theme-toggle]');
                if (toggleBtn) toggleBtn.click();
            } else if (action === 'open-watchlist') {
                renderWatchlistDrawer();
                openDrawer('watchlist-drawer');
            } else if (el.href) {
                progressBar.start();
                window.location.href = el.href;
            }
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-open-cmdk]')) {
                openPalette();
                return;
            }
            if (event.target === modal) {
                closePalette();
                return;
            }
            const cmdBtn = event.target.closest('[data-cmdk-item]');
            if (cmdBtn && modal.contains(cmdBtn)) {
                if (cmdBtn.dataset.cmdkAction) {
                    event.preventDefault();
                    runItemAction(cmdBtn);
                } else {
                    closePalette();
                }
            }
        });

        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            let count = 0;
            items.forEach((el) => {
                const show = !term || el.textContent.toLowerCase().includes(term);
                el.hidden = !show;
                if (show) count += 1;
            });
            if (emptyEl) emptyEl.style.display = count === 0 ? 'block' : 'none';
            highlightIndex(0);
        });

        input.addEventListener('keydown', (event) => {
            const visible = visibleItems();
            const currentIdx = visible.findIndex((el) => el.classList.contains('is-selected'));

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlightIndex(currentIdx + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlightIndex(currentIdx - 1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const target = visible[currentIdx >= 0 ? currentIdx : 0];
                if (target) runItemAction(target);
            } else if (event.key === 'Escape') {
                closePalette();
            }
        });

        document.addEventListener('keydown', (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                if (modal.classList.contains('is-open')) {
                    closePalette();
                } else {
                    openPalette();
                }
            } else if (event.key === 'Escape') {
                closePalette();
            }
        });
    }

    /* =========================================================
       Create Post Live Feed Card Preview
       ========================================================= */
    function initCreateLivePreview() {
        const preview = document.getElementById('create-live-preview');
        const form = document.querySelector('form[data-post-create-form]');
        if (!preview || !form) return;

        const lpImg = document.getElementById('lp-img');
        const lpPlaceholder = document.getElementById('lp-placeholder');
        const lpPlaceholderIcon = document.getElementById('lp-placeholder-icon');
        const lpTypeDot = document.getElementById('lp-type-dot');
        const lpTypeLabel = document.getElementById('lp-type-label');
        const lpStatusPill = document.getElementById('lp-status-pill');
        const lpSensPill = document.getElementById('lp-sensitive-pill');
        const lpCustodyOverlay = document.getElementById('lp-custody-overlay');
        const lpCustodyText = document.getElementById('lp-custody-text');
        const lpCategory = document.getElementById('lp-category');
        const lpTitle = document.getElementById('lp-title');
        const lpDesc = document.getElementById('lp-desc');
        const lpBuilding = document.getElementById('lp-building');
        const lpDate = document.getElementById('lp-date');
        const lpTags = document.getElementById('lp-tags');

        const photoInput = form.querySelector('#photo');
        const sensCheckbox = form.querySelector('input[name="is_sensitive"]');

        const syncCardText = () => {
            const selectedType = form.querySelector('.type-radio:checked')?.value || 'found';
            const isFound = selectedType === 'found';

            if (lpTypeDot) lpTypeDot.style.background = isFound ? 'var(--emerald-found)' : 'var(--rose-lost)';
            if (lpTypeLabel) lpTypeLabel.textContent = isFound ? 'Found' : 'Lost';
            if (lpStatusPill) lpStatusPill.className = 'badge ' + (isFound ? 'badge-found-open' : 'badge-lost-open');
            if (lpPlaceholder) lpPlaceholder.className = 'post-media-placeholder ' + (isFound ? 'is-found' : 'is-lost');
            if (lpPlaceholderIcon) lpPlaceholderIcon.style.color = isFound ? 'var(--primary-navy)' : 'var(--rose-lost)';

            const isSens = Boolean(sensCheckbox && sensCheckbox.checked);
            if (lpSensPill) lpSensPill.hidden = !isSens;
            if (lpImg) lpImg.classList.toggle('sensitive-blur', isSens);

            const catSelect = form.querySelector('#category_id');
            const customCatInput = form.querySelector('#custom_category');
            if (lpCategory && catSelect) {
                const selectedOpt = catSelect.options[catSelect.selectedIndex];
                const isOther = Boolean(selectedOpt && selectedOpt.dataset.isOther === 'true');
                if (isOther) {
                    const customText = customCatInput?.value.trim();
                    lpCategory.textContent = customText || 'Others';
                } else {
                    lpCategory.textContent = catSelect.value ? selectedOpt.text : 'Category';
                }
            }

            const titleVal = form.querySelector('#title')?.value.trim();
            if (lpTitle) lpTitle.textContent = titleVal || 'Your item title...';

            const descVal = form.querySelector('#description')?.value.trim();
            if (lpDesc) lpDesc.textContent = descVal || 'Item traits, brand, color, and helpful identifying notes will show here.';

            const bldVal = form.querySelector('#building')?.value;
            if (lpBuilding) lpBuilding.textContent = bldVal || 'Building';

            const dateVal = form.querySelector('#event_date')?.value;
            if (lpDate && dateVal) lpDate.textContent = dateVal;

            const custodyVal = form.querySelector('#custody_location')?.value;
            if (lpCustodyOverlay && lpCustodyText) {
                lpCustodyOverlay.hidden = !(isFound && custodyVal);
                lpCustodyText.textContent = custodyVal || '';
            }

            if (lpTags) {
                const rawTags = (form.querySelector('#tags')?.value || '')
                    .split(',')
                    .map((t) => t.replace(/^#+/, '').trim().toLowerCase())
                    .filter(Boolean);
                lpTags.innerHTML = '';
                [...new Set(rawTags)].slice(0, 5).forEach((t) => {
                    const pill = document.createElement('span');
                    pill.className = 'tag-pill';
                    pill.textContent = '#' + t;
                    lpTags.appendChild(pill);
                });
            }
        };

        if (photoInput && lpImg && lpPlaceholder) {
            photoInput.addEventListener('change', () => {
                const file = photoInput.files && photoInput.files[0];
                if (!file || !file.type || !file.type.startsWith('image/')) {
                    lpImg.style.display = 'none';
                    lpPlaceholder.style.display = 'flex';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    lpImg.src = e.target.result;
                    lpImg.style.display = 'block';
                    lpPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });
        }

        form.addEventListener('input', syncCardText);
        form.addEventListener('change', syncCardText);
        syncCardText();
    }

    /* =========================================================
       Instant Table Filtering & Audit Action Click-to-Filter
       ========================================================= */
    function initTableFiltersAndAuditActions() {
        document.querySelectorAll('[data-table-filter-group]').forEach((group) => {
            const searchInput = group.querySelector('[data-table-search]');
            const statusBtns = group.querySelectorAll('[data-table-status]');
            const rows = group.querySelectorAll('[data-table-row]');
            const emptyRow = group.querySelector('[data-table-empty]');
            let currentStatus = 'all';

            const applyFilter = () => {
                const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
                let visible = 0;
                rows.forEach((row) => {
                    const matchesStatus = currentStatus === 'all' || (row.dataset.rowStatus || '') === currentStatus;
                    const matchesSearch = !term || (row.textContent || '').toLowerCase().includes(term);
                    const show = matchesStatus && matchesSearch;
                    row.style.display = show ? '' : 'none';
                    if (show) visible += 1;
                });
                if (emptyRow) emptyRow.hidden = visible > 0;
            };

            if (searchInput) searchInput.addEventListener('input', applyFilter);
            statusBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    currentStatus = btn.dataset.tableStatus || 'all';
                    statusBtns.forEach((b) => b.classList.toggle('is-active', b === btn));
                    applyFilter();
                });
            });
        });

        document.addEventListener('click', (event) => {
            const badge = event.target.closest('[data-filter-action]');
            if (!badge) return;
            const actionSelect = document.getElementById('action');
            if (!actionSelect || !actionSelect.form) return;
            actionSelect.value = badge.dataset.filterAction || '';
            progressBar.start();
            actionSelect.form.submit();
        });
    }

    /* =========================================================
       Theme Switcher (Light / Dark Mode)
       ========================================================= */
    function applyTheme(theme, persist = true) {
        const next = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.dataset.theme = next;
        if (persist) {
            try {
                window.localStorage.setItem('lostracer-theme', next);
            } catch {
                // Storage blocked
            }
        }

        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            const isDark = next === 'dark';
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        });
    }

    function initThemeToggle() {
        const current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
        applyTheme(current, false);

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-theme-toggle]');
            if (!btn) return;
            const nowDark = document.documentElement.dataset.theme === 'dark';
            const next = nowDark ? 'light' : 'dark';
            applyTheme(next, true);
            toast(next === 'dark' ? 'Dark mode enabled' : 'Light mode enabled', 'info', 2000);
        });

        if (window.matchMedia) {
            const mq = window.matchMedia('(prefers-color-scheme: dark)');
            if (typeof mq.addEventListener === 'function') {
                mq.addEventListener('change', (event) => {
                    try {
                        if (window.localStorage.getItem('lostracer-theme')) return;
                    } catch {
                        // Ignore storage errors
                    }
                    applyTheme(event.matches ? 'dark' : 'light', false);
                });
            }
        }
    }

    /* =========================================================
       Boot
       ========================================================= */
    function boot() {
        initThemeToggle();
        initAlerts();
        initScrollChrome();
        initButtonRipples();
        initNavigation();
        initConfirmModal();
        initFormGuards();
        initAuthControls();
        initLiveFeed();
        initViewModeSwitcher();
        initFiltersAndShortcuts();
        initItemForms();
        initCreateLivePreview();
        initFileUploads();
        initLightbox();
        initCopyAndPrintButtons();
        initWatchlistAndDrawers();
        initCommandPalette();
        initTableFiltersAndAuditActions();
        initLiveChat();
    }

    window.Lostracer = {
        toast,
        openConfirmModal,
        closeConfirmModal,
        progress: progressBar,
        setTheme: applyTheme,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
