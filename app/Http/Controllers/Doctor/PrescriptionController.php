<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionMedicine;
use App\Models\User;
use App\Models\Appointment;
use App\Services\PdfService;
use App\Services\BillService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;
use App\Models\DoctorMedicine;

class PrescriptionController extends Controller
{
    public function __construct(
        private PdfService      $pdf,
        private WhatsAppService $whatsApp,
    ) {}

    public function index(Request $request)
    {
        $doctor = auth()->user();
        $query  = Prescription::where('doctor_user_id', $doctor->id)
            ->with(['patient.profile', 'medicines'])
            ->latest('prescribed_date');

        if ($s = $request->get('q')) {
            $query->where(function ($q) use ($s) {
                $q->where('prescription_number', 'like', "%{$s}%")
                  ->orWhereHas('patient.profile', fn($p) => $p->where('full_name', 'like', "%{$s}%"));
            });
        }
        if ($f = $request->get('filter')) {
            match ($f) {
                'today'     => $query->whereDate('prescribed_date', today()),
                'unsent'    => $query->where('is_sent_whatsapp', false),
                'this_week' => $query->whereBetween('prescribed_date', [now()->startOfWeek(), now()->endOfWeek()]),
                default     => null,
            };
        }

        $prescriptions = $query->paginate(20)->withQueryString();
        return view('doctor.prescriptions.index', compact('prescriptions'));
    }

    public function create(Request $request)
    {
        $doctor  = auth()->user();
        $profile = $doctor->doctorProfile;
        $patient = $appointment = $record = $familyMember = null;

        if ($pid = $request->get('patient')) {
            $patient = User::where('id', $pid)->where('role', 'patient')
                ->with(['profile', 'familyMembers' => fn($q) => $q->where('is_delinked', false)])
                ->first();
        }
        if ($aid = $request->get('appointment')) {
            $appointment = Appointment::where('id', $aid)->where('doctor_user_id', $doctor->id)->first();
        }
        if ($mid = $request->get('member')) {
            $familyMember = \App\Models\FamilyMember::find($mid);
        }
        // If no explicit member param but appointment has one, use it
        if (!$familyMember && $appointment?->family_member_id) {
            $familyMember = $appointment->familyMember;
        }

        $recentMedicines = PrescriptionMedicine::whereHas('prescription',
            fn($q) => $q->where('doctor_user_id', $doctor->id)
        )->select('medicine_name','generic_name','form','dosage','frequency','timing','duration_days')
         ->orderByDesc('id')->limit(300)->get()
         ->unique('medicine_name')->take(60)->values();

        $myMedicines = DoctorMedicine::where('doctor_user_id', $doctor->id)
            ->active()
            ->orderBy('medicine_name')
            ->get(['id','medicine_name','generic_name','form','dosage','frequency','timing','duration_days','special_instructions']);

        return view('doctor.prescriptions.create', compact('doctor','profile','patient','appointment','record','recentMedicines','myMedicines','familyMember'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_user_id'           => ['required','exists:users,id'],
            'family_member_id'          => ['nullable','exists:family_members,id'],
            'appointment_id'            => ['nullable','exists:appointments,id'],
            'prescribed_date'           => ['required','date'],
            'chief_complaint'           => ['nullable','string','max:500'],
            'diagnosis'                 => ['nullable','string','max:500'],
            'notes'                     => ['nullable','string','max:2000'],
            'follow_up_instructions'    => ['nullable','string','max:1000'],
            'follow_up_date'            => ['nullable','date'],
            'medicines'                 => ['required','array','min:1'],
            'medicines.*.medicine_name' => ['required','string','max:200'],
            'medicines.*.dosage'        => ['nullable','string','max:100'],
            'medicines.*.form'          => ['nullable','string','max:50'],
            'medicines.*.frequency'     => ['nullable','string','max:100'],
            'medicines.*.duration_days' => ['nullable','integer','min:1'],
            'medicines.*.timing'        => ['nullable','string'],
            'medicines.*.special_instructions' => ['nullable','string','max:300'],
        ]);

        DB::beginTransaction();
        try {
            // Only link visits that belong to this doctor.
            $appointmentId = null;
            if ($request->filled('appointment_id')) {
                $appointmentId = Appointment::where('id', $request->appointment_id)
                    ->where('doctor_user_id', auth()->id())
                    ->value('id');
            }

            $rx = Prescription::create([
                'doctor_user_id'         => auth()->id(),
                'patient_user_id'        => $request->patient_user_id,
                'family_member_id'       => $request->family_member_id ?: null,
                'medical_record_id'      => $request->medical_record_id ?: null,
                'appointment_id'         => $appointmentId,
                'prescribed_date'        => $request->prescribed_date,
                'chief_complaint'        => $request->chief_complaint,
                'diagnosis'              => $request->diagnosis,
                'notes'                  => $request->notes,
                'follow_up_instructions' => $request->follow_up_instructions,
                'follow_up_date'         => $request->follow_up_date ?: null,
                'status'                 => 'issued',
                'is_sent_whatsapp'       => false,
            ]);

            NotificationService::prescriptionCreated($rx->load('doctor.profile'));

            foreach ($request->medicines as $i => $med) {
                if (empty($med['medicine_name'])) continue;
                PrescriptionMedicine::create([
                    'prescription_id'      => $rx->id,
                    'medicine_name'        => $med['medicine_name'],
                    'generic_name'         => $med['generic_name'] ?? null,
                    'dosage'               => $med['dosage'] ?? null,
                    'form'                 => $med['form'] ?? null,
                    'frequency'            => $med['frequency'] ?? null,
                    'duration_days'        => $med['duration_days'] ?? null,
                    'timing'               => $med['timing'] ?: 'after_food',
                    'special_instructions' => $med['special_instructions'] ?? null,
                    'sort_order'           => $i,
                ]);
            }

            app(\App\Services\PrescriptionReminderService::class)->createForPrescription($rx);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Could not save prescription: ' . $e->getMessage());
        }

        dispatch(new \App\Jobs\GeneratePrescriptionPdfJob($rx->id));

        // Schedule WhatsApp follow-up reminder 3 days before the follow-up date at 9 AM
        if ($rx->follow_up_date && $rx->follow_up_date->isFuture()) {
            $rx->loadMissing('doctor.profile');
            $remindAt = $rx->follow_up_date->copy()->subDays(3)->setTime(9, 0);
            if ($remindAt->isFuture()) {
                \App\Jobs\SendWhatsAppReminderJob::dispatch(
                    userId:  $rx->patient_user_id,
                    type:    'follow_up',
                    payload: [
                        'doctor_name'    => $rx->doctor->profile?->full_name ?? 'your doctor',
                        'follow_up_date' => $rx->follow_up_date->format('d M Y'),
                    ]
                )->delay($remindAt);
            }
        }

        if ($request->get('action') === 'complete_payment' && $rx->appointment_id) {
            app(BillService::class)->forAppointment(Appointment::findOrFail($rx->appointment_id));

            return redirect()
                ->to(route('doctor.prescriptions.show', $rx) . '#billing')
                ->with('success', 'Prescription saved — add the payment heads below.');
        }

        if ($request->get('action') === 'send_whatsapp') {
            return redirect()->route('doctor.prescriptions.send-whatsapp', $rx)
                ->with('success', 'Prescription saved.');
        }
        return redirect()->route('doctor.prescriptions.show', $rx)
            ->with('success', 'Prescription #'.$rx->prescription_number.' created.');
    }

