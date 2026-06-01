<?php
// This file renders the public quotation link for your clients.
get_header(); 

// Securely retrieve the quote data from post meta (with fallback to old post_content for backward compatibility)
$meta_data = get_post_meta($post->ID, '_tcc_quote_json_data', true);
if (!empty($meta_data)) {
    $quote_data = json_decode($meta_data, true);
} else {
    $quote_data = json_decode($post->post_content, true);
}

// Get Global Settings
$global_settings = get_option('tcc_global_settings', array());

// Indian Currency Formatter for PHP
function tcc_format_inr($num) {
    $num = round($num, 2);
    $explrestunits = "";
    $numStr = (string)$num;
    $parts = explode('.', $numStr);
    $num = $parts[0];
    if(strlen($num)>3) {
        $lastthree = substr($num, strlen($num)-3, strlen($num));
        $restunits = substr($num, 0, strlen($num)-3); 
        $restunits = (strlen($restunits)%2 == 1) ? "0".$restunits : $restunits; 
        $expunit = str_split($restunits, 2);
        for($i=0; $i<sizeof($expunit); $i++) {
            if($i==0) $explrestunits .= ltrim($expunit[$i],"0").","; 
            else $explrestunits .= $expunit[$i].",";
        }
        $thecash = $explrestunits.$lastthree;
    } else {
        $thecash = $num;
    }
    $decimal = isset($parts[1]) ? '.' . str_pad($parts[1], 2, '0', STR_PAD_RIGHT) : '.00';
    return '₹' . $thecash . $decimal;
}

// Helper to convert Rich Text HTML to Plain Text for WhatsApp Copying
function tcc_html_to_wa($html, $bullet = "•") {
    if (empty(trim(wp_strip_all_tags($html)))) return "";
    $text = str_ireplace(array('<br>', '<br/>', '<br />'), "\n", $html);
    $text = str_ireplace('</p>', "\n\n", $text);
    $text = preg_replace('/<li[^>]*>/i', "\n" . $bullet . " ", $text);
    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text); // Clean excessive newlines
    return trim($text);
}

// Helper to force inline styles on text icons and emojis for PDF & Frontend
function tcc_clean_rte_html($html) {
    if (empty(trim(wp_strip_all_tags($html, true)))) return $html;
    
    // Match any image that is likely an emoji or icon based on URL or class
    $pattern_url = '/<img([^>]*src=[\'"]([^\'"]*(emoji|s\.w\.org|twimg|gstatic|twemoji|icons|cdn-icons|flaticon)[^\'"]*)[\'"][^>]*)>/i';
    $pattern_class = '/<img([^>]*class=[\'"][^\'"]*(emoji|icon)[^\'"]*[\'"][^>]*)>/i';
    
    $inline_style = '<img style="display:inline-block !important; width:16px !important; height:16px !important; margin:0 2px !important; vertical-align:-2px !important; border:none !important; box-shadow:none !important; background:none !important;" $1>';
    
    $html = preg_replace($pattern_url, $inline_style, $html);
    $html = preg_replace($pattern_class, $inline_style, $html);
    
    return $html;
}

// Function to safely turn newlines into HTML bullets OR output existing HTML cleanly
function tcc_render_bullets($text) {
    if (empty(trim(wp_strip_all_tags($text)))) return "<p style='font-size:13px; color:#64748b; margin:0;'>None specified.</p>";
    
    $text = tcc_clean_rte_html($text);
    
    // If it has HTML tags (from Rich Text Editor), render natively
    if (preg_match('/<(p|div|li|br|span|strong|em|u|h[1-6])[^>]*>/i', $text)) {
        return "<div class='tcc-rte-display' style='font-size:13px; line-height:1.5;'>" . $text . "</div>";
    }
    
    // Plain text fallback (for older quotes)
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $html = "<ul style='margin:0; padding-left:18px; font-size:13px; line-height:1.5; color:inherit;'>";
    foreach($lines as $line) if(trim($line) !== '') $html .= "<li style='margin-bottom:2px;'>" . esc_html(trim($line)) . "</li>";
    $html .= "</ul>";
    return $html;
}

// Function to safely turn HTML/Newlines into plain text WhatsApp bullets
function tcc_text_bullets($text, $bullet = "✓") {
    if (empty(trim(wp_strip_all_tags($text)))) return "None specified.";
    
    // If it has HTML tags (from Rich Text Editor), convert cleanly
    if (preg_match('/<(p|div|li|br|span|strong|em|u|h[1-6])[^>]*>/i', $text)) {
        return tcc_html_to_wa($text, $bullet);
    }

    // Plain text fallback (for older quotes)
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $res = "";
    foreach($lines as $line) if(trim($line) !== '') $res .= $bullet . " " . trim($line) . "\n";
    return trim($res);
}

if(!$quote_data) {
    echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Quotation Not Found or Expired.</h2></div>";
    get_footer();
    exit;
}

// --- DYNAMIC FALLBACK FOR FIXED DEPARTURES PRESETS ---
// Safely get the Tour ID
$tour_id = get_post_meta($post->ID, '_tcc_tour_id', true);
if (!$tour_id) { 
    $tour_id = get_post_meta($post->ID, '_tcc_fixed_tour_id', true); 
}

