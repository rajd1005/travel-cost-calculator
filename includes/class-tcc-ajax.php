<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_tcc_calculate_trip', 'tcc_calculate_trip' );
add_action( 'wp_ajax_nopriv_tcc_calculate_trip', 'tcc_calculate_trip' );

add_action( 'wp_ajax_tcc_save_global_settings', 'tcc_save_global_settings' );
add_action( 'wp_ajax_tcc_save_master_settings', 'tcc_save_master_settings' );
add_action( 'wp_ajax_tcc_save_pricing_settings', 'tcc_save_pricing_settings' );
add_action( 'wp_ajax_tcc_save_transport_settings', 'tcc_save_transport_settings' );

add_action( 'wp_ajax_tcc_delete_hotel_rate', 'tcc_delete_hotel_rate' );
add_action( 'wp_ajax_tcc_rename_hotel_rate', 'tcc_rename_hotel_rate' ); 
add_action( 'wp_ajax_tcc_delete_transport_rate', 'tcc_delete_transport_rate' );
add_action( 'wp_ajax_tcc_rename_master_element', 'tcc_rename_master_element' ); 

add_action( 'wp_ajax_tcc_fetch_hotel_names', 'tcc_fetch_hotel_names' );
add_action( 'wp_ajax_nopriv_tcc_fetch_hotel_names', 'tcc_fetch_hotel_names' ); 

add_action( 'wp_ajax_tcc_fetch_hotel_rate', 'tcc_fetch_hotel_rate' );
add_action( 'wp_ajax_tcc_fetch_transport_rate', 'tcc_fetch_transport_rate' );

add_action( 'wp_ajax_tcc_optimize_transport', 'tcc_optimize_transport' );
add_action( 'wp_ajax_nopriv_tcc_optimize_transport', 'tcc_optimize_transport' );

// Full Itinerary Preset Actions
add_action( 'wp_ajax_tcc_save_itinerary_preset', 'tcc_save_itinerary_preset' );
add_action( 'wp_ajax_nopriv_tcc_save_itinerary_preset', 'tcc_save_itinerary_preset' ); 
add_action( 'wp_ajax_tcc_load_itinerary_presets', 'tcc_load_itinerary_presets' );
add_action( 'wp_ajax_nopriv_tcc_load_itinerary_presets', 'tcc_load_itinerary_presets' ); 
add_action( 'wp_ajax_tcc_delete_itinerary_preset', 'tcc_delete_itinerary_preset' );
add_action( 'wp_ajax_nopriv_tcc_delete_itinerary_preset', 'tcc_delete_itinerary_preset' );

add_action( 'wp_ajax_tcc_load_quotes_list', 'tcc_load_quotes_list' );
add_action( 'wp_ajax_tcc_load_quote_payments', 'tcc_load_quote_payments' );
add_action( 'wp_ajax_tcc_add_payment', 'tcc_add_payment' );
add_action( 'wp_ajax_tcc_delete_payment', 'tcc_delete_payment' );
add_action( 'wp_ajax_tcc_save_post_discount', 'tcc_save_post_discount' );

add_action( 'wp_ajax_tcc_delete_quote', 'tcc_delete_quote' );
add_action( 'wp_ajax_tcc_duplicate_quote', 'tcc_duplicate_quote' ); 
add_action( 'wp_ajax_tcc_update_quote_client', 'tcc_update_quote_client' );
add_action( 'wp_ajax_tcc_get_full_quote_data', 'tcc_get_full_quote_data' );

// BACKUP & RESTORE
add_action( 'wp_ajax_tcc_export_backup', 'tcc_export_backup' );
add_action( 'wp_ajax_tcc_import_backup', 'tcc_import_backup' );

// EMAIL ACTION
add_action( 'wp_ajax_tcc_send_quote_email', 'tcc_send_quote_email' );

// QUICK NOTES ACTIONS
add_action( 'wp_ajax_tcc_load_notes', 'tcc_load_notes' );
add_action( 'wp_ajax_tcc_save_note', 'tcc_save_note' );
add_action( 'wp_ajax_tcc_delete_note', 'tcc_delete_note' );

// ADDON PRESET ACTIONS
add_action( 'wp_ajax_tcc_save_addon_preset', 'tcc_save_addon_preset' );
add_action( 'wp_ajax_nopriv_tcc_save_addon_preset', 'tcc_save_addon_preset' );
add_action( 'wp_ajax_tcc_load_addon_presets', 'tcc_load_addon_presets' );
add_action( 'wp_ajax_nopriv_tcc_load_addon_presets', 'tcc_load_addon_presets' );
add_action( 'wp_ajax_tcc_delete_addon_preset', 'tcc_delete_addon_preset' );
add_action( 'wp_ajax_nopriv_tcc_delete_addon_preset', 'tcc_delete_addon_preset' );

// INDIVIDUAL DAY PRESET ACTIONS
add_action( 'wp_ajax_tcc_save_single_day_preset', 'tcc_save_single_day_preset' );
add_action( 'wp_ajax_nopriv_tcc_save_single_day_preset', 'tcc_save_single_day_preset' );
add_action( 'wp_ajax_tcc_load_single_day_presets', 'tcc_load_single_day_presets' );
add_action( 'wp_ajax_nopriv_tcc_load_single_day_presets', 'tcc_load_single_day_presets' );
add_action( 'wp_ajax_tcc_delete_single_day_preset', 'tcc_delete_single_day_preset' );
add_action( 'wp_ajax_nopriv_tcc_delete_single_day_preset', 'tcc_delete_single_day_preset' );

// SERVER-SIDE PDF GENERATION ACTION
add_action( 'wp_ajax_tcc_generate_server_pdf', 'tcc_generate_server_pdf' );
// [PATCH-C1A] Unauthenticated PDF endpoint removed — prevented SSRF/DoS attacks.
// add_action( 'wp_ajax_nopriv_tcc_generate_server_pdf', 'tcc_generate_server_pdf' );

// =========================================================================
// SAFE JSON RETRIEVAL HELPER (Maintains backward compatibility for older quotes)
// =========================================================================
function tcc_get_quote_json_data($post_id) {
    $post = get_post($post_id);
    if(!$post) return array();
    
    // First try the new secure meta location
    $meta_data = get_post_meta($post_id, '_tcc_quote_json_data', true);
    if (!empty($meta_data)) {
        return json_decode($meta_data, true);
    }
    
    // Fallback to old post_content method for older quotes
    return json_decode($post->post_content, true);
}

// =========================================================================
// SAFE DATABASE SCHEMA CHECKER (Prevents old hotels from disappearing)
// =========================================================================
function tcc_ensure_room_types_column() {
    global $wpdb;
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    
    // Check if the table exists first to avoid installation errors
    if($wpdb->get_var("SHOW TABLES LIKE '$table_hotels'") == $table_hotels) {
        $col_check = $wpdb->get_results("SHOW COLUMNS FROM $table_hotels LIKE 'room_types'");
        if (empty($col_check)) {
            $wpdb->query("ALTER TABLE $table_hotels ADD COLUMN room_types longtext DEFAULT '' NOT NULL");
        }
    }
}
// =========================================================================

function tcc_optimize_transport() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    global $wpdb;
    $dest = trim(sanitize_text_field($_POST['destination']));
    $pickup = trim(sanitize_text_field($_POST['pickup_location']));
    $pax = intval($_POST['total_pax']);

    if ($pax <= 0) { wp_send_json_error(); wp_die(); }

    $table = $wpdb->prefix . 'tcc_transport_rates';
    $vehicles = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE destination = %s AND pickup_location = %s", $dest, $pickup));

    if (!$vehicles || count($vehicles) == 0) { wp_send_json_error(); wp_die(); }

    $max_cap = 0;
    foreach($vehicles as $v) {
        $cap = max(1, intval($v->capacity));
        if($cap > $max_cap) $max_cap = $cap;
    }

    $target = $pax + $max_cap;
    $cars = array_fill(0, $target + 1, INF);
    $cost = array_fill(0, $target + 1, INF);
    $choice = array_fill(0, $target + 1, null);
    
    $cars[0] = 0;
    $cost[0] = 0;

    for ($i = 0; $i <= $target; $i++) {
        if ($cars[$i] === INF) continue;
        
        foreach($vehicles as $v) {
            $cap = max(1, intval($v->capacity));
            $price = floatval($v->price_per_day);
            
            $next = $i + $cap;
            if ($next > $target) continue;

            $new_cars = $cars[$i] + 1;
            $new_cost = $cost[$i] + $price;

            if ($new_cars < $cars[$next] || ($new_cars == $cars[$next] && $new_cost < $cost[$next])) {
                $cars[$next] = $new_cars;
                $cost[$next] = $new_cost;
                $choice[$next] = array('prev' => $i, 'vehicle' => $v->vehicle_type);
            }
        }
    }

    $min_cars = INF;
    $best_idx = $pax;
    $best_cost = INF;
    
    for ($i = $pax; $i <= $target; $i++) {
        if ($cars[$i] === INF) continue;
        
        if ($cars[$i] < $min_cars) {
            $min_cars = $cars[$i];
            $best_idx = $i;
            $best_cost = $cost[$i];
        } elseif ($cars[$i] == $min_cars && $cost[$i] < $best_cost) {
            $best_idx = $i;
            $best_cost = $cost[$i];
        }
    }

    if ($min_cars === INF) { wp_send_json_error(); wp_die(); }

    $mix = [];
    $curr = $best_idx;
    while ($curr > 0 && isset($choice[$curr])) {
        $v_name = $choice[$curr]['vehicle'];
        if (!isset($mix[$v_name])) $mix[$v_name] = 0;
        $mix[$v_name]++;
        $curr = $choice[$curr]['prev'];
    }

    $result = [];
    foreach($mix as $v_name => $qty) { 
        $result[] = array('vehicle' => $v_name, 'qty' => $qty); 
    }
    
    wp_send_json_success($result);
}

function tcc_calculate_trip() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    tcc_ensure_room_types_column(); // Ensure schema is up-to-date
    
    global $wpdb;
    
    $globals = get_option('tcc_global_settings', array('gst' => 5, 'pt' => 10, 'pg' => 3));
    $gst_pct = floatval($globals['gst']);
    $pt_pct  = floatval($globals['pt']);
    $pg_pct  = floatval($globals['pg']);

    $edit_quote_id = isset($_POST['edit_quote_id']) ? intval($_POST['edit_quote_id']) : 0;

    $client_name  = isset($_POST['client_name']) ? trim(sanitize_text_field($_POST['client_name'])) : '';
    $client_phone = isset($_POST['client_phone']) ? trim(sanitize_text_field($_POST['client_phone'])) : '';
    $client_email = isset($_POST['client_email']) ? trim(sanitize_email($_POST['client_email'])) : '';

    $destination    = trim(sanitize_text_field($_POST['destination']));
    $total_pax      = intval($_POST['total_pax']);
    $child_pax      = intval($_POST['child_pax']);
    $child_6_12_pax = isset($_POST['child_6_12_pax']) ? intval($_POST['child_6_12_pax']) : 0;
    $total_days     = intval($_POST['total_days']);
    $no_of_rooms    = intval($_POST['no_of_rooms']);
    $extra_beds     = intval($_POST['extra_beds']);
    
    $pickup_loc   = trim(sanitize_text_field($_POST['pickup_location']));
    $pickup_custom= isset($_POST['pickup_custom']) ? trim(sanitize_text_field($_POST['pickup_custom'])) : '';
    $drop_loc     = isset($_POST['drop_location']) ? trim(sanitize_text_field($_POST['drop_location'])) : $pickup_loc;
    $drop_custom  = isset($_POST['drop_custom']) ? trim(sanitize_text_field($_POST['drop_custom'])) : '';
    $share_cab_cost = isset($_POST['share_cab_cost']) ? 1 : 0;
