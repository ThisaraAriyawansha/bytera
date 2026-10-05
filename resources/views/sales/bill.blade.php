@php
    use App\Support\Money;

    /** @var \App\Models\Sale $sale */
    /** @var \App\Models\ShopSetting $shop */
    $legs = $sale->paymentLegs();
    $isCash = count($legs) === 1 && $legs[0]['method'] === 'cash';
    $row = 1;
    $cell = 'padding:2.5mm 2mm; border-bottom:1px solid #e4e4e7; vertical-align:top;';
    $head = 'padding:2mm; text-align:left; font-size:8pt; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#52525b; border-bottom:1px solid #0a0a0a;';
    $muted = 'font-size:8pt; color:#71717a;';
@endphp

{{-- A4 bill (SPEC §8.6). Printed with window.print() and captured by html2canvas for the PDF. --}}
<div id="bill-print"
     style="width:210mm; min-height:297mm; padding:15mm; box-sizing:border-box; display:flex; flex-direction:column; background:#fff; color:#0a0a0a; font-family:Poppins, sans-serif; font-size:10pt; line-height:1.45;">

    {{-- Header --}}
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8mm; padding-bottom:4mm; border-bottom:2px solid #0a0a0a;">
        <div style="display:flex; align-items:center; gap:4mm;">
            <img src="{{ asset('shop_logo/IMG_0112.PNG') }}" alt="" style="height:20mm; width:auto;" crossorigin="anonymous">
            <div>
                <div style="font-family:Montserrat, sans-serif; font-size:18pt; font-weight:700; line-height:1.2;">{{ $shop->name }}</div>
                @if (filled($shop->address))
                    <div style="{{ $muted }}">{{ $shop->address }}</div>
                @endif
                <div style="{{ $muted }}">Tel: {{ $shop->phone }}@if (filled($shop->email)) | {{ $shop->email }}@endif</div>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:9pt; font-weight:600; letter-spacing:.2em; color:#e30613;">INVOICE</div>
            <div style="font-family:Montserrat, sans-serif; font-size:14pt; font-weight:700;">{{ $sale->invoice_no }}</div>
            @if ($sale->status === 'cancelled')
                <div style="display:inline-block; margin:1mm 0; padding:0.5mm 2mm; border:1.5px solid #dc2626; color:#dc2626; font-size:9pt; font-weight:700; letter-spacing:.15em;">CANCELLED</div>
            @endif
            <div style="{{ $muted }}">Date: {{ $sale->created_at->format('M j, Y') }}</div>
            <div style="{{ $muted }}">Time: {{ $sale->created_at->format('g:i A') }}</div>
        </div>
    </div>

    {{-- Bill To / Served By --}}
    <div style="display:flex; justify-content:space-between; gap:8mm; margin:5mm 0;">
        <div>
            <div style="{{ $muted }} text-transform:uppercase; letter-spacing:.05em; font-weight:600;">Bill To</div>
            <div style="font-weight:600;">{{ $sale->customer_name ?: 'Walk-in Customer' }}</div>
            @if (filled($sale->customer_phone))
                <div>{{ $sale->customer_phone }}</div>
            @endif
            @if (filled($sale->customer_email))
                <div>{{ $sale->customer_email }}</div>
            @endif
            @if (filled($sale->job_no))
                <div>Job: {{ $sale->job_no }}</div>
            @endif
        </div>
        <div style="text-align:right;">
            <div style="{{ $muted }} text-transform:uppercase; letter-spacing:.05em; font-weight:600;">Served By</div>
            <div style="font-weight:600;">{{ $sale->cashier_name }}</div>
            @if (count($legs) > 1)
                <div>{{ collect($legs)->map(fn ($leg) => $leg['label'].': '.Money::format($leg['amount']))->implode(' · ') }}</div>
            @else
                <div>Payment: {{ $legs[0]['label'] }}</div>
            @endif
        </div>
    </div>

    {{-- Items --}}
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th style="{{ $head }} width:8mm;">#</th>
                <th style="{{ $head }}">Description</th>
                <th style="{{ $head }} text-align:right; width:12mm;">Qty</th>
                <th style="{{ $head }} text-align:right; width:28mm;">Unit Price</th>
                <th style="{{ $head }} text-align:right; width:24mm;">Disc.</th>
                <th style="{{ $head }} text-align:right; width:28mm;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->services ?? [] as $service)
                @php
                    $isFree = ($service['chargeType'] ?? 'paid') === 'free';
                    $servicePrice = (float) $service['price'] < 0 ? '- '.Money::format(abs((float) $service['price'])) : Money::format($service['price']);
                @endphp
                <tr>
                    <td style="{{ $cell }}">{{ $row++ }}</td>
                    <td style="{{ $cell }}">
                        <div style="font-weight:500;">{{ $service['name'] }} <span style="{{ $muted }}">(Service{{ filled($service['jobNo'] ?? null) ? ' · '.$service['jobNo'] : '' }})</span></div>
                        @foreach ($service['fields'] ?? [] as $field)
                            @continue($field['value'] === '' || $field['value'] === null || $field['value'] === false)
                            <div style="{{ $muted }}">{{ $field['label'] }}: {{ $field['value'] === true ? 'Yes' : $field['value'] }}</div>
                        @endforeach
                        @if ($isFree)
                            <div style="font-size:8pt; color:#15803d;">Free{{ filled($service['freeReason'] ?? null) ? ' — '.$service['freeReason'] : '' }}</div>
                        @endif
                    </td>
                    <td style="{{ $cell }} text-align:right;">1</td>
                    <td style="{{ $cell }} text-align:right;">{{ $isFree ? 'Free' : $servicePrice }}</td>
                    <td style="{{ $cell }} text-align:right;">—</td>
                    <td style="{{ $cell }} text-align:right;">{{ $isFree ? 'Free' : $servicePrice }}</td>
                </tr>
            @endforeach

            @foreach ($sale->items as $item)
                @php $lineDiscount = (float) $item->discount * $item->qty; @endphp
                <tr>
                    <td style="{{ $cell }}">{{ $row++ }}</td>
                    <td style="{{ $cell }}">
                        <div style="font-weight:500;">{{ $item->product_name }} <span style="{{ $muted }}">({{ $item->sku }})</span></div>
                        @if (! empty($item->units))
                            <div style="{{ $muted }}">Serial: {{ collect($item->units)->pluck('serialNumber')->implode(', ') }}</div>
                        @endif
                        @if ($item->warranty_months > 0)
                            <div style="{{ $muted }}">Warranty: {{ $item->warranty_months }} {{ str('month')->plural($item->warranty_months) }}</div>
                        @endif
                    </td>
                    <td style="{{ $cell }} text-align:right;">{{ $item->qty }}</td>
                    <td style="{{ $cell }} text-align:right;">{{ Money::format($item->unit_price) }}</td>
                    <td style="{{ $cell }} text-align:right; {{ $lineDiscount > 0 ? 'color:#e30613;' : '' }}">{{ $lineDiscount > 0 ? '- '.Money::format($lineDiscount) : '—' }}</td>
                    <td style="{{ $cell }} text-align:right;">{{ Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div style="display:flex; justify-content:flex-end; margin-top:5mm;">
        <div style="width:55mm;">
            <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Subtotal</span><span>{{ Money::format($sale->subtotal) }}</span></div>
            @if ((float) $sale->discount_amount > 0)
                <div style="display:flex; justify-content:space-between; padding:1mm 0; color:#e30613;"><span>Discount</span><span>- {{ Money::format($sale->discount_amount) }}</span></div>
            @endif
            @if ($sale->points_redeemed > 0)
                <div style="display:flex; justify-content:space-between; padding:1mm 0; color:#7e22ce;"><span>Points Redeemed</span><span>- {{ Money::format($sale->points_redeemed) }}</span></div>
            @endif
            @if ((float) $sale->tax_amount > 0)
                <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Tax</span><span>{{ Money::format($sale->tax_amount) }}</span></div>
            @endif
            <div style="display:flex; justify-content:space-between; margin-top:1mm; padding:2mm 0 1mm; border-top:2px solid #0a0a0a; font-family:Montserrat, sans-serif; font-size:13pt; font-weight:700;">
                <span>Total</span><span>{{ Money::format($sale->total_amount) }}</span>
            </div>
            @if ($isCash && $sale->amount_tendered !== null)
                <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Tendered</span><span>{{ Money::format($sale->amount_tendered) }}</span></div>
                <div style="display:flex; justify-content:space-between; padding:1mm 0; color:#15803d;"><span>Change</span><span>{{ Money::format($sale->change_amount) }}</span></div>
            @endif
        </div>
    </div>

    {{-- Bottom block, pinned to the page bottom --}}
    {{-- Pushes the bottom block to the foot of the page; print CSS replaces its margin-top:auto with 6mm. --}}
    <div style="flex:1 1 auto;" aria-hidden="true"></div>
    <div class="bill-signature-block" style="margin-top:auto; padding-top:8mm;">
        <div style="background:#f4f4f5; border-radius:4px; padding:3mm 4mm; font-size:7.5pt; line-height:1.5; color:#3f3f46;">
            <div style="font-weight:700; letter-spacing:.05em; color:#0a0a0a; margin-bottom:1mm;">WARRANTY TERMS &amp; CONDITIONS</div>
            Warranty replacement period: 14 days, warranty covers manufacturing defects only, no warranty for physical, liquid, electrical, or accidental damage, no warranty for software issues, OS installation, formatting, virus removal, or service/labor charges, warranty is void if the warranty sticker or serial number is removed, damaged, altered, or unreadable, all warranty claims are subject to inspection by our technicians.
        </div>

        @if (filled($sale->note))
            <div style="margin-top:3mm; font-size:9pt;"><strong>Note:</strong> {{ $sale->note }}</div>
        @endif

        <div style="display:flex; justify-content:space-between; gap:20mm; margin-top:14mm; font-size:8pt; color:#52525b; text-align:center;">
            <div style="flex:1;">
                <div style="border-top:1px dotted #0a0a0a; padding-top:1.5mm;">(Authority Signature)</div>
            </div>
            <div style="flex:1;">
                <div style="border-top:1px dotted #0a0a0a; padding-top:1.5mm;">(Customer Signature)</div>
                <div>Goods received in Good Condition</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; gap:8mm; margin-top:6mm; padding-top:3mm; border-top:1px solid #e4e4e7; font-size:7.5pt; color:#71717a;">
            <div>
                <div style="font-weight:700; color:#0a0a0a;">{{ $shop->name }}</div>
                <div>Quality Repairs. Genuine Parts. Trusted Service.</div>
                <div>Thank you for your purchase!</div>
            </div>
            <div style="text-align:right;">
                @if (filled($shop->email))
                    <div>Support: {{ $shop->email }}</div>
                @endif
                <div>Copyright © {{ $sale->created_at->format('Y') }} {{ $shop->name }}. All Rights Reserved.</div>
            </div>
        </div>
    </div>
</div>
