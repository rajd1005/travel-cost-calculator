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
add_action( 'wp_ajax_tcc_duplicate_quote', 'tcc_duplicate_quote' ); // NEW DUPLICATE ACTION
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


function tcc_optimize_transport() {
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
    if ( ! is_user_logged_in() ) wp_die();
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

    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_transport = $wpdb->prefix . 'tcc_transport_rates';

    $error_messages = [];
    $transport_cost = 0;
    $total_capacity = 0;
    $transport_details = [];
    $hotel_cost = 0;
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
    $dest_inclusions = isset($_POST['quote_inclusions']) && !empty($_POST['quote_inclusions']) ? wp_kses_post(wp_unslash($_POST['quote_inclusions'])) : (isset($master_data[$destination]['inclusions']) ? $master_data[$destination]['inclusions'] : '');
    
    $dest_exclusions = isset($_POST['quote_exclusions']) && !empty($_POST['quote_exclusions']) ? wp_kses_post(wp_unslash($_POST['quote_exclusions'])) : (isset($master_data[$destination]['exclusions']) ? $master_data[$destination]['exclusions'] : '');
    
    $dest_payment_terms = isset($_POST['quote_payment_terms']) && !empty($_POST['quote_payment_terms']) ? wp_kses_post(wp_unslash($_POST['quote_payment_terms'])) : (isset($master_data[$destination]['payment_terms']) ? $master_data[$destination]['payment_terms'] : '');

    $dest_note = isset($_POST['quote_dest_note']) && !empty($_POST['quote_dest_note']) ? wp_kses_post(wp_unslash($_POST['quote_dest_note'])) : (isset($master_data[$destination]['dest_note']) ? $master_data[$destination]['dest_note'] : '');

    $company_details = isset($_POST['quote_company_details']) && !empty($_POST['quote_company_details']) ? wp_kses_post(wp_unslash($_POST['quote_company_details'])) : (isset($master_data[$destination]['company_details']) ? $master_data[$destination]['company_details'] : '');


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
    }
    
    $transport_summary_string = implode(", ", $transport_details);

    if ( !empty($stay_places) ) {
        foreach ( $stay_places as $index => $place_name ) {
            $place = trim(sanitize_text_field($place_name));
            $nights = intval($stay_nights[$index]);
            
            if ($place === 'No Hotel') {
                $detailed_stay_info[] = array('place' => 'No Hotel Required', 'category' => '-', 'nights' => $nights, 'options' => array(array('name' => 'No Room Provided', 'link' => '')));
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

            $primary_hotel = $selected_hotel_array[0];
            $hotel_rate = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $row_cat, $primary_hotel));

            if ( $hotel_rate ) {
                $daily_room_cost      = $no_of_rooms * $hotel_rate->room_price;
                $daily_extra_bed_cost = $extra_beds * $hotel_rate->extra_bed_price;
                $daily_child_cost     = $child_6_12_pax * $hotel_rate->child_price; 
                
                $total_daily_hotel_cost = $daily_room_cost + $daily_extra_bed_cost + $daily_child_cost;
                $row_base = $total_daily_hotel_cost * $nights;
                $row_final = $row_base * $surcharge_multiplier;
                
                $hotel_cost += $row_final; 
                $hotel_row_costs[$index] = $row_final;

                $display_names = [];
                foreach($selected_hotel_array as $h_name) {
                    $hr = $wpdb->get_row( $wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $row_cat, $h_name));
                    if($hr) {
                        $display_names[] = array('name' => $hr->hotel_name, 'link' => $hr->hotel_website);
                    }
                }

                $detailed_stay_info[] = array('place' => $place, 'category' => $row_cat, 'nights' => $nights, 'options' => $display_names);
            } else {
                $error_messages[] = "Missing Rate ({$place} - {$row_cat} - {$primary_hotel}).";
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
                
                // Calculate Profit ON Total Actual Cost
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
    
    // Automatically Round Up to nearest 100
    $grand_total = ceil($exact_grand_total / 100) * 100; 
    
    $discounted_base_price = $grand_total / $M;
    $gst = $grand_total - $discounted_base_price;
    
    $per_person_excl_gst = ($total_pax > 0) ? ($discounted_base_price / $total_pax) : 0;
    $per_person_inc_gst = ($total_pax > 0) ? ($grand_total / $total_pax) : 0;

    $prof_tax = $grand_total * $pt_rate;
    $pg_charge = $grand_total * $pg_rate;
    
    $gross_profit = $discounted_base_price - $actual_cost; 
    $net_profit = $gross_profit - $prof_tax - $pg_charge;

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
        'stays'       => $detailed_stay_info,
        'inclusions'  => $dest_inclusions,
        'exclusions'  => $dest_exclusions,
        'payment_terms' => $dest_payment_terms,
        'dest_note'   => $dest_note,
        'company_details' => $company_details,
        
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
        
        'transport_row_costs' => $transport_row_costs,
        'hotel_row_costs' => $hotel_row_costs,
        'surcharge_applied' => $surcharge_percent,
        'itinerary'   => array_map('sanitize_text_field', is_array($day_itinerary) ? $day_itinerary : array()),
        'itinerary_desc'   => array_map('wp_kses_post', is_array($day_itinerary_desc) ? $day_itinerary_desc : array()),
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
        'override_profit' => isset($_POST['override_profit']) ? $_POST['override_profit'] : '',
        'manual_pp_override' => isset($_POST['manual_pp_override']) ? $_POST['manual_pp_override'] : '',
        'd1_type' => $d1_type,
        'd1_val' => $d1_val,
        'd2_type' => $d2_type,
        'd2_val' => $d2_val,
        'pickup_custom' => $pickup_custom,
        'drop_custom' => $drop_custom,
        'quote_inclusions' => isset($_POST['quote_inclusions']) ? wp_unslash($_POST['quote_inclusions']) : '',
        'quote_exclusions' => isset($_POST['quote_exclusions']) ? wp_unslash($_POST['quote_exclusions']) : '',
        'quote_payment_terms' => isset($_POST['quote_payment_terms']) ? wp_unslash($_POST['quote_payment_terms']) : '',
        'quote_dest_note' => isset($_POST['quote_dest_note']) ? wp_unslash($_POST['quote_dest_note']) : '',
        'quote_company_details' => isset($_POST['quote_company_details']) ? wp_unslash($_POST['quote_company_details']) : '',
        'itinerary_desc' => is_array($day_itinerary_desc) ? array_map('wp_kses_post', $day_itinerary_desc) : array(),
        'itinerary_image' => is_array($day_itinerary_image) ? $day_itinerary_image : array(),
        'itinerary_stay_place' => is_array($day_itinerary_stays) ? $day_itinerary_stays : array(),
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
                'post_content' => wp_slash( $post_content_json )
            ));
            $permalink = get_permalink($edit_quote_id);
        } else {
            $random_string = wp_generate_password(8, false); 
            $post_title = !empty($client_name) ? "{$client_name} - {$destination} (" . strtoupper($random_string) . ")" : 'Quote ' . strtoupper($random_string);

            $post_id = wp_insert_post(array(
                'post_type' => 'tcc_quote',
                'post_name' => $random_string,
                'post_title' => $post_title,
                'post_content' => wp_slash( $post_content_json ), 
                'post_status' => 'publish'
            ));
            $permalink = get_permalink($post_id);
        }
    }

    wp_send_json_success(array(
        'per_person'  => round($per_person_excl_gst, 2),
        'per_person_with_gst' => round($per_person_inc_gst, 2),
        'gst'         => round($gst, 2),
        'grand_total' => round($grand_total, 2),
        'summary_data'=> $summary_data,
        'permalink'   => $permalink
    ));
}

