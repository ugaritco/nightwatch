<?php

namespace Ugarit\Nightwatch\Hooks;

use Heritage\Cache\Events\CacheEvent;
use Ugarit\Nightwatch\Core;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class CacheEventListener
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(CacheEvent $event): void
    {
        try {
            $this->nightwatch->cacheEvent($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
