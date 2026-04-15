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

function tcc_render_calculator_form() {
    $master_data = tcc_get_safe_master_data();
    $destinations = array_keys($master_data);

    ob_start(); ?>
    <div class="tcc-wrapper">
        <script>var tccMasterData = <?php echo json_encode($master_data); ?>;</script>

        <form id="tcc-calc-form" class="tcc-form">
            
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
                    <label>Overall Hotel Category</label>
                    <select name="hotel_category" id="calc_hotel_cat" required></select>
                </div>
            </div>

            <div class="tcc-card">
                <div class="tcc-card-title">3. Transportation</div>
                <div class="tcc-form-group">
                    <label>Pickup Location</label>
                    <select name="pickup_location" id="calc_pickup" required></select>
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
                    <div class="night-stay-row tcc-repeater-row" style="flex-wrap:wrap;">
                        <select name="stay_place[]" class="stay_place_dropdown" required style="flex:2;"></select>
                        <div style="flex:2;">
                            <select class="stay_hotel_dropdown" multiple required style="width:100%; height:55px !important; border:1px solid #ccc; border-radius:3px; padding:2px; font-size:12px; background:#fff; outline:none;"></select>
                            <input type="hidden" name="stay_hotel[]" class="stay_hotel_hidden">
                        </div>
                        <input type="number" name="stay_nights[]" placeholder="Nights" class="stay_nights" min="1" required style="flex:1;">
                        <button type="button" class="remove_stay_place tcc-btn-del">X</button>
                        <div class="tcc-hotel-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
                    </div>
                </div>
                <button type="button" id="add_stay_place" class="tcc-btn-secondary" style="margin-top:5px;">+ Add Location</button>
            </div>

            <div class="tcc-card" style="border-color: #bee3f8; background:#f0f9ff;">
                <div class="tcc-card-title" style="color:#0369a1;">5. Adjustments</div>
                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Adjust Profit (₹)</label>
                        <input type="number" name="override_profit" id="calc_override_profit" placeholder="Auto" step="0.01">
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
                            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase;">Per Person</div>
                            <div class="tcc-preview-val" id="live_pp">₹0.00</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase;">Profit</div>
                            <div class="tcc-preview-val tcc-preview-profit" id="live_profit">₹0.00</div>
                        </div>
                    </div>
                    <div class="tcc-preview-sub">
                        <span>Disc: <span id="live_discount">₹0.00</span></span>
                        <span>GST: <span id="live_gst">₹0.00</span></span>
                        <span style="font-weight:bold; color:#000;">Total: <span id="live_total">₹0.00</span></span>
                    </div>
                    <div style="text-align:center; font-size:10px; color:#d84b6b; margin-top:5px; display:none;" id="live_surcharge_info"></div>
                    <div id="live_error" style="color:#dc3232; font-size:12px; margin-top:5px; display:none; text-align:center;"></div>
                </div>
            </div>

            <button type="submit" id="tcc_calculate_btn" class="tcc-btn-primary">Generate Quote Link</button>
        </form>

        <div id="tcc_link_wrapper" style="display:none; text-align:center; padding:15px; background:#d4edda; border-radius:4px; margin-top:15px; border:1px solid #c3e6cb;">
            <h3 style="color:#155724; margin:0 0 8px 0; font-size:15px;">Link Generated!</h3>
            <input type="text" id="tcc_generated_link" readonly style="width:100%; padding:8px; font-size:13px; text-align:center; border-radius:3px; border:1px solid #28a745; margin-bottom:8px;">
            <div class="tcc-grid-2">
                <button type="button" id="tcc_copy_btn" class="tcc-btn-primary" style="margin:0;">Copy</button>
                <a href="#" id="tcc_open_btn" target="_blank" class="tcc-btn-secondary" style="margin:0; line-height:2.2; text-decoration:none;">Open</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function tcc_render_settings_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) return '<div class="tcc-wrapper">Permission Denied.</div>';

    $master_data = tcc_get_safe_master_data();
    $destinations = array_keys($master_data);

    ob_start(); ?>
    <div class="tcc-wrapper tcc-settings-wrapper tcc-form">
        <script>var tccMasterData = <?php echo json_encode($master_data); ?>;</script>
        
        <h3 style="text-align:center; margin-bottom:15px; font-size:16px;">Agency Settings</h3>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header">1. Master Setup <span>&#9660;</span></div>
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
            <div class="tcc-accordion-header">2. Hotel Pricing <span>&#9660;</span></div>
            <div class="tcc-accordion-body">
                <form id="tcc-settings-form">
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group"><label>Destination</label><select name="set_destination" id="set_hotel_dest" required></select></div>
                        <div class="tcc-form-group"><label>Stay Place</label><select name="set_night_stay_place" id="set_hotel_stay" required></select></div>
                    </div>
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group"><label>Category</label><select name="set_hotel_cat" id="set_hotel_cat" required></select></div>
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
                    <button type="submit" class="tcc-btn-primary">Save Hotel</button>
                </form>
            </div>
        </div>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header">3. Transport Pricing <span>&#9660;</span></div>
            <div class="tcc-accordion-body">
                <form id="tcc-transport-settings-form">
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group"><label>Destination</label><select name="set_destination" id="set_trans_dest" required></select></div>
                        <div class="tcc-form-group"><label>Pickup</label><select name="set_pickup_loc" id="set_trans_pickup" required></select></div>
                    </div>
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group"><label>Vehicle <span id="trans_fetch_status" style="color:#007cba; font-weight:normal;"></span></label><select name="set_vehicle" id="set_trans_vehicle" required></select></div>
                        <div class="tcc-form-group"><label>Capacity</label><input type="number" name="set_capacity" id="set_capacity" min="1" required></div>
                    </div>
                    <div class="tcc-form-group"><label>Price/Day (₹)</label><input type="number" name="set_transport_price" id="set_transport_price" step="0.01" required></div>
                    <button type="submit" class="tcc-btn-primary">Save Transport</button>
                </form>
            </div>
        </div>

        <div id="tcc_settings_msg" style="display:none; margin-top:10px; padding:8px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:3px; text-align:center; font-weight:bold;"></div>
    </div>
    <?php
    return ob_get_clean();
}