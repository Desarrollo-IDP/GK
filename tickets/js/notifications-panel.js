/**
 * Panel de notificaciones — lecturas persistidas en servidor
 */
(function () {
    'use strict';

    const POLL_MS = 30000;
    const cfg = window.TICKETS_NOTIF_CONFIG || {};
    const apiUrl = cfg.apiUrl || '';
    const viewBase = cfg.viewBase || '';
    const userId = String(cfg.userId || '0');
    const legacyStorageKey = 'tickets_notif_read_' + userId;
    const migratedKey = 'tickets_notif_migrated_' + userId;

    const els = {
        bell: null,
        badge: null,
        overlay: null,
        panel: null,
        list: null,
        markAll: null,
        closeBtn: null,
    };

    let items = [];
    let serverUnreadCount = null;
    let pollTimer = null;
    let isOpen = false;
    let marking = false;
    let legacyMigrated = false;

    function getLegacyReadIds() {
        try {
            const raw = localStorage.getItem(legacyStorageKey);
            const arr = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(arr)) return [];
            return arr.map(Number).filter(function (id) {
                return id > 0;
            });
        } catch (e) {
            return [];
        }
    }

    function clearLegacyStorage() {
        try {
            localStorage.removeItem(legacyStorageKey);
            localStorage.setItem(migratedKey, '1');
        } catch (e) {
            /* ignore */
        }
    }

    function postMark(payload) {
        if (!apiUrl) {
            return Promise.reject(new Error('API no configurada'));
        }
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(function (data) {
            if (!data || !data.ok) {
                throw new Error((data && data.error) || 'Error al marcar');
            }
            return data;
        });
    }

    function applyLocalRead(ids) {
        const set = new Set((ids || []).map(String));
        let newlyRead = 0;
        items = items.map(function (item) {
            if (set.has(String(item.id)) && !item.is_read) {
                newlyRead++;
                return Object.assign({}, item, { is_read: true });
            }
            return item;
        });
        if (serverUnreadCount != null && newlyRead > 0) {
            serverUnreadCount = Math.max(0, serverUnreadCount - newlyRead);
        }
    }

    function localUnreadCount() {
        return items.filter(function (item) {
            return !item.is_read;
        }).length;
    }

    function unreadCount() {
        const local = localUnreadCount();
        if (serverUnreadCount == null) return local;
        // Si el servidor reporta más no leídas que las del listado, usamos ese total
        return Math.max(local, serverUnreadCount);
    }

    function updateBadge() {
        if (!els.badge) return;
        const n = unreadCount();
        const hasLocalUnread = localUnreadCount() > 0;
        if (n > 0) {
            els.badge.textContent = n > 99 ? '99+' : String(n);
            els.badge.classList.add('is-visible');
            els.badge.setAttribute('aria-hidden', 'false');
        } else {
            els.badge.textContent = '';
            els.badge.classList.remove('is-visible');
            els.badge.setAttribute('aria-hidden', 'true');
        }
        if (els.markAll) {
            els.markAll.disabled = marking || (!hasLocalUnread && n === 0);
        }
    }

    function formatWhen(iso, fallback) {
        const raw = iso || fallback;
        if (!raw) return '';
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return fallback || '';
        const now = new Date();
        const diff = (now - d) / 1000;
        if (diff < 60) return 'Ahora';
        if (diff < 3600) return Math.floor(diff / 60) + ' min';
        if (diff < 86400) return Math.floor(diff / 3600) + ' h';
        return d.toLocaleString('es-MX', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function channelClass(channel) {
        if (channel === 'whatsapp') return 'is-whatsapp';
        if (channel === 'email') return 'is-email';
        return '';
    }

    function channelIcon(channel) {
        if (channel === 'whatsapp') return 'fab fa-whatsapp';
        if (channel === 'email') return 'fas fa-envelope';
        return 'fas fa-comment';
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render() {
        if (!els.list) return;

        if (!items.length) {
            els.list.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash mb-2 d-block"></i>No hay mensajes recientes</div>';
            updateBadge();
            return;
        }

        els.list.innerHTML = items.map(function (item) {
            const unread = !item.is_read;
            const href = viewBase + encodeURIComponent(item.ticket_id);
            const subject = item.ticket_subject
                ? ' · ' + escapeHtml(item.ticket_subject).slice(0, 60)
                : '';
            return (
                '<div class="notif-item' + (unread ? ' is-unread' : '') + '" data-notif-id="' + item.id + '" data-ticket-id="' + item.ticket_id + '">' +
                    '<a class="notif-item-link" href="' + href + '">' +
                        '<div class="notif-item-top">' +
                            '<span class="notif-ticket">' +
                                (unread ? '<span class="notif-dot" aria-hidden="true"></span>' : '') +
                                'Ticket #' + escapeHtml(item.ticket_number) + subject +
                            '</span>' +
                            '<span class="notif-channel ' + channelClass(item.channel) + '">' +
                                '<i class="' + channelIcon(item.channel) + '"></i> ' + escapeHtml(item.channel_label) +
                            '</span>' +
                        '</div>' +
                        '<div class="notif-sender">' + escapeHtml(item.sender_name) + '</div>' +
                        '<div class="notif-preview">' + escapeHtml(item.preview) + '</div>' +
                        '<div class="notif-meta">' + escapeHtml(formatWhen(item.created_at_iso, item.created_at)) + '</div>' +
                    '</a>' +
                    (unread
                        ? '<button type="button" class="notif-mark-one" title="Marcar como leída" aria-label="Marcar como leída" data-mark-id="' + item.id + '">' +
                            '<i class="fas fa-check"></i>' +
                          '</button>'
                        : '') +
                '</div>'
            );
        }).join('');

        updateBadge();
    }

    function markReadIds(ids, opts) {
        const options = opts || {};
        const cleanIds = (ids || []).map(Number).filter(function (id) {
            return id > 0;
        });
        if (!cleanIds.length) {
            return Promise.resolve({ marked: 0 });
        }

        applyLocalRead(cleanIds);
        render();

        return postMark({
            action: 'mark_read',
            message_ids: cleanIds,
        }).catch(function (err) {
            console.error('Marcar leídas:', err);
            if (!options.silent) {
                fetchNotifications(false);
            }
            throw err;
        });
    }

    function markAllRead() {
        if (marking) return;
        const unreadIds = items.filter(function (item) {
            return !item.is_read;
        }).map(function (item) {
            return item.id;
        });
        if (!unreadIds.length) return;

        marking = true;
        updateBadge();
        if (els.markAll) {
            els.markAll.textContent = 'Marcando…';
        }

        applyLocalRead(unreadIds);
        render();

        postMark({ action: 'mark_all', limit: 100 })
            .then(function () {
                items = items.map(function (item) {
                    return Object.assign({}, item, { is_read: true });
                });
                serverUnreadCount = 0;
                render();
            })
            .catch(function (err) {
                console.error('Marcar todas:', err);
                fetchNotifications(false);
            })
            .finally(function () {
                marking = false;
                if (els.markAll) {
                    els.markAll.textContent = 'Marcar leídas';
                }
                updateBadge();
            });
    }

    function markTicketFromPage() {
        try {
            const params = new URLSearchParams(window.location.search || '');
            const path = String(window.location.pathname || '');
            if (!/view\.php$/i.test(path)) return;
            const ticketId = parseInt(params.get('id') || '0', 10);
            if (!ticketId) return;

            postMark({ action: 'mark_ticket', ticket_id: ticketId })
                .then(function (data) {
                    if (data && data.marked > 0) {
                        fetchNotifications(false);
                    }
                })
                .catch(function () {
                    /* silencioso */
                });
        } catch (e) {
            /* ignore */
        }
    }

    function migrateLegacyReads() {
        if (legacyMigrated) return Promise.resolve();
        try {
            if (localStorage.getItem(migratedKey) === '1') {
                legacyMigrated = true;
                return Promise.resolve();
            }
        } catch (e) {
            /* ignore */
        }

        const legacyIds = getLegacyReadIds();
        if (!legacyIds.length) {
            clearLegacyStorage();
            legacyMigrated = true;
            return Promise.resolve();
        }

        return postMark({ action: 'mark_read', message_ids: legacyIds })
            .then(function () {
                clearLegacyStorage();
                legacyMigrated = true;
            })
            .catch(function () {
                /* reintentará en otro ciclo */
            });
    }

    function openPanel() {
        isOpen = true;
        if (els.overlay) {
            els.overlay.classList.add('is-open');
            els.overlay.setAttribute('aria-hidden', 'false');
        }
        if (els.panel) {
            els.panel.classList.add('is-open');
            els.panel.setAttribute('aria-hidden', 'false');
        }
        document.body.style.overflow = 'hidden';
        fetchNotifications(true);
    }

    function closePanel() {
        isOpen = false;
        if (els.overlay) {
            els.overlay.classList.remove('is-open');
            els.overlay.setAttribute('aria-hidden', 'true');
        }
        if (els.panel) {
            els.panel.classList.remove('is-open');
            els.panel.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    function fetchNotifications(showLoading) {
        if (!apiUrl || !els.list) return;
        if (showLoading && !items.length) {
            els.list.innerHTML = '<div class="notif-loading"><i class="fas fa-spinner fa-spin me-1"></i>Cargando…</div>';
        }

        fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.error) || 'Error al cargar');
                }
                items = (Array.isArray(data.items) ? data.items : []).map(function (item) {
                    return Object.assign({}, item, {
                        is_read: !!item.is_read,
                    });
                });
                if (typeof data.unread_count === 'number') {
                    serverUnreadCount = data.unread_count;
                }
                render();
            })
            .catch(function (err) {
                console.error('Notificaciones:', err);
                if (!items.length && els.list) {
                    els.list.innerHTML = '<div class="notif-error">No se pudieron cargar las notificaciones</div>';
                }
            });
    }

    function bind() {
        els.bell = document.getElementById('notifBellBtn');
        els.badge = document.getElementById('notifBadge');
        els.overlay = document.getElementById('notifOverlay');
        els.panel = document.getElementById('notifPanel');
        els.list = document.getElementById('notifList');
        els.markAll = document.getElementById('notifMarkAll');
        els.closeBtn = document.getElementById('notifCloseBtn');

        if (!els.bell || !els.panel) return;

        els.bell.addEventListener('click', function (e) {
            e.preventDefault();
            if (isOpen) closePanel();
            else openPanel();
        });

        if (els.overlay) {
            els.overlay.addEventListener('click', closePanel);
        }
        if (els.closeBtn) {
            els.closeBtn.addEventListener('click', closePanel);
        }
        if (els.markAll) {
            els.markAll.addEventListener('click', function () {
                markAllRead();
            });
        }

        els.list.addEventListener('click', function (e) {
            const markBtn = e.target.closest('.notif-mark-one');
            if (markBtn) {
                e.preventDefault();
                e.stopPropagation();
                const id = parseInt(markBtn.getAttribute('data-mark-id') || '0', 10);
                if (id) {
                    markReadIds([id]);
                }
                return;
            }

            const link = e.target.closest('a.notif-item-link');
            if (!link) return;
            const wrap = link.closest('.notif-item');
            if (!wrap) return;
            const id = parseInt(wrap.getAttribute('data-notif-id') || '0', 10);
            if (id) {
                // Marca de inmediato; la navegación continúa
                markReadIds([id], { silent: true });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) closePanel();
        });

        migrateLegacyReads().finally(function () {
            fetchNotifications(false);
            markTicketFromPage();
        });

        pollTimer = setInterval(function () {
            fetchNotifications(false);
        }, POLL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
