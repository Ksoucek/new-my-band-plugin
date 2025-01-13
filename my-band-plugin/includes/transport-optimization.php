<?php

// Existing transport calculation function
function calculate_transport($pickup_location, $destination) {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key');
    if (!$api_key) {
        return 'Auto'; // Default value if API key is not set
    }

    $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $api_key . '&start=' . urlencode($pickup_location) . '&end=' . urlencode($destination);

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return 'Auto'; // Default value in case of an error
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['routes'][0]['summary']['distance'])) {
        $distance = $data['routes'][0]['summary']['distance']; // Distance in meters
        if ($distance < 5000) {
            return 'Chůze';
        } elseif ($distance < 20000) {
            return 'Kolo';
        } else {
            return 'Auto';
        }
    }

    return 'Auto'; // Default value
}

// New function: Optimize route using AI
function optimize_route_with_ai($locations, $arrival_time) {
    $api_key = get_option('my_team_plugin_openai_api_key'); // Fetch OpenAI API key
    if (!$api_key) {
        return [
            'error' => 'API key for OpenAI is not set.'
        ];
    }

    // Prepare input for AI
    $prompt = "Plan the shortest route and schedule for the following stops: " . implode(", ", $locations) . ". Ensure arrival at the final destination by $arrival_time.";

    $response = wp_remote_post('https://api.openai.com/v1/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'text-davinci-003',
            'prompt' => $prompt,
            'max_tokens' => 200,
        ]),
    ]);

    if (is_wp_error($response)) {
        return [
            'error' => 'Error communicating with OpenAI API.',
            'details' => $response->get_error_message()
        ];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['choices'][0]['text'])) {
        // Předpokládáme, že odpověď obsahuje časy vyzvednutí ve formátu JSON
        $pickup_times = json_decode($data['choices'][0]['text'], true);
        return [
            'route' => $data['choices'][0]['text'],
            'pickup_times' => $pickup_times,
            'raw_response' => $data
        ];
    }

    return [
        'error' => 'Failed to generate a response from OpenAI.',
        'raw_response' => $data
    ];
}

// Add REST API endpoint for route optimization
add_action('rest_api_init', function () {
    register_rest_route('transport-ai/v1', '/optimize', [
        'methods'  => 'POST',
        'callback' => 'handle_optimize_route_request',
        'permission_callback' => '__return_true', // Modify this for proper permission handling
    ]);
});

function handle_optimize_route_request(WP_REST_Request $request) {
    $locations = $request->get_param('locations');
    $arrival_time = $request->get_param('arrival_time');

    if (!$locations || !$arrival_time) {
        return new WP_REST_Response(['error' => 'Missing parameters: locations or arrival_time'], 400);
    }

    $result = optimize_route_with_ai($locations, $arrival_time);

    if (isset($result['error'])) {
        return new WP_REST_Response(['error' => $result['error']], 500);
    }

    return new WP_REST_Response(['route' => $result['route'], 'pickup_times' => $result['pickup_times']], 200);
}

?>
