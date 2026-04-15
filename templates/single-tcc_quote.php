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
    if (empty(trim($text))) return "<p style='font-size:13px; color:#718096;'>None specified.</p>";
    $lines = explode("\n", $text);
    $html = "<ul style='margin:0; padding-left:20px; font-size:14px; line-height:1.6;'>";
    foreach($lines as $line) {
        if(trim($line) !== '') $html .= "<li>" . esc_html(trim($line)) . "</li>";
    }
    $html .= "</ul>";
    return $html;
}

if(!$quote_data) {
    echo "<div style='padding:50px; text-align:center;'><h2>Quotation Not Found or Expired.</h2></div>";
    get_footer();
    exit;
}

$d = $quote_data['summary'];
?>

<div class="tcc-public-quote-wrapper" style="max-width:800px; margin:40px auto; padding:20px; background:#fff; border-radius:4px; border:1px solid #e2e8f0; font-family:inherit; color:#333;">
    
    <div style="text-align:center; border-bottom:2px solid #b93b59; padding-bottom:15px; margin-bottom:20px;">
        <h1 style="color:#b93b59; margin:0 0 5px 0; font-size:24px;">Your Travel Quotation</h1>
        <p style="color:#666; margin:0; font-size:14px;">Prepared for your upcoming trip to <strong><?php echo esc_html($d['destination']); ?></strong></p>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:10px; background:#fdf2f5; padding:15px; border-radius:3px; margin-bottom:20px; border:1px solid #f9dbe1; font-size:13px;">
        <div><strong>Start Date:</strong> <?php echo date('d M Y', strtotime($d['start_date'])); ?></div>
        <div><strong>End Date:</strong> <?php echo date('d M Y', strtotime($d['end_date'])); ?></div>
        <div><strong>Duration:</strong> <?php echo esc_html($d['days']); ?> Days / <?php echo esc_html($d['days'] - 1); ?> Nights</div>
        <div><strong>Travelers:</strong> <?php echo esc_html($d['pax']); ?> Adults, <?php echo esc_html($d['child']); ?> Child</div>
        <div><strong>Rooms & Beds:</strong> <?php echo esc_html($d['rooms']); ?> Rooms / <?php echo esc_html($d['extra_beds']); ?> Extra Beds</div>
        <div><strong>Hotel Category:</strong> <?php echo esc_html($d['hotel_cat']); ?></div>
        <div style="grid-column: 1 / -1;"><strong>Transport:</strong> <?php echo wp_kses_post($d['transport_string']); ?> <small style="color:#666;">(Pickup: <?php echo esc_html($d['pickup']); ?>)</small></div>
    </div>

    <h3 style="color:#222; margin-bottom:10px; font-size:16px;">Itinerary & Stay Details</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; margin-bottom:25px; font-size:13px;">
            <thead>
                <tr>
                    <th style="background:#f9f9f9; padding:10px; border:1px solid #ddd; text-align:left; color:#444;">Location</th>
                    <th style="background:#f9f9f9; padding:10px; border:1px solid #ddd; text-align:left; color:#444;">Hotel Options</th>
                    <th style="background:#f9f9f9; padding:10px; border:1px solid #ddd; text-align:center; color:#444;">Nights</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($d['stays'] as $stay): 
                    $formatted_hotels = array();
                    foreach($stay['options'] as $opt) {
                        $link = $opt['link'] ? " <a href='".esc_url($opt['link'])."' target='_blank' style='font-size:11px; color:#b93b59; text-decoration:none;'>(View)</a>" : '';
                        $formatted_hotels[] = "<strong>".esc_html($opt['name'])."</strong>" . $link;
                    }
                    $combined_hotels = implode(' <span style="color:#e53e3e; font-size:10px;">OR</span> ', $formatted_hotels);
                    if(empty($combined_hotels)) $combined_hotels = "No selection made";
                ?>
                <tr>
                    <td style="padding:10px; border:1px solid #eee;"><?php echo esc_html($stay['place']); ?></td>
                    <td style="padding:10px; border:1px solid #eee;"><?php echo $combined_hotels; ?> / Similar</td>
                    <td style="padding:10px; border:1px solid #eee; text-align:center;"><?php echo esc_html($stay['nights']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:15px; margin-bottom:25px;">
        <div style="background:#f0fdf4; padding:15px; border:1px solid #c6f6d5; border-radius:4px;">
            <h4 style="color:#22543d; margin-top:0; margin-bottom:10px; font-size:15px;">Included in Package</h4>
            <?php echo tcc_render_bullets($d['inclusions']); ?>
        </div>
        <div style="background:#fff5f5; padding:15px; border:1px solid #fed7d7; border-radius:4px;">
            <h4 style="color:#9b2c2c; margin-top:0; margin-bottom:10px; font-size:15px;">Excluded from Package</h4>
            <?php echo tcc_render_bullets($d['exclusions']); ?>
        </div>
        <div style="background:#fffaf0; padding:15px; border:1px solid #f6e05e; border-radius:4px;">
            <h4 style="color:#975a16; margin-top:0; margin-bottom:10px; font-size:15px;">Payment Terms</h4>
            <?php echo tcc_render_bullets($d['payment_terms']); ?>
        </div>
    </div>

    <div style="background:#f9f9f9; padding:15px; border-radius:3px; border:1px solid #ddd; text-align:right;">
        <p style="margin:4px 0; font-size:14px; color:#444;">Per Person Base: <strong><?php echo tcc_format_inr($quote_data['per_person']); ?></strong></p>
        <p style="margin:4px 0; font-size:14px; color:#444;">GST (5%): <strong><?php echo tcc_format_inr($quote_data['gst']); ?></strong></p>
        
        <?php if($d['discount_amount'] > 0): ?>
            <p style="margin:4px 0; font-size:14px; color:#b93b59;">Discount Applied: <strong>-<?php echo tcc_format_inr($d['discount_amount']); ?></strong></p>
        <?php endif; ?>

        <h2 style="margin:10px 0 0 0; color:#b93b59; font-size:22px; border-top:1px dashed #ccc; padding-top:10px;">Total Quote: <?php echo tcc_format_inr($quote_data['grand_total']); ?></h2>
    </div>

</div>

<?php get_footer(); ?>