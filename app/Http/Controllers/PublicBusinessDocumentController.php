<?php

namespace App\Http\Controllers;

use App\BusinessDocument;
use App\Services\BusinessDocumentPdfService;
use Illuminate\Support\Str;

class PublicBusinessDocumentController extends Controller
{
    public function show(string $token)
    {
        $document = $this->resolve($token);
        $document->load(['type', 'lines', 'signatories', 'parent']);
        $scriptNonce = Str::random(32);

        return response()->view('smart_documents.public', compact('document', 'token', 'scriptNonce'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Frame-Options', 'DENY')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-{$scriptNonce}'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    }

    public function pdf(string $token, BusinessDocumentPdfService $pdf)
    {
        return $this->pdfResponse($token, $pdf, 'inline', 'preview');
    }

    public function print(string $token, BusinessDocumentPdfService $pdf)
    {
        return $this->pdfResponse($token, $pdf, 'inline', 'print');
    }

    public function download(string $token, BusinessDocumentPdfService $pdf)
    {
        return $this->pdfResponse($token, $pdf, 'attachment', 'download');
    }

    private function pdfResponse(string $token, BusinessDocumentPdfService $pdf, string $disposition, string $action)
    {
        $document = $this->resolve($token);

        return response($pdf->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($document).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; sandbox",
            'X-CashERP-Document-Action' => $action,
        ]);
    }

    private function resolve(string $token): BusinessDocument
    {
        abort_unless(strlen($token) === 64, 404);

        return BusinessDocument::where('public_token_hash', hash('sha256', $token))
            ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
            ->where(function ($query) {
                $query->whereNull('share_expires_at')->orWhere('share_expires_at', '>', now());
            })
            ->firstOrFail();
    }
}
