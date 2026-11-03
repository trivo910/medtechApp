<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\TimelineTemplate;
use App\Models\TimelineMilestone;
use App\Models\PatientTimeline;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\NotificationService;

class TimelineController extends Controller
{
    // ── Template list (doctor's own + system) ─────────────────────────────────
    public function index()
    {
        $doctor = auth()->user();

        $systemTemplates = TimelineTemplate::system()->active()
            ->withCount('patientTimelines')
            ->with('milestones')
            ->get();

        $myTemplates = TimelineTemplate::where('doctor_user_id', $doctor->id)
            ->withCount('patientTimelines')
            ->with('milestones')
            ->latest()
            ->get();

        // Patients currently on an active timeline assigned by this doctor
        $activeAssignments = PatientTimeline::where('assigned_by_doctor_id', $doctor->id)
            ->where('is_active', true)
            ->with(['template', 'patient.profile', 'familyMember'])
            ->latest()
            ->limit(10)
            ->get();

        return view('doctor.timelines.index', compact(
            'systemTemplates', 'myTemplates', 'activeAssignments'
        ));
    }

    // ── Assign template to a patient ──────────────────────────────────────────
    public function showAssign(Request $request, int $patientId)
    {
        $patient   = User::where('id', $patientId)->where('role', 'patient')
            ->with(['profile', 'familyMembers'])
            ->firstOrFail();

        // Verify doctor has access to this patient
        $hasAccess = \App\Models\DoctorAccessRequest::where('doctor_user_id', auth()->id())
            ->where('patient_user_id', $patientId)
            ->where('status', 'approved')
            ->where(fn($q) => $q->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
            ->exists();

        if (! $hasAccess) {
            return redirect()->route('doctor.patients.index')
                ->with('error', 'You need patient access to assign a timeline. Request access first.');
        }

        $templates = TimelineTemplate::active()
            ->withCount('milestones')
            ->orderByRaw('is_system_template DESC')
            ->orderBy('specialty_type')
            ->get()
            ->groupBy('specialty_type');

        // Already assigned timelines for this patient
        $existing = PatientTimeline::where('patient_user_id', $patientId)
            ->where('is_active', true)
            ->with('template')
            ->get();

        $preselectedId = $request->get('template');

        return view('doctor.timelines.assign', compact('patient', 'templates', 'existing', 'preselectedId'));
    }

    public function assign(Request $request, int $patientId)
    {
        $appointment = null;
        if ($request->filled('appointment_id')) {
            $request->validate(['appointment_id' => ['integer']]);
            $appointment = \App\Models\Appointment::where('doctor_user_id', auth()->id())
                ->where('patient_user_id', $patientId)->find($request->input('appointment_id'));
            abort_unless($appointment, 404, 'This appointment is unavailable or does not belong to you and this patient. Refresh the appointment page or choose one of your own appointments.');
            $request->merge(['family_member_id' => $appointment->family_member_id]);
        }

        // An owned appointment authorizes care for its patient. Standalone
        // assignments still require an unexpired approved access request.
        if (! $appointment) {
            $hasAccess = \App\Models\DoctorAccessRequest::where('doctor_user_id', auth()->id())
                ->where('patient_user_id', $patientId)->where('status', 'approved')
                ->where(fn ($q) => $q->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
                ->exists();
            abort_unless($hasAccess, 403, 'Patient access is missing or has expired. Request patient access and ask the patient to approve it before assigning a timeline.');
        }

        $request->validate([
            'template_id'      => ['required', 'exists:timeline_templates,id'],
            'start_date'       => ['required', 'date'],
            'family_member_id' => ['nullable', \Illuminate\Validation\Rule::exists('family_members', 'id')->where('primary_user_id', $patientId)],
            'custom_notes'     => ['nullable', 'string', 'max:1000'],
        ], [
            'template_id.required' => 'Select a timeline before assigning.',
            'template_id.exists' => 'This template is no longer available. Refresh the page and select another timeline.',
            'start_date.required' => 'Choose a start date for this timeline.',
            'start_date.date' => 'Enter a valid start date.',
            'family_member_id.exists' => 'The family member is no longer linked to this patient. Refresh the appointment and check the patient details.',
            'custom_notes.max' => 'Shorten the notes to 1,000 characters or fewer.',
        ]);

        $patient  = User::findOrFail($patientId);
        $template = TimelineTemplate::findOrFail($request->template_id);
        if (! $template->is_active || (! $template->is_system_template && $template->doctor_user_id != auth()->id())) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'template_id' => 'This timeline is inactive or unavailable to you. Refresh the page and select an active system template or one of your own templates.',
            ]);
        }

        // Calculate expected end date
        $startDate  = Carbon::parse($request->start_date);
        $endDate    = match($template->duration_unit) {
            'week'  => $startDate->copy()->addDays($template->total_duration_days),
            'month' => $startDate->copy()->addDays($template->total_duration_days),
            default => $startDate->copy()->addDays($template->total_duration_days),
        };

        $patientTimeline = PatientTimeline::create([
            'template_id'           => $template->id,
            'patient_user_id'       => $patient->id,
            'family_member_id'      => $request->family_member_id ?: null,
            'assigned_by_doctor_id' => auth()->id(),
            'start_date'            => $startDate,
            'expected_end_date'     => $endDate,
            'custom_notes'          => $request->custom_notes ? ['note' => $request->custom_notes] : null,
            'is_active'             => true,
        ]);
        NotificationService::timelineAssigned($patientTimeline->load('template', 'assignedByDoctor.profile'));