$hide_travelers_rooms = isset($_POST['hide_travelers_rooms']) ? 1 : 0;
$hide_pricing = isset($_POST['hide_pricing']) ? 1 : 0;

    $display_pickup = !empty($pickup_custom) ? "{$pickup_custom} ({$pickup_loc})" : $pickup_loc;
    $display_drop   = !empty($drop_custom) ? "{$drop_custom} ({$drop_loc})" : $drop_loc;
    
    $hotel_cat    = trim(sanitize_text_field($_POST['hotel_category'])); 
    
    $start_date   = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = '';
    if (!empty($start_date) && $total_days > 0) {
        $total_nights = $total_days - 1;
        $end_date = date('Y-m-d', strtotime($start_date . " + {$total_nights} days"));
    } elseif (empty($start_date) && $total_days > 0) {
        $total_nights = $total_days - 1;
        $end_date = date('Y-m-d', strtotime(date('Y-m-d') . " + {$total_nights} days")); 
    }

    $stay_places  = isset($_POST['stay_place']) ? $_POST['stay_place'] : array();
    $stay_hotels  = isset($_POST['stay_hotel']) ? $_POST['stay_hotel'] : array();
    $stay_nights  = isset($_POST['stay_nights']) ? $_POST['stay_nights'] : array();
    $stay_cats    = isset($_POST['stay_category']) ? $_POST['stay_category'] : array(); 
    $stay_room_types = isset($_POST['stay_room_type']) ? wp_unslash($_POST['stay_room_type']) : array();

    $transports   = isset($_POST['transportation']) ? $_POST['transportation'] : array();
    $trans_qtys   = isset($_POST['transport_qty']) ? $_POST['transport_qty'] : array();
    $trans_days   = isset($_POST['transport_days']) ? $_POST['transport_days'] : array();
    $trans_pickups= isset($_POST['transport_pickup']) ? $_POST['transport_pickup'] : array(); 
    $trans_custom_rates = isset($_POST['transport_custom_rate']) ? $_POST['transport_custom_rate'] : array(); 
    $trans_custom_totals = isset($_POST['transport_custom_total']) ? $_POST['transport_custom_total'] : array();

    $day_itinerary = isset($_POST['itinerary_day']) ? wp_unslash($_POST['itinerary_day']) : array();
    $day_itinerary_desc = isset($_POST['itinerary_desc']) ? wp_unslash($_POST['itinerary_desc']) : array();
    $day_itinerary_image = isset($_POST['itinerary_image']) ? wp_unslash($_POST['itinerary_image']) : array();
    $day_itinerary_stays = isset($_POST['itinerary_stay_place']) ? wp_unslash($_POST['itinerary_stay_place']) : array();
    $day_itinerary_routes = isset($_POST['itinerary_route']) ? wp_unslash($_POST['itinerary_route']) : array();

    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_transport = $wpdb->prefix . 'tcc_transport_rates';

    $error_messages = [];
    $transport_cost = 0;
    $total_capacity = 0;
    $transport_details = [];
    $hotel_cost = 0;
    $child_actual_hotel_total = 0; // ADDED
    $detailed_stay_info = [];
    $transport_row_costs = [];
    $hotel_row_costs = [];

    $master_data = get_option('tcc_master_settings', array());
    
    // Tiered & Flat Profit Setup
    $profit_per_person = 0;
    $profit_type = 'flat';
    $profit_tiers = [];
    
    if(isset($master_data[$destination])) {
        if(isset($master_data[$destination]['profit_per_person'])) {
            $profit_per_person = floatval($master_data[$destination]['profit_per_person']);
        }
        if(isset($master_data[$destination]['profit_type'])) {
            $profit_type = $master_data[$destination]['profit_type'];
        }
        if(isset($master_data[$destination]['profit_tiers'])) {
            $profit_tiers = $master_data[$destination]['profit_tiers'];
        }
    }

    // CAPTURE EDITABLE QUOTE TERMS FROM FORM (Fallback to Master Data)
    $dest_inclusions = isset($_POST['quote_inclusions']) && !empty($_POST['quote_inclusions']) ? wp_unslash($_POST['quote_inclusions']) : (isset($master_data[$destination]['inclusions']) ? $master_data[$destination]['inclusions'] : '');
    
    $dest_exclusions = isset($_POST['quote_exclusions']) && !empty($_POST['quote_exclusions']) ? wp_unslash($_POST['quote_exclusions']) : (isset($master_data[$destination]['exclusions']) ? $master_data[$destination]['exclusions'] : '');
    
    $dest_payment_terms = isset($_POST['quote_payment_terms']) && !empty($_POST['quote_payment_terms']) ? wp_unslash($_POST['quote_payment_terms']) : (isset($master_data[$destination]['payment_terms']) ? $master_data[$destination]['payment_terms'] : '');

    $important_note = isset($_POST['quote_important_note']) && !empty($_POST['quote_important_note']) ? wp_unslash($_POST['quote_important_note']) : (isset($master_data[$destination]['important_note']) ? $master_data[$destination]['important_note'] : '');

    $why_choose_us = isset($_POST['quote_why_choose_us']) && !empty($_POST['quote_why_choose_us']) ? wp_unslash($_POST['quote_why_choose_us']) : (isset($master_data[$destination]['why_choose_us']) ? $master_data[$destination]['why_choose_us'] : '');

    $essential_guidelines = isset($_POST['quote_essential_guidelines']) && !empty($_POST['quote_essential_guidelines']) ? wp_unslash($_POST['quote_essential_guidelines']) : (isset($master_data[$destination]['essential_guidelines']) ? $master_data[$destination]['essential_guidelines'] : '');

    $surcharge_percent = 0;
    $surcharge_date = !empty($start_date) ? $start_date : date('Y-m-d'); 
    
    if(isset($master_data[$destination]['seasons']) && is_array($master_data[$destination]['seasons'])) {
        $trip_start_ts = strtotime($surcharge_date);
        foreach($master_data[$destination]['seasons'] as $season) {
            $s_start = strtotime($season['start']);
            $s_end = strtotime($season['end']);
            if($trip_start_ts >= $s_start && $trip_start_ts <= $s_end) {
                $surcharge_percent = floatval($season['percent']);
                break; 
            }
        }
    }
    
    $surcharge_multiplier = 1 + ($surcharge_percent / 100);

    if ( !empty($transports) ) {
        $first_rate_obj = null;
        $first_active_price = 0;
        $first_t_days = $total_days;
        $first_row_pickup = $pickup_loc;
        
        foreach ( $transports as $index => $veh ) {
            $qty = isset($trans_qtys[$index]) ? intval($trans_qtys[$index]) : 1;
            if ($qty <= 0) $qty = 1;
            $vehicle = trim(sanitize_text_field($veh));
            $t_days = isset($trans_days[$index]) && intval($trans_days[$index]) > 0 ? intval($trans_days[$index]) : $total_days;
            $row_pickup = isset($trans_pickups[$index]) && !empty($trans_pickups[$index]) ? trim(sanitize_text_field($trans_pickups[$index])) : $pickup_loc;
            
            $custom_rate_val = isset($trans_custom_rates[$index]) && $trans_custom_rates[$index] !== '' ? floatval($trans_custom_rates[$index]) : false;
            $custom_total_val = isset($trans_custom_totals[$index]) && $trans_custom_totals[$index] !== '' ? floatval($trans_custom_totals[$index]) : false;

            $rate = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_transport WHERE destination = %s AND pickup_location = %s AND vehicle_type = %s", $destination, $row_pickup, $vehicle));

            if ( $custom_total_val !== false || $custom_rate_val !== false || $rate ) {
                $cap = $rate ? max(1, intval($rate->capacity)) : 6; 

                if ($custom_total_val !== false) {
                    $row_final = $custom_total_val;
                    if ($index === 0) { 
                        $first_rate_obj = (object)['capacity' => $cap, 'price_per_day' => 0]; 
                        $first_active_price = ($qty > 0 && $t_days > 0) ? ($custom_total_val / $surcharge_multiplier / $qty / $t_days) : 0;
                        $first_t_days = $t_days; 
                        $first_row_pickup = $row_pickup;
                    }
                } else {
                    $active_price = ($custom_rate_val !== false) ? $custom_rate_val : floatval($rate->price_per_day);
                    if ($index === 0) { 
                        $first_rate_obj = (object)['capacity' => $cap, 'price_per_day' => $active_price]; 
                        $first_active_price = $active_price;
                        $first_t_days = $t_days; 
                        $first_row_pickup = $row_pickup;
                    } 
                    $row_base = ($active_price * $t_days) * $qty;
                    $row_final = $row_base * $surcharge_multiplier;
                }
                
                $transport_cost += $row_final;
                $total_capacity += ($cap * $qty);
                $transport_details[$index] = "{$qty}x {$vehicle} ({$t_days}D) [{$row_pickup}]";
                $transport_row_costs[$index] = $row_final;
            } else {
                $error_messages[] = "No Rate for {$vehicle} in {$row_pickup}";
            }
        }
        
        if ($total_capacity < $total_pax && $total_pax > 0 && $first_rate_obj) {
            $deficit = $total_pax - $total_capacity;
            $cap = max(1, intval($first_rate_obj->capacity));
            $extra_cars = ceil($deficit / $cap);
            $trans_qtys[0] += $extra_cars;
            
            $extra_cost_base = ($first_active_price * $first_t_days) * $extra_cars;
            $extra_cost_final = $extra_cost_base * $surcharge_multiplier;
            
            $transport_cost += $extra_cost_final;
            $total_capacity += ($cap * $extra_cars);
            
$transport_details[0] = "{$trans_qtys[0]}x " . trim(sanitize_text_field($transports[0])) . " ({$first_t_days}D) [{$first_row_pickup}]";
            $transport_row_costs[0] += $extra_cost_final;
        }

        // --- NEW LOGIC: DISTRIBUTE CAB COST PER SEAT ---
        if ($share_cab_cost && $total_capacity > 0) {
            $total_seats_needed = $total_pax + $child_6_12_pax + $child_pax;
            if ($total_seats_needed > $total_capacity) {
                $total_seats_needed = $total_capacity; // Cap it so we don't overcharge
            }
            $cost_per_seat = $transport_cost / $total_capacity;
            $shared_transport_cost = $cost_per_seat * $total_seats_needed;
            
            // Re-proportion row costs for accurate live breakdown display
            if ($transport_cost > 0) {
                $ratio = $shared_transport_cost / $transport_cost;
                foreach ($transport_row_costs as $idx => $cost) {
                    $transport_row_costs[$idx] = $cost * $ratio;
                }
            }
            $transport_cost = $shared_transport_cost;
        }
        // -----------------------------------------------
    }
    
    $transport_summary_string = implode(", ", $transport_details);
    if ($share_cab_cost) {
        $transport_summary_string .= " (Cost Shared)";
    }

    if ( !empty($stay_places) ) {
        foreach ( $stay_places as $index => $place_name ) {
            $place = trim(sanitize_text_field($place_name));
            $nights = intval($stay_nights[$index]);
            
            if ($place === 'No Hotel') {
                $detailed_stay_info[] = array('place' => 'No Hotel Required', 'category' => '-', 'nights' => $nights, 'options' => array(array('name' => 'No Room Provided', 'link' => '')), 'room_type' => '');
                $hotel_row_costs[$index] = 0;
                continue;
            }

            $row_cat = isset($stay_cats[$index]) && !empty($stay_cats[$index]) ? sanitize_text_field($stay_cats[$index]) : $hotel_cat;
            $raw_hotels = isset($stay_hotels[$index]) ? sanitize_text_field($stay_hotels[$index]) : '';
            $selected_hotel_array = array_map('trim', explode(',', $raw_hotels));
            $selected_hotel_array = array_filter($selected_hotel_array); 

            if(empty($selected_hotel_array) || in_array('None', $selected_hotel_array)) {
                $error_messages[] = "No Hotel selected for {$place}.";
                continue;
            }

            $requested_room_type = isset($stay_room_types[$index]) ? sanitize_text_field($stay_room_types[$index]) : 'Default';
            
            $highest_daily_cost = -1;
            $highest_hotel_rate = null;
            $final_room_name = 'Deluxe Room';
            $highest_daily_child_cost = 0; // ADDED

            // Loop through ALL selected hotels to find the most expensive one based on selected room category
            foreach ($selected_hotel_array as $h_name) {
                $temp_rate = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $row_cat, $h_name));
                
                if ($temp_rate) {
                    $r_price = floatval($temp_rate->room_price);
                    $eb_price = floatval($temp_rate->extra_bed_price);
                    $c_price = floatval($temp_rate->child_price);
                    $current_room_name = 'Deluxe Room';

                    // Apply custom room type pricing if requested and available in DB JSON
                    if ($requested_room_type !== 'Default' && !empty($temp_rate->room_types)) {
                        $rts = json_decode($temp_rate->room_types, true);
                        if (is_array($rts)) {
                            foreach($rts as $rt) {
                                if ($rt['name'] === $requested_room_type) {
                                    $r_price = floatval($rt['room']);
                                    $eb_price = floatval($rt['eb']);
                                    $c_price = floatval($rt['child']);
                                    $current_room_name = $rt['name'];
                                    break;
                                }
                            }
                        }
                    }

                    $temp_room_cost      = $no_of_rooms * $r_price;
                    $temp_extra_bed_cost = $extra_beds * $eb_price;
                    $temp_child_cost     = $child_6_12_pax * $c_price; 
                    
                    $temp_total_daily = $temp_room_cost + $temp_extra_bed_cost + $temp_child_cost;
                    
                    if ($temp_total_daily > $highest_daily_cost) {
                        $highest_daily_cost = $temp_total_daily;
                        $highest_hotel_rate = $temp_rate;
                        $final_room_name = $current_room_name;
                        $highest_daily_child_cost = $temp_child_cost; // ADDED
                    }
                }
            }

            if ( $highest_hotel_rate ) {
                $total_daily_hotel_cost = $highest_daily_cost;
                $row_base = $total_daily_hotel_cost * $nights;
                $row_final = $row_base * $surcharge_multiplier;
                
                $hotel_cost += $row_final; 
                $hotel_row_costs[$index] = $row_final;

                // ADDED
                $child_actual_hotel_total += ($highest_daily_child_cost * $nights * $surcharge_multiplier);

                $display_names = [];
                foreach($selected_hotel_array as $h_name) {
                    $hr = $wpdb->get_row( $wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $row_cat, $h_name));
                    if($hr) $display_names[] = array('name' => $hr->hotel_name, 'link' => $hr->hotel_website);
                }

                $detailed_stay_info[] = array('place' => $place, 'category' => $row_cat, 'nights' => $nights, 'options' => $display_names, 'room_type' => $final_room_name);
            } else {
                $error_messages[] = "Missing Rate ({$place} - {$row_cat} - " . implode(', ', $selected_hotel_array) . ").";
            }
        }
    }

    $addon_names = isset($_POST['addon_name']) ? $_POST['addon_name'] : array();
    $addon_prices = isset($_POST['addon_price']) ? $_POST['addon_price'] : array();
    $addon_types = isset($_POST['addon_type']) ? $_POST['addon_type'] : array();
    
    $total_addon_cost = 0;
    $valid_addons = array();
    $payable_pax = $total_pax + $child_6_12_pax;

    if(is_array($addon_names)) {
        for ($i = 0; $i < count($addon_names); $i++) {
            $name = trim(sanitize_text_field($addon_names[$i]));
            $price = isset($addon_prices[$i]) ? floatval($addon_prices[$i]) : 0;
            $type = isset($addon_types[$i]) ? sanitize_text_field($addon_types[$i]) : 'flat';
            
            if (!empty($name)) {
                if ($type === 'per_person') {
                    $total_addon_cost += ($price * $payable_pax);
                } else {
                    $total_addon_cost += $price;
                }
                $valid_addons[] = $name;
            }
        }
    }

    if (!empty($valid_addons)) {
        $addon_bullets = "<ul>";
        foreach($valid_addons as $va) {
            $addon_bullets .= "<li>" . esc_html($va) . "</li>";
        }
        $addon_bullets .= "</ul>";
        $dest_inclusions .= $addon_bullets;
    }

    if (!empty($error_messages)) { wp_send_json_error(array('errors' => implode(" <br> ", $error_messages))); wp_die(); }

    $actual_cost = $transport_cost + $hotel_cost + $total_addon_cost;
    
    // --- ADD THIS BLOCK FOR BREAKDOWN MATH ---
    $payable_pax = $total_pax + $child_6_12_pax;
    $transport_pp = $payable_pax > 0 ? ($transport_cost / $payable_pax) : 0;
    $addon_pp = $payable_pax > 0 ? ($total_addon_cost / $payable_pax) : 0;

    $child_actual_total = $child_actual_hotel_total + ($transport_pp * $child_6_12_pax) + ($addon_pp * $child_6_12_pax);
    $adult_actual_total = $actual_cost - $child_actual_total;

    $adult_ratio = $actual_cost > 0 ? ($adult_actual_total / $actual_cost) : ($total_pax > 0 ? 1 : 0);
    $child_ratio = $actual_cost > 0 ? ($child_actual_total / $actual_cost) : 0;
    // ------------------------------------------
    
    $override_profit = isset($_POST['override_profit']) && $_POST['override_profit'] !== '' ? floatval($_POST['override_profit']) : false;
    $manual_pp_override = isset($_POST['manual_pp_override']) && $_POST['manual_pp_override'] !== '' ? floatval($_POST['manual_pp_override']) : false;

    $d1_type = sanitize_text_field($_POST['discount_1_type']);
    $d1_val  = isset($_POST['discount_1_value']) ? floatval($_POST['discount_1_value']) : 0;
    $d2_type = sanitize_text_field($_POST['discount_2_type']);
    $d2_val  = isset($_POST['discount_2_value']) ? floatval($_POST['discount_2_value']) : 0;

    $gst_rate = $gst_pct / 100;
    $pt_rate = $pt_pct / 100;
    $pg_rate = $pg_pct / 100;
    $M = 1 + $gst_rate;

    if ($manual_pp_override !== false) {
        $target_grand_total = $manual_pp_override * $total_pax;
        $initial_base_price = $target_grand_total / $M;
    } else {
        if ($override_profit !== false) {
            $target_net_profit = $override_profit; 
            $denominator = 1 - ($M * $pt_rate) - ($M * $pg_rate);
            if ($denominator <= 0) $denominator = 0.01; 
            $initial_base_price = ($target_net_profit + $actual_cost) / $denominator;
        } else {
            if ($profit_type === 'percent') {
                $matched_percent = 0;
                foreach($profit_tiers as $tier) {
                    if ($total_pax >= $tier['min'] && $total_pax <= $tier['max']) {
                        $matched_percent = $tier['percent'];
                        break;
                    }
                }
                $profit_margin = $matched_percent / 100;
                
                $target_net_profit = $actual_cost * $profit_margin;
                
                $denominator = 1 - ($M * $pt_rate) - ($M * $pg_rate);
                if ($denominator <= 0) $denominator = 0.01; 
                $initial_base_price = ($target_net_profit + $actual_cost) / $denominator;
            } else {
                $target_net_profit = $profit_per_person * $total_pax; 
                $denominator = 1 - ($M * $pt_rate) - ($M * $pg_rate);
                if ($denominator <= 0) $denominator = 0.01; 
                $initial_base_price = ($target_net_profit + $actual_cost) / $denominator;
            }
        }
    }

    $d1_amt  = ($d1_type === 'flat') ? $d1_val : (($d1_type === 'percent') ? ($initial_base_price * ($d1_val / 100)) : 0);
    $d2_amt  = ($d2_type === 'flat') ? $d2_val : (($d2_type === 'percent') ? ($initial_base_price * ($d2_val / 100)) : 0);
    $total_discount_amount = $d1_amt + $d2_amt;
    
    $discounted_base_price = max(0, $initial_base_price - $total_discount_amount);
    
    $exact_grand_total = $discounted_base_price * $M;
    
    $grand_total = ceil($exact_grand_total / 100) * 100; 
    
    $discounted_base_price = $grand_total / $M;
    $gst = $grand_total - $discounted_base_price;
    
    // --- ADD THIS BLOCK ---
    $adult_base_total = $discounted_base_price * $adult_ratio;
    $child_base_total = $discounted_base_price * $child_ratio;
    $adult_base_pp = $total_pax > 0 ? ($adult_base_total / $total_pax) : 0;
    $child_base_pp = $child_6_12_pax > 0 ? ($child_base_total / $child_6_12_pax) : 0;

    $adult_grand_total = $grand_total * $adult_ratio;
    $child_grand_total = $grand_total * $child_ratio;
    $adult_gst_pp = $total_pax > 0 ? ($adult_grand_total / $total_pax) : 0;
    $child_gst_pp = $child_6_12_pax > 0 ? ($child_grand_total / $child_6_12_pax) : 0;
    // ----------------------
    
    $per_person_excl_gst = ($total_pax > 0) ? ($discounted_base_price / $total_pax) : 0;
    $per_person_inc_gst = ($total_pax > 0) ? ($grand_total / $total_pax) : 0;

    $prof_tax = $grand_total * $pt_rate;
    $pg_charge = $grand_total * $pg_rate;
    
    $gross_profit = $discounted_base_price - $actual_cost; 
    $net_profit = $gross_profit - $prof_tax - $pg_charge;

    $dynamic_route_arr = array();
    if(is_array($day_itinerary_routes)) {
        foreach($day_itinerary_routes as $r) {
            $r = trim(sanitize_text_field($r));
            if(!empty($r)) $dynamic_route_arr[] = $r; 
        }
    }
    $dynamic_route_string = !empty($dynamic_route_arr) ? implode(' → ', $dynamic_route_arr) : '';

    $summary_data = array(
        'client_name' => $client_name,
        'client_phone'=> $client_phone,
        'client_email'=> $client_email,
        'destination' => $destination,
        'start_date'  => $start_date,
        'end_date'    => $end_date,
        'pax'         => $total_pax,
        'child_6_12'  => $child_6_12_pax,
        'child'       => $child_pax,
        'days'        => $total_days,
        'rooms'       => $no_of_rooms,
        'extra_beds'  => $extra_beds,
        'transport_string' => $transport_summary_string,
        'corrected_trans_qtys' => $trans_qtys, 
        'pickup'      => $display_pickup,
        'drop'        => $display_drop,
        'hotel_cat'   => $hotel_cat,
        'dynamic_route' => $dynamic_route_string,
        'stays'       => $detailed_stay_info,
        'inclusions'  => $dest_inclusions,
        'exclusions'  => $dest_exclusions,
        'payment_terms' => $dest_payment_terms,
'important_note' => $important_note,
    'why_choose_us' => $why_choose_us,
    'essential_guidelines' => $essential_guidelines,
    'share_cab_cost' => $share_cab_cost, // <-- EXISTING LINE
    'hide_travelers_rooms' => $hide_travelers_rooms, // <-- ADD THIS
    'hide_pricing' => $hide_pricing, // <-- ADD THIS

        'actual_cost' => round($actual_cost, 2),
        'total_hotel_cost' => round($hotel_cost, 2),
        'total_trans_cost' => round($transport_cost, 2),
        'total_addon_cost' => round($total_addon_cost, 2),

        'final_profit' => round($gross_profit, 2), 
        'prof_tax'    => round($prof_tax, 2),
        'pg_charge'   => round($pg_charge, 2),
        'net_profit'  => round($net_profit, 2),
        
        'discount_amount' => round($total_discount_amount, 2),
        'total_base_price' => round($discounted_base_price, 2), 
        'gst_pct'     => $gst_pct,
        'pt_pct'      => $pt_pct,
        'pg_pct'      => $pg_pct,
        'per_person_excl_gst' => round($per_person_excl_gst, 2),
        'per_person_with_gst' => round($per_person_inc_gst, 2),
        
        // --- ADD THESE NEW LINES ---
        'adult_actual_pp' => $total_pax > 0 ? round($adult_actual_total / $total_pax, 2) : 0,
        'child_actual_pp' => $child_6_12_pax > 0 ? round($child_actual_total / $child_6_12_pax, 2) : 0,
        'adult_base_pp'   => round($adult_base_pp, 2),
        'child_base_pp'   => round($child_base_pp, 2),
        'adult_inc_gst_pp'=> round($adult_gst_pp, 2),
        'child_inc_gst_pp'=> round($child_gst_pp, 2),
        // ---------------------------
        
        'transport_row_costs' => $transport_row_costs,
        'hotel_row_costs' => $hotel_row_costs,
        'surcharge_applied' => $surcharge_percent,
        'itinerary'   => array_map('sanitize_text_field', is_array($day_itinerary) ? $day_itinerary : array()),
        'itinerary_desc'   => is_array($day_itinerary_desc) ? $day_itinerary_desc : array(),
        'itinerary_image'   => array_map('sanitize_url', is_array($day_itinerary_image) ? $day_itinerary_image : array()),
        'itinerary_stay_places' => array_map('sanitize_text_field', is_array($day_itinerary_stays) ? $day_itinerary_stays : array())
    );

    $raw_form = array(
        'transports' => $transports,
        'trans_qtys' => $trans_qtys,
        'trans_days' => $trans_days,
        'trans_pickups' => $trans_pickups, 
        'trans_custom_rates' => $trans_custom_rates, 
        'trans_custom_totals' => $trans_custom_totals,
        'addon_names' => $addon_names,
        'addon_prices' => $addon_prices,
        'addon_types' => $addon_types,
        'stay_places' => $stay_places,
        'stay_hotels' => $stay_hotels,
        'stay_nights' => $stay_nights,
        'stay_categories' => $stay_cats, 
        'stay_room_type' => isset($_POST['stay_room_type']) && is_array($_POST['stay_room_type']) ? array_map('sanitize_text_field', wp_unslash($_POST['stay_room_type'])) : array(),
        'override_profit' => isset($_POST['override_profit']) ? $_POST['override_profit'] : '',
        'manual_pp_override' => isset($_POST['manual_pp_override']) ? $_POST['manual_pp_override'] : '',
        'd1_type' => $d1_type,
        'd1_val' => $d1_val,
'd2_type' => $d2_type,
    'd2_val' => $d2_val,
    'share_cab_cost' => $share_cab_cost, // <-- EXISTING LINE
    'hide_travelers_rooms' => $hide_travelers_rooms, // <-- ADD THIS
    'hide_pricing' => $hide_pricing, // <-- ADD THIS
        'pickup_custom' => $pickup_custom,
        'drop_custom' => $drop_custom,
        'quote_inclusions' => isset($_POST['quote_inclusions']) ? wp_unslash($_POST['quote_inclusions']) : '',
        'quote_exclusions' => isset($_POST['quote_exclusions']) ? wp_unslash($_POST['quote_exclusions']) : '',
        'quote_payment_terms' => isset($_POST['quote_payment_terms']) ? wp_unslash($_POST['quote_payment_terms']) : '',
        'quote_important_note' => isset($_POST['quote_important_note']) ? wp_unslash($_POST['quote_important_note']) : '',
        'quote_why_choose_us' => isset($_POST['quote_why_choose_us']) ? wp_unslash($_POST['quote_why_choose_us']) : '',
        'quote_essential_guidelines' => isset($_POST['quote_essential_guidelines']) ? wp_unslash($_POST['quote_essential_guidelines']) : '',
        'itinerary_desc' => is_array($day_itinerary_desc) ? $day_itinerary_desc : array(),
        'itinerary_image' => is_array($day_itinerary_image) ? $day_itinerary_image : array(),
        'itinerary_stay_place' => is_array($day_itinerary_stays) ? $day_itinerary_stays : array(),
        'itinerary_route' => is_array($day_itinerary_routes) ? $day_itinerary_routes : array(),
    );

    $is_final = isset($_POST['generate_link']) ? 1 : 0;
    $permalink = '';
    
    if ($is_final) {
        $post_content_json = wp_json_encode(array(
            'per_person' => round($per_person_excl_gst, 2), 
            'per_person_with_gst' => round($per_person_inc_gst, 2),
            'gst' => round($gst, 2),
            'grand_total' => round($grand_total, 2),
            'summary' => $summary_data,
            'raw' => $raw_form
        ));

        if($edit_quote_id > 0) {
            $post = get_post($edit_quote_id);
            $post_title = !empty($client_name) ? "{$client_name} - {$destination} (" . strtoupper($post->post_name) . ")" : 'Quote ' . strtoupper($post->post_name);
            
            wp_update_post(array(
                'ID' => $edit_quote_id,
                'post_title' => $post_title,
                'post_content' => ''
            ));
            
            update_post_meta($edit_quote_id, '_tcc_quote_json_data', wp_slash($post_content_json));
            $permalink     = get_permalink($edit_quote_id);
            $final_post_id = $edit_quote_id;
        } else {
            $random_string = wp_generate_password(8, false); 
            $post_title = !empty($client_name) ? "{$client_name} - {$destination} (" . strtoupper($random_string) . ")" : 'Quote ' . strtoupper($random_string);

            $post_id = wp_insert_post(array(
                'post_type' => 'tcc_quote',
                'post_name' => $random_string,
                'post_title' => $post_title,
                'post_content' => '', 
                'post_status' => 'publish'
            ));
            
            update_post_meta($post_id, '_tcc_quote_json_data', wp_slash($post_content_json));
            $permalink     = get_permalink($post_id);
            $final_post_id = $post_id;
        }
    }

    wp_send_json_success(array(
        'per_person'         => round($per_person_excl_gst, 2),
        'per_person_with_gst'=> round($per_person_inc_gst, 2),
        'gst'                => round($gst, 2),
        'grand_total'        => round($grand_total, 2),
        'summary_data'       => $summary_data,
        'permalink'          => $permalink,
        'post_id'            => isset($final_post_id) ? intval($final_post_id) : 0,
    ));
}

