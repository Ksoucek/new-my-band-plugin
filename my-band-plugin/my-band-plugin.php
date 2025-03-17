<?php
/*
Plugin Name: Muzikantské kšefty
Description: Umožňuje uživatelům vytvářet a spravovat týmy, přidávat členy a plánovat události.
Version: 1.0
Author: Vaše Jméno
*/

error_log('Muzikantské kšefty plugin loaded'); // Logování načtení pluginu

$autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php'; // Cesta k autoload souboru
if (file_exists($autoload_path)) { // Kontrola, zda soubor existuje
    require_once $autoload_path; // Načtení knihovny Google API PHP Client
}

require_once plugin_dir_path(__FILE__) . 'includes/google-calendar.php'; // Načtení souboru pro Google Kalendář

function my_team_plugin_enqueue_scripts() {
    wp_enqueue_script('jquery'); // Načtení jQuery
    wp_enqueue_script('my-team-plugin-script', plugins_url('/js/my-team-plugin.js', __FILE__), array('jquery'), '1.0', true); // Načtení hlavního JS souboru
    wp_localize_script('my-team-plugin-script', 'myTeamPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php'), // URL pro AJAX požadavky
        'nonce' => wp_create_nonce('wp_rest'), // Nonce pro zabezpečení
        'site_url' => site_url(), // URL webu
        'post_id' => get_the_ID(), // ID aktuálního příspěvku
        'api_key' => get_option('my_team_plugin_openrouteservice_api_key'), // API klíč pro OpenRouteService
        'rest_url' => rest_url('google-calendar/v1/add-to-calendar'), // REST URL pro přidání do kalendáře
        'kseft_id' => get_the_ID() // Přidání kseft_id
     ));
    wp_enqueue_script('role-selection-script', plugins_url('/js/role-selection.js', __FILE__), array('jquery'), '1.0', true); // Načtení JS souboru pro výběr rolí
    wp_enqueue_script('google-calendar-script', plugins_url('/js/google-calendar.js', __FILE__), array('jquery', 'my-team-plugin-script'), '1.0', true); // Načtení JS souboru pro Google Kalendář
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . get_option('my_team_plugin_google_maps_api_key') . '&libraries=places', null, null, true); // Načtení Google Maps API
    wp_enqueue_style('my-team-plugin-style', plugins_url('/css/my-team-plugin.css', __FILE__)); // Načtení hlavního CSS souboru
    wp_enqueue_style('my-team-plugin-responsive-style', plugins_url('/css/my-team-plugin-responsive.css', __FILE__)); // Načtení CSS souboru pro responzivní design
}
add_action('wp_enqueue_scripts', 'my_team_plugin_enqueue_scripts'); // Přidání akce pro načtení skriptů a stylů

function my_team_plugin_create_kseft() {
    check_ajax_referer('wp_rest', 'nonce');
    $kseft_name = sanitize_text_field($_POST['kseft_name']); // Sanitizace názvu kšeftu
    $kseft_id = wp_insert_post(array(
        'post_title' => $kseft_name, // Název příspěvku
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish' // Stav příspěvku
    ));

    if (is_wp_error($kseft_id)) { // Kontrola, zda došlo k chybě
        wp_send_json_error(array('message' => 'Chyba při vytváření Akce.')); // Odeslání chybové zprávy
    } else {
        wp_send_json_success(array('kseft_id' => $kseft_id, 'redirect_url' => get_permalink($kseft_id))); // Odeslání úspěšné zprávy s ID kšeftu a URL pro přesměrování
    }
    wp_die();
}
add_action('wp_ajax_my_team_plugin_create_kseft', 'my_team_plugin_create_kseft'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_my_team_plugin_create_kseft', 'my_team_plugin_create_kseft'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_add_member() {
    check_ajax_referer('wp_rest', 'nonce');
    $kseft_id = intval($_POST['kseft_id']); // Získání ID kšeftu
    $member_name = sanitize_text_field($_POST['member_name']); // Sanitizace jména člena
    add_post_meta($kseft_id, 'kseft_member', $member_name); // Přidání člena jako meta data
    echo 'Member added'; // Zobrazení zprávy
    wp_die(); // Ukončení skriptu
}
add_action('wp_ajax_my_team_plugin_add_member', 'my_team_plugin_add_member'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_my_team_plugin_add_member', 'my_team_plugin_add_member'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_schedule_event() {
    check_ajax_referer('wp_rest', 'nonce');
    $kseft_id = intval($_POST['kseft_id']); // Získání ID kšeftu
    $event_name = sanitize_text_field($_POST['event_name']); // Sanitizace názvu události
    $event_date = sanitize_text_field($_POST['event_date']); // Sanitizace data události
    add_post_meta($kseft_id, 'kseft_event', array('name' => $event_name, 'date' => $event_date)); // Přidání události jako meta data
    echo 'Event scheduled'; // Zobrazení zprávy
    wp_die(); // Ukončení skriptu
}
add_action('wp_ajax_my_team_plugin_schedule_event', 'my_team_plugin_schedule_event'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_my_team_plugin_schedule_event', 'my_team_plugin_schedule_event'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_register_post_types() {
    // Registrace post typu 'kseft'
    register_post_type('kseft', array(
        'labels' => array(
            'name' => __('Kšefty', 'textdomain'), // Název typu příspěvku
            'singular_name' => __('Kšeft', 'textdomain'), // Jednotné číslo typu příspěvku
            'menu_name' => __('Kšefty', 'textdomain'), // Název v menu
            'name_admin_bar' => __('Kšeft', 'textdomain'), // Název v admin baru
            'add_new' => __('Přidat nový', 'textdomain'), // Text pro přidání nového příspěvku
            'add_new_item' => __('Přidat nový kšeft', 'textdomain'), // Text pro přidání nového příspěvku
            'new_item' => __('Nový kšeft', 'textdomain'), // Text pro nový příspěvek
            'edit_item' => __('Upravit kšeft', 'textdomain'), // Text pro úpravu příspěvku
            'view_item' => __('Zobrazit kšeft', 'textdomain'), // Text pro zobrazení příspěvku
            'all_items' => __('Všechny kšefty', 'textdomain'), // Text pro všechny příspěvky
            'search_items' => __('Hledat kšefty', 'textdomain'), // Text pro hledání příspěvků
            'parent_item_colon' => __('Nadřazený kšeft:', 'textdomain'), // Text pro nadřazený příspěvek
            'not_found' => __('Žádné kšefty nenalezeny.', 'textdomain'), // Text pro nenalezené příspěvky
            'not_found_in_trash' => __('Žádné kšefty v koši.', 'textdomain') // Text pro nenalezené příspěvky v koši
        ),
        'public' => true, // Veřejný typ příspěvku
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'kseft'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-calendar-alt' // Ikona pro 'kseft'
    ));

    // Registrace post typu 'obsazeni_template'
    register_post_type('obsazeni_template', array(
        'labels' => array(
            'name' => __('Šablony obsazení', 'textdomain'), // Název typu příspěvku
            'singular_name' => __('Šablona obsazení', 'textdomain'), // Jednotné číslo typu příspěvku
            'menu_name' => __('Šablony obsazení', 'textdomain'), // Název v menu
            'name_admin_bar' => __('Šablona obsazení', 'textdomain'), // Název v admin baru
            'add_new' => __('Přidat novou', 'textdomain'), // Text pro přidání nového příspěvku
            'add_new_item' => __('Přidat novou šablonu obsazení', 'textdomain'), // Text pro přidání nového příspěvku
            'new_item' => __('Nová šablona obsazení', 'textdomain'), // Text pro nový příspěvek
            'edit_item' => __('Upravit šablonu obsazení', 'textdomain'), // Text pro úpravu příspěvku
            'view_item' => __('Zobrazit šablonu obsazení', 'textdomain'), // Text pro zobrazení příspěvku
            'all_items' => __('Všechny šablony obsazení', 'textdomain'), // Text pro všechny příspěvky
            'search_items' => __('Hledat šablony obsazení', 'textdomain'), // Text pro hledání příspěvků
            'parent_item_colon' => __('Nadřazená šablona obsazení:', 'textdomain'), // Text pro nadřazený příspěvek
            'not_found' => __('Žádné šablony obsazení nenalezeny.', 'textdomain'), // Text pro nenalezené příspěvky
            'not_found_in_trash' => __('Žádné šablony obsazení v koši.', 'textdomain') // Text pro nenalezené příspěvky v koši
        ),
        'public' => true, // Veřejný typ příspěvku
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'obsazeni-template'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-clipboard' // Ikona pro 'obsazeni_template'
    ));

    // Registrace post typu 'role'
    register_post_type('role', array(
        'labels' => array(
            'name' => __('Role', 'textdomain'), // Název typu příspěvku
            'singular_name' => __('Role', 'textdomain'), // Jednotné číslo typu příspěvku
            'menu_name' => __('Role', 'textdomain'), // Název v menu
            'name_admin_bar' => __('Role', 'textdomain'), // Název v admin baru
            'add_new' => __('Přidat novou', 'textdomain'), // Text pro přidání nového příspěvku
            'add_new_item' => __('Přidat novou roli', 'textdomain'), // Text pro přidání nového příspěvku
            'new_item' => __('Nová role', 'textdomain'), // Text pro nový příspěvek
            'edit_item' => __('Upravit roli', 'textdomain'), // Text pro úpravu příspěvku
            'view_item' => __('Zobrazit roli', 'textdomain'), // Text pro zobrazení příspěvku
            'all_items' => __('Všechny role', 'textdomain'), // Text pro všechny příspěvky
            'search_items' => __('Hledat role', 'textdomain'), // Text pro hledání příspěvků
            'parent_item_colon' => __('Nadřazená role:', 'textdomain'), // Text pro nadřazený příspěvek
            'not_found' => __('Žádné role nenalezeny.', 'textdomain'), // Text pro nenalezené příspěvky
            'not_found_in_trash' => __('Žádné role v koši.', 'textdomain') // Text pro nenalezené příspěvky v koši
        ),
        'public' => true, // Veřejný typ příspěvku
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'role'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-groups' // Ikona pro 'role'
    ));

    // Registrace post typu 'auta'
    register_post_type('auta', array(
        'labels' => array(
            'name' => __('Auta', 'textdomain'), // Název typu příspěvku
            'singular_name' => __('Auto', 'textdomain'), // Jednotné číslo typu příspěvku
            'menu_name' => __('Auta', 'textdomain'), // Název v menu
            'name_admin_bar' => __('Auto', 'textdomain'), // Název v admin baru
            'add_new' => __('Přidat nové', 'textdomain'), // Text pro přidání nového příspěvku
            'add_new_item' => __('Přidat nové auto', 'textdomain'), // Text pro přidání nového příspěvku
            'new_item' => __('Nové auto', 'textdomain'), // Text pro nový příspěvek
            'edit_item' => __('Upravit auto', 'textdomain'), // Text pro úpravu příspěvku
            'view_item' => __('Zobrazit auto', 'textdomain'), // Text pro zobrazení příspěvku
            'all_items' => __('Všechna auta', 'textdomain'), // Text pro všechny příspěvky
            'search_items' => __('Hledat auta', 'textdomain'), // Text pro hledání příspěvků
            'parent_item_colon' => __('Nadřazené auto:', 'textdomain'), // Text pro nadřazený příspěvek
            'not_found' => __('Žádná auta nenalezena.', 'textdomain'), // Text pro nenalezené příspěvky
            'not_found_in_trash' => __('Žádná auta v koši.', 'textdomain') // Text pro nenalezené příspěvky v koši
        ),
        'public' => true, // Veřejný typ příspěvku
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'auta'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-car' // Ikona pro 'auta'
    ));
}
add_action('init', 'my_team_plugin_register_post_types'); // Přidání akce pro registraci typů příspěvků

