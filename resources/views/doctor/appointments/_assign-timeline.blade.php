<div id="assign-timeline" style="margin-top:16px;background:var(--cream);border:1px solid var(--warm-bd);border-radius:14px;overflow:hidden">
    <div style="padding:13px 18px;border-bottom:1px solid var(--warm-bd);font-family:'Cormorant Garamond',serif;font-size:1.05rem;color:var(--txt)">Assign Care Timeline</div>
    <div style="padding:16px 18px">
        <p style="font-size:.78rem;color:var(--txt-lt);margin:0 0 14px">Assigning to <strong>{{ $name }}</strong></p>
        <div id="timeline-feedback" role="status" aria-live="polite" tabindex="-1" hidden style="font-size:.8rem;margin-bottom:12px;line-height:1.5"></div>
        <a id="timeline-upgrade" href="{{ route('doctor.subscription.plans') }}" hidden style="font-size:.8rem;color:var(--leaf)">View Premium plans</a>
        @if(session('success'))
            <div role="status" style="font-size:.8rem;color:#166534;margin-bottom:12px">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" style="font-size:.8rem;color:#991b1b;margin-bottom:12px">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @if($timelineTemplates->isEmpty())
            <p style="font-size:.8rem;color:var(--txt-lt)">No active timeline templates available.</p>
            <a href="{{ route('doctor.timelines.create') }}" style="font-size:.8rem;color:var(--leaf)">Create a template</a>
        @else
            <form id="timeline-assignment-form" method="POST" action="{{ route('doctor.timelines.assign.save', $patient->id) }}">
                @csrf
                <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                <label for="care-template" style="display:block;font-size:.75rem;font-weight:600;margin-bottom:6px;color:var(--txt-md)">Timeline</label>
                <select id="care-template" name="template_id" required style="width:100%;padding:10px;border:1px solid var(--warm-bd);border-radius:8px;background:#fff;color:var(--txt);font:inherit;font-size:.8rem;margin-bottom:14px">
                    <option value="">Select a timeline</option>
                    @foreach($timelineTemplates->groupBy(fn ($template) => $template->is_system_template ? 'System templates' : 'My templates') as $group => $templates)
                        <optgroup label="{{ $group }}">
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>{{ $template->title }} ({{ $template->total_duration_days }} days, {{ $template->milestones_count }} milestones)</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <label for="care-start" style="display:block;font-size:.75rem;font-weight:600;margin-bottom:6px;color:var(--txt-md)">Start date</label>
                <input id="care-start" type="date" name="start_date" required value="{{ old('start_date', today()->format('Y-m-d')) }}" style="box-sizing:border-box;width:100%;padding:10px;border:1px solid var(--warm-bd);border-radius:8px;background:#fff;font:inherit;font-size:.8rem;margin-bottom:14px">
                <label for="care-notes" style="display:block;font-size:.75rem;font-weight:600;margin-bottom:6px;color:var(--txt-md)">Notes (optional)</label>
                <textarea id="care-notes" name="custom_notes" rows="2" maxlength="1000" style="box-sizing:border-box;width:100%;padding:10px;border:1px solid var(--warm-bd);border-radius:8px;background:#fff;font:inherit;font-size:.8rem;margin-bottom:14px;resize:vertical">{{ old('custom_notes') }}</textarea>
                <button type="submit" style="width:100%;padding:11px;background:var(--leaf);color:#fff;border:0;border-radius:9px;font:inherit;font-size:.85rem;font-weight:600;cursor:pointer">Assign Timeline</button>
            </form>
        @endif
    </div>
</div>
<script>
document.getElementById('timeline-assignment-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = this;
    const button = form.querySelector('button[type="submit"]');
    const feedback = document.getElementById('timeline-feedback');
    const upgrade = document.getElementById('timeline-upgrade');
    button.disabled = true;
    button.textContent = 'Assigning…';
    feedback.hidden = true;
    upgrade.hidden = true;
    try {
        const response = await fetch(form.action, {
            method: 'POST', body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json().catch(() => ({}));
        let message;
        if (response.ok && data.success) {
            message = data.message;
            feedback.style.color = '#166534';
        } else {
            feedback.style.color = '#991b1b';
            if (response.status === 419 || response.status === 401 || response.redirected) {
                message = 'Your session has expired. Refresh the page and sign in again before assigning.';
            } else if (response.status === 422 && data.errors) {
                message = Object.values(data.errors).flat().join(' ');
            } else if (response.status === 403) {
                message = data.message || 'You do not have permission to assign this timeline. Check your patient access and Premium subscription.';
                if (data.upgrade_url) {
                    message += ' Choose a Premium plan to enable care timeline assignment.';
                    upgrade.hidden = false;
                }
            } else if (response.status === 404) {
                message = data.message || 'The appointment or template is no longer available. Refresh the page and try again.';
            } else {
                message = 'We could not confirm the assignment. Refresh the page and check Care Timelines in patient history before retrying. If the issue continues, contact support.';
            }
        }
        feedback.textContent = message;
    } catch (error) {
        feedback.style.color = '#991b1b';
        feedback.textContent = 'Connection lost. Check your internet connection, then check Care Timelines in patient history before retrying to avoid assigning twice.';
    } finally {
        feedback.hidden = false;
        feedback.focus();
        button.disabled = false;
        button.textContent = 'Assign Timeline';
    }
});
</script>
