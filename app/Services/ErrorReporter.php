<?php

namespace App\Services;

use Throwable;

class ErrorReporter
{
    public function report(Throwable $e): void
    {
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            return;
        }

        if (function_exists('\Sentry\captureException') && filled(config('services.sentry.dsn'))) {
            \Sentry\captureException($e);
        }
    }
}
