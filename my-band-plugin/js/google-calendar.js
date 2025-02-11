jQuery(document).ready(function($) {
    console.log('google-calendar.js file loaded');

    function handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId) {
        console.log('handleGoogleCalendarEvent called with:', kseftId, eventDetails, googleEventId);
        if (!kseftId) {
            console.error('Missing kseft_id');
            alert('Chyba: kseft_id není k dispozici.');
            return;
        }

        var action = googleEventId ? 'update_google_calendar_event' : 'add_google_calendar_event';
        var ajaxUrl = googleEventId ? myTeamPlugin.ajax_url : myTeamPlugin.rest_url;

        console.log('Sending AJAX request to ' + (googleEventId ? 'update' : 'add') + ' Google Calendar event:', eventDetails);

        var data = {
            action: action,
            kseft_id: kseftId,
            event_details: eventDetails
        };

        if (googleEventId) {
            data.event_id = googleEventId;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                if (!googleEventId) {
                    xhr.setRequestHeader('X-WP-Nonce', myTeamPlugin.nonce);
                }
            },
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    console.log('Google Calendar event ' + (googleEventId ? 'updated' : 'added') + ' successfully:', response.event_id);
                    alert('Událost byla úspěšně ' + (googleEventId ? 'aktualizována' : 'přidána') + ' do Google Kalendáře.');

                    if (!googleEventId) {
                        // Uložení Google Calendar event ID ke kartě kšeftu
                        $.post(myTeamPlugin.ajax_url, {
                            action: 'save_google_event_id',
                            kseft_id: kseftId,
                            google_event_id: response.event_id
                        }).done(function(saveResponse) {
                            if (saveResponse.success) {
                                console.log('Google Calendar event ID saved successfully.');
                            } else {
                                console.error('Error saving Google Calendar event ID:', saveResponse.error);
                            }
                        }).fail(function(xhr, status, error) {
                            console.error('AJAX error:', status, error);
                            console.error('AJAX response:', xhr.responseText);
                        });
                    }
                } else {
                    console.error('Error ' + (googleEventId ? 'updating' : 'adding') + ' event to Google Calendar:', response.error);
                    alert('Chyba při ' + (googleEventId ? 'aktualizaci' : 'přidávání') + ' události do Google Kalendáře.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                console.error('Response:', xhr.responseText);
                alert('Chyba při komunikaci se serverem.');
            }
        });
    }

    function addToCalendarButtonHandler() {
        console.log('Add to Calendar button clicked');
        var kseftId = $('#kseft_id').val(); // Oprava pro získání správného kseft_id

        if (!kseftId) {
            console.error('Missing kseft_id');
            alert('Chyba: kseft_id není k dispozici.');
            return;
        }

        $.post(myTeamPlugin.ajax_url, {
            action: 'get_event_details',
            kseft_id: kseftId
        }, function(response) {
            if (response.success) {
                var eventDate = response.data.event_date;
                var meetingTime = response.data.meeting_time;
                var kseftName = response.data.kseft_name;
                var kseftLocation = response.data.kseft_location;
                var kseft_duration = response.data.kseft_duration || 2; // Předpokládaná délka v hodinách, výchozí hodnota je 2 hodiny
                var kseftDescription = response.data.kseft_description; // Přidání popisu

                var eventDetails = {
                    summary: kseftName,
                    location: kseftLocation,
                    description: kseftDescription, // Přidání popisu do detailů události
                    start: {},
                    end: {}
                };

                if (meetingTime) {
                    // Formátování datumu a času
                    var startDateTime = new Date(eventDate + 'T' + meetingTime + ':00').toISOString();
                    var endDateTime = new Date(new Date(eventDate + 'T' + meetingTime + ':00').getTime() + kseft_duration * 60 * 60 * 1000).toISOString();

                    eventDetails.start.dateTime = startDateTime;
                    eventDetails.end.dateTime = endDateTime;
                    eventDetails.start.timeZone = 'Europe/Prague';
                    eventDetails.end.timeZone = 'Europe/Prague';
                } else {
                    // Nastavení akce jako celodenní
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
        });
    }

    function manageKseftFormHandler(e) {
        e.preventDefault();
        console.log('Manage Kseft form submitted');

        var kseftId = $('input[name="kseft_id"]').val();
        var kseftName = $('input[name="kseft_name"]').val();
        var kseftLocation = $('input[name="kseft_location"]').val();
        var kseftMeetingTime = $('input[name="kseft_meeting_time"]').val();
        var kseftEventDate = $('input[name="kseft_event_date"]').val();
        var kseftDuration = $('input[name="kseft_duration"]').val();
        var kseftStatus = $('select[name="kseft_status"]').val();
        var kseftDescription = $('textarea[name="kseft_description"]').val(); // Přidání popisu

        if (!kseftId) {
            console.error('Missing kseft_id');
            alert('Chyba: kseft_id není k dispozici.');
            return;
        }

        var startTime = kseftMeetingTime ? kseftEventDate + 'T' + kseftMeetingTime + ':00' : kseftEventDate + 'T00:00:00';
        var endTime = kseftMeetingTime ? new Date(new Date(startTime).getTime() + (kseftDuration ? kseftDuration : 24) * 3600 * 1000).toISOString() : kseftEventDate + 'T23:59:59';

        if (isNaN(Date.parse(startTime)) || isNaN(Date.parse(endTime))) {
            endTime = kseftEventDate + 'T23:59:59';
        }

        var eventDetails = {
            summary: kseftName,
            location: kseftLocation,
            description: kseftDescription, // Přidání popisu do detailů události
            start: startTime,
            end: endTime
        };

        var googleEventId = $('input[name="google_calendar_event_id"]').val();
        handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId);

        this.submit();
    }

    $('#add-to-calendar-button').on('click', addToCalendarButtonHandler);
    $('#manage-kseft-form').on('submit', manageKseftFormHandler);
});

