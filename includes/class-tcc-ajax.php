<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_tcc_calculate_trip', 'tcc_calculate_trip' );
add_action( 'wp_ajax_nopriv_tcc_calculate_trip', 'tcc_calculate_trip' );

add_action( 'wp_ajax_tcc_save_master_settings', 'tcc_save_master_settings' );
add_action( 'wp_ajax_tcc_save_pricing_settings', 'tcc_save_pricing_settings' );
add_action( 'wp_ajax_tcc_save_transport_settings', 'tcc_save_transport_settings' );

add_action( 'wp_ajax_tcc_fetch_hotel_names', 'tcc_fetch_hotel_names' );
add_action( 'wp_ajax_nopriv_tcc_fetch_hotel_names', 'tcc_fetch_hotel_names' ); 

add_action( 'wp_ajax_tcc_fetch_hotel_rate', 'tcc_fetch_hotel_rate' );
add_action( 'wp_ajax_tcc_fetch_transport_rate', 'tcc_fetch_transport_rate' );

add_action( 'wp_ajax_tcc_optimize_transport', 'tcc_optimize_transport' );
add_action( 'wp_ajax_nopriv_tcc_optimize_transport', 'tcc_optimize_transport' );

function tcc_optimize_transport() {
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
    global $wpdb;
    
    $destination  = trim(sanitize_text_field($_POST['destination']));
    $total_pax    = intval($_POST['total_pax']);
    $child_pax    = intval($_POST['child_pax']);
    $total_days   = intval($_POST['total_days']);
    $no_of_rooms  = intval($_POST['no_of_rooms']);
    $extra_beds   = intval($_POST['extra_beds']);
    $pickup_loc   = trim(sanitize_text_field($_POST['pickup_location']));
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
    $transports   = isset($_POST['transportation']) ? $_POST['transportation'] : array();
    $trans_qtys   = isset($_POST['transport_qty']) ? $_POST['transport_qty'] : array();

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
            
            $raw_hotels = isset($stay_hotels[$index]) ? sanitize_text_field($stay_hotels[$index]) : '';
            $selected_hotel_array = array_map('trim', explode(',', $raw_hotels));
            $selected_hotel_array = array_filter($selected_hotel_array); 

            if(empty($selected_hotel_array)) {
                $error_messages[] = "No Hotel selected for {$place}.";
                continue;
            }

            $primary_hotel = $selected_hotel_array[0];
            $hotel_rate = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $hotel_cat, $primary_hotel));

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
                    $hr = $wpdb->get_row( $wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $destination, $place, $hotel_cat, $h_name));
                    if($hr) {
                        $display_names[] = array('name' => $hr->hotel_name, 'link' => $hr->hotel_website);
                    }
                }

                $detailed_stay_info[] = array('place' => $place, 'nights' => $nights, 'options' => $display_names);
            } else {
                $error_messages[] = "Missing Rate ({$place} - {$hotel_cat} - {$primary_hotel}).";
            }
        }
    }

    if (!empty($error_messages)) { wp_send_json_error(array('errors' => implode(" <br> ", $error_messages))); wp_die(); }

    $actual_cost = $transport_cost + $hotel_cost;
    
    $override_profit = isset($_POST['override_profit']) && $_POST['override_profit'] !== '' ? floatval($_POST['override_profit']) : false;
    if ($override_profit !== false) {
        $total_profit = $override_profit; 
    } else {
        $total_profit = $profit_per_person * $total_pax; 
    }

    $calculated_basic_cost = $actual_cost + $total_profit;

    $manual_pp_override = isset($_POST['manual_pp_override']) && $_POST['manual_pp_override'] !== '' ? floatval($_POST['manual_pp_override']) : false;
    if ($manual_pp_override !== false) {
        $base_for_discount = $manual_pp_override * $total_pax;
    } else {
        $base_for_discount = $calculated_basic_cost;
    }

    $d1_type = sanitize_text_field($_POST['discount_1_type']);
    $d1_val  = isset($_POST['discount_1_value']) ? floatval($_POST['discount_1_value']) : 0;
    $d1_amt  = ($d1_type === 'flat') ? $d1_val : (($d1_type === 'percent') ? ($base_for_discount * ($d1_val / 100)) : 0);

    $d2_type = sanitize_text_field($_POST['discount_2_type']);
    $d2_val  = isset($_POST['discount_2_value']) ? floatval($_POST['discount_2_value']) : 0;
    $d2_amt  = ($d2_type === 'flat') ? $d2_val : (($d2_type === 'percent') ? ($base_for_discount * ($d2_val / 100)) : 0);

    $total_discount_amount = $d1_amt + $d2_amt;
    $total_after_discount = max(0, $base_for_discount - $total_discount_amount);
    
    $final_profit = $total_after_discount - $actual_cost;
    $per_person_basic = ($total_pax > 0) ? ($total_after_discount / $total_pax) : 0;
    $gst = $total_after_discount * 0.05;
    $grand_total = $total_after_discount + $gst;

    $summary_data = array(
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
        'hotel_cat'   => $hotel_cat,
        'stays'       => $detailed_stay_info,
        'inclusions'  => $dest_inclusions,
        'exclusions'  => $dest_exclusions,
        'payment_terms' => $dest_payment_terms,
        'final_profit' => round($final_profit, 2),
        'discount_amount' => round($total_discount_amount, 2),
        'transport_row_costs' => $transport_row_costs,
        'hotel_row_costs' => $hotel_row_costs,
        'surcharge_applied' => $surcharge_percent
    );

    $is_final = isset($_POST['generate_link']) ? 1 : 0;
    $permalink = '';
    if ($is_final) {
        $random_string = wp_generate_password(8, false); 
        $post_id = wp_insert_post(array(
            'post_type' => 'tcc_quote',
            'post_name' => $random_string,
            'post_title' => 'Quote ' . strtoupper($random_string),
            'post_content' => wp_json_encode(array(
                'per_person' => $per_person_basic,
                'gst' => $gst,
                'grand_total' => $grand_total,
                'summary' => $summary_data
            )),
            'post_status' => 'publish'
        ));
        $permalink = get_permalink($post_id);
    }

    wp_send_json_success(array(
        'per_person'  => round($per_person_basic, 2),
        'gst'         => round($gst, 2),
        'grand_total' => round($grand_total, 2),
        'summary_data'=> $summary_data,
        'permalink'   => $permalink
    ));
}

