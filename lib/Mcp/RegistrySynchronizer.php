<?php

declare(strict_types=1);

namespace WorkflowGate\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use WorkflowGate\SubjectProviderInterface;
use WorkflowGate\TransitionGate;

/**
 * Keeps the tools of an MCP server equal to the transitions the workflow
 * allows for the current subject. The registry announces every change, so
 * listening clients refresh their tool list.
 */
final class RegistrySynchronizer
{
    /**
     * @var array<string, true>
     */
    private array $registered = [];

    public function __construct(
        private readonly RegistryInterface $registry,
        private readonly TransitionGate $gate,
        private readonly SubjectProviderInterface $subjects,
    ) {
    }

    public function synchronize(): void
    {
        $subject = $this->subjects->current();
        $tools = null === $subject ? [] : $this->gate->tools($subject);

        foreach (array_diff_key($this->registered, $tools) as $name => $_) {
            $this->registry->unregisterTool($name);
            unset($this->registered[$name]);
        }

        foreach (array_diff_key($tools, $this->registered) as $name => $tool) {
            $this->registry->registerTool(
                new Tool($name, null, ['type' => 'object', 'properties' => new \stdClass()], $tool->description, null),
                fn (): string => $this->apply($tool->transition),
            );
            $this->registered[$name] = true;
        }
    }

    private function apply(string $transition): string
    {
        $subject = $this->subjects->current() ?? throw new \LogicException('No subject is open.');

        try {
            return $this->gate->apply($subject, $transition);
        } finally {
            $this->synchronize();
        }
    }
}
