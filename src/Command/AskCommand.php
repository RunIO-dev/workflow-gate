<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\Mode;
use App\Agent\OrderDesk;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:ask', 'Asks the order desk agent to do something with an order')]
final readonly class AskCommand
{
    public function __construct(
        private OrderDesk $desk,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The order to work on')]
        int $order,
        #[Argument(description: 'What the agent should do')]
        string $message,
        #[Option(description: 'How the agent gets its tools: gated, all-tools or prompt-rules')]
        string $mode = 'gated',
    ): int {
        $run = $this->desk->ask($order, $message, Mode::from($mode));

        foreach ($run['calls'] as $call) {
            $io->writeln(\sprintf('  <comment>%s</comment> -> %s', $call['tool'], $call['result']));
        }
        $io->writeln($run['answer']);
        $io->writeln(\sprintf('<info>Order %d is now "%s".</info>', $run['order']->id, $run['order']->getStatus()));

        return Command::SUCCESS;
    }
}
