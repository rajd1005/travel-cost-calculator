<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'travel_calculator', 'tcc_render_calculator_form' );
add_shortcode( 'travel_calculator_settings', 'tcc_render_settings_dashboard' );

function tcc_get_safe_master_data() {
    $master_data = get_option('tcc_master_settings', array());
    if (empty($master_data) || !is_array($master_data)) {
        $master_data = array(
            'Kashmir' => array(
                'profit_type' => 'flat',
                'profit_per_person' => 0,
                'profit_tiers' => array(),
                'pickups' => array('Srinagar', 'Jammu'),
                'stay_places' => array('Srinagar', 'Gulmarg', 'Pahalgam'),
                'vehicles' => array('Innova', 'Tempo Traveler'),
                'hotel_categories' => array('Deluxe', 'Premium'),
                'inclusions' => '',
                'exclusions' => '',
                'payment_terms' => '',
                'dest_note' => '',
                'company_details' => '',
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
        'pg' => 3,
        'company_banner' => ''
    ));
}

// --- INTERCONNECTIVITY AJAX SYNC ---
add_action('wp_ajax_tcc_get_sync_data', 'tcc_ajax_get_sync_data');
function tcc_ajax_get_sync_data() {
    wp_send_json_success(array(
        'master' => tcc_get_safe_master_data(),
        'global' => tcc_get_global_settings()
    ));
}
// -----------------------------------

function tcc_render_calculator_form() {
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; color:#dc2626; font-family:sans-serif; background:#fee2e2; border:1px solid #f87171; border-radius:6px; margin:20px 0;"><strong>Access Denied:</strong> Please log in to access the Travel Calculator.</div>';
    }

    // Ensures the WordPress media uploader is loaded
    wp_enqueue_media();

    $master_data = tcc_get_safe_master_data();
    $global_settings = tcc_get_global_settings();
    $destinations = array_keys($master_data);
    
    // Default 15 days from today
    $default_start_date = date('Y-m-d', strtotime('+15 days'));

    ob_start(); ?>
    <div class="tcc-wrapper">
        <script>
            var tccMasterData = <?php echo json_encode($master_data); ?>;
            var tccGlobalSettings = <?php echo json_encode($global_settings); ?>;
        </script>

        <div class="tcc-accordion" style="margin-bottom: 20px;">
            <div class="tcc-accordion-header" style="background:#f0fdf4; color:#166534; border-color:#bbf7d0; cursor:pointer; padding:12px 15px; border-radius:4px; font-weight:bold; display:flex; justify-content:space-between; border:1px solid #bbf7d0;">
                <span>💳 Manage Bookings & Payments</span>
                <span>&#9660;</span>
            </div>
            <div class="tcc-accordion-body" style="background:#f8fafc; display:none; padding:15px; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 4px 4px;">
                
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
    
    <button type="button" id="pmt_duplicate_quote_btn" class="tcc-btn-secondary" style="margin:0; flex:1; min-width:100px; background:#fffbeb; color:#d97706; border-color:#fde68a;">📑 Duplicate</button>
    
    <button type="button" id="pmt_load_edit_btn" class="tcc-btn-primary" style="margin:0; flex:1; min-width:120px;">📥 Load to Editor</button>

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
                    
                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:10px; background:#fff; padding:10px; border-radius:4px; border:1px solid #e2e8f0; margin-bottom:10px; text-align:center;">
                        <div style="flex:1; min-width:80px;">
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Value</div>
                            <div id="pmt_total_val" style="font-weight:bold; font-size:15px; color:#0f172a;">₹0.00</div>
                        </div>
                        <div style="flex:1; min-width:80px;">
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Net Profit</div>
                            <div id="pmt_profit_val" style="font-weight:bold; font-size:15px; color:#0ea5e9;">₹0.00</div>
                        </div>
                        <div style="flex:1; min-width:80px;">
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Received</div>
                            <div id="pmt_received_val" style="font-weight:bold; font-size:15px; color:#16a34a;">₹0.00</div>
                        </div>
                        <div style="flex:1; min-width:80px;">
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Balance Due</div>
                            <div id="pmt_balance_val" style="font-weight:bold; font-size:15px; color:#dc2626;">₹0.00</div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:10px; border-radius:4px; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                        <div>
                            <strong style="font-size:13px; color:#334155;">Post-Quote Flat Discount (₹):</strong>
                            <p style="font-size:11px; color:#64748b; margin:0;">Given to customer after quote generation.</p>
                        </div>
                        <div style="display:flex; gap:5px;">
                            <input type="number" id="pmt_post_discount" placeholder="0" min="0" step="0.01" style="width:100px; padding:4px 8px; border:1px solid #ccc; border-radius:3px;">
                            <button type="button" id="pmt_save_discount_btn" class="tcc-btn-secondary" style="margin:0; padding:4px 10px;">Apply</button>
                        </div>
                    </div>

                    <div id="pmt_vendor_direct_wrapper" style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:10px; border-radius:4px; margin-bottom:15px; display:none; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-size:12px; display:block; margin-bottom:4px;"><strong>If Paid Direct to Vendor</strong><br><small>(Excludes GST, PT & PG)</small></span>
                            <div style="font-size:11px; color:#16a34a;">Potential Tax Waiver: <strong id="pmt_vendor_waiver_val">₹0.00</strong></div>
                        </div>
                        <div id="pmt_vendor_balance_val" style="font-weight:bold; font-size:18px; color:#b45309;">₹0.00</div>
                    </div>

                    <div id="pmt_missing_client_msg" style="display:none; background:#fffbeb; color:#92400e; padding:10px; border:1px solid #fde68a; border-radius:4px; margin-bottom:15px; text-align:center;">
                        ⚠️ <strong>Client Details Missing!</strong> You must click "✏️ Client Info" above and add the Client Name and Phone number before you can record transactions.
                    </div>

                    <div id="tcc-add-payment-wrapper">
                        <h4 style="margin:0 0 10px 0; font-size:14px; color:#334155;">Record New Transaction</h4>
                        <form id="tcc-add-payment-form" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                            <input type="hidden" id="pmt_edit_id" value="">
                            <input type="date" id="pmt_date" required style="flex:1; min-width:120px;" title="Transaction Date">
                            <input type="number" id="pmt_amount" placeholder="Amount (₹) *" step="0.01" min="1" required style="flex:1; min-width:100px;">
                            <input type="number" id="pmt_pg_fee" step="0.01" min="0" placeholder="PG Fee (₹) *" required style="padding:8px 12px; border-radius:4px; border:1px solid #ccc; flex:1; min-width:110px;" title="Actual PG fee deducted (Type 0 if none)">
                            <select id="pmt_method" required style="flex:1; min-width:120px;">
                                <option value="UPI">UPI</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Direct to Vendor" style="color:#d97706; font-weight:bold;">Direct to Vendor</option>
                                <option value="Refund" style="color:red; font-weight:bold;">Refund / Cancellation</option>
                            </select>
                            <input type="text" id="pmt_ref" placeholder="Txn Ref / Details" style="flex:2; min-width:150px;">
                            <button type="submit" class="tcc-btn-primary" style="margin:0; flex:1; min-width:100px;">Add Record</button>
                            <button type="button" id="pmt_cancel_edit_btn" class="tcc-btn-secondary" style="display:none; margin:0; flex:0.5; min-width:80px;">Cancel</button>
                        </form>
                    </div>

                    <h4 style="margin:0 0 10px 0; font-size:14px; color:#334155;">Transaction History</h4>
                    <div id="pmt_history_table" style="background:#fff; border:1px solid #e2e8f0; border-radius:4px; overflow-x:auto;">
                    </div>
                </div>
            </div>
        </div>

        <form id="tcc-calc-form" class="tcc-form">
            
            <input type="hidden" name="edit_quote_id" id="edit_quote_id" value="">

            <div id="tcc-step-1" class="tcc-step active">
                
                <div class="tcc-card" style="border-left: 4px solid #b93b59;">
                    <div class="tcc-card-title tcc-step-accordion-header" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
                        <span>1. Trip Basics & Client Details</span>
                        <span class="tcc-acc-icon" style="color:#b93b59; font-size:12px;">▼</span>
                    </div>
                    
                    <div class="tcc-step-accordion-body" style="display:none; margin-top:10px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                        <div class="tcc-grid-3" style="margin-bottom: 10px;">
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
                                <label>Start Date <small style="color:#64748b;">(Optional)</small></label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo $default_start_date; ?>">
                            </div>
                        </div>
                        <div class="tcc-grid-3">
                            <div class="tcc-form-group">
                                <label>Adults (>12yr)</label>
                                <div class="tcc-qty-wrapper">
                                    <button type="button" class="tcc-qty-btn minus">-</button>
                                    <input type="number" name="total_pax" id="total_pax" min="1" value="" placeholder="Adults" required>
                                    <button type="button" class="tcc-qty-btn plus">+</button>
                                </div>
                            </div>
                            <div class="tcc-form-group">
                                <label>Child (6-12yr)</label>
                                <div class="tcc-qty-wrapper">
                                    <button type="button" class="tcc-qty-btn minus">-</button>
                                    <input type="number" name="child_6_12_pax" id="child_6_12_pax" min="0" value="0" required>
                                    <button type="button" class="tcc-qty-btn plus">+</button>
                                </div>
                            </div>
                            <div class="tcc-form-group">
                                <label>Infant (<6yr)</label>
                                <div class="tcc-qty-wrapper">
                                    <button type="button" class="tcc-qty-btn minus">-</button>
                                    <input type="number" name="child_pax" id="child_pax" min="0" value="0" required>
                                    <button type="button" class="tcc-qty-btn plus">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="tcc-grid-3" style="border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                            <div class="tcc-form-group">
                                <label>Total Days</label>
                                <div class="tcc-qty-wrapper">
                                    <button type="button" class="tcc-qty-btn minus">-</button>
                                    <input type="number" name="total_days" id="total_days" value="4" min="1" required>
                                    <button type="button" class="tcc-qty-btn plus">+</button>
                                </div>
                                <small style="font-size:10px; color:#64748b;">Nights: <span id="total_nights_display">3</span></small>
                            </div>
                            <div class="tcc-form-group">
                                <label>Rooms</label>
                                <div class="tcc-qty-wrapper">
                                    <button type="button" class="tcc-qty-btn minus">-</button>
                                    <input type="number" name="no_of_rooms" id="no_of_rooms" value="1" min="1" required>
                                    <button type="button" class="tcc-qty-btn plus">+</button>
                                </div>
                            </div>
                            <div class="tcc-form-group">
                                <label>Extra Beds</label>
                                <div class="tcc-qty-wrapper">
                                    <button type="button" class="tcc-qty-btn minus">-</button>
                                    <input type="number" name="extra_beds" id="extra_beds" value="0" min="0" required>
                                    <button type="button" class="tcc-qty-btn plus">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="tcc-form-group">
                            <label>Overall Hotel Category <small style="color:#64748b;">(Changes all locations below)</small></label>
                            <select name="hotel_category" id="calc_hotel_cat" required></select>
                        </div>
                    </div>
                </div>

                <div class="tcc-card">
                    <div class="tcc-card-title" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>2. Day-wise Itinerary (Drag to Reorder)</span>
                        <div style="display:flex; gap:5px;">
                            <select id="itinerary_preset_select" style="width:140px; font-weight:normal;">
                                <option value="">-- Load Preset --</option>
                            </select>
                            <button type="button" id="delete_itinerary_preset" class="tcc-btn-del" style="display:none; padding:4px 8px; margin:0; background:#fee2e2; color:#dc2626; border:1px solid #f87171;" title="Delete Preset">🗑️</button>
                        </div>
                    </div>
                    
                    <div id="day-wise-wrapper" style="margin-bottom: 10px;"></div>

                    <div style="display:flex; gap:5px; background: #f9f9f9; padding: 8px; border: 1px dashed #ccc; border-radius: 3px;">
                        <input type="text" id="new_preset_name" placeholder="Preset Name (e.g. Kashmir 5D/4N)" style="flex:2; margin:0;">
                        <button type="button" id="save_itinerary_preset" class="tcc-btn-secondary" style="flex:1; margin:0;">Save as Preset</button>
                    </div>
                    <div id="preset_msg" style="font-size:11px; color:#16a34a; margin-top:5px; display:none; font-weight:bold;">Saved!</div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:15px;">
                    <button type="button" id="tcc_quick_wa_btn" class="tcc-btn-secondary" style="flex:1; background:#25D366; color:#fff; border-color:#128C7E; font-size:11px !important;">📱 Summary</button>
                    <button type="button" id="tcc_copy_itinerary_btn" class="tcc-btn-secondary" style="flex:1; font-size:11px !important; background:#fff;">📋 Itinerary</button>
                    <button type="button" id="tcc_copy_hotels_btn" class="tcc-btn-secondary" style="flex:1; font-size:11px !important; background:#fff;">🏨 Hotels</button>
                    <button type="button" id="tcc_copy_inclusions_btn" class="tcc-btn-secondary" style="flex:1; font-size:11px !important; background:#fff;">✅ Inclusions</button>
                    <button type="button" id="tcc_copy_exclusions_btn" class="tcc-btn-secondary" style="flex:1; font-size:11px !important; background:#fff;">❌ Exclusions</button>
                    <button type="button" id="tcc_copy_payment_btn" class="tcc-btn-secondary" style="flex:1; font-size:11px !important; background:#fff;">💳 Payments</button>
                </div>

                <div class="tcc-card">
                    <div class="tcc-card-title tcc-step-accordion-header" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
                        <span>3. Transportation</span>
                        <span class="tcc-acc-icon" style="color:#b93b59; font-size:12px;">▼</span>
                    </div>
                    
                    <div class="tcc-step-accordion-body" style="display:none; margin-top:10px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                        <div class="tcc-grid-2">
                            <div class="tcc-form-group">
                                <label>Pickup Location</label>
                                <div style="display:flex; gap:4px;">
                                    <select name="pickup_location" id="calc_pickup" required style="flex:1;">
                                        <option value="">-- Select --</option>
                                    </select>
                                    <input type="text" name="pickup_custom" id="calc_pickup_custom" placeholder="Custom Name (Opt.)" style="flex:1;">
                                </div>
                            </div>
                            <div class="tcc-form-group">
                                <label>Drop Location</label>
                                <div style="display:flex; gap:4px;">
                                    <select name="drop_location" id="calc_drop" required style="flex:1;">
                                        <option value="">-- Select --</option>
                                    </select>
                                    <input type="text" name="drop_custom" id="calc_drop_custom" placeholder="Custom Name (Opt.)" style="flex:1;">
                                </div>
                            </div>
                        </div>
                        <div id="transport-wrapper"></div>
                        <button type="button" id="add_transport" class="tcc-btn-secondary">+ Add Vehicle</button>
                    </div>
                </div>

            </div>

            <div id="tcc-step-2" class="tcc-step">

                <div class="tcc-card">
                    <div class="tcc-card-title">4. Itinerary Routing (Hotels)</div>
                    <div id="night-stay-wrapper"></div>
                    <button type="button" id="add_stay_place" class="tcc-btn-secondary" style="margin-top:5px;">+ Add Location</button>
                </div>
                
                <div class="tcc-card">
                    <div class="tcc-card-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span>5. Trip Add-ons <small style="font-size:11px; font-weight:normal; color:#64748b;">(Cost added to total)</small></span>
                        <div style="display:flex; gap:5px;">
                            <select id="addon_preset_select" style="font-size:11px; padding:2px; font-weight:normal; max-width: 140px;">
                                <option value="">-- Saved Add-ons --</option>
                            </select>
                            <button type="button" id="insert_addon_preset" class="tcc-btn-secondary" style="display:none; padding:2px 6px; font-size:11px;" title="Add to Trip">+ Add</button>
                            <button type="button" id="delete_addon_preset" class="tcc-btn-del" style="display:none; padding:2px 6px; font-size:11px;" title="Delete from Library">🗑️</button>
                        </div>
                    </div>
                    
                    <div class="tcc-form-group" style="margin-bottom:0;">
                        <div id="addons-wrapper"></div>
                        <button type="button" id="add_addon_btn" class="tcc-btn-secondary" style="margin-top:5px; font-size:11px; padding:4px 8px;">+ Custom Add-on</button>
                        <div id="addon_preset_msg" style="font-size:10px; color:#16a34a; margin-top:3px; display:none; font-weight:bold;"></div>
                    </div>
                </div>

                <div class="tcc-card">
                    <div class="tcc-card-title tcc-step-accordion-header" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; margin-bottom:0;">
                        <span>6. Terms & Policies (Editable for Quote)</span>
                        <span class="tcc-acc-icon" style="color:#b93b59; font-size:12px;">▼</span>
                    </div>
                    <div class="tcc-step-accordion-body" style="display:none; margin-top:10px; border-top:1px dashed #e2e8f0; padding-top:10px;">
                        
                        <div class="tcc-form-group">
                            <label>Inclusions</label>
                            <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                                <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                    <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                    <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                                </div>
                                <div class="tcc-rte-editor" id="quote_inclusions_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                                <textarea name="quote_inclusions" id="quote_inclusions" style="display:none;"></textarea>
                            </div>
                        </div>

                        <div class="tcc-form-group" style="margin-top:10px;">
                            <label>Exclusions</label>
                            <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                                <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                    <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                    <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                                </div>
                                <div class="tcc-rte-editor" id="quote_exclusions_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                                <textarea name="quote_exclusions" id="quote_exclusions" style="display:none;"></textarea>
                            </div>
                        </div>

                        <div class="tcc-form-group" style="margin-top:10px;">
                            <label>Payment Terms</label>
                            <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                                <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                    <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                    <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                                </div>
                                <div class="tcc-rte-editor" id="quote_payment_terms_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                                <textarea name="quote_payment_terms" id="quote_payment_terms" style="display:none;"></textarea>
                            </div>
                        </div>

                        <div class="tcc-form-group" style="margin-top:10px;">
                            <label>Destination Note (Optional)</label>
                            <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                                <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                    <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                    <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                                </div>
                                <div class="tcc-rte-editor" id="quote_dest_note_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                                <textarea name="quote_dest_note" id="quote_dest_note" style="display:none;"></textarea>
                            </div>
                        </div>

                        <div class="tcc-form-group" style="margin-top:10px;">
                            <label>Company Details</label>
                            <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                                <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                    <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                    <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                    <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                                </div>
                                <div class="tcc-rte-editor" id="quote_company_details_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                                <textarea name="quote_company_details" id="quote_company_details" style="display:none;"></textarea>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="tcc-step-nav">
                <button type="button" id="tcc_prev_step" class="tcc-btn-secondary" style="margin:0 !important; min-width:100px;">&laquo; Previous</button>
                <div id="tcc_step_indicator" style="font-weight:bold; font-size:13px; color:#64748b;">Step 1 of 2</div>
                <button type="button" id="tcc_next_step" class="tcc-btn-primary" style="margin:0 !important; min-width:100px;">Next &raquo;</button>
            </div>

            <div class="tcc-card" style="border-color: #bee3f8; background:#f0f9ff; margin-top:20px;">
                <div class="tcc-card-title" style="color:#0369a1;">Final Adjustments & Overview</div>

                <div style="background:#e0f2fe; padding:10px; border-radius:4px; border:1px solid #bae6fd; margin-bottom:15px; font-size:12px; color:#0369a1;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span>Total Hotel Cost:</span>
                        <strong id="live_total_hotel">₹0.00</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span>Total Cab Cost:</span>
                        <strong id="live_total_trans">₹0.00</strong>
                    </div>
                    <div id="live_addons_row" style="display:none; justify-content:space-between; margin-bottom:4px;">
                        <span>Add-ons Cost:</span>
                        <strong id="live_total_addons">₹0.00</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:4px; border-top:1px dashed #93c5fd; font-weight:bold; color:#075985;">
                        <span>Total Actual Cost:</span>
                        <span id="live_actual_cost">₹0.00</span>
                    </div>
                </div>

                <div class="tcc-grid-2">
                    <div class="tcc-form-group">
                        <label>Net Profit (₹)</label>
                        <input type="number" name="override_profit" id="calc_override_profit" placeholder="Target Net Profit" step="0.01">
                    </div>
                    <div class="tcc-form-group">
                        <label>Override PP (Inc GST) (₹)</label>
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
                <button type="button" id="tcc_copy_wa_btn" class="tcc-btn-primary" style="margin:0; background:#25D366; border-color:#128C7E;">WA Full Text</button>
                <a href="#" id="tcc_open_btn" target="_blank" class="tcc-btn-secondary" style="margin:0; line-height:2.2; text-decoration:none;">Open</a>
            </div>
        </div>

        <style>
            .tcc-qty-wrapper { display: flex; align-items: stretch; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; background: #fff; height: 32px; }
            .tcc-qty-wrapper input[type="number"] { flex: 1; width: 100%; min-width: 30px; text-align: center; border: none !important; border-radius: 0 !important; margin: 0 !important; -moz-appearance: textfield; padding: 4px 2px !important; outline: none; font-size: 13px; box-shadow: none !important; }
            .tcc-qty-wrapper input[type="number"]::-webkit-inner-spin-button, .tcc-qty-wrapper input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
            .tcc-qty-btn { width: 28px; background: #f1f5f9; border: none; cursor: pointer; font-weight: bold; font-size: 16px; color: #475569; display: flex; align-items: center; justify-content: center; padding: 0; touch-action: manipulation; transition: 0.1s; }
            .tcc-qty-btn:hover { background: #e2e8f0; color: #0f172a; }
            .tcc-qty-btn.minus { border-right: 1px solid #cbd5e1; }
            .tcc-qty-btn.plus { border-left: 1px solid #cbd5e1; }

            #tcc-notes-toggle {
                position: fixed; bottom: 30px; right: 30px; background: #b93b59; color: #fff; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 9999; border: none; padding: 0; outline: none; touch-action: manipulation;
            }
            #tcc-notes-toggle:hover { background: #9d314b; }
            #tcc-notes-modal-content {
                position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; width: 350px; max-width: 94%; border-radius: 6px; display: flex; flex-direction: column; max-height: 85vh; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            }
            .tcc-note-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            @media (max-width: 600px) {
                #tcc-notes-toggle { bottom: 15px; right: 15px; width: 46px; height: 46px; font-size: 20px; }
                #tcc-notes-modal-content { width: 92%; max-height: 90vh; }
            }
        </style>

        <button type="button" id="tcc-notes-toggle" title="Quick Notes">📝</button>

        <div id="tcc-notes-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:10000; touch-action: manipulation;">
            <div id="tcc-notes-modal-content">
                <div style="padding:10px 12px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border-radius:6px 6px 0 0;">
                    <h3 style="margin:0; font-size:14px; color:#0f172a; display:flex; align-items:center; gap:6px;">📝 Quick Notes</h3>
                    <button type="button" id="tcc-notes-close" style="cursor:pointer; font-size:22px; color:#64748b; background:none; border:none; padding:0; line-height:1;">&times;</button>
                </div>
                
                <div style="padding:8px 12px; background:#f1f5f9; border-bottom:1px solid #e2e8f0; display:flex; gap:8px; align-items:center;">
                    <label style="font-size:11px; color:#475569; font-weight:bold; margin:0;">Filter:</label>
                    <select id="tcc-notes-filter" style="flex:1; padding:4px 6px; font-size:11px; border-radius:3px; border:1px solid #cbd5e1; margin:0; outline:none;">
                        <option value="All">All Groups</option>
                    </select>
                </div>

                <div style="padding:12px; overflow-y:auto; flex:1; min-height:120px; background:#f8fafc;" id="tcc-notes-list"></div>

                <div style="padding:12px; border-top:1px solid #e2e8f0; background:#fff; border-radius:0 0 6px 6px;">
                    <input type="text" id="tcc-new-note-group" placeholder="Group (e.g. Leads)" list="tcc-note-groups-list" value="General" style="width:100%; border:1px solid #cbd5e1; border-radius:3px; padding:6px; font-size:11px; margin-bottom:6px; outline:none;">
                    <datalist id="tcc-note-groups-list"></datalist>
                    
                    <textarea id="tcc-new-note-text" rows="2" style="width:100%; border:1px solid #cbd5e1; border-radius:3px; padding:6px; font-size:12px; margin-bottom:8px; resize:vertical; outline:none;" placeholder="Type a new note here..."></textarea>
                    
                    <input type="hidden" id="tcc-edit-note-id" value="">
                    <div style="display:flex; justify-content:space-between; gap:6px;">
                        <button type="button" id="tcc-cancel-edit-note" class="tcc-btn-secondary" style="display:none; margin:0; padding:5px 10px; font-size:11px; flex:1;">Cancel</button>
                        <button type="button" id="tcc-save-note-btn" class="tcc-btn-primary" style="margin:0; padding:5px 10px; font-size:11px; flex:2;">Save Note</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

function tcc_render_settings_dashboard() {
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; color:#dc2626; font-family:sans-serif; background:#fee2e2; border:1px solid #f87171; border-radius:6px; margin:20px 0;"><strong>Access Denied:</strong> Please log in to access the Settings Dashboard.</div>';
    }

    wp_enqueue_media(); // Ensure media uploader is ready for the banner

    $master_data = tcc_get_safe_master_data();
    $global_settings = tcc_get_global_settings();
    $destinations = array_keys($master_data);

    ob_start(); ?>
    <div class="tcc-wrapper tcc-settings-wrapper tcc-form">
        <script>
            var tccMasterData = <?php echo json_encode($master_data); ?>;
            var tccGlobalSettings = <?php echo json_encode($global_settings); ?>;
            
            // Inline JS to handle WP Media Uploader for the Banner
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function($){
                    $('#upload_banner_btn').click(function(e) {
                        e.preventDefault();
                        var image = wp.media({ title: 'Upload Company Banner', multiple: false }).open()
                        .on('select', function(e){
                            var uploaded_image = image.state().get('selection').first();
                            var image_url = uploaded_image.toJSON().url;
                            $('#global_banner').val(image_url);
                        });
                    });
                });
            }
        </script>
        
        <h3 style="text-align:center; margin-bottom:15px; font-size:16px;">Agency Settings</h3>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header" style="background:#fef3c7; color:#92400e; border-color:#fde68a;">1. Global Settings & Taxes <span>&#9660;</span></div>
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
                    <div class="tcc-form-group">
                        <label>Company Banner Image (Shows at the bottom of Quote/PDF)</label>
                        <div style="display:flex; gap:10px;">
                            <input type="url" name="global_banner" id="global_banner" value="<?php echo esc_attr($global_settings['company_banner']); ?>" style="flex:1;" placeholder="Image URL">
                            <button type="button" class="tcc-btn-secondary" id="upload_banner_btn" style="margin:0;">Choose Image</button>
                        </div>
                    </div>
                    <button type="submit" class="tcc-btn-primary" style="background:#d97706; border-color:#b45309;">Save Global Settings</button>
                </form>
            </div>
        </div>

        <div class="tcc-accordion">
            <div class="tcc-accordion-header">2. Destination Setup <span>&#9660;</span></div>
            <div class="tcc-accordion-body">
                <form id="tcc-master-settings-form">
                    <div class="tcc-grid-2">
                        <div class="tcc-form-group">
                            <label>Select / Add Destination</label>
                            <select id="master_dest_select" style="margin-bottom:5px;">
                                <option value="">-- Add New --</option>
                                <?php foreach($destinations as $dest): ?><option value="<?php echo esc_attr($dest); ?>"><?php echo esc_html($dest); ?></option><?php endforeach; ?>
                            </select>
                            <input type="text" name="master_dest_name" id="master_dest_name" placeholder="Destination Name" required>
                        </div>
                        
                        <div class="tcc-form-group">
                            <label>Profit Setup</label>
                            <select name="master_profit_type" id="master_profit_type" style="margin-bottom:5px;">
                                <option value="flat">Flat Profit PP (₹)</option>
                                <option value="percent">Tiered Percentage (%)</option>
                            </select>
                            
                            <div id="master_profit_flat_wrapper">
                                <input type="number" name="master_profit" id="master_profit" step="0.01" value="0" placeholder="Def. Profit PP (₹)">
                            </div>
                            
                            <div id="master_profit_tier_wrapper" style="display:none; margin-top:5px; padding:8px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:4px;">
                                <label style="font-size:11px; margin-bottom:5px; display:block;">Tiered Profit (%) based on Adults</label>
                                <div id="profit-tiers-wrapper"></div>
                                <button type="button" id="add_profit_tier_btn" class="tcc-btn-secondary" style="padding:2px 8px; font-size:11px; margin-top:5px;">+ Add Tier</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tcc-form-group"><label>Pickups (Comma Sep)</label><input type="text" name="master_pickups" id="master_pickups" required></div>
                    <div class="tcc-form-group"><label>Stay Places (Comma Sep)</label><input type="text" name="master_stays" id="master_stays" required></div>
                    <div class="tcc-form-group"><label>Vehicles (Comma Sep)</label><input type="text" name="master_vehicles" id="master_vehicles" required></div>
                    <div class="tcc-form-group"><label>Hotel Categories (Comma Sep)</label><input type="text" name="master_hotel_cats" id="master_hotel_cats" required></div>
                    
                    <div class="tcc-form-group" style="margin-top:10px;">
                        <label>Inclusions</label>
                        <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                            <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                            </div>
                            <div class="tcc-rte-editor" id="master_inclusions_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                            <textarea name="master_inclusions" id="master_inclusions" style="display:none;"></textarea>
                        </div>
                    </div>

                    <div class="tcc-form-group" style="margin-top:10px;">
                        <label>Exclusions</label>
                        <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                            <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                            </div>
                            <div class="tcc-rte-editor" id="master_exclusions_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                            <textarea name="master_exclusions" id="master_exclusions" style="display:none;"></textarea>
                        </div>
                    </div>

                    <div class="tcc-form-group" style="margin-top:10px;">
                        <label>Payment Terms</label>
                        <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                            <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                            </div>
                            <div class="tcc-rte-editor" id="master_payment_terms_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                            <textarea name="master_payment_terms" id="master_payment_terms" style="display:none;"></textarea>
                        </div>
                    </div>

                    <div class="tcc-form-group" style="margin-top:10px;">
                        <label>Destination Note (Optional)</label>
                        <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                            <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                            </div>
                            <div class="tcc-rte-editor" id="master_dest_note_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                            <textarea name="master_dest_note" id="master_dest_note" style="display:none;"></textarea>
                        </div>
                    </div>

                    <div class="tcc-form-group" style="margin-top:10px;">
                        <label>Company Details</label>
                        <div class="tcc-rte-container" style="border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                            <div class="tcc-rte-toolbar" style="background:#f1f5f9; padding:6px; border-bottom:1px solid #cbd5e1; display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="tcc-rte-btn" data-cmd="bold" style="font-weight:bold; cursor:pointer;" title="Bold">B</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="italic" style="font-style:italic; cursor:pointer;" title="Italic">I</button>
                                <button type="button" class="tcc-rte-btn" data-cmd="underline" style="text-decoration:underline; cursor:pointer;" title="Underline">U</button>
                                <div style="width:1px; background:#cbd5e1; margin:0 4px;"></div>
                                <button type="button" class="tcc-rte-btn" data-cmd="insertUnorderedList" style="cursor:pointer;" title="Bullet List">• Bullets</button>
                            </div>
                            <div class="tcc-rte-editor" id="master_company_details_editor" contenteditable="true" style="padding:12px; min-height:80px; font-size:13px; outline:none; line-height:1.6; color:#334155;"></div>
                            <textarea name="master_company_details" id="master_company_details" style="display:none;"></textarea>
                        </div>
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
            <div class="tcc-accordion-header" style="background:#f1f5f9; color:#334155; border-color:#cbd5e1;">5. Backup & Restore <span>&#9660;</span></div>
            <div class="tcc-accordion-body" style="background:#fff;">
                <div class="tcc-grid-2">
                    <div style="border:1px solid #e2e8f0; padding:15px; border-radius:4px; text-align:center;">
                        <h4 style="margin:0 0 10px; color:#0f172a;">Export Backup</h4>
                        <p style="font-size:12px; color:#64748b; margin-bottom:15px;">Download a complete backup of all global settings, hotel/cab rates, presets, client details, and generated quotes.</p>
                        <button type="button" id="tcc_export_btn" class="tcc-btn-primary" style="margin:0;">Download JSON Backup</button>
                    </div>
                    
                    <div style="border:1px solid #fee2e2; background:#fef2f2; padding:15px; border-radius:4px; text-align:center;">
                        <h4 style="margin:0 0 10px; color:#b91c1c;">Import Backup</h4>
                        <p style="font-size:12px; color:#dc2626; margin-bottom:10px;"><strong>Warning:</strong> Restoring a backup will permanently overwrite and replace all current data!</p>
                        <input type="file" id="tcc_import_file" accept=".json" style="margin-bottom:10px; font-size:12px; max-width: 100%;">
                        <button type="button" id="tcc_import_btn" class="tcc-btn-del" style="margin:0; background:#dc2626; color:#fff;">Restore Data</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="tcc_settings_msg" style="display:none; margin-top:10px; padding:8px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:3px; text-align:center; font-weight:bold;"></div>
    </div>

    <script>
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ajaxSuccess(function(event, xhr, settings) {
            if (settings.data && settings.data.indexOf('action=tcc_load_quote_payments') !== -1) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && res.data) {
                        var vbal = res.data.vendor_direct_balance || 0;
                        var bal = res.data.balance || 0;
                        var waiver = bal - vbal;
                        if(waiver < 0) waiver = 0;
                        
                        jQuery('#pmt_vendor_balance_val').text('₹' + parseFloat(vbal).toFixed(2));
                        jQuery('#pmt_vendor_waiver_val').text('₹' + parseFloat(waiver).toFixed(2));
                        
                        // Hide the vendor direct box if there is no balance remaining
                        if (vbal <= 0) {
                            jQuery('#pmt_vendor_direct_wrapper').hide();
                        } else {
                            jQuery('#pmt_vendor_direct_wrapper').css('display', 'flex');
                        }
                    }
                } catch(e) {}
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}