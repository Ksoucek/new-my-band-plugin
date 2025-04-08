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
    error_log('Funkce invoice_generator_generate_pdf byla spuštěna.');

    // Pokud číslo faktury není nastaveno, vygenerujeme nové
    if (empty($invoice_data['invoice_number'])) {
        $invoice_data['invoice_number'] = invoice_generator_get_unique_invoice_number();
        update_post_meta($invoice_id, 'invoice_data', $invoice_data);
    }

    // Ověříme, zda knihovna existuje o jednu úroveň výš nad kořenovým adresářem WordPressu
    $fpdf_path = dirname(ABSPATH) . '/vendor/setasign/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        error_log('FPDF knihovna nebyla nalezena na cestě: ' . $fpdf_path);
        return false;
    }

    error_log('FPDF knihovna byla nalezena na cestě: ' . $fpdf_path);
    require_once $fpdf_path;

    try {
        $pdf = new FPDF();
        $pdf->AddPage();

        // Nastavení fontu s podporou diakritiky
        $font_path = dirname(ABSPATH) . '/vendor/setasign/fpdf/font/DejaVuSans.ttf';
        if (!file_exists($font_path)) {
            error_log('Font DejaVuSans nebyl nalezen na cestě: ' . $font_path);
            return false;
        }
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->SetFont('DejaVu', '', 12);

        // Hlavička faktury
        $pdf->SetFont('DejaVu', 'B', 16);
        $pdf->Cell(0, 10, 'Faktura', 0, 1, 'C');
        $pdf->Ln(10);

        // Informace o faktuře
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->Cell(0, 10, 'Číslo faktury: ' . $invoice_data['invoice_number'], 0, 1);
        $pdf->Cell(0, 10, 'Datum vystavení: ' . date('Y-m-d'), 0, 1);
        $pdf->Cell(0, 10, 'Částka: ' . $invoice_data['amount'] . ' CZK', 0, 1);
        $pdf->Cell(0, 10, 'Variabilní symbol: ' . $invoice_data['variable_symbol'], 0, 1);

        // Přidání QR kódu, pokud existuje
        if (!empty($invoice_data['qr_code_url'])) {
            $qr_code_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $invoice_data['qr_code_url']);
            if (file_exists($qr_code_path)) {
                $pdf->Ln(10);
                $pdf->Cell(0, 10, 'QR Kód pro platbu:', 0, 1);
                $pdf->Image($qr_code_path, $pdf->GetX(), $pdf->GetY(), 50, 50); // Přidání QR kódu jako obrázku
                $pdf->Ln(60);
            } else {
                error_log('QR kód nebyl nalezen na cestě: ' . $qr_code_path);
            }
        }

        error_log('Data faktury byla přidána do PDF.');

        // Uložíme PDF na server
        $upload_dir = wp_upload_dir();
        $invoices_dir = $upload_dir['basedir'] . '/invoices';
        $pdf_path = $invoices_dir . '/invoice-' . $invoice_id . '.pdf';
        $pdf_url = $upload_dir['baseurl'] . '/invoices/invoice-' . $invoice_id . '.pdf';

        if (!file_exists($invoices_dir)) {
            if (!mkdir($invoices_dir, 0755, true) && !is_dir($invoices_dir)) {
                error_log('Nepodařilo se vytvořit adresář pro faktury: ' . $invoices_dir);
                return false;
            }
        }

        $pdf->Output('F', $pdf_path);

        if (file_exists($pdf_path)) {
            error_log('PDF bylo úspěšně vytvořeno: ' . $pdf_path);
            return $pdf_url;
        } else {
            error_log('Chyba: PDF nebylo vytvořeno.');
            return false;
        }
    } catch (Exception $e) {
        error_log('Výjimka při generování PDF: ' . $e->getMessage());
        return false;
    }
}
