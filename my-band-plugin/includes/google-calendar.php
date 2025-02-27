<?php

require_once dirname(__DIR__, 5) . '/vendor/autoload.php'; // Ujistěte se, že cesta je správná

// Přidání REST API endpointu pro přidání události do Google Kalendáře
add_action('rest_api_init', function () {
    register_rest_route('google-calendar/v1', '/add-to-calendar', [
        'methods'  => 'POST',
        'callback' => 'handle_add_to_calendar_request',
        'permission_callback' => '__return_true', // Změňte podle potřeby
    ]);
});

// Přidání REST API endpointu pro získání poslední chyby z logu
add_action('rest_api_init', function () {
    register_rest_route('google-calendar/v1', '/get-last-error', [
        'methods'  => 'GET',
        'callback' => 'handle_get_last_error_request',
        'permission_callback' => '__return_true', // Změňte podle potřeby
    ]);
});

// Přidání funkce pro generování JSON objektu pro vytvoření události
function generate_event_json($summary, $start_time, $end_time, $location, $description = '') {
    $event_details = [
        'summary' => $summary,
        'start' => [
            'dateTime' => date('c', strtotime($start_time)),
        ],
        'end' => [
            'dateTime' => date('c', strtotime($end_time)),
        ],
        'location' => $location,
        'description' => $description // Přidání popisu
    ];

    return json_encode(['event_details' => $event_details]);
}

function handle_add_to_calendar_request(WP_REST_Request $request) {
    $event_details = $request->get_param('event_details');
    $kseft_id = $request->get_param('kseft_id'); // Přidání kseft_id

    if (!$event_details) {
        return new WP_REST_Response(['error' => 'Missing parameter: event_details'], 400);
    }

    if (!$kseft_id) {
        return new WP_REST_Response(['error' => 'Missing parameter: kseft_id'], 400);
    }

    if (isset($event_details['start']['dateTime']) && isset($event_details['end']['dateTime'])) {
        // Formátování datumu a času
        $start_time = $event_details['start']['dateTime'];
        $end_time = $event_details['end']['dateTime'];

        $event_details['start']['dateTime'] = date('c', strtotime($start_time));
        $event_details['end']['dateTime'] = date('c', strtotime($end_time));
    } else {
        // Nastavení akce jako celodenní
        $event_details['start']['date'] = $event_details['start']['date'];
        $event_details['end']['date'] = $event_details['end']['date'];
    }

    if (empty($event_details['summary']) || empty($event_details['start']) || empty($event_details['location'])) {
        return new WP_REST_Response(['error' => 'Invalid event details. Please provide summary, start dateTime, and location.'], 400);
    }

    // Nastavení výchozí hodnoty pro description, pokud není definováno
    if (!isset($event_details['description'])) {
        $event_details['description'] = '';
    }

    // Logování pro ověření pole description
    error_log('Event Description: ' . $event_details['description']);

    $result = add_event_to_google_calendar($event_details);

    if (isset($result['error'])) {
        return new WP_REST_Response(['error' => $result['error']], 500);
    }

    // Uložení ID události Google Kalendáře do metadat příspěvku
    update_post_meta($kseft_id, 'google_calendar_event_id', $result['event_id']);

    // Generování odkazu na Google Kalendářovou akci
    $event_link = generate_google_calendar_event_link($result['event_id']);

    return new WP_REST_Response(['success' => true, 'event_id' => $result['event_id'], 'event_link' => $event_link], 200);
}

// Přidání funkce pro přidání události do Google Kalendáře
function add_event_to_google_calendar($event_details) {
    $credentials_path = plugin_dir_path(__FILE__) . 'credential.json'; // Ujistěte se, že cesta je správná
    if (!file_exists($credentials_path)) {
        return [
            'error' => 'Credentials file not found.'
        ];
    }
    
    $client = new Google_Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google_Service_Calendar::CALENDAR);

    $service = new Google_Service_Calendar($client);

    $event = new Google_Service_Calendar_Event($event_details);
    $event->setDescription($event_details['description']); // Přidání popisu

    try {
        $calendarId = 'olo0v28necdv27n6mg7psud2dc@group.calendar.google.com';
        $event = $service->events->insert($calendarId, $event);
        return [
            'success' => true,
            'event_id' => $event->getId()
        ];
    } catch (Exception $e) {
        return [
            'error' => 'Error adding event to Google Calendar.',
            'details' => $e->getMessage()
        ];
    }
}

