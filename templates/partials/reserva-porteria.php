<?php
/**
 * Portería: book a service for a family (parents often ask in person or by phone).
 * Variables: $ap_pt { servicios, familias, flash, hoy }
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_script('area-profesores-reservas-porteria');
$ap_old = ($ap_pt['flash'] && $ap_pt['flash']['type'] === 'error' && is_array($ap_pt['flash']['old'])) ? $ap_pt['flash']['old'] : array();
$ap_servs = array();
foreach ($ap_pt['servicios'] as $k => $s) {
    $ap_servs[$k] = array(
        'nombre'   => $s['nombre'],
        'varios'   => $s['varios_dias'] === 'yes',
        'etapas'   => array_values((array) $s['etapas']),
        'desayuno' => $s['desayuno'] === 'yes',
        'dieta'    => $s['dieta'] === 'yes',
        'recoge'   => $s['recoge'] === 'yes',
        'hora_ini' => $s['hora_inicio'],
        'hora_fin' => $s['hora_fin'],
    );
}
$ap_tipos = array();
if (isset($ap_pt['servicios']['dieta'])) {
    $ap_tipos = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $ap_pt['servicios']['dieta']['tipos_dieta'])));
}
if (!$ap_tipos) {
    $ap_tipos = array(__('Dieta blanda', 'area-profesores'), __('Sin lactosa', 'area-profesores'), __('Sin gluten', 'area-profesores'));
}
?>
<details class="ap-rp"<?php echo $ap_old ? ' open' : ''; ?>>
    <summary class="ap-btn ap-btn-primary ap-btn-sm"><?php esc_html_e('+ Nueva reserva para una familia', 'area-profesores'); ?></summary>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-rp-form" id="ap-rp-form"
          data-familias="<?php echo esc_attr(wp_json_encode($ap_pt['familias'])); ?>"
          data-servicios="<?php echo esc_attr(wp_json_encode($ap_servs)); ?>"
          data-old="<?php echo esc_attr(wp_json_encode($ap_old)); ?>"
          data-textos="<?php echo esc_attr(wp_json_encode(array('t0' => __('Familia:', 'area-profesores'), 't1' => __('Correo de la familia:', 'area-profesores'), 't2' => __('Esta familia no tiene correo: no recibirá la confirmación.', 'area-profesores'), 't3' => __('No hay ningún alumno con ese nombre.', 'area-profesores'), 't4' => __('Hora de llegada', 'area-profesores'), 't5' => __('Hora de salida', 'area-profesores'), 't6' => __('Este servicio no está disponible para su etapa', 'area-profesores'), 't7' => __('Busca al alumno y elígelo de la lista.', 'area-profesores'), 't8' => __('Marca para qué alumno es la reserva.', 'area-profesores'), 't9' => __('Elige el servicio.', 'area-profesores')))); ?>">
        <input type="hidden" name="action" value="ap_reserva_porteria">
        <input type="hidden" name="familia_id" value="">
        <?php wp_nonce_field('ap_reserva_porteria'); ?>
        <p class="ap-records-subtitle"><?php esc_html_e('Para cuando una familia pide un servicio de palabra, por teléfono o por correo. Se avisa al tutor/a (y a quien reciba el aviso en "Avisos"), igual que si lo reservara la familia, y la reserva aparece en su Área de Familias. No hay plazos: puedes reservar para hoy a cualquier hora.', 'area-profesores'); ?>
            <?php if (empty($ap_pt['porteria'])) : ?> <?php esc_html_e('Solo salen los alumnos de tus clases. Puedes anular las reservas que hagas tú.', 'area-profesores'); ?><?php endif; ?></p>

        <fieldset class="ap-rp-paso">
            <legend>1. <?php esc_html_e('Alumno', 'area-profesores'); ?></legend>
            <div class="ap-rp-buscar">
                <input type="search" class="ap-rp-q" placeholder="<?php esc_attr_e('Escribe el nombre del alumno o de la familia…', 'area-profesores'); ?>" autocomplete="off" aria-label="<?php esc_attr_e('Buscar alumno', 'area-profesores'); ?>">
                <ul class="ap-rp-resultados" hidden></ul>
            </div>
            <div class="ap-rp-familia" hidden>
                <div class="ap-rp-familia-head"><strong class="ap-rp-familia-nombre"></strong> <button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-rp-cambiar"><?php esc_html_e('Cambiar', 'area-profesores'); ?></button></div>
                <div class="ap-rp-hijos"></div>
                <small class="ap-rp-emails"></small>
            </div>
        </fieldset>

        <fieldset class="ap-rp-paso">
            <legend>2. <?php esc_html_e('Servicio', 'area-profesores'); ?></legend>
            <div class="ap-rp-servicios">
                <?php foreach ($ap_pt['servicios'] as $k => $s) : ?>
                    <label class="ap-rp-serv"><input type="radio" name="servicio" value="<?php echo esc_attr($k); ?>"> <span><?php echo esc_html(trim($s['icono'] . ' ' . $s['nombre'])); ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="ap-rp-paso">
            <legend>3. <?php esc_html_e('Cuándo', 'area-profesores'); ?></legend>
            <div class="ap-rp-modo" data-si="varios">
                <label><input type="radio" name="modo" value="dia" checked> <?php esc_html_e('Un día', 'area-profesores'); ?></label>
                <label><input type="radio" name="modo" value="varios"> <?php esc_html_e('Varios días', 'area-profesores'); ?></label>
            </div>
            <div class="ap-rp-fila" data-modo="dia">
                <label><?php esc_html_e('Día', 'area-profesores'); ?> <input type="date" name="fecha" value="<?php echo esc_attr($ap_pt['hoy']); ?>" min="<?php echo esc_attr($ap_pt['hoy']); ?>"></label>
            </div>
            <div class="ap-rp-fila" data-modo="varios" hidden>
                <label><?php esc_html_e('Desde', 'area-profesores'); ?> <input type="date" name="desde" value="<?php echo esc_attr($ap_pt['hoy']); ?>" min="<?php echo esc_attr($ap_pt['hoy']); ?>"></label>
                <label><?php esc_html_e('Hasta', 'area-profesores'); ?> <input type="date" name="hasta" min="<?php echo esc_attr($ap_pt['hoy']); ?>"></label>
                <span class="ap-rp-dias">
                    <?php foreach (array(1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V') as $n => $d) : ?>
                        <label><input type="checkbox" name="dias[]" value="<?php echo (int) $n; ?>" checked> <?php echo esc_html($d); ?></label>
                    <?php endforeach; ?>
                </span>
            </div>
            <div class="ap-rp-fila" data-si="hora" hidden>
                <label><span class="ap-rp-hora-txt"><?php esc_html_e('Hora', 'area-profesores'); ?></span> <input type="time" name="hora"></label>
            </div>
        </fieldset>

        <fieldset class="ap-rp-paso">
            <legend>4. <?php esc_html_e('Detalles', 'area-profesores'); ?></legend>
            <div class="ap-rp-fila" data-si="desayuno" hidden>
                <span><?php esc_html_e('¿Con desayuno?', 'area-profesores'); ?></span>
                <label><input type="radio" name="desayuno" value="si"> <?php esc_html_e('Sí', 'area-profesores'); ?></label>
                <label><input type="radio" name="desayuno" value="no"> <?php esc_html_e('No', 'area-profesores'); ?></label>
            </div>
            <p class="ap-rp-fila ap-rp-nota-dieta" data-si="dieta_aviso" hidden><?php esc_html_e('🥗 Solo se puede reservar una dieta los días que el alumno se queda a comer (comensal fijo o con el comedor reservado). Si no se queda, elige el servicio Comedor y marca ahí que necesita dieta.', 'area-profesores'); ?></p>
            <div class="ap-rp-fila" data-si="dieta_sn" hidden>
                <span><?php esc_html_e('¿Necesita dieta?', 'area-profesores'); ?></span>
                <label><input type="radio" name="dieta" value="no" checked> <?php esc_html_e('No', 'area-profesores'); ?></label>
                <label><input type="radio" name="dieta" value="si"> <?php esc_html_e('Sí', 'area-profesores'); ?></label>
            </div>
            <div class="ap-rp-fila" data-si="dieta" hidden>
                <label><?php esc_html_e('Tipo de dieta', 'area-profesores'); ?>
                    <select name="tipo_dieta">
                        <option value=""><?php esc_html_e('— Elige —', 'area-profesores'); ?></option>
                        <?php foreach ($ap_tipos as $t) : ?><option value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></option><?php endforeach; ?>
                    </select></label>
                <label class="ap-rp-ancho"><?php esc_html_e('Instrucciones especiales', 'area-profesores'); ?> <input type="text" name="instrucciones"></label>
            </div>
            <div class="ap-rp-fila" data-si="recoge" hidden>
                <label class="ap-rp-ancho"><?php esc_html_e('Quién le recoge', 'area-profesores'); ?> <input type="text" name="recoge" placeholder="<?php esc_attr_e('Ej.: su abuela, Carmen', 'area-profesores'); ?>"></label>
            </div>
            <div class="ap-rp-fila" data-si="motivo" hidden>
                <label class="ap-rp-ancho"><?php esc_html_e('Motivo', 'area-profesores'); ?> <input type="text" name="motivo" placeholder="<?php esc_attr_e('Ej.: cita médica', 'area-profesores'); ?>"></label>
            </div>
            <div class="ap-rp-fila">
                <label class="ap-rp-ancho"><?php esc_html_e('Observaciones', 'area-profesores'); ?> <input type="text" name="observaciones"></label>
            </div>
            <div class="ap-rp-fila">
                <label class="ap-rp-ancho"><?php esc_html_e('Quién lo ha pedido', 'area-profesores'); ?> <input type="text" name="pedido" placeholder="<?php esc_attr_e('Ej.: la madre, por teléfono', 'area-profesores'); ?>"></label>
            </div>
            <label class="ap-rp-confirmar"><input type="checkbox" name="confirmar" value="1" checked> <?php esc_html_e('Enviar la confirmación por correo a la familia', 'area-profesores'); ?></label>
        </fieldset>

        <p class="ap-rp-error" role="alert" hidden></p>
        <button type="submit" class="ap-btn ap-btn-primary"><?php esc_html_e('Hacer la reserva', 'area-profesores'); ?></button>
    </form>
</details>
