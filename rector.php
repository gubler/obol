<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\If_\NullableCompareToNullRector;
use Rector\Config\RectorConfig;
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
        strictBooleans: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withSkip([
        __DIR__ . '/assets/vendor/*',
        ReturnNeverTypeRector::class,
        OptionalParametersAfterRequiredRector::class,
        EncapsedStringsToSprintfRector::class,
        NullableCompareToNullRector::class,
        AddArrowFunctionReturnTypeRector::class,
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withRootFiles()
;
