@php
    use App\Services\JobService;
    use App\Support\Money;

    /** @var \App\Models\Job $job */
    /** @var \App\Models\ShopSetting $shop */
    $finalCents = JobService::finalCostCents($job);
    $balanceCents = max(0, $finalCents - (int) round((float) $job->advance_paid * 100));
    $cell = 'padding:2mm; border-bottom:1px solid #e4e4e7; vertical-align:top;';
    $head = 'padding:2mm; text-align:left; font-size:8pt; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#52525b; border-bottom:1px solid #0a0a0a;';
    $muted = 'font-size:8pt; color:#71717a;';
    $label = $muted.' text-transform:uppercase; letter-spacing:.05em; font-weight:600;';
    $section = 'font-size:8.5pt; font-weight:700; letter-spacing:.05em; text-transform:uppercase; margin:4mm 0 1.5mm;';
    $deviceType = $job->device_type === 'Other' && filled($job->device_type_other) ? $job->device_type_other : $job->device_type;
    $services = $job->services ?? [];
@endphp

{{-- A4 job note (SPEC §8.6 / §8.7). Printed with window.print() and captured by html2canvas for the PDF. --}}
<div id="job-print"
     style="width:210mm; min-height:297mm; padding:15mm; box-sizing:border-box; display:flex; flex-direction:column; background:#fff; color:#0a0a0a; font-family:Poppins, sans-serif; font-size:9.5pt; line-height:1.45;">

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
            <div style="font-size:9pt; font-weight:600; letter-spacing:.2em; color:#e30613;">JOB NOTE</div>
            <div style="font-family:Montserrat, sans-serif; font-size:14pt; font-weight:700;">{{ $job->job_no }}</div>
            <div style="{{ $muted }}">Received: {{ $job->created_at->format('M j, Y g:i A') }}</div>
            @if ($job->expected_delivery_date)
                <div style="{{ $muted }}">Expected: {{ $job->expected_delivery_date->format('M j, Y') }}</div>
            @endif
            @if ($job->date_returned)
                <div style="{{ $muted }}">Returned: {{ $job->date_returned->format('M j, Y') }}</div>
            @endif
        </div>
    </div>

    {{-- Customer / Device --}}
    <div style="display:flex; justify-content:space-between; gap:8mm; margin:5mm 0 2mm;">
        <div style="flex:1;">
            <div style="{{ $label }}">Customer</div>
            <div style="font-weight:600;">{{ $job->customer_name }}</div>
            @if (filled($job->customer_company))
                <div>{{ $job->customer_company }}</div>
            @endif
            @if (filled($job->customer_address) || filled($job->customer_city))
                <div>{{ collect([$job->customer_address, $job->customer_city])->filter()->implode(', ') }}</div>
            @endif
            <div>{{ collect([$job->customer_phone, $job->customer_phone2])->filter()->implode(' / ') }}</div>
            @if (filled($job->customer_email))
                <div>{{ $job->customer_email }}</div>
            @endif
        </div>
        <div style="flex:1;">
            <div style="{{ $label }}">Device</div>
            <div style="font-weight:600;">{{ $deviceType }}</div>
            @if (filled($job->brand) || filled($job->model))
                <div>{{ trim($job->brand.' '.$job->model) }}</div>
            @endif
            @if (filled($job->serial_no))
                <div>Serial: {{ $job->serial_no }}</div>
            @endif
            @if (filled($job->color))
                <div>Colour: {{ $job->color }}</div>
            @endif
        </div>
        <div style="text-align:right;">
            <div style="{{ $label }}">Received By</div>
            <div style="font-weight:600;">{{ $job->received_by_name }}</div>
            <div style="{{ $label }} margin-top:2mm;">Technician</div>
            <div>{{ $job->assigned_technician_name ?: '—' }}</div>
        </div>
    </div>

    {{-- Device parts --}}
    @if (! empty($job->parts))
        <div style="{{ $section }}">Device Parts</div>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="{{ $head }} width:8mm;">#</th>
                    <th style="{{ $head }}">Part</th>
                    <th style="{{ $head }}">Spec</th>
                    <th style="{{ $head }}">Serial No</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($job->parts as $part)
                    <tr>
                        <td style="{{ $cell }}">{{ $loop->iteration }}</td>
                        <td style="{{ $cell }}">{{ $part['name'] ?: '—' }}</td>
                        <td style="{{ $cell }}">{{ $part['spec'] ?: '—' }}</td>
                        <td style="{{ $cell }}">{{ $part['serialNo'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Fault & intake --}}
    <div style="{{ $section }}">Fault Description</div>
    <div style="white-space:pre-line;">{{ $job->fault_description }}</div>

    <div style="display:flex; gap:8mm;">
        <div style="flex:1;">
            <div style="{{ $section }}">Accessories</div>
            <div>{{ collect([...($job->accessories ?? []), $job->accessories_other])->filter()->implode(', ') ?: 'None' }}</div>
        </div>
        <div style="flex:1;">
            <div style="{{ $section }}">Physical Condition</div>
            <div>{{ implode(', ', $job->physical_condition ?? []) ?: '—' }}</div>
        </div>
    </div>

    @if (filled($job->special_notes))
        <div style="{{ $section }}">Special Notes</div>
        <div style="white-space:pre-line;">{{ $job->special_notes }}</div>
    @endif

    {{-- Services & charges --}}
    @if ($services !== [])
        <div style="{{ $section }}">Services &amp; Charges</div>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="{{ $head }} width:8mm;">#</th>
                    <th style="{{ $head }}">Service</th>
                    <th style="{{ $head }} text-align:right; width:32mm;">Charge</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($services as $service)
                    @php $isFree = ($service['chargeType'] ?? 'paid') === 'free'; @endphp
                    <tr>
                        <td style="{{ $cell }}">{{ $loop->iteration }}</td>
                        <td style="{{ $cell }}">
                            {{ $service['name'] }}
                            @if ($isFree && filled($service['freeReason'] ?? null))
                                <span style="{{ $muted }}">({{ $service['freeReason'] }})</span>
                            @endif
                        </td>
                        <td style="{{ $cell }} text-align:right; {{ $isFree ? 'color:#15803d;' : '' }}">{{ $isFree ? 'Free' : Money::format($service['price']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Totals --}}
    <div style="display:flex; justify-content:flex-end; margin-top:4mm;">
        <div style="width:60mm;">
            @if ($services !== [])
                <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Services total</span><span>{{ Money::format(JobService::servicesTotalCents($services) / 100) }}</span></div>
            @endif
            <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Estimated cost</span><span>{{ Money::format($job->estimated_cost) }}</span></div>
            @if ($job->repair_cost !== null)
                <div style="display:flex; justify-content:space-between; padding:1mm 0;"><span>Repair cost</span><span>{{ Money::format($job->repair_cost) }}</span></div>
            @endif
            <div style="display:flex; justify-content:space-between; padding:1mm 0; color:#15803d;"><span>Advance paid</span><span>- {{ Money::format($job->advance_paid) }}</span></div>
            <div style="display:flex; justify-content:space-between; margin-top:1mm; padding:2mm 0 1mm; border-top:2px solid #0a0a0a; font-family:Montserrat, sans-serif; font-size:12pt; font-weight:700;">
                <span>Balance</span><span>{{ Money::format($balanceCents / 100) }}</span>
            </div>
        </div>
    </div>

    {{-- Bottom block, pinned to the page bottom --}}
    {{-- Pushes the bottom block to the foot of the page; print CSS replaces its margin-top:auto with 6mm. --}}
    <div style="flex:1 1 auto;" aria-hidden="true"></div>
    <div class="job-signature-block" style="margin-top:auto; padding-top:8mm;">
        <div style="background:#f4f4f5; border-radius:4px; padding:3mm 4mm; font-size:7.5pt; line-height:1.5; color:#3f3f46;">
            <div style="font-weight:700; letter-spacing:.05em; color:#0a0a0a; margin-bottom:1mm;">TERMS &amp; CONDITIONS</div>
            Please present this job note when collecting your device. The estimated cost may change after inspection; we will contact you before any additional work. We are not responsible for data loss — please back up your data. Devices not collected within 30 days of completion may be disposed of to recover costs. The advance payment is non-refundable once work has started.
        </div>

        <div style="display:flex; justify-content:space-between; gap:20mm; margin-top:14mm; font-size:8pt; color:#52525b; text-align:center;">
            <div style="flex:1;">
                <div style="border-top:1px dotted #0a0a0a; padding-top:1.5mm;">(Authority Signature)</div>
            </div>
            <div style="flex:1;">
                <div style="border-top:1px dotted #0a0a0a; padding-top:1.5mm;">(Customer Signature)</div>
                <div>I agree to the terms above</div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; gap:8mm; margin-top:6mm; padding-top:3mm; border-top:1px solid #e4e4e7; font-size:7.5pt; color:#71717a;">
            <div>
                <div style="font-weight:700; color:#0a0a0a;">{{ $shop->name }}</div>
                <div>Quality Repairs. Genuine Parts. Trusted Service.</div>
            </div>
            <div style="text-align:right;">
                @if (filled($shop->email))
                    <div>Support: {{ $shop->email }}</div>
                @endif
                <div>Copyright © {{ $job->created_at->format('Y') }} {{ $shop->name }}. All Rights Reserved.</div>
            </div>
        </div>
    </div>
</div>
