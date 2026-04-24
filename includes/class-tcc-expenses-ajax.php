<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_tcc_load_master_finances', 'tcc_load_master_finances_ajax' );
add_action( 'wp_ajax_tcc_save_booking_expense', 'tcc_save_booking_expense_ajax' );
add_action( 'wp_ajax_tcc_save_general_expense', 'tcc_save_general_expense_ajax' );
add_action( 'wp_ajax_tcc_delete_general_expense', 'tcc_delete_general_expense_ajax' );
add_action( 'wp_ajax_tcc_save_auto_expense', 'tcc_save_auto_expense_ajax' );
add_action( 'wp_ajax_tcc_delete_auto_expense', 'tcc_delete_auto_expense_ajax' );

function tcc_load_master_finances_ajax() {
    if ( ! is_user_logged_in() ) wp_die();

    // 1. PROCESS AUTO-DAILY EXPENSES LOGIC
    $last_run = get_option('tcc_last_auto_expense_date', '');
    $today = current_time('Y-m-d');
    
    if ($last_run !== $today) {
        $recurring = get_option('tcc_auto_daily_expenses', array());
        if (!empty($recurring)) {
            $general = get_option('tcc_general_expenses', array());
            $start_ts = empty($last_run) ? strtotime($today) : strtotime($last_run . ' +1 day');
            $today_ts = strtotime($today);
            
            if ($today_ts - $start_ts > 30 * 86400) $start_ts = $today_ts - (30 * 86400); 

            $changed = false;
            for ($ts = $start_ts; $ts <= $today_ts; $ts += 86400) {
                $loop_date = date('Y-m-d', $ts);
                foreach ($recurring as $rec) {
                    $general[] = array(
                        'id' => uniqid('ge_auto_'),
                        'date' => $loop_date,
                        'category' => $rec['category'],
                        'desc' => $rec['desc'] . ' [Auto]',
                        'amount' => $rec['amount']
                    );
                    $changed = true;
                }
            }
            if ($changed) update_option('tcc_general_expenses', $general);
        }
        update_option('tcc_last_auto_expense_date', $today);
    }

    // 2. FETCH ALL BOOKINGS
    $args = array('post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'publish');
    $quotes = get_posts($args);
    $bookings_data = array();

    foreach($quotes as $q) {
        $payments = get_post_meta($q->ID, 'tcc_payments', true);
        $booking_income = 0;
        $actual_pg_paid = 0; // Tracks Actual PG Fee Deducted from the Bank
        
        if (is_array($payments)) {
            foreach($payments as $p) {
                if (isset($p['method']) && $p['method'] !== 'Refund') {
                    $booking_income += floatval($p['amount']);
                    $actual_pg_paid += isset($p['pg_fee']) ? floatval($p['pg_fee']) : 0;
                }
            }
        }

        $status = get_post_meta($q->ID, 'tcc_lead_status', true);
        
        if ($status !== 'Booking Done' && $booking_income <= 0) {
            continue; 
        }

        $data = json_decode($q->post_content, true);
        $sum = isset($data['summary']) ? $data['summary'] : array();

        $booking_date = date('Y-m-d', strtotime($q->post_date));

        // Get Traveler data
        $pax = isset($sum['pax']) ? intval($sum['pax']) : 0;
        $child = isset($sum['child']) ? intval($sum['child']) : 0;
        $child_6_12 = isset($sum['child_6_12']) ? intval($sum['child_6_12']) : 0;
        $total_travellers = $pax + $child + $child_6_12;

        $grand_total = isset($data['grand_total']) ? floatval($data['grand_total']) : (isset($sum['grand_total']) ? floatval($sum['grand_total']) : 0);

        // ADDON LOGIC: Extract addon costs so we can inject them automatically as manual adjustments
        $total_addon_cost = isset($sum['total_addon_cost']) ? floatval($sum['total_addon_cost']) : 0;
        $quote_addons = array();
        
        if (isset($data['addons']) && is_array($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                if (!empty($addon['name']) && floatval($addon['price']) > 0) {
                    $quote_addons[] = array(
                        'type' => 'base_cost',
                        'desc' => '[Trip Add-on] ' . sanitize_text_field($addon['name']),
                        'amount' => floatval($addon['price']),
                        'is_addon' => true
                    );
                }
            }
        }

        $original_cost = 0;
        if (isset($sum['actual_cost'])) {
            $original_cost = floatval($sum['actual_cost']);
        } else {
            $h = isset($sum['total_hotel_cost']) ? floatval($sum['total_hotel_cost']) : 0;
            $t = isset($sum['total_trans_cost']) ? floatval($sum['total_trans_cost']) : 0;
            $original_cost = $h + $t + $total_addon_cost;
        }

        $override_cost = get_post_meta($q->ID, 'tcc_override_actual_cost', true);
        $actual_cost = ($override_cost !== '' && $override_cost !== false && floatval($override_cost) > 0) ? floatval($override_cost) : $original_cost;
        
        // Remove the addon cost from the Base Cost Input because they are being added as rows dynamically
        $base_cost_no_addons = max(0, $actual_cost - $total_addon_cost);

        $vendor_payments = get_post_meta($q->ID, 'tcc_vendor_payments', true);
        if(!is_array($vendor_payments)) $vendor_payments = array();
        $vendor_paid = 0;
        foreach($vendor_payments as $vp) { $vendor_paid += floatval($vp['amount']); }

        $pt = isset($sum['prof_tax']) ? floatval($sum['prof_tax']) : 0;
        $pg = isset($sum['pg_charge']) ? floatval($sum['pg_charge']) : 0; // Theoretical PG Quoted
        $gst = isset($data['gst']) ? floatval($data['gst']) : 0;

        $manual_expenses = get_post_meta($q->ID, 'tcc_manual_expenses', true);
        if (!is_array($manual_expenses)) { $manual_expenses = array(); }

        $c_name = isset($sum['client_name']) ? $sum['client_name'] : 'Unknown';
        $dest = isset($sum['destination']) ? $sum['destination'] : 'Unknown';

        $bookings_data[$q->ID] = array(
            'title' => $q->post_title . ' | ' . $c_name . ' (' . $dest . ')',
            'url' => get_permalink($q->ID),
            'destination' => $dest,
            'pax' => $total_travellers,
            'booking_date' => $booking_date,
            'income' => $booking_income,
            'pkg_value' => $grand_total,
            'auto_cost' => $base_cost_no_addons, // Filtered Cost without addons
            'vendor_paid' => $vendor_paid,
            'vendor_history' => $vendor_payments,
            'auto_pt' => $pt,
            'auto_pg' => $pg, // Quote PG
            'actual_pg' => $actual_pg_paid, // Real Actual PG Cut
            'auto_gst' => $gst,
            'quote_addons' => $quote_addons, 
            'manual_expenses' => $manual_expenses
        );
    }

    $general_expenses = get_option('tcc_general_expenses', array());
    usort($general_expenses, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

    $auto_expenses = get_option('tcc_auto_daily_expenses', array());

    wp_send_json_success(array(
        'bookings' => $bookings_data,
        'general_expenses' => $general_expenses,
        'auto_expenses' => $auto_expenses
    ));
}

function tcc_save_booking_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    
    // Addons are missing from the JS post because they are readonly. We must read them and add them back to DB base cost.
    $post = get_post($quote_id);
    $data = json_decode($post->post_content, true);
    $addon_cost = isset($data['summary']['total_addon_cost']) ? floatval($data['summary']['total_addon_cost']) : 0;

    if (isset($_POST['override_cost']) && $_POST['override_cost'] !== '') {
        $new_override = floatval($_POST['override_cost']) + $addon_cost;
        update_post_meta($quote_id, 'tcc_override_actual_cost', $new_override);
    } else {
        delete_post_meta($quote_id, 'tcc_override_actual_cost'); 
    }

    $vp_dates = isset($_POST['vp_date']) ? $_POST['vp_date'] : array();
    $vp_descs = isset($_POST['vp_desc']) ? $_POST['vp_desc'] : array();
    $vp_amts  = isset($_POST['vp_amt']) ? $_POST['vp_amt'] : array();
    
    $vendor_payments = array();
    for($i = 0; $i < count($vp_dates); $i++) {
        if (!empty($vp_dates[$i]) && floatval($vp_amts[$i]) > 0) {
            $vendor_payments[] = array(
                'date' => sanitize_text_field($vp_dates[$i]),
                'desc' => sanitize_text_field($vp_descs[$i]),
                'amount' => floatval($vp_amts[$i])
            );
        }
    }
    update_post_meta($quote_id, 'tcc_vendor_payments', $vendor_payments);

    // Save Manual Adjustments with TYPE
    $descs = isset($_POST['exp_desc']) ? $_POST['exp_desc'] : array();
    $amts = isset($_POST['exp_amt']) ? $_POST['exp_amt'] : array();
    $types = isset($_POST['exp_type']) ? $_POST['exp_type'] : array();

    $manual = array();
    for($i = 0; $i < count($descs); $i++) {
        if (!empty($descs[$i]) && floatval($amts[$i]) > 0) {
            $manual[] = array(
                'type' => sanitize_text_field(isset($types[$i]) ? $types[$i] : 'base_cost'),
                'desc' => sanitize_text_field($descs[$i]),
                'amount' => floatval($amts[$i])
            );
        }
    }
    update_post_meta($quote_id, 'tcc_manual_expenses', $manual);
    wp_send_json_success();
}

function tcc_save_general_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $date = sanitize_text_field($_POST['date']);
    $cat = sanitize_text_field($_POST['cat']);
    $desc = sanitize_text_field($_POST['desc']);
    $amount = floatval($_POST['amount']);
    if(empty($date) || empty($cat) || $amount <= 0) wp_send_json_error("Invalid input");
    $general = get_option('tcc_general_expenses', array());
    $general[] = array('id' => uniqid('ge_'), 'date' => $date, 'category' => $cat, 'desc' => $desc, 'amount' => $amount);
    update_option('tcc_general_expenses', $general);
    wp_send_json_success();
}

function tcc_delete_general_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = sanitize_text_field($_POST['id']);
    $general = get_option('tcc_general_expenses', array());
    $new_general = array();
    foreach($general as $ge) { if($ge['id'] !== $id) $new_general[] = $ge; }
    update_option('tcc_general_expenses', $new_general);
    wp_send_json_success();
}

function tcc_save_auto_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $cat = sanitize_text_field($_POST['cat']);
    $desc = sanitize_text_field($_POST['desc']);
    $amount = floatval($_POST['amount']);
    if(empty($cat) || $amount <= 0) wp_send_json_error("Invalid input");
    
    $autos = get_option('tcc_auto_daily_expenses', array());
    $autos[] = array('id' => uniqid('ae_'), 'category' => $cat, 'desc' => $desc, 'amount' => $amount);
    update_option('tcc_auto_daily_expenses', $autos);
    wp_send_json_success();
}

function tcc_delete_auto_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = sanitize_text_field($_POST['id']);
    $autos = get_option('tcc_auto_daily_expenses', array());
    $new_autos = array();
    foreach($autos as $a) { if($a['id'] !== $id) $new_autos[] = $a; }
    update_option('tcc_auto_daily_expenses', $new_autos);
    wp_send_json_success();
}