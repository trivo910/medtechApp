<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class DoctorPaymentsKpiTest extends TestCase
{
    public function test_kpi_cards_render_in_view_body(): void
    {
        $src  = file_get_contents(resource_path('views/doctor/payments/index.blade.php'));
        $body = $this->stripLayoutDirectives($src);
        $blade = app('blade.compiler');
        $html  = $blade->render($body, [
            'payments' => new LengthAwarePaginator([], 0, 20),
            'tab'      => 'all',
            'counts'   => ['all' => 0, 'paid' => 0, 'cash' => 0, 'unpaid' => 0],
            'totals'   => ['all'=>42,'paid'=>31450.00,'cash_total'=>12100.00,
                           'online_total'=>19350.00,'due_total'=>4800.00,
                           'bill_collected'=>27500.00,'bill_outstanding'=>4800.00,
                           'bill_waived'=>1500.00],
            'filters'  => ['q'=>'','status'=>'','method'=>'','from'=>'','to'=>''],
        ]);
        $this->assertStringContainsString('doc-pay-kpi-grid', $html);
        $this->assertStringContainsString('Collected (bills)', $html);
        $this->assertStringContainsString('Paid (all time)', $html);
        $this->assertStringContainsString('Online (Razorpay+UPI)', $html);
        $this->assertStringContainsString('Cash Collected', $html);
        $this->assertStringContainsString('Outstanding (bills)', $html);
        $this->assertStringContainsString('Waived', $html);
        $this->assertStringContainsString('₹31,450.00', $html);
        $this->assertStringContainsString('₹12,100.00', $html);
        $this->assertStringContainsString('₹19,350.00', $html);
        $this->assertStringContainsString('₹4,800.00', $html);
        $this->assertStringContainsString('₹1,500.00', $html);
        $this->assertStringContainsString('Export Excel', $html);
        $this->assertStringContainsString('doctor/payments/all/export', $html);
        $this->assertStringContainsString('Money received via bills', $html);
        $this->assertStringContainsString('Balances on open &amp; partial bills', $html);
        $this->assertStringContainsString('Bills marked waived', $html);
        $this->assertStringContainsString('dp-q-suggestions', $html);
        $this->assertStringContainsString('dp-date-input', $html);
        $this->assertStringContainsString('>Patient<', $html);
        $this->assertStringContainsString('>Bill<', $html);
    }

    public function test_payment_totals_method_signature(): void
    {
        $ctrl    = new \App\Http\Controllers\Doctor\PaymentsController(app(\App\Services\ExcelExportService::class));
        $reflect = new \ReflectionMethod($ctrl, 'paymentTotals');
        $reflect->setAccessible(true);
        $this->assertTrue($reflect->isPrivate());
        $this->assertEquals('int', $reflect->getParameters()[0]->getType()->getName());
        $this->assertEquals('array', $reflect->getReturnType()->getName());
    }

    private function stripLayoutDirectives(string $src): string
    {
        $body = $src;
        $body = str_replace("@extends('layouts.doctor')", '', $body);
        $body = str_replace("@section('title', 'Payments')", '', $body);
        $body = str_replace("@section('page-title', 'Payments')", '', $body);
        $body = str_replace("@section('content')", '', $body);
        $body = str_replace('@stop', '', $body);
        $body = str_replace('@endsection', '', $body);
        $idx = strpos($body, "@section('scripts')");
        if ($idx !== false) { $body = substr($body, 0, $idx); }
        return ltrim($body);
    }
}