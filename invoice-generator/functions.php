<?php
function invoice_generator_generate_pdf($invoice_id, $invoice_data) {
    error_log('Funkce invoice_generator_generate_pdf byla spuštěna.');

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
        $pdf->SetFont('Arial', 'B', 16);

        // Hlavička faktury
        $pdf->Cell(0, 10, 'Faktura', 0, 1, 'C');
        $pdf->Ln(10);

        // Informace o faktuře
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Cislo faktury: ' . $invoice_id, 0, 1);
        $pdf->Cell(0, 10, 'Datum vystaveni: ' . date('Y-m-d'), 0, 1);
        $pdf->Cell(0, 10, 'Castka: ' . $invoice_data['amount'] . ' CZK', 0, 1);
        $pdf->Cell(0, 10, 'Variabilni symbol: ' . $invoice_data['variable_symbol'], 0, 1);
        $pdf->Cell(0, 10, 'Status: ' . $invoice_data['status'], 0, 1);

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
