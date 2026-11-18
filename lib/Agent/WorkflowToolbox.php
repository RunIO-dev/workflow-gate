<?php

declare(strict_types=1);

namespace WorkflowGate\Agent;

use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use WorkflowGate\SubjectProviderInterface;
use WorkflowGate\TransitionBlocked;
use WorkflowGate\TransitionGate;
use WorkflowGate\TransitionTool;

/**
 * The toolbox of a Symfony AI agent, made of the transitions the workflow
 * allows for the current subject.
 */
final readonly class WorkflowToolbox implements ToolboxInterface
{
    /**
     * @param bool $gated false offers every agent transition in every state and leaves
     *                    refusing to the workflow, for comparing both approaches
     */
    public function __construct(
        private TransitionGate $gate,
        private SubjectProviderInterface $subjects,
        private bool $gated = true,
    ) {
    }

    public function getTools(): array
    {
        return array_values(array_map(
            static fn (TransitionTool $tool): Tool => new Tool(new ExecutionReference(self::class, 'execute'), $tool->name, $tool->description),
            $this->transitionTools(),
        ));
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $tool = $this->transitionTools()[$toolCall->getName()] ?? throw ToolNotFoundException::notFoundForToolCall($toolCall);
        $subject = $this->subjects->current() ?? throw ToolNotFoundException::notFoundForToolCall($toolCall);

        try {
            return new ToolResult($toolCall, $this->gate->apply($subject, $tool->transition));
        } catch (TransitionBlocked $blocked) {
            return new ToolResult($toolCall, 'Refused. '.$blocked->getMessage());
        }
    }

    /**
     * @return array<string, TransitionTool>
     */
    private function transitionTools(): array
    {
        $subject = $this->subjects->current();
        if (null === $subject) {
            return [];
        }

        return $this->gated ? $this->gate->tools($subject) : $this->gate->allTools($subject);
    }
}
