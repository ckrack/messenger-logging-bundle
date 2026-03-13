<?php

declare(strict_types=1);

$config = new PhpCsFixer\Config();

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'vendor',
    ])
;

$config
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
    ])
;

return $config;
