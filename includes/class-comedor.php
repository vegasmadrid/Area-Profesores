<?php
/**
 * Comedor Management Class
 * Handles database operations, data persistence, and AJAX endpoints for dining room records.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Comedor {

    private static $instance = null;
    private static $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'ap_comedor_registros';

        // AJAX handlers for frontend
        add_action('wp_ajax_ap_save_comedor_record', array($this, 'ajax_save_record'));
        add_action('wp_ajax_ap_get_teacher_records', array($this, 'ajax_get_teacher_records'));
        add_action('wp_ajax_ap_delete_comedor_record', array($this, 'ajax_delete_record'));

        // Admin export CSV
        add_action('admin_post_ap_export_comedor_csv', array($this, 'export_csv'));
    }

    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'ap_comedor_registros';
    }

    /**
     * Available standard classes (matching the user's form image)
     */
    public static function get_available_classes() {
        return array(
            '1A' => '1º A',
            '1B' => '1º B',
            '2A' => '2º A',
            '2B' => '2º B',
            '3A' => '3º A',
            '3B' => '3º B',
            '4A' => '4º A',
            '4B' => '4º B',
            '5A' => '5º A',
            '5B' => '5º B',
            '6A' => '6º A',
            '6B' => '6º B',
        );
    }

    /**
     * Available stages (Etapa)
     */
    public static function get_available_stages() {
        return array(
            'Primer Ciclo' => 'Primer Ciclo',
            'Infantil'     => 'Infantil',
            'Primaria'     => 'Primaria',
        );
    }

    /**
     * Create custom table in database on activation
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            clase VARCHAR(30) NOT NULL,
            etapa VARCHAR(60) NOT NULL,
            num_alumnos INT(11) NOT NULL DEFAULT 0,
            dietas TEXT DEFAULT NULL,
            alergicos_faltan TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY fecha_idx (fecha),
            KEY user_fecha_idx (user_id, fecha),
            KEY clase_fecha_idx (clase, fecha)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * AJAX handler to save or update comedor record
     */
    public function ajax_save_record() {
        check_ajax_referer('area_profesores_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Debes iniciar sesión para realizar esta acción.', 'area-profesores')));
        }

        $user_id = get_current_user_id();

        // Check if user is a teacher
        if (!Area_Profesores_Admin::is_user_profesor($user_id)) {
            wp_send_json_error(array('message' => __('No tienes permisos de profesor.', 'area-profesores')));
        }

        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $clase = isset($_POST['clase']) ? sanitize_text_field(wp_unslash($_POST['clase'])) : '';
        $etapa = isset($_POST['etapa']) ? sanitize_text_field(wp_unslash($_POST['etapa'])) : '';
        $num_alumnos = isset($_POST['num_alumnos']) ? intval($_POST['num_alumnos']) : -1;
        $dietas = isset($_POST['dietas']) ? sanitize_textarea_field(wp_unslash($_POST['dietas'])) : '';
        $alergicos_faltan = isset($_POST['alergicos_faltan']) ? sanitize_textarea_field(wp_unslash($_POST['alergicos_faltan'])) : '';
        $fecha = isset($_POST['fecha']) && !empty($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : current_time('Y-m-d');

        // Validation
        if (empty($clase)) {
            wp_send_json_error(array('message' => __('Por favor selecciona la clase.', 'area-profesores')));
        }
        if (empty($etapa)) {
            wp_send_json_error(array('message' => __('Por favor selecciona la etapa.', 'area-profesores')));
        }
        if ($num_alumnos < 0) {
            wp_send_json_error(array('message' => __('Introduce un número válido de alumnos que se quedan a comer.', 'area-profesores')));
        }

        global $wpdb;
        $table = self::get_table_name();

        // Check if a record already exists for this date and class (to prevent duplicates or update)
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id FROM $table WHERE fecha = %s AND clase = %s",
            $fecha,
            $clase
        ));

        if ($record_id > 0) {
            // Update existing record
            $wpdb->update(
                $table,
                array(
                    'clase'            => $clase,
                    'etapa'            => $etapa,
                    'num_alumnos'      => $num_alumnos,
                    'dietas'           => $dietas,
                    'alergicos_faltan' => $alergicos_faltan,
                    'fecha'            => $fecha,
                    'updated_at'       => current_time('mysql'),
                ),
                array('id' => $record_id),
                array('%s', '%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            $saved_id = $record_id;
            $msg = __('¡Registro de comedor actualizado correctamente!', 'area-profesores');
        } elseif ($existing) {
            // A record for this class and date already exists; update it
            $wpdb->update(
                $table,
                array(
                    'user_id'          => $user_id,
                    'etapa'            => $etapa,
                    'num_alumnos'      => $num_alumnos,
                    'dietas'           => $dietas,
                    'alergicos_faltan' => $alergicos_faltan,
                    'updated_at'       => current_time('mysql'),
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
            $saved_id = $existing->id;
            $msg = __('¡Registro actualizado para hoy!', 'area-profesores');
        } else {
            // Insert new record
            $inserted = $wpdb->insert(
                $table,
                array(
                    'user_id'          => $user_id,
                    'fecha'            => $fecha,
                    'clase'            => $clase,
                    'etapa'            => $etapa,
                    'num_alumnos'      => $num_alumnos,
                    'dietas'           => $dietas,
                    'alergicos_faltan' => $alergicos_faltan,
                    'created_at'       => current_time('mysql'),
                    'updated_at'       => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );

            if (false === $inserted) {
                wp_send_json_error(array('message' => __('Error al guardar en la base de datos.', 'area-profesores')));
            }
            $saved_id = $wpdb->insert_id;
            $msg = __('¡Registro de comedor guardado con éxito!', 'area-profesores');
        }

        // Return updated list of today's records for this teacher
        $teacher_records = self::get_records_by_user_and_date($user_id, $fecha);

        wp_send_json_success(array(
            'message'   => $msg,
            'record_id' => $saved_id,
            'records'   => $teacher_records,
        ));
    }

    /**
     * AJAX handler to get today's records for current teacher
     */
    public function ajax_get_teacher_records() {
        check_ajax_referer('area_profesores_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $user_id = get_current_user_id();
        $fecha = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : current_time('Y-m-d');
        $records = self::get_records_by_user_and_date($user_id, $fecha);

        wp_send_json_success(array('records' => $records));
    }

    /**
     * AJAX handler to delete record
     */
    public function ajax_delete_record() {
        check_ajax_referer('area_profesores_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'No autorizado'));
        }

        $user_id = get_current_user_id();
        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;

        global $wpdb;
        $table = self::get_table_name();

        // Allow delete if user is the creator or administrator
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $record_id));
        if (!$record) {
            wp_send_json_error(array('message' => __('Registro no encontrado.', 'area-profesores')));
        }

        if ($record->user_id != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permiso para eliminar este registro.', 'area-profesores')));
        }

        $wpdb->delete($table, array('id' => $record_id), array('%d'));

        wp_send_json_success(array('message' => __('Registro eliminado correctamente.', 'area-profesores')));
    }

    /**
     * Query records by date
     */
    public static function get_records_by_date($fecha) {
        global $wpdb;
        $table = self::get_table_name();
        $sql = $wpdb->prepare(
            "SELECT r.*, u.display_name as profesor_nombre 
             FROM $table r 
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
             WHERE r.fecha = %s 
             ORDER BY r.clase ASC",
            $fecha
        );
        return $wpdb->get_results($sql);
    }

    /**
     * Query records by user and date
     */
    public static function get_records_by_user_and_date($user_id, $fecha) {
        global $wpdb;
        $table = self::get_table_name();
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND fecha = %s ORDER BY id DESC",
            $user_id,
            $fecha
        );
        return $wpdb->get_results($sql);
    }

    /**
     * Get summary totals for a given date
     */
    public static function get_summary_by_date($fecha) {
        global $wpdb;
        $table = self::get_table_name();
        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_clases,
                COALESCE(SUM(num_alumnos), 0) as total_comensales,
                SUM(CASE WHEN dietas IS NOT NULL AND dietas != '' THEN 1 ELSE 0 END) as clases_con_dietas,
                SUM(CASE WHEN alergicos_faltan IS NOT NULL AND alergicos_faltan != '' THEN 1 ELSE 0 END) as clases_con_incidencias
             FROM $table 
             WHERE fecha = %s",
            $fecha
        );
        return $wpdb->get_row($sql);
    }

    /**
     * Export daily records as CSV (for kitchen or admin report)
     */
    public function export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Acceso denegado.', 'area-profesores'));
        }

        check_admin_referer('ap_export_csv_nonce');

        $fecha = isset($_GET['fecha']) ? sanitize_text_field($_GET['fecha']) : current_time('Y-m-d');
        $records = self::get_records_by_date($fecha);

        $filename = 'comedor_' . $fecha . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header row
        fputcsv($output, array(
            'Fecha',
            'Clase',
            'Etapa',
            'Nº Alumnos (Sin dietas)',
            'Dietas y Especiales',
            'Alérgicos que faltan / Observaciones',
            'Profesor',
            'Hora de registro'
        ), ';');

        $total_alumnos = 0;

        foreach ($records as $r) {
            $total_alumnos += intval($r->num_alumnos);
            fputcsv($output, array(
                $r->fecha,
                $r->clase,
                $r->etapa,
                $r->num_alumnos,
                $r->dietas,
                $r->alergicos_faltan,
                $r->profesor_nombre,
                $r->created_at,
            ), ';');
        }

        // Summary row
        fputcsv($output, array('', '', 'TOTAL ALUMNOS (Sin dietas):', $total_alumnos, '', '', '', ''), ';');

        fclose($output);
        exit;
    }
}
