<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_tcc_load_master_finances', 'tcc_load_master_finances_ajax' );
add_action( 'wp_ajax_tcc_save_booking_expense', 'tcc_save_booking_expense_ajax' );
add_action( 'wp_ajax_tcc_save_general_expense', 'tcc_save_general_expense_ajax' );
add_action( 'wp_ajax_tcc_delete_general_expense', 'tcc_delete_general_expense_ajax' );
add_action( 'wp_ajax_tcc_save_auto_expense', 'tcc_save_auto_expense_ajax' );
add_action( 'wp_ajax_tcc_delete_auto_expense', 'tcc_delete_auto_expense_ajax' );

add_action( 'wp_ajax_tcc_save_partner', 'tcc_save_partner_ajax' );
add_action( 'wp_ajax_tcc_delete_partner', 'tcc_delete_partner_ajax' );
add_action( 'wp_ajax_tcc_save_custom_pl', 'tcc_save_custom_pl_ajax' );
add_action( 'wp_ajax_tcc_delete_custom_pl', 'tcc_delete_custom_pl_ajax' );

function tcc_load_master_finances_ajax() {
    if ( ! is_user_logged_in() ) wp_die();

    $last_run = get_option('tcc_last_auto_expense_date', '');
    $today = current_time('Y-m-d');
    
    if ($last_run !== $today) {
        $recurring = get_option('tcc_auto_daily_expenses', array());
        if (!is_array($recurring)) $recurring = array(); 

        if (!empty($recurring)) {
            $general = get_option('tcc_general_expenses', array());
            if (!is_array($general)) $general = array(); 

            $start_ts = empty($last_run) ? strtotime($today) : strtotime($last_run . ' +1 day');
            $today_ts = strtotime($today);
            if ($today_ts - $start_ts > 30 * 86400) $start_ts = $today_ts - (30 * 86400); 

            $changed = false;
            for ($ts = $start_ts; $ts <= $today_ts; $ts += 86400) {
                $loop_date = date('Y-m-d', $ts);
                $days_in_month = date('t', $ts); 
                
                foreach ($recurring as $rec) {
                    $freq = isset($rec['freq']) ? $rec['freq'] : 'daily';
                    $amt = floatval($rec['amount']);
                    
                    if ($freq === 'monthly') {
                        $daily_amt = round($amt / $days_in_month, 2); 
                        $desc_suffix = ' [Auto-Monthly]';
                    } else {
                        $daily_amt = $amt;
                        $desc_suffix = ' [Auto-Daily]';
                    }
                    
                    $general[] = array('id' => uniqid('ge_auto_'), 'date' => $loop_date, 'category' => $rec['category'], 'desc' => $rec['desc'] . $desc_suffix, 'amount' => $daily_amt);
                    $changed = true;
                }
            }
            if ($changed) update_option('tcc_general_expenses', array_values($general));
        }
        update_option('tcc_last_auto_expense_date', $today);
    }

    $args = array('post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'publish');
    $quotes = get_posts($args);
    $bookings_data = array();

    foreach($quotes as $q) {
        $booking_date = date('Y-m-d', strtotime($q->post_date));
        $payments = get_post_meta($q->ID, 'tcc_payments', true);
        $booking_income = 0;
        $actual_pg_paid = 0; 
        $last_payment_date = $booking_date; 
        $valid_payments = array();
        
        if (is_array($payments)) {
            foreach($payments as $p) {
                if (isset($p['method']) && $p['method'] !== 'Refund') {
                    $booking_income += floatval($p['amount']);
                    $actual_pg_paid += isset($p['pg_fee']) ? floatval($p['pg_fee']) : 0;
                    if (!empty($p['date'])) {
                        $p_date = date('Y-m-d', strtotime($p['date']));
                        if ($p_date > $last_payment_date) $last_payment_date = $p_date;
                    }
                    $valid_payments[] = $p;
                }
            }
        }

        $status = get_post_meta($q->ID, 'tcc_lead_status', true);
        if ($status !== 'Booking Done' && $booking_income <= 0) continue; 

        $data = json_decode($q->post_content, true);
        $sum = isset($data['summary']) ? $data['summary'] : array();
        $pax = isset($sum['pax']) ? intval($sum['pax']) : 0;
        $child = isset($sum['child']) ? intval($sum['child']) : 0;
        $child_6_12 = isset($sum['child_6_12']) ? intval($sum['child_6_12']) : 0;
        $total_travellers = $pax + $child + $child_6_12;

        $grand_total = isset($data['grand_total']) ? floatval($data['grand_total']) : (isset($sum['grand_total']) ? floatval($sum['grand_total']) : 0);

        $total_addon_cost = isset($sum['total_addon_cost']) ? floatval($sum['total_addon_cost']) : 0;
        $quote_addons = array();
        if (isset($data['addons']) && is_array($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                if (!empty($addon['name']) && floatval($addon['price']) > 0) {
                    $quote_addons[] = array('type' => 'base_cost', 'desc' => '[Trip Add-on] ' . sanitize_text_field($addon['name']), 'amount' => floatval($addon['price']), 'is_addon' => true);
                }
            }
        }

        $original_cost = 0;
        if (isset($sum['actual_cost'])) $original_cost = floatval($sum['actual_cost']);
        else { $h = isset($sum['total_hotel_cost']) ? floatval($sum['total_hotel_cost']) : 0; $t = isset($sum['total_trans_cost']) ? floatval($sum['total_trans_cost']) : 0; $original_cost = $h + $t + $total_addon_cost; }

        $override_cost = get_post_meta($q->ID, 'tcc_override_actual_cost', true);
        $actual_cost = ($override_cost !== '' && $override_cost !== false && floatval($override_cost) > 0) ? floatval($override_cost) : $original_cost;
        $base_cost_no_addons = max(0, $actual_cost - $total_addon_cost);

        $vendor_payments = get_post_meta($q->ID, 'tcc_vendor_payments', true);
        if(!is_array($vendor_payments)) $vendor_payments = array();
        $vendor_paid = 0;
        foreach($vendor_payments as $vp) { $vendor_paid += floatval($vp['amount']); }

        $pt = isset($sum['prof_tax']) ? floatval($sum['prof_tax']) : 0;
        $pg = isset($sum['pg_charge']) ? floatval($sum['pg_charge']) : 0;
        $gst = isset($data['gst']) ? floatval($data['gst']) : 0;

        $manual_expenses = get_post_meta($q->ID, 'tcc_manual_expenses', true);
        if (!is_array($manual_expenses)) $manual_expenses = array();

        $c_name = isset($sum['client_name']) ? $sum['client_name'] : 'Unknown';
        $dest = isset($sum['destination']) ? $sum['destination'] : 'Unknown';

        $bookings_data[$q->ID] = array(
            'title' => $q->post_title . ' | ' . $c_name . ' (' . $dest . ')', 'url' => get_permalink($q->ID), 'destination' => $dest,
            'pax' => $total_travellers, 'booking_date' => $booking_date, 'final_payment_date' => $last_payment_date, 'payment_history' => $valid_payments,
            'income' => $booking_income, 'pkg_value' => $grand_total, 'auto_cost' => $base_cost_no_addons,
            'vendor_paid' => $vendor_paid, 'vendor_history' => $vendor_payments, 'auto_pt' => $pt, 'auto_pg' => $pg, 'actual_pg' => $actual_pg_paid,
            'auto_gst' => $gst, 'quote_addons' => $quote_addons, 'manual_expenses' => $manual_expenses
        );
    }

    $general_expenses = get_option('tcc_general_expenses', array());
    if(!is_array($general_expenses)) $general_expenses = array();
    $general_expenses = array_values($general_expenses); 
    usort($general_expenses, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

    $auto_expenses = get_option('tcc_auto_daily_expenses', array());
    if(!is_array($auto_expenses)) $auto_expenses = array();
    $auto_expenses = array_values($auto_expenses); 

    $partners = get_option('tcc_agency_partners', array());
    if(!is_array($partners)) $partners = array();
    
    // Fallback migration to inject "history" and "is_investor" format into any older partner data arrays
    foreach($partners as &$p) {
        if(!isset($p['history'])) {
            $p['history'] = array(array('date' => '2000-01-01', 'percent' => isset($p['percent']) ? floatval($p['percent']) : 0));
        }
        if(!isset($p['is_investor'])) {
            $p['is_investor'] = false;
        }
    }
    
    $custom_pl = get_option('tcc_custom_pl', array());
    if(!is_array($custom_pl)) $custom_pl = array();
    usort($custom_pl, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

    wp_send_json_success(array(
        'bookings' => $bookings_data,
        'general_expenses' => $general_expenses,
        'auto_expenses' => $auto_expenses,
        'partners' => array_values($partners),
        'custom_pl' => $custom_pl
    ));
}

function tcc_save_booking_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    
    $post = get_post($quote_id);
    $data = json_decode($post->post_content, true);
    $addon_cost = isset($data['summary']['total_addon_cost']) ? floatval($data['summary']['total_addon_cost']) : 0;

    if (isset($_POST['override_cost']) && $_POST['override_cost'] !== '') {
        $new_override = floatval($_POST['override_cost']) + $addon_cost;
        update_post_meta($quote_id, 'tcc_override_actual_cost', $new_override);
    } else { delete_post_meta($quote_id, 'tcc_override_actual_cost'); }

    $vp_dates = isset($_POST['vp_date']) ? $_POST['vp_date'] : array();
    $vp_descs = isset($_POST['vp_desc']) ? $_POST['vp_desc'] : array();
    $vp_amts  = isset($_POST['vp_amt']) ? $_POST['vp_amt'] : array();
    $vendor_payments = array();
    for($i = 0; $i < count($vp_dates); $i++) {
        if (!empty($vp_dates[$i]) && floatval($vp_amts[$i]) > 0) {
            $vendor_payments[] = array('date' => sanitize_text_field($vp_dates[$i]), 'desc' => sanitize_text_field($vp_descs[$i]), 'amount' => floatval($vp_amts[$i]));
        }
    }
    update_post_meta($quote_id, 'tcc_vendor_payments', $vendor_payments);

    $descs = isset($_POST['exp_desc']) ? $_POST['exp_desc'] : array();
    $amts = isset($_POST['exp_amt']) ? $_POST['exp_amt'] : array();
    $types = isset($_POST['exp_type']) ? $_POST['exp_type'] : array();
    $manual = array();
    for($i = 0; $i < count($descs); $i++) {
        if (!empty($descs[$i]) && floatval($amts[$i]) > 0) {
            $manual[] = array('type' => sanitize_text_field(isset($types[$i]) ? $types[$i] : 'base_cost'), 'desc' => sanitize_text_field($descs[$i]), 'amount' => floatval($amts[$i]));
        }
    }
    update_post_meta($quote_id, 'tcc_manual_expenses', $manual);
    wp_send_json_success();
}

function tcc_save_general_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
    $date = sanitize_text_field($_POST['date']);
    $cat = sanitize_text_field($_POST['cat']);
    $desc = sanitize_text_field($_POST['desc']);
    $amount = floatval($_POST['amount']);
    
    if(empty($date) || empty($cat) || $amount <= 0) wp_send_json_error("Invalid input");
    $general = get_option('tcc_general_expenses', array());
    if(!is_array($general)) $general = array(); 
    
    if (!empty($id)) {
        foreach ($general as &$ge) { if ($ge['id'] === $id) { $ge['date'] = $date; $ge['category'] = $cat; $ge['desc'] = $desc; $ge['amount'] = $amount; break; } }
    } else {
        $general[] = array('id' => uniqid('ge_'), 'date' => $date, 'category' => $cat, 'desc' => $desc, 'amount' => $amount);
    }
    update_option('tcc_general_expenses', array_values($general));
    wp_send_json_success();
}

function tcc_delete_general_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = sanitize_text_field($_POST['id']);
    $general = get_option('tcc_general_expenses', array());
    if(!is_array($general)) $general = array(); 
    $new_general = array();
    foreach($general as $ge) { if($ge['id'] !== $id) $new_general[] = $ge; }
    update_option('tcc_general_expenses', array_values($new_general));
    wp_send_json_success();
}

