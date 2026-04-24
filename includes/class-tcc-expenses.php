<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'travel_expense_calculator', 'tcc_render_expense_calculator' );

add_action( 'wp_enqueue_scripts', 'tcc_enqueue_expense_scripts' );
function tcc_enqueue_expense_scripts() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'travel_expense_calculator' ) ) {
        wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13' );
        wp_enqueue_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '4.6.13', true );
        
        // Version bumped to 2.9 to clear cache
        wp_enqueue_script( 'tcc-expenses-js', plugin_dir_url( dirname(__FILE__) ) . 'assets/js/tcc-expenses.js', array('jquery', 'flatpickr-js'), '2.9', true );
        wp_localize_script( 'tcc-expenses-js', 'tcc_exp_obj', array(
            'ajax_url' => admin_url( 'admin-ajax.php' )
        ));
    }
}

function tcc_render_expense_calculator() {
    if ( ! is_user_logged_in() ) return '<div style="padding:40px; text-align:center; color:#dc2626;"><strong>Access Denied:</strong> Please log in.</div>';

    $args = array('post_type' => 'tcc_quote', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC');
    $all_quotes = get_posts($args);
    $booked_quotes = array();

    foreach($all_quotes as $q) {
        $status = get_post_meta($q->ID, 'tcc_lead_status', true);
        $payments = get_post_meta($q->ID, 'tcc_payments', true);
        $has_income = false;
        
        if (is_array($payments)) {
            foreach($payments as $p) {
                if (isset($p['method']) && $p['method'] !== 'Refund' && floatval($p['amount']) > 0) { 
                    $has_income = true; 
                    break; 
                }
            }
        }
        
        if ($status === 'Booking Done' || $has_income) {
            $booked_quotes[] = $q;
        }
    }

    ob_start(); ?>
    <style>
        .tcc-exp-tabs { display: flex; gap: 5px; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; flex-wrap:wrap; }
        .tcc-exp-tab { padding: 8px 15px; background: #f1f5f9; color: #475569; font-weight: bold; border-radius: 4px; cursor: pointer; transition: 0.2s; font-size: 13px; border: 1px solid #cbd5e1; }
        .tcc-exp-tab.active { background: #0f172a; color: #fff; border-color: #0f172a; }
        .tcc-exp-section { display: none; }
        .tcc-exp-section.active { display: block; animation: tccFadeIn 0.3s ease-in-out; }
        .bk-manual-type { font-size:11px; padding:2px; height:28px; }
    </style>

    <div class="tcc-wrapper tcc-form" id="tcc_expense_app">
        <h2 style="margin-bottom: 15px; font-size: 18px; color: #0f172a;">Master Agency Profit & Loss</h2>
        
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:15px; background:#f8fafc; padding:10px; border:1px solid #cbd5e1; border-radius:4px;">
            <strong style="font-size:13px; color:#334155;">Filter Date Range:</strong>
            <input type="text" id="m_filter_range" placeholder="Select Date Range..." readonly style="height:30px; font-size:13px; border:1px solid #94a3b8; border-radius:3px; padding:2px 10px; min-width: 220px; background: #fff; cursor: pointer; font-weight:bold; color:#0f172a;">
            <a href="#" id="m_filter_clear" style="font-size:12px; color:#dc2626; text-decoration:none; font-weight:bold; margin-left:5px;">(Clear / All Time)</a>
        </div>

        <div class="tcc-preview-panel" style="margin-bottom: 20px; padding: 15px;">
            <div class="tcc-grid-3" style="margin-bottom:0; text-align:center;">
                <div>
                    <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Received Income</div>
                    <div id="m_income" style="font-size:20px; font-weight:bold; color:#16a34a;">Loading...</div>
                    <div id="m_income_compare"></div>
                </div>
                <div style="border-left:1px solid #cbd5e1; border-right:1px solid #cbd5e1;">
                    <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Paid Expenses</div>
                    <div id="m_expense" style="font-size:20px; font-weight:bold; color:#dc2626;">Loading...</div>
                    <div id="m_expense_compare"></div>
                </div>
                <div>
                    <div style="font-size:11px; color:#0369a1; text-transform:uppercase; font-weight:bold;">Net Profit</div>
                    <div id="m_profit" style="font-size:22px; font-weight:900; color:#0ea5e9;">...</div>
                    <div id="m_profit_compare"></div>
                </div>
            </div>

            <div class="tcc-grid-3" style="margin-top:15px; border-top:1px dashed #cbd5e1; padding-top:10px; text-align:center;">
                <div>
                    <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Bookings</div>
                    <div id="m_total_bookings" style="font-size:18px; font-weight:bold; color:#334155;">0</div>
                    <div id="m_bookings_compare"></div>
                </div>
                <div style="border-left:1px solid #cbd5e1; border-right:1px solid #cbd5e1;">
                    <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Total Travellers</div>
                    <div id="m_total_travellers" style="font-size:18px; font-weight:bold; color:#334155;">0</div>
                    <div id="m_travellers_compare"></div>
                </div>
                <div>
                    <div style="font-size:11px; color:#64748b; text-transform:uppercase;">Top 5 Destinations</div>
                    <div id="m_top_destinations" style="font-size:11px; color:#334155; margin-top:4px; line-height:1.4;">-</div>
                </div>
            </div>
            
            <div style="display:flex; justify-content:center; flex-wrap:wrap; gap:15px; margin-top:15px; font-size:12px; color:#475569; border-top:1px dashed #cbd5e1; padding-top:10px;">
                <div>Total PT: <strong id="m_pt" style="color:#dc2626;">₹0.00</strong></div>
                <div>Total PG: <strong id="m_pg" style="color:#dc2626;">₹0.00</strong></div>
                <div>Total GST: <strong id="m_gst" style="color:#dc2626;">₹0.00</strong></div>
            </div>
        </div>

        <div class="tcc-exp-tabs">
            <div class="tcc-exp-tab active" data-target="sec_general">🏢 Everyday Agency Expenses</div>
            <div class="tcc-exp-tab" data-target="sec_auto_daily">⚙️ Auto-Daily Setup</div>
            <div class="tcc-exp-tab" data-target="sec_bookings">✈️ Booking-Specific Expenses</div>
        </div>

        <div id="sec_general" class="tcc-exp-section active">
            <div class="tcc-card" style="border-left: 4px solid #f59e0b;">
                <div class="tcc-card-title">Add Everyday Expense (Ads, Rent, Salaries, etc.)</div>
                <form id="frm_general_expense" style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px;">
                    <input type="date" id="ge_date" required style="flex:1; min-width:110px;">
                    <select id="ge_cat" required style="flex:1.5; min-width:130px;">
                        <option value="">-- Category --</option>
                        <option value="Facebook/Google Ads">Facebook/Google Ads</option>
                        <option value="Office Rent">Office Rent</option>
                        <option value="Salaries">Salaries</option>
                        <option value="Software Subscriptions">Software Subscriptions</option>
                        <option value="Misc/Other">Misc/Other</option>
                    </select>
                    <input type="text" id="ge_desc" placeholder="Details" style="flex:2; min-width:150px;">
                    <input type="number" id="ge_amt" step="0.01" min="1" placeholder="Amount (₹)" required style="flex:1; min-width:100px;">
                    <button type="submit" class="tcc-btn-primary" style="margin:0; flex:1; min-width:80px;">Add</button>
                </form>
                
                <h4 style="margin: 15px 0 8px; font-size:13px;">Expense History <span style="font-weight:normal; font-size:11px; color:#64748b;">(Auto-added daily expenses appear here for you to edit/delete)</span></h4>
                <div id="ge_history_table" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; font-size:12px; overflow:hidden;"></div>
            </div>
        </div>

        <div id="sec_auto_daily" class="tcc-exp-section">
            <div class="tcc-card" style="border-left: 4px solid #0284c7;">
                <div class="tcc-card-title">Setup Recurring Daily Expenses</div>
                <form id="frm_auto_expense" style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px;">
                    <select id="ae_cat" required style="flex:1.5; min-width:130px;">
                        <option value="">-- Category --</option>
                        <option value="Facebook/Google Ads">Facebook/Google Ads</option>
                        <option value="Office Rent">Office Rent</option>
                        <option value="Salaries">Salaries</option>
                        <option value="Misc/Other">Misc/Other</option>
                    </select>
                    <input type="text" id="ae_desc" placeholder="Details" style="flex:2; min-width:150px;" required>
                    <input type="number" id="ae_amt" step="0.01" min="1" placeholder="Daily Amt (₹)" required style="flex:1; min-width:100px;">
                    <button type="submit" class="tcc-btn-primary" style="margin:0; flex:1; min-width:80px;">Set Daily</button>
                </form>
                
                <div id="ae_history_table" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; font-size:12px; overflow:hidden; margin-top:15px;"></div>
            </div>
        </div>

        <div id="sec_bookings" class="tcc-exp-section">
            <div class="tcc-card" style="background:#f8fafc; display:flex; flex-direction:column; gap:10px;">
                <strong style="color:#334155; font-size:14px;">Select & Search Booking:</strong>
                
                <div style="display:flex; gap:5px; font-size:12px;" id="bk_filters">
                    <button type="button" class="bk-filter-btn" data-filter="all" style="padding:4px 10px; border:1px solid #cbd5e1; background:#0f172a; color:#fff; border-radius:4px; cursor:pointer;">All Bookings</button>
                    <button type="button" class="bk-filter-btn" data-filter="advance" style="padding:4px 10px; border:1px solid #cbd5e1; background:#fff; color:#475569; border-radius:4px; cursor:pointer;">⏳ Advance</button>
                    <button type="button" class="bk-filter-btn" data-filter="cust_due" style="padding:4px 10px; border:1px solid #cbd5e1; background:#fff; color:#475569; border-radius:4px; cursor:pointer;">🔴 Cust Due</button>
                    <button type="button" class="bk-filter-btn" data-filter="ven_due" style="padding:4px 10px; border:1px solid #cbd5e1; background:#fff; color:#475569; border-radius:4px; cursor:pointer;">🟠 Ven Due</button>
                </div>

                <div style="display:flex; gap:10px; align-items:center;">
                    <div style="flex:1;">
                        <select id="bk_select" style="width:100%;">
                            <option value="">Loading Bookings...</option>
                        </select>
                    </div>
                    <a href="#" id="bk_view_quote" target="_blank" class="tcc-btn-secondary" style="display:none; margin:0; padding:6px 12px; font-size:12px; text-decoration:none; background:#fff; border-color:#cbd5e1; color:#0f172a; white-space:nowrap;">👁️ View Quote</a>
                </div>
            </div>
            
            <div id="bk_dashboard" style="display:none; margin-top:15px;">
                <div class="tcc-grid-2">
                    <div class="tcc-card" style="border-left: 4px solid #16a34a; position:relative;">
                        <div class="tcc-card-title">💰 Total Received Income</div>
                        <div style="font-size:24px; font-weight:bold; color:#16a34a;" id="bk_income">₹0.00</div>
                        <div style="font-size:12px; color:#64748b; margin-top:4px;">Sold Package Value: <strong id="bk_pkg_value">₹0.00</strong></div>
                        <div id="bk_cust_pending_badge" style="display:none; margin-top:10px; background:#fee2e2; color:#dc2626; padding:6px 10px; border-radius:4px; font-size:12px; border:1px solid #f87171;">
                            <strong style="display:block; font-size:13px;">⚠️ Customer Payment Pending</strong>
                            Due Amount: <span id="bk_cust_pending" style="font-weight:bold;">₹0.00</span>
                        </div>
                    </div>
                    
                    <div class="tcc-card" style="border-left: 4px solid #dc2626;">
                        <div class="tcc-card-title">📉 Booked Trip Expected Costs & Taxes</div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:8px; align-items:center;">
                            <span>Actual Base Cost:</span> 
                            <input type="number" id="bk_override_cost" step="0.01" min="0" style="width:110px; height:24px; padding:2px 6px; font-size:13px; font-weight:bold; text-align:right; border:1px solid #94a3b8; border-radius:3px; background:#fff;">
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px; padding-top:4px; border-top:1px dotted #e2e8f0;">
                            <span>Prof Tax (PT):</span> <strong id="bk_auto_pt">₹0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                            <span>PG Fees:</span> <strong id="bk_auto_pg">₹0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                            <span>GST:</span> <strong id="bk_auto_gst">₹0.00</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:bold; color:#dc2626; border-top:1px dashed #ccc; margin-top:4px; padding-top:4px;">
                            <span>Total Expected Cost:</span> <span id="bk_auto_total">₹0.00</span>
                        </div>
                    </div>
                </div>

                <div class="tcc-grid-2">
                    <div class="tcc-card" style="border-left: 4px solid #d97706;">
                        <div class="tcc-card-title">🛒 Actual Day-Wise Vendor Payments</div>
                        <div id="bk_vendor_wrapper"></div>
                        <button type="button" id="bk_add_vendor" class="tcc-btn-secondary" style="margin-top:8px; font-size:11px; padding:4px 8px;">+ Record Vendor Payment</button>
                        
                        <div style="background:#fffbeb; padding:10px; border:1px solid #fde68a; border-radius:4px; margin-top:10px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                                <span>Total Paid to Vendors:</span> <strong id="bk_total_vendor_paid" style="color:#16a34a;">₹0.00</strong>
                            </div>
                            <div id="bk_vendor_pending_badge" style="display:flex; justify-content:space-between; font-size:13px; font-weight:bold; color:#b45309;">
                                <span>⚠️ Vendor Pending:</span> <span id="bk_pending_cost">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="tcc-card">
                        <div class="tcc-card-title">➕ Additional Manual Adjustments</div>
                        <div id="bk_manual_wrapper"></div>
                        <button type="button" id="bk_add_manual" class="tcc-btn-secondary" style="margin-top:8px; font-size:11px; padding:4px 8px;">+ Add Manual Adjustment</button>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="button" id="bk_save_manual_btn" class="tcc-btn-primary" style="flex:1; margin:0;">💾 Update Booking Finances</button>
                </div>

                <div class="tcc-preview-panel" style="text-align:center; margin-top:15px;">
                    <div style="font-size:12px; color:#64748b; font-weight:bold;">EXPECTED NET PROFIT</div>
                    <div style="font-size:10px; color:#94a3b8; margin-bottom:5px;">(Sold Value - Expected Costs)</div>
                    <div id="bk_expected_profit" style="font-size:22px; font-weight:900;">₹0.00</div>
                    <div id="bk_unconfirmed_warning" style="display:none; font-size:11px; color:#dc2626; margin-top:8px; font-weight:bold;">⚠️ Profit Excluded from Master Dashboard (Customer Paid ≤ Base Cost)</div>
                </div>

            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}