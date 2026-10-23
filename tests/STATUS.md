# Low List PHPUnit campaign

Status: **reviewed checkpoint published; coverage incomplete and source regressions red.** Production remains immutable.

- Origin: `https://github.com/EEHarbor/low_list.git`.
- Clean `develop` fetched and pulled with `--ff-only`; local/remote parity `0 0`.
- Pinned baseline: `86e4da6b2e585467b9c068e55f0190e69107fe4f`.
- Test branch: `unit-tests-2026`.
- No existing tests, tooling, dirty files, or additional worktrees at baseline.
- Shipped scope: four loose PHP files, 22 PHP archive entries and the PHAR extraction stub. `source-inventory.json` records every function/magic method/procedural file and source SHA-256; no shipped source is excluded.
- Archive SHA-256: `32a3538131e73a067d439d9d078f7b6a6abb5d576a01cbbd60c0deb7ec48144c`.
- Local pinned PHPUnit 9.6.38 runs on explicit Herd binaries. All three scaffold tool versions/hashes are in `.phpunit/versions.txt`; `.phpunit/install.sh` verifies downloads. Production dependencies are unchanged.
- Preflight command: `/Users/tomjaeger/.codex/skills/ee-addon-phpunit-coverage-writer/scripts/addon-phpunit-preflight.sh --repo-root "$PWD" --source-root low_list --scaffold --download-phars`; resolved command `./tests/.phpunit/run-phpunit.sh -c phpunit.xml`.
- Full seven-version execution, independent review, coverage evidence and publication details are recorded below.

Initial behavior inventory includes list/range/tag parameter normalization, renderer delegation, membership guards, static and persistent cache identity/false values/state, serialized global field settings, base-class compatibility aliases, installer Active Record calls, setup/autoload/view procedural paths, and native archive compile/load diagnostics.

Known unchanged source issues already reproduced: static `Pi::info()` uses `$this`; extension snake-case aliases discard caller arguments; static cache conflates false-like values and zero key components; persistent-cache request lookups use inconsistent keys; missing/malformed global settings emit diagnostics hidden by `@`.

## Reviewed checkpoint, October 23, 2026

Status: **coverage incomplete; source regressions intentionally red; independent review approved**. This is a useful tests-only checkpoint, not a 100% coverage milestone. The original production tree and PHAR are byte-for-byte unchanged from the pinned baseline.

### Complete runtime matrix

Command: `tests/run-matrix.sh`. Each iteration executes `PHP_BINARY="/Users/tomjaeger/Library/Application Support/Herd/bin/php<runtime>" tests/.phpunit/run-phpunit.sh -c phpunit.xml --log-junit "<unique-report-dir>/php<runtime>.xml"`. PHPUnit is 9.6.38 on every runtime. Tests run with E_ALL, strict diagnostics even under `@`, and native child loads use the identical binary. No global Herd/runtime/INI/service state is changed.

| Herd PHP | Tests | Assertions | Errors | Failures | Warnings | Risky | Skipped | Incomplete | Exit |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 7.4.33 | 158 | 706 | 5 | 15 | 0 | 0 | 0 | 0 | 2 |
| 8.0.30 | 158 | 700 | 6 | 15 | 0 | 0 | 0 | 0 | 2 |
| 8.1.33 | 158 | 699 | 7 | 15 | 0 | 0 | 0 | 0 | 2 |
| 8.2.30 | 158 | 699 | 7 | 15 | 0 | 0 | 0 | 0 | 2 |
| 8.3.28 | 158 | 699 | 7 | 15 | 0 | 0 | 0 | 0 | 2 |
| 8.4.15 | 158 | 699 | 7 | 15 | 0 | 0 | 0 | 0 | 2 |
| 8.5.0 | 158 | 699 | 7 | 15 | 0 | 0 | 0 | 0 | 2 |

`results/php-matrix.json` records every failing case and its diagnostic. Original fresh reports: `/var/folders/07/g6ql86ns3m75y257j7nb6pv00000gn/T/low-list-matrix.YEQpqn`. No tool/harness deprecations remain. PHP 8.5 random order (`--order-by=random --random-order-seed=23026`) also produced 158 tests, 699 assertions, 7 errors and 15 failures, with no additional diagnostics. All 23 test/support PHP files lint clean under explicit Herd PHP 7.4.

### Confirmed unchanged production defects

All paths below are original archive entries unless marked otherwise. Tests assert the intended safe result directly; they do not expect/suppress these diagnostics.

