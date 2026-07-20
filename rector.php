<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\CodingStyle\Rector\If_\NullableCompareToNullRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\YieldDataProviderRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/assets',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withConfiguredRule(EncapsedStringsToSprintfRector::class, [
        // Keep concatenation for simple strings; only reach for sprintf when
        // literal text is interleaved with the values, never wholesale.
        EncapsedStringsToSprintfRector::ALWAYS => false,
    ])
    ->withSkip([
        __DIR__ . '/assets/vendor/*',
        ReturnNeverTypeRector::class,
        OptionalParametersAfterRequiredRector::class,
        // 'fn' -> fn(...) rewrites (e.g. \call_user_func(...)) read poorly here.
        FunctionFirstClassCallableRector::class,
        NullableCompareToNullRector::class,
        AddArrowFunctionReturnTypeRector::class,
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
        // The house style (enforced by php-cs-fixer's php_unit_test_case_static_method_calls)
        // is `self::assert*` in tests; this rule wants `$this->assert*` and would fight it forever.
        PreferPHPUnitThisCallRector::class,
        // php-cs-fixer keeps stateless test helpers `static`; this rule wants them as instance
        // methods, so the two would ping-pong. Stateless helpers staying static is fine.
        LocallyCalledStaticMethodToNonStaticRector::class,
        // Drops casts it deems redundant, but strips `(string)` off NumberFormatter::getSymbol()
        // (which returns string|false), breaking type safety. The casts are deliberate.
        RecastingRemovalRector::class,
        // Rewrites array data providers to yield but drops the @return docblock, so PHPStan loses
        // the iterable value type. Our providers are array-style throughout; keep them uniform.
        YieldDataProviderRector::class,
    ])
    ->withRootFiles()
    // Cap the worker count instead of letting Rector auto-detect cores and take the whole machine
    // (measured at ~830% CPU and 2.5 GiB on a 12-core box with a cold cache). This runs on every
    // `mise run check` and in CI, and the CI runner shares the same physical machine as local work,
    // so an uncapped run stalls everything else. Four keeps most of the parallel win; the cost is
    // wall-clock on cold runs. Deliberate - do not restore auto-detect.
    ->withParallel(maxNumberOfProcess: 4)
;
