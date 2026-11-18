<?php

declare(strict_types=1);

namespace App\Tests\WorkflowGate\Agent;

use App\Tests\WorkflowGate\Parcel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use WorkflowGate\Agent\WorkflowToolbox;

final class WorkflowToolboxTest extends TestCase
{
    public function testTheToolsFollowTheStateOfTheSubject(): void
    {
        $parcel = new Parcel();
        $toolbox = new WorkflowToolbox(Parcel::gate(), $parcel);

        self::assertSame(['send_parcel'], self::names($toolbox->getTools()));

        $result = $toolbox->execute(new ToolCall('1', 'send_parcel'));

        self::assertSame('Done: "send" was applied. The parcel is now "sent".', $result->getResult());
        self::assertSame('sent', $parcel->state);
        self::assertSame(['deliver_parcel'], self::names($toolbox->getTools()));
    }

    public function testAToolOfAnotherStateDoesNotExist(): void
    {
        $toolbox = new WorkflowToolbox(Parcel::gate(), new Parcel('packed'));

        $this->expectException(ToolNotFoundException::class);

        $toolbox->execute(new ToolCall('1', 'deliver_parcel'));
    }

    public function testUngatedOffersEveryToolAndTheWorkflowRefusesOnCall(): void
    {
        $parcel = new Parcel('packed');
        $toolbox = new WorkflowToolbox(Parcel::gate(), $parcel, gated: false);

        self::assertSame(['send_parcel', 'deliver_parcel'], self::names($toolbox->getTools()));

        $result = $toolbox->execute(new ToolCall('1', 'deliver_parcel'));

        self::assertStringStartsWith('Refused.', $result->getResult());
        self::assertSame('packed', $parcel->state);
    }

    /**
     * @param Tool[] $tools
     *
     * @return list<string>
     */
    private static function names(array $tools): array
    {
        return array_values(array_map(static fn (Tool $tool): string => $tool->getName(), $tools));
    }
}
