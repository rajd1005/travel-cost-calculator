<?php
/*
Plugin Name: Travel Cost Calculator
Description: Complete V3.4 - Smart Auto-Calc, Indian Currency, Persistent Links, Inclusions/Exclusions, Booking Manager, and Lead Follow-up Dashboard.
Version: 3.4
Author: Your Agency
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue Styles & Scripts with AJAX
add_action( 'wp_enqueue_scripts', 'tcc_enqueue_scripts' );
function tcc_enqueue_scripts() {
    wp_enqueue_style( 'tcc-style', plugin_dir_url( __FILE__ ) . 'assets/css/tcc-style.css', array(), '3.4' );
    wp_enqueue_script( 'tcc-script', plugin_dir_url( __FILE__ ) . 'assets/js/tcc-script.js', array('jquery'), '3.4', true );
    
    wp_localize_script( 'tcc-script', 'tcc_ajax_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' )
    ));
}

// Include required files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tcc-followup.php'; // NEW: Follow-up Dashboard Module

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
    flush_rewrite_rules(); 
}