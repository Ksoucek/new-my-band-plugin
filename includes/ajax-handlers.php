add_action('wp_ajax_fetch_ares_data', 'fetch_ares_data');
add_action('wp_ajax_nopriv_fetch_ares_data', 'fetch_ares_data');

function fetch_ares_data() {
    // Nonce should be passed in AJAX request, typically via wp_localize_script.
    // Using a generic nonce name here as an example. It should match what's sent from JS.
    check_ajax_referer('my_band_plugin_ares_nonce', 'security');

    $plugin_text_domain = 'my-band-plugin';
    $ico = isset($_GET['ico']) ? sanitize_text_field(wp_unslash($_GET['ico'])) : '';
    // error_log('Přijatý parametr IČO: ' . $ico);

    if (empty($ico)) {
        wp_send_json_error(__('IČO není zadáno.', $plugin_text_domain), 400);
        return; // Ensure no further execution
    }

    // Validate ICO format (basic validation, ARES has its own more complex rules)
    if (!preg_match('/^\d{8}$/', $ico)) {
        wp_send_json_error(__('Neplatný formát IČO.', $plugin_text_domain), 400);
        return;
    }

    $url = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/$ico";
    // error_log('URL požadavku na ARES API: ' . $url);

    $response = wp_remote_get($url, array('timeout' => 15)); // Added timeout

    if (is_wp_error($response)) {
        // error_log('Chyba při komunikaci s ARES API: ' . $response->get_error_message());
        wp_send_json_error(
            sprintf(
                '%s: %s',
                __('Chyba při komunikaci s ARES API.', $plugin_text_domain),
                $response->get_error_message()
            ),
            500
        );
        return;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    // error_log('ARES API HTTP Code: ' . $http_code);
    // error_log('Odpověď z ARES API: ' . $body);

    if ($http_code !== 200) {
        wp_send_json_error(
            sprintf(
                __('Chyba ARES API (HTTP %d): %s', $plugin_text_domain),
                $http_code,
                wp_remote_retrieve_response_message($response)
            ),
            $http_code
        );
        return;
    }

    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(__('Chyba při dekódování odpovědi z ARES API.', $plugin_text_domain), 500);
        return;
    }
    
    // Check for specific ARES error structure if known, e.g. chybovaZprava
    if (isset($data['chybovaZprava'])) {
         wp_send_json_error(
            sprintf(
                __('ARES API vrátilo chybu: %s', $plugin_text_domain),
                sanitize_text_field($data['chybovaZprava'])
            ), 404);
        return;
    }

    if (empty($data) || !isset($data['obchodniJmeno']) || !isset($data['sidlo']['textovaAdresa'])) {
        wp_send_json_error(__('Data z ARES API nejsou dostupná nebo jsou neplatná. Zkontrolujte IČO.', $plugin_text_domain), 404);
        return;
    }

    // Sanitize data received from ARES before sending it further
    $result = array(
        'nazev' => sanitize_text_field($data['obchodniJmeno']),
        'adresa' => wp_kses_post(nl2br(esc_html($data['sidlo']['textovaAdresa']))) // Allow line breaks, escape HTML
    );

    wp_send_json_success($result);
    // wp_die() is called by wp_send_json_success
}
