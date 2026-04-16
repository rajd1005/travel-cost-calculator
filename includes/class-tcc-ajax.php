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

add_action( 'wp_ajax_tcc_delete_quote', 'tcc_delete_quote' );
add_action( 'wp_ajax_tcc_update_quote_client', 'tcc_update_quote_client' );
add_action( 'wp_ajax_tcc_get_full_quote_data', 'tcc_get_full_quote_data' );

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

    $dp = array_fill(0, $pax + 1, INF);
    $choice = array_fill(0, $pax + 1, null);
    $dp[0] = 0;

    for ($i = 1; $i <= $pax; $i++) {
        foreach($vehicles as $v) {
            $cap = max(1, intval($v->capacity));
            $price = floatval($v->price_per_day);
            $prev_pax = max(0, $i - $cap); 
            if ($dp[$prev_pax] + $price < $dp[$i]) {
                $dp[$i] = $dp[$prev_pax] + $price;
                $choice[$i] = array('prev' => $prev_pax, 'vehicle' => $v->vehicle_type);
            }
        }
    }

    $mix = [];
    $curr = $pax;
    while ($curr > 0 && isset($choice[$curr])) {
        $v_name = $choice[$curr]['vehicle'];
        if (!isset($mix[$v_name])) $mix[$v_name] = 0;
        $mix[$v_name]++;
        $curr = $choice[$curr]['prev'];
    }

    $result = [];
    foreach($mix as $v_name => $qty) { $result[] = array('vehicle' => $v_name, 'qty' => $qty); }
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

    $destination  = trim(sanitize_text_field($_POST['destination']));
    $total_pax    = intval($_POST['total_pax']);
    $child_pax    = intval($_POST['child_pax']);
    $total_days   = intval($_POST['total_days']);
    $no_of_rooms  = intval($_POST['no_of_rooms']);
    $extra_beds   = intval($_POST['extra_beds']);
    $pickup_loc   = trim(sanitize_text_field($_POST['pickup_location']));
    $drop_loc     = isset($_POST['drop_location']) ? trim(sanitize_text_field($_POST['drop_location'])) : $pickup_loc;
    
    $hotel_cat    = trim(sanitize_text_field($_POST['hotel_category'])); 
    $start_date   = sanitize_text_field($_POST['start_date']);

    $end_date = '';
    if (!empty($start_date) && $total_days > 0) {
        $total_nights = $total_days - 1;
        $end_date = date('Y-m-d', strtotime($start_date . " + {$total_nights} days"));
    }

    $stay_places  = isset($_POST['stay_place']) ? $_POST['stay_place'] : array();
    $stay_hotels  = isset($_POST['stay_hotel']) ? $_POST['stay_hotel'] : array();
    $stay_nights  = isset($_POST['stay_nights']) ? $_POST['stay_nights'] : array();
    $stay_cats    = isset($_POST['stay_category']) ? $_POST['stay_category'] : array(); 

    $transports   = isset($_POST['transportation']) ? $_POST['transportation'] : array();
    $trans_qtys   = isset($_POST['transport_qty']) ? $_POST['transport_qty'] : array();

    $day_itinerary = isset($_POST['itinerary_day']) ? $_POST['itinerary_day'] : array();

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
    
    $profit_per_person = 0;
    if(isset($master_data[$destination]) && isset($master_data[$destination]['profit_per_person'])) {
        $profit_per_person = floatval($master_data[$destination]['profit_per_person']);
    }

    $dest_inclusions = isset($master_data[$destination]['inclusions']) ? $master_data[$destination]['inclusions'] : '';
    $dest_exclusions = isset($master_data[$destination]['exclusions']) ? $master_data[$destination]['exclusions'] : '';
    $dest_payment_terms = isset($master_data[$destination]['payment_terms']) ? $master_data[$destination]['payment_terms'] : '';

    $surcharge_percent = 0;
    if(!empty($start_date) && isset($master_data[$destination]['seasons']) && is_array($master_data[$destination]['seasons'])) {
        $trip_start_ts = strtotime($start_date);
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
        $first_rate = null;
        foreach ( $transports as $index => $veh ) {
            $qty = intval($trans_qtys[$index]);
            $vehicle = trim(sanitize_text_field($veh));

            $rate = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_transport WHERE destination = %s AND pickup_location = %s AND vehicle_type = %s", $destination, $pickup_loc, $vehicle));

            if ( $rate ) {
                if ($index === 0) $first_rate = $rate; 
                $row_base = ($rate->price_per_day * $total_days) * $qty;
                $row_final = $row_base * $surcharge_multiplier;
                
                $transport_cost += $row_final;
                $total_capacity += ($rate->capacity * $qty);
                $transport_details[$index] = "{$qty}x {$vehicle}";
                $transport_row_costs[$index] = $row_final;
            } else {
                $error_messages[] = "No Rate for {$vehicle}";
            }
        }
        
        if ($total_capacity < $total_pax && $total_pax > 0 && $first_rate) {
            $deficit = $total_pax - $total_capacity;
            $cap = max(1, intval($first_rate->capacity));
            $extra_cars = ceil($deficit / $cap);
            $trans_qtys[0] += $extra_cars;
            
            $extra_cost_base = ($first_rate->price_per_day * $total_days) * $extra_cars;
            $extra_cost_final = $extra_cost_base * $surcharge_multiplier;
            
            $transport_cost += $extra_cost_final;
            $total_capacity += ($cap * $extra_cars);
            
            $transport_details[0] = "{$trans_qtys[0]}x " . trim(sanitize_text_field($transports[0]));
            $transport_row_costs[0] += $extra_cost_final;
        }
    }
    
    $transport_summary_string = implode(", ", $transport_details);

    if ( !empty($stay_places) ) {
        foreach ( $stay_places as $index => $place_name ) {
            $nights = intval($stay_nights[$index]);
            $place = trim(sanitize_text_field($place_name));
            $row_cat = isset($stay_cats[$index]) && !empty($stay_cats[$index]) ? sanitize_text_field($stay_cats[$index]) : $hotel_cat;
            
            $raw_hotels = isset($stay_hotels[$index]) ? sanitize_text_field($stay_hotels[$index]) : '';
            $selected_hotel_array = array_map('trim', explode(',', $raw_hotels));
            $selected_hotel_array = array_filter($selected_hotel_array); 

            if(empty($selected_hotel_array)) {
                $error_messages[] = "No Hotel selected for {$place}.";
                continue;
            }

            $primary_hotel = $selected_hotel_array[0];
            $hotel_rate = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $row_cat, $primary_hotel));

            if ( $hotel_rate ) {
                $daily_room_cost      = $no_of_rooms * $hotel_rate->room_price;
                $daily_extra_bed_cost = $extra_beds * $hotel_rate->extra_bed_price;
                $daily_child_cost     = $child_pax * $hotel_rate->child_price;
                
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

    if (!empty($error_messages)) { wp_send_json_error(array('errors' => implode(" <br> ", $error_messages))); wp_die(); }

    $actual_cost = $transport_cost + $hotel_cost;
    
    // --- EXACT DISCOUNT & NET PROFIT MATHEMATICS ---
    $override_profit = isset($_POST['override_profit']) && $_POST['override_profit'] !== '' ? floatval($_POST['override_profit']) : false;
    $manual_pp_override = isset($_POST['manual_pp_override']) && $_POST['manual_pp_override'] !== '' ? floatval($_POST['manual_pp_override']) : false;

    $d1_type = sanitize_text_field($_POST['discount_1_type']);
    $d1_val  = isset($_POST['discount_1_value']) ? floatval($_POST['discount_1_value']) : 0;
    $d2_type = sanitize_text_field($_POST['discount_2_type']);
    $d2_val  = isset($_POST['discount_2_value']) ? floatval($_POST['discount_2_value']) : 0;

    $gst_rate = $gst_pct / 100;
    $pt_rate = $pt_pct / 100;
    $pg_rate = $pg_pct / 100;

    // STEP 1: Calculate the INITIAL Base Price (Before Discount)
    if ($manual_pp_override !== false) {
        $initial_base_price = $manual_pp_override * $total_pax;
    } else {
        if ($override_profit !== false) {
            $target_net_profit = $override_profit; 
        } else {
            $target_net_profit = $profit_per_person * $total_pax; 
        }
        
        $M = 1 + $gst_rate;
        $denominator = 1 - ($M * $pt_rate) - ($M * $pg_rate);
        if ($denominator <= 0) $denominator = 0.01; 
        
        // This is the base price required to hit target profit IF NO DISCOUNT is applied
        $initial_base_price = ($target_net_profit + $actual_cost) / $denominator;
    }

    // STEP 2: Calculate and Deduct Discount from the Initial Base Price
    $d1_amt  = ($d1_type === 'flat') ? $d1_val : (($d1_type === 'percent') ? ($initial_base_price * ($d1_val / 100)) : 0);
    $d2_amt  = ($d2_type === 'flat') ? $d2_val : (($d2_type === 'percent') ? ($initial_base_price * ($d2_val / 100)) : 0);
    $total_discount_amount = $d1_amt + $d2_amt;
    
    // This is the true Final Base Price that the rest of the math follows
    $discounted_base_price = max(0, $initial_base_price - $total_discount_amount);
    
    // STEP 3: Forward Calculate the rest based on the Discounted Base Price
    $per_person_excl_gst = ($total_pax > 0) ? ($discounted_base_price / $total_pax) : 0;
    
    $gst = $discounted_base_price * $gst_rate;
    $grand_total = $discounted_base_price + $gst; // This is Total Received from Client
    
    $per_person_inc_gst = ($total_pax > 0) ? ($grand_total / $total_pax) : 0;

    // Taxes recalculate downwards because the grand_total is lower due to the discount
    $prof_tax = $grand_total * $pt_rate;
    $pg_charge = $grand_total * $pg_rate;
    
    // Profit takes the hit
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
        'child'       => $child_pax,
        'days'        => $total_days,
        'rooms'       => $no_of_rooms,
        'extra_beds'  => $extra_beds,
        'transport_string' => $transport_summary_string,
        'corrected_trans_qtys' => $trans_qtys, 
        'pickup'      => $pickup_loc,
        'drop'        => $drop_loc,
        'hotel_cat'   => $hotel_cat,
        'stays'       => $detailed_stay_info,
        'inclusions'  => $dest_inclusions,
        'exclusions'  => $dest_exclusions,
        'payment_terms' => $dest_payment_terms,
        
        'actual_cost' => round($actual_cost, 2),
        'total_hotel_cost' => round($hotel_cost, 2),
        'total_trans_cost' => round($transport_cost, 2),

        'final_profit' => round($gross_profit, 2), // Gross Profit
        'prof_tax'    => round($prof_tax, 2),
        'pg_charge'   => round($pg_charge, 2),
        'net_profit'  => round($net_profit, 2),
        
        'discount_amount' => round($total_discount_amount, 2),
        'total_base_price' => round($discounted_base_price, 2), // The discounted base
        'gst_pct'     => $gst_pct,
        'pt_pct'      => $pt_pct,
        'pg_pct'      => $pg_pct,
        'per_person_excl_gst' => round($per_person_excl_gst, 2),
        'per_person_with_gst' => round($per_person_inc_gst, 2),
        
        'transport_row_costs' => $transport_row_costs,
        'hotel_row_costs' => $hotel_row_costs,
        'surcharge_applied' => $surcharge_percent,
        'itinerary'   => array_map('sanitize_text_field', $day_itinerary) 
    );

    $raw_form = array(
        'transports' => $transports,
        'trans_qtys' => $trans_qtys,
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
    );

    $is_final = isset($_POST['generate_link']) ? 1 : 0;
    $permalink = '';
    
    if ($is_final) {
        $post_content_json = wp_json_encode(array(
            'per_person' => round($per_person_excl_gst, 2), // Save strictly as EXCL GST
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

// ... Keep all other functions in tcc-ajax.php identical ...
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

    update_option('tcc_global_settings', array(
        'gst' => $gst,
        'pt' => $pt,
        'pg' => $pg
    ));
    wp_send_json_success(array('message' => 'Global Taxes & Fees Saved!'));
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

    $results = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT hotel_name FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $dest, $place, $cat));
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
    $profit = floatval($_POST['master_profit']);
    $pickups = explode(',', sanitize_text_field($_POST['master_pickups']));
    $stays = explode(',', sanitize_text_field($_POST['master_stays']));
    $vehicles = explode(',', sanitize_text_field($_POST['master_vehicles']));
    $cats = explode(',', sanitize_text_field($_POST['master_hotel_cats']));

    $inclusions = sanitize_textarea_field(wp_unslash($_POST['master_inclusions']));
    $exclusions = sanitize_textarea_field(wp_unslash($_POST['master_exclusions']));
    $payment_terms = sanitize_textarea_field(wp_unslash($_POST['master_payment_terms']));

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
        'profit_per_person' => $profit,
        'pickups' => array_map('trim', $pickups),
        'stay_places' => array_map('trim', $stays),
        'vehicles' => array_map('trim', $vehicles),
        'hotel_categories' => array_map('trim', $cats),
        'inclusions' => $inclusions,
        'exclusions' => $exclusions,
        'payment_terms' => $payment_terms,
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
    if(is_array($raw_days)) {
         $days = array_map('sanitize_text_field', wp_unslash($raw_days));
    } else {
         $days = array();
    }

    if(empty($preset_name) || empty($days)) { wp_send_json_error("Missing data"); wp_die(); }

    $presets = get_option('tcc_itinerary_presets', array());
    if(!isset($presets[$destination])) $presets[$destination] = array();
    
    $presets[$destination][$preset_name] = $days;
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

// --- PAYMENTS & QUOTE MGMT ENDPOINTS ---

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
    
    $payments = get_post_meta($post_id, 'tcc_payments', true);
    if(!is_array($payments)) $payments = [];
    
    $status = get_post_meta($post_id, 'tcc_lead_status', true);

    $total_paid = 0;
    $total_refunded = 0;
    $has_refund = false;

    foreach($payments as $p) { 
        if (isset($p['method']) && $p['method'] === 'Refund') {
            $total_refunded += floatval($p['amount']);
            $has_refund = true;
        } else {
            $total_paid += floatval($p['amount']); 
        }
    }
    
    $is_cancelled = ($has_refund || $status === 'Canceled');
    $retained_income = max(0, $total_paid - $total_refunded);
    $balance = $is_cancelled ? 0 : max(0, $grand_total - $total_paid);

    wp_send_json_success(array(
        'grand_total' => $grand_total,
        'total_paid' => $total_paid,
        'total_refunded' => $total_refunded,
        'retained_income' => $retained_income,
        'balance' => $balance,
        'is_cancelled' => $is_cancelled,
        'payments' => $payments
    ));
}

function tcc_add_payment() {
    if ( ! is_user_logged_in() ) wp_die();
    $post_id = intval($_POST['quote_id']);
    $amount = floatval($_POST['amount']);
    $date = sanitize_text_field($_POST['date']);
    $method = sanitize_text_field($_POST['method']);
    $ref = sanitize_text_field($_POST['ref']);

    if(!$post_id || empty($date)) wp_send_json_error("Invalid data");

    $payments = get_post_meta($post_id, 'tcc_payments', true);
    if(!is_array($payments)) $payments = [];

    $new_payment = array(
        'id' => uniqid('pmt_'),
        'amount' => $amount,
        'date' => $date,
        'method' => $method,
        'ref' => $ref
    );
    
    $payments[] = $new_payment;
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