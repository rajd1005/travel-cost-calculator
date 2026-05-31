<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_Packages {

    public static function init() {
        add_action( 'init',            array( __CLASS__, 'register_cpt' ) );
        add_filter( 'template_include',array( __CLASS__, 'package_template' ) );
        add_filter( 'the_content',     array( __CLASS__, 'content_filter' ), 20 );
        add_shortcode( 'tcc_packages', array( __CLASS__, 'shortcode_grid' ) );

        // SEO + UX hooks for single package pages
        add_action( 'wp_head',         array( __CLASS__, 'seo_meta' ), 2 );
        add_action( 'wp_head',         array( __CLASS__, 'hide_title_css' ) );

        add_action( 'wp_ajax_tcc_publish_preset_package',   array( __CLASS__, 'ajax_publish' ) );
        add_action( 'wp_ajax_tcc_delete_package_post',      array( __CLASS__, 'ajax_delete' ) );
        add_action( 'wp_ajax_tcc_list_all_packages',        array( __CLASS__, 'ajax_list' ) );
        add_action( 'wp_ajax_tcc_get_tours_for_pkg_link',   array( __CLASS__, 'ajax_tours_for_link' ) );
        add_action( 'wp_ajax_tcc_toggle_package_status',    array( __CLASS__, 'ajax_toggle_status' ) );
        add_action( 'wp_ajax_tcc_save_preset_extras',       array( __CLASS__, 'ajax_save_preset_extras' ) );
        add_action( 'wp_ajax_tcc_set_package_image',        array( __CLASS__, 'ajax_set_package_image' ) );
        add_action( 'wp_ajax_tcc_set_vehicle_image',        array( __CLASS__, 'ajax_set_vehicle_image' ) );
        // Group tour package content (addons, inclusions, exclusions, payment terms)
        add_action( 'wp_ajax_tcc_save_tour_content',        array( __CLASS__, 'ajax_save_tour_content' ) );
        add_action( 'wp_ajax_tcc_get_tour_content',         array( __CLASS__, 'ajax_get_tour_content' ) );

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

    // ─── the_content filter ─────────────────────────────────────────────────
    public static function content_filter( $content ) {
        if ( ! is_singular( 'tcc_package' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        ob_start();
        self::render_package_html( get_the_ID() );
        return ob_get_clean();
    }

    // ─── SEO: Open Graph + Twitter Card meta tags ────────────────────────────
    public static function seo_meta() {
        if ( ! is_singular( 'tcc_package' ) ) return;

        $pid   = get_the_ID();
        $url   = get_permalink( $pid );
        $site  = get_bloginfo( 'name' );

        // ── META TITLE: Post Title | Site Name ──────────────────────────────
        $title = get_the_title( $pid ) . ' | ' . $site;

        // ── META DESCRIPTION: Route + Day 1 Itinerary details ───────────────
        $route          = get_post_meta( $pid, '_tcc_pkg_route', true );
        $itinerary      = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary',      true ), true ) ?: array();
        $itinerary_desc = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary_desc', true ), true ) ?: array();

        $desc_parts = array();

        // Route
        if ( $route ) {
            $desc_parts[] = 'Route: ' . $route;
        }

        // Day 1 title + description
        $day1_title = isset( $itinerary[0] )      ? trim( strip_tags( $itinerary[0] ) )      : '';
        $day1_text  = isset( $itinerary_desc[0] ) ? trim( strip_tags( $itinerary_desc[0] ) ) : '';

        if ( $day1_title ) {
            $day1 = 'Day 1 – ' . $day1_title;
            if ( $day1_text ) $day1 .= ': ' . $day1_text;
            $desc_parts[] = $day1;
        }

        $desc = implode( '. ', $desc_parts );
        $desc = preg_replace( '/\s+/', ' ', $desc ); // collapse whitespace
        $desc = trim( $desc );

        // Trim to 155 chars for Google snippet (hard SEO limit)
        if ( mb_strlen( $desc ) > 155 ) {
            $desc = mb_substr( $desc, 0, 152 ) . '...';
        }

        // Fallback if no route/itinerary data yet
        if ( ! $desc ) {
            $destination = get_post_meta( $pid, '_tcc_pkg_destination', true );
            $days        = (int) get_post_meta( $pid, '_tcc_pkg_days', true );
            $nights      = max( 0, $days - 1 );
            $desc        = trim( $destination . ( $days ? ' – ' . $days . 'D/' . $nights . 'N tour package' : '' ) );
        }

        // ── META IMAGE: Featured (uploaded) image → first itinerary image ───
        $img = get_the_post_thumbnail_url( $pid, 'full' );
        if ( ! $img ) {
            $iimgs = json_decode( get_post_meta( $pid, '_tcc_pkg_itinerary_img', true ), true );
            if ( is_array( $iimgs ) ) {
                foreach ( $iimgs as $u ) { if ( $u ) { $img = $u; break; } }
            }
        }

        // ── OUTPUT ───────────────────────────────────────────────────────────
        echo "\n<!-- TCC Package SEO Meta Tags -->\n";

        // Standard meta
        echo '<meta name="description" content="'    . esc_attr( $desc )  . '">' . "\n";
        echo '<link rel="canonical" href="'          . esc_url( $url )    . '">' . "\n";

        // Open Graph (Facebook, WhatsApp, LinkedIn)
        echo '<meta property="og:type"        content="website">' . "\n";
        echo '<meta property="og:site_name"   content="' . esc_attr( $site )  . '">' . "\n";
        echo '<meta property="og:title"       content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $desc )  . '">' . "\n";
        echo '<meta property="og:url"         content="' . esc_url( $url )    . '">' . "\n";
        if ( $img ) {
            echo '<meta property="og:image"        content="' . esc_url( $img ) . '">' . "\n";
            echo '<meta property="og:image:width"  content="1200">'  . "\n";
            echo '<meta property="og:image:height" content="630">'   . "\n";
            echo '<meta property="og:image:alt"    content="' . esc_attr( get_the_title( $pid ) ) . '">' . "\n";
        }

        // Twitter Card
        echo '<meta name="twitter:card"        content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title"       content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $desc )  . '">' . "\n";
        if ( $img ) {
            echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr( get_the_title( $pid ) ) . '">' . "\n";
        }

        // Also filter the document <title> tag for search results
        add_filter( 'pre_get_document_title', function() use ( $title ) { return $title; }, 99 );

        echo "<!-- /TCC Package SEO -->\n\n";
    }

    // ─── Hide theme page title on package single pages ───────────────────────
    public static function hide_title_css() {
        if ( ! is_singular( 'tcc_package' ) ) return;
        echo '<style id="tcc-pkg-hide-title">
/* ── Hide duplicate theme title ─────────────────────────────────────────── */
.single-tcc_package .entry-title,
.single-tcc_package .page-title,
.single-tcc_package .post-title,
.single-tcc_package h1.wp-block-post-title,
.single-tcc_package .entry-header h1,
.single-tcc_package .page-header { display:none!important }
.single-tcc_package .entry-header:empty,
.single-tcc_package .page-header:empty { margin:0!important;padding:0!important }

/* ── Force full-width content area (works with most themes) ─────────────── */
/* Strip padding from the content wrapper so hero reaches screen edges       */
.single-tcc_package .entry-content,
.single-tcc_package .post-content,
.single-tcc_package .page-content,
.single-tcc_package .wp-block-post-content,
.single-tcc_package article.type-tcc_package {
    padding:0!important;
    margin-left:0!important;
    margin-right:0!important;
    max-width:100%!important;
    width:100%!important;
    box-sizing:border-box!important;
}
/* Common theme content column wrappers */
.single-tcc_package #content,
.single-tcc_package #main,
.single-tcc_package main.site-main,
.single-tcc_package .site-content,
.single-tcc_package .content-area,
.single-tcc_package .hentry,
.single-tcc_package article {
    max-width:100%!important;
    padding-left:0!important;
    padding-right:0!important;
}
/* Block editor / Gutenberg full width */
.single-tcc_package .wp-block-group,
.single-tcc_package .is-layout-constrained > * {
    max-width:100%!important;
    padding-left:0!important;
    padding-right:0!important;
}
/* Prevent any parent from clipping the hero */
.single-tcc_package .entry-content,
.single-tcc_package .post-content { overflow:visible!important }
</style>' . "\n";
    }

    // ─── Full package page HTML (self-contained, works inside any theme) ────
    // ─── Full package page HTML — mobile-first + desktop 2-col ─────────────
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

        $pkg_adults     = (int) get_post_meta( $pid, '_tcc_pkg_adults',     true );
        $pkg_rooms      = (int) get_post_meta( $pid, '_tcc_pkg_rooms',      true );
        $pkg_extra_beds = (int) get_post_meta( $pid, '_tcc_pkg_extra_beds', true );
        $pkg_price_pp   = get_post_meta( $pid, '_tcc_pkg_price_pp', true );
        $pkg_vehicles   = json_decode( get_post_meta( $pid, '_tcc_pkg_vehicles', true ), true ) ?: array();
        $pkg_addons     = json_decode( get_post_meta( $pid, '_tcc_pkg_addons',   true ), true ) ?: array();
        $pkg_pickup     = get_post_meta( $pid, '_tcc_pkg_pickup', true );
        $pkg_drop       = get_post_meta( $pid, '_tcc_pkg_drop',   true );

        // ── Linked group tour data ───────────────────────────────────────────
        $linked_tour_id    = (int) $linked_tour;
        $linked_tour_title = '';
        $linked_tour_price = 0;
        $tour_logistics    = array( 'vehicle' => '', 'pickup' => '', 'drop' => '' );
        $vehicle_image     = '';
        $is_group_tour     = false;
        if ( $linked_tour_id ) {
            $lt = get_post( $linked_tour_id );
            if ( $lt ) {
                $is_group_tour     = true;
                $linked_tour_title = $lt->post_title;
                $linked_tour_price = self::get_tour_triple_price( $linked_tour_id );
                $tour_logistics    = self::get_tour_logistics( $linked_tour_id );
                $vehicle_image     = self::get_tour_vehicle_image( $linked_tour_id );
            }
        }

        // Override vehicle/pickup/drop with group tour's logistics if available
        if ( $is_group_tour ) {
            if ( $tour_logistics['vehicle'] ) $pkg_vehicles = array( $tour_logistics['vehicle'] );
            if ( $tour_logistics['pickup'] )  $pkg_pickup   = $tour_logistics['pickup'];
            if ( $tour_logistics['drop'] )    $pkg_drop     = $tour_logistics['drop'];
        }

        // ── Auto-update: Pull Package Content live for group tour packages ──────
        //
        // PRIORITY CHAIN (highest → lowest):
        //  1. _tcc_tour_inclusions (accordion 6 per-tour override)  ─┐ via get_tour_content()
        //  2. _tcc_inclusions      (tcc-script.js Group Tour Manager) ─┘
        //  3. tcc_master_settings[$destination] (live Destination Setup)
        //  4. _tcc_pkg_inclusions  (stale preset cache — last resort)
        //
        // Itinerary and Hotels are read directly from _tcc_fd_itinerary_data
        // saved by tcc-script.js — so editing a tour's itinerary in the
        // Group Tour Manager automatically updates all linked package pages.
        if ( $is_group_tour ) {
            $tc = self::get_tour_content( $linked_tour_id );

            // ── Add-ons: tour-specific (new field, set via accordion 6) ──────
            if ( ! empty( $tc['addons'] ) ) {
                $pkg_addons = $tc['addons'];
            }

            // ── Package Details: tour > destination master > stale preset ────
            $master      = get_option( 'tcc_master_settings', array() );
            $dest_master = isset( $master[ $destination ] ) ? $master[ $destination ] : array();

            if ( $tc['inclusions'] ) {
                $inclusions = $tc['inclusions'];          // tour-specific
            } elseif ( ! empty( $dest_master['inclusions'] ) ) {
                $inclusions = $dest_master['inclusions']; // destination live
            }
            // else: keep stale $inclusions from _tcc_pkg_inclusions

            if ( $tc['exclusions'] ) {
                $exclusions = $tc['exclusions'];
            } elseif ( ! empty( $dest_master['exclusions'] ) ) {
                $exclusions = $dest_master['exclusions'];
            }

            if ( $tc['payment_terms'] ) {
                $payment_terms = $tc['payment_terms'];
            } elseif ( ! empty( $dest_master['payment_terms'] ) ) {
                $payment_terms = $dest_master['payment_terms'];
            }

            // ── Days / Nights: scan departure data on the fixed tour ─────────
            $tour_days = self::get_tour_days( $linked_tour_id );
            if ( $tour_days > 0 ) {
                $days   = $tour_days;
                $nights = max( 0, $days - 1 );
            }

            // ── Day-Wise Itinerary: use tour's own if set; keep preset otherwise
            if ( ! empty( $tc['itinerary'] ) && array_filter( $tc['itinerary'] ) ) {
                $itinerary      = $tc['itinerary'];
                $itinerary_desc = $tc['itinerary_desc'];
                $itinerary_img  = $tc['itinerary_img'];
                $stay_places    = $tc['stay_places'];
                // Recalculate days from tour itinerary length
                $itin_days = count( array_filter( $itinerary ) );
                if ( $itin_days > 0 ) { $days = $itin_days; $nights = max( 0, $days - 1 ); }
            }

            // ── Hotels: use tour's own if set; keep preset otherwise ──────────
            if ( ! empty( $tc['routing']['places'] ) && array_filter( $tc['routing']['places'] ) ) {
                $routing      = $tc['routing'];
                $r_places     = isset( $routing['places'] )     ? $routing['places']     : array();
                $r_categories = isset( $routing['categories'] ) ? $routing['categories'] : array();
                $r_nights     = isset( $routing['nights'] )     ? $routing['nights']     : array();
                $r_hotels     = isset( $routing['hotels'] )     ? $routing['hotels']     : array();
                $r_room_types = isset( $routing['room_types'] ) ? $routing['room_types'] : array();
            }
        }

        // Hero image: featured → first itinerary image
        $hero_img = get_the_post_thumbnail_url( $pid, 'full' );
        if ( ! $hero_img ) {
            foreach ( $itinerary_img as $img ) { if ( $img ) { $hero_img = $img; break; } }
        }

        // WhatsApp — different message for group tour vs custom package
        $wa_num = preg_replace( '/[^0-9]/', '', get_option( 'tcc_wa_number', '' ) );
        if ( $is_group_tour ) {
            $price_info = $linked_tour_price > 0
                ? ' (Triple Sharing from ₹' . number_format( (float) $linked_tour_price, 0 ) . '/pp)'
                : '';
            $wa_text = 'Hi! I am interested in the *' . get_the_title( $pid ) . '* Group Tour ('
                . $days . 'D/' . $nights . 'N)' . $price_info . '.'
                . ' Please share upcoming departure dates and availability.';
        } else {
            $wa_text = 'Hi! I am interested in the *' . get_the_title( $pid ) . '* package ('
                . $days . 'D/' . $nights . 'N).'
                . ' Please share availability and pricing details.';
        }
        $wa_url = 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_text );

        // Random enquiry count — changes on every page load
        $fomo_count = rand( 28, 87 );

        // Linked tour URL
        $book_url = '';
        if ( $linked_tour ) {
            $bpages = get_posts( array(
                'post_type' => 'page', 'post_status' => 'publish',
                'posts_per_page' => 1, 's' => (string) $linked_tour,
            ) );
            if ( ! empty( $bpages ) ) $book_url = get_permalink( $bpages[0]->ID );
        }
        ?>

<style>
/* ═══ RESET ══════════════════════════════════════════════════════════════════ */
.tp{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b;-webkit-font-smoothing:antialiased;overflow-x:clip}
.tp *{box-sizing:border-box;margin:0;padding:0}
.tp a{color:inherit;text-decoration:none}
.tp img{max-width:100%;display:block}

/* ═══ HERO — always full viewport width ══════════════════════════════════════ */
.tp-hero{width:100%;position:relative;overflow:hidden;background:linear-gradient(160deg,#0f2027,#203a43,#2c5364);height:72vw;min-height:280px;max-height:520px}
.tp-hero img{width:100%;height:100%;object-fit:cover;object-position:center;display:block}
/* Also keep vw-bleed as backup for themes that don't respond to the wp_head CSS */
.tp,.tp-hero{margin-left:0;margin-right:0;box-sizing:border-box}
.tp-hero-ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.75) 0%,rgba(0,0,0,.2) 50%,transparent 100%);pointer-events:none}
.tp-hero-body{position:absolute;bottom:0;left:0;right:0;padding:20px 16px 22px}
.tp-hero-dest{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#fbbf24;margin-bottom:6px}
.tp-hero-title{font-size:22px;font-weight:900;color:#fff;line-height:1.2;margin-bottom:10px;text-shadow:0 2px 14px rgba(0,0,0,.6)}
.tp-hero-pills{display:flex;gap:7px;flex-wrap:wrap}
.tp-hero-pill{background:rgba(255,255,255,.15);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.25);color:#fff;font-size:11px;font-weight:700;padding:4px 11px;border-radius:20px;white-space:nowrap}
.tp-hero-pill.hot{background:rgba(239,68,68,.75);border-color:rgba(239,68,68,.4)}

/* ═══ TRUST CARDS ROW — fixed 3-col grid, no scroll ════════════════════════ */
.tp-trust{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
.tp-trust-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 8px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.tp-trust-icon{font-size:20px;line-height:1}
.tp-trust-main{font-size:11px;font-weight:800;color:#0f172a;line-height:1.3}
.tp-trust-sub{font-size:10px;color:#64748b;font-weight:500;line-height:1.3}
@keyframes tp-pulse{0%,100%{opacity:1}50%{opacity:.35}}
.tp-pulse{animation:tp-pulse 1.8s infinite}

/* ═══ STICKY BOTTOM BAR — mobile only ════════════════════════════════════════ */
.tp-sticky{display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#fff;border-top:2px solid #e2e8f0;padding:10px 16px 10px;align-items:center;justify-content:space-between;gap:10px;box-shadow:0 -4px 24px rgba(0,0,0,.14)}
.tp-sticky-price{display:flex;flex-direction:column;line-height:1.1}
.tp-sticky-from{font-size:9px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.tp-sticky-amt{font-size:19px;font-weight:900;color:#0f172a}
.tp-sticky-note{font-size:9px;color:#94a3b8}
.tp-sticky-wa{display:flex;align-items:center;gap:7px;background:#16a34a;color:#fff!important;font-size:14px;font-weight:800;padding:12px 20px;border-radius:10px;text-decoration:none!important;white-space:nowrap;flex-shrink:0;box-shadow:0 4px 16px rgba(22,163,74,.38)}

/* ═══ PAGE BODY ═══════════════════════════════════════════════════════════════ */
.tp-body{padding:16px 16px 100px}

/* Mobile layout: single column, sidebar content first */
.tp-layout{display:flex;flex-direction:column;gap:0}
.tp-sidebar{order:1}
.tp-main{order:2}

/* ═══ PRICE CARD ══════════════════════════════════════════════════════════════ */
/* Mobile: shown inside main col (before Package Details); hidden in sidebar   */
/* Desktop: shown inside sidebar; hidden in main col                           */
.tp-price-mob{display:block;margin-bottom:22px}
.tp-price-desk{display:none}
/* Group tour shortcode visibility */
.tp-group-mob{display:block}   /* mobile: visible in main col */
.tp-group-desk{display:none}   /* mobile: hidden in sidebar   */
.tp-price-card{background:linear-gradient(145deg,#0c1445 0%,#1e3a8a 60%,#1e40af 100%);border-radius:16px;padding:20px;color:#fff;margin-bottom:16px;position:relative;overflow:hidden}
.tp-price-card::before{content:'';position:absolute;top:-50px;right:-50px;width:150px;height:150px;background:rgba(255,255,255,.04);border-radius:50%}
.tp-price-card::after{content:'';position:absolute;bottom:-40px;left:-20px;width:120px;height:120px;background:rgba(255,255,255,.03);border-radius:50%}
.tp-price-badge{position:absolute;top:14px;right:14px;background:#fbbf24;color:#0f172a;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.8px;padding:3px 9px;border-radius:20px}
.tp-price-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#93c5fd;margin-bottom:4px}
.tp-price-val{font-size:38px;font-weight:900;line-height:1;color:#fff;position:relative;z-index:1}
.tp-price-sub{font-size:11px;color:#93c5fd;margin-top:5px;margin-bottom:16px;line-height:1.4}
.tp-price-wa{display:flex;align-items:center;justify-content:center;gap:8px;background:#16a34a;color:#fff!important;font-size:14px;font-weight:800;padding:13px;border-radius:10px;text-decoration:none!important;width:100%;box-shadow:0 4px 14px rgba(22,163,74,.4);position:relative;z-index:1}

/* ═══ SECTION TITLE ══════════════════════════════════════════════════════════ */
.tp-sect{margin-bottom:22px}
.tp-sect-title{font-size:15px;font-weight:800;color:#0f172a;border-left:3px solid #b93b59;padding-left:10px;margin-bottom:13px;display:flex;align-items:center;gap:8px;line-height:1}

/* ═══ VEHICLE CARD ═══════════════════════════════════════════════════════════ */
.tp-veh-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:16px;overflow:hidden}
.tp-veh-rows{padding:2px 14px}
.tp-veh-img{width:100%;height:180px;object-fit:cover;object-position:center;display:block}
.tp-veh-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9}
.tp-veh-row:last-child{border-bottom:none}
.tp-veh-icon{font-size:19px;flex-shrink:0;padding-top:2px}
.tp-veh-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:#94a3b8;margin-bottom:5px}
.tp-veh-tags{display:flex;flex-wrap:wrap;gap:6px}
.tp-veh-tag{background:#fff;border:1px solid #e2e8f0;color:#334155;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px}
.tp-route{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.tp-route-loc{font-size:14px;font-weight:800;color:#0f172a}
.tp-addon-tag{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;font-weight:600;padding:4px 11px;border-radius:20px}

/* ═══ ITINERARY ACCORDION ═══════════════════════════════════════════════════ */
.tp-day{border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:8px;background:#fff}
.tp-day-hd{display:flex;align-items:center;gap:11px;padding:13px 14px;cursor:pointer;-webkit-tap-highlight-color:transparent;user-select:none;transition:background .15s}
.tp-day-hd:active{background:#f8fafc}
.tp-day-num{background:#b93b59;color:#fff;font-size:10px;font-weight:800;padding:3px 9px;border-radius:20px;white-space:nowrap;flex-shrink:0}
.tp-day-ttl{font-size:14px;font-weight:700;color:#0f172a;flex:1;line-height:1.3}
.tp-day-arr{font-size:12px;color:#94a3b8;transition:transform .25s;flex-shrink:0;border:1px solid #e2e8f0;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center}
.tp-day.open .tp-day-arr{transform:rotate(180deg);border-color:#b93b59;color:#b93b59}
.tp-day-body{display:none;padding:0 14px 14px;border-top:1px solid #f1f5f9}
.tp-day.open .tp-day-body{display:block}
.tp-day-img{width:100%;border-radius:8px;margin:12px 0 10px;object-fit:cover;object-position:center;max-height:220px}
.tp-day-desc{font-size:14px;color:#475569;line-height:1.7}
.tp-day-desc ul,.tp-day-desc ol{padding-left:16px;margin:6px 0}
.tp-day-desc li{margin-bottom:4px}
.tp-day-desc p{margin-bottom:6px}
.tp-day-stay{display:inline-flex;align-items:center;gap:5px;margin-top:10px;font-size:13px;font-weight:700;color:#1d4ed8;padding:5px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px}

/* ═══ HOTELS ═════════════════════════════════════════════════════════════════ */
.tp-hotel-list{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
.tp-hotel-row{padding:14px 16px;border-bottom:1px solid #f1f5f9}
.tp-hotel-row:last-child{border-bottom:none}
/* Category as coloured header bar */
.tp-hotel-cat-bar{display:inline-flex;align-items:center;gap:5px;background:#4f46e5;color:#fff;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;padding:3px 10px;border-radius:4px;margin-bottom:6px}
.tp-hotel-place{font-size:14px;font-weight:800;color:#0f172a;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.tp-hotel-meta-row{display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap}
.tp-hotel-nights-badge{background:#f0fdf4;color:#166534;font-size:12px;font-weight:700;padding:2px 9px;border-radius:20px}
.tp-hotel-room-type{font-size:12px;color:#64748b;font-weight:600}
.tp-hotel-names{font-size:13px;color:#334155;line-height:1.8}

/* ═══ INCLUSIONS TABS ═══════════════════════════════════════════════════════ */
.tp-tabs{display:flex;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:12px}
.tp-tab{flex:1;padding:10px 6px;font-size:12px;font-weight:700;text-align:center;cursor:pointer;background:#f8fafc;color:#64748b;border:none;-webkit-tap-highlight-color:transparent;border-right:1px solid #e2e8f0}
.tp-tab:last-child{border-right:none}
.tp-tab.active{background:#fff;color:#0f172a;box-shadow:inset 0 -2px 0 #b93b59}
.tp-tab-panel{display:none;font-size:14px;line-height:1.7;color:#334155}
.tp-tab-panel.active{display:block}
.tp-tab-panel ul,.tp-tab-panel ol{padding-left:16px}
.tp-tab-panel li{margin-bottom:4px}
.tp-tab-panel p{margin-bottom:6px}
.tp-inc-wrap{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;margin-bottom:10px}
.tp-exc-wrap{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;margin-bottom:10px}
.tp-pay-wrap{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px}
.tp-panel-ttl{font-size:13px;font-weight:800;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(0,0,0,.07)}

/* ═══ FINAL CTA ══════════════════════════════════════════════════════════════ */
.tp-cta{background:linear-gradient(145deg,#0c1445,#1e3a8a);border-radius:16px;padding:24px 18px;text-align:center;color:#fff;margin-top:6px}
.tp-cta h3{font-size:18px;font-weight:900;margin-bottom:6px}
.tp-cta p{font-size:13px;opacity:.8;margin-bottom:18px;line-height:1.5}
.tp-cta-btns{display:flex;flex-direction:column;gap:10px}
.tp-cta-wa{display:flex;align-items:center;justify-content:center;gap:8px;background:#16a34a;color:#fff!important;font-size:15px;font-weight:800;padding:14px;border-radius:10px;text-decoration:none!important;box-shadow:0 4px 14px rgba(22,163,74,.45)}
.tp-cta-book{display:flex;align-items:center;justify-content:center;gap:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);color:#fff!important;font-size:14px;font-weight:700;padding:13px;border-radius:10px;text-decoration:none!important}
.tp-group-badge{display:inline-flex;align-items:center;gap:6px;background:#fbbf24;color:#0f172a;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;padding:3px 10px;border-radius:20px;margin-bottom:10px}

/* ═══ DESKTOP 2-COLUMN ═══════════════════════════════════════════════════════ */
@media (min-width:900px){
  .tp-body{padding:28px 32px 60px;max-width:1100px;margin:0 auto}
  .tp-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:32px;align-items:start}
  .tp-sidebar{order:0;grid-column:2;grid-row:1}
  .tp-main{order:0;grid-column:1;grid-row:1}
  .tp-sidebar-inner{position:sticky;top:24px}
  .tp-hero-title{font-size:32px}
  .tp-hero-body{padding:32px 36px 36px;max-width:1100px;margin:0 auto;left:50%;transform:translateX(-50%);width:1100px}
  .tp-price-val{font-size:42px}
  /* On desktop: show price in sidebar, hide it from main col */
  .tp-price-mob{display:none}
  .tp-price-desk{display:block}
  /* Group tour shortcode: swap visibility on desktop */
  .tp-group-mob{display:none}
  .tp-group-desk{display:block}
  .tp-cta-btns{flex-direction:row;justify-content:center}
  .tp-cta-wa,.tp-cta-book{flex:1;max-width:240px}
  .tp-sticky{display:none!important}
}
@media (max-width:899px){
  .tp-sticky{display:flex}
}

/* ═══ MEDIUM TABLETS ═════════════════════════════════════════════════════════ */
@media (min-width:600px) and (max-width:899px){
  .tp-body{padding:20px 24px 100px}
  .tp-hero-title{font-size:26px}
  .tp-price-val{font-size:34px}
}
</style>

<div class="tp" id="tp-<?php echo (int) $pid; ?>">

    <!-- ══ HERO ════════════════════════════════════════════════════════════ -->
    <div class="tp-hero">
        <?php if ( $hero_img ) : ?>
        <img src="<?php echo esc_url( $hero_img ); ?>"
             alt="<?php echo esc_attr( get_the_title( $pid ) ); ?>"
             loading="eager"
             style="width:100%;height:100%;object-fit:cover;object-position:center;">
        <?php endif; ?>
        <div class="tp-hero-ov"></div>
        <div class="tp-hero-body">
            <?php if ( $destination ) : ?>
            <div class="tp-hero-dest">📍 <?php echo esc_html( $destination ); ?></div>
            <?php endif; ?>
            <h1 class="tp-hero-title"><?php echo esc_html( get_the_title( $pid ) ); ?></h1>
            <div class="tp-hero-pills">
                <?php if ( $days ) : ?>
                <span class="tp-hero-pill">🌅 <?php echo esc_html( $days ); ?>D / <?php echo esc_html( $nights ); ?>N</span>
                <?php endif; ?>
                <?php if ( $is_group_tour ) : ?>
                <span class="tp-hero-pill" style="background:rgba(251,191,36,.85);color:#0f172a;border-color:rgba(251,191,36,.5);font-weight:800;">👥 Group Tour</span>
                <?php elseif ( $pkg_adults >= 1 ) : ?>
                <span class="tp-hero-pill">👥 <?php echo esc_html( $pkg_adults ); ?> Adults</span>
                <?php endif; ?>
                <span class="tp-hero-pill hot">🔥 High Demand</span>
            </div>
        </div>
    </div>

    <!-- ══ BODY ════════════════════════════════════════════════════════════ -->
    <div class="tp-body">

        <!-- 2-COLUMN LAYOUT (desktop) / stacked (mobile) -->
        <div class="tp-layout">

            <!-- ── SIDEBAR: Trust + Price + Transport + CTA ──────────────────────── -->
            <div class="tp-sidebar">
                <div class="tp-sidebar-inner">

                    <!-- TRUST CARDS — 3 cards above price -->
                    <div class="tp-trust">
                        <div class="tp-trust-card">
                            <span class="tp-trust-icon">🔥</span>
                            <span class="tp-trust-main"><?php echo esc_html( $fomo_count ); ?> Enquiries</span>
                            <span class="tp-trust-sub">This week</span>
                        </div>
                        <div class="tp-trust-card">
                            <span class="tp-trust-icon">🎯</span>
                            <span class="tp-trust-main">Customisable</span>
                            <span class="tp-trust-sub">Tailored for you</span>
                        </div>
                        <div class="tp-trust-card">
                            <span class="tp-trust-icon">🏅</span>
                            <span class="tp-trust-main">Local Experts</span>
                            <span class="tp-trust-sub">Trusted agency</span>
                        </div>
                    </div>

                    <!-- 1. PRICE CARD — desktop only -->
                    <div class="tp-price-desk">
                    <?php
                    $display_price    = $linked_tour_price ? $linked_tour_price : $pkg_price_pp;
                    $display_price_label = $linked_tour_price ? 'Triple Sharing · Per Person' : 'Per Person · Incl. GST';
                    $display_price_badge = $linked_tour_price ? 'Group Tour Price' : 'Best Price ✨';
                    if ( $display_price ) :
                    ?>
                    <div class="tp-price-card">
                        <div class="tp-price-badge"><?php echo esc_html( $display_price_badge ); ?></div>
                        <div class="tp-price-label">Starting From</div>
                        <div class="tp-price-val">
                            <?php
                            if ( $linked_tour_price ) {
                                echo '₹' . esc_html( number_format( (float) $linked_tour_price, 0 ) );
                            } else {
                                $numeric = floatval( preg_replace( '/[^\d.]/', '', $display_price ) );
                                echo '₹' . esc_html( number_format( $numeric, 0 ) );
                            }
                            ?>
                        </div>
                        <div class="tp-price-sub"><?php echo esc_html( $display_price_label ); ?><?php
                            if ( ! $linked_tour_price ) {
                                $bits = array();
                                if ( $pkg_adults >= 1 ) $bits[] = esc_html( $pkg_adults ) . ' adults';
                                if ( $pkg_rooms  >= 1 ) $bits[] = esc_html( $pkg_rooms  ) . ' room' . ( $pkg_rooms > 1 ? 's' : '' );
                                if ( $bits ) echo ' · Based on ' . implode( ', ', $bits );
                            }
                        ?></div>
                        <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tp-price-wa">💬 Get This Price on WhatsApp</a>
                    </div>
                    <?php endif; ?>
                    </div>

                    <!-- 2. VEHICLE & TRANSPORT -->
                    <?php if ( ! empty( $pkg_vehicles ) || $pkg_pickup || $pkg_drop || ! empty( $pkg_addons ) || $vehicle_image ) : ?>
                    <div class="tp-sect">
                        <div class="tp-sect-title">🚗 Transport &amp; Add-ons</div>
                        <div class="tp-veh-card">
                            <?php if ( $vehicle_image ) : ?>
                            <img src="<?php echo esc_url( $vehicle_image ); ?>" alt="Vehicle" class="tp-veh-img" loading="lazy">
                            <?php endif; ?>
                            <div class="tp-veh-rows">
                            <?php if ( ! empty( $pkg_vehicles ) ) : ?>
                            <div class="tp-veh-row">
                                <span class="tp-veh-icon">🚐</span>
                                <div>
                                    <div class="tp-veh-lbl">Vehicle</div>
                                    <div class="tp-veh-tags">
                                        <?php foreach ( $pkg_vehicles as $v ) : ?>
                                        <span class="tp-veh-tag"><?php echo esc_html( $v ); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ( $pkg_pickup || $pkg_drop ) : ?>
                            <div class="tp-veh-row">
                                <span class="tp-veh-icon">📍</span>
                                <div>
                                    <div class="tp-veh-lbl">Pickup &amp; Drop</div>
                                    <div class="tp-route">
                                        <?php if ( $pkg_pickup ) : ?><span class="tp-route-loc"><?php echo esc_html( $pkg_pickup ); ?></span><?php endif; ?>
                                        <?php if ( $pkg_pickup && $pkg_drop ) : ?><span style="color:#94a3b8;font-size:18px;">→</span><?php endif; ?>
                                        <?php if ( $pkg_drop   ) : ?><span class="tp-route-loc"><?php echo esc_html( $pkg_drop ); ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ( ! empty( $pkg_addons ) ) : ?>
                            <div class="tp-veh-row">
                                <span class="tp-veh-icon">✨</span>
                                <div>
                                    <div class="tp-veh-lbl">Trip Add-ons Included</div>
                                    <div class="tp-veh-tags">
                                        <?php foreach ( $pkg_addons as $addon ) : ?>
                                        <span class="tp-addon-tag"><?php echo esc_html( $addon ); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            </div><!-- .tp-veh-rows -->
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 3. GROUP TOUR SHORTCODE — desktop only (mobile copy is in main col) -->
                    <?php if ( $linked_tour_id ) : ?>
                    <div class="tp-group-desk" style="margin-bottom:16px;">
                        <?php echo do_shortcode( '[tcc_group_tour id="' . (int) $linked_tour_id . '"]' ); ?>
                    </div>
                    <?php endif; ?>

                    <!-- DESKTOP CTA (inside sidebar) -->
                    <div class="tp-cta" style="display:none;" id="tp-desk-cta-<?php echo (int) $pid; ?>">
                        <h3>Ready to Book? 🎒</h3>
                        <p>Share your dates — we'll personalise this package and give you the best price.</p>
                        <div class="tp-cta-btns">
                            <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tp-cta-wa">💬 WhatsApp Now</a>
                            <?php if ( $book_url ) : ?>
                            <a href="<?php echo esc_url( $book_url ); ?>" class="tp-cta-book">📋 View Dates</a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div><!-- .tp-sidebar -->

            <!-- ── MAIN: Itinerary + Hotels + Inc/Exc ─────────────────────── -->
            <div class="tp-main">

                <!-- DAY-WISE ITINERARY -->
                <?php if ( ! empty( $itinerary ) && array_filter( $itinerary ) ) : ?>
                <div class="tp-sect">
                    <div class="tp-sect-title">📅 Day-Wise Itinerary</div>
                    <?php foreach ( $itinerary as $idx => $day_title ) :
                        if ( ! trim( $day_title ) ) continue;
                        $d_img  = isset( $itinerary_img[ $idx ] )  ? $itinerary_img[ $idx ]  : '';
                        $d_desc = isset( $itinerary_desc[ $idx ] ) ? $itinerary_desc[ $idx ] : '';
                        $d_stay = isset( $stay_places[ $idx ] )    ? $stay_places[ $idx ]    : '';
                    ?>
                    <div class="tp-day<?php echo $idx === 0 ? ' open' : ''; ?>">
                        <div class="tp-day-hd" onclick="tpDay(this)">
                            <span class="tp-day-num">Day <?php echo esc_html( $idx + 1 ); ?></span>
                            <span class="tp-day-ttl"><?php echo esc_html( $day_title ); ?></span>
                            <span class="tp-day-arr">▾</span>
                        </div>
                        <div class="tp-day-body">
                            <?php if ( $d_img ) : ?>
                            <img src="<?php echo esc_url( $d_img ); ?>" alt="" class="tp-day-img" loading="lazy">
                            <?php endif; ?>
                            <?php if ( $d_desc ) : ?>
                            <div class="tp-day-desc"><?php echo wp_kses_post( $d_desc ); ?></div>
                            <?php endif; ?>
                            <?php if ( $d_stay && strtolower( trim( $d_stay ) ) !== 'trip ends' ) : ?>
                            <div class="tp-day-stay">🏨 Night Stay: <?php echo esc_html( $d_stay ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- HOTELS -->
                <?php if ( ! empty( $r_places ) && array_filter( $r_places ) ) : ?>
                <div class="tp-sect">
                    <div class="tp-sect-title">🏨 Hotels &amp; Accommodations</div>
                    <p style="font-size:12px;color:#64748b;margin-bottom:10px;">Hotels shown are representative — similar quality alternatives may be arranged.</p>
                    <div class="tp-hotel-list">
                    <?php foreach ( $r_places as $ri => $rplace ) :
                        if ( empty( $rplace ) || strtolower( trim( $rplace ) ) === 'trip ends' ) continue;
                        $rcat    = isset( $r_categories[ $ri ] ) ? $r_categories[ $ri ] : '';
                        $rnights = isset( $r_nights[ $ri ] )     ? $r_nights[ $ri ]     : 1;
                        $rhotel  = isset( $r_hotels[ $ri ] )     ? $r_hotels[ $ri ]     : '';
                        $rroom   = isset( $r_room_types[ $ri ] ) ? $r_room_types[ $ri ] : 'Default';
                        if ( $rroom === 'Default' ) $rroom = 'Deluxe Room';

                        // Category colour map
                        $cat_colors = array(
                            'deluxe'   => '#4f46e5',
                            'premium'  => '#0891b2',
                            'luxury'   => '#b45309',
                            'standard' => '#64748b',
                            'budget'   => '#6b7280',
                            'resort'   => '#065f46',
                        );
                        $cat_bg = '#4f46e5';
                        if ( $rcat ) {
                            foreach ( $cat_colors as $k => $v ) {
                                if ( stripos( $rcat, $k ) !== false ) { $cat_bg = $v; break; }
                            }
                        }

                        // Build hotel links
                        $harr   = array_filter( array_map( 'trim', explode( ',', $rhotel ) ) );
                        $hparts = array();
                        foreach ( $harr as $h ) {
                            if ( strpos( $h, '::' ) !== false ) {
                                list( $hn, $hl ) = explode( '::', $h, 2 );
                                $h_name = esc_html( trim( $hn ) );
                                $h_link = esc_url( trim( $hl ) );
                                $h_tip  = 'Visit Hotel Website';
                            } else {
                                $h_name = esc_html( trim( $h ) );
                                $h_link = esc_url( 'https://www.google.com/maps/search/' . rawurlencode( trim( $h ) . ( $destination ? ', ' . $destination : '' ) ) );
                                $h_tip  = 'Search on Google Maps';
                            }
                            $hparts[] = '<a href="' . $h_link . '" target="_blank" rel="noopener" title="' . esc_attr( $h_tip ) . '" style="color:#2563eb;font-weight:600;text-decoration:none;white-space:nowrap;">' . $h_name . ' <span style="font-size:10px;opacity:.55;">↗</span></a>';
                        }
                        $hhtml = ! empty( $hparts )
                            ? implode( ' <span style="color:#cbd5e1;">/ </span>', $hparts ) . ' <em style="color:#94a3b8;font-size:11px;font-weight:400;">/ Similar</em>'
                            : '<em style="color:#94a3b8;">Group Standard / Similar</em>';
                    ?>
                    <div class="tp-hotel-row">
                        <?php if ( $rcat ) : ?>
                        <div class="tp-hotel-cat-bar" style="background:<?php echo esc_attr( $cat_bg ); ?>;">
                            ★ <?php echo esc_html( $rcat ); ?> Category
                        </div>
                        <?php endif; ?>
                        <div class="tp-hotel-place">🏙️ <strong><?php echo esc_html( $rplace ); ?></strong></div>
                        <div class="tp-hotel-meta-row">
                            <span class="tp-hotel-nights-badge">🌙 <?php echo esc_html( $rnights ); ?> Night<?php echo $rnights > 1 ? 's' : ''; ?></span>
                            <span class="tp-hotel-room-type"><?php echo esc_html( $rroom ); ?></span>
                        </div>
                        <div class="tp-hotel-names"><?php echo $hhtml; ?></div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PRICE CARD — mobile only (shown above Package Details) -->
                <div class="tp-price-mob">
                <?php
                $display_price_m     = $linked_tour_price ? $linked_tour_price : $pkg_price_pp;
                $display_price_lbl_m = $linked_tour_price ? 'Triple Sharing · Per Person' : 'Per Person · Incl. GST';
                $display_badge_m     = $linked_tour_price ? 'Group Tour Price' : 'Best Price ✨';
                if ( $display_price_m ) :
                    $numeric_m = $linked_tour_price
                        ? (float) $linked_tour_price
                        : floatval( preg_replace( '/[^\d.]/', '', $display_price_m ) );
                ?>
                <div class="tp-price-card">
                    <div class="tp-price-badge"><?php echo esc_html( $display_badge_m ); ?></div>
                    <div class="tp-price-label">Starting From</div>
                    <div class="tp-price-val">₹<?php echo esc_html( number_format( $numeric_m, 0 ) ); ?></div>
                    <div class="tp-price-sub"><?php echo esc_html( $display_price_lbl_m ); ?><?php
                        if ( ! $linked_tour_price ) {
                            $bits2 = array();
                            if ( $pkg_adults >= 1 ) $bits2[] = esc_html( $pkg_adults ) . ' adults';
                            if ( $pkg_rooms  >= 1 ) $bits2[] = esc_html( $pkg_rooms  ) . ' room' . ( $pkg_rooms > 1 ? 's' : '' );
                            if ( $bits2 ) echo ' · Based on ' . implode( ', ', $bits2 );
                        }
                    ?></div>
                    <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tp-price-wa">💬 Get This Price on WhatsApp</a>
                </div>
                <?php endif; ?>
                </div>

                <!-- GROUP TOUR SHORTCODE — mobile only, right after price card -->
                <?php if ( $linked_tour_id ) : ?>
                <div class="tp-group-mob" style="margin-bottom:22px;">
                    <?php echo do_shortcode( '[tcc_group_tour id="' . (int) $linked_tour_id . '"]' ); ?>
                </div>
                <?php endif; ?>

                <!-- INCLUSIONS / EXCLUSIONS / PAYMENT TERMS -->
                <?php if ( $inclusions || $exclusions || $payment_terms ) : ?>
                <div class="tp-sect">
                    <div class="tp-sect-title">📋 Package Details</div>

                    <?php if ( $inclusions && $exclusions ) : ?>
                    <div class="tp-tabs">
                        <button class="tp-tab active" onclick="tpTab(this,'tp-inc-<?php echo (int)$pid;?>')">✅ Included</button>
                        <button class="tp-tab"        onclick="tpTab(this,'tp-exc-<?php echo (int)$pid;?>')">❌ Not Included</button>
                        <?php if ( $payment_terms ) : ?><button class="tp-tab" onclick="tpTab(this,'tp-pay-<?php echo (int)$pid;?>')">💳 Payment</button><?php endif; ?>
                    </div>
                    <div id="tp-inc-<?php echo (int)$pid;?>" class="tp-tab-panel active tp-inc-wrap">
                        <div class="tp-panel-ttl">✅ What's Included</div>
                        <?php echo wp_kses_post( $inclusions ); ?>
                    </div>
                    <div id="tp-exc-<?php echo (int)$pid;?>" class="tp-tab-panel tp-exc-wrap">
                        <div class="tp-panel-ttl">❌ Not Included</div>
                        <?php echo wp_kses_post( $exclusions ); ?>
                    </div>
                    <?php if ( $payment_terms ) : ?>
                    <div id="tp-pay-<?php echo (int)$pid;?>" class="tp-tab-panel tp-pay-wrap">
                        <div class="tp-panel-ttl">💳 Payment Terms</div>
                        <?php echo wp_kses_post( $payment_terms ); ?>
                    </div>
                    <?php endif; ?>

                    <?php else : ?>
                    <?php if ( $inclusions ) : ?><div class="tp-inc-wrap"><div class="tp-panel-ttl">✅ Included</div><?php echo wp_kses_post( $inclusions ); ?></div><?php endif; ?>
                    <?php if ( $exclusions ) : ?><div class="tp-exc-wrap" style="margin-top:10px;"><div class="tp-panel-ttl">❌ Not Included</div><?php echo wp_kses_post( $exclusions ); ?></div><?php endif; ?>
                    <?php if ( $payment_terms ) : ?><div class="tp-pay-wrap" style="margin-top:10px;"><div class="tp-panel-ttl">💳 Payment Terms</div><?php echo wp_kses_post( $payment_terms ); ?></div><?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- MOBILE FINAL CTA -->
                <div class="tp-cta" id="tp-mob-cta-<?php echo (int) $pid; ?>">
                    <h3>Ready to Book? 🎒</h3>
                    <p>Share your dates — we'll personalise this package and give you the best price.</p>
                    <div class="tp-cta-btns">
                        <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tp-cta-wa">💬 WhatsApp Us Now</a>
                        <?php if ( $book_url ) : ?>
                        <a href="<?php echo esc_url( $book_url ); ?>" class="tp-cta-book">📋 View Dates &amp; Book</a>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- .tp-main -->

        </div><!-- .tp-layout -->

    </div><!-- .tp-body -->

</div><!-- .tp -->

<!-- ══ RELATED PACKAGES — same grid card design, 3 col ══════════════════════ -->
<?php
$related = get_posts( array(
    'post_type'      => 'tcc_package',
    'post_status'    => 'publish',
    'posts_per_page' => 3,
    'post__not_in'   => array( $pid ),
    'orderby'        => 'rand',
    'meta_query'     => array( array(
        'key'   => '_tcc_pkg_destination',
        'value' => $destination,
    ) ),
) );
if ( count( $related ) < 3 ) {
    $extra = get_posts( array(
        'post_type'      => 'tcc_package',
        'post_status'    => 'publish',
        'posts_per_page' => 3 - count( $related ),
        'post__not_in'   => array_merge( array( $pid ), wp_list_pluck( $related, 'ID' ) ),
        'orderby'        => 'rand',
    ) );
    $related = array_merge( $related, $extra );
}
if ( ! empty( $related ) ) :
    self::inline_css( 3 ); // outputs grid card CSS (skipped if already done)
?>
<div style="padding:24px 16px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <div style="max-width:1100px;margin:0 auto;">
        <div style="font-size:15px;font-weight:800;color:#0f172a;border-left:3px solid #b93b59;padding-left:10px;margin-bottom:16px;">🗺️ You May Also Like</div>
        <div class="tcc-pkg-grid">
            <?php foreach ( $related as $rp ) self::render_card( $rp ); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ STICKY BOTTOM BAR — mobile only ══════════════════════════════════════ -->
<div class="tp-sticky">
    <?php
    $sticky_price = '';
    $sticky_note  = '';
    if ( $linked_tour_price > 0 ) {
        $sticky_price = '₹' . number_format( (float) $linked_tour_price, 0 );
        $sticky_note  = 'Triple Sharing / pp';
    } elseif ( $pkg_price_pp ) {
        $sticky_price = $pkg_price_pp;
        $sticky_note  = '/ person incl. GST';
    }
    ?>
    <div class="tp-sticky-price">
        <span class="tp-sticky-from"><?php echo $sticky_price ? 'From' : 'Get'; ?></span>
        <span class="tp-sticky-amt"><?php echo $sticky_price ? esc_html( $sticky_price ) : 'Best Price'; ?></span>
        <span class="tp-sticky-note"><?php echo $sticky_price ? esc_html( $sticky_note ) : 'Personalised quote'; ?></span>
    </div>
    <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="tp-sticky-wa">💬 Enquire Now</a>
</div>

<!-- ══ JS: accordion, tabs, desktop CTA show ═════════════════════════════════ -->
<script>
function tpDay(hd){ hd.parentElement.classList.toggle('open'); }
function tpTab(btn, id){
    var w = btn.closest('.tp-sect') || btn.parentElement.parentElement;
    w.querySelectorAll('.tp-tab').forEach(function(t){ t.classList.remove('active'); });
    w.querySelectorAll('.tp-tab-panel').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active');
    var p = document.getElementById(id); if(p) p.classList.add('active');
}
// Show desktop CTA in sidebar on wide screens
(function(){
    function toggleDesktopCTA(){
        var el = document.getElementById('tp-desk-cta-<?php echo (int)$pid;?>');
        var mob = document.getElementById('tp-mob-cta-<?php echo (int)$pid;?>');
        if(!el || !mob) return;
        if(window.innerWidth >= 900){
            el.style.display='block'; mob.style.display='none';
        } else {
            el.style.display='none'; mob.style.display='block';
        }
    }
    toggleDesktopCTA();
    window.addEventListener('resize', toggleDesktopCTA);
})();
</script>
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

        // ── Package display extras (saved by tcc_save_preset_extras) ────────────
        $pkg_adults     = (int)( isset( $p['pkg_adults'] )     ? $p['pkg_adults']     : 0 );
        $pkg_rooms      = (int)( isset( $p['pkg_rooms'] )      ? $p['pkg_rooms']      : 0 );
        $pkg_extra_beds = (int)( isset( $p['pkg_extra_beds'] ) ? $p['pkg_extra_beds'] : 0 );
        $pkg_price_pp   = isset( $p['pkg_price_pp'] )          ? sanitize_text_field( $p['pkg_price_pp'] ) : '';
        $pkg_vehicles   = array_values( array_filter( (array)( isset( $p['pkg_vehicles'] ) ? $p['pkg_vehicles'] : array() ) ) );
        $pkg_addons     = array_values( array_filter( (array)( isset( $p['pkg_addons'] )   ? $p['pkg_addons']   : array() ) ) );
        $pkg_pickup     = isset( $p['pkg_pickup'] ) ? sanitize_text_field( $p['pkg_pickup'] ) : '';
        $pkg_drop       = isset( $p['pkg_drop'] )   ? sanitize_text_field( $p['pkg_drop'] )   : '';
        // Direct POST values (sent at publish time) override preset-stored values
        $post_pickup = sanitize_text_field( wp_unslash( isset( $_POST['pickup'] ) ? $_POST['pickup'] : '' ) );
        $post_drop   = sanitize_text_field( wp_unslash( isset( $_POST['drop'] )   ? $_POST['drop']   : '' ) );
        if ( $post_pickup ) $pkg_pickup = $post_pickup;
        if ( $post_drop )   $pkg_drop   = $post_drop;

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
            '_tcc_pkg_vehicles'       => wp_json_encode( $pkg_vehicles ),
            '_tcc_pkg_addons'         => wp_json_encode( $pkg_addons ),
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
            '_tcc_pkg_adults'      => $pkg_adults,
            '_tcc_pkg_rooms'       => $pkg_rooms,
            '_tcc_pkg_extra_beds'  => $pkg_extra_beds,
            '_tcc_pkg_price_pp'    => $pkg_price_pp,
            '_tcc_pkg_pickup'      => $pkg_pickup,
            '_tcc_pkg_drop'        => $pkg_drop,
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
            $thumb = has_post_thumbnail( $p->ID )
                ? get_the_post_thumbnail_url( $p->ID, 'thumbnail' )
                : '';
            // Fallback to first itinerary image if no featured image
            if ( ! $thumb ) {
                $iimgs = json_decode( get_post_meta( $p->ID, '_tcc_pkg_itinerary_img', true ), true );
                if ( is_array( $iimgs ) ) {
                    foreach ( $iimgs as $u ) { if ( $u ) { $thumb = $u; break; } }
                }
            }
            $linked_tour_id = (int) get_post_meta( $p->ID, '_tcc_pkg_linked_tour', true );
            $vehicle_img    = '';
            if ( $linked_tour_id ) {
                $vehicle_img = get_post_meta( $linked_tour_id, '_tcc_vehicle_image', true );
            }
            $data[] = array(
                'id'          => $p->ID,
                'title'       => $p->post_title,
                'status'      => $p->post_status,
                'destination' => get_post_meta( $p->ID, '_tcc_pkg_destination', true ),
                'days'        => (int) get_post_meta( $p->ID, '_tcc_pkg_days', true ),
                'preset_name' => get_post_meta( $p->ID, '_tcc_pkg_preset_name', true ),
                'permalink'   => get_permalink( $p->ID ),
                'linked_tour' => $linked_tour_id,
                'vehicle_img' => $vehicle_img,
                'thumb'       => $thumb,
            );
        }
        wp_send_json_success( $data );
    }


    // ─── AJAX: Set / update package featured image ───────────────────────────
    public static function ajax_set_package_image() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();

        $post_id = intval( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
        $att_id  = intval( isset( $_POST['att_id'] )  ? $_POST['att_id']  : 0 );

        if ( ! $post_id || get_post_type( $post_id ) !== 'tcc_package' ) {
            wp_send_json_error( 'Invalid package.' );
        }
        if ( ! $att_id || ! wp_attachment_is_image( $att_id ) ) {
            wp_send_json_error( 'Invalid image.' );
        }

        set_post_thumbnail( $post_id, $att_id );
        $thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
        wp_send_json_success( array( 'thumb' => $thumb_url ) );
    }


    // ─── Helper: collect ALL triple prices recursively from departure arrays ────
    private static function collect_triple_prices( $data, &$prices ) {
        if ( ! is_array( $data ) ) return;
        foreach ( $data as $key => $value ) {
            if ( is_string( $key ) && stripos( $key, 'triple' ) !== false
                 && is_numeric( $value ) && floatval( $value ) > 0 ) {
                $prices[] = floatval( $value );
            }
            if ( is_array( $value ) ) {
                self::collect_triple_prices( $value, $prices );
            }
        }
    }

    // ─── Helper: scan departure arrays for days count ─────────────────────────
    // Departure data is stored as nested arrays. We look for a 'days' key and
    // return the most common (mode) value across all departures.
    private static function collect_days( $data, &$days_arr ) {
        if ( ! is_array( $data ) ) return;
        foreach ( $data as $key => $value ) {
            if ( is_string( $key ) && strtolower( $key ) === 'days'
                 && is_numeric( $value ) && intval( $value ) > 0 ) {
                $days_arr[] = intval( $value );
            }
            if ( is_array( $value ) ) {
                self::collect_days( $value, $days_arr );
            }
        }
    }

    private static function get_tour_days( $post_id ) {
        // Method 1: read days from departure array (_tcc_fd_dates, each item has a 'days' key)
        $raw = get_post_meta( $post_id, '_tcc_fd_dates', true );
        $dates = is_array( $raw ) ? $raw : ( json_decode( $raw, true ) ?: array() );
        if ( ! empty( $dates ) ) {
            $days_arr = array();
            foreach ( $dates as $dep ) {
                if ( isset( $dep['days'] ) && intval( $dep['days'] ) > 0 ) {
                    $days_arr[] = intval( $dep['days'] );
                }
            }
            if ( ! empty( $days_arr ) ) {
                $counts = array_count_values( $days_arr );
                arsort( $counts );
                return (int) array_key_first( $counts );
            }
        }
        // Method 2: count itinerary rows from _tcc_fd_itinerary_data
        $raw2 = get_post_meta( $post_id, '_tcc_fd_itinerary_data', true );
        $itin = is_array( $raw2 ) ? $raw2 : ( json_decode( $raw2, true ) ?: array() );
        if ( ! empty( $itin['itinerary'] ) && is_array( $itin['itinerary'] ) ) {
            $n = count( array_filter( $itin['itinerary'] ) );
            if ( $n > 0 ) return $n;
        }
        return 0;
    }

    // ─── Helper: return MINIMUM triple price across all departures ────────────
    private static function get_tour_triple_price( $post_id ) {
        $all_meta = get_post_meta( $post_id );
        $prices   = array();
        foreach ( $all_meta as $meta_key => $meta_values ) {
            $raw = $meta_values[0];
            $val = maybe_unserialize( $raw );
            if ( is_string( $val ) ) {
                $json = json_decode( $val, true );
                if ( is_array( $json ) ) $val = $json;
            }
            // Direct key containing "triple"
            if ( stripos( $meta_key, 'triple' ) !== false && ! is_array( $val ) ) {
                $n = floatval( preg_replace( '/[^\d.]/', '', (string) $val ) );
                if ( $n > 0 ) $prices[] = $n;
            }
            // Nested departure arrays
            if ( is_array( $val ) ) {
                self::collect_triple_prices( $val, $prices );
            }
        }
        return ! empty( $prices ) ? min( $prices ) : 0;
    }

    // ─── Helper: get Vehicle / Pickup / Drop from Logistics & Policies ────────
    // Uses exact meta keys saved by tcc-script.js (discovered via tcc_dump_tour_meta).
    private static function get_tour_logistics( $post_id ) {
        return array(
            'vehicle' => (string) get_post_meta( $post_id, '_tcc_transport_details', true ),
            'pickup'  => (string) get_post_meta( $post_id, '_tcc_pickup',            true ),
            'drop'    => (string) get_post_meta( $post_id, '_tcc_drop',              true ),
        );
    }

    // ─── AJAX: Group tours for link dropdown ─────────────────────────────────
    public static function ajax_tours_for_link() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();
        $tours = get_posts( array( 'post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
        $data  = array();
        foreach ( $tours as $t ) {
            $triple_price = self::get_tour_triple_price( $t->ID );
            $shortcode    = 'tcc_group_tour id="' . $t->ID . '"';
            $data[] = array(
                'id'           => $t->ID,
                'title'        => $t->post_title,
                'shortcode'    => $shortcode,
                'triple_price' => $triple_price > 0 ? $triple_price : '',
            );
        }
        wp_send_json_success( $data );
    }

    // ─── Helper: get vehicle image from fixed tour ────────────────────────────
    private static function get_tour_vehicle_image( $tour_id ) {
        return $tour_id ? (string) get_post_meta( $tour_id, '_tcc_vehicle_image', true ) : '';
    }

    // ─── Helper: get Package Content from group tour meta ────────────────────
    // ALL meta keys confirmed via tcc_dump_tour_meta diagnostic:
    //   _tcc_inclusions / _tcc_exclusions / _tcc_payment_terms  → Package Details
    //   _tcc_fd_itinerary_data  → JSON blob with itinerary, hotels, stay places
    //   _tcc_tour_addons        → our new per-tour add-ons (set via accordion 6)
    //   _tcc_tour_inclusions / _tcc_tour_exclusions / _tcc_tour_payment_terms
    //       → per-tour overrides (set via accordion 6; take priority over the keys above)
    private static function get_tour_content( $tour_id ) {
        if ( ! $tour_id ) return array();

        // ── Itinerary data (set by tcc-script.js, stored as one JSON blob) ──
        $raw = get_post_meta( $tour_id, '_tcc_fd_itinerary_data', true );
        $itin_data = is_array( $raw ) ? $raw : ( json_decode( $raw, true ) ?: array() );

        $routing = array(
            'places'     => isset( $itin_data['routing_stay_places'] )     ? (array) $itin_data['routing_stay_places']     : array(),
            'categories' => isset( $itin_data['routing_stay_categories'] ) ? (array) $itin_data['routing_stay_categories'] : array(),
            'nights'     => isset( $itin_data['routing_stay_nights'] )     ? (array) $itin_data['routing_stay_nights']     : array(),
            'hotels'     => isset( $itin_data['routing_stay_hotels'] )     ? (array) $itin_data['routing_stay_hotels']     : array(),
            'room_types' => isset( $itin_data['routing_stay_room_types'] ) ? (array) $itin_data['routing_stay_room_types'] : array(),
        );

        // ── Package Details: accordion-6 override takes priority, else direct key ──
        $inclusions   = get_post_meta( $tour_id, '_tcc_tour_inclusions',   true )
                     ?: get_post_meta( $tour_id, '_tcc_inclusions',        true );
        $exclusions   = get_post_meta( $tour_id, '_tcc_tour_exclusions',   true )
                     ?: get_post_meta( $tour_id, '_tcc_exclusions',        true );
        $payment_terms = get_post_meta( $tour_id, '_tcc_tour_payment_terms', true )
                     ?: get_post_meta( $tour_id, '_tcc_payment_terms',     true );

        return array(
            'addons'         => json_decode( get_post_meta( $tour_id, '_tcc_tour_addons', true ), true ) ?: array(),
            'inclusions'     => (string) $inclusions,
            'exclusions'     => (string) $exclusions,
            'payment_terms'  => (string) $payment_terms,
            // Itinerary: note tcc-script.js uses 'itinerary_image' (not 'itinerary_img')
            'itinerary'      => isset( $itin_data['itinerary'] )      ? (array) $itin_data['itinerary']      : array(),
            'itinerary_desc' => isset( $itin_data['itinerary_desc'] ) ? (array) $itin_data['itinerary_desc'] : array(),
            'itinerary_img'  => isset( $itin_data['itinerary_image'] ) ? (array) $itin_data['itinerary_image'] : array(),
            'stay_places'    => isset( $itin_data['stay_places'] )    ? (array) $itin_data['stay_places']    : array(),
            'routing'        => $routing,
        );
    }

    // ─── AJAX: Save Group Tour Package Content ────────────────────────────────
    // Saves: add-ons, inclusions, exclusions, payment terms to the tcc_fixed_tour.
    // All linked package pages & grid cards auto-update on next load — no sync needed.
    public static function ajax_save_tour_content() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();

        $tour_id = intval( isset( $_POST['tour_id'] ) ? $_POST['tour_id'] : 0 );
        if ( ! $tour_id || get_post_type( $tour_id ) !== 'tcc_fixed_tour' ) {
            wp_send_json_error( 'Invalid tour.' );
        }

        // Add-ons: comma-separated string → JSON array
        $addons_raw = sanitize_text_field( wp_unslash( isset( $_POST['addons'] ) ? $_POST['addons'] : '' ) );
        $addon_arr  = array_values( array_filter( array_map( 'trim', explode( ',', $addons_raw ) ) ) );
        update_post_meta( $tour_id, '_tcc_tour_addons', wp_json_encode( $addon_arr ) );

        // Inclusions / Exclusions / Payment Terms (allow HTML)
        $allowed = wp_kses_allowed_html( 'post' );
        update_post_meta( $tour_id, '_tcc_tour_inclusions',
            wp_kses( wp_unslash( isset( $_POST['inclusions'] )   ? $_POST['inclusions']   : '' ), $allowed ) );
        update_post_meta( $tour_id, '_tcc_tour_exclusions',
            wp_kses( wp_unslash( isset( $_POST['exclusions'] )   ? $_POST['exclusions']   : '' ), $allowed ) );
        update_post_meta( $tour_id, '_tcc_tour_payment_terms',
            wp_kses( wp_unslash( isset( $_POST['payment_terms'] )? $_POST['payment_terms'] : '' ), $allowed ) );

        wp_send_json_success( array(
            'message' => 'Package content saved! All linked package pages will auto-update instantly.',
        ) );
    }

    // ─── AJAX: Get Group Tour Package Content ─────────────────────────────────
    // Returns effective values: accordion-6 override if set, otherwise the
    // direct keys saved by tcc-script.js (_tcc_inclusions etc.)
    public static function ajax_get_tour_content() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();

        $tour_id = intval( isset( $_POST['tour_id'] ) ? $_POST['tour_id'] : 0 );
        if ( ! $tour_id ) wp_send_json_error( 'Invalid tour.' );

        $tc = self::get_tour_content( $tour_id );
        $addons = json_decode( get_post_meta( $tour_id, '_tcc_tour_addons', true ), true ) ?: array();

        wp_send_json_success( array(
            'addons'        => implode( ', ', $addons ),
            'inclusions'    => $tc['inclusions'],    // accordion-6 override OR _tcc_inclusions
            'exclusions'    => $tc['exclusions'],    // accordion-6 override OR _tcc_exclusions
            'payment_terms' => $tc['payment_terms'], // accordion-6 override OR _tcc_payment_terms
        ) );
    }

    // ─── AJAX: Save Day-Wise Itinerary to group tour ──────────────────────────

    // ─── AJAX: Get Day-Wise Itinerary from group tour ─────────────────────────

    // ─── AJAX: Save Hotels & Routing to group tour ────────────────────────────

    // ─── AJAX: Get Hotels from group tour ─────────────────────────────────────

    // ─── AJAX: Diagnostic — dump all meta keys from a tcc_fixed_tour post ────
    // This reveals exactly what meta keys tcc-script.js saves so we can read
    // the Day-wise Itinerary, Hotels, and other data directly from the manager.

    // ─── AJAX: Save vehicle image for a fixed tour ───────────────────────────
    public static function ajax_set_vehicle_image() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();

        $tour_id = intval( isset( $_POST['tour_id'] ) ? $_POST['tour_id'] : 0 );
        $att_id  = intval( isset( $_POST['att_id'] )  ? $_POST['att_id']  : 0 );

        if ( ! $tour_id || get_post_type( $tour_id ) !== 'tcc_fixed_tour' ) {
            wp_send_json_error( 'Invalid tour.' );
        }
        if ( ! $att_id || ! wp_attachment_is_image( $att_id ) ) {
            wp_send_json_error( 'Invalid image.' );
        }

        $img_url = wp_get_attachment_url( $att_id );
        update_post_meta( $tour_id, '_tcc_vehicle_image', esc_url_raw( $img_url ) );
        wp_send_json_success( array( 'img' => $img_url ) );
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

    // ─── AJAX: Save extra package display fields into the preset ─────────────
    // Called silently after "Save as Preset" to attach Adults / Rooms /
    // Extra Beds / PP Price / Vehicles / Add-ons to the preset entry.
    // Rules: skip (don't overwrite) any field whose value is < 1 or empty.
    public static function ajax_save_preset_extras() {
        check_ajax_referer( 'tcc_secure_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_die();

        $destination = sanitize_text_field( wp_unslash( isset( $_POST['destination'] ) ? $_POST['destination'] : '' ) );
        $preset_name = sanitize_text_field( wp_unslash( isset( $_POST['preset_name'] ) ? $_POST['preset_name'] : '' ) );

        $all_presets = get_option( 'tcc_itinerary_presets', array() );
        if ( empty( $all_presets[ $destination ][ $preset_name ] ) ) {
            wp_send_json_error( 'Preset not found.' );
        }

        // Work on a reference so we can update in-place
        $p = &$all_presets[ $destination ][ $preset_name ];

        // Adults (>12yr) — skip if < 1
        $adults = intval( isset( $_POST['adults'] ) ? $_POST['adults'] : 0 );
        if ( $adults >= 1 ) {
            $p['pkg_adults'] = $adults;
        }

        // Rooms — skip if < 1
        $rooms = intval( isset( $_POST['rooms'] ) ? $_POST['rooms'] : 0 );
        if ( $rooms >= 1 ) {
            $p['pkg_rooms'] = $rooms;
        }

        // Extra Beds — skip if < 1; clear stored value if now 0
        $extra_beds = intval( isset( $_POST['extra_beds'] ) ? $_POST['extra_beds'] : 0 );
        if ( $extra_beds >= 1 ) {
            $p['pkg_extra_beds'] = $extra_beds;
        } else {
            unset( $p['pkg_extra_beds'] );
        }

        // PP Price (Inc GST) — skip if empty or zero
        $price_pp_raw = sanitize_text_field( wp_unslash( isset( $_POST['price_pp'] ) ? $_POST['price_pp'] : '' ) );
        $price_numeric = floatval( preg_replace( '/[^\d.]+/', '', $price_pp_raw ) );
        if ( $price_numeric > 0 ) {
            $p['pkg_price_pp'] = $price_pp_raw; // store with ₹ symbol intact
        }

        // Vehicles — JSON array of name strings; skip if empty
        $vehicles_json = wp_unslash( isset( $_POST['vehicles'] ) ? $_POST['vehicles'] : '[]' );
        $vehicles = json_decode( $vehicles_json, true );
        if ( is_array( $vehicles ) ) {
            $vehicles = array_values( array_filter( array_map( 'sanitize_text_field', $vehicles ) ) );
            if ( ! empty( $vehicles ) ) {
                $p['pkg_vehicles'] = $vehicles;
            } else {
                unset( $p['pkg_vehicles'] );
            }
        }

        // Add-ons — JSON array of name strings; skip if empty
        $addons_json = wp_unslash( isset( $_POST['addons'] ) ? $_POST['addons'] : '[]' );
        $addons = json_decode( $addons_json, true );
        if ( is_array( $addons ) ) {
            $addons = array_values( array_filter( array_map( 'sanitize_text_field', $addons ) ) );
            if ( ! empty( $addons ) ) {
                $p['pkg_addons'] = $addons;
            } else {
                unset( $p['pkg_addons'] );
            }
        }

        // Pickup location — skip if empty
        $pickup = sanitize_text_field( wp_unslash( isset( $_POST['pickup'] ) ? $_POST['pickup'] : '' ) );
        if ( $pickup ) {
            $p['pkg_pickup'] = $pickup;
        }

        // Drop location — skip if empty
        $drop = sanitize_text_field( wp_unslash( isset( $_POST['drop'] ) ? $_POST['drop'] : '' ) );
        if ( $drop ) {
            $p['pkg_drop'] = $drop;
        }

        update_option( 'tcc_itinerary_presets', $all_presets );
        wp_send_json_success( 'Preset extras saved.' );
    }

    // ─── Shortcode [tcc_packages] ────────────────────────────────────────────
    public static function shortcode_grid( $atts ) {
        $atts = shortcode_atts( array(
            'destination' => '',
            'cols'        => '3',
            'limit'       => '-1',
        ), $atts );

        $query_args = array(
            'post_type'      => 'tcc_package',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
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
            return '<p style="text-align:center;padding:40px;color:#64748b;font-family:sans-serif;">No packages available yet.</p>';
        }

        $cols = max( 1, min( 4, intval( $atts['cols'] ) ) );

        // Collect unique filter values from all packages
        $dests    = array();
        $has_days = array( '1-4' => false, '5-7' => false, '8+' => false );
        $has_group  = false;
        $has_custom = false;

        foreach ( $pkgs as $p ) {
            $d = get_post_meta( $p->ID, '_tcc_pkg_destination', true );
            if ( $d ) $dests[ $d ] = true;
            $days = (int) get_post_meta( $p->ID, '_tcc_pkg_days', true );
            if ( $days >= 1 && $days <= 4  ) $has_days['1-4'] = true;
            if ( $days >= 5 && $days <= 7  ) $has_days['5-7'] = true;
            if ( $days >= 8                ) $has_days['8+']  = true;
            // Type detection
            if ( get_post_meta( $p->ID, '_tcc_pkg_linked_tour', true ) ) {
                $has_group = true;
            } else {
                $has_custom = true;
            }
        }
        ksort( $dests );
        $dests = array_keys( $dests );

        $gid = 'tpg-' . substr( md5( uniqid() ), 0, 8 );

        ob_start();
        self::inline_css( $cols );
        ?>

<div class="tcc-fw" id="<?php echo esc_attr( $gid ); ?>-wrap">

    <!-- ── FILTER TOGGLE — mobile only ── -->
    <div class="tcc-ftoggle-wrap">
        <button class="tcc-ftoggle" id="<?php echo esc_attr( $gid ); ?>-ftbtn">
            <span class="tcc-ftoggle-icon">⚙️</span>
            <span class="tcc-ftoggle-label">Filter &amp; Sort</span>
            <span class="tcc-ftoggle-badge" style="display:none;"></span>
            <span class="tcc-ftoggle-arr">▾</span>
        </button>
    </div>

    <!-- ── FILTER BAR ── -->
    <div class="tcc-fb" id="<?php echo esc_attr( $gid ); ?>-fb">

        <?php if ( count( $dests ) > 1 ) : ?>
        <div class="tcc-frow">
            <span class="tcc-flbl">📍 Destination</span>
            <div class="tcc-fchips" data-fk="dest">
                <button class="tcc-fc active" data-v="all">All</button>
                <?php foreach ( $dests as $dest ) : ?>
                <button class="tcc-fc" data-v="<?php echo esc_attr( strtolower( $dest ) ); ?>"><?php echo esc_html( $dest ); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( array_filter( $has_days ) ) : ?>
        <div class="tcc-frow">
            <span class="tcc-flbl">🌅 Duration</span>
            <div class="tcc-fchips" data-fk="days">
                <button class="tcc-fc active" data-v="all">Any</button>
                <?php if ( $has_days['1-4'] ) : ?><button class="tcc-fc" data-v="1-4">1–4 Days</button><?php endif; ?>
                <?php if ( $has_days['5-7'] ) : ?><button class="tcc-fc" data-v="5-7">5–7 Days</button><?php endif; ?>
                <?php if ( $has_days['8+']  ) : ?><button class="tcc-fc" data-v="8+">8+ Days</button><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $has_group && $has_custom ) : ?>
        <div class="tcc-frow">
            <span class="tcc-flbl">🏷️ Tour Type</span>
            <div class="tcc-fchips" data-fk="type">
                <button class="tcc-fc active" data-v="all">All</button>
                <button class="tcc-fc" data-v="group">👥 Group Tour</button>
                <button class="tcc-fc" data-v="custom">✈️ Custom Package</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="tcc-frow tcc-sort-row">
            <span class="tcc-flbl">⬆️ Sort By</span>
            <select class="tcc-sort-sel">
                <option value="default">Recommended</option>
                <option value="price-asc">Price: Low → High</option>
                <option value="price-desc">Price: High → Low</option>
                <option value="days-asc">Duration: Short → Long</option>
                <option value="days-desc">Duration: Long → Short</option>
            </select>
        </div>

    </div><!-- .tcc-fb -->

    <!-- ── META ROW: count + clear ── -->
    <div class="tcc-fmeta">
        <span class="tcc-rcnt"></span>
        <button class="tcc-clr" style="display:none;">✕ Clear Filters</button>
    </div>

    <!-- ── GRID ── -->
    <div class="tcc-pkg-grid" id="<?php echo esc_attr( $gid ); ?>">
        <?php foreach ( $pkgs as $pkg ) self::render_card( $pkg ); ?>
    </div>

    <!-- ── NO RESULTS ── -->
    <div class="tcc-nores" style="display:none;">
        <div style="font-size:40px;margin-bottom:12px;">🔍</div>
        <div style="font-size:17px;font-weight:800;color:#0f172a;margin-bottom:6px;">No packages found</div>
        <div style="font-size:13px;color:#64748b;margin-bottom:18px;">Try adjusting your filters</div>
        <button class="tcc-clr tcc-clr-lg">Clear All Filters</button>
    </div>

</div><!-- .tcc-fw -->

<script>
(function(){
    var wrap = document.getElementById(<?php echo json_encode( $gid . '-wrap' ); ?>);
    if (!wrap) return;
    var grid     = document.getElementById(<?php echo json_encode( $gid ); ?>);
    var fb       = document.getElementById(<?php echo json_encode( $gid . '-fb' ); ?>);
    var ftBtn    = document.getElementById(<?php echo json_encode( $gid . '-ftbtn' ); ?>);
    var cards    = Array.from(grid.querySelectorAll('.tcc-pkg-card'));
    var noRes    = wrap.querySelector('.tcc-nores');
    var rcnt     = wrap.querySelector('.tcc-rcnt');
    var sortSel  = wrap.querySelector('.tcc-sort-sel');
    var clrBtns  = wrap.querySelectorAll('.tcc-clr');
    var badge    = ftBtn ? ftBtn.querySelector('.tcc-ftoggle-badge') : null;

    /* Mobile filter toggle */
    if (ftBtn && fb) {
        ftBtn.addEventListener('click', function(){
            var open = fb.classList.toggle('tcc-fb-open');
            ftBtn.classList.toggle('open', open);
        });
    }

    var fState   = { dest: 'all', days: 'all', type: 'all' };
    var sState   = 'default';

    cards.forEach(function(c, i){ c._oi = i; });

    function cDays(c) { return parseInt(c.dataset.days) || 0; }
    function cPrice(c){ return parseInt(c.dataset.price)|| 0; }
    function cDest(c) { return (c.dataset.dest || '').toLowerCase(); }

    function matches(c){
        if (fState.dest !== 'all' && cDest(c) !== fState.dest) return false;
        if (fState.days !== 'all'){
            var d = cDays(c);
            if (fState.days === '1-4' && (d < 1 || d > 4)) return false;
            if (fState.days === '5-7' && (d < 5 || d > 7)) return false;
            if (fState.days === '8+'  && d < 8)             return false;
        }
        if (fState.type !== 'all' && (c.dataset.type || 'custom') !== fState.type) return false;
        return true;
    }

    function run(){
        var vis = [], hid = [];
        cards.forEach(function(c){ (matches(c) ? vis : hid).push(c); });

        /* sort visible */
        vis.sort(function(a,b){
            if (sState === 'price-asc')  return cPrice(a) - cPrice(b);
            if (sState === 'price-desc') return cPrice(b) - cPrice(a);
            if (sState === 'days-asc')   return cDays(a)  - cDays(b);
            if (sState === 'days-desc')  return cDays(b)  - cDays(a);
            return a._oi - b._oi;
        });

        /* fade-out hidden */
        hid.forEach(function(c){
            c.style.opacity   = '0';
            c.style.transform = 'scale(.94) translateY(10px)';
            clearTimeout(c._ht);
            c._ht = setTimeout(function(){ c.style.display = 'none'; }, 260);
        });

        /* reorder + fade-in visible */
        vis.forEach(function(c, i){
            clearTimeout(c._ht);
            if (c.style.display === 'none'){
                c.style.opacity   = '0';
                c.style.transform = 'scale(.94) translateY(10px)';
            }
            c.style.display = '';
            grid.appendChild(c);
            setTimeout(function(){
                c.style.opacity   = '1';
                c.style.transform = 'scale(1) translateY(0)';
            }, 30 + i * 25);
        });

        var n = vis.length, t = cards.length;
        rcnt.textContent = n === t
            ? n + ' package' + (n !== 1 ? 's' : '')
            : n + ' of ' + t + ' package' + (t !== 1 ? 's' : '');

        grid.style.display  = n === 0 ? 'none'  : '';
        noRes.style.display = n === 0 ? 'block' : 'none';

        var active = fState.dest !== 'all' || fState.days !== 'all' || fState.type !== 'all';
        clrBtns.forEach(function(b){ b.style.display = active ? '' : 'none'; });
        /* Update mobile badge count */
        if (badge) {
            var cnt = (fState.dest !== 'all' ? 1 : 0) + (fState.days !== 'all' ? 1 : 0) + (fState.type !== 'all' ? 1 : 0) + (sState !== 'default' ? 1 : 0);
            badge.textContent  = cnt;
            badge.style.display = cnt > 0 ? '' : 'none';
        }
    }

    /* filter chip clicks */
    wrap.querySelectorAll('.tcc-fchips').forEach(function(grp){
        var fk = grp.dataset.fk;
        grp.querySelectorAll('.tcc-fc').forEach(function(btn){
            btn.addEventListener('click', function(){
                grp.querySelectorAll('.tcc-fc').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                fState[fk] = btn.dataset.v;
                run();
            });
        });
    });

    /* sort */
    if (sortSel) sortSel.addEventListener('change', function(){ sState = this.value; run(); });

    /* clear */
    clrBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            fState = { dest:'all', days:'all', type:'all' };
            sState = 'default';
            if (sortSel) sortSel.value = 'default';
            wrap.querySelectorAll('.tcc-fchips .tcc-fc').forEach(function(b){
                b.classList.toggle('active', b.dataset.v === 'all');
            });
            run();
        });
    });

    run(); /* initial count */
})();
</script>

        <?php
        return ob_get_clean();
    }

    // ─── Grid CSS (once per page) ────────────────────────────────────────────
    private static function inline_css( $cols ) {
        static $done = false;
        if ( $done ) return;
        $done = true;
        $c = (int) $cols;
        echo '<style id="tcc-pkg-css">
.tcc-fw{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
/* ── Filter Toggle Button (mobile only) ── */
.tcc-ftoggle-wrap{display:none;margin-bottom:12px}
.tcc-ftoggle{display:flex;align-items:center;gap:8px;width:100%;background:#0f172a;border:2px solid #0f172a;border-radius:12px;padding:12px 16px;font-size:13px;font-weight:700;color:#fff!important;cursor:pointer;font-family:inherit;box-shadow:0 3px 10px rgba(0,0,0,.18);transition:all .2s}
.tcc-ftoggle:hover{background:#1e293b;border-color:#1e293b}
.tcc-ftoggle-icon{font-size:16px}
.tcc-ftoggle-label{flex:1;text-align:left;color:#fff!important}
.tcc-ftoggle-badge{background:#fbbf24;color:#0f172a!important;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;min-width:18px;text-align:center}
.tcc-ftoggle-arr{font-size:12px;color:rgba(255,255,255,.6);transition:transform .2s}
.tcc-ftoggle.open .tcc-ftoggle-arr{transform:rotate(180deg)}
.tcc-ftoggle.open{background:#b93b59;border-color:#9d314b}
/* ── Filter Bar ── */
.tcc-fb{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;margin-bottom:18px;display:flex;flex-direction:column;gap:12px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.tcc-frow{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.tcc-flbl{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.8px;white-space:nowrap;min-width:90px;flex-shrink:0}
.tcc-fchips{display:flex;gap:6px;flex-wrap:wrap;flex:1}
/* Inactive chip: white bg, dark text, clear border */
.tcc-fc{background:#fff;color:#1e293b!important;border:1.5px solid #cbd5e1;border-radius:20px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap;line-height:1.2;font-family:inherit}
.tcc-fc:hover{background:#fdf2f5;border-color:#f9a8be;color:#b93b59!important}
/* Active chip: strong crimson bg, pure white text — !important beats theme */
.tcc-fc.active{background:#b93b59!important;color:#fff!important;border-color:#9d314b!important;font-weight:800;box-shadow:0 2px 8px rgba(185,59,89,.35)}
.tcc-sort-row{border-top:1px solid #f1f5f9;padding-top:12px}
/* Sort select: dark text on white, visible focus ring */
.tcc-sort-sel{border:1.5px solid #cbd5e1;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;color:#1e293b!important;cursor:pointer;background:#fff;outline:none;min-width:200px;font-family:inherit;transition:border-color .15s}
.tcc-sort-sel:focus,.tcc-sort-sel:hover{border-color:#b93b59}
.tcc-fmeta{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;min-height:22px}
.tcc-rcnt{font-size:13px;font-weight:700;color:#64748b}
.tcc-clr{background:none;border:1px solid #e2e8f0;border-radius:20px;padding:4px 14px;font-size:12px;font-weight:600;color:#475569;cursor:pointer;transition:all .15s;font-family:inherit}
.tcc-clr:hover{background:#fef2f2;border-color:#fecaca;color:#b93b59}
.tcc-clr-lg{background:#b93b59;color:#fff!important;border:none;border-radius:9px;padding:11px 24px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit}
.tcc-nores{text-align:center;padding:50px 20px;font-family:inherit}
/* ── Grid ── */
.tcc-pkg-grid{display:grid;grid-template-columns:repeat(' . $c . ',1fr);gap:20px;padding:4px 0;max-width:100%}
@media(max-width:900px){.tcc-pkg-grid{grid-template-columns:repeat(2,1fr);gap:14px}}
@media(max-width:540px){.tcc-pkg-grid{grid-template-columns:1fr;gap:16px}}
@media(max-width:640px){
    .tcc-ftoggle-wrap{display:block}
    .tcc-fb{display:none}
    .tcc-fb.tcc-fb-open{display:flex;animation:tcc-slide-down .22s ease}
    @keyframes tcc-slide-down{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
    .tcc-flbl{min-width:60px;font-size:10px}
    .tcc-fc{font-size:11px;padding:5px 10px}
    .tcc-sort-sel{min-width:140px}
}
/* ── Card ── */
.tcc-pkg-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08);display:flex;flex-direction:column;text-decoration:none!important;color:inherit!important;border:1px solid #e8edf3;transition:transform .22s,box-shadow .22s,opacity .26s,transform .26s}
.tcc-pkg-card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(0,0,0,.14)}
.tcc-pkg-hero{position:relative;height:190px;overflow:hidden;background:linear-gradient(160deg,#0f2027,#203a43,#2c5364);flex-shrink:0}
.tcc-pkg-hero img{width:100%;height:100%;object-fit:cover;object-position:center;display:block;transition:transform .4s}
.tcc-pkg-card:hover .tcc-pkg-hero img{transform:scale(1.04)}
.tcc-pkg-hero-ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.78) 0%,rgba(0,0,0,.15) 55%,transparent 100%)}
.tcc-pkg-hero-c{position:absolute;bottom:0;left:0;right:0;padding:12px 14px}
.tcc-pkg-dest-lbl{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#fbbf24;margin-bottom:4px}
.tcc-pkg-title{font-size:16px;font-weight:900;color:#fff;line-height:1.25;margin-bottom:7px;text-shadow:0 1px 8px rgba(0,0,0,.5)}
.tcc-pkg-hero-pills{display:flex;gap:5px;flex-wrap:wrap}
.tcc-pkg-pill{background:rgba(255,255,255,.18);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.25);color:#fff;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap}
.tcc-pkg-pill.hot{background:rgba(239,68,68,.72);border-color:rgba(239,68,68,.4)}
.tcc-pkg-price-strip{background:linear-gradient(90deg,#0c1445,#1e3a8a);color:#fff;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px}
.tcc-pkg-price-from{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#93c5fd;line-height:1}
.tcc-pkg-price-val{font-size:21px;font-weight:900;color:#fff;line-height:1}
.tcc-pkg-price-note{font-size:10px;color:#93c5fd;margin-top:2px}
.tcc-pkg-enquire-badge{background:#fbbf24;color:#0f172a;font-size:10px;font-weight:800;padding:3px 10px;border-radius:20px;white-space:nowrap;flex-shrink:0}
.tcc-pkg-body{padding:14px 15px 15px;flex:1;display:flex;flex-direction:column;gap:5px}
.tcc-pkg-info-row{display:flex;align-items:flex-start;gap:7px;padding:6px 10px;border-radius:8px;font-size:12px;font-weight:700;line-height:1.45;margin-bottom:2px}
.tcc-pkg-row-icon{font-size:14px;flex-shrink:0;margin-top:1px}
.tcc-pkg-row-text{flex:1;word-break:break-word}
.tcc-pkg-row-route{background:#eff6ff;color:#1e40af}
.tcc-pkg-row-loc{background:#f0fdf4;color:#166534}
.tcc-pkg-row-veh{background:#fffbeb;color:#92400e}
.tcc-pkg-row-hotel{background:#ede9fe;color:#5b21b6}
.tcc-pkg-addon-row{display:flex;flex-direction:column;gap:5px;margin-bottom:3px;padding:6px 10px;background:#fefce8;border-radius:8px}
.tcc-pkg-addon-lbl{font-size:11px;font-weight:800;color:#92400e}
.tcc-pkg-addon-tags{display:flex;flex-wrap:wrap;gap:5px}
.tcc-pkg-addon-tag{background:#fef3c7;border:1px solid #fde68a;color:#92400e;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px}
.tcc-pkg-btn{display:block;background:linear-gradient(135deg,#b93b59,#9d314b);color:#fff!important;text-decoration:none!important;padding:12px 14px;border-radius:9px;font-size:13px;font-weight:800;text-align:center;margin-top:auto;letter-spacing:.3px;transition:opacity .2s}
.tcc-pkg-btn:hover{opacity:.88}
</style>';
    }

    // ─── Single card HTML ────────────────────────────────────────────────────
    private static function render_card( $pkg ) {
        $destination = get_post_meta( $pkg->ID, '_tcc_pkg_destination', true );
        $days        = (int) get_post_meta( $pkg->ID, '_tcc_pkg_days', true );
        $nights      = max( 0, $days - 1 );
        $route       = get_post_meta( $pkg->ID, '_tcc_pkg_route', true );
        $price_pp    = get_post_meta( $pkg->ID, '_tcc_pkg_price_pp', true );
        $pkg_adults  = (int) get_post_meta( $pkg->ID, '_tcc_pkg_adults', true );
        $pkg_rooms   = (int) get_post_meta( $pkg->ID, '_tcc_pkg_rooms',  true );
        $pickup      = get_post_meta( $pkg->ID, '_tcc_pkg_pickup', true );
        $drop        = get_post_meta( $pkg->ID, '_tcc_pkg_drop',   true );
        $permalink   = get_permalink( $pkg->ID );

        $vehicles    = json_decode( get_post_meta( $pkg->ID, '_tcc_pkg_vehicles', true ), true ) ?: array();
        $addons      = json_decode( get_post_meta( $pkg->ID, '_tcc_pkg_addons',   true ), true ) ?: array();
        $routing     = json_decode( get_post_meta( $pkg->ID, '_tcc_pkg_routing',  true ), true ) ?: array();
        $categories  = isset( $routing['categories'] ) ? $routing['categories'] : array();
        $unique_cats = array_values( array_unique( array_filter( $categories ) ) );

        // ── Group tour data (overrides vehicle/pickup/drop + price) ──────────
        $linked_tour_id = (int) get_post_meta( $pkg->ID, '_tcc_pkg_linked_tour', true );
        $is_group_tour  = false;
        $group_price    = 0;
        if ( $linked_tour_id ) {
            $lt = get_post( $linked_tour_id );
            if ( $lt ) {
                $is_group_tour = true;
                $group_price   = self::get_tour_triple_price( $linked_tour_id );
                $logistics     = self::get_tour_logistics( $linked_tour_id );
                if ( $logistics['vehicle'] ) $vehicles = array( $logistics['vehicle'] );
                if ( $logistics['pickup'] )  $pickup   = $logistics['pickup'];
                if ( $logistics['drop'] )    $drop     = $logistics['drop'];
            }
        }

        // Use group triple price if available, else preset PP price
        $display_price     = $group_price > 0 ? '₹' . number_format( $group_price, 0 ) : $price_pp;
        $display_price_sub = $group_price > 0 ? 'Triple Sharing · Per Person' : 'Per Person · Incl. GST';

        // Override add-ons from group tour meta (live, auto-updates)
        if ( $is_group_tour ) {
            $tc = self::get_tour_content( $linked_tour_id );
            if ( ! empty( $tc['addons'] ) ) $addons = $tc['addons'];
        }

        // Random enquiry count — changes every page reload
        $fomo = rand( 28, 87 );

        // Image: featured → first itinerary image
        $img_url = has_post_thumbnail( $pkg->ID )
            ? get_the_post_thumbnail_url( $pkg->ID, 'medium_large' )
            : '';
        if ( ! $img_url ) {
            $imgs = json_decode( get_post_meta( $pkg->ID, '_tcc_pkg_itinerary_img', true ), true );
            if ( is_array( $imgs ) ) {
                foreach ( $imgs as $u ) { if ( $u ) { $img_url = $u; break; } }
            }
        }

        $price_numeric = $group_price > 0 ? (int) $group_price : (int) preg_replace( '/[^\d]/', '', $price_pp );

        echo '<a href="' . esc_url( $permalink ) . '" class="tcc-pkg-card"'
           . ' data-dest="'  . esc_attr( strtolower( $destination ) ) . '"'
           . ' data-days="'  . esc_attr( $days ) . '"'
           . ' data-price="' . esc_attr( $price_numeric ) . '"'
           . ' data-type="'  . ( $is_group_tour ? 'group' : 'custom' ) . '"'
           . '>';

        // ── HERO IMAGE ──
        echo '<div class="tcc-pkg-hero">';
        if ( $img_url ) {
            echo '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $pkg->post_title ) . '" loading="lazy">';
        }
        echo '<div class="tcc-pkg-hero-ov"></div>';
        echo '<div class="tcc-pkg-hero-c">';
        if ( $destination ) echo '<div class="tcc-pkg-dest-lbl">📍 ' . esc_html( $destination ) . '</div>';
        echo '<div class="tcc-pkg-title">' . esc_html( $pkg->post_title ) . '</div>';
        echo '<div class="tcc-pkg-hero-pills">';
        if ( $days ) echo '<span class="tcc-pkg-pill">🌅 ' . esc_html( $days ) . 'D/' . esc_html( $nights ) . 'N</span>';
        if ( $is_group_tour ) {
            echo '<span class="tcc-pkg-pill" style="background:rgba(251,191,36,.85);color:#0f172a;border-color:rgba(251,191,36,.5);font-weight:800;">👥 Group Tour</span>';
        } elseif ( $pkg_adults >= 1 ) {
            echo '<span class="tcc-pkg-pill">👥 ' . esc_html( $pkg_adults ) . ' Pax</span>';
        }
        echo '<span class="tcc-pkg-pill hot">🔥 High Demand</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // ── PRICE STRIP ──
        if ( $display_price ) {
            echo '<div class="tcc-pkg-price-strip">';
            echo '<div>';
            echo '<div class="tcc-pkg-price-from">Starting From</div>';
            echo '<div class="tcc-pkg-price-val">' . esc_html( $display_price ) . '</div>';
            echo '<div class="tcc-pkg-price-note">' . esc_html( $display_price_sub ) . '</div>';
            echo '</div>';
            echo '<div class="tcc-pkg-enquire-badge">🔥 ' . esc_html( $fomo ) . ' Enquiries</div>';
            echo '</div>';
        }

        // ── CARD BODY ──
        echo '<div class="tcc-pkg-body">';

        // Route — wraps, blue highlight
        if ( $route ) {
            echo '<div class="tcc-pkg-info-row tcc-pkg-row-route">'
               . '<span class="tcc-pkg-row-icon">🗺️</span>'
               . '<span class="tcc-pkg-row-text">' . esc_html( $route ) . '</span>'
               . '</div>';
        }

        // Pickup / Drop: "Srinagar To Srinagar" — green highlight
        if ( $pickup || $drop ) {
            if ( $pickup && $drop )      $pd_str = $pickup . ' To ' . $drop;
            elseif ( $pickup )           $pd_str = 'From ' . $pickup;
            else                         $pd_str = 'To ' . $drop;
            echo '<div class="tcc-pkg-info-row tcc-pkg-row-loc">'
               . '<span class="tcc-pkg-row-icon">📍</span>'
               . '<span class="tcc-pkg-row-text">' . esc_html( $pd_str ) . '</span>'
               . '</div>';
        }

        // Vehicle — amber highlight
        if ( ! empty( $vehicles ) ) {
            echo '<div class="tcc-pkg-info-row tcc-pkg-row-veh">'
               . '<span class="tcc-pkg-row-icon">🚐</span>'
               . '<span class="tcc-pkg-row-text">' . esc_html( implode( ' / ', $vehicles ) ) . '</span>'
               . '</div>';
        }

        // Hotel Category — purple highlight
        if ( ! empty( $unique_cats ) ) {
            echo '<div class="tcc-pkg-info-row tcc-pkg-row-hotel">'
               . '<span class="tcc-pkg-row-icon">🏨</span>'
               . '<span class="tcc-pkg-row-text">' . esc_html( implode( ' / ', $unique_cats ) ) . ' Hotels</span>'
               . '</div>';
        }

        // Trip Add-ons — label on top, all tags wrap cleanly below
        if ( ! empty( $addons ) ) {
            echo '<div class="tcc-pkg-addon-row">';
            echo '<span class="tcc-pkg-addon-lbl">✨ Add-ons Included</span>';
            echo '<div class="tcc-pkg-addon-tags">';
            foreach ( $addons as $addon ) {
                echo '<span class="tcc-pkg-addon-tag">' . esc_html( $addon ) . '</span>';
            }
            echo '</div></div>';
        }

        // FOMO row — shown only when no price strip
        if ( ! $price_pp ) {
            echo '<div class="tcc-pkg-info-row" style="background:#fff0f0;color:#991b1b;">'
               . '<span class="tcc-pkg-row-icon">🔥</span>'
               . '<span class="tcc-pkg-row-text"><strong>' . esc_html( $fomo ) . ' enquiries</strong> this week</span>'
               . '</div>';
        }

        echo '<span class="tcc-pkg-btn">View Full Itinerary →</span>';
        echo '</div>';
        echo '</a>';
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
                    <label style="font-size:12px;font-weight:600;color:#475569;">Link to a Group Tour <small style="font-weight:normal;">(Optional — shows Book button on package page)</small></label>
                    <!-- Hidden field stores selected tour ID -->
                    <input type="hidden" id="pkg_modal_tour_link" value="">
                    <div id="pkg_modal_tour_list" style="margin-top:8px;max-height:260px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;">
                        <div style="padding:14px;text-align:center;color:#94a3b8;font-size:12px;">Loading tours…</div>
                    </div>
                </div>
                <input type="hidden" id="pkg_modal_preset_name">
                <input type="hidden" id="pkg_modal_destination">
                <input type="hidden" id="pkg_modal_existing_id" value="">
                <input type="hidden" id="pkg_modal_pickup" value="">
                <input type="hidden" id="pkg_modal_drop" value="">
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