if ($tour_id) {
    // 1. RECOVER THE EXACT HOTEL CATEGORY FROM THE MASTER PARENT TOUR
    $start_date = isset($quote_data['summary']['start_date']) ? $quote_data['summary']['start_date'] : get_post_meta($post->ID, '_tcc_start_date', true);
    $parent_cat = '';
    
    if ($start_date) {
        $fd_dates_json = get_post_meta($tour_id, '_tcc_fd_dates', true);
        $fd_dates = json_decode($fd_dates_json, true);
        if (is_array($fd_dates)) {
            foreach ($fd_dates as $d) {
                if (isset($d['start_date']) && $d['start_date'] === $start_date) {
                    if (!empty($d['hotel_cat'])) {
                        $parent_cat = trim($d['hotel_cat']);
                    }
                    break;
                }
            }
        }
    }

    // FORCE APPLY the exact parent category if found, to fix old quotes 
    // that may have mistakenly stored the default master category incorrectly.
    if (!empty($parent_cat)) {
        $quote_data['summary']['hotel_cat'] = $parent_cat;
    }
    
    $h_cat = !empty($quote_data['summary']['hotel_cat']) ? trim($quote_data['summary']['hotel_cat']) : '';

    // FORCE CLEAR saved stays so it dynamically rebuilds with STRICT category filtering
    $quote_data['summary']['stays'] = array();

    // 2. Fallback for General Info
    if (empty($quote_data['summary']['inclusions'])) $quote_data['summary']['inclusions'] = get_post_meta($tour_id, '_tcc_inclusions', true);
    if (empty($quote_data['summary']['exclusions'])) $quote_data['summary']['exclusions'] = get_post_meta($tour_id, '_tcc_exclusions', true);
    if (empty($quote_data['summary']['payment_terms'])) $quote_data['summary']['payment_terms'] = get_post_meta($tour_id, '_tcc_payment_terms', true);
    if (empty($quote_data['summary']['essential_guidelines'])) $quote_data['summary']['essential_guidelines'] = get_post_meta($tour_id, '_tcc_essential_guidelines', true);
    if (empty($quote_data['summary']['important_note'])) $quote_data['summary']['important_note'] = get_post_meta($tour_id, '_tcc_important_note', true);
    if (empty($quote_data['summary']['why_choose_us'])) $quote_data['summary']['why_choose_us'] = get_post_meta($tour_id, '_tcc_why_choose_us', true);
    if (empty($quote_data['summary']['transport_string'])) $quote_data['summary']['transport_string'] = get_post_meta($tour_id, '_tcc_transport_details', true);
    if (empty($quote_data['summary']['pickup'])) $quote_data['summary']['pickup'] = get_post_meta($tour_id, '_tcc_pickup', true);
    if (empty($quote_data['summary']['drop'])) $quote_data['summary']['drop'] = get_post_meta($tour_id, '_tcc_drop', true);

    // 3. Fallback for Itinerary Data
    $itinerary_json = get_post_meta($tour_id, '_tcc_fd_itinerary_data', true);
    $itinerary_data = json_decode($itinerary_json, true);
    if (is_string($itinerary_data)) { $itinerary_data = json_decode($itinerary_data, true); }
    $fd_dest = get_post_meta($tour_id, '_tcc_fd_destination', true);
    
    $is_empty_itin = true;
    if (!empty($itinerary_data['itinerary'])) { $is_empty_itin = false; }
    elseif (!empty($itinerary_data[0])) { $is_empty_itin = false; }

    if (empty($itinerary_data) || $is_empty_itin) {
        $fd_preset = get_post_meta($tour_id, '_tcc_fd_itinerary_preset', true);
        if ($fd_dest && $fd_preset) {
            $all_presets = get_option('tcc_itinerary_presets', array());
            if (isset($all_presets[$fd_dest][$fd_preset])) {
                $itinerary_data = $all_presets[$fd_dest][$fd_preset];
            }
        }
    }
    
    if ($itinerary_data) {
        if (empty($quote_data['summary']['itinerary'])) {
            if (!empty($itinerary_data['itinerary'])) $quote_data['summary']['itinerary'] = array_values((array)$itinerary_data['itinerary']);
            elseif (!empty($itinerary_data[0])) $quote_data['summary']['itinerary'] = array_values((array)$itinerary_data);
        }
        if (empty($quote_data['summary']['itinerary_desc']) && !empty($itinerary_data['itinerary_desc'])) $quote_data['summary']['itinerary_desc'] = array_values((array)$itinerary_data['itinerary_desc']);
        if (empty($quote_data['summary']['itinerary_image']) && !empty($itinerary_data['itinerary_image'])) $quote_data['summary']['itinerary_image'] = array_values((array)$itinerary_data['itinerary_image']);
        if (empty($quote_data['summary']['itinerary_stay_places']) && !empty($itinerary_data['stay_places'])) $quote_data['summary']['itinerary_stay_places'] = array_values((array)$itinerary_data['stay_places']);

        // 4. Strict Dynamic Hotel Fetching
        global $wpdb;
        $table_hotels = $wpdb->prefix . 'tcc_hotel_rates';
        
        if (!empty($itinerary_data['routing_stay_places'])) {
            $final_stays = array();
            foreach($itinerary_data['routing_stay_places'] as $idx => $place) {
                if (empty($place) || $place === 'Trip Ends') continue;
                
                $row_cat = !empty($itinerary_data['routing_stay_categories'][$idx]) ? trim($itinerary_data['routing_stay_categories'][$idx]) : '';
                
                // STRICT FILTER: If the master row has a category and it doesn't match the selected departure category, SKIP IT!
                if (!empty($h_cat) && !empty($row_cat) && strcasecmp($row_cat, $h_cat) !== 0) {
                    continue; 
                }
                
                $cat = !empty($row_cat) ? $row_cat : $h_cat;
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
                            if ($fd_dest) {
                                $hr = $wpdb->get_row($wpdb->prepare("SELECT hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s AND hotel_name = %s LIMIT 1", $fd_dest, $place, $cat, $h_name));
                            }
                            $options[] = array('name' => $h_name, 'link' => ($hr && !empty($hr->hotel_website)) ? $hr->hotel_website : '');
                        }
                    } elseif ($fd_dest) {
                        // Fallback ONLY if nothing was typed
                        $hotels = $wpdb->get_results($wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $fd_dest, $place, $cat));
                        if ($hotels) { foreach($hotels as $h) { $options[] = array('name' => $h->hotel_name, 'link' => $h->hotel_website); } }
                    }
                }
                
                if (empty($options) && $place !== 'No Hotel' && $place !== 'No Hotel Required') {
                    $options[] = array('name' => 'Group Standard Hotel', 'link' => '');
                }

                $final_stays[] = array('place' => ($place === 'No Hotel') ? 'No Hotel Required' : $place, 'category' => $cat, 'nights' => $nights, 'options' => $options, 'room_type' => $room);
            }
            $quote_data['summary']['stays'] = $final_stays;
        } 
        elseif (!empty($itinerary_data['stay_places'])) {
            $final_stays = array();
            $grouped = array();
            $stays_arr = array_values((array)$itinerary_data['stay_places']);
            $current_stay = $stays_arr[0];
            $count = 1;
            for ($i = 1; $i < count($stays_arr); $i++) {
                if ($stays_arr[$i] === $current_stay) { $count++; } else { $grouped[] = array('place' => $current_stay, 'nights' => $count); $current_stay = $stays_arr[$i]; $count = 1; }
            }
            $grouped[] = array('place' => $current_stay, 'nights' => $count);

            foreach ($grouped as $g) {
                if (empty($g['place']) || $g['place'] === 'Trip Ends') continue;
                
                $options = array();
                if ($g['place'] !== 'No Hotel' && $fd_dest) {
                    $hotels = $wpdb->get_results($wpdb->prepare("SELECT hotel_name, hotel_website FROM $table_hotels WHERE destination = %s AND night_stay_place = %s AND hotel_category = %s", $fd_dest, $g['place'], $h_cat));
                    if ($hotels) { foreach($hotels as $h) { $options[] = array('name' => $h->hotel_name, 'link' => $h->hotel_website); } }
                }
                if (empty($options)) { $options[] = array('name' => 'Group Standard Hotel', 'link' => ''); }

                $final_stays[] = array('place' => ($g['place'] === 'No Hotel') ? 'No Hotel Required' : $g['place'], 'category' => $h_cat, 'nights' => $g['nights'], 'options' => $options, 'room_type' => 'Default');
            }
            $quote_data['summary']['stays'] = $final_stays;
        }
    }
}
// -----------------------------------------------------

$d = $quote_data['summary'];
// Make sure it reads the night stays accurately
$raw_stays = isset($quote_data['raw']['itinerary_stay_place']) ? $quote_data['raw']['itinerary_stay_place'] : (isset($d['itinerary_stay_places']) ? $d['itinerary_stay_places'] : []);

$hide_tr = !empty($d['hide_travelers_rooms']) ? true : false;
$hide_pr = !empty($d['hide_pricing']) ? true : false;

// Safe Route Fetcher (Supports Old Quotes and New Dynamic Route Quotes)
$display_route = !empty($d['dynamic_route']) ? $d['dynamic_route'] : (!empty($d['short_itinerary']) ? $d['short_itinerary'] : '');

// ----------------------------------------------------------------------
// PAYMENT & INVOICE LOGIC (With Refund, Vendor Handling, & Post-Quote Discount)
// ----------------------------------------------------------------------
$payments = get_post_meta($post->ID, 'tcc_payments', true);
if(!is_array($payments)) $payments = [];
$status = get_post_meta($post->ID, 'tcc_lead_status', true);

$gst_pct = isset($d['gst_pct']) ? floatval($d['gst_pct']) / 100 : 0.05;
$pt_pct = isset($d['pt_pct']) ? floatval($d['pt_pct']) / 100 : 0;
$pg_pct = isset($d['pg_pct']) ? floatval($d['pg_pct']) / 100 : 0;

$total_paid = 0;
$total_refunded = 0;
$vendor_paid = 0;
$tax_waiver = 0;
$has_refund = false;

foreach($payments as $p) { 
    $amt = floatval($p['amount']);
    if (isset($p['method']) && $p['method'] === 'Refund') {
        $total_refunded += $amt;
        $has_refund = true;
    } elseif (isset($p['method']) && $p['method'] === 'Direct to Vendor') {
        $vendor_paid += $amt;
        $tax_waiver += ($amt * $gst_pct) + ($amt * (1 + $gst_pct) * ($pt_pct + $pg_pct));
    } else {
        $total_paid += $amt; 
    }
}

$is_cancelled = ($has_refund || $status === 'Canceled');
$retained_income = max(0, $total_paid - $total_refunded);

$grand_total = floatval($quote_data['grand_total']);

// Fetch Post Quote Discount
$post_quote_discount = floatval(get_post_meta($post->ID, 'tcc_post_quote_discount', true));

$balance = $is_cancelled ? 0 : max(0, $grand_total - $post_quote_discount - $total_paid - $vendor_paid - $tax_waiver);

$doc_title = "Travel Quotation";
$doc_color = "#0f172a"; 
$badge_bg = "#334155";

if ($is_cancelled) {
    $doc_title = "Booking Canceled";
    $doc_color = "#b91c1c"; 
    $badge_bg = "#991b1b";
} elseif (count($payments) > 0) {
    if ($balance <= 0) {
        $doc_title = "Final Paid Invoice";
        $doc_color = "#15803d"; 
        $badge_bg = "#166534";
    } else {
        $doc_title = "Advance Receipt & Confirmation";
        $doc_color = "#1d4ed8"; 
        $badge_bg = "#1e3a8a";
    }
}

// ----------------------------------------------------------------------
// PREPARE RAW TEXT STRINGS & WA API LINKS FOR THE BUTTONS
// ----------------------------------------------------------------------

$wa_phone = isset($d['client_phone']) ? preg_replace('/[^0-9]/', '', $d['client_phone']) : '';
$wa_api_base = !empty($wa_phone) ? "https://wa.me/{$wa_phone}?text=" : "https://wa.me/?text=";

$drop_txt = !empty($d['drop']) ? $d['drop'] : $d['pickup'];

$child_6_12_disp = isset($d['child_6_12']) ? $d['child_6_12'] : 0;

