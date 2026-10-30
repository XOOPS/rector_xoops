# Changelog

All notable changes to `xoops/rector-xoops` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0-alpha2] - 2026-10-29

### Fixed

- `QueryFToQueryOrExecRector` rewrote every write it could not read as `query()`, which throws
  "query() called with a mutating statement" on 2.7. The keyword is now read through concatenation
  (`'DELETE FROM ' . $db->prefix('x')`), interpolation (`"UPDATE {$t} …"`), `sprintf()` and the
  `$sql` variable's assignments before the call in the same function or script; a call whose SQL
  cannot be classified is left as `queryF()`: a parameter, `global`, `foreach` or reference variable,
  a variable passed to another call (it may be taken by reference), a write after the call, an arrow function, or a literal that may stop inside the keyword
  (`'S' . 'ELECT …'`). Reads are the core's own set (`SELECT|WITH|SHOW|DESCRIBE|EXPLAIN`),
  so `SET`, `OPTIMIZE` … go to `exec()` exactly as the core routes them. `MysqlFunctionToXoopsDbRector`
  uses the same classifier.
- `AddAllowedClassesToUnserializeRector` added `['allowed_classes' => false]` to every call, which
  turns a stored object (a cached `CriteriaCompo`, a serialized `XoopsObject`) into
  `__PHP_Incomplete_Class`. It now rewrites only a call whose result is used solely in `is_array()`
  and truthiness/`false`/`null` tests. An array can hold objects and `count()`, `isset($x['k'])` and
  `array_keys()` also take a serialized `ArrayObject`, so any other use leaves the call alone; the
  rule now fires rarely by design.
- `HandlerConstructorDbTypeRector` typed `$db = null` as `\XoopsDatabase`, an implicitly nullable
  type deprecated in PHP 8.4; it now writes `?\XoopsDatabase`.
- `KernelIncludePathRector` rewrote the path inside any string, including a module's own
  `__DIR__ . '/class/…'`. It now rewrites only core-rooted paths (`XOOPS_ROOT_PATH . '…'`, and
  `path('…')` on `$xoops`, `$GLOBALS['xoops']` or `Xoops::getInstance()`), and covers the two other forwarding stubs: `class/xoopsuser.php` →
  `kernel/user.php`, `class/mimetypes.inc.php` → `include/mimetypes.inc.php`.

### Added

- `XoopsLoadAliasRector`: all eight deprecated `xoops_load()` short names the core maps
  (`cache`, `uploader`, `utility`, `captcha`, `file`, `model`, `calendar`, `userutility`), only for
  the `core`/`class` types and positional arguments. Replaces the built-in `xoops_load('cache')` configuration, which also
  rewrote `xoops_load('cache', 'mymodule')`.
- `LegacyCoreCallRector` and `GetLinkedUnameFromIdRector` replace the built-in
  `FuncCallToStaticCallRector` and `RenameStaticMethodRector` configurations. Rector 2.8 deprecated
  those rules (and `FunctionArgumentDefaultValueReplacerRector`) and no longer runs them, so the set
  printed deprecation warnings and silently skipped the rewrites. The old mappings were also wrong:
  `xoops_refcheck()` became a static call to the instance method `XoopsSecurity::checkReferer()`, a
  fatal error, and `xoops_getLinkedUnameFromId()` lost its `$linked = true` and the
  `xoops_load('XoopsUserUtility')` the removed function did.
- PHPUnit fixture tests for the custom rules (`composer test`, also run by `composer qa` and CI).
- README: the default set needs XOOPS 2.5.12 or later (`exec()`).

### Changed

- Requires `rector/rector` `^2.4` (file-level scope via `FileNode`).

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

[Unreleased]: https://github.com/xoops/rector_xoops/compare/v1.0.0-alpha2...HEAD
[1.0.0-alpha2]: https://github.com/xoops/rector_xoops/releases/tag/v1.0.0-alpha2
[1.0.0-alpha1]: https://github.com/xoops/rector_xoops/releases/tag/v1.0.0-alpha1
