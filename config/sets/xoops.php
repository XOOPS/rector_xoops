<?php

declare(strict_types=1);

/**
 * rector-xoops — the reusable XOOPS modernisation rule set.
 *
 * This file contains ONLY the XOOPS-specific rules and renames — no paths, no
 * skip list, no PHP upgrade sets, no cache. The consumer's rector.php controls
 * those. Reference it via Xoops\Rector\Set\XoopsSetList::XOOPS, or by path:
 *
 *     ->withSets([__DIR__ . '/vendor/xoops/rector-xoops/config/sets/xoops.php'])
 *
 * @license GPL-2.0-or-later
 */

use Rector\Arguments\Rector\FuncCall\FunctionArgumentDefaultValueReplacerRector;
use Rector\Arguments\ValueObject\ReplaceFuncCallArgumentDefaultValue;
use Rector\CodeQuality\Rector\Include_\AbsolutizeRequireAndIncludePathRector;
use Rector\Config\RectorConfig;
use Rector\Php70\Rector\FuncCall\MultiDirnameRector;
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\Rector\StaticCall\RenameStaticMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Rector\Renaming\ValueObject\RenameStaticMethod;
use Rector\Transform\Rector\FuncCall\FuncCallToStaticCallRector;
use Rector\Transform\ValueObject\FuncCallToStaticCall;
use Xoops\Rector\Rules\AddAllowedClassesToUnserializeRector;
use Xoops\Rector\Rules\BooleanLiteralStrictComparisonRector;
use Xoops\Rector\Rules\DropReferenceFromAssignmentRector;
use Xoops\Rector\Rules\GetMagicQuotesGpcToFalseRector;
use Xoops\Rector\Rules\GlobalXoopsOptionRector;
use Xoops\Rector\Rules\HandlerConstructorDbTypeRector;
use Xoops\Rector\Rules\KernelIncludePathRector;
use Xoops\Rector\Rules\MysqlFunctionToXoopsDbRector;
use Xoops\Rector\Rules\MysqlSelectDbToXoopsDbRector;
use Xoops\Rector\Rules\NewModuleAdminToXmfRector;
use Xoops\Rector\Rules\QueryFToQueryOrExecRector;
use Xoops\Rector\Rules\RemoveFuncCallRector;
use Xoops\Rector\Rules\RenameLegacyHttpVarsRector;
use Xoops\Rector\Rules\RenameMethodCallByNameRector;
use Xoops\Rector\Rules\RenameMethodWithAddedFirstArgRector;
use Xoops\Rector\Rules\SessionIsRegisteredToIssetRector;
use Xoops\Rector\Rules\StaticStringCallableToArrayRector;
use Xoops\Rector\Rules\UniqidMtRandStrictRector;

