<section id="previous-prescriptions" aria-labelledby="previous-prescriptions-title" style="background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;margin-bottom:16px;overflow:hidden">
    <div style="padding:13px 20px;border-bottom:1px solid var(--warm-bd)">
        <h2 id="previous-prescriptions-title" style="margin:0;font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:500;color:var(--txt)">Previous prescriptions</h2>
        <p style="margin:5px 0 0;font-size:.75rem;color:var(--txt-lt)">Prescriptions you issued for {{ $name }}, newest first.</p>
        <a href="{{ route('doctor.patients.history', array_filter(['patient' => $appointment->patient_user_id, 'tab' => 'prescriptions', 'member' => $appointment->family_member_id])) }}" style="display:inline-block;margin-top:8px;font-size:.8rem;color:var(--leaf);font-weight:600">View patient history</a>
    </div>
    <div style="padding:0 20px">
        @forelse($previousPrescriptions as $prescription)
            <article style="padding:16px 0;border-bottom:1px solid var(--warm-bd)">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                    <div>
                        <strong style="font-size:.85rem;color:var(--txt)">{{ $prescription->prescription_number }}</strong>
                        <span style="font-size:.75rem;color:var(--txt-lt);margin-left:8px">{{ $prescription->prescribed_date?->format('d M Y') }}</span>
                    </div>
                    <a href="{{ route('doctor.prescriptions.show', $prescription) }}" style="font-size:.8rem;color:var(--leaf);font-weight:600">View prescription</a>
                </div>
                @if($prescription->diagnosis_summary)
                    <p style="margin:8px 0;font-size:.85rem;color:var(--txt-md)">{{ $prescription->diagnosis_summary }}</p>
                @endif
                <ul style="padding-left:18px;margin:8px 0 0;font-size:.8rem;color:var(--txt-md)">
                    @foreach($prescription->medicines as $medicine)
                        <li style="margin-top:4px">{{ $medicine->medicine_name }}
                            {{ $medicine->dosage ? ' / ' . $medicine->dosage : '' }}
                            {{ $medicine->frequency ? ' / ' . $medicine->frequency : '' }}
                            {{ $medicine->duration_days ? ' / ' . $medicine->duration_days . ' days' : '' }}
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <p style="padding:16px 0;margin:0;font-size:.85rem;color:var(--txt-lt)">No previous prescriptions from you for this patient.</p>
        @endforelse
        @if($previousPrescriptions->hasPages())
            <div style="padding:16px 0">{{ $previousPrescriptions->links() }}</div>
        @endif
    </div>
</section>
