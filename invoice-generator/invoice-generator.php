<?php
/*
Plugin Name: Invoice Generator
Description: Generuje faktury s QR kódem pro každou kartu kšeftu.
Version: 1.0
Author: Vaše Jméno
*/

function invoice_generator_enqueue_scripts() {
    wp_enqueue_style('invoice-generator-style', plugins_url('/css/invoice-generator.css', __FILE__)); // Načtení CSS
}
add_action('wp_enqueue_scripts', 'invoice_generator_enqueue_scripts');

function invoice_generator_add_meta_box() {
    add_meta_box(
        'invoice_details',
        'Fakturační údaje',
        'invoice_generator_render_meta_box',
        'kseft',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes Invoice', 'invoice_generator_add_meta_box');

function invoice_generator_render_meta_box($post) {
    $invoice_data = get_post_meta($post->ID, 'invoice_data', true);
    $invoice_data = wp_parse_args($invoice_data, array(
        'amount' => '',
        'variable_symbol' => ''
    ));
    ?>
    <label for="invoice_amount">Částka:</label>
    <input type="number" name="invoice_data[amount]" id="invoice_amount" value="<?php echo esc_attr($invoice_data['amount']); ?>" step="0.01" /><br><br>
    <label for="invoice_variable_symbol">Variabilní symbol:</label>
    <input type="text" name="invoice_data[variable_symbol]" id="invoice_variable_symbol" value="<?php echo esc_attr($invoice_data['variable_symbol']); ?>" /><br><br>
    <?php
}

function invoice_generator_save_meta_box_data($post_id) {
    if (isset($_POST['invoice_data'])) {
        update_post_meta($post_id, 'invoice_data', $_POST['invoice_data']);
    }
}
add_action('save_post', 'invoice_generator_save_meta_box_data');

function invoice_generator_display_invoice($content) {
    if (is_singular('kseft')) {
        $post_id = get_the_ID();
        $invoice_data = get_post_meta($post_id, 'invoice_data', true);
        $logo_url = get_option('invoice_generator_logo', '');

        if ($invoice_data) {
            $account_number = get_option('invoice_generator_account_number', '');
            $bank_code = get_option('invoice_generator_bank_code', '');
            $default_due_days = get_option('invoice_generator_default_due_days', 14);
            $event_date = get_post_meta($post_id, 'kseft_event_date', true);
            $due_date = date('Y-m-d', strtotime($event_date . " + $default_due_days days"));

            $qr_code_url = invoice_generator_generate_qr_code(array_merge($invoice_data, [
                'account_number' => $account_number,
                'bank_code' => $bank_code,
                'due_date' => $due_date
            ]));

            if (!empty($logo_url)) {
                $content .= '<img src="' . esc_url($logo_url) . '" alt="Logo" style="max-width: 200px; margin-bottom: 20px;" />';
            }
            $content .= '<h3>Fakturační údaje</h3>';
            $content .= '<p><strong>Částka:</strong> ' . esc_html($invoice_data['amount']) . ' Kč</p>';
            $content .= '<p><strong>Číslo účtu:</strong> ' . esc_html($account_number) . '/' . esc_html($bank_code) . '</p>';
            $content .= '<p><strong>Datum splatnosti:</strong> ' . esc_html($due_date) . '</p>';
            $content .= '<img src="' . esc_url($qr_code_url) . '" alt="QR Platba" />';
        }
    }
    return $content;
}
add_filter('the_content_Invoice', 'invoice_generator_display_invoice');

function invoice_generator_generate_qr_code($invoice_data) {
    // Sestavení řetězce podle specifikace QR Platba
    $qr_string = sprintf(
        'SPD*1.0*ACC:%s*AM:%s*CC:CZK*X-VS:%s*DT:%s',
        $invoice_data['account_number'] . '/' . $invoice_data['bank_code'], // Číslo účtu a kód banky
        number_format($invoice_data['amount'], 2, '.', ''), // Částka ve formátu s desetinnou tečkou
        $invoice_data['variable_symbol'], // Variabilní symbol
        $invoice_data['due_date'] // Datum splatnosti
    );

    // URL pro generování QR kódu
    $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
    $params = array(
        'data' => $qr_string, // Data pro QR kód
        'size' => '200x200' // Velikost QR kódu
    );

    // Odeslání požadavku na API
    $response = wp_remote_get($api_url . '?' . http_build_query($params));
    if (is_wp_error($response)) {
        return ''; // Vrátíme prázdný řetězec v případě chyby
    }

    // Vrátíme URL QR kódu
    return $api_url . '?' . http_build_query($params);
}

function invoice_generator_update_status() {
    check_ajax_referer('update_invoice_status', 'nonce');
    $post_id = intval($_POST['post_id']);
    $status = sanitize_text_field($_POST['status']);
    $invoice_data = get_post_meta($post_id, 'invoice_data', true);
    $invoice_data['status'] = $status;
    update_post_meta($post_id, 'invoice_data', $invoice_data);
    wp_send_json_success();
}
add_action('wp_ajax_update_invoice_status', 'invoice_generator_update_status');

function invoice_generator_register_templates($templates) {
    $templates['invoice-overview.php'] = 'Invoice Overview'; // Registrace šablony pro přehled faktur
    $templates['invoice-details.php'] = 'Invoice Details'; // Registrace šablony pro detaily faktury
    return $templates;
}
add_filter('theme_page_templates', 'invoice_generator_register_templates'); // Přidání filtru pro registraci šablon

function invoice_generator_load_template($template) {
    if (is_page_template('invoice-overview.php')) {
        $template = plugin_dir_path(__FILE__) . 'invoice-overview.php'; // Načtení šablony pro přehled faktur
    } elseif (is_page_template('invoice-details.php') || isset($_GET['invoice_id'])) {
        $template = plugin_dir_path(__FILE__) . 'invoice-details.php'; // Načtení šablony pro detaily faktury
    }
    return $template;
}
add_filter('template_include', 'invoice_generator_load_template'); // Přidání filtru pro načtení šablon

function invoice_generator_add_settings_page() {
    add_options_page(
        'Nastavení Invoice Generator',
        'Invoice Generator',
        'manage_options',
        'invoice-generator-settings',
        'invoice_generator_render_settings_page'
    );
}
add_action('admin_menu', 'invoice_generator_add_settings_page');

function invoice_generator_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Nastavení Invoice Generator</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('invoice_generator_settings_group');
            do_settings_sections('invoice-generator-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function invoice_generator_register_settings() {
    register_setting('invoice_generator_settings_group', 'invoice_generator_password');
    register_setting('invoice_generator_settings_group', 'invoice_generator_account_number'); // Číslo účtu
    register_setting('invoice_generator_settings_group', 'invoice_generator_bank_code'); // Kód banky
    register_setting('invoice_generator_settings_group', 'invoice_generator_default_due_days'); // Výchozí splatnost
    register_setting('invoice_generator_settings_group', 'invoice_generator_logo'); // Logo

    add_settings_section(
        'invoice_generator_settings_section',
        'Obecná nastavení',
        null,
        'invoice-generator-settings'
    );

    add_settings_field(
        'invoice_generator_password',
        'Heslo pro přístup k fakturám',
        'invoice_generator_password_callback',
        'invoice-generator-settings',
        'invoice_generator_settings_section'
    );

    add_settings_field(
        'invoice_generator_account_number',
        'Číslo účtu',
        'invoice_generator_account_number_callback',
        'invoice-generator-settings',
        'invoice_generator_settings_section'
    );

    add_settings_field(
        'invoice_generator_bank_code',
        'Kód banky',
        'invoice_generator_bank_code_callback',
        'invoice-generator-settings',
        'invoice_generator_settings_section'
    );

    add_settings_field(
        'invoice_generator_default_due_days',
        'Výchozí splatnost (dny)',
        'invoice_generator_default_due_days_callback',
        'invoice-generator-settings',
        'invoice_generator_settings_section'
    );

    add_settings_field(
        'invoice_generator_logo',
        'Logo (obrázek)',
        'invoice_generator_logo_callback',
        'invoice-generator-settings',
        'invoice_generator_settings_section'
    );
}
add_action('admin_init', 'invoice_generator_register_settings'); // Správné volání pro registraci nastavení

function invoice_generator_password_callback() {
    $password = get_option('invoice_generator_password', '');
    ?>
    <input type="password" name="invoice_generator_password" value="<?php echo esc_attr($password); ?>" size="50">
    <?php
}

function invoice_generator_account_number_callback() {
    $account_number = get_option('invoice_generator_account_number', '');
    ?>
    <input type="text" name="invoice_generator_account_number" value="<?php echo esc_attr($account_number); ?>" size="50">
    <?php
}

function invoice_generator_bank_code_callback() {
    $bank_code = get_option('invoice_generator_bank_code', '');
    ?>
    <input type="text" name="invoice_generator_bank_code" value="<?php echo esc_attr($bank_code); ?>" size="50">
    <?php
}

function invoice_generator_default_due_days_callback() {
    $default_due_days = get_option('invoice_generator_default_due_days', 14); // Výchozí hodnota 14 dní
    ?>
    <input type="number" name="invoice_generator_default_due_days" value="<?php echo esc_attr($default_due_days); ?>" size="5">
    <?php
}

function invoice_generator_logo_callback() {
    $logo_url = get_option('invoice_generator_logo', '');
    ?>
    <input type="hidden" id="invoice_generator_logo" name="invoice_generator_logo" value="<?php echo esc_attr($logo_url); ?>">
    <button type="button" class="button" id="upload_logo_button">Nahrát logo</button>
    <div id="logo_preview" style="margin-top: 10px;">
        <?php if ($logo_url): ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 200px;">
        <?php endif; ?>
    </div>
    <?php
}

function invoice_generator_protect_pages() {
    if (is_page_template('invoice-overview.php') || is_page_template('invoice-details.php')) {
        $password = get_option('invoice_generator_password', '');
        if (empty($password)) {
            return; // Pokud není heslo nastaveno, stránka není chráněna
        }

        // Pokud je heslo již uloženo v cookie, nevyžadujeme ho znovu
        if (isset($_COOKIE['invoice_generator_access']) && $_COOKIE['invoice_generator_access'] === md5($password)) {
            return;
        }

        // Pokud heslo nebylo zadáno, zobrazíme formulář
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_generator_password'])) {
            if ($_POST['invoice_generator_password'] === $password) {
                setcookie('invoice_generator_access', md5($password), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
                wp_redirect($_SERVER['REQUEST_URI']);
                exit;
            } else {
                echo '<p style="color:red;">Nesprávné heslo.</p>';
            }
        }

        // Formulář pro zadání hesla
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <title>Přístup chráněn heslem</title>
            <style>
                body {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    font-family: Arial, sans-serif;
                    background-color: #f9f9f9;
                    margin: 0;
                }
                .password-container {
                    text-align: center;
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                }
                .password-container input[type="password"] {
                    padding: 10px;
                    font-size: 16px;
                    margin-bottom: 10px;
                    width: 100%;
                    box-sizing: border-box;
                }
                .password-container input[type="submit"] {
                    padding: 10px 20px;
                    font-size: 16px;
                    background-color: #0073aa;
                    color: #fff;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                .password-container input[type="submit"]:hover {
                    background-color: #005177;
                }
            </style>
        </head>
        <body>
            <div class="password-container">
                <h2>Chráněná stránka</h2>
                <form method="post">
                    <input type="password" name="invoice_generator_password" placeholder="Zadejte heslo">
                    <br>
                    <input type="submit" value="Přihlásit">
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
add_action('template_redirect', 'invoice_generator_protect_pages');

function invoice_generator_enqueue_admin_scripts($hook) {
    if ($hook === 'settings_page_invoice-generator-settings') {
        wp_enqueue_media(); // Načtení Media Uploaderu pouze na stránce nastavení pluginu
        wp_enqueue_script('invoice-generator-admin-script', plugins_url('/js/admin.js', __FILE__), array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'invoice_generator_enqueue_admin_scripts');
?>