function tcc_duplicate_quote() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    if(!$quote_id) wp_send_json_error('Invalid Quote ID');

    $post = get_post($quote_id);
    if(!$post) wp_send_json_error('Quote not found');

    $data = tcc_get_quote_json_data($quote_id);
    
    $random_string = wp_generate_password(8, false); 
    $client_name = isset($data['summary']['client_name']) ? $data['summary']['client_name'] : '';
    $dest = isset($data['summary']['destination']) ? $data['summary']['destination'] : '';
    
    $post_title = !empty($client_name) ? "{$client_name} - {$dest} (" . strtoupper($random_string) . ")" : 'Quote ' . strtoupper($random_string);

    $new_post_id = wp_insert_post(array(
        'post_type' => 'tcc_quote',
        'post_name' => $random_string,
        'post_title' => $post_title,
        'post_content' => '', 
        'post_status' => 'publish'
    ));

    if(is_wp_error($new_post_id)) {
        wp_send_json_error('Failed to duplicate.');
    }

    update_post_meta($new_post_id, '_tcc_quote_json_data', wp_slash(wp_json_encode($data)));

    wp_send_json_success(array(
        'new_id' => $new_post_id,
        'message' => 'Quote Option Duplicated successfully! It has now been loaded into the Calculator.'
    ));
}