        if ($appointment && $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$template->title}\" assigned successfully."]);
        }

        if ($appointment) {
            return redirect()->route('doctor.appointments.show', $appointment)
                ->with('success', "\"{$template->title}\" assigned successfully.");
        }
        
        return redirect()
            ->route('doctor.patients.history', $patientId)
            ->with('success', "\"{$template->title}\" assigned to {$patient->profile?->full_name} starting {$startDate->format('d M Y')}.");
    }

    // ── Unassign / deactivate a patient timeline ──────────────────────────────
    public function unassign(Request $request, PatientTimeline $patientTimeline)
    {
        // Only the assigning doctor or any doctor with access can unassign
        $patientTimeline->update(['is_active' => false]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Timeline removed.');
    }

    // ── Template CRUD ─────────────────────────────────────────────────────────
    public function show(TimelineTemplate $template)
    {
        $template->load(['milestones' => fn($q) => $q->orderBy('sort_order')]);
        $assignments = PatientTimeline::where('template_id', $template->id)
            ->with(['patient.profile', 'familyMember'])
            ->where('is_active', true)
            ->latest()
            ->get();

        return view('doctor.timelines.show', compact('template', 'assignments'));
    }

    public function create()
    {
        return view('doctor.timelines.form', ['template' => null]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'specialty_type'      => ['required', 'string', 'max:50'],
            'title'               => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string'],
            'total_duration_days' => ['required', 'integer', 'min:1'],
            'duration_unit'       => ['required', 'in:day,week,month'],
        ]);

        $template = TimelineTemplate::create([
            'doctor_user_id'      => auth()->id(),
            'specialty_type'      => $request->specialty_type,
            'title'               => $request->title,
            'description'         => $request->description,
            'total_duration_days' => $request->total_duration_days,
            'duration_unit'       => $request->duration_unit,
            'is_system_template'  => false,
            'is_active'           => true,
        ]);

        return redirect()
            ->route('doctor.timelines.show', $template)
            ->with('success', 'Template created. Add milestones now.');
    }

    public function edit(TimelineTemplate $template)
    {
        abort_if($template->is_system_template, 403, 'System templates cannot be edited.');
        abort_if($template->doctor_user_id !== auth()->id(), 403);
        return view('doctor.timelines.form', compact('template'));
    }

    public function update(Request $request, TimelineTemplate $template)
    {
        abort_if($template->is_system_template, 403);
        abort_if($template->doctor_user_id !== auth()->id(), 403);

        $request->validate([
            'title'               => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string'],
            'total_duration_days' => ['required', 'integer', 'min:1'],
        ]);

        $template->update($request->only('title', 'description', 'total_duration_days', 'duration_unit', 'specialty_type'));

        return back()->with('success', 'Template updated.');
    }

    public function destroy(TimelineTemplate $template)
    {
        abort_if($template->is_system_template, 403);
        abort_if($template->doctor_user_id !== auth()->id(), 403);
        $template->delete();
        return redirect()->route('doctor.timelines.index')->with('success', 'Template deleted.');
    }

    // ── Milestone CRUD ────────────────────────────────────────────────────────
    public function storeMilestone(Request $request, TimelineTemplate $template)
    {
        abort_if($template->is_system_template && ! auth()->user()->isAdmin(), 403);

        $request->validate([
            'title'          => ['required', 'string', 'max:150'],
            'offset_value'   => ['required', 'integer', 'min:0'],
            'offset_unit'    => ['required', 'in:day,week,month'],
            'milestone_type' => ['required', 'in:visit,scan,test,vaccination,medication,procedure,info'],
            'description'    => ['nullable', 'string'],
            'precautions'    => ['nullable', 'string'],
            'icon'           => ['nullable', 'string', 'max:10'],
            'color'          => ['nullable', 'string', 'max:10'],
        ]);

        $maxSort = $template->milestones()->max('sort_order') ?? -1;

        TimelineMilestone::create([
            'template_id'    => $template->id,
            'title'          => $request->title,
            'description'    => $request->description,
            'offset_value'   => $request->offset_value,
            'offset_unit'    => $request->offset_unit,
            'milestone_type' => $request->milestone_type,
            'precautions'    => $request->precautions,
            'icon'           => $request->icon ?: '📋',
            'color'          => $request->color ?: '#B5EAD7',
            'sort_order'     => $maxSort + 1,
        ]);

        return back()->with('success', 'Milestone added.');
    }

    public function updateMilestone(Request $request, TimelineTemplate $template, TimelineMilestone $milestone)
    {
        abort_if($template->is_system_template && ! auth()->user()->isAdmin(), 403);
        $milestone->update($request->only('title', 'description', 'precautions', 'offset_value', 'offset_unit', 'milestone_type', 'icon', 'color'));
        return back()->with('success', 'Milestone updated.');
    }

    public function destroyMilestone(TimelineTemplate $template, TimelineMilestone $milestone)
    {
        abort_if($template->is_system_template && ! auth()->user()->isAdmin(), 403);
        $milestone->delete();
        return back()->with('success', 'Milestone removed.');
    }

    // ── Admin methods ─────────────────────────────────────────────────────────
    public function adminIndex()
    {
        $templates = TimelineTemplate::withCount(['milestones', 'patientTimelines'])
            ->orderByRaw('is_system_template DESC')
            ->get();
        return view('admin.timelines.index', compact('templates'));
    }

    public function activate(TimelineTemplate $template)
    {
        $template->update(['is_active' => true]);
        return back()->with('success', 'Template activated.');
    }

    public function deactivate(TimelineTemplate $template)
    {
        $template->update(['is_active' => false]);
        return back()->with('success', 'Template deactivated.');
    }
}
