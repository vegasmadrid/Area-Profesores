<?php
/**
 * Site-wide Access Restriction
 * Only logged-in (registered) users can view any front-end page of the site.
 * Visitors who are not logged in are redirected to the login page configured
 * in "Área Profesores > Ajustes" (falls back to wp-login.php if none is set).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Area_Profesores_Access {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Run as early as possible on front-end requests (before themes/plugins render anything).
        add_action('template_redirect', array($this, 'restrict_frontend'), 0);

        // Block anonymous access to the REST API (it exposes pages, posts and users).
        add_filter('rest_authentication_errors', array($this, 'restrict_rest_api'), 99);

        // Start page after a standard WordPress login (wp-login.php).
        add_filter('login_redirect', array($this, 'filter_login_redirect'), 20, 3);

        // Hide the admin toolbar on the front end for teachers (non-editors).
        add_filter('show_admin_bar', array($this, 'filter_show_admin_bar'), 20);

        // Keep teachers (non-editors) out of /wp-admin.
        add_action('admin_init', array($this, 'block_wp_admin'), 1);

        // Block RSS/Atom feeds for anonymous visitors.
        foreach (array('do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments') as $feed_hook) {
            add_action($feed_hook, array($this, 'restrict_feed'), 0);
        }
    }

    /**
     * Is the site-wide restriction enabled?
     */
    public static function is_enabled() {
        return Area_Profesores_Admin::get_setting('restrict_site', 'yes') === 'yes';
    }

    /**
     * Published login page ID, or 0 if none is configured / published.
     */
    public static function get_login_page_id() {
        $login_page_id = absint(Area_Profesores_Admin::get_setting('login_page_id', 0));
        if ($login_page_id && get_post_status($login_page_id) === 'publish') {
            return $login_page_id;
        }
        return 0;
    }

    /**
     * Build the login URL, keeping the page the visitor wanted to see as redirect_to.
     */
    public static function get_login_url($redirect_to = '') {
        $login_page_id = self::get_login_page_id();

        if ($login_page_id) {
            $url = get_permalink($login_page_id);
            if (!empty($redirect_to)) {
                $url = add_query_arg('redirect_to', rawurlencode($redirect_to), $url);
            }
            return $url;
        }

        return wp_login_url($redirect_to);
    }

    /**
     * URL of the start page teachers are sent to after logging in.
     * Priority: custom URL > selected page > Área de Profesores page > home.
     */
    public static function get_start_url() {
        $custom = trim((string) Area_Profesores_Admin::get_setting('start_custom_url', ''));
        if ($custom !== '') {
            return $custom;
        }

        $start_page_id = absint(Area_Profesores_Admin::get_setting('start_page_id', 0));
        if ($start_page_id && get_post_status($start_page_id) === 'publish') {
            return get_permalink($start_page_id);
        }

        $area_page_id = absint(Area_Profesores_Admin::get_setting('area_page_id', 0));
        if ($area_page_id && get_post_status($area_page_id) === 'publish') {
            return get_permalink($area_page_id);
        }

        return home_url('/');
    }

    /**
     * Decide where to send a user after login.
     * If "Volver a la página solicitada" is on and the visitor was sent to the login
     * page from a specific internal page, go back there; otherwise use the start page.
     */
    public static function resolve_login_redirect($requested = '') {
        $start = self::get_start_url();

        if ($requested === '' || Area_Profesores_Admin::get_setting('respect_redirect_to', 'yes') !== 'yes') {
            return $start;
        }

        $requested = wp_validate_redirect($requested, '');
        if ($requested === '') {
            return $start;
        }

        // The home page, the login page or wp-admin are not "specific" pages.
        $normalize = function ($url) {
            $parts = wp_parse_url($url);
            $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
            // The query is part of the page (plain permalinks: /?page_id=6).
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            return strtolower((isset($parts['host']) ? $parts['host'] : '') . $path . $query);
        };
        $generic = array($normalize(home_url('/')));
        $login_page_id = self::get_login_page_id();
        if ($login_page_id) {
            $generic[] = $normalize(get_permalink($login_page_id));
        }
        if (in_array($normalize($requested), $generic, true) || strpos($requested, admin_url()) === 0) {
            return $start;
        }

        return $requested;
    }

    /**
     * Logout URL: after logging out, go to the login page.
     */
    public static function get_logout_url() {
        $login_page_id = self::get_login_page_id();
        return wp_logout_url($login_page_id ? get_permalink($login_page_id) : home_url('/'));
    }

    /**
     * Users that may use the WordPress dashboard (editors and admins).
     */
    private static function can_use_dashboard($user = null) {
        $user = $user ? $user : wp_get_current_user();
        return $user && $user->exists() && user_can($user, 'edit_posts');
    }

    /**
     * login_redirect: send teachers to the configured start page.
     */
    public function filter_login_redirect($redirect_to, $requested, $user) {
        if (!($user instanceof WP_User) || self::can_use_dashboard($user)) {
            return $redirect_to;
        }
        return self::resolve_login_redirect((string) $requested);
    }

    public function filter_show_admin_bar($show) {
        if (Area_Profesores_Admin::get_setting('hide_admin_bar', 'yes') === 'yes' && is_user_logged_in() && !self::can_use_dashboard()) {
            return false;
        }
        return $show;
    }

    public function block_wp_admin() {
        if (Area_Profesores_Admin::get_setting('block_wp_admin', 'yes') !== 'yes') {
            return;
        }
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON) || !is_user_logged_in() || self::can_use_dashboard()) {
            return;
        }
        // admin-post.php handlers (e.g. form submissions) must keep working.
        global $pagenow;
        if ($pagenow === 'admin-post.php') {
            return;
        }
        wp_safe_redirect(self::get_start_url());
        exit;
    }

    /**
     * IDs of pages that stay public (login page + pages marked as public in settings).
     */
    public static function get_public_page_ids() {
        $ids = Area_Profesores_Admin::get_setting('public_page_ids', array());
        $ids = is_array($ids) ? array_map('absint', $ids) : array();

        $login_page_id = self::get_login_page_id();
        if ($login_page_id) {
            $ids[] = $login_page_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Current full request URL.
     */
    private function get_current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host   = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : wp_parse_url(home_url(), PHP_URL_HOST);
        $uri    = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return esc_url_raw($scheme . $host . $uri);
    }

    /**
     * Should this front-end request bypass the restriction?
     */
    private function is_request_allowed() {
        if (is_user_logged_in()) {
            return true;
        }

        if (!self::is_enabled()) {
            return true;
        }

        if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) || is_admin()) {
            return true;
        }

        // robots.txt and favicon must stay reachable.
        if (is_robots() || (function_exists('is_favicon') && is_favicon())) {
            return true;
        }

        // Nextend Social Login callbacks (?loginSocial=google) must reach the provider.
        if (isset($_GET['loginSocial'])) {
            return true;
        }

        // Public pages (always includes the login page to avoid redirect loops).
        $public_ids = self::get_public_page_ids();
        if (!empty($public_ids)) {
            $queried_id = get_queried_object_id();
            if ($queried_id && in_array((int) $queried_id, $public_ids, true)) {
                return true;
            }
            if (is_page($public_ids)) {
                return true;
            }
        }

        /**
         * Filter to allow extra public requests from code.
         */
        return (bool) apply_filters('area_profesores_is_public_request', false);
    }

    /**
     * template_redirect: send anonymous visitors to the login page.
     */
    public function restrict_frontend() {
        if ($this->is_request_allowed()) {
            return;
        }

        nocache_headers();
        wp_safe_redirect(self::get_login_url($this->get_current_url()), 302);
        exit;
    }

    /**
     * Feeds: redirect anonymous visitors to the login page.
     */
    public function restrict_feed() {
        if (is_user_logged_in() || !self::is_enabled()) {
            return;
        }
        nocache_headers();
        wp_safe_redirect(self::get_login_url(home_url('/')), 302);
        exit;
    }

    /**
     * REST API: deny anonymous requests.
     */
    public function restrict_rest_api($result) {
        if (!empty($result)) {
            return $result; // Another error/auth result already decided.
        }
        if (is_user_logged_in() || !self::is_enabled()) {
            return $result;
        }

        if (apply_filters('area_profesores_is_public_rest_request', false)) {
            return $result;
        }

        return new WP_Error(
            'rest_not_logged_in',
            __('Debes iniciar sesión para acceder a este contenido.', 'area-profesores'),
            array('status' => 401)
        );
    }
}
