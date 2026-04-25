jQuery(document).ready(function($) {

    let masterData = null;
    let currentBookingFilter = 'all';

    function expFormatINR(amount) {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(amount);
    }

    $('.tcc-exp-tab').on('click', function() {
        $('.tcc-exp-tab').removeClass('active');
        $(this).addClass('active');
        $('.tcc-exp-section').removeClass('active');
        $('#' + $(this).data('target')).addClass('active');
    });

    let now = new Date();
    let firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    let lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    let fpInstance = flatpickr("#m_filter_range", {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: [firstDay, lastDay],
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                try { calculateDashboardTotals(); } catch(e) { console.error("Filter Error:", e); }
            }
        }
    });

    $('#m_filter_clear').on('click', function(e) {
        e.preventDefault();
        fpInstance.clear(); 
        try { calculateDashboardTotals(); } catch(e) { console.error(e); }
    });

    function loadMasterData(callback = null) {
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_load_master_finances' }, function(res) {
            if(res.success) {
                masterData = res.data;
                
                // ISOLATED EXECUTION: If one fails, the others will still run perfectly.
                try { renderAutoExpenses(); } catch(err) { 
                    console.error("Auto Expenses Render Failed:", err); 
                    $('#ae_history_table').html('<div style="padding:15px; text-align:center; color:#dc2626;">Error parsing recurring data format.</div>');
                }
                
                try { calculateDashboardTotals(); } catch(err) { console.error("Dashboard Failed:", err); }
                try { renderBookingDropdown(); } catch(err) { console.error("Bookings Failed:", err); }
                
                if(callback) callback();
            }
        }).fail(function() {
            $('#ae_history_table').html('<div style="padding:15px; text-align:center; color:#dc2626;">Server Connection Error.</div>');
        });
    }

    function calculateDashboardTotals() {
        if (!masterData) return;
        
        let dates = fpInstance.selectedDates;
        let hasFilter = dates.length === 2;
        
        let start = '', end = '';
        let prevStart = '', prevEnd = '';
        let hasCompare = false;

        if (hasFilter) {
            start = fpInstance.formatDate(dates[0], "Y-m-d");
            end = fpInstance.formatDate(dates[1], "Y-m-d");
            
            let dStart = new Date(dates[0]);
            let dEnd = new Date(dates[1]);
            let diffTime = Math.abs(dEnd - dStart);
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            let pEnd = new Date(dStart);
            pEnd.setDate(pEnd.getDate() - 1);
            let pStart = new Date(pEnd);
            pStart.setDate(pStart.getDate() - diffDays + 1);

            prevStart = fpInstance.formatDate(pStart, "Y-m-d");
            prevEnd = fpInstance.formatDate(pEnd, "Y-m-d");
            hasCompare = true;
        }

        let t_income = 0, t_expense_paid = 0, t_net_profit = 0;
        let t_pt = 0, t_pg = 0, t_gst = 0;
        let t_bookings = 0, t_travellers = 0;
        let dest_counts = {};
        
        let p_income = 0, p_expense_paid = 0, p_net_profit = 0;
        let p_bookings = 0, p_travellers = 0;

        let generalHtml = '';
        let filteredGeneral = [];
        let t_general_expenses = 0;
        let p_general_expenses = 0;
        
        if (masterData.general_expenses) {
            let genExpenses = Array.isArray(masterData.general_expenses) ? masterData.general_expenses : Object.values(masterData.general_expenses);
            genExpenses.forEach(e => {
                if(!e || typeof e !== 'object') return;
                let amt = parseFloat(e.amount) || 0;
                if (hasFilter) {
                    if (e.date >= start && e.date <= end) {
                        t_general_expenses += amt;            
                        filteredGeneral.push(e);
                    } else if (hasCompare && e.date >= prevStart && e.date <= prevEnd) {
                        p_general_expenses += amt;
                    }
                } else {
                    t_general_expenses += amt;            
                    filteredGeneral.push(e);
                }
            });
        }

        t_expense_paid += t_general_expenses;
        p_expense_paid += p_general_expenses;

        if(filteredGeneral.length === 0) {
            generalHtml = '<div style="padding:15px; text-align:center; color:#64748b;">No everyday expenses logged for this date range.</div>';
        } else {
            generalHtml += `<table style="width:100%; border-collapse:collapse; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #cbd5e1;">
                            <th style="padding:8px;">Date</th><th style="padding:8px;">Category</th><th style="padding:8px;">Details</th><th style="padding:8px;">Amount</th><th style="padding:8px; text-align:right;">Action</th>
                        </tr>`;
            filteredGeneral.forEach(e => {
                let safeDesc = e.desc ? String(e.desc).replace(/"/g, '&quot;') : '';
                generalHtml += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;">${e.date}</td>
                            <td style="padding:8px;">${e.category}</td>
                            <td style="padding:8px; color:#64748b;">${e.desc}</td>
                            <td style="padding:8px; font-weight:bold; color:#dc2626;">-${expFormatINR(e.amount)}</td>
                            <td style="padding:8px; text-align:right;">
                                <button type="button" class="edit-ge-btn" data-id="${e.id}" data-date="${e.date}" data-cat="${e.category}" data-desc="${safeDesc}" data-amount="${e.amount}" style="background:none; border:none; color:#0369a1; cursor:pointer; text-decoration:underline; margin-right:8px; font-size:12px;">Edit</button>
                                <button type="button" class="del-ge-btn" data-id="${e.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline; font-size:12px;">Delete</button>
                            </td>
                         </tr>`;
            });
            generalHtml += `</table>`;
        }
        $('#ge_history_table').html(generalHtml);

        if (masterData.bookings) {
            $.each(masterData.bookings, function(id, b) {
                let inCurrentBooking = true;
                let inPrevBooking = false;

                if (hasFilter) {
                    inCurrentBooking = (b.booking_date >= start && b.booking_date <= end);
                    inPrevBooking = (hasCompare && b.booking_date >= prevStart && b.booking_date <= prevEnd);
                }
                
                let manualBase = 0, manualProfit = 0, manualPkg = 0;
                
                if(b.quote_addons && b.quote_addons.length > 0) {
                    b.quote_addons.forEach(addon => { manualBase += parseFloat(addon.amount) || 0; });
                }

                if(b.manual_expenses && b.manual_expenses.length > 0) {
                    b.manual_expenses.forEach(me => { 
                        let amt = parseFloat(me.amount) || 0;
                        let type = me.type || 'base_cost';
                        if(type === 'base_cost') manualBase += amt;
                        else if(type === 'net_profit') manualProfit += amt;
                        else if(type === 'pkg_value') manualPkg += amt;
                    });
                }

                let b_income = parseFloat(b.income) || 0;

                if (b.payment_history && b.payment_history.length > 0) {
                    b.payment_history.forEach(pay => {
                        let pAmt = parseFloat(pay.amount) || 0;
                        let pDate = pay.date ? pay.date.split('T')[0] : b.booking_date; 
                        
                        if (hasFilter) {
                            if (pDate >= start && pDate <= end) t_income += pAmt;
                            else if (hasCompare && pDate >= prevStart && pDate <= prevEnd) p_income += pAmt;
                        } else {
                            t_income += pAmt;
                        }
                    });
                } else {
                    if (inCurrentBooking) t_income += b_income;
                    if (inPrevBooking) p_income += b_income;
                }

                let base_cost_raw = parseFloat(b.auto_cost) || 0;
                let b_base_cost = base_cost_raw + manualBase;
                let b_vendor_paid = parseFloat(b.vendor_paid) || 0;
                let b_actual_pg = parseFloat(b.actual_pg) || 0;
                
                let b_actual_paid_expense = b_vendor_paid + parseFloat(b.auto_pt) + b_actual_pg + parseFloat(b.auto_gst);
                let b_expected_total_expense = b_base_cost + parseFloat(b.auto_pt) + parseFloat(b.auto_pg) + parseFloat(b.auto_gst);
                let b_pax = parseInt(b.pax) || 0;

                let b_pkg_value = (parseFloat(b.pkg_value) || 0) + manualPkg;
                let is_fully_paid = b_income >= (b_pkg_value - 1); 

                if (inCurrentBooking) {
                    t_bookings++;
                    t_travellers += b_pax;
                    let dName = b.destination || 'Unknown';
                    if(!dest_counts[dName]) dest_counts[dName] = 0;
                    dest_counts[dName]++;
                }
                if (inPrevBooking) {
                    p_bookings++;
                    p_travellers += b_pax;
                }

                if (is_fully_paid) {
                    let fpDate = b.final_payment_date || b.booking_date;
                    let inCurrentFP = true;
                    let inPrevFP = false;
                    
                    if (hasFilter) {
                        inCurrentFP = (fpDate >= start && fpDate <= end);
                        inPrevFP = (hasCompare && fpDate >= prevStart && fpDate <= prevEnd);
                    }

                    if (inCurrentFP) {
                        t_expense_paid += b_actual_paid_expense;
                        t_pt += parseFloat(b.auto_pt) || 0;
                        t_pg += b_actual_pg; 
                        t_gst += parseFloat(b.auto_gst) || 0;
                        t_net_profit += (b_income + manualProfit - b_expected_total_expense);
                    }
                    if (inPrevFP) {
                        p_expense_paid += b_actual_paid_expense;
                        p_net_profit += (b_income + manualProfit - b_expected_total_expense);
                    }
                }
            });
        }

        t_net_profit -= t_general_expenses;
        p_net_profit -= p_general_expenses;

        let destArr = [];
        for (let d in dest_counts) { destArr.push({ dest: d, count: dest_counts[d] }); }
        destArr.sort((a, b) => b.count - a.count);
        let top5 = destArr.slice(0, 5).map(item => `<strong>${item.dest}</strong> (${item.count})`).join(', ');
        if (top5 === '') top5 = 'None';
        $('#m_top_destinations').html(top5);

        $('#m_income').text(expFormatINR(t_income));
        $('#m_expense').text(expFormatINR(t_expense_paid));

        $('#m_profit').text(expFormatINR(t_net_profit)).css('color', t_net_profit < 0 ? '#dc2626' : '#0ea5e9');

        $('#m_total_bookings').text(t_bookings);
        $('#m_total_travellers').text(t_travellers);

        $('#m_pt').text(expFormatINR(t_pt));
        $('#m_pg').text(expFormatINR(t_pg));
        $('#m_gst').text(expFormatINR(t_gst));

        function getTrendHtml(current, prev, invertColor = false) {
            if (!hasCompare) return '';
            let pct = 0;
            if (prev === 0) {
                pct = current === 0 ? 0 : 100;
            } else {
                pct = ((current - prev) / prev) * 100;
            }
            
            let isUp = pct > 0;
            let isDown = pct < 0;
            let color = '#64748b';
            let icon = '▬';
            
            if (isUp) { color = invertColor ? '#dc2626' : '#16a34a'; icon = '▲'; }
            if (isDown) { color = invertColor ? '#16a34a' : '#dc2626'; icon = '▼'; }
            
            let absPct = Math.abs(pct);
            let pctStr = absPct === Infinity ? '100%' : absPct.toFixed(1) + '%';
            
            if(pctStr !== '0.0%' && pctStr !== '0%') {
                return `<div style="color:${color}; font-size:10px; font-weight:bold; margin-top:3px;">${icon} ${pctStr} vs prev period</div>`;
            }
            return `<div style="color:#64748b; font-size:10px; font-weight:bold; margin-top:3px;">▬ No Change</div>`;
        }

        $('#m_income_compare').html(getTrendHtml(t_income, p_income));
        $('#m_expense_compare').html(getTrendHtml(t_expense_paid, p_expense_paid, true));
        $('#m_profit_compare').html(getTrendHtml(t_net_profit, p_net_profit));
        $('#m_bookings_compare').html(getTrendHtml(t_bookings, p_bookings));
        $('#m_travellers_compare').html(getTrendHtml(t_travellers, p_travellers));
    }

    // --- EVERYDAY EXPENSE LOGIC ---
    $('#frm_general_expense').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]');
        let origText = btn.text();
        btn.text('Saving...').prop('disabled', true);

        $.post(tcc_exp_obj.ajax_url, {
            action: 'tcc_save_general_expense',
            id: $('#ge_id').val(),
            date: $('#ge_date').val(), 
            cat: $('#ge_cat').val(), 
            desc: $('#ge_desc').val(), 
            amount: $('#ge_amt').val()
        }, function(res) {
            btn.prop('disabled', false);
            if(res.success) {
                $('#ge_id').val('');
                $('#frm_general_expense')[0].reset();
                btn.text('Add');
                $('#ge_cancel_edit').hide();
                loadMasterData();
            } else {
                btn.text(origText);
                alert("Error saving expense.");
            }
        });
    });

    $(document).on('click', '.edit-ge-btn', function() {
        $('#ge_id').val($(this).data('id'));
        $('#ge_date').val($(this).data('date'));
        $('#ge_cat').val($(this).data('cat'));
        $('#ge_desc').val($(this).data('desc'));
        $('#ge_amt').val($(this).data('amount'));
        
        $('#frm_general_expense button[type="submit"]').text('Update');
        $('#ge_cancel_edit').show();
        
        $('html, body').animate({
            scrollTop: $("#frm_general_expense").offset().top - 80
        }, 300);
    });

    $('#ge_cancel_edit').on('click', function() {
        $('#ge_id').val('');
        $('#frm_general_expense')[0].reset();
        $('#frm_general_expense button[type="submit"]').text('Add');
        $(this).hide();
    });

    $(document).on('click', '.del-ge-btn', function() {
        if(!confirm('Delete this expense?')) return;
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_delete_general_expense', id: $(this).data('id') }, function(res) {
            if(res.success) loadMasterData();
        });
    });

    // --- AUTO EXPENSES REDESIGN ---
    function renderAutoExpenses() {
        let html = '';
        let expenses = [];
        
        if(masterData && masterData.auto_expenses) {
            if (Array.isArray(masterData.auto_expenses)) {
                expenses = masterData.auto_expenses;
            } else if (typeof masterData.auto_expenses === 'object') {
                expenses = Object.values(masterData.auto_expenses);
            }
        }

        if(expenses.length === 0) {
            html = '<div style="padding:15px; text-align:center; color:#64748b;">No automatic daily expenses set up.</div>';
        } else {
            html += `<table style="width:100%; border-collapse:collapse; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #cbd5e1;">
                            <th style="padding:8px;">Status</th><th style="padding:8px;">Category & Details</th><th style="padding:8px;">Daily Amt</th><th style="padding:8px;">Action</th>
                        </tr>`;
            expenses.forEach(e => {
                if (!e || typeof e !== 'object') return; 
                
                let safeDesc = e.desc ? String(e.desc).replace(/"/g, '&quot;') : '';
                let cat = e.category || 'N/A';
                let amt = parseFloat(e.amount) || 0;
                let eid = e.id || '';
                
                html += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;"><span style="background:#dcfce7; color:#166534; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:bold; border:1px solid #bbf7d0;">🟢 Active</span></td>
                            <td style="padding:8px;"><strong>${cat}</strong><br><span style="color:#64748b; font-size:11px;">${safeDesc}</span></td>
                            <td style="padding:8px; font-weight:bold;">${expFormatINR(amt)} <span style="font-size:10px; font-weight:normal; color:#64748b;">/ day</span></td>
                            <td style="padding:8px;">
                                <button type="button" class="edit-ae-btn" data-id="${eid}" data-cat="${cat}" data-desc="${safeDesc}" data-amount="${amt}" style="background:none; border:none; color:#0369a1; cursor:pointer; text-decoration:underline; margin-right:8px; font-size:12px;">Edit</button>
                                <button type="button" class="del-ae-btn" data-id="${eid}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline; font-size:12px;">Delete</button>
                            </td>
                         </tr>`;
            });
            html += `</table>`;
        }
        $('#ae_history_table').html(html);
    }

    $('#frm_auto_expense').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]');
        let origText = btn.text();
        btn.text('...').prop('disabled', true);

        $.post(tcc_exp_obj.ajax_url, {
            action: 'tcc_save_auto_expense',
            id: $('#ae_id').val(),
            cat: $('#ae_cat').val(), 
            desc: $('#ae_desc').val(), 
            amount: $('#ae_amt').val()
        }, function(res) {
            btn.text(origText).prop('disabled', false);
            if(res && res.success) {
                $('#ae_id').val('');
                $('#frm_auto_expense')[0].reset();
                $('#ae_cancel_edit').hide();
                $('#frm_auto_expense button[type="submit"]').text('Set Daily');
                loadMasterData();
                alert("Recurring expense saved successfully!");
            } else {
                alert("Error: Data could not be saved. Please check your inputs.");
            }
        }).fail(function() {
            btn.text(origText).prop('disabled', false);
            alert("Server Error: Your database could not process the request.");
        });
    });

    $(document).on('click', '.edit-ae-btn', function() {
        $('#ae_id').val($(this).data('id'));
        $('#ae_cat').val($(this).data('cat'));
        $('#ae_desc').val($(this).data('desc'));
        $('#ae_amt').val($(this).data('amount'));
        
        $('#frm_auto_expense button[type="submit"]').text('Update');
        $('#ae_cancel_edit').show();
        
        $('html, body').animate({
            scrollTop: $("#frm_auto_expense").offset().top - 80
        }, 300);
    });

    $('#ae_cancel_edit').on('click', function() {
        $('#ae_id').val('');
        $('#frm_auto_expense')[0].reset();
        $('#frm_auto_expense button[type="submit"]').text('Set Daily');
        $(this).hide();
    });

    $(document).on('click', '.del-ae-btn', function() {
        if(!confirm('Delete this auto-daily expense? (Past logs will remain untouched)')) return;
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_delete_auto_expense', id: $(this).data('id') }, function(res) {
            if(res.success) loadMasterData();
        });
    });

    // --- QUICK STATUS FILTERS ---
    $(document).on('click', '.bk-filter-btn', function() {
        $('.bk-filter-btn').css({'background':'#fff', 'color':'#475569'});
        $(this).css({'background':'#0f172a', 'color':'#fff'});
        currentBookingFilter = $(this).data('filter');
        try { renderBookingDropdown(); } catch(e){}
    });

    function renderBookingDropdown() {
        if (!masterData) return;
        let currentSelection = $('#bk_select').val();
        let $sel = $('#bk_select');

        if ($sel.hasClass("select2-hidden-accessible")) {
            $sel.select2('destroy');
        }

        $sel.empty().append('<option value="">-- Search / Choose a Booking --</option>');
        
        if (masterData.bookings) {
            $.each(masterData.bookings, function(id, data) {
                let flags = [];
                let isAdvance = false, isCustDue = false, isVenDue = false;

                let b_inc = parseFloat(data.income) || 0;
                let b_cost = parseFloat(data.auto_cost) || 0;
                
                if (b_inc <= b_cost) { flags.push('⏳ Advance'); isAdvance = true; }
                
                let manualBase = 0;
                if(data.quote_addons && data.quote_addons.length > 0) {
                    data.quote_addons.forEach(a => { manualBase += parseFloat(a.amount) || 0; });
                }
                if(data.manual_expenses && data.manual_expenses.length > 0) {
                    data.manual_expenses.forEach(me => { 
                        if(me.type === 'base_cost' || !me.type) manualBase += parseFloat(me.amount) || 0; 
                    });
                }
                
                let adj_cost = b_cost + manualBase;
                let vendor_pending = adj_cost - (parseFloat(data.vendor_paid) || 0);
                let cust_pending = (parseFloat(data.pkg_value) || 0) - b_inc;

                if(cust_pending > 0) { flags.push('🔴 Cust Due'); isCustDue = true; }
                if(vendor_pending > 0) { flags.push('🟠 Ven Due'); isVenDue = true; }
                
                let match = false;
                if (currentBookingFilter === 'all') match = true;
                if (currentBookingFilter === 'advance' && isAdvance) match = true;
                if (currentBookingFilter === 'cust_due' && isCustDue) match = true;
                if (currentBookingFilter === 'ven_due' && isVenDue) match = true;

                if (match) {
                    let flagStr = flags.length > 0 ? ` [${flags.join(' | ')}]` : '';
                    $sel.append(`<option value="${id}">${data.title}${flagStr}</option>`);
                }
            });
        }

        $sel.select2({
            placeholder: "-- Search / Choose a Booking --",
            allowClear: true,
            width: '100%'
        });

        if(currentSelection && $sel.find(`option[value="${currentSelection}"]`).length > 0) {
            $sel.val(currentSelection).trigger('change');
        } else {
            $sel.val(null).trigger('change');
            $('#bk_dashboard').hide();
            $('#bk_view_quote').hide();
        }
    }

    function addVendorRow(date = '', desc = '', amt = '') {
        let today = new Date().toISOString().split('T')[0];
        if(!date) date = today;
        let html = `
        <div class="tcc-repeater-row bk-vendor-row" style="margin-bottom:6px; display:flex; gap:6px;">
            <input type="date" name="vp_date[]" value="${date}" style="flex:1; max-width:130px;" required>
            <input type="text" name="vp_desc[]" value="${desc}" placeholder="Vendor Name / Details" style="flex:2;" required>
            <input type="number" name="vp_amt[]" class="bk-vendor-amt" value="${amt}" step="0.01" min="1" placeholder="Amount (₹)" style="flex:1; max-width:110px;" required>
            <button type="button" class="tcc-btn-del remove-bk-vendor" style="margin:0;">X</button>
        </div>`;
        $('#bk_vendor_wrapper').append(html);
    }

    function addManualRow(desc = '', amt = '', type = 'base_cost', isAddon = false) {
        let addonClass = isAddon ? 'is-addon' : '';
        let ro = isAddon ? 'readonly style="background:#f1f5f9; color:#475569; flex:2;" title="Auto-fetched from Quote"' : 'style="flex:2;"';
        let roAmt = isAddon ? 'readonly style="background:#f1f5f9; color:#475569; flex:1; max-width:110px;"' : 'style="flex:1; max-width:110px;"';
        let roType = isAddon ? 'disabled style="background:#f1f5f9; color:#475569; flex:1; max-width:130px;"' : 'style="flex:1; max-width:130px;"';
        let delBtn = isAddon ? '<span style="width:28px;"></span>' : '<button type="button" class="tcc-btn-del remove-bk-manual" style="margin:0;">X</button>';
        
        let html = `
        <div class="tcc-repeater-row bk-manual-row ${addonClass}" style="margin-bottom:6px; display:flex; gap:6px;">
            <select name="exp_type[]" class="bk-manual-type" ${roType} required>
                <option value="base_cost" ${type === 'base_cost' ? 'selected' : ''}>+ Base Cost</option>
                <option value="pkg_value" ${type === 'pkg_value' ? 'selected' : ''}>+ Package Value</option>
                <option value="net_profit" ${type === 'net_profit' ? 'selected' : ''}>+ Net Profit</option>
            </select>
            ${isAddon ? `<input type="hidden" name="exp_type[]" value="${type}">` : ''} 
            <input type="text" name="exp_desc[]" value="${desc}" placeholder="Details..." ${ro} required>
            <input type="number" name="exp_amt[]" class="bk-manual-amt" value="${amt}" step="0.01" min="1" placeholder="Amount (₹)" ${roAmt} required>
            ${delBtn}
        </div>`;
        $('#bk_manual_wrapper').append(html);
    }

    $('#bk_add_vendor').on('click', function() { addVendorRow(); });
    $('#bk_add_manual').on('click', function() { addManualRow(); });
    
    $(document).on('click', '.remove-bk-vendor, .remove-bk-manual', function() { 
        $(this).closest('.tcc-repeater-row').remove(); 
        calcBookingLiveProfit();
    });

    $('#bk_select').on('change', function() {
        let qid = $(this).val();
        if(!qid || !masterData || !masterData.bookings[qid]) {
            $('#bk_dashboard').hide();
            $('#bk_view_quote').hide();
            return;
        }

        let b = masterData.bookings[qid];
        
        $('#bk_view_quote').attr('href', b.url).show();
        $('#bk_income').text(expFormatINR(b.income));
        
        $('#bk_override_cost').val(b.auto_cost);
        $('#bk_auto_pt').text(expFormatINR(b.auto_pt));
        $('#bk_auto_gst').text(expFormatINR(b.auto_gst));

        $('#bk_vendor_wrapper').empty();
        if(b.vendor_history && b.vendor_history.length > 0) {
            b.vendor_history.forEach(v => { addVendorRow(v.date, v.desc, v.amount); });
        }

        $('#bk_manual_wrapper').empty();
        
        if(b.quote_addons && b.quote_addons.length > 0) {
            b.quote_addons.forEach(a => { addManualRow(a.desc, a.amount, a.type, true); });
        }
        
        if(b.manual_expenses && b.manual_expenses.length > 0) {
            b.manual_expenses.forEach(e => { addManualRow(e.desc, e.amount, e.type, false); });
        }

        $('#bk_dashboard').fadeIn();
        calcBookingLiveProfit();
    });

    $(document).on('input', '.bk-manual-amt, .bk-vendor-amt, #bk_override_cost', function() {
        calcBookingLiveProfit();
    });
    
    $(document).on('change', '.bk-manual-type', function() {
        calcBookingLiveProfit();
    });

    function calcBookingLiveProfit() {
        let qid = $('#bk_select').val();
        if(!qid) return;

        let b = masterData.bookings[qid];
        let income = parseFloat(b.income) || 0;
        
        let manualBase = 0, manualPkg = 0, manualProfit = 0;
        $('.bk-manual-row').each(function() { 
            let t = $(this).find('.bk-manual-type').val();
            let a = parseFloat($(this).find('.bk-manual-amt').val()) || 0;
            if(t === 'base_cost') manualBase += a;
            else if(t === 'pkg_value') manualPkg += a;
            else if(t === 'net_profit') manualProfit += a;
        });

        let vendorTotal = 0;
        $('.bk-vendor-amt').each(function() { vendorTotal += parseFloat($(this).val()) || 0; });
        $('#bk_total_vendor_paid').text(expFormatINR(vendorTotal));

        let pkg_value = (parseFloat(b.pkg_value) || 0) + manualPkg;
        $('#bk_pkg_value').text(expFormatINR(pkg_value));
        
        let cust_pending = pkg_value - income;
        if(cust_pending < 0) cust_pending = 0;
        if(cust_pending > 0) {
            $('#bk_cust_pending').text(expFormatINR(cust_pending));
            $('#bk_cust_pending_badge').fadeIn();
        } else {
            $('#bk_cust_pending_badge').hide();
        }

        let current_base_cost = (parseFloat($('#bk_override_cost').val()) || 0) + manualBase;
        
        let pending = current_base_cost - vendorTotal;
        if(pending < 0) pending = 0;
        $('#bk_pending_cost').text(expFormatINR(pending));

        if(pending > 0) {
            $('#bk_vendor_pending_badge').css('color', '#dc2626').show();
        } else {
            $('#bk_vendor_pending_badge').css('color', '#16a34a');
            $('#bk_pending_cost').text('All Cleared ✔');
        }

        let auto_pt = parseFloat(b.auto_pt) || 0;
        let auto_pg = parseFloat(b.auto_pg) || 0;
        let actual_pg = parseFloat(b.actual_pg) || 0;
        let auto_gst = parseFloat(b.auto_gst) || 0;

        $('#bk_auto_pg').html(`${expFormatINR(auto_pg)} <span style="color:#64748b; font-size:10px; font-weight:normal;">(Actual: ${expFormatINR(actual_pg)})</span>`);

        let expectedTotalCost = current_base_cost + auto_pt + auto_pg + auto_gst;
        $('#bk_auto_total').text(expFormatINR(expectedTotalCost));

        let expectedProfit = pkg_value - expectedTotalCost + manualProfit;
        $('#bk_expected_profit').text(expFormatINR(expectedProfit)).css('color', expectedProfit < 0 ? '#dc2626' : '#0ea5e9');

        let is_fully_paid_live = income >= (pkg_value - 1);
        if (!is_fully_paid_live) {
            $('#bk_unconfirmed_warning').text('⚠️ Profit Excluded from Master Dashboard (Customer Payment Pending)').fadeIn();
        } else {
            $('#bk_unconfirmed_warning').hide();
        }
    }

    $('#bk_save_manual_btn').on('click', function() {
        let qid = $('#bk_select').val();
        if(!qid) return;

        let btn = $(this);
        let origText = btn.text();
        btn.text('Saving...').prop('disabled', true);

        let data = $('#bk_dashboard :input').not('.is-addon :input').serializeArray();
        data.push({name: 'action', value: 'tcc_save_booking_expense'});
        data.push({name: 'quote_id', value: qid});

        $.post(tcc_exp_obj.ajax_url, data, function(res) {
            btn.text(origText).prop('disabled', false);
            if(res.success) {
                loadMasterData(function() {
                    alert("Booking Finances Updated & Master Sync Successful!");
                });
            }
        });
    });

    loadMasterData();
});