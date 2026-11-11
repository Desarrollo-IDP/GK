(function () {
    const cfg = window.TICKET_EMAIL || {};
    if (!cfg.enabled || !cfg.apiUrl || !cfg.ticketId) {
        return;
    }

    const POLL_MS = 5000;
    let polling = false;
    let pollTimer = null;
    let lastSignature = '';

    const el = (id) => document.getElementById(id);

    function conversationSignature(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return Array.from(tmp.querySelectorAll('[data-message-id]'))
            .map((node) => node.getAttribute('data-message-id'))
            .join('|');
    }

    function isNearBottom(box) {
        if (!box) return true;
        return box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    }

    function setLiveStatus(text, live = true) {
        const status = el('ticketEmailLiveStatus');
        if (!status) return;
        status.classList.toggle('is-error', !live);
        const label = status.querySelector('.ticket-email-live-text');
        if (label) {
            label.textContent = text;
        }
    }

    async function refreshEmailConversation(force = false) {
        if (polling || (!force && document.hidden)) return;
        polling = true;

        const box = el('ticketEmailMessages');
        if (!box) {
            polling = false;
            return;
        }

        const stick = isNearBottom(box);
        box.classList.add('is-updating');
        setLiveStatus('Actualizando…', false);

        try {
            const url = cfg.apiUrl + '?ticket_id=' + encodeURIComponent(cfg.ticketId) + '&_t=' + Date.now();
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!data.ok) {
                throw new Error(data.error || 'Error al actualizar');
            }

            const sig = conversationSignature(data.html || '');
            if (sig !== lastSignature) {
                box.innerHTML = data.html || '';
                lastSignature = sig;
                if (stick) {
                    box.scrollTop = box.scrollHeight;
                }
            } else if (force) {
                box.scrollTop = box.scrollHeight;
            }

            const time = new Date().toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
            const count = data.count ?? 0;
            setLiveStatus('En vivo · ' + time + ' · ' + count + ' mensaje(s)', true);
        } catch (e) {
            console.warn('Email poll:', e.message);
            setLiveStatus('Reintentando actualización…', false);
        } finally {
            box.classList.remove('is-updating');
            polling = false;
        }
    }

    window.TICKET_EMAIL.refresh = function () {
        return refreshEmailConversation(true);
    };

    function init() {
        const box = el('ticketEmailMessages');
        if (!box) return;

        lastSignature = conversationSignature(box.innerHTML);
        box.scrollTop = box.scrollHeight;

        refreshEmailConversation();
        pollTimer = setInterval(function () {
            refreshEmailConversation(false);
        }, POLL_MS);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshEmailConversation(true);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
