<?php
/**
 * Teacher Portal: "Comunicados" tab (only for Dirección).
 * Publish one announcement (text + optional image) in several Google Chat spaces
 * at once, now or at a scheduled date and time.
 *
 * Variables: $chat_grupos (grupo_key => array(label, espacios)), $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_total_espacios = 0;
foreach ($chat_grupos as $ap_g) {
    $ap_total_espacios += count($ap_g['espacios']);
}

// Default scheduled time: tomorrow at 08:00 (site time zone).
$ap_tz = wp_timezone();
$ap_manana = new DateTime('tomorrow 08:00', $ap_tz);
$ap_hoy = wp_date('Y-m-d');
$ap_max = wp_date('Y-m-d', time() + Area_Profesores_Chat::MAX_DIAS_PROGRAMACION * DAY_IN_SECONDS);
?>

<div class="ap-form-card ap-comunicados">
    <div class="ap-form-header">
        <div>
            <span class="ap-tag-primary"><?php esc_html_e('Dirección', 'area-profesores'); ?></span>
            <h2 class="ap-form-title"><?php esc_html_e('Comunicado a las familias', 'area-profesores'); ?></h2>
            <p class="ap-form-subtitle"><?php esc_html_e('Se publicará en Google Chat, firmado como el colegio, en todos los espacios que marques. Puedes publicarlo ya o programarlo.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($ap_total_espacios === 0) : ?>
        <div class="ap-empty-state">
            <div class="ap-empty-icon">💬</div>
            <h3><?php esc_html_e('Todavía no hay espacios de Chat conectados', 'area-profesores'); ?></h3>
            <p><?php esc_html_e('El administrador debe conectarlos en Área Profesores › Espacios de Chat.', 'area-profesores'); ?></p>
        </div>
    <?php else : ?>

    <form id="ap-comunicado-form" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="replace_id" id="ap-com-replace-id" value="">
        <input type="hidden" name="reuse_image_id" id="ap-com-reuse-image" value="">

        <div class="ap-com-editing" id="ap-com-editing" style="display:none;">
            <span id="ap-com-editing-text"></span>
            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" id="ap-com-editing-cancel"><?php esc_html_e('Descartar cambios', 'area-profesores'); ?></button>
        </div>

        <div id="ap-comunicado-alert" class="ap-alert" style="display:none;"></div>

        <div class="ap-comunicado-layout">
            <div class="ap-comunicado-main">
                <!-- Título -->
                <div class="ap-form-group-card">
                    <label class="ap-label" for="ap-com-titulo"><?php esc_html_e('Título', 'area-profesores'); ?> <span class="ap-optional"><?php esc_html_e('(opcional, aparece en negrita)', 'area-profesores'); ?></span></label>
                    <input type="text" id="ap-com-titulo" name="titulo" class="ap-textarea" maxlength="200" placeholder="<?php esc_attr_e('Ej: Excursión al Planetario', 'area-profesores'); ?>">
                </div>

                <!-- Mensaje -->
                <div class="ap-form-group-card">
                    <label class="ap-label" for="ap-com-mensaje"><?php esc_html_e('Mensaje', 'area-profesores'); ?></label>
                    <textarea id="ap-com-mensaje" name="mensaje" class="ap-textarea" rows="8" maxlength="<?php echo (int) Area_Profesores_Chat::MAX_TEXT; ?>" placeholder="<?php esc_attr_e('Escribe aquí el comunicado…', 'area-profesores'); ?>"></textarea>
                    <div class="ap-com-help">
                        <span><?php echo wp_kses(__('Formato de Chat: <code>*negrita*</code> · <code>_cursiva_</code> · <code>~tachado~</code>. Los enlaces se activan solos.', 'area-profesores'), array('code' => array())); ?></span>
                        <span class="ap-com-count"><span id="ap-com-count">0</span>/<?php echo (int) Area_Profesores_Chat::MAX_TEXT; ?></span>
                    </div>
                </div>

                <!-- Imagen -->
                <div class="ap-form-group-card">
                    <span class="ap-label"><?php esc_html_e('Imagen', 'area-profesores'); ?> <span class="ap-optional"><?php esc_html_e('(JPG, PNG o GIF, máx. 8 MB)', 'area-profesores'); ?></span></span>
                    <label class="ap-com-dropzone" id="ap-com-dropzone">
                        <input type="file" id="ap-com-imagen" name="imagen" accept="image/jpeg,image/png,image/gif">
                        <span class="ap-com-dropzone-text">🖼️ <?php esc_html_e('Pulsa para elegir una imagen o arrástrala aquí', 'area-profesores'); ?></span>
                    </label>
                    <div class="ap-com-image-preview" id="ap-com-image-preview" style="display:none;">
                        <img src="" alt="">
                        <button type="button" class="ap-btn ap-btn-sm ap-btn-subtle-danger" id="ap-com-image-remove"><?php esc_html_e('Quitar imagen', 'area-profesores'); ?></button>
                    </div>
                </div>

                <!-- Espacios -->
                <div class="ap-form-group-card">
                    <div class="ap-com-espacios-header">
                        <span class="ap-label"><?php esc_html_e('Publicar en', 'area-profesores'); ?></span>
                        <div class="ap-com-quick">
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-select="all"><?php esc_html_e('Todo el colegio', 'area-profesores'); ?></button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-select="none"><?php esc_html_e('Ninguno', 'area-profesores'); ?></button>
                        </div>
                    </div>

                    <?php foreach ($chat_grupos as $grupo) : ?>
                        <fieldset class="ap-com-grupo">
                            <legend>
                                <label class="ap-com-grupo-toggle">
                                    <input type="checkbox" class="ap-com-grupo-check">
                                    <?php echo esc_html($grupo['label']); ?>
                                </label>
                            </legend>
                            <div class="ap-com-chips">
                                <?php foreach ($grupo['espacios'] as $e) : ?>
                                    <label class="ap-com-chip" title="<?php echo esc_attr($e['subtitulo']); ?>">
                                        <input type="checkbox" name="espacios[]" value="<?php echo esc_attr($e['id']); ?>">
                                        <span><?php echo esc_html($e['titulo']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>

                <!-- Cuándo -->
                <div class="ap-form-group-card">
                    <span class="ap-label"><?php esc_html_e('Cuándo', 'area-profesores'); ?></span>
                    <div class="ap-com-modo">
                        <label class="ap-com-chip is-selected">
                            <input type="radio" name="modo" value="ahora" checked>
                            <span>⚡ <?php esc_html_e('Publicar ahora', 'area-profesores'); ?></span>
                        </label>
                        <label class="ap-com-chip">
                            <input type="radio" name="modo" value="programar">
                            <span>🕗 <?php esc_html_e('Programar', 'area-profesores'); ?></span>
                        </label>
                    </div>
                    <div class="ap-com-programar" id="ap-com-programar" style="display:none;">
                        <div class="ap-com-programar-fields">
                            <label>
                                <span><?php esc_html_e('Día', 'area-profesores'); ?></span>
                                <input type="date" name="fecha" id="ap-com-fecha" class="ap-textarea" min="<?php echo esc_attr($ap_hoy); ?>" max="<?php echo esc_attr($ap_max); ?>" value="<?php echo esc_attr($ap_manana->format('Y-m-d')); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Hora', 'area-profesores'); ?></span>
                                <input type="time" name="hora" id="ap-com-hora" class="ap-textarea" step="300" value="08:00">
                            </label>
                        </div>
                        <div class="ap-com-quick ap-com-quick-dates">
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-when="tomorrow"><?php esc_html_e('Mañana 8:00', 'area-profesores'); ?></button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-when="monday"><?php esc_html_e('Lunes 8:00', 'area-profesores'); ?></button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-when="today17"><?php esc_html_e('Hoy 17:00', 'area-profesores'); ?></button>
                        </div>
                        <p class="ap-com-programar-text" id="ap-com-programar-text"></p>
                    </div>
                </div>
            </div>

            <!-- Vista previa -->
            <aside class="ap-comunicado-preview">
                <span class="ap-label"><?php esc_html_e('Vista previa', 'area-profesores'); ?></span>
                <div class="ap-chat-bubble">
                    <div class="ap-chat-bubble-head">
                        <?php if (!empty($logo_url)) : ?>
                            <img class="ap-chat-avatar" src="<?php echo esc_url($logo_url); ?>" alt="">
                        <?php endif; ?>
                        <strong><?php esc_html_e('Colegio Casa de la Virgen', 'area-profesores'); ?></strong>
                        <span class="ap-chat-app-tag"><?php esc_html_e('App', 'area-profesores'); ?></span>
                    </div>
                    <div class="ap-chat-bubble-text" id="ap-com-preview-text"><em><?php esc_html_e('El texto aparecerá aquí…', 'area-profesores'); ?></em></div>
                    <img class="ap-chat-bubble-img" id="ap-com-preview-img" src="" alt="" style="display:none;">
                </div>
                <p class="ap-com-preview-note"><?php esc_html_e('El nombre y el icono son los del webhook configurado en cada espacio.', 'area-profesores'); ?></p>
            </aside>
        </div>

        <div class="ap-form-footer">
            <button type="submit" class="ap-btn ap-btn-primary ap-btn-lg" id="ap-com-submit" disabled>
                <span class="ap-com-submit-label"><?php esc_html_e('Publicar', 'area-profesores'); ?></span>
            </button>
            <span class="ap-com-selected" id="ap-com-selected"><?php esc_html_e('Ningún espacio seleccionado', 'area-profesores'); ?></span>
        </div>
    </form>

    <!-- Resultado del envío -->
    <div class="ap-com-result" id="ap-com-result" style="display:none;">
        <h3 class="ap-com-result-title"></h3>
        <p class="ap-com-result-text"></p>
        <ul class="ap-com-result-list"></ul>
        <div class="ap-com-result-actions">
            <button type="button" class="ap-btn ap-btn-primary ap-btn-sm" id="ap-com-retry" style="display:none;"><?php esc_html_e('Reintentar los que han fallado', 'area-profesores'); ?></button>
            <button type="button" class="ap-btn ap-btn-outline ap-btn-sm" id="ap-com-new"><?php esc_html_e('Escribir otro comunicado', 'area-profesores'); ?></button>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Programados -->
<div class="ap-records-card ap-com-historial-card">
    <div class="ap-records-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Programados', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Se publicarán solos en la fecha indicada. Puedes editarlos, enviarlos ya o cancelarlos.', 'area-profesores'); ?></p>
        </div>
    </div>
    <div id="ap-com-programados">
        <?php echo Area_Profesores_Chat::render_programados_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </div>
</div>

<!-- Historial -->
<div class="ap-records-card ap-com-historial-card">
    <div class="ap-records-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Últimos comunicados', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Comunicados publicados desde el Área de Profesores y en qué espacios se entregaron.', 'area-profesores'); ?></p>
        </div>
    </div>
    <div id="ap-com-historial">
        <?php echo Area_Profesores_Chat::render_historial_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </div>
</div>
