jQuery(document).ready(function($) {

    // === INTERCONNECTIVITY EVENT LISTENERS ===
    $(document).on('tcc_finances_updated', function(e, source) {
        if (source === 'tcc-expenses') return;
        loadMasterData(function() {
            let currentBooking = $('#bk_select').val();
            if(currentBooking) {
                $('#bk_select').val(currentBooking).trigger('change');
            }
        });
    });

    let masterData = null;
    let currentBookingFilter = 'all';

    function expFormatINR(amount) {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(amount);
    }

    function getLocalDateString(dateObj) {
        let y = dateObj.getFullYear();
        let m = String(dateObj.getMonth() + 1).padStart(2, '0');
        let d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
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
        onChange: function(selectedDates) {
            if (selectedDates.length === 2) try { calculateDashboardTotals(); } catch(e) {}
        }
    });

    $('#m_filter_clear').on('click', function(e) {
        e.preventDefault();
        fpInstance.clear(); 
        try { calculateDashboardTotals(); } catch(e) {}
    });

    let ptNow = new Date();
    let ptSevenDaysAgo = new Date();
    ptSevenDaysAgo.setDate(ptNow.getDate() - 6);

    let fpPtInstance = flatpickr("#pt_filter_range", {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: [ptSevenDaysAgo, ptNow],
        onChange: function(selectedDates) {
            if (selectedDates.length === 2) try { renderPartnerLedger(); } catch(e) {}
        }
    });
    
    $('#pt_filter_clear').on('click', function(e) {
        e.preventDefault();
        fpPtInstance.clear(); 
        try { renderPartnerLedger(); } catch(e) {}
    });

    function loadMasterData(callback = null) {
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_load_master_finances' }, function(res) {
            if(res.success) {
                masterData = res.data;
                try { renderAutoExpenses(); } catch(err) {}
                try { calculateDashboardTotals(); } catch(err) {}
                try { renderBookingDropdown(); } catch(err) {}
                try { renderPartners(); } catch(err) {}
                try { renderCustomPL(); } catch(err) {}
                try { renderPartnerLedger(); } catch(err) {}
                if(callback) callback();
            }
        });
    }

    function calculateDashboardTotals() {
        if (!masterData) return;
        
        let dates = fpInstance.selectedDates;
        let hasFilter = dates.length === 2;
        let start = '', end = '', prevStart = '', prevEnd = '';
        let hasCompare = false;

        if (hasFilter) {
            start = fpInstance.formatDate(dates[0], "Y-m-d");
            end = fpInstance.formatDate(dates[1], "Y-m-d");
            let dStart = new Date(dates[0]), dEnd = new Date(dates[1]);
            let diffTime = Math.abs(dEnd - dStart);
            let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            let pEnd = new Date(dStart); pEnd.setDate(pEnd.getDate() - 1);
            let pStart = new Date(pEnd); pStart.setDate(pStart.getDate() - diffDays + 1);
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
        let generalHtml = '', filteredGeneral = [];
        let t_general_expenses = 0, p_general_expenses = 0;
        
        if (masterData.general_expenses) {
            let genExpenses = Array.isArray(masterData.general_expenses) ? masterData.general_expenses : Object.values(masterData.general_expenses);
            genExpenses.forEach(e => {
                if(!e || typeof e !== 'object') return;
                let amt = parseFloat(e.amount) || 0;
                if (hasFilter) {
                    if (e.date >= start && e.date <= end) { t_general_expenses += amt; filteredGeneral.push(e); } 
                    else if (hasCompare && e.date >= prevStart && e.date <= prevEnd) { p_general_expenses += amt; }
                } else {
                    t_general_expenses += amt; filteredGeneral.push(e);
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
                
                let paidByName = 'Agency / Bank';
                if(e.paid_by && masterData.partners) {
                    let pt = masterData.partners.find(p => p.id === e.paid_by);
                    if(pt) paidByName = pt.name;
                }
                let paidByBadge = e.paid_by ? `<span style="font-size:10px; background:#e0f2fe; color:#0284c7; padding:2px 6px; border-radius:3px; font-weight:bold; margin-left:5px;">Paid by: ${paidByName}</span>` : '';

                generalHtml += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;">${e.date}</td><td style="padding:8px;">${e.category}</td><td style="padding:8px; color:#64748b;">${e.desc} ${paidByBadge}</td>
                            <td style="padding:8px; font-weight:bold; color:#dc2626;">-${expFormatINR(e.amount)}</td>
                            <td style="padding:8px; text-align:right;">
                                <button type="button" class="edit-ge-btn" data-id="${e.id}" data-date="${e.date}" data-cat="${e.category}" data-desc="${safeDesc}" data-amount="${e.amount}" data-paidby="${e.paid_by || ''}" style="background:none; border:none; color:#0369a1; cursor:pointer; text-decoration:underline; margin-right:8px; font-size:12px;">Edit</button>
                                <button type="button" class="del-ge-btn" data-id="${e.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline; font-size:12px;">Delete</button>
                            </td></tr>`;
            });
            generalHtml += `</table>`;
        }
        $('#ge_history_table').html(generalHtml);

        if (masterData.bookings) {
            $.each(masterData.bookings, function(id, b) {
                let inCurrentBooking = true, inPrevBooking = false;
                if (hasFilter) {
                    inCurrentBooking = (b.booking_date >= start && b.booking_date <= end);
                    inPrevBooking = (hasCompare && b.booking_date >= prevStart && b.booking_date <= prevEnd);
                }
                let manualBase = 0, manualProfit = 0, manualPkg = 0;
                if(b.quote_addons && b.quote_addons.length > 0) { b.quote_addons.forEach(addon => { manualBase += parseFloat(addon.amount) || 0; }); }
                if(b.manual_expenses && b.manual_expenses.length > 0) {
                    b.manual_expenses.forEach(me => { 
                        let amt = parseFloat(me.amount) || 0; let type = me.type || 'base_cost';
                        if(type === 'base_cost') manualBase += amt;
                        else if(type === 'net_profit') manualProfit += amt;
                        else if(type === 'pkg_value') manualPkg += amt;
                    });
                }
                let b_income = parseFloat(b.income) || 0;
                if (b.payment_history && b.payment_history.length > 0) {
                    b.payment_history.forEach(pay => {
                        let pAmt = parseFloat(pay.amount) || 0; let pDate = pay.date ? pay.date.split('T')[0] : b.booking_date; 
                        if (hasFilter) {
                            if (pDate >= start && pDate <= end) t_income += pAmt;
                            else if (hasCompare && pDate >= prevStart && pDate <= prevEnd) p_income += pAmt;
                        } else { t_income += pAmt; }
                    });
                } else {
                    if (inCurrentBooking) t_income += b_income;
                    if (inPrevBooking) p_income += b_income;
                }

                let b_base_cost = (parseFloat(b.auto_cost) || 0) + manualBase;
                let b_vendor_paid = parseFloat(b.vendor_paid) || 0;
                let b_actual_pg = parseFloat(b.actual_pg) || 0;
                let b_tax_waiver = parseFloat(b.tax_waiver) || 0;

                let b_actual_paid_expense = b_base_cost + parseFloat(b.auto_pt) + b_actual_pg + parseFloat(b.auto_gst) - b_tax_waiver;
                let b_expected_total_expense = b_base_cost + parseFloat(b.auto_pt) + parseFloat(b.auto_pg) + parseFloat(b.auto_gst) - b_tax_waiver;
                let b_pkg_value = (parseFloat(b.pkg_value) || 0) + manualPkg;
                
                let cleared_value = b_income + b_vendor_paid + b_tax_waiver;
                let is_fully_paid = cleared_value >= (b_pkg_value - 1); 

                if (inCurrentBooking) {
                    t_bookings++; t_travellers += parseInt(b.pax) || 0;
                    let dName = b.destination || 'Unknown';
                    if(!dest_counts[dName]) dest_counts[dName] = 0;
                    dest_counts[dName]++;
                }
                if (inPrevBooking) { p_bookings++; p_travellers += parseInt(b.pax) || 0; }

                if (is_fully_paid) {
                    let fpDate = b.final_payment_date || b.booking_date;
                    let inCurrentFP = true, inPrevFP = false;
                    if (hasFilter) {
                        inCurrentFP = (fpDate >= start && fpDate <= end);
                        inPrevFP = (hasCompare && fpDate >= prevStart && fpDate <= prevEnd);
                    }
                    if (inCurrentFP) {
                        t_expense_paid += b_actual_paid_expense;
                        t_pt += parseFloat(b.auto_pt) || 0; t_pg += b_actual_pg; t_gst += parseFloat(b.auto_gst) || 0;
                        t_net_profit += (b_pkg_value + manualProfit - b_expected_total_expense);
                    }
                    if (inPrevFP) {
                        p_expense_paid += b_actual_paid_expense;
                        p_net_profit += (b_pkg_value + manualProfit - b_expected_total_expense);
                    }
                }
            });
        }

        if (masterData.custom_pl) {
            masterData.custom_pl.forEach(pl => {
                let plAmt = parseFloat(pl.amount) || 0;
                
                if(pl.type === 'investment') return;

                let isProfit = pl.type === 'profit';
                if (hasFilter) {
                    if (pl.date >= start && pl.date <= end) t_net_profit += isProfit ? plAmt : -plAmt;
                    else if (hasCompare && pl.date >= prevStart && pl.date <= prevEnd) p_net_profit += isProfit ? plAmt : -plAmt;
                } else {
                    t_net_profit += isProfit ? plAmt : -plAmt;
                }
            });
        }

        t_net_profit -= t_general_expenses;
        p_net_profit -= p_general_expenses;

        let destArr = []; for (let d in dest_counts) { destArr.push({ dest: d, count: dest_counts[d] }); }
        destArr.sort((a, b) => b.count - a.count);
        let top5 = destArr.slice(0, 5).map(item => `<strong>${item.dest}</strong> (${item.count})`).join(', ');
        $('#m_top_destinations').html(top5 === '' ? 'None' : top5);
        $('#m_income').text(expFormatINR(t_income)); $('#m_expense').text(expFormatINR(t_expense_paid));
        $('#m_profit').text(expFormatINR(t_net_profit)).css('color', t_net_profit < 0 ? '#dc2626' : '#0ea5e9');
        $('#m_total_bookings').text(t_bookings); $('#m_total_travellers').text(t_travellers);
        $('#m_pt').text(expFormatINR(t_pt)); $('#m_pg').text(expFormatINR(t_pg)); $('#m_gst').text(expFormatINR(t_gst));

        function getTrendHtml(current, prev, invertColor = false) {
            if (!hasCompare) return '';
            let pct = (prev === 0) ? (current === 0 ? 0 : 100) : ((current - prev) / prev) * 100;
            let isUp = pct > 0, isDown = pct < 0;
            let color = '#64748b', icon = '▬';
            if (isUp) { color = invertColor ? '#dc2626' : '#16a34a'; icon = '▲'; }
            if (isDown) { color = invertColor ? '#16a34a' : '#dc2626'; icon = '▼'; }
            let pctStr = Math.abs(pct) === Infinity ? '100%' : Math.abs(pct).toFixed(1) + '%';
            if(pctStr !== '0.0%' && pctStr !== '0%') return `<div style="color:${color}; font-size:10px; font-weight:bold; margin-top:3px;">${icon} ${pctStr} vs prev period</div>`;
            return `<div style="color:#64748b; font-size:10px; font-weight:bold; margin-top:3px;">▬ No Change</div>`;
        }
        $('#m_income_compare').html(getTrendHtml(t_income, p_income));
        $('#m_expense_compare').html(getTrendHtml(t_expense_paid, p_expense_paid, true));
        $('#m_profit_compare').html(getTrendHtml(t_net_profit, p_net_profit));
        $('#m_bookings_compare').html(getTrendHtml(t_bookings, p_bookings));
        $('#m_travellers_compare').html(getTrendHtml(t_travellers, p_travellers));
    }

    $('#frm_general_expense').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]'); let origText = btn.text(); btn.text('Saving...').prop('disabled', true);
        $.post(tcc_exp_obj.ajax_url, {
            action: 'tcc_save_general_expense', id: $('#ge_id').val(), date: $('#ge_date').val(), cat: $('#ge_cat').val(), desc: $('#ge_desc').val(), amount: $('#ge_amt').val(), paid_by: $('#ge_paid_by').val()
        }, function(res) {
            btn.prop('disabled', false);
            if(res.success) { 
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                $('#ge_id').val(''); $('#frm_general_expense')[0].reset(); btn.text('Add'); $('#ge_cancel_edit').hide(); loadMasterData(); 
            } 
            else { btn.text(origText); alert("Error saving expense."); }
        });
    });

    $(document).on('click', '.edit-ge-btn', function() {
        $('#ge_id').val($(this).data('id')); $('#ge_date').val($(this).data('date')); $('#ge_cat').val($(this).data('cat'));
        $('#ge_desc').val($(this).data('desc')); $('#ge_amt').val($(this).data('amount')); $('#ge_paid_by').val($(this).data('paidby'));
        $('#frm_general_expense button[type="submit"]').text('Update'); $('#ge_cancel_edit').show();
        $('html, body').animate({ scrollTop: $("#frm_general_expense").offset().top - 80 }, 300);
    });
    $('#ge_cancel_edit').on('click', function() { $('#ge_id').val(''); $('#frm_general_expense')[0].reset(); $('#frm_general_expense button[type="submit"]').text('Add'); $(this).hide(); });
    $(document).on('click', '.del-ge-btn', function() {
        if(!confirm('Delete this expense?')) return;
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_delete_general_expense', id: $(this).data('id') }, function(res) { 
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                loadMasterData(); 
            }
        });
    });

    function renderAutoExpenses() {
        let html = '', expenses = [];
        if(masterData && masterData.auto_expenses) {
            if (Array.isArray(masterData.auto_expenses)) expenses = masterData.auto_expenses;
            else if (typeof masterData.auto_expenses === 'object') expenses = Object.values(masterData.auto_expenses);
        }
        if(expenses.length === 0) { html = '<div style="padding:15px; text-align:center; color:#64748b;">No automatic recurring expenses set up.</div>'; } 
        else {
            html += `<table style="width:100%; border-collapse:collapse; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #cbd5e1;">
                            <th style="padding:8px;">Status</th><th style="padding:8px;">Category & Details</th><th style="padding:8px;">Amount</th><th style="padding:8px;">Action</th>
                        </tr>`;
            expenses.forEach(e => {
                if (!e || typeof e !== 'object') return; 
                let safeDesc = e.desc ? String(e.desc).replace(/"/g, '&quot;') : ''; let cat = e.category || 'N/A';
                let amt = parseFloat(e.amount) || 0; let eid = e.id || ''; let freq = e.freq || 'daily';
                let amtStr = expFormatINR(amt);
                if (freq === 'monthly') amtStr += ` <span style="font-size:10px; font-weight:normal; color:#64748b;">/ month</span>`;
                else amtStr += ` <span style="font-size:10px; font-weight:normal; color:#64748b;">/ day</span>`;
                
                let paidByName = 'Agency / Bank';
                if(e.paid_by && masterData.partners) {
                    let pt = masterData.partners.find(p => p.id === e.paid_by);
                    if(pt) paidByName = pt.name;
                }
                let paidByBadge = e.paid_by ? `<span style="font-size:10px; background:#e0f2fe; color:#0284c7; padding:2px 6px; border-radius:3px; font-weight:bold; margin-left:5px;">Paid by: ${paidByName}</span>` : '';

                html += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;"><span style="background:#dcfce7; color:#166534; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:bold; border:1px solid #bbf7d0;">🟢 Active</span></td>
                            <td style="padding:8px;"><strong>${cat}</strong><br><span style="color:#64748b; font-size:11px;">${safeDesc} ${paidByBadge}</span></td>
                            <td style="padding:8px; font-weight:bold;">${amtStr}</td>
                            <td style="padding:8px;">
                                <button type="button" class="edit-ae-btn" data-id="${eid}" data-cat="${cat}" data-desc="${safeDesc}" data-amount="${amt}" data-freq="${freq}" data-paidby="${e.paid_by || ''}" style="background:none; border:none; color:#0369a1; cursor:pointer; text-decoration:underline; margin-right:8px; font-size:12px;">Edit</button>
                                <button type="button" class="del-ae-btn" data-id="${eid}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline; font-size:12px;">Delete</button>
                            </td></tr>`;
            });
            html += `</table>`;
        }
        $('#ae_history_table').html(html);
    }

    $('#frm_auto_expense').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]'); let origText = btn.text(); btn.text('...').prop('disabled', true);
        $.post(tcc_exp_obj.ajax_url, {
            action: 'tcc_save_auto_expense', id: $('#ae_id').val(), cat: $('#ae_cat').val(), desc: $('#ae_desc').val(), amount: $('#ae_amt').val(), freq: $('#ae_freq').val(), paid_by: $('#ae_paid_by').val()
        }, function(res) {
            btn.text(origText).prop('disabled', false);
            if(res && res.success) { 
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                $('#ae_id').val(''); $('#frm_auto_expense')[0].reset(); $('#ae_cancel_edit').hide(); $('#frm_auto_expense button[type="submit"]').text('Set Recurring'); loadMasterData(); alert("Recurring expense saved successfully!"); 
            } 
            else alert("Error: Data could not be saved. Please check your inputs.");
        }).fail(function() { btn.text(origText).prop('disabled', false); alert("Server Error: Your database could not process the request."); });
    });

    $(document).on('click', '.edit-ae-btn', function() {
        $('#ae_id').val($(this).data('id')); $('#ae_cat').val($(this).data('cat')); $('#ae_desc').val($(this).data('desc'));
        $('#ae_amt').val($(this).data('amount')); $('#ae_freq').val($(this).data('freq') || 'daily'); $('#ae_paid_by').val($(this).data('paidby'));
        $('#frm_auto_expense button[type="submit"]').text('Update'); $('#ae_cancel_edit').show();
        $('html, body').animate({ scrollTop: $("#frm_auto_expense").offset().top - 80 }, 300);
    });

    $('#ae_cancel_edit').on('click', function() { $('#ae_id').val(''); $('#frm_auto_expense')[0].reset(); $('#frm_auto_expense button[type="submit"]').text('Set Recurring'); $(this).hide(); });

    $(document).on('click', '.del-ae-btn', function() {
        if(!confirm('Delete this auto-recurring expense? (Past logs will remain untouched)')) return;
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_delete_auto_expense', id: $(this).data('id') }, function(res) { 
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                loadMasterData(); 
            }
        });
    });

    $(document).on('click', '.bk-filter-btn', function() {
        $('.bk-filter-btn').css({'background':'#fff', 'color':'#475569'}); $(this).css({'background':'#0f172a', 'color':'#fff'});
        currentBookingFilter = $(this).data('filter');
        try { renderBookingDropdown(); } catch(e){}
    });

    function renderBookingDropdown() {
        if (!masterData) return;
        let currentSelection = $('#bk_select').val(); let $sel = $('#bk_select');
        if ($sel.hasClass("select2-hidden-accessible")) $sel.select2('destroy');
        $sel.empty().append('<option value="">-- Search / Choose a Booking --</option>');
        
        if (masterData.bookings) {
            $.each(masterData.bookings, function(id, data) {
                let flags = []; let isAdvance = false, isCustDue = false, isVenDue = false;
                let b_inc = parseFloat(data.income) || 0; let b_cost = parseFloat(data.auto_cost) || 0;
                if (b_inc <= b_cost) { flags.push('⏳ Advance'); isAdvance = true; }
                
                let manualBase = 0;
                if(data.quote_addons && data.quote_addons.length > 0) { data.quote_addons.forEach(a => { manualBase += parseFloat(a.amount) || 0; }); }
                if(data.manual_expenses && data.manual_expenses.length > 0) { data.manual_expenses.forEach(me => { if(me.type === 'base_cost' || !me.type) manualBase += parseFloat(me.amount) || 0; }); }
                
                let adj_cost = b_cost + manualBase;
                let vendor_pending = adj_cost - (parseFloat(data.vendor_paid) || 0);

                let b_tax_waiver = parseFloat(data.tax_waiver) || 0;
                let cleared_value = b_inc + (parseFloat(data.vendor_paid) || 0) + b_tax_waiver;
                let cust_pending = (parseFloat(data.pkg_value) || 0) - cleared_value;

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
        $sel.select2({ placeholder: "-- Search / Choose a Booking --", allowClear: true, width: '100%' });
        if(currentSelection && $sel.find(`option[value="${currentSelection}"]`).length > 0) $sel.val(currentSelection).trigger('change');
        else { $sel.val(null).trigger('change'); $('#bk_dashboard').hide(); $('#bk_view_quote').hide(); }
    }

    function addVendorRow(date = '', desc = '', amt = '', isAuto = false) {
        let today = new Date().toISOString().split('T')[0]; if(!date) date = today;
        let ro = isAuto ? 'readonly style="background:#f1f5f9; color:#475569;" title="Auto-synced from Payments. Edit there."' : '';
        let delBtn = isAuto ? '<span style="width:28px; text-align:center;" title="Locked">🔒</span>' : '<button type="button" class="tcc-btn-del remove-bk-vendor" style="margin:0;">X</button>';
        
        let nDate = isAuto ? '' : 'name="vp_date[]"';
        let nDesc = isAuto ? '' : 'name="vp_desc[]"';
        let nAmt = isAuto ? '' : 'name="vp_amt[]"';

        let html = `
        <div class="tcc-repeater-row bk-vendor-row" style="margin-bottom:6px; display:flex; gap:6px;">
            <input type="date" ${nDate} value="${date}" style="flex:1; max-width:130px;" ${ro} required>
            <input type="text" ${nDesc} value="${desc}" placeholder="Vendor Name / Details" style="flex:2;" ${ro} required>
            <input type="number" ${nAmt} class="bk-vendor-amt" value="${amt}" step="0.01" min="1" placeholder="Amount (₹)" style="flex:1; max-width:110px;" ${ro} required>
            ${delBtn}
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
    
    $(document).on('click', '.remove-bk-vendor, .remove-bk-manual', function() { $(this).closest('.tcc-repeater-row').remove(); calcBookingLiveProfit(); });

    $('#bk_select').on('change', function() {
        let qid = $(this).val();
        if(!qid || !masterData || !masterData.bookings[qid]) { $('#bk_dashboard').hide(); $('#bk_view_quote').hide(); return; }

        let b = masterData.bookings[qid];
        $('#bk_view_quote').attr('href', b.url).show(); $('#bk_income').text(expFormatINR(b.income));
        $('#bk_override_cost').val(b.auto_cost); $('#bk_auto_pt').text(expFormatINR(b.auto_pt)); $('#bk_auto_gst').text(expFormatINR(b.auto_gst));
        $('#bk_auto_tax_waiver').text('-' + expFormatINR(b.tax_waiver || 0));

        $('#bk_vendor_wrapper').empty();
        if(b.vendor_history && b.vendor_history.length > 0) { b.vendor_history.forEach(v => { addVendorRow(v.date, v.desc, v.amount, v.is_auto); }); }
        $('#bk_manual_wrapper').empty();
        if(b.quote_addons && b.quote_addons.length > 0) { b.quote_addons.forEach(a => { addManualRow(a.desc, a.amount, a.type, true); }); }
        if(b.manual_expenses && b.manual_expenses.length > 0) { b.manual_expenses.forEach(e => { addManualRow(e.desc, e.amount, e.type, false); }); }

        $('#bk_dashboard').fadeIn();
        calcBookingLiveProfit();
    });

    $(document).on('input', '.bk-manual-amt, .bk-vendor-amt, #bk_override_cost', function() { calcBookingLiveProfit(); });
    $(document).on('change', '.bk-manual-type', function() { calcBookingLiveProfit(); });

    function calcBookingLiveProfit() {
        let qid = $('#bk_select').val();
        if(!qid) return;

        let b = masterData.bookings[qid]; let income = parseFloat(b.income) || 0;
        let manualBase = 0, manualPkg = 0, manualProfit = 0;
        $('.bk-manual-row').each(function() { 
            let t = $(this).find('.bk-manual-type').val(); let a = parseFloat($(this).find('.bk-manual-amt').val()) || 0;
            if(t === 'base_cost') manualBase += a; else if(t === 'pkg_value') manualPkg += a; else if(t === 'net_profit') manualProfit += a;
        });

        let vendorTotal = 0;
        $('.bk-vendor-amt').each(function() { vendorTotal += parseFloat($(this).val()) || 0; });
        $('#bk_total_vendor_paid').text(expFormatINR(vendorTotal));

        let pkg_value = (parseFloat(b.pkg_value) || 0) + manualPkg;
        $('#bk_pkg_value').text(expFormatINR(pkg_value));
        
        let tax_waiver = parseFloat(b.tax_waiver) || 0;
        let cleared_amt = income + vendorTotal + tax_waiver;
        let cust_pending = pkg_value - cleared_amt; if(cust_pending < 0) cust_pending = 0;
        
        if(cust_pending > 0) { $('#bk_cust_pending').text(expFormatINR(cust_pending)); $('#bk_cust_pending_badge').fadeIn(); } else { $('#bk_cust_pending_badge').hide(); }

        let current_base_cost = (parseFloat($('#bk_override_cost').val()) || 0) + manualBase;
        let pending = current_base_cost - vendorTotal; if(pending < 0) pending = 0;
        $('#bk_pending_cost').text(expFormatINR(pending));

        if(pending > 0) { $('#bk_vendor_pending_badge').css('color', '#dc2626').show(); } else { $('#bk_vendor_pending_badge').css('color', '#16a34a'); $('#bk_pending_cost').text('All Cleared ✔'); }

        let auto_pt = parseFloat(b.auto_pt) || 0; let auto_pg = parseFloat(b.auto_pg) || 0; let actual_pg = parseFloat(b.actual_pg) || 0; let auto_gst = parseFloat(b.auto_gst) || 0;
        $('#bk_auto_pg').html(`${expFormatINR(auto_pg)} <span style="color:#64748b; font-size:10px; font-weight:normal;">(Actual: ${expFormatINR(actual_pg)})</span>`);

        let expectedTotalCost = current_base_cost + auto_pt + auto_pg + auto_gst - tax_waiver;
        $('#bk_auto_total').text(expFormatINR(expectedTotalCost));

        let expectedProfit = pkg_value - expectedTotalCost + manualProfit;
        $('#bk_expected_profit').text(expFormatINR(expectedProfit)).css('color', expectedProfit < 0 ? '#dc2626' : '#0ea5e9');

        let is_fully_paid_live = cleared_amt >= (pkg_value - 1);
        if (!is_fully_paid_live) { $('#bk_unconfirmed_warning').text('⚠️ Profit Excluded from Master Dashboard (Customer Payment Pending)').fadeIn(); } else { $('#bk_unconfirmed_warning').hide(); }
    }

    $('#bk_save_manual_btn').on('click', function() {
        let qid = $('#bk_select').val(); if(!qid) return;
        let btn = $(this); let origText = btn.text(); btn.text('Saving...').prop('disabled', true);

        let data = $('#bk_dashboard :input').not('.is-addon :input').serializeArray();
        data.push({name: 'action', value: 'tcc_save_booking_expense'});
        data.push({name: 'quote_id', value: qid});

        $.post(tcc_exp_obj.ajax_url, data, function(res) {
            btn.text(origText).prop('disabled', false);
            if(res.success) { 
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                loadMasterData(function() { alert("Booking Finances Updated & Master Sync Successful!"); }); 
            }
        });
    });

    function getLatestPercent(partner) {
        if(partner.history && partner.history.length > 0) {
            let h = [...partner.history].sort((a,b) => b.date.localeCompare(a.date));
            return parseFloat(h[0].percent);
        }
        return parseFloat(partner.percent || 0); 
    }

    function renderPartners() {
        let html = '';
        let totalPercent = 0;
        
        let geOptions = '<option value="">Agency / Bank</option>';
        let aeOptions = '<option value="">Agency / Bank</option>';
        let cplOptions = '<option value="">-- Select Partner --</option>';

        if(masterData.partners && masterData.partners.length > 0) {
            html += `<table style="width:100%; border-collapse:collapse; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #cbd5e1;">
                            <th style="padding:6px;">Partner Name</th>
                            <th style="padding:6px;">Current Share %</th>
                            <th style="padding:6px; text-align:right;">Action</th>
                        </tr>`;
            masterData.partners.forEach(p => {
                let curPercent = getLatestPercent(p);
                totalPercent += curPercent;
                
                geOptions += `<option value="${p.id}">${p.name}</option>`;
                aeOptions += `<option value="${p.id}">${p.name}</option>`;
                cplOptions += `<option value="${p.id}">${p.name}</option>`;
                
                html += `<tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:6px; font-weight:bold; color:#475569;">${p.name}</td>
                    <td style="padding:6px; color:#0369a1; font-weight:bold;">${curPercent}%</td>
                    <td style="padding:6px; text-align:right;">
                        <button type="button" class="edit-pt-btn" data-id="${p.id}" data-name="${p.name}" data-percent="${curPercent}" style="background:none; border:none; color:#0369a1; cursor:pointer; font-size:11px; text-decoration:underline; margin-right:5px;">Edit</button>
                        <button type="button" class="del-pt-btn" data-id="${p.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:11px; text-decoration:underline;">Remove</button>
                    </td>
                </tr>`;
            });
            html += `</table>`;
            if(totalPercent > 100) html += `<div style="color:#dc2626; font-size:11px; margin-top:8px; font-weight:bold; background:#fee2e2; padding:4px; border-radius:3px;">⚠️ Warning: Total shares exceed 100% (${totalPercent}%)</div>`;
            else html += `<div style="color:#16a34a; font-size:11px; margin-top:8px; font-weight:bold; background:#dcfce7; padding:4px; border-radius:3px;">Total Distributed: ${totalPercent}%</div>`;
        } else {
            html = '<div style="padding:10px; color:#64748b;">No partners configured yet.</div>';
        }
        $('#pt_list_table').html(html);

        let curGe = $('#ge_paid_by').val();
        $('#ge_paid_by').html(geOptions).val(curGe || '');
        
        let curAe = $('#ae_paid_by').val();
        $('#ae_paid_by').html(aeOptions).val(curAe || '');
        
        let curCpl = $('#cpl_partner').val();
        $('#cpl_partner').html(cplOptions).val(curCpl || '');
    }

    function renderCustomPL() {
        let html = '';
        let filteredPL = [];
        
        if(masterData.custom_pl && masterData.custom_pl.length > 0) {
            let tObj = new Date();
            let todayStr = getLocalDateString(tObj);
            
            tObj.setDate(tObj.getDate() - 6);
            let sevenDaysAgoStr = getLocalDateString(tObj);
            
            filteredPL = masterData.custom_pl.filter(pl => pl.date >= sevenDaysAgoStr && pl.date <= todayStr);
        }
        
        if(filteredPL.length > 0) {
            html += `<table style="width:100%; border-collapse:collapse; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #cbd5e1;">
                            <th style="padding:6px;">Date</th><th style="padding:6px;">Details</th><th style="padding:6px;">Amount</th><th style="padding:6px;"></th>
                        </tr>`;
            filteredPL.forEach(pl => {
                let isProfit = pl.type === 'profit';
                let isInv = pl.type === 'investment';
                let color = isProfit ? '#16a34a' : (isInv ? '#d97706' : '#dc2626');
                
                let partnerName = '';
                if(isInv && pl.partner_id && masterData.partners) {
                    let pt = masterData.partners.find(p => p.id === pl.partner_id);
                    if(pt) partnerName = `<span style="font-size:10px; display:block; color:#64748b;">By: ${pt.name}</span>`;
                }

                html += `<tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:6px;">${pl.date}</td>
                    <td style="padding:6px; color:#475569;">${pl.desc} ${partnerName}</td>
                    <td style="padding:6px; font-weight:bold; color:${color};">${isProfit || isInv ? '+' : '-'}${expFormatINR(pl.amount)}</td>
                    <td style="padding:6px; text-align:right;"><button type="button" class="del-cpl-btn" data-id="${pl.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:11px; text-decoration:underline;">X</button></td>
                </tr>`;
            });
            html += `</table>`;
        } else {
            html = '<div style="padding:10px; color:#64748b;">No manual records in the last 7 days.</div>';
        }
        $('#cpl_list_table').html(html);
    }

    $('#frm_partner_setup').on('submit', function(e) {
        e.preventDefault();
        
        let editId = $('#pt_id').val();
        let newPercent = parseFloat($('#pt_percent').val()) || 0;
        let otherTotal = 0;
        
        if(masterData.partners) {
            masterData.partners.forEach(p => {
                if(p.id !== editId) otherTotal += getLatestPercent(p);
            });
        }
        
        if(otherTotal + newPercent > 100) {
            let avail = 100 - otherTotal;
            alert(`Cannot save! The maximum available share remaining is ${avail}%. Please adjust your input.`);
            return;
        }

        let btn = $(this).find('button[type="submit"]'); let origText = btn.text(); btn.text('...').prop('disabled', true);
        $.post(tcc_exp_obj.ajax_url, { 
            action: 'tcc_save_partner', 
            id: editId,
            name: $('#pt_name').val(), 
            percent: newPercent
        }, function(res) {
            btn.text('Save').prop('disabled', false);
            if(res.success) { 
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                $('#pt_id').val(''); $('#frm_partner_setup')[0].reset(); $('#pt_cancel_edit').hide();
                loadMasterData(); 
            }
        });
    });

    $(document).on('click', '.edit-pt-btn', function() {
        $('#pt_id').val($(this).data('id')); 
        $('#pt_name').val($(this).data('name')); 
        $('#pt_percent').val($(this).data('percent'));
        $('#frm_partner_setup button[type="submit"]').text('Update Share'); 
        $('#pt_cancel_edit').show();
    });

    $('#pt_cancel_edit').on('click', function() { 
        $('#pt_id').val(''); $('#frm_partner_setup')[0].reset(); 
        $('#frm_partner_setup button[type="submit"]').text('Save'); 
        $(this).hide(); 
    });

    $(document).on('click', '.del-pt-btn', function() {
        if(confirm("Remove this partner?")) $.post(tcc_exp_obj.ajax_url, { action: 'tcc_delete_partner', id: $(this).data('id') }, function(res) { 
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                loadMasterData(); 
            }
        });
    });

    $('#cpl_type').on('change', function() {
        if($(this).val() === 'investment') {
            $('#cpl_partner').show().prop('required', true);
        } else {
            $('#cpl_partner').hide().prop('required', false).val('');
        }
    });

    $('#frm_custom_pl').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]'); let origText = btn.text(); btn.text('...').prop('disabled', true);
        $.post(tcc_exp_obj.ajax_url, { action: 'tcc_save_custom_pl', date: $('#cpl_date').val(), type: $('#cpl_type').val(), desc: $('#cpl_desc').val(), amount: $('#cpl_amt').val(), partner_id: $('#cpl_partner').val() }, function(res) {
            btn.text(origText).prop('disabled', false);
            if(res.success) { 
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                $('#frm_custom_pl')[0].reset(); $('#cpl_partner').hide().prop('required', false); loadMasterData(); 
            }
        });
    });

    $(document).on('click', '.del-cpl-btn', function() {
        if(confirm("Delete this manual record?")) $.post(tcc_exp_obj.ajax_url, { action: 'tcc_delete_custom_pl', id: $(this).data('id') }, function(res) { 
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-expenses']);
                loadMasterData(); 
            }
        });
    });

    function renderPartnerLedger() {
        if (!masterData) return;
        
        let dates = fpPtInstance.selectedDates;
        let hasFilter = dates.length === 2;
        let start = hasFilter ? fpPtInstance.formatDate(dates[0], "Y-m-d") : '';
        let end = hasFilter ? fpPtInstance.formatDate(dates[1], "Y-m-d") : '';

        let dailyData = {};
        function getDay(d) {
            if(!dailyData[d]) dailyData[d] = { income: 0, booking_profit: 0, everyday_expense: 0, custom_pl: 0 };
            return dailyData[d];
        }

        let partnerReimbursements = {}; 
        let partners = masterData.partners || [];
        partners.forEach(p => { partnerReimbursements[p.id] = 0; });

        if(masterData.general_expenses) { 
            masterData.general_expenses.forEach(e => { 
                let amt = parseFloat(e.amount)||0;
                getDay(e.date).everyday_expense += amt; 
                if(e.paid_by && partnerReimbursements[e.paid_by] !== undefined) {
                    partnerReimbursements[e.paid_by] += amt;
                }
            }); 
        }

        if(masterData.custom_pl) { 
            masterData.custom_pl.forEach(pl => { 
                let amt = parseFloat(pl.amount)||0; 
                if(pl.type === 'investment') {
                    if(pl.partner_id && partnerReimbursements[pl.partner_id] !== undefined) {
                        partnerReimbursements[pl.partner_id] += amt;
                    }
                } else {
                    getDay(pl.date).custom_pl += pl.type === 'profit' ? amt : -amt; 
                }
            }); 
        }
        
        if(masterData.bookings) {
            $.each(masterData.bookings, function(id, b) {
                if (b.payment_history && b.payment_history.length > 0) {
                    b.payment_history.forEach(pay => { let pDate = pay.date ? pay.date.split('T')[0] : b.booking_date; getDay(pDate).income += parseFloat(pay.amount)||0; });
                } else {
                    getDay(b.booking_date).income += parseFloat(b.income)||0;
                }

                let manualBase = 0, manualProfit = 0, manualPkg = 0;
                if(b.quote_addons) b.quote_addons.forEach(a => manualBase += parseFloat(a.amount)||0);
                if(b.manual_expenses) b.manual_expenses.forEach(me => {
                    let amt = parseFloat(me.amount)||0;
                    if(me.type === 'base_cost') manualBase += amt; else if(me.type === 'pkg_value') manualPkg += amt; else if(me.type === 'net_profit') manualProfit += amt;
                });
                
                let b_base_cost = (parseFloat(b.auto_cost)||0) + manualBase;
                let b_tax_waiver = parseFloat(b.tax_waiver) || 0;
                let expectedTotalCost = b_base_cost + parseFloat(b.auto_pt||0) + parseFloat(b.auto_pg||0) + parseFloat(b.auto_gst||0) - b_tax_waiver;
                let pkg_value = (parseFloat(b.pkg_value)||0) + manualPkg;
                let income = parseFloat(b.income)||0;
                let b_vendor_paid = parseFloat(b.vendor_paid)||0;
                let cleared_value = income + b_vendor_paid + b_tax_waiver;

                if (cleared_value >= (pkg_value - 1)) {
                    let fpDate = b.final_payment_date || b.booking_date;
                    let b_net_profit = (pkg_value + manualProfit) - expectedTotalCost;
                    getDay(fpDate).booking_profit += b_net_profit;
                }
            });
        }

        let sortedDates = Object.keys(dailyData).sort((a,b) => b.localeCompare(a));
        let finalDates = [];
        sortedDates.forEach(d => {
            if(hasFilter) { if(d >= start && d <= end) finalDates.push(d); } else finalDates.push(d);
        });

        if(finalDates.length === 0) {
            $('#pt_ledger_table').html('<div style="padding:15px; color:#64748b;">No financial activity found for the selected dates.</div>');
            return;
        }

        let pHeaders = '';
        partners.forEach(p => { 
            let curPercent = getLatestPercent(p);
            pHeaders += `<th style="padding:8px; border-left:1px solid #cbd5e1;">${p.name}<br><span style="font-size:9px;">(Curr: ${curPercent}%)</span></th>`; 
        });

        let html = `<table style="width:100%; border-collapse:collapse; text-align:right;">
            <tr style="background:#0f172a; color:#fff; font-size:11px;">
                <th style="padding:8px; text-align:left;">Date</th>
                <th style="padding:8px; color:#94a3b8; font-weight:normal;">Cash Recv.</th>
                <th style="padding:8px; color:#4ade80;">Completed<br>Profit</th>
                <th style="padding:8px; color:#f87171;">Everyday<br>Expenses</th>
                <th style="padding:8px; color:#fcd34d;">Custom<br>P/L</th>
                <th style="padding:8px; color:#38bdf8;">Daily Net Profit</th>
                ${pHeaders}
            </tr>`;

        let sumInc = 0, sumBProf = 0, sumExp = 0, sumCpl = 0, sumNet = 0;
        let sumPartners = {}; partners.forEach(p => { sumPartners[p.id] = 0; });

        finalDates.forEach(d => {
            let row = dailyData[d];
            let net = row.booking_profit - row.everyday_expense + row.custom_pl;
            
            if (row.income === 0 && row.booking_profit === 0 && row.everyday_expense === 0 && row.custom_pl === 0 && net === 0) return;

            sumInc += row.income; sumBProf += row.booking_profit; sumExp += row.everyday_expense; sumCpl += row.custom_pl; sumNet += net;

            let pCells = '';
            partners.forEach(p => {
                let applicablePercent = 0;
                if(p.history && p.history.length > 0) {
                    let sortedH = [...p.history].sort((a,b) => b.date.localeCompare(a.date)); 
                    for(let i=0; i<sortedH.length; i++) {
                        if(sortedH[i].date <= d) { applicablePercent = parseFloat(sortedH[i].percent); break; }
                    }
                } else {
                    applicablePercent = parseFloat(p.percent||0);
                }

                let share = net * (applicablePercent / 100);
                sumPartners[p.id] += share;
                pCells += `<td style="padding:8px; border-left:1px solid #e2e8f0; color:${share < 0 ? '#dc2626' : '#16a34a'}; font-weight:bold;">${expFormatINR(share)}</td>`;
            });

            html += `<tr style="border-bottom:1px solid #f1f5f9; font-size:11px;">
                <td style="padding:8px; text-align:left; font-weight:bold; color:#475569;">${d}</td>
                <td style="padding:8px; color:#94a3b8;">${expFormatINR(row.income)}</td>
                <td style="padding:8px; color:#16a34a;">+${expFormatINR(row.booking_profit)}</td>
                <td style="padding:8px; color:#dc2626;">-${expFormatINR(row.everyday_expense)}</td>
                <td style="padding:8px; color:${row.custom_pl < 0 ? '#dc2626' : '#d97706'}">${row.custom_pl >= 0 ? '+' : ''}${expFormatINR(row.custom_pl)}</td>
                <td style="padding:8px; font-weight:bold; background:#f8fafc; color:${net < 0 ? '#dc2626' : '#0ea5e9'}; font-size:12px;">${expFormatINR(net)}</td>
                ${pCells}
            </tr>`;
        });

        let pShareFooters = '';
        let pRefundFooters = '';
        let pFinalFooters = '';

        partners.forEach(p => { 
            let shareAmt = sumPartners[p.id];
            
            // Reimburses directly to the individual who paid/invested
            let refundAmt = partnerReimbursements[p.id] || 0;
            
            let finalPayout = shareAmt + refundAmt;

            pShareFooters += `<th style="padding:8px; border-left:1px solid #94a3b8; font-size:12px; color:${shareAmt < 0 ? '#fca5a5' : '#86efac'}">${expFormatINR(shareAmt)}</th>`; 
            
            let refundText = refundAmt > 0 ? '+ ' + expFormatINR(refundAmt) : '-';
            pRefundFooters += `<th style="padding:8px; border-left:1px solid #94a3b8; font-size:11px; color:#fef08a;">${refundText}</th>`; 
            
            let finalPayoutText = finalPayout < 0 ? 'DUE: ' + expFormatINR(Math.abs(finalPayout)) : expFormatINR(finalPayout);
            pFinalFooters += `<th style="padding:8px; border-left:1px solid #94a3b8; font-size:14px; font-weight:900; color:${finalPayout < 0 ? '#fca5a5' : '#4ade80'}">${finalPayoutText}</th>`; 
        });

        html += `<tr style="background:#334155; color:#fff; font-size:12px;">
            <th style="padding:8px; text-align:left;" colspan="5">P&L SHARE % TOTAL</th>
            <th style="padding:8px; font-weight:bold; color:#7dd3fc; font-size:14px;">${expFormatINR(sumNet)}</th>
            ${pShareFooters}
        </tr>`;

        html += `<tr style="background:#1e293b; color:#94a3b8; font-size:11px; border-top:1px dotted #475569;">
            <th style="padding:8px; text-align:right;" colspan="6">Individual Capital / Expense Reimbursements:</th>
            ${pRefundFooters}
        </tr>`;

        html += `<tr style="background:#0f172a; color:#fff; font-size:12px; border-top:2px solid #000;">
            <th style="padding:8px; text-align:right; color:#38bdf8;" colspan="6">NET PARTNER PAYOUT / (AMOUNT DUE):</th>
            ${pFinalFooters}
        </tr></table>`;

        $('#pt_ledger_table').html(html);
    }

    loadMasterData();
});