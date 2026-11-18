<?php

declare(strict_types=1);

namespace App\Order;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

final readonly class OrderWorkflowListener
{
    private const REFUND_LIMIT = 500;

    public function __construct(
        private OrderRepository $orders,
    ) {
    }

    #[AsEventListener('workflow.order.guard.refund')]
    public function guardRefund(GuardEvent $event): void
    {
        $order = $event->getSubject();
        \assert($order instanceof Order);

        if ($order->total > self::REFUND_LIMIT) {
            $event->addTransitionBlocker(new TransitionBlocker(\sprintf('Refunds above %d EUR need a human.', self::REFUND_LIMIT), 'refund_limit'));
        }
    }

    #[AsEventListener('workflow.order.completed')]
    public function save(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        \assert($order instanceof Order);

        $this->orders->save($order);
    }
}
