<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            // Payment head this service books money under; validated against
            // config('medtech.billing_heads') on the server.
            $table->string('head', 30);
            $table->string('name', 150);
            $table->decimal('default_price', 10, 2)->default(0);
            $table->unsignedSmallInteger('default_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['doctor_user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_services');
    }
};
