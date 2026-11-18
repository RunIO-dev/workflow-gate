<?php

declare(strict_types=1);

namespace App\Agent;

use App\Order\OpenOrder;
use App\Order\OrderRepository;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * The baseline to compare against: tools that change the order without asking
 * the workflow. Whether a rule is kept depends on the model following the
 * prompt. Do not build agents like this.
 */
final readonly class PromptRulesToolbox implements ToolboxInterface
{
    public const RULES = 'Rules: ship an order only when its status is "paid". Cancel only orders with the status "draft". Refund only paid or shipped orders, and never more than 500 EUR.';

    private const TOOLS = [
        'cancel_order' => ['Cancels the open order.', 'cancelled'],
        'ship_order' => ['Hands the open order to the carrier.', 'shipped'],
        'refund_order' => ['Pays the money of the open order back to the customer.', 'refunded'],
    ];

    public function __construct(
        private OpenOrder $openOrder,
        private OrderRepository $orders,
    ) {
    }

    public function getTools(): array
    {
        $tools = [];
        foreach (self::TOOLS as $name => [$description]) {
            $tools[] = new Tool(new ExecutionReference(self::class, 'execute'), $name, $description);
        }

        return $tools;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        [, $status] = self::TOOLS[$toolCall->getName()] ?? throw ToolNotFoundException::notFoundForToolCall($toolCall);
        $order = $this->openOrder->current() ?? throw ToolNotFoundException::notFoundForToolCall($toolCall);

        $order->setStatus($status);
        $this->orders->save($order);

        return new ToolResult($toolCall, \sprintf('Done: the order is now "%s".', $status));
    }
}
