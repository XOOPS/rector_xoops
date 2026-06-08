# Changelog

All notable changes to `xoops/rector-xoops` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-alpha1] - 2026-06-08

First (alpha) release — a Composer-installable Rector extension that automates the safe, mechanical
parts of XOOPS module modernisation. **22 custom rules** plus several configured built-in rules,
across two sets.

### Added

**Default set `XoopsSetList::XOOPS`** (behaviour-preserving):

- *Database (XOOPS 2.7 `query`/`exec` split):* `MysqlFunctionToXoopsDbRector` (`mysql_*`/`mysqli_*` →
  `$GLOBALS['xoopsDB']->…`; `*_query` routed to `query()`/`exec()` by SQL keyword; drops the connection
  arg for connection-first `mysqli_*`), `QueryFToQueryOrExecRector`, `MysqlSelectDbToXoopsDbRector`,
  `HandlerConstructorDbTypeRector` (types an untyped `$db` ctor param `\XoopsDatabase` on handler subclasses).
- *Removed / deprecated PHP:* `RenameLegacyHttpVarsRector`, `SessionIsRegisteredToIssetRector`,
  `GetMagicQuotesGpcToFalseRector`, `UniqidMtRandStrictRector`, `RemoveFuncCallRector`
  (`mt_srand`/`imagedestroy`), `DropReferenceFromAssignmentRector`, `StaticStringCallableToArrayRector`,
  `AddAllowedClassesToUnserializeRector`, `BooleanLiteralStrictComparisonRector`.
- *XOOPS framework API:* `GlobalXoopsOptionRector`, `KernelIncludePathRector`, `NewModuleAdminToXmfRector`.
- *MyTextSanitizer & Smarty (via `RenameMethodCallByNameRector` / `RenameMethodWithAddedFirstArgRector`):*
  deprecated MyTS methods → current API; Smarty 2 → Smarty 4 PHP-API renames; the Smarty `register_*`
  dispatcher migration (`register_function`→`registerPlugin('function', …)`, …).
- *Configured built-ins:* `MultiDirnameRector`, `AbsolutizeRequireAndIncludePathRector`,
  `RenameMethodRector` (`quoteString`→`quote`), `RenameFunctionRector`
  (`xoops_gethandler`/`xoops_getmodulehandler` casing, error/exception-handler typos),
  `FuncCallToStaticCallRector` (`xoops_refcheck`, `xoops_getLinkedUnameFromId`),
  `RenameStaticMethodRector` (`Database::getInstance()`), `FunctionArgumentDefaultValueReplacerRector`
  (`xoops_load('cache')`→`'xoopscache'`).

**Opt-in set `XoopsSetList::XOOPS_RISKY`** (behaviour-changing — review every diff):

- `ServerSuperglobalToXmfRequestRector` (default key `HTTP_REFERER`), `RemoveDeadMytsStripSlashesRector`,
  `MytsAddSlashesToDbEscapeRector`, `MytsHtmlSpecialCharsToNativeRector`. The MyTS rules are
  receiver-aware (`$myts` / `$GLOBALS['myts']` only).

**Packaging & tooling:**

- PSR-4 autoload (`Xoops\Rector\` → `src/`); requires PHP >= 8.2 and `rector/rector ^2.0`.
- Shared `SqlKeywordDetector` / `MytsReceiverDetector` traits.
- Standalone full-sweep config `rector-xoops.php` (`withPhpSets(php84: true)` + example paths;
  default `XOOPS` set only).
- GitHub Actions CI (PHP 8.2/8.3/8.4: `composer validate`, PHP-CS-Fixer, PHPStan, autoload smoke test),
  Dependabot, and the package's own PHPStan / PHP-CS-Fixer / Rector config.
- README rule reference + `docs/TUTORIAL.md` (rule guide + how to write your own rules).

### Notes

- ModuleAdmin `render*`→`display*` renames are a documented **per-module opt-in**, deliberately **not**
  in the default set (`render*` returns a string, `display*` echoes — not behaviour-preserving when the
  return value is captured). See `docs/TUTORIAL.md` §3.4.
- Rules were harvested from the `xoops2511B_php.txc` and `Smarty4.txc` TextCrawler scripts and
  cross-checked against independent reviews. Template-side (`.tpl`) Smarty changes are out of scope —
  this tool is PHP-AST only.

[1.0.0-alpha1]: https://github.com/xoops/rector_xoops/releases/tag/v1.0.0-alpha1
