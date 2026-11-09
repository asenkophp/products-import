<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/config', __DIR__ . '/tests', __DIR__ . '/migrations', __DIR__ . '/public'])
    ->append([__DIR__ . '/bin/console']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS2.0'                 => true,
        '@PHP83Migration'            => true,
        'declare_strict_types'       => true,
        'strict_param'               => true,
        'no_unused_imports'          => true,
        'ordered_imports'            => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'single_quote'               => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match']],
        // выравнивание => и = в столбик, как в коде проекта
        'binary_operator_spaces'     => [
            'default'   => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal', '=' => 'align_single_space_minimal'],
        ],
    ])
    ->setFinder($finder);
