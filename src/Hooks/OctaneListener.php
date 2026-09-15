<?php

namespace Ugarit\Nightwatch\Hooks;

use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Ugarit\Octane\Events\RequestReceived;
use Throwable;

/**
 * @internal
 */
final class OctaneListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(private Core $nightwatch)
    {
        //
    }

    public function __invoke(RequestReceived $event): void // @phpstan-ignore class.notFound
    {
        try {
            $this->nightwatch->prepareForRequest($event->request); // @phpstan-ignore class.notFound
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
