<?php

declare(strict_types=1);

namespace App\Tests\WorkflowGate\Mcp;

use App\Tests\WorkflowGate\Parcel;
use Mcp\Capability\Registry;
use Mcp\Event\ToolListChangedEvent;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use WorkflowGate\Mcp\RegistrySynchronizer;
use WorkflowGate\TransitionBlocked;

final class RegistrySynchronizerTest extends TestCase
{
    private Registry $registry;
    private int $announcedChanges = 0;

    protected function setUp(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolListChangedEvent::class, function (): void {
            ++$this->announcedChanges;
        });

        $this->registry = new Registry($dispatcher);
    }

    public function testTheRegistryHoldsTheToolsOfTheCurrentState(): void
    {
        $parcel = new Parcel();
        $synchronizer = new RegistrySynchronizer($this->registry, Parcel::gate(), $parcel);

        $synchronizer->synchronize();
        self::assertSame(['send_parcel'], $this->toolNames());

        // The state changes outside of the MCP session.
        $parcel->state = 'sent';
        $synchronizer->synchronize();

        self::assertSame(['deliver_parcel'], $this->toolNames());
    }

    public function testAnUnchangedStateAnnouncesNothing(): void
    {
        $synchronizer = new RegistrySynchronizer($this->registry, Parcel::gate(), new Parcel());

        $synchronizer->synchronize();
        $synchronizer->synchronize();

        self::assertSame(1, $this->announcedChanges);
    }

    public function testCallingAToolAppliesTheTransitionAndUpdatesTheTools(): void
    {
        $parcel = new Parcel();
        (new RegistrySynchronizer($this->registry, Parcel::gate(), $parcel))->synchronize();

        $handler = $this->registry->getTool('send_parcel')->handler;
        self::assertInstanceOf(\Closure::class, $handler);

        $result = $handler();

        self::assertSame('Done: "send" was applied. The parcel is now "sent".', $result);
        self::assertSame('sent', $parcel->state);
        self::assertSame(['deliver_parcel'], $this->toolNames());
    }

    public function testAToolAClientStillHoldsIsRefusedOnceTheStateMovedOn(): void
    {
        $parcel = new Parcel();
        (new RegistrySynchronizer($this->registry, Parcel::gate(), $parcel))->synchronize();
        $staleHandler = $this->registry->getTool('send_parcel')->handler;
        self::assertInstanceOf(\Closure::class, $staleHandler);

        $parcel->state = 'delivered';

        try {
            $staleHandler();
            self::fail('The transition was applied.');
        } catch (TransitionBlocked) {
            self::assertSame('delivered', $parcel->state);
            self::assertSame([], $this->toolNames());
        }
    }

    /**
     * @return list<string>
     */
    private function toolNames(): array
    {
        return array_values(array_map(static fn (Tool $tool): string => $tool->name, iterator_to_array($this->registry->getTools())));
    }
}
