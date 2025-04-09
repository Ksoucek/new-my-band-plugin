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
    // Pokud není ID faktury, nastavíme chybu 404
    $wp_query->set_404();
    status_header(404);
    get_template_part(404);
    exit;
}

// Explicitně nastavíme, že stránka není 404
$wp_query->is_404 = false;

// Načtení dat faktury
$invoice_data = get_post_meta($invoice_id, 'invoice_data', true);
$invoice_data = wp_parse_args($invoice_data, array(
    'amount' => '',
    'variable_symbol' => '',
    'status' => 'Nová'
));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_data'])) {
    check_admin_referer('save_invoice_details');

    // Pokud číslo faktury není nastaveno, vygenerujeme nové
    if (empty($invoice_data['invoice_number'])) {
        $invoice_data['invoice_number'] = invoice_generator_get_unique_invoice_number();
    }

    update_post_meta($invoice_id, 'invoice_data', $_POST['invoice_data']);
    $invoice_data = $_POST['invoice_data']; // Aktualizace zobrazených dat
    echo '<div class="updated"><p>Faktura byla úspěšně uložena.</p></div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr_code'])) {
    $account_number = get_option('invoice_generator_account_number', '');
    $bank_code = get_option('invoice_generator_bank_code', '');
    $default_due_days = get_option('invoice_generator_default_due_days', 14);
    $event_date = get_post_meta($invoice_id, 'kseft_event_date', true);
    $due_date = date('Y-m-d', strtotime($event_date . " + $default_due_days days"));

    $qr_code_data = array(
        'account_number' => $account_number,
        'bank_code' => $bank_code,
        'amount' => $invoice_data['amount'],
        'variable_symbol' => $invoice_data['variable_symbol'],
        'due_date' => $due_date
    );

    // Uložíme data posílaná do API pro zobrazení na stránce
    $invoice_data['qr_code_request_data'] = $qr_code_data;

    $qr_code_url = invoice_generator_generate_qr_code($qr_code_data);
    if ($qr_code_url) {
        update_post_meta($invoice_id, 'qr_code_url', $qr_code_url);
        $invoice_data['qr_code_url'] = $qr_code_url; // Uložíme URL QR kódu do aktuálních dat
    } else {
        $invoice_data['qr_code_error'] = 'Chyba při generování QR kódu. Zkontrolujte nastavení pluginu.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf_invoice'])) {
    $pdf_url = invoice_generator_generate_pdf($invoice_id, $invoice_data);
    if ($pdf_url) {
        $invoice_data['pdf_invoice_url'] = $pdf_url; // Uložíme URL PDF faktury do aktuálních dat
        echo '<div class="updated"><p>PDF faktura byla úspěšně vygenerována. <a href="' . esc_url($pdf_url) . '" target="_blank">Stáhnout fakturu</a></p></div>';
    } else {
        $error_message = 'Chyba při generování PDF faktury. Zkontrolujte nastavení pluginu.';
        echo '<div class="error"><p>' . esc_html($error_message) . '</p></div>';
        echo '<script>console.error("' . esc_js($error_message) . '");</script>'; // Výpis chyby do konzole
    }

    // Zakomentujte přesměrování, pokud existuje
    // wp_redirect(...);
    // exit;
}

$qr_code_url = isset($invoice_data['qr_code_url']) ? $invoice_data['qr_code_url'] : get_post_meta($invoice_id, 'qr_code_url', true);
$qr_code_error = isset($invoice_data['qr_code_error']) ? $invoice_data['qr_code_error'] : '';

get_header(); // Načtení hlavičky šablony
?>
<style>
    body {
        background-color: #f9f9f9; /* Nastavení výchozího pozadí */
        background-image: none; /* Zrušení obrázku na pozadí */
    }
</style>
<div class="wrap">
    <h1>Detail Faktury  <?php echo esc_html($invoice_id); ?></h1>
    <a href="<?php echo site_url('/faktury'); ?>" class="button">Zpět na přehled faktur</a>
    <form method="post">
        <?php wp_nonce_field('save_invoice_details'); ?>
        <table class="form-table">
            <tr>
                <th><label for="invoice_amount">Částka</label></th>
                <td><input type="number" name="invoice_data[amount]" id="invoice_amount" value="<?php echo esc_attr($invoice_data['amount']); ?>" step="0.01" /></td>
            </tr>
            <tr>
                <th><label for="invoice_variable_symbol">Variabilní symbol</label></th>
                <td><input type="text" name="invoice_data[variable_symbol]" id="invoice_variable_symbol" value="<?php echo esc_attr($invoice_data['variable_symbol']); ?>" /></td>
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
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary">Uložit</button>
        </p>
    </form>
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
    <?php if (!empty($qr_code_url)): ?>
        <h2>QR Kód</h2>
        <img src="<?php echo esc_url($qr_code_url); ?>" alt="QR Kód">
    <?php elseif (!empty($qr_code_error)): ?>
        <p style="color: red;"><?php echo esc_html($qr_code_error); ?></p>
    <?php endif; ?>
    <?php if (!empty($invoice_data['pdf_invoice_url'])): ?>
        <h2>PDF Faktura</h2>
        <a href="<?php echo esc_url($invoice_data['pdf_invoice_url']); ?>" target="_blank" class="button">Stáhnout PDF Fakturu</a>
    <?php endif; ?>
</div>
<?php
get_footer(); // Načtení patičky šablony
?>
