<?php

require_once dirname(__DIR__, 5) . '/vendor/autoload.php'; // Ujistěte se, že cesta je správná

// Přidání REST API endpointu pro přidání události do Google Kalendáře
add_action('rest_api_init', function () {
    error_log_with_timestamp('Registering REST API endpoint: /add-to-calendar');
    register_rest_route('google-calendar/v1', '/add-to-calendar', [
        'methods'  => 'POST',
        'callback' => 'handle_add_to_calendar_request',
        'permission_callback' => '__return_true', // Změňte podle potřeby
    ]);
});

// Přidání REST API endpointu pro získání poslední chyby z logu
add_action('rest_api_init', function () {
    error_log_with_timestamp('Registering REST API endpoint: /get-last-error');
    register_rest_route('google-calendar/v1', '/get-last-error', [
        'methods'  => 'GET',
        'callback' => 'handle_get_last_error_request',
        'permission_callback' => '__return_true', // Změňte podle potřeby
    ]);
});

// Přidání funkce pro generování JSON objektu pro vytvoření události
function generate_event_json($summary, $start_time, $end_time, $location) {
    $event_details = [
        'summary' => $summary,
        'start' => [
            'dateTime' => date('c', strtotime($start_time)),
        ],
        'end' => [
            'dateTime' => date('c', strtotime($end_time)),
        ],
        'location' => $location
    ];

    return json_encode(['event_details' => $event_details]);
}

function handle_add_to_calendar_request(WP_REST_Request $request) {
    error_log_with_timestamp('handle_add_to_calendar_request called');
    $event_details = $request->get_param('event_details');

    if (!$event_details) {
        error_log_with_timestamp('Missing parameter: event_details');
        return new WP_REST_Response(['error' => 'Missing parameter: event_details'], 400);
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

    error_log_with_timestamp('Event details received: ' . json_encode($event_details));

    if (empty($event_details['summary']) || empty($event_details['start']) || empty($event_details['location'])) {
        error_log_with_timestamp('Invalid event details: ' . json_encode($event_details));
        return new WP_REST_Response(['error' => 'Invalid event details. Please provide summary, start dateTime, and location.'], 400);
    }

    $result = add_event_to_google_calendar($event_details);

    if (isset($result['error'])) {
        error_log_with_timestamp('Error response: ' . $result['error']);
        return new WP_REST_Response(['error' => $result['error']], 500);
    }

    // Generování odkazu na Google Kalendářovou akci
    $event_link = generate_google_calendar_event_link($result['event_id']);

    return new WP_REST_Response(['success' => true, 'event_id' => $result['event_id'], 'event_link' => $event_link], 200);
}

// Přidání funkce pro přidání události do Google Kalendáře
function add_event_to_google_calendar($event_details) {
    error_log_with_timestamp('add_event_to_google_calendar called');
    $credentials_path = plugin_dir_path(__FILE__) . 'credential.json'; // Ujistěte se, že cesta je správná
    if (!file_exists($credentials_path)) {
        error_log_with_timestamp('Credentials file not found: ' . $credentials_path);
        return [
            'error' => 'Credentials file not found.'
        ];
    }

    error_log_with_timestamp('Credentials file found: ' . $credentials_path);

    $client = new Google_Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google_Service_Calendar::CALENDAR);

    $service = new Google_Service_Calendar($client);

    $event = new Google_Service_Calendar_Event($event_details);

    try {
        $calendarId = 'olo0v28necdv27n6mg7psud2dc@group.calendar.google.com';
        $event = $service->events->insert($calendarId, $event);
        error_log_with_timestamp('Event added to Google Calendar: ' . json_encode($event));
        return [
            'success' => true,
            'event_id' => $event->getId()
        ];
    } catch (Exception $e) {
        error_log_with_timestamp('Error adding event to Google Calendar: ' . $e->getMessage());
        return [
            'error' => 'Error adding event to Google Calendar.',
            'details' => $e->getMessage()
        ];
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

// Přidání funkce pro logování s časovou značkou
function error_log_with_timestamp($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    $log_file = plugin_dir_path(__FILE__) . '../../custom_error_log.log';

    // Zajistíme, že adresář existuje
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // Zajistíme, že soubor existuje
    if (!file_exists($log_file)) {
        file_put_contents($log_file, '');
    }

    error_log($log_message, 3, $log_file);
}

?>
