<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TCC REST API
 * Exposes group-tour data and a booking endpoint for the Child Plugin.
 * One mother plugin → one child connection via a single secret API key.
 *
 * REST base: {home_url}/wp-json/tcc-api/v1/
 * Endpoints:
 *   GET  /ping      — connectivity test
 *   GET  /settings  — form config (GST, form_flow, WA number, etc.)
 *   GET  /tours     — all published group tours with dates & pricing
 *   GET  /seats     — live available-seat counts for all tours
 *   POST /book      — create a booking and return WhatsApp URL + quote URL
 */
class TCC_API {

    public static function init() {
        add_action( 'rest_api_init',  array( __CLASS__, 'register_routes' ) );
        add_action( 'admin_menu',     array( __CLASS__, 'register_admin_menu' ) );
        add_action( 'admin_init',     array( __CLASS__, 'handle_key_actions' ) );
        // PDF generation endpoint for the child plugin (key-authenticated, no WP nonce needed)
        add_action( 'wp_ajax_nopriv_tcc_api_generate_pdf', array( __CLASS__, 'ajax_generate_pdf' ) );
        add_action( 'wp_ajax_tcc_api_generate_pdf',        array( __CLASS__, 'ajax_generate_pdf' ) );
    }

    // ── Authentication ──────────────────────────────────────────────────────
    public static function authenticate( WP_REST_Request $req ) {
        $key    = $req->get_header( 'X-TCC-API-Key' ) ?: (string) $req->get_param( 'api_key' );
        $stored = (string) get_option( 'tcc_child_api_key', '' );
        if ( empty( $stored ) || ! hash_equals( $stored, $key ) ) {
            return new WP_Error( 'tcc_unauthorized', 'Invalid or missing API key.', array( 'status' => 401 ) );
        }
        // Log the last-seen child origin for the admin panel
        $origin = $req->get_header( 'Origin' ) ?: $req->get_header( 'Referer' );
        if ( $origin ) {
            $p = wp_parse_url( $origin );
            update_option( 'tcc_child_last_seen_url',  ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? '' ) );
            update_option( 'tcc_child_last_seen_time', current_time( 'Y-m-d H:i:s' ) );
        }
        return true;
    }

    // ── Route registration ──────────────────────────────────────────────────
    public static function register_routes() {
        $ns   = 'tcc-api/v1';
        $auth = array( __CLASS__, 'authenticate' );
        foreach ( array(
            array( 'ping',     'GET',  'endpoint_ping' ),
            array( 'settings', 'GET',  'endpoint_settings' ),
            array( 'tours',    'GET',  'endpoint_tours' ),
            array( 'seats',    'GET',  'endpoint_seats' ),
            array( 'book',     'POST', 'endpoint_book' ),
        ) as $r ) {
            register_rest_route( $ns, '/' . $r[0], array(
                'methods'             => $r[1],
                'callback'            => array( __CLASS__, $r[2] ),
                'permission_callback' => $auth,
            ) );
        }
    }

    // ── Endpoints ───────────────────────────────────────────────────────────
    public static function endpoint_ping( WP_REST_Request $req ) {
        return rest_ensure_response( array( 'status' => 'ok', 'site' => get_bloginfo( 'name' ), 'url' => home_url() ) );
    }

    public static function endpoint_settings( WP_REST_Request $req ) {
        $g = get_option( 'tcc_global_settings', array( 'gst' => 5 ) );
        return rest_ensure_response( array(
            'gst'               => floatval( $g['gst'] ?? 5 ),
            'form_flow'         => get_option( 'tcc_form_flow', 'calc_first' ),
            'auto_wa_redirect'  => get_option( 'tcc_auto_wa_redirect', '1' ),
            'wa_number'         => get_option( 'tcc_wa_number', '' ),
            'max_child_double'  => (int) get_option( 'tcc_max_child_double', 1 ),
            'max_infant_double' => (int) get_option( 'tcc_max_infant_double', 1 ),
            'max_child_triple'  => (int) get_option( 'tcc_max_child_triple', 1 ),
            'max_infant_triple' => (int) get_option( 'tcc_max_infant_triple', 1 ),
            'max_child_single'  => (int) get_option( 'tcc_max_child_single', 1 ),
            'max_infant_single' => (int) get_option( 'tcc_max_infant_single', 1 ),
        ) );
    }

    public static function endpoint_tours( WP_REST_Request $req ) {
        $tour_id = intval( $req->get_param( 'id' ) ?: 0 );
        $dest    = sanitize_text_field( $req->get_param( 'destination' ) ?: '' );

        if ( $tour_id ) {
            $post = get_post( $tour_id );
            if ( ! $post || $post->post_type !== 'tcc_fixed_tour' ) {
                return new WP_Error( 'not_found', 'Tour not found.', array( 'status' => 404 ) );
            }
            $d = get_post_meta( $tour_id, '_tcc_fd_destination', true ) ?: 'General / Other';
            return rest_ensure_response( self::build_tour( $tour_id, $post->post_title, $d ) );
        }

        $tours  = get_posts( array( 'post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        $result = array();
        foreach ( $tours as $t ) {
            $td = get_post_meta( $t->ID, '_tcc_fd_destination', true ) ?: 'General / Other';
            if ( $dest && strcasecmp( $td, $dest ) !== 0 ) continue;
            $payload = self::build_tour( $t->ID, $t->post_title, $td );
            if ( ! empty( $payload['prices']['dates'] ) ) $result[ $t->ID ] = $payload;
        }
        return rest_ensure_response( $result );
    }

    public static function endpoint_seats( WP_REST_Request $req ) {
        $tours = get_posts( array( 'post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        $data  = array();
        foreach ( $tours as $t ) {
            $dates = json_decode( get_post_meta( $t->ID, '_tcc_fd_dates', true ), true ) ?: array();
            foreach ( $dates as $d ) {
                $booked = TCC_Fixed_Departures::get_booked_seats( $t->ID, $d['start_date'] );
                $data[ $t->ID ][ $d['start_date'] ] = max( 0, intval( $d['capacity'] ) - $booked );
            }
        }
        return rest_ensure_response( $data );
    }

    public static function endpoint_book( WP_REST_Request $req ) {
        $tour_id    = intval( $req->get_param( 'tour_id' ) ?: 0 );
        $name       = sanitize_text_field( $req->get_param( 'name' ) ?: '' );
        $phone      = sanitize_text_field( $req->get_param( 'phone' ) ?: '' );
        $email      = sanitize_email( $req->get_param( 'email' ) ?: '' );
        $date       = sanitize_text_field( $req->get_param( 'date' ) ?: '' );
        $pax        = (array) ( $req->get_param( 'pax' ) ?: array() );
        $total      = floatval( $req->get_param( 'grand_total' ) ?: 0 );
        $source     = sanitize_text_field( $req->get_param( 'source_url' ) ?: '' );

        if ( ! $tour_id || ! $date ) {
            return new WP_Error( 'missing', 'tour_id and date are required.', array( 'status' => 400 ) );
        }

        $dbl = intval( $pax['double'] ?? 0 ); $tpl = intval( $pax['triple'] ?? 0 );
        $sgl = intval( $pax['single'] ?? 0 ); $chl = intval( $pax['child']  ?? 0 );
        $inf = intval( $pax['infant'] ?? 0 ); $total_pax = $dbl + $tpl + $sgl + $chl + $inf;

        $tour_title = get_the_title( $tour_id );
        $source_host = $source ? ( wp_parse_url( $source, PHP_URL_HOST ) ?: $source ) : '';
        $suffix     = $source_host ? " (via {$source_host})" : ' (Child Site Lead)';
        $q_title    = $name ? "{$name} - {$tour_title}{$suffix}" : "{$tour_title}{$suffix}";

        $post_id = wp_insert_post( array( 'post_title' => $q_title, 'post_type' => 'tcc_quote', 'post_status' => 'publish' ) );
        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'insert_failed', 'Could not create booking.', array( 'status' => 500 ) );
        }

        // Find end_date + per-date hotel_cat
        $end_date = $hotel_cat_date = '';
        $dates_raw = json_decode( get_post_meta( $tour_id, '_tcc_fd_dates', true ), true ) ?: array();
        foreach ( $dates_raw as $d ) {
            if ( $d['start_date'] === $date ) { $end_date = $d['end_date'] ?? ''; $hotel_cat_date = $d['hotel_cat'] ?? ''; break; }
        }
        $hotel_cat = $hotel_cat_date ?: ( get_post_meta( $tour_id, '_tcc_hotel_cat', true ) ?: 'Group Standard' );

        // Store all meta
        $meta_map = array(
            '_tcc_quote_type'       => 'fixed_departure',
            '_tcc_fixed_tour_id'    => $tour_id,
            '_tcc_start_date'       => $date,
            '_tcc_end_date'         => $end_date,
            '_tcc_client_name'      => $name,
            '_tcc_client_phone'     => $phone,
            '_tcc_client_email'     => $email,
            '_tcc_total_pax'        => $total_pax,
            '_tcc_fd_pax_breakdown' => $pax,
            '_tcc_grand_total'      => $total,
            '_tcc_quote_status'     => 'Pending',
        );
        foreach ( $meta_map as $k => $v ) update_post_meta( $post_id, $k, $v );

        // Build quote JSON
        $g        = get_option( 'tcc_global_settings', array( 'gst' => 5 ) );
        $gst_pct  = floatval( $g['gst'] ?? 5 );
        $base     = $total / ( 1 + $gst_pct / 100 );
        $gst_amt  = $total - $base;
        $days     = 1;
        if ( $date && $end_date ) { try { $days = ( new DateTime( $date ) )->diff( new DateTime( $end_date ) )->days + 1; } catch ( Exception $e ) {} }

        $itin_data = json_decode( get_post_meta( $tour_id, '_tcc_fd_itinerary_data', true ), true ) ?: array();
        $qjson = array(
            'grand_total'         => $total,
            'per_person'          => $total_pax > 0 ? $base / $total_pax : 0,
            'per_person_with_gst' => $total_pax > 0 ? $total / $total_pax : 0,
            'gst'                 => round( $gst_amt, 2 ),
            'summary' => array(
                'client_name'      => $name,       'client_phone' => $phone, 'client_email' => $email,
                'destination'      => $tour_title . ' (Group)',
                'start_date'       => $date,        'end_date'    => $end_date, 'days' => $days,
                'pax'              => $dbl + $tpl + $sgl, 'child_6_12' => $chl, 'child' => $inf,
                'rooms'            => max( 1, (int) ceil( $dbl / 2 ) + (int) ceil( $tpl / 3 ) + $sgl ),
                'extra_beds'       => 0,
                'transport_string' => get_post_meta( $tour_id, '_tcc_transport_details', true ) ?: 'Group Transport',
                'pickup'           => get_post_meta( $tour_id, '_tcc_pickup', true ) ?: 'Designated Point',
                'drop'             => get_post_meta( $tour_id, '_tcc_drop', true ) ?: 'Designated Point',
                'hotel_cat'        => $hotel_cat,
                'inclusions'       => get_post_meta( $tour_id, '_tcc_inclusions', true ),
                'exclusions'       => get_post_meta( $tour_id, '_tcc_exclusions', true ),
                'payment_terms'    => get_post_meta( $tour_id, '_tcc_payment_terms', true ),
                'important_note'   => get_post_meta( $tour_id, '_tcc_important_note', true ),
                'why_choose_us'    => get_post_meta( $tour_id, '_tcc_why_choose_us', true ),
                'essential_guidelines' => get_post_meta( $tour_id, '_tcc_essential_guidelines', true ),
                'head_office'      => get_post_meta( $tour_id, '_tcc_head_office', true ),
                'discount_amount'  => 0,
                'gst_pct'          => $gst_pct,
                'total_base_price' => $base,
                'itinerary'        => ! empty( $itin_data['itinerary'] ) ? array_values( (array) $itin_data['itinerary'] ) : array(),
                'itinerary_desc'   => ! empty( $itin_data['itinerary_desc'] ) ? array_values( (array) $itin_data['itinerary_desc'] ) : array(),
                'itinerary_stay_places' => ! empty( $itin_data['stay_places'] ) ? array_values( (array) $itin_data['stay_places'] ) : array(),
                'stays'            => array(),
            ),
            'raw' => array(),
        );
        update_post_meta( $post_id, '_tcc_quote_json_data', wp_slash( wp_json_encode( $qjson ) ) );

        $quote_url = get_permalink( $post_id );

        // Build WhatsApp URL — clean formatting, no inline source host that corrupts the quote URL
        $wa_num = preg_replace( '/[^0-9]/', '', get_option( 'tcc_wa_number', '' ) );
        $wa_msg = "Hi! I am interested in the group tour: *{$tour_title}*\n";
        $wa_msg .= "*Departure Date:* {$date}\n";
        $wa_msg .= "*Travelers:* {$total_pax}\n";
        $wa_msg .= "*Quoted Price:* Rs {$total}\n";
        if ( $name )  $wa_msg .= "*Name:* {$name}\n";
        if ( $phone ) $wa_msg .= "*Phone:* {$phone}\n";
        if ( $quote_url ) {
            $wa_msg .= "\n*My Quotation Link:*\n" . $quote_url . "\n";
        }
        if ( $source_host ) {
            $wa_msg .= "\n(Enquiry from: " . $source_host . ")";
        }
        $wa_url = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_msg );

        // Admin email
        if ( $name || $phone || $email ) {
            $to  = get_option( 'tcc_admin_email', get_option( 'admin_email' ) );
            $sub = 'New Child Lead' . ( $name ? ": {$name}" : '' ) . ( $source_host ? " from {$source_host}" : '' );
            $msg = "Tour: {$tour_title}\nDate: {$date}\n" . ( $name ? "Name: {$name}\n" : '' ) . ( $phone ? "Phone: {$phone}\n" : '' ) . ( $email ? "Email: {$email}\n" : '' ) . "Total: ₹{$total}\n" . ( $quote_url ? "Quote: {$quote_url}\n" : '' );
            @wp_mail( $to, $sub, $msg );
        }

        return rest_ensure_response( array( 'success' => true, 'quote_id' => $post_id, 'quote_url' => $quote_url, 'wa_url' => $wa_url ) );
    }

    // ── PDF generation (called by child plugin, authenticated via api_token) ─
    public static function ajax_generate_pdf() {
        $token  = sanitize_text_field( $_POST['api_token'] ?? '' );
        $stored = (string) get_option( 'tcc_child_api_key', '' );
        if ( empty( $stored ) || ! hash_equals( $stored, $token ) ) {
            status_header( 401 ); wp_die( 'Unauthorized.' );
        }
        $quote_id = intval( $_POST['quote_id'] ?? 0 );
        if ( ! $quote_id || get_post_type( $quote_id ) !== 'tcc_quote' ) {
            status_header( 400 ); wp_die( 'Invalid quote ID.' );
        }
        // Delegate to the existing frontend-ajax handler (which handles the full
        // wp_remote_get + DOMDocument + dompdf pipeline and streams the file)
        if ( class_exists( 'TCC_Frontend_Ajax' ) && method_exists( 'TCC_Frontend_Ajax', 'generate_pdf_by_id' ) ) {
            $_POST['quote_id'] = $quote_id; // already set but be explicit
            TCC_Frontend_Ajax::generate_pdf_by_id();
        }
        status_header( 500 ); wp_die( 'PDF generator unavailable.' );
    }

    // ── Helper ───────────────────────────────────────────────────────────────
    private static function build_tour( $id, $title, $dest ) {
        $prices = array(
            'double' => floatval( get_post_meta( $id, '_tcc_price_double', true ) ),
            'triple' => floatval( get_post_meta( $id, '_tcc_price_triple', true ) ),
            'single' => floatval( get_post_meta( $id, '_tcc_price_single', true ) ),
            'child'  => floatval( get_post_meta( $id, '_tcc_price_child',  true ) ),
            'infant' => floatval( get_post_meta( $id, '_tcc_price_infant', true ) ),
            'dates'  => array(),
        );
        $global_cat = get_post_meta( $id, '_tcc_hotel_cat', true ) ?: 'Standard';
        foreach ( json_decode( get_post_meta( $id, '_tcc_fd_dates', true ), true ) ?: array() as $d ) {
            $booked  = TCC_Fixed_Departures::get_booked_seats( $id, $d['start_date'] );
            $avail   = max( 0, intval( $d['capacity'] ) - $booked );
            $d['available']   = $avail;
            $d['is_sold_out'] = ( $avail <= 0 );
            $d['hotel_cat']   = ! empty( $d['hotel_cat'] ) ? $d['hotel_cat'] : $global_cat;
            $prices['dates'][] = $d;
        }
        return array( 'id' => $id, 'title' => $title, 'destination' => $dest, 'prices' => $prices );
    }

    // ── Admin — API Key Manager ─────────────────────────────────────────────
    public static function register_admin_menu() {
        add_submenu_page( 'options-general.php', 'TCC Child API', 'TCC API Key', 'manage_options', 'tcc-api-key', array( __CLASS__, 'admin_page_html' ) );
    }

    public static function handle_key_actions() {
        if ( empty( $_POST['tcc_api_action'] ) || ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'tcc_api_key_action' );
        if ( $_POST['tcc_api_action'] === 'generate' ) {
            update_option( 'tcc_child_api_key', wp_generate_password( 48, false ) );
            delete_option( 'tcc_child_last_seen_url' );
            delete_option( 'tcc_child_last_seen_time' );
        } elseif ( $_POST['tcc_api_action'] === 'revoke' ) {
            delete_option( 'tcc_child_api_key' );
            delete_option( 'tcc_child_last_seen_url' );
            delete_option( 'tcc_child_last_seen_time' );
        }
        wp_safe_redirect( admin_url( 'options-general.php?page=tcc-api-key&done=1' ) );
        exit;
    }

    public static function admin_page_html() {
        $key       = get_option( 'tcc_child_api_key', '' );
        $last_url  = get_option( 'tcc_child_last_seen_url', '' );
        $last_time = get_option( 'tcc_child_last_seen_time', '' );
        $rest_base = rest_url( 'tcc-api/v1' );
        ?>
        <div class="wrap">
            <h1>🔌 TCC Child Plugin — API Key Manager</h1>
            <?php if ( isset( $_GET['done'] ) ): ?><div class="notice notice-success is-dismissible"><p>Done.</p></div><?php endif; ?>

            <div style="max-width:680px; margin-top:20px; display:flex; flex-direction:column; gap:16px;">

                <div class="postbox" style="padding:16px;">
                    <h2 style="margin:0 0 8px;">🌐 API Base URL <span style="font-size:12px; color:#666; font-weight:normal;">(copy to child plugin)</span></h2>
                    <div style="display:flex; gap:6px;">
                        <input id="tcc-rest-url" type="text" value="<?php echo esc_attr( $rest_base ); ?>" readonly class="large-text code" style="background:#f0f0f1;" onclick="this.select();" />
                        <button class="button" onclick="navigator.clipboard.writeText(document.getElementById('tcc-rest-url').value);this.textContent='✅';setTimeout(()=>this.textContent='📋 Copy',2000)">📋 Copy</button>
                    </div>
                </div>

                <div class="postbox" style="padding:16px;">
                    <h2 style="margin:0 0 8px;">🔑 API Key</h2>
                    <?php if ( empty( $key ) ): ?>
                        <p style="color:#d63638;">No key generated yet.</p>
                    <?php else: ?>
                        <div style="display:flex; gap:6px; margin-bottom:8px;">
                            <input id="tcc-api-key" type="text" value="<?php echo esc_attr( $key ); ?>" readonly class="large-text code" style="background:#f6f7f7;" onclick="this.select();" />
                            <button class="button" onclick="navigator.clipboard.writeText(document.getElementById('tcc-api-key').value);this.textContent='✅';setTimeout(()=>this.textContent='📋 Copy',2000)">📋 Copy</button>
                        </div>
                        <p class="description">Keep this secret. It grants booking access to your site.</p>
                    <?php endif; ?>
                    <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                        <form method="post"><?php wp_nonce_field( 'tcc_api_key_action' ); ?><input type="hidden" name="tcc_api_action" value="generate">
                            <button class="button button-primary" onclick="return confirm('<?php echo empty($key)?'Generate a new API key?':'Regenerate? This disconnects the current child immediately.'; ?>')"><?php echo empty($key)?'🔑 Generate Key':'🔄 Regenerate'; ?></button>
                        </form>
                        <?php if ( $key ): ?><form method="post"><?php wp_nonce_field( 'tcc_api_key_action' ); ?><input type="hidden" name="tcc_api_action" value="revoke">
                            <button class="button" style="color:#d63638;border-color:#d63638;" onclick="return confirm('Revoke? Child plugin stops immediately.')">🗑️ Revoke</button>
                        </form><?php endif; ?>
                    </div>
                </div>

                <?php if ( $last_url ): ?>
                <div class="postbox" style="padding:16px; border-left:4px solid #00a32a;">
                    <h2 style="margin:0 0 8px; color:#00a32a;">📡 Connected Child Site</h2>
                    <p style="margin:0;"><strong>URL:</strong> <a href="<?php echo esc_url($last_url); ?>" target="_blank"><?php echo esc_html($last_url); ?></a></p>
                    <p style="margin:4px 0 0; color:#666; font-size:12px;">Last API call: <?php echo esc_html($last_time); ?></p>
                </div>
                <?php endif; ?>

                <div class="postbox" style="padding:16px; background:#fffbeb; border-color:#f0c040;">
                    <h3 style="margin:0 0 8px;">📋 Quick Setup</h3>
                    <ol style="margin:0; padding-left:18px; font-size:13px; line-height:2;">
                        <li>Install <strong>TCC Child Plugin</strong> on the second website</li>
                        <li>Go to <strong>Settings → TCC Child Plugin</strong></li>
                        <li>Paste the <strong>API Base URL</strong> and <strong>API Key</strong> above</li>
                        <li>Click <strong>Test Connection</strong> → should show ✅ Connected</li>
                        <li>Add shortcodes to any page: <code>[tcc_all_group_tours]</code></li>
                    </ol>
                </div>

            </div>
        </div>
        <?php
    }
}
TCC_API::init();