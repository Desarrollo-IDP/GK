<?php
/**
 * Campo de descripción con editor Quill.
 *
 * @var string $fieldId
 * @var string $fieldName
 * @var string $label
 * @var string $value
 * @var string $placeholder
 * @var bool   $required
 * @var string $wrapperClass
 */
$fieldId = $fieldId ?? 'description';
$fieldName = $fieldName ?? 'description';
$editorId = $fieldId . '_quill';
$label = $label ?? 'Descripción';
$value = $value ?? '';
$placeholder = $placeholder ?? 'Detalle del trabajo a realizar…';
$required = !empty($required);
$wrapperClass = $wrapperClass ?? 'span-full';
$labelClass = $labelClass ?? 'task-form-label';
$showLabel = !isset($showLabel) || $showLabel;
$deferQuillInit = isset($deferQuillInit) && $deferQuillInit === true;
$editorContent = taskPrepareDescriptionForEditor($value);
$initialContentB64 = base64_encode($editorContent);
$safeEditorContent = str_replace('</template>', '<\\/template>', $editorContent);
$safeTextareaContent = str_replace('</textarea>', '</text' . 'area>', $editorContent);
?>
<div class="task-quill-field <?php echo htmlspecialchars($wrapperClass); ?>"
    data-placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"
    data-initial-content-b64="<?php echo htmlspecialchars($initialContentB64, ENT_QUOTES, 'UTF-8'); ?>"
    <?php if (!empty($deferQuillInit)): ?>data-defer-init="1"<?php endif; ?>>
    <?php if ($showLabel && $label !== ''): ?>
    <label class="<?php echo htmlspecialchars($labelClass); ?>" for="<?php echo htmlspecialchars($editorId); ?>">
        <?php echo htmlspecialchars($label); ?>
        <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
    </label>
    <?php endif; ?>
    <template class="task-quill-initial"><?php echo $safeEditorContent; ?></template>
    <div id="<?php echo htmlspecialchars($editorId); ?>" class="task-quill-editor"></div>
    <textarea
        name="<?php echo htmlspecialchars($fieldName); ?>"
        id="<?php echo htmlspecialchars($fieldId); ?>"
        class="task-quill-input d-none"
        aria-hidden="true"
        tabindex="-1"
        data-initial-html="<?php echo htmlspecialchars(str_replace(["\r\n", "\r", "\n"], ' ', $editorContent), ENT_QUOTES, 'UTF-8'); ?>"
        <?php if ($required): ?>data-required="1"<?php endif; ?>
    ><?php echo $safeTextareaContent; ?></textarea>
</div>
