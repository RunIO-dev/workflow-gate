<?php

declare(strict_types=1);

namespace App\Agent;

/**
 * How the order desk agent gets its tools. Only the first one is Workflow
 * Gate, the other two exist to compare against.
 */
enum Mode: string
{
    /** The tools are the transitions the workflow allows now. */
    case Gated = 'gated';

    /** Every tool in every state, the workflow refuses on call. */
    case AllTools = 'all-tools';

    /** Every tool in every state, the rules are only written in the prompt. */
    case PromptRules = 'prompt-rules';
}
