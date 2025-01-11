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

function optimize_transport($pickup_locations, $destination) {
    error_log('Optimize transport called');
    error_log('Pickup locations: ' . print_r($pickup_locations, true));
    error_log('Destination: ' . $destination);

    $auta = get_posts(array('post_type' => 'auta', 'numberposts' => -1));
    $total_passengers = count($pickup_locations);

    foreach ($auta as $auto) {
        $seats = get_post_meta($auto->ID, 'doprava_seats', true);
        if ($seats >= $total_passengers) {
            error_log('Selected auto: ' . get_the_title($auto->ID));
            return get_the_title($auto->ID);
        }
    }

    error_log('No suitable auto found');
    return 'Auto'; // Defaultní hodnota v případě, že žádné auto nemá dostatečnou kapacitu
}

function my_team_plugin_optimize_transport() {
    error_log('my_team_plugin_optimize_transport called');
    error_log('POST parameters: ' . print_r($_POST, true));
    
    if (!isset($_POST['post_id']) || !isset($_POST['pickup_locations']) || !isset($_POST['destination'])) {
        error_log('Missing required POST parameters');
        wp_send_json_error('Missing required POST parameters');
        wp_die();
    }

    $post_id = intval($_POST['post_id']);
    $pickup_locations = $_POST['pickup_locations'];
    $destination = sanitize_text_field($_POST['destination']);

    if (empty($post_id) || !is_int($post_id)) {
        error_log('Invalid post_id');
        wp_send_json_error('Invalid post_id');
        wp_die();
    }

    if (!is_array($pickup_locations) || empty($pickup_locations)) {
        error_log('Invalid pickup_locations');
        wp_send_json_error('Invalid pickup_locations');
        wp_die();
    }

    foreach ($pickup_locations as $location) {
        if (!is_string($location) || empty($location)) {
            error_log('Invalid pickup location: ' . print_r($location, true));
            wp_send_json_error('Invalid pickup location');
            wp_die();
        }
    }

    if (empty($destination)) {
        error_log('Empty destination');
        wp_send_json_error('Empty destination');
        wp_die();
    }

    $transport = optimize_transport($pickup_locations, $destination);

    // Aktualizace pole dopravy pro všechny role
    $roles = get_post_meta($post_id, 'obsazeni_template_roles', true);
    if ($roles) {
        foreach ($roles as $role_id) {
            update_post_meta($post_id, 'transport_' . $role_id, $transport);
            error_log('Updated transport for role ' . $role_id . ' to ' . $transport);
        }
    }

    error_log('Transport optimization completed');
    wp_send_json_success($transport);
    wp_die();
}

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

add_action('wp_ajax_optimize_transport', 'my_team_plugin_optimize_transport');
add_action('wp_ajax_nopriv_optimize_transport', 'my_team_plugin_optimize_transport');
?>