- `FluxCapacitor/Base/Pi.php:30`: static `info()` reads `$this`, causing an `Error` on every runtime.
- `FluxCapacitor/Base/Ext.php:189`: `register_extension` overwrites hook/priority/enabled arguments while forwarding. Its regression checks the original arguments.
- `FluxCapacitor/Bridge/EE2.php:47`: `version_compare` uses invalid operator `=<`, throwing `ValueError` on PHP 8+. This stops base-URL/session/module-URL vectors after the first call there; the route translation suite runs independently.
- `FluxCapacitor/Bridge/EE3.php:412` and `EE4.php:412`: an empty request path dereferences absent segment 3 under `@`.
- `FluxCapacitor/Bridge/EE3.php:100`, `EE4.php:100`, `EE5.php:100` (inherited by EE6): an active navigation alias stores the header object as the CP page title instead of the title string.
- `FluxCapacitor/Conduit/GlobalFieldSettings.php:21,33`: missing setting keys and malformed serialized storage emit diagnostics hidden with `@`.
- `FluxCapacitor/Conduit/StaticCache.php:18,106`: valid false-like values, including an empty array used by the documented API, do not round trip; zero key components collide. Existing shipped consumers also store integer/bool values (for example license existence/count and table validation), so these are meaningful supported vectors.
- `FluxCapacitor/Conduit/PersistentCache.php:21,44,79`: request-cache reads use unprocessed caller keys whereas writes use hashes; after a disk read the request cache also stores encoded data. Tests reproduce loss of the successfully cached value once the disk entry disappears during the request.
- `FluxCapacitor/Conduit/McpNav.php:7`: a native fresh load warns while including absent optional `low_list/Conduit/McpNav.php`. Strict E_ALL stops the load; the downstream navigation methods remain unmeasured rather than being faked with a nonexistent add-on file.
- `FluxCapacitor/Conduit/Version.php:200`: database null license values emit a `preg_replace` deprecation on PHP 8.1+.
- Original `low_list.phar` extraction stub line 177: when the real PHAR stream is disabled in a fresh child, extraction calls `@mkdir` on an already existing directory. Strict E_ALL catches `mkdir(): File exists` and stops fallback loading. All extraction writes are contained in the suite's unique temporary directory; the production archive is neither copied nor rebuilt for execution.

### Coverage evidence and exact gaps

Command: `tests/coverage.sh` with Herd PHP 8.3.28 and bundled Xdebug 3.5.3. It records original shipped source execution, raw basic-block graphs including `out`/`out_hit`, source/archive SHA-256 provenance, and distinct native-child graphs. No graphs are merged across incompatible conditional declarations or runtimes. Unique fresh reports: `/var/folders/07/g6ql86ns3m75y257j7nb6pv00000gn/T/low-list-coverage.qHt7qn`.

The first instrumented run measured 696/1325 lines and 433/1006 basic blocks, with four shipped files not compiled in the main process. The final run measured **1080/1565 executable lines and 734/1160 basic blocks among compiled source**. One shipped file (`McpNav.php`) remains uncompiled there; its missing denominator is unknown, never zero-percent or 100-percent completion. The baseline checkout had no runnable harness, so there was no valid pre-test baseline suite to claim as successful.

`results/coverage.json` contains every exact uncovered line and graph exit by source file/function; this is the primary branch-capable evidence. `results/loose-methods.json` and `results/archive-methods.json` are the required skill method reports from mapped Clover line data against verified byte-identical measurement copies. The helper labels line-complete methods `at_target` while reporting `branch_percent: null`; **that label does not establish branch closure**.

All five `Low_list` methods have 100% executable method lines. Their normal membership/colon/range/variable-name outcomes and nonempty loop exits are exercised. Still-unhit graph exits include constructor edge 0→17 (zero parameter definitions, impossible with the shipped four-element property), `each` edge 41→78 (zero items, impossible after ordinary `explode`/valid `range`), and synthetic terminal/exception exits. `each` always has at least one item even for an empty string. `_equate`'s early-return terminal exit records zero despite the guard returning in multiple passing tests. Main-script graphs similarly report zero block hits despite covered procedural lines; the direct-access guard is verified separately in a fresh child. These instrumentation limitations are retained, not silently converted into covered outcomes.

Other proven full-coverage blockers include immutable `FluxCapacitor::L = false` (for example `Version.php:187`), PHP < 5.3.6-only `Mcp::paginateModel` code on a required PHP 7.4+ matrix, Windows-only stub `tmpdir` paths on macOS, and fallback execution stopped by the original warning above. Source defects do not authorize source fixes.

