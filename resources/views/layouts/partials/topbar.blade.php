<header class="pp-topbar">
    <div class="flex items-center gap-4">
        <button type="button" class="pp-topbar-icon-btn lg:hidden" aria-label="Open menu">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="relative hidden sm:block">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[var(--pp-text-muted)] text-sm">search</span>
            <input type="text" class="pp-topbar-search" placeholder="Search...">
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button type="button"
                id="ppThemeToggle"
                class="pp-topbar-icon-btn pp-theme-toggle"
                data-mode="{{ auth()->user()->themePreference() === 'dark' ? 'dark' : 'light' }}"
                title="Toggle theme"
                aria-label="Toggle theme"
                onclick="window.ppToggleTheme(event)">
            <span class="material-symbols-outlined pp-theme-icon-light">dark_mode</span>
            <span class="material-symbols-outlined pp-theme-icon-dark">light_mode</span>
        </button>

        <div class="relative" id="ppNotifications">
            <button type="button"
                    id="ppNotificationsBtn"
                    class="pp-topbar-icon-btn relative"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-label="Notifications"
                    onclick="window.ppToggleNotifications(event)">
                <span class="material-symbols-outlined">notifications</span>
                <span id="ppNotificationsBadge"
                      class="absolute top-2 right-2 min-w-[0.5rem] h-2 px-0 bg-[#ba1a1a] rounded-full border-2 border-white hidden"
                      data-count="0"></span>
            </button>

            <div id="ppNotificationsDropdown"
                 class="pp-notifications-dropdown hidden"
                 role="menu"
                 aria-label="Notifications">
                <div class="pp-notifications-head">
                    <div>
                        <p class="pp-notifications-title">Notifications</p>
                        <p class="pp-notifications-sub" id="ppNotificationsSub">Loading…</p>
                    </div>
                    <a href="{{ route('notifications.page') }}" class="pp-notifications-markall">View all</a>
                </div>
                <div id="ppNotificationsList" class="pp-notifications-list">
                    <div class="pp-notifications-empty">Loading notifications…</div>
                </div>
                <div class="pp-notifications-foot">
                    <a href="{{ route('notifications.page') }}" class="pp-notifications-viewall">View all notifications</a>
                </div>
            </div>
        </div>
        <div id="ppNotificationsBackdrop" class="pp-notifications-backdrop hidden" aria-hidden="true"></div>

        <button type="button" class="pp-topbar-icon-btn pp-topbar-help" title="Help">
            <span class="material-symbols-outlined">help</span>
        </button>
        <div class="pp-topbar-divider hidden sm:block"></div>

        <div class="relative" id="ppUserMenu">
            <button type="button"
                    id="ppUserMenuBtn"
                    class="pp-topbar-user-btn"
                    aria-haspopup="true"
                    aria-expanded="false"
                    onclick="window.ppToggleUserMenu(event)">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-[#2563eb] text-white flex items-center justify-center font-bold text-sm border-2 border-[#dbe1ff] shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="text-left hidden sm:block min-w-0">
                    <p class="pp-topbar-user-name truncate max-w-[140px]">{{ auth()->user()->name }}</p>
                    <p class="pp-topbar-user-role">{{ auth()->user()->roleLabel() }}</p>
                </div>
                <span class="material-symbols-outlined text-[var(--pp-text-muted)] text-[18px] hidden sm:inline" id="ppUserMenuChevron">expand_more</span>
            </button>

            <div id="ppUserMenuDropdown"
                 class="pp-topbar-menu hidden"
                 role="menu">
                <div class="px-4 py-2 border-b border-[var(--pp-border-soft)] mb-1">
                    <p class="text-sm font-semibold text-[var(--pp-text)] truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-[var(--pp-text-muted)] truncate">{{ auth()->user()->email }}</p>
                </div>
                <a href="{{ route('profile.edit') }}"
                   class="pp-topbar-menu-link"
                   role="menuitem">
                    <span class="material-symbols-outlined text-[18px]">settings</span>
                    Profile Settings
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full pp-topbar-menu-link text-[var(--pp-danger)] hover:bg-[var(--pp-danger-soft)]"
                            role="menuitem">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<script>
window.ppToggleUserMenu = function (event) {
    event.stopPropagation();
    var dropdown = document.getElementById('ppUserMenuDropdown');
    var btn = document.getElementById('ppUserMenuBtn');
    var chevron = document.getElementById('ppUserMenuChevron');
    if (!dropdown || !btn) return;

    document.getElementById('ppNotificationsDropdown')?.classList.add('hidden');
    document.getElementById('ppNotificationsBtn')?.setAttribute('aria-expanded', 'false');
    document.getElementById('ppNotificationsBackdrop')?.classList.add('hidden');
    document.body.classList.remove('pp-notifications-open');

    var isOpen = !dropdown.classList.contains('hidden');
    dropdown.classList.toggle('hidden', isOpen);
    btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    if (chevron) chevron.textContent = isOpen ? 'expand_more' : 'expand_less';
};

(function () {
    var endpoints = {
        list: @json(route('notifications.feed', ['limit' => 10])),
        page: @json(route('notifications.page')),
        readOneBase: @json(url('/api/notifications')),
    };
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var loaded = false;
    var lastLatestId = Number(localStorage.getItem('pp_notif_latest_id') || 0);
    var audioCtx = null;
    var pollMs = 20000;
    var storageKeySeen = 'pp_notif_latest_id';

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ensureAudio() {
        if (!audioCtx) {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(function () {});
        }
        return audioCtx;
    }

    function playNotifySound() {
        try {
            var ctx = ensureAudio();
            if (!ctx) return;
            var now = ctx.currentTime;
            [880, 1174].forEach(function (freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.08, now + 0.02 + i * 0.08);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22 + i * 0.1);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + i * 0.08);
                osc.stop(now + 0.28 + i * 0.1);
            });
        } catch (e) {}
    }

    function requestBrowserPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission().catch(function () {});
        }
    }

    function showBrowserPush(item) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        try {
            var n = new Notification(item.title || 'PixFlow', {
                body: item.body || 'You have a new update.',
                icon: @json(asset('favicon.ico')),
                tag: 'pixflow-notif-' + (item.id || Date.now()),
            });
            n.onclick = function () {
                window.focus();
                if (item.link) window.location.href = item.link;
                n.close();
            };
            setTimeout(function () { n.close(); }, 6000);
        } catch (e) {}
    }

    function setBadge(count) {
        var badge = document.getElementById('ppNotificationsBadge');
        var sub = document.getElementById('ppNotificationsSub');
        if (!badge) return;
        badge.dataset.count = String(count);
        if (count > 0) {
            badge.classList.remove('hidden');
            badge.textContent = count > 9 ? '9+' : '';
            if (count > 9) badge.classList.add('is-count');
            else badge.classList.remove('is-count');
        } else {
            badge.classList.add('hidden');
            badge.classList.remove('is-count');
            badge.textContent = '';
        }
        if (sub) {
            sub.textContent = count > 0 ? (count + ' unread') : 'Latest 10';
        }
    }

    function levelClass(level) {
        if (level === 'success') return 'pp-notif-level--success';
        if (level === 'danger') return 'pp-notif-level--danger';
        if (level === 'warning') return 'pp-notif-level--warning';
        return 'pp-notif-level--info';
    }

    function renderList(items) {
        var list = document.getElementById('ppNotificationsList');
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<div class="pp-notifications-empty">No notifications yet.</div>';
            return;
        }

        list.innerHTML = items.map(function (n) {
            var href = n.link || endpoints.page;
            var unread = n.unread ? ' is-unread' : '';
            return '<a href="' + escapeHtml(href) + '" class="pp-notif-item' + unread + '" data-id="' + n.id + '" data-unread="' + (n.unread ? '1' : '0') + '">' +
                '<span class="pp-notif-icon ' + levelClass(n.level) + '"><span class="material-symbols-outlined">' + escapeHtml(n.icon) + '</span></span>' +
                '<span class="pp-notif-body">' +
                    '<span class="pp-notif-title">' + escapeHtml(n.title) + '</span>' +
                    (n.body ? '<span class="pp-notif-text">' + escapeHtml(n.body) + '</span>' : '') +
                    '<span class="pp-notif-time">' + escapeHtml(n.created_at_human || '') + '</span>' +
                '</span>' +
            '</a>';
        }).join('');
    }

    async function fetchNotifications(opts) {
        opts = opts || {};
        var list = document.getElementById('ppNotificationsList');
        var dropdownOpen = !document.getElementById('ppNotificationsDropdown')?.classList.contains('hidden');
        if (list && !loaded && dropdownOpen) {
            list.innerHTML = '<div class="pp-notifications-empty">Loading notifications…</div>';
        }
        try {
            var res = await fetch(endpoints.list, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();
            var items = data.notifications || [];
            var latestId = Number(data.latest_id || 0);
            var unread = data.unread_count || 0;

            if (opts.alert && latestId > lastLatestId && lastLatestId > 0) {
                var newest = items.find(function (n) { return Number(n.id) === latestId; }) || items[0];
                if (newest) {
                    playNotifySound();
                    showBrowserPush(newest);
                }
            }

            if (latestId > 0) {
                lastLatestId = latestId;
                localStorage.setItem(storageKeySeen, String(latestId));
            }

            setBadge(unread);
            if (dropdownOpen || !loaded) {
                renderList(items);
            }
            loaded = true;
            return data;
        } catch (e) {
            if (list && dropdownOpen) {
                list.innerHTML = '<div class="pp-notifications-empty">Could not load notifications.</div>';
            }
            return null;
        }
    }

    async function markRead(id) {
        try {
            var res = await fetch(endpoints.readOneBase + '/' + id + '/read', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            var data = await res.json();
            setBadge(data.unread_count || 0);
        } catch (e) {}
    }

    function setNotificationsOpen(open) {
        var dropdown = document.getElementById('ppNotificationsDropdown');
        var btn = document.getElementById('ppNotificationsBtn');
        var backdrop = document.getElementById('ppNotificationsBackdrop');
        if (dropdown) dropdown.classList.toggle('hidden', !open);
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (backdrop) backdrop.classList.toggle('hidden', !open);
        document.body.classList.toggle('pp-notifications-open', !!open);
    }

    window.ppToggleNotifications = function (event) {
        event.stopPropagation();
        var dropdown = document.getElementById('ppNotificationsDropdown');
        if (!dropdown) return;

        document.getElementById('ppUserMenuDropdown')?.classList.add('hidden');
        document.getElementById('ppUserMenuBtn')?.setAttribute('aria-expanded', 'false');

        var willOpen = dropdown.classList.contains('hidden');
        setNotificationsOpen(willOpen);
        if (willOpen) {
            requestBrowserPermission();
            ensureAudio();
            fetchNotifications({ alert: false });
        }
    };

    document.getElementById('ppNotificationsBackdrop')?.addEventListener('click', function () {
        setNotificationsOpen(false);
    });

    document.getElementById('ppNotificationsList')?.addEventListener('click', function (e) {
        var item = e.target.closest('.pp-notif-item');
        if (!item) return;
        if (item.dataset.unread === '1') {
            markRead(item.dataset.id);
            item.classList.remove('is-unread');
            item.dataset.unread = '0';
        }
    });

    document.addEventListener('click', function (event) {
        var menu = document.getElementById('ppUserMenu');
        var dropdown = document.getElementById('ppUserMenuDropdown');
        var btn = document.getElementById('ppUserMenuBtn');
        var chevron = document.getElementById('ppUserMenuChevron');
        if (menu && dropdown && !dropdown.classList.contains('hidden') && !menu.contains(event.target)) {
            dropdown.classList.add('hidden');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            if (chevron) chevron.textContent = 'expand_more';
        }

        var nRoot = document.getElementById('ppNotifications');
        var nDrop = document.getElementById('ppNotificationsDropdown');
        if (nRoot && nDrop && !nDrop.classList.contains('hidden') && !nRoot.contains(event.target)) {
            setNotificationsOpen(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        setNotificationsOpen(false);
        var dropdown = document.getElementById('ppUserMenuDropdown');
        var btn = document.getElementById('ppUserMenuBtn');
        var chevron = document.getElementById('ppUserMenuChevron');
        if (dropdown) dropdown.classList.add('hidden');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        if (chevron) chevron.textContent = 'expand_more';
    });

    // Unlock audio after first user gesture anywhere
    ['pointerdown', 'keydown'].forEach(function (evt) {
        document.addEventListener(evt, function once() {
            ensureAudio();
            requestBrowserPermission();
            document.removeEventListener(evt, once, true);
        }, true);
    });

    fetchNotifications({ alert: false });
    setInterval(function () {
        fetchNotifications({ alert: true });
    }, pollMs);
})();
</script>
