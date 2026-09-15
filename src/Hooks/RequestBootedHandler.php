<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Contracts\Foundation\Application;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RequestBootedHandler
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->nightwatch->stage(ExecutionStage::BeforeMiddleware);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
