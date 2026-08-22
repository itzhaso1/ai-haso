<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceInvoice;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;

class PdfInvoiceService
{
    public function download(FinanceInvoice $invoice): Response|Responsable
    {
        $invoice->loadMissing(['customer', 'supplier', 'items.product']);
        $fileName = 'invoice-'.$invoice->invoice_number.'.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('workspace.finance.invoices.pdf', [
                'invoice' => $invoice,
            ])->setPaper('a4');

            return $pdf->download($fileName);
        }

        // Fallback keeps architecture ready when PDF package is not installed yet.
        return response()
            ->view('workspace.finance.invoices.pdf', ['invoice' => $invoice])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
