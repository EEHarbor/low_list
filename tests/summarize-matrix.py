#!/usr/bin/env python3
"""Reject empty/stale matrix runs and report complete configured suites."""

import json
import pathlib
import sys
import xml.etree.ElementTree as ET

reports = pathlib.Path(sys.argv[1])
results = []
for runtime in ('74', '80', '81', '82', '83', '84', '85'):
    report = reports / f'php{runtime}.xml'
    root = ET.parse(report).getroot()
    suite = next(root.iter('testsuite'))
    cases = list(root.iter('testcase'))
    if not cases or int(suite.attrib['tests']) != len(cases):
        raise RuntimeError(f'Empty or inconsistent PHP {runtime} suite')
    diagnostics = []
    for case in cases:
        for child in case:
            if child.tag in ('error', 'failure', 'skipped'):
                diagnostics.append({'test': case.attrib['name'], 'kind': child.tag,
                                    'message': child.attrib.get('message', (child.text or '').split('\n')[0]),
                                    'details': (child.text or '').splitlines()[1:5]})
    result = {'runtime': (reports / f'php{runtime}-version.txt').read_text().splitlines()[0],
              'exit': int((reports / f'php{runtime}.exit').read_text()),
              'tests': len(cases), 'assertions': int(suite.attrib['assertions']),
              'errors': int(suite.attrib.get('errors', 0)),
              'failures': int(suite.attrib.get('failures', 0)),
              'skipped': int(suite.attrib.get('skipped', 0)),
              'warnings': int(suite.attrib.get('warnings', 0)),
              'risky': sum(1 for case in cases for issue in case if issue.tag == 'error' and 'RiskyTest' in issue.attrib.get('type', '')),
              'incomplete': sum(1 for case in cases for issue in case if issue.tag == 'skipped' and 'IncompleteTest' in issue.attrib.get('type', '')),
              'diagnostics': diagnostics}
    results.append(result)
    print(f"PHP {runtime}: {result['tests']} tests / {result['assertions']} assertions / {result['errors']} errors / {result['failures']} failures / {result['skipped']} skipped; exit {result['exit']}")
(reports / 'summary.json').write_text(json.dumps(results, indent=2) + '\n')
if any(result['exit'] != 0 for result in results):
    sys.exit(1)
