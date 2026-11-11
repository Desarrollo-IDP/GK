<?php
/**
 * Estilos UI compartidos (alineados con tickets/modules/tickets/new.php)
 * Uso: include '../../includes/module_ui_styles.php';
 *
 * @param string $layout 'form' | 'list'
 */
$module_ui_layout = $module_ui_layout ?? 'form';
$form_max_width = $module_ui_form_max_width ?? '760px';
$list_max_width = $module_ui_list_max_width ?? '1120px';
$max_width = ($module_ui_layout === 'list') ? $list_max_width : $form_max_width;
?>
<style>
.body-bg {
    background: #f7f8fa;
    min-height: 100vh;
    width: 100%;
    overflow-x: hidden;
}
.main-content.main-content-boxed {
    max-width: <?php echo htmlspecialchars($max_width); ?>;
    margin-top: 2rem;
    margin-bottom: 2rem;
    margin-right: auto;
    padding: 2.5rem 2rem;
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px 0 rgba(32,38,84,0.07);
    min-height: 80vh;
}
@media (max-width: 991.98px) {
    .main-content.main-content-boxed {
        padding: 1.25rem 1rem;
        border-radius: 0.75rem;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }
}
@media (max-width: 767.98px) {
    .main-content.main-content-boxed {
        padding: 0.85rem 0.65rem;
        border-radius: 0.5rem;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
}

/* Formulario — paneles como new.php */
.ticket-edit-form-card {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}
.ticket-edit-form-card > .card-body { padding: 0 !important; }
.ticket-edit-panel {
    height: 100%;
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(23, 32, 51, 0.04);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.ticket-edit-panel-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1.1rem 1.2rem;
    border-bottom: 1px solid #edf1f6;
    background: linear-gradient(180deg, #fbfcfe 0%, #f7f9fc 100%);
}
.ticket-edit-panel-icon {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #202654;
    color: #fff;
    flex-shrink: 0;
    font-size: 0.95rem;
}
.ticket-edit-panel-icon--priority { background: #3a4478; }
.ticket-edit-panel-icon--details { background: #475569; }
.ticket-edit-panel-icon--contact { background: #0f766e; }
.ticket-edit-panel-header h4,
.ticket-edit-panel-header h5 {
    margin: 0;
    border: 0;
    padding: 0;
    font-size: 1rem;
    font-weight: 750;
    color: #202654;
    letter-spacing: -0.01em;
}
.ticket-edit-panel-subtitle {
    margin: 0.2rem 0 0;
    font-size: 0.8rem;
    color: #64748b;
}
.ticket-edit-panel-body {
    padding: 1.15rem 1.2rem 1.25rem;
    flex: 1 1 auto;
}
.ticket-edit-panel-body .mb-3 { margin-bottom: 1rem !important; }
.ticket-edit-panel-body .mb-3:last-child { margin-bottom: 0 !important; }
.ticket-edit-panel .form-label {
    font-size: 0.84rem;
    font-weight: 650;
    color: #334155;
    margin-bottom: 0.4rem;
}
.ticket-edit-panel .form-control,
.ticket-edit-panel .form-select {
    border-radius: 10px;
    border-color: #d7dee8;
    min-height: 2.55rem;
    padding: 0.6rem 0.85rem;
}
.ticket-edit-panel .form-control:focus,
.ticket-edit-panel .form-select:focus {
    border-color: #93a4e0;
    box-shadow: 0 0 0 0.2rem rgba(32, 38, 84, 0.12);
}
.ticket-edit-form .form-actions {
    margin-top: 1.25rem !important;
    padding: 1rem 0 0;
    border-top: 1px solid #e6ebf2;
}
@media (max-width: 767.98px) {
    .ticket-edit-columns > [class*="col-"] { margin-bottom: 0.85rem; }
}

/* Listados */
.table-container {
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(23, 32, 51, 0.04);
    margin-bottom: 1.5rem;
    padding: 0.5rem;
    overflow: hidden;
}
.tickets-list-table thead th {
    background: #f7f9fc;
    color: #202654;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #edf1f6;
    white-space: nowrap;
}
.tickets-list-table tbody td {
    vertical-align: middle;
    color: #334155;
    border-color: #edf1f6;
}
.tickets-list-table tbody tr:hover {
    background: #fbfcfe;
}
@media (max-width: 767.98px) {
    .table th, .table td {
        padding: 0.5rem 0.5rem;
        font-size: 0.95rem;
    }
    .table-responsive { overflow-x: auto; }
}

/* Modales */
.modal { z-index: 1050 !important; }
.modal-backdrop { z-index: 1040 !important; }
.modal-content {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 1rem 1rem 0 0;
}
.modal-title { font-weight: 600; color: #2c3e50; }
.modal-body { padding: 1.5rem; }
.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-radius: 0 0 1rem 1rem;
}
</style>
