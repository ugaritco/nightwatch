<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Routing\Events\PreparingResponse;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\ExecutionStage;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class PreparingResponseListener
{
    /**
     * @param  Core<RequestState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(PreparingResponse $event): void
    {
        try {
            if ($this->nightwatch->executionStageIs(ExecutionStage::Action)) {
                $this->nightwatch->stage(ExecutionStage::Render);
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
