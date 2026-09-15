<?php

namespace Ugarit\NightwatchAgent;

class IngestDetails
{
    public function __construct(
        public string $token,
        public int $expiresIn,
        public string $ingestUrl,
        public int $refreshIn,
        public int $expiresAt,
    ) {
        //
    }
}