function tcc_save_auto_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
    $cat = sanitize_text_field($_POST['cat']);
    $desc = sanitize_text_field($_POST['desc']);
    $amount = floatval($_POST['amount']);
    $freq = isset($_POST['freq']) ? sanitize_text_field($_POST['freq']) : 'daily';
    
    if(empty($cat) || $amount <= 0) wp_send_json_error("Invalid input");
    $autos = get_option('tcc_auto_daily_expenses', array());
    if(!is_array($autos)) $autos = array(); 
    
    if (!empty($id)) {
        foreach ($autos as &$a) { if ($a['id'] === $id) { $a['category'] = $cat; $a['desc'] = $desc; $a['amount'] = $amount; $a['freq'] = $freq; break; } }
    } else {
        $autos[] = array('id' => uniqid('ae_'), 'category' => $cat, 'desc' => $desc, 'amount' => $amount, 'freq' => $freq);
    }
    update_option('tcc_auto_daily_expenses', array_values($autos));
    wp_send_json_success();
}

function tcc_delete_auto_expense_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = sanitize_text_field($_POST['id']);
    $autos = get_option('tcc_auto_daily_expenses', array());
    if(!is_array($autos)) $autos = array(); 
    $new_autos = array();
    foreach($autos as $a) { if($a['id'] !== $id) $new_autos[] = $a; }
    update_option('tcc_auto_daily_expenses', array_values($new_autos));
    wp_send_json_success();
}

