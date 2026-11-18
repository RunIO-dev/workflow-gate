<?php

declare(strict_types=1);

namespace WorkflowGate;

use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Makes the workflow the authority over what an agent can do with a subject:
 * it lists the enabled transitions as tools and applies a transition only if
 * the workflow still allows it.
 */
final readonly class TransitionGate
{
    /**
     * Transitions are offered to agents only when they carry this metadata
     * key. Its value is the tool description.
     */
    public const METADATA_KEY = 'agent_tool';

    public function __construct(
        private Registry $workflows,
    ) {
    }

    /**
     * @return array<string, TransitionTool> the tools the subject's state allows, keyed by tool name
     */
    public function tools(object $subject): array
    {
        $workflow = $this->workflows->get($subject);

        return $this->describe($workflow, $workflow->getEnabledTransitions($subject));
    }

    /**
     * The tools of every agent transition, whatever the state. Use it to
     * compare against agents that see all tools and are stopped only on call.
     *
     * @return array<string, TransitionTool>
     */
    public function allTools(object $subject): array
    {
        $workflow = $this->workflows->get($subject);

        return $this->describe($workflow, $workflow->getDefinition()->getTransitions());
    }

    /**
     * @param iterable<Transition> $transitions
     *
     * @return array<string, TransitionTool>
     */
    private function describe(WorkflowInterface $workflow, iterable $transitions): array
    {
        $tools = [];

        foreach ($transitions as $transition) {
            $description = $workflow->getMetadataStore()->getTransitionMetadata($transition)[self::METADATA_KEY] ?? null;
            if (!\is_string($description)) {
                continue;
            }

            $name = $transition->getName().'_'.$workflow->getName();
            $tools[$name] = new TransitionTool($name, $description, $transition->getName());
        }

        return $tools;
    }

    /**
     * @return string what happened, in words an agent can pass on
     *
     * @throws TransitionBlocked when the workflow does not allow the transition
     */
    public function apply(object $subject, string $transition): string
    {
        $workflow = $this->workflows->get($subject);

        $blockers = $workflow->buildTransitionBlockerList($subject, $transition);
        if (!$blockers->isEmpty()) {
            throw TransitionBlocked::by($transition, $blockers);
        }

        $marking = $workflow->apply($subject, $transition);

        return \sprintf('Done: "%s" was applied. The %s is now "%s".', $transition, $workflow->getName(), implode('", "', array_keys($marking->getPlaces())));
    }
}
