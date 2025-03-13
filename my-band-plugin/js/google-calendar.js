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
                // alert('Akce nebyla upravena na google kalendáři update-GC.JS.'); // Zobrazení chybové zprávy
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
                var startTime = response.data.performance_start; // Začátek vystoupení
                var endTime = response.data.performance_end; // Konec vystoupení
                var kseftName = response.data.kseft_name; // Název kšeftu
                var kseftLocation = response.data.kseft_location; // Lokace kšeftu
                var kseftDescription = response.data.kseft_description; // Popis kšeftu

                var eventDetails = {
                    summary: kseftName, // Název události
                    location: kseftLocation, // Lokace události
                    description: kseftDescription, // Popis události
                    start: {
                        dateTime: eventDate + 'T' + startTime + ':00', // Začátek události
                        timeZone: 'Europe/Prague' // Časová zóna
                    },
                    end: {
                        dateTime: eventDate + 'T' + endTime + ':00', // Konec události
                        timeZone: 'Europe/Prague' // Časová zóna
                    }
                };

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
        var kseftStartTime = $('input[name="kseft_performance_start"]').val(); // Získání začátku vystoupení
        var kseftEndTime = $('input[name="kseft_performance_end"]').val(); // Získání konce vystoupení
        var kseftStatus = $('select[name="kseft_status"]').val(); // Získání stavu kšeftu
        var kseftDescription = $('textarea[name="kseft_description"]').val(); // Získání popisu kšeftu

        if (!kseftId) { // Kontrola, zda je kseftId k dispozici
            console.error('Missing kseft_id'); // Logování chyby
            alert('Chyba: kseft_id není k dispozici.'); // Zobrazení chybové zprávy
            return; // Ukončení funkce
        }

        var eventDetails = {
            summary: kseftName, // Název události
            location: kseftLocation, // Lokace události
            description: kseftDescription, // Popis události
            start: {
                dateTime: kseftEventDate + 'T' + kseftStartTime + ':00', // Začátek události
                timeZone: 'Europe/Prague' // Časová zóna
            },
            end: {
                dateTime: kseftEventDate + 'T' + kseftEndTime + ':00', // Konec události
                timeZone: 'Europe/Prague' // Časová zóna
            }
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

