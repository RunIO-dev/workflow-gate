<?php

declare(strict_types=1);

namespace App\Tests\WorkflowGate\Agent;

use App\Tests\WorkflowGate\Parcel;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Tool\Tool;
use WorkflowGate\Agent\LiveToolsPlatform;
use WorkflowGate\Agent\WorkflowToolbox;

final class LiveToolsPlatformTest extends TestCase
{
    public function testEveryModelCallGetsTheToolsOfTheCurrentState(): void
    {
        $parcel = new Parcel();
        $inner = new class implements PlatformInterface {
            /** @var list<list<string>> */
            public array $sent = [];

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                $this->sent[] = array_values(array_map(static fn (Tool $tool): string => $tool->getName(), $options['tools'] ?? []));

                return MockPlatformFactory::createPlatform('ok')->invoke($model, $input);
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                return new FallbackModelCatalog();
            }
        };

        $platform = new LiveToolsPlatform($inner, new WorkflowToolbox(Parcel::gate(), $parcel));

        // The agent read its toolbox before the run, so it keeps passing the first tools.
        $stale = ['tools' => []];
        $platform->invoke('model', 'hello', $stale);
        $parcel->state = 'sent';
        $platform->invoke('model', 'hello', $stale);
        $parcel->state = 'delivered';
        $platform->invoke('model', 'hello', $stale);

        self::assertSame([['send_parcel'], ['deliver_parcel'], []], $inner->sent);
    }
}
