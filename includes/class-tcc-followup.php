<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Shortcode to display the Follow Up Dashboard
add_shortcode( 'travel_calculator_followup', 'tcc_render_followup_dashboard' );

// AJAX Endpoints to Save Lead Updates
add_action( 'wp_ajax_tcc_update_lead', 'tcc_update_lead_action' );

function tcc_update_lead_action() {
    if ( ! is_user_logged_in() ) wp_die();
    
    $quote_id = intval($_POST['quote_id']);
    $status = sanitize_text_field($_POST['status']);
    $followup_date = sanitize_text_field($_POST['followup_date']);
    $priority = intval($_POST['priority']);

    if($quote_id) {
        update_post_meta($quote_id, 'tcc_lead_status', $status);
        update_post_meta($quote_id, 'tcc_followup_date', $followup_date);
        update_post_meta($quote_id, 'tcc_is_priority', $priority);
        wp_send_json_success("Lead Updated");
    }
    wp_send_json_error("Failed");
}

function tcc_render_followup_dashboard() {
    // RESTRICT TO ANY LOGGED IN USER
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; color:#dc2626; font-family:sans-serif; background:#fee2e2; border:1px solid #f87171; border-radius:6px; margin:20px 0;"><strong>Access Denied:</strong> Please log in to access the Follow-up Dashboard.</div>';
    }

    // 1. Fetch All Quotes
    $args = array(
        'post_type' => 'tcc_quote',
        'posts_per_page' => -1, 
        'post_status' => 'publish'
    );
    $quotes = get_posts($args);
    $leads = array();

    $current_date = current_time('Y-m-d');
    $current_timestamp = strtotime($current_date);

    foreach($quotes as $q) {
        $content = json_decode($q->post_content, true);
        if(!$content || !isset($content['summary'])) continue;
        
        $d = $content['summary'];
        $start_date = isset($d['start_date']) ? $d['start_date'] : '';
        $pax = isset($d['pax']) ? intval($d['pax']) : 0;
        
        // Custom Lead Metas
        $status = get_post_meta($q->ID, 'tcc_lead_status', true);
        if(empty($status)) $status = 'Pending';
        
        $followup_date = get_post_meta($q->ID, 'tcc_followup_date', true);
        $is_priority = get_post_meta($q->ID, 'tcc_is_priority', true) ? 1 : 0;

        // Check if Trip is Over
        $is_expired = false;
        if(!empty($start_date) && strtotime($start_date) < $current_timestamp) {
            $is_expired = true;
        }

        // Default Filter: Hide Completed/Dead/Expired leads UNLESS manually requested
        $is_active = true;
        if(in_array($status, ['Booking Done', 'Canceled', 'No Response']) || $is_expired) {
            $is_active = false;
        }
        
        $raw_c_name = isset($d['client_name']) ? $d['client_name'] : '';
        $raw_c_phone = isset($d['client_phone']) ? $d['client_phone'] : '';
        $raw_c_email = isset($d['client_email']) ? $d['client_email'] : '';

        $leads[] = array(
            'id' => $q->ID,
            'client_name' => !empty($d['client_name']) ? $d['client_name'] : 'Unknown Client',
            'client_phone' => (!empty($d['client_phone']) && trim($d['client_phone']) !== '') ? $d['client_phone'] : 'N/A',
            'client_email' => !empty($d['client_email']) ? $d['client_email'] : '',
            'raw_c_name' => $raw_c_name,
            'raw_c_phone' => $raw_c_phone,
            'raw_c_email' => $raw_c_email,
            'destination' => $d['destination'],
            'start_date' => $start_date,
            'pax' => $pax,
            'status' => $status,
            'followup_date' => $followup_date,
            'is_priority' => $is_priority,
            'is_active' => $is_active,
            'is_expired' => $is_expired,
            'quote_date' => get_the_date('Y-m-d', $q->ID),
            'link' => get_permalink($q->ID)
        );
    }

    // 2. Advanced Smart Sorting Algorithm
    usort($leads, function($a, $b) {
        if ($a['is_priority'] != $b['is_priority']) return $b['is_priority'] - $a['is_priority']; 
        
        $a_has_phone = ($a['client_phone'] !== 'N/A') ? 1 : 0;
        $b_has_phone = ($b['client_phone'] !== 'N/A') ? 1 : 0;
        if ($a_has_phone != $b_has_phone) return $b_has_phone - $a_has_phone;

        $a_time = !empty($a['start_date']) ? strtotime($a['start_date']) : 9999999999;
        $b_time = !empty($b['start_date']) ? strtotime($b['start_date']) : 9999999999;
        if ($a_time != $b_time) return $a_time - $b_time;

        return $b['pax'] - $a['pax']; 
    });

    ob_start(); ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* Base Desktop Table Styles */
        .tcc-lead-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-size: 13px; margin-bottom: 15px; }
        .tcc-lead-table th { background: #1e293b; color: #fff; padding: 12px; text-align: left; }
        .tcc-lead-table td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .tcc-lead-table tr:hover { background: #f8fafc; }
        .lead-status-select { padding: 4px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 3px; max-width:100%; }
        .lead-date-input { padding: 4px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 3px; width: 115px; max-width:100%; }
        .lead-save-btn { background: #2563eb; color: #fff; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 11px; transition: 0.2s; font-weight:bold; }
        .lead-save-btn:hover { background: #1d4ed8; }
        .star-priority { cursor: pointer; font-size: 18px; color: #cbd5e1; user-select: none; transition: 0.2s; }
        .star-priority.active { color: #eab308; }
        .lead-wa-btn { background: #25D366; color: #fff; text-decoration: none; padding: 4px 8px; border-radius: 3px; font-size: 11px; display: inline-block; transition: 0.2s; font-weight:bold;}
        .lead-wa-btn:hover { background: #1da851; color:#fff;}
        .lead-email-btn { background: #e0e7ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 3px; font-size: 11px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .lead-email-btn:hover { background: #bfdbfe; }
        .lead-expired { opacity: 0.6; background: #fef2f2 !important; }

        /* Compact Filter Bar Styles */
        .tcc-filter-bar {
            display: flex;
            gap: 15px;
            background: #f8fafc;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 15px;
            align-items: center;
        }
        .tcc-filter-bar > * {
            flex: 1; /* Distributes space equally among the 3 inputs on large screens */
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 13px;
            box-sizing: border-box;
            min-width: 0;
            background: #fff;
        }

        /* Compact Mobile Responsive Transformations */
        @media screen and (max-width: 768px) {
            /* Stack filter bar vertically */
            .tcc-filter-bar {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }
            .tcc-filter-bar > * {
                width: 100%;
            }

            .tcc-lead-table thead { display: none; }
            .tcc-lead-table, .tcc-lead-table tbody, .tcc-lead-table tr, .tcc-lead-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .tcc-lead-table tr { 
                margin-bottom: 15px; 
                border: 1px solid #cbd5e1; 
                border-left: 4px solid #b93b59; 
                border-radius: 4px; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
                background: #fff; 
                padding: 12px; 
                position: relative; 
            }
            
            .tcc-lead-table td { padding: 0; border: none; text-align: left; }
            .tcc-lead-table td:nth-child(1) { display: none; } /* hide index on mobile */
            .tcc-lead-table td:nth-child(2) { position: absolute; top: 10px; right: 12px; width: auto; z-index: 10; }
            .tcc-lead-table td:nth-child(3) { margin-bottom: 8px; padding-right: 30px; }
            .tcc-lead-table td:nth-child(3) strong { font-size: 14px; color: #0f172a; }
            .tcc-lead-table td:nth-child(4) { background: #f8fafc; padding: 8px; border-radius: 4px; border: 1px dashed #cbd5e1; margin-bottom: 10px; font-size: 12px; }
            .tcc-lead-table td:nth-child(5), .tcc-lead-table td:nth-child(6) { display: inline-block; width: 48%; vertical-align: top; }
            .tcc-lead-table td:nth-child(5) { margin-right: 2%; }
            .tcc-lead-table td:nth-child(5)::before { content:"Status"; display:block; font-size:10px; color:#64748b; margin-bottom:2px; font-weight:bold; }
            .tcc-lead-table td:nth-child(6)::before { content:"Follow-up Date"; display:block; font-size:10px; color:#64748b; margin-bottom:2px; font-weight:bold; }
            .lead-status-select, .lead-date-input { width: 100%; }
            .tcc-lead-table td:nth-child(7) { margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
            .tcc-lead-table td:nth-child(7) > div { justify-content: flex-start; gap: 8px; }
            .lead-save-btn, .lead-wa-btn, .lead-email-btn { padding: 6px 12px; font-size: 12px; }
        }
    </style>

    <div class="tcc-wrapper tcc-form" style="max-width: 100%;">
        <div class="tcc-card" style="margin-bottom:0; background:transparent; box-shadow:none; border:none; padding:0;">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-wrap:wrap; gap:10px;">
                <h2 style="margin:0; color:#0f172a; font-size:18px;">Quotation Follow-up Dashboard</h2>
                <div style="font-size:13px; font-weight:bold; color:#334155; background:#e2e8f0; padding:6px 12px; border-radius:20px;">
                    Showing <span id="lead_row_count" style="color:#b93b59;">0</span> lead(s)
                </div>
            </div>

            <div class="tcc-filter-bar">
                <input type="text" id="lead_search" placeholder="Search Phone, Email, Name...">
                
                <select id="lead_view_filter">
                    <option value="active">View Active Leads Only</option>
                    <option value="all">View All (Including Completed & Expired)</option>
                </select>

                <input type="text" id="lead_date_range" placeholder="Filter by Date Range...">
            </div>

            <table class="tcc-lead-table" id="tcc_leads_table">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">#</th>
                        <th title="High Priority">⭐</th>
                        <th>Client Details</th>
                        <th>Trip Specs</th>
                        <th>Status</th>
                        <th>Follow-up Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tcc_leads_tbody">
                    <?php 
                    $count = 0;
                    foreach($leads as $l): 
                        $tr_class = 'lead-row ' . ($l['is_active'] ? 'lead-active' : 'lead-inactive');
                        if($l['is_expired']) $tr_class .= ' lead-expired';
                        
                        $search_string = strtolower($l['client_name'] . ' ' . $l['client_phone'] . ' ' . $l['client_email']);
                        
                        $wa_msg = rawurlencode("Hello,\nJust following up regarding your trip inquiry for {$l['destination']}. Let me know if you have any questions!\nView Quote: {$l['link']}");
                        $wa_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $l['client_phone']) . "?text=" . $wa_msg;
                    ?>
                    <tr class="<?php echo $tr_class; ?>" 
                        data-id="<?php echo $l['id']; ?>" 
                        data-search-string="<?php echo esc_attr($search_string); ?>" 
                        data-quote-date="<?php echo esc_attr($l['quote_date']); ?>"
                        data-c-name="<?php echo esc_attr($l['raw_c_name']); ?>"
                        data-c-phone="<?php echo esc_attr($l['raw_c_phone']); ?>"
                        data-c-email="<?php echo esc_attr($l['raw_c_email']); ?>">
                        <td style="text-align:center; font-weight:bold; color:#64748b;"><span class="row-count-idx"></span></td>
                        <td>
                            <span class="star-priority <?php echo $l['is_priority'] ? 'active' : ''; ?>" title="Toggle High Priority">★</span>
                            <input type="hidden" class="val-priority" value="<?php echo $l['is_priority']; ?>">
                        </td>
                        <td>
                            <strong><?php echo esc_html($l['client_name']); ?></strong><br>
                            <span style="color:#64748b; font-size:11px;">📞 <?php echo esc_html($l['client_phone']); ?></span><br>
                            <span style="color:#94a3b8; font-size:10px;">Created: <?php echo date('d M Y', strtotime($l['quote_date'])); ?></span>
                            <?php if($l['is_expired']): ?>
                                <br><span style="color:#dc2626; font-size:10px; font-weight:bold;">[Trip Date Passed]</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($l['destination']); ?> (<?php echo esc_html($l['pax']); ?> Pax)<br>
                            <span style="color:#b93b59; font-size:11px; font-weight:bold;">Trip: <?php echo !empty($l['start_date']) ? date('d M Y', strtotime($l['start_date'])) : 'TBD'; ?></span>
                        </td>
                        <td>
                            <select class="lead-status-select val-status">
                                <option value="Pending" <?php selected($l['status'], 'Pending'); ?>>Pending</option>
                                <option value="Follow Up" <?php selected($l['status'], 'Follow Up'); ?>>Follow Up</option>
                                <option value="Booking Done" <?php selected($l['status'], 'Booking Done'); ?>>Booking Done</option>
                                <option value="No Response" <?php selected($l['status'], 'No Response'); ?>>No Response</option>
                                <option value="Canceled" <?php selected($l['status'], 'Canceled'); ?>>Canceled</option>
                            </select>
                        </td>
                        <td>
                            <input type="date" class="lead-date-input val-followup" value="<?php echo esc_attr($l['followup_date']); ?>">
                        </td>
                        <td>
                            <div style="display:flex; gap:5px; flex-wrap:wrap; align-items:center;">
                                <button type="button" class="lead-save-btn">Save</button>
                                <?php if($l['client_phone'] != 'N/A'): ?>
                                    <a href="<?php echo $wa_link; ?>" target="_blank" class="lead-wa-btn">WA</a>
                                <?php endif; ?>
                                <button type="button" class="lead-email-btn">Email</button>
                                <a href="<?php echo esc_url($l['link']); ?>" target="_blank" style="color:#475569; font-size:12px; font-weight:bold; line-height:2.2; margin-left:5px; text-decoration:none;">View &rarr;</a>
                            </div>
                        </td>
                    </tr>
                    <?php 
                    $count++;
                    endforeach; 
                    
                    if($count == 0): ?>
                        <tr class="lead-row lead-active"><td colspan="7" style="text-align:center; padding:30px;">No Quotes/Leads Generated Yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tcc-pagination-controls" style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
                <button type="button" id="tcc_prev_page" class="tcc-btn-secondary" style="margin:0; width:auto; padding:6px 15px;">&laquo; Prev</button>
                <span id="tcc_page_info" style="font-size:13px; font-weight:bold; color:#475569;">Page 1</span>
                <button type="button" id="tcc_next_page" class="tcc-btn-secondary" style="margin:0; width:auto; padding:6px 15px;">Next &raquo;</button>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
    jQuery(document).ready(function($) {
        
        // Initialize the Date Range Picker
        flatpickr("#lead_date_range", {
            mode: "range",
            dateFormat: "Y-m-d",
            onChange: function(selectedDates, dateStr, instance) {
                // Render table immediately when a valid range or single date is picked
                currentPage = 1;
                renderTable();
            }
        });

        let currentPage = 1;
        const rowsPerPage = 10;

        function renderTable() {
            let $rows = $('#tcc_leads_tbody tr.lead-row');
            let viewFilter = $('#lead_view_filter').val();
            let searchTerm = $('#lead_search').val().toLowerCase();
            
            // Extract the dates from the range picker input
            let dateRangeStr = $('#lead_date_range').val();
            let dateFrom = '';
            let dateTo = '';
            
            if (dateRangeStr) {
                if (dateRangeStr.indexOf(' to ') !== -1) {
                    let parts = dateRangeStr.split(' to ');
                    dateFrom = parts[0];
                    dateTo = parts[1];
                } else {
                    // Single date selected
                    dateFrom = dateRangeStr;
                    dateTo = dateRangeStr;
                }
            }
            
            let $filteredRows = $rows.filter(function() {
                let show = true;
                if (viewFilter === 'active' && !$(this).hasClass('lead-active')) show = false;
                
                let searchStr = $(this).data('search-string') || '';
                if (searchTerm && searchStr.indexOf(searchTerm) === -1) show = false;

                let qDate = $(this).data('quote-date'); 
                if (dateFrom && qDate < dateFrom) show = false;
                if (dateTo && qDate > dateTo) show = false;

                return show;
            });

            // Update row index numbers dynamically based on filtered set
            let displayIdx = 1;
            $filteredRows.each(function() {
                $(this).find('.row-count-idx').text(displayIdx++);
            });

            // Update Row Counter Header
            $('#lead_row_count').text($filteredRows.length);

            let totalRows = $filteredRows.length;
            let totalPages = Math.ceil(totalRows / rowsPerPage);
            
            if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            let start = (currentPage - 1) * rowsPerPage;
            let end = start + rowsPerPage;

            $rows.hide();
            $filteredRows.slice(start, end).fadeIn(200);

            $('#tcc_page_info').text(`Page ${currentPage} of ${totalPages || 1}`);
            $('#tcc_prev_page').prop('disabled', currentPage === 1).css('opacity', currentPage === 1 ? '0.5' : '1');
            $('#tcc_next_page').prop('disabled', currentPage === totalPages || totalPages === 0).css('opacity', (currentPage === totalPages || totalPages === 0) ? '0.5' : '1');
        }

        $('#tcc_prev_page').click(function() { if(currentPage > 1) { currentPage--; renderTable(); } });
        $('#tcc_next_page').click(function() { currentPage++; renderTable(); });
        $('#lead_view_filter, #lead_search').on('input change', function() { currentPage = 1; renderTable(); });

        renderTable();

        $('.star-priority').on('click', function() {
            let isActive = $(this).hasClass('active');
            if(isActive) {
                $(this).removeClass('active');
                $(this).siblings('.val-priority').val(0);
            } else {
                $(this).addClass('active');
                $(this).siblings('.val-priority').val(1);
            }
            $(this).closest('tr').find('.lead-save-btn').trigger('click');
        });

        // Email Prompt & Send Logic
        $('.lead-email-btn').on('click', function() {
            let $row = $(this).closest('tr');
            let quoteId = $row.data('id');
            let cName = $row.data('c-name');
            let cPhone = $row.data('c-phone');
            let cEmail = $row.data('c-email');
            let $btn = $(this);

            if (!cEmail || cEmail.trim() === '') {
                let newEmail = prompt("⚠️ Client email is missing.\n\nPlease enter the client's email address to instantly save it and send the quotation:");
                if (!newEmail || newEmail.trim() === '') {
                    return; 
                }
                
                let originalText = $btn.text();
                $btn.text('Saving...').prop('disabled', true);
                
                // First save the newly provided email address via existing AJAX logic
                $.post(tcc_ajax_obj.ajax_url, {
                    action: 'tcc_update_quote_client',
                    quote_id: quoteId,
                    c_name: cName,
                    c_phone: cPhone,
                    c_email: newEmail.trim()
                }, function(res) {
                    if(res.success) {
                        $row.data('c-email', newEmail.trim());
                        sendEmailAJAX(quoteId, $btn, originalText); // Trigger the email
                    } else {
                        $btn.text(originalText).prop('disabled', false);
                        alert("Failed to save the new email address.");
                    }
                });
            } else {
                if(confirm('Send quotation email to ' + cEmail + ' (BCC sent to Admin)?')) {
                    sendEmailAJAX(quoteId, $btn, $btn.text());
                }
            }
        });

        function sendEmailAJAX(quoteId, $btn, originalText) {
            $btn.text('Sending...').prop('disabled', true);
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_send_quote_email', quote_id: quoteId }, function(res) {
                $btn.text(originalText).prop('disabled', false);
                if(res.success) {
                    alert(res.data);
                } else {
                    alert("Error: " + res.data);
                }
            }).fail(function() {
                $btn.text(originalText).prop('disabled', false);
                alert("Server error occurred while sending email.");
            });
        }

        $('.lead-save-btn').on('click', function() {
            let $row = $(this).closest('tr');
            let $btn = $(this);
            let originalText = $btn.text();
            
            let data = {
                action: 'tcc_update_lead',
                quote_id: $row.data('id'),
                status: $row.find('.val-status').val(),
                followup_date: $row.find('.val-followup').val(),
                priority: $row.find('.val-priority').val()
            };

            $btn.text('...');
            $.post(tcc_ajax_obj.ajax_url, data, function(res) {
                if(res.success) {
                    $btn.text('✔');
                    $btn.css('background', '#16a34a');
                    setTimeout(function(){ 
                        $btn.text(originalText); 
                        $btn.css('background', '#2563eb');
                        
                        let newStatus = data.status;
                        if (newStatus === 'Booking Done' || newStatus === 'Canceled' || newStatus === 'No Response') {
                            $row.removeClass('lead-active').addClass('lead-inactive');
                        } else {
                            $row.removeClass('lead-inactive').addClass('lead-active');
                        }
                        renderTable();
                    }, 1000);
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}