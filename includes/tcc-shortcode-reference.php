<?php
/**
 * TCC Shortcode Reference Admin Page
 * Register this by adding to travel-cost-calculator.php:
 *   require_once plugin_dir_path(__FILE__) . 'includes/tcc-shortcode-reference.php';
 *
 * Or paste the TCC_Shortcode_Reference::register_page() call into
 * TCC_Frontend_Group::init() and copy the page method there.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_Shortcode_Reference {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
    }

    public static function register_page() {
        add_submenu_page(
            'options-general.php',
            'TCC Shortcode Reference',
            'TCC Shortcodes',
            'manage_options',
            'tcc-shortcode-reference',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        // Collect live data: destinations, tours, packages
        $master_data   = get_option( 'tcc_master_settings', array() );
        $destinations  = array_keys( $master_data );
        sort( $destinations );

        $tours = get_posts( array(
            'post_type'      => 'tcc_fixed_tour',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );

        // Group tours by destination
        $tours_by_dest = array();
        foreach ( $tours as $t ) {
            $dest = get_post_meta( $t->ID, '_tcc_fd_destination', true ) ?: 'Other';
            $tours_by_dest[ $dest ][] = $t;
        }

        $packages = get_posts( array(
            'post_type'      => 'tcc_package',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft' ),
        ) );
        ?>
<style>
#tcc-sc-ref { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; }
#tcc-sc-ref h1 { font-size: 22px; font-weight: 900; margin: 0 0 6px; }
#tcc-sc-ref .tcc-sr-subtitle { font-size: 13px; color: #64748b; margin-bottom: 24px; }
.tcc-sr-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; overflow: hidden; }
.tcc-sr-section-hd { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; }
.tcc-sr-section-hd .icon { font-size: 20px; }
.tcc-sr-section-hd h2 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
.tcc-sr-section-hd .badge { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; padding: 2px 8px; border-radius: 10px; }
.badge-backend  { background: #fee2e2; color: #991b1b; }
.badge-frontend { background: #dcfce7; color: #166534; }
.badge-admin    { background: #e0e7ff; color: #3730a3; }
.tcc-sr-rows { padding: 0; }
.tcc-sr-row { display: grid; grid-template-columns: 340px 1fr; gap: 16px; padding: 14px 18px; border-bottom: 1px solid #f8fafc; align-items: start; }
.tcc-sr-row:last-child { border-bottom: none; }
.tcc-sr-row:hover { background: #fafbff; }
.tcc-sr-code { display: flex; align-items: center; gap: 8px; }
.tcc-sr-code code { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 10px; font-size: 12px; font-weight: 700; color: #0f172a; cursor: copy; flex: 1; user-select: all; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; }
.tcc-sr-copy { background: none; border: 1px solid #e2e8f0; border-radius: 5px; padding: 4px 8px; font-size: 10px; font-weight: 700; cursor: pointer; color: #64748b; transition: all .15s; flex-shrink: 0; }
.tcc-sr-copy:hover { background: #f0fdf4; border-color: #86efac; color: #166534; }
.tcc-sr-copy.copied { background: #f0fdf4; border-color: #86efac; color: #166534; }
.tcc-sr-desc { font-size: 12px; color: #475569; line-height: 1.55; }
.tcc-sr-desc strong { color: #0f172a; font-size: 13px; display: block; margin-bottom: 3px; }
.tcc-sr-attrs { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 5px; }
.tcc-sr-attr { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; font-family: monospace; color: #475569; padding: 2px 7px; }
.tcc-sr-attr.req { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.tcc-sr-sub-hd { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .7px; color: #94a3b8; padding: 8px 18px 4px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
.tcc-sr-live-entry code { font-size: 11px; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 3px 7px; border-radius: 4px; cursor: copy; }
.tcc-sr-live-entry { font-size: 12px; color: #334155; padding: 6px 18px; border-bottom: 1px solid #f8fafc; display: flex; align-items: center; justify-content: space-between; }
.tcc-sr-live-entry:last-child { border-bottom: none; }
.tcc-sr-empty { font-size: 12px; color: #94a3b8; padding: 14px 18px; font-style: italic; }
</style>

<div class="wrap" id="tcc-sc-ref">
    <h1>📋 TCC Shortcode Reference</h1>
    <p class="tcc-sr-subtitle">All shortcodes available in the Travel Cost Calculator plugin — click any code to copy it.</p>

    <!-- ═══ BACKEND SHORTCODES ═══════════════════════════════════════════ -->
    <div class="tcc-sr-section">
        <div class="tcc-sr-section-hd">
            <span class="icon">⚙️</span>
            <h2>Backend / Agent Tools</h2>
            <span class="badge badge-backend">Login Required</span>
        </div>
        <div class="tcc-sr-rows">

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[travel_calculator]</code>
                    <button class="tcc-sr-copy" data-sc="[travel_calculator]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Main Calculator & Quote Generator</strong>
                    Custom trip cost calculator, day-wise itinerary builder, hotel/cab pricing, preset library, Group Tour booking, and WhatsApp/PDF quote generation. Only accessible to logged-in users.
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[travel_calculator_settings]</code>
                    <button class="tcc-sr-copy" data-sc="[travel_calculator_settings]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Agency Settings Dashboard</strong>
                    Manage global taxes (GST/PT/PG), destination setup, hotel pricing, transport rates, itinerary presets, backup/restore, and published package pages.
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[travel_expense_calculator]</code>
                    <button class="tcc-sr-copy" data-sc="[travel_expense_calculator]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Expense & P&amp;L Dashboard</strong>
                    Master income vs expense dashboard, everyday expenses, auto-recurring expenses, booking-specific cost tracking, partner profit distribution and daily ledger.
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[travel_calculator_followup]</code>
                    <button class="tcc-sr-copy" data-sc="[travel_calculator_followup]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Lead Follow-up Dashboard</strong>
                    Filterable list of all quotations with status tracking, follow-up date, priority flag, WhatsApp quick-send, and email dispatch per lead.
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[tcc_fixed_departures_settings]</code>
                    <button class="tcc-sr-copy" data-sc="[tcc_fixed_departures_settings]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Group Tours Manager</strong>
                    Create and manage Group Tour / Fixed Departure packages: departure dates with per-date pricing and capacity, day-wise itinerary, hotel routing, logistics, inclusions/exclusions, trip add-ons, and duplicate/delete controls.
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ FRONTEND BOOKING FORMS ═══════════════════════════════════════ -->
    <div class="tcc-sr-section">
        <div class="tcc-sr-section-hd">
            <span class="icon">🌐</span>
            <h2>Frontend Booking Forms</h2>
            <span class="badge badge-frontend">Public</span>
        </div>
        <div class="tcc-sr-rows">

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[tcc_all_group_tours]</code>
                    <button class="tcc-sr-copy" data-sc="[tcc_all_group_tours]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Master Tour Selector</strong>
                    Multi-step booking form — visitors pick a destination, select a tour, choose a departure date, set travelers (Double/Triple/Single/Child/Infant), see live pricing, enter contact details, and submit via WhatsApp. Shows all published Group Tours across all destinations.
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[tcc_dest_group_tours destination="Kashmir"]</code>
                    <button class="tcc-sr-copy" data-sc='[tcc_dest_group_tours destination="Kashmir"]'>Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Destination-Locked Booking Form</strong>
                    Same multi-step form as above but pre-filtered to one destination. Visitors skip destination selection and go straight to tour + date picking.
                    <div class="tcc-sr-attrs">
                        <span class="tcc-sr-attr req">destination="…" — required</span>
                    </div>
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[tcc_group_tour id="123"]</code>
                    <button class="tcc-sr-copy" data-sc='[tcc_group_tour id="123"]'>Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Single Group Tour Booking Form</strong>
                    Booking form locked to one specific Group Tour. Shows live departure dates, seat counts, per-date pricing, traveler selector, and WhatsApp/PDF submission. Used on individual tour pages and embedded in Package pages via the sidebar.
                    <div class="tcc-sr-attrs">
                        <span class="tcc-sr-attr req">id="…" — Group Tour post ID, required</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ PACKAGE GRID ═════════════════════════════════════════════════ -->
    <div class="tcc-sr-section">
        <div class="tcc-sr-section-hd">
            <span class="icon">📦</span>
            <h2>Package Pages Grid</h2>
            <span class="badge badge-frontend">Public</span>
        </div>
        <div class="tcc-sr-rows">

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[tcc_packages]</code>
                    <button class="tcc-sr-copy" data-sc="[tcc_packages]">Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>All Published Packages Grid</strong>
                    Filterable, sortable grid of all published tcc_package pages. Each card shows hero image, destination, days/nights badge, Group Tour pill, price strip (triple/pp), route, pickup/drop, vehicle, hotel category, and add-ons.
                    Includes client-side filter bar: Destination chips, Duration, Tour Type, Sort dropdown.
                </div>
            </div>

            <div class="tcc-sr-row">
                <div class="tcc-sr-code">
                    <code>[tcc_packages destination="Kashmir" cols="3"]</code>
                    <button class="tcc-sr-copy" data-sc='[tcc_packages destination="Kashmir" cols="3"]'>Copy</button>
                </div>
                <div class="tcc-sr-desc">
                    <strong>Filtered Packages Grid</strong>
                    Show only packages matching a specific destination, with custom column count.
                    <div class="tcc-sr-attrs">
                        <span class="tcc-sr-attr">destination="…" — filter by destination</span>
                        <span class="tcc-sr-attr">cols="1|2|3|4" — grid columns (default 3)</span>
                        <span class="tcc-sr-attr">limit="-1" — max packages (-1 = all)</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ LIVE SHORTCODES: DESTINATIONS ════════════════════════════════ -->
    <?php if ( ! empty( $destinations ) ) : ?>
    <div class="tcc-sr-section">
        <div class="tcc-sr-section-hd">
            <span class="icon">📍</span>
            <h2>Live Destination Shortcodes</h2>
            <span class="badge badge-admin"><?php echo count( $destinations ); ?> Destinations</span>
        </div>
        <div class="tcc-sr-sub-hd">Copy-ready shortcodes for each destination in your Master Settings</div>
        <?php foreach ( $destinations as $dest ) : ?>
        <div class="tcc-sr-live-entry">
            <span><strong><?php echo esc_html( $dest ); ?></strong></span>
            <div style="display:flex;align-items:center;gap:8px;">
                <code id="dest-sc-<?php echo esc_attr( sanitize_title( $dest ) ); ?>"><?php
                    $sc = '[tcc_dest_group_tours destination="' . esc_attr( $dest ) . '"]';
                    echo esc_html( $sc );
                ?></code>
                <button class="tcc-sr-copy" data-sc="<?php echo esc_attr( '[tcc_dest_group_tours destination="' . $dest . '"]' ); ?>">Copy</button>
                <span style="font-size:11px;color:#94a3b8;">|</span>
                <code><?php echo esc_html( '[tcc_packages destination="' . $dest . '"]' ); ?></code>
                <button class="tcc-sr-copy" data-sc="<?php echo esc_attr( '[tcc_packages destination="' . $dest . '"]' ); ?>">Copy</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ LIVE SHORTCODES: GROUP TOURS ═════════════════════════════════ -->
    <?php if ( ! empty( $tours ) ) : ?>
    <div class="tcc-sr-section">
        <div class="tcc-sr-section-hd">
            <span class="icon">👥</span>
            <h2>Live Group Tour Shortcodes</h2>
            <span class="badge badge-admin"><?php echo count( $tours ); ?> Tours</span>
        </div>
        <?php foreach ( $tours_by_dest as $dest => $dest_tours ) : ?>
        <div class="tcc-sr-sub-hd">📍 <?php echo esc_html( $dest ); ?></div>
        <?php foreach ( $dest_tours as $t ) :
            $sc = '[tcc_group_tour id="' . $t->ID . '"]';
        ?>
        <div class="tcc-sr-live-entry">
            <span>
                <strong style="color:#0f172a;"><?php echo esc_html( $t->post_title ); ?></strong>
                <span style="font-size:10px;color:#94a3b8;margin-left:6px;">ID: <?php echo $t->ID; ?></span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <code><?php echo esc_html( $sc ); ?></code>
                <button class="tcc-sr-copy" data-sc="<?php echo esc_attr( $sc ); ?>">Copy</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ LIVE SHORTCODES: PACKAGES GRID PRESETS ═══════════════════════ -->
    <?php if ( ! empty( $packages ) ) : ?>
    <div class="tcc-sr-section">
        <div class="tcc-sr-section-hd">
            <span class="icon">📦</span>
            <h2>Quick Package Grid Shortcodes</h2>
            <span class="badge badge-admin"><?php echo count( $packages ); ?> Packages</span>
        </div>
        <div class="tcc-sr-sub-hd">One-click [tcc_packages] shortcodes for each destination that has published packages</div>
        <?php
        $pkg_dests = array();
        foreach ( $packages as $p ) {
            $d = get_post_meta( $p->ID, '_tcc_pkg_destination', true );
            if ( $d ) $pkg_dests[ $d ] = ( $pkg_dests[ $d ] ?? 0 ) + 1;
        }
        ksort( $pkg_dests );
        foreach ( $pkg_dests as $dest => $count ) :
            $sc = '[tcc_packages destination="' . $dest . '" cols="3"]';
        ?>
        <div class="tcc-sr-live-entry">
            <span>
                <strong><?php echo esc_html( $dest ); ?></strong>
                <span style="font-size:10px;color:#94a3b8;margin-left:6px;"><?php echo $count; ?> package<?php echo $count > 1 ? 's' : ''; ?></span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <code><?php echo esc_html( $sc ); ?></code>
                <button class="tcc-sr-copy" data-sc="<?php echo esc_attr( $sc ); ?>">Copy</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.tcc-sr-copy').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var sc = this.getAttribute('data-sc');
        var me = this;
        var orig = me.textContent;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(sc).then(function() {
                me.textContent = 'Copied!';
                me.classList.add('copied');
                setTimeout(function() { me.textContent = orig; me.classList.remove('copied'); }, 1800);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = sc;
            ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
            document.body.appendChild(ta);
            ta.focus(); ta.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(ta);
            me.textContent = 'Copied!';
            me.classList.add('copied');
            setTimeout(function() { me.textContent = orig; me.classList.remove('copied'); }, 1800);
        }
    });
});

// Make <code> tags also copy on click
document.querySelectorAll('#tcc-sc-ref code').forEach(function(el) {
    el.title = 'Click to copy';
    el.style.cursor = 'copy';
    el.addEventListener('click', function() {
        var txt = this.textContent;
        if (navigator.clipboard) navigator.clipboard.writeText(txt);
        var orig = this.style.background;
        this.style.background = '#bbf7d0';
        setTimeout(function() { el.style.background = orig; }, 700);
    });
});
</script>
        <?php
    }
}
TCC_Shortcode_Reference::init();