// NEW FUNCTION: Duplicate Quote Action
function tcc_duplicate_quote() {
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    if(!$quote_id) wp_send_json_error('Invalid Quote ID');

    $post = get_post($quote_id);
    if(!$post) wp_send_json_error('Quote not found');

    $data = json_decode($post->post_content, true);
    
    // Generate a fresh random string for the permalink
    $random_string = wp_generate_password(8, false); 
    $client_name = isset($data['summary']['client_name']) ? $data['summary']['client_name'] : '';
    $dest = isset($data['summary']['destination']) ? $data['summary']['destination'] : '';
    
    $post_title = !empty($client_name) ? "{$client_name} - {$dest} (" . strtoupper($random_string) . ")" : 'Quote ' . strtoupper($random_string);

    $new_post_id = wp_insert_post(array(
        'post_type' => 'tcc_quote',
        'post_name' => $random_string,
        'post_title' => $post_title,
        'post_content' => wp_slash( $post->post_content ), // Directly clone the identical JSON content
        'post_status' => 'publish'
    ));

    if(is_wp_error($new_post_id)) {
        wp_send_json_error('Failed to duplicate.');
    }

    // Notice we DO NOT copy over post_meta for "tcc_payments" or "tcc_lead_status".
    // This correctly makes the duplicate an empty slate waiting to be turned into a new option!

    wp_send_json_success(array(
        'new_id' => $new_post_id,
        'message' => 'Quote Option Duplicated successfully! It has now been loaded into the Calculator.'
    ));
}

