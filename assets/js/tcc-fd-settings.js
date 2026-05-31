jQuery(document).ready(function($) {
    
    window.tccCurrentCategories = [];

    function initQuillEditors() {
        if (typeof Quill === 'undefined') return;
        $('.tcc-quill-wrapper').each(function() {
            if ($(this).hasClass('quill-ready')) return;
            $(this).addClass('quill-ready');
            let $editorDiv = $(this).find('.tcc-quill-editor');
            let $hiddenTextarea = $(this).find('textarea');
            let quill = new Quill($editorDiv[0], {
                theme: 'snow',
                modules: { toolbar: [['bold', 'italic', 'underline', 'strike'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], [{ 'header': [3, false] }], [{ 'color': [] }, { 'background': [] }], ['clean']] }
            });
            quill.on('text-change', function() { $hiddenTextarea.val(quill.root.innerHTML); });
        });
    }
    setTimeout(initQuillEditors, 200);

    let sortableDays, sortableStays;
    function initSortables() {
        if (typeof Sortable !== 'undefined') {
            let dayEl = document.getElementById('fd-day-wise-wrapper');
            let stayEl = document.getElementById('fd-night-stay-wrapper');
            if (dayEl) sortableDays = Sortable.create(dayEl, { handle: '.drag-handle', animation: 150 });
            if (stayEl) sortableStays = Sortable.create(stayEl, { handle: '.drag-handle', animation: 150 });
        }
    }
    setTimeout(initSortables, 500);

    function loadTours() {
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_fixed_tours' }, function(res) {
            if(res.success) {
                let html = '<option value="">-- Create New Group Tour --</option>';
                res.data.forEach(t => { html += `<option value="${t.id}">${t.name}</option>`; });
                $('#fd_edit_tour_select').html(html);
            }
        });
    }
    loadTours();

    $('#fd_destination').change(function() {
        let dest = $(this).val();
        let $presetSel = $('#fd_itinerary_preset');
        $presetSel.html('<option value="">-- Select Preset --</option>');
        
        if(dest && tccMasterData[dest]) {
            let mData = tccMasterData[dest];

            // Setup Categories
            window.tccCurrentCategories = mData.hotel_categories || [];
            let catHtml = '<option value="">-- Select --</option>';
            window.tccCurrentCategories.forEach(c => catHtml += `<option value="${c}">${c}</option>`);
            
            // Update existing dropdowns visually if changed
            $('.d-cat, .stay-cat').each(function() {
                let currentVal = $(this).val();
                $(this).html(catHtml);
                if(currentVal) $(this).val(currentVal);
            });
            
            function autoFillQuill(id, val) {
                if(val !== undefined && val !== null) {
                    $('#' + id).val(val);
                    let q = Quill.find(document.getElementById(id + '_editor'));
                    if(q) q.clipboard.dangerouslyPasteHTML(val);
                }
            }
            autoFillQuill('fd_inclusions', mData.inclusions);
            autoFillQuill('fd_exclusions', mData.exclusions);
            autoFillQuill('fd_payment_terms', mData.payment_terms);
            autoFillQuill('fd_important_note', mData.important_note);
            autoFillQuill('fd_why_choose_us', mData.why_choose_us);
            autoFillQuill('fd_essential_guidelines', mData.essential_guidelines);

            // Populate Head Office
            $('#fd_head_office').val(mData.head_office || '');

            // Populate Pickup/Drop
            if(mData.pickups && Array.isArray(mData.pickups) && mData.pickups.length > 0) {
                $('#fd_pickup').val(mData.pickups[0]); $('#fd_drop').val(mData.pickups[0]);
            } else if (typeof mData.pickups === 'string') {
                let firstPickup = mData.pickups.split(',')[0].trim();
                $('#fd_pickup').val(firstPickup); $('#fd_drop').val(firstPickup);
            }

            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_itinerary_presets', destination: dest }, function(res) {
                if(res.success && res.data) {
                    let pData = res.data;
                    if(typeof pData === 'string') pData = JSON.parse(pData);
                    $presetSel.data('presets', pData);
                    Object.keys(pData).forEach(pName => { $presetSel.append(`<option value="${pName}">${pName}</option>`); });
                }
            });
        }
    });

    $('#fd_itinerary_preset').change(function() {
        let pName = $(this).val();
        let presets = $(this).data('presets');
        if(pName && presets && presets[pName]) {
            let pData = presets[pName];
            $('#fd-day-wise-wrapper, #fd-night-stay-wrapper').empty();

            let daysArr = pData.itinerary || Object.values(pData);
            let routesArr = pData.itinerary_routes || [];
            let staysArr = pData.stay_places || [];
            let descsArr = pData.itinerary_desc || [];
            let imagesArr = pData.itinerary_image || [];

            if(Array.isArray(daysArr) || typeof daysArr === 'object') {
                Object.values(daysArr).forEach((title, i) => {
                    if(typeof title === 'string') addFdDayRow(title, routesArr[i]||'', staysArr[i]||'', descsArr[i]||'', imagesArr[i]||'');
                });
            }

            if(pData.routing_stay_places && pData.routing_stay_places.length > 0) {
                pData.routing_stay_places.forEach((place, i) => {
                    let cat = pData.routing_stay_categories ? pData.routing_stay_categories[i] : '';
                    let nights = pData.routing_stay_nights ? pData.routing_stay_nights[i] : 1;
                    let room = pData.routing_stay_room_types ? pData.routing_stay_room_types[i] : 'Default';
                    let hotels = pData.routing_stay_hotels ? pData.routing_stay_hotels[i] : '';
                    addFdStayRow(place, cat, nights, room, hotels);
                });
            } else if (staysArr.length > 0) {
                 let grouped = []; let currentStay = staysArr[0]; let count = 1;
                 for(let i=1; i<staysArr.length; i++){
                     if(staysArr[i] === currentStay) count++;
                     else { grouped.push({place: currentStay, nights: count}); currentStay = staysArr[i]; count = 1; }
                 }
                 grouped.push({place: currentStay, nights: count});
                 grouped.forEach(g => {
                     if(g.place && g.place !== 'Trip Ends') addFdStayRow(g.place, '', g.nights, 'Default', '');
                 });
            }
        }
    });

    function addFdDateRow(start='', end='', cap='', h_cat='', p_dbl='', p_tpl='', p_sgl='', p_chl='', p_inf='') {
        let catHtml = '<option value="">-- Tour Category --</option>';
        window.tccCurrentCategories.forEach(c => { let sel = (c === h_cat) ? 'selected' : ''; catHtml += `<option value="${c}" ${sel}>${c}</option>`; });

        // Auto-calculate "Days" if start and end are provided (for editing mode)
        let days = '';
        let endDisplay = 'N/A';
        if (start && end) {
            let d1 = new Date(start);
            let d2 = new Date(end);
            if (!isNaN(d1) && !isNaN(d2)) {
                days = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
                endDisplay = d2.toLocaleDateString('en-GB');
            }
        }

        let row = `
        <div class="fd-date-row" style="background:#fff; padding:15px; border:1px solid #cbd5e1; margin-bottom:10px; border-radius:4px; position:relative;">
            <button type="button" class="tcc-btn-del remove-fd-date" style="position:absolute; top:10px; right:10px; width:25px; height:25px; padding:0;">X</button>
            <div style="display:flex; gap:10px; margin-bottom:10px; flex-wrap:wrap; padding-right:20px;">
                <div style="flex:1.2;"><label style="font-size:11px;">Start</label><input type="date" class="d-start" value="${start}" required style="width:100%; height:28px;"></div>
                
                <div style="flex:0.5;"><label style="font-size:11px;">Days</label><input type="number" class="d-days" value="${days}" min="1" required style="width:100%; height:28px;"></div>
                
                <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                    <label style="font-size:11px;">End Date</label>
                    <div class="d-end-display" style="font-size:13px; color:#16a34a; font-weight:bold; padding-top:4px;">${endDisplay}</div>
                    <input type="hidden" class="d-end" value="${end}">
                </div>

                <div style="flex:1;"><label style="font-size:11px;">Capacity</label><input type="number" class="d-cap" value="${cap}" required style="width:100%; height:28px;"></div>
                <div style="flex:1.5;"><label style="font-size:11px;">Tour Category</label><select class="d-cat" required style="width:100%; height:28px;">${catHtml}</select></div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; border-top:1px dashed #e2e8f0; padding-top:10px;">
                <div style="flex:1;"><label style="font-size:10px;">Double ₹</label><input type="number" class="d-dbl" value="${p_dbl}" required style="width:100%;"></div>
                <div style="flex:1;"><label style="font-size:10px;">Triple ₹</label><input type="number" class="d-tpl" value="${p_tpl}" required style="width:100%;"></div>
                <div style="flex:1;"><label style="font-size:10px;">Single ₹</label><input type="number" class="d-sgl" value="${p_sgl}" required style="width:100%;"></div>
                <div style="flex:1;"><label style="font-size:10px;">Child ₹</label><input type="number" class="d-chl" value="${p_chl}" required style="width:100%;"></div>
                <div style="flex:1;"><label style="font-size:10px;">Infant ₹</label><input type="number" class="d-inf" value="${p_inf}" required style="width:100%;"></div>
            </div>
        </div>`;
        $('#fd_dates_wrapper').append(row);
    }
    $('#add_fd_date_btn').click(() => addFdDateRow());
    $(document).on('click', '.remove-fd-date', function() { $(this).closest('.fd-date-row').remove(); });
	
	// Auto calculate End Date based on Start Date and Days
    $(document).on('input change', '.d-start, .d-days', function() {
        let $row = $(this).closest('.fd-date-row');
        let start = $row.find('.d-start').val();
        let days = parseInt($row.find('.d-days').val());

        if (start && days > 0) {
            let d = new Date(start);
            // 1 day tour means end = start, so we add (days - 1)
            d.setDate(d.getDate() + days - 1); 
            
            // Format to YYYY-MM-DD for the hidden input (database format)
            let month = ('0' + (d.getMonth() + 1)).slice(-2);
            let day = ('0' + d.getDate()).slice(-2);
            let endFormatted = d.getFullYear() + '-' + month + '-' + day;
            
            $row.find('.d-end').val(endFormatted);
            $row.find('.d-end-display').text(d.toLocaleDateString('en-GB'));
        } else {
            $row.find('.d-end').val('');
            $row.find('.d-end-display').text('N/A');
        }
    });

    function addFdDayRow(title='', route='', stay='', desc='', image='') {
        let dest = $('#fd_destination').val();
        let routes = '<option value="">-- Route --</option>';
        let stays = '<option value="">-- Night Stay --</option><option value="No Hotel">No Hotel Required</option><option value="Trip Ends">Trip Ends</option>';
        
        if (dest && tccMasterData[dest]) {
            tccMasterData[dest].daily_routes.forEach(r => { let sel = (r===route)?'selected':''; routes += `<option value="${r}" ${sel}>${r}</option>`; });
            tccMasterData[dest].stay_places.forEach(s => { let sel = (s===stay)?'selected':''; stays += `<option value="${s}" ${sel}>${s}</option>`; });
        }

        let uniqueId = 'day_desc_' + Math.random().toString(36).substr(2, 9);

        let html = `
            <div class="day-item" style="background:#fff; border:1px solid #cbd5e1; padding:15px; margin-bottom:10px; border-radius:4px; display:flex; gap:10px;">
                <div class="drag-handle" style="cursor:move; font-size:24px; color:#94a3b8;">☰</div>
                <div style="flex:1;">
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="text" class="day-title" value="${title.replace(/"/g, '&quot;')}" placeholder="Day Title" style="flex:2;" required>
                        <select class="day-route" style="flex:1;">${routes}</select>
                        <select class="day-stay" style="flex:1;">${stays}</select>
                        <button type="button" class="tcc-btn-del remove-day" style="height:35px; width:35px; padding:0; margin:0;">X</button>
                    </div>
                    <div class="tcc-quill-wrapper quill-ready" style="border:1px solid #ccc; background:#fff; margin-bottom:10px;">
                        <div class="tcc-quill-editor" id="${uniqueId}" style="min-height:80px; font-size:12px;">${desc}</div>
                        <textarea class="day-desc" style="display:none;">${desc}</textarea>
                    </div>
                    <input type="text" class="day-image" value="${image}" placeholder="Image URL (Optional)" style="width:100%;">
                </div>
            </div>`;
        $('#fd-day-wise-wrapper').append(html);
        
        let q = new Quill(`#${uniqueId}`, { theme: 'snow', modules: { toolbar: [['bold', 'italic'], [{'list': 'bullet'}], ['clean']] } });
        q.on('text-change', function() { $(`#${uniqueId}`).siblings('.day-desc').val(q.root.innerHTML); });
    }
    $('#fd_add_day_btn').click(() => addFdDayRow());
    $(document).on('click', '.remove-day', function() { $(this).closest('.day-item').remove(); });

    function addFdStayRow(place='', category='', nights='1', room='Default', hotelsStr='') {
        let dest = $('#fd_destination').val();
        let placesHtml = '<option value="">Select Place</option>';
        if(dest && tccMasterData[dest]) {
            tccMasterData[dest].stay_places.forEach(p => { let sel = (p===place)?'selected':''; placesHtml += `<option value="${p}" ${sel}>${p}</option>`; });
        }
        
        let catHtmlStay = '<option value="">-- Target Category --</option>';
        window.tccCurrentCategories.forEach(c => { let sel = (c === category) ? 'selected' : ''; catHtmlStay += `<option value="${c}" ${sel}>${c}</option>`; });

        let hotelHtml = '';
        if(hotelsStr) {
            let hArr = hotelsStr.split(',');
            hArr.forEach(h => {
                let hName = h.trim(); let hLink = '';
                if(hName.includes('::')) { let parts = hName.split('::'); hName = parts[0]; hLink = parts[1] || ''; }
                if(hName) {
                    hotelHtml += `
                    <div class="fd-custom-hotel" style="display:flex; gap:5px; margin-bottom:5px;">
                        <input type="text" class="ch-name" value="${hName.replace(/"/g, '&quot;')}" placeholder="Hotel Name" style="flex:1; font-size:11px; padding:4px;">
                        <input type="text" class="ch-link" value="${hLink}" placeholder="Website Link (Opt)" style="flex:1; font-size:11px; padding:4px;">
                        <button type="button" class="tcc-btn-del remove-ch" style="padding:2px 6px; margin:0;">x</button>
                    </div>`;
                }
            });
        }

        let html = `
            <div class="stay-item" style="background:#fff; border:1px solid #cbd5e1; padding:10px; margin-bottom:10px; border-radius:4px; display:flex; gap:10px; align-items:flex-start;">
                <div class="drag-handle" style="cursor:move; font-size:24px; color:#94a3b8; padding-top:5px;">☰</div>
                <div style="flex:1.5;">
                    <select class="stay-place" style="width:100%; margin-bottom:5px;" required>${placesHtml}</select>
                    <select class="stay-cat" style="width:100%; margin-bottom:5px; height:28px;" required>${catHtmlStay}</select>
                    <div style="display:flex; gap:5px;">
                        <input type="number" class="stay-nights" value="${nights}" min="1" style="width:70px;" placeholder="Nights">
                        <input type="text" class="stay-room" value="${room}" placeholder="Room Type" style="flex:1;">
                    </div>
                </div>
                <div class="fd-custom-hotels-wrapper" style="flex:2; background:#f8fafc; padding:8px; border:1px dashed #cbd5e1; border-radius:4px;">
                    <div style="font-size:10px; font-weight:bold; margin-bottom:5px; color:#64748b;">Assigned Custom Hotels</div>
                    <div class="ch-list">${hotelHtml}</div>
                    <button type="button" class="tcc-btn-secondary add-custom-hotel-btn" style="font-size:10px; padding:4px 8px; margin:0;">+ Add Hotel w/ Link</button>
                </div>
                <button type="button" class="tcc-btn-del remove-stay" style="padding:4px 8px; margin:0;">X</button>
            </div>`;
        $('#fd-night-stay-wrapper').append(html);
    }
    
    $('#fd_add_stay_place').click(() => addFdStayRow());
    $(document).on('click', '.remove-stay', function() { $(this).closest('.stay-item').remove(); });
    
    $(document).on('click', '.add-custom-hotel-btn', function() {
        $(this).siblings('.ch-list').append(`
            <div class="fd-custom-hotel" style="display:flex; gap:5px; margin-bottom:5px;">
                <input type="text" class="ch-name" placeholder="Hotel Name" style="flex:1; font-size:11px; padding:4px;">
                <input type="url" class="ch-link" placeholder="Website Link (Opt)" style="flex:1; font-size:11px; padding:4px;">
                <button type="button" class="tcc-btn-del remove-ch" style="padding:2px 6px; margin:0;">x</button>
            </div>
        `);
    });
    $(document).on('click', '.remove-ch', function() { $(this).closest('.fd-custom-hotel').remove(); });

    $('#fd_edit_tour_select').change(function() {
        let tid = $(this).val();
        if(!tid) {
            $('#tcc-adv-fixed-tour-form')[0].reset();
            $('#fd_tour_id').val('');
            $('#fd_addons').val('');
            $('#fd_dates_wrapper, #fd-day-wise-wrapper, #fd-night-stay-wrapper').empty();
            $('.ql-editor').html(''); 
            $('#fd_delete_tour_btn, #fd_duplicate_tour_btn').hide();
            $('#save_fixed_tour_btn').text('Save Group Tour');
            return;
        }

        $('#fd_delete_tour_btn, #fd_duplicate_tour_btn').show();
        $('#save_fixed_tour_btn').text('Update Group Tour');
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_single_fixed_tour', tour_id: tid }, function(res) {
            if(res.success) {
                let d = res.data;
                $('#fd_tour_id').val(d.id); $('#fd_tour_name').val(d.name);
                $('#fd_destination').val(d.fd_destination).trigger('change');
                
                $('#fd_transport_details').val(d.transport_details);
                $('#fd_pickup').val(d.pickup); $('#fd_drop').val(d.drop); $('#fd_head_office').val(d.head_office);
                $('#fd_addons').val(d.addons || '');

                setTimeout(() => {
                    ['inclusions', 'exclusions', 'payment_terms', 'important_note', 'why_choose_us', 'essential_guidelines'].forEach(key => {
                        let val = d[key] || '';
                        $('#fd_' + key).val(val);
                        let q = Quill.find(document.getElementById('fd_' + key + '_editor'));
                        if(q) q.clipboard.dangerouslyPasteHTML(val);
                    });
                }, 300);

                $('#fd_dates_wrapper').empty();
                if(d.fd_dates) {
                    try { JSON.parse(d.fd_dates).forEach(dt => addFdDateRow(dt.start_date, dt.end_date, dt.capacity, dt.hotel_cat, dt.price_double, dt.price_triple, dt.price_single, dt.price_child, dt.price_infant)); } catch(e){}
                }

                $('#fd-day-wise-wrapper, #fd-night-stay-wrapper').empty();
                if(d.fd_itinerary_data) {
                    try {
                        let itin = JSON.parse(d.fd_itinerary_data);
                        if(itin.itinerary && itin.itinerary.length > 0) {
                            itin.itinerary.forEach((title, i) => {
                                addFdDayRow(title, itin.itinerary_routes ? itin.itinerary_routes[i] : '', itin.stay_places ? itin.stay_places[i] : '', itin.itinerary_desc ? itin.itinerary_desc[i] : '', itin.itinerary_image ? itin.itinerary_image[i] : '');
                            });
                        }
                        if(itin.routing_stay_places && itin.routing_stay_places.length > 0) {
                            itin.routing_stay_places.forEach((place, i) => {
                                addFdStayRow(place, itin.routing_stay_categories ? itin.routing_stay_categories[i] : '', itin.routing_stay_nights ? itin.routing_stay_nights[i] : 1, itin.routing_stay_room_types ? itin.routing_stay_room_types[i] : 'Default', itin.routing_stay_hotels ? itin.routing_stay_hotels[i] : '');
                            });
                        }
                    } catch(e) {}
                }
            }
        });
    });

    $('#tcc-adv-fixed-tour-form').submit(function(e) {
        e.preventDefault();
        
        $('.tcc-quill-wrapper').each(function() {
            let $ta = $(this).find('textarea');
            let $ed = $(this).find('.ql-editor');
            if($ed.length) $ta.val($ed.html());
        });

        let fd_dates = [];
        $('.fd-date-row').each(function() {
            fd_dates.push({
                start_date: $(this).find('.d-start').val(), end_date: $(this).find('.d-end').val(),
                capacity: $(this).find('.d-cap').val(), hotel_cat: $(this).find('.d-cat').val(),
                price_double: $(this).find('.d-dbl').val(), price_triple: $(this).find('.d-tpl').val(),
                price_single: $(this).find('.d-sgl').val(), price_child: $(this).find('.d-chl').val(), price_infant: $(this).find('.d-inf').val()
            });
        });

        if(fd_dates.length === 0) { alert("You must add at least one Departure Date, as this is where you specify the pricing."); return; }

        let itineraryData = {
            itinerary: [], itinerary_desc: [], itinerary_routes: [], itinerary_image: [], stay_places: [],
            routing_stay_places: [], routing_stay_categories: [], routing_stay_hotels: [], routing_stay_nights: [], routing_stay_room_types: []
        };

        $('.day-item').each(function() {
            itineraryData.itinerary.push($(this).find('.day-title').val());
            itineraryData.itinerary_routes.push($(this).find('.day-route').val());
            itineraryData.itinerary_desc.push($(this).find('.day-desc').val());
            itineraryData.stay_places.push($(this).find('.day-stay').val());
            itineraryData.itinerary_image.push($(this).find('.day-image').val());
        });

        $('.stay-item').each(function() {
            itineraryData.routing_stay_places.push($(this).find('.stay-place').val());
            itineraryData.routing_stay_categories.push($(this).find('.stay-cat').val()); 
            itineraryData.routing_stay_nights.push($(this).find('.stay-nights').val());
            itineraryData.routing_stay_room_types.push($(this).find('.stay-room').val());
            
            let customHotels = [];
            $(this).find('.fd-custom-hotel').each(function() {
                let name = $(this).find('.ch-name').val().trim();
                let link = $(this).find('.ch-link').val().trim();
                if(name) { if(link) customHotels.push(name + '::' + link); else customHotels.push(name); }
            });
            itineraryData.routing_stay_hotels.push(customHotels.join(','));
        });

        let payload = {
            action: 'tcc_save_fixed_tour', security: tcc_ajax_obj.nonce,
            tour_id: $('#fd_tour_id').val(), tour_name: $('#fd_tour_name').val(), fd_destination: $('#fd_destination').val(),
            fd_dates: JSON.stringify(fd_dates), fd_itinerary_data: JSON.stringify(itineraryData),
            pickup: $('#fd_pickup').val(), drop: $('#fd_drop').val(),
            transport_details: $('#fd_transport_details').val(), head_office: $('#fd_head_office').val(),
            addons: $('#fd_addons').val(),
            inclusions: $('#fd_inclusions').val(), exclusions: $('#fd_exclusions').val(),
            payment_terms: $('#fd_payment_terms').val(), important_note: $('#fd_important_note').val(),
            why_choose_us: $('#fd_why_choose_us').val(), essential_guidelines: $('#fd_essential_guidelines').val()
        };

        $('#save_fixed_tour_btn').text('Saving...');
        $.post(tcc_ajax_obj.ajax_url, payload, function(res) {
            $('#save_fixed_tour_btn').text('Save Group Tour');
            if(res.success) { $('#fd_save_msg').text('Saved successfully!').show().delay(3000).fadeOut(); loadTours(); }
        });
    });

    $('#fd_delete_tour_btn').click(function() {
        if(confirm('Delete Tour?')) {
            $.post(tcc_ajax_obj.ajax_url, {action: 'tcc_delete_fixed_tour', tour_id: $('#fd_tour_id').val(), security: tcc_ajax_obj.nonce}, function(res) {
                if(res.success) location.reload();
            });
        }
    });

    $('#fd_duplicate_tour_btn').click(function() {
        if(confirm('Duplicate this Tour?')) {
            $.post(tcc_ajax_obj.ajax_url, {action: 'tcc_duplicate_fixed_tour', tour_id: $('#fd_tour_id').val(), security: tcc_ajax_obj.nonce}, function(res) {
                if(res.success) { alert("Duplicated! Select the copy from the dropdown."); loadTours(); }
            });
        }
    });
});