<?php
/**
 * Template: single-tcc_package.php
 * Public-facing itinerary page generated from a saved preset.
 * Place in /templates/ inside the plugin folder.
 */
get_header();

if ( ! have_posts() ) {
    echo '<p style="text-align:center;padding:60px;font-family:sans-serif;color:#64748b;">Package not found.</p>';
    get_footer();
    exit;
}

while ( have_posts() ) : the_post();

$pid = get_the_ID();

// ── Meta fields ──────────────────────────────────────────────────────────────
$destination    = get_post_meta( $pid, '_tcc_pkg_destination', true );
$days           = (int) get_post_meta( $pid, '_tcc_pkg_days', true );
$nights         = max( 0, $days - 1 );
$route          = get_post_meta( $pid, '_tcc_pkg_route', true );
$linked_tour    = (int) get_post_meta( $pid, '_tcc_pkg_linked_tour', true );

$itinerary      = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary', true ), true )      ?: array();
$itinerary_desc = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary_desc', true ), true ) ?: array();
$itinerary_img  = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary_img', true ), true )  ?: array();
$stay_places    = json_decode( get_post_meta( $pid, '_tcc_pkg_stay_places', true ), true )     ?: array();
$routing        = json_decode( get_post_meta( $pid, '_tcc_pkg_routing', true ), true )         ?: array();
$inclusions     = get_post_meta( $pid, '_tcc_pkg_inclusions', true );
$exclusions     = get_post_meta( $pid, '_tcc_pkg_exclusions', true );
$payment_terms  = get_post_meta( $pid, '_tcc_pkg_payment_terms', true );

$r_places       = $routing['places']     ?? array();
$r_categories   = $routing['categories'] ?? array();
$r_nights       = $routing['nights']     ?? array();
$r_hotels       = $routing['hotels']     ?? array();
$r_room_types   = $routing['room_types'] ?? array();

// Hero image
$hero_img = has_post_thumbnail() ? get_the_post_thumbnail_url( $pid, 'full' ) : '';
if ( ! $hero_img ) {
    foreach ( $itinerary_img as $img ) { if ( $img ) { $hero_img = $img; break; } }
}

$unique_stays = array_unique( array_filter( array_values( $stay_places ) ) );

// WhatsApp CTA
$wa_number = get_option( 'tcc_wa_number', '' );
$clean_wa  = preg_replace( '/[^0-9]/', '', $wa_number );
$wa_text   = "Hi! I am interested in the *" . get_the_title() . "* package (" . $days . " Days / " . $nights . " Nights - " . $destination . "). Please share availability and pricing.";
$wa_url    = 'https://wa.me/' . $clean_wa . '?text=' . rawurlencode( $wa_text );

// Helper: render RTE or plain text as bullet list
function tcc_pkg_render_rte( $html ) {
    if ( empty( trim( wp_strip_all_tags( $html ) ) ) ) return '';
    if ( preg_match( '/<(p|li|br|ul|div|strong|em)[^>]*>/i', $html ) ) {
        return '<div class="tcc-sp-rte">' . $html . '</div>';
    }
    $lines = array_filter( preg_split( '/[\r\n]+/', $html ) );
    $out   = '<ul class="tcc-sp-rte">';
    foreach ( $lines as $l ) $out .= '<li>' . esc_html( trim( $l ) ) . '</li>';
    return $out . '</ul>';
}

endwhile;
?>
<style>
/* ── Package Page Styles ──────────────────────────────────────────────── */
.tcc-sp-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#334155;max-width:100%;margin:0;}
.tcc-sp-wrap *{box-sizing:border-box;}

