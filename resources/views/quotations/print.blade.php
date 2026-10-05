@php
    use App\Support\Money;

    /** @var \App\Models\Quotation $quotation */
    /** @var \App\Models\ShopSetting $shop */
    $cell = 'padding:2.5mm 2mm; border-bottom:1px solid #e4e4e7; vertical-align:top;';
    $head = 'padding:2mm; text-align:left; font-size:8pt; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#52525b; border-bottom:1px solid #0a0a0a;';
    $muted = 'font-size:8pt; color:#71717a;';
    $label = $muted.' text-transform:uppercase; letter-spacing:.05em; font-weight:600;';
@endphp

{{-- A4 quotation (SPEC §8.6 / §8.9). Printed with window.print() and captured by html2canvas for the PDF. --}}
<div id="quotation-print"
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
            <div style="font-size:9pt; font-weight:600; letter-spacing:.2em; color:#e30613;">QUOTATION</div>
            <div style="font-family:Montserrat, sans-serif; font-size:14pt; font-weight:700;">{{ $quotation->quotation_no }}</div>
            <div style="{{ $muted }}">Date: {{ $quotation->created_at->format('M j, Y') }}</div>
            <div style="{{ $muted }}">Valid until: {{ $quotation->valid_until->format('M j, Y') }}</div>
        </div>
    </div>

    {{-- Quotation For / Prepared By --}}
    <div style="display:flex; justify-content:space-between; gap:8mm; margin:5mm 0;">
        <div>
            <div style="{{ $label }}">Quotation For</div>
            <div style="font-weight:600;">{{ $quotation->customer_name ?: 'Walk-in Customer' }}</div>
            @if (filled($quotation->customer_phone))
                <div>{{ $quotation->customer_phone }}</div>
            @endif
            @if (filled($quotation->customer_address))
                <div style="white-space:pre-line;">{{ $quotation->customer_address }}</div>
            @endif
        </div>
        <div style="text-align:right;">
            <div style="{{ $label }}">Prepared By</div>
            <div style="font-weight:600;">{{ $quotation->prepared_by_name }}</div>
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
            @foreach ($quotation->items as $item)
                @php $lineDiscount = (float) $item->discount * $item->qty; @endphp
                <tr>
                    <td style="{{ $cell }}">{{ $loop->iteration }}</td>
                    <td style="{{ $cell }}">
                        <div style="font-weight:500;">{{ $item->product_name }}@if (filled($item->sku)) <span style="{{ $muted }}">({{ $item->sku }})</span>@endif</div>
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
            <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Subtotal</span><span>{{ Money::format($quotation->subtotal) }}</span></div>
            @if ((float) $quotation->discount_amount > 0)
                <div style="display:flex; justify-content:space-between; padding:1mm 0; color:#e30613;"><span>Discount</span><span>- {{ Money::format($quotation->discount_amount) }}</span></div>
            @endif
            <div style="display:flex; justify-content:space-between; margin-top:1mm; padding:2mm 0 1mm; border-top:2px solid #0a0a0a; font-family:Montserrat, sans-serif; font-size:13pt; font-weight:700;">
                <span>Total</span><span>{{ Money::format($quotation->total_amount) }}</span>
            </div>
        </div>
    </div>

    {{-- Bottom block, pinned to the page bottom --}}
    {{-- Pushes the bottom block to the foot of the page; print CSS replaces its margin-top:auto with 6mm. --}}
    <div style="flex:1 1 auto;" aria-hidden="true"></div>
    <div class="bill-signature-block" style="margin-top:auto; padding-top:8mm;">
        <div style="background:#f4f4f5; border-radius:4px; padding:3mm 4mm; font-size:8pt; line-height:1.5; color:#3f3f46;">
            <div style="font-weight:700; letter-spacing:.05em; color:#0a0a0a; margin-bottom:1mm;">NOTE / TERMS</div>
            @if (filled($quotation->note))
                <div style="white-space:pre-line;">{{ $quotation->note }}</div>
            @endif
            <div>This quotation is valid until {{ $quotation->valid_until->format('M j, Y') }}. Prices and availability are subject to change after that date.</div>
        </div>

        <div style="display:flex; justify-content:space-between; gap:20mm; margin-top:14mm; font-size:8pt; color:#52525b; text-align:center;">
            <div style="flex:1;">
                <div style="border-top:1px dotted #0a0a0a; padding-top:1.5mm;">(Authority Signature)</div>
            </div>
            <div style="flex:1;">
                <div style="border-top:1px dotted #0a0a0a; padding-top:1.5mm;">(Customer Signature)</div>
                <div>Quotation accepted</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; gap:8mm; margin-top:6mm; padding-top:3mm; border-top:1px solid #e4e4e7; font-size:7.5pt; color:#71717a;">
            <div>
                <div style="font-weight:700; color:#0a0a0a;">{{ $shop->name }}</div>
                <div>Quality Repairs. Genuine Parts. Trusted Service.</div>
                <div>Thank you for considering us!</div>
            </div>
            <div style="text-align:right;">
                @if (filled($shop->email))
                    <div>Support: {{ $shop->email }}</div>
                @endif
                <div>Copyright © {{ $quotation->created_at->format('Y') }} {{ $shop->name }}. All Rights Reserved.</div>
            </div>
        </div>
    </div>
</div>
