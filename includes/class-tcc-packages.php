<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_Packages {

    public static function init() {
        add_action( 'init',            array( __CLASS__, 'register_cpt' ) );
        add_filter( 'template_include',array( __CLASS__, 'package_template' ) );

        // PRIMARY display method — injects full HTML via the_content filter.
        // Works with ANY theme. No template file needed.
        add_filter( 'the_content',     array( __CLASS__, 'content_filter' ), 20 );

        add_shortcode( 'tcc_packages', array( __CLASS__, 'shortcode_grid' ) );

        add_action( 'wp_ajax_tcc_publish_preset_package',   array( __CLASS__, 'ajax_publish' ) );
        add_action( 'wp_ajax_tcc_delete_package_post',      array( __CLASS__, 'ajax_delete' ) );
        add_action( 'wp_ajax_tcc_list_all_packages',        array( __CLASS__, 'ajax_list' ) );
        add_action( 'wp_ajax_tcc_get_tours_for_pkg_link',   array( __CLASS__, 'ajax_tours_for_link' ) );
        add_action( 'wp_ajax_tcc_toggle_package_status',    array( __CLASS__, 'ajax_toggle_status' ) );
    }

    // ─── CPT ────────────────────────────────────────────────────────────────
    public static function register_cpt() {
        register_post_type( 'tcc_package', array(
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'label'              => 'Tour Packages',
            'supports'           => array( 'title', 'thumbnail', 'excerpt' ),
            'rewrite'            => array( 'slug' => 'tour-package' ),
            'has_archive'        => false,
        ) );
    }

    // ─── Template fallback (only used if theme has single-tcc_package.php) ──
    public static function package_template( $template ) {
        if ( is_singular( 'tcc_package' ) ) {
            $theme_file  = locate_template( array( 'single-tcc_package.php' ) );
            if ( $theme_file ) return $theme_file;
            $plugin_file = trailingslashit( dirname( dirname( __FILE__ ) ) ) . 'templates/single-tcc_package.php';
            if ( file_exists( $plugin_file ) ) return $plugin_file;
        }
        return $template;
    }

    // ─── the_content filter ─ replaces empty post content with full package ─
    public static function content_filter( $content ) {
        if ( ! is_singular( 'tcc_package' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        ob_start();
        self::render_package_html( get_the_ID() );
        return ob_get_clean();
    }

    // ─── Full package page HTML (self-contained, works inside any theme) ────
    private static function render_package_html( $pid ) {

        $destination   = get_post_meta( $pid, '_tcc_pkg_destination',   true );
        $days          = (int) get_post_meta( $pid, '_tcc_pkg_days',    true );
        $nights        = max( 0, $days - 1 );
        $route         = get_post_meta( $pid, '_tcc_pkg_route',         true );
        $linked_tour   = (int) get_post_meta( $pid, '_tcc_pkg_linked_tour', true );

        $itinerary      = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary',      true ), true ) ?: array();
        $itinerary_desc = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary_desc', true ), true ) ?: array();
        $itinerary_img  = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary_img',  true ), true ) ?: array();
        $stay_places    = json_decode( get_post_meta( $pid, '_tcc_pkg_stay_places',    true ), true ) ?: array();
        $routing        = json_decode( get_post_meta( $pid, '_tcc_pkg_routing',        true ), true ) ?: array();
        $inclusions     = get_post_meta( $pid, '_tcc_pkg_inclusions',    true );
        $exclusions     = get_post_meta( $pid, '_tcc_pkg_exclusions',    true );
        $payment_terms  = get_post_meta( $pid, '_tcc_pkg_payment_terms', true );

        $r_places     = isset( $routing['places'] )     ? $routing['places']     : array();
        $r_categories = isset( $routing['categories'] ) ? $routing['categories'] : array();
        $r_nights     = isset( $routing['nights'] )     ? $routing['nights']     : array();
        $r_hotels     = isset( $routing['hotels'] )     ? $routing['hotels']     : array();
        $r_room_types = isset( $routing['room_types'] ) ? $routing['room_types'] : array();

        // Hero image
        $hero_img = get_the_post_thumbnail_url( $pid, 'full' );
        if ( ! $hero_img ) {
            foreach ( $itinerary_img as $img ) { if ( $img ) { $hero_img = $img; break; } }
        }

        $unique_stays = array_unique( array_filter( array_values( $stay_places ) ) );

        // WhatsApp CTA
        $wa_num   = preg_replace( '/[^0-9]/', '', get_option( 'tcc_wa_number', '' ) );
        $wa_text  = 'Hi! I am interested in the ' . get_the_title( $pid ) . ' package (' . $days . 'D/' . $nights . 'N). Please share availability and pricing.';
        $wa_url   = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_text );
        ?>

        <style>
        .tcc-sp{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#334155;width:100%}
        .tcc-sp *{box-sizing:border-box}
        .tcc-sp-hero{position:relative;height:360px;overflow:hidden;background:linear-gradient(135deg,#1e3a8a,#2563eb);margin:0 0 0 0}
        .tcc-sp-hero img{width:100%;height:100%;object-fit:cover;display:block}
        .tcc-sp-hero-ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.65) 0%,rgba(0,0,0,.1) 60%,transparent 100%)}
        .tcc-sp-hero-c{position:absolute;bottom:0;left:0;right:0;padding:26px 20px;color:#fff}
        .tcc-sp-dest{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#93c5fd;margin-bottom:6px}
        .tcc-sp-htitle{font-size:26px;font-weight:900;margin:0 0 12px;line-height:1.25;text-shadow:0 2px 8px rgba(0,0,0,.4)}
        .tcc-sp-badges{display:flex;gap:8px;flex-wrap:wrap}
        .tcc-sp-badge{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);color:#fff;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px}
        .tcc-sp-body{padding:24px 0 56px}
        .tcc-sp-sect{margin-bottom:32px}
        .tcc-sp-sttl{font-size:17px;font-weight:800;color:#0f172a;border-left:4px solid #b93b59;padding-left:11px;margin:0 0 18px}
        .tcc-sp-ov{display:flex;gap:12px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:26px}
        .tcc-sp-chip{display:flex;flex-direction:column;align-items:center;gap:3px;min-width:80px}
        .tcc-sp-chip-i{font-size:20px}
        .tcc-sp-chip-l{font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase}
        .tcc-sp-chip-v{font-size:13px;color:#0f172a;font-weight:800;text-align:center;line-height:1.3}
        .tcc-sp-tl{border-left:3px solid #e2e8f0;padding-left:22px;margin-left:10px}
        .tcc-sp-day{position:relative;margin-bottom:28px}
        .tcc-sp-day:last-child{margin-bottom:0}
        .tcc-sp-day::before{content:'';position:absolute;left:-31px;top:3px;width:14px;height:14px;background:#b93b59;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 2px #b93b59}
        .tcc-sp-dnum{font-size:11px;font-weight:700;text-transform:uppercase;color:#b93b59;letter-spacing:1px;margin-bottom:3px}
        .tcc-sp-dtitle{font-size:15px;font-weight:800;color:#0f172a;margin-bottom:8px}
        .tcc-sp-dimg{width:100%;border-radius:8px;margin:10px 0;max-height:300px;object-fit:cover;border:1px solid #e2e8f0}
        .tcc-sp-ddesc{font-size:13px;color:#475569;line-height:1.65}
        .tcc-sp-ddesc ul,.tcc-sp-ddesc ol{padding-left:18px;margin:4px 0}
        .tcc-sp-ddesc li{margin-bottom:3px}
        .tcc-sp-ddesc p{margin:0 0 5px}
        .tcc-sp-dstay{display:inline-block;margin-top:10px;font-size:12px;font-weight:700;color:#1d4ed8;padding:5px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px}
        .tcc-sp-htbl{width:100%;border-collapse:collapse;font-size:12px}
        .tcc-sp-htbl th{background:#f1f5f9;padding:9px 12px;text-align:left;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0}
        .tcc-sp-htbl td{padding:9px 12px;border-bottom:1px solid #f8fafc;vertical-align:top}
        .tcc-sp-htbl tr:last-child td{border-bottom:none}
        .tcc-sp-catb{display:inline-block;background:#ede9fe;color:#5b21b6;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
        .tcc-sp-panel{padding:18px;border-radius:10px;font-size:13px;line-height:1.65}
        .tcc-sp-panel+.tcc-sp-panel{margin-top:12px}
        .tcc-sp-pg{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
        .tcc-sp-pr{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .tcc-sp-py{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
        .tcc-sp-pt{font-size:14px;font-weight:800;margin:0 0 10px;padding-bottom:7px;border-bottom:1px solid rgba(0,0,0,.08)}
        .tcc-sp-panel ul,.tcc-sp-panel ol{padding-left:16px;margin:4px 0}
        .tcc-sp-panel li{margin-bottom:3px}
        .tcc-sp-panel p{margin:0 0 5px}
        .tcc-sp-cta{background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);color:#fff;border-radius:12px;padding:30px;text-align:center;margin-top:36px}
        .tcc-sp-cta h3{margin:0 0 8px;font-size:21px;font-weight:900}
        .tcc-sp-cta p{margin:0 0 20px;opacity:.8;font-size:14px}
        .tcc-sp-cta-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
        .tcc-sp-wa{display:inline-flex;align-items:center;gap:6px;background:#22c55e;color:#fff!important;text-decoration:none!important;padding:12px 24px;border-radius:7px;font-size:14px;font-weight:700;transition:.2s}
        .tcc-sp-wa:hover{background:#16a34a}
        @media(max-width:640px){.tcc-sp-hero{height:230px}.tcc-sp-htitle{font-size:19px}.tcc-sp-body{padding:18px 0 44px}.tcc-sp-htbl{font-size:11px}.tcc-sp-htbl th,.tcc-sp-htbl td{padding:7px 8px}}
        </style>

        <div class="tcc-sp">

            <!-- HERO -->
            <div class="tcc-sp-hero">
                <?php if ( $hero_img ) : ?><img src="<?php echo esc_url( $hero_img ); ?>" alt="<?php echo esc_attr( get_the_title( $pid ) ); ?>"><?php endif; ?>
                <div class="tcc-sp-hero-ov"></div>
                <div class="tcc-sp-hero-c">
                    <?php if ( $destination ) : ?><div class="tcc-sp-dest">📍 <?php echo esc_html( $destination ); ?></div><?php endif; ?>
                    <h2 class="tcc-sp-htitle"><?php echo esc_html( get_the_title( $pid ) ); ?></h2>
                    <div class="tcc-sp-badges">
                        <?php if ( $days ) : ?><span class="tcc-sp-badge">🌅 <?php echo esc_html( $days ); ?>D / <?php echo esc_html( $nights ); ?>N</span><?php endif; ?>
                        <?php if ( ! empty( $unique_stays ) ) : ?><span class="tcc-sp-badge">🏨 <?php echo esc_html( implode( ' · ', array_slice( $unique_stays, 0, 3 ) ) ); ?></span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tcc-sp-body">

                <!-- OVERVIEW CHIPS -->
                <div class="tcc-sp-ov">
                    <?php if ( $days ) : ?>
                    <div class="tcc-sp-chip"><span class="tcc-sp-chip-i">📅</span><span class="tcc-sp-chip-l">Duration</span><span class="tcc-sp-chip-v"><?php echo esc_html( $days ); ?>D / <?php echo esc_html( $nights ); ?>N</span></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $unique_stays ) ) : ?>
                    <div class="tcc-sp-chip"><span class="tcc-sp-chip-i">🗺️</span><span class="tcc-sp-chip-l">Places</span><span class="tcc-sp-chip-v"><?php echo esc_html( count( $unique_stays ) ); ?> Locations</span></div>
                    <?php endif; ?>
                    <?php if ( $destination ) : ?>
                    <div class="tcc-sp-chip"><span class="tcc-sp-chip-i">✈️</span><span class="tcc-sp-chip-l">Destination</span><span class="tcc-sp-chip-v"><?php echo esc_html( $destination ); ?></span></div>
                    <?php endif; ?>
                    <?php if ( $route ) : ?>
                    <div class="tcc-sp-chip" style="flex:1;min-width:180px;align-items:flex-start;"><span class="tcc-sp-chip-i">🚗</span><span class="tcc-sp-chip-l">Route</span><span class="tcc-sp-chip-v" style="text-align:left;font-size:12px;"><?php echo esc_html( $route ); ?></span></div>
                    <?php endif; ?>
                </div>

                <!-- DAY-WISE ITINERARY -->
                <?php if ( ! empty( $itinerary ) && array_filter( $itinerary ) ) : ?>
                <div class="tcc-sp-sect">
                    <h3 class="tcc-sp-sttl">📅 Day-Wise Itinerary</h3>
                    <div class="tcc-sp-tl">
                        <?php foreach ( $itinerary as $idx => $day_title ) :
                            if ( ! trim( $day_title ) ) continue;
                            $d_img  = isset( $itinerary_img[ $idx ] )  ? $itinerary_img[ $idx ]  : '';
                            $d_desc = isset( $itinerary_desc[ $idx ] ) ? $itinerary_desc[ $idx ] : '';
                            $d_stay = isset( $stay_places[ $idx ] )    ? $stay_places[ $idx ]    : '';
                        ?>
                        <div class="tcc-sp-day">
                            <div class="tcc-sp-dnum">Day <?php echo esc_html( $idx + 1 ); ?></div>
                            <div class="tcc-sp-dtitle"><?php echo esc_html( $day_title ); ?></div>
                            <?php if ( $d_img ) : ?><img src="<?php echo esc_url( $d_img ); ?>" alt="Day <?php echo (int)( $idx + 1 ); ?>" class="tcc-sp-dimg" loading="lazy"><?php endif; ?>
                            <?php if ( $d_desc ) : ?><div class="tcc-sp-ddesc"><?php echo wp_kses_post( $d_desc ); ?></div><?php endif; ?>
                            <?php if ( $d_stay && strtolower( trim( $d_stay ) ) !== 'trip ends' ) : ?>
                                <div class="tcc-sp-dstay">🏨 Night Stay: <?php echo esc_html( $d_stay ); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- HOTELS -->
                <?php if ( ! empty( $r_places ) && array_filter( $r_places ) ) : ?>
                <div class="tcc-sp-sect">
                    <h3 class="tcc-sp-sttl">🏨 Hotels &amp; Accommodations</h3>
                    <p style="font-size:13px;color:#64748b;margin:0 0 14px;">Hotels listed are representative — similar-quality alternatives may be arranged based on availability.</p>
                    <div style="overflow-x:auto;">
                        <table class="tcc-sp-htbl">
                            <thead><tr><th>Location</th><th>Category</th><th>Hotels</th><th>Room Type</th><th style="text-align:center;">Nights</th></tr></thead>
                            <tbody>
                            <?php foreach ( $r_places as $ri => $rplace ) :
                                if ( empty( $rplace ) || strtolower( trim( $rplace ) ) === 'trip ends' ) continue;
                                $rcat    = isset( $r_categories[ $ri ] ) ? $r_categories[ $ri ] : '';
                                $rnights = isset( $r_nights[ $ri ] )     ? $r_nights[ $ri ]     : 1;
                                $rhotel  = isset( $r_hotels[ $ri ] )     ? $r_hotels[ $ri ]     : '';
                                $rroom   = isset( $r_room_types[ $ri ] ) ? $r_room_types[ $ri ] : 'Default';
                                if ( $rroom === 'Default' ) $rroom = 'Deluxe Room';
                                $harr   = array_filter( array_map( 'trim', explode( ',', $rhotel ) ) );
                                $hhtml  = '';
                                foreach ( $harr as $h ) {
                                    if ( strpos( $h, '::' ) !== false ) {
                                        list( $hn, $hl ) = explode( '::', $h, 2 );
                                        $hhtml .= '<a href="' . esc_url( $hl ) . '" target="_blank" rel="noopener" style="color:#2563eb;">' . esc_html( trim( $hn ) ) . '</a>, ';
                                    } else {
                                        $hhtml .= esc_html( $h ) . ', ';
                                    }
                                }
                                $hhtml = rtrim( $hhtml, ', ' ) ?: '<em style="color:#94a3b8;">Group Standard / Similar</em>';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $rplace ); ?></strong></td>
                                <td><?php if ( $rcat ) echo '<span class="tcc-sp-catb">' . esc_html( $rcat ) . '</span>'; ?></td>
                                <td><?php echo $hhtml; ?></td>
                                <td style="color:#475569;"><?php echo esc_html( $rroom ); ?></td>
                                <td style="text-align:center;font-weight:800;color:#0f172a;"><?php echo esc_html( $rnights ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- INCLUSIONS / EXCLUSIONS / PAYMENT TERMS -->
                <?php if ( $inclusions || $exclusions || $payment_terms ) : ?>
                <div class="tcc-sp-sect">
                    <h3 class="tcc-sp-sttl">📋 Package Details</h3>
                    <?php if ( $inclusions ) : ?>
                    <div class="tcc-sp-panel tcc-sp-pg">
                        <div class="tcc-sp-pt">✅ Included in Package</div>
                        <?php echo wp_kses_post( $inclusions ); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $exclusions ) : ?>
                    <div class="tcc-sp-panel tcc-sp-pr">
                        <div class="tcc-sp-pt">❌ Not Included</div>
                        <?php echo wp_kses_post( $exclusions ); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $payment_terms ) : ?>
                    <div class="tcc-sp-panel tcc-sp-py">
                        <div class="tcc-sp-pt">💳 Payment Terms</div>
                        <?php echo wp_kses_post( $payment_terms ); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- CTA -->
                <div class="tcc-sp-cta">
                    <h3>Interested in This Package? 🎒</h3>
                    <p>Contact us to check live availability and get a personalised quote for your group.</p>
                    <div class="tcc-sp-cta-btns">
                        <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tcc-sp-wa">💬 WhatsApp Enquiry</a>
                        <?php if ( $linked_tour ) :
                            $bpages = get_posts( array(
                                'post_type' => 'page', 'post_status' => 'publish',
                                'posts_per_page' => 1,
                                's' => '[tcc_group_tour id="' . $linked_tour . '"]',
                            ) );
                            if ( $bpages ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $bpages[0]->ID ) ); ?>"
                               style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff!important;text-decoration:none!important;padding:12px 24px;border-radius:7px;font-size:14px;font-weight:700;">
                                📋 View Dates &amp; Book
                            </a>
                            <?php endif;
                        endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    // ─── AJAX: Publish / sync preset → CPT post ─────────────────────────────
    public static function ajax_publish() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();

        $destination  = sanitize_text_field( wp_unslash( isset( $_POST['destination'] )   ? $_POST['destination']   : '' ) );
        $preset_name  = sanitize_text_field( wp_unslash( isset( $_POST['preset_name'] )   ? $_POST['preset_name']   : '' ) );
        $display_name = sanitize_text_field( wp_unslash( isset( $_POST['display_name'] )  ? $_POST['display_name']  : $preset_name ) );
        $existing_id  = intval( isset( $_POST['existing_id'] )   ? $_POST['existing_id']   : 0 );
        $linked_tour  = intval( isset( $_POST['linked_tour_id'] ) ? $_POST['linked_tour_id'] : 0 );

        $all_presets = get_option( 'tcc_itinerary_presets', array() );
        if ( empty( $all_presets[ $destination ][ $preset_name ] ) ) {
            wp_send_json_error( 'Preset not found. Please save the preset first, then publish.' );
        }
        $p = $all_presets[ $destination ][ $preset_name ];

        $master      = get_option( 'tcc_master_settings', array() );
        $dest_master = isset( $master[ $destination ] ) ? $master[ $destination ] : array();

        $itinerary       = array_values( (array)( isset( $p['itinerary'] )           ? $p['itinerary']           : array() ) );
        $itinerary_desc  = array_values( (array)( isset( $p['itinerary_desc'] )      ? $p['itinerary_desc']      : array() ) );
        $itinerary_image = array_values( (array)( isset( $p['itinerary_image'] )     ? $p['itinerary_image']     : array() ) );
        $stay_places     = array_values( (array)( isset( $p['stay_places'] )         ? $p['stay_places']         : array() ) );
        $routes          = array_filter( array_values( (array)( isset( $p['itinerary_routes'] ) ? $p['itinerary_routes'] : array() ) ) );

        $days      = count( $itinerary );
        $nights    = max( 0, $days - 1 );
        $route_str = implode( ' → ', $routes );

        $routing = array(
            'places'     => array_values( (array)( isset( $p['routing_stay_places'] )     ? $p['routing_stay_places']     : array() ) ),
            'categories' => array_values( (array)( isset( $p['routing_stay_categories'] ) ? $p['routing_stay_categories'] : array() ) ),
            'nights'     => array_values( (array)( isset( $p['routing_stay_nights'] )     ? $p['routing_stay_nights']     : array() ) ),
            'hotels'     => array_values( (array)( isset( $p['routing_stay_hotels'] )     ? $p['routing_stay_hotels']     : array() ) ),
            'room_types' => array_values( (array)( isset( $p['routing_stay_room_types'] ) ? $p['routing_stay_room_types'] : array() ) ),
        );

        $post_args = array( 'post_type' => 'tcc_package', 'post_title' => $display_name, 'post_status' => 'publish' );
        if ( $existing_id && get_post_type( $existing_id ) === 'tcc_package' ) {
            $post_args['ID'] = $existing_id;
            $post_id = wp_update_post( $post_args );
        } else {
            $post_id = wp_insert_post( $post_args );
        }
        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( 'WordPress error: ' . $post_id->get_error_message() );
        }

        $string_meta = array(
            '_tcc_pkg_itinerary'      => wp_json_encode( $itinerary ),
            '_tcc_pkg_itinerary_desc' => wp_json_encode( $itinerary_desc ),
            '_tcc_pkg_itinerary_img'  => wp_json_encode( $itinerary_image ),
            '_tcc_pkg_stay_places'    => wp_json_encode( $stay_places ),
            '_tcc_pkg_routing'        => wp_json_encode( $routing ),
            '_tcc_pkg_inclusions'     => isset( $dest_master['inclusions'] )    ? $dest_master['inclusions']    : '',
            '_tcc_pkg_exclusions'     => isset( $dest_master['exclusions'] )    ? $dest_master['exclusions']    : '',
            '_tcc_pkg_payment_terms'  => isset( $dest_master['payment_terms'] ) ? $dest_master['payment_terms'] : '',
        );
        foreach ( $string_meta as $key => $value ) {
            update_post_meta( $post_id, $key, wp_slash( $value ) );
        }

        $scalar_meta = array(
            '_tcc_pkg_destination' => $destination,
            '_tcc_pkg_preset_name' => $preset_name,
            '_tcc_pkg_days'        => $days,
            '_tcc_pkg_nights'      => $nights,
            '_tcc_pkg_route'       => $route_str,
            '_tcc_pkg_linked_tour' => $linked_tour,
        );
        foreach ( $scalar_meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // Auto-set featured image from first itinerary image (if none set yet)
        if ( ! has_post_thumbnail( $post_id ) ) {
            global $wpdb;
            foreach ( $itinerary_image as $img_url ) {
                if ( empty( $img_url ) ) continue;
                $att_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid=%s LIMIT 1",
                    $img_url
                ) );
                if ( $att_id ) { set_post_thumbnail( $post_id, (int) $att_id ); break; }
            }
        }

        wp_send_json_success( array(
            'post_id'   => $post_id,
            'permalink' => get_permalink( $post_id ),
            'message'   => $existing_id ? 'Package updated & synced!' : 'Package published successfully!',
        ) );
    }

    // ─── AJAX: Delete ────────────────────────────────────────────────────────
    public static function ajax_delete() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();
        $id = intval( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
        if ( $id && get_post_type( $id ) === 'tcc_package' ) {
            wp_delete_post( $id, true );
            wp_send_json_success();
        }
        wp_send_json_error( 'Invalid post.' );
    }

    // ─── AJAX: List for admin panel ──────────────────────────────────────────
    public static function ajax_list() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();
        $posts = get_posts( array(
            'post_type'      => 'tcc_package',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft' ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        $data = array();
        foreach ( $posts as $p ) {
            $data[] = array(
                'id'          => $p->ID,
                'title'       => $p->post_title,
                'status'      => $p->post_status,
                'destination' => get_post_meta( $p->ID, '_tcc_pkg_destination', true ),
                'days'        => (int) get_post_meta( $p->ID, '_tcc_pkg_days', true ),
                'preset_name' => get_post_meta( $p->ID, '_tcc_pkg_preset_name', true ),
                'permalink'   => get_permalink( $p->ID ),
                'linked_tour' => (int) get_post_meta( $p->ID, '_tcc_pkg_linked_tour', true ),
            );
        }
        wp_send_json_success( $data );
    }

    // ─── AJAX: Group tours for link dropdown ─────────────────────────────────
    public static function ajax_tours_for_link() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();
        $tours = get_posts( array( 'post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        $data  = array();
        foreach ( $tours as $t ) {
            $data[] = array( 'id' => $t->ID, 'title' => $t->post_title );
        }
        wp_send_json_success( $data );
    }

    // ─── AJAX: Toggle publish / draft ────────────────────────────────────────
    public static function ajax_toggle_status() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();
        $id = intval( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
        $p  = get_post( $id );
        if ( ! $p || $p->post_type !== 'tcc_package' ) wp_send_json_error();
        $new = ( $p->post_status === 'publish' ) ? 'draft' : 'publish';
        wp_update_post( array( 'ID' => $id, 'post_status' => $new ) );
        wp_send_json_success( $new );
    }

    // ─── Shortcode [tcc_packages] ────────────────────────────────────────────
    public static function shortcode_grid( $atts ) {
        $atts = shortcode_atts( array(
            'destination' => '',
            'cols'        => '3',
            'limit'       => '12',
        ), $atts );

        $query_args = array(
            'post_type'      => 'tcc_package',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( ! empty( $atts['destination'] ) ) {
            $query_args['meta_query'] = array( array(
                'key'   => '_tcc_pkg_destination',
                'value' => sanitize_text_field( $atts['destination'] ),
            ) );
        }
        $pkgs = get_posts( $query_args );

        if ( empty( $pkgs ) ) {
            return '<p style="text-align:center;padding:40px;color:#64748b;font-family:sans-serif;">No tour packages are available at the moment.</p>';
        }

        $cols = max( 1, min( 4, intval( $atts['cols'] ) ) );
        ob_start();
        self::inline_css( $cols );
        echo '<div class="tcc-pkg-grid">';
        foreach ( $pkgs as $pkg ) self::render_card( $pkg );
        echo '</div>';
        return ob_get_clean();
    }

    // ─── Grid CSS (once per page) ────────────────────────────────────────────
    private static function inline_css( $cols ) {
        static $done = false;
        if ( $done ) return;
        $done = true;
        echo '<style id="tcc-pkg-css">
.tcc-pkg-grid{display:grid;grid-template-columns:repeat(' . (int)$cols . ',1fr);gap:24px;padding:20px 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:100%}
@media(max-width:960px){.tcc-pkg-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:580px){.tcc-pkg-grid{grid-template-columns:1fr}}
.tcc-pkg-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.07);transition:transform .22s,box-shadow .22s;display:flex;flex-direction:column;border:1px solid #e2e8f0;text-decoration:none!important;color:inherit!important}
.tcc-pkg-card:hover{transform:translateY(-5px);box-shadow:0 12px 32px rgba(0,0,0,.12)}
.tcc-pkg-thumb{width:100%;height:210px;object-fit:cover;display:block}
.tcc-pkg-ph{width:100%;height:210px;background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%);display:flex;align-items:center;justify-content:center;font-size:52px}
.tcc-pkg-body{padding:18px;flex:1;display:flex;flex-direction:column;gap:8px}
.tcc-pkg-dest{font-size:11px;font-weight:700;text-transform:uppercase;color:#b93b59;letter-spacing:1px}
.tcc-pkg-name{font-size:17px;font-weight:800;color:#0f172a;margin:0;line-height:1.3}
.tcc-pkg-badge{display:inline-flex;align-items:center;gap:4px;background:#ede9fe;color:#5b21b6;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;width:fit-content}
.tcc-pkg-info{font-size:12px;color:#64748b;line-height:1.5}
.tcc-pkg-btn{display:block;background:linear-gradient(135deg,#b93b59 0%,#9d314b 100%);color:#fff!important;text-decoration:none!important;padding:11px 16px;border-radius:8px;font-size:13px;font-weight:700;text-align:center;transition:.2s;margin-top:auto}
.tcc-pkg-btn:hover{background:linear-gradient(135deg,#9d314b 0%,#6f1028 100%)}
</style>';
    }

    // ─── Single card HTML ────────────────────────────────────────────────────
    private static function render_card( $pkg ) {
        $destination = get_post_meta( $pkg->ID, '_tcc_pkg_destination', true );
        $days        = (int) get_post_meta( $pkg->ID, '_tcc_pkg_days', true );
        $nights      = max( 0, $days - 1 );
        $route       = get_post_meta( $pkg->ID, '_tcc_pkg_route', true );
        $permalink   = get_permalink( $pkg->ID );

        $img_url = has_post_thumbnail( $pkg->ID ) ? get_the_post_thumbnail_url( $pkg->ID, 'medium_large' ) : '';
        if ( ! $img_url ) {
            $imgs = json_decode( get_post_meta( $pkg->ID, '_tcc_pkg_itinerary_img', true ), true );
            if ( is_array( $imgs ) ) {
                foreach ( $imgs as $u ) { if ( $u ) { $img_url = $u; break; } }
            }
        }

        $stays = json_decode( get_post_meta( $pkg->ID, '_tcc_pkg_stay_places', true ), true );
        $stays_str = '';
        if ( is_array( $stays ) ) {
            $stays_str = implode( ' → ', array_unique( array_filter( array_values( $stays ) ) ) );
        }

        echo '<a href="' . esc_url( $permalink ) . '" class="tcc-pkg-card">';
        if ( $img_url ) {
            echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pkg->post_title ) . '" class="tcc-pkg-thumb" loading="lazy">';
        } else {
            echo '<div class="tcc-pkg-ph">🗺️</div>';
        }
        echo '<div class="tcc-pkg-body">';
        if ( $destination ) echo '<div class="tcc-pkg-dest">📍 ' . esc_html( $destination ) . '</div>';
        echo '<h3 class="tcc-pkg-name">' . esc_html( $pkg->post_title ) . '</h3>';
        if ( $days ) echo '<span class="tcc-pkg-badge">🌅 ' . esc_html( $days ) . 'D / ' . esc_html( $nights ) . 'N</span>';
        if ( $stays_str ) echo '<div class="tcc-pkg-info">🏨 ' . esc_html( $stays_str ) . '</div>';
        if ( $route )     echo '<div class="tcc-pkg-info">🗺️ ' . esc_html( $route ) . '</div>';
        echo '<span class="tcc-pkg-btn">View Full Itinerary →</span>';
        echo '</div></a>';
    }

    // ─── Admin settings panel ────────────────────────────────────────────────
    // NOTE: shortcode examples are rendered via JS String.fromCharCode(91/93).
    // PHP never writes [ ] brackets anywhere in this method's output, so
    // WordPress / page-builders cannot execute them during any processing pass.
    public static function render_settings_panel() { ?>
        <div class="tcc-accordion">
            <div class="tcc-accordion-header" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0;">
                6. Published Package Pages <span>&#9660;</span>
            </div>
            <div class="tcc-accordion-body" style="background:#fff;">
                <p style="font-size:12px;color:#64748b;margin:0 0 15px;">
                    Package pages are public-facing itinerary pages built from your saved presets.
                    To publish: load a preset in the <strong>Calculator</strong> tab, then click <strong>🌐 Publish</strong>.
                </p>
                <div id="tcc_packages_list" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:15px;text-align:center;color:#64748b;">
                    Loading packages…
                </div>
                <div style="margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:13px;">
                    <strong style="color:#1d4ed8;">Frontend Shortcodes</strong><br><br>
                    <!-- data-sc holds shortcode name WITHOUT brackets — brackets are added by JS only -->
                    <code class="tcc-sc-pill" data-sc="tcc_packages"
                          style="background:#e0e7ff;padding:3px 8px;border-radius:3px;cursor:copy;user-select:all;font-size:12px;"></code> — show all packages<br><br>
                    <code class="tcc-sc-pill" data-sc='tcc_packages destination="Kashmir" cols="3"'
                          style="background:#e0e7ff;padding:3px 8px;border-radius:3px;cursor:copy;user-select:all;font-size:12px;"></code> — filter by destination<br><br>
                    <code class="tcc-sc-pill" data-sc="tcc_packages limit=6"
                          style="background:#e0e7ff;padding:3px 8px;border-radius:3px;cursor:copy;user-select:all;font-size:12px;"></code> — limit how many show
                </div>
                <script>
                /* Brackets are built via charCode — PHP never writes [ ] so no shortcode can execute */
                (function(){
                    var ob = String.fromCharCode(91);
                    var cb = String.fromCharCode(93);
                    document.querySelectorAll('.tcc-sc-pill').forEach(function(el){
                        el.textContent = ob + el.getAttribute('data-sc') + cb;
                        el.title = 'Click to copy';
                        el.addEventListener('click', function(){
                            if (navigator.clipboard) navigator.clipboard.writeText(el.textContent);
                            var prev = el.style.background;
                            el.style.background = '#bbf7d0';
                            setTimeout(function(){ el.style.background = prev; }, 700);
                        });
                    });
                })();
                </script>
            </div>
        </div>

        <!-- Publish / Sync modal -->
        <div id="tcc-pkg-modal-wrap" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;padding:24px;width:440px;max-width:94%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <h3 style="margin:0 0 4px;color:#0f172a;font-size:16px;">🌐 Publish as Package Page</h3>
                <p style="font-size:12px;color:#64748b;margin:0 0 16px;">Creates a public itinerary page from the currently loaded preset.</p>
                <div class="tcc-form-group">
                    <label style="font-size:12px;font-weight:600;color:#475569;">Public Package Name</label>
                    <input type="text" id="pkg_modal_name" placeholder="e.g. Kashmir 5D/4N Spring Package" style="width:100%;margin-top:4px;">
                </div>
                <div class="tcc-form-group" style="margin-top:12px;">
                    <label style="font-size:12px;font-weight:600;color:#475569;">Link to a Group Tour <small style="font-weight:normal;">(Optional)</small></label>
                    <select id="pkg_modal_tour_link" style="width:100%;margin-top:4px;">
                        <option value="">— No Group Tour Linked —</option>
                    </select>
                </div>
                <input type="hidden" id="pkg_modal_preset_name">
                <input type="hidden" id="pkg_modal_destination">
                <input type="hidden" id="pkg_modal_existing_id" value="">
                <div style="display:flex;gap:8px;margin-top:20px;">
                    <button type="button" id="pkg_modal_submit" class="tcc-btn-primary" style="flex:2;margin:0;background:#10b981;border-color:#059669;">🌐 Publish Package</button>
                    <button type="button" id="pkg_modal_cancel" class="tcc-btn-secondary" style="flex:1;margin:0;">Cancel</button>
                </div>
                <div id="pkg_modal_msg" style="margin-top:10px;font-size:12px;display:none;"></div>
            </div>
        </div>
    <?php }
}

TCC_Packages::init();