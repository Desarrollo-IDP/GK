(function () {
    const TOOLBAR = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ indent: '-1' }, { indent: '+1' }],
        ['blockquote', 'link'],
        ['clean'],
    ];

    function isEmptyHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        const text = (div.textContent || '').replace(/\u00a0/g, ' ').trim();
        return text === '';
    }

    function decodeB64Utf8(b64) {
        if (!b64) {
            return '';
        }
        try {
            const binary = atob(b64);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return new TextDecoder('utf-8').decode(bytes);
        } catch (e) {
            return '';
        }
    }

    function decodeInitialContent(wrap, ta) {
        const tpl = wrap.querySelector('template.task-quill-initial');
        if (tpl) {
            const fromTpl = (tpl.innerHTML || '').trim();
            if (fromTpl) {
                return fromTpl;
            }
        }

        let html = (ta.value || '').trim();
        if (!html && wrap.dataset.initialContentB64) {
            html = decodeB64Utf8(wrap.dataset.initialContentB64).trim();
        }
        if (!html && ta.dataset.initialHtml) {
            html = ta.dataset.initialHtml;
        }
        return html;
    }

    function setQuillHtml(quill, html) {
        if (!html || !quill || !quill.root) {
            return;
        }

        const applyInner = () => {
            quill.root.innerHTML = html;
        };

        try {
            if (typeof quill.clipboard.dangerouslyPasteHTML === 'function') {
                quill.clipboard.dangerouslyPasteHTML(html, 'silent');
            } else {
                const delta = quill.clipboard.convert({ html });
                quill.setContents(delta, 'silent');
            }
        } catch (e) {
            applyInner();
        }

        if (isEmptyHtml(quill.root.innerHTML) && !isEmptyHtml(html)) {
            applyInner();
        }
    }

    function syncWrap(wrap) {
        const ta = wrap.querySelector('textarea.task-quill-input');
        const key = ta && ta.id;
        const quill = key && window.TaskQuill.editors[key];
        if (ta && quill) {
            ta.value = quill.root.innerHTML;
        }
        return ta;
    }

    window.TaskQuill = {
        editors: {},
        isEmptyHtml,
        syncWrap,
        syncById(id) {
            const ta = document.getElementById(id);
            if (!ta) {
                return '';
            }
            const quill = this.editors[id];
            if (quill) {
                ta.value = quill.root.innerHTML;
            }
            return ta.value;
        },
        initAll() {
            if (typeof Quill === 'undefined') {
                return;
            }

            document.querySelectorAll('.task-quill-field').forEach((wrap) => {
                const ta = wrap.querySelector('textarea.task-quill-input');
                const edEl = wrap.querySelector('.task-quill-editor');
                if (!ta || !edEl || !edEl.id) {
                    return;
                }
                if (edEl.dataset.quillReady === '1') {
                    return;
                }
                if (wrap.dataset.deferInit === '1') {
                    return;
                }

                const initialHtml = decodeInitialContent(wrap, ta);
                const quill = new Quill('#' + edEl.id, {
                    theme: 'snow',
                    placeholder: wrap.dataset.placeholder || '',
                    modules: { toolbar: TOOLBAR },
                });

                setQuillHtml(quill, initialHtml);
                queueMicrotask(() => {
                    if (initialHtml && isEmptyHtml(quill.root.innerHTML)) {
                        setQuillHtml(quill, initialHtml);
                    }
                });

                edEl.dataset.quillReady = '1';
                window.TaskQuill.editors[ta.id] = quill;

                const form = wrap.closest('form');
                if (form && !form.dataset.taskQuillBound) {
                    form.dataset.taskQuillBound = '1';
                    form.addEventListener('submit', (e) => {
                        let invalid = false;
                        form.querySelectorAll('.task-quill-field').forEach((w) => {
                            const field = syncWrap(w);
                            if (!field) {
                                return;
                            }
                            if (field.dataset.required === '1' && isEmptyHtml(field.value)) {
                                invalid = true;
                                const q = window.TaskQuill.editors[field.id];
                                if (q) {
                                    q.focus();
                                }
                            }
                        });
                        if (invalid) {
                            e.preventDefault();
                            alert('La descripción es obligatoria.');
                        }
                    });
                }
            });
        },
    };

    function bootTaskQuill() {
        window.TaskQuill.initAll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTaskQuill);
    } else {
        bootTaskQuill();
    }

    window.addEventListener('load', bootTaskQuill);
})();
