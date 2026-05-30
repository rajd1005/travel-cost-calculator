<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TCC_FD_Settings {

    public static function init() {
        add_shortcode( 'tcc_fixed_departures_settings', array( __CLASS__, 'render_dashboard' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
    }

    public static function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'tcc_fixed_departures_settings' ) ) {
            wp_enqueue_media();
            wp_enqueue_style( 'quill-snow', 'https://cdn.quilljs.com/1.3.6/quill.snow.css', array(), '1.3.6' );
            wp_enqueue_script( 'quill-js', 'https://cdn.quilljs.com/1.3.6/quill.js', array(), '1.3.6', true );
            wp_enqueue_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', array(), null, true);
            wp_enqueue_script( 'tcc-fd-settings-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/tcc-fd-settings.js', array('jquery'), time(), true );
            wp_localize_script( 'tcc-fd-settings-js', 'tcc_ajax_obj', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'tcc_secure_nonce' )
            ));
        }
    }

    public static function render_dashboard() {
        if ( ! is_user_logged_in() ) return '<div style="padding:40px; text-align:center; color:red;">Access Denied.</div>';

        $master_data = get_option('tcc_master_settings', array());
        $destinations = array_keys($master_data);
        
        ob_start(); ?>
        <div class="tcc-wrapper tcc-form tcc-settings-wrapper">
            <script>var tccMasterData = <?php echo wp_json_encode($master_data); ?>;</script>
            <h2 style="text-align:center; color:#3730a3; border-bottom: 2px solid #c7d2fe; padding-bottom: 10px;">Group Tours (Fixed Departures) Manager</h2>
            
            <form id="tcc-adv-fixed-tour-form">
                
                <div class="tcc-card" style="border-left: 4px solid #4f46e5;">
                    <div style="background:#fff; padding:15px; border-radius:4px; border:1px solid #c7d2fe; margin-bottom:15px;">
                        <label style="color:#3730a3; font-weight:bold; display:block; margin-bottom:5px;">Edit or Delete an Existing Tour</label>
                        <div style="display:flex; gap:10px;">
                            <select id="fd_edit_tour_select" style="flex:1; border-color:#a5b4fc; padding:8px; border-radius:4px;">
                                <option value="">-- Create New Group Tour --</option>
                            </select>
                            <button type="button" id="fd_delete_tour_btn" class="tcc-btn-del" style="display:none; margin:0; background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;">🗑️ Delete</button>
                            <button type="button" id="fd_duplicate_tour_btn" class="tcc-btn-secondary" style="display:none; margin:0; background:#0ea5e9; color:white; border:none;">📄 Duplicate</button>
                        </div>
                    </div>

                    <input type="hidden" id="fd_tour_id" value="">
                    
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group">
                            <label>Tour Name (e.g. Spiti Valley Group Tour)</label>
                            <input type="text" id="fd_tour_name" required>
                        </div>
                        <div class="tcc-form-group">
                            <label>Destination (Unlocks Presets & Categories)</label>
                            <select id="fd_destination" required>
                                <option value="">-- Select --</option>
                                <?php foreach($destinations as $dest): ?><option value="<?php echo esc_attr($dest); ?>"><?php echo esc_html($dest); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="tcc-form-group" style="background:#f8fafc; padding:15px; border:1px dashed #cbd5e1;">
                        <label style="color:#3730a3; font-weight:bold; display:block; margin-bottom:10px;">Departure Dates, Capacities, Categories & Prices</label>
                        <div id="fd_dates_wrapper"></div>
                        <button type="button" id="add_fd_date_btn" class="tcc-btn-secondary" style="margin-top:10px;">+ Add Departure Date</button>
                    </div>
                </div>

                <div class="tcc-card" style="border-left: 4px solid #b93b59;">
                    <div class="tcc-card-title" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Day-wise Itinerary (Drag to Reorder)</span>
                        <select id="fd_itinerary_preset" style="font-weight:normal; font-size:12px; padding:4px 8px; border-radius:3px;">
                            <option value="">-- Load Preset --</option>
                        </select>
                    </div>
                    <div id="fd-day-wise-wrapper"></div>
                    <button type="button" id="fd_add_day_btn" class="tcc-btn-secondary" style="margin-top:10px;">+ Add Day</button>
                </div>

                <div class="tcc-card" style="border-left: 4px solid #0284c7;">
                    <div class="tcc-card-title">Itinerary Routing (Hotels & Multi-Categories)</div>
                    <div id="fd-night-stay-wrapper"></div>
                    <button type="button" id="fd_add_stay_place" class="tcc-btn-secondary" style="margin-top:5px;">+ Add Night Stay Location</button>
                </div>

                <div class="tcc-card" style="border-left: 4px solid #16a34a;">
                    <div class="tcc-card-title">Logistics & Policies</div>
                    <div class="tcc-grid-3">
                        <div class="tcc-form-group"><label>Transport Details</label><input type="text" id="fd_transport_details" placeholder="e.g. Tempo Traveller"></div>
                        <div class="tcc-form-group"><label>Pickup Point</label><input type="text" id="fd_pickup"></div>
                        <div class="tcc-form-group"><label>Drop Point</label><input type="text" id="fd_drop"></div>
                    </div>
                    <div class="tcc-form-group">
                        <label>Operational Head Office Address</label>
                        <textarea id="fd_head_office" rows="2" placeholder="Leave blank to use default address..." style="width: 100%;"></textarea>
                    </div>

                    <?php 
                    $quill_fields = array(
                        'fd_inclusions' => '✅ Inclusions',
                        'fd_exclusions' => '❌ Exclusions',
                        'fd_payment_terms' => '💳 Payment Terms',
                        'fd_important_note' => '📌 Important Note',
                        'fd_why_choose_us' => '🌟 Why Choose Us',
                        'fd_essential_guidelines' => '📋 Essential Travel Guidelines'
                    );
                    foreach($quill_fields as $id => $label): ?>
                        <div class="tcc-form-group" style="margin-bottom: 15px;">
                            <label style="font-weight: bold;"><?php echo $label; ?></label>
                            <div class="tcc-quill-wrapper" style="background:#fff; border:1px solid #cbd5e1; border-radius:4px;">
                                <div class="tcc-quill-editor" id="<?php echo $id; ?>_editor" style="min-height: 100px;"></div>
                                <textarea id="<?php echo $id; ?>" style="display:none;"></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" id="save_fixed_tour_btn" class="tcc-btn-primary" style="width: 100%; font-size: 16px; padding: 15px;">Save Group Tour</button>
                <div id="fd_save_msg" style="display:none; color:green; font-weight:bold; margin-top:10px; text-align:center;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
TCC_FD_Settings::init();