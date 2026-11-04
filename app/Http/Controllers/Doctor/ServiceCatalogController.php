<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-doctor service catalogue.
 *
 * Each row is a saved payment-head line (name, head, default price and
 * quantity) that pre-fills the Complete Visit & Payment panel. Heads are
 * validated against config('medtech.billing_heads') — no migration needed
 * to add billing categories. See docs/BILLING.md.
 */
class ServiceCatalogController extends Controller
{
    public function index()
    {
        $doctor   = auth()->user();
        $services = DoctorService::where('doctor_user_id', $doctor->id)
            ->ordered()
            ->get()
            ->groupBy(fn ($s) => $s->headLabel());
        $total    = DoctorService::where('doctor_user_id', $doctor->id)->count();
        $heads    = collect(config('medtech.billing_heads', []));

        return view('doctor.services.index', compact('services', 'total', 'heads'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Keep catalogue entries unique per doctor; editing an existing row
        // beats silently creating a duplicate with the same name.
        $service = DoctorService::updateOrCreate(
            [
                'doctor_user_id' => auth()->id(),
                'name'           => $data['name'],
                'head'           => $data['head'],
            ],
            $data,
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'service' => $service]);
        }

        return back()->with('success', '"' . $service->name . '" saved to your service list.');
    }

    public function update(Request $request, DoctorService $service)
    {
        $this->gate($service);

        $service->update($this->validated($request));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'service' => $service->fresh()]);
        }

        return back()->with('success', 'Service updated.');
    }

    public function destroy(Request $request, DoctorService $service)
    {
        $this->gate($service);
        $name = $service->name;
        $service->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', '"' . $name . '" removed from your service list.');
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'head'             => ['required', 'string', 'max:30', Rule::in(array_keys(config('medtech.billing_heads', ['other' => []])))],
            'name'             => ['required', 'string', 'max:150'],
            'default_price'    => ['required', 'numeric', 'min:0', 'max:10000000'],
            'default_quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active'        => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    private function gate(DoctorService $service): void
    {
        if ((int) $service->doctor_user_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
