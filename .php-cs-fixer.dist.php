<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

const RULES = [
    '@PHP82Migration' => true,
    '@PHPUnit100Migration:risky' => true,
    '@PhpCsFixer' => true,
    '@PhpCsFixer:risky' => true,
    '@Symfony' => true,
    '@Symfony:risky' => true,
    'protected_to_private' => false,
    'no_unused_imports' => true,
    'strict_param' => true,
    'array_syntax' => ['syntax' => 'short'],
    'concat_space' => ['spacing' => 'one'],
    'php_unit_test_class_requires_covers' => false,
    'php_unit_internal_class' => false,
    'octal_notation' => false,
    'static_lambda' => false,
];

$finder = Finder::create()
    ->in(dirs: __DIR__)
    ->ignoreVCSIgnored(ignoreVCSIgnored: true)
    ->exclude(dirs: [
        'config/secrets',
        'public',
        'reference',
        'var',
    ])
;

return new Config()
    ->setParallelConfig(config: ParallelConfigFactory::detect())
    ->setRules(rules: RULES)
    ->setRiskyAllowed(isRiskyAllowed: true)
    ->setCacheFile(cacheFile: 'var/cache/php-cs-fixer.cache')
    ->setFinder(finder: $finder)
;
