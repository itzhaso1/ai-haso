<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Models\PosCashierInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosCashierInvoiceController extends PosBaseController
{
    public function index(Request $request): View
    {
        $this->authorizePos($request, 'orders.manage');

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $invoices = PosCashierInvoice::query()
            ->with(['table:id,name', 'closer:id,name'])
            ->whereDate('closed_at', $date)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.pos.invoices.index', [
            'invoices' => $invoices,
            'date' => $date,
        ]);
    }

    public function show(Request $request, PosCashierInvoice $invoice): View
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('view', $invoice);

        return view('workspace.pos.invoices.show', [
            'invoice' => $invoice->load(['items', 'table', 'session', 'closer', 'orders']),
        ]);
    }

    public function print(Request $request, PosCashierInvoice $invoice): View
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('view', $invoice);

        return view('workspace.pos.invoices.print', [
            'invoice' => $invoice->load(['items', 'table', 'session', 'closer']),
        ]);
    }
}
