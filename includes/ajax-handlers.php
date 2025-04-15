add_action('wp_ajax_fetch_ares_data', 'fetch_ares_data');
add_action('wp_ajax_nopriv_fetch_ares_data', 'fetch_ares_data');

function fetch_ares_data() {
    $ico = isset($_GET['ico']) ? sanitize_text_field($_GET['ico']) : '';
    error_log('Přijatý parametr IČO: ' . $ico);

    if (!$ico) {
        wp_send_json_error('IČO není zadáno.', 400);
    }

    $url = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/$ico";
    error_log('URL požadavku na ARES API: ' . $url);

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        error_log('Chyba při komunikaci s ARES API: ' . $response->get_error_message());
        wp_send_json_error('Chyba při komunikaci s ARES API.', 500);
    }

    $body = wp_remote_retrieve_body($response);
    error_log('Odpověď z ARES API: ' . $body);

    $data = json_decode($body, true);
    if (empty($data) || !isset($data['obchodniJmeno']) || !isset($data['sidlo']['textovaAdresa'])) {
        wp_send_json_error('Data z ARES API nejsou dostupná nebo jsou neplatná.', 404);
    }

    $result = array(
        'nazev' => $data['obchodniJmeno'],
        'adresa' => $data['sidlo']['textovaAdresa']
    );

    wp_send_json_success($result);
}
