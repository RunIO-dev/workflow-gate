<?php

declare(strict_types=1);

namespace WorkflowGate;

use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\TransitionBlockerList;

/**
 * The workflow refused a transition an agent asked for.
 */
final class TransitionBlocked extends \RuntimeException
{
    public static function by(string $transition, TransitionBlockerList $blockers): self
    {
        $reasons = array_map(static fn (TransitionBlocker $blocker): string => $blocker->getMessage(), iterator_to_array($blockers));

        return new self(\sprintf('The workflow does not allow "%s" now: %s', $transition, implode(' ', $reasons)));
    }
}
