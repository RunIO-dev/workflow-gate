<?php

declare(strict_types=1);

namespace App\Command;

use App\Order\Payments;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:pay', 'Simulates the payment provider reporting a payment for an order')]
final readonly class PayCommand
{
    public function __construct(
        private Payments $payments,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'The order that was paid')] int $order): int
    {
        $paid = $this->payments->receive($order);
        $io->success(\sprintf('Order %d is now "%s".', $paid->id, $paid->getStatus()));

        return Command::SUCCESS;
    }
}
