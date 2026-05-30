<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_Frontend_Ajax {
    public static function init() {
        add_action('wp_ajax_nopriv_tcc_front_submit', array(__CLASS__, 'process_submission'));
        add_action('wp_ajax_tcc_front_submit', array(__CLASS__, 'process_submission'));

        // Partial Lead Email Processor
        add_action('wp_ajax_nopriv_tcc_front_submit_lead_only', array(__CLASS__, 'process_partial_lead'));
        add_action('wp_ajax_tcc_front_submit_lead_only', array(__CLASS__, 'process_partial_lead'));

        // Direct PDF download / share from the frontend form success screen
        add_action('wp_ajax_nopriv_tcc_front_generate_pdf', array(__CLASS__, 'generate_pdf_by_id'));
        add_action('wp_ajax_tcc_front_generate_pdf', array(__CLASS__, 'generate_pdf_by_id'));
    }

    /**
     * Generates a PDF for a given tcc_quote post ID by:
     *  1. Fetching the rendered quote page server-side (wp_remote_get)
     *  2. Extracting the existing #tcc_hidden_pdf_template div via DOMDocument
     *  3. Piping it through the existing tcc_generate_server_pdf() so output
     *     is byte-identical to the Download button on the quote page itself.
     */
    public static function generate_pdf_by_id() {
        $quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0;

        if (!$quote_id || get_post_type($quote_id) !== 'tcc_quote') {
            status_header(400);
            wp_die('Invalid quote.');
        }

        $quote_url = get_permalink($quote_id);
        if (!$quote_url) {
            status_header(500);
            wp_die('Quote permalink not available.');
        }

        // Internal HTTP request to render the quote page exactly as a visitor would see it.
        $response = wp_remote_get($quote_url, array(
            'timeout'     => 30,
            'sslverify'   => false,
            'redirection' => 3,
            'user-agent'  => 'TCC-PDF-Fetcher/1.0',
        ));

        if (is_wp_error($response)) {
            status_header(500);
            wp_die('Could not fetch quote page: ' . esc_html($response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            status_header(500);
            wp_die('Could not fetch quote page. HTTP ' . intval($code));
        }

        $page_html = wp_remote_retrieve_body($response);
        if (empty($page_html)) {
            status_header(500);
            wp_die('Empty quote page response.');
        }

        // Extract the hidden PDF template node robustly using DOMDocument.
        if (!class_exists('DOMDocument')) {
            status_header(500);
            wp_die('DOMDocument not available on this server.');
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // Force UTF-8 so emojis / Devanagari etc. survive the parse
        $dom->loadHTML('<?xml encoding="UTF-8">' . $page_html);
        libxml_clear_errors();

        $node = $dom->getElementById('tcc_hidden_pdf_template');
        if (!$node) {
            status_header(500);
            wp_die('PDF template not found on quote page. Please open the quotation link and use the Download button on that page instead.');
        }

        $pdf_html = '';
        foreach ($node->childNodes as $child) {
            $pdf_html .= $dom->saveHTML($child);
        }

        if (empty(trim(strip_tags($pdf_html)))) {
            status_header(500);
            wp_die('Extracted PDF content is empty.');
        }

        // Build a friendly filename — Quotation-ClientName-#ID
        $client_name = trim((string) get_post_meta($quote_id, '_tcc_client_name', true));
        if (!empty($client_name)) {
            $title = 'Quotation-' . $client_name . '-' . $quote_id;
        } else {
            $title = 'Quotation-' . $quote_id;
        }

        // Re-use the existing dompdf renderer. It reads $_POST['html'] (urlencoded) and $_POST['title'].
        $_POST['html']  = rawurlencode($pdf_html);
        $_POST['title'] = sanitize_file_name($title);

        if (function_exists('tcc_generate_server_pdf')) {
            tcc_generate_server_pdf(); // streams the PDF and exits
        }

        status_header(500);
        wp_die('PDF generator not available.');
    }

    public static function process_partial_lead() {
        $name    = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email   = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        // If all contact details are blank, skip sending the partial lead email
        if (empty($name) && empty($phone) && empty($email)) {
            wp_send_json_success();
            wp_die();
        }

        try {
            $admin_email = get_option('tcc_admin_email', get_option('admin_email'));
            $subject = "🔥 Partial Website Lead" . (!empty($name) ? ": " . $name : "");
            $message = "A user just started filling out the booking form on your website.\n\n";
            if (!empty($name)) $message .= "Name: $name\n";
            if (!empty($phone)) $message .= "Phone: $phone\n";
            if (!empty($email)) $message .= "Email: $email\n\n";
            $message .= "They are currently navigating the tour selection and pricing steps. If they abandon the form and do not complete the final submission, you can follow up with them using these contact details.";
            
            @wp_mail($admin_email, $subject, $message);
        } catch (Exception $e) {}

        wp_send_json_success();
        wp_die();
    }

    public static function process_submission() {
        $tour_id = isset($_POST['tour_id']) ? intval($_POST['tour_id']) : 0;
        $name    = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email   = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $date    = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $pax     = isset($_POST['pax']) ? $_POST['pax'] : array();
        
        $tour_title = get_the_title($tour_id);
        $total      = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : 0;

        $dbl = isset($pax['double']) ? intval($pax['double']) : 0;
        $tpl = isset($pax['triple']) ? intval($pax['triple']) : 0;
        $sgl = isset($pax['single']) ? intval($pax['single']) : 0;
        $chl = isset($pax['child']) ? intval($pax['child']) : 0;
        $inf = isset($pax['infant']) ? intval($pax['infant']) : 0;
        $total_pax = $dbl + $tpl + $sgl + $chl + $inf;

        // =========================================================
        // 1. AUTO-GENERATE THE QUOTE IN THE BACKEND
        // =========================================================
        $quote_url = '';
        
        // Hide the name in the Quote if it was left blank
        $quote_title = !empty($name) ? $name . ' - ' . $tour_title . ' (Website Lead)' : $tour_title . ' (Website Lead)';
        
        $post_id = wp_insert_post(array(
            'post_title'  => $quote_title,
            'post_type'   => 'tcc_quote',
            'post_status' => 'publish',
        ));

        if (!is_wp_error($post_id)) {
            $dates_json = get_post_meta($tour_id, '_tcc_fd_dates', true);
            $end_date = '';
            $date_hotel_cat = ''; // Fetch specific category for this date
            if($dates_json) {
                $d_arr = json_decode($dates_json, true);
                if(is_array($d_arr)) {
                    foreach($d_arr as $d) {
                        if($d['start_date'] == $date) { 
                            $end_date = $d['end_date']; 
                            $date_hotel_cat = !empty($d['hotel_cat']) ? $d['hotel_cat'] : '';
                            break; 
                        }
                    }
                }
            }

            update_post_meta($post_id, '_tcc_quote_type', 'fixed_departure');
            update_post_meta($post_id, '_tcc_fixed_tour_id', $tour_id);
            update_post_meta($post_id, '_tcc_start_date', $date);
            update_post_meta($post_id, '_tcc_end_date', $end_date);
            update_post_meta($post_id, '_tcc_client_name', $name);
            update_post_meta($post_id, '_tcc_client_phone', $phone);
            update_post_meta($post_id, '_tcc_client_email', $email);
            update_post_meta($post_id, '_tcc_total_pax', $total_pax);
            update_post_meta($post_id, '_tcc_fd_pax_breakdown', $pax);
            update_post_meta($post_id, '_tcc_grand_total', $total);
            update_post_meta($post_id, '_tcc_quote_status', 'Pending');
            
            $inclusions = get_post_meta($tour_id, '_tcc_inclusions', true);
            $exclusions = get_post_meta($tour_id, '_tcc_exclusions', true);
            $payment_terms = get_post_meta($tour_id, '_tcc_payment_terms', true);
            
            // Assign specific date category, or fallback to general setting
            $hotel_cat = !empty($date_hotel_cat) ? $date_hotel_cat : (get_post_meta($tour_id, '_tcc_hotel_cat', true) ?: 'Group Standard');
            
            $transport_details = get_post_meta($tour_id, '_tcc_transport_details', true) ?: 'Group Transport Included';
            
            $days = 1;
            if (!empty($date) && !empty($end_date)) {
                $datetime1 = new DateTime($date);
                $datetime2 = new DateTime($end_date);
                $days = $datetime1->diff($datetime2)->days + 1;
            }

            $rooms_double = ceil($dbl / 2);
            $rooms_triple = ceil($tpl / 3);
            $total_rooms = $rooms_double + $rooms_triple + $sgl;

            $globals = get_option('tcc_global_settings', array('gst' => 5, 'pt' => 10, 'pg' => 3));
            $gst_pct = floatval($globals['gst']);
            $base_total = $total / (1 + ($gst_pct / 100));
            $gst_amount = $total - $base_total;

            $itinerary_json = get_post_meta($tour_id, '_tcc_fd_itinerary_data', true);
            $itinerary_data = json_decode($itinerary_json, true);
            if (is_string($itinerary_data)) { $itinerary_data = json_decode($itinerary_data, true); }
            $fd_destination = get_post_meta($tour_id, '_tcc_fd_destination', true);
            
            // Smart fallback: Check if the saved itinerary is an array but empty inside
            $is_empty_itin = true;
            if (!empty($itinerary_data['itinerary'])) { $is_empty_itin = false; }
            elseif (!empty($itinerary_data[0])) { $is_empty_itin = false; }

            if (empty($itinerary_data) || $is_empty_itin) {
                $fd_preset = get_post_meta($tour_id, '_tcc_fd_itinerary_preset', true);
                if ($fd_destination && $fd_preset) {
                    $all_presets = get_option('tcc_itinerary_presets', array());
                    if (isset($all_presets[$fd_destination][$fd_preset])) { $itinerary_data = $all_presets[$fd_destination][$fd_preset]; }
                }
            }

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
                        
                        $row_cat = !empty($itinerary_data['routing_stay_categories'][$idx]) ? trim($itinerary_data['routing_stay_categories'][$idx]) : '';
                        
                        // STRICT FILTER: If the master row has a category and it doesn't match the selected departure category, SKIP IT!
                        if (!empty($hotel_cat) && !empty($row_cat) && strcasecmp($row_cat, $hotel_cat) !== 0) {
                            continue; 
                        }
                        
                        $cat = !empty($row_cat) ? $row_cat : $hotel_cat;
                        $hotels_str = !empty($itinerary_data['routing_stay_hotels'][$idx]) ? $itinerary_data['routing_stay_hotels'][$idx] : '';
                        $nights = !empty($itinerary_data['routing_stay_nights'][$idx]) ? $itinerary_data['routing_stay_nights'][$idx] : 1;
                        $room = !empty($itinerary_data['routing_stay_room_types'][$idx]) ? $itinerary_data['routing_stay_room_types'][$idx] : 'Default';
                        
                        $options = array();
                        if ($place !== 'No Hotel' && $place !== 'No Hotel Required') {
                            $hotel_arr = array_filter(array_map('trim', explode(',', $hotels_str)));
                            
                            if (!empty($hotel_arr)) {
                                foreach($hotel_arr as $h_name) {
                                    // Strictly use typed name, try to fetch link if exists
                                    $hr = null;
                                    if ($fd_destination) {
                                        $hr = $wpdb->get_row($wpdb->prepare("SELECT hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $fd_destination, $place, $cat, $h_name));
                                    }
                                    $options[] = array('name' => $h_name, 'link' => ($hr && !empty($hr->hotel_website)) ? $hr->hotel_website : '');
                                }
                            } elseif ($fd_destination) {
                                // Fallback ONLY if nothing was typed
                                $hotels = $wpdb->get_results($wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $fd_destination, $place, $cat));
                                if ($hotels) { foreach($hotels as $h) { $options[] = array('name' => $h->hotel_name, 'link' => $h->hotel_website); } }
                            }
                        }
                        
                        if (empty($options) && $place !== 'No Hotel' && $place !== 'No Hotel Required') { 
                            $options[] = array('name' => 'Group Standard Hotel', 'link' => ''); 
                        }
                        
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
                'grand_total' => $total,
                'per_person' => $total_pax > 0 ? ($base_total / $total_pax) : 0,
                'per_person_with_gst' => $total_pax > 0 ? ($total / $total_pax) : 0,
                'gst' => round($gst_amount, 2), 
                'summary' => array(
                    'client_name' => $name, 'client_phone' => $phone, 'client_email' => $email,
                    'destination' => $tour_title . ' (Group)',
                    'start_date' => $date, 'end_date' => $end_date, 'days' => $days,
                    'pax' => $dbl + $tpl + $sgl, 'child_6_12' => $chl, 'child' => $inf,
                    'rooms' => max(1, $total_rooms), 'extra_beds' => 0,
                    'transport_string' => $transport_details,
                    'pickup' => get_post_meta($tour_id, '_tcc_pickup', true) ?: 'Designated Point',
                    'drop' => get_post_meta($tour_id, '_tcc_drop', true) ?: 'Designated Point',
                    'hotel_cat' => $hotel_cat, 'inclusions' => $inclusions, 'exclusions' => $exclusions,
                    'payment_terms' => $payment_terms, 'important_note' => get_post_meta($tour_id, '_tcc_important_note', true),
                    'why_choose_us' => get_post_meta($tour_id, '_tcc_why_choose_us', true),
                    'essential_guidelines' => get_post_meta($tour_id, '_tcc_essential_guidelines', true),
                    'head_office' => get_post_meta($tour_id, '_tcc_head_office', true),
                    'dynamic_route' => $dynamic_route_string,
                    'itinerary' => $final_itinerary, 'itinerary_desc' => $final_itinerary_desc,
                    'itinerary_image' => $final_itinerary_image,
                    'itinerary_stay_places' => $final_itinerary_stay, 'stays' => $final_stays,
                    'discount_amount' => 0, 'gst_pct' => $gst_pct,
                    'total_base_price' => $base_total
                ),
                'raw' => array()
            );

            update_post_meta($post_id, '_tcc_quote_json_data', wp_slash(wp_json_encode($quote_json_data)));
            $quote_url = get_permalink($post_id);
        }

        // =========================================================
        // 2. GENERATE WHATSAPP URL
        // =========================================================
        $wa_number = get_option('tcc_wa_number', '');
        $wa_text = "Hi, I am interested in the group tour: *$tour_title*.\n\n";
        $wa_text .= "*Date:* $date\n";
        $wa_text .= "*Travelers:* $total_pax \n";
        $wa_text .= "*Quoted Price:* ₹$total\n";
        if (!empty($name)) { $wa_text .= "*Name:* $name\n"; }
        if (!empty($phone)) { $wa_text .= "*Phone:* $phone\n"; }
        if($quote_url) { $wa_text .= "\n*My Quotation Link:*\n$quote_url\n"; }
        
        $clean_wa_number = preg_replace('/[^0-9]/', '', $wa_number);
        $wa_url = "https://wa.me/" . $clean_wa_number . "?text=" . rawurlencode($wa_text);

        // =========================================================
        // 3. SEND ADMIN EMAIL
        // =========================================================
        // Only send the email if at least ONE contact field was filled out
        if (!empty($name) || !empty($phone) || !empty($email)) {
            try {
                $admin_email = get_option('tcc_admin_email', get_option('admin_email'));
                $subject = "New Website Booking Lead" . (!empty($name) ? ": " . $name : "");
                $message = "You have received a new booking from the website!\n\n";
                $message .= "Tour: $tour_title\nDate: $date\n";
                if (!empty($name)) $message .= "Name: $name\n";
                if (!empty($phone)) $message .= "Phone: $phone\n";
                if (!empty($email)) $message .= "Email: $email\n";
                $message .= "Quoted Total: ₹$total\n";
                if($quote_url) { $message .= "\nView Generated Quote Here: $quote_url\n"; }
                
                @wp_mail($admin_email, $subject, $message);
            } catch (Exception $e) {}
        }

        wp_send_json_success(array(
            'redirect_url' => $wa_url,
            'quote_url'    => $quote_url,
            'quote_id'     => isset($post_id) && !is_wp_error($post_id) ? intval($post_id) : 0,
        ));
        wp_die();
    }
}
TCC_Frontend_Ajax::init();