<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Log\Context\Repository;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class ContextDehydratingHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Repository $context): void
    {
        try {
            if (($context->getHidden('nightwatch_user_id') ?? '') === '') {
                $context->addHidden('nightwatch_user_id', $this->nightwatch->executionState->user->resolvedUserId());
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
