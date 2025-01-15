<?php

// Přidání REST API endpointu pro přidání události do Google Kalendáře
add_action('rest_api_init', function () {
    register_rest_route('transport-ai/v1', '/add-to-calendar', [
        'methods'  => 'POST',
        'callback' => 'handle_add_to_calendar_request',
        'permission_callback' => '__return_true', // Změňte podle potřeby
    ]);
});

function handle_add_to_calendar_request(WP_REST_Request $request) {
    $event_details = $request->get_param('event_details');

    if (!$event_details) {
        error_log_with_timestamp('Missing parameter: event_details');
        return new WP_REST_Response(['error' => 'Missing parameter: event_details'], 400);
    }

    error_log_with_timestamp('Event details received: ' . json_encode($event_details));

    if (empty($event_details['summary']) || empty($event_details['start']['dateTime']) || empty($event_details['location'])) {
        error_log_with_timestamp('Invalid event details: ' . json_encode($event_details));
        return new WP_REST_Response(['error' => 'Invalid event details'], 400);
    }

    $result = add_event_to_google_calendar($event_details);

    if (isset($result['error'])) {
        error_log_with_timestamp('Error response: ' . $result['error']);
        return new WP_REST_Response(['error' => $result['error']], 500);
    }

    return new WP_REST_Response(['success' => true, 'event_id' => $result['event_id']], 200);
}

// Přidání funkce pro přidání události do Google Kalendáře
function add_event_to_google_calendar($event_details) {
    $credentials_path = plugin_dir_path(__FILE__) . '../credential.json'; // Ujistěte se, že cesta je správná
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
        $calendarId = 'primary';
        $event = $service->events->insert($calendarId, $event);
        error_log_with_timestamp('Event added to Google Calendar: ' . $event->getId());
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
