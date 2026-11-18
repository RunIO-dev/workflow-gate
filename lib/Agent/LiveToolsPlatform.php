<?php

declare(strict_types=1);

namespace WorkflowGate\Agent;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Sends the current tools of the toolbox with every model call.
 *
 * An agent reads its toolbox once per call, so a tool that appears while the
 * agent works (the order was just paid, so it can be shipped) would only be
 * offered on the next user message.
 */
final readonly class LiveToolsPlatform implements PlatformInterface
{
    public function __construct(
        private PlatformInterface $platform,
        private ToolboxInterface $toolbox,
    ) {
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $options['tools'] = $this->toolbox->getTools();
        if ([] === $options['tools']) {
            unset($options['tools']);
        }

        return $this->platform->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
