<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'travel_calculator', 'tcc_render_calculator_form' );
add_shortcode( 'travel_calculator_settings', 'tcc_render_settings_dashboard' );

function tcc_get_safe_master_data() {
    $master_data = get_option('tcc_master_settings', array());
    if (empty($master_data) || !is_array($master_data)) {
        $master_data = array(
            'Kashmir' => array(
                'profit_per_person' => 0,
                'pickups' => array('Srinagar', 'Jammu'),
                'stay_places' => array('Srinagar', 'Gulmarg', 'Pahalgam'),
                'vehicles' => array('Innova', 'Tempo Traveler'),
                'hotel_categories' => array('Deluxe', 'Premium'),
                'inclusions' => '',
                'exclusions' => '',
                'payment_terms' => '',
                'seasons' => array()
            )
        );
    }
    return $master_data;
}

function tcc_get_global_settings() {
    return get_option('tcc_global_settings', array(
        'gst' => 5,
        'pt' => 10,
        'pg' => 3
    ));
}

function tcc_render_calculator_form() {
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; color:#dc2626; font-family:sans-serif; background:#fee2e2; border:1px solid #f87171; border-radius:6px; margin:20px 0;"><strong>Access Denied:</strong> Please log in to access the Travel Calculator.</div>';
    }

    $master_data = tcc_get_safe_master_data();
    $global_settings = tcc_get_global_settings();
    $destinations = array_keys($master_data);

    ob_start(); ?>
    <div class="tcc-wrapper">
        <script>
            var tccMasterData = <?php echo json_encode($master_data); ?>;
            var tccGlobalSettings = <?php echo json_encode($global_settings); ?>;
        </script>

        <form id="tcc-calc-form" class="tcc-form">
            
            <div class="tcc-card" style="background:#f8fafc; border-color:#cbd5e1; display:flex; justify-content:space-between; align-items:center; padding:10px 15px;">
                <strong style="color:#334155; font-size:14px;">✏️ Load Quote to Edit:</strong>
                <select id="calc_edit_quote_select" style="max-width:250px; margin:0; font-size:13px;">
                    <option value="">-- Create New Quote --</option>
                </select>
                <input type="hidden" name="edit_quote_id" id="edit_quote_id" value="">
            </div>

            <div class="tcc-card" style="border-left: 4px solid #b93b59;">
                <div class="tcc-card-title">0. Client Details <span style="font-size:11px; font-weight:normal; color:#64748b;">(Optional for Quotation)</span></div>
                <div class="tcc-grid-3">
                    <div class="tcc-form-group">
                        <label>Client Name</label>
                        <input type="text" name="client_name" id="client_name" placeholder="E.g. Rahul Sharma">
                    </div>
                    <div class="tcc-form-group">
                        <label>WhatsApp Number</label>
                        <input type="text" name="client_phone" id="client_phone" placeholder="+91 XXXXX XXXXX">
                    </div>
                    <div class="tcc-form-group">
                        <label>Email</label>
                        <input type="email" name="client_email" id="client_email" placeholder="client@email.com">
                    </div>
                </div>
            </div>

            <div class="tcc-card">
                <div class="tcc-card-title">1. Trip Basics</div>
                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Destination</label>
                        <select name="destination" id="calc_destination" required>
                            <?php foreach($destinations as $dest): ?>
                                <option value="<?php echo esc_attr($dest); ?>"><?php echo esc_html($dest); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tcc-form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" id="start_date" required>
                    </div>
                </div>
                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Adults (>6yr)</label>
                        <input type="number" name="total_pax" id="total_pax" min="1" value="2" required>
                    </div>
                    <div class="tcc-form-group">
                        <label>Child (<6yr)</label>
                        <input type="number" name="child_pax" id="child_pax" min="0" value="0" required>
                    </div>
                </div>
            </div>

            <div class="tcc-card">
                <div class="tcc-card-title">2. Stay Duration & Rooms</div>
                <div class="tcc-grid-3">
                    <div class="tcc-form-group">
                        <label>Total Days</label>
                        <input type="number" name="total_days" id="total_days" value="4" min="1" required>
                        <small style="font-size:10px; color:#64748b;">Nights: <span id="total_nights_display">3</span></small>
                    </div>
                    <div class="tcc-form-group">
                        <label>Rooms</label>
                        <input type="number" name="no_of_rooms" id="no_of_rooms" value="1" min="1" required>
                    </div>
                    <div class="tcc-form-group">
                        <label>Extra Beds</label>
                        <input type="number" name="extra_beds" id="extra_beds" value="0" min="0" required>
                    </div>
                </div>
                <div class="tcc-form-group">
                    <label>Overall Hotel Category <small style="color:#64748b;">(Changes all locations below)</small></label>
                    <select name="hotel_category" id="calc_hotel_cat" required></select>
                </div>
            </div>

            <div class="tcc-card">
                <div class="tcc-card-title">3. Transportation</div>
                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Pickup Location</label>
                        <select name="pickup_location" id="calc_pickup" required></select>
                    </div>
                    <div class="tcc-form-group">
                        <label>Drop Location</label>
                        <select name="drop_location" id="calc_drop" required></select>
                    </div>
                </div>
                <div id="transport-wrapper">
                    <div class="transport-row tcc-repeater-row" style="flex-wrap:wrap;">
                        <select name="transportation[]" class="transport_dropdown" required style="flex:3;"></select>
                        <input type="number" name="transport_qty[]" value="1" min="1" placeholder="Qty" required style="flex:1;">
                        <button type="button" class="remove_transport tcc-btn-del">X</button>
                        <div class="tcc-trans-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
                    </div>
                </div>
                <button type="button" id="add_transport" class="tcc-btn-secondary">+ Add Vehicle</button>
            </div>

            <div class="tcc-card">
                <div class="tcc-card-title">4. Itinerary Routing</div>
                <div id="night-stay-wrapper">
                    <div class="night-stay-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap; gap:5px;">
                        <select name="stay_place[]" class="stay_place_dropdown" required style="flex:2;"></select>
                        <select name="stay_category[]" class="stay_cat_dropdown" required style="flex:1.5;"></select>
                        <div style="flex:3;">
                            <select class="stay_hotel_dropdown" multiple required style="width:100%; height:55px !important; border:1px solid #ccc; border-radius:3px; padding:2px; font-size:12px; background:#fff; outline:none;"></select>
                            <input type="hidden" name="stay_hotel[]" class="stay_hotel_hidden">
                        </div>
                        <input type="number" name="stay_nights[]" placeholder="Nights" value="1" class="stay_nights" min="1" required style="flex:0.8;">
                        <button type="button" class="remove_stay_place tcc-btn-del">X</button>
                        <div class="tcc-hotel-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
                    </div>
                </div>
                <button type="button" id="add_stay_place" class="tcc-btn-secondary" style="margin-top:5px;">+ Add Location</button>
            </div>

            <div class="tcc-card">
                <div class="tcc-card-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>4.5 Day-wise Itinerary (Drag to Reorder)</span>
                    <div style="display:flex; gap:5px;">
                        <select id="itinerary_preset_select" style="width:140px; font-weight:normal;">
                            <option value="">-- Load Preset --</option>
                        </select>
                        <button type="button" id="delete_itinerary_preset" class="tcc-btn-del" style="display:none; padding:4px 8px; margin:0; background:#fee2e2; color:#dc2626; border:1px solid #f87171;" title="Delete Preset">🗑️</button>
                    </div>
                </div>
                
                <div id="day-wise-wrapper" style="margin-bottom: 10px;">
                </div>

                <div style="display:flex; gap:5px; background: #f9f9f9; padding: 8px; border: 1px dashed #ccc; border-radius: 3px;">
                    <input type="text" id="new_preset_name" placeholder="Preset Name (e.g. Kashmir 5D/4N)" style="flex:2; margin:0;">
                    <button type="button" id="save_itinerary_preset" class="tcc-btn-secondary" style="flex:1; margin:0;">Save as Preset</button>
                </div>
                <div id="preset_msg" style="font-size:11px; color:#16a34a; margin-top:5px; display:none; font-weight:bold;">Saved!</div>
            </div>

            <div class="tcc-card" style="border-color: #bee3f8; background:#f0f9ff;">
                <div class="tcc-card-title" style="color:#0369a1;">5. Adjustments</div>
                
                <div style="background:#e0f2fe; padding:10px; border-radius:4px; border:1px solid #bae6fd; margin-bottom:15px; font-size:12px; color:#0369a1;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span>Total Hotel Cost:</span>
                        <strong id="live_total_hotel">₹0.00</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span>Total Cab Cost:</span>
                        <strong id="live_total_trans">₹0.00</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:4px; border-top:1px dashed #93c5fd; font-weight:bold; color:#075985;">
                        <span>Total Actual Cost:</span>
                        <span id="live_actual_cost">₹0.00</span>
                    </div>
                </div>

                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Adjust Profit (₹)</label>
                        <input type="number" name="override_profit" id="calc_override_profit" placeholder="Target Net Profit" step="0.01">
                    </div>
                    <div class="tcc-form-group">
                        <label>Override PP Base (₹)</label>
                        <input type="number" name="manual_pp_override" id="calc_manual_pp_override" placeholder="Auto" step="0.01">
                    </div>
                </div>
                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Discount 1</label>
                        <div style="display:flex; gap:3px;">
                            <select name="discount_1_type" style="flex:1;"><option value="none">Off</option><option value="flat">₹</option><option value="percent">%</option></select>
                            <input type="number" name="discount_1_value" value="0" min="0" step="0.01" style="flex:1;">
                        </div>
                    </div>
                    <div class="tcc-form-group">
                        <label>Discount 2</label>
                        <div style="display:flex; gap:3px;">
                            <select name="discount_2_type" style="flex:1;"><option value="none">Off</option><option value="flat">₹</option><option value="percent">%</option></select>
                            <input type="number" name="discount_2_value" value="0" min="0" step="0.01" style="flex:1;">
                        </div>
                    </div>
                </div>

                <div class="tcc-preview-panel">
                    <div class="tcc-grid-2" style="margin-bottom:0;">
                        <div>
                            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase;">PP Base (Excl GST)</div>
                            <div class="tcc-preview-val" id="live_pp_base" style="font-size:22px; color:#0f172a;">₹0.00</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:11px; color:#0369a1; font-weight:bold; text-transform:uppercase;">Net Profit</div>
                            <div class="tcc-preview-val tcc-preview-profit" id="live_profit">₹0.00</div>
                        </div>
                    </div>
                    
                    <div class="tcc-preview-sub" style="border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-top: 8px; display:flex; justify-content:space-between; gap:5px; flex-wrap:wrap;">
                        <span style="color:#0f172a; font-weight:bold;">PP (Inc GST): <span id="live_pp_inc">₹0.00</span></span>
                        <span>Gross Profit: <span id="live_gross_profit">₹0.00</span></span>
                        <span style="color:#dc2626;"><span id="live_pt_label">PT (<?php echo $global_settings['pt']; ?>%):</span> <span id="live_pt">₹0.00</span></span>
                        <span style="color:#dc2626;"><span id="live_pg_label">PG (<?php echo $global_settings['pg']; ?>%):</span> <span id="live_pg">₹0.00</span></span>
                    </div>

                    <div class="tcc-preview-sub" style="background:#f1f5f9; padding:8px; border-radius:4px; margin-top:8px;">
                        <span>Total Base: <span id="live_total_base">₹0.00</span></span>
                        <span><span id="live_gst_label">GST (<?php echo $global_settings['gst']; ?>%):</span> <span id="live_gst">₹0.00</span></span>
                        <span style="font-weight:900; color:#000; font-size:14px;">Total Value: <span id="live_total">₹0.00</span></span>
                    </div>
                    
                    <div style="text-align:center; font-size:10px; color:#d84b6b; margin-top:5px; display:none;" id="live_surcharge_info"></div>
                    <div id="live_error" style="color:#dc3232; font-size:12px; margin-top:5px; display:none; text-align:center;"></div>
                </div>
            </div>

            <button type="submit" id="tcc_calculate_btn" class="tcc-btn-primary">Generate Quote Link</button>
        </form>

        <div id="tcc_link_wrapper" style="display:none; text-align:center; padding:15px; background:#d4edda; border-radius:4px; margin-top:15px; border:1px solid #c3e6cb;">
            <h3 style="color:#155724; margin:0 0 8px 0; font-size:15px;">Success!</h3>
            <input type="text" id="tcc_generated_link" readonly style="width:100%; padding:8px; font-size:13px; text-align:center; border-radius:3px; border:1px solid #28a745; margin-bottom:8px;">
            <div style="display:grid; grid-template-columns: 1fr 1.5fr 1fr; gap:10px;">
                <button type="button" id="tcc_copy_btn" class="tcc-btn-primary" style="margin:0;">Copy Link</button>
                <button type="button" id="tcc_copy_wa_btn" class="tcc-btn-primary" style="margin:0; background:#25D366; border-color:#128C7E;">WA Text</button>
                <a href="#" id="tcc_open_btn" target="_blank" class="tcc-btn-secondary" style="margin:0; line-height:2.2; text-decoration:none;">Open</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ... (Rest of tcc-shortcodes.php settings dashboard remains completely identical)
