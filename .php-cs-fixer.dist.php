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
    // CS Fixer 3.95+ ships a rule that removes declare(strict_types=1)
    // from every file under @Symfony / @PhpCsFixer. The project convention
    // (CLAUDE.md) is the opposite — every code file declares strict types.
    // Explicit override keeps the declares in place.
    'declare_strict_types' => true,
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
    // maxProcesses caps what detect() would otherwise size to every core (measured at ~660% CPU on
    // a 12-core box). This runs on every commit via the pre-commit hook as well as in CI, which
    // shares the same physical machine as local work. Deliberate - do not drop back to a bare detect().
    ->setParallelConfig(config: ParallelConfigFactory::detect(maxProcesses: 4))
    ->setRules(rules: RULES)
    ->setRiskyAllowed(isRiskyAllowed: true)
    ->setCacheFile(cacheFile: 'var/cache/php-cs-fixer.cache')
    ->setFinder(finder: $finder)
;
