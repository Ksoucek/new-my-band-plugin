<?php

function calculate_transport($pickup_location, $destination) {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key');
    if (!$api_key) {
        return 'Auto'; // Defaultní hodnota v případě, že není nastaven API klíč
    }

    $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $api_key . '&start=' . urlencode($pickup_location) . '&end=' . urlencode($destination);

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return 'Auto'; // Defaultní hodnota v případě chyby
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['routes'][0]['summary']['distance'])) {
        $distance = $data['routes'][0]['summary']['distance']; // Vzdálenost v metrech
        if ($distance < 5000) {
            return 'Chůze';
        } elseif ($distance < 20000) {
            return 'Kolo';
        } else {
            return 'Auto';
        }
    }

    return 'Auto'; // Defaultní hodnota v případě, že není dostupná vzdálenost
}

function optimize_transport($locations) {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key');
    $url = 'https://api.openrouteservice.org/v2/matrix/driving-car';

    $body = json_encode(array(
        'locations' => $locations,
        'metrics' => ['distance', 'duration'],
        'units' => 'km'
    ));

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => $body
    ));

    if (is_wp_error($response)) {
        return $response->get_error_message();
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
}

function my_team_plugin_optimize_transport() {
    $locations = isset($_POST['locations']) ? $_POST['locations'] : array();
    $locations = array_map(function($location) {
        return array_map('floatval', $location);
    }, $locations);
    $result = optimize_transport($locations);
    echo json_encode($result);
    wp_die();
}

add_action('wp_ajax_optimize_transport', 'my_team_plugin_optimize_transport');
add_action('wp_ajax_nopriv_optimize_transport', 'my_team_plugin_optimize_transport');

function my_team_plugin_test_api() {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key');
    $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $api_key . '&start=Seattle,WA&end=San+Francisco,CA';

    error_log('Testing API with URL: ' . $url);

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        error_log('API request failed: ' . $response->get_error_message());
        wp_send_json_error('API request failed');
        wp_die();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    error_log('API response: ' . print_r($data, true));

    wp_send_json_success($data);
    wp_die();
}
add_action('wp_ajax_test_api', 'my_team_plugin_test_api');
add_action('wp_ajax_nopriv_test_api', 'my_team_plugin_test_api');

function get_coordinates_from_address($address) {
    $api_key = get_option('my_team_plugin_google_maps_api_key');
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $api_key;

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['results'][0]['geometry']['location'])) {
        $location = $data['results'][0]['geometry']['location'];
        return [$location['lat'], $location['lng']];
    }

    return false;
}

function my_team_plugin_get_coordinates() {
    $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
    $coordinates = get_coordinates_from_address($address);
    if ($coordinates) {
        wp_send_json_success($coordinates);
    } else {
        wp_send_json_error('Unable to get coordinates');
    }
    wp_die();
}
add_action('wp_ajax_get_coordinates', 'my_team_plugin_get_coordinates');
add_action('wp_ajax_nopriv_get_coordinates', 'my_team_plugin_get_coordinates');
?>
