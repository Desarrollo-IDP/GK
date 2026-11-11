(function () {
    const cfg = window.TICKET_WA || {};
    if (!cfg.enabled || !cfg.apiUrl) {
        return;
    }

    const apiUrl = cfg.apiUrl;
    const ticketId = cfg.ticketId;
    const technicianId = cfg.technicianId || '';
    let lastMessageId = cfg.lastMessageId || 0;
    let pollTimer = null;
    let polling = false;
    let pollCount = 0;
    let sending = false;
    const POLL_MS = 3000;
    const MAX_ATTACHMENTS = 10;
    const MAX_FILE_BYTES = 16 * 1024 * 1024;

    /** @type {{ id: string, file: File, previewUrl: string | null }[]} */
    const pendingFiles = [];

    const el = (id) => document.getElementById(id);

    async function api(action, options = {}) {
        const method = options.method || 'GET';
        let url = apiUrl + '?action=' + encodeURIComponent(action);
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
        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseErr) {
            const snippet = raw.replace(/\s+/g, ' ').trim().slice(0, 120);
            throw new Error('Respuesta inválida del servidor' + (snippet ? ': ' + snippet : ''));
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

    function formatFileSize(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unit = 0;
        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit++;
        }
        return (unit === 0 ? size : size.toFixed(1)) + ' ' + units[unit];
    }

    function initialsFromName(name, fallback) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        let initials = '';
        for (let i = 0; i < parts.length && initials.length < 2; i++) {
            initials += parts[i].charAt(0).toUpperCase();
        }
        return initials || fallback;
    }

    function fileKey(file) {
        return [file.name, file.size, file.lastModified, file.type].join('::');
    }

    function detectSendType(file) {
        const mime = (file.type || '').toLowerCase();
        if (mime.startsWith('image/')) return 'image';
        if (mime.startsWith('audio/')) return 'audio';
        return 'document';
    }

    function fileIconClass(file) {
        const type = detectSendType(file);
        if (type === 'audio') return 'fas fa-microphone ticket-chat-attach-icon--audio';
        if (type === 'image') return 'fas fa-image';
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext === 'pdf') return 'fas fa-file-pdf';
        if (['doc', 'docx'].includes(ext)) return 'fas fa-file-word';
        if (['xls', 'xlsx'].includes(ext)) return 'fas fa-file-excel';
        if (['ppt', 'pptx'].includes(ext)) return 'fas fa-file-powerpoint';
        if (['zip', 'rar', '7z'].includes(ext)) return 'fas fa-file-archive';
        return 'fas fa-file-alt';
    }

    function revokePreview(item) {
        if (item && item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    }

    function setLiveStatus(text, ok = true) {
        const status = el('ticketWaLiveStatus');
        if (!status) return;
        status.innerHTML = ok
            ? '<i class="fas fa-circle ticket-chat-pulse"></i> ' + escapeHtml(text)
            : '<i class="fas fa-circle text-warning"></i> ' + escapeHtml(text);
    }

    function isNearBottom(box) {
        if (!box) return true;
        return box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    }

    function hideEmptyState() {
        const empty = el('ticketWaEmpty');
        if (empty) empty.remove();
    }

    function renderChatImage(url) {
        return '<div class="ticket-chat-media-item"><img class="ticket-chat-image-zoom" src="' +
            escapeHtml(url) + '" alt="imagen" role="button" tabindex="0"></div>';
    }

    function renderAttachmentHtml(attachments, waType) {
        let html = '';
        const list = attachments || [];
        const imageUrls = [];

        list.forEach((a) => {
            const url = a.url || ('../../' + (a.file_path || ''));
            const mime = (a.mime_type || '').toLowerCase();
            if (mime.startsWith('image/') || /\.(jpe?g|png|gif|webp)$/i.test(a.file_path || '')) {
                imageUrls.push(url);
            } else if (mime.startsWith('audio/')) {
                html += '<audio controls src="' + escapeHtml(url) + '"></audio>';
            } else {
                const name = escapeHtml(a.filename || 'Archivo');
                html += '<a class="ticket-chat-file" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' +
                    '<i class="fas fa-file-alt"></i><span>' + name + '</span></a>';
            }
        });

        if (imageUrls.length === 1) {
            html = renderChatImage(imageUrls[0]) + html;
        } else if (imageUrls.length > 1) {
            html = '<div class="ticket-chat-media-grid">' +
                imageUrls.map(renderChatImage).join('') + '</div>' + html;
        }
        if (!html && waType === 'audio') {
            html = '<div><i class="fas fa-microphone"></i> Audio</div>';
        }
        if (!html && waType === 'document') {
            html = '<a class="ticket-chat-file" href="#" onclick="return false;"><i class="fas fa-paperclip"></i><span>' +
                escapeHtml('Documento') + '</span></a>';
        }
        return html;
    }

    function chatVisibleMessageText(message, attachments, waType) {
        const text = String(message || '').trim();
        if (!text) {
            return '';
        }
        const list = attachments || [];
        if (!list.length) {
            return text;
        }

        const placeholderRe = /^\[(?:Imagen|Image|Documento|Document|Audio|Video|Sticker|Mensaje)\s+WhatsApp\]\s*/iu;
        let stripped = text.replace(placeholderRe, '').trim();
        if (!stripped && /^\[(?:Imagen|Image|Documento|Document|Audio|Video|Sticker|Mensaje)\s+WhatsApp\]$/iu.test(text)) {
            return '';
        }

        for (const att of list) {
            const filename = String(att.filename || '').trim();
            if (!filename) continue;
            const candidates = [filename, filename.split(/[/\\]/).pop()];
            for (const candidate of candidates) {
                if (!candidate) continue;
                if (text.localeCompare(candidate, undefined, { sensitivity: 'accent' }) === 0) {
                    return '';
                }
                if (stripped && stripped.localeCompare(candidate, undefined, { sensitivity: 'accent' }) === 0) {
                    return '';
                }
            }
        }

        const type = String(waType || '').toLowerCase();
        if (['image', 'audio', 'document', 'video', 'sticker'].includes(type) && !stripped) {
            return '';
        }

        return stripped || text;
    }

    function renderBubble(m) {
        const dir = m.sender_type === 'technician' ? 'out' : 'in';
        const senderLabel = m.sender_name || (dir === 'out' ? 'Técnico' : 'Cliente');
        const fallback = dir === 'out' ? 'T' : 'C';
        const waType = m.whatsapp_type || 'text';
        const attachments = m.attachments || [];

        const row = document.createElement('div');
        row.className = 'ticket-chat-msg ticket-chat-msg--' + dir + ' ticket-chat-msg--new';
        row.dataset.messageId = m.id;

        const avatar = document.createElement('div');
        avatar.className = 'ticket-chat-avatar';
        avatar.title = senderLabel;
        avatar.textContent = initialsFromName(senderLabel, fallback);

        const body = document.createElement('div');
        body.className = 'ticket-chat-body';

        const meta = document.createElement('div');
        meta.className = 'ticket-chat-meta';
        meta.innerHTML =
            '<span class="ticket-chat-sender">' + escapeHtml(senderLabel) + '</span>' +
            '<span class="ticket-chat-time">' + escapeHtml(formatTime(m.created_at)) + '</span>';

        const bubble = document.createElement('div');
        bubble.className = 'ticket-chat-bubble';

        let content = renderAttachmentHtml(attachments, waType);
        const visibleText = chatVisibleMessageText(m.message, attachments, waType);
        if (visibleText) {
            content += '<div>' + escapeHtml(visibleText).replace(/\n/g, '<br>') + '</div>';
        } else if (!content && String(m.message || '').trim()) {
            content = '<div>' + escapeHtml(String(m.message).trim()).replace(/\n/g, '<br>') + '</div>';
        }

        bubble.innerHTML = content;
        body.appendChild(meta);
        body.appendChild(bubble);
        row.appendChild(avatar);
        row.appendChild(body);

        setTimeout(() => row.classList.remove('ticket-chat-msg--new'), 400);
        return row;
    }

    function appendMessages(messages) {
        const box = el('ticketWaThread');
        if (!box || !messages || !messages.length) return 0;

        const stick = isNearBottom(box);
        let added = 0;

        messages.forEach((m) => {
            if (box.querySelector('[data-message-id="' + m.id + '"]')) return;
            box.appendChild(renderBubble(m));
            if (m.id > lastMessageId) lastMessageId = m.id;
            added++;
        });

        if (added > 0) {
            hideEmptyState();
            if (stick) {
                box.scrollTop = box.scrollHeight;
            }
        }

        return added;
    }

    async function pollMessages() {
        if (polling) return;
        polling = true;
        pollCount++;
        try {
            const importWebhooks = pollCount === 1 || pollCount % 20 === 0 ? '1' : '0';
            const data = await api('messages', {
                params: {
                    ticket_id: ticketId,
                    since_id: lastMessageId,
                    sync: '1',
                    import_webhooks: importWebhooks,
                },
            });
            const added = appendMessages(data.messages || []);
            const synced = data.sync && data.sync.synced ? data.sync.synced : 0;
            if (added > 0) {
                setLiveStatus(synced > 0 ? added + ' mensaje(s) nuevo(s)' : 'Actualizado');
            } else {
                setLiveStatus('En vivo');
            }
        } catch (e) {
            console.warn('WA poll:', e.message);
            setLiveStatus(e.message || 'Reconectando…', false);
        } finally {
            polling = false;
        }
    }

    async function sendPayload(type, body, file) {
        const fd = new FormData();
        fd.append('ticket_id', ticketId);
        fd.append('technician_id', technicianId);
        fd.append('type', type);
        fd.append('body', body || '');
        if (file) {
            fd.append('file', file);
        }
        const data = await api('send', { method: 'POST', formData: fd });
        if (data.message) {
            appendMessages([data.message]);
        } else {
            await pollMessages();
        }
    }

    function setSendingState(active) {
        sending = active;
        const composer = document.querySelector('.ticket-chat-composer');
        const btn = el('ticketWaSend');
        if (composer) {
            composer.classList.toggle('is-sending', active);
        }
        if (btn) {
            btn.disabled = active;
            btn.innerHTML = active
                ? '<i class="fas fa-spinner fa-spin me-1"></i> Enviando…'
                : '<i class="fas fa-paper-plane me-1"></i> Enviar';
        }
    }

    async function sendMessage() {
        if (sending) return;

        const input = el('ticketWaInput');
        const text = (input ? input.value : '').trim();
        const files = pendingFiles.map((item) => item.file);

        if (!text && files.length === 0) {
            return;
        }

        setSendingState(true);
        try {
            if (text) {
                if (input) input.value = '';
                await sendPayload('text', text, null);
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const type = detectSendType(file);
                const caption = type === 'image' && files.length === 1 && !text ? '' : '';
                await sendPayload(type, caption, file);
            }

            clearPendingFiles();
            setLiveStatus('Enviado');
        } catch (e) {
            alert(e.message || 'Error al enviar');
        } finally {
            setSendingState(false);
        }
    }

    function updateAttachmentsUi() {
        const wrap = el('ticketWaAttachments');
        const grid = el('ticketWaAttachGrid');
        const counter = el('ticketWaAttachCounter');
        const clearBtn = el('ticketWaClearAttach');

        if (!wrap || !grid) return;

        grid.innerHTML = '';

        if (pendingFiles.length === 0) {
            wrap.hidden = true;
            if (counter) counter.textContent = '0 archivos';
            if (clearBtn) clearBtn.hidden = true;
            return;
        }

        wrap.hidden = false;
        if (clearBtn) clearBtn.hidden = false;
        if (counter) {
            counter.textContent = pendingFiles.length === 1
                ? '1 archivo listo para enviar'
                : pendingFiles.length + ' archivos listos para enviar';
        }

        pendingFiles.forEach((item) => {
            const card = document.createElement('div');
            const type = detectSendType(item.file);
            card.className = 'ticket-chat-attach-preview' + (type !== 'image' ? ' ticket-chat-attach-preview--wide' : '');
            card.dataset.attachId = item.id;

            let previewHtml = '';
            if (type === 'image' && item.previewUrl) {
                previewHtml = '<img class="ticket-chat-attach-thumb" src="' + escapeHtml(item.previewUrl) + '" alt="">';
            } else {
                previewHtml = '<div class="ticket-chat-attach-icon"><i class="' + fileIconClass(item.file) + '"></i></div>';
            }

            card.innerHTML =
                previewHtml +
                '<button type="button" class="ticket-chat-attach-remove" title="Quitar" data-remove-id="' + escapeHtml(item.id) + '">' +
                '<i class="fas fa-times"></i></button>' +
                '<div class="ticket-chat-attach-info">' +
                '<span class="ticket-chat-attach-name" title="' + escapeHtml(item.file.name) + '">' + escapeHtml(item.file.name) + '</span>' +
                '<span class="ticket-chat-attach-size">' + formatFileSize(item.file.size) + '</span>' +
                '</div>';

            grid.appendChild(card);
        });
    }

    function clearPendingFiles() {
        while (pendingFiles.length > 0) {
            revokePreview(pendingFiles.pop());
        }
        updateAttachmentsUi();
        const fileInput = el('ticketWaFiles');
        if (fileInput) fileInput.value = '';
    }

    function removePendingFile(id) {
        const index = pendingFiles.findIndex((item) => item.id === id);
        if (index === -1) return;
        revokePreview(pendingFiles[index]);
        pendingFiles.splice(index, 1);
        updateAttachmentsUi();
    }

    function addPendingFiles(fileList) {
        if (!fileList || !fileList.length) return;

        const existing = new Set(pendingFiles.map((item) => fileKey(item.file)));
        const errors = [];

        Array.from(fileList).forEach((file) => {
            if (pendingFiles.length >= MAX_ATTACHMENTS) {
                errors.push('Máximo ' + MAX_ATTACHMENTS + ' archivos por envío.');
                return;
            }
            if (file.size > MAX_FILE_BYTES) {
                errors.push('"' + file.name + '" supera 16 MB.');
                return;
            }
            const key = fileKey(file);
            if (existing.has(key)) {
                return;
            }
            existing.add(key);

            const type = detectSendType(file);
            const previewUrl = type === 'image' ? URL.createObjectURL(file) : null;
            pendingFiles.push({
                id: key + '::' + Date.now() + '::' + Math.random().toString(36).slice(2),
                file,
                previewUrl,
            });
        });

        if (errors.length) {
            alert(errors[0]);
        }

        updateAttachmentsUi();
    }

    const lightbox = {
        scale: 1,
        fitScale: 1,
        panX: 0,
        panY: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        panStartX: 0,
        panStartY: 0,
    };

    function lightboxElements() {
        return {
            box: el('ticketChatLightbox'),
            img: el('ticketChatLightboxImg'),
            stage: el('ticketChatLightboxStage'),
            label: el('ticketChatLightboxZoom'),
            download: el('ticketChatLightboxDownload'),
        };
    }

    function isLightboxOpen() {
        const box = el('ticketChatLightbox');
        return box && !box.hidden;
    }

    function clampLightboxScale(value) {
        const min = Math.min(lightbox.fitScale * 0.25, 0.15);
        return Math.min(5, Math.max(min, value));
    }

    function computeFitScale(img, stage) {
        const sw = stage.clientWidth;
        const sh = stage.clientHeight;
        const nw = img.naturalWidth;
        const nh = img.naturalHeight;
        if (!sw || !sh || !nw || !nh) {
            return 1;
        }
        return Math.min(sw / nw, sh / nh);
    }

    function applyLightboxTransform() {
        const { img, label } = lightboxElements();
        if (!img) return;
        img.style.transform =
            'translate(calc(-50% + ' + lightbox.panX + 'px), calc(-50% + ' + lightbox.panY + 'px)) scale(' + lightbox.scale + ')';
        if (label) {
            const pct = lightbox.fitScale > 0 ? Math.round((lightbox.scale / lightbox.fitScale) * 100) : 100;
            label.textContent = pct + '%';
        }
    }

    function resetLightboxPan() {
        lightbox.panX = 0;
        lightbox.panY = 0;
    }

    function refreshLightboxFit() {
        const { img, stage } = lightboxElements();
        if (!img || !stage) return;
        lightbox.fitScale = computeFitScale(img, stage);
        lightbox.scale = lightbox.fitScale;
        resetLightboxPan();
        applyLightboxTransform();
    }

    function openLightbox(src, alt) {
        const { box, img, stage, download } = lightboxElements();
        if (!box || !img || !stage || !src) return;

        resetLightboxPan();
        lightbox.dragging = false;
        stage.classList.remove('is-dragging');

        img.onload = function () {
            refreshLightboxFit();
        };
        img.src = src;
        img.alt = alt || 'Imagen';

        if (download) {
            download.href = src;
            download.setAttribute('download', alt || 'imagen');
        }

        box.hidden = false;
        box.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (img.complete && img.naturalWidth) {
            refreshLightboxFit();
        }
    }

    function closeLightbox() {
        const { box, img, stage } = lightboxElements();
        if (!box || box.hidden) return;
        box.hidden = true;
        box.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        lightbox.dragging = false;
        if (stage) stage.classList.remove('is-dragging');
        if (img) img.removeAttribute('src');
    }

    function changeLightboxZoom(mode) {
        const { img, stage } = lightboxElements();
        if (!img || !stage) return;

        if (mode === 'in') {
            lightbox.scale = clampLightboxScale(lightbox.scale * 1.25);
        } else if (mode === 'out') {
            lightbox.scale = clampLightboxScale(lightbox.scale / 1.25);
        } else if (mode === 'fit') {
            lightbox.scale = lightbox.fitScale;
            resetLightboxPan();
        } else if (mode === 'reset') {
            lightbox.scale = 1;
            resetLightboxPan();
        }
        applyLightboxTransform();
    }

    function initLightbox() {
        const { box, stage } = lightboxElements();
        if (!box || !stage) return;

        document.addEventListener('click', (e) => {
            const zoomImg = e.target.closest('.ticket-chat-image-zoom');
            if (!zoomImg || !zoomImg.closest('#ticketWaThread')) return;
            e.preventDefault();
            openLightbox(zoomImg.currentSrc || zoomImg.src, zoomImg.alt || 'Imagen');
        });

        document.addEventListener('keydown', (e) => {
            const zoomImg = e.target.closest('.ticket-chat-image-zoom');
            if (zoomImg && zoomImg.closest('#ticketWaThread') && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                openLightbox(zoomImg.currentSrc || zoomImg.src, zoomImg.alt || 'Imagen');
                return;
            }
            if (e.key === 'Escape' && isLightboxOpen()) {
                closeLightbox();
            }
        });

        box.addEventListener('click', (e) => {
            if (e.target.closest('[data-lightbox-close]')) {
                closeLightbox();
            }
        });

        box.addEventListener('click', (e) => {
            const zoomBtn = e.target.closest('[data-lightbox-zoom]');
            if (!zoomBtn) return;
            e.preventDefault();
            changeLightboxZoom(zoomBtn.getAttribute('data-lightbox-zoom'));
        });

        stage.addEventListener('wheel', (e) => {
            if (!isLightboxOpen()) return;
            e.preventDefault();
            const factor = e.deltaY > 0 ? 0.9 : 1.1;
            lightbox.scale = clampLightboxScale(lightbox.scale * factor);
            applyLightboxTransform();
        }, { passive: false });

        stage.addEventListener('mousedown', (e) => {
            if (!isLightboxOpen() || e.button !== 0) return;
            e.preventDefault();
            lightbox.dragging = true;
            lightbox.dragStartX = e.clientX;
            lightbox.dragStartY = e.clientY;
            lightbox.panStartX = lightbox.panX;
            lightbox.panStartY = lightbox.panY;
            stage.classList.add('is-dragging');
        });

        window.addEventListener('mousemove', (e) => {
            if (!lightbox.dragging) return;
            lightbox.panX = lightbox.panStartX + (e.clientX - lightbox.dragStartX);
            lightbox.panY = lightbox.panStartY + (e.clientY - lightbox.dragStartY);
            applyLightboxTransform();
        });

        window.addEventListener('mouseup', () => {
            if (!lightbox.dragging) return;
            lightbox.dragging = false;
            stage.classList.remove('is-dragging');
        });

        window.addEventListener('resize', () => {
            if (!isLightboxOpen()) return;
            refreshLightboxFit();
        });
    }

    function initConversationChannelSwitch() {
        const wrap = document.getElementById('ticketChannelSwitch');
        const waPanel = document.getElementById('ticketConversationWa');
        const emailPanel = document.getElementById('ticketConversationEmail');
        if (!wrap || !waPanel || !emailPanel) {
            return;
        }

        const storageKey = 'ticketConversationChannel_' + (cfg.ticketId || '');

        function setChannel(channel) {
            wrap.querySelectorAll('.ticket-channel-switch-btn').forEach((btn) => {
                const active = btn.dataset.channel === channel;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            waPanel.classList.toggle('is-active', channel === 'whatsapp');
            emailPanel.classList.toggle('is-active', channel === 'email');
            try {
                sessionStorage.setItem(storageKey, channel);
            } catch (e) {
                // ignore
            }
        }

        wrap.addEventListener('click', (e) => {
            const btn = e.target.closest('.ticket-channel-switch-btn');
            if (!btn) return;
            setChannel(btn.dataset.channel || 'whatsapp');
        });

        let saved = null;
        try {
            saved = sessionStorage.getItem(storageKey);
        } catch (e) {
            // ignore
        }
        if (saved === 'email' || saved === 'whatsapp') {
            setChannel(saved);
        }
    }

    function bindEvents() {
        const btn = el('ticketWaSend');
        const input = el('ticketWaInput');
        const fileInput = el('ticketWaFiles');
        const clearBtn = el('ticketWaClearAttach');
        const attachGrid = el('ticketWaAttachGrid');
        const composer = document.querySelector('.ticket-chat-composer');

        if (btn) {
            btn.addEventListener('click', () => sendMessage());
        }
        if (input) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
        if (fileInput) {
            fileInput.addEventListener('click', function () {
                this.value = '';
            });
            fileInput.addEventListener('change', (e) => {
                addPendingFiles(e.target.files || []);
            });
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', clearPendingFiles);
        }
        if (attachGrid) {
            attachGrid.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('[data-remove-id]');
                if (!removeBtn) return;
                removePendingFile(removeBtn.getAttribute('data-remove-id'));
            });
        }

        if (composer) {
            ['dragenter', 'dragover'].forEach((evt) => {
                composer.addEventListener(evt, (e) => {
                    e.preventDefault();
                    composer.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach((evt) => {
                composer.addEventListener(evt, (e) => {
                    e.preventDefault();
                    if (evt === 'dragleave' && composer.contains(e.relatedTarget)) {
                        return;
                    }
                    composer.classList.remove('is-dragover');
                });
            });
            composer.addEventListener('drop', (e) => {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    addPendingFiles(e.dataTransfer.files);
                }
            });
        }

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) pollMessages();
        });

        const box = el('ticketWaThread');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    }

    bindEvents();
    initLightbox();
    initConversationChannelSwitch();
    pollMessages();
    pollTimer = setInterval(pollMessages, POLL_MS);
})();
