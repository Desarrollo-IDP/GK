(function () {
    const API = window.WA_API || 'api/chat.php';
    const TICKETS_VIEW = window.WA_TICKETS_VIEW || '../tickets/modules/tickets/view.php';
    let currentPhone = '';
    let pollTimer = null;
    let apiConfigured = false;
    let sending = false;
    let ticketReprocessDone = false;

    const el = (id) => document.getElementById(id);

    function showToast(message, type = 'danger') {
        const container = el('toastContainer');
        if (!container) {
            alert(message);
            return;
        }
        const toast = document.createElement('div');
        toast.className = 'wa-toast wa-toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('wa-toast-hide');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    async function api(action, options = {}) {
        const method = options.method || 'GET';
        let url = API + '?action=' + encodeURIComponent(action);

        if (options.params) {
            Object.entries(options.params).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') {
                    url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
                }
            });
        }

        const fetchOpts = { method, credentials: 'same-origin' };
        if (method === 'POST') {
            if (options.formData) {
                options.formData.append('action', action);
                fetchOpts.body = options.formData;
            } else {
                const fd = new FormData();
                fd.append('action', action);
                Object.entries(options.data || {}).forEach(([k, v]) => fd.append(k, v));
                fetchOpts.body = fd;
            }
        }

        const res = await fetch(url, fetchOpts);
        let data;
        try {
            data = await res.json();
        } catch {
            throw new Error('Respuesta inválida del servidor (HTTP ' + res.status + ')');
        }
        if (!data.ok) {
            throw new Error(data.error || 'Error en la API');
        }
        return data;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(String(iso).replace(' ', 'T'));
        return d.toLocaleString('es-MX', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function normalizePhone(phone) {
        return String(phone || '').replace(/\D/g, '');
    }

    function setComposerState() {
        const panel = el('composerPanel');
        const input = el('messageInput');
        const btn = el('btnSend');
        const alertBox = el('composerAlert');
        if (!panel) return;

        if (!apiConfigured) {
            panel.classList.add('wa-composer-disabled');
            if (alertBox) {
                alertBox.classList.remove('d-none');
                alertBox.className = 'wa-composer-alert alert alert-danger py-2 px-3 mb-0';
                alertBox.textContent = 'WhatsApp no configurado. Complete ACCESS_TOKEN y PHONE_NUMBER_ID en whatsapp/.env';
            }
            if (input) input.disabled = true;
            if (btn) btn.disabled = true;
            return;
        }

        panel.classList.remove('wa-composer-disabled');
        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
        }
        if (input) input.disabled = sending || !currentPhone;
        if (btn) btn.disabled = sending || !currentPhone;
    }

    function setSending(active) {
        sending = active;
        const badge = el('chatSendingBadge');
        if (badge) badge.classList.toggle('d-none', !active);
        setComposerState();
    }

    async function loadStatus() {
        try {
            const data = await api('status');
            apiConfigured = !!data.configured;
            let syncHint = '';
            if (data.sync && data.sync.imported > 0) {
                syncHint = ' <span class="badge bg-info">' + data.sync.imported + ' nuevos</span>';
            }
            el('statusPanel').innerHTML =
                '<span class="badge bg-' + (data.configured ? 'success' : 'danger') + '">' +
                (data.configured ? 'Listo para enviar' : 'Sin configurar') + '</span> ' +
                '<span class="badge bg-secondary">' + data.conversations + ' chats</span>' + syncHint;
            setComposerState();
        } catch (e) {
            el('statusPanel').innerHTML = '<span class="badge bg-danger">Error: ' + escapeHtml(e.message) + '</span>';
        }
    }

    function previewText(m) {
        if (m.type === 'image') return '📷 ' + (m.body || 'Imagen');
        if (m.type === 'audio') return '🎤 Audio';
        if (m.type === 'document') return '📎 ' + (m.body || 'Documento');
        return m.body || '[' + (m.type || 'mensaje') + ']';
    }

    async function loadRecentInbound() {
        const list = el('recentInboundList');
        if (!list) return;
        try {
            const data = await api('recent_inbound', { params: { limit: 20 } });
            list.innerHTML = '';
            if (!data.messages.length) {
                list.innerHTML = '<p class="text-muted small px-3 mb-0">Sin mensajes recibidos aún.</p>';
                return;
            }
            data.messages.forEach((m) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'wa-inbound-item';
                const name = m.contact_name || ('+' + m.phone);
                btn.innerHTML =
                    '<strong>' + escapeHtml(name) + '</strong>' +
                    '<small>' + escapeHtml(previewText(m)) + '</small>' +
                    '<div class="wa-inbound-meta">' + formatTime(m.created_at) + '</div>';
                btn.addEventListener('click', () => openChat(m.phone, m.contact_name));
                list.appendChild(btn);
            });
        } catch (e) {
            list.innerHTML = '<p class="text-danger small px-3 mb-0">' + escapeHtml(e.message) + '</p>';
        }
    }

    async function loadConversations() {
        const data = await api('conversations');
        const list = el('conversationList');
        list.innerHTML = '';

        if (!data.conversations.length) {
            list.innerHTML = '<p class="text-muted small px-3">Sin conversaciones. Abre un chat con un número o recibe un mensaje.</p>';
            return;
        }

        data.conversations.forEach((c) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wa-conv-item' + (c.phone === currentPhone ? ' active' : '');
            btn.innerHTML =
                '<strong>' + escapeHtml(c.contact_name || c.phone) + '</strong>' +
                '<small>' + escapeHtml(c.last_message || '').substring(0, 60) + '</small>';
            btn.addEventListener('click', () => openChat(c.phone, c.contact_name));
            list.appendChild(btn);
        });
    }

    async function loadTicketLink() {
        const box = el('chatTicketLink');
        if (!box || !currentPhone) return;
        try {
            const data = await api('ticket_for_phone', { params: { phone: currentPhone } });
            if (data.ticket && data.ticket.id) {
                const num = data.ticket.ticket_number || data.ticket.id;
                box.classList.remove('d-none');
                box.innerHTML = '<a href="' + escapeHtml(TICKETS_VIEW + '?id=' + data.ticket.id) + '" class="text-success" target="_blank" rel="noopener">' +
                    '<i class="fas fa-ticket-alt me-1"></i>Ticket #' + escapeHtml(String(num)) + ' abierto</a>';
            } else {
                box.classList.add('d-none');
                box.innerHTML = '';
            }
        } catch {
            box.classList.add('d-none');
        }
    }

    async function openChat(phone, name) {
        currentPhone = normalizePhone(phone);
        if (currentPhone.length < 10) {
            showToast('Teléfono inválido. Use formato 5215512345678', 'warning');
            return;
        }
        el('emptyState').classList.add('d-none');
        el('chatPanel').classList.remove('d-none');
        el('chatPhone').textContent = '+' + currentPhone;
        el('chatContactName').textContent = name || ('+' + currentPhone);
        setComposerState();
        await loadMessages();
        await loadTicketLink();
        await loadConversations();
        el('messageInput')?.focus();
    }

    function mediaSrc(m) {
        return m.media_url || m.media_path || '';
    }

    function renderMessage(m, pending) {
        const dir = m.direction === 'outbound' ? 'out' : (m.type === 'system' ? 'system' : 'in');
        const bubble = document.createElement('div');
        bubble.className = 'wa-bubble ' + dir + (pending ? ' wa-bubble-pending' : '');
        if (m.id) bubble.dataset.messageId = m.id;

        let content = '';
        const src = mediaSrc(m);
        if (m.type === 'image') {
            if (src) {
                content += '<img src="' + escapeHtml(src) + '" alt="imagen">';
            } else if (m.media_id) {
                content += '<div class="small text-muted mb-1">[Imagen Meta: ' + escapeHtml(m.media_id) + ']</div>';
            }
            if (m.body) content += '<div>' + escapeHtml(m.body) + '</div>';
        } else if (m.type === 'audio') {
            if (src) {
                content += '<audio controls src="' + escapeHtml(src) + '"></audio>';
            } else {
                content += '<div><i class="fas fa-microphone"></i> Audio' + (m.media_id ? ' (' + escapeHtml(m.media_id) + ')' : '') + '</div>';
            }
        } else if (m.type === 'document') {
            if (src) {
                content += '<a href="' + escapeHtml(src) + '" target="_blank" rel="noopener">📎 ' + escapeHtml(m.body || 'Documento') + '</a>';
            } else {
                content += '<div>📎 ' + escapeHtml(m.body || 'Documento') + '</div>';
            }
        } else {
            content = '<div>' + escapeHtml(m.body || '').replace(/\n/g, '<br>') + '</div>';
        }

        const status = pending ? 'enviando…' : (m.status || '');
        bubble.innerHTML = content + '<span class="wa-time">' + formatTime(m.created_at) + (status ? ' · ' + escapeHtml(status) : '') + '</span>';
        return bubble;
    }

    async function loadMessages() {
        if (!currentPhone) return;
        const data = await api('messages', { params: { phone: currentPhone } });
        const box = el('messageList');
        box.innerHTML = '';
        data.messages.forEach((m) => box.appendChild(renderMessage(m)));
        box.scrollTop = box.scrollHeight;
    }

    function appendPendingBubble(body, type) {
        const box = el('messageList');
        const pending = {
            direction: 'outbound',
            type: type || 'text',
            body: body,
            created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
            status: 'enviando',
        };
        const bubble = renderMessage(pending, true);
        bubble.id = 'waPendingBubble';
        box.appendChild(bubble);
        box.scrollTop = box.scrollHeight;
        return bubble;
    }

    async function sendMessage(type, body, file) {
        if (!currentPhone) {
            showToast('Seleccione o abra un chat primero', 'warning');
            return;
        }
        if (!apiConfigured) {
            showToast('Configure WhatsApp en whatsapp/.env antes de enviar', 'warning');
            return;
        }

        setSending(true);
        const pendingBubble = appendPendingBubble(body || (file ? file.name : ''), type);

        try {
            let data;
            if (file) {
                const fd = new FormData();
                fd.append('phone', currentPhone);
                fd.append('type', type);
                fd.append('file', file);
                fd.append('body', body || (type === 'image' ? (file.name || 'Imagen') : file.name || ''));
                data = await api('send', { method: 'POST', formData: fd });
            } else {
                data = await api('send', {
                    method: 'POST',
                    data: { phone: currentPhone, type: 'text', body: body },
                });
            }

            pendingBubble.remove();
            if (data.ticket && data.ticket.ticket_id) {
                showToast('Enviado y guardado en ticket #' + (data.ticket.ticket_number || data.ticket.ticket_id), 'success');
                await loadTicketLink();
            } else {
                showToast('Mensaje enviado por WhatsApp', 'success');
            }
            await loadMessages();
            await loadConversations();
            await loadRecentInbound();
        } catch (e) {
            pendingBubble.remove();
            showToast(e.message || 'Error al enviar', 'danger');
        } finally {
            setSending(false);
        }
    }

    async function sendText() {
        const input = el('messageInput');
        const body = input.value.trim();
        if (!body) return;
        input.value = '';
        input.style.height = 'auto';
        await sendMessage('text', body, null);
    }

    async function sendFile(type, file) {
        if (!file) return;
        const caption = type === 'image' ? (el('messageInput').value.trim() || file.name || 'Imagen') : '';
        if (type === 'image' && caption) {
            el('messageInput').value = '';
        }
        await sendMessage(type, caption, file);
    }

    async function runTests() {
        const baseUrl = el('testBaseUrl').value.trim() || 'http://localhost';
        const liveSend = el('testLiveSend').checked ? '1' : '0';
        const phone = el('testLivePhone').value.trim();

        el('testsResults').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';

        const data = await api('tests', {
            params: { base_url: baseUrl, live_send: liveSend, phone: phone },
        });

        const s = data.summary;
        el('testsSummary').innerHTML =
            '<div class="alert alert-' + (s.all_ok ? 'success' : 'warning') + '">' +
            '<strong>' + s.passed + '/' + s.total + '</strong> pruebas exitosas' +
            (s.failed ? ' — <strong>' + s.failed + ' fallaron</strong>' : '') +
            '</div>';

        const groups = {};
        data.results.forEach((r) => {
            groups[r.group] = groups[r.group] || [];
            groups[r.group].push(r);
        });

        let html = '';
        Object.entries(groups).forEach(([group, items]) => {
            html += '<div class="test-group-title">' + escapeHtml(group) + '</div>';
            items.forEach((r) => {
                html += '<div class="test-row ' + (r.ok ? 'pass' : 'fail') + '">' +
                    '<span>' + (r.ok ? '✅' : '❌') + ' ' + escapeHtml(r.name) + '<br><small class="text-muted">' + escapeHtml(r.message) + '</small></span>' +
                    '</div>';
            });
        });
        el('testsResults').innerHTML = html;

        await refreshAll();
    }

    async function refreshAll() {
        await loadStatus();
        if (!ticketReprocessDone) {
            ticketReprocessDone = true;
            try {
                const data = await api('reprocess_tickets', { params: { limit: 500 } });
                const r = data.reprocess || {};
                if ((r.tickets_created || 0) > 0) {
                    showToast('Se crearon ' + r.tickets_created + ' ticket(s) desde mensajes pendientes', 'success');
                }
            } catch (e) {
                console.warn('reprocess_tickets:', e.message);
            }
        }
        await loadRecentInbound();
        await loadConversations();
        if (currentPhone) {
            await loadMessages();
            await loadTicketLink();
        }
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(refreshAll, 5000);
    }

    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // Eventos
    el('btnRefresh').addEventListener('click', () => refreshAll().catch((e) => showToast(e.message)));
    el('btnSend').addEventListener('click', () => sendText());
    el('messageInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });
    el('messageInput').addEventListener('input', (e) => autoResizeTextarea(e.target));
    el('btnNewChat').addEventListener('click', () => {
        const p = el('newPhone').value.trim();
        if (p) openChat(p, '');
    });
    el('newPhone').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const p = el('newPhone').value.trim();
            if (p) openChat(p, '');
        }
    });
    el('btnRunTests').addEventListener('click', () => {
        new bootstrap.Modal(el('testsModal')).show();
        runTests().catch((e) => showToast(e.message));
    });
    el('btnImport').addEventListener('click', async () => {
        try {
            const data = await api('sync');
            const s = data.sync || {};
            showToast(
                'Sync: ' + (s.imported || 0) + ' msgs, ' + (s.tickets_created || 0) + ' tickets nuevos, ' + (s.ticket_errors || 0) + ' errores',
                (s.ticket_errors || 0) > 0 ? 'warning' : 'success'
            );
            await refreshAll();
        } catch (e) {
            showToast(e.message);
        }
    });
    el('btnReprocessTickets').addEventListener('click', async () => {
        try {
            const data = await api('reprocess_tickets');
            const r = data.reprocess || {};
            showToast(
                'Tickets: ' + (r.tickets_created || 0) + ' creados, ' + (r.tickets_linked || 0) + ' vinculados, ' + (r.skipped || 0) + ' omitidos',
                (r.errors || 0) > 0 ? 'warning' : 'success'
            );
            await refreshAll();
        } catch (e) {
            showToast(e.message);
        }
    });
    el('btnSimulate').addEventListener('click', () => {
        new bootstrap.Modal(el('simulateModal')).show();
    });
    el('btnConfirmSimulate').addEventListener('click', async () => {
        try {
            await api('simulate', {
                method: 'POST',
                data: {
                    phone: currentPhone,
                    type: el('simType').value,
                    body: el('simBody').value,
                    contact_name: el('simName').value,
                },
            });
            bootstrap.Modal.getInstance(el('simulateModal')).hide();
            await refreshAll();
            showToast('Mensaje simulado guardado', 'success');
        } catch (e) {
            showToast(e.message);
        }
    });
    el('fileImage').addEventListener('change', (e) => {
        if (e.target.files[0]) sendFile('image', e.target.files[0]);
        e.target.value = '';
    });
    el('fileAudio').addEventListener('change', (e) => {
        if (e.target.files[0]) sendFile('audio', e.target.files[0]);
        e.target.value = '';
    });
    el('fileDocument').addEventListener('change', (e) => {
        if (e.target.files[0]) sendFile('document', e.target.files[0]);
        e.target.value = '';
    });

    refreshAll().catch((e) => showToast(e.message));
    startPolling();
})();