function tcc_rename_master_element() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;
    
    $type = sanitize_text_field($_POST['element_type']);
    $dest = trim(sanitize_text_field($_POST['target_dest']));
    $old_name = trim(wp_unslash($_POST['old_name']));
    $new_name = trim(wp_unslash($_POST['new_name']));

    if(empty($old_name) || empty($new_name)) { wp_send_json_error(array('message' => 'Invalid names.')); wp_die(); }

    $master_data = get_option('tcc_master_settings', array());
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_trans = $wpdb->prefix . 'tcc_transport_rates';

    if($type === 'destination') {
        if(isset($master_data[$old_name])) {
            $master_data[$new_name] = $master_data[$old_name];
            unset($master_data[$old_name]);
            update_option('tcc_master_settings', $master_data);
            $wpdb->update($table_hotels, array('destination' => $new_name), array('destination' => $old_name));
            $wpdb->update($table_trans, array('destination' => $new_name), array('destination' => $old_name));
        } else { wp_send_json_error(array('message' => 'Destination not found.')); wp_die(); }
    } elseif($type === 'stay_place') {
        if(isset($master_data[$dest])) {
            $idx = array_search($old_name, $master_data[$dest]['stay_places']);
            if($idx !== false) $master_data[$dest]['stay_places'][$idx] = $new_name;
            update_option('tcc_master_settings', $master_data);
            $wpdb->update($table_hotels, array('night_stay_place' => $new_name), array('destination' => $dest, 'night_stay_place' => $old_name));
        }
    } elseif($type === 'hotel_cat') {
        if(isset($master_data[$dest])) {
            $idx = array_search($old_name, $master_data[$dest]['hotel_categories']);
            if($idx !== false) $master_data[$dest]['hotel_categories'][$idx] = $new_name;
            update_option('tcc_master_settings', $master_data);
            $wpdb->update($table_hotels, array('hotel_category' => $new_name), array('destination' => $dest, 'hotel_category' => $old_name));
        }
    } elseif($type === 'pickup') {
        if(isset($master_data[$dest])) {
            $idx = array_search($old_name, $master_data[$dest]['pickups']);
            if($idx !== false) $master_data[$dest]['pickups'][$idx] = $new_name;
            update_option('tcc_master_settings', $master_data);
            $wpdb->update($table_trans, array('pickup_location' => $new_name), array('destination' => $dest, 'pickup_location' => $old_name));
        }
    } elseif($type === 'vehicle') {
        if(isset($master_data[$dest])) {
            $idx = array_search($old_name, $master_data[$dest]['vehicles']);
            if($idx !== false) $master_data[$dest]['vehicles'][$idx] = $new_name;
            update_option('tcc_master_settings', $master_data);
            $wpdb->update($table_trans, array('vehicle_type' => $new_name), array('destination' => $dest, 'vehicle_type' => $old_name));
        }
    }
    wp_send_json_success(array('message' => 'Renamed successfully!', 'new_master' => $master_data));
}

function tcc_save_global_settings() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $gst = floatval($_POST['global_gst']);
    $pt = floatval($_POST['global_pt']);
    $pg = floatval($_POST['global_pg']);
    $banner = isset($_POST['global_banner']) ? esc_url_raw($_POST['global_banner']) : '';

    update_option('tcc_global_settings', array(
        'gst' => $gst,
        'pt' => $pt,
        'pg' => $pg,
        'company_banner' => $banner
    ));
    wp_send_json_success(array('message' => 'Global Settings & Taxes Saved!'));
}

function tcc_get_full_quote_data() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    if(!$quote_id) wp_send_json_error();
    
    $post = get_post($quote_id);
    if(!$post) wp_send_json_error();
    
    $data = tcc_get_quote_json_data($quote_id);
    wp_send_json_success($data);
}

function tcc_fetch_hotel_names() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    tcc_ensure_room_types_column(); // Ensure schema is up-to-date
    
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_hotel_rates';
    $dest  = trim(sanitize_text_field($_POST['dest']));
    $place = trim(sanitize_text_field($_POST['place']));
    $cat   = trim(sanitize_text_field($_POST['cat']));

    // 1. Try to fetch hotels matching the requested category
    $results = $wpdb->get_results( $wpdb->prepare("SELECT hotel_name, hotel_website, hotel_category, room_types FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s GROUP BY hotel_name", $dest, $place, $cat) );
    
    $is_settings = isset($_POST['is_settings']) ? intval($_POST['is_settings']) : 0;

    // 2. FALLBACK LOGIC: Only run if NOT in the Settings Dashboard
    if (empty($results) && !$is_settings) {
        $fallback_cat_row = $wpdb->get_row( $wpdb->prepare("SELECT hotel_category FROM $table WHERE destination = %s AND night_stay_place = %s LIMIT 1", $dest, $place) );
        
        if ($fallback_cat_row) {
            $fallback_cat = $fallback_cat_row->hotel_category;
            // Fetch hotels for this new fallback category instead
            $results = $wpdb->get_results( $wpdb->prepare("SELECT hotel_name, hotel_website, hotel_category, room_types FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s GROUP BY hotel_name", $dest, $place, $fallback_cat) );
        }
    }

    wp_send_json_success($results);
}

function tcc_fetch_hotel_rate() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    tcc_ensure_room_types_column(); // Ensure schema is up-to-date
    
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_hotel_rates';
    $dest  = trim(sanitize_text_field($_POST['destination']));
    $place = trim(sanitize_text_field($_POST['place']));
    $cat   = trim(sanitize_text_field($_POST['category']));
    $name  = trim(sanitize_text_field($_POST['hotel_name']));
    
    $existing = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $dest, $place, $cat, $name));
    if($existing) wp_send_json_success($existing); else wp_send_json_error();
}

