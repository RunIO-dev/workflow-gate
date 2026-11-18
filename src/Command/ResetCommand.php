<?php

declare(strict_types=1);

namespace App\Command;

use App\Order\OrderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:reset', 'Puts the demo orders back into their initial state')]
final readonly class ResetCommand
{
    public function __construct(
        private OrderRepository $orders,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->orders->reset();
        $io->success('The demo orders are back in their initial state.');

        return Command::SUCCESS;
    }
}
