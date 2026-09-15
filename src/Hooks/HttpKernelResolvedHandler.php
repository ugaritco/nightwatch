<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Contracts\Foundation\Application;
use Heritage\Contracts\Http\Kernel as KernelContract;
use Heritage\Foundation\Events\Terminating;
use Heritage\Foundation\Http\Kernel;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\Facades\Nightwatch;
use Ugarit\Nightwatch\Http\Middleware\Sample;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class HttpKernelResolvedHandler
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        if (! $kernel instanceof Kernel) {
            return;
        }

        try {
            /**
             * @see \Ugarit\Nightwatch\ExecutionStage::End
             * @see \Ugarit\Nightwatch\Records\Request
             * @see \Ugarit\Nightwatch\Core::finishExecution()
             */
            $kernel->whenRequestLifecycleIsLongerThan(-1, new RequestLifecycleIsLongerThanHandler($this->nightwatch));
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }

        try {
            /**
             * @see \Ugarit\Nightwatch\ExecutionStage::Terminating
             */
            $kernel->prependMiddleware(GlobalMiddleware::class);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }

        try {
            $kernel->prependToMiddlewarePriority(Sample::class);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
