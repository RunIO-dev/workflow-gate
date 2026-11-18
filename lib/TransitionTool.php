<?php

declare(strict_types=1);

namespace WorkflowGate;

/**
 * A workflow transition offered to an agent as a tool.
 */
final readonly class TransitionTool
{
    public function __construct(
        public string $name,
        public string $description,
        public string $transition,
    ) {
    }
}
