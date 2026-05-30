<?php
/*
Plugin Name: Travel Cost Calculator
Description: Complete V3.4 - Smart Auto-Calc, Indian Currency, Persistent Links, Inclusions/Exclusions, Booking Manager, Lead Follow-up Dashboard, and Detailed Itinerary.
Version: 3.4
Author: Your Agency
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue Styles & Scripts with AJAX
add_action( 'wp_enqueue_scripts', 'tcc_enqueue_scripts' );
function tcc_enqueue_scripts() {
    if ( is_user_logged_in() ) {
        wp_enqueue_media(); 
    }

    // Add Quill.js CDN Links
    wp_enqueue_style( 'quill-snow', 'https://cdn.quilljs.com/1.3.6/quill.snow.css', array(), '1.3.6' );
    wp_enqueue_script( 'quill-js', 'https://cdn.quilljs.com/1.3.6/quill.min.js', array(), '1.3.6', true );

    // Dynamic Cache Busting based on file modification time (Fixes caching performance)
    $css_file = plugin_dir_path( __FILE__ ) . 'assets/css/tcc-style.css';
    $css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : '3.4';
    
    $js_file  = plugin_dir_path( __FILE__ ) . 'assets/js/tcc-script.js';
    $js_ver   = file_exists( $js_file ) ? filemtime( $js_file ) : '3.4';

    wp_enqueue_style( 'tcc-style', plugin_dir_url( __FILE__ ) . 'assets/css/tcc-style.css', array(), $css_ver );
    wp_enqueue_script( 'tcc-script', plugin_dir_url( __FILE__ ) . 'assets/js/tcc-script.js', array('jquery', 'quill-js'), $js_ver, true );
    
    // Localize Script with Security Nonce
    wp_localize_script( 'tcc-script', 'tcc_ajax_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'tcc_secure_nonce' ) // Generates a secure CSRF token
    ));
}

// Include required files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-followup.php'; // Follow-up Dashboard Module
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-expenses.php'; 
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-expenses-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-fixed-departures.php';
// NEW: Frontend Group Tour Modules
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-frontend-group.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-frontend-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-fd-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-api.php'; // Child Plugin REST API
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-packages.php';

// REGISTER CUSTOM POST TYPE FOR QUOTES
add_action( 'init', 'tcc_register_quote_cpt' );
function tcc_register_quote_cpt() {
    register_post_type( 'tcc_quote', array(
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'exclude_from_search' => true,
        'rewrite' => array( 'slug' => 'QQuot' ),
        'supports' => array( 'title', 'editor' ),
    ));
}

// ROUTE LINK TO TEMPLATE
add_filter( 'template_include', 'tcc_quote_template' );
function tcc_quote_template( $template ) {
    if ( is_singular( 'tcc_quote' ) ) {
        $theme_file = locate_template( array( 'single-tcc_quote.php' ) );
        if ( $theme_file ) return $theme_file;
        return plugin_dir_path( __FILE__ ) . 'templates/single-tcc_quote.php';
    }
    return $template;
}

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC ACCESS — tcc_quote (QQuot) pages bypass LoginPress Force Login
//
// ROOT CAUSE of the previous failed fix:
//   LoginPress Force Login hooks its redirect onto 'init' (not template_redirect).
//   Our old Layer A used is_singular() inside the whitelist filter — but
//   is_singular() returns false at init time, so the whitelist was always empty.
//   Our old Layer B cleaned up template_redirect — which fires AFTER the
//   redirect + exit() had already run at init.
//
// CORRECT APPROACH — two methods that both work at init time:
//
//  1. URL-based whitelist filter (Layer A fixed)
//     Same LP filters, but now we detect the /QQuot/ slug directly from
//     REQUEST_URI instead of using is_singular(). Works at any hook stage.
//
//  2. init priority 0 — remove LP force-login callbacks (Layer B fixed)
//     At init priority 0, ALL plugins' add_action() calls are already
//     registered in $wp_filter (that happens at load time, before any hook
//     fires).  We scan init, wp, and template_redirect and surgically remove
//     every callback whose class name contains "loginpress" — before LP's
//     own init callback can execute and call wp_redirect().
// ═══════════════════════════════════════════════════════════════════════════

// ── Shared helper: is the current HTTP request a QQuot page? ──────────────
// Works at any hook stage including init (no WP query needed).
function tcc_is_qquot_request() {
    static $result = null;
    if ( $result !== null ) return $result;

    $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $result = (bool) preg_match( '#/QQuot/#i', $uri );
    return $result;
}

// ── Shared helper: remove all LoginPress callbacks from a given hook ───────
function tcc_strip_loginpress_from_hook( $hook ) {
    global $wp_filter;
    if ( empty( $wp_filter[ $hook ] ) ) return;

    foreach ( $wp_filter[ $hook ]->callbacks as $priority => $cbs ) {
        foreach ( $cbs as $key => $cb ) {
            $fn = $cb['function'];
            $is_lp = false;

            // [$object, 'method'] — match on class name
            if ( is_array( $fn ) && isset( $fn[0] ) && is_object( $fn[0] ) ) {
                $is_lp = false !== stripos( get_class( $fn[0] ), 'loginpress' );
            }
            // ['ClassName', 'method'] static call
            elseif ( is_array( $fn ) && isset( $fn[0] ) && is_string( $fn[0] ) ) {
                $is_lp = false !== stripos( $fn[0], 'loginpress' );
            }
            // plain function name e.g. loginpress_force_login_redirect()
            elseif ( is_string( $fn ) ) {
                $is_lp = false !== stripos( $fn, 'loginpress' )
                      && ( false !== stripos( $fn, 'force' )
                           || false !== stripos( $fn, 'redirect' )
                           || false !== stripos( $fn, 'login' ) );
            }

            if ( $is_lp ) {
                unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $key ] );
            }
        }
    }
}

// ── Method 1: URL-based whitelist filter (fixed — no is_singular) ─────────
add_filter( 'loginpress_force_login_whitelist', 'tcc_lp_whitelist_qquot_url' );
add_filter( 'loginpress_whitelist',             'tcc_lp_whitelist_qquot_url' ); // LP < 2.0

function tcc_lp_whitelist_qquot_url( $list ) {
    if ( ! is_array( $list ) ) $list = array();
    if ( ! tcc_is_qquot_request() ) return $list;

    // Build the full URL from server vars — reliable at any hook stage.
    $scheme      = is_ssl() ? 'https' : 'http';
    $host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $uri         = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $uri_no_qs   = strtok( $uri, '?' ); // strip query string

    // Add all URL variants LP might compare against
    foreach ( array(
        $scheme . '://' . $host . $uri,
        $scheme . '://' . $host . $uri_no_qs,
        $scheme . '://' . $host . rtrim( $uri_no_qs, '/' ),
        $scheme . '://' . $host . rtrim( $uri_no_qs, '/' ) . '/',
    ) as $url ) {
        $list[] = $url;
    }

    return $list;
}

// ── Method 2: init priority 0 — strip LP Force Login before it can run ────
// All plugins' add_action() calls are registered at load time, so even at
// init priority 0 we can see and remove LP's priority-10+ init callbacks.
add_action( 'init', 'tcc_init_remove_lp_force_login', 0 );

function tcc_init_remove_lp_force_login() {
    if ( ! tcc_is_qquot_request() ) return;

    // Remove LP from every hook it might use for its force-login redirect.
    foreach ( array( 'init', 'wp', 'template_redirect', 'send_headers' ) as $hook ) {
        tcc_strip_loginpress_from_hook( $hook );
    }

    // Belt-and-suspenders: repeat at 'wp' and 'template_redirect' in case
    // LP registered anything after our init priority-0 ran (shouldn't happen,
    // but some LP Pro versions add callbacks from within their own init hooks).
    add_action( 'wp',               'tcc_wp_remove_lp_force_login', 0 );
    add_action( 'template_redirect','tcc_tr_remove_lp_force_login', 0 );
}

function tcc_wp_remove_lp_force_login() {
    tcc_strip_loginpress_from_hook( 'wp' );
    tcc_strip_loginpress_from_hook( 'template_redirect' );
}

function tcc_tr_remove_lp_force_login() {
    tcc_strip_loginpress_from_hook( 'template_redirect' );
}

// ACTIVATION: Initialize Database and Master Settings
register_activation_hook( __FILE__, 'tcc_plugin_activation' );
function tcc_plugin_activation() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $sql_hotels = "CREATE TABLE $table_hotels (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        destination varchar(100) NOT NULL,
        night_stay_place varchar(100) NOT NULL,
        hotel_category varchar(50) NOT NULL,
        hotel_name varchar(150) NOT NULL,
        hotel_website varchar(255) DEFAULT '' NOT NULL,
        room_price float NOT NULL,
        extra_bed_price float NOT NULL,
        child_price float NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_hotels );

    $table_transport = $wpdb->prefix . 'tcc_transport_rates';
    $sql_transport = "CREATE TABLE $table_transport (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        destination varchar(100) NOT NULL,
        pickup_location varchar(100) NOT NULL,
        vehicle_type varchar(100) NOT NULL,
        capacity int(11) NOT NULL DEFAULT 1,
        price_per_day float NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql_transport );

    if ( ! get_option( 'tcc_master_settings' ) ) {
        $default_master = array(
            'Kashmir' => array(
                'profit_per_person' => 0,
                'pickups' => array('Srinagar', 'Jammu'),
                'stay_places' => array('Srinagar', 'Gulmarg', 'Pahalgam', 'Sonamarg'),
                'vehicles' => array('Innova', 'Tempo Traveler', 'Sedan'),
                'hotel_categories' => array('Deluxe', 'Premium', 'Standard'),
                'inclusions' => "Welcome Drink on Arrival\nDaily Breakfast & Dinner\nToll Taxes & Parking",
                'exclusions' => "Flights/Train Tickets\nPersonal Expenses\nEntry Fees to Monuments",
                'payment_terms' => "50% Advance to confirm booking\n50% Before arrival",
                'seasons' => array()
            )
        );
        add_option( 'tcc_master_settings', $default_master );
    }

    tcc_register_quote_cpt();
	TCC_Packages::register_cpt();
    flush_rewrite_rules(); 
}