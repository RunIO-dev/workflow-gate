<?php

declare(strict_types=1);

namespace App\Controller;

use App\Agent\Mode;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AskRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $message,
        public Mode $mode = Mode::Gated,
    ) {
    }
}
