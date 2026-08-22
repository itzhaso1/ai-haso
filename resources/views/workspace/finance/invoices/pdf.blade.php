@php
    $setting = \App\Models\Finance\FinanceSetting::withoutGlobalScopes()
        ->where('workspace_id', $invoice->workspace_id)
        ->first();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; margin: 0; padding: 24px; background: #f8fafc; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; }
        .title { color: #06C2A4; font-size: 28px; font-weight: 700; margin: 0; }
        .muted { color: #64748b; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px; font-size: 12px; text-align: right; }
        th { background: #f8fafc; font-weight: 700; }
        .totals td { border: none; padding: 4px 0; }
    </style>
</head>
<body>
    <div class="card">
        <table>
            <tr>
                <td style="width:55%">
                    <h1 class="title">{{ $setting?->company_name_ar ?: ($setting?->company_name ?: 'HASem Financial') }}</h1>
                    <p class="muted">
                        {{ $setting?->address_line }} {{ $setting?->street }} {{ $setting?->city }} {{ $setting?->country_code }}<br>
                        VAT: {{ $setting?->vat_number ?? '-' }} | CR: {{ $setting?->commercial_registration ?? '-' }}<br>
                        {{ $setting?->phone ?? '-' }} | {{ $setting?->email ?? '-' }}
                    </p>
                </td>
                <td style="text-align:left;">
                    <p style="font-size:20px;font-weight:700;margin:0;">فاتورة</p>
                    <p class="muted">#{{ $invoice->invoice_number }}</p>
                    <p class="muted">Issue: {{ $invoice->issue_date }}</p>
                    <p class="muted">Due: {{ $invoice->due_date ?? '-' }}</p>
                </td>
            </tr>
        </table>

        <table style="margin-top:16px;">
            <tr>
                <td>
                    <strong>العميل:</strong> {{ $invoice->customer?->name ?? '-' }}<br>
                    <span class="muted">{{ $invoice->customer?->phone ?? '' }}</span>
                </td>
                <td>
                    <strong>المورد:</strong> {{ $invoice->supplier?->name ?? '-' }}<br>
                    <span class="muted">{{ $invoice->supplier?->phone ?? '' }}</span>
                </td>
            </tr>
        </table>

        <table style="margin-top:18px;">
            <thead>
                <tr>
                    <th>المنتج/الخدمة</th>
                    <th>الوصف</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الخصم</th>
                    <th>VAT</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ number_format((float) $item->quantity, 3) }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ number_format((float) $item->discount, 2) }}</td>
                        <td>{{ number_format((float) $item->tax_amount, 2) }}</td>
                        <td>{{ number_format((float) $item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals" style="margin-top:12px;">
            <tr><td style="width:70%"></td><td>Subtotal:</td><td>{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            <tr><td></td><td>Discount:</td><td>{{ number_format((float) $invoice->discount, 2) }}</td></tr>
            <tr><td></td><td>Taxable Amount:</td><td>{{ number_format((float) $invoice->taxable_amount, 2) }}</td></tr>
            <tr><td></td><td>VAT:</td><td>{{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
            <tr><td></td><td><strong>Total:</strong></td><td><strong>{{ number_format((float) $invoice->total, 2) }}</strong></td></tr>
            <tr><td></td><td>Paid:</td><td>{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
            <tr><td></td><td>Due:</td><td>{{ number_format((float) $invoice->amount_due, 2) }}</td></tr>
        </table>

        @if($invoice->notes)
            <div style="margin-top:12px;">
                <strong>ملاحظات:</strong>
                <p class="muted">{{ $invoice->notes }}</p>
            </div>
        @endif

        <div style="margin-top:14px;">
            <strong>QR / ZATCA Architecture:</strong>
            <p class="muted">
                UUID: {{ $invoice->zatca_uuid ?? 'N/A' }}<br>
                XML Hash: {{ $invoice->zatca_xml_hash ?? 'N/A' }}<br>
                QR Payload: {{ $invoice->zatca_qr_code ? 'Available' : 'Not generated yet' }}
            </p>
        </div>
    </div>
</body>
</html>