$txt_trip = "*Trip Details*\n";
if (!empty($d['start_date'])) {
    $txt_trip .= "Start Date: " . date('d M Y', strtotime($d['start_date'])) . "\nEnd Date: " . date('d M Y', strtotime($d['end_date'])) . "\n";
}
$txt_trip .= "Duration: {$d['days']} Days / " . ($d['days'] - 1) . " Nights\n";
if (!$hide_tr) {
    $txt_trip .= "Travelers: {$d['pax']} Adults, {$child_6_12_disp} Child (6-12), {$d['child']} Infants\nRooms & Beds: {$d['rooms']} Rooms / {$d['extra_beds']} Extra Beds\n";
}
$txt_trip .= "Hotel Category: {$d['hotel_cat']}\nTransport: " . strip_tags($d['transport_string']) . " (Pickup: {$d['pickup']} | Drop: {$drop_txt})";


$txt_itin = "*Itinerary Details*\n";
if(!empty($display_route)) {
    $txt_itin .= "Route: " . $display_route . "\n\n";
}
if(!empty($d['itinerary'])){
    foreach($d['itinerary'] as $idx => $t){
        if(trim($t)) {
            $txt_itin .= "*Day ".($idx+1).":* " . trim($t) . "\n";
            if (!empty($d['itinerary_desc'][$idx])) {
                $txt_itin .= tcc_html_to_wa($d['itinerary_desc'][$idx], '•') . "\n";
            }
            if (!empty($raw_stays[$idx])) {
                $txt_itin .= "Night Stay: " . trim($raw_stays[$idx]) . "\n";
            }
            $txt_itin .= "\n";
        }
    }
} else {
    $txt_itin .= "No itinerary provided.\n";
}

$txt_hotels = "*Hotels Selected*\n";
if(!empty($d['stays'])){
    foreach($d['stays'] as $stay){
        if($stay['place'] === 'No Hotel Required') {
            $txt_hotels .= "- No Hotel Required ({$stay['nights']}N)\n";
        } else {
            $opts = [];
            foreach($stay['options'] as $opt){
                $str = $opt['name'];
                if(!empty($opt['link'])) $str .= " (".$opt['link'].")";
                $opts[] = $str;
            }
            $catText = !empty($stay['category']) && $stay['category'] !== '-' ? "[{$stay['category']}] " : "";
            $room_txt = isset($stay['room_type']) && $stay['room_type'] !== 'Default' ? $stay['room_type'] : 'Deluxe Room';
            $txt_hotels .= "- {$stay['place']} ({$stay['nights']}N): {$catText}" . implode(' OR ', $opts) . " ({$room_txt}) / Similar\n";
        }
    }
} else {
    $txt_hotels .= "No hotels selected.\n";
}

// Map the HTML inputs to WA text cleanly utilizing specific emojis
$txt_inc = "*Inclusions in Package*\n" . tcc_text_bullets($d['inclusions'], '✅');
$txt_exc = "*Exclusions from Package*\n" . tcc_text_bullets($d['exclusions'], '❌');
$txt_pay = "*Payment Terms*\n" . tcc_text_bullets($d['payment_terms'], '💳');
$txt_guide = "*Essential Travel Guidelines*\n" . tcc_text_bullets(isset($d['essential_guidelines']) ? $d['essential_guidelines'] : '', '📋');

$gst_pct_disp = isset($d['gst_pct']) ? $d['gst_pct'] : 5;
$pp_gst = isset($quote_data['per_person_with_gst']) ? $quote_data['per_person_with_gst'] : ($grand_total / max(1, $d['pax']));

$discount_amt = floatval($d['discount_amount']);

// --- STRICT BACK CALCULATION ---
$calc_gst_decimal = floatval($gst_pct_disp) / 100;
$calculated_base = $grand_total / (1 + $calc_gst_decimal);
$calculated_gst = $grand_total - $calculated_base;

$txt_price = "*Pricing Summary*\n";

if($discount_amt > 0) {
    $txt_price .= "Total Base: " . tcc_format_inr($calculated_base + $discount_amt) . "\n";
    $txt_price .= "Discount Applied: -" . tcc_format_inr($discount_amt) . "\n";
} else {
    $txt_price .= "Total Base: " . tcc_format_inr($calculated_base) . "\n";
}

$txt_price .= "GST ({$gst_pct_disp}%): " . tcc_format_inr($calculated_gst) . "\n";
$txt_price .= "------------------------\n";
$txt_price .= "*Total Package Value:* " . tcc_format_inr($grand_total) . "\n";

// Append Post-Quote Discount to WhatsApp copy string
if ($post_quote_discount > 0) {
    $txt_price .= "*Special Discount:* -" . tcc_format_inr($post_quote_discount) . "\n";
    $txt_price .= "*Final Package Value:* " . tcc_format_inr($grand_total - $post_quote_discount) . "\n";
}

if ($is_cancelled) {
    $txt_price .= "\n*BOOKING CANCELLED*\n";
    $txt_price .= "Total Paid: " . tcc_format_inr($total_paid) . "\n";
    $txt_price .= "Amount Refunded: " . tcc_format_inr($total_refunded) . "\n";
    $txt_price .= "Cancellation Charges: " . tcc_format_inr($retained_income) . "\n";
} elseif ($total_paid > 0 || $vendor_paid > 0) {
    $txt_price .= "\n------------------------\n";
    $txt_price .= "*Agency Received:* " . tcc_format_inr($total_paid) . "\n";
    if ($vendor_paid > 0) {
        $txt_price .= "*Direct Vendor Payments:* " . tcc_format_inr($vendor_paid) . "\n";
        $txt_price .= "*Vendor Discount:* -" . tcc_format_inr($tax_waiver) . "\n";
    }
    $txt_price .= "*Balance Due:* " . tcc_format_inr($balance);
}
?>