function tcc_fetch_hotel_names() {
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_hotel_rates';
    $dest  = trim(sanitize_text_field($_POST['dest']));
    $place = trim(sanitize_text_field($_POST['place']));
    $cat   = trim(sanitize_text_field($_POST['cat']));

    $results = $wpdb->get_col( $wpdb->prepare("SELECT DISTINCT hotel_name FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $dest, $place, $cat));
    wp_send_json_success($results);
}

function tcc_fetch_hotel_rate() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_hotel_rates';
    $dest  = trim(sanitize_text_field($_POST['destination']));
    $place = trim(sanitize_text_field($_POST['place']));
    $cat   = trim(sanitize_text_field($_POST['category']));
    
    // THE FIX: Added the missing closing parenthesis here
    $name  = trim(sanitize_text_field($_POST['hotel_name']));
    
    $existing = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $dest, $place, $cat, $name));
    if($existing) wp_send_json_success($existing); else wp_send_json_error();
}

function tcc_fetch_transport_rate() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    global $wpdb;
    $table = $wpdb->prefix . 'tcc_transport_rates';
    $dest   = trim(sanitize_text_field($_POST['destination']));
    $pickup = trim(sanitize_text_field($_POST['pickup']));
    $vehicle= trim(sanitize_text_field($_POST['vehicle']));
    $existing = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE destination = %s AND pickup_location = %s AND vehicle_type = %s LIMIT 1", $dest, $pickup, $vehicle));
    if($existing) wp_send_json_success($existing); else wp_send_json_error();
}

function tcc_save_master_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    $dest_name = trim(sanitize_text_field($_POST['master_dest_name']));
    $profit = floatval($_POST['master_profit']);
    $pickups = explode(',', sanitize_text_field($_POST['master_pickups']));
    $stays = explode(',', sanitize_text_field($_POST['master_stays']));
    $vehicles = explode(',', sanitize_text_field($_POST['master_vehicles']));
    $cats = explode(',', sanitize_text_field($_POST['master_hotel_cats']));

    $inclusions = sanitize_textarea_field($_POST['master_inclusions']);
    $exclusions = sanitize_textarea_field($_POST['master_exclusions']);
    $payment_terms = sanitize_textarea_field($_POST['master_payment_terms']);

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
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
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
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
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