function tcc_fetch_transport_rate() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_transport_rates';
    $dest   = trim(sanitize_text_field($_POST['destination']));
    $pickup = trim(sanitize_text_field($_POST['pickup']));
    $vehicle= trim(sanitize_text_field($_POST['vehicle']));
    $existing = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE destination = %s AND pickup_location = %s AND vehicle_type = %s LIMIT 1", $dest, $pickup, $vehicle));
    if($existing) wp_send_json_success($existing); else wp_send_json_error();
}

function tcc_delete_hotel_rate() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $destination      = trim(sanitize_text_field($_POST['set_destination']));
    $night_stay_place = trim(sanitize_text_field($_POST['set_night_stay_place']));
    $hotel_cat        = trim(sanitize_text_field($_POST['set_hotel_cat']));
    $hotel_name       = trim(sanitize_text_field($_POST['set_hotel_name']));

    if($destination && $night_stay_place && $hotel_cat && $hotel_name) {
        $wpdb->delete($table_hotels, array(
            'destination' => $destination,
            'night_stay_place' => $night_stay_place,
            'hotel_category' => $hotel_cat,
            'hotel_name' => $hotel_name
        ));
        wp_send_json_success(array('message' => 'Hotel deleted successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Missing data.'));
    }
}

function tcc_delete_transport_rate() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;
    $table_trans = $wpdb->prefix . 'tcc_transport_rates';
    $destination = trim(sanitize_text_field($_POST['set_destination']));
    $pickup_loc  = trim(sanitize_text_field($_POST['set_pickup_loc']));
    $vehicle     = trim(sanitize_text_field($_POST['set_vehicle']));

    if($destination && $pickup_loc && $vehicle) {
        $wpdb->delete($table_trans, array(
            'destination' => $destination,
            'pickup_location' => $pickup_loc,
            'vehicle_type' => $vehicle
        ));
        wp_send_json_success(array('message' => 'Transport rate deleted.'));
    } else {
        wp_send_json_error(array('message' => 'Missing data.'));
    }
}

function tcc_save_master_settings() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $dest_name = trim(sanitize_text_field($_POST['master_dest_name']));
    $profit_type = isset($_POST['master_profit_type']) ? sanitize_text_field($_POST['master_profit_type']) : 'flat';
    $profit = floatval($_POST['master_profit']);
    
    $tier_mins = isset($_POST['tier_min_pax']) ? $_POST['tier_min_pax'] : [];
    $tier_maxs = isset($_POST['tier_max_pax']) ? $_POST['tier_max_pax'] : [];
    $tier_percents = isset($_POST['tier_percent']) ? $_POST['tier_percent'] : [];
    $profit_tiers = [];
    for($i=0; $i<count($tier_mins); $i++) {
        if(!empty($tier_mins[$i]) && !empty($tier_maxs[$i])) {
            $profit_tiers[] = array(
                'min' => intval($tier_mins[$i]),
                'max' => intval($tier_maxs[$i]),
                'percent' => floatval($tier_percents[$i])
            );
        }
    }

    $pickups = explode(',', sanitize_text_field($_POST['master_pickups']));
    $daily_routes = isset($_POST['master_daily_routes']) ? explode(',', sanitize_text_field($_POST['master_daily_routes'])) : array();
    $stays = explode(',', sanitize_text_field($_POST['master_stays']));
    $vehicles = explode(',', sanitize_text_field($_POST['master_vehicles']));
    $cats = explode(',', sanitize_text_field($_POST['master_hotel_cats']));

    $inclusions = wp_unslash($_POST['master_inclusions']);
    $exclusions = wp_unslash($_POST['master_exclusions']);
    $payment_terms = wp_unslash($_POST['master_payment_terms']);
    
    $important_note = isset($_POST['master_important_note']) ? wp_unslash($_POST['master_important_note']) : '';
    $why_choose_us = isset($_POST['master_why_choose_us']) ? wp_unslash($_POST['master_why_choose_us']) : '';
    $essential_guidelines = isset($_POST['master_essential_guidelines']) ? wp_unslash($_POST['master_essential_guidelines']) : '';
    $share_cab_cost = isset( $_POST['share_cab_cost'] ) ? 1 : 0; // [PATCH-C2] Variable was undefined — caused silent null on every Destination Setup save

    $season_starts = isset($_POST['season_start']) ? $_POST['season_start'] : [];
    $season_ends   = isset($_POST['season_end']) ? $_POST['season_end'] : [];
    $season_percents = isset($_POST['season_percent']) ? $_POST['season_percent'] : [];
    $seasons = [];
    for($i=0; $i<count($season_starts); $i++) {
        if(!empty($season_starts[$i]) && !empty($season_ends[$i])) {
            $seasons[] = array(
                'start' => sanitize_text_field($season_starts[$i]),
                'end' => sanitize_text_field($season_ends[$i]),
                'percent' => floatval($season_percents[$i])
            );
        }
    }

    $master_data = get_option('tcc_master_settings', array());
    $master_data[$dest_name] = array(
        'profit_type' => $profit_type,
        'profit_per_person' => $profit,
        'profit_tiers' => $profit_tiers,
        'pickups' => array_map('trim', $pickups),
        'daily_routes' => array_map('trim', $daily_routes),
        'stay_places' => array_map('trim', $stays),
        'vehicles' => array_map('trim', $vehicles),
        'hotel_categories' => array_map('trim', $cats),
        'inclusions' => $inclusions,
        'exclusions' => $exclusions,
        'payment_terms' => $payment_terms,
        'important_note' => $important_note,
        'why_choose_us' => $why_choose_us,
        'essential_guidelines' => $essential_guidelines,
        'share_cab_cost' => $share_cab_cost, // <-- ADD THIS
        'seasons' => $seasons
    );
    update_option('tcc_master_settings', $master_data);
    wp_send_json_success(array('message' => 'Saved! Dropdowns Updated.', 'new_master' => $master_data));
}

function tcc_save_pricing_settings() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    
    global $wpdb;
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    
    // Ensure the new column exists for existing users
    tcc_ensure_room_types_column();

    $destination      = trim(sanitize_text_field($_POST['set_destination']));
    $night_stay_place = trim(sanitize_text_field($_POST['set_night_stay_place']));
    $hotel_cat        = trim(sanitize_text_field($_POST['set_hotel_cat']));
    $hotel_name       = trim(sanitize_text_field($_POST['set_hotel_name']));
    $hotel_website    = esc_url_raw($_POST['set_hotel_website']);
    $room_price       = floatval($_POST['set_room_price']);
    $extra_bed        = floatval($_POST['set_extra_bed']);
    $child_price      = floatval($_POST['set_child_price']);

    // Capture Multiple Room Types
    $rt_names = isset($_POST['rt_name']) ? $_POST['rt_name'] : [];
    $rt_rooms = isset($_POST['rt_room']) ? $_POST['rt_room'] : [];
    $rt_ebs = isset($_POST['rt_eb']) ? $_POST['rt_eb'] : [];
    $rt_childs = isset($_POST['rt_child']) ? $_POST['rt_child'] : [];
    $room_types_arr = [];
    for($i=0; $i<count($rt_names); $i++) {
        if(!empty(trim($rt_names[$i]))) {
            $room_types_arr[] = array(
                'name' => sanitize_text_field(trim($rt_names[$i])),
                'room' => floatval($rt_rooms[$i]),
                'eb' => floatval($rt_ebs[$i]),
                'child' => floatval($rt_childs[$i])
            );
        }
    }
    $room_types_json = wp_json_encode($room_types_arr);

    $existing = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s", $destination, $night_stay_place, $hotel_cat, $hotel_name));

    if ( $existing ) {
        $wpdb->update($table_hotels, array('hotel_website' => $hotel_website, 'room_price' => $room_price, 'extra_bed_price' => $extra_bed, 'child_price' => $child_price, 'room_types' => $room_types_json), array( 'id' => $existing->id ));
    } else {
        $wpdb->insert($table_hotels, array('destination' => $destination, 'night_stay_place' => $night_stay_place, 'hotel_category' => $hotel_cat, 'hotel_name' => $hotel_name, 'hotel_website' => $hotel_website, 'room_price' => $room_price, 'extra_bed_price' => $extra_bed, 'child_price' => $child_price, 'room_types' => $room_types_json));
    }
    wp_send_json_success(array('message' => 'Hotel pricing saved.'));
}

function tcc_save_transport_settings() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    global $wpdb;
    $table_transport = $wpdb->prefix . 'tcc_transport_rates';
    $destination = trim(sanitize_text_field($_POST['set_destination']));
    $pickup_loc  = trim(sanitize_text_field($_POST['set_pickup_loc']));
    $vehicle     = trim(sanitize_text_field($_POST['set_vehicle']));
    $capacity    = intval($_POST['set_capacity']);
    $price       = floatval($_POST['set_transport_price']);

    $existing = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_transport WHERE destination = %s AND pickup_location = %s AND vehicle_type = %s", $destination, $pickup_loc, $vehicle));

    if ( $existing ) {
        $wpdb->update($table_transport, array( 'capacity' => $capacity, 'price_per_day' => $price ), array( 'id' => $existing->id ));
    } else {
        $wpdb->insert($table_transport, array('destination' => $destination, 'pickup_location' => $pickup_loc, 'vehicle_type' => $vehicle, 'capacity' => $capacity, 'price_per_day' => $price));
    }
    wp_send_json_success(array('message' => 'Transport pricing saved.'));
}

