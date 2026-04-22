<?php
// This file renders the public quotation link for your clients.
get_header(); 

$quote_data = json_decode($post->post_content, true);

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

// Function to safely turn newlines into HTML bullets
function tcc_render_bullets($text) {
    if (empty(trim($text))) return "<p style='font-size:12px; color:#64748b; margin:0;'>None specified.</p>";
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $html = "<ul style='margin:0; padding-left:15px; font-size:12px; line-height:1.6; color:inherit;'>";
    foreach($lines as $line) if(trim($line) !== '') $html .= "<li>" . esc_html(trim($line)) . "</li>";
    $html .= "</ul>";
    return $html;
}

// Function to safely turn newlines into plain text WhatsApp bullets
function tcc_text_bullets($text) {
    if (empty(trim($text))) return "None specified.";
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $res = "";
    foreach($lines as $line) if(trim($line) !== '') $res .= "✓ " . trim($line) . "\n";
    return trim($res);
}

if(!$quote_data) {
    echo "<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Quotation Not Found or Expired.</h2></div>";
    get_footer();
    exit;
}

$d = $quote_data['summary'];
$raw_stays = isset($quote_data['raw']['itinerary_stay_place']) ? $quote_data['raw']['itinerary_stay_place'] : [];

// ----------------------------------------------------------------------
// PAYMENT & INVOICE LOGIC (With Refund Handling)
// ----------------------------------------------------------------------
$payments = get_post_meta($post->ID, 'tcc_payments', true);
if(!is_array($payments)) $payments = [];
$status = get_post_meta($post->ID, 'tcc_lead_status', true);

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

$grand_total = floatval($quote_data['grand_total']);
$balance = $is_cancelled ? 0 : max(0, $grand_total - $total_paid);

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

$txt_trip = "*Trip Details*\nStart Date: " . date('d M Y', strtotime($d['start_date'])) . "\nEnd Date: " . date('d M Y', strtotime($d['end_date'])) . "\nDuration: {$d['days']} Days / " . ($d['days'] - 1) . " Nights\nTravelers: {$d['pax']} Adults, {$d['child']} Child\nRooms & Beds: {$d['rooms']} Rooms / {$d['extra_beds']} Extra Beds\nHotel Category: {$d['hotel_cat']}\nTransport: " . strip_tags($d['transport_string']) . " (Pickup: {$d['pickup']} | Drop: {$drop_txt})";

$txt_itin = "*Itinerary Details*\n";
if(!empty($d['itinerary'])){
    foreach($d['itinerary'] as $idx => $t){
        if(trim($t)) {
            $stay_text = "";
            if (!empty($raw_stays[$idx])) {
                $stay_text = " n/s-" . trim($raw_stays[$idx]) . " ";
            }
            $txt_itin .= "*Day ".($idx+1).":* " . trim($t) . $stay_text . "\n";
        }
    }
} else {
    $txt_itin .= "No itinerary provided.\n";
}

$txt_hotels = "*Hotels Selected*\n";
if(!empty($d['stays'])){
    foreach($d['stays'] as $stay){
        $opts = [];
        foreach($stay['options'] as $opt){
            $str = $opt['name'];
            if(!empty($opt['link'])) $str .= " (".$opt['link'].")";
            $opts[] = $str;
        }
        $catText = !empty($stay['category']) ? "[{$stay['category']}] " : "";
        $txt_hotels .= "- {$stay['place']} ({$stay['nights']}N): {$catText}" . implode(' OR ', $opts) . " / Similar\n";
    }
} else {
    $txt_hotels .= "No hotels selected.\n";
}

$txt_inc = "*Included in Package*\n" . tcc_text_bullets($d['inclusions']);
$txt_exc = "*Excluded from Package*\n" . tcc_text_bullets($d['exclusions']);
$txt_pay = "*Payment Terms*\n" . tcc_text_bullets($d['payment_terms']);

$gst_pct_disp = isset($d['gst_pct']) ? $d['gst_pct'] : 5;
$pp_gst = isset($quote_data['per_person_with_gst']) ? $quote_data['per_person_with_gst'] : ($grand_total / max(1, $d['pax']));
$pax_count = max(1, $d['pax']);

$discount_amt = floatval($d['discount_amount']);
$final_base = $quote_data['per_person'] * $pax_count;
$initial_base = $final_base + $discount_amt;