<style>
    :root {
        --tcc-primary: <?php echo $doc_color; ?>;
        --tcc-badge: <?php echo $badge_bg; ?>;
        --tcc-bg-site: #f1f5f9;
        --tcc-bg-card: #ffffff;
        --tcc-border: #e2e8f0;
        --tcc-text-dark: #0f172a;
        --tcc-text: #334155;
        --tcc-text-light: #64748b;
        --tcc-radius: 8px;
    }
    .tcc-container * { box-sizing: border-box; }
    
    /* FULL WIDTH CONTAINER */
    .tcc-container { 
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
        max-width: 100%; 
        margin: 0; 
        background: var(--tcc-bg-site); 
        color: var(--tcc-text); 
        line-height: 1.5; 
        min-height: 100vh;
    }
    
    /* Full width header */
    .tcc-header { 
        background: #ffffff; 
        color: var(--tcc-text-dark); 
        padding: 25px 5% 20px; /* Reduced padding */
        text-align: center; 
        border-bottom: 4px solid var(--tcc-primary);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .tcc-header h1 { margin: 0 0 4px; font-size: 26px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; color: var(--tcc-primary); }
    .tcc-header h2 { margin: 0 0 8px; font-size: 13px; font-weight: 600; color: var(--tcc-text-light); letter-spacing: 0.5px; }
    .tcc-header .badge { display: inline-block; background: var(--tcc-badge); padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff;}
    
    .tcc-header-address { margin: 0 auto 10px; max-width: 800px; line-height: 1.4; color: #334155; }
    .tcc-header-address .head-office { font-size: 14px; font-weight: 700; color: #0f172a; }
    .tcc-header-address .corp-office { font-size: 11px; font-weight: 500; color: #64748b; margin-top: 2px; }
    
    .tcc-header-meta { display: flex; justify-content: space-between; align-items: flex-end; max-width: 1000px; margin: 15px auto 0; padding-top: 15px; border-top: 1px solid var(--tcc-border); text-align: left; flex-wrap: wrap; gap: 15px; }

    /* Full width body with horizontal padding */
    .tcc-body { padding: 25px 5%; max-width: 1200px; margin: 0 auto; background: var(--tcc-bg-card); box-shadow: 0 0 15px rgba(0,0,0,0.05); }
    
    .tcc-section { margin-bottom: 30px; } 
    
    .tcc-section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--tcc-border); padding-bottom: 8px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
    .tcc-section-title { font-size: 18px; font-weight: 700; color: var(--tcc-text-dark); margin: 0; display:flex; align-items:center; gap:8px; }

    .tcc-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 4px; cursor: pointer; border: none; transition: 0.2s; background: #64748b; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; }
    .tcc-btn:hover { background: #475569; color: #fff; }
    .tcc-btn-wa { background: #22c55e; }
    .tcc-btn-wa:hover { background: #16a34a; }

    .tcc-grid-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; background: #f8fafc; padding: 15px; border-radius: var(--tcc-radius); border: 1px solid var(--tcc-border); }
    .tcc-detail-item { font-size: 13px; color: var(--tcc-text-dark); font-weight: 500; }
    .tcc-detail-item span { display: block; font-size: 10px; color: var(--tcc-text-light); text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }

    .tcc-timeline { border-left: 2px solid var(--tcc-primary); margin-left: 10px; padding-left: 15px; }
    .tcc-timeline-item { position: relative; margin-bottom: 25px; }
    .tcc-timeline-item:last-child { margin-bottom: 0; }
    .tcc-timeline-item::before { content: ''; position: absolute; left: -22px; top: 2px; width: 12px; height: 12px; background: var(--tcc-primary); border: 2px solid #fff; border-radius: 50%; }
    .tcc-timeline-title { font-size: 15px; font-weight: 700; color: var(--tcc-primary); margin: 0 0 8px; }
    .tcc-timeline-image { margin-bottom: 10px; width: 100%; height: auto; max-height: 450px; object-fit: cover; border-radius: 6px; border: 1px solid var(--tcc-border); }
    
    .tcc-timeline-stay { font-size: 13px; color: var(--tcc-primary); margin: 0; padding-top: 8px; border-top: 1px dashed var(--tcc-border); font-weight: 600;}

    .tcc-price-grid { display: grid; grid-template-columns: 1fr; gap: 15px; background: #f8fafc; padding: 20px; border-radius: var(--tcc-radius); border: 1px solid var(--tcc-border); margin-bottom: 25px; }
    @media(min-width: 768px) { .tcc-price-grid { grid-template-columns: 1fr 1fr; } }
    .tcc-price-row { display: flex; justify-content: space-between; font-size: 14px; color: var(--tcc-text-light); margin-bottom: 10px; }
    .tcc-price-row strong { color: var(--tcc-text-dark); }
    .tcc-price-total { display: flex; justify-content: space-between; align-items: center; font-size: 18px; font-weight: 900; color: var(--tcc-text-dark); margin-top: 12px; padding-top: 12px; border-top: 2px dashed var(--tcc-border); }
    .tcc-status-box { text-align: center; margin-top: 12px; padding: 10px; border-radius: 6px; font-weight: 900; font-size: 15px; letter-spacing: 1px; }

    .tcc-inc-grid { display: flex; flex-direction: column; gap: 12px; }
    .tcc-inc-card { padding: 15px; border-radius: var(--tcc-radius); }
    .tcc-inc-card h4 { margin: 0 0 10px; font-size: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 6px; }
    
    .tcc-inc-green { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .tcc-inc-green h4 { color: #15803d; }
    .tcc-inc-red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .tcc-inc-red h4 { color: #b91c1c; }
    .tcc-inc-yellow { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .tcc-inc-yellow h4 { color: #b45309; }
    .tcc-inc-grey { background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; }
    .tcc-inc-grey h4 { color: #0f172a; }
    
    /* RTE Dynamic Content Styling - STRICT SINGLE SPACING */
    .tcc-rte-display p { margin: 0 !important; padding: 0 !important; line-height: 1.5 !important; }
    .tcc-rte-display ul, .tcc-rte-display ol { margin: 4px 0 !important; padding-left: 18px !important; }
    .tcc-rte-display li { margin-bottom: 2px !important; }
    .tcc-rte-display br { margin: 0 !important; padding: 0 !important; }
    
    /* Quill Fallbacks */
    .ql-align-center { text-align: center; }
    .ql-align-right { text-align: right; }
    .ql-align-justify { text-align: justify; }
    .ql-size-small { font-size: 0.85em; }
    .ql-size-large { font-size: 1.5em; }
    .ql-size-huge { font-size: 2.5em; }
    strong { font-weight: bold; }
    em { font-style: italic; }

    /* Lock text icons/emojis to surrounding text size on the frontend */
    .tcc-rte-display img.emoji,
    .tcc-rte-display img.icon,
    .tcc-rte-display img[src*="emoji" i],
    .tcc-rte-display img[src*="s.w.org" i],
    .tcc-rte-display img[src*="twimg" i],
    .tcc-rte-display img[src*="gstatic" i],
    .tcc-rte-display img[src*="icon" i],
    .tcc-rte-display img[class*="emoji" i] {
        display: inline-block !important;
        width: 16px !important;
        height: 16px !important;
        margin: 0 2px !important;
        vertical-align: -2px !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        background: none !important;
    }

    .tcc-container .WA-copy-feedback { pointer-events: none; position: fixed; top: 10px; left: 50%; transform: translateX(-50%) translateY(-20px); background: rgba(0,0,0,0.8); color: #fff; padding: 8px 20px; border-radius: 20px; font-size: 12px; font-weight: bold; opacity: 0; transition: 0.3s; z-index: 999; }
    .tcc-container .WA-copy-feedback.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    
    /* Responsive Mode */
    @media(max-width: 768px) {
        .tcc-header { padding: 20px 15px; }
        .tcc-body { padding: 15px; }
        .tcc-grid-details { grid-template-columns: 1fr 1fr; gap: 12px 8px; padding: 12px; }
    }
</style>

<div class="tcc-container" id="tcc_web_view">
    <div class="WA-copy-feedback" id="tcc_copy_feedback">Copied to clipboard!</div>
    
    <div class="tcc-header">
        <h1>SOULFUL TOUR & TRAVELS</h1>
        <h2>GSTIN: 19AXIPD7432L1Z5</h2>
        
        <div class="tcc-header-address">
            <div class="head-office">
                📍 Operational Head Office: 
                <span style="font-weight:normal;"><?php echo esc_html(isset($d['head_office']) && !empty($d['head_office']) ? str_replace("\n", ", ", $d['head_office']) : 'Lake City Plaza, Karan Nagar Rd,Karan Nagar, Srinagar, Jammu and Kashmir 190010'); ?></span>
            </div>
            <div class="corp-office">🏢 Corporate Office: Soulful Pathfinder, 505/S/A, Sirajmondal Road, Kanchrapara, 24 PGS (N), WB Kolkata -743145</div>
        </div>

        <div class="tcc-header-meta">
            <div>
                <span style="color:var(--tcc-text-light); text-transform:uppercase; font-size:11px; font-weight:700; display:block; margin-bottom:2px;">Prepared For</span>
                <strong style="font-size: 15px; color: var(--tcc-text-dark);"><?php echo isset($d['client_name']) && !empty($d['client_name']) ? esc_html($d['client_name']) : 'Valued Client'; ?></strong>
                <?php if(isset($d['client_phone']) && !empty($d['client_phone'])): ?>
                    <div style="color: #64748b; font-size: 13px; margin-top: 1px;">📞 <?php echo esc_html($d['client_phone']); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="badge"><?php echo $doc_title; ?></div>
            </div>
        </div>
    </div>

    <div class="tcc-body">

        <div class="tcc-section">
            <div class="tcc-section-header" style="padding-right: 5px;">
                <h3 class="tcc-section-title">📍 Trip Details</h3>
                
                <div class="tcc-action-buttons" style="display:flex; gap:5px; flex-wrap:wrap;">
                    <button class="tcc-btn" style="background:#dc2626;" onclick="tccDownloadPDF(this)">📄 Download</button>
                    <button class="tcc-btn" style="background:#8b5cf6;" onclick="tccSharePDF(this)">📤 Share</button>
                    
                    <?php if(is_user_logged_in()): ?>
                        <button class="tcc-btn" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_trip); ?>')">📋 Copy</button>
                        <a href="<?php echo $wa_api_base . rawurlencode($txt_trip); ?>" target="_blank" class="tcc-btn tcc-btn-wa">💬 Send</a>
                        <button class="tcc-btn" style="background:#2563eb;" onclick="tccSendEmail(this, <?php echo $post->ID; ?>, '<?php echo esc_js(isset($d['client_email']) ? $d['client_email'] : ''); ?>', '<?php echo esc_js(isset($d['client_name']) ? $d['client_name'] : ''); ?>', '<?php echo esc_js(isset($d['client_phone']) ? $d['client_phone'] : ''); ?>')">📧 Email</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tcc-grid-details">
                <div class="tcc-detail-item"><span>Destination</span> <?php echo esc_html($d['destination']); ?></div>
                <?php if (!empty($d['start_date'])): ?>
                    <div class="tcc-detail-item"><span>Start Date</span> <?php echo date('d M Y', strtotime($d['start_date'])); ?></div>
                    <div class="tcc-detail-item"><span>End Date</span> <?php echo date('d M Y', strtotime($d['end_date'])); ?></div>
                <?php endif; ?>
                <div class="tcc-detail-item"><span>Duration</span> <?php echo esc_html($d['days']); ?> Days / <?php echo esc_html($d['days'] - 1); ?> Nights</div>
                <?php if(!$hide_tr): ?>
                    <div class="tcc-detail-item"><span>Travelers</span> <?php echo esc_html($d['pax']); ?> Ad, <?php echo esc_html(isset($d['child_6_12']) ? $d['child_6_12'] : 0); ?> Ch(6-12), <?php echo esc_html($d['child']); ?> Inf</div>
                    <div class="tcc-detail-item"><span>Rooms</span> <?php echo esc_html($d['rooms']); ?> R / <?php echo esc_html($d['extra_beds']); ?> EB</div>
                <?php endif; ?>
                <div class="tcc-detail-item"><span>Category</span> <?php echo esc_html($d['hotel_cat']); ?></div>
                <div class="tcc-detail-item" style="grid-column: 1 / -1;">
                    <span>Transport Details</span> 
                    <?php echo wp_kses_post($d['transport_string']); ?> 
                    <span style="display:inline; color:var(--tcc-text-light); text-transform:none; font-weight:normal; font-size:12px;">(Pickup: <?php echo esc_html($d['pickup']); ?> | Drop: <?php echo esc_html($drop_txt); ?>)</span>
                </div>
            </div>
        </div>

        <?php if(isset($d['itinerary']) && !empty($d['itinerary']) && array_filter($d['itinerary'])): ?>
        <div class="tcc-section">
            <div class="tcc-section-header" style="padding-right: 5px;">
                <h3 class="tcc-section-title">📅 Itinerary Details</h3>
                <?php if(is_user_logged_in()): ?>
                    <div class="tcc-action-buttons" style="display:flex; gap:5px;">
                        <button class="tcc-btn" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_itin); ?>')">📋 Copy</button>
                        <a href="<?php echo $wa_api_base . rawurlencode($txt_itin); ?>" target="_blank" class="tcc-btn tcc-btn-wa">💬 Send</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if(!empty($display_route)): ?>
            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; color: var(--tcc-primary); font-size: 14px;">
                🗺️ Route: <?php echo esc_html($display_route); ?>
            </div>
            <?php endif; ?>

            <div class="tcc-timeline">
                <?php foreach($d['itinerary'] as $index => $day_text): 
                    if(trim($day_text) === '') continue;
                ?>
                    <div class="tcc-timeline-item">
                        <h4 class="tcc-timeline-title">Day <?php echo $index + 1; ?>: <?php echo esc_html($day_text); ?></h4>
                        
                        <?php if (!empty($d['itinerary_image'][$index])): ?>
                            <img src="<?php echo esc_url($d['itinerary_image'][$index]); ?>" alt="Day <?php echo $index + 1; ?>" class="tcc-timeline-image">
                        <?php endif; ?>

                        <?php if (!empty($d['itinerary_desc'][$index])): ?>
                            <div class="tcc-rte-display"><?php echo tcc_clean_rte_html($d['itinerary_desc'][$index]); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($raw_stays[$index])): ?>
                            <p class="tcc-timeline-stay"><strong>Night Stay:</strong> <?php echo esc_html(trim($raw_stays[$index])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($d['stays']) && !empty($d['stays'])): ?>
        <div class="tcc-section">
            <div class="tcc-section-header" style="padding-right: 5px; border-bottom: none;">
                <h3 class="tcc-section-title">
                    <span style="font-size:22px;">🏨</span> <span style="background:#e2e8f0; padding:6px 12px; border-radius:4px;">Hotels Selected</span>
                </h3>
                <?php if(is_user_logged_in()): ?>
                    <div class="tcc-action-buttons" style="display:flex; gap:5px;">
                        <button class="tcc-btn tcc-btn-wa" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_hotels); ?>')">WA Copy</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tcc-hotels-wrapper">
                <?php foreach($d['stays'] as $stay): 
                    if($stay['place'] === 'No Hotel Required') {
                        $combined_hotels = "No Room Provided";
                        $loc_cat = "-";
                        $room_txt = "";
                    } else {
                        $formatted_hotels = array();
                        foreach($stay['options'] as $opt) {
                            $link = !empty($opt['link']) ? " <a href='".esc_url($opt['link'])."' target='_blank' style='color:#2563eb; text-decoration:underline; font-size:12px;'>(View)</a>" : '';
                            $formatted_hotels[] = "<strong style='color:#0f172a;'>".esc_html($opt['name'])."</strong>" . $link;
                        }
                        $combined_hotels = implode(' <span style="color:#94a3b8; font-size:12px; font-weight:normal; margin:0 4px;">/</span> ', $formatted_hotels);
                        if(empty($combined_hotels)) $combined_hotels = "No selection made";
                        
                        $loc_cat = isset($stay['category']) && !empty($stay['category']) ? $stay['category'] : $d['hotel_cat'];
                        $room_txt = isset($stay['room_type']) && $stay['room_type'] !== 'Default' ? $stay['room_type'] : 'Deluxe Room';
                    }
                ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 15px; background: #fff; font-family: inherit;">
                    <div style="display: flex; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; align-items: center;">
                        <div style="width: 100px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Location</div>
                        <div style="flex: 1; text-align: center; font-weight: 700; color: #0f172a; font-size: 14px;"><?php echo esc_html($stay['place']); ?></div>
                        <div style="width: 120px; text-align: right; font-size: 12px; font-weight: 600; color: #b91c1c;">
                            <?php echo esc_html($loc_cat); ?>
                            <?php if(!empty($room_txt)): ?><br><span style="font-size:10px; color:#475569; font-weight:500;"><?php echo esc_html($room_txt); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; align-items: center;">
                        <div style="width: 100px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Hotels</div>
                        <div style="flex: 1; text-align: right; font-size: 14px; color: #0f172a; line-height: 1.5;"><?php echo $combined_hotels; ?> <span style="color:#64748b; font-size:12px;">/ Similar</span></div>
                    </div>
                    <div style="display: flex; padding: 12px 15px; align-items: center;">
                        <div style="width: 100px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Nights</div>
                        <div style="flex: 1; text-align: right; font-weight: 800; font-size: 15px; color: #0f172a;"><?php echo esc_html($stay['nights']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="tcc-inc-grid" style="margin-bottom: 30px;">
            <div class="tcc-inc-card tcc-inc-green">
                <h4>
                    <span>✅ Inclusions</span> 
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:4px 8px; font-size:10px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_inc); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_inc); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:4px 8px; font-size:10px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['inclusions']); ?>
            </div>
            
            <div class="tcc-inc-card tcc-inc-red">
                <h4>
                    <span>❌ Exclusions</span> 
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:4px 8px; font-size:10px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_exc); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_exc); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:4px 8px; font-size:10px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['exclusions']); ?>
            </div>
        </div>

        <?php if(!$hide_pr): ?>
        <div class="tcc-section">
            <div class="tcc-section-header" style="padding-right: 5px;">
                <h3 class="tcc-section-title">🧾 Pricing & Receipts</h3>
                <?php if(is_user_logged_in()): ?>
                    <div class="tcc-action-buttons" style="display:flex; gap:5px;">
                        <button class="tcc-btn" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_price); ?>')">📋 Copy</button>
                        <a href="<?php echo $wa_api_base . rawurlencode($txt_price); ?>" target="_blank" class="tcc-btn tcc-btn-wa">💬 Send</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tcc-price-grid">
                
                <div>
                    <h4 style="margin:0 0 12px; font-size:16px; color:var(--tcc-text-dark);">Transaction History</h4>
                    <?php if(count($payments) > 0): ?>
                        <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left; background:#fff; border-radius:6px; overflow:hidden; border:1px solid #e2e8f0;">
                            <thead style="display:table-header-group;">
                                <tr style="background:#f1f5f9; color:#475569;">
                                    <th style="padding:10px 12px; border-bottom:1px solid #e2e8f0;">Date</th>
                                    <th style="padding:10px 12px; border-bottom:1px solid #e2e8f0;">Method</th>
                                    <th style="padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payments as $p): 
                                    $is_refund = (isset($p['method']) && $p['method'] === 'Refund');
                                    $is_vendor = (isset($p['method']) && $p['method'] === 'Direct to Vendor');
                                    $amt_color = $is_refund ? '#dc2626' : ($is_vendor ? '#d97706' : '#16a34a');
                                    $amt_prefix = $is_refund ? '-' : '';
                                ?>
                                <tr style="border-bottom:1px solid #f8fafc;">
                                    <td data-label="Date" style="padding:10px 12px; color:#334155;"><?php echo esc_html(date('d M y', strtotime($p['date']))); ?></td>
                                    <td data-label="Method" style="padding:10px 12px; color:#64748b;">
                                        <?php echo esc_html($p['method']); ?>
                                        <?php if($is_vendor): ?>
                                            <br><span style="font-size:9px; color:#16a34a; font-weight:bold;">+ Vendor Discount: <?php echo tcc_format_inr(($p['amount'] * $gst_pct) + ($p['amount'] * (1 + $gst_pct) * ($pt_pct + $pg_pct))); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Amount" style="padding:10px 12px; text-align:right; font-weight:bold; color:<?php echo $amt_color; ?>;"><?php echo $amt_prefix . tcc_format_inr($p['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="background:#fff; border:1px dashed var(--tcc-border); border-radius:6px; padding:20px; text-align:center; font-size:14px; color:var(--tcc-text-light);">No payments recorded yet.</div>
                    <?php endif; ?>
                </div>

                <div class="tcc-price-box" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--tcc-border); box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                    <h4 style="color:var(--tcc-text-dark); font-size:16px; margin:0 0 15px 0; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Pricing Summary</h4>
                    
                   <?php if($discount_amt > 0): ?>
                        <div class="tcc-price-row"><span>Total Base:</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($calculated_base + $discount_amt); ?></strong></div>
                        <div class="tcc-price-row" style="color:#dc2626;"><span>Discount Applied:</span> <strong>-<?php echo tcc_format_inr($discount_amt); ?></strong></div>
                    <?php else: ?>
                        <div class="tcc-price-row"><span>Total Base:</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($calculated_base); ?></strong></div>
                    <?php endif; ?>
                    
                    <div class="tcc-price-row"><span>GST (<?php echo $gst_pct_disp; ?>%):</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($calculated_gst); ?></strong></div>

                    <div class="tcc-price-total">
                        <span>Total Package Value:</span>
                        <span style="<?php echo $is_cancelled ? 'text-decoration:line-through; opacity:0.5;' : ''; ?>"><?php echo tcc_format_inr($grand_total); ?></span>
                    </div>

                    <?php if($post_quote_discount > 0): ?>
                    <div class="tcc-price-row" style="color:#dc2626; margin-top:8px;">
                        <span>Special Discount:</span> 
                        <strong>-<?php echo tcc_format_inr($post_quote_discount); ?></strong>
                    </div>
                    <div class="tcc-price-total" style="padding-top:10px; margin-top:10px; font-size:20px; color:#15803d; border-top:none;">
                        <span>Final Package Value:</span>
                        <span><?php echo tcc_format_inr($grand_total - $post_quote_discount); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if($is_cancelled): ?>
                        <div style="margin-top:15px; padding-top:12px; border-top:2px solid #fee2e2;">
                            <div class="tcc-price-row"><span>Total Received:</span> <strong style="color:#15803d;"><?php echo tcc_format_inr($total_paid); ?></strong></div>
                            <div class="tcc-price-row" style="color:#dc2626;"><span>Amount Refunded:</span> <strong><?php echo tcc_format_inr($total_refunded); ?></strong></div>
                            <div class="tcc-price-row" style="margin-top:10px; padding-top:10px; border-top:1px dashed var(--tcc-border); font-size:15px; font-weight:700; color:#16a34a;"><span>Cancellation Charges:</span> <span><?php echo tcc_format_inr($retained_income); ?></span></div>
                            <div class="tcc-status-box" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca;">STATUS: CANCELLED</div>
                        </div>
                    <?php elseif($total_paid > 0 || $vendor_paid > 0): ?>
                        <div style="margin-top:15px; padding-top:12px; border-top:2px solid var(--tcc-border);">
                            <div class="tcc-price-row" style="color:#16a34a; font-weight:700; font-size:15px;"><span>Agency Received:</span> <span><?php echo tcc_format_inr($total_paid); ?></span></div>
                            <?php if($vendor_paid > 0): ?>
                                <div class="tcc-price-row" style="color:#d97706; font-size:14px;"><span>Paid to Vendors:</span> <span><?php echo tcc_format_inr($vendor_paid); ?></span></div>
                                <div class="tcc-price-row" style="color:#16a34a; font-size:14px;"><span>Vendor Discount:</span> <span>-<?php echo tcc_format_inr($tax_waiver); ?></span></div>
                            <?php endif; ?>
                            <div class="tcc-status-box" style="background:<?php echo $balance > 0 ? '#fef2f2' : '#f0fdf4'; ?>; color:<?php echo $balance > 0 ? '#dc2626' : '#15803d'; ?>; border:1px solid <?php echo $balance > 0 ? '#fecaca' : '#bbf7d0'; ?>; display:flex; justify-content:space-between; align-items:center;">
                                <span><?php echo $balance > 0 ? 'Balance Due:' : 'Balance Cleared:'; ?></span>
                                <span style="font-size:16px;"><?php echo tcc_format_inr($balance); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="tcc-inc-grid">
            <div class="tcc-inc-card tcc-inc-yellow">
                <h4>
                    <span>💳 Payment Terms</span> 
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:4px 8px; font-size:10px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_pay); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_pay); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:4px 8px; font-size:10px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['payment_terms']); ?>
            </div>

            <?php if(!empty($d['important_note'])): ?>
            <div class="tcc-inc-card tcc-inc-grey">
                <h4><span>📌 Important Note</span></h4>
                <?php echo tcc_render_bullets($d['important_note']); ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($d['why_choose_us'])): ?>
            <div class="tcc-inc-card tcc-inc-grey">
                <h4><span>🌟 Why Choose Us</span></h4>
                <?php echo tcc_render_bullets($d['why_choose_us']); ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($d['essential_guidelines'])): ?>
            <div class="tcc-inc-card tcc-inc-grey">
                <h4>
                    <span>📋 Essential Travel Guidelines</span>
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:4px 8px; font-size:10px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_guide); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_guide); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:4px 8px; font-size:10px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['essential_guidelines']); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if(!empty($global_settings['company_banner'])): ?>
        <div style="margin-top: 30px; text-align: center; border-top: 2px dashed var(--tcc-border); padding-top: 20px;">
            <img src="<?php echo esc_url($global_settings['company_banner']); ?>" style="max-width: 100%; height: auto; object-fit: contain; border-radius: var(--tcc-radius);">
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="tcc_hidden_pdf_template" style="display:none; color: #333; background: #fff;">
    <style>
        .ql-align-center { text-align: center; }
        .ql-align-right { text-align: right; }
        .ql-align-justify { text-align: justify; }
        .ql-size-small { font-size: 0.85em; }
        .ql-size-large { font-size: 1.5em; }
        .ql-size-huge { font-size: 2.5em; }
        strong { font-weight: bold; }
        em { font-style: italic; }
        
        /* FIX FOR PDF LINE GAPS - Strict Single Spacing */
        .tcc-rte-display p { margin: 0 !important; padding: 0 !important; line-height: 1.5 !important; }
        .tcc-rte-display ul, .tcc-rte-display ol { margin: 2px 0 !important; padding-left: 18px !important; }
        .tcc-rte-display li { margin-bottom: 2px !important; }
        .tcc-rte-display br { display: block !important; margin: 0 !important; }

        /* Lock text icons/emojis to surrounding text size on the PDF */
        img.emoji, 
        img.icon,
        img[src*="emoji" i],
        img[src*="s.w.org" i],
        img[src*="twimg" i],
        img[src*="gstatic" i],
        img[src*="icon" i],
        img[class*="emoji" i] { 
            display: inline-block !important; 
            width: 16px !important; 
            height: 16px !important; 
            margin: 0 2px !important; 
            vertical-align: -2px !important; 
            border: none !important; 
            box-shadow: none !important; 
            padding: 0 !important; 
            background: none !important; 
        }
    </style>
    
    <div style="text-align: center; border-bottom: 3px solid <?php echo $doc_color; ?>; padding-bottom: 8px; margin-bottom: 12px;">
        <h1 style="margin: 0 0 2px; font-size: 24px; font-weight: 900; color: <?php echo $doc_color; ?>; text-transform: uppercase; letter-spacing: 1px;">SOULFUL TOUR & TRAVELS</h1>
        <div style="font-size: 11px; font-weight: bold; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px;">GSTIN: 19AXIPD7432L1Z5</div>
        
        <div style="margin-bottom: 8px; line-height: 1.3;">
            <div style="font-size: 13px; font-weight: bold; color: #0f172a;">
                📍 Operational Head Office: 
                <span style="font-weight:normal;"><?php echo esc_html(isset($d['head_office']) && !empty($d['head_office']) ? str_replace("\n", ", ", $d['head_office']) : 'Lake City Plaza, Karan Nagar Rd,Karan Nagar, Srinagar, Jammu and Kashmir 190010'); ?></span>
            </div>
            <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                🏢 Corporate Office: Soulful Pathfinder, 505/S/A, Sirajmondal Road, Kanchrapara, 24 PGS (N), WB Kolkata -743145
            </div>
        </div>

        <table style="width: 100%; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 12px; text-align: left;">
            <tr>
                <td style="width: 50%; vertical-align: top; border:none; padding:0;">
                    <span style="color:#64748b; text-transform:uppercase; font-size:9px; font-weight:bold; display:block; margin-bottom:2px;">Prepared For</span>
                    <strong style="font-size: 13px; color: #0f172a;"><?php echo esc_html(isset($d['client_name']) && !empty($d['client_name']) ? $d['client_name'] : 'Valued Client'); ?></strong><br>
                    <?php if(isset($d['client_phone']) && !empty($d['client_phone'])) echo '<span style="color: #475569;">📞 Phone: ' . esc_html($d['client_phone']) . '</span><br>'; ?>
                    <?php if(isset($d['client_email']) && !empty($d['client_email'])) echo '<span style="color: #475569;">📧 Email: ' . esc_html($d['client_email']) . '</span>'; ?>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top; border:none; padding:0;">
                    <div style="display: inline-block; background: <?php echo $badge_bg; ?>; color: #fff; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: bold; margin-bottom: 4px; text-transform: uppercase;"><?php echo $doc_title; ?></div><br>
                    </td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 12px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 3px; font-size: 14px; margin-bottom: 6px; margin-top:0;">📍 Trip Details</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <tr>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0; width: 20%;">Destination</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0; width: 30%;"><?php echo esc_html($d['destination']); ?></td>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0; width: 20%;">Duration</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0; width: 30%;"><?php echo esc_html($d['days']); ?> Days / <?php echo esc_html($d['days'] - 1); ?> Nights</td>
            </tr>
            <?php if (!empty($d['start_date'])): ?>
            <tr>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Start Date</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0;"><?php echo date('d M Y', strtotime($d['start_date'])); ?></td>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">End Date</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0;"><?php echo date('d M Y', strtotime($d['end_date'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if(!$hide_tr): ?>
            <tr>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Travelers</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0;"><?php echo esc_html($d['pax']); ?> Adults, <?php echo esc_html($child_6_12_disp); ?> Child (6-12), <?php echo esc_html($d['child']); ?> Infants</td>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Rooms</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0;"><?php echo esc_html($d['rooms']); ?> Rooms / <?php echo esc_html($d['extra_beds']); ?> Extra Beds</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Category</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0;"><?php echo esc_html($d['hotel_cat']); ?></td>
                <td style="padding: 6px 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Transport</td>
                <td style="padding: 6px 8px; border: 1px solid #e2e8f0;"><?php echo wp_kses_post($d['transport_string']); ?><br><span style="font-size:10px; color:#64748b;">(Pickup: <?php echo esc_html($d['pickup']); ?> | Drop: <?php echo esc_html($drop_txt); ?>)</span></td>
            </tr>
        </table>
    </div>

    <?php if(isset($d['itinerary']) && !empty($d['itinerary']) && array_filter($d['itinerary'])): ?>
    <div style="margin-bottom: 15px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 3px; font-size: 14px; margin-bottom: 6px; margin-top:0;">📅 Itinerary Details</h3>
        
        <?php if(!empty($display_route)): ?>
        <div style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 6px; border-radius: 3px; margin-bottom: 8px; font-weight: bold; color: <?php echo $doc_color; ?>; font-size: 12px;">
            🗺️ Route: <?php echo esc_html($display_route); ?>
        </div>
        <?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <?php foreach($d['itinerary'] as $index => $day_text): 
                if(trim($day_text) === '') continue;
            ?>
            <tr>
                <td style="padding: 10px; background: #f8fafc; font-weight: bold; color: <?php echo $doc_color; ?>; border: 1px solid #e2e8f0; width: 14%; vertical-align: top; font-size:13px;">Day <?php echo $index + 1; ?></td>
                <td style="padding: 10px; border: 1px solid #e2e8f0; width: 86%; vertical-align: top;">
                    <div style="font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #0f172a;"><?php echo esc_html($day_text); ?></div>
                    
                    <?php if (!empty($d['itinerary_image'][$index])): ?>
                        <div style="margin-bottom: 8px;">
                            <img src="<?php echo esc_url($d['itinerary_image'][$index]); ?>" style="width: 100%; border-radius: 4px; border: 1px solid #cbd5e1;">
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($d['itinerary_desc'][$index])): ?>
                        <div class="tcc-rte-display" style="font-size: 12px; color: #334155; margin-bottom: 8px;">
                            <?php echo tcc_clean_rte_html($d['itinerary_desc'][$index]); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($raw_stays[$index])): ?>
                        <div style="font-size: 12px; font-weight: bold; color: <?php echo $doc_color; ?>; padding-top: 6px; border-top: 1px dashed #e2e8f0;">Night Stay: <?php echo esc_html(trim($raw_stays[$index])); ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <?php if(isset($d['stays']) && !empty($d['stays'])): ?>
    <div style="margin-bottom: 15px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 3px; font-size: 14px; margin-bottom: 6px; margin-top:0;">🏨 Hotels Selected</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="padding: 6px 8px; border: 1px solid #e2e8f0; text-align: left; width: 25%;">Location</th>
                    <th style="padding: 6px 8px; border: 1px solid #e2e8f0; text-align: left; width: 60%;">Hotels / Similar</th>
                    <th style="padding: 6px 8px; border: 1px solid #e2e8f0; text-align: center; width: 15%;">Nights</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($d['stays'] as $stay): 
                    if($stay['place'] === 'No Hotel Required') {
                        $combined_hotels = "No Room Provided";
                        $loc_cat = "-";
                        $room_txt = "";
                    } else {
                        $formatted_hotels = array();
                        foreach($stay['options'] as $opt) { $formatted_hotels[] = "<strong>".esc_html($opt['name'])."</strong>"; }
                        $combined_hotels = implode(' / ', $formatted_hotels);
                        if(empty($combined_hotels)) $combined_hotels = "No selection made";
                        $loc_cat = isset($stay['category']) && !empty($stay['category']) ? $stay['category'] : $d['hotel_cat'];
                        $room_txt = isset($stay['room_type']) && $stay['room_type'] !== 'Default' ? $stay['room_type'] : 'Deluxe Room';
                    }
                ?>
                <tr>
                    <td style="padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top;">
                        <strong><?php echo esc_html($stay['place']); ?></strong><br>
                        <span style="font-size:10px; color:#b91c1c;"><?php echo esc_html($loc_cat); ?><?php if(!empty($room_txt)) echo ' - ' . esc_html($room_txt); ?></span>
                    </td>
                    <td style="padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; line-height: 1.4;"><?php echo $combined_hotels; ?></td>
                    <td style="padding: 6px 8px; border: 1px solid #e2e8f0; text-align: center; font-weight: bold; vertical-align: middle;"><?php echo esc_html($stay['nights']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div style="margin-bottom: 12px; padding: 12px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; border-radius: 4px; font-size: 12px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; border-bottom: 1px solid #bbf7d0; padding-bottom: 4px;">✅ Inclusions</h4>
        <?php echo tcc_render_bullets($d['inclusions']); ?>
    </div>
    
    <div style="margin-bottom: 15px; padding: 12px; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 4px; font-size: 12px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; border-bottom: 1px solid #fecaca; padding-bottom: 4px;">❌ Exclusions</h4>
        <?php echo tcc_render_bullets($d['exclusions']); ?>
    </div>

    <?php if(!$hide_pr): ?>
    <div style="margin-bottom: 15px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 3px; font-size: 14px; margin-bottom: 6px; margin-top:0;">🧾 Pricing Summary</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc; width: 60%;">Total Base</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold; width: 40%;"><?php echo tcc_format_inr($discount_amt > 0 ? ($calculated_base + $discount_amt) : $calculated_base); ?></td>
            </tr>
            <?php if($discount_amt > 0): ?>
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #fef2f2; color: #dc2626;">Discount Applied</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #fef2f2; text-align: right; font-weight: bold; color: #dc2626;">-<?php echo tcc_format_inr($discount_amt); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc;">GST (<?php echo $gst_pct_disp; ?>%)</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($calculated_gst); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e2e8f0; background: #f1f5f9; font-size: 15px; font-weight: bold;">Total Package Value</td>
                <td style="padding: 10px; border: 1px solid #e2e8f0; background: #f1f5f9; text-align: right; font-size: 15px; font-weight: bold;"><?php echo tcc_format_inr($grand_total); ?></td>
            </tr>
            
            <?php if($post_quote_discount > 0): ?>
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #fef2f2; color: #dc2626; font-weight: bold;">Special Discount</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #fef2f2; text-align: right; font-weight: bold; color: #dc2626;">-<?php echo tcc_format_inr($post_quote_discount); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e2e8f0; background: #f0fdf4; font-size: 15px; font-weight: bold; color:#15803d;">Final Package Value</td>
                <td style="padding: 10px; border: 1px solid #e2e8f0; background: #f0fdf4; text-align: right; font-size: 15px; font-weight: bold; color:#15803d;"><?php echo tcc_format_inr($grand_total - $post_quote_discount); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($is_cancelled): ?>
                <tr><td colspan="2" style="padding: 10px; border: 1px solid #e2e8f0; background: #fef2f2; color: #dc2626; text-align: center; font-weight: bold;">BOOKING CANCELLED</td></tr>
            <?php elseif($total_paid > 0 || $vendor_paid > 0): ?>
                <tr>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #16a34a; font-weight: bold;">Agency Received</td>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #16a34a; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($total_paid); ?></td>
                </tr>
                <?php if($vendor_paid > 0): ?>
                <tr>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #d97706; font-weight: bold;">Direct Vendor Payments</td>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #d97706; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($vendor_paid); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #16a34a; font-weight: bold;">Vendor Discount Applied</td>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #16a34a; text-align: right; font-weight: bold;">-<?php echo tcc_format_inr($tax_waiver); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #dc2626; font-weight: bold;">Balance Due</td>
                    <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #dc2626; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($balance); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>

    <div style="margin-bottom: 12px; padding: 12px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 4px; font-size: 12px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; border-bottom: 1px solid #fde68a; padding-bottom: 4px;">💳 Payment Terms</h4>
        <?php echo tcc_render_bullets($d['payment_terms']); ?>
    </div>

    <?php if(!empty($d['important_note'])): ?>
    <div style="margin-bottom: 12px; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; border-radius: 4px; font-size: 12px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px;">📌 Important Note</h4>
        <?php echo tcc_render_bullets($d['important_note']); ?>
    </div>
    <?php endif; ?>

    <?php if(!empty($d['why_choose_us'])): ?>
    <div style="margin-bottom: 15px; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; border-radius: 4px; font-size: 12px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px;">🌟 Why Choose Us</h4>
        <?php echo tcc_render_bullets($d['why_choose_us']); ?>
    </div>
    <?php endif; ?>

    <?php if(!empty($d['essential_guidelines'])): ?>
    <div style="margin-bottom: 15px; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; border-radius: 4px; font-size: 12px;">
        <h4 style="margin: 0 0 6px; font-size: 13px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px;">📋 Essential Travel Guidelines</h4>
        <?php echo tcc_render_bullets($d['essential_guidelines']); ?>
    </div>
    <?php endif; ?>

    <?php if(!empty($global_settings['company_banner'])): ?>
    <div style="margin-top: 15px; text-align: center;">
        <img src="<?php echo esc_url($global_settings['company_banner']); ?>" style="width: 100%; height: auto;">
    </div>
    <?php endif; ?>

</div>

<script>
function tccCopySection(btn, encodedText) {
    let text = decodeURIComponent(encodedText.replace(/\+/g, '%20'));
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() { tccShowSuccess(btn); }).catch(function() { tccFallbackCopy(btn, text); });
    } else {
        tccFallbackCopy(btn, text);
    }
}

