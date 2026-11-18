<?php

declare(strict_types=1);

namespace App\Tests\WorkflowGate;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use WorkflowGate\SubjectProviderInterface;
use WorkflowGate\TransitionGate;

/**
 * A test subject with a small workflow:
 * packed -(send)-> sent -(deliver)-> delivered, and sent -(lose)-> lost.
 * "lose" is not an agent tool and "send" is blocked for heavy parcels.
 */
final class Parcel implements SubjectProviderInterface
{
    public function __construct(
        public string $state = 'packed',
        public int $weight = 1,
    ) {
    }

    public function current(): self
    {
        return $this;
    }

    public static function gate(): TransitionGate
    {
        $send = new Transition('send', 'packed', 'sent');
        $deliver = new Transition('deliver', 'sent', 'delivered');
        $lose = new Transition('lose', 'sent', 'lost');

        /** @var \SplObjectStorage<Transition, array<mixed>> $metadata */
        $metadata = new \SplObjectStorage();
        $metadata[$send] = [TransitionGate::METADATA_KEY => 'Sends the parcel.'];
        $metadata[$deliver] = [TransitionGate::METADATA_KEY => 'Delivers the parcel.'];

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.parcel.guard.send', static function (GuardEvent $event): void {
            $parcel = $event->getSubject();
            \assert($parcel instanceof self);
            if ($parcel->weight > 30) {
                $event->addTransitionBlocker(new TransitionBlocker('Too heavy for the carrier.', 'weight'));
            }
        });

        $definition = new Definition(['packed', 'sent', 'delivered', 'lost'], [$send, $deliver, $lose], 'packed', new InMemoryMetadataStore(transitionsMetadata: $metadata));

        $registry = new Registry();
        $registry->addWorkflow(
            new StateMachine($definition, new MethodMarkingStore(true, 'state'), $dispatcher, 'parcel'),
            new InstanceOfSupportStrategy(self::class),
        );

        return new TransitionGate($registry);
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }
}