// Přidání funkce pro aktualizaci Google akce
function updateGoogleCalendar($event_id, $details) {
    $credentials_path = plugin_dir_path(__FILE__) . 'credential.json'; // Ujistěte se, že cesta je správná
    if (!file_exists($credentials_path)) {
        return false;
    }

    $client = new Google_Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google_Service_Calendar::CALENDAR);

    $service = new Google_Service_Calendar($client);

    try {
        $calendarId = 'olo0v28necdv27n6mg7psud2dc@group.calendar.google.com'; // Použití kalendář ID z jiného souboru
        $event = $service->events->get($calendarId, $event_id);
        $event->setSummary($details['summary']);
        $event->setLocation($details['location']);
        $event->setDescription($details['description']); // Přidání popisu
        $event->setStart(new Google_Service_Calendar_EventDateTime(array('dateTime' => $details['start']['dateTime'], 'timeZone' => 'Europe/Prague')));
        $event->setEnd(new Google_Service_Calendar_EventDateTime(array('dateTime' => $details['end']['dateTime'], 'timeZone' => 'Europe/Prague')));
        $updatedEvent = $service->events->update($calendarId, $event->getId(), $event);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Přidání funkce pro generování odkazu na Google Kalendářovou akci
function generate_google_calendar_event_link($event_id) {
    $calendar_id = 'olo0v28necdv27n6mg7psud2dc@group.calendar.google.com';
    return 'https://calendar.google.com/calendar/event?eid=' . $event_id . '&ctz=Europe/Prague&calendarId=' . $calendar_id;
}

// Přidání funkce pro získání poslední chyby z logu
function get_last_error_from_log() {
    $log_file = plugin_dir_path(__FILE__) . '../../custom_error_log.log';
    if (!file_exists($log_file)) {
        return 'Log file not found.';
    }

    $lines = file($log_file);
    if (empty($lines)) {
        return 'Log file is empty.';
    }

    return trim(end($lines));
}

function handle_get_last_error_request() {
    $last_error = get_last_error_from_log();
    return new WP_REST_Response(['last_error' => $last_error], 200);
}

// Přidání funkce pro uložení Google Calendar event ID ke kartě kšeftu
function save_google_event_id() {
    $kseft_id = intval($_POST['kseft_id']);
    $google_event_id = sanitize_text_field($_POST['google_event_id']);

    if (update_post_meta($kseft_id, 'google_calendar_event_id', $google_event_id)) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['error' => 'Failed to save Google Calendar event ID.']);
    }
}
add_action('wp_ajax_save_google_event_id', 'save_google_event_id');
add_action('wp_ajax_nopriv_save_google_event_id', 'save_google_event_id');

// Přidání funkce pro vytvoření Google akce
function createGoogleCalendarEvent($kseftId, $eventDetails) {
    $credentials_path = plugin_dir_path(__FILE__) . 'credential.json'; // Ujistěte se, že cesta je správná
    if (!file_exists($credentials_path)) {
        return false;
    }

    $client = new Google_Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google_Service_Calendar::CALENDAR);

    $service = new Google_Service_Calendar($client);

    $event = new Google_Service_Calendar_Event($eventDetails);
    $event->setDescription($eventDetails['description']); // Přidání popisu

    try {
        $calendarId = 'primary';
        $event = $service->events->insert($calendarId, $event);
        update_post_meta($kseftId, 'google_calendar_event_id', $event->id); // Uložení ID události do meta dat příspěvku
        return $event->id;
    } catch (Exception $e) {
        return false;
    }
}

?>