function tccFallbackCopy(btn, text) {
    let textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.top = "0"; textarea.style.left = "0"; textarea.style.position = "fixed"; textarea.style.opacity = "0";
    document.body.appendChild(textarea);
    textarea.focus(); textarea.select(); textarea.setSelectionRange(0, 99999); 
    try { document.execCommand('copy'); tccShowSuccess(btn); } catch (err) { alert('Failed to auto-copy text.'); }
    document.body.removeChild(textarea);
}

function tccShowSuccess(btn) {
    let oldText = btn.innerText;
    btn.innerText = 'Copied!';
    btn.style.background = '#16a34a';
    let toast = document.getElementById('tcc_copy_feedback');
    toast.classList.add('show');
    setTimeout(function() { 
        btn.innerText = oldText; 
        btn.style.background = '';
        toast.classList.remove('show');
    }, 2000);
}

// EMAIL PROMPT LOGIC
function tccSendEmail(btn, quoteId, currentEmail, clientName, clientPhone) {
    let oldText = btn.innerText;

    if (!currentEmail || currentEmail.trim() === '') {
        let newEmail = prompt("⚠️ Client email is missing.\n\nPlease enter the client's email address to instantly save it and send the quotation:");
        if (!newEmail || newEmail.trim() === '') return;

        btn.innerText = 'Saving...';
        btn.disabled = true;

        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'tcc_update_quote_client',
            security: '<?php echo wp_create_nonce("tcc_secure_nonce"); ?>',
            quote_id: quoteId,
            c_name: clientName,
            c_phone: clientPhone,
            c_email: newEmail.trim()
        }, function(res) {
            if(res.success) {
                btn.setAttribute('onclick', `tccSendEmail(this, ${quoteId}, '${newEmail.trim()}', '${clientName}', '${clientPhone}')`);
                executeEmailSend(btn, quoteId, oldText, newEmail.trim());
            } else {
                btn.disabled = false;
                btn.innerText = oldText;
                alert("Failed to save the new email address.");
            }
        });
    } else {
        if(!confirm('Send quotation email to ' + currentEmail + ' (BCC sent to Admin)?')) return;
        executeEmailSend(btn, quoteId, oldText, currentEmail);
    }
}

