jQuery(document).ready(function($) {

// --- REUSABLE AJAX LIVE SEAT SYNC FUNCTION ---
    function tcc_sync_live_seats() {
        $.post(tcc_front_ajax.url, { action: 'tcc_front_live_seats' }, function(res) {
            if (res.success) {
                let liveData = res.data;
                
                $('.tcc-ft-container').each(function() {
                    let $container = $(this);
                    let isMaster = $container.hasClass('master-tour-form');
                    
                    if (isMaster) {
                        let allToursRaw = $container.attr('data-all-tours');
                        if (allToursRaw) {
                            let allTours = JSON.parse(allToursRaw);
                            for (let tId in allTours) {
                                if (liveData[tId] && allTours[tId].prices && allTours[tId].prices.dates) {
                                    allTours[tId].prices.dates.forEach(pd => {
                                        if (liveData[tId][pd.start_date] !== undefined) {
                                            pd.available = liveData[tId][pd.start_date];
                                            pd.is_sold_out = (pd.available <= 0);
                                        }
                                    });
                                }
                            }
                            $container.attr('data-all-tours', JSON.stringify(allTours));
                        }
                    }
                    
                    let tourId = $container.attr('data-tour-id');
                    if (tourId && liveData[tourId]) {
                        $container.find('.fd-date-btn-modern').each(function() {
                            let dateVal = $(this).attr('data-date');
                            
                            if (dateVal && liveData[tourId][dateVal] !== undefined) {
                                let avail = liveData[tourId][dateVal];
                                let $btn = $(this);
                                
                                if (avail <= 0) {
                                    if (!$btn.hasClass('sold-out')) {
                                        $btn.addClass('sold-out');
                                        $btn.find('input, .fd-date-check').remove();
                                        $btn.find('.fd-date-main-text').css({'color':'#94a3b8', 'text-decoration':'line-through'});
                                        $btn.find('.fd-date-sub-text').removeClass('fd-seat-avail').css({'color':'#ef4444', 'font-weight':'bold'}).html('Sold Out');
                                        $btn.find('span').last().css({'background':'#f1f5f9', 'color':'#94a3b8'});
                                    }
                                } else {
                                    if ($btn.hasClass('sold-out')) {
                                        $btn.removeClass('sold-out');
                                        $btn.find('.fd-date-main-text').css({'color':'', 'text-decoration':''});
                                        let seatText = avail < 6 ? '<span style="color:#ea580c; font-weight:bold;">🔥 Only ' + avail + ' Seats Left!</span>' : avail + ' Seats Left';
                                        $btn.find('.fd-date-sub-text').addClass('fd-seat-avail').css({'color':'', 'font-weight':''}).html(seatText);
                                        $btn.prepend('<input type="radio" name="tcc_ft_date" value="' + dateVal + '" style="display:none;"><div class="fd-date-check">&#10003;</div>');
                                        $btn.find('span').last().css({'background':'#e0e7ff', 'color':'#3730a3'});
                                    } else {
                                        let seatText = avail < 6 ? '<span style="color:#ea580c; font-weight:bold;">🔥 Only ' + avail + ' Seats Left!</span>' : avail + ' Seats Left';
                                        $btn.find('.fd-seat-avail').html(seatText);
                                    }
                                }
                            }
                        });
                    }
                });
            }
        });
    }

    tcc_sync_live_seats();
    $(document).on('click', '#btn-ts-next, #btn-calc-back, #btn-calc-next, #btn-lead-back', function() { tcc_sync_live_seats(); });
    $(document).on('click', '.tcc-ft-dest-btn, .tcc-ft-tour-btn', function() { tcc_sync_live_seats(); });
  
    $('.tcc-ft-container').each(function() {
        let $container = $(this);
        let pricesRaw = $container.attr('data-prices');
        let prices = pricesRaw ? JSON.parse(pricesRaw) : {};
        let flow = $container.attr('data-flow');
        let isMaster = $container.hasClass('master-tour-form');
        let isLoggedIn = $container.attr('data-logged-in') === 'true';
        let defaultTitle = $container.find('.tcc-ft-title').attr('data-default-title') || $container.find('.tcc-ft-title').text();
        
        let $stepTS = $container.find('#tcc-ft-step-tour-select');
        let $stepCalc = $container.find('#tcc-ft-step-calc');
        let $stepLead = $container.find('#tcc-ft-step-lead');

        // --- 1. SETUP 3-STEP NAVIGATION BASED ON FLOW ---
        if (flow === 'lead_first') {
            $stepTS.hide(); $stepCalc.hide(); $stepLead.show();
            $container.find('#btn-lead-back').hide();
            $container.find('#btn-lead-next').show(); 
            $container.find('#btn-lead-submit').hide();

            $container.find('#btn-ts-back').show(); 
            $container.find('#btn-ts-next').show(); 

            $container.find('#btn-calc-back').show(); 
            $container.find('#btn-calc-next').hide(); 
            $container.find('#btn-calc-submit').show(); 
        } else {
            $stepTS.show(); $stepCalc.hide(); $stepLead.hide();
            $container.find('#btn-ts-back').hide();
            $container.find('#btn-ts-next').show(); 

            $container.find('#btn-calc-back').show(); 
            $container.find('#btn-calc-next').show(); 
            $container.find('#btn-calc-submit').hide();

            $container.find('#btn-lead-back').show(); 
            $container.find('#btn-lead-next').hide();
            $container.find('#btn-lead-submit').show(); 
        }

        // --- 2. DESTINATION / TOUR LOGIC (Master Form Only) ---
        $container.on('change', 'input[name="tcc_ft_destination"]', function() {
            $container.find('.tcc-ft-dest-btn').removeClass('active');
            $(this).closest('.tcc-ft-dest-btn').addClass('active');
            
            let dest = $(this).val();
            $container.find('#tcc-ft-date-card').hide();
            $container.find('#tcc-ft-date-card .tcc-ft-date-grid').empty();
            $container.find('#tcc-ft-date').val('');
            
            $container.find('#tcc-ft-tour-card').fadeIn(200);
            $container.find('.tcc-ft-tour-btn').each(function() {
                if ($(this).attr('data-dest') === dest) { $(this).show(); } else { $(this).hide(); }
                $(this).removeClass('active');
                $(this).find('input').prop('checked', false);
            });

            $container.attr('data-tour-id', '');
            $container.find('.tcc-ft-title').text(defaultTitle); // Reset title when changing dest
            prices = {}; resetQuantities(); calculateTotals();
        });

        $container.on('change', 'input[name="tcc_ft_tour"]', function() {
            $container.find('.tcc-ft-tour-btn').removeClass('active');
            $(this).closest('.tcc-ft-tour-btn').addClass('active');
            
            let tourId = $(this).val();
            $container.attr('data-tour-id', tourId); 

            let freshToursRaw = $container.attr('data-all-tours');
            let freshTours = freshToursRaw ? JSON.parse(freshToursRaw) : null;

            if (freshTours && freshTours[tourId]) {
                let selectedTour = freshTours[tourId];
                prices = selectedTour.prices; 
                $container.find('.tcc-ft-title').text(selectedTour.title);

                let $dateGrid = $container.find('#tcc-ft-date-card .tcc-ft-date-grid');
                $dateGrid.empty();
                $container.find('.tcc-ft-cat-filters').remove(); // Failsafe clear

                if (prices.dates && prices.dates.length > 0) {
                    prices.dates.forEach(pd => {
                        let cat = pd.hotel_cat || 'Standard';
                        let dObj = new Date(pd.start_date);
                        let formatted = dObj.toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
                        if (formatted === 'Invalid Date') formatted = pd.start_date;
                        if (pd.available <= 0) {
                            $dateGrid.append(`
                                <label class="fd-date-btn-modern sold-out" data-cat="${cat}" data-date="${pd.start_date}">
                                    <span class="fd-date-main-text" style="display:block; text-align:center; color:#94a3b8; text-decoration: line-through;">🗓️ ${formatted}</span>
                                    <span class="fd-date-sub-text" style="display:block; text-align:center; color:#ef4444; font-weight:bold;">Sold Out</span>
                                    <span style="display:block; font-size:10px; background:#f1f5f9; color:#94a3b8; padding:2px 6px; border-radius:3px; margin:4px auto 0; width:fit-content; text-align:center;">${cat}</span>
                                </label>
                            `);
                        } else {
                            let seatText = pd.available < 6 ? '<span style="color:#ea580c; font-weight:bold;">🔥 Only ' + pd.available + ' Seats Left!</span>' : pd.available + ' Seats Left';
                            $dateGrid.append(`
                                <label class="fd-date-btn-modern" data-cat="${cat}" data-date="${pd.start_date}">
                                    <input type="radio" name="tcc_ft_date" value="${pd.start_date}" style="display:none;">
                                    <div class="fd-date-check">&#10003;</div>
                                    <span class="fd-date-main-text" style="display:block; text-align:center;">🗓️ ${formatted}</span>
                                    <span class="fd-date-sub-text fd-seat-avail" style="display:block; text-align:center;">${seatText}</span>
                                    <span style="display:block; font-size:10px; background:#e0e7ff; color:#3730a3; padding:2px 6px; border-radius:3px; margin:4px auto 0; width:fit-content; text-align:center;">${cat}</span>
                                </label>
                            `);
                        }
                    });
                    $container.find('#tcc-ft-date-card').fadeIn(200);
                    $container.find('#tcc-ft-date').val(''); 
                } else {
                    $dateGrid.html('<span style="font-size:12px; color:#ef4444;">No dates available.</span>');
                    $container.find('#tcc-ft-date').val('');
                }
                resetQuantities(); calculateTotals();
            }
        });

        // --- 3. AUTO-SELECT FIRST DATE ON PAGE LOAD (Single Tour Only) ---
        if (!isMaster) {
            let $firstDateBtn = $container.find('#tcc-ft-date-card .fd-date-btn-modern:not(.sold-out)').first();
            if ($firstDateBtn.length) {
                let $firstInput = $firstDateBtn.find('input[type="radio"]');
                $firstInput.prop('checked', true);
                $firstDateBtn.addClass('active');
                $container.find('#tcc-ft-date').val($firstInput.val());
            }
        }

        // --- 4. DATE BUTTON SELECT LOGIC ---
        $container.on('change', 'input[name="tcc_ft_date"]', function() {
            $container.find('input[name="tcc_ft_date"]').closest('.fd-date-btn-modern').removeClass('active');
            $(this).closest('.fd-date-btn-modern').addClass('active');
            $container.find('#tcc-ft-date').val($(this).val());
            calculateTotals(); 
        });

        // --- 5. AUTO-ADVANCE UPON FIRST DATE CLICK ---
        $container.on('click', '#tcc-ft-date-card .fd-date-btn-modern:not(.sold-out)', function() {
            if ($stepTS.is(':visible')) {
                setTimeout(() => {
                    if (!$container.data('auto-advanced')) {
                        $container.data('auto-advanced', true);
                        $container.find('#btn-ts-next').click();
                    }
                }, 300);
            }
        });

        // --- 6. UPDATE SELECTION SUMMARY TEXT ---
        function updateSelectionSummary() {
            let tourName = $container.find('.tcc-ft-title').text();
            let selectedDateRaw = $container.find('#tcc-ft-date').val();
            let formattedDate = "";
            
            if (selectedDateRaw) {
                let dObj = new Date(selectedDateRaw);
                formattedDate = dObj.toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
                if (formattedDate === 'Invalid Date') formattedDate = selectedDateRaw;
            }
            
            let summaryText = "";
            if (isMaster) {
                let dest = $container.find('input[name="tcc_ft_destination"]:checked').val() || '';
                if (dest) { summaryText = `📍 ${dest} &nbsp;|&nbsp; 🗺️ ${tourName} &nbsp;|&nbsp; 🗓️ ${formattedDate}`; }
                else { summaryText = `🗺️ ${tourName} &nbsp;|&nbsp; 🗓️ ${formattedDate}`; }
            } else { summaryText = `🗺️ ${tourName} &nbsp;|&nbsp; 🗓️ ${formattedDate}`; }
            
            $container.find('.tcc-ft-selection-summary').html(summaryText).show();
        }

        // --- 7. NAVIGATION BUTTON CLICK EVENTS ---
        $container.on('click', '#btn-ts-next', function() {
            if(isMaster && !$container.attr('data-tour-id')) { alert("Please select a Destination and a Tour first."); return; }
            if(!$container.find('#tcc-ft-date').val()) { alert("Please select a departure date."); return; }
            
            updateSelectionSummary();
            $stepTS.hide(); $stepCalc.fadeIn(200);
        });

        $container.on('click', '#btn-calc-back', function() { $stepCalc.hide(); $stepTS.fadeIn(200); });

        $container.on('click', '#btn-calc-next', function() {
            let totalPax = 0; $('.tcc-ft-pax-val', $container).each(function(){ totalPax += parseInt($(this).val()); });
            if(totalPax === 0) { alert("Please add at least 1 traveler."); return; }
            $stepCalc.hide(); $stepLead.fadeIn(200);
        });

        $container.on('click', '#btn-lead-back', function() { 
            if (flow === 'calc_first') { $stepLead.hide(); $stepCalc.fadeIn(200); }
            else { $stepTS.hide(); $stepLead.fadeIn(200); } 
        });
        
        $container.on('click', '#btn-ts-back', function() { 
            if (flow === 'lead_first') {
                $container.find('.tcc-ft-title').text('✨ Unlock Live Pricing & Itinerary');
            }
            $stepTS.hide(); $stepLead.fadeIn(200); 
        });

        $container.on('click', '#btn-lead-next', function() {
            let name = $.trim($container.find('#tcc-ft-name').val());
            let phone = $.trim($container.find('#tcc-ft-phone').val());
            let email = $.trim($container.find('#tcc-ft-email').val());
            
            if(!isLoggedIn && (!name || !phone)) { alert("Please fill your Name and WhatsApp Number."); return; }
            
            let $btn = $(this); let originalText = $btn.text();
            $btn.text('Saving...').prop('disabled', true);

            $.post(tcc_front_ajax.url, { action: 'tcc_front_submit_lead_only', name: name, phone: phone, email: email }, function(res) {
                $btn.text(originalText).prop('disabled', false);
                
                if (flow === 'lead_first') {
                    if (isMaster && $container.attr('data-tour-id')) {
                        let freshToursRaw = $container.attr('data-all-tours');
                        let freshTours = freshToursRaw ? JSON.parse(freshToursRaw) : null;
                        let tId = $container.attr('data-tour-id');
                        if (freshTours && freshTours[tId]) { $container.find('.tcc-ft-title').text(freshTours[tId].title); } 
                        else { $container.find('.tcc-ft-title').text(defaultTitle); }
                    } else {
                        $container.find('.tcc-ft-title').text(defaultTitle);
                    }
                }
                
                $stepLead.hide(); $stepTS.fadeIn(200); 
            }).fail(function() {
                $btn.text(originalText).prop('disabled', false);
                if (flow === 'lead_first') {
                    if (isMaster && $container.attr('data-tour-id')) { } 
                    else { $container.find('.tcc-ft-title').text(defaultTitle); }
                }
                $stepLead.hide(); $stepTS.fadeIn(200); 
            });
        });

        // --- 8. QTY INCREMENT / DECREMENT ---
        $container.find('.tcc-ft-btn-qty').off('click').on('click', function() {
            let type = $(this).data('target');
            let $input = $container.find('#tcc-ft-qty-' + type);
            let val = parseInt($input.val()) || 0;
            let isPlus = $(this).hasClass('plus');

            let d = parseInt($container.find('#tcc-ft-qty-double').val()) || 0;
            let t = parseInt($container.find('#tcc-ft-qty-triple').val()) || 0;
            let s = parseInt($container.find('#tcc-ft-qty-single').val()) || 0;
            let c = parseInt($container.find('#tcc-ft-qty-child').val()) || 0;
            let i = parseInt($container.find('#tcc-ft-qty-infant').val()) || 0;

            let mcDbl = parseInt($container.attr('data-mc-dbl')) || 0;
            let miDbl = parseInt($container.attr('data-mi-dbl')) || 0;
            let mcTpl = parseInt($container.attr('data-mc-tpl')) || 0;
            let miTpl = parseInt($container.attr('data-mi-tpl')) || 0;
            let mcSgl = parseInt($container.attr('data-mc-sgl')) || 0;
            let miSgl = parseInt($container.attr('data-mi-sgl')) || 0;

            let maxChild = Math.floor((d/2)*mcDbl + (t/3)*mcTpl + s*mcSgl);
            let maxInfant = Math.floor((d/2)*miDbl + (t/3)*miTpl + s*miSgl);

            if (isPlus) {
                if (type === 'child' || type === 'infant') {
                    if ((d+t+s) === 0) { alert("Children and Infants must accompany at least one Adult."); return; }
                    if (type === 'child' && c >= maxChild) { alert("Room capacity reached!\nYou are allowed a maximum of " + maxChild + " Child(ren) based on your selected rooms."); return; }
                    if (type === 'infant' && i >= maxInfant) { alert("Room capacity reached!\nYou are allowed a maximum of " + maxInfant + " Infant(s) based on your selected rooms."); return; }
                }
                if (type === 'double') val += 2; else if (type === 'triple') val += 3; else val += 1;
                $input.val(val);
            } else {
                let newVal = val;
                if (type === 'double') newVal = Math.max(0, val - 2); else if (type === 'triple') newVal = Math.max(0, val - 3); else newVal = Math.max(0, val - 1);
                $input.val(newVal);

                if (type === 'double' || type === 'triple' || type === 'single') {
                    let new_d = parseInt($container.find('#tcc-ft-qty-double').val()) || 0;
                    let new_t = parseInt($container.find('#tcc-ft-qty-triple').val()) || 0;
                    let new_s = parseInt($container.find('#tcc-ft-qty-single').val()) || 0;
                    
                    let newMaxChild = Math.floor((new_d/2)*mcDbl + (new_t/3)*mcTpl + new_s*mcSgl);
                    let newMaxInfant = Math.floor((new_d/2)*miDbl + (new_t/3)*miTpl + new_s*miSgl);

                    let changed = false;
                    if (c > newMaxChild) { $container.find('#tcc-ft-qty-child').val(newMaxChild); changed = true; }
                    if (i > newMaxInfant) { $container.find('#tcc-ft-qty-infant').val(newMaxInfant); changed = true; }
                    if (changed) { alert("Notice: Child/Infant count was automatically reduced to match the updated room capacities."); }
                }
            }
            calculateTotals();
        });

        function resetQuantities() { ['double', 'triple', 'single', 'child', 'infant'].forEach(key => { $container.find('#tcc-ft-qty-' + key).val(0); }); }

        // --- 9. STRICT BACK CALCULATION ---
        function calculateTotals() {
            let grandTotal = 0;
            let selectedDate = $container.find('#tcc-ft-date').val();
            let currentPrices = { double: parseFloat(prices.double)||0, triple: parseFloat(prices.triple)||0, single: parseFloat(prices.single)||0, child: parseFloat(prices.child)||0, infant: parseFloat(prices.infant)||0 };

            if (selectedDate && Array.isArray(prices.dates)) {
                let matchedDate = prices.dates.find(d => d.start_date === selectedDate);
                if (matchedDate) {
                    ['double', 'triple', 'single', 'child', 'infant'].forEach(key => {
                        let datePriceRaw = matchedDate['price_' + key];
                        if (datePriceRaw !== undefined && datePriceRaw !== "") { currentPrices[key] = parseFloat(datePriceRaw) || 0; }
                    });
                }
            }
            
            ['double', 'triple', 'single', 'child', 'infant'].forEach(key => {
                let qty = parseInt($container.find('#tcc-ft-qty-' + key).val()) || 0;
                let price = currentPrices[key];
                grandTotal += (qty * price); 
                let formattedPrice = price.toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 2});
                $container.find('#tcc-ft-qty-' + key).closest('.tcc-ft-pax-item').find('.tcc-ft-pax-price').text('₹' + formattedPrice + ' /pax');
            });

            let rawGst = $container.attr('data-gst'); let parsedGst = parseFloat(rawGst); let finalGstPct = (isNaN(parsedGst) || parsedGst <= 0) ? 5 : parsedGst;
            let gstAmount = (grandTotal * finalGstPct) / (100 + finalGstPct);
            let basePrice = grandTotal - gstAmount;

            function fmt(num) { return num.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
            $container.find('#tcc-ft-base').text('₹' + fmt(basePrice));
            $container.find('#tcc-ft-gst').text('₹' + fmt(gstAmount));
            $container.find('#tcc-ft-grand').text('₹' + fmt(grandTotal));
        }
        calculateTotals();

        // --- 10. FINAL SUBMISSION ---
        $container.on('click', '#btn-lead-submit, #btn-calc-submit', function(e) {
            e.preventDefault(); 
            
            let name = $.trim($container.find('#tcc-ft-name').val());
            let phone = $.trim($container.find('#tcc-ft-phone').val());
            
            if(!isLoggedIn && (!name || !phone)) { alert("Please fill your Name and WhatsApp Number."); return; }
            
            let totalPax = 0; $('.tcc-ft-pax-val', $container).each(function(){ totalPax += parseInt($(this).val()); });
            if(totalPax === 0) { alert("Please add at least 1 traveler."); return; }

            let email = $container.find('#tcc-ft-email').val();
            let date = $container.find('#tcc-ft-date').val();
            let grandTotal = $container.find('#tcc-ft-grand').text().replace('₹', '').replace(/,/g, '');

            let pax = {
                double: $container.find('#tcc-ft-qty-double').val(), triple: $container.find('#tcc-ft-qty-triple').val(),
                single: $container.find('#tcc-ft-qty-single').val(), child: $container.find('#tcc-ft-qty-child').val(), infant: $container.find('#tcc-ft-qty-infant').val()
            };

            let $btn = $(this); let originalText = $btn.text();
            $btn.text('Processing...').css('pointer-events', 'none').css('opacity', '0.7');
            $container.find('#tcc-ft-error').hide();

            $.ajax({
                url: tcc_front_ajax.url,
                type: 'POST', dataType: 'json',
                data: {
                    action: 'tcc_front_submit', security: tcc_front_ajax.nonce, tour_id: $container.attr('data-tour-id'), 
                    name: name, phone: phone, email: email, date: date, pax: pax, grand_total: grandTotal
                },
                success: function(res) {
                    if(res.success && res.data && res.data.redirect_url) {
                        $container.find('.tcc-ft-step').hide();
                        let $successStep = $container.find('#tcc-ft-step-success');

                        // Stash the quote ID for the PDF download/share buttons
                        let qId = res.data.quote_id || 0;
                        $successStep.attr('data-quote-id', qId);

                        // Reset PDF buttons to fresh state on each submission
                        $successStep.find('#tcc-ft-pdf-download').prop('disabled', false).text('📥 Download PDF').css('opacity', '1');
                        $successStep.find('#tcc-ft-pdf-share').prop('disabled', true).text('⏳ Preparing share...').css('opacity', '0.7');

                        // Clear any previously cached share file from an earlier booking
                        $container.removeData('pdfSharedFile');

                        // Only show Download/Share if we actually have a saved quote
                        if (qId) {
                            $successStep.find('#tcc-ft-pdf-download').show();

                            // Detect whether the browser can share PDF files at all
                            let canFileShare = false;
                            try {
                                canFileShare = !!(navigator.canShare && navigator.canShare({
                                    files: [new File([new Blob([''], { type: 'application/pdf' })], 'test.pdf', { type: 'application/pdf' })]
                                }));
                            } catch (e) { canFileShare = false; }

                            if (canFileShare) {
                                let $shareBtn = $successStep.find('#tcc-ft-pdf-share').show();

                                // PRE-FETCH the PDF in the background so navigator.share() can later be
                                // called SYNCHRONOUSLY inside the click handler. iOS Safari and several
                                // Android browsers revoke "transient user activation" across awaits, so
                                // doing the fetch on click (after awaiting) fails with:
                                //   "Must be handling a user gesture to perform a share request."
                                let prefetchData = new FormData();
                                prefetchData.append('action', 'tcc_front_generate_pdf');
                                prefetchData.append('quote_id', qId);

                                fetch(tcc_front_ajax.url, { method: 'POST', body: prefetchData })
                                    .then(function(r) {
                                        if (!r.ok) throw new Error('HTTP ' + r.status);
                                        return r.blob();
                                    })
                                    .then(function(blob) {
                                        if (!blob || blob.size < 200) throw new Error('Empty PDF');
                                        let docTitle = ($container.find('.tcc-ft-title').text() || 'Quotation')
                                            .trim().replace(/[^a-zA-Z0-9\-_ ]/g, '').substring(0, 60) || 'Quotation';
                                        let file = new File([blob], docTitle + '.pdf', { type: 'application/pdf' });

                                        // Final feasibility check now that we have a real PDF file
                                        if (!navigator.canShare({ files: [file] })) {
                                            throw new Error('Files not shareable in this browser');
                                        }

                                        $container.data('pdfSharedFile', file);
                                        $shareBtn.prop('disabled', false).text('📤 Share PDF').css('opacity', '1');
                                    })
                                    .catch(function(err) {
                                        console.warn('PDF prefetch for share failed:', err);
                                        $shareBtn.hide();
                                    });
                            } else {
                                $successStep.find('#tcc-ft-pdf-share').hide();
                            }
                        } else {
                            $successStep.find('#tcc-ft-pdf-download').hide();
                            $successStep.find('#tcc-ft-pdf-share').hide();
                        }

                        if (!isLoggedIn) {
                            $successStep.find('#tcc-ft-wa-link').attr('href', res.data.redirect_url);
                        }
                        // View My Itinerary button removed — quote_url still available in res.data.quote_url if needed elsewhere

                        $successStep.fadeIn(400); 
                        
                        if (!isLoggedIn) {
                            // Auto-open WhatsApp only if the admin setting is enabled (default: on)
                            if (tcc_front_ajax.auto_wa_redirect !== '0') {
                                window.open(res.data.redirect_url, '_blank');
                            }
                        }
                    } else {
                        $container.find('#tcc-ft-error').text("Error processing request. Please try again.").show();
                        $btn.text(originalText).css('pointer-events', 'auto').css('opacity', '1');
                    }
                },
                error: function(xhr, status, error) {
                    try { let match = xhr.responseText.match(/"redirect_url":"([^"]+)"/); if (match && match[1]) { window.location.href = match[1].replace(/\\\//g, '/'); return; } } catch(e) {}
                    $container.find('#tcc-ft-error').text("Server error. Please refresh the page and try again.").show();
                    $btn.text(originalText).css('pointer-events', 'auto').css('opacity', '1');
                }
            });
        });

        // --- 10b. DOWNLOAD PDF (native form POST, triggers a real file download) ---
        $container.on('click', '#tcc-ft-pdf-download', function(e) {
            e.preventDefault();
            let $btn = $(this);
            let $successStep = $container.find('#tcc-ft-step-success');
            let quoteId = parseInt($successStep.attr('data-quote-id')) || 0;
            if (!quoteId) { alert('Quote reference missing. Please refresh and try again.'); return; }

            let originalText = $btn.text();
            $btn.prop('disabled', true).text('⏳ Generating PDF...');

            // Build a hidden form and POST to admin-ajax so the browser handles the file download natively
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = tcc_front_ajax.url;
            form.target = '_self';
            form.style.display = 'none';

            let actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'tcc_front_generate_pdf';
            form.appendChild(actionField);

            let idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'quote_id';
            idField.value = quoteId;
            form.appendChild(idField);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Re-enable the button shortly after — the browser handles the download out-of-band
            setTimeout(function() {
                $btn.prop('disabled', false).text(originalText);
            }, 3500);
        });

        // --- 10c. SHARE PDF (Web Share API, mobile-friendly) ---
        // IMPORTANT: must be synchronous up to the navigator.share() call. Mobile
        // browsers (especially iOS Safari) revoke transient user activation across
        // awaits, so the PDF is pre-fetched in the success callback and cached.
        $container.on('click', '#tcc-ft-pdf-share', function(e) {
            e.preventDefault();
            let $btn = $(this);
            let $successStep = $container.find('#tcc-ft-step-success');
            let quoteId = parseInt($successStep.attr('data-quote-id')) || 0;
            if (!quoteId) { alert('Quote reference missing. Please refresh and try again.'); return; }

            let file = $container.data('pdfSharedFile');
            if (!file) {
                alert('PDF is still being prepared. Please wait a moment and try again.');
                return;
            }

            if (!navigator.canShare || !navigator.canShare({ files: [file] })) {
                alert("Your browser doesn't support sharing PDF files. Please use the Download PDF button instead.");
                return;
            }

            let docTitle = ($container.find('.tcc-ft-title').text() || 'Quotation').trim();

            // SYNCHRONOUS share call — no awaits before this, so the user gesture is intact
            navigator.share({
                files: [file],
                title: docTitle,
                text: 'Here is my tour quotation.'
            }).then(function() {
                // Successfully shared — no UI change needed
            }).catch(function(err) {
                // AbortError = user dismissed the share sheet, that's not an error
                if (err && err.name !== 'AbortError') {
                    console.error('Share PDF error:', err);
                    alert('Could not share PDF: ' + (err.message || err));
                }
            });
        });

        // --- 11. RESUBMIT / NEW BOOKING (LOGGED IN ONLY) ---
        $container.on('click', '.tcc-btn-resubmit', function(e) {
            e.preventDefault();
            
            // Wipe standard inputs (Name, Email, Phone)
            $container.find('input[type="text"], input[type="email"], input[type="tel"]').val('');
            
            // Reset Quantities
            resetQuantities(); 
            
            // Reset form configurations
            let targetDest = $container.attr('data-target-dest');
            if (isMaster) {
                if (targetDest && targetDest !== '') {
                    // It's a locked destination form
                    $container.find('#tcc-ft-date-card').hide();
                    $container.find('.tcc-ft-tour-btn').removeClass('active').find('input').prop('checked', false);
                } else {
                    // Standard master form
                    $container.find('input[name="tcc_ft_destination"]').prop('checked', false).closest('.tcc-ft-dest-btn').removeClass('active');
                    $container.find('#tcc-ft-tour-card, #tcc-ft-date-card').hide();
                    $container.find('.tcc-ft-tour-btn').hide().removeClass('active').find('input').prop('checked', false);
                }
                $container.attr('data-tour-id', '');
                prices = {};
            } else {
                // For single tour, try to re-select the first available date
                let $firstDateBtn = $container.find('#tcc-ft-date-card .fd-date-btn-modern:not(.sold-out)').first();
                $container.find('.fd-date-btn-modern').removeClass('active');
                if ($firstDateBtn.length) {
                    let $firstInput = $firstDateBtn.find('input[type="radio"]');
                    $firstInput.prop('checked', true);
                    $firstDateBtn.addClass('active');
                    $container.find('#tcc-ft-date').val($firstInput.val());
                } else {
                    $container.find('#tcc-ft-date').val('');
                }
            }

            calculateTotals();
            
            // Restore Submit Buttons
            $container.find('#btn-lead-submit, #btn-calc-submit')
                .text(isLoggedIn ? 'Generate Booking' : 'Submit Query & WhatsApp')
                .css('pointer-events', 'auto')
                .css('opacity', '1');
            
            $container.find('.tcc-ft-selection-summary').hide();
            $container.find('#tcc-ft-error').hide();

            // Navigate back to Step 1
            $container.find('.tcc-ft-step').hide();
            if (flow === 'lead_first') {
                $container.find('.tcc-ft-title').text('✨ Unlock Live Pricing & Itinerary');
                $stepLead.fadeIn(200);
            } else {
                $container.find('.tcc-ft-title').text(defaultTitle);
                $stepTS.fadeIn(200);
            }
        });

    });
});