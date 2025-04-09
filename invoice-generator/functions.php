<?php
/**
 * Vygeneruje unikátní číslo faktury.
 */
function invoice_generator_get_unique_invoice_number() {
    $last_invoice_id = get_option('invoice_generator_last_invoice_id', 0);
    $new_invoice_id = $last_invoice_id + 1;
    update_option('invoice_generator_last_invoice_id', $new_invoice_id);
    return $new_invoice_id;
}


function invoice_generator_generate_pdf($invoice_id, $invoice_data) {
    // Kontrola, zda je TCPDF dostupná
    if (!class_exists('\TCPDF')) {
        error_log('TCPDF knihovna nebyla nalezena. Ujistěte se, že je správně nainstalována přes Composer.');
        return false; // Zabráníme dalšímu zpracování
    }

    try {
        $pdf = new \TCPDF(); // Použití TCPDF z Composeru
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Invoice Generator');
        $pdf->SetTitle('Faktura');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        // Nastavení fontu podporujícího diakritiku
        $pdf->SetFont('dejavusans', '', 10);

        // Přidání loga
        $logo_url = get_option('invoice_generator_logo', '');
        if (!empty($logo_url)) {
            $logo_path = download_image_to_temp($logo_url);
            if ($logo_path) {
                $pdf->Image($logo_path, 10, 10, 25); // Zmenšené logo
                unlink($logo_path); // Smazání dočasného souboru
            }
        }

        // Přidání nadpisu s číslem faktury
        $pdf->Ln(10);
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, 'Faktura - číslo: ' . $invoice_id, 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);

        // Přidání tabulky s fakturačními údaji
        $pdf->Ln(60); // Posun dolů
        $html = '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="font-weight: bold; padding: 5px;">Částka:</td><td style="padding: 5px;">' . htmlspecialchars($invoice_data['amount']) . ' Kč</td></tr>';
        $html .= '<tr><td style="font-weight: bold; padding: 5px;">Číslo účtu:</td><td style="padding: 5px;">' . htmlspecialchars(get_option('invoice_generator_account_number')) . '/' . htmlspecialchars(get_option('invoice_generator_bank_code')) . '</td></tr>';
        $html .= '<tr><td style="font-weight: bold; padding: 5px;">Datum splatnosti:</td><td style="padding: 5px;">' . htmlspecialchars($invoice_data['due_date']) . '</td></tr>';
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // Přidání QR kódu
        $qr_code_url = invoice_generator_generate_qr_code($invoice_data);
        if (!empty($qr_code_url)) {
            $qr_code_path = download_image_to_temp($qr_code_url);
            if ($qr_code_path) {
                $pdf->Image($qr_code_path, 10, 100, 50); // Umístění QR kódu
                unlink($qr_code_path); // Smazání dočasného souboru
            }
        }

        // Kontrola a vytvoření složky pro faktury
        $upload_dir = wp_upload_dir(); // Získání cesty k upload složce
        $invoices_dir = $upload_dir['basedir'] . '/invoices/';
        if (!file_exists($invoices_dir)) {
            if (!mkdir($invoices_dir, 0755, true) && !is_dir($invoices_dir)) {
                error_log('Chyba při vytváření složky pro faktury: ' . $invoices_dir);
                return false;
            }
        }

        // Uložení PDF s názvem podle formátu "invoice-{číslo kšeftu}.pdf"
        $output_path = $invoices_dir . 'invoice-' . $invoice_id . '.pdf';
        $pdf->Output($output_path, 'F');
        // Vytvoření URL namísto lokální cesty
        $output_url = $upload_dir['baseurl'] . '/invoices/invoice-' . $invoice_id . '.pdf';
        return $output_url;
    } catch (\Exception $e) {
        error_log('Chyba při generování PDF: ' . $e->getMessage());
        return false; // Zabráníme dalšímu zpracování
    }
}

function download_image_to_temp($url) {
    $temp_file = tempnam(sys_get_temp_dir(), 'img_');
    $response = wp_remote_get($url, array('timeout' => 10));
    if (is_wp_error($response)) {
        error_log('Chyba při stahování obrázku: ' . $response->get_error_message());
        return false;
    }
    $image_data = wp_remote_retrieve_body($response);
    if (file_put_contents($temp_file, $image_data)) {
        return $temp_file;
    }
    error_log('Chyba při ukládání obrázku do dočasného souboru.');
    return false;
}
