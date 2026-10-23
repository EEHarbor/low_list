# Low List tests

Install the pinned local tools with `tests/.phpunit/install.sh`. The launcher verifies PHPUnit 9.6.38's SHA-256 and uses an explicit Herd binary; it never changes global Herd settings or Composer dependencies.

```sh
PHP_BINARY='/Users/tomjaeger/Library/Application Support/Herd/bin/php83' tests/.phpunit/run-phpunit.sh -c phpunit.xml
tests/run-matrix.sh
tests/coverage.sh
```

The matrix runs the **complete configured suite** on Herd PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5. Every run uses a unique report directory and positive JUnit case counts. The strict test handler throws on E_ALL, including diagnostics hidden by `@`; native compilation/load probes install the same policy before loading source. Harness diagnostics fail too. Runtime changes do not alter production or global configuration.

Tests execute the original loose files and `low_list.phar`. The fieldtype fixture contains only the inherited settings/content-ID boundary and does not replace shipped add-on code. Other collaborators record real EE Active Record, template, cache, view, URL and alert interfaces. The template parameter fixture follows the observed `Template::fetch_param` defaults and legacy yes/no aliases in the current EE checkout. There is no network request, customer database or actual CP redirect.

`source-inventory.json` includes all 27 shipped PHP source units, including the original extraction stub, archive views, anonymous functions, magic methods and abstract declarations. Regenerate it with the explicit PHP binary and `tests/inventory.php`.

Coverage uses Xdebug's native line/basic-block/exit instrumentation and records PHP/Xdebug versions and source hashes. Every measurement copy under `.coverage-source` is verified byte-for-byte against shipped source and is used only for the skill's method parser. Production archives are never rebuilt. The Clover method helper's `branch_percent: null` remains unknown even when its generated `status` is `at_target`; consult `results/coverage.json` for actual graphs, `out` and `out_hit`.

The main-process report and native-child reports are separate. Conditional EE class declarations and fallback archive loading produce distinct compiled graphs; those results are not merged. Basic-block hit counts alone do not establish decision coverage. Synthetic return/exception exits and main-script graphs have Xdebug limitations described in `STATUS.md`. No report excludes unexecuted shipped code or treats an uncompiled file as complete.

Confirmed production defects remain ordinary failing tests. See `STATUS.md` for the exact results, blockers and remaining coverage gaps.
