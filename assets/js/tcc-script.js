jQuery(document).ready(function($) {
    
    $('.tcc-accordion-header').on('click', function() {
        let $body = $(this).next('.tcc-accordion-body');
        $('.tcc-accordion-body').not($body).slideUp(150);
        $body.slideToggle(150);
    });

    function formatINR(amount) {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(amount);
    }

    function populateDropdown(selectElement, optionsArray) {
        let $el = $(selectElement);
        let currentVal = $el.val();
        $el.empty();
        
        if (typeof optionsArray === 'string') {
            optionsArray = optionsArray.split(',').map(item => item.trim()).filter(item => item);
        }

        if(optionsArray && Array.isArray(optionsArray) && optionsArray.length > 0) {
            $.each(optionsArray, function(i, val) { $el.append($('<option></option>').val(val).text(val)); });
            
            if(currentVal && $el.find("option[value='" + currentVal + "']").length) {
                $el.val(currentVal);
            } else {
                $el.prop('selectedIndex', 0); 
            }
        } else {
            $el.append('<option value="">N/A</option>');
        }
    }

    function rebuildDestinationDropdowns() {
        if(typeof tccMasterData === 'undefined') return;
        let dests = Object.keys(tccMasterData);
        
        populateDropdown('#calc_destination', dests);
        populateDropdown('#set_hotel_dest', dests);
        populateDropdown('#set_trans_dest', dests);

        let m_dest = $('#master_dest_select').val();
        let $md = $('#master_dest_select');
        $md.empty().append('<option value="">-- Add New --</option>');
        $.each(dests, function(i, val) { $md.append($('<option></option>').val(val).text(val)); });
        if(m_dest && dests.includes(m_dest)) $md.val(m_dest);
    }

    function updateCalculatorDropdowns() {
        let dest = $('#calc_destination').val();
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            populateDropdown('#calc_pickup', tccMasterData[dest].pickups);
            populateDropdown('#calc_drop', tccMasterData[dest].pickups); 
            populateDropdown('#calc_hotel_cat', tccMasterData[dest].hotel_categories);
            
            $('.stay_place_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].stay_places); });
            $('.stay_cat_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].hotel_categories); });
            $('.transport_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].vehicles); });
            
            setTimeout(function() { $('.night-stay-row').each(function() { updateRowHotels($(this)); }); }, 100);
        }
    }

    $('#calc_destination').on('change', updateCalculatorDropdowns);

    function updateRowHotels($row) {
        let dest = $('#calc_destination').val();
        let cat = $row.find('.stay_cat_dropdown').val(); 
        let place = $row.find('.stay_place_dropdown').val();
        let $hotelDrop = $row.find('.stay_hotel_dropdown');
        let $hiddenInput = $row.find('.stay_hotel_hidden');
        let preValue = $hiddenInput.val(); 

        if(dest && cat && place) {
            $hotelDrop.html('<option value="">Loading...</option>');
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_names', dest: dest, place: place, cat: cat }, function(res) {
                $hotelDrop.empty();
                if(res.success && res.data.length > 0) {
                    $.each(res.data, function(i, name) { $hotelDrop.append(`<option value="${name}">${name}</option>`); });
                    
                    if(preValue) {
                        $hotelDrop.val(preValue.split(','));
                    } else {
                        $hotelDrop.val([res.data[0]]); 
                    }
                } else {
                    $hotelDrop.append('<option value="">No Hotels Saved</option>');
                }
                let vals = $hotelDrop.val();
                $hiddenInput.val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
                triggerLiveCalculation();
            }).fail(function() {
                $hotelDrop.empty().append('<option value="">Error Loading</option>');
            });
        } else {
            $hotelDrop.html('<option value="">-- Select --</option>');
            $hiddenInput.val('');
        }
    }

    $(document).on('change', '.stay_hotel_dropdown', function() {
        let vals = $(this).val();
        $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        triggerLiveCalculation();
    });

    $(document).on('change', '.stay_place_dropdown, .stay_cat_dropdown', function() { updateRowHotels($(this).closest('.night-stay-row')); });
    
    $('#calc_hotel_cat').on('change', function() { 
        let newVal = $(this).val();
        $('.stay_cat_dropdown').val(newVal); 
        $('.night-stay-row').each(function() { updateRowHotels($(this)); }); 
    });

    $('#total_pax, #child_pax').on('input', function() {
        let pax = parseInt($('#total_pax').val()) || 0;
        let child = parseInt($('#child_pax').val()) || 0;

        let rooms = Math.max(Math.ceil(pax / 3), child);
        if(rooms < 1) rooms = 1;
        let baseAdultCap = rooms * 2;
        let extraBeds = pax > baseAdultCap ? pax - baseAdultCap : 0;
        
        $('#no_of_rooms').val(rooms);
        $('#extra_beds').val(extraBeds);

        triggerAutoTransportOptimization();
    });

    $('#calc_pickup').on('change', function() { 
        $('#calc_drop').val($(this).val()); 
        triggerAutoTransportOptimization(); 
    });

    let transportTimeout;
    function triggerAutoTransportOptimization() {
        clearTimeout(transportTimeout);
        transportTimeout = setTimeout(function() {
            let dest = $('#calc_destination').val();
            let pickup = $('#calc_pickup').val();
            let pax = parseInt($('#total_pax').val()) || 0;
            if(!dest || !pickup || pax <= 0) return;

            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_optimize_transport', destination: dest, pickup_location: pickup, total_pax: pax
            }, function(res) {
                if(res.success && res.data.length > 0) {
                    $('#transport-wrapper .transport-row').remove(); 
                    $.each(res.data, function(i, item) { addTransportRow(item.vehicle, item.qty); });
                    triggerLiveCalculation();
                } else {
                    triggerLiveCalculation();
                }
            });
        }, 400); 
    }

    function addTransportRow(vehicleName = '', qty = 1) {
        let dest = $('#calc_destination').val();
        let optionsHtml = '';
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let vehs = tccMasterData[dest].vehicles;
            if(typeof vehs === 'string') vehs = vehs.split(',').map(s=>s.trim());
            if(Array.isArray(vehs)) {
                $.each(vehs, function(i, val) {
                    let sel = (val === vehicleName) ? 'selected' : '';
                    optionsHtml += `<option value="${val}" ${sel}>${val}</option>`;
                });
            }
        }
        let row = `
        <div class="transport-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap;">
            <select name="transportation[]" class="transport_dropdown" required style="flex:3;">${optionsHtml}</select>
            <input type="number" name="transport_qty[]" value="${qty}" min="1" placeholder="Qty" required style="flex:1;">
            <button type="button" class="remove_transport tcc-btn-del">X</button>
            <div class="tcc-trans-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
        </div>`;
        $('#transport-wrapper').append(row);
    }

    $('#add_transport').on('click', function() { addTransportRow(); triggerLiveCalculation(); });
    $(document).on('click', '.remove_transport', function() { $(this).closest('.transport-row').remove(); triggerLiveCalculation(); });

    $('#total_days').on('input', function() {
        let days = parseInt($(this).val()) || 0;
        $('#total_nights_display').text(days > 0 ? days - 1 : 0);
        triggerLiveCalculation();
    });

    function addStayRow(placeName = '', catVal = '', hotelVal = '', nights = 1) {
        let dest = $('#calc_destination').val();
        
        let placeOptionsHtml = '';
        let catOptionsHtml = '';
        
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let stays = tccMasterData[dest].stay_places;
            if(typeof stays === 'string') stays = stays.split(',').map(s=>s.trim());
            if(Array.isArray(stays)) {
                $.each(stays, function(i, val) { 
                    let sel = (val === placeName) ? 'selected' : '';
                    placeOptionsHtml += `<option value="${val}" ${sel}>${val}</option>`; 
                });
            }

            let cats = tccMasterData[dest].hotel_categories;
            if(typeof cats === 'string') cats = cats.split(',').map(s=>s.trim());
            let defaultCat = $('#calc_hotel_cat').val();
            let selectedCat = catVal || defaultCat;
            if(Array.isArray(cats)) {
                $.each(cats, function(i, val) { 
                    let sel = (val === selectedCat) ? 'selected' : '';
                    catOptionsHtml += `<option value="${val}" ${sel}>${val}</option>`; 
                });
            }
        }
        
        let row = `
        <div class="night-stay-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap; gap:5px;">
            <select name="stay_place[]" class="stay_place_dropdown" required style="flex:2;">${placeOptionsHtml}</select>
            <select name="stay_category[]" class="stay_cat_dropdown" required style="flex:1.5;">${catOptionsHtml}</select>
            <div style="flex:3;">
                <select class="stay_hotel_dropdown" multiple required style="width:100%; height:55px !important; border:1px solid #ccc; border-radius:3px; padding:2px; font-size:12px; background:#fff; outline:none;"></select>
                <input type="hidden" name="stay_hotel[]" class="stay_hotel_hidden" value="${hotelVal}">
            </div>
            <input type="number" name="stay_nights[]" placeholder="Nights" value="${nights}" class="stay_nights" min="1" required style="flex:0.8;">
            <button type="button" class="remove_stay_place tcc-btn-del">X</button>
            <div class="tcc-hotel-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
        </div>`;
        let $newRow = $(row);
        $('#night-stay-wrapper').append($newRow);
        updateRowHotels($newRow);
    }

    $('#add_stay_place').on('click', function() { addStayRow(); });
    $(document).on('click', '.remove_stay_place', function() { $(this).closest('.night-stay-row').remove(); triggerLiveCalculation(); });

    function validateCalculator() {
        let pax = parseInt($('#total_pax').val()) || 0;
        let child = parseInt($('#child_pax').val()) || 0;
        let rooms = parseInt($('#no_of_rooms').val()) || 0;
        let extra_beds = parseInt($('#extra_beds').val()) || 0;
        let total_days = parseInt($('#total_days').val()) || 0;
        let expected_nights = total_days > 0 ? total_days - 1 : 0;

        if (!$('#start_date').val()) return "Select Start Date.";
        if (extra_beds > rooms) return "Max 1 Extra Bed/room.";
        if (child > rooms) return "Max 1 Child (<6yr)/room.";
        if ((rooms * 2) + extra_beds < pax) return `Not enough beds for ${pax} Adults.`;

        let sum_nights = 0;
        $('.stay_nights').each(function() { sum_nights += parseInt($(this).val()) || 0; });
        if (sum_nights !== expected_nights) return `Nights (${sum_nights}) must equal ${expected_nights}.`;

        return null; 
    }

    let liveCalcTimeout;
    $('#tcc-calc-form').on('input change', 'input, select', function(e) {
        if(e.target.id === 'tcc_calculate_btn' || e.target.id === 'client_name' || e.target.id === 'client_phone' || e.target.id === 'client_email' || e.target.id === 'calc_edit_quote_select') return;
        if(e.target.id !== 'total_pax' && e.target.id !== 'child_pax' && e.target.id !== 'calc_pickup') {
            triggerLiveCalculation();
        }
    });

    function triggerLiveCalculation() {
        $('#live_error').hide();
        $('#tcc_link_wrapper').slideUp(); 
        
        let validationError = validateCalculator();
        if (validationError) {
            $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst, #live_actual_cost, #live_total_hotel, #live_total_trans, #live_total_base').text('₹0.00').css('opacity', '1');
            $('.tcc-trans-cost, .tcc-hotel-cost').text('');
            $('#live_error').html(validationError).show();
            return;
        }

        $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst').css('opacity', '0.5'); 
        clearTimeout(liveCalcTimeout);
        liveCalcTimeout = setTimeout(runLiveCalculation, 400);
    }

    function runLiveCalculation() {
        $('.stay_hotel_dropdown').each(function() {
            let vals = $(this).val();
            $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        });

        let formData = $('#tcc-calc-form').serialize();
        $.ajax({
            url: tcc_ajax_obj.ajax_url, type: 'POST',
            data: formData + '&action=tcc_calculate_trip',
            success: function(res) {
                $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst').css('opacity', '1');
                if(res.success) {
                    let d = res.data;
                    
                    // FIXED MAPPING
                    $('#live_pp_base').text(formatINR(d.summary_data.per_person_excl_gst));
                    $('#live_pp_inc').text(formatINR(d.summary_data.per_person_with_gst));
                    $('#live_total_base').text(formatINR(d.summary_data.total_base_price));
                    
                    $('#live_pt_label').text(`PT (${d.summary_data.pt_pct}%):`);
                    $('#live_pg_label').text(`PG (${d.summary_data.pg_pct}%):`);
                    $('#live_gst_label').text(`GST (${d.summary_data.gst_pct}%):`);

                    $('#live_total_hotel').text(formatINR(d.summary_data.total_hotel_cost));
                    $('#live_total_trans').text(formatINR(d.summary_data.total_trans_cost));
                    $('#live_actual_cost').text(formatINR(d.summary_data.actual_cost));

                    $('#live_profit').text(formatINR(d.summary_data.net_profit));
                    $('#live_gross_profit').text(formatINR(d.summary_data.final_profit));
                    $('#live_pt').text("-" + formatINR(d.summary_data.prof_tax));
                    $('#live_pg').text("-" + formatINR(d.summary_data.pg_charge));

                    $('#live_discount').text("-" + formatINR(d.summary_data.discount_amount));
                    $('#live_gst').text(formatINR(d.gst));
                    $('#live_total').text(formatINR(d.grand_total));
                    
                    $('#live_profit').removeClass('tcc-preview-loss tcc-preview-profit').addClass(d.summary_data.net_profit < 0 ? 'tcc-preview-loss' : 'tcc-preview-profit');
                    
                    if(d.summary_data.surcharge_applied > 0) {
                        $('#live_surcharge_info').text(`*Season Surcharge applied: +${d.summary_data.surcharge_applied}%`).show();
                    } else {
                        $('#live_surcharge_info').hide();
                    }

                    $('.transport-row').each(function(index) {
                        if(d.summary_data.transport_row_costs[index] !== undefined) {
                            $(this).find('.tcc-trans-cost').text('Cost: ' + formatINR(d.summary_data.transport_row_costs[index]));
                        }
                    });

                    $('.night-stay-row').each(function(index) {
                        if(d.summary_data.hotel_row_costs[index] !== undefined) {
                            $(this).find('.tcc-hotel-cost').text('Cost: ' + formatINR(d.summary_data.hotel_row_costs[index]));
                        }
                    });

                    if (d.summary_data.corrected_trans_qtys) {
                        $('.transport-row').each(function(index) {
                            let $qtyInput = $(this).find('input[name="transport_qty[]"]');
                            if (d.summary_data.corrected_trans_qtys[index] !== undefined && !$qtyInput.is(':focus')) {
                                $qtyInput.val(d.summary_data.corrected_trans_qtys[index]);
                            }
                        });
                    }

                    $('#live_error').hide();
                } else {
                    $('#live_pp_base, #live_pp_inc, #live_profit, #live_gross_profit, #live_pt, #live_pg, #live_discount, #live_total, #live_gst, #live_total_base').text('₹0.00');
                    $('#live_total_hotel, #live_total_trans, #live_actual_cost').text('₹0.00');
                    $('.tcc-trans-cost, .tcc-hotel-cost').text('');
                    $('#live_error').html(res.data.errors).show();
                }
            }
        });
    }

    window.tccLastQuoteData = null;

    $('#tcc-calc-form').on('submit', function(e) {
        e.preventDefault();
        $('#live_error').hide();
        $('#tcc_link_wrapper').hide();
        
        let validationError = validateCalculator();
        if(validationError) { $('#live_error').html(validationError).show(); return; }

        $('.stay_hotel_dropdown').each(function() {
            let vals = $(this).val();
            $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        });

        let btn = $('#tcc_calculate_btn');
        btn.text('Processing...').prop('disabled', true);

        $.ajax({
            url: tcc_ajax_obj.ajax_url, type: 'POST',
            data: $(this).serialize() + '&action=tcc_calculate_trip&generate_link=1',
            success: function(response) {
                btn.text($('#edit_quote_id').val() ? 'Update Quote Link' : 'Generate Quote Link').prop('disabled', false);
                if(response.success) {
                    window.tccLastQuoteData = response.data;
                    $('#tcc_generated_link').val(response.data.permalink);
                    $('#tcc_open_btn').attr('href', response.data.permalink);
                    $('#tcc_link_wrapper').slideDown();
                    loadPaymentQuotes(); 
                } else {
                    $('#live_error').html(response.data.errors).show();
                }
            }
        });
    });

    $('#tcc_copy_btn').on('click', function() {
        let copyText = document.getElementById("tcc_generated_link");
        copyText.select();
        copyText.setSelectionRange(0, 99999); 
        document.execCommand("copy");
        let $btn = $(this);
        let originalText = $btn.text();
        $btn.text("Copied!");
        setTimeout(function(){ $btn.text(originalText); }, 2000);
    });

    $('#tcc_copy_wa_btn').on('click', function() {
        if(!window.tccLastQuoteData) return;
        
        let d = window.tccLastQuoteData.summary_data;
        let pp_gst = formatINR(d.per_person_with_gst || (window.tccLastQuoteData.grand_total / Math.max(1, d.pax)));
        
        // FIXED MAPPING FOR WA TEXT
        let pp_base = formatINR(d.per_person_excl_gst);
        let gst = formatINR(window.tccLastQuoteData.gst);
        let discount = d.discount_amount > 0 ? formatINR(d.discount_amount) : 0;
        let total = formatINR(window.tccLastQuoteData.grand_total);
        let link = window.tccLastQuoteData.permalink;
        let gst_pct = d.gst_pct !== undefined ? d.gst_pct : 5;
        let drop_txt = d.drop !== undefined ? d.drop : d.pickup;
        let pax_count = Math.max(1, d.pax);
        let total_base = formatINR(d.total_base_price);
        
        function formatDate(dateStr) {
            if(!dateStr) return '';
            let options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(dateStr).toLocaleDateString('en-GB', options);
        }
        
        function formatBullets(text) {
            if(!text) return "None specified.";
            return text.split(/\r\n|\n|\r/).filter(line => line.trim() !== '').map(line => `✓ ${line.trim()}`).join('\n');
        }

        let msg = `*SOULFUL TOUR & TRAVELS*\n`;
        msg += `GSTIN: 19AXIPD7432L1Z5\n\n`;
        if(d.client_name) msg += `*Client Name:* ${d.client_name}\n`;
        msg += `*Your Quotation for ${d.destination}*\n`;
        msg += `*Trip Date:* ${formatDate(d.start_date)} to ${formatDate(d.end_date)}\n`;
        msg += `*Duration:* ${d.days} Days / ${d.days > 0 ? d.days - 1 : 0} Nights\n`;
        msg += `*Travelers:* ${d.pax} Adults, ${d.child} Child\n`;
        msg += `*Rooms & Beds:* ${d.rooms} Rooms / ${d.extra_beds} Extra Beds\n`;
        msg += `*Transport:* ${d.transport_string} (Pickup: ${d.pickup} | Drop: ${drop_txt})\n\n`;
        
        if(d.itinerary && d.itinerary.length > 0 && d.itinerary.some(day => day.trim() !== '')) {
            msg += `*Itinerary Details:*\n`;
            d.itinerary.forEach(function(day_text, index) {
                if(day_text.trim() !== '') {
                    msg += `*Day ${index + 1}:* ${day_text}\n`;
                }
            });
            msg += `\n`;
        }

        msg += `*Hotels Selected:*\n`;
        if(d.stays && d.stays.length > 0) {
            d.stays.forEach(function(stay) {
                let hotelNames = stay.options.map(o => o.name).join(" OR ");
                let catText = stay.category ? `[${stay.category}] ` : '';
                msg += `- ${stay.place} (${stay.nights}N): ${catText}${hotelNames} / Similar\n`;
            });
        } else {
            msg += `No hotels selected.\n`;
        }
        
        msg += `\n*Included in Package:*\n`;
        msg += `${formatBullets(d.inclusions)}\n`;
        
        msg += `\n*Pricing Summary:*\n`;
        msg += `Per Person (Excl. GST): ${pp_base}\n`;
        msg += `Total Base (${pax_count} Pax): ${total_base}\n`;
        msg += `GST (${gst_pct}%): ${gst}\n`;
        if(d.discount_amount > 0) msg += `(Note: A discount of ${discount} was applied to your base price)\n`;
        msg += `\n*Total Quote:* ${total}\n`;
        msg += `*Per Person (Inc. GST):* ${pp_gst}\n\n`;

        msg += `*View Detailed Itinerary, Receipts & Quote here:*\n${link}`;

        let copyTextarea = document.createElement("textarea");
        document.body.appendChild(copyTextarea);
        copyTextarea.value = msg;
        copyTextarea.select();
        document.execCommand("copy");
        document.body.removeChild(copyTextarea);

        let $btn = $(this);
        let originalText = $btn.text();
        $btn.text("Copied!");
        setTimeout(function(){ $btn.text(originalText); }, 2000);
    });

    function renumberDays() {
        $('#day-wise-wrapper .tcc-day-label').each(function(index) {
            $(this).text('Day ' + (index + 1));
        });
    }

    function initSortable() {
        let el = document.getElementById('day-wise-wrapper');
        if(el && typeof Sortable !== 'undefined') {
            Sortable.create(el, { handle: '.drag-handle', animation: 150, ghostClass: 'tcc-sortable-ghost', onEnd: function () { renumberDays(); triggerLiveCalculation(); } });
        }
    }

    function generateDayInputs() {
        let days = parseInt($('#total_days').val()) || 0;
        let wrapper = $('#day-wise-wrapper');
        let currentValues = [];
        wrapper.find('.tcc-day-input').each(function() { currentValues.push($(this).val()); });
        wrapper.empty();
        for(let i = 1; i <= days; i++) {
            let val = currentValues[i-1] ? currentValues[i-1] : '';
            let row = `
            <div class="tcc-day-row" style="display:flex; align-items:stretch; margin-bottom:5px; background:#fff; border-radius:3px;">
                <div class="drag-handle" style="cursor:grab; padding:8px 10px; background:#f1f5f9; color:#94a3b8; border:1px solid #ccc; border-right:none; border-radius:3px 0 0 3px; display:flex; align-items:center;">&#9776;</div>
                <div class="tcc-day-label" style="background:#e2e8f0; padding:8px 8px; font-size:11px; font-weight:bold; border:1px solid #ccc; border-right:none; border-left:none; width:55px; text-align:center; display:flex; align-items:center; justify-content:center;">Day ${i}</div>
                <input type="text" name="itinerary_day[]" class="tcc-day-input" value="${val}" placeholder="E.g. Arrival at Srinagar & Transfer to Hotel" style="border-radius:0 3px 3px 0; margin-bottom:0; flex:1;" required>
            </div>`;
            wrapper.append(row);
        }
        if (typeof Sortable === 'undefined') { $.getScript('https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', initSortable); } else { initSortable(); }
    }

    $('#total_days').on('input', generateDayInputs);
    setTimeout(generateDayInputs, 500);

    function loadPresets() {
        let dest = $('#calc_destination').val();
        let $select = $('#itinerary_preset_select');
        $select.html('<option value="">-- Load Preset --</option>');
        $('#delete_itinerary_preset').hide();
        if(!dest) return;
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_itinerary_presets', destination: dest }, function(res) {
            if(res.success && Object.keys(res.data).length > 0) {
                $select.data('presets', res.data);
                $.each(res.data, function(presetName, daysArr) {
                    $select.append(`<option value="${presetName}">${presetName} (${daysArr.length} Days)</option>`);
                });
            }
        });
    }
    $('#calc_destination').on('change', loadPresets);
    setTimeout(loadPresets, 500);

    $('#itinerary_preset_select').on('change', function() {
        let presetName = $(this).val();
        if(!presetName) { 
            $('#delete_itinerary_preset').hide();
            $('#new_preset_name').val('');
            $('#save_itinerary_preset').text('Save as Preset');
            return; 
        }
        $('#delete_itinerary_preset').show();
        $('#new_preset_name').val(presetName);
        $('#save_itinerary_preset').text('Update Preset');
        let presets = $(this).data('presets');
        if(presets && presets[presetName]) {
            let daysArr = presets[presetName];
            $('#total_days').val(daysArr.length).trigger('input'); 
            setTimeout(function() {
                $('#day-wise-wrapper .tcc-day-input').each(function(index) { if(daysArr[index]) $(this).val(daysArr[index]); });
                triggerLiveCalculation();
            }, 100);
        }
    });

    $('#new_preset_name').on('input', function() {
        if($(this).val() !== $('#itinerary_preset_select').val()) $('#save_itinerary_preset').text('Save as Preset');
        else $('#save_itinerary_preset').text('Update Preset');
    });

    $('#save_itinerary_preset').on('click', function() {
        let presetName = $('#new_preset_name').val();
        let btn = $(this);
        if(!presetName) { alert("Please enter a Preset Name"); return; }
        let formDataArray = $('#tcc-calc-form').serializeArray();
        formDataArray.push({ name: 'action', value: 'tcc_save_itinerary_preset' });
        formDataArray.push({ name: 'preset_name', value: presetName });
        let formData = $.param(formDataArray);
        btn.text('Saving...');
        $.post(tcc_ajax_obj.ajax_url, formData, function(res) {
            btn.text('Save/Update Preset');
            if(res.success) {
                $('#preset_msg').text('Saved successfully!').css('color', '#16a34a').fadeIn().delay(2000).fadeOut();
                loadPresets(); 
                setTimeout(function(){ $('#itinerary_preset_select').val(presetName).trigger('change'); }, 500); 
            } else {
                $('#preset_msg').text('Error saving').css('color', 'red').fadeIn().delay(3000).fadeOut();
            }
        });
    });

    $('#delete_itinerary_preset').on('click', function() {
        let dest = $('#calc_destination').val();
        let presetName = $('#itinerary_preset_select').val();
        if(!presetName) return;
        if(confirm('Are you sure you want to permanently delete the "' + presetName + '" preset?')) {
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_itinerary_preset', destination: dest, preset_name: presetName }, function(res) {
                if(res.success) {
                    $('#new_preset_name').val('');
                    $('#save_itinerary_preset').text('Save as Preset');
                    $('#delete_itinerary_preset').hide();
                    $('#preset_msg').text('Deleted!').css('color', '#dc2626').fadeIn().delay(2000).fadeOut();
                    loadPresets();
                }
            });
        }
    });

    // --- BOOKING & PAYMENTS MANAGER LOGIC ---
    window.tccAllQuotes = [];

    function renderQuotesDropdown(filterText = '') {
        let term = filterText.toLowerCase();
        
        let $selPmt = $('#pmt_quote_select');
        if($selPmt.length > 0) {
            $selPmt.empty().append('<option value="">-- Select Quote/Booking --</option>');
            $.each(window.tccAllQuotes, function(i, q) {
                let searchStr = `${q.title} ${q.c_name} ${q.c_phone} ${q.c_email}`.toLowerCase();
                if(term === '' || searchStr.includes(term)) {
                    let details = [];
                    if(q.c_name) details.push(q.c_name);
                    if(q.c_phone) details.push(q.c_phone);
                    let label = q.title + (details.length ? ' [' + details.join(' | ') + ']' : ' [No Client Details]');
                    $selPmt.append($('<option></option>').val(q.id).text(label));
                }
            });
        }

        let $selCalc = $('#calc_edit_quote_select');
        if($selCalc.length > 0 && term === '') {
            $selCalc.find('option:not(:first)').remove();
            $.each(window.tccAllQuotes, function(i, q) {
                let label = q.title;
                $selCalc.append($('<option></option>').val(q.id).text(label));
            });
        }
    }

    function loadPaymentQuotes() {
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res) {
            if(res.success) {
                window.tccAllQuotes = res.data;
                renderQuotesDropdown($('#pmt_quote_search').val() || '');
            }
        });
    }

    $('#calc_edit_quote_select').on('change', function() {
        let qid = $(this).val();
        if(!qid) {
            $('#tcc-calc-form')[0].reset();
            $('#edit_quote_id').val('');
            $('#tcc_calculate_btn').text('Generate Quote Link');
            $('#transport-wrapper').empty();
            $('#night-stay-wrapper').empty();
            
            addStayRow(); 
            
            $('#day-wise-wrapper').empty();
            $('#live_error').hide();
            $('#tcc_link_wrapper').hide();
            setTimeout(triggerLiveCalculation, 100);
            return;
        }

        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_full_quote_data', quote_id: qid }, function(res) {
            if(res.success) {
                let d = res.data.summary;
                let r = res.data.raw; 
                
                $('#edit_quote_id').val(qid);
                $('#tcc_calculate_btn').text('Update Quote Link');

                $('#client_name').val(d.client_name || '');
                $('#client_phone').val(d.client_phone || '');
                $('#client_email').val(d.client_email || '');
                $('#calc_destination').val(d.destination).trigger('change');
                
                setTimeout(() => {
                    $('#start_date').val(d.start_date);
                    $('#total_pax').val(d.pax);
                    $('#child_pax').val(d.child);
                    $('#total_days').val(d.days);
                    $('#no_of_rooms').val(d.rooms);
                    $('#extra_beds').val(d.extra_beds);
                    $('#calc_pickup').val(d.pickup);
                    $('#calc_drop').val(d.drop || d.pickup);
                    $('#calc_hotel_cat').val(d.hotel_cat).trigger('change');
                    
                    $('#transport-wrapper').empty();
                    if(r && r.transports) {
                        r.transports.forEach((veh, i) => { addTransportRow(veh, r.trans_qtys[i]); });
                    }
                    
                    $('#night-stay-wrapper').empty();
                    if(r && r.stay_places) {
                        r.stay_places.forEach((place, i) => { 
                            let cat = (r.stay_categories && r.stay_categories[i]) ? r.stay_categories[i] : d.hotel_cat;
                            addStayRow(place, cat, r.stay_hotels[i], r.stay_nights[i]); 
                        });
                    } else if (d.stays) {
                        d.stays.forEach((stay) => { addStayRow(stay.place, stay.category, '', stay.nights); }); 
                    }

                    if(d.itinerary) {
                        $('#total_days').trigger('input');
                        setTimeout(() => {
                            $('#day-wise-wrapper .tcc-day-input').each(function(i) {
                                if(d.itinerary[i]) $(this).val(d.itinerary[i]);
                            });
                        }, 500);
                    }

                    if(r) {
                        $('#calc_override_profit').val(r.override_profit || '');
                        $('#calc_manual_pp_override').val(r.manual_pp_override || '');
                        $('select[name="discount_1_type"]').val(r.d1_type || 'none');
                        $('input[name="discount_1_value"]').val(r.d1_val || 0);
                        $('select[name="discount_2_type"]').val(r.d2_type || 'none');
                        $('input[name="discount_2_value"]').val(r.d2_val || 0);
                    }

                    triggerLiveCalculation();
                }, 500); 
            }
        });
    });


    $('#pmt_quote_search').on('input', function() {
        renderQuotesDropdown($(this).val());
        $('#pmt_dashboard, #pmt_quote_actions, #pmt_edit_client_wrapper').hide();
    });

    $('#pmt_quote_select').on('change', function() {
        let qid = $(this).val();
        $('#pmt_edit_client_wrapper').hide();

        if(!qid) { 
            $('#pmt_dashboard').slideUp(); 
            $('#pmt_quote_actions').hide();
            return; 
        }
        
        $('#pmt_quote_actions').css('display', 'flex');
        
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        
        if(!quoteData.c_name || !quoteData.c_phone) {
            $('#tcc-add-payment-wrapper').hide();
            $('#pmt_missing_client_msg').show();
        } else {
            $('#tcc-add-payment-wrapper').show();
            $('#pmt_missing_client_msg').hide();
        }
        
        refreshPaymentDashboard();
    });

    $('#pmt_view_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        if(quoteData && quoteData.link) {
            window.open(quoteData.link, '_blank');
        }
    });

    $('#pmt_copy_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        if(quoteData && quoteData.link) {
            let tempInput = document.createElement("input");
            document.body.appendChild(tempInput);
            tempInput.value = quoteData.link;
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            
            let $btn = $(this);
            let originalText = $btn.text();
            $btn.text("Copied!");
            setTimeout(function(){ $btn.text(originalText); }, 2000);
        }
    });

    $('#pmt_edit_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        
        $('#edit_c_name').val(quoteData.c_name);
        $('#edit_c_phone').val(quoteData.c_phone);
        $('#edit_c_email').val(quoteData.c_email);
        $('#pmt_edit_client_wrapper').slideDown();
    });

    $('#pmt_full_edit_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        
        if($('#calc_edit_quote_select').length > 0) {
            $('#calc_edit_quote_select').val(qid).trigger('change');
            $('html, body').animate({ scrollTop: $('#calc_edit_quote_select').offset().top - 50 }, 500);
        } else {
            alert("Please open the Calculator page and use the 'Load Quote to Edit' dropdown at the top.");
        }
    });

    $('#cancel_edit_client_btn').on('click', function() {
        $('#pmt_edit_client_wrapper').slideUp();
    });

    $('#save_edit_client_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        let btn = $(this);
        let data = {
            action: 'tcc_update_quote_client',
            quote_id: qid,
            c_name: $('#edit_c_name').val(),
            c_phone: $('#edit_c_phone').val(),
            c_email: $('#edit_c_email').val()
        };

        btn.text('Saving...').prop('disabled', true);
        $.post(tcc_ajax_obj.ajax_url, data, function(res) {
            btn.text('Save Details').prop('disabled', false);
            if(res.success) {
                $('#pmt_edit_client_wrapper').slideUp();
                let currentSearch = $('#pmt_quote_search').val();
                $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res2) {
                    if(res2.success) {
                        window.tccAllQuotes = res2.data;
                        renderQuotesDropdown(currentSearch);
                        $('#pmt_quote_select').val(qid).trigger('change');
                    }
                });
            }
        });
    });

    $('#pmt_delete_quote_btn').on('click', function() {
        if(!confirm("Are you sure you want to permanently delete this quote/booking? This cannot be undone.")) return;
        let qid = $('#pmt_quote_select').val();
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_quote', quote_id: qid }, function(res) {
            if(res.success) {
                $('#pmt_dashboard, #pmt_quote_actions, #pmt_edit_client_wrapper').hide();
                loadPaymentQuotes();
            }
        });
    });

    $('#pmt_method').on('change', function() {
        let btn = $('#tcc-add-payment-form button[type="submit"]');
        if($(this).val() === 'Refund') {
            btn.text('Process Refund').css({'background': '#dc2626', 'border-color': '#b91c1c'});
        } else {
            btn.text('Add Record').removeAttr('style');
            btn.css({'margin': '0', 'flex': '1', 'min-width': '100px'});
        }
    });

    function refreshPaymentDashboard() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quote_payments', quote_id: qid }, function(res) {
            if(res.success) {
                
                if(res.data.is_cancelled) {
                    $('#pmt_total_val').html(`<span style="text-decoration:line-through; opacity:0.5;">${formatINR(res.data.grand_total)}</span> <br><span style="font-size:10px; color:#dc2626; font-weight:bold;">CANCELLED</span>`);
                    $('#pmt_received_val').parent().find('div:first').text('TOTAL REFUNDED');
                    $('#pmt_received_val').text(formatINR(res.data.total_refunded)).css('color', '#dc2626');
                    $('#pmt_balance_val').parent().find('div:first').text('CANCELLATION INCOME');
                    $('#pmt_balance_val').text(formatINR(res.data.retained_income)).css('color', '#16a34a');
                } else {
                    $('#pmt_total_val').text(formatINR(res.data.grand_total));
                    $('#pmt_received_val').parent().find('div:first').text('TOTAL RECEIVED');
                    $('#pmt_received_val').text(formatINR(res.data.total_paid)).css('color', '#16a34a');
                    $('#pmt_balance_val').parent().find('div:first').text('BALANCE DUE');
                    $('#pmt_balance_val').text(formatINR(res.data.balance)).css('color', '#dc2626');
                }

                let phtml = '';
                if(res.data.payments.length > 0) {
                    phtml += `<table style="width:100%; border-collapse:collapse; font-size:12px; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:8px;">Date</th><th style="padding:8px;">Amount</th><th style="padding:8px;">Method</th><th style="padding:8px;">Ref</th><th style="padding:8px;">Action</th>
                        </tr>`;
                    $.each(res.data.payments, function(i, p) {
                        let isRefund = (p.method === 'Refund');
                        let amtColor = isRefund ? '#dc2626' : '#16a34a';
                        let amtPrefix = isRefund ? '-' : '';

                        phtml += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;">${p.date}</td>
                            <td style="padding:8px; font-weight:bold; color:${amtColor};">${amtPrefix}${formatINR(p.amount)}</td>
                            <td style="padding:8px;">${p.method}</td>
                            <td style="padding:8px; color:#64748b;">${p.ref}</td>
                            <td style="padding:8px;"><button type="button" class="del-payment-btn" data-id="${p.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline;">Delete</button></td>
                        </tr>`;
                    });
                    phtml += `</table>`;
                } else {
                    phtml = `<div style="padding:15px; text-align:center; color:#64748b; font-size:13px;">No payments/refunds recorded yet.</div>`;
                }
                $('#pmt_history_table').html(phtml);
                $('#pmt_dashboard').slideDown();
            }
        });
    }

    $('#tcc-add-payment-form').on('submit', function(e) {
        e.preventDefault();
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;

        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        let data = {
            action: 'tcc_add_payment',
            quote_id: qid,
            amount: $('#pmt_amount').val(),
            date: $('#pmt_date').val(),
            method: $('#pmt_method').val(),
            ref: $('#pmt_ref').val()
        };

        $.post(tcc_ajax_obj.ajax_url, data, function(res) {
            btn.prop('disabled', false).text(originalText);
            if(res.success) {
                $('#pmt_amount, #pmt_ref').val('');
                refreshPaymentDashboard();
            }
        });
    });

    $(document).on('click', '.del-payment-btn', function() {
        if(!confirm('Delete this record?')) return;
        let pmt_id = $(this).data('id');
        let qid = $('#pmt_quote_select').val();
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_payment', quote_id: qid, pmt_id: pmt_id }, function(res) {
            if(res.success) refreshPaymentDashboard();
        });
    });

    // --- SETTINGS DASHBOARD DYNAMICS ---

    $(document).on('click', '.tcc-rename-master', function(e) {
        e.preventDefault();
        let type = $(this).data('type');
        let dropdownId = $(this).data('dropdown');
        let oldVal = $(dropdownId).val();
        
        let dest = '';
        if(type === 'destination') {
            dest = oldVal;
        } else if(type === 'stay_place' || type === 'hotel_cat') {
            dest = $('#set_hotel_dest').val();
        } else if(type === 'pickup' || type === 'vehicle') {
            dest = $('#set_trans_dest').val();
        }
        
        if(!oldVal || oldVal === 'ADD_NEW') return alert("Please select an item from the dropdown first to rename it.");
        
        let newVal = prompt("Rename '" + oldVal + "' to:", oldVal);
        if(newVal && newVal.trim() !== '' && newVal !== oldVal) {
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_rename_master_element',
                element_type: type,
                target_dest: dest,
                old_name: oldVal,
                new_name: newVal.trim()
            }, function(res) {
                if(res.success) {
                    tccMasterData = res.data.new_master;
                    rebuildDestinationDropdowns();
                    updateSettingsDropdowns();
                    updateCalculatorDropdowns();
                    showSettingsMessage(res.data.message);
                    $(dropdownId).val(newVal.trim()).trigger('change');
                } else {
                    showSettingsMessage(res.data.message || "Error renaming.");
                }
            });
        }
    });

    $('#tcc-global-settings-form').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_global_settings', function(res) {
            btn.prop('disabled', false).text(originalText);
            if(res.success) { 
                showSettingsMessage(res.data.message); 
                tccGlobalSettings.gst = parseFloat($('#global_gst').val());
                tccGlobalSettings.pt = parseFloat($('#global_pt').val());
                tccGlobalSettings.pg = parseFloat($('#global_pg').val());
                triggerLiveCalculation();
            }
        });
    });

    $('#add_season_btn').on('click', function() {
        let row = `
        <div class="season-row tcc-repeater-row tcc-fade-in">
            <input type="date" name="season_start[]" required style="flex:1;">
            <input type="date" name="season_end[]" required style="flex:1;">
            <input type="number" name="season_percent[]" placeholder="%" step="0.01" required style="flex:0.5;">
            <button type="button" class="remove_season tcc-btn-del">X</button>
        </div>`;
        $('#season-settings-wrapper').append(row);
    });
    $(document).on('click', '.remove_season', function() { $(this).closest('.season-row').remove(); });

    function updateSettingsDropdowns() {
        if(typeof tccMasterData === 'undefined') return;
        
        let h_dest = $('#set_hotel_dest').val();
        if(h_dest && tccMasterData[h_dest]) {
            populateDropdown('#set_hotel_stay', tccMasterData[h_dest].stay_places);
            populateDropdown('#set_hotel_cat', tccMasterData[h_dest].hotel_categories);
        } else {
            $('#set_hotel_stay, #set_hotel_cat').empty().append('<option value="">N/A</option>');
        }

        let t_dest = $('#set_trans_dest').val();
        if(t_dest && tccMasterData[t_dest]) {
            populateDropdown('#set_trans_pickup', tccMasterData[t_dest].pickups);
            populateDropdown('#set_trans_vehicle', tccMasterData[t_dest].vehicles);
        } else {
            $('#set_trans_pickup, #set_trans_vehicle').empty().append('<option value="">N/A</option>');
        }
    }

    function fetchHotelNamesList() {
        let dest = $('#set_hotel_dest').val();
        let place = $('#set_hotel_stay').val();
        let cat = $('#set_hotel_cat').val();

        if(dest && place && cat) {
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_names', dest: dest, place: place, cat: cat }, function(res) {
                let $dd = $('#hotel_name_dropdown');
                $dd.empty().append('<option value="">-- Add/Select --</option>');
                if(res.success && res.data.length > 0) {
                    $.each(res.data, function(i, name) { $dd.append(`<option value="${name}">${name}</option>`); });
                }
                $dd.append('<option value="ADD_NEW" style="font-weight:bold; color:#108043;">+ ADD NEW</option>');
                $dd.val('').trigger('change');
            });
        }
    }

    $('#hotel_name_dropdown').on('change', function() {
        let val = $(this).val();
        let $input = $('#set_hotel_name_input');
        
        if(val === 'ADD_NEW') {
            $input.val('').show().focus();
            $('#set_hotel_website, #set_room_price, #set_extra_bed, #set_child_price').val('');
            $('#hotel_fetch_status').text('(New)');
            $('#edit_hotel_name_btn').hide();
            $('#delete_hotel_btn').hide();
        } else if (val !== '') {
            $input.val(val).hide(); 
            fetchHotelPricingSpecific(val);
            $('#edit_hotel_name_btn').show();
            $('#delete_hotel_btn').show();
        } else {
            $input.val('').hide();
            $('#set_hotel_website, #set_room_price, #set_extra_bed, #set_child_price').val('');
            $('#hotel_fetch_status').text('');
            $('#edit_hotel_name_btn').hide();
            $('#delete_hotel_btn').hide();
        }
    });

    function fetchHotelPricingSpecific(hotel_name) {
        let dest = $('#set_hotel_dest').val();
        let place = $('#set_hotel_stay').val();
        let cat = $('#set_hotel_cat').val();
        $('#hotel_fetch_status').text('...');
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_rate', destination: dest, place: place, category: cat, hotel_name: hotel_name }, function(res) {
            if(res.success) {
                $('#hotel_fetch_status').text('(Edit)');
                $('#set_hotel_website').val(res.data.hotel_website);
                $('#set_room_price').val(res.data.room_price);
                $('#set_extra_bed').val(res.data.extra_bed_price);
                $('#set_child_price').val(res.data.child_price);
            }
        });
    }

    $('#edit_hotel_name_btn').on('click', function() {
        let currentName = $('#hotel_name_dropdown').val();
        if(!currentName || currentName === 'ADD_NEW') return;

        let newName = prompt("Enter new name for " + currentName + ":", currentName);
        if(newName && newName.trim() !== '' && newName !== currentName) {
            let dest = $('#set_hotel_dest').val();
            let place = $('#set_hotel_stay').val();
            let cat = $('#set_hotel_cat').val();

            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_rename_hotel_rate',
                set_destination: dest,
                set_night_stay_place: place,
                set_hotel_cat: cat,
                old_hotel_name: currentName,
                new_hotel_name: newName
            }, function(res) {
                if(res.success) {
                    showSettingsMessage(res.data.message);
                    fetchHotelNamesList(); 
                } else {
                    showSettingsMessage("Error renaming hotel.");
                }
            });
        }
    });

    $('#delete_hotel_btn').on('click', function() {
        let dest = $('#set_hotel_dest').val();
        let place = $('#set_hotel_stay').val();
        let cat = $('#set_hotel_cat').val();
        let hotel = $('#hotel_name_dropdown').val();

        if(!hotel || hotel === 'ADD_NEW') return;

        if(confirm('Are you sure you want to permanently delete this hotel?')) {
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_delete_hotel_rate',
                set_destination: dest,
                set_night_stay_place: place,
                set_hotel_cat: cat,
                set_hotel_name: hotel
            }, function(res) {
                if(res.success) {
                    showSettingsMessage(res.data.message);
                    fetchHotelNamesList(); 
                    $('#edit_hotel_name_btn').hide();
                    $('#delete_hotel_btn').hide();
                } else {
                    showSettingsMessage("Error deleting hotel.");
                }
            });
        }
    });

    $('#delete_transport_btn').on('click', function() {
        let dest = $('#set_trans_dest').val();
        let pickup = $('#set_trans_pickup').val();
        let vehicle = $('#set_trans_vehicle').val();

        if(!dest || !pickup || !vehicle) return;

        if(confirm('Are you sure you want to permanently delete this transport rate?')) {
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_delete_transport_rate',
                set_destination: dest,
                set_pickup_loc: pickup,
                set_vehicle: vehicle
            }, function(res) {
                if(res.success) {
                    showSettingsMessage(res.data.message);
                    fetchTransportPricing(); 
                } else {
                    showSettingsMessage("Error deleting transport.");
                }
            });
        }
    });

    $('#set_hotel_dest, #set_hotel_stay, #set_hotel_cat').on('change', function() {
        if($(this).attr('id') === 'set_hotel_dest') updateSettingsDropdowns();
        fetchHotelNamesList();
    });

    $('#set_trans_dest').on('change', function() { updateSettingsDropdowns(); fetchTransportPricing(); });

    function fetchTransportPricing() {
        let dest = $('#set_trans_dest').val();
        let pickup = $('#set_trans_pickup').val();
        let vehicle = $('#set_trans_vehicle').val();

        if(dest && pickup && vehicle) {
            $('#trans_fetch_status').text('...');
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_transport_rate', destination: dest, pickup: pickup, vehicle: vehicle }, function(res) {
                if(res.success) {
                    $('#trans_fetch_status').text('(Edit)');
                    $('#set_capacity').val(res.data.capacity);
                    $('#set_transport_price').val(res.data.price_per_day);
                    $('#delete_transport_btn').show();
                } else {
                    $('#trans_fetch_status').text('(New)');
                    $('#set_transport_price').val('');
                    $('#delete_transport_btn').hide();
                }
            });
        }
    }
    $('#set_trans_pickup, #set_trans_vehicle').on('change', fetchTransportPricing);
    
    function populateMasterForm(val) {
        $('#season-settings-wrapper').empty(); 

        if(val && tccMasterData[val]) {
            $('#master_dest_name').val(val);
            $('#master_profit').val(tccMasterData[val].profit_per_person || 0);
            
            let p = tccMasterData[val].pickups;
            $('#master_pickups').val(Array.isArray(p) ? p.join(', ') : p);
            
            let s = tccMasterData[val].stay_places;
            $('#master_stays').val(Array.isArray(s) ? s.join(', ') : s);
            
            let v = tccMasterData[val].vehicles;
            $('#master_vehicles').val(Array.isArray(v) ? v.join(', ') : v);
            
            let c = tccMasterData[val].hotel_categories;
            $('#master_hotel_cats').val(Array.isArray(c) ? c.join(', ') : c);

            $('#master_inclusions').val(tccMasterData[val].inclusions || '');
            $('#master_exclusions').val(tccMasterData[val].exclusions || '');
            $('#master_payment_terms').val(tccMasterData[val].payment_terms || '');

            if(tccMasterData[val].seasons && tccMasterData[val].seasons.length > 0) {
                $.each(tccMasterData[val].seasons, function(i, season) {
                    let row = `
                    <div class="season-row tcc-repeater-row tcc-fade-in">
                        <input type="date" name="season_start[]" value="${season.start}" required style="flex:1;">
                        <input type="date" name="season_end[]" value="${season.end}" required style="flex:1;">
                        <input type="number" name="season_percent[]" value="${season.percent}" placeholder="%" step="0.01" required style="flex:0.5;">
                        <button type="button" class="remove_season tcc-btn-del">X</button>
                    </div>`;
                    $('#season-settings-wrapper').append(row);
                });
            }
        } else {
            $('#tcc-master-settings-form')[0].reset();
            $('#master_dest_name').val('');
        }
    }

    $('#master_dest_select').on('change', function() { populateMasterForm($(this).val()); });

    setTimeout(() => { 
        rebuildDestinationDropdowns();
        populateMasterForm($('#master_dest_select').val());
        updateSettingsDropdowns();
        updateCalculatorDropdowns();
        fetchHotelNamesList(); 
        fetchTransportPricing(); 
        
        if($('#night-stay-wrapper').children().length === 0) {
            addStayRow();
        }

        setTimeout(() => { triggerAutoTransportOptimization(); }, 500); 
        loadPaymentQuotes(); 
    }, 200);

    function showSettingsMessage(msg) { $('#tcc_settings_msg').text(msg).fadeIn().delay(3000).fadeOut(); }

    $('#tcc-master-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_master_settings', function(res) {
            if(res.success) { 
                showSettingsMessage(res.data.message); 
                tccMasterData = res.data.new_master; 
                rebuildDestinationDropdowns(); 
                updateSettingsDropdowns();
                updateCalculatorDropdowns();
            }
        });
    });

    $('#tcc-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_pricing_settings', function(res) {
            if(res.success) { showSettingsMessage(res.data.message); fetchHotelNamesList(); }
        });
    });

    $('#tcc-transport-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_transport_settings', function(res) {
            if(res.success) showSettingsMessage(res.data.message);
        });
    });
});