<?php
/**
 * Form for "Apuntar Comedor"
 * Matches the school's dining registration requirements and design.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ap-form-card">
    <div class="ap-form-header">
        <div class="ap-form-header-content">
            <span class="ap-tag ap-tag-primary"><?php esc_html_e('Servicio de Comedor', 'area-profesores'); ?></span>
            <h2 class="ap-form-title"><?php esc_html_e('Registro Diario de Comedor', 'area-profesores'); ?></h2>
            <p class="ap-form-subtitle">
                <?php esc_html_e('Introduce el número de alumnos y dietas que se quedan a comer en el día de hoy.', 'area-profesores'); ?>
            </p>
        </div>
        <div class="ap-today-indicator">
            <span class="ap-date-label"><?php esc_html_e('Fecha de servicio:', 'area-profesores'); ?></span>
            <span class="ap-date-value">
                <svg xmlns="http://www.w3.org/2000/svg" class="ap-icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <?php echo esc_html($today_formatted); ?>
            </span>
        </div>
    </div>

    <!-- Alert / notification area -->
    <div id="ap-form-alert" class="ap-alert" style="display: none;"></div>

    <form id="ap-comedor-form" class="ap-form" method="post">
        <input type="hidden" name="fecha" id="ap-field-fecha" value="<?php echo esc_attr($today); ?>">
        <input type="hidden" name="record_id" id="ap-field-record-id" value="">

        <!-- BLOQUE 1: CLASE -->
        <div class="ap-form-group-card">
            <div class="ap-group-header">
                <label class="ap-label ap-required">
                    <?php esc_html_e('Clase', 'area-profesores'); ?>
                    <span class="ap-asterisk">*</span>
                </label>
                <span class="ap-group-help"><?php esc_html_e('Selecciona el grupo correspondiente', 'area-profesores'); ?></span>
            </div>

            <div class="ap-grid-classes">
                <?php foreach ($available_classes as $val => $label) : 
                    $is_checked = ($tutoria === $val);
                ?>
                    <label class="ap-radio-chip <?php echo $is_checked ? 'is-selected' : ''; ?>">
                        <input type="radio" name="clase" value="<?php echo esc_attr($val); ?>" <?php checked($is_checked, true); ?> required>
                        <span class="ap-chip-indicator"></span>
                        <span class="ap-chip-text"><?php echo esc_html($val); ?></span>
                        <span class="ap-chip-subtext"><?php echo esc_html($label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- BLOQUE 2: ETAPA -->
        <div class="ap-form-group-card">
            <div class="ap-group-header">
                <label class="ap-label ap-required">
                    <?php esc_html_e('Etapa', 'area-profesores'); ?>
                    <span class="ap-asterisk">*</span>
                </label>
                <span class="ap-group-help"><?php esc_html_e('Indica el ciclo educativo de los alumnos', 'area-profesores'); ?></span>
            </div>

            <div class="ap-radio-vertical-list">
                <?php foreach ($available_stages as $stage_key => $stage_name) : 
                    // Default to Primaria or based on tutoría
                    $default_stage = ($stage_key === 'Primaria');
                ?>
                    <label class="ap-radio-row">
                        <input type="radio" name="etapa" value="<?php echo esc_attr($stage_key); ?>" <?php checked($default_stage, true); ?> required>
                        <span class="ap-radio-custom"></span>
                        <span class="ap-radio-label"><?php echo esc_html($stage_name); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- BLOQUE 3: ALUMNOS QUE SE QUEDAN A COMER -->
        <div class="ap-form-group-card">
            <div class="ap-group-header">
                <label for="ap-num-alumnos" class="ap-label ap-required">
                    <?php esc_html_e('Alumnos que se quedan a comer (Sin incluir las dietas)', 'area-profesores'); ?>
                    <span class="ap-asterisk">*</span>
                </label>
                <span class="ap-group-help"><?php esc_html_e('Indica el recuento exacto de comensales con menú estándar', 'area-profesores'); ?></span>
            </div>

            <div class="ap-counter-wrapper">
                <button type="button" class="ap-counter-btn" id="ap-count-minus" aria-label="<?php esc_attr_e('Restar uno', 'area-profesores'); ?>">−</button>
                <input type="number" 
                       id="ap-num-alumnos" 
                       name="num_alumnos" 
                       class="ap-input ap-input-number" 
                       min="0" 
                       max="100" 
                       placeholder="Tu respuesta (ej: 18)" 
                       required>
                <button type="button" class="ap-counter-btn" id="ap-count-plus" aria-label="<?php esc_attr_e('Sumar uno', 'area-profesores'); ?>">+</button>
            </div>
        </div>

        <!-- BLOQUE 4: DIETAS -->
        <div class="ap-form-group-card">
            <div class="ap-group-header">
                <label for="ap-dietas" class="ap-label">
                    <?php esc_html_e('Dietas. Incluye los nombres y curso de los alumnos que tengan dieta.', 'area-profesores'); ?>
                </label>
                <span class="ap-group-help"><?php esc_html_e('Especiales, intolerancias, celiaquía, menús religiosos, etc.', 'area-profesores'); ?></span>
            </div>

            <textarea id="ap-dietas" 
                      name="dietas" 
                      class="ap-textarea" 
                      rows="3" 
                      placeholder="<?php esc_attr_e('Tu respuesta (ej: Lucas Pérez - Menú celíaco; Sofía García - Sin lactosa)', 'area-profesores'); ?>"></textarea>
        </div>

        <!-- BLOQUE 5: INFO BANNER "MÁS DATOS" -->
        <div class="ap-info-notice">
            <div class="ap-info-notice-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="ap-info-notice-body">
                <h4 class="ap-info-notice-title"><?php esc_html_e('MÁS DATOS', 'area-profesores'); ?></h4>
                <p class="ap-info-notice-text">
                    <?php esc_html_e('A continuación escribe los alérgicos que no han venido para que en la cocina no preparen esos menús especiales que luego no se pueden dar a otro alumno. Puedes usar también este campo para indicar alguna cosa extraordinaria que consideres importante.', 'area-profesores'); ?>
                </p>
            </div>
        </div>

        <!-- BLOQUE 6: ALÉRGICOS QUE FALTAN / OBSERVACIONES -->
        <div class="ap-form-group-card">
            <div class="ap-group-header">
                <label for="ap-alergicos-faltan" class="ap-label">
                    <?php esc_html_e('Alérgicos que faltan (puedes usar también este campo para indicar alguna cosa extraordinaria que consideres importante)', 'area-profesores'); ?>
                </label>
            </div>

            <textarea id="ap-alergicos-faltan" 
                      name="alergicos_faltan" 
                      class="ap-textarea" 
                      rows="3" 
                      placeholder="<?php esc_attr_e('Tu respuesta...', 'area-profesores'); ?>"></textarea>
        </div>

        <!-- ACCIONES DEL FORMULARIO -->
        <div class="ap-form-footer">
            <button type="submit" id="ap-submit-btn" class="ap-btn ap-btn-primary ap-btn-lg">
                <span class="ap-btn-text">
                    <svg class="ap-btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <?php esc_html_e('Guardar y Enviar a Cocina', 'area-profesores'); ?>
                </span>
                <span class="ap-btn-loading" style="display: none;">
                    <span class="ap-spinner"></span>
                    <?php esc_html_e('Enviando...', 'area-profesores'); ?>
                </span>
            </button>
            <button type="button" id="ap-reset-btn" class="ap-btn ap-btn-outline" style="display: none;">
                <?php esc_html_e('Nuevo registro para otra clase', 'area-profesores'); ?>
            </button>
        </div>
    </form>
</div>
