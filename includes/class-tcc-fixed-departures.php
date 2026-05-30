<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_Fixed_Departures {
    
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_fixed_tour_cpt' ) );
        add_action( 'wp_ajax_tcc_save_fixed_tour', array( __CLASS__, 'ajax_save_tour' ) );
        add_action( 'wp_ajax_tcc_get_fixed_tours', array( __CLASS__, 'ajax_get_tours' ) );
        add_action( 'wp_ajax_tcc_generate_fixed_booking', array( __CLASS__, 'ajax_generate_booking' ) );
        add_action( 'wp_ajax_tcc_get_single_fixed_tour', array( __CLASS__, 'ajax_get_single_tour' ) );
        add_action( 'wp_ajax_tcc_delete_fixed_tour', array( __CLASS__, 'ajax_delete_tour' ) );
        add_action( 'wp_ajax_tcc_duplicate_fixed_tour', array( __CLASS__, 'ajax_duplicate_tour' ) );
    }

    public static function register_fixed_tour_cpt() {
        register_post_type( 'tcc_fixed_tour', array(
            'public' => false,
            'show_ui' => true,
            'label' => 'Fixed Departures',
            'supports' => array( 'title' )
        ));
    }

    public static function get_booked_seats( $tour_id, $start_date = '' ) {
        global $wpdb;
        $booked_seats = 0;
        
        $meta_query = array( array( 'key' => '_tcc_fixed_tour_id', 'value' => $tour_id ) );
        if ( !empty($start_date) ) { $meta_query[] = array( 'key' => '_tcc_start_date', 'value' => $start_date ); }

        $quotes = get_posts(array( 'post_type' => 'tcc_quote', 'meta_query' => $meta_query, 'posts_per_page' => -1, 'fields' => 'ids' ));

        foreach ($quotes as $quote_id) {
            $status = get_post_meta($quote_id, '_tcc_quote_status', true);
            if ($status !== 'Cancelled') {
                $payments = get_post_meta($quote_id, 'tcc_payments', true);
                $has_valid_payment = false;
                
                if (is_array($payments) && !empty($payments)) {
                    foreach($payments as $p) {
                        if (isset($p['method']) && $p['method'] !== 'Refund' && floatval($p['amount']) > 0) {
                            $has_valid_payment = true; break;
                        }
                    }
                } else {
                    $total_paid = get_post_meta($quote_id, '_tcc_total_received', true) ?: 0;
                    if ($total_paid > 0) { $has_valid_payment = true; }
                }

                if ($has_valid_payment) {
                    $pax = get_post_meta($quote_id, '_tcc_total_pax', true) ?: 0;
                    $booked_seats += intval($pax);
                }
            }
        }
        return $booked_seats;
    }

    public static function ajax_save_tour() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        
        $tour_id = isset($_POST['tour_id']) ? intval($_POST['tour_id']) : 0;
        $post_data = array('post_title' => sanitize_text_field($_POST['tour_name']), 'post_type' => 'tcc_fixed_tour', 'post_status' => 'publish');

        if ($tour_id > 0) {
            $post_data['ID'] = $tour_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (is_wp_error($post_id)) wp_send_json_error('Failed to save tour.');

        $fd_dates_json = isset($_POST['fd_dates']) ? wp_unslash($_POST['fd_dates']) : '[]';
        // Add wp_slash so WordPress doesn't corrupt the JSON array
        update_post_meta($post_id, '_tcc_fd_dates', wp_slash($fd_dates_json));

        update_post_meta($post_id, '_tcc_pickup', sanitize_text_field($_POST['pickup']));
        update_post_meta($post_id, '_tcc_drop', sanitize_text_field($_POST['drop']));
        update_post_meta($post_id, '_tcc_transport_details', sanitize_text_field($_POST['transport_details']));
        update_post_meta($post_id, '_tcc_head_office', wp_kses_post($_POST['head_office']));
        
        update_post_meta($post_id, '_tcc_inclusions', wp_kses_post($_POST['inclusions']));
        update_post_meta($post_id, '_tcc_exclusions', wp_kses_post($_POST['exclusions']));
        update_post_meta($post_id, '_tcc_payment_terms', wp_kses_post($_POST['payment_terms']));
        update_post_meta($post_id, '_tcc_important_note', wp_kses_post($_POST['important_note']));
        update_post_meta($post_id, '_tcc_why_choose_us', wp_kses_post($_POST['why_choose_us']));
        update_post_meta($post_id, '_tcc_essential_guidelines', wp_kses_post($_POST['essential_guidelines']));
        update_post_meta($post_id, '_tcc_fd_destination', sanitize_text_field($_POST['fd_destination']));
        
        $itinerary_data = isset($_POST['fd_itinerary_data']) ? wp_unslash($_POST['fd_itinerary_data']) : '';
        // Add wp_slash so WordPress doesn't corrupt the HTML inside the Itinerary JSON
        update_post_meta($post_id, '_tcc_fd_itinerary_data', wp_slash($itinerary_data));

        wp_send_json_success(array('message' => 'Fixed Departure Saved!', 'tour_id' => $post_id));
    }

    public static function ajax_get_tours() {
        $tours = get_posts(array('post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'publish'));
        $data = array();
        foreach ($tours as $tour) {
            $dates_json = get_post_meta($tour->ID, '_tcc_fd_dates', true);
            $dates = json_decode($dates_json, true);
            if (!is_array($dates)) $dates = array();

            $processed_dates = array();
            foreach($dates as $d) {
                $booked = self::get_booked_seats($tour->ID, $d['start_date']);
                $avail = max(0, intval($d['capacity']) - $booked);
                $processed_dates[] = array(
                    'start_date' => $d['start_date'],
                    'end_date'   => $d['end_date'],
                    'capacity'   => intval($d['capacity']),
                    'available'  => $avail,
					'hotel_cat'  => isset($d['hotel_cat']) ? $d['hotel_cat'] : '',
                    'prices' => array(
                        'double' => isset($d['price_double']) ? floatval($d['price_double']) : 0,
                        'triple' => isset($d['price_triple']) ? floatval($d['price_triple']) : 0,
                        'single' => isset($d['price_single']) ? floatval($d['price_single']) : 0,
                        'child'  => isset($d['price_child']) ? floatval($d['price_child']) : 0,
                        'infant' => isset($d['price_infant']) ? floatval($d['price_infant']) : 0
                    )
                );
            }
            $data[] = array( 'id' => $tour->ID, 'name' => $tour->post_title, 'dates' => $processed_dates );
        }
        wp_send_json_success($data);
    }

    public static function ajax_generate_booking() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );

        $tour_id = isset($_POST['tour_id']) ? intval($_POST['tour_id']) : 0;
        $selected_start_date = sanitize_text_field($_POST['selected_start_date']);
        $selected_end_date = sanitize_text_field($_POST['selected_end_date']);
        
        if (!$tour_id) wp_send_json_error(array('errors' => 'Invalid Tour ID selected.'));
        if (empty($selected_start_date)) wp_send_json_error(array('errors' => 'Please select a specific departure date.'));

        $dates_json = get_post_meta($tour_id, '_tcc_fd_dates', true);
        $dates = json_decode($dates_json, true);
        $date_prices = null;
        if (is_array($dates)) {
            foreach($dates as $d) {
                if ($d['start_date'] === $selected_start_date) { $date_prices = $d; break; }
            }
        }

        $price_double = isset($date_prices['price_double']) ? floatval($date_prices['price_double']) : 0;
        $price_triple = isset($date_prices['price_triple']) ? floatval($date_prices['price_triple']) : 0;
        $price_single = isset($date_prices['price_single']) ? floatval($date_prices['price_single']) : 0;
        $price_child  = isset($date_prices['price_child']) ? floatval($date_prices['price_child']) : 0;
        $price_infant = isset($date_prices['price_infant']) ? floatval($date_prices['price_infant']) : 0;

        $c_name = sanitize_text_field($_POST['client_name']);
        $c_phone = sanitize_text_field($_POST['client_phone']);
        $c_email = sanitize_email($_POST['client_email']);

        $pax_double = intval($_POST['pax_double']);
        $pax_triple = intval($_POST['pax_triple']);
        $pax_single = intval($_POST['pax_single']);
        $pax_child  = intval($_POST['pax_child']);
        $pax_infant = intval($_POST['pax_infant']);

        $total_pax = $pax_double + $pax_triple + $pax_single + $pax_child + $pax_infant;
        if ($total_pax <= 0) wp_send_json_error(array('errors' => 'Please enter at least 1 passenger.'));

        $total_cost = ($pax_double * $price_double) + ($pax_triple * $price_triple) + ($pax_single * $price_single) + ($pax_child * $price_child) + ($pax_infant * $price_infant);

        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_val = floatval($_POST['discount_val']);
        $discount_amount = 0;
        if ($discount_type === 'flat') { $discount_amount = $discount_val; } elseif ($discount_type === 'percent') { $discount_amount = $total_cost * ($discount_val / 100); }
        $grand_total = max(0, $total_cost - $discount_amount);

        $post_id = wp_insert_post(array('post_title' => $c_name . ' - ' . get_the_title($tour_id) . ' (Group)', 'post_type' => 'tcc_quote', 'post_status' => 'publish'));
        if (is_wp_error($post_id)) wp_send_json_error(array('errors' => 'Failed to generate booking link in database.'));

        update_post_meta($post_id, '_tcc_quote_type', 'fixed_departure');
        update_post_meta($post_id, '_tcc_fixed_tour_id', $tour_id);
        update_post_meta($post_id, '_tcc_start_date', $selected_start_date);
        update_post_meta($post_id, '_tcc_end_date', $selected_end_date);
        update_post_meta($post_id, '_tcc_client_name', $c_name);
        update_post_meta($post_id, '_tcc_client_phone', $c_phone);
        update_post_meta($post_id, '_tcc_client_email', $c_email);
        update_post_meta($post_id, '_tcc_total_pax', $total_pax);
        
        $pax_breakdown = array('double' => $pax_double, 'triple' => $pax_triple, 'single' => $pax_single, 'child' => $pax_child, 'infant' => $pax_infant);
        update_post_meta($post_id, '_tcc_fd_pax_breakdown', $pax_breakdown);

        update_post_meta($post_id, '_tcc_grand_total', $grand_total);
        update_post_meta($post_id, '_tcc_discount_amount', $discount_amount);
        update_post_meta($post_id, '_tcc_quote_status', 'Pending'); 

        $tour_title = get_the_title($tour_id);
        $inclusions = get_post_meta($tour_id, '_tcc_inclusions', true);
        $exclusions = get_post_meta($tour_id, '_tcc_exclusions', true);
        $payment_terms = get_post_meta($tour_id, '_tcc_payment_terms', true);
        
        $hotel_cat = '';
        if (isset($date_prices['hotel_cat']) && !empty($date_prices['hotel_cat'])) {
            $hotel_cat = sanitize_text_field($date_prices['hotel_cat']);
        }
        
        $transport_details = get_post_meta($tour_id, '_tcc_transport_details', true) ?: 'Group Transport Included';
        $important_note = get_post_meta($tour_id, '_tcc_important_note', true);
        $why_choose_us = get_post_meta($tour_id, '_tcc_why_choose_us', true);
        $essential_guidelines = get_post_meta($tour_id, '_tcc_essential_guidelines', true);
        $head_office = get_post_meta($tour_id, '_tcc_head_office', true);

        $days = 1;
        if (!empty($selected_start_date) && !empty($selected_end_date)) {
            $datetime1 = new DateTime($selected_start_date);
            $datetime2 = new DateTime($selected_end_date);
            $days = $datetime1->diff($datetime2)->days + 1;
        }

        $rooms_double = ceil($pax_double / 2);
        $rooms_triple = ceil($pax_triple / 3);
        $rooms_single = $pax_single; 
        $total_rooms = $rooms_double + $rooms_triple + $rooms_single;

        $globals = get_option('tcc_global_settings', array('gst' => 5, 'pt' => 10, 'pg' => 3));
        $gst_pct = floatval($globals['gst']);
        $gst_rate = $gst_pct / 100;
        
        $base_total = $grand_total / (1 + $gst_rate);
        $gst_amount = $grand_total - $base_total;

        $itinerary_json = get_post_meta($tour_id, '_tcc_fd_itinerary_data', true);
        $itinerary_data = json_decode($itinerary_json, true);
        if (is_string($itinerary_data)) { $itinerary_data = json_decode($itinerary_data, true); }
        $fd_destination = get_post_meta($tour_id, '_tcc_fd_destination', true);

        $final_itinerary = array(); $final_itinerary_desc = array(); $final_itinerary_image = array(); $final_itinerary_stay = array(); $final_stays = array(); $dynamic_route_string = '';
        if (!empty($itinerary_data['itinerary_routes'])) {
            $dynamic_route_arr = array();
            foreach($itinerary_data['itinerary_routes'] as $r) { $r = trim($r); if(!empty($r)) $dynamic_route_arr[] = $r; }
            $dynamic_route_string = implode(' → ', $dynamic_route_arr);
        }

        if ($itinerary_data) {
            if (!empty($itinerary_data['itinerary'])) { $final_itinerary = array_values((array)$itinerary_data['itinerary']); } elseif (!empty($itinerary_data[0])) { $final_itinerary = array_values((array)$itinerary_data); }
            if (!empty($itinerary_data['itinerary_desc'])) { $final_itinerary_desc = array_values((array)$itinerary_data['itinerary_desc']); }
            if (!empty($itinerary_data['itinerary_image'])) { $final_itinerary_image = array_values((array)$itinerary_data['itinerary_image']); }
            if (!empty($itinerary_data['stay_places'])) { $final_itinerary_stay = array_values((array)$itinerary_data['stay_places']); }

            global $wpdb;
            $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
            
            if (!empty($itinerary_data['routing_stay_places'])) {
                foreach($itinerary_data['routing_stay_places'] as $idx => $place) {
                    if (empty($place) || $place === 'Trip Ends') continue;
                    
                    // IF THE DATE HAS A SPECIFIC CATEGORY, FILTER OUT ROUTING ROWS THAT DON'T MATCH
                    $row_cat = !empty($itinerary_data['routing_stay_categories'][$idx]) ? $itinerary_data['routing_stay_categories'][$idx] : '';
                    if (!empty($hotel_cat) && !empty($row_cat) && $row_cat !== $hotel_cat) {
                        continue; 
                    }
                    
                    $cat = !empty($row_cat) ? $row_cat : $hotel_cat;
                    $hotels_str = !empty($itinerary_data['routing_stay_hotels'][$idx]) ? $itinerary_data['routing_stay_hotels'][$idx] : '';
                    $nights = !empty($itinerary_data['routing_stay_nights'][$idx]) ? $itinerary_data['routing_stay_nights'][$idx] : 1;
                    $room = !empty($itinerary_data['routing_stay_room_types'][$idx]) ? $itinerary_data['routing_stay_room_types'][$idx] : 'Default';
                    
                    $options = array();
                    if ($place !== 'No Hotel' && $place !== 'No Hotel Required' && $fd_destination) {
                        $hotel_arr = array_filter(array_map('trim', explode(',', $hotels_str)));
                        if (!empty($hotel_arr)) {
                            foreach($hotel_arr as $h_str) {
                                if (strpos($h_str, '::') !== false) {
                                    $parts = explode('::', $h_str);
                                    $options[] = array('name' => trim($parts[0]), 'link' => isset($parts[1]) ? trim($parts[1]) : '');
                                } else {
                                    $h_name = $h_str;
                                    $hr = $wpdb->get_row($wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $fd_destination, $place, $cat, $h_name));
                                    if ($hr) { $options[] = array('name' => $hr->hotel_name, 'link' => $hr->hotel_website); }
                                    else { $options[] = array('name' => $h_name, 'link' => ''); }
                                }
                            }
                        } 
                        if (empty($options)) {
                            $hotels = $wpdb->get_results($wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $fd_destination, $place, $cat));
                            if ($hotels) { foreach($hotels as $h) { $options[] = array('name' => $h->hotel_name, 'link' => $h->hotel_website); } }
                        }
                    }
                    if (empty($options) && $place !== 'No Hotel' && $place !== 'No Hotel Required') { $options[] = array('name' => 'Group Standard Hotel', 'link' => ''); }
                    $final_stays[] = array('place' => ($place === 'No Hotel') ? 'No Hotel Required' : $place, 'category' => $cat, 'nights' => $nights, 'options' => $options, 'room_type' => $room);
                }
            } elseif (!empty($itinerary_data['stay_places'])) {
                $grouped = array(); $current_stay = $final_itinerary_stay[0]; $count = 1;
                for ($i = 1; $i < count($final_itinerary_stay); $i++) { if ($final_itinerary_stay[$i] === $current_stay) { $count++; } else { $grouped[] = array('place' => $current_stay, 'nights' => $count); $current_stay = $final_itinerary_stay[$i]; $count = 1; } }
                $grouped[] = array('place' => $current_stay, 'nights' => $count);

                foreach ($grouped as $g) {
                    if (empty($g['place']) || $g['place'] === 'Trip Ends') continue;
                    $options = array();
                    if ($g['place'] !== 'No Hotel' && $fd_destination) {
                        $hotels = $wpdb->get_results($wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $fd_destination, $g['place'], $hotel_cat));
                        if ($hotels) { foreach($hotels as $h) { $options[] = array('name' => $h->hotel_name, 'link' => $h->hotel_website); } }
                    }
                    if (empty($options)) { $options[] = array('name' => 'Group Standard Hotel', 'link' => ''); }
                    $final_stays[] = array('place' => ($g['place'] === 'No Hotel') ? 'No Hotel Required' : $g['place'], 'category' => $hotel_cat, 'nights' => $g['nights'], 'options' => $options, 'room_type' => 'Default');
                }
            }
        }
        
        $quote_json_data = array(
            'grand_total' => $grand_total,
            'per_person' => $total_pax > 0 ? ($base_total / $total_pax) : 0,
            'per_person_with_gst' => $total_pax > 0 ? ($grand_total / $total_pax) : 0,
            'gst' => round($gst_amount, 2), 
            'summary' => array(
                'client_name' => $c_name, 'client_phone' => $c_phone, 'client_email' => $c_email,
                'destination' => $tour_title . ' (Group Departure)',
                'start_date' => $selected_start_date, 'end_date' => $selected_end_date, 'days' => $days,
                'pax' => $pax_double + $pax_triple + $pax_single, 'child_6_12' => $pax_child, 'child' => $pax_infant,
                'rooms' => max(1, $total_rooms), 'extra_beds' => 0,
                'transport_string' => $transport_details,
                'pickup' => get_post_meta($tour_id, '_tcc_pickup', true) ?: 'Designated Point',
                'drop' => get_post_meta($tour_id, '_tcc_drop', true) ?: 'Designated Point',
                'hotel_cat' => $hotel_cat, 'inclusions' => $inclusions, 'exclusions' => $exclusions,
                'payment_terms' => $payment_terms, 'important_note' => $important_note,
                'why_choose_us' => $why_choose_us, 'essential_guidelines' => $essential_guidelines,
                'head_office' => $head_office, 'dynamic_route' => $dynamic_route_string,
                'itinerary' => $final_itinerary, 'itinerary_desc' => $final_itinerary_desc,
                'itinerary_image' => $final_itinerary_image, 'itinerary_stay_places' => $final_itinerary_stay,
                'stays' => $final_stays, 'discount_amount' => $discount_amount,
                'gst_pct' => $gst_pct, 'pt_pct' => floatval($globals['pt']), 'pg_pct' => floatval($globals['pg']),
                'hide_travelers_rooms' => false, 'hide_pricing' => false,
                'total_base_price' => $base_total, 'per_person_excl_gst' => $total_pax > 0 ? ($base_total / $total_pax) : 0,
                'per_person_with_gst' => $total_pax > 0 ? ($grand_total / $total_pax) : 0
            ),
            'raw' => array()
        );
        
        update_post_meta($post_id, '_tcc_quote_json_data', wp_slash(wp_json_encode($quote_json_data)));

        $permalink = get_permalink($post_id);
        wp_send_json_success(array('permalink' => $permalink, 'quote_id' => $post_id, 'grand_total' => $grand_total, 'gst' => round($gst_amount, 2), 'summary_data' => $quote_json_data['summary'] ));
    }

    public static function ajax_get_single_tour() {
        $tour_id = intval($_POST['tour_id']);
        if(!$tour_id) wp_send_json_error('Invalid ID');

        $tour = get_post($tour_id);
        $data = array(
            'id' => $tour->ID,
            'name' => $tour->post_title,
            'fd_dates' => get_post_meta($tour->ID, '_tcc_fd_dates', true),
            'pickup' => get_post_meta($tour->ID, '_tcc_pickup', true),
            'drop' => get_post_meta($tour->ID, '_tcc_drop', true),
            'transport_details' => get_post_meta($tour->ID, '_tcc_transport_details', true),
            'head_office' => get_post_meta($tour->ID, '_tcc_head_office', true),
            'inclusions' => get_post_meta($tour->ID, '_tcc_inclusions', true),
            'exclusions' => get_post_meta($tour->ID, '_tcc_exclusions', true),
            'payment_terms' => get_post_meta($tour->ID, '_tcc_payment_terms', true),
            'important_note' => get_post_meta($tour->ID, '_tcc_important_note', true),
            'why_choose_us' => get_post_meta($tour->ID, '_tcc_why_choose_us', true),
            'essential_guidelines' => get_post_meta($tour->ID, '_tcc_essential_guidelines', true),
            'fd_destination' => get_post_meta($tour->ID, '_tcc_fd_destination', true),
        );
        
        $fd_itinerary_data = get_post_meta($tour->ID, '_tcc_fd_itinerary_data', true);
        $data['fd_itinerary_data'] = $fd_itinerary_data;
        wp_send_json_success($data);
    }

    public static function ajax_delete_tour() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        $tour_id = intval($_POST['tour_id']);
        wp_delete_post($tour_id, true);
        wp_send_json_success('Group Tour permanently deleted.');
    }

    public static function ajax_duplicate_tour() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        $tour_id = intval($_POST['tour_id']);
        if(!$tour_id) wp_send_json_error('Invalid ID');
        $tour = get_post($tour_id);
        $new_post = array('post_title' => $tour->post_title . ' (Copy)', 'post_type' => 'tcc_fixed_tour', 'post_status' => 'publish');
        $new_id = wp_insert_post($new_post);
        if(is_wp_error($new_id)) wp_send_json_error('Failed to duplicate');
        $meta = get_post_meta($tour_id);
        foreach($meta as $key => $values) { if(strpos($key, '_tcc_') === 0) { update_post_meta($new_id, $key, $values[0]); } }
        wp_send_json_success('Tour Duplicated Successfully');
    }
}
TCC_Fixed_Departures::init();