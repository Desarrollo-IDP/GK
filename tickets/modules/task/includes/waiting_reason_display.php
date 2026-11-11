<?php
/**
 * Motivo de En atención / Espera (estado 10).
 * Requiere: $waitingInfo con status_reason, started_at, started_by
 */
if (empty($waitingInfo) || empty($waitingInfo['status_reason'])) {
    return;
}
?>
<section class="subtask-section subtask-section-waiting" aria-label="Motivo de espera">
    <div class="subtask-section-label">
        <i class="fas fa-hourglass-half"></i>
        Motivo de En atención / Espera
    </div>
    <div class="task-waiting-reason-box">
        <p class="task-waiting-reason-text"><?php echo nl2br(htmlspecialchars($waitingInfo['status_reason'])); ?></p>
        <?php if (!empty($waitingInfo['started_at']) || !empty($waitingInfo['started_by'])): ?>
        <div class="task-waiting-reason-meta">
            <?php if (!empty($waitingInfo['started_at'])): ?>
            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($waitingInfo['started_at']); ?></span>
            <?php endif; ?>
            <?php if (!empty($waitingInfo['started_by'])): ?>
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($waitingInfo['started_by']); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