// PARTNERS
function tcc_save_partner_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    
    $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
    $name = sanitize_text_field($_POST['name']);
    $percent = floatval($_POST['percent']);
    $is_investor = isset($_POST['is_investor']) && $_POST['is_investor'] === 'true';
    $today = current_time('Y-m-d');

    if(empty($name) || $percent < 0) wp_send_json_error();

    $partners = get_option('tcc_agency_partners', array());
    if(!is_array($partners)) $partners = array();

    // If this partner is the investor, remove the investor tag from everyone else
    if ($is_investor) {
        foreach($partners as &$p) { $p['is_investor'] = false; }
    }

    if(!empty($id)) {
        foreach($partners as &$p) {
            if($p['id'] === $id) {
                $p['name'] = $name;
                $p['is_investor'] = $is_investor;
                
                if(!isset($p['history'])) { $p['history'] = array(array('date' => '2000-01-01', 'percent' => isset($p['percent']) ? floatval($p['percent']) : 0)); }
                
                usort($p['history'], function($a, $b) { return strcmp($a['date'], $b['date']); });
                $last_idx = count($p['history']) - 1;
                
                if($p['history'][$last_idx]['date'] === $today) {
                    $p['history'][$last_idx]['percent'] = $percent;
                } else if (floatval($p['history'][$last_idx]['percent']) !== $percent) {
                    $p['history'][] = array('date' => $today, 'percent' => $percent);
                }
                break;
            }
        }
    } else {
        $partners[] = array(
            'id' => uniqid('pt_'), 
            'name' => $name, 
            'is_investor' => $is_investor,
            'history' => array(array('date' => $today, 'percent' => $percent))
        );
    }
    
    update_option('tcc_agency_partners', array_values($partners));
    wp_send_json_success();
}

