<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_Frontend_Group {

    public static function init() {
        add_shortcode('tcc_group_tour', array(__CLASS__, 'render_shortcode'));
        add_shortcode('tcc_all_group_tours', array(__CLASS__, 'render_master_shortcode')); 
        add_shortcode('tcc_dest_group_tours', array(__CLASS__, 'render_master_shortcode')); // NEW Destination Shortcode
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_scripts'));
        add_action('admin_menu', array(__CLASS__, 'register_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function register_scripts() {
        wp_register_style('tcc-front-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/tcc-front.css', array(), time());
        wp_register_script('tcc-front-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/tcc-front.js', array('jquery'), time(), true);
    }

    public static function register_settings_page() {
        add_submenu_page('options-general.php', 'TCC Frontend Form', 'TCC Frontend Form', 'manage_options', 'tcc-frontend-settings', array(__CLASS__, 'settings_page_html'));
    }

    public static function register_settings() {
        register_setting('tcc_frontend_group', 'tcc_wa_number');
        register_setting('tcc_frontend_group', 'tcc_admin_email');
        register_setting('tcc_frontend_group', 'tcc_form_flow');
        register_setting('tcc_frontend_group', 'tcc_max_child_double');
        register_setting('tcc_frontend_group', 'tcc_max_infant_double');
        register_setting('tcc_frontend_group', 'tcc_max_child_triple');
        register_setting('tcc_frontend_group', 'tcc_max_infant_triple');
        register_setting('tcc_frontend_group', 'tcc_max_child_single');
        register_setting('tcc_frontend_group', 'tcc_max_infant_single');
        register_setting('tcc_frontend_group', 'tcc_auto_wa_redirect'); // Auto-open WhatsApp on submit
    }

    public static function settings_page_html() {
        $tours = get_posts(array(
            'post_type' => 'tcc_fixed_tour',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        // Get unique destinations for the new shortcode block
        $unique_dests = array();
        if ($tours) {
            foreach ($tours as $t) {
                $dest = get_post_meta($t->ID, '_tcc_fd_destination', true);
                if (!empty($dest)) { $unique_dests[$dest] = true; }
            }
        }
        $dest_list_admin = array_keys($unique_dests);
        sort($dest_list_admin);
        ?>
        <div class="wrap">
            <h2>Group Tour Frontend Shortcode Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('tcc_frontend_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Company WhatsApp Number</th>
                        <td><input type="text" name="tcc_wa_number" value="<?php echo esc_attr(get_option('tcc_wa_number')); ?>" placeholder="e.g. 919876543210" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Admin Notification Email</th>
                        <td><input type="email" name="tcc_admin_email" value="<?php echo esc_attr(get_option('tcc_admin_email', get_option('admin_email'))); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Form Step Order</th>
                        <td>
                            <select name="tcc_form_flow">
                                <option value="calc_first" <?php selected(get_option('tcc_form_flow'), 'calc_first'); ?>>1: Select Tour -> 2: Pricing -> 3: Client Details</option>
                                <option value="lead_first" <?php selected(get_option('tcc_form_flow'), 'lead_first'); ?>>1: Client Details -> 2: Select Tour -> 3: Pricing</option>
                            </select>
                            <br><em>If "Client Details" is first, you will receive a partial lead email immediately when they click "Next", even if they abandon the pricing step!</em>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-Open WhatsApp on Submit</th>
                        <td>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="hidden" name="tcc_auto_wa_redirect" value="0" />
                                <input type="checkbox" name="tcc_auto_wa_redirect" value="1" <?php checked('1', get_option('tcc_auto_wa_redirect', '1')); ?> />
                                <span>Automatically open WhatsApp in a new tab when a visitor submits the booking form</span>
                            </label>
                            <p class="description" style="margin-top:6px;">When <strong>unchecked</strong>, the WhatsApp button still appears on the success screen so visitors can tap it manually — it just won't open automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Max Capacity (Double Room)</th>
                        <td>
                            Child: <input type="number" min="0" name="tcc_max_child_double" value="<?php echo esc_attr(get_option('tcc_max_child_double', 1)); ?>" class="regular-text" style="width: 60px; margin-right: 15px;" />
                            Infant: <input type="number" min="0" name="tcc_max_infant_double" value="<?php echo esc_attr(get_option('tcc_max_infant_double', 1)); ?>" class="regular-text" style="width: 60px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Max Capacity (Triple Room)</th>
                        <td>
                            Child: <input type="number" min="0" name="tcc_max_child_triple" value="<?php echo esc_attr(get_option('tcc_max_child_triple', 1)); ?>" class="regular-text" style="width: 60px; margin-right: 15px;" />
                            Infant: <input type="number" min="0" name="tcc_max_infant_triple" value="<?php echo esc_attr(get_option('tcc_max_infant_triple', 1)); ?>" class="regular-text" style="width: 60px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Max Capacity (Single Room)</th>
                        <td>
                            Child: <input type="number" min="0" name="tcc_max_child_single" value="<?php echo esc_attr(get_option('tcc_max_child_single', 1)); ?>" class="regular-text" style="width: 60px; margin-right: 15px;" />
                            Infant: <input type="number" min="0" name="tcc_max_infant_single" value="<?php echo esc_attr(get_option('tcc_max_infant_single', 1)); ?>" class="regular-text" style="width: 60px;" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <hr style="margin-top: 30px;">
            <h2>📋 Form Shortcodes</h2>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px; background:#e0f2fe; padding:20px; border:1px solid #bae6fd; border-radius:8px;">
                    <h3 style="margin-top: 0;">🌟 Master Tour Selector</h3>
                    <p>Use this shortcode to display a master form where clients can pick a Destination, select a Tour, and choose Dates dynamically.</p>
                    <code style="background:#fff; padding:10px; font-size:16px; user-select:all; display:block; font-weight:bold; color:#0369a1; text-align:center; border: 1px solid #7dd3fc; border-radius: 4px;">[tcc_all_group_tours]</code>
                </div>

                <div style="flex: 1; min-width: 300px; background:#fef3c7; padding:20px; border:1px solid #fde68a; border-radius:8px;">
                    <h3 style="margin-top: 0;">🌍 Destination Forms</h3>
                    <p>Displays a form pre-locked to a specific Destination.</p>
                    <div style="background:#fff; padding:15px; border:1px solid #fde68a; border-radius:5px; max-height: 250px; overflow-y: auto;">
                        <?php
                        if (!empty($dest_list_admin)) {
                            echo '<ul style="list-style-type:none; padding:0; margin:0;">';
                            foreach ($dest_list_admin as $dst) {
                                echo '<li style="margin-bottom:12px; border-bottom: 1px solid #fef3c7; padding-bottom: 8px;">';
                                echo '<strong style="display:block; margin-bottom: 4px; color:#92400e;">📍 ' . esc_html($dst) . '</strong>';
                                echo '<code style="background:#fffbeb; padding:4px 8px; font-size:13px; user-select:all; color:#b45309; border-radius: 3px; border: 1px dashed #fcd34d;">[tcc_dest_group_tours destination="' . esc_attr($dst) . '"]</code>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p style="color:#d97706; margin:0;">No destinations found.</p>';
                        }
                        ?>
                    </div>
                </div>

                <div style="flex: 1; min-width: 300px; background:#f8fafc; padding:20px; border:1px solid #cbd5e1; border-radius:8px;">
                    <h3 style="margin-top: 0;">📍 Individual Tour Shortcodes</h3>
                    <p>Copy and paste these shortcodes to display a form locked to a <b>specific</b> tour.</p>
                    
                    <div style="background:#fff; padding:15px; border:1px solid #e2e8f0; border-radius:5px; max-height: 250px; overflow-y: auto;">
                        <?php
                        if ($tours) {
                            echo '<ul style="list-style-type:none; padding:0; margin:0;">';
                            foreach ($tours as $t) {
                                echo '<li style="margin-bottom:12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">';
                                echo '<strong style="display:block; margin-bottom: 4px; color:#334155;">' . esc_html($t->post_title) . '</strong>';
                                echo '<code style="background:#f1f5f9; padding:4px 8px; font-size:13px; user-select:all; color:#dc2626; border-radius: 3px;">[tcc_group_tour id="' . $t->ID . '"]</code>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p style="color:#d97706; margin:0;">No group tours found in database.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $tour_id = intval($atts['id']);
        if (!$tour_id) return '<p>Error: Please provide a valid Group Tour ID.</p>';
        $tour = get_post($tour_id);
        if (!$tour || $tour->post_type !== 'tcc_fixed_tour') return '<p>Error: Tour not found.</p>';

        wp_enqueue_style('tcc-front-css'); wp_enqueue_script('tcc-front-js');
        wp_localize_script('tcc-front-js', 'tcc_front_ajax', array(
            'url'              => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('tcc_front_nonce'),
            'auto_wa_redirect' => get_option('tcc_auto_wa_redirect', '1'),
        ));

        $prices = array(
            'double' => floatval(get_post_meta($tour_id, '_tcc_price_double', true)), 'triple' => floatval(get_post_meta($tour_id, '_tcc_price_triple', true)),
            'single' => floatval(get_post_meta($tour_id, '_tcc_price_single', true)), 'child'  => floatval(get_post_meta($tour_id, '_tcc_price_child', true)),
            'infant' => floatval(get_post_meta($tour_id, '_tcc_price_infant', true)), 'dates'  => array() 
        );

        $dates_json = get_post_meta($tour_id, '_tcc_fd_dates', true); $dates = json_decode($dates_json, true) ?: array();
        $processed_dates = array();
        foreach($dates as $d) {
            $booked = TCC_Fixed_Departures::get_booked_seats($tour_id, $d['start_date']);
            $avail = max(0, intval($d['capacity']) - $booked);
            $d['available'] = $avail; 
            $d['is_sold_out'] = ($avail <= 0); 
            $d['hotel_cat'] = !empty($d['hotel_cat']) ? $d['hotel_cat'] : (get_post_meta($tour_id, '_tcc_hotel_cat', true) ?: 'Standard'); // ADD THIS
            $processed_dates[] = $d;
        }
        $prices['dates'] = $processed_dates;
        $prices_json = esc_attr(wp_json_encode($prices));
        
        $globals = get_option('tcc_global_settings', array('gst' => 5));
        $gst_pct = floatval($globals['gst']); $flow = get_option('tcc_form_flow', 'calc_first');

        $mc_dbl = get_option('tcc_max_child_double', 1); $mi_dbl = get_option('tcc_max_infant_double', 1);
        $mc_tpl = get_option('tcc_max_child_triple', 1); $mi_tpl = get_option('tcc_max_infant_triple', 1);
        $mc_sgl = get_option('tcc_max_child_single', 1); $mi_sgl = get_option('tcc_max_infant_single', 1);
        
        $logged_in = is_user_logged_in() ? 'true' : 'false';

        // --- ENGAGING TITLE LOGIC ---
        $default_title = '🗺️ ' . esc_html($tour->post_title);
        $initial_title = ($flow === 'lead_first') ? '✨ Unlock Live Pricing & Itinerary' : $default_title;

        ob_start();
        ?>
        <div class="tcc-ft-container single-tour-form" data-logged-in="<?php echo $logged_in; ?>" data-tour-id="<?php echo $tour_id; ?>" data-prices='<?php echo $prices_json; ?>' data-gst="<?php echo $gst_pct; ?>" data-flow="<?php echo $flow; ?>" data-mc-dbl="<?php echo esc_attr($mc_dbl); ?>" data-mi-dbl="<?php echo esc_attr($mi_dbl); ?>" data-mc-tpl="<?php echo esc_attr($mc_tpl); ?>" data-mi-tpl="<?php echo esc_attr($mi_tpl); ?>" data-mc-sgl="<?php echo esc_attr($mc_sgl); ?>" data-mi-sgl="<?php echo esc_attr($mi_sgl); ?>">
            <h3 class="tcc-ft-title" data-default-title="<?php echo esc_attr($default_title); ?>"><?php echo $initial_title; ?></h3>
            <div class="tcc-ft-steps">
                <div class="tcc-ft-step" id="tcc-ft-step-tour-select">
                    
                    <?php if ($flow === 'calc_first'): ?>
                    <div style="background: #f0fdfa; border: 1px dashed #14b8a6; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <span style="display:block; font-size: 15px; font-weight:bold; color:#0f766e; margin-bottom: 4px;">🎯 Plan Your Perfect Trip!</span>
                        <span style="font-size: 13px; color: #0d9488; line-height: 1.4; display:block;">Select your travel dates below to check live seat availability and instantly calculate your detailed quotation.</span>
                    </div>
                    <?php endif; ?>

                    <div class="tcc-ft-card" id="tcc-ft-date-card">
                        <label>Select Departure Date</label>
                        
                        <div class="tcc-ft-date-grid">
                           <?php foreach($processed_dates as $pd): ?>
                                <?php if($pd['is_sold_out']): ?>
                                    <label class="fd-date-btn-modern sold-out" data-cat="<?php echo esc_attr($pd['hotel_cat']); ?>" data-date="<?php echo esc_attr($pd['start_date']); ?>">
                                        <span class="fd-date-main-text" style="display:block; text-align:center; color:#94a3b8; text-decoration: line-through;">🗓️ <?php echo date('d M Y', strtotime($pd['start_date'])); ?></span>
                                        <span class="fd-date-sub-text" style="display:block; text-align:center; color:#ef4444; font-weight:bold;">Sold Out</span>
                                        <span style="display:block; font-size:10px; background:#f1f5f9; color:#94a3b8; padding:2px 6px; border-radius:3px; margin:4px auto 0; width:fit-content; text-align:center;"><?php echo esc_html($pd['hotel_cat']); ?></span>
                                    </label>
                                <?php else: ?>
                                    <label class="fd-date-btn-modern" data-cat="<?php echo esc_attr($pd['hotel_cat']); ?>" data-date="<?php echo esc_attr($pd['start_date']); ?>">
                                        <input type="radio" name="tcc_ft_date" value="<?php echo $pd['start_date']; ?>" style="display:none;">
                                        <div class="fd-date-check">&#10003;</div>
                                        <span class="fd-date-main-text" style="display:block; text-align:center;">🗓️ <?php echo date('d M Y', strtotime($pd['start_date'])); ?></span>
                                        <span class="fd-date-sub-text fd-seat-avail" style="display:block; text-align:center;">
                                            <?php if($pd['available'] < 6): ?>
                                                <span style="color:#ea580c; font-weight:bold;">🔥 Only <?php echo $pd['available']; ?> Seats Left!</span>
                                            <?php else: ?>
                                                <?php echo $pd['available']; ?> Seats Left
                                            <?php endif; ?>
                                        </span>
                                        <span style="display:block; font-size:10px; background:#e0e7ff; color:#3730a3; padding:2px 6px; border-radius:3px; margin:4px auto 0; width:fit-content; text-align:center;"><?php echo esc_html($pd['hotel_cat']); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="tcc-ft-date" value="">
                    </div>
                    
                    <div class="tcc-ft-actions">
                        <button type="button" class="tcc-ft-btn-back" id="btn-ts-back" style="display:none;">&larr; Back</button>
                        <button type="button" class="tcc-ft-btn-main" id="btn-ts-next">Continue to Travelers &rarr;</button>
                    </div>
                </div>
                <?php self::render_pax_and_lead($prices, $gst_pct, $flow); ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public static function render_master_shortcode($atts) {
        // Support destination filtering
        $atts = shortcode_atts(array('destination' => ''), $atts);
        $target_dest = sanitize_text_field($atts['destination']);

        wp_enqueue_style('tcc-front-css'); wp_enqueue_script('tcc-front-js');
        wp_localize_script('tcc-front-js', 'tcc_front_ajax', array(
            'url'              => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('tcc_front_nonce'),
            'auto_wa_redirect' => get_option('tcc_auto_wa_redirect', '1'),
        ));

        $tours = get_posts(array('post_type' => 'tcc_fixed_tour', 'posts_per_page' => -1, 'post_status' => 'publish'));
        if (empty($tours)) return '<p>No Active Tours Found.</p>';

        $all_tours = array(); $destinations = array();
        foreach($tours as $t) {
            $dest = get_post_meta($t->ID, '_tcc_fd_destination', true); if (empty($dest)) $dest = 'General / Other';

            // Filter if destination is targeted via shortcode
            if (!empty($target_dest) && strcasecmp($dest, $target_dest) !== 0) {
                continue;
            }

            $p = array('double' => floatval(get_post_meta($t->ID, '_tcc_price_double', true)), 'triple' => floatval(get_post_meta($t->ID, '_tcc_price_triple', true)), 'single' => floatval(get_post_meta($t->ID, '_tcc_price_single', true)), 'child'  => floatval(get_post_meta($t->ID, '_tcc_price_child', true)), 'infant' => floatval(get_post_meta($t->ID, '_tcc_price_infant', true)), 'dates'  => array());
            $dates_json = get_post_meta($t->ID, '_tcc_fd_dates', true); $dates = json_decode($dates_json, true) ?: array();
            
            $valid_dates = array();
            $global_cat = get_post_meta($t->ID, '_tcc_hotel_cat', true);
            foreach($dates as $d) {
                $booked = TCC_Fixed_Departures::get_booked_seats($t->ID, $d['start_date']); $avail = max(0, intval($d['capacity']) - $booked);
                $d['available'] = $avail; 
                $d['hotel_cat'] = !empty($d['hotel_cat']) ? $d['hotel_cat'] : ($global_cat ?: 'Standard'); // ADD THIS
                $valid_dates[] = $d; 
            }
            $p['dates'] = $valid_dates;
            if (!empty($valid_dates)) { $all_tours[$t->ID] = array('id' => $t->ID, 'title' => $t->post_title, 'destination' => $dest, 'prices' => $p); $destinations[$dest] = true; }
        }

        if(empty($all_tours)) return '<p>All tours for this selection are currently sold out or have no dates.</p>';
        $dest_list = array_keys($destinations); sort($dest_list);
        $all_tours_json = esc_attr(wp_json_encode($all_tours));
        
        $globals = get_option('tcc_global_settings', array('gst' => 5)); $gst_pct = floatval($globals['gst']); $flow = get_option('tcc_form_flow', 'calc_first');

        $mc_dbl = get_option('tcc_max_child_double', 1); $mi_dbl = get_option('tcc_max_infant_double', 1);
        $mc_tpl = get_option('tcc_max_child_triple', 1); $mi_tpl = get_option('tcc_max_infant_triple', 1);
        $mc_sgl = get_option('tcc_max_child_single', 1); $mi_sgl = get_option('tcc_max_infant_single', 1);
        
        $logged_in = is_user_logged_in() ? 'true' : 'false';

        // --- ENGAGING TITLE LOGIC ---
        $default_title = !empty($target_dest) ? '📍 Best of ' . esc_html($target_dest) . ' Packages' : '🌍 Discover Your Next Adventure';
        $initial_title = ($flow === 'lead_first') ? '✨ Unlock Live Pricing & Itinerary' : $default_title;
        $is_dest_locked = !empty($target_dest);

        ob_start();
        ?>
        <div class="tcc-ft-container master-tour-form" data-logged-in="<?php echo $logged_in; ?>" data-tour-id="" data-prices='{}' data-all-tours='<?php echo $all_tours_json; ?>' data-gst="<?php echo $gst_pct; ?>" data-flow="<?php echo $flow; ?>" data-mc-dbl="<?php echo esc_attr($mc_dbl); ?>" data-mi-dbl="<?php echo esc_attr($mi_dbl); ?>" data-mc-tpl="<?php echo esc_attr($mc_tpl); ?>" data-mi-tpl="<?php echo esc_attr($mi_tpl); ?>" data-mc-sgl="<?php echo esc_attr($mc_sgl); ?>" data-mi-sgl="<?php echo esc_attr($mi_sgl); ?>" data-target-dest="<?php echo esc_attr($target_dest); ?>">
            <h3 class="tcc-ft-title" data-default-title="<?php echo esc_attr($default_title); ?>"><?php echo $initial_title; ?></h3>
            <div class="tcc-ft-steps">
                
                <div class="tcc-ft-step" id="tcc-ft-step-tour-select">
                    
                    <?php if ($flow === 'calc_first'): ?>
                    <div style="background: #f0fdfa; border: 1px dashed #14b8a6; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <span style="display:block; font-size: 15px; font-weight:bold; color:#0f766e; margin-bottom: 4px;">🎯 Plan Your Perfect Trip!</span>
                        <span style="font-size: 13px; color: #0d9488; line-height: 1.4; display:block;">
                            <?php 
                                if ($is_dest_locked) {
                                    echo 'Select your tour and travel dates below to check live seat availability and instantly calculate your group tour cost.';
                                } else {
                                    echo 'Select your destination and travel dates below to check live seat availability and instantly calculate your group tour cost.';
                                }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_dest_locked): ?>
                        <input type="radio" name="tcc_ft_destination" value="<?php echo esc_attr($target_dest); ?>" checked style="display:none;">
                    <?php endif; ?>

                    <div class="tcc-ft-card" id="tcc-ft-dest-card" <?php if($is_dest_locked) echo 'style="display:none;"'; ?>>
                        <label>Select Destination</label>
                        <div class="tcc-ft-date-grid">
                            <?php foreach($dest_list as $dest): ?>
                                <label class="fd-date-btn-modern tcc-ft-dest-btn" style="min-height: auto; padding: 16px 10px; border-width: 2px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.2s ease;">
                                    <input type="radio" name="tcc_ft_destination" value="<?php echo esc_attr($dest); ?>" style="display:none;">
                                    <div class="fd-date-check">&#10003;</div>
                                    <span class="fd-date-main-text" style="margin: 0; font-size: 14px; font-weight: 600; text-align: center; white-space: normal; line-height: 1.4;">📍 <?php echo esc_html($dest); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="tcc-ft-card" id="tcc-ft-tour-card" <?php if($is_dest_locked) echo 'style="display:block; background: #eef2ff; border-color: #c7d2fe;"'; else echo 'style="display:none; background: #eef2ff; border-color: #c7d2fe;"'; ?>>
                        <label style="color: #3730a3;">Select Group Tour</label>
                        <div class="tcc-ft-date-grid">
                            <?php foreach($all_tours as $tid => $td): ?>
                                <label class="fd-date-btn-modern tcc-ft-tour-btn" data-dest="<?php echo esc_attr($td['destination']); ?>" style="min-height: auto; padding: 16px 10px; border-width: 2px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.2s ease; <?php if(!$is_dest_locked) echo 'display:none;'; ?>">
                                    <input type="radio" name="tcc_ft_tour" value="<?php echo $tid; ?>" style="display:none;">
                                    <div class="fd-date-check">&#10003;</div>
                                    <span class="fd-date-main-text" style="margin: 0; font-size: 14px; font-weight: 600; text-align: center; white-space: normal; line-height: 1.4;">🗺️ <?php echo esc_html($td['title']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="tcc-ft-card" id="tcc-ft-date-card" style="display:none;">
                        <label>Select Departure Date</label>
                        <div class="tcc-ft-date-grid"></div><input type="hidden" id="tcc-ft-date" value="">
                    </div>
                    <div class="tcc-ft-actions">
                        <button type="button" class="tcc-ft-btn-back" id="btn-ts-back" style="display:none;">&larr; Back</button>
                        <button type="button" class="tcc-ft-btn-main" id="btn-ts-next">Continue to Travelers &rarr;</button>
                    </div>
                </div>
                <?php self::render_pax_and_lead(array(), $gst_pct, $flow); ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function render_pax_and_lead($prices, $gst_pct, $flow = 'calc_first') {
        $pr = empty($prices) ? array('double'=>0,'triple'=>0,'single'=>0,'child'=>0,'infant'=>0) : $prices;
        
        $is_logged = is_user_logged_in();
        $req = $is_logged ? '' : 'required';
        $star = $is_logged ? '' : ' *';
        ?>
        <div class="tcc-ft-step" id="tcc-ft-step-calc" style="display:none;">
            
            <div class="tcc-ft-selection-summary" style="display:none; font-size: 13px; color: #3730a3; background: #e0e7ff; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-weight: 700; text-align: center; border: 1px solid #c7d2fe; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"></div>

            <div class="tcc-ft-card">
                <label>Number of Travelers</label>
                <div class="tcc-ft-pax-grid">
                    <?php foreach(array('double'=>'Adults (Double Share)', 'triple'=>'Adults (Triple Share)', 'single'=>'Adults (Single Room)', 'child'=>'Child (6-12 yrs)', 'infant'=>'Infant (<6 yrs)') as $key => $label): ?>
                    <div class="tcc-ft-pax-item">
                        <div class="tcc-ft-pax-info"><span class="tcc-ft-pax-label"><?php echo $label; ?></span><span class="tcc-ft-pax-price">₹<?php echo number_format($pr[$key], 0); ?> /pax</span></div>
                        <div class="tcc-ft-qty"><button type="button" class="tcc-ft-btn-qty minus" data-target="<?php echo $key; ?>">-</button><input type="number" id="tcc-ft-qty-<?php echo $key; ?>" class="tcc-ft-pax-val" value="0" readonly tabindex="-1"><button type="button" class="tcc-ft-btn-qty plus" data-target="<?php echo $key; ?>">+</button></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="tcc-ft-breakdown">
                <div class="tcc-ft-row"><span>Base Price:</span> <span id="tcc-ft-base">₹0.00</span></div>
                <div class="tcc-ft-row"><span>GST (<?php echo $gst_pct; ?>%):</span> <span id="tcc-ft-gst">₹0.00</span></div>
                <div class="tcc-ft-row tcc-ft-grand"><span>Grand Total:</span> <span id="tcc-ft-grand">₹0.00</span></div>
            </div>
            <div class="tcc-ft-actions">
                <button type="button" class="tcc-ft-btn-back" id="btn-calc-back">&larr; Back</button>
                <button type="button" class="tcc-ft-btn-main" id="btn-calc-next">Continue to Details &rarr;</button>
                <button type="button" class="tcc-ft-btn-main tcc-ft-btn-wa" id="btn-calc-submit" style="display:none;"><?php echo $is_logged ? 'Generate Booking' : 'Submit Query & WhatsApp'; ?></button>
            </div>
        </div>

        <div class="tcc-ft-step" id="tcc-ft-step-lead" style="display:none;">
            
            <?php if ($flow === 'lead_first'): ?>
            <div style="background: #fffbeb; border: 1px dashed #f59e0b; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <span style="display:block; font-size: 15px; font-weight:bold; color:#d97706; margin-bottom: 4px;">🚀 Unlock Live Availability & Pricing!</span>
                <span style="font-size: 13px; color: #475569; line-height: 1.4; display:block;">Please enter your details below to view tour dates, live seat counts, and generate your instant WhatsApp quotation.</span>
            </div>
            <?php endif; ?>

            <div class="tcc-ft-card" style="margin-bottom: 20px;">
                <label>Your Name<?php echo $star; ?></label><input type="text" id="tcc-ft-name" placeholder="E.g. Rahul Sharma" <?php echo $req; ?>>
                <label>WhatsApp Number<?php echo $star; ?></label><input type="tel" id="tcc-ft-phone" placeholder="+91 XXXXX XXXXX" <?php echo $req; ?>>
                <label>Email Address</label><input type="email" id="tcc-ft-email" placeholder="rahul@example.com">
            </div>
            <div class="tcc-ft-actions">
                <button type="button" class="tcc-ft-btn-back" id="btn-lead-back">&larr; Back</button>
                <button type="button" class="tcc-ft-btn-main" id="btn-lead-next" style="display:none;">Continue to Select Tour &rarr;</button>
                <button type="button" class="tcc-ft-btn-main tcc-ft-btn-wa" id="btn-lead-submit"><?php echo $is_logged ? 'Generate Booking' : 'Submit Query & WhatsApp'; ?></button>
            </div>
            <div id="tcc-ft-error" style="color:#ef4444; font-size:12px; margin-top:8px; display:none; text-align:center;"></div>
        </div>

        <div class="tcc-ft-step" id="tcc-ft-step-success" style="display:none;">
            <div class="tcc-ft-card tcc-ft-success-card">
                <div class="tcc-ft-success-icon">🎉</div>
                <h3 class="tcc-ft-success-title"><?php echo $is_logged ? 'Booking Generated!' : 'Query Submitted!'; ?></h3>
                <p class="tcc-ft-success-desc">
                    <?php echo $is_logged
                        ? 'Booking saved successfully. Download or share the itinerary PDF using the buttons below.'
                        : 'Thanks! Our team will be in touch soon. Use the buttons below to open WhatsApp or download your itinerary PDF.'; ?>
                </p>
                <div class="tcc-ft-actions" style="margin-top: 15px; flex-direction: column;">

                    <?php if (!$is_logged): ?>
                        <a href="#" id="tcc-ft-wa-link" target="_blank" class="tcc-ft-btn-main tcc-ft-btn-wa" style="text-decoration:none; display:block; width:100%; padding: 12px; margin-bottom: 5px; text-align:center; box-sizing:border-box;">💬 Open in WhatsApp</a>
                    <?php endif; ?>

                    <div style="display:flex; gap:6px; margin-top:5px; width:100%;">
                        <button type="button" id="tcc-ft-pdf-download" style="flex:1; padding:8px 10px; font-size:13px; font-weight:700; line-height:1.3; color:#fff; background:linear-gradient(135deg,#dc2626 0%,#991b1b 100%); border:none; border-radius:6px; cursor:pointer; box-shadow:0 2px 4px rgba(220,38,38,.25); box-sizing:border-box; touch-action:manipulation;">📥 Download PDF</button>
                        <button type="button" id="tcc-ft-pdf-share" style="display:none; flex:1; padding:8px 10px; font-size:13px; font-weight:700; line-height:1.3; color:#fff; background:linear-gradient(135deg,#8b5cf6 0%,#6d28d9 100%); border:none; border-radius:6px; cursor:pointer; box-shadow:0 2px 4px rgba(139,92,246,.25); box-sizing:border-box; touch-action:manipulation;">📤 Share PDF</button>
                    </div>

                    <?php if ($is_logged): ?>
                        <button type="button" class="tcc-ft-btn-main tcc-btn-resubmit" style="width:100%; padding:12px; margin-top:5px; background:#3b82f6; border-color:#2563eb; color:#fff; text-align:center; box-sizing:border-box;">🔄 Create Another Booking</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
TCC_Frontend_Group::init();