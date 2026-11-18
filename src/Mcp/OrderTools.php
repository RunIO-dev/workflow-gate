<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Order\OpenOrder;
use App\Order\Order;
use App\Order\OrderRepository;
use Mcp\Capability\Attribute\McpTool;
use WorkflowGate\Mcp\RegistrySynchronizer;

/**
 * The tools that exist in every state. Everything that changes an order is a
 * workflow transition and comes from the gate.
 */
final readonly class OrderTools
{
    public function __construct(
        private OrderRepository $orders,
        private OpenOrder $openOrder,
        private RegistrySynchronizer $synchronizer,
    ) {
    }

    /**
     * Lists all orders with their status.
     *
     * @return list<array{id: int, customer: string, total: int, status: string}>
     */
    #[McpTool(name: 'list_orders')]
    public function list(): array
    {
        return array_map(self::describe(...), $this->orders->all());
    }

    /**
     * Opens an order to work on it. The actions that are possible for it then appear as tools.
     *
     * @return array{id: int, customer: string, total: int, status: string}
     */
    #[McpTool(name: 'open_order')]
    public function open(int $id): array
    {
        $order = $this->orders->find($id) ?? throw new \InvalidArgumentException(\sprintf('There is no order %d.', $id));

        $this->openOrder->open($id);
        $this->synchronizer->synchronize();

        return self::describe($order);
    }

    /**
     * @return array{id: int, customer: string, total: int, status: string}
     */
    private static function describe(Order $order): array
    {
        return ['id' => $order->id, 'customer' => $order->customer, 'total' => $order->total, 'status' => $order->getStatus()];
    }
}
