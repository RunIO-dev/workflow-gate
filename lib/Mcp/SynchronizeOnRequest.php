<?php

declare(strict_types=1);

namespace WorkflowGate\Mcp;

use Mcp\Event\RequestEvent;

/**
 * Synchronizes before every request, so a state change made outside the MCP
 * session (another user, a web page, a queue worker) is respected as well.
 */
final readonly class SynchronizeOnRequest
{
    public function __construct(
        private RegistrySynchronizer $synchronizer,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $this->synchronizer->synchronize();
    }
}
