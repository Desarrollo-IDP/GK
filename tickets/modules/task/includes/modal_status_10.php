<!-- Modal: motivo obligatorio para estado En atención / Espera (10) -->
<div class="modal fade" id="modalStatus10" tabindex="-1" aria-labelledby="modalStatus10Label" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalStatus10Label">En atención / Espera</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Indique por qué el trabajo queda en espera. Registrado por: <strong><?php echo htmlspecialchars(getUserName()); ?></strong></p>
        <label for="waiting_reason_input" class="form-label">Motivo <span class="text-danger">*</span></label>
        <textarea class="form-control" id="waiting_reason_input" rows="4" minlength="10" required
          placeholder="Ej.: Esperando respuesta del proveedor, falta de acceso al servidor..."></textarea>
        <div class="invalid-feedback" id="waiting_reason_error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnConfirmStatus10">Guardar y cambiar estado</button>
      </div>
    </div>
  </div>
</div>
