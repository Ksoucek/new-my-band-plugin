jQuery(document).ready(function($) {
    console.log('google-calendar.js file loaded'); // Logování načtení souboru

    function handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId) {
        console.log('handleGoogleCalendarEvent called with:', kseftId, eventDetails, googleEventId); // Logování volání funkce
        if (!kseftId) { // Kontrola, zda je kseftId k dispozici
            console.error('Missing kseft_id'); // Logování chyby
            alert('Chyba: kseft_id není k dispozici.'); // Zobrazení chybové zprávy
            return; // Ukončení funkce
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
                            if (saveResponse.success) {
                                console.log('Google Calendar event ID saved successfully.'); // Logování úspěchu uložení ID události
                            } else {
                                console.error('Error saving Google Calendar event ID:', saveResponse.error); // Logování chyby uložení ID události
                            }
                        }).fail(function(xhr, status, error) {
                            console.error('AJAX error:', status, error); // Logování chyby AJAX požadavku
                            console.error('AJAX response:', xhr.responseText); // Logování odpovědi AJAX požadavku
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
                alert('Akce nebyla upravena na google kalendáři update-GC.JS.'); // Zobrazení chybové zprávy
            }
        });
    }

    function addToCalendarButtonHandler() {
        console.log('Add to Calendar button clicked'); // Logování kliknutí na tlačítko
        var kseftId = $('#kseft_id').val(); // Získání ID kšeftu

        if (!kseftId) { // Kontrola, zda je kseftId k dispozici
            console.error('Missing kseft_id'); // Logování chyby
            alert('Chyba: kseft_id není k dispozici.'); // Zobrazení chybové zprávy
            return; // Ukončení funkce
        }

        $.post(myTeamPlugin.ajax_url, {
            action: 'get_event_details', // Akce pro získání detailů události
            kseft_id: kseftId // ID kšeftu
        }, function(response) {
            if (response.success) {
                var eventDate = response.data.event_date; // Datum události
                var meetingTime = response.data.meeting_time; // Čas srazu
                var kseftName = response.data.kseft_name; // Název kšeftu
                var kseftLocation = response.data.kseft_location; // Lokace kšeftu
                var kseft_duration = response.data.kseft_duration || 2; // Předpokládaná délka v hodinách, výchozí hodnota je 2 hodiny
                var kseftDescription = response.data.kseft_description; // Popis kšeftu

                var eventDetails = {
                    summary: kseftName, // Název události
                    location: kseftLocation, // Lokace události
                    description: kseftDescription, // Popis události
                    start: {}, // Začátek události
                    end: {} // Konec události
                };

                if (meetingTime) {
                    // Formátování datumu a času
                    var startDateTime = new Date(eventDate + 'T' + meetingTime + ':00').toISOString(); // Začátek události
                    var endDateTime = new Date(new Date(eventDate + 'T' + meetingTime + ':00').getTime() + kseft_duration * 60 * 60 * 1000).toISOString(); // Konec události

                    eventDetails.start.dateTime = startDateTime; // Nastavení začátku události
                    eventDetails.end.dateTime = endDateTime; // Nastavení konce události
                    eventDetails.start.timeZone = 'Europe/Prague'; // Nastavení časové zóny
                    eventDetails.end.timeZone = 'Europe/Prague'; // Nastavení časové zóny
                } else {
                    // Nastavení akce jako celodenní
                    eventDetails.start.date = eventDate; // Datum začátku události
                    eventDetails.end.date = eventDate; // Datum konce události
                }

                console.log('JS Event details:', eventDetails); // Logování detailů události

                var googleEventId = response.data.google_event_id || null; // ID události v Google Kalendáři
                handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId); // Volání funkce pro přidání nebo aktualizaci události
            } else {
                console.error('Error fetching event details:', response.data); // Logování chyby získání detailů události
                alert('Chyba při získávání detailů události.'); // Zobrazení chybové zprávy
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error); // Logování chyby AJAX požadavku
            console.error('Response:', xhr.responseText); // Logování odpovědi AJAX požadavku
            alert('Chyba při komunikaci se serverem add - GC.JS.'); // Zobrazení chybové zprávy
        });
    }

    function manageKseftFormHandler(e) {
        e.preventDefault(); // Zabránění výchozímu chování formuláře
        console.log('Manage Kseft form submitted'); // Logování odeslání formuláře

        var kseftId = $('input[name="kseft_id"]').val(); // Získání ID kšeftu
        var kseftName = $('input[name="kseft_name"]').val(); // Získání názvu kšeftu
        var kseftLocation = $('input[name="kseft_location"]').val(); // Získání lokace kšeftu
        var kseftMeetingTime = $('input[name="kseft_meeting_time"]').val(); // Získání času srazu
        var kseftEventDate = $('input[name="kseft_event_date"]').val(); // Získání data kšeftu
        var kseftDuration = $('input[name="kseft_duration"]').val(); // Získání délky kšeftu
        var kseftStatus = $('select[name="kseft_status"]').val(); // Získání stavu kšeftu
        var kseftDescription = $('textarea[name="kseft_description"]').val(); // Získání popisu kšeftu

        if (!kseftId) { // Kontrola, zda je kseftId k dispozici
            console.error('Missing kseft_id'); // Logování chyby
            alert('Chyba: kseft_id není k dispozici.'); // Zobrazení chybové zprávy
            return; // Ukončení funkce
        }

        var startTime = kseftMeetingTime ? kseftEventDate + 'T' + kseftMeetingTime + ':00' : kseftEventDate + 'T00:00:00'; // Začátek události
        var endTime = kseftMeetingTime ? new Date(new Date(startTime).getTime() + (kseftDuration ? kseftDuration : 24) * 3600 * 1000).toISOString() : kseftEventDate + 'T23:59:59'; // Konec události

        if (isNaN(Date.parse(startTime)) || isNaN(Date.parse(endTime))) { // Kontrola platnosti datumu a času
            endTime = kseftEventDate + 'T23:59:59'; // Nastavení konce události na konec dne
        }

        var eventDetails = {
            summary: kseftName, // Název události
            location: kseftLocation, // Lokace události
            description: kseftDescription, // Popis události
            start: startTime, // Začátek události
            end: endTime // Konec události
        };

        var googleEventId = $('input[name="google_calendar_event_id"]').val(); // Získání ID události v Google Kalendáři
        if (googleEventId) {
            handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId); // Volání funkce pro aktualizaci události
        }

        this.submit(); // Odeslání formuláře
    }

    $('#add-to-calendar-button').on('click', addToCalendarButtonHandler); // Přidání obsluhy kliknutí na tlačítko
    $('#manage-kseft-form').on('submit', manageKseftFormHandler); // Přidání obsluhy odeslání formuláře
});

