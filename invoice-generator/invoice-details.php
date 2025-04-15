<?php
/*
Template Name: Invoice Details
*/

// Načtení funkcí pluginu
require_once plugin_dir_path(__FILE__) . 'functions.php';

global $wp_query;

// Získání ID faktury z parametru URL
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
if (!$invoice_id) {
    $wp_query->set_404();
    status_header(404);
    get_template_part(404);
    exit;
}

// Explicitně nastavíme, že stránka není 404
$wp_query->is_404 = false;

// Načtení dat faktury nebo vytvoření výchozích dat, pokud faktura neexistuje
$invoice_data = get_post_meta($invoice_id, 'invoice_data', true);
if (empty($invoice_data)) {
    $invoice_data = array(
        'amount' => '',
        'variable_symbol' => '',
        'reference_number' => '', // Přidáno referenční číslo
        'payment_method' => 'Bankovní převod', // Výchozí hodnota
        'status' => 'Nová',
        'message' => '',
        'customer_name' => '',
        'customer_address' => '',
        'customer_phone' => '',
        'customer_ico' => ''
    );
    update_post_meta($invoice_id, 'invoice_data', $invoice_data);
}

$invoice_data = wp_parse_args($invoice_data, array(
    'amount' => '',
    'variable_symbol' => '',
    'reference_number' => '', // Přidáno referenční číslo
    'payment_method' => 'Bankovní převod', // Výchozí hodnota
    'status' => 'Nová',
    'message' => '',
    'customer_name' => '',
    'customer_address' => '',
    'customer_phone' => '',
    'customer_ico' => ''
));

// Získání názvu kšeftu
$event_title = get_post_meta($invoice_id, 'kseft_event_title', true);
if (!$event_title) {
    $event_title = get_the_title($invoice_id);
}

// Kontrola, zda existuje PDF faktura v upload složce
$upload_dir = wp_upload_dir();
$pdf_file_path = $upload_dir['basedir'] . '/invoices/faktura-' . $invoice_id . '.pdf';
$pdf_file_url = $upload_dir['baseurl'] . '/invoices/faktura-' . $invoice_id . '.pdf';