function tcc_save_itinerary_preset() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    $destination = trim(sanitize_text_field($_POST['destination']));
    
    $raw_days = isset($_POST['itinerary_day']) ? $_POST['itinerary_day'] : array();
    $days = is_array($raw_days) ? array_map('sanitize_text_field', wp_unslash($raw_days)) : array();

    $raw_stays = isset($_POST['itinerary_stay_place']) ? $_POST['itinerary_stay_place'] : array();
    $stays = is_array($raw_stays) ? array_map('sanitize_text_field', wp_unslash($raw_stays)) : array();

    $raw_routes = isset($_POST['itinerary_route']) ? $_POST['itinerary_route'] : array();
    $routes = is_array($raw_routes) ? array_map('sanitize_text_field', wp_unslash($raw_routes)) : array();

    $raw_desc = isset($_POST['itinerary_desc']) ? $_POST['itinerary_desc'] : array();
    $desc = is_array($raw_desc) ? array_map('wp_unslash', $raw_desc) : array();

    $raw_image = isset($_POST['itinerary_image']) ? $_POST['itinerary_image'] : array();
    $image = is_array($raw_image) ? array_map('sanitize_url', wp_unslash($raw_image)) : array();

    // --- CAPTURE SPECIFIC HOTEL SELECTIONS ---
    $routing_stay_places  = isset($_POST['stay_place']) ? array_map('sanitize_text_field', wp_unslash($_POST['stay_place'])) : array();
    $routing_stay_categories = isset($_POST['stay_category']) ? array_map('sanitize_text_field', wp_unslash($_POST['stay_category'])) : array();
    $routing_stay_hotels  = isset($_POST['stay_hotel']) ? array_map('sanitize_text_field', wp_unslash($_POST['stay_hotel'])) : array();
    $routing_stay_nights  = isset($_POST['stay_nights']) ? array_map('sanitize_text_field', wp_unslash($_POST['stay_nights'])) : array();
    $routing_stay_room_types = isset($_POST['stay_room_type']) ? array_map('sanitize_text_field', wp_unslash($_POST['stay_room_type'])) : array();

    $pickup = isset($_POST['pickup_location']) ? sanitize_text_field($_POST['pickup_location']) : '';
    $pickup_custom = isset($_POST['pickup_custom']) ? sanitize_text_field($_POST['pickup_custom']) : '';
    $drop = isset($_POST['drop_location']) ? sanitize_text_field($_POST['drop_location']) : '';
    $drop_custom = isset($_POST['drop_custom']) ? sanitize_text_field($_POST['drop_custom']) : '';

    $hotel_category = isset($_POST['hotel_category']) ? sanitize_text_field($_POST['hotel_category']) : '';

    $transports = isset($_POST['transportation']) ? array_map('sanitize_text_field', wp_unslash($_POST['transportation'])) : array();
    $trans_qtys = isset($_POST['transport_qty']) ? array_map('sanitize_text_field', wp_unslash($_POST['transport_qty'])) : array();
    $trans_days = isset($_POST['transport_days']) ? array_map('sanitize_text_field', wp_unslash($_POST['transport_days'])) : array();
    $trans_pickups = isset($_POST['transport_pickup']) ? array_map('sanitize_text_field', wp_unslash($_POST['transport_pickup'])) : array();
    $trans_rates = isset($_POST['transport_custom_rate']) ? array_map('sanitize_text_field', wp_unslash($_POST['transport_custom_rate'])) : array();
    $trans_totals = isset($_POST['transport_custom_total']) ? array_map('sanitize_text_field', wp_unslash($_POST['transport_custom_total'])) : array();

    $addon_names = isset($_POST['addon_name']) ? array_map('sanitize_text_field', wp_unslash($_POST['addon_name'])) : array();
    $addon_prices = isset($_POST['addon_price']) ? array_map('sanitize_text_field', wp_unslash($_POST['addon_price'])) : array();
    $addon_types = isset($_POST['addon_type']) ? array_map('sanitize_text_field', wp_unslash($_POST['addon_type'])) : array();

    if(empty($preset_name) || empty($days)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_itinerary_presets', array());
    if(!isset($presets[$destination])) $presets[$destination] = array();
    
    $presets[$destination][$preset_name] = array(
        'itinerary' => $days,
        'itinerary_desc' => $desc,
        'itinerary_image' => $image,
        'stay_places' => $stays,
        'itinerary_routes' => $routes,
        'routing_stay_places' => $routing_stay_places,
        'routing_stay_categories' => $routing_stay_categories,
        'routing_stay_hotels' => $routing_stay_hotels,
        'routing_stay_nights' => $routing_stay_nights,
        'routing_stay_room_types' => $routing_stay_room_types,
        'pickup' => $pickup,
        'pickup_custom' => $pickup_custom,
        'drop' => $drop,
        'drop_custom' => $drop_custom,
        'hotel_category' => $hotel_category,
        'transports' => $transports,
        'trans_qtys' => $trans_qtys,
        'trans_days' => $trans_days,
        'trans_pickups' => $trans_pickups,
        'trans_rates' => $trans_rates,
        'trans_totals' => $trans_totals,
        'addon_names' => $addon_names,
        'addon_prices' => $addon_prices,
        'addon_types' => $addon_types
    );
    update_option('tcc_itinerary_presets', $presets);
    
    wp_send_json_success("Saved Successfully");
}
function tcc_load_itinerary_presets() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $destination = trim(sanitize_text_field($_POST['destination']));
    $presets = get_option('tcc_itinerary_presets', array());
    if(isset($presets[$destination])) wp_send_json_success($presets[$destination]);
    else wp_send_json_success(array());
}

function tcc_delete_itinerary_preset() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    $destination = trim(sanitize_text_field($_POST['destination']));
    if(empty($preset_name) || empty($destination)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_itinerary_presets', array());
    if(isset($presets[$destination]) && isset($presets[$destination][$preset_name])) {
        unset($presets[$destination][$preset_name]);
        update_option('tcc_itinerary_presets', $presets);
        wp_send_json_success("Deleted Successfully");
    } else {
        wp_send_json_error("Preset not found");
    }
}

function tcc_load_quotes_list() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    
    $args = array(
        'post_type' => 'tcc_quote',
        'posts_per_page' => -1, 
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );
    $quotes = get_posts($args);
    $result = array();
    foreach($quotes as $q) {
        $content = tcc_get_quote_json_data($q->ID);
        $c_name = isset($content['summary']['client_name']) ? $content['summary']['client_name'] : '';
        $c_phone = isset($content['summary']['client_phone']) ? $content['summary']['client_phone'] : '';
        $c_email = isset($content['summary']['client_email']) ? $content['summary']['client_email'] : '';
        
        $result[] = array( 
            'id' => $q->ID, 
            'title' => $q->post_title, 
            'date' => get_the_date('d M Y', $q->ID),
            'c_name' => $c_name,
            'c_phone' => $c_phone,
            'c_email' => $c_email,
            'link' => get_permalink($q->ID)
        );
    }
    wp_send_json_success($result);
}

function tcc_load_quote_payments() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    if(!$post_id) wp_send_json_error();

    $post = get_post($post_id);
    $data = tcc_get_quote_json_data($post_id);
    $grand_total = isset($data['grand_total']) ? floatval($data['grand_total']) : 0;
    
    $net_profit = isset($data['summary']['net_profit']) ? floatval($data['summary']['net_profit']) : 0;
    
    $post_discount = floatval(get_post_meta($post_id, 'tcc_post_quote_discount', true));
    
    $gst_pct = isset($data['summary']['gst_pct']) ? floatval($data['summary']['gst_pct']) / 100 : 0.05;
    $pt_pct = isset($data['summary']['pt_pct']) ? floatval($data['summary']['pt_pct']) / 100 : 0;
    $pg_pct = isset($data['summary']['pg_pct']) ? floatval($data['summary']['pg_pct']) / 100 : 0;
    
    $payments = get_post_meta($post_id, 'tcc_payments', true);
    if(!is_array($payments)) $payments = [];
    
    $status = get_post_meta($post_id, 'tcc_lead_status', true);

    $total_paid = 0; 
    $vendor_paid = 0;
    $tax_waiver = 0;
    $total_refunded = 0;
    $total_actual_pg = 0; 
    $has_refund = false;

    foreach($payments as &$p) { 
        $pg_fee = isset($p['pg_fee']) ? floatval($p['pg_fee']) : 0;
        $amt = floatval($p['amount']);
        
        if (isset($p['method']) && $p['method'] === 'Refund') {
            $total_refunded += $amt;
            $has_refund = true;
        } elseif (isset($p['method']) && $p['method'] === 'Direct to Vendor') {
            $vendor_paid += $amt;
            $waiver = ($amt * $gst_pct) + ($amt * (1 + $gst_pct) * ($pt_pct + $pg_pct));
            $tax_waiver += $waiver;
            $p['waiver_calculated'] = $waiver; 
        } else {
            $total_paid += $amt; 
            $total_actual_pg += $pg_fee; 
        }
    }
    
    $is_cancelled = ($has_refund || $status === 'Canceled');
    $retained_income = max(0, $total_paid - $total_refunded);
    
    $balance = $is_cancelled ? 0 : max(0, $grand_total - $post_discount - $total_paid - $vendor_paid - $tax_waiver);
    $net_in_bank = $total_paid - $total_actual_pg; 

    $vendor_direct_balance = $is_cancelled ? 0 : max(0, $balance / ( (1 + $gst_pct) * (1 + $pt_pct + $pg_pct) ));

    wp_send_json_success(array(
        'grand_total' => $grand_total,
        'net_profit' => round($net_profit, 2),
        'post_discount' => $post_discount, 
        'total_paid' => $total_paid,
        'vendor_paid' => $vendor_paid,
        'tax_waiver' => $tax_waiver,
        'total_actual_pg' => $total_actual_pg,
        'net_in_bank' => $net_in_bank,
        'total_refunded' => $total_refunded,
        'retained_income' => $retained_income,
        'balance' => $balance,
        'vendor_direct_balance' => round($vendor_direct_balance, 2), 
        'is_cancelled' => $is_cancelled,
        'payments' => $payments
    ));
}

function tcc_add_payment() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    $pmt_id = isset($_POST['pmt_id']) ? sanitize_text_field($_POST['pmt_id']) : '';
    
    $amount = floatval($_POST['amount']);
    $pg_fee = isset($_POST['pg_fee']) ? floatval($_POST['pg_fee']) : 0;
    $date = sanitize_text_field($_POST['date']);
    $method = sanitize_text_field($_POST['method']);
    $ref = sanitize_text_field($_POST['ref']);

    if(!$post_id || empty($date)) wp_send_json_error("Invalid data");

    $payments = get_post_meta($post_id, 'tcc_payments', true);
    if(!is_array($payments)) $payments = [];

    if ($pmt_id) {
        foreach($payments as &$p) {
            if($p['id'] === $pmt_id) {
                $p['amount'] = $amount;
                $p['pg_fee'] = $pg_fee;
                $p['date'] = $date;
                $p['method'] = $method;
                $p['ref'] = $ref;
                break;
            }
        }
    } else {
        $payments[] = array(
            'id' => uniqid('pmt_'),
            'amount' => $amount,
            'pg_fee' => $pg_fee, 
            'date' => $date,
            'method' => $method,
            'ref' => $ref
        );
    }
    
    update_post_meta($post_id, 'tcc_payments', $payments);
    
    // --- 🚨 CRITICAL UPDATE: Recalculate Total Received so Seats Auto-Update! ---
    $total_paid = 0;
    $has_refund = false;
    
    foreach($payments as $p) {
        if (isset($p['method'])) {
            if ($p['method'] === 'Refund') {
                $has_refund = true;
            } elseif ($p['method'] !== 'Direct to Vendor') {
                $total_paid += floatval($p['amount']);
            }
        }
    }
    
    update_post_meta($post_id, '_tcc_total_received', $total_paid);
    
    if ($has_refund) {
        update_post_meta($post_id, 'tcc_lead_status', 'Canceled');
        update_post_meta($post_id, '_tcc_quote_status', 'Cancelled');
    } else {
        // If money is received, mark Confirmed so seats get blocked!
        if ($total_paid > 0) {
            update_post_meta($post_id, 'tcc_lead_status', 'Converted');
            update_post_meta($post_id, '_tcc_quote_status', 'Confirmed');
        } else {
            update_post_meta($post_id, '_tcc_quote_status', 'Pending');
        }
    }
    // ------------------------------------------------------------------------------

    wp_send_json_success();
}

function tcc_delete_payment() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    $pmt_id = sanitize_text_field($_POST['pmt_id']);

    if(!$post_id || empty($pmt_id)) wp_send_json_error();

    $payments = get_post_meta($post_id, 'tcc_payments', true);
    if(!is_array($payments)) wp_send_json_error();

    $new_payments = array();
    foreach($payments as $p) {
        if($p['id'] !== $pmt_id) $new_payments[] = $p;
    }
    
    update_post_meta($post_id, 'tcc_payments', $new_payments);
    
    // --- 🚨 CRITICAL UPDATE: Recalculate Total Received so Seats Auto-Update! ---
    $total_paid = 0;
    $has_refund = false;
    
    foreach($new_payments as $p) {
        if (isset($p['method'])) {
            if ($p['method'] === 'Refund') {
                $has_refund = true;
            } elseif ($p['method'] !== 'Direct to Vendor') {
                $total_paid += floatval($p['amount']);
            }
        }
    }
    
    update_post_meta($post_id, '_tcc_total_received', $total_paid);
    
    if ($has_refund) {
        update_post_meta($post_id, 'tcc_lead_status', 'Canceled');
        update_post_meta($post_id, '_tcc_quote_status', 'Cancelled');
    } else {
        if ($total_paid > 0) {
            update_post_meta($post_id, 'tcc_lead_status', 'Converted');
            update_post_meta($post_id, '_tcc_quote_status', 'Confirmed');
        } else {
            update_post_meta($post_id, 'tcc_lead_status', 'Open');
            update_post_meta($post_id, '_tcc_quote_status', 'Pending');
        }
    }
    // ------------------------------------------------------------------------------
    
    wp_send_json_success();
}

function tcc_save_post_discount() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    $discount = floatval($_POST['discount']);
    if($quote_id) {
        update_post_meta($quote_id, 'tcc_post_quote_discount', $discount);
        wp_send_json_success();
    }
    wp_send_json_error();
}

function tcc_delete_quote() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    if($post_id) {
        wp_delete_post($post_id, true);
        wp_send_json_success();
    }
    wp_send_json_error();
}