return static function (RectorConfig $rectorConfig): void {
    // Non-configurable rules.
    $rectorConfig->rules([
        // Code quality: './foo.php' → __DIR__ . '/foo.php' in includes.
        AbsolutizeRequireAndIncludePathRector::class,
        // Custom XOOPS rules.
        DropReferenceFromAssignmentRector::class,
        MysqlFunctionToXoopsDbRector::class,
        QueryFToQueryOrExecRector::class,
        BooleanLiteralStrictComparisonRector::class,
        RenameLegacyHttpVarsRector::class,
        AddAllowedClassesToUnserializeRector::class,
        StaticStringCallableToArrayRector::class,
        NewModuleAdminToXmfRector::class,
        // P0 batch (harvested from xoops2511B_php.txc).
        GlobalXoopsOptionRector::class,
        MultiDirnameRector::class, // built-in: dirname(dirname(__DIR__)) → dirname(__DIR__, 2)
        SessionIsRegisteredToIssetRector::class,
        GetMagicQuotesGpcToFalseRector::class,
        KernelIncludePathRector::class,
        UniqidMtRandStrictRector::class,
        // P1 — behaviour-preserving (fix removed API / type-only).
        MysqlSelectDbToXoopsDbRector::class,
        HandlerConstructorDbTypeRector::class,
    ]);

    // Method renames — XOOPS DB API deprecations (typed receivers).
    // NOTE: queryF() is handled by QueryFToQueryOrExecRector (keyword-aware
    // query/exec split), NOT a flat rename — exec() on a SELECT would break fetch.
    $rectorConfig->ruleWithConfiguration(RenameMethodRector::class, [
        new MethodCallRename('XoopsMySQLDatabase', 'quoteString', 'quote'),
        new MethodCallRename('XoopsDatabase', 'quoteString', 'quote'),
        new MethodCallRename('XoopsObject', 'makeTareaData4Show', 'displayTarea'),
    ]);

    // Function renames — PHP-name typos and XOOPS canonical casing.
    $rectorConfig->ruleWithConfiguration(RenameFunctionRector::class, [
        'set_errorHandler' => 'set_error_handler',
        'restore_errorHandler' => 'restore_error_handler',
        'set_exceptionHandler' => 'set_exception_handler',
        'xoops_gethandler' => 'xoops_getHandler',
        'xoops_getmodulehandler' => 'xoops_getModuleHandler',
    ]);

    // Function-to-static-method conversions.
    $rectorConfig->ruleWithConfiguration(FuncCallToStaticCallRector::class, [
        new FuncCallToStaticCall('xoops_refcheck', 'XoopsSecurity', 'checkReferer'),
        new FuncCallToStaticCall('xoops_getLinkedUnameFromId', 'XoopsUserUtility', 'getUnameFromId'),
    ]);

    // Static-method renames.
    $rectorConfig->ruleWithConfiguration(RenameStaticMethodRector::class, [
        new RenameStaticMethod('Database', 'getInstance', 'XoopsDatabaseFactory', 'getDatabaseConnection'),
    ]);

    // Argument-value renames: xoops_load('cache') → xoops_load('xoopscache').
    $rectorConfig->ruleWithConfiguration(FunctionArgumentDefaultValueReplacerRector::class, [
        new ReplaceFuncCallArgumentDefaultValue('xoops_load', 0, 'cache', 'xoopscache'),
    ]);

    // Statement-level removal of deprecated no-op calls.
    $rectorConfig->ruleWithConfiguration(RemoveFuncCallRector::class, [
        'mt_srand',
        'imagedestroy',
    ]);

    // Name-only method renames — for legacy receivers without type hints.
    // (queryF is handled by QueryFToQueryOrExecRector, not here.)
    $rectorConfig->ruleWithConfiguration(RenameMethodCallByNameRector::class, [
        'quoteString' => 'quote',

        // Deprecated MyTextSanitizer methods → current API.
        'makeTboxData4Edit' => 'htmlSpecialChars',
        'makeTboxData4Show' => 'htmlSpecialChars',
        'makeTboxData4Save' => 'addSlashes',
        'makeTboxData4Preview' => 'htmlSpecialChars',
        'makeTboxData4PreviewInForm' => 'htmlSpecialChars',
        'makeTareaData4Save' => 'addSlashes',
        'makeTareaData4InsideQuotes' => 'htmlSpecialChars',
        'makeTareaData4Edit' => 'htmlSpecialChars',
        'makeTareaData4Show' => 'displayTarea',
        'makeTareaData4Preview' => 'previewTarea',
        'makeTareaData4PreviewInForm' => 'htmlSpecialChars',
        'sanitizeForDisplay' => 'displayTarea',
        'sanitizeForPreview' => 'previewTarea',
        'oopsStripSlashesGPC' => 'stripSlashesGPC',
        'oopsStripSlashesRT' => 'stripSlashesGPC',
        'oopsAddSlashes' => 'addSlashes',
        'oopsNl2Br' => 'nl2Br',
        'oopsHtmlSpecialChars' => 'htmlSpecialChars',

        // Smarty 2 → Smarty 4 method renames (snake_case → camelCase). The further
        // Smarty 4 → 5 step (e.g. assignByRef → assign) is a separate future opt-in set.
        'append_by_ref' => 'appendByRef',
        'assign_by_ref' => 'assignByRef',
        'clear_all_assign' => 'clearAllAssign',
        'clear_assign' => 'clearAssign',
        'clear_cache' => 'clearCache',
        'clear_compiled_tpl' => 'clearCompiledTemplate',
        'clear_config' => 'clearConfig',
        'config_load' => 'configLoad',
        'get_config_vars' => 'getConfigVars',
        'get_registered_object' => 'getRegisteredObject',
        'get_template_vars' => 'getTemplateVars',
        'is_cached' => 'isCached',
        'load_filter' => 'loadFilter',
        'register_resource' => 'registerResource',
        'template_exists' => 'templateExists',
        'unregister_resource' => 'unregisterResource',

        // NOTE: ModuleAdmin render*->display* renames are intentionally NOT in the default
        // set. render*() RETURN a string; display*() ECHO and return void — safe only when the
        // result is echoed/discarded, a behaviour change when the value is captured
        // ($html = $admin->renderButton(...)). Enable them per-module if you want them — see
        // docs/TUTORIAL.md §3.4. (addNavigation->displayNavigation is even more suspect.)
    ]);

    // Smarty 2 register_*/unregister_* → registerPlugin/registerFilter(type, …).
    $rectorConfig->ruleWithConfiguration(RenameMethodWithAddedFirstArgRector::class, [
        'register_function' => ['registerPlugin', 'function'],
        'unregister_function' => ['unregisterPlugin', 'function'],
        'register_block' => ['registerPlugin', 'block'],
        'unregister_block' => ['unregisterPlugin', 'block'],
        'register_compiler_function' => ['registerPlugin', 'compiler'],
        'unregister_compiler_function' => ['unregisterPlugin', 'compiler'],
        'register_modifier' => ['registerPlugin', 'modifier'],
        'unregister_modifier' => ['unregisterPlugin', 'modifier'],
        'register_prefilter' => ['registerFilter', 'pre'],
        'unregister_prefilter' => ['unregisterFilter', 'pre'],
        'register_postfilter' => ['registerFilter', 'post'],
        'unregister_postfilter' => ['unregisterFilter', 'post'],
        'register_outputfilter' => ['registerFilter', 'output'],
        'unregister_outputfilter' => ['unregisterFilter', 'output'],
    ]);
};
