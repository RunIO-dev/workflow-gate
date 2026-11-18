<?php

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder((new PhpCsFixer\Finder())->in([__DIR__.'/lib', __DIR__.'/src', __DIR__.'/tests']));
