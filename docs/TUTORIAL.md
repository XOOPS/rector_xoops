# rector-xoops — Tutorial & Rule Reference

A practical guide for XOOPS module developers: what `xoops/rector-xoops` does, how to run it on a
module, what every rule means, and how to write your own rules.

- **Tool runtime:** PHP 8.2+ · Rector 2.x. (The tool *runs* on 8.2+ even when modernising a
  XoopsCore25/PHP-7.4 module — Rector can target older output.)
- **Generated-code target:** whatever you set with `withPhpVersion()` / `withPhpSets()` — usually
  `php74` for XoopsCore25 modules and `php82` for XoopsCore27 (XOOPS 2.7's floor); go higher only if
  you're deliberately raising the module's PHP floor. The XOOPS rules themselves are PHP-level-agnostic.
- **Scope:** PHP only — it never touches `.tpl` files (Smarty templates are a separate XOOPS upgrade step).
- **Namespace:** `Xoops\Rector\` (PSR-4 → `src/`). Rules in `Xoops\Rector\Rules\`, sets via
  `Xoops\Rector\Set\XoopsSetList`.

> **Reading this as a wiki?** Each numbered section below is self-contained and maps to one wiki
> page — the Contents list is the suggested **Home / sidebar**. (When you split it, this page becomes
> *Home*, and each `## N.` heading becomes a page; the sub-headings under §3 and §4 are that page's
> in-page sections.)

## Contents