function tcc_update_quote_client() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    
    $post_id = intval($_POST['quote_id']);
    $name = trim(sanitize_text_field($_POST['c_name']));
    $phone = trim(sanitize_text_field($_POST['c_phone']));
    $email = trim(sanitize_email($_POST['c_email']));
    
    if(!$post_id) wp_send_json_error("Invalid Quote ID");

    $post = get_post($post_id);
    if(!$post) wp_send_json_error("Quote not found");

    $data = tcc_get_quote_json_data($post_id);
    $data['summary']['client_name'] = $name;
    $data['summary']['client_phone'] = $phone;
    $data['summary']['client_email'] = $email;

    $post_title = !empty($name) ? "{$name} - {$data['summary']['destination']} (" . strtoupper($post->post_name) . ")" : 'Quote ' . strtoupper($post->post_name);

    wp_update_post(array(
        'ID' => $post_id,
        'post_title' => $post_title
    ));
    
    update_post_meta($post_id, '_tcc_quote_json_data', wp_slash(wp_json_encode($data)));

    wp_send_json_success();
}

// ---------------------------------------------------------------------------
// Helper: collect ALL tcc_ post meta for one post as a flat key => value map.
// Skips WordPress-internal keys (_wp_*, _edit_*, etc.) to keep backup clean.
// ---------------------------------------------------------------------------
function tcc_collect_post_meta( $post_id ) {
    $raw   = get_post_meta( $post_id ); // returns array( key => array( value ) )
    $clean = array();
    foreach ( $raw as $key => $values ) {
        // Only back up meta owned by this plugin
        if ( strpos( $key, '_tcc_' ) === 0 || strpos( $key, 'tcc_' ) === 0 ) {
            $clean[ $key ] = $values[0]; // WP auto-unserialises; [0] is the single stored value
        }
    }
    return $clean;
}

// Helper: restore a flat meta map to a post, applying wp_slash to string
// values so JSON / HTML content survives WordPress's sanitisation pipeline.
function tcc_restore_post_meta( $post_id, $meta ) {
    if ( empty( $meta ) || ! is_array( $meta ) ) return;
    foreach ( $meta as $key => $value ) {
        if ( is_string( $value ) ) {
            update_post_meta( $post_id, $key, wp_slash( $value ) );
        } else {
            update_post_meta( $post_id, $key, $value ); // arrays / scalars stored directly
        }
    }
}