function executeEmailSend(btn, quoteId, oldText, email) {
    btn.innerText = 'Sending...';
    btn.disabled = true;
    
    jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
        action: 'tcc_send_quote_email',
        security: '<?php echo wp_create_nonce("tcc_secure_nonce"); ?>',
        quote_id: quoteId
    }, function(res) {
        btn.disabled = false;
        btn.innerText = oldText;
        if(res.success) {
            alert(res.data);
        } else {
            alert("Error: " + res.data);
        }
    }).fail(function() {
        btn.disabled = false;
        btn.innerText = oldText;
        alert("Server error. Check configuration.");
    });
}

// SERVER-SIDE NATIVE PDF DOWNLOAD
function tccDownloadPDF(btn) {
    let oldText = btn.innerText;
    btn.innerText = 'Downloading PDF...';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';

    let htmlContent = document.getElementById('tcc_hidden_pdf_template').innerHTML;
    let docTitle = 'Quotation_<?php echo sanitize_file_name($d['client_name'] . "_" . $d['destination']); ?>';

    // Create a hidden form to force an asynchronous file download via the browser natively
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo admin_url('admin-ajax.php'); ?>';

    let actionField = document.createElement('input');
    actionField.type = 'hidden';
    actionField.name = 'action';
    // [PATCH-C1D] Switched to tcc_front_generate_pdf — accepts quote_id only, no arbitrary HTML
    actionField.value = 'tcc_front_generate_pdf';
    form.appendChild(actionField);

    let quoteIdField = document.createElement('input');
    quoteIdField.type = 'hidden';
    quoteIdField.name = 'quote_id';
    quoteIdField.value = '<?php echo (int) $post->ID; ?>';
    form.appendChild(quoteIdField);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // Reset button after adequate download start time
    setTimeout(() => {
        btn.innerText = oldText;
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    }, 3000);
}

