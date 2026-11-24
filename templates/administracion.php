<?php
/**
 * Teacher Portal: "Administración" tab (Administración, Secretaría, Dirección and administrators).
 *
 * Two blocks for each month:
 * 1. Cocina: the pupils' meals of each day by dining room, to check the kitchen's invoice.
 * 2. Cobros a las familias: what to charge each family (extra meals and services), with the days.
 *
 * Variables: $comensales_mes { mes, adm (Area_Profesores_Comensales::administracion()) }
 */

if (!defined('ABSPATH')) {
    exit;
}

$cm = $comensales_mes;
$cm_a = $cm['adm'];
$cm_co = $cm_a['cocina'];
$cm_t = strtotime($cm['mes'] . '-01 12:00:00');
$cm_url = function ($mes) {
    return add_query_arg('ap_mes', $mes, remove_query_arg('ap_mes')) . '#administracion';
};
$cm_prev = gmdate('Y-m', strtotime('-1 month', $cm_t));
$cm_next = gmdate('Y-m', strtotime('+1 month', $cm_t));
$cm_dia = function ($f) {
    return date_i18n('D j', strtotime($f . ' 12:00:00'));
};
?>
<div class="ap-records-card ap-cm ap-adm">
    <div class="ap-enlaces-header">
        <div>
            <h2 class="ap-records-title"><?php esc_html_e('Administración', 'area-profesores'); ?></h2>
            <p class="ap-records-subtitle"><?php esc_html_e('Cada mes, en dos bloques: las comidas de los alumnos para cotejar con cocina y lo que hay que cobrar a cada familia.', 'area-profesores'); ?></p>
        </div>
        <div class="ap-ma-tools">
            <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'ap_comedor_csv_mes', 'mes' => $cm['mes']), admin_url('admin-post.php')), 'ap_comedor_csv_mes')); ?>"><?php esc_html_e('Descargar (Excel)', 'area-profesores'); ?></a>
            <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" onclick="window.print()"><?php esc_html_e('Imprimir', 'area-profesores'); ?></button>
        </div>
    </div>

    <div class="ap-res-nav">
        <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($cm_url($cm_prev)); ?>">&larr;</a>
        <strong class="ap-cm-mes"><?php echo esc_html(ucfirst(date_i18n('F \d\e Y', $cm_t))); ?></strong>
        <a class="ap-btn ap-btn-sm ap-btn-outline" href="<?php echo esc_url($cm_url($cm_next)); ?>">&rarr;</a>
    </div>

    <nav class="ap-adm-saltos">
        <a href="#adm-cocina">1. <?php esc_html_e('Comidas para cotejar con cocina', 'area-profesores'); ?></a>
        <a href="#adm-cobros">2. <?php esc_html_e('Cobros a las familias', 'area-profesores'); ?></a>
    </nav>

    <!-- 1. COCINA -->
    <section class="ap-adm-bloque" id="adm-cocina">
        <h3 class="ap-adm-h"><span>1</span> <?php esc_html_e('Comidas de los alumnos (para cotejar con cocina)', 'area-profesores'); ?></h3>
        <p class="ap-records-subtitle"><?php esc_html_e('Comidas validadas en el comedor diario, por comedor y por día: fijos, extras y reservas de las familias. Solo Primer Ciclo, Infantil y Primaria.', 'area-profesores'); ?></p>

        <div class="ap-cm-cifras">
            <div><b><?php echo (int) $cm_co['totales']['total']; ?></b><span><?php esc_html_e('comidas de alumnos en el mes', 'area-profesores'); ?></span></div>
            <?php foreach ($cm_co['comedores'] as $ck => $c) : ?>
                <div><b><?php echo (int) $cm_co['totales']['c'][$ck]; ?></b><span><?php echo esc_html($c['nombre']); ?></span></div>
            <?php endforeach; ?>
            <div><b><?php echo (int) $cm_co['totales']['comen']; ?></b><span><?php printf(esc_html__('comidas de profesores (%d comen habitualmente)', 'area-profesores'), (int) $cm_co['profes_habituales']); ?></span></div>
        </div>

        <?php if (!$cm_co['dias']) : ?>
            <p class="ap-ma-vacio"><?php esc_html_e('No hay ningún día con el comedor validado este mes.', 'area-profesores'); ?></p>
        <?php else : ?>
            <div class="ap-adm-scroll">
                <table class="ap-ma-tabla ap-adm-tabla">
                    <thead><tr>
                        <th><?php esc_html_e('Día', 'area-profesores'); ?></th>
                        <?php foreach ($cm_co['comedores'] as $c) : ?><th class="ap-adm-num"><?php echo esc_html($c['nombre']); ?></th><?php endforeach; ?>
                        <th class="ap-adm-num"><?php esc_html_e('Total alumnos', 'area-profesores'); ?></th>
                        <th class="ap-adm-num"><?php esc_html_e('De ellas, extra', 'area-profesores'); ?></th>
                        <th class="ap-adm-num"><?php esc_html_e('Clases validadas', 'area-profesores'); ?></th>
                        <th class="ap-adm-num"><?php esc_html_e('Profesores que comen', 'area-profesores'); ?></th>
                        <th class="ap-adm-num"><?php esc_html_e('Profesores que avisaron que no comen', 'area-profesores'); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($cm_co['dias'] as $f => $x) : $cm_falta = $x['clases'] < $cm_co['clases']; ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst(date_i18n('l j', strtotime($f . ' 12:00:00')))); ?></td>
                                <?php foreach ($x['c'] as $n) : ?><td class="ap-adm-num"><?php echo (int) $n; ?></td><?php endforeach; ?>
                                <td class="ap-adm-num"><strong><?php echo (int) $x['total']; ?></strong></td>
                                <td class="ap-adm-num"><?php echo (int) $x['extra']; ?></td>
                                <td class="ap-adm-num<?php echo $cm_falta ? ' ap-adm-aviso' : ''; ?>" title="<?php echo $cm_falta ? esc_attr__('Alguna clase no validó el comedor ese día', 'area-profesores') : ''; ?>"><?php echo (int) $x['clases'] . ' / ' . (int) $cm_co['clases']; ?></td>
                                <td class="ap-adm-num"><strong><?php echo (int) $x['comen']; ?></strong></td>
                                <td class="ap-adm-num"><?php echo (int) $x['profes']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr>
                        <th><?php esc_html_e('Total', 'area-profesores'); ?></th>
                        <?php foreach ($cm_co['totales']['c'] as $n) : ?><th class="ap-adm-num"><?php echo (int) $n; ?></th><?php endforeach; ?>
                        <th class="ap-adm-num"><?php echo (int) $cm_co['totales']['total']; ?></th>
                        <th class="ap-adm-num"><?php echo (int) $cm_co['totales']['extra']; ?></th>
                        <th></th>
                        <th class="ap-adm-num"><?php echo (int) $cm_co['totales']['comen']; ?></th>
                        <th class="ap-adm-num"><?php echo (int) $cm_co['totales']['profes']; ?></th>
                    </tr></tfoot>
                </table>
            </div>
            <p class="ap-records-subtitle"><?php esc_html_e('"Clases validadas" en naranja: ese día alguna clase no validó, así que sus comidas no están contadas. "Profesores que comen": los que comen habitualmente (casilla "Come en el colegio" de Área Profesores › Profesores) menos los que avisaron de que ese día no comían.', 'area-profesores'); ?></p>
        <?php endif; ?>

        <details class="ap-adm-serv">
            <summary><strong><?php esc_html_e('Profesores que avisaron de que no comían', 'area-profesores'); ?></strong>
                <span><?php printf(esc_html(_n('%d comida', '%d comidas', $cm_a['profes']['total'], 'area-profesores')), (int) $cm_a['profes']['total']); ?></span></summary>
            <?php if (!$cm_a['profes']['profes']) : ?>
                <p class="ap-ma-vacio"><?php esc_html_e('Ningún profesor ha marcado días sin comer en el colegio este mes.', 'area-profesores'); ?></p>
            <?php else : ?>
                <table class="ap-ma-tabla">
                    <tbody>
                        <?php foreach ($cm_a['profes']['profes'] as $p) : ?>
                            <tr>
                                <td class="ap-ma-alumno"><?php echo esc_html($p['nombre']); ?></td>
                                <td class="ap-adm-num"><strong><?php echo count($p['dias']); ?></strong></td>
                                <td class="ap-cm-dias"><?php foreach ($p['dias'] as $f) : ?><span class="ap-cm-dia is-profe"><?php echo esc_html($cm_dia($f)); ?></span><?php endforeach; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </details>
    </section>

    <!-- 2. COBROS A LAS FAMILIAS -->
    <section class="ap-adm-bloque" id="adm-cobros">
        <h3 class="ap-adm-h"><span>2</span> <?php esc_html_e('Cobros a las familias', 'area-profesores'); ?></h3>
        <p class="ap-records-subtitle"><?php esc_html_e('Lo que hay que cobrar a cada familia este mes: las comidas extra (alumnos que comieron sin ser fijos ese día, ya sea por reserva o porque los apuntó el profesor) y los demás servicios, con sus días lectivos. Las salidas anticipadas y los retrasos no se cobran.', 'area-profesores'); ?></p>

        <?php if (!$cm_a['familias']) : ?>
            <p class="ap-ma-vacio"><?php esc_html_e('Este mes no hay nada que cobrar a las familias.', 'area-profesores'); ?></p>
        <?php else : ?>
            <div class="ap-cm-cifras">
                <div><b><?php echo count($cm_a['familias']); ?></b><span><?php esc_html_e('familias a las que cobrar', 'area-profesores'); ?></span></div>
                <?php foreach ($cm_a['columnas'] as $k => $n) : ?>
                    <div><b><?php echo (int) (isset($cm_a['totales'][$k]) ? $cm_a['totales'][$k] : 0); ?></b><span><?php echo esc_html($n); ?> <?php esc_html_e('(días)', 'area-profesores'); ?></span></div>
                <?php endforeach; ?>
            </div>

            <div class="ap-adm-tools no-print">
                <input type="search" class="ap-adm-buscar" placeholder="<?php esc_attr_e('Buscar familia o alumno…', 'area-profesores'); ?>" aria-label="<?php esc_attr_e('Buscar familia o alumno', 'area-profesores'); ?>" oninput="var q=this.value.toLowerCase();document.querySelectorAll('.ap-adm-fam').forEach(function(d){d.hidden=q!==''&&d.getAttribute('data-q').indexOf(q)<0;});">
                <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" onclick="document.querySelectorAll('.ap-adm-fam').forEach(function(d){d.open=true;});"><?php esc_html_e('Desplegar todas', 'area-profesores'); ?></button>
                <button type="button" class="ap-btn ap-btn-sm ap-btn-outline" onclick="document.querySelectorAll('.ap-adm-fam').forEach(function(d){d.open=false;});"><?php esc_html_e('Plegar todas', 'area-profesores'); ?></button>
            </div>

            <?php foreach ($cm_a['familias'] as $fam) : ?>
                <details class="ap-adm-fam" data-q="<?php echo esc_attr(mb_strtolower($fam['nombre'] . ' ' . implode(' ', wp_list_pluck($fam['alumnos'], 'nombre')))); ?>">
                    <summary>
                        <span class="ap-adm-fam-nombre"><strong><?php echo esc_html($fam['nombre']); ?></strong>
                            <small><?php echo esc_html(implode(', ', array_map(function ($a) {
                                return $a['nombre'] . ' (' . $a['curso'] . ')';
                            }, $fam['alumnos']))); ?></small></span>
                        <span class="ap-adm-fam-tot">
                            <?php foreach ($fam['totales'] as $k => $n) : ?>
                                <span class="ap-adm-chip"><?php echo esc_html($cm_a['columnas'][$k]); ?>: <b><?php echo (int) $n; ?></b></span>
                            <?php endforeach; ?>
                        </span>
                    </summary>
                    <?php if ($fam['emails']) : ?><p class="ap-adm-fam-mail"><?php echo esc_html(implode(', ', $fam['emails'])); ?></p><?php endif; ?>
                    <table class="ap-ma-tabla">
                        <thead><tr>
                            <th><?php esc_html_e('Alumno', 'area-profesores'); ?></th>
                            <th><?php esc_html_e('Servicio', 'area-profesores'); ?></th>
                            <th class="ap-adm-num"><?php esc_html_e('Días', 'area-profesores'); ?></th>
                            <th><?php esc_html_e('Fechas', 'area-profesores'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($fam['alumnos'] as $a) : foreach ($a['servicios'] as $k => $d) : ?>
                                <tr>
                                    <td class="ap-ma-alumno"><?php echo esc_html($a['nombre']); ?><br><small><?php echo esc_html($a['curso']); ?></small></td>
                                    <td><?php echo esc_html($cm_a['columnas'][$k]); ?></td>
                                    <td class="ap-adm-num"><strong><?php echo count($d); ?></strong></td>
                                    <td class="ap-cm-dias"><?php foreach ($d as $f => $origen) : ?><span class="ap-cm-dia" title="<?php echo esc_attr($origen); ?>"><?php echo esc_html($cm_dia($f)); ?></span><?php endforeach; ?></td>
                                </tr>
                            <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endforeach; ?>
            <p class="ap-records-subtitle"><?php esc_html_e('Pasa el ratón por cada día para ver cómo se pidió (reserva de la familia, Portería o el profesor).', 'area-profesores'); ?></p>
        <?php endif; ?>

        <?php if ($cm_a['otras']) : ?>
            <details class="ap-adm-serv">
                <summary><strong><?php esc_html_e('Salidas anticipadas y retrasos (no se cobran)', 'area-profesores'); ?></strong>
                    <span><?php printf(esc_html(_n('%d reserva', '%d reservas', count($cm_a['otras']), 'area-profesores')), count($cm_a['otras'])); ?></span></summary>
                <table class="ap-ma-tabla">
                    <tbody>
                        <?php foreach ($cm_a['otras'] as $r) : ?>
                            <tr>
                                <td class="ap-ma-alumno"><?php echo esc_html($r['alumno']); ?><br><small><?php echo esc_html($r['curso_label']); ?></small></td>
                                <td><?php echo esc_html($r['servicio_nombre']); ?></td>
                                <td><?php echo esc_html($r['fechas_texto']); ?></td>
                                <td><small><?php echo esc_html(Area_Profesores_Reservas::origen_texto($r)); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </section>
</div>
