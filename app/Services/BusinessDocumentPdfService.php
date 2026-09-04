<?php

namespace App\Services;

use App\BusinessDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BusinessDocumentPdfService
{
    public function render(BusinessDocument $document): string
    {
        $document->loadMissing(['type', 'lines', 'signatories', 'parent']);
        $outputFormat = data_get($document->template_snapshot, 'output_format', optional($document->type)->output_format ?: 'a4');
        $view = $outputFormat === 'receipt_80mm' ? 'smart_documents.print_receipt' : 'smart_documents.print';
        $html = view($view, compact('document'))->render();
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => $tempDir,
            'mode' => 'utf-8',
            'format' => $outputFormat === 'receipt_80mm' ? [80, 297] : 'A4',
            'margin_top' => $outputFormat === 'receipt_80mm' ? 4 : 10,
            'margin_right' => $outputFormat === 'receipt_80mm' ? 4 : 10,
            'margin_bottom' => $outputFormat === 'receipt_80mm' ? 5 : 14,
            'margin_left' => $outputFormat === 'receipt_80mm' ? 4 : 10,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->useSubstitutions = true;
        $mpdf->SetTitle($document->title.' '.$document->document_number);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function filename(BusinessDocument $document): string
    {
        return Str::slug($document->title.'-'.$document->document_number).'.pdf';
    }
}
