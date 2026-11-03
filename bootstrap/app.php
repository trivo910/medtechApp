<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Load each route file with its own prefix & middleware group
        using: function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->prefix('auth')
                ->name('auth.')
                ->group(base_path('routes/auth.php'));

            Route::middleware(['web', 'auth', 'active', 'verified.mobile', 'locale'])
                ->prefix('doctor')
                ->name('doctor.')
                ->group(base_path('routes/doctor.php'));

            Route::middleware(['web', 'auth', 'active', 'verified.mobile', 'locale'])
                ->prefix('patient')
                ->name('patient.')
                ->group(base_path('routes/patient.php'));

            Route::middleware(['web', 'auth', 'active', 'verified.mobile', 'locale'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->call(fn() => app(\App\Services\PrescriptionReminderService::class)->sendDue())
            ->name('prescription-medication-reminders')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {

        // ── Global web middleware ────────────────────────────────────────────
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // ── Named middleware aliases ─────────────────────────────────────────
        $middleware->alias([
            'role'            => \App\Http\Middleware\RoleMiddleware::class,
            'verified.mobile' => \App\Http\Middleware\EnsureUserIsVerified::class,
            'active'          => \App\Http\Middleware\EnsureUserIsActive::class,
            'doctor.access'   => \App\Http\Middleware\DoctorAccessVerified::class,
            'premium'         => \App\Http\Middleware\PremiumDoctor::class,
            'locale'          => \App\Http\Middleware\SetLocale::class,
        ]);

        // ── Middleware priority (important for auth + role order) ────────────
        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\Authenticate::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnsureUserIsVerified::class,
            \App\Http\Middleware\RoleMiddleware::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Redirect to login on unauthenticated access (instead of JSON 401)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if (! $request->expectsJson()) {
                return redirect()->route('auth.login')
                    ->with('error', 'Please log in to continue.');
            }
        });

        // Redirect on authorization failure
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if (! $request->expectsJson()) {
                return back()->with('error', 'You are not authorized to perform this action.');
            }
        });
    })
    ->create();
