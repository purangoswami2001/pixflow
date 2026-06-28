/**
 * Live updates for automation pipeline run detail and log stream pages.
 * Polls JSON feeds — no full page reload.
 */
(function () {
    const STATUS_CLASSES = {
        completed: 'pp-status-badge--success',
        failed: 'pp-status-badge--danger',
        running: 'pp-status-badge--running',
    };

    function itemStatusClass(status) {
        if (status === 'post_created') return 'pp-status-badge--success';
        if (status === 'failed') return 'pp-status-badge--danger';
        if (status.startsWith('generating_')) return 'pp-status-badge--running';
        return 'pp-status-badge--muted';
    }

    function preserveDetailsState(container) {
        const open = [];
        container.querySelectorAll('details[open]').forEach((el) => {
            const summary = el.querySelector('summary')?.textContent?.trim();
            if (summary) open.push(summary);
        });
        return open;
    }

    function restoreDetailsState(container, openSummaries) {
        if (!openSummaries.length) return;
        container.querySelectorAll('details').forEach((el) => {
            const summary = el.querySelector('summary')?.textContent?.trim();
            if (summary && openSummaries.includes(summary)) {
                el.open = true;
            }
        });
    }

    function setLiveIndicator(el, active) {
        if (!el) return;
        el.classList.toggle('is-paused', !active);
        el.title = active ? 'Auto-updating every few seconds' : 'Run finished — slow refresh';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function buildLogEntryHtml(entry, logsPageUrl) {
        const ctx = entry.context || {};
        let links = '';
        if (ctx.item_id) {
            links += `<a href="${logsPageUrl}?item_id=${ctx.item_id}" class="text-[#004ac6] font-semibold">#${ctx.item_id}</a>`;
        }
        if (ctx.provider) {
            links += `<span class="pp-automation-badge">${escapeHtml(ctx.provider)}</span>`;
        }
        if (ctx.duration_ms) {
            links += `<span class="text-xs text-[#737686]">${ctx.duration_ms}ms</span>`;
        }

        let body = '';
        if (Object.keys(ctx).length) {
            body = `<details>
                <summary class="cursor-pointer text-[#004ac6] font-sans font-semibold text-xs mb-2">Expand payload</summary>
                <pre class="pp-automation-log-pre">${escapeHtml(JSON.stringify(ctx, null, 2))}</pre>
            </details>`;
        } else if ((entry.raw || '').length > 120) {
            body = `<details>
                <summary class="cursor-pointer text-[#737686] font-sans text-xs">Full entry</summary>
                <pre class="pp-automation-log-pre mt-2">${escapeHtml(entry.raw)}</pre>
            </details>`;
        }

        return `<div class="p-4 hover:bg-[#0f172a]/[0.02] transition-colors pp-log-entry pp-log-entry--new" data-log-hash="${entry.hash}">
            <div class="flex flex-wrap gap-2 items-center mb-2 not-italic font-sans">
                <span class="text-[#737686]">${escapeHtml(entry.at)}</span>
                <span class="pp-automation-step-badge">${escapeHtml(entry.message)}</span>
                ${links}
            </div>
            ${body}
        </div>`;
    }

    function updateItemHeader(article, item, root) {
        const badge = article.querySelector('[data-item-status-badge]');
        if (badge) {
            badge.textContent = item.status_label;
            badge.className = `pp-status-badge ${itemStatusClass(item.status)}`;
        }

        const actions = article.querySelector('[data-item-actions]');
        if (!actions) return;

        const retryBase = root.dataset.retryUrl || '/admin/automation/run-items';
        const postBase = root.dataset.postEditUrl || '/posts';
        const logsBase = root.dataset.logsUrl || '/admin/automation/logs';
        const runId = root.dataset.runId;
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

        let html = '';
        if (item.status === 'failed') {
            html += `<form action="${retryBase}/${item.id}/retry" method="POST">
                <input type="hidden" name="_token" value="${token}">
                <button type="submit" class="pp-btn-primary text-xs py-2 px-3">Retry</button>
            </form>`;
        }
        if (item.post_id) {
            html += `<a href="${postBase}/${item.post_id}/edit" class="pp-btn-secondary text-xs py-2 px-3">Open post</a>`;
        }
        html += `<a href="${logsBase}?run_id=${runId}&item_id=${item.id}" class="pp-btn-secondary text-xs py-2 px-3">Logs</a>`;
        actions.innerHTML = html;
    }

    function updateRunSummary(run) {
        const statusEl = document.querySelector('[data-run-status]');
        if (statusEl) {
            statusEl.textContent = run.status;
            statusEl.className = `pp-status-badge ${STATUS_CLASSES[run.status] || 'pp-status-badge--muted'} text-sm px-3 py-1`;
        }

        const created = document.querySelector('[data-run-created]');
        const planned = document.querySelector('[data-run-planned]');
        const progress = document.querySelector('[data-run-progress]');
        if (created) created.textContent = run.created_count;
        if (planned) planned.textContent = run.planned_count;
        if (progress) progress.style.width = `${run.progress_pct}%`;

        const failedWrap = document.querySelector('[data-run-failed-wrap]');
        const failedCount = document.querySelector('[data-run-failed]');
        if (failedWrap && failedCount) {
            failedCount.textContent = run.failed_count;
            failedWrap.classList.toggle('hidden', run.failed_count <= 0);
        }
    }

    const AutomationLive = {
        initRunDetail(root) {
            if (!root) return;

            const feedUrl = root.dataset.feedUrl;
            const indicator = document.getElementById('pp-run-live-indicator');
            let timer = null;
            let polling = false;
            let pollInterval = 3000;

            const poll = async () => {
                if (polling) return;
                polling = true;

                try {
                    const res = await fetch(feedUrl, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();

                    updateRunSummary(data.run);
                    setLiveIndicator(indicator, data.run.is_active);

                    const nextInterval = data.poll_interval || (data.run.is_active ? 3000 : 15000);
                    if (nextInterval !== pollInterval) {
                        pollInterval = nextInterval;
                        if (timer) {
                            clearInterval(timer);
                            timer = setInterval(poll, pollInterval);
                        }
                    }

                    (data.items || []).forEach((item) => {
                        const article = root.querySelector(`[data-run-item-id="${item.id}"]`);
                        if (!article) return;

                        updateItemHeader(article, item, root);

                        if (article.dataset.runItemRevision === item.revision) return;

                        const live = article.querySelector('[data-run-item-live]');
                        if (!live) return;

                        const openDetails = preserveDetailsState(live);
                        live.innerHTML = item.html;
                        restoreDetailsState(live, openDetails);
                        article.dataset.runItemRevision = item.revision;
                    });
                } catch (e) {
                    console.warn('Run feed poll failed', e);
                } finally {
                    polling = false;
                }
            };

            const schedule = () => {
                if (timer) clearInterval(timer);
                timer = setInterval(poll, pollInterval);
            };

            poll().then(schedule);

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (timer) clearInterval(timer);
                    timer = null;
                } else {
                    poll().then(() => {
                        const next = pollInterval;
                        if (timer) clearInterval(timer);
                        timer = setInterval(poll, next);
                    });
                }
            });
        },

        initLogStream(root) {
            if (!root) return;

            const feedUrl = root.dataset.feedUrl;
            const logsPageUrl = root.dataset.logsPageUrl || '/admin/automation/logs';
            const runId = root.dataset.runId;
            const itemId = root.dataset.itemId;
            const container = document.getElementById('pp-log-entries');
            const meta = document.getElementById('pp-log-meta');
            const indicator = document.getElementById('pp-log-live-indicator');
            const knownHashes = new Set();

            container?.querySelectorAll('[data-log-hash]').forEach((el) => {
                knownHashes.add(el.dataset.logHash);
            });

            let timer = null;
            let polling = false;

            const poll = async () => {
                if (polling) return;
                polling = true;

                try {
                    const params = new URLSearchParams();
                    if (runId) params.set('run_id', runId);
                    if (itemId) params.set('item_id', itemId);
                    if (knownHashes.size) {
                        params.set('exclude_hashes', [...knownHashes].slice(-200).join(','));
                    }

                    const res = await fetch(`${feedUrl}?${params}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();

                    if (meta) {
                        meta.textContent = `${data.log_file || 'automation.log'} · ${data.total} entries`;
                    }

                    setLiveIndicator(indicator, true);

                    const wasAtTop = container && container.scrollTop < 40;
                    let added = 0;

                    (data.entries || []).forEach((entry) => {
                        if (knownHashes.has(entry.hash)) return;
                        knownHashes.add(entry.hash);
                        added++;

                        const empty = document.getElementById('pp-log-empty');
                        if (empty) empty.remove();

                        container.insertAdjacentHTML('afterbegin', buildLogEntryHtml(entry, logsPageUrl));
                    });

                    if (added && wasAtTop && container) {
                        container.scrollTop = 0;
                    }

                    requestAnimationFrame(() => {
                        container?.querySelectorAll('.pp-log-entry--new').forEach((el) => {
                            el.classList.remove('pp-log-entry--new');
                        });
                    });
                } catch (e) {
                    console.warn('Log feed poll failed', e);
                } finally {
                    polling = false;
                }
            };

            const schedule = () => {
                if (timer) clearInterval(timer);
                poll();
                timer = setInterval(poll, 3000);
            };

            schedule();

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (timer) clearInterval(timer);
                } else {
                    schedule();
                }
            });
        },
    };

    window.AutomationLive = AutomationLive;
})();
