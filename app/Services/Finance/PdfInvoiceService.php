<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceSetting;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PdfInvoiceService
{
    public function download(FinanceInvoice $invoice): Response|Responsable
    {
        $invoice->loadMissing(['customer', 'supplier', 'items.product']);
        $fileName = 'invoice-'.$invoice->invoice_number.'.pdf';

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new RuntimeException('PDF generation is unavailable. Please install barryvdh/laravel-dompdf.');
        }

        $setting = FinanceSetting::query()->first();
        $companySnapshot = is_array($invoice->company_snapshot) ? $invoice->company_snapshot : [];
        $recipientSnapshot = is_array($invoice->recipient_snapshot) ? $invoice->recipient_snapshot : [];
        $pdfSnapshot = is_array($invoice->pdf_snapshot) ? $invoice->pdf_snapshot : [];
        $logoPath = $companySnapshot['logo_path'] ?? $setting?->logo_path;

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('workspace.finance.invoices.pdf', [
            'invoice' => $invoice,
            'setting' => $setting,
            'companySnapshot' => $companySnapshot,
            'recipientSnapshot' => $recipientSnapshot,
            'pdfSnapshot' => $pdfSnapshot,
            'logoDataUri' => $this->resolveLogoDataUri(is_string($logoPath) ? $logoPath : null),
        ])->setPaper('a4');

        return $pdf->download($fileName);
    }

    private function resolveLogoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($logoPath);
        if (! is_file($absolutePath)) {
            return null;
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false) {
            return null;
        }

        $mimeType = function_exists('mime_content_type')
            ? mime_content_type($absolutePath)
            : 'image/png';
        $mimeType = is_string($mimeType) && $mimeType !== '' ? $mimeType : 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($binary);
    }
}
