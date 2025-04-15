jQuery(document).ready(function($) {
    console.log('google-calendar.js file loaded'); // Logování načtení souboru

    function handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId) {
        console.log('handleGoogleCalendarEvent called with:', kseftId, eventDetails, googleEventId); // Logování volání funkce
        if (!kseftId) { // Kontrola, zda je kseftId k dispozici
            console.error('Missing kseft_id'); // Logování chyby
            alert('Chyba: kseft_id není k dispozici.'); // Zobrazení chybové zprávy
            return; // Ukončení funkce
        }

        // Přidáno: kontrola, zda je end menší nebo roven start (přes noc)
        var startDate = new Date(eventDetails.start.dateTime);
        var endDate = new Date(eventDetails.end.dateTime);
        if (endDate <= startDate) {
            endDate.setDate(endDate.getDate() + 1);
            eventDetails.end.dateTime = endDate.toISOString().split('.')[0] + "Z";
        }

        var action = googleEventId ? 'update_google_calendar_event' : 'add_google_calendar_event'; // Určení akce (přidání nebo aktualizace události)
        var ajaxUrl = googleEventId ? myTeamPlugin.ajax_url : myTeamPlugin.rest_url; // Určení URL pro AJAX požadavek

        console.log('Sending AJAX request to ' + (googleEventId ? 'update' : 'add') + ' Google Calendar event:', eventDetails); // Logování odesílání AJAX požadavku

        var data = {
            action: action, // Akce (přidání nebo aktualizace události)
            kseft_id: kseftId, // ID kšeftu
            event_details: eventDetails, // Detaily události
            nonce: myTeamPlugin.nonce
        };

        if (googleEventId) {
            data.event_id = googleEventId; // Přidání ID události, pokud existuje
        }

        $.ajax({
            url: ajaxUrl, // URL pro AJAX požadavek
            method: 'POST', // Metoda požadavku
            beforeSend: function(xhr) {
                if (!googleEventId) {
                    xhr.setRequestHeader('X-WP-Nonce', myTeamPlugin.nonce); // Nastavení nonce pro REST API
                }
            },
            data: JSON.stringify(data), // Data pro požadavek
            contentType: 'application/json', // Typ obsahu
            success: function(response) {
                if (response.success) {
                    console.log('Google Calendar event ' + (googleEventId ? 'updated' : 'added') + ' successfully:', response.event_id); // Logování úspěchu
                    alert('Událost byla úspěšně ' + (googleEventId ? 'aktualizována' : 'přidána') + ' do Google Kalendáře.'); // Zobrazení úspěšné zprávy

                    if (!googleEventId) {
                        // Uložení Google Calendar event ID ke kartě kšeftu
                        $.post(myTeamPlugin.ajax_url, {
                            action: 'save_google_event_id', // Akce pro uložení ID události
                            kseft_id: kseftId, // ID kšeftu
                            google_event_id: response.event_id // ID události
                        }).done(function(saveResponse) {
                            if (saveResponse && saveResponse.success) {
                                console.log('Google Calendar event ID saved successfully.'); // Logování úspěchu uložení ID události
                            } else {
                                var errorMessage = saveResponse && saveResponse.data ? saveResponse.data : 'Unknown error'; // Získání chybové zprávy
                            }
                        }).fail(function(xhr, status, error) {
                            console.error('AJAX error:', status, error); // Logování chyby AJAX požadavku
                            console.error('AJAX response:', xhr.responseText); // Logování odpovědi AJAX požadavku
                            alert('Chyba při komunikaci se serverem při ukládání Google Calendar event ID.'); // Zobrazení chybové zprávy
                        });
                    }
                } else {
                    console.error('Error ' + (googleEventId ? 'updating' : 'adding') + ' event to Google Calendar:', response.error); // Logování chyby přidání nebo aktualizace události
                    alert('Chyba při ' + (googleEventId ? 'aktualizaci' : 'přidávání') + ' události do Google Kalendáře.'); // Zobrazení chybové zprávy
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error); // Logování chyby AJAX požadavku
                console.error('Response:', xhr.responseText); // Logování odpovědi AJAX požadavku
                // alert('Akce nebyla upravena na google kalendáři update-GC.JS.'); // Zobrazení chybové zprávy
            }
        });
    }

    function decodeHtmlEntities(text) {
        var textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // Nová pomocná funkce pro formátování datumu s offsetem pro Europe/Prague
    function formatDateTimeWithOffset(dateObj, timeZone) {
        // Použijeme Intl.DateTimeFormat k získání částí formátu
        const formatter = new Intl.DateTimeFormat('sv-SE', {
            timeZone: timeZone,
            hour12: false,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const parts = formatter.formatToParts(dateObj);
        let year, month, day, hour, minute, second;
        parts.forEach(part => {
            switch(part.type) {
                case 'year': year = part.value; break;
                case 'month': month = part.value; break;
                case 'day': day = part.value; break;
                case 'hour': hour = part.value; break;
                case 'minute': minute = part.value; break;
                case 'second': second = part.value; break;
            }
        });
        // Jednoduchá logika – předpokládáme, že měsíc mezi dubnem a říjnem je letní čas (+02:00)
        const monthInt = parseInt(month, 10);
        const offset = (monthInt >= 4 && monthInt <= 10) ? '+02:00' : '+01:00';
        return `${year}-${month}-${day}T${hour}:${minute}:${second}${offset}`;
    }

    function addToCalendarButtonHandler() {
        console.log('Add to Calendar button clicked'); // Logování kliknutí
        var kseftId = $('#kseft_id').val(); // Získání ID kšeftu
        if (!kseftId) { // Kontrola, zda je kseftId k dispozici
            console.error('Missing kseft_id');
            alert('Chyba: kseft_id není k dispozici.');
            return;
        }
        $.post(myTeamPlugin.ajax_url, {
            action: 'get_event_details', // Akce pro získání detailů události
            kseft_id: kseftId
        }, function(response) {
            if (response.success) {
                var eventDate = response.data.event_date; // Datum události
                var startTime = response.data.performance_start; // Začátek vystoupení
                var endTime = response.data.performance_end; // Konec vystoupení
                var kseftName = response.data.kseft_name; // Název kšeftu
                var kseftLocation = response.data.kseft_location; // Lokace kšeftu
                var kseftDescription = response.data.kseft_description; // Popis události
                var eventDetails = {
                    summary: decodeHtmlEntities(kseftName),
                    location: decodeHtmlEntities(kseftLocation),
                    description: kseftDescription,
                    start: {},
                    end: {}
                };

                if (startTime && endTime) {
                    var startLocal = new Date(eventDate + 'T' + startTime + ':00');
                    var endLocal = new Date(eventDate + 'T' + endTime + ':00');
                    // Použijeme funkci, která vrátí řetězec v ISO formátu s offsetem.
                    eventDetails.start.dateTime = formatDateTimeWithOffset(startLocal, 'Europe/Prague');
                    eventDetails.end.dateTime = formatDateTimeWithOffset(endLocal, 'Europe/Prague');
                    // Vynecháváme explicitní nastavení timeZone
                } else {
                    eventDetails.start.date = eventDate;
                    eventDetails.end.date = eventDate;
                }

                console.log('JS Event details:', eventDetails);
                var googleEventId = response.data.google_event_id || null;
                handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId);
            } else {
                console.error('Error fetching event details:', response.data);
                alert('Chyba při získávání detailů události.');
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('Response:', xhr.responseText);
            alert('Chyba při komunikaci se serverem add - GC.JS.');
        });
    }

    function manageKseftFormHandler(e) {
        e.preventDefault(); // Zabránění výchozímu odeslání formuláře
        console.log('Manage Kseft form submitted');
        var kseftId = $('input[name="kseft_id"]').val(); // ID kšeftu
        var kseftName = $('input[name="kseft_name"]').val();
        var kseftLocation = $('input[name="kseft_location"]').val();
        var kseftMeetingTime = $('input[name="kseft_meeting_time"]').val();
        var kseftEventDate = $('input[name="kseft_event_date"]').val(); // Datum akce
        var kseftStartTime = $('input[name="kseft_performance_start"]').val(); // Začátek vystoupení
        var kseftEndTime = $('input[name="kseft_performance_end"]').val(); // Konec vystoupení
        var kseftStatus = $('select[name="kseft_status"]').val();
        var kseftDescription = $('textarea[name="kseft_description"]').val();

        if (!kseftId) {
            console.error('Missing kseft_id');
            alert('Chyba: kseft_id není k dispozici.');
            return;
        }

        var eventDetails = {
            summary: decodeHtmlEntities(kseftName),
            location: decodeHtmlEntities(kseftLocation),
            description: decodeHtmlEntities(kseftDescription),
            start: {},
            end: {}
        };

        if (kseftStartTime && kseftEndTime) {
            var startLocal = new Date(kseftEventDate + 'T' + kseftStartTime + ':00');
            var endLocal = new Date(kseftEventDate + 'T' + kseftEndTime + ':00');
            eventDetails.start.dateTime = formatDateTimeWithOffset(startLocal, 'Europe/Prague');
            eventDetails.end.dateTime = formatDateTimeWithOffset(endLocal, 'Europe/Prague');
        } else {
            eventDetails.start.date = kseftEventDate;
            eventDetails.end.date = kseftEventDate;
        }

        var googleEventId = $('input[name="google_calendar_event_id"]').val(); // ID události v Google Kalendáři
        if (googleEventId) {
            handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId);
        }

        this.submit(); // Odeslání formuláře
    }

    $('#add-to-calendar-button').on('click', addToCalendarButtonHandler); // Přidání obsluhy kliknutí na tlačítko
    $('#manage-kseft-form').on('submit', manageKseftFormHandler); // Přidání obsluhy odeslání formuláře
});

