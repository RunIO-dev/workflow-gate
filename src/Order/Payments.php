<?php

declare(strict_types=1);

namespace App\Order;

use Mcp\Schema\Notification\ToolListChangedNotification;
use Mcp\Server\Subscription\NotificationBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Stands for the payment provider. A payment is not an agent tool, it arrives
 * from outside and moves the order through the same workflow.
 */
final readonly class Payments
{
    public function __construct(
        private OrderRepository $orders,
        private WorkflowInterface $orderStateMachine,
        private NotificationBusInterface $defaultNotificationBus,
    ) {
    }

    public function receive(int $orderId): Order
    {
        $order = $this->orders->find($orderId) ?? throw new \InvalidArgumentException(\sprintf('There is no order %d.', $orderId));

        $this->orderStateMachine->apply($order, 'pay');

        // Tells MCP clients that are connected right now to read their tools again.
        $this->defaultNotificationBus->publish(new ToolListChangedNotification());

        return $order;
    }
}