$txt_price = "*Pricing Summary*\n";
$txt_price .= "Per Person (Excl. GST): " . tcc_format_inr($quote_data['per_person']) . "\n";

if($discount_amt > 0) {
    $txt_price .= "Total Base ({$pax_count} Pax): " . tcc_format_inr($initial_base) . "\n";
    $txt_price .= "Discount Applied: -" . tcc_format_inr($discount_amt) . "\n";
} else {
    $txt_price .= "Total Base ({$pax_count} Pax): " . tcc_format_inr($final_base) . "\n";
}

$txt_price .= "GST ({$gst_pct_disp}%): " . tcc_format_inr($quote_data['gst']) . "\n";
$txt_price .= "------------------------\n";
$txt_price .= "*Total Package Value:* " . tcc_format_inr($grand_total) . "\n";
$txt_price .= "*Per Person (Inc. GST):* " . tcc_format_inr($pp_gst) . "\n";

if ($is_cancelled) {
    $txt_price .= "\n*BOOKING CANCELLED*\n";
    $txt_price .= "Total Paid: " . tcc_format_inr($total_paid) . "\n";
    $txt_price .= "Amount Refunded: " . tcc_format_inr($total_refunded) . "\n";
    $txt_price .= "Cancellation Charges: " . tcc_format_inr($retained_income) . "\n";
} elseif ($total_paid > 0) {
    $txt_price .= "\n------------------------\n";
    $txt_price .= "*Total Received:* " . tcc_format_inr($total_paid) . "\n";
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
    .tcc-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 800px; margin: 20px auto; background: var(--tcc-bg-card); border-radius: var(--tcc-radius); box-shadow: 0 4px 12px rgba(0,0,0,0.08); color: var(--tcc-text); line-height: 1.5; }
    
    .tcc-header { background: var(--tcc-primary); color: #fff; padding: 25px 20px; text-align: center; border-radius: var(--tcc-radius) var(--tcc-radius) 0 0; }
    .tcc-header h1 { margin: 0 0 5px; font-size: 22px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
    .tcc-header h2 { margin: 0 0 2px; font-size: 16px; opacity: 0.9; font-weight: 500; }
    .tcc-header .badge { display: inline-block; background: var(--tcc-badge); padding: 5px 12px; border-radius: 20px; font-size: 11px; margin-top: 10px; font-weight: 600; letter-spacing: 0.5px; }

    .tcc-body { padding: 20px; }
    .tcc-section { margin-bottom: 25px; } 
    
    .tcc-section-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--tcc-border); padding-bottom: 8px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
    .tcc-section-title { font-size: 18px; font-weight: 700; color: var(--tcc-text-dark); margin: 0; display:flex; align-items:center; gap:8px; }

    .tcc-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 4px; cursor: pointer; border: none; transition: 0.2s; background: #64748b; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; }
    .tcc-btn:hover { background: #475569; color: #fff; }
    .tcc-btn-wa { background: #22c55e; }
    .tcc-btn-wa:hover { background: #16a34a; }

    .tcc-grid-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; background: #f8fafc; padding: 15px; border-radius: var(--tcc-radius); border: 1px solid var(--tcc-border); }
    .tcc-detail-item { font-size: 13px; color: var(--tcc-text-dark); font-weight: 500; }
    .tcc-detail-item span { display: block; font-size: 10px; color: var(--tcc-text-light); text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }

    .tcc-timeline { border-left: 2px solid var(--tcc-primary); margin-left: 10px; padding-left: 15px; }
    .tcc-timeline-item { position: relative; margin-bottom: 15px; }
    .tcc-timeline-item:last-child { margin-bottom: 0; }
    .tcc-timeline-item::before { content: ''; position: absolute; left: -22px; top: 2px; width: 12px; height: 12px; background: var(--tcc-primary); border: 2px solid #fff; border-radius: 50%; }
    .tcc-timeline-title { font-size: 13px; font-weight: 700; color: var(--tcc-primary); margin: 0 0 2px; }
    .tcc-timeline-desc { font-size: 13px; margin: 0; color: var(--tcc-text); }

    .tcc-price-grid { display: grid; grid-template-columns: 1fr; gap: 20px; background: #f8fafc; padding: 20px; border-radius: var(--tcc-radius); border: 1px solid var(--tcc-border); margin-bottom: 30px; }
    @media(min-width: 650px) { .tcc-price-grid { grid-template-columns: 1fr 1fr; } }
    .tcc-price-row { display: flex; justify-content: space-between; font-size: 14.5px; color: var(--tcc-text-light); margin-bottom: 12px; }
    .tcc-price-row strong { color: var(--tcc-text-dark); }
    .tcc-price-total { display: flex; justify-content: space-between; align-items: center; font-size: 18px; font-weight: 900; color: var(--tcc-text-dark); margin-top: 15px; padding-top: 15px; border-top: 2px dashed var(--tcc-border); }
    .tcc-price-highlight { display: flex; justify-content: space-between; align-items: center; background: #eff6ff; color: #1d4ed8; padding: 12px 15px; border-radius: 6px; font-weight: 700; margin-top: 12px; border: 1px solid #bfdbfe; font-size: 15px; }
    .tcc-price-highlight span:last-child { font-size: 17px; }
    .tcc-status-box { text-align: center; margin-top: 15px; padding: 12px; border-radius: 6px; font-weight: 900; font-size: 16px; letter-spacing: 1px; }

    .tcc-inc-grid { display: grid; grid-template-columns: 1fr; gap: 15px; }
    @media(min-width: 650px) { .tcc-inc-grid { grid-template-columns: 1fr 1fr 1fr; } }
    .tcc-inc-card { padding: 18px; border-radius: var(--tcc-radius); }
    .tcc-inc-card h4 { margin: 0 0 12px; font-size: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 5px; }
    
    .tcc-inc-green { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .tcc-inc-green h4 { color: #15803d; }
    .tcc-inc-red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .tcc-inc-red h4 { color: #b91c1c; }
    .tcc-inc-yellow { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .tcc-inc-yellow h4 { color: #b45309; }

    .tcc-container .WA-copy-feedback { pointer-events: none; position: fixed; top: 10px; left: 50%; transform: translateX(-50%) translateY(-20px); background: rgba(0,0,0,0.8); color: #fff; padding: 6px 15px; border-radius: 20px; font-size: 11px; font-weight: bold; opacity: 0; transition: 0.3s; z-index: 999; }
    .tcc-container .WA-copy-feedback.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    
    /* Responsive Mode */
    @media(max-width: 600px) {
        .tcc-container { margin: 0; border-radius: 0; border: none; box-shadow: none; }
        .tcc-header { padding: 20px 15px; }
        .tcc-header h1 { font-size: 18px; }
        .tcc-body { padding: 15px; }
        .tcc-grid-details { grid-template-columns: 1fr 1fr; gap: 15px 10px; padding: 12px; }
        .tcc-price-grid { padding: 15px; gap: 15px; }
        .tcc-inc-grid { gap: 12px; }
        .tcc-inc-card h4 { font-size: 14px; }
    }
</style>

<div class="tcc-container" id="tcc_web_view">
    <div class="WA-copy-feedback" id="tcc_copy_feedback">Copied to clipboard!</div>
    
    <div class="tcc-header">
        <h1>SOULFUL TOUR & TRAVELS</h1>
        <h2>GSTIN: 19AXIPD7432L1Z5</h2>
        <div class="badge"><?php echo $doc_title; ?></div>
        <p style="margin-top:12px; font-size:14px; font-weight:500;">
            Prepared for: <strong><?php echo isset($d['client_name']) && !empty($d['client_name']) ? esc_html($d['client_name']) : 'Valued Client'; ?></strong>
            <?php if(isset($d['client_phone']) && !empty($d['client_phone'])): ?>
                <br>📞 <?php echo esc_html($d['client_phone']); ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="tcc-body">

        <div class="tcc-section">
            <div class="tcc-section-header" style="padding-right: 5px;">
                <h3 class="tcc-section-title">📍 Trip Details</h3>
                <?php if(is_user_logged_in()): ?>
                    <div class="tcc-action-buttons" style="display:flex; gap:5px;">
                        <button class="tcc-btn" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_trip); ?>')">📋 Copy</button>
                        <a href="<?php echo $wa_api_base . rawurlencode($txt_trip); ?>" target="_blank" class="tcc-btn tcc-btn-wa">💬 Send</a>
                        <button class="tcc-btn" style="background:#2563eb;" onclick="tccSendEmail(this, <?php echo $post->ID; ?>, '<?php echo esc_js(isset($d['client_email']) ? $d['client_email'] : ''); ?>', '<?php echo esc_js(isset($d['client_name']) ? $d['client_name'] : ''); ?>', '<?php echo esc_js(isset($d['client_phone']) ? $d['client_phone'] : ''); ?>')">📧 Email</button>
                        <button class="tcc-btn" style="background:#dc2626;" onclick="tccDownloadPDF(this)">📄 PDF</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tcc-grid-details">
                <div class="tcc-detail-item"><span>Destination</span> <?php echo esc_html($d['destination']); ?></div>
                <div class="tcc-detail-item"><span>Start Date</span> <?php echo date('d M Y', strtotime($d['start_date'])); ?></div>
                <div class="tcc-detail-item"><span>End Date</span> <?php echo date('d M Y', strtotime($d['end_date'])); ?></div>
                <div class="tcc-detail-item"><span>Duration</span> <?php echo esc_html($d['days']); ?> Days / <?php echo esc_html($d['days'] - 1); ?> Nights</div>
                <div class="tcc-detail-item"><span>Travelers</span> <?php echo esc_html($d['pax']); ?> Ad, <?php echo esc_html($d['child']); ?> Ch</div>
                <div class="tcc-detail-item"><span>Rooms</span> <?php echo esc_html($d['rooms']); ?> R / <?php echo esc_html($d['extra_beds']); ?> EB</div>
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
            <div class="tcc-timeline">
                <?php foreach($d['itinerary'] as $index => $day_text): 
                    if(trim($day_text) === '') continue;
                    
                    $stay_html = "";
                    if (!empty($raw_stays[$index])) {
                        $stay_html = " <strong style='color:var(--tcc-primary);'>n/s-" . esc_html(trim($raw_stays[$index])) . "</strong>";
                    }
                ?>
                    <div class="tcc-timeline-item">
                        <h4 class="tcc-timeline-title">Day <?php echo $index + 1; ?></h4>
                        <p class="tcc-timeline-desc"><?php echo esc_html($day_text) . $stay_html; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="tcc-section">
            <div class="tcc-section-header" style="padding-right: 5px; border-bottom: none;">
                <h3 class="tcc-section-title">
                    <span style="font-size:20px;">🏨</span> <span style="background:#e2e8f0; padding:4px 8px; border-radius:4px;">Hotels Selected</span>
                </h3>
                <?php if(is_user_logged_in()): ?>
                    <div class="tcc-action-buttons" style="display:flex; gap:5px;">
                        <button class="tcc-btn tcc-btn-wa" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_hotels); ?>')">WA Copy</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tcc-hotels-wrapper">
                <?php foreach($d['stays'] as $stay): 
                    $formatted_hotels = array();
                    foreach($stay['options'] as $opt) {
                        $link = !empty($opt['link']) ? " <a href='".esc_url($opt['link'])."' target='_blank' style='color:#2563eb; text-decoration:underline; font-size:11px;'>(View)</a>" : '';
                        $formatted_hotels[] = "<strong style='color:#0f172a;'>".esc_html($opt['name'])."</strong>" . $link;
                    }
                    $combined_hotels = implode(' <span style="color:#94a3b8; font-size:11px; font-weight:normal; margin:0 2px;">/</span> ', $formatted_hotels);
                    if(empty($combined_hotels)) $combined_hotels = "No selection made";
                    
                    $loc_cat = isset($stay['category']) && !empty($stay['category']) ? $stay['category'] : $d['hotel_cat'];
                ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 15px; background: #fff; font-family: inherit;">
                    <div style="display: flex; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; align-items: center;">
                        <div style="width: 80px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Location</div>
                        <div style="flex: 1; text-align: center; font-weight: 600; color: #0f172a; font-size: 14px;"><?php echo esc_html($stay['place']); ?></div>
                        <div style="width: 100px; text-align: right; font-size: 12px; font-weight: 600; color: #b91c1c;"><?php echo esc_html($loc_cat); ?></div>
                    </div>
                    <div style="display: flex; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; align-items: center;">
                        <div style="width: 80px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Hotels</div>
                        <div style="flex: 1; text-align: right; font-size: 14px; color: #0f172a; line-height: 1.4;"><?php echo $combined_hotels; ?> <span style="color:#64748b; font-size:12px;">/ Similar</span></div>
                    </div>
                    <div style="display: flex; padding: 12px 15px; align-items: center;">
                        <div style="width: 80px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Nights</div>
                        <div style="flex: 1; text-align: right; font-weight: 700; font-size: 15px; color: #0f172a;"><?php echo esc_html($stay['nights']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

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
                        <table style="width:100%; border-collapse:collapse; font-size:13.5px; text-align:left; background:#fff; border-radius:6px; overflow:hidden; border:1px solid #e2e8f0;">
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
                                    $amt_color = $is_refund ? '#dc2626' : '#16a34a';
                                    $amt_prefix = $is_refund ? '-' : '';
                                ?>
                                <tr style="border-bottom:1px solid #f8fafc;">
                                    <td data-label="Date" style="padding:10px 12px; color:#334155;"><?php echo esc_html(date('d M y', strtotime($p['date']))); ?></td>
                                    <td data-label="Method" style="padding:10px 12px; color:#64748b;"><?php echo esc_html($p['method']); ?></td>
                                    <td data-label="Amount" style="padding:10px 12px; text-align:right; font-weight:bold; color:<?php echo $amt_color; ?>;"><?php echo $amt_prefix . tcc_format_inr($p['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="background:#fff; border:1px dashed var(--tcc-border); border-radius:6px; padding:20px; text-align:center; font-size:14px; color:var(--tcc-text-light);">No payments recorded yet.</div>
                    <?php endif; ?>
                </div>

                <div class="tcc-price-box" style="background:#fff; padding:20px; border-radius:8px; border:1px solid var(--tcc-border); box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                    <h4 style="color:var(--tcc-text-dark); font-size:16px; margin:0 0 15px 0; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Pricing Summary</h4>
                    
                    <div class="tcc-price-row"><span>Per Person (Excl. GST):</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($quote_data['per_person']); ?></strong></div>
                    
                    <?php if($discount_amt > 0): ?>
                        <div class="tcc-price-row"><span>Total Base (<?php echo esc_html($pax_count); ?> Pax):</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($initial_base); ?></strong></div>
                        <div class="tcc-price-row" style="color:#dc2626;"><span>Discount Applied:</span> <strong>-<?php echo tcc_format_inr($discount_amt); ?></strong></div>
                    <?php else: ?>
                        <div class="tcc-price-row"><span>Total Base (<?php echo esc_html($pax_count); ?> Pax):</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($final_base); ?></strong></div>
                    <?php endif; ?>
                    
                    <div class="tcc-price-row"><span>GST (<?php echo $gst_pct_disp; ?>%):</span> <strong style="color:#0f172a;"><?php echo tcc_format_inr($quote_data['gst']); ?></strong></div>

                    <div class="tcc-price-total">
                        <span>Total Package Value:</span>
                        <span style="<?php echo $is_cancelled ? 'text-decoration:line-through; opacity:0.5;' : ''; ?>"><?php echo tcc_format_inr($grand_total); ?></span>
                    </div>

                    <div class="tcc-price-highlight">
                        <span>Per Person (Inc. GST):</span>
                        <span><?php echo tcc_format_inr($pp_gst); ?></span>
                    </div>

                    <?php if($is_cancelled): ?>
                        <div style="margin-top:20px; padding-top:15px; border-top:2px solid #fee2e2;">
                            <div class="tcc-price-row"><span>Total Received:</span> <strong style="color:#15803d;"><?php echo tcc_format_inr($total_paid); ?></strong></div>
                            <div class="tcc-price-row" style="color:#dc2626;"><span>Amount Refunded:</span> <strong><?php echo tcc_format_inr($total_refunded); ?></strong></div>
                            <div class="tcc-price-row" style="margin-top:10px; padding-top:10px; border-top:1px dashed var(--tcc-border); font-size:16px; font-weight:700; color:#16a34a;"><span>Cancellation Charges:</span> <span><?php echo tcc_format_inr($retained_income); ?></span></div>
                            <div class="tcc-status-box" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca;">STATUS: CANCELLED</div>
                        </div>
                    <?php elseif($total_paid > 0): ?>
                        <div style="margin-top:20px; padding-top:15px; border-top:2px solid var(--tcc-border);">
                            <div class="tcc-price-row" style="color:#16a34a; font-weight:600; font-size:15px;"><span>Total Received:</span> <span><?php echo tcc_format_inr($total_paid); ?></span></div>
                            <div class="tcc-status-box" style="background:<?php echo $balance > 0 ? '#fef2f2' : '#f0fdf4'; ?>; color:<?php echo $balance > 0 ? '#dc2626' : '#15803d'; ?>; border:1px solid <?php echo $balance > 0 ? '#fecaca' : '#bbf7d0'; ?>; display:flex; justify-content:space-between; align-items:center;">
                                <span><?php echo $balance > 0 ? 'Balance Due:' : 'Balance Cleared:'; ?></span>
                                <span style="font-size:16px;"><?php echo tcc_format_inr($balance); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <div class="tcc-inc-grid">
            <div class="tcc-inc-card tcc-inc-green">
                <h4>
                    <span>✅ Included</span> 
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:3px 6px; font-size:9px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_inc); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_inc); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:3px 6px; font-size:9px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['inclusions']); ?>
            </div>
            
            <div class="tcc-inc-card tcc-inc-red">
                <h4>
                    <span>❌ Excluded</span> 
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:3px 6px; font-size:9px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_exc); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_exc); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:3px 6px; font-size:9px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['exclusions']); ?>
            </div>
            
            <div class="tcc-inc-card tcc-inc-yellow">
                <h4>
                    <span>💳 Payment Terms</span> 
                    <?php if(is_user_logged_in()): ?>
                        <div class="tcc-action-buttons" style="display:flex; gap:3px;">
                            <button class="tcc-btn" style="padding:3px 6px; font-size:9px;" onclick="tccCopySection(this, '<?php echo rawurlencode($txt_pay); ?>')">Copy</button>
                            <a href="<?php echo $wa_api_base . rawurlencode($txt_pay); ?>" target="_blank" class="tcc-btn tcc-btn-wa" style="padding:3px 6px; font-size:9px;">Send</a>
                        </div>
                    <?php endif; ?>
                </h4>
                <?php echo tcc_render_bullets($d['payment_terms']); ?>
            </div>
        </div>

    </div>
</div>

<div id="tcc_hidden_pdf_template" style="display:none; width: 800px; padding: 25px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; background: #fff; box-sizing: border-box;">
    
    <div style="text-align: center; border-bottom: 2px solid <?php echo $doc_color; ?>; padding-bottom: 15px; margin-bottom: 20px;">
        <h1 style="margin: 0 0 5px; font-size: 24px; color: <?php echo $doc_color; ?>; text-transform: uppercase;">SOULFUL TOUR & TRAVELS</h1>
        <div style="font-size: 12px; color: #64748b; margin-bottom: 10px;">GSTIN: 19AXIPD7432L1Z5</div>
        <div style="display: inline-block; background: <?php echo $badge_bg; ?>; color: #fff; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 15px;"><?php echo $doc_title; ?></div>
        
        <table style="width: 100%; border: none; font-size: 13px;">
            <tr>
                <td style="text-align: left; width: 50%;"><strong>Prepared For:</strong> <?php echo esc_html(isset($d['client_name']) && !empty($d['client_name']) ? $d['client_name'] : 'Valued Client'); ?></td>
                <td style="text-align: right; width: 50%;"><strong>Date:</strong> <?php echo date('d M Y'); ?></td>
            </tr>
            <tr>
                <td style="text-align: left; width: 50%;"><strong>Phone:</strong> <?php echo esc_html(isset($d['client_phone']) && !empty($d['client_phone']) ? $d['client_phone'] : 'N/A'); ?></td>
                <td style="text-align: right; width: 50%;"><strong>Email:</strong> <?php echo esc_html(isset($d['client_email']) && !empty($d['client_email']) ? $d['client_email'] : 'N/A'); ?></td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 5px; font-size: 16px; margin-bottom: 10px;">📍 Trip Details</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <tr>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0; width: 20%;">Destination</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0; width: 30%;"><?php echo esc_html($d['destination']); ?></td>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0; width: 20%;">Duration</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0; width: 30%;"><?php echo esc_html($d['days']); ?> Days / <?php echo esc_html($d['days'] - 1); ?> Nights</td>
            </tr>
            <tr>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Start Date</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?php echo date('d M Y', strtotime($d['start_date'])); ?></td>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">End Date</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?php echo date('d M Y', strtotime($d['end_date'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Travelers</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?php echo esc_html($d['pax']); ?> Adults, <?php echo esc_html($d['child']); ?> Child</td>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Rooms</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?php echo esc_html($d['rooms']); ?> Rooms / <?php echo esc_html($d['extra_beds']); ?> Extra Beds</td>
            </tr>
            <tr>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Category</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?php echo esc_html($d['hotel_cat']); ?></td>
                <td style="padding: 8px; background: #f8fafc; font-weight: bold; border: 1px solid #e2e8f0;">Transport</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><?php echo wp_kses_post($d['transport_string']); ?><br><span style="font-size:10px; color:#64748b;">(Pickup: <?php echo esc_html($d['pickup']); ?> | Drop: <?php echo esc_html($drop_txt); ?>)</span></td>
            </tr>
        </table>
    </div>

    <?php if(isset($d['itinerary']) && !empty($d['itinerary']) && array_filter($d['itinerary'])): ?>
    <div style="margin-bottom: 20px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 5px; font-size: 16px; margin-bottom: 10px;">📅 Itinerary Details</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <?php foreach($d['itinerary'] as $index => $day_text): 
                if(trim($day_text) === '') continue;
                $stay_html = !empty($raw_stays[$index]) ? " <strong style='color:".$doc_color.";'>[n/s-" . esc_html(trim($raw_stays[$index])) . "]</strong>" : "";
            ?>
            <tr class="pdf-avoid-break">
                <td style="padding: 10px; background: #f8fafc; font-weight: bold; color: <?php echo $doc_color; ?>; border: 1px solid #e2e8f0; width: 12%; vertical-align: top;">Day <?php echo $index + 1; ?></td>
                <td style="padding: 10px; border: 1px solid #e2e8f0; line-height: 1.5; width: 88%; vertical-align: top;"><?php echo esc_html($day_text) . $stay_html; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 5px; font-size: 16px; margin-bottom: 10px;">🏨 Hotels Selected</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0;">
            <thead>
                <tr style="background: #f1f5f9;">
                    <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left; width: 25%;">Location</th>
                    <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left; width: 60%;">Hotels / Similar</th>
                    <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; width: 15%;">Nights</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($d['stays'] as $stay): 
                    $formatted_hotels = array();
                    foreach($stay['options'] as $opt) { $formatted_hotels[] = "<strong>".esc_html($opt['name'])."</strong>"; }
                    $combined_hotels = implode(' / ', $formatted_hotels);
                    if(empty($combined_hotels)) $combined_hotels = "No selection made";
                    $loc_cat = isset($stay['category']) && !empty($stay['category']) ? $stay['category'] : $d['hotel_cat'];
                ?>
                <tr class="pdf-avoid-break">
                    <td style="padding: 8px; border: 1px solid #e2e8f0; vertical-align: top;"><strong><?php echo esc_html($stay['place']); ?></strong><br><span style="font-size:10px; color:#b91c1c;"><?php echo esc_html($loc_cat); ?></span></td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; vertical-align: top; line-height: 1.5;"><?php echo $combined_hotels; ?></td>
                    <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; font-weight: bold; vertical-align: middle;"><?php echo esc_html($stay['nights']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pdf-avoid-break" style="margin-bottom: 20px;">
        <h3 style="border-bottom: 1px solid #e2e8f0; color: #0f172a; padding-bottom: 5px; font-size: 16px; margin-bottom: 10px;">🧾 Pricing Summary</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc; width: 60%;">Per Person (Excl. GST)</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold; width: 40%;"><?php echo tcc_format_inr($quote_data['per_person']); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc;">Total Base (<?php echo esc_html($pax_count); ?> Pax)</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($discount_amt > 0 ? $initial_base : $final_base); ?></td>
            </tr>
            <?php if($discount_amt > 0): ?>
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #fef2f2; color: #dc2626;">Discount Applied</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #fef2f2; text-align: right; font-weight: bold; color: #dc2626;">-<?php echo tcc_format_inr($discount_amt); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; background: #f8fafc;">GST (<?php echo $gst_pct_disp; ?>%)</td>
                <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($quote_data['gst']); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e2e8f0; background: #f1f5f9; font-size: 15px; font-weight: bold;">Total Package Value</td>
                <td style="padding: 10px; border: 1px solid #e2e8f0; background: #f1f5f9; text-align: right; font-size: 15px; font-weight: bold;"><?php echo tcc_format_inr($grand_total); ?></td>
            </tr>
            <tr>
                <td style="padding: 12px 10px; border: 1px solid #bfdbfe; background: #eff6ff; font-size: 16px; font-weight: bold; color: #1d4ed8;">Per Person (Inc. GST)</td>
                <td style="padding: 12px 10px; border: 1px solid #bfdbfe; background: #eff6ff; text-align: right; font-size: 16px; font-weight: bold; color: #1d4ed8;"><?php echo tcc_format_inr($pp_gst); ?></td>
            </tr>
            <?php if($is_cancelled): ?>
                <tr><td colspan="2" style="padding: 10px; border: 1px solid #e2e8f0; background: #fef2f2; color: #dc2626; text-align: center; font-weight: bold;">BOOKING CANCELLED</td></tr>
            <?php elseif($total_paid > 0): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; color: #16a34a; font-weight: bold;">Total Received</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; color: #16a34a; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($total_paid); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; color: #dc2626; font-weight: bold;">Balance Due</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; color: #dc2626; text-align: right; font-weight: bold;"><?php echo tcc_format_inr($balance); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="pdf-avoid-break">
        <table style="width: 100%; border-collapse: separate; border-spacing: 15px 0; margin-left: -15px; margin-right: -15px; font-size: 11px;">
            <tr>
                <td style="width: 33.3%; padding: 15px; vertical-align: top; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; border-radius: 4px;">
                    <h4 style="margin: 0 0 8px; font-size: 13px; border-bottom: 1px solid #bbf7d0; padding-bottom: 4px;">✅ Included</h4>
                    <?php echo tcc_render_bullets($d['inclusions']); ?>
                </td>
                <td style="width: 33.3%; padding: 15px; vertical-align: top; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 4px;">
                    <h4 style="margin: 0 0 8px; font-size: 13px; border-bottom: 1px solid #fecaca; padding-bottom: 4px;">❌ Excluded</h4>
                    <?php echo tcc_render_bullets($d['exclusions']); ?>
                </td>
                <td style="width: 33.3%; padding: 15px; vertical-align: top; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 4px;">
                    <h4 style="margin: 0 0 8px; font-size: 13px; border-bottom: 1px solid #fde68a; padding-bottom: 4px;">💳 Payment Terms</h4>
                    <?php echo tcc_render_bullets($d['payment_terms']); ?>
                </td>
            </tr>
        </table>
    </div>
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

function tccDownloadPDF(btn) {
    let oldText = btn.innerText;
    btn.innerText = 'Preparing PDF...';
    
    // Get the pure HTML content of your hidden template
    let pdfContent = document.getElementById('tcc_hidden_pdf_template').innerHTML;
    let docTitle = 'Quotation_<?php echo sanitize_file_name($d['client_name'] . "_" . $d['destination']); ?>';
    
    // Open a completely clean, isolated popup window
    let printWindow = window.open('', '_blank', 'width=900,height=800');
    
    if (!printWindow) {
        alert("Please allow pop-ups in your browser to generate the PDF.");
        btn.innerText = oldText;
        return;
    }

    // Write the document into the new window with strict print styles
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${docTitle}</title>
            <style>
                /* Base styles for the isolated window */
                body { 
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
                    color: #333; 
                    margin: 0 auto;
                    max-width: 800px;
                    padding: 20px;
                    background: #fff;
                }
                
                /* FIX FOR GIANT ICONS: Replicate WordPress Emoji CSS */
                img.emoji {
                    display: inline !important;
                    border: none !important;
                    box-shadow: none !important;
                    height: 1em !important;
                    width: 1em !important;
                    margin: 0 0.07em !important;
                    vertical-align: -0.1em !important;
                    background: none !important;
                    padding: 0 !important;
                }

                /* Backup fix for any other custom images placed inside headings */
                h1 img, h2 img, h3 img, h4 img {
                    max-height: 1.2em;
                    width: auto;
                    vertical-align: middle;
                }
                
                /* CRITICAL: This tells the native browser NEVER to slice tables or headings in half */
                .pdf-avoid-break, tr, h3, td { 
                    page-break-inside: avoid !important; 
                    break-inside: avoid !important; 
                }
                
                /* Print-specific layout rules */
                @media print {
                    body { 
                        padding: 0; 
                        max-width: 100%; 
                        margin: 0;
                    }
                    /* Sets a clean 15mm margin on the PDF pages */
                    @page { 
                        margin: 15mm; 
                        size: A4 portrait;
                    }
                }
            </style>
        </head>
        <body>
            ${pdfContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Give the browser half a second to render the HTML, then trigger the print dialog
    setTimeout(() => {
        printWindow.print();
        btn.innerText = oldText;
    }, 500);
}
</script>

<?php get_footer(); ?>