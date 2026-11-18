<?php

declare(strict_types=1);

namespace App\Order;

use WorkflowGate\SubjectProviderInterface;

/**
 * The order the agent has opened. It is read from the store on every access,
 * so a change made elsewhere is seen at once.
 */
final class OpenOrder implements SubjectProviderInterface
{
    private ?int $id = null;

    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    public function open(int $id): void
    {
        $this->id = $id;
    }

    public function current(): ?Order
    {
        return null === $this->id ? null : $this->orders->find($this->id);
    }
}
