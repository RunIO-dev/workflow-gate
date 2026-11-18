<?php

declare(strict_types=1);

namespace App\Tests\WorkflowGate;

use PHPUnit\Framework\TestCase;
use WorkflowGate\TransitionBlocked;

final class TransitionGateTest extends TestCase
{
    public function testOnlyTheTransitionsOfTheCurrentStateAreTools(): void
    {
        $gate = Parcel::gate();

        self::assertSame(['send_parcel'], array_keys($gate->tools(new Parcel('packed'))));
        self::assertSame(['deliver_parcel'], array_keys($gate->tools(new Parcel('sent'))));
        self::assertSame([], $gate->tools(new Parcel('delivered')));
    }

    public function testAToolCarriesTheDescriptionFromTheTransitionMetadata(): void
    {
        $tool = Parcel::gate()->tools(new Parcel())['send_parcel'];

        self::assertSame('Sends the parcel.', $tool->description);
        self::assertSame('send', $tool->transition);
    }

    public function testATransitionWithoutTheMetadataIsNeverATool(): void
    {
        $gate = Parcel::gate();

        self::assertArrayNotHasKey('lose_parcel', $gate->tools(new Parcel('sent')));
        self::assertArrayNotHasKey('lose_parcel', $gate->allTools(new Parcel('sent')));
    }

    public function testAGuardedTransitionIsNotATool(): void
    {
        self::assertSame([], Parcel::gate()->tools(new Parcel('packed', weight: 40)));
    }

    public function testAllToolsIgnoresTheState(): void
    {
        self::assertSame(['send_parcel', 'deliver_parcel'], array_keys(Parcel::gate()->allTools(new Parcel('delivered'))));
    }

    public function testApplyMovesTheSubject(): void
    {
        $parcel = new Parcel();

        Parcel::gate()->apply($parcel, 'send');

        self::assertSame('sent', $parcel->state);
    }

    public function testApplyRefusesATransitionTheStateDoesNotAllow(): void
    {
        $parcel = new Parcel('packed');

        try {
            Parcel::gate()->apply($parcel, 'deliver');
            self::fail('The transition was applied.');
        } catch (TransitionBlocked $blocked) {
            self::assertStringContainsString('"deliver"', $blocked->getMessage());
        }

        self::assertSame('packed', $parcel->state);
    }

    public function testApplyRefusesWithTheReasonOfTheGuard(): void
    {
        $parcel = new Parcel('packed', weight: 40);

        $this->expectException(TransitionBlocked::class);
        $this->expectExceptionMessage('Too heavy for the carrier.');

        Parcel::gate()->apply($parcel, 'send');
    }
}
