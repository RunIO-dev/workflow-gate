<?php

declare(strict_types=1);

namespace App\Order;

final class Order
{
    public function __construct(
        public readonly int $id,
        public readonly string $customer,
        public readonly int $total,
        private string $status = 'draft',
    ) {
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}
