# rector-xoops — automated XOOPS module modernisation

Custom [Rector](https://getrector.com) rules and a ready-to-use rule **set** that
automate the safe, mechanical parts of XOOPS module modernisation (DB-API
renames, `mysql_*` → `$xoopsDB`, legacy superglobals, reference-assignment
cleanup, `unserialize` hardening, the PHP 8.5 string-callable fix, and more).

- **Tool runtime:** PHP 8.2+ · Rector 2.x (runs on 8.2+ even when modernising a Core25/PHP-7.4 module)
- **Generated-code target:** whatever your `rector.php` sets — usually `php74` for XoopsCore25, `php82`
  for XoopsCore27 (higher only if you raise the module's PHP floor)
- **Pairs with:** the [XOOPS module skeleton](https://github.com/XoopsModules27x/module-skeleton) —
  its `rector.php` can pull this set in directly.

---

> 📖 **New here?** See [`docs/TUTORIAL.md`](docs/TUTORIAL.md) for a full walkthrough — every rule
> explained, how to run it on a module, and how to write your own rules.

## Install

```bash
composer require --dev rector/rector "xoops/rector-xoops:^1.0@alpha"
```

> **Pre-release:** the only published version is `1.0.0-alpha1`. The `@alpha` flag lets Composer
> install it without changing your project's global `minimum-stability`. Drop the flag once a stable
> `1.0.0` is tagged: `composer require --dev xoops/rector-xoops`.

## Use it in a module (recommended)

Reference the set from the module's own `rector.php`. **You** choose the PHP level
(via `withPhpSets`); this set only adds the XOOPS-specific rules, so it composes
cleanly with the skeleton's profile-pinned config:

```php
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Xoops\Rector\Set\XoopsSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/class', __DIR__ . '/admin', __DIR__ . '/include'])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPhpSets(php82: true)
    ->withSets([XoopsSetList::XOOPS]);
```

Then run Rector's binary directly (this package ships **no** `composer rector` scripts — add them to
the module's own `composer.json` if you want the shortcut):

```bash
vendor/bin/rector process --dry-run   # preview
vendor/bin/rector process             # apply
```

## Standalone full-sweep (a whole XOOPS tree)

For one-off modernisation across many modules, use the bundled `rector-xoops.php`,
which enables the **default `XOOPS` set only** (not `XOOPS_RISKY`), an example
path/skip list, and `withPhpSets(php84: true)`. That php84 sweep is **aggressive** —
lower it to `php82` (XoopsCore27) or `php74` (XoopsCore25) to match the module's PHP floor:

```bash
# Always dry-run one module first:
vendor/bin/rector process /path/to/htdocs/modules/songlist --config=rector-xoops.php --dry-run

# Apply when the diff looks sane:
vendor/bin/rector process /path/to/htdocs/modules/songlist --config=rector-xoops.php
```

`rector-xoops.php` bootstraps this package's Composer autoloader, so it resolves
the XOOPS rules even when invoked by a Rector binary installed elsewhere in the tree.

> **Do I need to create a `.rector-cache/` folder?** No. Rector creates it
> automatically the first time it runs (the configs point `withCache()` at it).
> It is **git-ignored** and must not be committed — it's a machine-local, stale-prone
> cache. Delete it any time to force a clean run.

---

## What it does

`xoops/rector-xoops` ships **22 custom rules** plus several configured built-in
Rector rules, organised into two sets. The set is PHP-level-agnostic — your
`rector.php` picks the PHP target (see *Install* above).

- **`XoopsSetList::XOOPS`** (default, behaviour-preserving) — the XOOPS 2.7 DB API
  (`query`/`exec` split, `quoteString`→`quote`), removed/deprecated PHP (legacy
  superglobals, `session_is_registered`, `get_magic_quotes_gpc`, `=&` cleanup,
  `unserialize` hardening, …), XOOPS framework API (`new ModuleAdmin()`→
  `\Xmf\Module\Admin`, kernel include paths, handler/function renames), and
  MyTextSanitizer + **Smarty 2→4** PHP-API renames.
- **`XoopsSetList::XOOPS_RISKY`** (opt-in, behaviour-changing — review every diff) —
  `$_SERVER`→`\Xmf\Request`, and the receiver-aware `$myts` escaping/slashes rewrites.

```php
->withSets([XoopsSetList::XOOPS, XoopsSetList::XOOPS_RISKY]) // risky is opt-in
```

**Not in the default set:** the ModuleAdmin `render*`→`display*` renames (opt-in — not
behaviour-preserving when the return value is captured), and the future Smarty 4→5
`XOOPS_SMARTY5` set (planned, not shipped).

> 📖 **Full rule-by-rule reference** — every rule with before→after, the configured
> built-ins, how to write your own, and what it deliberately does *not* do — lives in
> **[`docs/TUTORIAL.md`](docs/TUTORIAL.md)** (§3 rules · §4 authoring · §6 non-goals).
> The tutorial is the single source of truth; this section is just an overview.

---

## Package layout

```
composer.json                 package + PSR-4 autoload ("Xoops\Rector\\" => src/)
src/Set/XoopsSetList.php       XoopsSetList::XOOPS → config/sets/xoops.php
src/Rules/*.php                the 22 custom rule classes (one per file, Xoops\Rector\Rules\*)
src/Support/*.php              shared detector traits (SqlKeywordDetector, MytsReceiverDetector)
config/sets/xoops.php          the reusable rule set (no paths/PHP-sets)
rector-xoops.php              standalone full-sweep config (php84 + example paths)
rector.php / phpstan.neon.dist / .php-cs-fixer.dist.php   the package's own QA
.github/workflows/ci.yml      library CI (validate, CS, PHPStan, autoload smoke test)
```

## Adding new rules

1. **Mechanical AST transform, no ambiguity?** Add a class under `src/Rules/`
   (one class per file, namespace `Xoops\Rector\Rules`) and register it in
   `config/sets/xoops.php`.
2. **Per-call judgement** (e.g. "use `getVar('n')` for HTML fields)? Leave it as a
   documented lesson — it can't be automated safely.
3. **Detection-only** (e.g. "no new `global $xoopsDB`")? Make it a PHPStan rule, not Rector.

## License

GNU GPL 2.0 or later.
