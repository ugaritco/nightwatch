<?php

namespace Tests;

use Heritage\Contracts\Queue\Job as JobContract;
use Heritage\Queue\Jobs\Job;
use Ugarit\Nightwatch\Core;

use function app;
use function once;

class FakeJob extends Job implements JobContract
{
    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return once(fn () => app(Core::class)->uuid->make());
    }

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return '{"job":""}';
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return 1;
    }
}
