<?php

namespace Tests\Feature;

use App\Http\Controllers\Doctor\PaymentsController;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class DoctorPaymentsSuggestTest extends TestCase
{
    public function test_empty_query_returns_empty_suggestions_list(): void
    {
        $doctor = new User(['id' => 7, 'role' => 'doctor']);
        $this->actingAs($doctor);

        $controller = new PaymentsController(app(\App\Services\ExcelExportService::class));
        $request    = Request::create('/doctor/payments/suggest', 'GET', ['q' => '']);
        $response   = $controller->patientSuggestions($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['suggestions' => []], $response->getData(true));
    }

    public function test_whitespace_only_query_returns_empty_suggestions_list(): void
    {
        $doctor = new User(['id' => 7, 'role' => 'doctor']);
        $this->actingAs($doctor);

        $controller = new PaymentsController(app(\App\Services\ExcelExportService::class));
        $request    = Request::create('/doctor/payments/suggest', 'GET', ['q' => '   ']);
        $response   = $controller->patientSuggestions($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['suggestions' => []], $response->getData(true));
    }

    public function test_missing_query_param_returns_empty_suggestions_list(): void
    {
        $doctor = new User(['id' => 7, 'role' => 'doctor']);
        $this->actingAs($doctor);

        $controller = new PaymentsController(app(\App\Services\ExcelExportService::class));
        $request    = Request::create('/doctor/payments/suggest', 'GET');
        $response   = $controller->patientSuggestions($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['suggestions' => []], $response->getData(true));
    }
}
