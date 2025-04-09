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

    // Nastavíme číslo faktury na ID kšeftu
    $invoice_data['invoice_number'] = $invoice_id;
    update_post_meta($invoice_id, 'invoice_data', $invoice_data);

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

        // Registrace fontů
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.php');
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.php');
        $pdf->AddFont('DejaVu', 'I', 'DejaVuSans-Oblique.php');
        $pdf->AddFont('DejaVu', 'BI', 'DejaVuSans-BoldOblique.php');

        // Funkce pro převod textu na ISO-8859-2
        $convert_to_iso = function ($text) {
            return iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
        };

        // Přidání loga firmy
        $logo_path = get_option('invoice_generator_company_logo', '');
        if (!empty($logo_path)) {
            $logo_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $logo_path);
            if (file_exists($logo_path)) {
                $pdf->Image($logo_path, 10, 10, 50); // Logo v levém horním rohu, šířka 50 mm
                $pdf->Ln(20); // Posun dolů po přidání loga
            } else {
                error_log('Logo firmy nebylo nalezeno na cestě: ' . $logo_path);
            }
        }

        // Hlavička faktury
        $pdf->SetFont('DejaVu', 'B', 16);
        $pdf->Cell(0, 10, $convert_to_iso('Faktura '). $invoice_data['invoice_number'], 0, 1, 'C');
        $pdf->Ln(10);

        // Informace o faktuře
        $pdf->SetFont('DejaVu', '', 12);
        $pdf->Cell(0, 10, $convert_to_iso('Datum vystavení: ') . date('d.m.Y'), 0, 1);

        // Formátování částky na účetní formát
        $formatted_amount = number_format($invoice_data['amount'], 2, ',', ' ');
        $pdf->Cell(0, 10, $convert_to_iso('Částka: ') . $formatted_amount . ' CZK', 0, 1);

        $pdf->Cell(0, 10, $convert_to_iso('Variabilní symbol: ') . $invoice_data['variable_symbol'], 0, 1);

        // Přidání QR kódu, pokud existuje
        if (!empty($invoice_data['qr_code_url'])) {
            $qr_code_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $invoice_data['qr_code_url']);
            if (file_exists($qr_code_path)) {
                $pdf->Ln(10);
                $pdf->Cell(0, 10, $convert_to_iso('QR Kód pro platbu:'), 0, 1);
                $pdf->Image($qr_code_path, $pdf->GetX(), $pdf->GetY(), 50, 50); // Přidání QR kódu jako obrázku
                $pdf->Ln(60);
            } else {
                error_log('QR kód nebyl nalezen na cestě: ' . $qr_code_path);
            }
        } else {
            error_log('QR kód není dostupný v $invoice_data[qr_code_url].');
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
                echo '<script>console.error("Nepodařilo se vytvořit adresář pro faktury: ' . esc_js($invoices_dir) . '");</script>';
                return false;
            }
        }

        $pdf->Output('F', $pdf_path);

        if (file_exists($pdf_path)) {
            error_log('PDF bylo úspěšně vytvořeno: ' . $pdf_path);
            return $pdf_url;
        } else {
            error_log('Chyba: PDF nebylo vytvořeno.');
            echo '<script>console.error("Chyba: PDF nebylo vytvořeno.");</script>';
            return false;
        }
    } catch (Exception $e) {
        error_log('Výjimka při generování PDF: ' . $e->getMessage());
        echo '<script>console.error("Výjimka při generování PDF: ' . esc_js($e->getMessage()) . '");</script>';
        return false;
    }
}
