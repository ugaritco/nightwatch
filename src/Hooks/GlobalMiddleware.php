<?php

namespace Ugarit\Nightwatch\Hooks;

use Closure;
use Heritage\Http\Request;
use Ugarit\Nightwatch\Compatibility;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\Facades\Nightwatch;
use Ugarit\Nightwatch\State\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class GlobalMiddleware
{
    private bool $hasHandledRequest = false;

    private bool $hasTerminated = false;

    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->hasHandledRequest) {
            return $next($request);
        }

        $this->hasHandledRequest = true;

        try {
            $this->nightwatch->configureRequestSampling();
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }

        try {
            $this->nightwatch->captureRequestPreview($request);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->hasTerminated || Compatibility::$terminatingEventExists) {
            return;
        }

        $this->hasTerminated = true;

        try {
            $this->nightwatch->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
