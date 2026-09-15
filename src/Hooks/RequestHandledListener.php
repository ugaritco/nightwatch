<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Foundation\Http\Events\RequestHandled;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RequestHandledListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(RequestHandled $event): void
    {
        try {
            $this->nightwatch->stage(ExecutionStage::Sending);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
