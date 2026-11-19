<?php
/**
 * Admin View: Alumnos y familias
 * Variables: $tab, $flash, $totales, $cursos_por_etapa, $data
 */

if (!defined('ABSPATH')) {
    exit;
}

$ap_url = function ($args = array()) {
    return add_query_arg($args, admin_url('admin.php?page=' . Area_Profesores_Alumnos_Admin::SLUG));
};
$ap_curso_label = function ($k) {
    $c = Area_Profesores_Cursos::get_curso($k);
    return $c ? $c['label'] : '—';
};
$ap_curso_options = function ($selected, $con_etapas = false) use ($cursos_por_etapa) {
    foreach ($cursos_por_etapa as $etapa => $cursos) {
        echo '<optgroup label="' . esc_attr(Area_Profesores_Cursos::get_etapa_label($etapa)) . '">';
        if ($con_etapas) {
            echo '<option value="etapa:' . esc_attr($etapa) . '"' . selected($selected, 'etapa:' . $etapa, false) . '>' . esc_html(sprintf(__('Toda %s', 'area-profesores'), Area_Profesores_Cursos::get_etapa_label($etapa))) . '</option>';
        }
        foreach ($cursos as $k => $c) {
            echo '<option value="' . esc_attr($k) . '"' . selected($selected, $k, false) . '>' . esc_html($c['label']) . '</option>';
        }
        echo '</optgroup>';
    }
};
$tabs = array(
    'alumnos'   => sprintf(__('Alumnos (%d)', 'area-profesores'), $totales['alumnos']),
    'familias'  => sprintf(__('Familias (%d)', 'area-profesores'), $totales['familias']),
    'importar'  => __('Importar de Alexia', 'area-profesores'),
    'historial' => __('Historial', 'area-profesores'),
);
?>
<div class="wrap ap-admin-wrap ap-alumnos-admin">
    <div class="ap-admin-header">
        <div class="ap-admin-title-area">
            <span class="ap-admin-badge"><?php esc_html_e('Área Profesores', 'area-profesores'); ?></span>
            <h1 class="wp-heading-inline"><?php esc_html_e('Alumnos y familias', 'area-profesores'); ?></h1>
            <p class="ap-admin-lead"><?php esc_html_e('Los datos vienen de Alexia (importación por etapas) y se pueden corregir aquí en cualquier momento. Los tutores ven a sus alumnos en "Mis alumnos" y las familias ven a sus hijos en el Área Familias.', 'area-profesores'); ?></p>
        </div>
    </div>

    <?php if ($flash) : ?>
        <div class="notice notice-<?php echo esc_attr($flash['type']); ?> is-dismissible"><p><?php echo esc_html($flash['text']); ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper ap-alumnos-tabs">
        <?php foreach ($tabs as $k => $label) : ?>
            <a href="<?php echo esc_url($ap_url(array('tab' => $k))); ?>" class="nav-tab <?php echo $tab === $k ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'alumnos') : $fl = $data['filtros']; ?>

        <?php if (isset($data['editar'])) : $al = $data['editar']; ?>
            <div class="ap-settings-card ap-edit-card">
                <h2 class="ap-settings-section-title"><span class="dashicons dashicons-id"></span> <?php echo $al['id'] ? esc_html($al['nombre']) : esc_html__('Nuevo alumno', 'area-profesores'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ap_alumno_guardar">
                    <input type="hidden" name="id" value="<?php echo esc_attr($al['id']); ?>">
                    <?php wp_nonce_field('ap_alumno_guardar'); ?>
                    <div class="ap-form-grid">
                        <p><label><strong><?php esc_html_e('Nombre y apellidos', 'area-profesores'); ?></strong><br><input type="text" name="nombre" class="widefat" required value="<?php echo esc_attr($al['nombre']); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Correo del colegio', 'area-profesores'); ?></strong><br><input type="email" name="email" class="widefat" value="<?php echo esc_attr($al['email']); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Curso', 'area-profesores'); ?></strong><br>
                            <select name="curso" class="widefat"><option value=""><?php esc_html_e('— Sin curso —', 'area-profesores'); ?></option><?php $ap_curso_options($al['curso']); ?></select></label></p>
                        <p><label><strong><?php esc_html_e('Estado', 'area-profesores'); ?></strong><br>
                            <select name="estado" class="widefat">
                                <option value="alta" <?php selected($al['estado'], 'alta'); ?>><?php esc_html_e('De alta', 'area-profesores'); ?></option>
                                <option value="baja" <?php selected($al['estado'], 'baja'); ?>><?php esc_html_e('De baja', 'area-profesores'); ?></option>
                            </select></label>
                            <?php if (!empty($al['fecha_baja'])) : ?><span class="description"><?php printf(esc_html__('Baja desde %s', 'area-profesores'), esc_html(date_i18n(get_option('date_format'), strtotime($al['fecha_baja'])))); ?></span><?php endif; ?>
                        </p>
                    </div>
                    <p><label><strong><?php esc_html_e('Notas internas', 'area-profesores'); ?></strong><br><textarea name="notas" class="widefat" rows="2"><?php echo esc_textarea((string) $al['notas']); ?></textarea></label></p>

                    <h3><?php esc_html_e('Familias', 'area-profesores'); ?></h3>
                    <?php if (empty($data['editar_familias'])) : ?>
                        <p class="description"><?php esc_html_e('Sin familia asignada.', 'area-profesores'); ?></p>
                    <?php else : ?>
                        <ul class="ap-list-plain">
                            <?php foreach ($data['editar_familias'] as $fm) : ?>
                                <li>
                                    <a href="<?php echo esc_url($ap_url(array('tab' => 'familias', 'editar' => $fm['id']))); ?>"><?php echo esc_html($fm['nombre']); ?></a>
                                    <span class="description"><?php echo esc_html($fm['emails']); ?></span>
                                    <label class="ap-quitar"><input type="checkbox" name="quitar_familia[]" value="<?php echo esc_attr($fm['id']); ?>"> <?php esc_html_e('quitar', 'area-profesores'); ?></label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p><label><?php esc_html_e('Añadir a otra familia (por ejemplo, padres separados):', 'area-profesores'); ?><br>
                        <select name="anadir_familia" class="widefat ap-select-familia">
                            <option value="0"><?php esc_html_e('— Ninguna —', 'area-profesores'); ?></option>
                            <?php foreach ($data['todas_familias'] as $fm) : ?>
                                <option value="<?php echo esc_attr($fm['id']); ?>"><?php echo esc_html($fm['nombre'] . ' · ' . $fm['emails']); ?></option>
                            <?php endforeach; ?>
                        </select></label></p>

                    <p class="ap-form-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Guardar', 'area-profesores'); ?></button>
                        <a class="button" href="<?php echo esc_url($ap_url(array('tab' => 'alumnos', 'curso' => $fl['f_curso'], 'estado' => $fl['f_estado'], 'q' => $fl['f_q']))); ?>"><?php esc_html_e('Cerrar', 'area-profesores'); ?></a>
                        <?php if ($al['id']) : ?>
                            <a class="button button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_alumno_borrar&id=' . $al['id']), 'ap_alumno_borrar_' . $al['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('¿Eliminar del todo a este alumno? Si se ha ido del colegio es mejor marcarlo "De baja".', 'area-profesores')); ?>');"><?php esc_html_e('Eliminar', 'area-profesores'); ?></a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        <?php endif; ?>

        <form method="get" class="ap-admin-toolbar ap-filtros">
            <input type="hidden" name="page" value="<?php echo esc_attr(Area_Profesores_Alumnos_Admin::SLUG); ?>">
            <input type="hidden" name="tab" value="alumnos">
            <select name="curso" onchange="this.form.submit()"><option value=""><?php esc_html_e('Todos los cursos', 'area-profesores'); ?></option><?php $ap_curso_options($fl['f_curso'], true); ?></select>
            <select name="estado" onchange="this.form.submit()">
                <option value="alta" <?php selected($fl['f_estado'], 'alta'); ?>><?php esc_html_e('De alta', 'area-profesores'); ?></option>
                <option value="baja" <?php selected($fl['f_estado'], 'baja'); ?>><?php printf(esc_html__('De baja (%d)', 'area-profesores'), (int) $totales['bajas']); ?></option>
                <option value="todos" <?php selected($fl['f_estado'], 'todos'); ?>><?php esc_html_e('Todos', 'area-profesores'); ?></option>
            </select>
            <input type="search" name="q" value="<?php echo esc_attr($fl['f_q']); ?>" placeholder="<?php esc_attr_e('Buscar por nombre o correo…', 'area-profesores'); ?>">
            <button class="button"><?php esc_html_e('Buscar', 'area-profesores'); ?></button>
            <span class="ap-toolbar-stats"><?php printf(esc_html(_n('%d alumno', '%d alumnos', count($data['alumnos']), 'area-profesores')), count($data['alumnos'])); ?></span>
            <a class="button button-primary" href="<?php echo esc_url($ap_url(array('tab' => 'alumnos', 'editar' => 0))); ?>"><?php esc_html_e('Añadir alumno', 'area-profesores'); ?></a>
        </form>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat striped ap-table">
                <thead><tr>
                    <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                    <th style="width:110px;"><?php esc_html_e('Curso', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Padres / tutores y teléfonos de emergencia', 'area-profesores'); ?></th>
                    <th style="width:70px;"></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($data['alumnos'])) : ?>
                        <tr><td colspan="4" style="text-align:center; padding:28px;"><?php esc_html_e('No hay alumnos con estos filtros. Si todavía no has importado nada, ve a "Importar de Alexia".', 'area-profesores'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($data['alumnos'] as $a) : $fams = $data['familias_de'][$a['id']] ?? array(); ?>
                        <tr<?php echo $a['estado'] === 'baja' ? ' class="ap-row-baja"' : ''; ?>>
                            <td><strong><?php echo esc_html($a['nombre']); ?></strong><?php if ($a['estado'] === 'baja') : ?> <span class="ap-status-pill ap-status-danger"><?php esc_html_e('Baja', 'area-profesores'); ?></span><?php endif; ?><br><span class="ap-mini"><?php echo esc_html((string) $a['email']); ?></span></td>
                            <td><?php echo esc_html($ap_curso_label($a['curso'])); ?></td>
                            <td>
                                <?php foreach ($fams as $fm) : ?>
                                    <div class="ap-fam-bloque">
                                        <a href="<?php echo esc_url($ap_url(array('tab' => 'familias', 'editar' => $fm['id']))); ?>"><?php echo esc_html($fm['nombre']); ?></a>
                                        <?php foreach (Area_Profesores_Alumnos::contactos_con_telefono($fm) as $ct) : ?>
                                            <div class="ap-mini"><?php echo esc_html($ct['nombre'] !== '' ? $ct['nombre'] : __('Teléfono', 'area-profesores')); ?><?php if ($ct['telefono'] !== '') : ?>: <strong><?php echo esc_html($ct['telefono']); ?></strong><?php endif; ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$fams) : ?><span class="ap-mini">—</span><?php endif; ?>
                            </td>
                            <td><a class="button button-small" href="<?php echo esc_url($ap_url(array('tab' => 'alumnos', 'editar' => $a['id'], 'curso' => $fl['f_curso'], 'estado' => $fl['f_estado'], 'q' => $fl['f_q']))); ?>"><?php esc_html_e('Editar', 'area-profesores'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($tab === 'familias') : ?>

        <?php if (isset($data['editar'])) : $fm = $data['editar']; ?>
            <div class="ap-settings-card ap-edit-card">
                <h2 class="ap-settings-section-title"><span class="dashicons dashicons-groups"></span> <?php echo $fm['id'] ? esc_html($fm['nombre']) : esc_html__('Nueva familia', 'area-profesores'); ?></h2>
                <?php if (!empty($fm['editado_por_familia'])) : ?>
                    <div class="notice notice-info inline"><p><?php printf(esc_html__('La familia actualizó sus teléfonos o direcciones desde el Área Familias el %s (ya se ven aquí y en "Mis alumnos", y Secretaría recibió el aviso). Hasta que Alexia tenga los mismos datos, las importaciones no los vuelven a cambiar.', 'area-profesores'), esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($fm['editado_por_familia'])))); ?></p></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ap_familia_guardar">
                    <input type="hidden" name="id" value="<?php echo esc_attr($fm['id']); ?>">
                    <?php wp_nonce_field('ap_familia_guardar'); ?>
                    <div class="ap-form-grid">
                        <p><label><strong><?php esc_html_e('Nombre de la familia', 'area-profesores'); ?></strong><br><input type="text" name="nombre" class="widefat" value="<?php echo esc_attr($fm['nombre']); ?>"></label></p>
                        <?php if (!empty($fm['telefono'])) : ?>
                            <p><label><strong><?php esc_html_e('Teléfono antiguo de la familia', 'area-profesores'); ?></strong><br><input type="text" name="telefono" class="widefat" value="<?php echo esc_attr($fm['telefono']); ?>"></label>
                                <span class="description"><?php esc_html_e('De una importación anterior. Pásalo al padre o la madre que corresponda y deja este campo vacío.', 'area-profesores'); ?></span></p>
                        <?php endif; ?>
                    </div>
                    <p><label><strong><?php esc_html_e('Correos familiares', 'area-profesores'); ?></strong> <span class="description"><?php esc_html_e('(cuentas con las que la familia entra en el Área Familias; normalmente una compartida por los padres; uno por línea)', 'area-profesores'); ?></span><br>
                        <textarea name="emails" class="widefat" rows="2"><?php echo esc_textarea(implode("\n", Area_Profesores_Alumnos::emails_array($fm['emails']))); ?></textarea></label></p>

                    <h3><?php esc_html_e('Padres / tutores', 'area-profesores'); ?></h3>
                    <table class="widefat ap-contactos-table">
                        <thead><tr><th><?php esc_html_e('Nombre', 'area-profesores'); ?></th><th style="width:200px;"><?php esc_html_e('Teléfono de emergencia', 'area-profesores'); ?></th><th><?php esc_html_e('Dirección', 'area-profesores'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach (array_merge($fm['contactos'], array(array('nombre' => '', 'telefono' => '', 'direccion' => ''), array('nombre' => '', 'telefono' => '', 'direccion' => ''))) as $ct) : ?>
                                <tr>
                                    <td><input type="text" name="ct_nombre[]" class="widefat" value="<?php echo esc_attr($ct['nombre']); ?>"></td>
                                    <td><input type="text" name="ct_telefono[]" class="widefat" value="<?php echo esc_attr(isset($ct['telefono']) ? $ct['telefono'] : ''); ?>"></td>
                                    <td><input type="text" name="ct_direccion[]" class="widefat" value="<?php echo esc_attr($ct['direccion']); ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description"><?php esc_html_e('Deja una fila vacía para quitarla.', 'area-profesores'); ?></p>

                    <p><label><strong><?php esc_html_e('Notas internas', 'area-profesores'); ?></strong><br><textarea name="notas" class="widefat" rows="2"><?php echo esc_textarea((string) $fm['notas']); ?></textarea></label></p>

                    <?php if ($fm['id']) : ?>
                        <h3><?php esc_html_e('Hijos', 'area-profesores'); ?></h3>
                        <?php if (empty($data['editar_hijos'])) : ?>
                            <p class="description"><?php esc_html_e('Sin hijos asignados. Asígnalos desde la ficha de cada alumno.', 'area-profesores'); ?></p>
                        <?php else : ?>
                            <ul class="ap-list-plain">
                                <?php foreach ($data['editar_hijos'] as $a) : ?>
                                    <li><a href="<?php echo esc_url($ap_url(array('tab' => 'alumnos', 'editar' => $a['id']))); ?>"><?php echo esc_html($a['nombre']); ?></a> · <?php echo esc_html($ap_curso_label($a['curso'])); ?><?php echo $a['estado'] === 'baja' ? ' · ' . esc_html__('baja', 'area-profesores') : ''; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="ap-form-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Guardar', 'area-profesores'); ?></button>
                        <a class="button" href="<?php echo esc_url($ap_url(array('tab' => 'familias', 'q' => $data['f_q']))); ?>"><?php esc_html_e('Cerrar', 'area-profesores'); ?></a>
                        <?php if ($fm['id']) : ?>
                            <a class="button button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ap_familia_borrar&id=' . $fm['id']), 'ap_familia_borrar_' . $fm['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('¿Eliminar esta familia? Sus hijos no se borran, solo dejan de estar asignados a ella.', 'area-profesores')); ?>');"><?php esc_html_e('Eliminar familia', 'area-profesores'); ?></a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        <?php endif; ?>

        <form method="get" class="ap-admin-toolbar ap-filtros">
            <input type="hidden" name="page" value="<?php echo esc_attr(Area_Profesores_Alumnos_Admin::SLUG); ?>">
            <input type="hidden" name="tab" value="familias">
            <input type="search" name="q" value="<?php echo esc_attr($data['f_q']); ?>" placeholder="<?php esc_attr_e('Buscar familia, padre/madre, correo o teléfono…', 'area-profesores'); ?>">
            <button class="button"><?php esc_html_e('Buscar', 'area-profesores'); ?></button>
            <span class="ap-toolbar-stats"><?php printf(esc_html(_n('%d familia', '%d familias', count($data['familias']), 'area-profesores')), count($data['familias'])); ?></span>
            <a class="button button-primary" href="<?php echo esc_url($ap_url(array('tab' => 'familias', 'editar' => 0))); ?>"><?php esc_html_e('Añadir familia', 'area-profesores'); ?></a>
        </form>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat striped ap-table">
                <thead><tr>
                    <th><?php esc_html_e('Familia', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Correos familiares', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Padres / tutores y teléfonos', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Hijos', 'area-profesores'); ?></th>
                    <th style="width:70px;"></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($data['familias'])) : ?>
                        <tr><td colspan="5" style="text-align:center; padding:28px;"><?php esc_html_e('No hay familias.', 'area-profesores'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($data['familias'] as $fm) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($fm['nombre']); ?></strong><?php if (!empty($fm['editado_por_familia'])) : ?> <span class="ap-status-pill ap-status-warning" title="<?php esc_attr_e('La familia actualizó sus datos desde el Área Familias', 'area-profesores'); ?>"><?php esc_html_e('Datos de la familia', 'area-profesores'); ?></span><?php endif; ?></td>
                            <td class="ap-mini"><?php echo esc_html(str_replace(',', ', ', $fm['emails'])); ?></td>
                            <td><?php foreach (Area_Profesores_Alumnos::contactos_con_telefono($fm) as $ct) : ?><div class="ap-mini"><?php echo esc_html($ct['nombre'] !== '' ? $ct['nombre'] : __('Teléfono', 'area-profesores')); ?><?php if ($ct['telefono'] !== '') : ?>: <strong><?php echo esc_html($ct['telefono']); ?></strong><?php endif; ?></div><?php endforeach; ?></td>
                            <td><?php foreach ($data['hijos_de'][$fm['id']] ?? array() as $a) : ?><div><?php echo esc_html($a['nombre']); ?> <span class="ap-mini"><?php echo esc_html($ap_curso_label($a['curso'])); ?></span></div><?php endforeach; ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url($ap_url(array('tab' => 'familias', 'editar' => $fm['id'], 'q' => $data['f_q']))); ?>"><?php esc_html_e('Editar', 'area-profesores'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($tab === 'importar') : $pv = $data['previa']; ?>

        <?php if (!$pv) : ?>
            <div class="ap-settings-card ap-edit-card">
                <h2 class="ap-settings-section-title"><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Importar el "Listado de familias" de Alexia', 'area-profesores'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Sube el Excel tal como lo da Alexia (uno por etapa: Infantil, Primaria, ESO o Bachillerato). No hace falta cambiarle nada.', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Verás una vista previa con los alumnos y familias nuevos, los cambios y las posibles bajas. No se cambia nada hasta que pulses "Aplicar".', 'area-profesores'); ?></li>
                    <li><?php esc_html_e('Puedes importar el mismo fichero las veces que quieras: no se duplican alumnos ni familias.', 'area-profesores'); ?></li>
                </ol>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ap_importar_subir">
                    <?php wp_nonce_field('ap_importar_subir'); ?>
                    <p><input type="file" name="fichero" accept=".xls,.xlsx,.csv" required></p>
                    <p><label><?php esc_html_e('Etapa del fichero:', 'area-profesores'); ?>
                        <select name="grupo">
                            <option value="auto"><?php esc_html_e('Detectar automáticamente', 'area-profesores'); ?></option>
                            <?php foreach (Area_Profesores_Importador::get_grupos() as $k => $l) : ?>
                                <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <span class="description"><?php esc_html_e('Los alumnos de esta etapa que no estén en el fichero se proponen como baja (los hermanos de otras etapas no).', 'area-profesores'); ?></span></p>
                    <p><button class="button button-primary"><?php esc_html_e('Ver vista previa', 'area-profesores'); ?></button></p>
                </form>
            </div>
        <?php else : $pl = $pv['plan']; $grupos = Area_Profesores_Importador::get_grupos(); ?>
            <div class="ap-settings-card ap-edit-card" id="previa">
                <h2 class="ap-settings-section-title"><span class="dashicons dashicons-visibility"></span> <?php printf(esc_html__('Vista previa: %1$s (%2$s)', 'area-profesores'), esc_html($pv['fichero']), esc_html($grupos[$pv['grupo']] ?? $pv['grupo'])); ?></h2>
                <p class="description"><?php printf(esc_html__('%d familias en el fichero. Todavía no se ha cambiado nada.', 'area-profesores'), count($pv['familias'])); ?></p>
                <?php foreach ($pv['avisos'] as $av) : ?><div class="notice notice-warning inline"><p><?php echo esc_html($av); ?></p></div><?php endforeach; ?>

                <div class="ap-import-stats">
                    <div><strong><?php echo count($pl['alumnos_nuevos']); ?></strong><span><?php esc_html_e('alumnos nuevos', 'area-profesores'); ?></span></div>
                    <div><strong><?php echo count($pl['alumnos_cambios']); ?></strong><span><?php esc_html_e('alumnos con cambios', 'area-profesores'); ?></span></div>
                    <div><strong><?php echo (int) $pl['alumnos_iguales']; ?></strong><span><?php esc_html_e('alumnos sin cambios', 'area-profesores'); ?></span></div>
                    <div><strong><?php echo count($pl['familias_nuevas']); ?></strong><span><?php esc_html_e('familias nuevas', 'area-profesores'); ?></span></div>
                    <div><strong><?php echo count($pl['familias_cambios']); ?></strong><span><?php esc_html_e('familias con cambios', 'area-profesores'); ?></span></div>
                    <div class="<?php echo $pl['bajas'] ? 'is-warn' : ''; ?>"><strong><?php echo count($pl['bajas']); ?></strong><span><?php esc_html_e('posibles bajas', 'area-profesores'); ?></span></div>
                </div>

                <?php
                $ap_lista = function ($titulo, $items) {
                    if (empty($items)) {
                        return;
                    }
                    echo '<details class="ap-import-detalle"><summary>' . esc_html($titulo) . ' (' . count($items) . ')</summary><ul>';
                    foreach ($items as $it) {
                        echo '<li>' . esc_html($it) . '</li>';
                    }
                    echo '</ul></details>';
                };
                $ap_lista(__('Alumnos nuevos', 'area-profesores'), $pl['alumnos_nuevos']);
                $ap_lista(__('Alumnos con cambios', 'area-profesores'), $pl['alumnos_cambios']);
                $ap_lista(__('Familias nuevas', 'area-profesores'), $pl['familias_nuevas']);
                $ap_lista(__('Familias con cambios', 'area-profesores'), $pl['familias_cambios']);
                $ap_lista(__('Familias que actualizaron sus teléfonos o direcciones desde el Área Familias y Alexia todavía tiene los datos antiguos (se mantienen los de la familia)', 'area-profesores'), $pl['conservados']);
                ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ap_importar_aplicar">
                    <?php wp_nonce_field('ap_importar_aplicar'); ?>
                    <?php if ($pl['bajas']) : ?>
                        <h3><?php esc_html_e('Posibles bajas', 'area-profesores'); ?></h3>
                        <p class="description"><?php esc_html_e('Alumnos de esta etapa que no aparecen en el fichero. Marca los que se han ido del colegio (pasarán a "De baja"; no se borran).', 'area-profesores'); ?></p>
                        <div class="ap-bajas-list">
                            <?php foreach ($pl['bajas'] as $b) : ?>
                                <label><input type="checkbox" name="bajas[]" value="<?php echo esc_attr($b['id']); ?>"> <?php echo esc_html($b['nombre'] . ' · ' . $b['curso']); ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="ap-form-actions">
                        <button class="button button-primary button-large" onclick="return confirm('<?php echo esc_js(__('¿Aplicar la importación?', 'area-profesores')); ?>');"><?php esc_html_e('Aplicar importación', 'area-profesores'); ?></button>
                        <button class="button button-large" name="cancelar" value="1"><?php esc_html_e('Cancelar', 'area-profesores'); ?></button>
                    </p>
                </form>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'historial') : ?>

        <div class="ap-card-table-wrap">
            <table class="wp-list-table widefat striped ap-table">
                <thead><tr>
                    <th style="width:140px;"><?php esc_html_e('Fecha', 'area-profesores'); ?></th>
                    <th style="width:150px;"><?php esc_html_e('Quién', 'area-profesores'); ?></th>
                    <th style="width:160px;"><?php esc_html_e('Qué', 'area-profesores'); ?></th>
                    <th><?php esc_html_e('Detalle', 'area-profesores'); ?></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($data['historial'])) : ?>
                        <tr><td colspan="4" style="text-align:center; padding:28px;"><?php esc_html_e('Todavía no hay cambios.', 'area-profesores'); ?></td></tr>
                    <?php endif; ?>
                    <?php
                    $origenes = array('backend' => __('backend', 'area-profesores'), 'importacion' => __('importación', 'area-profesores'), 'familia' => __('la familia', 'area-profesores'));
                    foreach ($data['historial'] as $h) :
                        $u = $h['user_id'] ? get_userdata($h['user_id']) : null;
                        $det = json_decode((string) $h['detalle'], true);
                        if (is_array($det)) {
                            $partes = array();
                            foreach ($det as $k => $v) {
                                $partes[] = $k . ': ' . (is_array($v) ? (count($v) === 2 && isset($v[0]) ? $v[0] . ' → ' . $v[1] : wp_json_encode($v, JSON_UNESCAPED_UNICODE)) : $v);
                            }
                            $det = implode(' · ', $partes);
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($h['fecha']))); ?></td>
                            <td><?php echo esc_html($u ? $u->display_name : '—'); ?> <span class="ap-mini">(<?php echo esc_html($origenes[$h['origen']] ?? $h['origen']); ?>)</span></td>
                            <td><?php echo esc_html($h['objeto'] . ' #' . $h['objeto_id'] . ' · ' . $h['accion']); ?></td>
                            <td class="ap-mini"><?php echo esc_html((string) $det); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
