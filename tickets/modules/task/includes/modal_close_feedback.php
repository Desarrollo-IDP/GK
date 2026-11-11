<!-- Modal: retroalimentación obligatoria al cerrar (estado 3) -->
<div class="modal fade" id="modalCloseFeedback" tabindex="-1" aria-labelledby="modalCloseFeedbackLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCloseFeedbackLabel">Cerrar — retroalimentación requerida</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="close_open_activities_warning" class="alert alert-warning d-none mb-3" role="alert">
          <div class="d-flex gap-2">
            <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
            <div>
              <strong>Hay actividades aún abiertas.</strong>
              <p class="mb-2 small">Si cierra la tarea, las siguientes actividades también se marcarán como <strong>Cerrado</strong> automáticamente:</p>
              <ul id="close_open_activities_list" class="mb-0 small ps-3"></ul>
            </div>
          </div>
        </div>
        <p class="text-muted small mb-2" id="close_feedback_context">
          Indique qué se realizó antes de cerrar. Registrado por: <strong><?php echo htmlspecialchars(getUserName()); ?></strong>
        </p>
        <label for="close_feedback_input" class="form-label">Retroalimentación / lo que hice <span class="text-danger">*</span></label>
        <textarea class="form-control" id="close_feedback_input" rows="5" minlength="5" required
          placeholder="Describe la solución, el trabajo completado o el resultado…"></textarea>
        <div class="invalid-feedback d-block" id="close_feedback_error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="btnConfirmClose">
          <i class="fas fa-check me-1"></i>Guardar y cerrar
        </button>
      </div>
    </div>
  </div>
</div>