if (file_exists($pdf_file_path)) {
    $invoice_data['pdf_invoice_url'] = $pdf_file_url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_data'])) {
    check_admin_referer('save_invoice_details');

    // Získáme aktuální data faktury
    $invoice_data = get_post_meta($invoice_id, 'invoice_data', true);
    $invoice_data = wp_parse_args($invoice_data, array(
        'amount' => '',
        'variable_symbol' => '',
        'reference_number' => '', // Přidáno referenční číslo
        'payment_method' => 'Bankovní převod', // Výchozí hodnota
        'status' => 'Nová',
        'message' => '',
        'customer_name' => '',
        'customer_address' => '',
        'customer_phone' => '',
        'customer_ico' => ''
    ));

    // Aktualizujeme data z formuláře
    $invoice_data['amount'] = isset($_POST['invoice_data']['amount']) ? sanitize_text_field($_POST['invoice_data']['amount']) : '';
    $invoice_data['variable_symbol'] = isset($_POST['invoice_data']['variable_symbol']) ? sanitize_text_field($_POST['invoice_data']['variable_symbol']) : '';
    $invoice_data['reference_number'] = isset($_POST['invoice_data']['reference_number']) ? sanitize_text_field($_POST['invoice_data']['reference_number']) : ''; // Přidáno referenční číslo
    $invoice_data['payment_method'] = isset($_POST['invoice_data']['payment_method']) ? sanitize_text_field($_POST['invoice_data']['payment_method']) : 'Bankovní převod'; // Výchozí hodnota
    $invoice_data['status'] = isset($_POST['invoice_data']['status']) ? sanitize_text_field($_POST['invoice_data']['status']) : '';
    $invoice_data['message'] = isset($_POST['invoice_data']['message']) ? sanitize_text_field($_POST['invoice_data']['message']) : '';
    $invoice_data['customer_name'] = isset($_POST['invoice_data']['customer_name']) ? sanitize_text_field($_POST['invoice_data']['customer_name']) : '';
    $invoice_data['customer_address'] = isset($_POST['invoice_data']['customer_address']) ? sanitize_text_field($_POST['invoice_data']['customer_address']) : '';
    $invoice_data['customer_phone'] = isset($_POST['invoice_data']['customer_phone']) ? sanitize_text_field($_POST['invoice_data']['customer_phone']) : '';
    $invoice_data['customer_ico'] = isset($_POST['invoice_data']['customer_ico']) ? sanitize_text_field($_POST['invoice_data']['customer_ico']) : '';

    // Uložíme aktualizovaná data
    update_post_meta($invoice_id, 'invoice_data', $invoice_data);

    echo '<div class="updated"><p>Faktura byla úspěšně uložena.</p></div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr_code'])) {
    $account_number = get_option('invoice_generator_account_number', '');
    $bank_code = get_option('invoice_generator_bank_code', '');
    $default_due_days = get_option('invoice_generator_default_due_days', 14);
    $event_date = get_post_meta($invoice_id, 'kseft_event_date', true);

    if (!empty($event_date) && strtotime($event_date) !== false) {
        $due_date = date('Y-m-d', strtotime($event_date . " + $default_due_days days"));
    } else {
        $due_date = date('Y-m-d', strtotime("+$default_due_days days"));
    }

    $invoice_data['due_date'] = $due_date;

    $qr_code_data = array(
        'account_number' => $account_number,
        'bank_code' => $bank_code,
        'amount' => $invoice_data['amount'],
        'variable_symbol' => $invoice_data['variable_symbol'],
        'due_date' => $due_date,
        'message' => $invoice_data['message']
    );

    $invoice_data['qr_code_request_data'] = $qr_code_data;

    $qr_code_url = invoice_generator_generate_qr_code($qr_code_data);
    if ($qr_code_url) {
        update_post_meta($invoice_id, 'qr_code_url', $qr_code_url);
        $invoice_data['qr_code_url'] = $qr_code_url;
    } else {
        $invoice_data['qr_code_error'] = 'Chyba při generování QR kódu. Zkontrolujte nastavení pluginu.';
    }

    update_post_meta($invoice_id, 'invoice_data', $invoice_data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf_invoice'])) {
    $pdf_url = invoice_generator_generate_pdf($invoice_id, $invoice_data, 'faktura');
    if ($pdf_url) {
        $invoice_data['pdf_invoice_url'] = $pdf_url;
        echo '<div class="updated"><p>PDF faktura byla úspěšně vygenerována. <a href="' . esc_url($pdf_url) . '" target="_blank">Stáhnout fakturu</a></p></div>';
    } else {
        $error_message = 'Chyba při generování PDF faktury. Zkontrolujte nastavení pluginu.';
        echo '<div class="error"><p>' . esc_html($error_message) . '</p></div>';
        echo '<script>console.error("' . esc_js($error_message) . '");</script>';
    }
}

$qr_code_url = isset($invoice_data['qr_code_url']) ? $invoice_data['qr_code_url'] : get_post_meta($invoice_id, 'qr_code_url', true);
$qr_code_error = isset($invoice_data['qr_code_error']) ? $invoice_data['qr_code_error'] : '';

$due_date_display = isset($invoice_data['due_date']) && strtotime($invoice_data['due_date']) !== false
    ? date('d.m.Y', strtotime($invoice_data['due_date']))
    : '';

$formatted_amount = (isset($invoice_data['amount']) && is_numeric($invoice_data['amount'])) 
    ? number_format((float)$invoice_data['amount'], 2, ',', ' ') . ' Kč' 
    : '0,00 Kč';

get_header();
?>
<style>
    body {
        background-color: rgb(31, 31, 31);
        background-image: none;
    }
    .invoice-buttons {
        display: flex;
        justify-content: center; /* Zarovnání tlačítek na střed */
        gap: 20px; /* Mezera mezi tlačítky */
        margin-top: 20px;
    }
    .invoice-buttons form {
        margin: 0;
    }
    .invoice-sections {
        display: flex;
        gap: 20px;
        margin-top: 30px;
    }
    .invoice-section {
        flex: 1;
        text-align: center; /* Zarovnání obsahu sekce na střed */
    }
    .invoice-section img {
        max-width: 100%;
        height: auto;
        margin: 0 auto; /* Zarovnání obrázku na střed */
    }
    table.form-table {
        background-color: rgb(31, 31, 31); /* Šedé pozadí tabulky */
        border-collapse: collapse;
        width: 100%;
    }
    table.form-table th, table.form-table td {
        padding: 10px;
        border: 1px solid #ddd; /* Jemné ohraničení */
    }
    table.form-table th {
        background-color: rgb(20, 20, 20); /* O něco tmavší šedá pro hlavičky */
        text-align: left;
    }
    input[type="text"], input[type="number"], textarea, select {
        background-color:rgb(20, 20, 20); /* Stejná barva jako tlačítka */
        color: #fff; /* Bílý text */
        border: none;
        padding: 10px;
        border-radius: 20px; /* Kulaté rohy */
        width: 100%;
        box-sizing: border-box;
        margin-bottom: 10px;
    }
    input[type="text"]::placeholder, textarea::placeholder {
        color: #d9d9d9; /* Světlejší text pro placeholder */
    }
    input[type="text"]:focus, input[type="number"]:focus, textarea:focus, select:focus {
        outline: none;
        box-shadow: 0 0 5px rgb(20, 20, 20); /* Zvýraznění při zaostření */
    }
    /* Červený rámeček pro neuložená pole */
    .unsaved {
        border: 2px solid red !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                input.classList.add('unsaved'); // Přidá červený rámeček při změně
            });
        });

        form.addEventListener('submit', () => {
            inputs.forEach(input => {
                input.classList.remove('unsaved'); // Odebere červený rámeček po uložení
            });
        });

        const icoInput = document.getElementById('invoice_customer_ico');
        const nameInput = document.getElementById('invoice_customer_name');
        const addressInput = document.getElementById('invoice_customer_address');

        icoInput.addEventListener('blur', async function () {
            const ico = icoInput.value.trim();
            console.log('Hodnota IČO:', ico);

            if (ico) {
                // Přímé volání API ARES bez interního odkazu
                const requestUrl = `https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/${ico}`;
                console.log('Odeslaný request:', requestUrl);
                
                try {
                    const response = await fetch(requestUrl);
                    console.log('Stav odpovědi:', response.status, response.statusText);
                    
                    if (response.ok) {
                        const data = await response.json();
                        console.log('Odpověď z ARES API:', data);

                        if (data && data.obchodniJmeno) {
                            nameInput.value = data.obchodniJmeno;
                            
                            // Použijeme textovaAdresa, která je nyní pod záložkou "sidlo"
                            if (data.sidlo && data.sidlo.textovaAdresa) {
                                addressInput.value = data.sidlo.textovaAdresa;
                            } else {
                                addressInput.value = '';
                            }
                            nameInput.classList.remove('unsaved');
                            addressInput.classList.remove('unsaved');
                        } else {
                            console.error('Data z ARES API nejsou kompletní nebo neplatná.', data);
                        }
                    } else {
                        const errorText = await response.text();
                        console.error('Chyba při načítání údajů z ARES:', response.status, response.statusText, errorText);
                    }
                } catch (error) {
                    console.error('Chyba při komunikaci s ARES API:', error);
                }
            }
        });
    });
