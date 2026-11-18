<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Records the tool calls of an agent run and what came back.
 */
final class RecordingToolbox implements ToolboxInterface
{
    /**
     * @var list<array{tool: string, result: string}>
     */
    private array $calls = [];

    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    public function getTools(): array
    {
        return $this->toolbox->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $result = $this->toolbox->execute($toolCall);
        $this->calls[] = ['tool' => $toolCall->getName(), 'result' => (string) $result->getResult()];

        return $result;
    }

    /**
     * @return list<array{tool: string, result: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