// NATIVE WEB SHARE API - SHARE PDF DIRECTLY
async function tccSharePDF(btn) {
    // 1. Check if the browser supports file sharing
    if (!navigator.canShare || !navigator.share) {
        alert("Your browser doesn't support native file sharing. Please use the '📄 PDF' download button instead.");
        return;
    }

    let oldText = btn.innerText;
    btn.innerText = 'Preparing...';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';

    let htmlContent = document.getElementById('tcc_hidden_pdf_template').innerHTML;
    let docTitle = 'Quotation_<?php echo sanitize_file_name($d['client_name'] . "_" . $d['destination']); ?>';

    // 2. Prepare the data to send to your existing PDF generator
    let formData = new FormData();
    // [PATCH-C1D-SHARE] Switched to tcc_front_generate_pdf — quote_id only
    formData.append('action', 'tcc_front_generate_pdf');
    formData.append('quote_id', '<?php echo (int) $post->ID; ?>');

    try {
        // 3. Fetch the PDF stream directly into memory
        let response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error('Network response was not ok');

        // 4. Convert the response to a Blob, then a File object
        let blob = await response.blob();
        let file = new File([blob], docTitle + '.pdf', { type: 'application/pdf' });

        // 5. Trigger the native Share Sheet
        if (navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: docTitle,
                text: 'Please find the attached travel quotation.'
            });
        } else {
            alert("Your system does not support sharing this file type natively.");
        }
    } catch (error) {
        console.error('Error sharing PDF:', error);
        if (error.name !== 'AbortError') {
            alert("An error occurred while generating the PDF for sharing.");
        }
    } finally {
        // 6. Reset the button
        btn.innerText = oldText;
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    }
}
</script>

<?php get_footer(); ?>