1. [Why a XOOPS Rector package?](#1-why-a-xoops-rector-package) — the AST-vs-regex rationale
2. [Install & run on a module](#2-install--run-on-a-module) — prerequisites · `rector.php` · the two sets · standalone sweep
3. [Rule reference](#3-rule-reference-what-each-rule-is-for) — database · removed PHP · XOOPS API · MyTS & Smarty · risky set · planned Smarty 5
4. [Writing your own rule](#4-writing-your-own-rule) — anatomy · idempotency · configurable rules · traits · register · test
5. [Tips for XOOPS module developers](#5-tips-for-xoops-module-developers)
6. [What this package deliberately does NOT do](#6-what-this-package-deliberately-does-not-do)
7. [Contributing](#7-contributing)

---

## 1. Why a XOOPS Rector package?

Modernising 200+ legacy XOOPS modules by hand — or with fragile find/replace scripts — is slow and
error-prone. Rector rewrites code on the **AST** (abstract syntax tree), so it understands structure:
it won't corrupt a string, a comment, or a language constant the way a regex can. This package bundles
the XOOPS-specific transforms (DB API, removed PHP functions, the `$xoopsDB` wrapper, MyTextSanitizer,
Smarty PHP API, …) as proper, tested Rector rules so any module can be modernised the same way, twice,
and safely.

> **PHP-only.** Rector parses PHP. Smarty **template** (`.tpl`) migration is handled separately by the
> XOOPS upgrade preflight scanner, not here.

---

## 2. Install & run on a module

### Prerequisites

This package is a *rule library* — you also need **Rector itself** and a **`rector.php`** in the
module. Require both (dev only):

```bash
composer require --dev rector/rector xoops/rector-xoops
```

### Quick-start checklist

1. `composer require --dev rector/rector xoops/rector-xoops`
2. Create `rector.php` (below) with your paths + `XoopsSetList::XOOPS`.
3. **Dry-run** — read the diff: `vendor/bin/rector process --dry-run`
4. **Apply**, then run the module's tests / load it with `XOOPS_DEBUG` on: `vendor/bin/rector process`
5. Handle `.tpl` templates **separately** (the XOOPS upgrade preflight scanner, not this tool).

### `rector.php`

**You** choose the PHP level via `withPhpSets()`; the XOOPS set only adds the XOOPS-specific rules, so
it composes cleanly. Add a `withSkip()` for paths that must never be rewritten:

```php
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Xoops\Rector\Set\XoopsSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/class', __DIR__ . '/admin', __DIR__ . '/include', __DIR__ . '/blocks'])
    ->withPhpVersion(PhpVersion::PHP_82)        // your TARGET runtime (php74 for XoopsCore25)
    ->withPhpSets(php82: true)                  // PHP upgrade level for the GENERATED code
    ->withSets([XoopsSetList::XOOPS])           // XOOPS modernisation rules
    ->withSkip([
        '*/vendor/*', '*/language/*', '*/templates/*', '*/templates_c/*',
    ]);
```

### Running it

This package ships **no** `composer rector` scripts — run Rector's binary directly, or add your own
scripts to the **module's** `composer.json`:

```bash
vendor/bin/rector process --dry-run   # preview, change nothing
vendor/bin/rector process             # apply
```

```jsonc
// optional, in the MODULE's composer.json:
"scripts": { "rector": "rector process --dry-run", "rector:fix": "rector process" }
```

**Always dry-run one module first, read the diff, then run the module's test suite / load it with
`XOOPS_DEBUG` on.** Roll out module-by-module; never run unattended on `system` or `protector`.

### Two sets

| Set | What it contains | When |
|---|---|---|
| `XoopsSetList::XOOPS` | **behaviour-preserving** modernisation (DB API, removed-function fixes, type-only) | default — safe to run |
| `XoopsSetList::XOOPS_RISKY` | **behaviour-changing** rewrites (input filtering, escaping, output encoding) | opt-in; review every diff |

```php
// opt into the risky set in addition to the default:
->withSets([XoopsSetList::XOOPS, XoopsSetList::XOOPS_RISKY])
```

### Standalone sweep (a whole tree)

For a one-off pass across many modules, the bundled `rector-xoops.php` adds `withPhpSets(php84: true)`,
an example path/skip list, and the **default `XOOPS` set only** (it does **not** enable `XOOPS_RISKY`):

```bash
# config path depends on layout — from the package root use rector-xoops.php; from a consumer use vendor/...
vendor/bin/rector process /path/to/htdocs/modules/news \
  --config=vendor/xoops/rector-xoops/rector-xoops.php --dry-run
```

---

## 3. Rule reference (what each rule is for)

There are **22 custom rules** in `src/Rules/` plus several **configured built-in** Rector rules. The
**default set** (`config/sets/xoops.php`) registers the behaviour-preserving ones; the **risky set**
(`config/sets/xoops-risky.php`) the behaviour-changing ones. For the exact, authoritative list, read
the two set files — this section explains them by domain.

### 3.1 Database API (XOOPS 2.7)

XOOPS 2.7 splits the old `queryF()` into `query()` (reads, returns a result) and `exec()` (writes,
returns bool). `quoteString()` → `quote()`. The legacy `mysql_*` / `mysqli_*` functions are gone.

| Rule | Before → After | Notes |
|---|---|---|
| `MysqlFunctionToXoopsDbRector` | `mysql_query("SELECT …")` → `$GLOBALS['xoopsDB']->query(…)`; `mysql_query("UPDATE …")` → `…->exec(…)`; `mysqli_error($link)` → `…->error()` | Routes `*_query` to `query`/`exec` by the **SQL keyword**; drops the `$link` arg for connection-first `mysqli_*`. |
| `QueryFToQueryOrExecRector` | `$db->queryF("SELECT …")` → `$db->query(…)`; `$db->queryF("DELETE …")` → `$db->exec(…)` | Same keyword routing for explicit `queryF()`. A flat `queryF`→`exec` would break SELECTs. |
| `MysqlSelectDbToXoopsDbRector` | `mysql_select_db($n)` → `mysqli_select_db($GLOBALS['xoopsDB']->conn, $n)` | Fixes a removed PHP-7 function. |
| `HandlerConstructorDbTypeRector` | `function __construct($db)` → `function __construct(\XoopsDatabase $db)` | **Only** on classes extending `XoopsPersistableObjectHandler`/`XoopsObjectHandler` (matches the parent signature). |
| built-in `RenameMethodRector` (typed) | `XoopsMySQLDatabase`/`XoopsDatabase::quoteString()` → `quote()`; `XoopsObject::makeTareaData4Show()` → `displayTarea()` | Configured in the set. |

The SQL keyword classification is shared via the `Xoops\Rector\Support\SqlKeywordDetector` trait.

### 3.2 Removed / deprecated PHP

| Rule | Before → After |
|---|---|
| `RenameLegacyHttpVarsRector` | `$HTTP_POST_VARS` → `$_POST` (and `_GET`/`_SERVER`/`_COOKIE`/…) |
| `SessionIsRegisteredToIssetRector` | `session_is_registered($k)` → `isset($_SESSION[$k])` |
| `GetMagicQuotesGpcToFalseRector` | `get_magic_quotes_gpc()` → `false` (replaces the call only; dead `if (false)` branches may then be removed by your PHP/dead-code sets) |
| `UniqidMtRandStrictRector` | `uniqid(mt_rand(), 1)` → `uniqid((string) mt_rand(), true)` |
| `RemoveFuncCallRector` *(configurable)* | drops statement-level `mt_srand()` / `imagedestroy()` |
| `DropReferenceFromAssignmentRector` | `$x =& foo()` / `=& new` / `=& Class::m()` → `$x = …` (call/`new` RHS only) |
| `StaticStringCallableToArrayRector` | `'static::m'` → `[static::class, 'm']` (PHP 8.5) |
| `AddAllowedClassesToUnserializeRector` | `unserialize($x)` → `unserialize($x, ['allowed_classes' => false])` |
| `BooleanLiteralStrictComparisonRector` | `== true/false/null` → `=== …` (both directions) |
| built-in `MultiDirnameRector` | `dirname(dirname(dirname(__DIR__)))` → `dirname(__DIR__, 3)` |
| built-in `AbsolutizeRequireAndIncludePathRector` | `require './foo.php'` → `require __DIR__ . '/foo.php'` |

### 3.3 XOOPS framework API

| Rule | Before → After |
|---|---|
| `GlobalXoopsOptionRector` | `$xoopsOption['template_main'] = …` → `$GLOBALS['xoopsOption']['template_main'] = …` |
| `KernelIncludePathRector` | moved kernel include paths: `class/xoopsobject.php`→`kernel/object.php`, `class/xoopsmodule.php`→`kernel/module.php` (include paths only) |
| `NewModuleAdminToXmfRector` | `new ModuleAdmin()` → `\Xmf\Module\Admin::getInstance()` |
| built-in `RenameFunctionRector` | `xoops_gethandler`→`xoops_getHandler`, `xoops_getmodulehandler`→`xoops_getModuleHandler`, `set_errorHandler`→`set_error_handler`, `restore_errorHandler`→`restore_error_handler`, `set_exceptionHandler`→`set_exception_handler` |
| built-in `FuncCallToStaticCallRector` | `xoops_refcheck()`→`XoopsSecurity::checkReferer()`, `xoops_getLinkedUnameFromId()`→`XoopsUserUtility::getUnameFromId()` |
| built-in `RenameStaticMethodRector` | `Database::getInstance()`→`XoopsDatabaseFactory::getDatabaseConnection()` |
| built-in `FunctionArgumentDefaultValueReplacerRector` | `xoops_load('cache')`→`xoops_load('xoopscache')` |

### 3.4 MyTextSanitizer & Smarty (method renames)

`RenameMethodCallByNameRector` (name-only, for untyped receivers) carries these maps in the default set:

- **`quoteString` → `quote`** (the XOOPS DB value-quoter rename).
- **MyTextSanitizer** deprecated methods → current API: `makeTarea*`/`makeTbox*`/`sanitizeFor*`/`oops*`
  → `displayTarea`/`previewTarea`/`htmlSpecialChars`/`addSlashes`/`stripSlashesGPC`/`nl2Br`.
- **Smarty 2 → 4** PHP API: `assign_by_ref`→`assignByRef`, `clear_compiled_tpl`→`clearCompiledTemplate`,
  `is_cached`→`isCached`, `get_template_vars`→`getTemplateVars`, … — these are the **Smarty 4** forms.
  (The further Smarty-4→5 step, e.g. `assignByRef`→`assign`, is a separate, future opt-in set — see §3.6.)

`RenameMethodWithAddedFirstArgRector` handles the Smarty dispatcher migration (rename + prepend a
literal arg): `register_function(…)`→`registerPlugin('function', …)`, `register_prefilter(…)`→
`registerFilter('pre', …)`, and the block/modifier/compiler/postfilter/outputfilter variants.

#### ModuleAdmin `render*` → `display*` — opt-in (NOT in the default set)

`\Xmf\Module\Admin` (and legacy `ModuleAdmin`) has both `renderX()` (returns a string) and `displayX()`
(echoes, returns void). A name-only `renderIndex`→`displayIndex` / `renderButton`→`displayButton`
rename is **only safe when the result is echoed or discarded** — it **breaks** any site that captures
the return value (`$html = $admin->renderButton(...)`). Because that is not behaviour-preserving, it is
deliberately **left out of the default set**. Enable it per-module *after* checking your call sites:

```php
use Xoops\Rector\Rules\RenameMethodCallByNameRector;

return RectorConfig::configure()
    // … your paths / sets …
    ->withConfiguredRule(RenameMethodCallByNameRector::class, [
        'renderIndex'  => 'displayIndex',
        'renderButton' => 'displayButton',
    ]);
```

### 3.5 Risky set (opt-in, behaviour-changing)

These ship in `XoopsSetList::XOOPS_RISKY` because they change runtime values/output. The MyTS rules are
**receiver-aware** (only `$myts` / `$GLOBALS['myts']`, via the `MytsReceiverDetector` trait).

| Rule | Before → After | Why it's risky |
|---|---|---|
| `ServerSuperglobalToXmfRequestRector` *(configurable)* | `$_SERVER['HTTP_REFERER']` → `\Xmf\Request::getString('HTTP_REFERER', '', 'SERVER')` | raw → filtered |
| `RemoveDeadMytsStripSlashesRector` | `$myts->stripSlashesGPC($x)` → `$x` | no-op on 7.4+, but depends on the MyTS build |
| `MytsAddSlashesToDbEscapeRector` | `$myts->addSlashes($x)` → `$GLOBALS['xoopsDB']->escape($x)` | only valid in a SQL context |
| `MytsHtmlSpecialCharsToNativeRector` | `$myts->htmlSpecialChars($x)` → `htmlspecialchars($x, ENT_QUOTES \| ENT_SUBSTITUTE, _CHARSET)` | may change entity handling |

### 3.6 Planned: `XOOPS_SMARTY5` (future opt-in set)

The Smarty **4 → 5** PHP-API changes (`assignByRef`→`assign`, `appendByRef`→`append`, the
property→setter moves like `template_dir = …`→`setTemplateDir(…)`, removed constants) are **not**
shipped yet — XOOPS 2.7.x runs Smarty **4**, so applying them prematurely would break working code.
They are planned as a gated `XoopsSetList::XOOPS_SMARTY5` set, enabled only when the core engine
actually moves to Smarty 5. **This constant does not exist yet** — there is no `XOOPS_SMARTY5` in
`XoopsSetList`, so don't go looking for it; the note is here to set expectations, not to document a
shipped feature.

---

## 4. Writing your own rule

A rule is a small class under `src/Rules/` that extends `Rector\Rector\AbstractRector` and answers
three questions: **which node types do I care about**, **how do I transform a matching node**, and
**what's my documentation example**.

### 4.1 Anatomy of a rule

```php
<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class MyExampleRector extends AbstractRector
{
    /** Which AST node classes Rector should hand to refactor(). Narrow = faster. */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /** Return a new/mutated node to replace $node, or null to leave it unchanged. */
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return null;
        }
        if ($node->name->toLowerString() !== 'old_function') {
            return null;
        }
        $node->name = new Name('new_function');
        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Renames old_function() to new_function().',
            [new CodeSample('old_function($x);', 'new_function($x);')]
        );
    }
}
```

### 4.2 The rules that matter

1. **Match precisely, bail early.** Re-check the instance and every shape assumption at the top of
   `refactor()` and `return null` the moment something doesn't fit. A loose rule is how you corrupt
   code.
2. **Idempotency / fix-point.** Rector runs every rule repeatedly until nothing changes. Your output
   must **not** re-match your own rule, or you get an infinite loop. (E.g. after renaming `old`→`new`,
   the node is now `new`, which your check skips.)
3. **`?Node` return.** Return the changed node (mutated or freshly built) to replace it, or `null` to
   skip. To **remove** a statement, return `\PhpParser\NodeVisitor::REMOVE_NODE` (widen the return type
   to `int|null` — see `RemoveFuncCallRector`).
4. **Find the node classes you need** by running Rector with `--debug`, or read php-parser's
   `PhpParser\Node\*` (Expr, Stmt, Scalar). Common ones: `FuncCall`, `MethodCall`, `StaticCall`,
   `New_`, `Assign`, `ArrayDimFetch`, `Variable`, `Name`, `Identifier`, `Scalar\String_`,
   `Scalar\Int_`, `Expr\ConstFetch`.
5. **Case-insensitivity.** Function/method names are case-insensitive in PHP. Compare with
   `->toLowerString()` (on `Name`) or `strtolower(...)`.

### 4.3 Configurable rules

To accept configuration from the set file, implement `ConfigurableRectorInterface` and a `configure()`
method (see `RemoveFuncCallRector`, `RenameMethodCallByNameRector`, `RenameMethodWithAddedFirstArgRector`,
`ServerSuperglobalToXmfRequestRector`):

```php
use Rector\Contract\Rector\ConfigurableRectorInterface;

final class RenameMethodCallByNameRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var array<string,string> */
    private array $map = [];

    /** @param mixed $configuration */
    public function configure($configuration): void
    {
        $this->map = is_array($configuration) ? $configuration : [];
    }
    // … refactor() reads $this->map …
}
```

Configured in the set with `ruleWithConfiguration()`:

```php
$rectorConfig->ruleWithConfiguration(RenameMethodCallByNameRector::class, [
    'oldMethod' => 'newMethod',
]);
```

### 4.4 Reuse shared logic with a trait

Cross-cutting helpers live in `src/Support/`. Two examples already in the package:

- `SqlKeywordDetector::isWriteSql(?Arg $arg): bool` — classifies a SQL literal as read vs write
  (used by the DB-routing rules).
- `MytsReceiverDetector::isMytsReceiver(Expr $var): bool` — true only for `$myts` / `$GLOBALS['myts']`
  (used by the receiver-aware MyTS rules).

`use` the trait in your rule rather than copying the logic.

### 4.5 Register the rule

Add it to the appropriate set:

```php
// config/sets/xoops.php  (behaviour-preserving)  — or  config/sets/xoops-risky.php
$rectorConfig->rules([
    // …
    \Xoops\Rector\Rules\MyExampleRector::class,
]);
```

**Decide which set.** If the rule is behaviour-preserving or fixes outright-broken (removed-function)
code → default `XOOPS` set. If it changes runtime values, escaping, or output → `XOOPS_RISKY`.

### 4.6 Test it

The fastest loop is a fixture: a tiny "before" snippet plus a one-rule config file. This works the
same on **PowerShell and bash** (no Unix-only process substitution):

1. `fix.php` — the "before":
   ```php
   <?php
   $x = old_function(1);
   ```
2. `rector-fixture.php` — a throwaway config enabling just your rule:
   ```php
   <?php
   use Rector\Config\RectorConfig;

   return RectorConfig::configure()
       ->withPaths([__DIR__ . '/fix.php'])
       ->withRules([\Xoops\Rector\Rules\MyExampleRector::class]);
   ```
3. Run it — and run it **twice**. The second pass must be a **no-op** (proves your rule is idempotent):
   ```bash
   vendor/bin/rector process --config=rector-fixture.php --dry-run
   ```

Then keep the package green:

```bash
composer analyse   # PHPStan
composer cs:fix    # PHP-CS-Fixer
```

The CI workflow (`.github/workflows/ci.yml`) runs `composer validate` + CS + PHPStan + an autoload
smoke test (every rule class + the set list resolve) across PHP 8.2/8.3/8.4 on every push.

---

## 5. Tips for XOOPS module developers

- **Run the PHP pass (this tool) first, the template pass second.** Smarty `.tpl` changes are a
  separate XOOPS upgrade step.
- **Make your module's `rector.php` path-tolerant.** A module may have `class/` but no `src/` (or
  vice-versa). Filter your paths with `is_dir` so a missing directory never errors (the bundled
  standalone `rector-xoops.php` does exactly this; this package's own `rector.php` is just QA config):
  `->withPaths(array_filter([__DIR__.'/class', __DIR__.'/src', __DIR__.'/admin'], 'is_dir'))`. Add
  `->withRootFiles()` to also include top-level `*.php`.
- **Profiles / PHP level.** The XOOPS set is PHP-level-agnostic; set `withPhpSets()` to your target
  (`php82` for XoopsCore27, `php74` for XoopsCore25 — run the tool on PHP 8.2+ either way; Rector can
  target older output).
- **The DB read/write split is keyword-based.** It classifies *literal* SQL. Dynamic SQL
  (`$db->queryF($sql)` where `$sql` is a variable) defaults to `query()` — review those by hand.
- **Skip what you can't safely change.** `vendor/`, `language/`, `templates/`, generated files —
  exclude them via `withSkip()`.
- **Commit the reformat separately.** Adopting the tool may reformat a module; keep that in its own
  commit and add the SHA to `.git-blame-ignore-revs` so `git blame` stays useful.

---

## 6. What this package deliberately does NOT do

Several common find/replace migrations are unsafe as AST rules and are intentionally omitted (e.g.
`include`→`require`, `or die`→`||`, `_handler`→`Handler`, SQL-string rewrites like `TYPE=MyISAM`,
by-ref param → typed). They change semantics or live in strings/comments. Handle those with review.

---

## 7. Contributing

1. Fork, branch.
2. Add the rule under `src/Rules/` (+ a `Support/` trait if shared), register it in the right set.
3. `composer analyse` + `composer cs:fix` must pass.
4. Update the rule reference in **this file (§3)** and `CHANGELOG.md` — the TUTORIAL is the single
   source of truth; `README.md` only carries a short overview.
5. Open a PR.

License: GNU GPL 2.0 or later.
