<?php
if (!defined('TASK_DELETE_SCRIPT_LOADED')) {
    define('TASK_DELETE_SCRIPT_LOADED', true);
    ?>
<script>
function eliminarTarea(taskId, folio, redirectUrl) {
    taskId = parseInt(taskId, 10);
    if (!taskId) return;
    const msg = '¿Eliminar la tarea "' + folio + '"?\n\nSe ocultará del listado junto con sus actividades. Esta acción no se puede deshacer.';
    if (!confirm(msg)) {
        return;
    }
    const fd = new FormData();
    fd.append('task_id', taskId);
    fetch(new URL('task_delete.php', window.location.href), {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
        .then(function(r) {
            return r.text().then(function(text) {
                try {
                    return { ok: r.ok, data: JSON.parse(text) };
                } catch (e) {
                    throw new Error('Respuesta inválida del servidor');
                }
            });
        })
        .then(function(res) {
            if (res.data && res.data.ok) {
                window.location.href = redirectUrl || 'tasks.php';
            } else {
                alert((res.data && res.data.message) || 'No se pudo eliminar la tarea.');
            }
        })
        .catch(function() {
            alert('Error de conexión al eliminar la tarea.');
        });
}

document.querySelectorAll('.btn-delete-task').forEach(function(btn) {
    btn.addEventListener('click', function() {
        eliminarTarea(
            this.dataset.taskId,
            this.dataset.folio || '',
            this.dataset.redirect || 'tasks.php'
        );
    });
});
</script>
    <?php
}