// ---------------------------------------------------------------------------
// EXPORT
// ---------------------------------------------------------------------------
function tcc_export_backup() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) { wp_send_json_error( 'You must be logged in to export backups.' ); wp_die(); }

    global $wpdb;

    $backup = array(
        'tcc_backup_version' => '3.4',
        'created_at'         => current_time( 'Y-m-d H:i:s' ),
    );

    // ── 1. ALL PLUGIN OPTIONS ──────────────────────────────────────────────
    $backup['options'] = array(
        // Core calculation & master data
        'tcc_global_settings'     => get_option( 'tcc_global_settings' ),
        'tcc_master_settings'     => get_option( 'tcc_master_settings' ),
        'tcc_itinerary_presets'   => get_option( 'tcc_itinerary_presets' ),
        'tcc_addon_presets'       => get_option( 'tcc_addon_presets' ),
        'tcc_single_day_presets'  => get_option( 'tcc_single_day_presets' ),
        'tcc_saved_notes'         => get_option( 'tcc_saved_notes' ),
        // Frontend group-tour form
        'tcc_wa_number'           => get_option( 'tcc_wa_number' ),
        'tcc_admin_email'         => get_option( 'tcc_admin_email' ),
        'tcc_form_flow'           => get_option( 'tcc_form_flow' ),
        'tcc_auto_wa_redirect'    => get_option( 'tcc_auto_wa_redirect', '1' ),
        'tcc_max_child_double'    => get_option( 'tcc_max_child_double' ),
        'tcc_max_infant_double'   => get_option( 'tcc_max_infant_double' ),
        'tcc_max_child_triple'    => get_option( 'tcc_max_child_triple' ),
        'tcc_max_infant_triple'   => get_option( 'tcc_max_infant_triple' ),
        'tcc_max_child_single'    => get_option( 'tcc_max_child_single' ),
        'tcc_max_infant_single'   => get_option( 'tcc_max_infant_single' ),
        // Expense & agency settings
        'tcc_agency_partners'     => get_option( 'tcc_agency_partners' ),
        'tcc_auto_daily_expenses' => get_option( 'tcc_auto_daily_expenses' ),
        'tcc_custom_pl'           => get_option( 'tcc_custom_pl' ),
        'tcc_general_expenses'    => get_option( 'tcc_general_expenses' ),
    );

    // ── 2. DATABASE TABLES ─────────────────────────────────────────────────
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_trans  = $wpdb->prefix . 'tcc_transport_rates';
    $backup['tables'] = array(
        'hotels'    => $wpdb->get_results( "SELECT * FROM $table_hotels", ARRAY_A ),
        'transport' => $wpdb->get_results( "SELECT * FROM $table_trans",  ARRAY_A ),
    );

    // ── 3. GROUP TOURS (tcc_fixed_tour) ────────────────────────────────────
    // Includes every _tcc_ meta key via tcc_collect_post_meta().
    // We also store the original post ID so the importer can remap
    // _tcc_fixed_tour_id references inside quotes after restore.
    $fixed_tours     = get_posts( array( 'post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'any' ) );
    $fixed_tour_data = array();
    foreach ( $fixed_tours as $t ) {
        $fixed_tour_data[] = array(
            'original_id' => $t->ID,               // for ID-remapping during restore
            'post_title'  => $t->post_title,
            'post_name'   => $t->post_name,
            'post_status' => $t->post_status,
            'post_date'   => $t->post_date,
            'menu_order'  => $t->menu_order,
            'meta'        => tcc_collect_post_meta( $t->ID ),
        );
    }
    $backup['fixed_tours'] = $fixed_tour_data;

    // ── 4. QUOTES (tcc_quote) ──────────────────────────────────────────────
    // Captures ALL tcc_ meta in one go — payments, expenses, statuses,
    // client details, JSON quote data, fixed-departure breakdown, etc.
    $quotes     = get_posts( array( 'post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'any' ) );
    $quote_data = array();
    foreach ( $quotes as $q ) {
        $quote_data[] = array(
            'post_title'  => $q->post_title,
            'post_name'   => $q->post_name,
            'post_status' => $q->post_status,
            'post_date'   => $q->post_date,
            'meta'        => tcc_collect_post_meta( $q->ID ),
        );
    }
    $backup['quotes'] = $quote_data;

    wp_send_json_success( $backup );
}

// ---------------------------------------------------------------------------
// IMPORT
// ---------------------------------------------------------------------------
function tcc_import_backup() {
    if ( ! is_user_logged_in() ) { wp_send_json_error( 'You must be logged in to import backups.' ); wp_die(); }
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Administrator access required to restore backups.' ); wp_die(); } // [PATCH-C4B] Capability check added

    $raw_body = file_get_contents( 'php://input' );

    // Security token is appended by the JS prefilter
    preg_match( '/&security=([^&]+)/', $raw_body, $matches );
    $token = isset( $matches[1] ) ? urldecode( $matches[1] ) : '';
    if ( ! wp_verify_nonce( $token, 'tcc_secure_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
        wp_die();
    }

    $json_data = preg_replace( '/&security=[^&]+/', '', $raw_body );
    $data      = json_decode( $json_data, true );

    if ( ! $data || ! isset( $data['options'] ) ) {
        wp_send_json_error( 'Invalid backup file structure.' );
        wp_die();
    }

    // ── 1. OPTIONS ─────────────────────────────────────────────────────────
    $option_keys = array(
        'tcc_global_settings', 'tcc_master_settings', 'tcc_itinerary_presets',
        'tcc_addon_presets', 'tcc_single_day_presets', 'tcc_saved_notes',
        'tcc_wa_number', 'tcc_admin_email', 'tcc_form_flow', 'tcc_auto_wa_redirect',
        'tcc_max_child_double', 'tcc_max_infant_double',
        'tcc_max_child_triple', 'tcc_max_infant_triple',
        'tcc_max_child_single', 'tcc_max_infant_single',
        'tcc_agency_partners', 'tcc_auto_daily_expenses',
        'tcc_custom_pl', 'tcc_general_expenses',
    );
    foreach ( $option_keys as $key ) {
        if ( isset( $data['options'][ $key ] ) ) {
            update_option( $key, $data['options'][ $key ] );
        }
    }

    // ── 2. DATABASE TABLES ─────────────────────────────────────────────────
    global $wpdb;
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_trans  = $wpdb->prefix . 'tcc_transport_rates';

    $wpdb->query( "TRUNCATE TABLE $table_hotels" );
    if ( ! empty( $data['tables']['hotels'] ) ) {
        foreach ( $data['tables']['hotels'] as $row ) { unset( $row['id'] ); $wpdb->insert( $table_hotels, $row ); }
    }
    $wpdb->query( "TRUNCATE TABLE $table_trans" );
    if ( ! empty( $data['tables']['transport'] ) ) {
        foreach ( $data['tables']['transport'] as $row ) { unset( $row['id'] ); $wpdb->insert( $table_trans, $row ); }
    }

    // ── 3. GROUP TOURS ─────────────────────────────────────────────────────
    // Delete all existing group tours, then recreate from backup.
    // Build old_id → new_id map so quote _tcc_fixed_tour_id can be remapped.
    $old_tours = get_posts( array( 'post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
    foreach ( $old_tours as $tid ) { wp_delete_post( $tid, true ); }

    $tour_id_map = array(); // original_id => new_id

    if ( ! empty( $data['fixed_tours'] ) ) {
        foreach ( $data['fixed_tours'] as $t ) {
            $new_id = wp_insert_post( array(
                'post_type'   => 'tcc_fixed_tour',
                'post_title'  => $t['post_title'],
                'post_name'   => isset( $t['post_name'] )   ? $t['post_name']   : '',
                'post_status' => isset( $t['post_status'] ) ? $t['post_status'] : 'publish',
                'post_date'   => isset( $t['post_date'] )   ? $t['post_date']   : current_time('mysql'),
                'menu_order'  => isset( $t['menu_order'] )  ? intval( $t['menu_order'] ) : 0,
            ) );

            if ( ! is_wp_error( $new_id ) ) {
                tcc_restore_post_meta( $new_id, isset( $t['meta'] ) ? $t['meta'] : array() );
                // Record mapping so quotes can be relinked
                if ( ! empty( $t['original_id'] ) ) {
                    $tour_id_map[ intval( $t['original_id'] ) ] = $new_id;
                }
            }
        }
    }

    // ── 4. QUOTES ──────────────────────────────────────────────────────────
    $old_quotes = get_posts( array( 'post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
    foreach ( $old_quotes as $qid ) { wp_delete_post( $qid, true ); }

    $restored_quote_ids = array(); // collect new IDs for post-restore ID remapping

    if ( ! empty( $data['quotes'] ) ) {
        foreach ( $data['quotes'] as $q ) {
            // Support both the new format (meta array) and the old format (post_content holds JSON)
            $meta = isset( $q['meta'] ) ? $q['meta'] : array();
            if ( empty( $meta['_tcc_quote_json_data'] ) && ! empty( $q['post_content'] ) ) {
                $meta['_tcc_quote_json_data'] = $q['post_content'];
            }

            $new_id = wp_insert_post( array(
                'post_type'    => 'tcc_quote',
                'post_title'   => $q['post_title'],
                'post_name'    => isset( $q['post_name'] )   ? $q['post_name']   : '',
                'post_content' => '',
                'post_status'  => isset( $q['post_status'] ) ? $q['post_status'] : 'publish',
                'post_date'    => isset( $q['post_date'] )   ? $q['post_date']   : current_time('mysql'),
            ) );

            if ( ! is_wp_error( $new_id ) ) {
                tcc_restore_post_meta( $new_id, $meta );
                $restored_quote_ids[] = $new_id;
            }
        }
    }

    // ── 5. REMAP _tcc_fixed_tour_id ────────────────────────────────────────
    // After restore, group tours have new IDs. Any tcc_quote that was linked
    // to a group tour via _tcc_fixed_tour_id will have the OLD id stored.
    // Use the tour_id_map built in step 3 to fix these links.
    if ( ! empty( $tour_id_map ) ) {
        foreach ( $restored_quote_ids as $new_q_id ) {
            $old_tour_id = intval( get_post_meta( $new_q_id, '_tcc_fixed_tour_id', true ) );
            if ( $old_tour_id && isset( $tour_id_map[ $old_tour_id ] ) ) {
                update_post_meta( $new_q_id, '_tcc_fixed_tour_id', $tour_id_map[ $old_tour_id ] );
            }
        }
    }

    wp_send_json_success( 'Backup restored successfully. Page will now refresh.' );
}

function tcc_send_quote_email() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    if(!$quote_id) wp_send_json_error('Invalid Quote ID');

    $post = get_post($quote_id);
    if(!$post) wp_send_json_error('Quote not found');

    $data = tcc_get_quote_json_data($quote_id);
    $client_email = isset($data['summary']['client_email']) ? sanitize_email($data['summary']['client_email']) : '';
    $client_name = isset($data['summary']['client_name']) ? sanitize_text_field($data['summary']['client_name']) : 'Valued Client';
    $dest = isset($data['summary']['destination']) ? sanitize_text_field($data['summary']['destination']) : 'Trip';

    if(empty($client_email)) wp_send_json_error('No email address provided for this client.');

    $permalink = get_permalink($quote_id);
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');

    $subject = "Your Travel Quotation / Receipt for $dest - $site_name";
    
    $message = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>";
    $message .= "<h3 style='color: #111;'>Hello $client_name,</h3>";
    $message .= "<p>Thank you for choosing <strong>$site_name</strong>. Please find your detailed travel quotation, itinerary, and receipt information for <strong>$dest</strong> at the secure link below:</p>";
    $message .= "<p style='margin: 25px 0;'><a href='$permalink' style='display: inline-block; padding: 12px 25px; background: #b93b59; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;'>View Your Quotation</a></p>";
    $message .= "<p>Or copy and paste this link securely into your browser: <br> <a href='$permalink'>$permalink</a></p>";
    $message .= "<p>If you have any questions, please reply directly to this email or contact us.</p>";
    $message .= "<p>Thank you,<br><strong>$site_name</strong></p>";
    $message .= "</body></html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $headers[] = 'Bcc: ' . $admin_email; 

    $sent = wp_mail($client_email, $subject, $message, $headers);

    if($sent) {
        wp_send_json_success("Email successfully sent to $client_email (and BCC'd to Admin).");
    } else {
        wp_send_json_error('Failed to send email. Please check your WordPress email server configuration.');
    }
}

function tcc_load_notes() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $notes = get_option('tcc_saved_notes', array());
    wp_send_json_success($notes);
}

function tcc_save_note() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $note_text = trim(sanitize_textarea_field(wp_unslash($_POST['note_text'])));
    $note_group = isset($_POST['note_group']) ? trim(sanitize_text_field($_POST['note_group'])) : 'General';
    if(empty($note_group)) $note_group = 'General';
    
    $note_id = isset($_POST['note_id']) ? sanitize_text_field($_POST['note_id']) : '';
    if(empty($note_text)) wp_send_json_error('Empty note');

    $notes = get_option('tcc_saved_notes', array());
    
    if($note_id) {
        foreach($notes as &$n) {
            if($n['id'] === $note_id) { 
                $n['text'] = $note_text; 
                $n['group'] = $note_group; 
                break; 
            }
        }
    } else {
        $notes[] = array(
            'id' => uniqid('note_'), 
            'text' => $note_text,
            'group' => $note_group
        );
    }
    
    update_option('tcc_saved_notes', $notes);
    wp_send_json_success($notes);
}

function tcc_delete_note() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $note_id = sanitize_text_field($_POST['note_id']);
    $notes = get_option('tcc_saved_notes', array());
    
    $new_notes = array();
    foreach($notes as $n) {
        if($n['id'] !== $note_id) $new_notes[] = $n;
    }
    
    update_option('tcc_saved_notes', $new_notes);
    wp_send_json_success($new_notes);
}

function tcc_save_addon_preset() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    $destination = trim(sanitize_text_field($_POST['destination']));
    
    $addon_price = isset($_POST['addon_price']) ? floatval($_POST['addon_price']) : 0;
    $addon_type = isset($_POST['addon_type']) ? sanitize_text_field($_POST['addon_type']) : 'flat';

    if(empty($preset_name) || empty($destination)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_addon_presets', array());
    if(!isset($presets[$destination])) $presets[$destination] = array();
    
    $presets[$destination][$preset_name] = array(
        'price' => $addon_price,
        'type'  => $addon_type
    );
    update_option('tcc_addon_presets', $presets);
    
    wp_send_json_success("Saved Successfully");
}

function tcc_load_addon_presets() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $destination = trim(sanitize_text_field($_POST['destination']));
    $presets = get_option('tcc_addon_presets', array());
    if(isset($presets[$destination])) wp_send_json_success($presets[$destination]);
    else wp_send_json_success(array());
}

function tcc_delete_addon_preset() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    $destination = trim(sanitize_text_field($_POST['destination']));
    if(empty($preset_name) || empty($destination)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_addon_presets', array());
    if(isset($presets[$destination]) && isset($presets[$destination][$preset_name])) {
        unset($presets[$destination][$preset_name]);
        update_option('tcc_addon_presets', $presets);
        wp_send_json_success("Deleted Successfully");
    } else {
        wp_send_json_error("Preset not found");
    }
}

function tcc_save_single_day_preset() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    
    $dest = trim(sanitize_text_field($_POST['destination']));
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    
    $title = trim(sanitize_text_field($_POST['title']));
    
    $desc = wp_unslash($_POST['desc']);
    
    $stay = trim(sanitize_text_field($_POST['stay']));
    $image = trim(sanitize_url($_POST['image']));

    if(empty($dest) || empty($preset_name) || empty($title)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_single_day_presets', array());
    if(!isset($presets[$dest])) $presets[$dest] = array();
    
    $presets[$dest][$preset_name] = array(
        'title' => $title,
        'desc'  => $desc,
        'stay'  => $stay,
        'image' => $image
    );
    update_option('tcc_single_day_presets', $presets);
    
    wp_send_json_success("Day Preset Saved Successfully");
}

function tcc_load_single_day_presets() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    
    $dest = trim(sanitize_text_field($_POST['destination']));
    $presets = get_option('tcc_single_day_presets', array());
    
    if(isset($presets[$dest])) {
        wp_send_json_success($presets[$dest]);
    } else {
        wp_send_json_success(array());
    }
}

function tcc_delete_single_day_preset() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    
    $dest = trim(sanitize_text_field($_POST['destination']));
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    
    if(empty($dest) || empty($preset_name)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_single_day_presets', array());
    
    if(isset($presets[$dest]) && isset($presets[$dest][$preset_name])) {
        unset($presets[$dest][$preset_name]);
        update_option('tcc_single_day_presets', $presets);
        wp_send_json_success("Day Preset Deleted Successfully");
    } else {
        wp_send_json_error("Preset not found");
    }
}

function tcc_generate_server_pdf() {
    // [PATCH-C1B] Auth check added — rejects unauthenticated callers
    if ( ! is_user_logged_in() ) { status_header( 401 ); wp_die( 'Unauthorized.' ); }
    $dompdf_path = plugin_dir_path( __FILE__ ) . 'dompdf/autoload.inc.php';
    if ( ! file_exists( $dompdf_path ) ) {
        wp_die('Dompdf library is missing. Please upload it to your includes/dompdf/ folder.');
    }
    require_once $dompdf_path;

    $html = isset($_POST['html']) ? rawurldecode($_POST['html']) : '';
    $title = isset($_POST['title']) ? sanitize_file_name($_POST['title']) : 'Quotation';

    if ( empty($html) ) {
        wp_die('No content provided.');
    }

    $html_content = wp_unslash($html);

    // FIX: Convert raw Unicode emoji characters (✅ 🔥 🎯 etc., typed directly in
    // the Quill editor) into <img class="emoji" src="...s.w.org..."> tags so
    // dompdf can render them colorfully. DejaVu Sans only has monochrome glyph
    // fallbacks for emoji, so without this step they'd appear as small B&W chars.
    // The existing CSS (img.emoji { width:1em; height:1em }) and the base64
    // image embedder below will then size and inline them correctly.
    if ( function_exists( 'wp_staticize_emoji' ) ) {
        $html_content = wp_staticize_emoji( $html_content );
    }

    // Set your multiplier. 1.20 = 120% (a 20% increase in text size)
    $scale_factor = 1.20;
    $html_content = preg_replace_callback('/font-size:\s*(\d+(?:\.\d+)?)px/i', function($matches) use ($scale_factor) {
        $new_size = round((float)$matches[1] * $scale_factor);
        return 'font-size: ' . $new_size . 'px';
    }, $html_content);
    
    $html_content = preg_replace_callback('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', function($matches) {
        $img_tag = $matches[0];
        $img_url = $matches[1];
        
        if (strpos($img_url, 'data:image') === 0) {
            return $img_tag;
        }

        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];
        
        $local_path = str_replace($base_url, $base_dir, $img_url);
        $img_data = '';
        $mime_type = 'image/jpeg'; 

        if (file_exists($local_path)) {
            $img_data = @file_get_contents($local_path);
            if(function_exists('mime_content_type')) {
                $mime_type = mime_content_type($local_path);
            }
        } else {
            $response = wp_remote_get($img_url, array('sslverify' => false, 'timeout' => 15));
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                $img_data = wp_remote_retrieve_body($response);
                $content_type = wp_remote_retrieve_header($response, 'content-type');
                if(!empty($content_type)) $mime_type = $content_type;
            }
        }

        if (!empty($img_data)) {
            $base64 = base64_encode($img_data);
            $new_src = 'data:' . $mime_type . ';base64,' . $base64;
            return str_replace($img_url, $new_src, $img_tag);
        }
        
        return $img_tag;
    }, $html_content);

    $full_html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "DejaVu Sans", "Helvetica", "Arial", sans-serif; color: #333; font-size: 13px; margin:0; padding:0; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            th, td { padding: 8px; border: 1px solid #e2e8f0; word-wrap: break-word; }
            div, table, tbody, tr, td, ul, li, p { page-break-inside: auto !important; page-break-before: auto !important; page-break-after: auto !important; }
            img { max-width: 100%; height: auto; display: block; margin: 0 auto; object-fit: contain; }
            img.emoji { display: inline-block !important; width: 1em !important; height: 1em !important; margin: 0 0.1em !important; vertical-align: middle !important; border: none !important; box-shadow: none !important; padding: 0 !important; background: none !important; }
            .tcc-rte-display img { width: auto; max-width: 100%; }
        </style>
    </head>
    <body>' . $html_content . '</body>
    </html>';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false); // [PATCH-C1C] Remote fetch disabled — images are base64-inlined above, PDF output unaffected 
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans'); 
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => FALSE,
            'verify_peer_name' => FALSE,
            'allow_self_signed' => TRUE
        ]
    ]);
    $options->setHttpContext($context);
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($full_html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($title . '.pdf', array("Attachment" => true));
    exit;
}

function tcc_rename_hotel_rate() {
    check_ajax_referer( 'tcc_secure_nonce', 'security' );
    if ( ! is_user_logged_in() ) wp_die();
    //if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    
    global $wpdb;
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    
    $destination = trim(sanitize_text_field($_POST['set_destination']));
    $night_stay_place = trim(sanitize_text_field($_POST['set_night_stay_place']));
    $hotel_cat = trim(sanitize_text_field($_POST['set_hotel_cat']));
    $old_hotel_name = trim(sanitize_text_field($_POST['old_hotel_name']));
    $new_hotel_name = trim(sanitize_text_field($_POST['new_hotel_name']));

    if(empty($destination) || empty($night_stay_place) || empty($hotel_cat) || empty($old_hotel_name) || empty($new_hotel_name)) {
        wp_send_json_error(array('message' => 'Missing data.'));
        wp_die();
    }

    $updated = $wpdb->update(
        $table_hotels,
        array('hotel_name' => $new_hotel_name),
        array(
            'destination' => $destination,
            'night_stay_place' => $night_stay_place,
            'hotel_category' => $hotel_cat,
            'hotel_name' => $old_hotel_name
        )
    );

    if ($updated !== false) {
        wp_send_json_success(array('message' => 'Hotel renamed successfully!'));
    } else {
        wp_send_json_error(array('message' => 'Database error while renaming hotel.'));
    }
}