Independent coverage remains open beyond those blockers: detailed CP license-save/redirect/pagination paths, additional legacy EE2 service/cache/view vectors, fieldtype legacy/entry-model/create-screen contexts, optional navigation methods after a safe native load, archive self-extraction/web/file-format failures, loader missing-archive/opcache configurations, and some bridge collaborator guards. The exact currently uncovered lines/exit IDs are retained in `results/coverage.json`; nothing is excluded to improve the reported percentage. Future work should continue those vectors if the campaign requires another checkpoint, while respecting the source immutability boundary.

### Review and publication

Self review completed: fixes included the PHP 7.4 native-exception boundary, faithful EE3 versus EE4+ collaborator APIs, shared operation recording for cloned legacy query builders, cross-test helper independence, unique temp roots, and file-backed native stdout/stderr to prevent a verbose Xdebug fatal from filling a pipe. The replacement coverage run completed normally. The coordinator assigned an independent exact-diff reviewer, who approved the corrected checkpoint. Review and origin-only publication evidence is recorded below; reviewed red-regression publication is authorized by the campaign contract.

Final preflight reports `PRECHECK_STATUS=ok` and resolves the launcher above. Its help pipeline also prints a nonblocking note; direct `tests/.phpunit/run-phpunit.sh --help` confirms `--coverage-clover` support. Actual fresh mapped Clover and native Xdebug reports are present, so the helper note is not used as coverage evidence.

Independent review found one test-collaborator defect in `Result::row`: its empty result, missing-column and numeric-index fallback differed from EE `DB_result`. The fixture now matches the verified native shape/current-row behavior and preserves SQL NULL; three focused contract cases pass with 17 assertions on PHP 8.5. The full seven-runtime matrix, random-order PHP 8.5 run and native Xdebug reports above were regenerated after this correction. The reviewer approved the corrected exact v2 diff; details follow below.

Independent reviewer `/root/tests_entry_mapper` approved corrected v2 patch SHA-256 `a51816f0853dc7d3e3a71e5c0cc03faee0d6574b3c87587ba502a24aed8d7140` for this incomplete checkpoint. A fresh private baseline-plus-patch checkout independently reproduced all seven 158-case matrix results and native Xdebug 1080/1565 line / 734/1160 basic-block totals, including the explicit uncompiled source and raw outgoing exits. The `Result::row` finding is resolved; no remaining actionable test/reporter defects were found. Residual risk remains the documented unchanged production failures and incomplete source/context/outcome coverage. Publication is authorized as reviewed red regressions, without a 100% completion claim.

Publication completed to **origin/unit-tests-2026** only. Reviewed changes are committed as `685316b` (pinned harness), `cd16a50` (list parsing/membership), and `2f38885` (archive/EE regression checkpoint). Initial published code HEAD `2f38885fe7be915778bfdcbc5c2abc4655bbc7e0` matched freshly fetched `origin/unit-tests-2026` and `git ls-remote`, with ahead/behind `0 0` and clean status. `git diff 86e4da6b2e585467b9c068e55f0190e69107fe4f -- low_list` is empty. This documentation-only publication record follows on the same branch; no PR, merge or production edit was made.

A final reciprocal review then identified one launcher guard gap: direct filters matching no tests could exit successfully despite the matrix's positive-JUnit guard. The follow-up sets `failOnEmptyTestSuite="true"`. Direct launcher checks with `--filter DefinitelyNoSuchTest --do-not-cache-result` now exit **1** with `No tests executed!` on both explicit Herd PHP 7.4 and 8.5. Fresh isolated evidence: `/var/folders/07/g6ql86ns3m75y257j7nb6pv00000gn/T/low-list-empty-v3-f9i7qlny`. The entire seven-runtime 158-case matrix was rerun after this configuration-only change and exactly retained the counts above, with no added diagnostics (`low-list-matrix.72jupM`). The narrow follow-up was independently approved and published; earlier source/code/coverage review remains valid.

Final guard follow-up approval: reviewer `/root/tests_entry_mapper` verified exact narrow patch SHA-256 `54016cfa9171f102ec415ec0f40b69c85e20e07d2d44b66e5d1d2aa03ea4c796` in a fresh private checkout. Independent no-match PHP 7.4/8.5 checks both exited 1 and the PHP 8.5 positive ResultContractTest passed 3 cases / 17 assertions. No actionable findings remain. Follow-up commit `ad3894ac14c62e483c552b1af26d2f80f0636f2e` was pushed to `origin/unit-tests-2026`; fresh fetch showed identical local/remote SHA and ahead/behind `0 0`, clean status, and no production changes. This final documentation-only record follows on that same branch.