function tcc_rename_master_element() {
    if ( ! is_user_logged_in() ) wp_die();
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
    if ( ! is_user_logged_in() ) wp_die();
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
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    if(!$quote_id) wp_send_json_error();
    
    $post = get_post($quote_id);
    if(!$post) wp_send_json_error();
    
    $data = json_decode($post->post_content, true);
    wp_send_json_success($data);
}

function tcc_fetch_hotel_names() {
    if ( ! is_user_logged_in() ) wp_die();
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_hotel_rates';
    $dest  = trim(sanitize_text_field($_POST['dest']));
    $place = trim(sanitize_text_field($_POST['place']));
    $cat   = trim(sanitize_text_field($_POST['cat']));

    $results = $wpdb->get_results( $wpdb->prepare("SELECT hotel_name, hotel_website FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s GROUP BY hotel_name", $dest, $place, $cat) );
    wp_send_json_success($results);
}

function tcc_fetch_hotel_rate() {
    if ( ! is_user_logged_in() ) wp_die();
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
    if ( ! is_user_logged_in() ) wp_die();
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
    if ( ! is_user_logged_in() ) wp_die();
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
    if ( ! is_user_logged_in() ) wp_die();
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
    $stays = explode(',', sanitize_text_field($_POST['master_stays']));
    $vehicles = explode(',', sanitize_text_field($_POST['master_vehicles']));
    $cats = explode(',', sanitize_text_field($_POST['master_hotel_cats']));

    $inclusions = wp_kses_post(wp_unslash($_POST['master_inclusions']));
    $exclusions = wp_kses_post(wp_unslash($_POST['master_exclusions']));
    $payment_terms = wp_kses_post(wp_unslash($_POST['master_payment_terms']));
    
    $dest_note = isset($_POST['master_dest_note']) ? wp_kses_post(wp_unslash($_POST['master_dest_note'])) : '';
    $company_details = isset($_POST['master_company_details']) ? wp_kses_post(wp_unslash($_POST['master_company_details'])) : '';

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
        'stay_places' => array_map('trim', $stays),
        'vehicles' => array_map('trim', $vehicles),
        'hotel_categories' => array_map('trim', $cats),
        'inclusions' => $inclusions,
        'exclusions' => $exclusions,
        'payment_terms' => $payment_terms,
        'dest_note' => $dest_note,
        'company_details' => $company_details,
        'seasons' => $seasons
    );
    update_option('tcc_master_settings', $master_data);
    wp_send_json_success(array('message' => 'Saved! Dropdowns Updated.', 'new_master' => $master_data));
}

function tcc_save_pricing_settings() {
    if ( ! is_user_logged_in() ) wp_die();
    global $wpdb;
    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $destination      = trim(sanitize_text_field($_POST['set_destination']));
    $night_stay_place = trim(sanitize_text_field($_POST['set_night_stay_place']));
    $hotel_cat        = trim(sanitize_text_field($_POST['set_hotel_cat']));
    $hotel_name       = trim(sanitize_text_field($_POST['set_hotel_name']));
    $hotel_website    = esc_url_raw($_POST['set_hotel_website']);
    $room_price       = floatval($_POST['set_room_price']);
    $extra_bed        = floatval($_POST['set_extra_bed']);
    $child_price      = floatval($_POST['set_child_price']);

    $existing = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s", $destination, $night_stay_place, $hotel_cat, $hotel_name));

    if ( $existing ) {
        $wpdb->update($table_hotels, array('hotel_website' => $hotel_website, 'room_price' => $room_price, 'extra_bed_price' => $extra_bed, 'child_price' => $child_price), array( 'id' => $existing->id ));
    } else {
        $wpdb->insert($table_hotels, array('destination' => $destination, 'night_stay_place' => $night_stay_place, 'hotel_category' => $hotel_cat, 'hotel_name' => $hotel_name, 'hotel_website' => $hotel_website, 'room_price' => $room_price, 'extra_bed_price' => $extra_bed, 'child_price' => $child_price));
    }
    wp_send_json_success(array('message' => 'Hotel pricing saved.'));
}