function tcc_render_settings_dashboard() {
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; color:#dc2626; font-family:sans-serif; background:#fee2e2; border:1px solid #f87171; border-radius:6px; margin:20px 0;"><strong>Access Denied:</strong> Please log in to access the Settings Dashboard.</div>';
    }

    $master_data = tcc_get_safe_master_data();
    $global_settings = tcc_get_global_settings();
    $destinations = array_keys($master_data);

    ob_start(); ?>
    <div class="tcc-wrapper tcc-settings-wrapper tcc-form">
        <script>
            var tccMasterData = <?php echo json_encode($master_data); ?>;
            var tccGlobalSettings = <?php echo json_encode($global_settings); ?>;
        </script>
        
        <h3 style="text-align:center; margin-bottom:15px; font-size:16px;">Agency Settings</h3>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header" style="background:#fef3c7; color:#92400e; border-color:#fde68a;">1. Global Taxes & Fees <span>&#9660;</span></div>
            <div class="tcc-accordion-body" style="background:#fffbeb;">
                <form id="tcc-global-settings-form">
                    <div class="tcc-grid-3">
                        <div class="tcc-form-group">
                            <label>GST (%)</label>
                            <input type="number" name="global_gst" id="global_gst" step="0.01" value="<?php echo esc_attr($global_settings['gst']); ?>" required>
                        </div>
                        <div class="tcc-form-group">
                            <label>Professional Tax (PT) (%)</label>
                            <input type="number" name="global_pt" id="global_pt" step="0.01" value="<?php echo esc_attr($global_settings['pt']); ?>" required>
                        </div>
                        <div class="tcc-form-group">
                            <label>Payment Gateway (PG) (%)</label>
                            <input type="number" name="global_pg" id="global_pg" step="0.01" value="<?php echo esc_attr($global_settings['pg']); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="tcc-btn-primary" style="background:#d97706; border-color:#b45309;">Save Global Taxes</button>
                </form>
            </div>
        </div>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header">2. Destination Setup <span>&#9660;</span></div>
            <div class="tcc-accordion-body">
                <form id="tcc-master-settings-form">
                    <div class="tcc-form-group">
                        <label>Select / Add Destination</label>
                        <select id="master_dest_select" style="margin-bottom:5px;">
                            <option value="">-- Add New --</option>
                            <?php foreach($destinations as $dest): ?><option value="<?php echo esc_attr($dest); ?>"><?php echo esc_html($dest); ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="master_dest_name" id="master_dest_name" placeholder="Destination Name" required>
                    </div>
                    <div class="tcc-form-group"><label>Def. Profit PP (₹)</label><input type="number" name="master_profit" id="master_profit" step="0.01" value="0"></div>
                    <div class="tcc-form-group"><label>Pickups (Comma Sep)</label><input type="text" name="master_pickups" id="master_pickups" required></div>
                    <div class="tcc-form-group"><label>Stay Places (Comma Sep)</label><input type="text" name="master_stays" id="master_stays" required></div>
                    <div class="tcc-form-group"><label>Vehicles (Comma Sep)</label><input type="text" name="master_vehicles" id="master_vehicles" required></div>
                    <div class="tcc-form-group"><label>Hotel Categories (Comma Sep)</label><input type="text" name="master_hotel_cats" id="master_hotel_cats" required></div>
                    
                    <div class="tcc-form-group" style="margin-top:10px;">
                        <label>Inclusions (One per line)</label>
                        <textarea name="master_inclusions" id="master_inclusions" rows="3" style="width:100%; border:1px solid #ccc; border-radius:3px; padding:6px; font-size:12px;"></textarea>
                    </div>
                    <div class="tcc-form-group" style="margin-top:5px;">
                        <label>Exclusions (One per line)</label>
                        <textarea name="master_exclusions" id="master_exclusions" rows="3" style="width:100%; border:1px solid #ccc; border-radius:3px; padding:6px; font-size:12px;"></textarea>
                    </div>
                    <div class="tcc-form-group" style="margin-top:5px;">
                        <label>Payment Terms (One per line)</label>
                        <textarea name="master_payment_terms" id="master_payment_terms" rows="3" style="width:100%; border:1px solid #ccc; border-radius:3px; padding:6px; font-size:12px;"></textarea>
                    </div>

                    <div class="tcc-form-group" style="margin-top:15px; border-top:1px dashed #ccc; padding-top:10px;">
                        <label>Season Surcharges (% increase on Hotel & Cab)</label>
                        <div id="season-settings-wrapper"></div>
                        <button type="button" id="add_season_btn" class="tcc-btn-secondary">+ Add Season Dates</button>
                    </div>

                    <button type="submit" class="tcc-btn-primary">Save Master Data</button>
                </form>
            </div>
        </div>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header">3. Hotel Pricing <span>&#9660;</span></div>
            <div class="tcc-accordion-body">
                <form id="tcc-settings-form">
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group">
                            <label>Destination <a href="#" class="tcc-rename-master" data-type="destination" data-dropdown="#set_hotel_dest" style="font-size:11px; color:#0284c7; float:right; text-decoration:none;">✏️ Rename</a></label>
                            <select name="set_destination" id="set_hotel_dest" required></select>
                        </div>
                        <div class="tcc-form-group">
                            <label>Stay Place <a href="#" class="tcc-rename-master" data-type="stay_place" data-dropdown="#set_hotel_stay" style="font-size:11px; color:#0284c7; float:right; text-decoration:none;">✏️ Rename</a></label>
                            <select name="set_night_stay_place" id="set_hotel_stay" required></select>
                        </div>
                    </div>
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group">
                            <label>Category <a href="#" class="tcc-rename-master" data-type="hotel_cat" data-dropdown="#set_hotel_cat" style="font-size:11px; color:#0284c7; float:right; text-decoration:none;">✏️ Rename</a></label>
                            <select name="set_hotel_cat" id="set_hotel_cat" required></select>
                        </div>
                        <div class="tcc-form-group">
                            <label>Hotel Name <span id="hotel_fetch_status" style="color:#007cba; font-weight:normal;"></span></label>
                            <select id="hotel_name_dropdown" required></select>
                            <input type="text" name="set_hotel_name" id="set_hotel_name_input" style="display:none; margin-top:5px;" placeholder="New Hotel">
                        </div>
                    </div>
                    <div class="tcc-form-group"><label>Website</label><input type="url" name="set_hotel_website" id="set_hotel_website"></div>
                    <div class="tcc-grid-3">
                        <div class="tcc-form-group"><label>Room (₹)</label><input type="number" name="set_room_price" id="set_room_price" step="0.01" required></div>
                        <div class="tcc-form-group"><label>Ex. Bed (₹)</label><input type="number" name="set_extra_bed" id="set_extra_bed" step="0.01" required></div>
                        <div class="tcc-form-group"><label>Child (₹)</label><input type="number" name="set_child_price" id="set_child_price" step="0.01" required></div>
                    </div>
                    
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button type="submit" class="tcc-btn-primary" style="margin:0; flex:1;">Save Hotel</button>
                        <button type="button" id="edit_hotel_name_btn" class="tcc-btn-secondary" style="margin:0; flex:1; display:none; background:#f0f9ff; color:#0284c7; border-color:#bae6fd;">✏️ Edit Name</button>
                        <button type="button" id="delete_hotel_btn" class="tcc-btn-del" style="margin:0; flex:1; display:none; background:#fee2e2; color:#dc2626; border:1px solid #f87171;">🗑️ Delete</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header">4. Transport Pricing <span>&#9660;</span></div>
            <div class="tcc-accordion-body">
                <form id="tcc-transport-settings-form">
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group">
                            <label>Destination <a href="#" class="tcc-rename-master" data-type="destination" data-dropdown="#set_trans_dest" style="font-size:11px; color:#0284c7; float:right; text-decoration:none;">✏️ Rename</a></label>
                            <select name="set_destination" id="set_trans_dest" required></select>
                        </div>
                        <div class="tcc-form-group">
                            <label>Pickup <a href="#" class="tcc-rename-master" data-type="pickup" data-dropdown="#set_trans_pickup" style="font-size:11px; color:#0284c7; float:right; text-decoration:none;">✏️ Rename</a></label>
                            <select name="set_pickup_loc" id="set_trans_pickup" required></select>
                        </div>
                    </div>
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group">
                            <label>Vehicle <a href="#" class="tcc-rename-master" data-type="vehicle" data-dropdown="#set_trans_vehicle" style="font-size:11px; color:#0284c7; float:right; text-decoration:none;">✏️ Rename</a></label>
                            <select name="set_vehicle" id="set_trans_vehicle" required></select>
                        </div>
                        <div class="tcc-form-group"><label>Capacity</label><input type="number" name="set_capacity" id="set_capacity" min="1" required></div>
                    </div>
                    <div class="tcc-form-group"><label>Price/Day (₹)</label><input type="number" name="set_transport_price" id="set_transport_price" step="0.01" required></div>
                    
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button type="submit" class="tcc-btn-primary" style="margin:0; flex:1;">Save Transport</button>
                        <button type="button" id="delete_transport_btn" class="tcc-btn-del" style="margin:0; flex:1; display:none; background:#fee2e2; color:#dc2626; border:1px solid #f87171;">🗑️ Delete</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header" style="background:#f0fdf4; color:#166534; border-color:#bbf7d0;">5. Manage Bookings & Payments <span>&#9660;</span></div>
            <div class="tcc-accordion-body" style="background:#f8fafc;">
                
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <input type="text" id="pmt_quote_search" placeholder="Search Name, Email, or WA No..." style="flex:1;">
                    <select id="pmt_quote_select" style="flex:2;">
                        <option value="">-- Select Quote/Booking --</option>
                    </select>
                </div>

                <div id="pmt_quote_actions" style="display:none; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
                    <button type="button" id="pmt_view_quote_btn" class="tcc-btn-secondary" style="margin:0; flex:1; min-width:90px; background:#f0f9ff; color:#0284c7; border-color:#bae6fd;">👁️ View</button>
                    <button type="button" id="pmt_copy_quote_btn" class="tcc-btn-secondary" style="margin:0; flex:1; min-width:90px; background:#f0fdf4; color:#16a34a; border-color:#bbf7d0;">📋 Link</button>
                    <button type="button" id="pmt_edit_quote_btn" class="tcc-btn-secondary" style="margin:0; flex:1; min-width:110px;">✏️ Client Info</button>
                    <button type="button" id="pmt_full_edit_btn" class="tcc-btn-secondary" style="margin:0; flex:1; min-width:110px; background:#fffbeb; color:#b45309; border-color:#fde68a;">🛠️ Full Edit</button>
                    <button type="button" id="pmt_delete_quote_btn" class="tcc-btn-del" style="margin:0; flex:1; min-width:90px; background:#fee2e2; color:#dc2626; border-color:#f87171;">🗑️ Delete</button>
                </div>

                <div id="pmt_edit_client_wrapper" style="display:none; background:#fff; padding:15px; border:1px solid #cbd5e1; border-radius:4px; margin-bottom:15px;">
                    <h4 style="margin:0 0 10px 0; color:#334155;">Update Client Details Only</h4>
                    <div class="tcc-grid-3">
                        <input type="text" id="edit_c_name" placeholder="Client Name">
                        <input type="text" id="edit_c_phone" placeholder="WA Number">
                        <input type="email" id="edit_c_email" placeholder="Email">
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" id="save_edit_client_btn" class="tcc-btn-primary" style="margin:0; flex:1;">Save Details</button>
                        <button type="button" id="cancel_edit_client_btn" class="tcc-btn-secondary" style="margin:0; flex:1;">Cancel</button>
                    </div>
                </div>

                <div id="pmt_dashboard" style="display:none; margin-top:15px; border-top:2px solid #e2e8f0; padding-top:15px;">
                    <div class="tcc-grid-3" style="background:#fff; padding:10px; border-radius:4px; border:1px solid #e2e8f0; margin-bottom:15px; text-align:center;">
                        <div>
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Value</div>
                            <div id="pmt_total_val" style="font-weight:bold; font-size:16px; color:#0f172a;">₹0.00</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Received</div>
                            <div id="pmt_received_val" style="font-weight:bold; font-size:16px; color:#16a34a;">₹0.00</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Balance Due</div>
                            <div id="pmt_balance_val" style="font-weight:bold; font-size:16px; color:#dc2626;">₹0.00</div>
                        </div>
                    </div>

                    <div id="pmt_missing_client_msg" style="display:none; background:#fffbeb; color:#92400e; padding:10px; border:1px solid #fde68a; border-radius:4px; margin-bottom:15px; text-align:center;">
                        ⚠️ <strong>Client Details Missing!</strong> You must click "✏️ Client Info" above and add the Client Name and Phone number before you can record transactions.
                    </div>

                    <div id="tcc-add-payment-wrapper">
                        <h4 style="margin:0 0 10px 0; font-size:14px; color:#334155;">Record New Transaction</h4>
                        <form id="tcc-add-payment-form" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                            <input type="date" id="pmt_date" required style="flex:1; min-width:120px;" title="Transaction Date">
                            <input type="number" id="pmt_amount" placeholder="Amount (₹)" step="0.01" min="1" required style="flex:1; min-width:100px;">
                            <select id="pmt_method" required style="flex:1; min-width:120px;">
                                <option value="UPI">UPI</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Refund" style="color:red; font-weight:bold;">Refund / Cancellation</option>
                            </select>
                            <input type="text" id="pmt_ref" placeholder="Txn Ref / Details" style="flex:2; min-width:150px;">
                            <button type="submit" class="tcc-btn-primary" style="margin:0; flex:1; min-width:100px;">Add Record</button>
                        </form>
                    </div>

                    <h4 style="margin:0 0 10px 0; font-size:14px; color:#334155;">Transaction History</h4>
                    <div id="pmt_history_table" style="background:#fff; border:1px solid #e2e8f0; border-radius:4px; overflow:hidden;">
                    </div>
                </div>
            </div>
        </div>

        <div id="tcc_settings_msg" style="display:none; margin-top:10px; padding:8px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:3px; text-align:center; font-weight:bold;"></div>
    </div>
    <?php
    return ob_get_clean();
}