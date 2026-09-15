<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Routing\Events\ResponsePrepared;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class ResponsePreparedListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(ResponsePrepared $event): void
    {
        try {
            if ($this->nightwatch->executionStageIs(ExecutionStage::Render)) {
                $this->nightwatch->stage(ExecutionStage::AfterMiddleware);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