// Přidání metaboxu pro výchozího hráče, výchozí místo vyzvednutí a heslo role při tvorbě role
function my_team_plugin_add_role_meta_boxes() {
    add_meta_box('role_default_player', 'Výchozí hráč', 'my_team_plugin_render_role_default_player_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro výchozího hráče
    add_meta_box('role_default_pickup_location', 'Výchozí místo vyzvednutí', 'my_team_plugin_render_role_default_pickup_location_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro výchozí místo vyzvednutí
    add_meta_box('role_password', 'Heslo role', 'my_team_plugin_render_role_password_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro heslo role
}
add_action('add_meta_boxes', 'my_team_plugin_add_role_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_role_default_player_meta_box($post) {
    $default_player = get_post_meta($post->ID, 'role_default_player', true); // Získání výchozího hráče
    ?>
    <label for="role_default_player">Výchozí hráč:</label>
    <input type="text" name="role_default_player" id="role_default_player" value="<?php echo esc_attr($default_player); ?>" size="25" /> <!-- Pole pro výchozího hráče -->
    <?php
}

function my_team_plugin_render_role_default_pickup_location_meta_box($post) {
    $default_pickup_location = get_post_meta($post->ID, 'role_default_pickup_location', true); // Získání výchozího místa vyzvednutí
    ?>
    <label for="role_default_pickup_location">Výchozí místo vyzvednutí:</label>
    <input type="text" name="role_default_pickup_location" id="role_default_pickup_location" value="<?php echo esc_attr($default_pickup_location); ?>" size="25" /> <!-- Pole pro výchozí místo vyzvednutí -->
    <?php
}

function my_team_plugin_render_role_password_meta_box($post) {
    $role_password = get_post_meta($post->ID, 'role_password', true); // Získání hesla role
    ?>
    <label for="role_password">Heslo role:</label>
    <input type="password" name="role_password" id="role_password" value="<?php echo esc_attr($role_password); ?>" size="25" /> <!-- Pole pro heslo role -->
    <?php
}

function my_team_plugin_save_role_meta_box_data($post_id) {
    if (array_key_exists('role_default_player', $_POST)) {
        update_post_meta($post_id, 'role_default_player', sanitize_text_field($_POST['role_default_player'])); // Uložení výchozího hráče
    }
    if (array_key_exists('role_default_pickup_location', $_POST)) {
        update_post_meta($post_id, 'role_default_pickup_location', sanitize_text_field($_POST['role_default_pickup_location'])); // Uložení výchozího místa vyzvednutí
    }
    if (array_key_exists('role_password', $_POST)) {
        update_post_meta($post_id, 'role_password', sanitize_text_field($_POST['role_password'])); // Uložení hesla role
    }
}
add_action('save_post', 'my_team_plugin_save_role_meta_box_data'); // Přidání akce pro uložení metaboxů

function my_team_plugin_display_ksefty() {
    error_log('my_team_plugin_display_ksefty function called'); // Logování volání funkce
    $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1'; // Kontrola, zda je zaškrtnuto zobrazení všech kšeftů
    $args = array(
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish', // Stav příspěvku
        'posts_per_page' => -1, // Počet příspěvků na stránku
        'meta_key' => 'kseft_event_date', // Klíč pro řazení
        'orderby' => 'meta_value', // Řazení podle meta hodnoty
        'order' => 'ASC' // Řazení vzestupně
    );

    if (!$show_all) {
        $args['meta_query'] = array(
            array(
                'key' => 'kseft_event_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            )
        );
    }

    $ksefty = new WP_Query($args); // Dotaz na příspěvky
    error_log('Query executed: ' . print_r($args, true)); // Logování dotazu
    $output = '<div class="business-overview" style="text-align: center;">'; // Přidání stylu pro vycentrování
    $output .= '<a href="' . site_url('/moje-ksefty') . '" class="button">Moje Akce</a>'; // Přidání tlačítka pro přechod na "moje kšefty"
    $output .= '<a href="' . site_url('/manage-kseft') . '" class="button">Vytvořit novou Akci</a>'; // Přesunutí tlačítka nahoru
    $output .= '<form method="GET" action=""><label><input type="checkbox" name="show_all" value="1" ' . ($show_all ? 'checked' : '') . '> Zobrazit všechny akce</label><button type="submit" class="button">Filtrovat</button></form>'; // Přidání zaškrtávacího políčka pro zobrazení všech kšeftů
    if ($ksefty->have_posts()) {
        $output .= '<table>';
        $output .= '<thead><tr><th>Termín</th><th>Název</th><th>Umístění</th><th>Stav obsazení</th><th>Stav</th></thead>';
        $output .= '<tbody>';
        while ($ksefty->have_posts()) {
            $ksefty->the_post();
            $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true); // Získání data události
            $location = get_post_meta(get_the_ID(), 'kseft_location', true); // Získání lokace
            $status = get_post_meta(get_the_ID(), 'kseft_status', true); // Získání stavu
            $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
            $all_confirmed = true; // Předpoklad, že všechny role jsou potvrzeny
            $has_substitute = false; // Předpoklad, že žádná role nemá záskok
            $pickup_location = ''; // Výchozí hodnota pro místo vyzvednutí
            $pickup_time = ''; // Výchozí hodnota pro čas vyzvednutí
            $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0; // Získání aktuální role z cookie
            if ($roles) {
                foreach ($roles as $role_id) {
                    $role_status = get_post_meta(get_the_ID(), 'role_status_' . $role_id, true); // Získání stavu role
                    if ($role_status === 'Záskok') {
                        $has_substitute = true; // Pokud je role záskok, nastaví se příznak
                    }
                    if ($role_status !== 'Jdu' && $role_status !== 'Záskok') {
                        $all_confirmed = false; // Pokud role není potvrzena, nastaví se příznak
                        break;
                    }
                    if ($role_id == $current_role_id) {
                        $pickup_location = get_post_meta(get_the_ID(), 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí pro aktuální roli
                        $pickup_time = get_post_meta(get_the_ID(), 'pickup_time_' . $role_id, true); // Získání času vyzvednutí pro aktuální roli
                    }
                }
            } else {
                $all_confirmed = false; // Pokud nejsou žádné role, nastaví se příznak
            }
            if ($all_confirmed) {
                $obsazeni_class = 'obsazeno'; // Pokud jsou všechny role potvrzeny, nastaví se třída
                $obsazeni_text = $has_substitute ? 'Obsazeno se záskokem' : 'Obsazeno'; // Pokud je záskok, nastaví se text
            } else {
                $obsazeni_class = 'neobsazeno'; // Pokud nejsou všechny role potvrzeny, nastaví se třída
                $obsazeni_text = 'Neobsazeno'; // Nastaví se text
            }
            $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
            $output .= '<tr>';
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($formatted_date) . '</a></td>'; // Přidání odkazu na termín
            $output .= '<td><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>'; // Přidání odkazu na název
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($location) . '</a></td>'; // Přidání odkazu na lokaci
            $output .= '<td><a href="' . get_permalink() . '" class="button kseft-status-button ' . esc_attr($obsazeni_class) . '">' . esc_html($obsazeni_text) . '</a></td>'; // Přidání odkazu na stav obsazení
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($status) . '</a></td>'; // Přidání odkazu na stav
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
        wp_reset_postdata(); // Resetování dotazu
    } else {
        $output .= '<p>Žádné akce nejsou k dispozici.</p>'; // Zobrazení zprávy, pokud nejsou žádné kšefty
    }
    $output .= '</div>';
    error_log('Output: ' . $output); // Logování výstupu
    return $output;
}
add_shortcode('display_ksefty', 'my_team_plugin_display_ksefty'); // Přidání shortcode pro zobrazení kšeftů

function my_team_plugin_display_moje_ksefty() {
    error_log('my_team_plugin_display_moje_ksefty function called'); // Logování volání funkce
    $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1'; // Kontrola, zda je zaškrtnuto zobrazení všech kšeftů
    $args = array(
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish', // Stav příspěvku
        'posts_per_page' => -1, // Počet příspěvků na stránku
        'meta_key' => 'kseft_event_date', // Klíč pro řazení
        'orderby' => 'meta_value', // Řazení podle meta hodnoty
        'order' => 'ASC' // Řazení vzestupně
    );

    if (!$show_all) {
        $args['meta_query'] = array(
            array(
                'key' => 'kseft_event_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            )
        );
    }

    $ksefty = new WP_Query($args); // Dotaz na příspěvky
    error_log('Query executed: ' . print_r($args, true)); // Logování dotazu
    $output = '<div class="business-overview" style="text-align: center;">'; // Přidání stylu pro vycentrování
    $output .= '<a href="' . site_url('/moje-ksefty') . '" class="button">Moje akce</a>'; // Přidání tlačítka pro přechod na "moje kšefty"
    $output .= '<a href="' . site_url('/manage-kseft') . '" class="button">Vytvořit novou akci</a>'; // Přesunutí tlačítka nahoru
    $output .= '<form method="GET" action=""><label><input type="checkbox" name="show_all" value="1" ' . ($show_all ? 'checked' : '') . '> Zobrazit všechny Akce</label><button type="submit" class="button">Filtrovat</button></form>'; // Přidání zaškrtávacího políčka pro zobrazení všech kšeftů
    if ($ksefty->have_posts()) {
        $output .= '<table>';
        $output .= '<thead><tr><th>Termín</th><th>Název</th><th>Umístění</th><th>Stav obsazení</th><th>Stav</th></thead>';
        $output .= '<tbody>';
        while ($ksefty->have_posts()) {
            $ksefty->the_post();
            $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true); // Získání data události
            $location = get_post_meta(get_the_ID(), 'kseft_location', true); // Získání lokace
            $status = get_post_meta(get_the_ID(), 'kseft_status', true); // Získání stavu
            $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
            $all_confirmed = true; // Předpoklad, že všechny role jsou potvrzeny
            $has_substitute = false; // Předpoklad, že žádná role nemá záskok
            $pickup_location = ''; // Výchozí hodnota pro místo vyzvednutí
            $pickup_time = ''; // Výchozí hodnota pro čas vyzvednutí
            $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0; // Získání aktuální role z cookie
            if ($roles) {
                foreach ($roles as $role_id) {
                    $role_status = get_post_meta(get_the_ID(), 'role_status_' . $role_id, true); // Získání stavu role
                    if ($role_status === 'Záskok') {
                        $has_substitute = true; // Pokud je role záskok, nastaví se příznak
                    }
                    if ($role_status !== 'Jdu' && $role_status !== 'Záskok') {
                        $all_confirmed = false; // Pokud role není potvrzena, nastaví se příznak
                        break;
                    }
                    if ($role_id == $current_role_id) {
                        $pickup_location = get_post_meta(get_the_ID(), 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí pro aktuální roli
                        $pickup_time = get_post_meta(get_the_ID(), 'pickup_time_' . $role_id, true); // Získání času vyzvednutí pro aktuální roli
                    }
                }
            } else {
                $all_confirmed = false; // Pokud nejsou žádné role, nastaví se příznak
            }
            if ($all_confirmed) {
                $obsazeni_class = 'obsazeno'; // Pokud jsou všechny role potvrzeny, nastaví se třída
                $obsazeni_text = $has_substitute ? 'Obsazeno se záskokem' : 'Obsazeno'; // Pokud je záskok, nastaví se text
            } else {
                $obsazeni_class = 'neobsazeno'; // Pokud nejsou všechny role potvrzena, nastaví se třída
                $obsazeni_text = 'Neobsazeno'; // Nastaví se text
            }
            $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
            $output .= '<tr>';
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($formatted_date) . '</a></td>'; // Přidání odkazu na termín
            $output .= '<td><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>'; // Přidání odkazu na název
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($location) . '</a></td>'; // Přidání odkazu na lokaci
            $output .= '<td><a href="' . get_permalink() . '" class="button kseft-status-button ' . esc_attr($obsazeni_class) . '">' . esc_html($obsazeni_text) . '</a></td>'; // Přidání odkazu na stav obsazení
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($status) . '</a></td>'; // Přidání odkazu na stav
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
        wp_reset_postdata(); // Resetování dotazu
    } else {
        $output .= '<p>Žádné Akce nejsou k dispozici.</p>'; // Zobrazení zprávy, pokud nejsou žádné kšefty
    }
    $output .= '</div>';
    error_log('Output: ' . $output); // Logování výstupu
    return $output;
}
add_shortcode('display_moje_ksefty', 'my_team_plugin_display_moje_ksefty'); // Přidání shortcode pro zobrazení mých kšeftů

function my_team_plugin_add_meta_boxes() {
    add_meta_box('kseft_details', 'Kšeft Details', 'my_team_plugin_render_meta_box', 'kseft', 'normal', 'high'); // Přidání metaboxu pro detaily kšeftu
}
add_action('add_meta_boxes', 'my_team_plugin_add_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_meta_box($post) {
    $location = get_post_meta($post->ID, 'kseft_location', true); // Získání lokace
    $meeting_time = get_post_meta($post->ID, 'kseft_meeting_time', true); // Získání času srazu
    $event_date = get_post_meta($post->ID, 'kseft_event_date', true); // Získání data události
    $performance_start = get_post_meta($post->ID, 'kseft_performance_start', true); // Získání začátku vystoupení
    $performance_end = get_post_meta($post->ID, 'kseft_performance_end', true); // Získání konce vystoupení
    $status = get_post_meta($post->ID, 'kseft_status', true); // Získání stavu
    $clothing = get_post_meta($post->ID, 'kseft_clothing', true); // Získání oblečení
    ?>
    <label for="kseft_location">Lokace (Google Maps URL):</label>
    <input type="text" name="kseft_location" id="kseft_location_wp" value="<?php echo esc_attr($location); ?>" size="25" /> <!-- Pole pro lokaci -->
    <div id="map-kseft-wp"></div> <!-- Změna ID elementu mapy -->
    <br><br>
    <label for="kseft_meeting_time">Čas srazu:</label>
    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($meeting_time); ?>" size="25" /> <!-- Pole pro čas srazu -->
    <br><br>
    <div style="display: flex; justify-content: space_between;">
        <div style="flex: 1; margin-right: 10px;">
            <label for="kseft_event_date">Datum Akce:</label>
            <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($event_date); ?>" size="25" /> <!-- Pole pro datum kšeftu -->
        </div>
        <div style="flex: 1; margin-right: 10px;">
            <label for="kseft_performance_start">Začátek vystoupení:</label>
            <input type="time" name="kseft_performance_start" id="kseft_performance_start" value="<?php echo esc_attr($performance_start); ?>" size="25" /> <!-- Pole pro začátek vystoupení -->
        </div>
        <div style="flex: 1;">
            <label for="kseft_performance_end">Konec vystoupení:</label>
            <input type="time" name="kseft_performance_end" id="kseft_performance_end" value="<?php echo esc_attr($performance_end); ?>" size="25" /> <!-- Pole pro konec vystoupení -->
        </div>
    </div>
    <br><br>
    <label for="kseft_status">Stav Akce:</label>
    <select name="kseft_status" id="kseft_status">
        <option value="Rezervace termínu" <?php selected($status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
        <option value="Podepsaná smlouva" <?php selected($status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
    </select> <!-- Výběr pro stav kšeftu -->
    <br><br>
    <label for="kseft_clothing">Oblečení:</label>
    <select name="kseft_clothing" id="kseft_clothing">
        <option value="krojová košile" <?php selected($clothing, 'krojová košile'); ?>>Krojová košile</option>
        <option value="společenská košile" <?php selected($clothing, 'společenská košile'); ?>>Společenská košile</option>
        <option value="Tmavý civil" <?php selected($clothing, 'Tmavý civil'); ?>>Tmavý civil</option>
    </select> <!-- Výběr pro oblečení -->
    <br><br>
    <?php
}

function my_team_plugin_save_meta_box_data($post_id) {
    if (array_key_exists('kseft_location', $_POST)) {
        update_post_meta($post_id, 'kseft_location', sanitize_text_field($_POST['kseft_location'])); // Uložení lokace
    }
    if (array_key_exists('kseft_meeting_time', $_POST)) {
        update_post_meta($post_id, 'kseft_meeting_time', sanitize_text_field($_POST['kseft_meeting_time'])); // Uložení času srazu
    }
    if (array_key_exists('kseft_event_date', $_POST)) {
        update_post_meta($post_id, 'kseft_event_date', sanitize_text_field($_POST['kseft_event_date'])); // Uložení data události
    }
    if (array_key_exists('kseft_performance_start', $_POST)) {
        update_post_meta($post_id, 'kseft_performance_start', sanitize_text_field($_POST['kseft_performance_start'])); // Uložení začátku vystoupení
    }
    if (array_key_exists('kseft_performance_end', $_POST)) {
        update_post_meta($post_id, 'kseft_performance_end', sanitize_text_field($_POST['kseft_performance_end'])); // Uložení konce vystoupení
    }
    if (array_key_exists('kseft_status', $_POST)) {
        update_post_meta($post_id, 'kseft_status', sanitize_text_field($_POST['kseft_status'])); // Uložení stavu
    }
    if (array_key_exists('kseft_clothing', $_POST)) {
        update_post_meta($post_id, 'kseft_clothing', sanitize_text_field($_POST['kseft_clothing'])); // Uložení oblečení
    }
}
add_action('save_post', 'my_team_plugin_save_meta_box_data'); // Přidání akce pro uložení metaboxů

function my_team_plugin_display_kseft_details($content) {
    if (is_singular('kseft')) {
        $kseft_id = get_the_ID(); // Získání ID kšeftu
        $location = get_post_meta($kseft_id, 'kseft_location', true); // Získání lokace
        $meeting_time = get_post_meta($kseft_id, 'kseft_meeting_time', true); // Získání času srazu
        $event_date = get_post_meta($kseft_id, 'kseft_event_date', true); // Získání data události
        $performance_start = get_post_meta($kseft_id, 'kseft_performance_start', true); // Získání začátku vystoupení
        $performance_end = get_post_meta($kseft_id, 'kseft_performance_end', true); // Získání konce vystoupení
        $status = get_post_meta($kseft_id, 'kseft_status', true); // Získání stavu
        $clothing = get_post_meta($kseft_id, 'kseft_clothing', true); // Získání oblečení
        $description = get_post_meta($kseft_id, 'kseft_description', true); // Získání popisu
        $obsazeni_template_id = get_post_meta($kseft_id, 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
        $obsazeni_template = get_post($obsazeni_template_id); // Získání šablony obsazení

        // Přidání tlačítek pro přechod na další nebo předchozí kšeft
        $prev_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'prev'); // Získání předchozího kšeftu
        $next_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'next'); // Získání dalšího kšeftu
        $custom_content = '<div class="kseft-navigation" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
        if ($prev_kseft) {
            $custom_content .= '<a href="' . get_permalink($prev_kseft->ID) . '" class="button" style="flex: 1; text-align: left;">Předchozí Akce</a>'; // Tlačítko pro předchozí kšeft
        } else {
            $custom_content .= '<span style="flex: 1;"></span>';
        }
        $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0; // Získání aktuální role z cookie
        $back_link = $current_role_id ? site_url('/moje-ksefty') : site_url('/ksefty'); // Odkaz zpět na přehled kšeftů
        $custom_content .= '<a href="' . $back_link . '" class="button" style="flex: 1; text-align: center;">Zpět na přehled akcí</a>'; // Tlačítko zpět na přehled kšeftů
        if ($next_kseft) {
            $custom_content .= '<a href="' . get_permalink($next_kseft->ID) . '" class="button" style="flex: 1; text-align: right;">Další Akce</a>'; // Tlačítko pro další kšeft
        } else {
            $custom_content .= '<span style="flex: 1;"></span>';
        }
        $custom_content .= '</div>';

        // Přidání tlačítek pro úpravu kšeftu a přidání do Google Kalendáře, pokud nepřicházíte ze stránky "moje-ksefty"
        if (!isset($_GET['from']) || $_GET['from'] !== 'moje-ksefty') {
            $custom_content .= '<a href="' . add_query_arg('kseft_id', $kseft_id, site_url('/manage-kseft')) . '" class="button">Upravit Akci</a>'; // Tlačítko pro úpravu kšeftu
            $custom_content .= '<button id="add-to-calendar-button" class="button">Přidat do Google Kalendáře</button>'; // Tlačítko pro přidání do Google Kalendáře
        }

        $custom_content .= '<h3>Detaily Akce</h3>';
        // $custom_content .= '<p><strong>ID Kšeftu:</strong> ' . esc_html($kseft_id) . '</p>'; // Zobrazení ID kšeftu
        $custom_content .= '<input type="hidden" id="kseft_id" value="' . esc_attr($kseft_id) . '">'; // Skryté pole pro kseft_id
        $custom_content .= '<p><strong>Lokace:</strong> ' . esc_html($location) . '</p>'; // Zobrazení lokace
        $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
        $custom_content .= '<p><strong>Datum Akce:</strong> ' . esc_html($formatted_date) . '</p>'; // Zobrazení data kšeftu
        $custom_content .= '<p><strong>Čas srazu:</strong> ' . esc_html($meeting_time) . '</p>'; // Zobrazení času srazu
        $custom_content .= '<p><strong>Začátek vystoupení:</strong> ' . esc_html($performance_start) . '</p>'; // Zobrazení začátku vystoupení
        $custom_content .= '<p><strong>Konec vystoupení:</strong> ' . esc_html($performance_end) . '</p>'; // Zobrazení konce vystoupení
        $custom_content .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>'; // Zobrazení stavu
        $custom_content .= '<p><strong>Oblečení:</strong> ' . esc_html($clothing) . '</p>'; // Zobrazení oblečení
        $custom_content .= '<p><strong>Popis:</strong> ' . wpautop($description) . '</p>'; // Zobrazení popisu s HTML úpravami
        if ($obsazeni_template) {
            $custom_content .= '<h4>Obsazení:</h4>';
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
            if ($roles) {
                $custom_content .= '<table id="obsazeni-table">';
                $custom_content .= '<thead><tr><th>Název role</th><th>Potvrzení</th><th>Místo vyzvednutí</th><th>Čas vyzvednutí</th><th class="sortable">Doprava</th><th class="sortable">Akce</th></tr></thead>';
                $custom_content .= '<tbody>';
                foreach ($roles as $role_id) {
                    $role = get_post($role_id); // Získání role
                    if ($role) {
                        $role_status = get_post_meta($kseft_id, 'role_status_' . $role_id, true); // Získání stavu role
                        $role_substitute = get_post_meta($kseft_id, 'role_substitute_' . $role_id, true); // Získání záskoku role
                        $default_player = get_post_meta($role_id, 'role_default_player', true); // Získání výchozího hráče
                        $default_pickup_location = get_post_meta($role_id, 'role_default_pickup_location', true); // Získání výchozího místa vyzvednutí
                        $pickup_location = get_post_meta($kseft_id, 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí
                        $pickup_time = get_post_meta($kseft_id, 'pickup_time_' . $role_id, true); // Získání času vyzvednutí
                        $transport = get_post_meta($kseft_id, 'transport_' . $role_id, true); // Získání dopravy
                        $button_class = 'role-confirmation'; // Výchozí třída pro tlačítko
                        $button_text = $role_status ?: 'Nepotvrzeno'; // Výchozí text pro tlačítko
                        if ($role_status === 'Jdu') {
                            $button_class .= ' role-confirmation-jdu'; // Pokud je role potvrzena, nastaví se třída
                        } elseif ($role_status === 'Záskok') {
                            $button_class .= ' role-confirmation-zaskok'; // Pokud je role záskok, nastaví se třída
                        } else {
                            $button_class .= ' role-confirmation-nepotvrzeno'; // Pokud role není potvrzena, nastaví se třída
                        }
                        $confirmation_text = $role_status === 'Záskok' ? 'Záskok: ' . esc_html($role_substitute) : esc_html($default_player); // Text pro potvrzení role
                        $custom_content .= '<tr>';
                        $custom_content .= '<td>' . esc_html($role->post_title) . '</td>'; // Zobrazení názvu role
                        $custom_content .= '<td>' . $confirmation_text . '</td>'; // Zobrazení potvrzení role
                        $custom_content .= '<td>' . esc_html($pickup_location) . '</td>'; // Zobrazení místa vyzvednutí
                        $custom_content .= '<td class="pickup-time"><input type="text" name="pickup_time_' . esc_attr($role_id) . '" value="' . esc_attr($pickup_time) . '" class="pickup-time-input" data-role-id="' . esc_attr($role_id) . '"></td>'; // Zobrazení editovatelného pole pro čas vyzvednutí
                        $custom_content .= '<td>
                            <select name="transport_' . esc_attr($role_id) . '" class="transport-select" data-role-id="' . esc_attr($role_id) . '">
                                <option value="">-- Vyberte auto --</option>';
                        $auta = get_posts(array('post_type' => 'auta', 'numberposts' => -1)); // Získání všech aut
                        foreach ($auta as $auto) {
                            $auto_title = get_the_title($auto->ID); // Získání názvu auta
                            $seats = get_post_meta($auto->ID, 'doprava_seats', true); // Získání počtu míst
                            $driver = get_post_meta($auto->ID, 'doprava_driver', true); // Získání řidiče
                            $custom_content .= '<option value="' . esc_attr($auto_title) . '" data-seats="' . esc_attr($seats) . '" ' . selected($transport, $auto_title, false) . '>' . esc_html($auto_title) . ' (' . esc_html($seats) . ' míst, řidič: ' . esc_html($driver) . ')</option>'; // Výběr pro auto
                        }
                        $custom_content .= '</select>
                        </td>';
                        $custom_content .= '<td><button class="button ' . esc_attr($button_class) . '" data-role-id="' . esc_attr($role_id) . '" data-kseft-id="' . esc_attr($kseft_id) . '" data-default-player="' . esc_attr($default_player) . '" data-pickup-location="' . esc_attr($pickup_location) . '" data-default-pickup-location="' . esc_attr($default_pickup_location) . '">' . esc_html($button_text) . '</button></td>'; // Tlačítko pro potvrzení role
                        $custom_content .= '</tr>';
                    }
                }
                $custom_content .= '</tbody>';
                $custom_content .= '</table>';
            }
        }

        // Přidání modálního okna pro potvrzení účasti
        $custom_content .= '<div id="role-confirmation-modal" style="display: none;">
            <div class="modal-content">
                <h3>Potvrdit účast</h3>
                <form id="role-confirmation-form">
                    <input type="hidden" name="kseft_id" id="kseft_id" value="">
                    <input type="hidden" name="role_id" id="role_id" value="">
                    <label for="role_status">Stav účasti:</label>
                    <select name="role_status" id="role_status">
                        <option value="Nepotvrzeno">Nepotvrzeno</option>
                        <option value="Jdu">Jdu</option>
                        <option value="Záskok">Záskok</option>
                    </select>
                    <div id="substitute-field" style="display: none;">
                        <label for="role_substitute">Záskok:</label>
                        <input type="text" name="role_substitute" id="role_substitute" value="">
                    </div>
                    <div id="pickup-location-field" style="display: none;">
                        <label for="pickup_location">Místo vyzvednutí:</label>
                        <input type="text" name="pickup_location" id="pickup_location" value="">
                    </div>
                    <div id="default-player-field" style="display: none;">
                        <label for="default_player">Jméno hráče:</label>
                        <input type="text" name="default_player" id="default_player" value="">
                    </div>
                    <button type="submit" class="button">Uložit</button>
                    <button type="button" class="button" id="close-modal">Zavřít</button>
                </form>
            </div>
        </div>';

        $content .= $custom_content;
    }
    return $content;
}
add_filter('the_content', 'my_team_plugin_display_kseft_details'); // Přidání filtru pro zobrazení detailů kšeftu

function my_team_plugin_save_pickup_time() {
    check_ajax_referer('wp_rest', 'nonce');
    $post_id = intval($_POST['post_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role
    $pickup_time = sanitize_text_field($_POST['pickup_time']); // Sanitizace času vyzvednutí

    // Kontrola formátu času (hh:mm) nebo prázdného pole
    if ($pickup_time !== '' && !preg_match('/^\d{2}:\d{2}$/', $pickup_time)) {
        echo 'Neplatný formát času. Použijte formát hh:mm.';
        wp_die();
    }

    if ($pickup_time === '') {
        delete_post_meta($post_id, 'pickup_time_' . $role_id); // Smazání času vyzvednutí
        echo 'Čas vyzvednutí byl smazán.';
    } else {
        update_post_meta($post_id, 'pickup_time_' . $role_id, $pickup_time); // Uložení času vyzvednutí
        echo 'Čas vyzvednutí byl uložen.';
    }

    wp_die();
}
add_action('wp_ajax_save_pickup_time', 'my_team_plugin_save_pickup_time'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_save_pickup_time', 'my_team_plugin_save_pickup_time'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_adjacent_kseft($current_date, $direction = 'next') {
    $order = ($direction === 'next') ? 'ASC' : 'DESC'; // Řazení podle směru
    $compare = ($direction === 'next') ? '>' : '<'; // Porovnání podle směru
    $args = array(
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish', // Stav příspěvku
        'posts_per_page' => 1, // Počet příspěvků na stránku
        'meta_key' => 'kseft_event_date', // Klíč pro řazení
        'orderby' => 'meta_value', // Řazení podle meta hodnoty
        'order' => $order, // Řazení podle směru
        'meta_query' => array(
            array(
                'key' => 'kseft_event_date', // Klíč pro porovnání
                'value' => $current_date, // Hodnota pro porovnání
                'compare' => $compare, // Porovnání podle směru
                'type' => 'DATE' // Typ hodnoty
            )
        )
    );
    $query = new WP_Query($args); // Dotaz na příspěvky
    if ($query->have_posts()) {
        return $query->posts[0]; // Vrácení prvního příspěvku
    }
    return false; // Pokud nejsou žádné příspěvky, vrátí se false
}

function my_team_plugin_save_role_confirmation() {
    check_ajax_referer('wp_rest', 'nonce');
    $post_id = intval($_POST['kseft_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role
    $role_status = sanitize_text_field($_POST['role_status']); // Sanitizace stavu role
    $role_substitute = sanitize_text_field($_POST['role_substitute']); // Sanitizace záskoku role
    $pickup_location = sanitize_text_field($_POST['pickup_location']); // Sanitizace místa vyzvednutí

    // Kontrola, zda uživatel může potvrdit účast za tuto roli
    if (!isset($_COOKIE['selectedRoleId']) || intval($_COOKIE['selectedRoleId']) !== $role_id) {
        wp_send_json_error('Nemáte oprávnění potvrdit účast za tuto roli.');
        wp_die();
    }

    update_post_meta($post_id, 'role_status_' . $role_id, $role_status); // Uložení stavu role
    update_post_meta($post_id, 'role_substitute_' . $role_id, $role_substitute); // Uložení záskoku role
    update_post_meta($post_id, 'pickup_location_' . $role_id, $pickup_location); // Uložení místa vyzvednutí

    error_log("php Role confirmation saved: kseft_id=$post_id, role_id=$role_id, role_status=$role_status, role_substitute=$role_substitute, pickup_location=$pickup_location"); // Logování uložení potvrzení role

    wp_send_json_success('Účast byla potvrzena.');
    wp_die();
}
add_action('wp_ajax_save_role_confirmation', 'my_team_plugin_save_role_confirmation'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_save_role_confirmation', 'my_team_plugin_save_role_confirmation'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_role_confirmation() {
    $post_id = intval($_POST['kseft_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role

    $role_status = get_post_meta($post_id, 'role_status_' . $role_id, true); // Získání stavu role
    $role_substitute = get_post_meta($post_id, 'role_substitute_' . $role_id, true); // Získání záskoku role
    $pickup_location = get_post_meta($post_id, 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí

    wp_send_json_success(array(
        'role_status' => $role_status,
        'role_substitute' => $role_substitute,
        'pickup_location' => $pickup_location
    ));
}
add_action('wp_ajax_get_role_confirmation', 'my_team_plugin_get_role_confirmation'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_role_confirmation', 'my_team_plugin_get_role_confirmation'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_save_role_transport() {
    check_ajax_referer('wp_rest', 'nonce');
    $post_id = intval($_POST['post_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role
    $transport = sanitize_text_field($_POST['transport']); // Sanitizace dopravy

    update_post_meta($post_id, 'transport_' . $role_id, $transport); // Uložení dopravy

    echo 'Doprava byla uložena.';
    wp_die();
}
add_action('wp_ajax_save_role_transport', 'my_team_plugin_save_role_transport'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_save_role_transport', 'my_team_plugin_save_role_transport'); // Přidání AJAX akce pro nepřihlášené uživatele

// Přidání metaboxu pro výběr rolí při tvorbě šablony obsazení
function my_team_plugin_add_obsazeni_template_meta_boxes() {
    add_meta_box('obsazeni_template_roles', 'Role v kapele', 'my_team_plugin_render_obsazeni_template_roles_meta_box', 'obsazeni_template', 'normal', 'high'); // Přidání metaboxu pro role v kapele
}
add_action('add_meta_boxes', 'my_team_plugin_add_obsazeni_template_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_obsazeni_template_roles_meta_box($post) {
    $selected_roles = get_post_meta($post->ID, 'obsazeni_template_roles', true); // Získání vybraných rolí
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1)); // Získání všech rolí
    ?>
    <label for="obsazeni_template_roles">Vyberte role:</label>
    <select name="obsazeni_template_roles[]" id="obsazeni_template_roles" multiple>
        <?php foreach ($roles as $role) : ?>
            <option value="<?php echo $role->ID; ?>" <?php echo (is_array($selected_roles) && in_array($role->ID, $selected_roles)) ? 'selected' : ''; ?>><?php echo $role->post_title; ?></option>
        <?php endforeach; ?>
    </select> <!-- Výběr pro role -->
    <?php
}

function my_team_plugin_save_obsazeni_template_meta_box_data($post_id) {
    if (array_key_exists('obsazeni_template_roles', $_POST)) {
        update_post_meta($post_id, 'obsazeni_template_roles', $_POST['obsazeni_template_roles']); // Uložení rolí
    }
}
add_action('save_post', 'my_team_plugin_save_obsazeni_template_meta_box_data'); // Přidání akce pro uložení metaboxů

// Přidání metaboxu pro stav kšeftu
function my_team_plugin_add_kseft_status_meta_box() {
    add_meta_box('kseft_status', 'Stav kšeftu', 'my_team_plugin_render_kseft_status_meta_box', 'kseft', 'side', 'default'); // Přidání metaboxu pro stav kšeftu
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_status_meta_box'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_kseft_status_meta_box($post) {
    $status = get_post_meta($post->ID, 'kseft_status', true); // Získání stavu kšeftu
    ?>
    <label for="kseft_status">Stav kšeftu:</label>
    <select name="kseft_status" id="kseft_status">
        <option value="Rezervace termínu" <?php selected($status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
        <option value="Podepsaná smlouva" <?php selected($status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
    </select> <!-- Výběr pro stav kšeftu -->
    <?php
}

function my_team_plugin_save_kseft_status_meta_box_data($post_id) {
    if (array_key_exists('kseft_status', $_POST)) {
        update_post_meta($post_id, 'kseft_status', sanitize_text_field($_POST['kseft_status'])); // Uložení stavu kšeftu
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_status_meta_box_data'); // Přidání akce pro uložení metaboxů

function my_team_plugin_add_kseft_details_meta_box() {
    add_meta_box('kseft_details', 'Detaily Kšeftu', 'my_team_plugin_render_kseft_details_meta_box', 'kseft', 'normal', 'high'); // Přidání metaboxu pro detaily kšeftu
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_details_meta_box'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_kseft_details_meta_box($post) {
    $location = get_post_meta($post->ID, 'kseft_location', true); // Získání lokace
    $meeting_time = get_post_meta($post->ID, 'kseft_meeting_time', true); // Získání času srazu
    $event_date = get_post_meta($post->ID, 'kseft_event_date', true); // Získání data události
    ?>
    <label for="kseft_location">Lokace (Google Maps URL):</label>
    <input type="text" name="kseft_location" id="kseft_location" value="<?php echo esc_attr($location); ?>" size="25" /> <!-- Pole pro lokaci -->
    <br><br>
    <label for="kseft_meeting_time">Čas srazu:</label>
    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($meeting_time); ?>" size="25" /> <!-- Pole pro čas srazu -->
    <br><br>
    <label for="kseft_event_date">Datum kšeftu:</label>
    <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($event_date); ?>" size="25" /> <!-- Pole pro datum kšeftu -->
    <?php
}

function my_team_plugin_save_kseft_details_meta_box_data($post_id) {
    if (array_key_exists('kseft_location', $_POST)) {
        update_post_meta($post_id, 'kseft_location', sanitize_text_field($_POST['kseft_location'])); // Uložení lokace
    }
    if (array_key_exists('kseft_meeting_time', $_POST)) {
        update_post_meta($post_id, 'kseft_meeting_time', sanitize_text_field($_POST['kseft_meeting_time'])); // Uložení času srazu
    }
    if (array_key_exists('kseft_event_date', $_POST)) {
        update_post_meta($post_id, 'kseft_event_date', sanitize_text_field($_POST['kseft_event_date'])); // Uložení data události
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_details_meta_box_data'); // Přidání akce pro uložení metaboxů

// Přidání metaboxu pro vlastnosti dopravy
function my_team_plugin_add_doprava_meta_boxes() {
    add_meta_box('doprava_seats', 'Počet míst', 'my_team_plugin_render_doprava_seats_meta_box', 'auta', 'normal', 'high'); // Přidání metaboxu pro počet míst
    add_meta_box('doprava_driver', 'Řidič', 'my_team_plugin_render_doprava_driver_meta_box', 'auta', 'normal', 'high'); // Přidání metaboxu pro řidiče
}
add_action('add_meta_boxes', 'my_team_plugin_add_doprava_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_doprava_seats_meta_box($post) {
    $seats = get_post_meta($post->ID, 'doprava_seats', true); // Získání počtu míst
    ?>
    <label for="doprava_seats">Počet míst:</label>
    <input type="number" name="doprava_seats" id="doprava_seats" value="<?php echo esc_attr($seats); ?>" size="25" /> <!-- Pole pro počet míst -->
    <?php
}

function my_team_plugin_render_doprava_driver_meta_box($post) {
    $driver = get_post_meta($post->ID, 'doprava_driver', true); // Získání řidiče
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1)); // Získání všech rolí
    ?>
    <label for="doprava_driver">Řidič:</label>
    <select name="doprava_driver" id="doprava_driver">
        <option value="">-- Vyberte řidiče --</option>
        <?php foreach ($roles as $role) : ?>
    $destination = sanitize_text_field($_GET['destination']);
            <?php $driver_name = get_post_meta($role->ID, 'role_default_player', true); ?>
            <option value="<?php echo esc_attr($driver_name); ?>" <?php selected($driver, $driver_name); ?>><?php echo esc_html($driver_name); ?></option>
        <?php endforeach; ?>
    </select> <!-- Výběr pro řidiče -->
    <?php
}

function my_team_plugin_save_doprava_meta_box_data($post_id) {
    if (array_key_exists('doprava_seats', $_POST)) {
        update_post_meta($post_id, 'doprava_seats', intval($_POST['doprava_seats'])); // Uložení počtu míst
    }
    if (array_key_exists('doprava_driver', $_POST)) {
        update_post_meta($post_id, 'doprava_driver', sanitize_text_field($_POST['doprava_driver'])); // Uložení řidiče
    }
}
add_action('save_post', 'my_team_plugin_save_doprava_meta_box_data'); // Přidání akce pro uložení metaboxů

// Přidání stránky nastavení pluginu
function my_team_plugin_add_settings_page() {
    add_options_page(
        'Nastavení Muzikantské kšefty',
        'Muzikantské kšefty',
        'manage_options',
        'my-team-plugin-settings',
        'my_team_plugin_render_settings_page'
    );
}
add_action('admin_menu', 'my_team_plugin_add_settings_page'); // Přidání akce pro přidání stránky nastavení

function my_team_plugin_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Nastavení Muzikantské kšefty</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('my_team_plugin_settings_group'); // Zobrazení polí nastavení
            do_settings_sections('my-team-plugin-settings'); // Zobrazení sekcí nastavení
            submit_button(); // Tlačítko pro uložení nastavení
            ?>
        </form>
    </div>
    <?php
}

function my_team_plugin_register_settings() {
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_google_maps_api_key'); // Registrace nastavení pro Google Maps API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_openrouteservice_api_key'); // Registrace nastavení pro OpenRouteService API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_openai_api_key'); // Registrace nastavení pro OpenAI API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_google_calendar_api_key'); // Registrace nastavení pro Google Calendar API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_manage_kseft_password'); // Registrace hesla pro správu Kšeftů

    add_settings_section(
        'my_team_plugin_settings_section',
        'Obecná nastavení',
        null,
        'my-team-plugin-settings'
    );

    add_settings_field(
        'my_team_plugin_google_maps_api_key',
        'Google Maps API Key',
        'my_team_plugin_google_maps_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_openrouteservice_api_key',
        'OpenRouteService API Key',
        'my_team_plugin_openrouteservice_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_openai_api_key',
        'OpenAI API Key',
        'my_team_plugin_openai_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_google_calendar_api_key',
        'Google Calendar API Key',
        'my_team_plugin_google_calendar_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_manage_kseft_password',
        'Heslo pro správu Kšeftů',
        'my_team_plugin_manage_kseft_password_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );
}
add_action('admin_init', 'my_team_plugin_register_settings');

function my_team_plugin_manage_kseft_password_callback() {
    $password = get_option('my_team_plugin_manage_kseft_password', 'heslo123');
    ?>
    <input type="text" name="my_team_plugin_manage_kseft_password" value="<?php echo esc_attr($password); ?>" size="50"> <!-- Pole pro heslo pro správu Kšeftů -->
    <?php
}

function my_team_plugin_google_maps_api_key_callback() {
    $api_key = get_option('my_team_plugin_google_maps_api_key'); // Získání Google Maps API klíče
    ?>
    <input type="text" name="my_team_plugin_google_maps_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro Google Maps API klíč -->
    <?php
}

function my_team_plugin_openrouteservice_api_key_callback() {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key'); // Získání OpenRouteService API klíče
    ?>
    <input type="text" name="my_team_plugin_openrouteservice_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro OpenRouteService API klíč -->
    <?php
}

function my_team_plugin_openai_api_key_callback() {
    $api_key = get_option('my_team_plugin_openai_api_key'); // Získání OpenAI API klíče
    ?>
    <input type="text" name="my_team_plugin_openai_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro OpenAI API klíč -->
    <?php
}

function my_team_plugin_google_calendar_api_key_callback() {
    $api_key = get_option('my_team_plugin_google_calendar_api_key'); // Získání Google Calendar API klíče
    ?>
    <input type="text" name="my_team_plugin_google_calendar_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro Google Calendar API klíč -->
    <?php
}

// Přidání sloupce pro oblečení do přehledu kšeftů
function my_team_plugin_add_clothing_column($columns) {
    $columns['kseft_clothing'] = 'Oblečení'; // Přidání sloupce pro oblečení
    return $columns;
}
add_filter('manage_kseft_posts_columns', 'my_team_plugin_add_clothing_column'); // Přidání filtru pro přidání sloupce

function my_team_plugin_display_clothing_column($column, $post_id) {
    if ($column === 'kseft_clothing') {
        $clothing = get_post_meta($post_id, 'kseft_clothing', true); // Získání oblečení
        echo esc_html($clothing); // Zobrazení oblečení
    }
}
add_action('manage_kseft_posts_custom_column', 'my_team_plugin_display_clothing_column', 10, 2); // Přidání akce pro zobrazení sloupce

// Registrace vlastních šablon stránek
function my_team_plugin_register_templates($templates) {
    $templates['manage-kseft.php'] = 'Manage Kseft'; // Registrace šablony pro správu kšeftu
    $templates['kseft-details.php'] = 'Kseft Details'; // Registrace šablony pro detaily kšeftu
    return $templates;
}
add_filter('theme_page_templates', 'my_team_plugin_register_templates'); // Přidání filtru pro registraci šablon

function my_team_plugin_load_template($template) {
    if (get_page_template_slug() === 'manage-kseft.php') {
        $template = plugin_dir_path(__FILE__) . 'templates/manage-kseft.php'; // Načtení šablony pro správu kšeftu
    } elseif (get_page_template_slug() === 'kseft-details.php') {
        $template = plugin_dir_path(__FILE__) . 'templates/kseft-details.php'; // Načtení šablony pro detaily kšeftu
    }
    return $template;
}
add_filter('template_include', 'my_team_plugin_load_template'); // Přidání filtru pro načtení šablon

function my_team_plugin_test_openai_api() {
    $result = test_openai_api(); // Testování OpenAI API

    if (isset($result['error'])) {
        wp_send_json_error(['error' => $result['error']]); // Odeslání chybové zprávy
    }

    wp_send_json_success(['response' => $result['response']]); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_test_openai_api', 'my_team_plugin_test_openai_api'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_test_openai_api', 'my_team_plugin_test_openai_api'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_event_details() {
    $post_id = intval($_POST['kseft_id']); // Získání ID příspěvku
    $event_date = get_post_meta($post_id, 'kseft_event_date', true); // Získání data události
    $meeting_time = get_post_meta($post_id, 'kseft_meeting_time', true); // Získání času srazu
    $kseft_name = get_the_title($post_id); // Získání názvu kšeftu
    $kseft_location = get_post_meta($post_id, 'kseft_location', true); // Získání lokace
    // Nová pole pro začátek a konec vystoupení:
    $performance_start = get_post_meta($post_id, 'kseft_performance_start', true);
    $performance_end = get_post_meta($post_id, 'kseft_performance_end', true);
    
    $response = array(
        'event_date' => $event_date,
        'meeting_time' => $meeting_time,
        'kseft_name' => $kseft_name,
        'kseft_location' => $kseft_location,
        'performance_start' => $performance_start,
        'performance_end' => $performance_end
    );
    
    wp_send_json_success($response); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_event_details', 'my_team_plugin_get_event_details'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_event_details', 'my_team_plugin_get_event_details'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_update_google_calendar_event() {
    error_log('my_team_plugin_update_google_calendar_event function called'); // Logování volání funkce
    $event_id = sanitize_text_field($_POST['event_id']); // Sanitizace ID události
    $event_details = $_POST['event_details']; // Získání detailů události

    error_log('AJAX request received to update Google Calendar event with ID: ' . $event_id); // Logování přijetí AJAX požadavku
    error_log('Event details: ' . print_r($event_details, true)); // Logování detailů události

    $result = updateGoogleCalendar($event_id, $event_details); // Aktualizace Google Kalendáře

    if ($result) {
        error_log('Google Calendar event updated successfully.'); // Logování úspěšné aktualizace
        wp_send_json_success(); // Odeslání úspěšné zprávy
    } else {
        error_log('Failed to update Google Calendar event.'); // Logování neúspěšné aktualizace
        wp_send_json_error(array('error' => 'Failed to update Google Calendar event.')); // Odeslání chybové zprávy
    }
}
add_action('wp_ajax_update_google_calendar_event', 'my_team_plugin_update_google_calendar_event'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_update_google_calendar_event', 'my_team_plugin_update_google_calendar_event'); // Přidání AJAX akce pro nepřihlášené uživatele

// Přidání funkce pro smazání Google akce
function delete_google_calendar_event($event_id) {
    $credentials_path = plugin_dir_path(__FILE__) . 'includes/credential.json'; // Ujistěte se, že cesta je správná
    if (!file_exists($credentials_path)) {
        return false;
    }

    $client = new Google_Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google_Service_Calendar::CALENDAR);

    $service = new Google_Service_Calendar($client);

    try {
        $calendarId = 'olo0v28necdv27n6mg7psud2dc@group.calendar.google.com';
        $service->events->delete($calendarId, $event_id);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Přidání akce pro smazání Google akce při smazání kšeftu
function my_team_plugin_delete_kseft($post_id) {
    if (get_post_type($post_id) === 'kseft') {
        $google_event_id = get_post_meta($post_id, 'google_calendar_event_id', true);
        if ($google_event_id) {
            delete_google_calendar_event($google_event_id);
        }
    }
}
add_action('before_delete_post', 'my_team_plugin_delete_kseft'); // Přidání akce pro smazání Google akce při smazání kšeftu

// Přidání přesměrování po smazání kšeftu
function my_team_plugin_redirect_after_delete() {
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['post']) && get_post_type($_GET['post']) === 'kseft') {
        wp_redirect(site_url('/ksefty'));
        exit;
    }
}
add_action('admin_init', 'my_team_plugin_redirect_after_delete'); // Přidání akce pro přesměrování po smazání kšeftu

function my_team_plugin_add_kseft_overview_page() {
    add_menu_page(
        'Přehled Kšeftů',
        'Přehled Kšeftů',
        'manage_options',
        'kseft-overview',
        'my_team_plugin_render_kseft_overview_page',
        'dashicons-calendar-alt',
        6
    );
}
add_action('admin_menu', 'my_team_plugin_add_kseft_overview_page'); // Přidání akce pro přidání stránky přehledu kšeftů

function my_team_plugin_render_kseft_overview_page() {
    ?>
    <div class="wrap">
        <h1>Přehled Akcí</h1>
        <?php echo do_shortcode('[kseft_overview]'); ?>
    </div>
    <?php
}

function my_team_plugin_kseft_overview_shortcode() {
    ob_start();
    ?>
    <div id="selected-role-display" style="margin-bottom: 20px; font-weight: bold; cursor: pointer;"></div>
    <?php include plugin_dir_path(__FILE__) . 'templates/role-selection-modal.php'; ?>
    <table id="kseft-overview-table">
        <thead>
            <tr>
                <th>Termín</th>
                <th>Název</th>
                <th>Lokace</th>
                <th>Stav</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $args = array(
                'post_type' => 'kseft', // Typ příspěvku
                'post_status' => 'publish', // Stav příspěvku
                'posts_per_page' => -1, // Počet příspěvků na stránku
                'meta_key' => 'kseft_event_date', // Klíč pro řazení
                'orderby' => 'meta_value', // Řazení podle meta hodnoty
                'order' => 'ASC' // Řazení vzestupně
            );
            $ksefty = new WP_Query($args); // Dotaz na příspěvky
            if ($ksefty->have_posts()) {
                while ($ksefty->have_posts()) {
                    $ksefty->the_post();
                    $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true); // Získání data události
                    $location = get_post_meta(get_the_ID(), 'kseft_location', true); // Získání lokace
                    $status = get_post_meta(get_the_ID(), 'kseft_status', true); // Získání stavu
                    $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
                    $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
                    $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
                    ?>
                    <tr data-kseft-id="<?php echo get_the_ID(); ?>" data-role-ids='<?php echo json_encode($roles); ?>'>
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($formatted_date); ?></a></td> <!-- Odkaz na termín -->
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title(); ?></a></td> <!-- Odkaz na název -->
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($location); ?></a></td> <!-- Odkaz na lokaci -->
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($status); ?></a></td> <!-- Odkaz na stav -->
                        <td><button class="button confirm-role-button" data-kseft-id="<?php echo get_the_ID(); ?>">Potvrdit účast</button></td> <!-- Tlačítko pro potvrzení účasti -->
                    </tr>
                    <?php
                }
                wp_reset_postdata(); // Resetování dotazu
            } else {
                ?>
                <tr>
                    <td colspan="5">Žádné akce nejsou k dispozici.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?php include plugin_dir_path(__FILE__) . 'templates/role-confirmation-modal.php'; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('kseft_overview', 'my_team_plugin_kseft_overview_shortcode'); // Přidání shortcode pro přehled kšeftů

function my_team_plugin_get_ksefty_by_role() {
    $role_id = intval($_POST['role_id']); // Získání ID role
    $ksefty = get_posts(array(
        'post_type' => 'kseft', // Typ příspěvku
        'numberposts' => -1, // Počet příspěvků
        'meta_query' => array(
            array(
                'key' => 'kseft_obsazeni_template', // Klíč pro porovnání
                'compare' => 'EXISTS' // Porovnání
            )
        )
    ));

    $kseft_ids = array();
    foreach ($ksefty as $kseft) {
        $obsazeni_template_id = get_post_meta($kseft->ID, 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
        $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
        if (is_array($roles) && in_array($role_id, $roles)) {
            $kseft_ids[] = $kseft->ID; // Přidání ID kšeftu do pole
        }
    }

    wp_send_json_success($kseft_ids); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_ksefty_by_role', 'my_team_plugin_get_ksefty_by_role'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_ksefty_by_role', 'my_team_plugin_get_ksefty_by_role'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_role_details() {
    $role_id = intval($_POST['role_id']); // Získání ID role
    $default_player = get_post_meta($role_id, 'role_default_player', true); // Získání výchozího hráče
    $default_pickup_location = get_post_meta($role_id, 'role_default_pickup_location', true); // Získání výchozího místa vyzvednutí

    $response = array(
        'default_player' => $default_player,
        'default_pickup_location' => $default_pickup_location
    );

    wp_send_json_success($response); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_role_details', 'my_team_plugin_get_role_details'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_role_details', 'my_team_plugin_get_role_details'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_role_status() {
    $kseft_id = intval($_POST['kseft_id']); // Získání ID kšeftu
    $role_id = intval($_POST['role_id']); // Získání ID role
    $role_status = get_post_meta($kseft_id, 'role_status_' . $role_id, true); // Získání stavu role

    $response = array(
        'role_status' => $role_status
    );

    wp_send_json_success($response); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_role_status', 'my_team_plugin_get_role_status'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_role_status', 'my_team_plugin_get_role_status'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_log_error($message) {
    $log_file = plugin_dir_path(__FILE__) . '../custom_error_log.log'; // Cesta k log souboru
    $timestamp = date('Y-m-d H:i:s'); // Získání aktuálního času
    error_log("[$timestamp] $message\n", 3, $log_file); // Zápis zprávy do log souboru
}


function my_team_plugin_check_password() {
    if (is_page('ksefty') && !is_user_logged_in()) {
        if (isset($_COOKIE['manageKseftAccess']) && $_COOKIE['manageKseftAccess'] === md5(get_option('my_team_plugin_manage_kseft_password', 'heslo123'))) {
            return; // Pokud je cookie nastavena, nevyžadovat heslo znovu
        }
        $password = get_option('my_team_plugin_manage_kseft_password', 'heslo123');
        if (!isset($_POST['manage_kseft_password']) || $_POST['manage_kseft_password'] !== $password) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_kseft_password'])) {
                echo '<p style="color:red;">Přístup zamítnut. Nesprávné heslo.</p>';
            }
            ?>
            <!DOCTYPE html>
            <html lang="cs">
            <head>
                <meta charset="UTF-8">
                <title>Přihlášení</title>
                <style>
                    body {
                        background-color: #f0f0f0;
                        font-family: Arial, sans-serif;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .login-container {
                        background: #fff;
                        padding: 30px;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        text-align: center;
                    }
                    .login-container h2 {
                        margin-bottom: 20px;
                        color: #0073aa;
                    }
                    .login-container input[type="password"] {
                        width: 80%;
                        padding: 10px;
                        margin-bottom: 15px;
                        border: 1px solid #ccc;
                        border-radius: 4px;
                        font-size: 16px;
                    }
                    .login-container input[type="submit"] {
                        padding: 10px 20px;
                        background-color: #0073aa;
                        color: #fff;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 16px;
                    }
                    .login-container input[type="submit"]:hover {
                        background-color: #005177;
                    }
                </style>
            </head>
            <body>
                <div class="login-container">
                    <h2>Prosím zadejte heslo</h2>
                    <form method="post">
                        <input type="password" name="manage_kseft_password" placeholder="Heslo">
                        <br>
                        <input type="submit" value="Přihlásit">
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            setcookie('manageKseftAccess', md5($password), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
    } elseif (is_page('moje-ksefty') && !is_user_logged_in()) {
        // Pokud cookie existuje, ale je neplatná (např. "undefined"), vymažeme ji
        if (isset($_COOKIE['selectedRoleId']) && ($_COOKIE['selectedRoleId'] === 'undefined' || !ctype_digit($_COOKIE['selectedRoleId']))) {
            setcookie('selectedRoleId', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
        // Nová část: pokud již byla role odeslána, proveď okamžitý redirect před výstupem
        if (isset($_POST['selected_role_id'])) {
            $selected_role_id = intval($_POST['selected_role_id']);
            $role = get_post($selected_role_id);
            $role_title = $role ? $role->post_title : 'Neznámá role';
            setcookie('selectedRoleId', $selected_role_id, time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            setcookie('selectedRoleText', urlencode($role_title), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            wp_redirect(site_url('/moje-ksefty'));
            exit;
        }
        // Pokud cookie již existuje, nic neprovádíme
        if (isset($_COOKIE['selectedRoleId'])) {
            return; // Pokud je role již vybrána, nevyžadovat heslo znovu
        }
        $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
        $role_passwords = array();
        foreach ($roles as $role) {
            $role_passwords[$role->ID] = get_post_meta($role->ID, 'role_password', true);
        }
        $valid_password = false;
        $matching_roles = array();
        if (isset($_POST['role_password']) && !empty($_POST['role_password'])) {
            foreach ($role_passwords as $role_id => $password) {
                if ($_POST['role_password'] === $password) {
                    $valid_password = true;
                    $matching_roles[] = $role_id;
                }
            }
        }
        if (!$valid_password) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_password'])) {
                echo '<p style="color:red;">Přístup zamítnut. Nesprávné heslo.</p>';
            }
            ?>
            <!DOCTYPE html>
            <html lang="cs">
            <head>
                <meta charset="UTF-8">
                <title>Přihlášení</title>
                <style>
                    body {
                        background-color: #f0f0f0;
                        font-family: Arial, sans-serif;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .login-container {
                        background: #fff;
                        padding: 30px;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        text-align: center;
                    }
                    .login-container h2 {
                        margin-bottom: 20px;
                        color: #0073aa;
                    }
                    .login-container input[type="password"] {
                        width: 80%;
                        padding: 10px;
                        margin-bottom: 15px;
                        border: 1px solid #ccc;
                        border-radius: 4px;
                        font-size: 16px;
                    }
                    .login-container input[type="submit"] {
                        padding: 10px 20px;
                        background-color: #0073aa;
                        color: #fff;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 16px;
                    }
                    .login-container input[type="submit"]:hover {
                        background-color: #005177;
                    }
                </style>
            </head>
            <body>
                <div class="login-container">
                    <h2>Prosím zadejte heslo</h2>
                    <form method="post">
                        <input type="password" name="role_password" placeholder="Heslo">
                        <br>
                        <input type="submit" value="Přihlásit">
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit;
        } elseif (count($matching_roles) > 1) {
            // Přidáno: uložení cookie s povolenými rolemi
            setcookie('allowedRoles', implode(',', $matching_roles), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            ?>
            <!DOCTYPE html>
            <html lang="cs">
            <head>
                <meta charset="UTF-8">
                <title>Výběr role</title>
                <style>
                    body {
                        background-color: #f0f0f0;
                        font-family: Arial, sans-serif;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .login-container {
                        background: #fff;
                        padding: 30px;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        text-align: center;
                    }
                    .login-container h2 {
                        margin-bottom: 20px;
                        color: #0073aa;
                    }
                    .login-container select {
                        width: 80%;
                        padding: 10px;
                        margin-bottom: 15px;
                        border: 1px solid #ccc;
                        border-radius: 4px;
                        font-size: 16px;
                    }
                    .login-container input[type="submit"] {
                        padding: 10px 20px;
                        background-color: #0073aa;
                        color: #fff;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 16px;
                    }
                    .login-container input[type="submit"]:hover {
                        background-color: #005177;
                    }
                </style>
            </head>
            <body>
                <div class="login-container">
                    <h2>Vyberte roli</h2>
                    <form method="post">
                        <select name="selected_role_id">
                            <?php foreach ($matching_roles as $role_id) : ?>
                                <option value="<?php echo esc_attr($role_id); ?>"><?php echo esc_html(get_the_title($role_id)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br>
                        <input type="submit" value="Přihlásit">
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            $selected_role_id = $matching_roles[0];
            $role = get_post($selected_role_id);
            $role_title = $role ? $role->post_title : 'Neznámá role';
            setcookie('selectedRoleId', $selected_role_id, time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            setcookie('selectedRoleText', urlencode($role_title), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            setcookie('allowedRoles', implode(',', $matching_roles), time() + 3600, COOKIEPATH, COOKIE_DOMAIN); // Nastavení cookie allowedRoles
            wp_redirect(site_url('/moje-ksefty'));
            exit;
        }
    }
}
add_action('template_redirect', 'my_team_plugin_check_password');

function my_team_plugin_display_selected_role() {
    if (isset($_COOKIE['selectedRoleId'])) {
        $role_id = intval($_COOKIE['selectedRoleId']);
        $role_title = urldecode($_COOKIE['selectedRoleText']);
        $allowed_roles = isset($_COOKIE['allowedRoles']) ? explode(',', $_COOKIE['allowedRoles']) : array();
        echo '<div id="selected-role-display" style="cursor: pointer;">Zvolená role: ' . esc_html($role_title);
        if (count($allowed_roles) > 1) {
            echo ' (klikněte pro změnu)';
        }
        echo '</div>';
    }
}
add_action('wp_footer', 'my_team_plugin_display_selected_role');

?>
