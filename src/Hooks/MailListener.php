<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Mail\Events\MessageSending;
use Heritage\Mail\Events\MessageSent;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class MailListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(MessageSending|MessageSent $event): void
    {
        try {
            $this->nightwatch->mail($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
