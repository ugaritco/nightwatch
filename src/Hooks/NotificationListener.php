<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Notifications\Events\NotificationSending;
use Heritage\Notifications\Events\NotificationSent;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class NotificationListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(NotificationSending|NotificationSent $event): void
    {
        try {
            $this->nightwatch->notification($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