    public function show(Prescription $prescription)
    {
        $this->auth($prescription);
        $prescription->load([
            'doctor.profile', 'doctor.doctorProfile', 'patient.profile', 'patient.familyMembers',
            'medicines', 'medicalRecord',
            'appointment.bill.items', 'appointment.bill.payments',
        ]);
        return view('doctor.prescriptions.show', compact('prescription'));
    }

    public function pdf(Prescription $prescription, Request $request)
    {
        $this->auth($prescription);
        $prescription->load(['doctor.profile','doctor.doctorProfile','patient.profile','medicines']);
        return $request->get('download')
            ? $this->pdf->download($prescription)
            : $this->pdf->stream($prescription);
    }

    public function showSendWhatsApp(Prescription $prescription)
    {
        $this->auth($prescription);
        $prescription->load(['patient.profile','medicines','doctor.profile','doctor.doctorProfile']);
        return view('doctor.prescriptions.send-whatsapp', compact('prescription'));
    }

    public function sendWhatsApp(Prescription $prescription)
    {
        $this->auth($prescription);
        $prescription->load(['patient.profile','medicines','doctor.profile','doctor.doctorProfile']);
        try {
            $disk = config('medtech.prescription.pdf_disk', 'local');
            if (! $prescription->pdf_path || ! \Storage::disk($disk)->exists($prescription->pdf_path)) {
                $this->pdf->generatePrescriptionPdf($prescription);
                $prescription->refresh();
            }
            $sent = $this->whatsApp->sendPrescription($prescription);
            if (! $sent) {
                return back()->with('error', 'Failed to send WhatsApp message. Please try again.');
            }
            NotificationService::prescriptionSentWhatsApp($prescription);
            return redirect()->route('doctor.prescriptions.show', $prescription)
                ->with('success', 'Prescription sent to patient\'s WhatsApp.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Send failed: '.$e->getMessage());
        }
    }

    public function regeneratePdf(Prescription $prescription)
    {
        $this->auth($prescription);
        $prescription->load(['doctor.profile','doctor.doctorProfile','patient.profile','medicines']);
        $this->pdf->generatePrescriptionPdf($prescription);
        return back()->with('success', 'PDF regenerated.');
    }

    private function auth(Prescription $rx): void
    {
        if ($rx->doctor_user_id !== auth()->id()) abort(403);
    }
}
