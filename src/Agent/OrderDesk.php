<?php

declare(strict_types=1);

namespace App\Agent;

use App\Order\OpenOrder;
use App\Order\Order;
use App\Order\OrderRepository;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WorkflowGate\Agent\LiveToolsPlatform;
use WorkflowGate\Agent\WorkflowToolbox;
use WorkflowGate\TransitionGate;

/**
 * The order desk agent. It can only do with an order what the order workflow
 * offers as tools.
 */
final readonly class OrderDesk
{
    private const PROMPT = <<<'TXT'
        You work at the order desk of a small shop and handle order %d (customer %s, %d EUR, status "%s").
        Act only through your tools. They are the actions that are possible right now, and they change when the order changes.
        Use a tool only for exactly what you are asked to do. If there is no tool for it, do nothing and say that it is not possible at this point. Never use another tool as a substitute: cancelling is not refunding, and refunding is not cancelling.
        After a tool confirmed an action, say that it is done. Your tools change after an action, that is normal and does not undo it. Report only what a tool confirmed and do not add consequences it did not state. Answer in one or two short sentences.
        TXT;

    public function __construct(
        private PlatformInterface $platform,
        private TransitionGate $gate,
        private OpenOrder $openOrder,
        private OrderRepository $orders,
        #[Autowire(env: 'AGENT_MODEL')]
        private string $model,
    ) {
    }

    /**
     * @return array{answer: string, calls: list<array{tool: string, result: string}>, order: Order}
     */
    public function ask(int $orderId, string $message, Mode $mode = Mode::Gated): array
    {
        $order = $this->orders->find($orderId) ?? throw new \InvalidArgumentException(\sprintf('There is no order %d.', $orderId));
        $this->openOrder->open($orderId);
        if ('' === $this->model) {
            throw new \LogicException('AGENT_MODEL is empty. Set it to the model the agent should use.');
        }

        $toolbox = new RecordingToolbox(new FaultTolerantToolbox(match ($mode) {
            Mode::Gated => new WorkflowToolbox($this->gate, $this->openOrder),
            Mode::AllTools => new WorkflowToolbox($this->gate, $this->openOrder, gated: false),
            Mode::PromptRules => new PromptRulesToolbox($this->openOrder, $this->orders),
        }));
        $prompt = \sprintf(self::PROMPT, $order->id, $order->customer, $order->total, $order->getStatus());
        if (Mode::PromptRules === $mode) {
            $prompt .= "\n".PromptRulesToolbox::RULES;
        }
        $agent = new Agent(
            new LiveToolsPlatform($this->platform, $toolbox),
            $this->model,
            [new SystemPromptInputProcessor($prompt)],
            toolbox: $toolbox,
        );

        $answer = $agent->call($message)->asText();

        return ['answer' => $answer, 'calls' => $toolbox->calls(), 'order' => $this->openOrder->current() ?? $order];
    }
}
