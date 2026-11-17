<?php
/**
 * Teacher Portal: "Comunicados" tab (only for Dirección).
 * Publish one announcement (text + optional image) in several Google Chat spaces at once.
 *
 * Variables: $chat_grupos (array grupo => espacios), $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_total_espacios = 0;
foreach ($chat_grupos as $ap_g) {
    $ap_total_espacios += count($ap_g);
}
?>

<div class="ap-form-card ap-comunicados">
    <div class="ap-form-header">
        <div>
            <span class="ap-tag-primary"><?php esc_html_e('Dirección', 'area-profesores'); ?></span>
            <h2 class="ap-form-title"><?php esc_html_e('Comunicado a las familias', 'area-profesores'); ?></h2>
            <p class="ap-form-subtitle"><?php esc_html_e('Se publicará en Google Chat, firmado como el colegio, en todos los espacios que marques.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($ap_total_espacios === 0) : ?>
        <div class="ap-empty-state">
            <div class="ap-empty-icon">💬</div>
            <h3><?php esc_html_e('Todavía no hay espacios de Chat conectados', 'area-profesores'); ?></h3>
            <p><?php esc_html_e('El administrador debe darlos de alta en Área Profesores › Espacios de Chat.', 'area-profesores'); ?></p>
        </div>
    <?php else : ?>

    <form id="ap-comunicado-form" enctype="multipart/form-data" novalidate>
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
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-select="all"><?php esc_html_e('Todos', 'area-profesores'); ?></button>
                            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" data-ap-select="none"><?php esc_html_e('Ninguno', 'area-profesores'); ?></button>
                        </div>
                    </div>

                    <?php foreach ($chat_grupos as $grupo => $espacios_grupo) : ?>
                        <fieldset class="ap-com-grupo">
                            <legend>
                                <label class="ap-com-grupo-toggle">
                                    <input type="checkbox" class="ap-com-grupo-check">
                                    <?php echo esc_html($grupo); ?>
                                </label>
                            </legend>
                            <div class="ap-com-chips">
                                <?php foreach ($espacios_grupo as $e) : ?>
                                    <label class="ap-com-chip">
                                        <input type="checkbox" name="espacios[]" value="<?php echo esc_attr($e['id']); ?>" data-nombre="<?php echo esc_attr($e['nombre']); ?>">
                                        <span><?php echo esc_html($e['nombre']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
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
        <ul class="ap-com-result-list"></ul>
        <div class="ap-com-result-actions">
            <button type="button" class="ap-btn ap-btn-primary ap-btn-sm" id="ap-com-retry" style="display:none;"><?php esc_html_e('Reintentar los que han fallado', 'area-profesores'); ?></button>
            <button type="button" class="ap-btn ap-btn-outline ap-btn-sm" id="ap-com-new"><?php esc_html_e('Escribir otro comunicado', 'area-profesores'); ?></button>
        </div>
    </div>

    <?php endif; ?>
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
