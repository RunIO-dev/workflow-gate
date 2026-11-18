<?php

declare(strict_types=1);

namespace App\Order;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stores the demo orders in a JSON file, so the demo runs without a database.
 */
final readonly class OrderRepository
{
    private const SEED = [
        ['id' => 1001, 'customer' => 'Ada', 'total' => 120, 'status' => 'draft'],
        ['id' => 1002, 'customer' => 'Linus', 'total' => 80, 'status' => 'paid'],
        ['id' => 1003, 'customer' => 'Grace', 'total' => 900, 'status' => 'paid'],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/data/%kernel.environment%/orders.json')]
        private string $file,
    ) {
    }

    /**
     * @return list<Order>
     */
    public function all(): array
    {
        $rows = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true, flags: \JSON_THROW_ON_ERROR) : self::SEED;

        return array_values(array_map(static fn (array $row): Order => new Order($row['id'], $row['customer'], $row['total'], $row['status']), $rows));
    }

    public function find(int $id): ?Order
    {
        foreach ($this->all() as $order) {
            if ($order->id === $id) {
                return $order;
            }
        }

        return null;
    }

    public function save(Order $order): void
    {
        $rows = array_map(
            static function (Order $stored) use ($order): array {
                $current = $stored->id === $order->id ? $order : $stored;

                return ['id' => $current->id, 'customer' => $current->customer, 'total' => $current->total, 'status' => $current->getStatus()];
            },
            $this->all(),
        );

        if (!is_dir(\dirname($this->file))) {
            mkdir(\dirname($this->file), recursive: true);
        }
        file_put_contents($this->file, json_encode($rows, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR), \LOCK_EX);
    }

    public function reset(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }
}