</script>

<div class="wrap">
    <h1>Detail Faktury <?php echo esc_html($invoice_id); ?><?php if (!empty($event_title)): ?> - <?php echo esc_html($event_title); ?><?php endif; ?></h1>
    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px;">
        <?php 
        // Získání všech kšeftů seřazených podle termínu akce
        $args = array(
            'post_type' => 'kseft',
            'posts_per_page' => -1,
            'meta_key' => 'kseft_event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'fields' => 'ids'
        );
        $all_ksefts = get_posts($args);

        // Najdeme aktuální pozici faktury v seznamu
        $current_index = array_search($invoice_id, $all_ksefts);

        // Získání ID předchozí a další faktury
        $prev_invoice_id = $current_index > 0 ? $all_ksefts[$current_index - 1] : null;
        $next_invoice_id = $current_index < count($all_ksefts) - 1 ? $all_ksefts[$current_index + 1] : null;
        ?>
        <?php if ($prev_invoice_id): ?>
            <a href="<?php echo esc_url(add_query_arg('invoice_id', $prev_invoice_id, site_url('/invoice-details'))); ?>" class="button">Předchozí faktura</a>
        <?php endif; ?>
        <a href="<?php echo site_url('/faktury'); ?>" class="button">Zpět na přehled faktur</a>
        <?php if ($next_invoice_id): ?>
            <a href="<?php echo esc_url(add_query_arg('invoice_id', $next_invoice_id, site_url('/invoice-details'))); ?>" class="button">Další faktura</a>
        <?php endif; ?>
    </div>
    <form method="post">
        <?php wp_nonce_field('save_invoice_details'); ?>
        <div style="display: flex; gap: 20px; margin-top: 20px;">
            <div style="flex: 1;">
                <table class="form-table">
                    <tr>
                        <th><label for="invoice_amount">Částka</label></th>
                        <td><input type="number" name="invoice_data[amount]" id="invoice_amount" value="<?php echo esc_attr($invoice_data['amount']); ?>" step="0.01" /></td>
                    </tr>
                    <tr>
                        <th><label for="invoice_variable_symbol">Variabilní symbol</label></th>
                        <td><input type="text" name="invoice_data[variable_symbol]" id="invoice_variable_symbol" value="<?php echo esc_attr($invoice_data['variable_symbol']); ?>" maxlength="10" required pattern=".{1,10}" title="Pole musí mít maximálně 10 znaků." /></td> <!-- Přidán atribut maxlength, required a pattern -->
                    </tr>
                    <tr>
                        <th><label for="invoice_reference_number">Referenční číslo</label></th> <!-- Přidáno referenční číslo -->
                        <td><input type="text" name="invoice_data[reference_number]" id="invoice_reference_number" value="<?php echo esc_attr($invoice_data['reference_number']); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="invoice_payment_method">Forma úhrady</label></th> <!-- Přidáno forma úhrady -->
                        <td>
                            <select name="invoice_data[payment_method]" id="invoice_payment_method">
                                <option value="Bankovní převod" <?php selected($invoice_data['payment_method'], 'Bankovní převod'); ?>>Bankovní převod</option>
                                <option value="Hotově" <?php selected($invoice_data['payment_method'], 'Hotově'); ?>>Hotově</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_status">Status</label></th>
                        <td>
                            <select name="invoice_data[status]" id="invoice_status">
                                <option value="Nová" <?php selected($invoice_data['status'], 'Nová'); ?>>Nová</option>
                                <option value="Vytvořena faktura" <?php selected($invoice_data['status'], 'Vytvořena faktura'); ?>>Vytvořena faktura</option>
                                <option value="Zaplaceno" <?php selected($invoice_data['status'], 'Zaplaceno'); ?>>Zaplaceno</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_message">Zpráva</label></th>
                        <td><textarea name="invoice_data[message]" id="invoice_message" rows="4" style="width: 100%;"><?php echo esc_textarea($invoice_data['message']); ?></textarea></td>
                    </tr>
                </table>
            </div>
            <div style="flex: 1;">
                <table class="form-table">
                    <tr>
                        <th><label for="invoice_customer_ico">IČO zákazníka</label></th>
                        <td>
                            <input type="text" name="invoice_data[customer_ico]" id="invoice_customer_ico" value="<?php echo esc_attr($invoice_data['customer_ico']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_customer_name">Jméno zákazníka</label></th>
                        <td>
                            <input type="text" name="invoice_data[customer_name]" id="invoice_customer_name" value="<?php echo esc_attr($invoice_data['customer_name']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_customer_address">Adresa zákazníka</label></th>
                        <td>
                            <input type="text" name="invoice_data[customer_address]" id="invoice_customer_address" value="<?php echo esc_attr($invoice_data['customer_address']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="invoice_customer_phone">Telefon zákazníka</label></th>
                        <td><input type="text" name="invoice_data[customer_phone]" id="invoice_customer_phone" value="<?php echo esc_attr($invoice_data['customer_phone']); ?>" /></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="invoice-buttons" style="margin-top: 20px;">
            <button type="submit" class="button button-primary">Uložit</button>
        </div>
    </form>
    <div class="invoice-buttons">
        <form method="post">
            <?php wp_nonce_field('generate_qr_code'); ?>
            <input type="hidden" name="generate_qr_code" value="1">
            <button type="submit" class="button">Vygenerovat QR kód</button>
        </form>
        <form method="post">
            <?php wp_nonce_field('generate_pdf_invoice'); ?>
            <input type="hidden" name="generate_pdf_invoice" value="1">
            <button type="submit" class="button">Vygenerovat PDF Fakturu</button>
        </form>
    </div>
    <div class="invoice-sections">
        <?php if (!empty($qr_code_url)): ?>
            <div class="invoice-section">
                <h2>QR Kód</h2>
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="QR Kód">
            </div>
        <?php elseif (!empty($qr_code_error)): ?>
            <div class="invoice-section">
                <p style="color: red;"><?php echo esc_html($qr_code_error); ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($invoice_data['pdf_invoice_url'])): ?>
            <div class="invoice-section">
                <h2>PDF Faktura</h2>
                <a href="<?php echo esc_url($invoice_data['pdf_invoice_url']); ?>" target="_blank" class="button">Stáhnout PDF Fakturu</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
get_footer();
?>