function tcc_save_transport_settings() {
    if ( ! is_user_logged_in() ) wp_die();
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
    if ( ! is_user_logged_in() ) wp_die();
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    $destination = trim(sanitize_text_field($_POST['destination']));
    
    $raw_days = isset($_POST['itinerary_day']) ? $_POST['itinerary_day'] : array();
    $days = is_array($raw_days) ? array_map('sanitize_text_field', wp_unslash($raw_days)) : array();

    $raw_stays = isset($_POST['itinerary_stay_place']) ? $_POST['itinerary_stay_place'] : array();
    $stays = is_array($raw_stays) ? array_map('sanitize_text_field', wp_unslash($raw_stays)) : array();

    $raw_desc = isset($_POST['itinerary_desc']) ? $_POST['itinerary_desc'] : array();
    $desc = is_array($raw_desc) ? array_map('wp_kses_post', wp_unslash($raw_desc)) : array();

    $raw_image = isset($_POST['itinerary_image']) ? $_POST['itinerary_image'] : array();
    $image = is_array($raw_image) ? array_map('sanitize_url', wp_unslash($raw_image)) : array();

    if(empty($preset_name) || empty($days)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_itinerary_presets', array());
    if(!isset($presets[$destination])) $presets[$destination] = array();
    
    $presets[$destination][$preset_name] = array(
        'itinerary' => $days,
        'itinerary_desc' => $desc,
        'itinerary_image' => $image,
        'stay_places' => $stays
    );
    update_option('tcc_itinerary_presets', $presets);
    
    wp_send_json_success("Saved Successfully");
}

function tcc_load_itinerary_presets() {
    if ( ! is_user_logged_in() ) wp_die();
    $destination = trim(sanitize_text_field($_POST['destination']));
    $presets = get_option('tcc_itinerary_presets', array());
    if(isset($presets[$destination])) wp_send_json_success($presets[$destination]);
    else wp_send_json_success(array());
}

function tcc_delete_itinerary_preset() {
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
        $content = json_decode($q->post_content, true);
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
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    if(!$post_id) wp_send_json_error();

    $post = get_post($post_id);
    $data = json_decode($post->post_content, true);
    $grand_total = floatval($data['grand_total']);
    
    // NEW: Fetch Net Profit from Quote Data
    $net_profit = isset($data['summary']['net_profit']) ? floatval($data['summary']['net_profit']) : 0;
    
    // Fetch Post Quote Discount
    $post_discount = floatval(get_post_meta($post_id, 'tcc_post_quote_discount', true));
    
    // Fetch original tax rates to reverse-calculate the exact waiver
    $gst_pct = isset($data['summary']['gst_pct']) ? floatval($data['summary']['gst_pct']) / 100 : 0.05;
    $pt_pct = isset($data['summary']['pt_pct']) ? floatval($data['summary']['pt_pct']) / 100 : 0;
    $pg_pct = isset($data['summary']['pg_pct']) ? floatval($data['summary']['pg_pct']) / 100 : 0;
    
    $payments = get_post_meta($post_id, 'tcc_payments', true);
    if(!is_array($payments)) $payments = [];
    
    $status = get_post_meta($post_id, 'tcc_lead_status', true);

    $total_paid = 0; // Agency Bank
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
            // Mathematical reversal: GST on base + PT/PG applied on (Base + GST)
            $waiver = ($amt * $gst_pct) + ($amt * (1 + $gst_pct) * ($pt_pct + $pg_pct));
            $tax_waiver += $waiver;
            $p['waiver_calculated'] = $waiver; // Send to JS dashboard
        } else {
            $total_paid += $amt; 
            $total_actual_pg += $pg_fee; 
        }
    }
    
    $is_cancelled = ($has_refund || $status === 'Canceled');
    $retained_income = max(0, $total_paid - $total_refunded);
    
    // Balance reduces by Agency Payment + Vendor Payment + The Waived Taxes + the flat Post-Quote Discount
    $balance = $is_cancelled ? 0 : max(0, $grand_total - $post_discount - $total_paid - $vendor_paid - $tax_waiver);
    $net_in_bank = $total_paid - $total_actual_pg; 

    // Calculate Vendor Direct Balance (mathematically stripping GST, PT, and PG)
    $vendor_direct_balance = $is_cancelled ? 0 : max(0, $balance / ( (1 + $gst_pct) * (1 + $pt_pct + $pg_pct) ));

    wp_send_json_success(array(
        'grand_total' => $grand_total,
        'net_profit' => round($net_profit, 2),
        'post_discount' => $post_discount, // Pass to frontend for rendering
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
    
    if ($method === 'Refund') {
        update_post_meta($post_id, 'tcc_lead_status', 'Canceled');
    }

    wp_send_json_success();
}

function tcc_delete_payment() {
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
    wp_send_json_success();
}

function tcc_save_post_discount() {
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
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    if($post_id) {
        wp_delete_post($post_id, true);
        wp_send_json_success();
    }
    wp_send_json_error();
}

function tcc_update_quote_client() {
    if ( ! is_user_logged_in() ) wp_die();
    
    $post_id = intval($_POST['quote_id']);
    $name = trim(sanitize_text_field($_POST['c_name']));
    $phone = trim(sanitize_text_field($_POST['c_phone']));
    $email = trim(sanitize_email($_POST['c_email']));
    
    if(!$post_id) wp_send_json_error("Invalid Quote ID");

    $post = get_post($post_id);
    if(!$post) wp_send_json_error("Quote not found");

    $data = json_decode($post->post_content, true);
    $data['summary']['client_name'] = $name;
    $data['summary']['client_phone'] = $phone;
    $data['summary']['client_email'] = $email;

    $post_title = !empty($name) ? "{$name} - {$data['summary']['destination']} (" . strtoupper($post->post_name) . ")" : 'Quote ' . strtoupper($post->post_name);

    wp_update_post(array(
        'ID' => $post_id,
        'post_title' => $post_title,
        'post_content' => wp_slash(wp_json_encode($data))
    ));
    
    wp_send_json_success();
}

function tcc_export_backup() {
    if ( ! is_user_logged_in() ) { wp_send_json_error('You must be logged in to export backups.'); wp_die(); }
    if ( ! current_user_can('manage_options') ) { wp_send_json_error('You do not have administrator permissions to export backups.'); wp_die(); }
    
    global $wpdb;
    $backup = array();

    $backup['options'] = array(
        'tcc_global_settings'    => get_option('tcc_global_settings'),
        'tcc_master_settings'    => get_option('tcc_master_settings'),
        'tcc_itinerary_presets'  => get_option('tcc_itinerary_presets'),
        'tcc_addon_presets'      => get_option('tcc_addon_presets'),
        'tcc_single_day_presets' => get_option('tcc_single_day_presets'),
    );

    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_trans  = $wpdb->prefix . 'tcc_transport_rates';
    
    $backup['tables'] = array(
        'hotels'    => $wpdb->get_results("SELECT * FROM $table_hotels", ARRAY_A),
        'transport' => $wpdb->get_results("SELECT * FROM $table_trans", ARRAY_A),
    );

    $quotes = get_posts(array('post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'any'));
    
    $quotes_data = array();
    foreach($quotes as $q) {
        $quotes_data[] = array(
            'post_title'   => $q->post_title,
            'post_name'    => $q->post_name,
            'post_content' => $q->post_content,
            'post_status'  => $q->post_status,
            'post_date'    => $q->post_date,
            'meta'         => array(
                'tcc_payments'      => get_post_meta($q->ID, 'tcc_payments', true),
                'tcc_lead_status'   => get_post_meta($q->ID, 'tcc_lead_status', true),
                'tcc_followup_date' => get_post_meta($q->ID, 'tcc_followup_date', true),
                'tcc_is_priority'   => get_post_meta($q->ID, 'tcc_is_priority', true),
            )
        );
    }
    $backup['quotes'] = $quotes_data;
    wp_send_json_success($backup);
}

function tcc_import_backup() {
    if ( ! is_user_logged_in() ) { wp_send_json_error('You must be logged in to import backups.'); wp_die(); }
    if ( ! current_user_can('manage_options') ) { wp_send_json_error('You do not have administrator permissions to import backups.'); wp_die(); }
    
    global $wpdb;
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if(!$data || !isset($data['options'])) { wp_send_json_error('Invalid backup file structure.'); wp_die(); }

    if(isset($data['options']['tcc_global_settings'])) update_option('tcc_global_settings', $data['options']['tcc_global_settings']);
    if(isset($data['options']['tcc_master_settings'])) update_option('tcc_master_settings', $data['options']['tcc_master_settings']);
    if(isset($data['options']['tcc_itinerary_presets'])) update_option('tcc_itinerary_presets', $data['options']['tcc_itinerary_presets']);
    if(isset($data['options']['tcc_addon_presets'])) update_option('tcc_addon_presets', $data['options']['tcc_addon_presets']);
    if(isset($data['options']['tcc_single_day_presets'])) update_option('tcc_single_day_presets', $data['options']['tcc_single_day_presets']);

    $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
    $table_trans  = $wpdb->prefix . 'tcc_transport_rates';
    
    $wpdb->query("TRUNCATE TABLE $table_hotels");
    if(!empty($data['tables']['hotels'])) {
        foreach($data['tables']['hotels'] as $row) { unset($row['id']); $wpdb->insert($table_hotels, $row); }
    }

    $wpdb->query("TRUNCATE TABLE $table_trans");
    if(!empty($data['tables']['transport'])) {
        foreach($data['tables']['transport'] as $row) { unset($row['id']); $wpdb->insert($table_trans, $row); }
    }

    $old_quotes = get_posts(array('post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids'));
    foreach($old_quotes as $qid) { wp_delete_post($qid, true); }

    if(!empty($data['quotes'])) {
        foreach($data['quotes'] as $q) {
            $new_id = wp_insert_post(array(
                'post_type'    => 'tcc_quote',
                'post_title'   => $q['post_title'],
                'post_name'    => $q['post_name'],
                'post_content' => wp_slash($q['post_content']),
                'post_status'  => $q['post_status'],
                'post_date'    => $q['post_date']
            ));

            if(!is_wp_error($new_id)) {
                if(isset($q['meta']['tcc_payments'])) update_post_meta($new_id, 'tcc_payments', $q['meta']['tcc_payments']);
                if(isset($q['meta']['tcc_lead_status'])) update_post_meta($new_id, 'tcc_lead_status', $q['meta']['tcc_lead_status']);
                if(isset($q['meta']['tcc_followup_date'])) update_post_meta($new_id, 'tcc_followup_date', $q['meta']['tcc_followup_date']);
                if(isset($q['meta']['tcc_is_priority'])) update_post_meta($new_id, 'tcc_is_priority', $q['meta']['tcc_is_priority']);
            }
        }
    }
    wp_send_json_success('Backup restored successfully. Page will now refresh.');
}

function tcc_send_quote_email() {
    if ( ! is_user_logged_in() ) wp_die();
    $quote_id = intval($_POST['quote_id']);
    if(!$quote_id) wp_send_json_error('Invalid Quote ID');

    $post = get_post($quote_id);
    if(!$post) wp_send_json_error('Quote not found');

    $data = json_decode($post->post_content, true);
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
    if ( ! is_user_logged_in() ) wp_die();
    $notes = get_option('tcc_saved_notes', array());
    wp_send_json_success($notes);
}

function tcc_save_note() {
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

// --- ADDON PRESET ENDPOINTS ---

function tcc_save_addon_preset() {
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
    if ( ! is_user_logged_in() ) wp_die();
    $destination = trim(sanitize_text_field($_POST['destination']));
    $presets = get_option('tcc_addon_presets', array());
    if(isset($presets[$destination])) wp_send_json_success($presets[$destination]);
    else wp_send_json_success(array());
}

function tcc_delete_addon_preset() {
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

// --- INDIVIDUAL DAY PRESET ENDPOINTS ---

function tcc_save_single_day_preset() {
    if ( ! is_user_logged_in() ) wp_die();
    
    $dest = trim(sanitize_text_field($_POST['destination']));
    $preset_name = trim(sanitize_text_field($_POST['preset_name']));
    
    $title = trim(sanitize_text_field($_POST['title']));
    
    // IMPORTANT: Allow rich text HTML tags for the Day Descriptions
    $desc = wp_kses_post(wp_unslash($_POST['desc']));
    
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