<?php

// Add REST API endpoint for adding event to Google Calendar
add_action('rest_api_init', function () {
    register_rest_route('transport-ai/v1', '/add-to-calendar', [
        'methods'  => 'POST',
        'callback' => 'handle_add_to_calendar_request',
        'permission_callback' => 'my_team_plugin_check_authorization', // Check authorization header
    ]);
});

// ...existing code...
?>
