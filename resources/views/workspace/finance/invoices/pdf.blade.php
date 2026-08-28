@php
    $company = is_array($companySnapshot ?? null) ? $companySnapshot : [];
    $recipient = is_array($recipientSnapshot ?? null) ? $recipientSnapshot : [];
    $theme = is_array($pdfSnapshot ?? null) ? $pdfSnapshot : [];
    $primaryColor = $theme['primary_color'] ?? ($setting?->invoice_primary_color ?: '#06C2A4');

    $invoiceStatus = $invoice->invoice_status
        ?? (in_array($invoice->status, ['draft', 'cancelled'], true) ? $invoice->status : 'issued');
    $paymentStatus = $invoice->payment_status
        ?? (in_array($invoice->status, ['unpaid', 'partial', 'paid', 'overdue'], true) ? $invoice->status : 'unpaid');

    $invoiceStatusLabels = [
        'draft' => 'مسودة',
        'issued' => 'معتمدة',
        'cancelled' => 'ملغاة',
    ];
    $paymentStatusLabels = [
        'unpaid' => 'غير مدفوعة',
        'partial' => 'مدفوعة جزئيًا',
        'paid' => 'مدفوعة بالكامل',
        'overdue' => 'متأخرة',
    ];

    $companyName = $company['company_name_ar'] ?? $company['company_name'] ?? $setting?->company_name_ar ?? $setting?->company_name ?? 'فاتورة';
    $companyNameEn = $company['company_name'] ?? $setting?->company_name;
    $companyAddress = trim(implode(' - ', array_filter([
        $company['address_line'] ?? null,
        $company['street'] ?? null,
        $company['district'] ?? null,
        $company['city'] ?? null,
        $company['postal_code'] ?? null,
        $company['country_code'] ?? null,
    ])));
    $companyVat = $company['vat_number'] ?? $setting?->vat_number;
    $companyCr = $company['commercial_registration'] ?? $setting?->commercial_registration;
    $companyPhone = $company['phone'] ?? $setting?->phone;
    $companyEmail = $company['email'] ?? $setting?->email;
    $companyWebsite = $company['website'] ?? $setting?->website;
    $invoiceFooter = $theme['footer_text'] ?? $setting?->invoice_footer_text;

    $recipientName = $recipient['name']
        ?? ($invoice->type === 'sales' ? ($invoice->customer?->name ?? $invoice->customer_name) : ($invoice->supplier?->name ?? null))
        ?? '-';
    $recipientAddress = $recipient['address']
        ?? ($invoice->type === 'sales' ? ($invoice->customer?->address ?? null) : ($invoice->supplier?->address ?? null));
    $recipientVat = $recipient['vat_number']
        ?? ($invoice->type === 'sales' ? ($invoice->customer?->vat_number ?? null) : ($invoice->supplier?->vat_number ?? null));
    $recipientPhone = $recipient['phone']
        ?? ($invoice->type === 'sales' ? ($invoice->customer?->phone ?? null) : ($invoice->supplier?->phone ?? null));
    $recipientEmail = $recipient['email']
        ?? ($invoice->type === 'sales' ? ($invoice->customer?->email ?? null) : ($invoice->supplier?->email ?? null));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 12mm 24mm 12mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.5;
        }

        .document {
            width: 100%;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .logo {
            max-width: 120px;
            max-height: 56px;
            margin-bottom: 6px;
        }

        .title {
            margin: 0;
            color: {{ $primaryColor }};
            font-size: 24px;
            font-weight: 700;
        }

        .subtitle {
            margin: 2px 0 0;
            color: #64748b;
            font-size: 11px;
        }

        .invoice-badge {
            display: inline-block;
            border: 1px solid #d1d5db;
            border-radius: 9999px;
            padding: 3px 8px;
            margin: 2px 2px 0 0;
            font-size: 11px;
            color: #334155;
            background: #f8fafc;
        }

        .blocks {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .blocks td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
        }

        .block-title {
            margin: 0 0 4px;
            color: #0f172a;
            font-weight: 700;
            font-size: 12px;
        }

        .muted {
            color: #64748b;
            font-size: 11px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .items thead th {
            background: #f8fafc;
            color: #0f172a;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            padding: 7px;
            font-size: 11px;
            text-align: right;
        }

        .items tbody td {
            border: 1px solid #e2e8f0;
            padding: 7px;
            font-size: 11px;
            vertical-align: top;
            word-break: break-word;
        }

        .items tbody tr {
            page-break-inside: avoid;
        }

        .totals {
            width: 48%;
            margin-right: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }

        .totals td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 11px;
        }

        .totals .label {
            background: #f8fafc;
            width: 65%;
            font-weight: 600;
        }

        .totals .grand {
            font-weight: 700;
            color: {{ $primaryColor }};
        }

        .notes-box {
            margin-top: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
        }

        .footer {
            position: fixed;
            bottom: -10mm;
            right: 0;
            left: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            color: #64748b;
            font-size: 10px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            border: none;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="document">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 60%;">
                        @if(!empty($logoDataUri))
                            <img src="{{ $logoDataUri }}" alt="Company Logo" class="logo">
                        @endif
                        <h1 class="title">{{ $companyName }}</h1>
                        @if($companyNameEn && $companyNameEn !== $companyName)
                            <p class="subtitle">{{ $companyNameEn }}</p>
                        @endif
                        <p class="subtitle">
                            {{ $companyAddress !== '' ? $companyAddress : '-' }}<br>
                            ضريبة القيمة المضافة: {{ $companyVat ?: '-' }} | السجل التجاري: {{ $companyCr ?: '-' }}<br>
                            {{ $companyPhone ?: '-' }} | {{ $companyEmail ?: '-' }}
                            @if($companyWebsite)
                                | {{ $companyWebsite }}
                            @endif
                        </p>
                    </td>
                    <td style="width: 40%; text-align: left;">
                        <p style="margin:0;font-size:18px;font-weight:700;">{{ $invoice->type === 'sales' ? 'فاتورة مبيعات' : 'فاتورة شراء' }}</p>
                        <p class="subtitle">#{{ $invoice->invoice_number }}</p>
                        <p class="subtitle">تاريخ الإصدار: {{ $invoice->issue_date?->format('Y-m-d') ?? '-' }}</p>
                        <p class="subtitle">تاريخ الاستحقاق: {{ $invoice->due_date?->format('Y-m-d') ?? '-' }}</p>
                        <div>
                            <span class="invoice-badge">حالة الفاتورة: {{ $invoiceStatusLabels[$invoiceStatus] ?? $invoiceStatus }}</span>
                            <span class="invoice-badge">حالة الدفع: {{ $paymentStatusLabels[$paymentStatus] ?? $paymentStatus }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="blocks">
            <tr>
                <td>
                    <p class="block-title">{{ $invoice->type === 'sales' ? 'بيانات العميل' : 'بيانات المورد' }}</p>
                    <div>{{ $recipientName }}</div>
                    <div class="muted">{{ $recipientAddress ?: '—' }}</div>
                    <div class="muted">الرقم الضريبي: {{ $recipientVat ?: '-' }}</div>
                    <div class="muted">{{ $recipientPhone ?: '-' }} | {{ $recipientEmail ?: '-' }}</div>
                </td>
                <td>
                    <p class="block-title">معلومات الفاتورة</p>
                    <div class="muted">العملة: {{ $invoice->currency }}</div>
                    <div class="muted">شروط السداد: {{ $invoice->payment_terms ?: ($company['default_payment_terms'] ?? '-') }}</div>
                    <div class="muted">مرجع ZATCA UUID: {{ $invoice->zatca_uuid ?: '-' }}</div>
                    <div class="muted">QR: {{ $invoice->zatca_qr_code ? 'متوفر' : 'غير متوفر' }}</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>المنتج/الخدمة</th>
                    <th>الوصف</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الخصم</th>
                    <th>الضريبة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->description ?: '-' }}</td>
                        <td>{{ number_format((float) $item->quantity, 3) }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ number_format((float) $item->discount, 2) }}</td>
                        <td>{{ number_format((float) $item->tax_amount, 2) }}</td>
                        <td>{{ number_format((float) $item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label">الإجمالي قبل الضريبة</td>
                <td>{{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">الخصم</td>
                <td>{{ number_format((float) $invoice->discount, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">المبلغ الخاضع للضريبة</td>
                <td>{{ number_format((float) $invoice->taxable_amount, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">الضريبة</td>
                <td>{{ number_format((float) $invoice->tax_amount, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label grand">الإجمالي</td>
                <td class="grand">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">المدفوع</td>
                <td>{{ number_format((float) $invoice->amount_paid, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="label">المتبقي</td>
                <td>{{ number_format((float) $invoice->amount_due, 2) }} {{ $invoice->currency }}</td>
            </tr>
        </table>

        @if($invoice->notes)
            <div class="notes-box">
                <strong>ملاحظات:</strong>
                <div class="muted">{{ $invoice->notes }}</div>
            </div>
        @endif

        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td style="text-align: right;">
                        {{ $invoiceFooter ?: 'تم إصدار هذه الفاتورة إلكترونيًا عبر النظام المالي.' }}
                    </td>
                    <td style="text-align: left;">
                        صفحة <span class="page-num"></span> من <span class="page-count"></span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $pdf->page_text(500, 815, "{PAGE_NUM}/{PAGE_COUNT}", $font, 9, [0.39, 0.45, 0.55]);
        }
    </script>
</body>
</html>