/* Hero */
.tcc-sp-hero{position:relative;height:420px;overflow:hidden;background:linear-gradient(135deg,#1e3a8a,#2563eb);}
.tcc-sp-hero img{width:100%;height:100%;object-fit:cover;display:block;}
.tcc-sp-hero-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.65) 0%,rgba(0,0,0,.2) 55%,transparent 100%);}
.tcc-sp-hero-content{position:absolute;bottom:0;left:0;right:0;padding:32px 5%;color:#fff;}
.tcc-sp-hero-dest{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#93c5fd;margin-bottom:8px;}
.tcc-sp-hero-title{font-size:34px;font-weight:900;margin:0 0 14px;line-height:1.2;text-shadow:0 2px 8px rgba(0,0,0,.4);}
.tcc-sp-hero-badges{display:flex;gap:10px;flex-wrap:wrap;}
.tcc-sp-hero-badge{background:rgba(255,255,255,.18);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.28);color:#fff;font-size:12px;font-weight:700;padding:5px 14px;border-radius:20px;}

/* Body */
.tcc-sp-body{max-width:860px;margin:0 auto;padding:36px 5% 72px;}
.tcc-sp-section{margin-bottom:44px;}
.tcc-sp-section-title{font-size:20px;font-weight:800;color:#0f172a;border-left:4px solid #b93b59;padding-left:12px;margin:0 0 22px;}

/* Overview chips */
.tcc-sp-overview{display:flex;gap:16px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:22px;margin-bottom:36px;}
.tcc-sp-chip{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:80px;}
.tcc-sp-chip-icon{font-size:24px;}
.tcc-sp-chip-label{font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;}
.tcc-sp-chip-value{font-size:14px;color:#0f172a;font-weight:800;text-align:center;line-height:1.3;}

/* Timeline */
.tcc-sp-timeline{border-left:3px solid #e2e8f0;padding-left:28px;margin-left:12px;}
.tcc-sp-day{position:relative;margin-bottom:36px;}
.tcc-sp-day:last-child{margin-bottom:0;}
.tcc-sp-day::before{content:'';position:absolute;left:-37px;top:3px;width:16px;height:16px;background:#b93b59;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 2px #b93b59;}
.tcc-sp-day-num{font-size:12px;font-weight:700;text-transform:uppercase;color:#b93b59;letter-spacing:1px;margin-bottom:4px;}
.tcc-sp-day-title{font-size:16px;font-weight:800;color:#0f172a;margin-bottom:10px;}
.tcc-sp-day-img{width:100%;border-radius:10px;margin:12px 0;max-height:360px;object-fit:cover;border:1px solid #e2e8f0;}
.tcc-sp-day-desc{font-size:14px;color:#475569;line-height:1.7;}
.tcc-sp-day-stay{display:inline-block;margin-top:12px;font-size:12px;font-weight:700;color:#1d4ed8;padding:6px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;}

/* Hotel table */
.tcc-sp-table{width:100%;border-collapse:collapse;font-size:13px;}
.tcc-sp-table th{background:#f1f5f9;padding:10px 14px;text-align:left;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;}
.tcc-sp-table td{padding:10px 14px;border-bottom:1px solid #f8fafc;vertical-align:top;}
.tcc-sp-table tr:last-child td{border-bottom:none;}
.tcc-sp-cat-badge{display:inline-block;background:#ede9fe;color:#5b21b6;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;}

/* Inc/Exc panels */
.tcc-sp-panel{padding:22px;border-radius:10px;font-size:13px;line-height:1.65;}
.tcc-sp-panel+.tcc-sp-panel{margin-top:16px;}
.tcc-sp-panel-green{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.tcc-sp-panel-red{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
.tcc-sp-panel-yellow{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.tcc-sp-panel-title{font-size:15px;font-weight:800;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid rgba(0,0,0,.08);}
.tcc-sp-rte ul,.tcc-sp-rte ol{padding-left:18px;margin:4px 0;}
.tcc-sp-rte li{margin-bottom:4px;}
.tcc-sp-rte p{margin:0 0 6px;}

/* CTA */
.tcc-sp-cta{background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);color:#fff;border-radius:14px;padding:36px;text-align:center;margin-top:48px;}
.tcc-sp-cta h3{margin:0 0 10px;font-size:24px;font-weight:900;}
.tcc-sp-cta p{margin:0 0 24px;opacity:.8;font-size:14px;}
.tcc-sp-cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.tcc-sp-wa-btn{display:inline-flex;align-items:center;gap:8px;background:#22c55e;color:#fff!important;text-decoration:none!important;padding:14px 28px;border-radius:8px;font-size:15px;font-weight:700;transition:.2s;box-shadow:0 4px 14px rgba(0,0,0,.2);}
.tcc-sp-wa-btn:hover{background:#16a34a;transform:translateY(-2px);}
.tcc-sp-enquire-btn{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff!important;text-decoration:none!important;padding:14px 28px;border-radius:8px;font-size:15px;font-weight:700;transition:.2s;}
.tcc-sp-enquire-btn:hover{background:rgba(255,255,255,.22);}

@media(max-width:640px){
    .tcc-sp-hero{height:280px;}
    .tcc-sp-hero-title{font-size:22px;}
    .tcc-sp-body{padding:20px 16px 60px;}
    .tcc-sp-table{font-size:11px;}
    .tcc-sp-table th,.tcc-sp-table td{padding:8px 10px;}
}
</style>

<div class="tcc-sp-wrap">

    <!-- ── HERO ────────────────────────────────────────────────────────── -->
    <div class="tcc-sp-hero">
        <?php if ( $hero_img ) : ?>
            <img src="<?php echo esc_url( $hero_img ); ?>" alt="<?php the_title_attribute(); ?>">
        <?php endif; ?>
        <div class="tcc-sp-hero-overlay"></div>
        <div class="tcc-sp-hero-content">
            <?php if ( $destination ) : ?>
                <div class="tcc-sp-hero-dest">📍 <?php echo esc_html( $destination ); ?></div>
            <?php endif; ?>
            <h1 class="tcc-sp-hero-title"><?php the_title(); ?></h1>
            <div class="tcc-sp-hero-badges">
                <span class="tcc-sp-hero-badge">🌅 <?php echo esc_html( $days ); ?> Days / <?php echo esc_html( $nights ); ?> Nights</span>
                <?php if ( ! empty( $unique_stays ) ) : ?>
                    <span class="tcc-sp-hero-badge">🏨 <?php echo esc_html( implode( ' · ', array_slice( $unique_stays, 0, 3 ) ) ); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tcc-sp-body">

        <!-- ── OVERVIEW CHIPS ──────────────────────────────────────────── -->
        <div class="tcc-sp-overview">
            <?php if ( $days ) : ?>
            <div class="tcc-sp-chip">
                <span class="tcc-sp-chip-icon">📅</span>
                <span class="tcc-sp-chip-label">Duration</span>
                <span class="tcc-sp-chip-value"><?php echo esc_html( $days ); ?>D / <?php echo esc_html( $nights ); ?>N</span>
            </div>
            <?php endif; ?>
            <?php if ( ! empty( $unique_stays ) ) : ?>
            <div class="tcc-sp-chip">
                <span class="tcc-sp-chip-icon">🗺️</span>
                <span class="tcc-sp-chip-label">Places</span>
                <span class="tcc-sp-chip-value"><?php echo esc_html( count( $unique_stays ) ); ?> Locations</span>
            </div>
            <?php endif; ?>
            <?php if ( $destination ) : ?>
            <div class="tcc-sp-chip">
                <span class="tcc-sp-chip-icon">✈️</span>
                <span class="tcc-sp-chip-label">Destination</span>
                <span class="tcc-sp-chip-value"><?php echo esc_html( $destination ); ?></span>
            </div>
            <?php endif; ?>
            <?php if ( $route ) : ?>
            <div class="tcc-sp-chip" style="flex:1;min-width:200px;align-items:flex-start;">
                <span class="tcc-sp-chip-icon">🚗</span>
                <span class="tcc-sp-chip-label">Route</span>
                <span class="tcc-sp-chip-value" style="text-align:left;font-size:12px;font-weight:600;"><?php echo esc_html( $route ); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── DAY-WISE ITINERARY ──────────────────────────────────────── -->
        <?php if ( ! empty( $itinerary ) && array_filter( $itinerary ) ) : ?>
        <div class="tcc-sp-section">
            <h2 class="tcc-sp-section-title">📅 Day-Wise Itinerary</h2>
            <div class="tcc-sp-timeline">
                <?php foreach ( $itinerary as $idx => $day_title ) :
                    if ( ! trim( $day_title ) ) continue;
                    $d_img  = $itinerary_img[ $idx ]  ?? '';
                    $d_desc = $itinerary_desc[ $idx ]  ?? '';
                    $d_stay = $stay_places[ $idx ]      ?? '';
                ?>
                <div class="tcc-sp-day">
                    <div class="tcc-sp-day-num">Day <?php echo esc_html( $idx + 1 ); ?></div>
                    <div class="tcc-sp-day-title"><?php echo esc_html( $day_title ); ?></div>

                    <?php if ( $d_img ) : ?>
                        <img src="<?php echo esc_url( $d_img ); ?>" alt="Day <?php echo $idx + 1; ?>" class="tcc-sp-day-img" loading="lazy">
                    <?php endif; ?>

                    <?php if ( $d_desc ) : ?>
                        <div class="tcc-sp-day-desc"><?php echo tcc_pkg_render_rte( $d_desc ); ?></div>
                    <?php endif; ?>

                    <?php if ( $d_stay && strtolower( $d_stay ) !== 'trip ends' ) : ?>
                        <div class="tcc-sp-day-stay">🏨 Night Stay: <?php echo esc_html( $d_stay ); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── HOTELS ──────────────────────────────────────────────────── -->
        <?php if ( ! empty( $r_places ) && array_filter( $r_places ) ) : ?>
        <div class="tcc-sp-section">
            <h2 class="tcc-sp-section-title">🏨 Hotels & Accommodations</h2>
            <p style="font-size:13px;color:#64748b;margin:0 0 18px;">Hotels listed are representative — similar-quality alternatives may be arranged based on availability.</p>
            <div style="overflow-x:auto;">
                <table class="tcc-sp-table">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Hotels</th>
                            <th>Room Type</th>
                            <th style="text-align:center;">Nights</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $r_places as $ri => $rplace ) :
                            if ( empty( $rplace ) || strtolower( $rplace ) === 'trip ends' ) continue;
                            $rcat    = $r_categories[ $ri ] ?? '';
                            $rnights = $r_nights[ $ri ]     ?? 1;
                            $rhotel  = $r_hotels[ $ri ]     ?? '';
                            $rroom   = $r_room_types[ $ri ] ?? 'Default';
                            if ( $rroom === 'Default' ) $rroom = 'Deluxe Room';

                            // Parse hotels (name::link or plain name)
                            $hotel_arr  = array_filter( array_map( 'trim', explode( ',', $rhotel ) ) );
                            $hotel_html = '';
                            foreach ( $hotel_arr as $h ) {
                                if ( strpos( $h, '::' ) !== false ) {
                                    [ $hname, $hlink ] = explode( '::', $h, 2 );
                                    $hotel_html .= '<a href="' . esc_url( $hlink ) . '" target="_blank" rel="noopener" style="color:#2563eb;">' . esc_html( trim( $hname ) ) . '</a>, ';
                                } else {
                                    $hotel_html .= esc_html( $h ) . ', ';
                                }
                            }
                            $hotel_html = rtrim( $hotel_html, ', ' ) ?: '<em style="color:#94a3b8;">Group Standard / Similar</em>';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $rplace ); ?></strong></td>
                            <td><?php if ( $rcat ) echo '<span class="tcc-sp-cat-badge">' . esc_html( $rcat ) . '</span>'; ?></td>
                            <td><?php echo $hotel_html; ?></td>
                            <td style="color:#475569;"><?php echo esc_html( $rroom ); ?></td>
                            <td style="text-align:center;font-weight:800;color:#0f172a;"><?php echo esc_html( $rnights ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── INCLUSIONS & EXCLUSIONS ────────────────────────────────── -->
        <?php if ( $inclusions || $exclusions ) : ?>
        <div class="tcc-sp-section">
            <h2 class="tcc-sp-section-title">📋 Package Details</h2>
            <?php if ( $inclusions ) : ?>
                <div class="tcc-sp-panel tcc-sp-panel-green">
                    <div class="tcc-sp-panel-title">✅ Included in Package</div>
                    <?php echo tcc_pkg_render_rte( $inclusions ); ?>
                </div>
            <?php endif; ?>
            <?php if ( $exclusions ) : ?>
                <div class="tcc-sp-panel tcc-sp-panel-red">
                    <div class="tcc-sp-panel-title">❌ Not Included</div>
                    <?php echo tcc_pkg_render_rte( $exclusions ); ?>
                </div>
            <?php endif; ?>
            <?php if ( $payment_terms ) : ?>
                <div class="tcc-sp-panel tcc-sp-panel-yellow" style="margin-top:16px;">
                    <div class="tcc-sp-panel-title">💳 Payment Terms</div>
                    <?php echo tcc_pkg_render_rte( $payment_terms ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── CTA ────────────────────────────────────────────────────── -->
        <div class="tcc-sp-cta">
            <h3>Plan Your <?php echo esc_html( $destination ?: 'Dream' ); ?> Trip 🎒</h3>
            <p>Contact us to check live availability, get a personalised quote, and confirm your seats.</p>
            <div class="tcc-sp-cta-btns">
                <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tcc-sp-wa-btn">
                    💬 WhatsApp Enquiry
                </a>
                <?php if ( $linked_tour ) :
                    // Find pages containing this tour's shortcode to link to the booking form
                    $booking_page = get_posts( array(
                        'post_type'      => 'page',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        's'              => '[tcc_group_tour id="' . $linked_tour . '"]',
                    ) );
                    if ( $booking_page ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $booking_page[0]->ID ) ); ?>" class="tcc-sp-enquire-btn">
                            📋 View Dates & Pricing →
                        </a>
                    <?php endif;
                endif; ?>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>