function tcc_delete_partner_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = sanitize_text_field($_POST['id']);
    $partners = get_option('tcc_agency_partners', array());
    $new_partners = array();
    foreach($partners as $p) { if($p['id'] !== $id) $new_partners[] = $p; }
    update_option('tcc_agency_partners', array_values($new_partners));
    wp_send_json_success();
}

// CUSTOM P&L
function tcc_save_custom_pl_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $date = sanitize_text_field($_POST['date']);
    $desc = sanitize_text_field($_POST['desc']);
    $type = sanitize_text_field($_POST['type']); // 'profit' or 'loss'
    $amount = floatval($_POST['amount']);

    if(empty($date) || $amount <= 0) wp_send_json_error();

    $pls = get_option('tcc_custom_pl', array());
    if(!is_array($pls)) $pls = array();

    $pls[] = array('id' => uniqid('cpl_'), 'date' => $date, 'desc' => $desc, 'type' => $type, 'amount' => $amount);
    update_option('tcc_custom_pl', array_values($pls));
    wp_send_json_success();
}

function tcc_delete_custom_pl_ajax() {
    if ( ! is_user_logged_in() ) wp_die();
    $id = sanitize_text_field($_POST['id']);
    $pls = get_option('tcc_custom_pl', array());
    $new_pls = array();
    foreach($pls as $p) { if($p['id'] !== $id) $new_pls[] = $p; }
    update_option('tcc_custom_pl', array_values($new_pls));
    wp_send_json_success();
}