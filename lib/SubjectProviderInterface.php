<?php

declare(strict_types=1);

namespace WorkflowGate;

/**
 * Provides the object an agent is working on. Its workflow state decides
 * which tools the agent has.
 */
interface SubjectProviderInterface
{
    public function current(): ?object;
}
