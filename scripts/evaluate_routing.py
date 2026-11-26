#!/usr/bin/env python3
"""Run existing route tests in an isolated database and retain measured evidence.

Requires the existing infra-app image, infra_default network and PostGIS container.
No external routing calls: feature tests fake HTTP and prohibit stray requests.
"""

import argparse
from datetime import datetime, timezone
import json
from pathlib import Path
import subprocess
import time
import uuid
import re

ROOT = Path(__file__).resolve().parents[1]


def run(command):
    start = time.perf_counter()
    completed = subprocess.run(command, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    return dict(command=command, exit_code=completed.returncode,
                wall_seconds=time.perf_counter() - start,
                output=re.sub(r'\x1b\[[0-9;]*m', '', completed.stdout))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / 'docs/evaluation_results')
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    database = 'pedestriroute_eval_' + uuid.uuid4().hex[:12]
    result = dict(started_at=datetime.now(timezone.utc).isoformat(), isolated_database=database,
                  external_api_calls=False, steps=[])
    # Mount the current source read-only. All framework writes live in tmpfs.
    base = ['docker', 'run', '--rm', '--network', 'infra_default', '--read-only',
            '--tmpfs', '/tmp', '--tmpfs', '/workspace/backend/storage',
            '--tmpfs', '/workspace/backend/bootstrap/cache',
            '-v', f'{ROOT}:/workspace:ro', '-w', '/workspace/backend',
            '-e', 'APP_ENV=testing', '-e', 'APP_DEBUG=false',
            '-e', 'APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            '-e', 'DB_CONNECTION=pgsql', '-e', 'DB_HOST=db', '-e', 'DB_PORT=5432',
            '-e', f'DB_DATABASE={database}', '-e', 'DB_USERNAME=pedestriroute',
            '-e', 'DB_PASSWORD=secretpassword', '-e', 'CACHE_STORE=array',
            '-e', 'LOG_CHANNEL=stderr', '-e', 'BROADCAST_CONNECTION=null',
            '--entrypoint', 'php', 'infra-app', '-r',
            'foreach (["storage/framework/cache/data", "storage/framework/views", '
            '"storage/framework/sessions", "storage/logs"] as $d) {'
            'if (!is_dir($d)) { mkdir($d, 0777, true); }} '
            '$p=proc_open(array_slice($argv, 1), [0=>STDIN, 1=>STDOUT, 2=>STDERR], $pipes); '
            'exit(proc_close($p));', '--']
    create = run(['docker', 'exec', 'pedestriroute_db', 'psql', '-U', 'pedestriroute',
                  '-d', 'postgres', '-c', f'CREATE DATABASE {database};'])
    result['steps'].append(dict(name='create_database', **create))
    if create['exit_code']:
        raise RuntimeError(create['output'])
    try:
        migration = run(base + ['php', 'artisan', 'migrate', '--force', '--no-interaction'])
        result['steps'].append(dict(name='migrations', **migration))
        if migration['exit_code'] == 0:
            seed = run(base + ['php', 'artisan', 'db:seed', '--class=KategoriMasalahSeeder', '--force', '--no-interaction'])
            result['steps'].append(dict(name='category_seed', **seed))
            if seed['exit_code'] == 0:
                for name, path in [
                    ('hazard_geometry', 'tests/Unit/RouteHazardGeometryTest.php'),
                    ('route_recommendation', 'tests/Feature/Route/RouteRecommendationTest.php'),
                    ('route_navigation', 'tests/Feature/Navigation/RouteNavigationTest.php'),
                ]:
                    step = run(base + ['php', 'vendor/bin/phpunit', '--do-not-cache-result',
                                       '--log-junit', '/tmp/evaluation.xml', path])
                    result['steps'].append(dict(name=name, **step))
                benchmark = run(base + ['php', '../scripts/evaluate_routing_cases.php'])
                result['steps'].append(dict(name='paired_controlled_benchmark', **benchmark))
                if benchmark['exit_code'] == 0:
                    payloads = [line for line in benchmark['output'].splitlines() if line.startswith('{"rows":')]
                    if payloads:
                        result['paired_benchmark'] = json.loads(payloads[-1])
                    else:
                        result['benchmark_parse_error'] = 'No JSON benchmark payload found; see retained output.'
        else:
            result['blocked'] = 'Database migrations failed; feature tests not executed.'
    finally:
        result['steps'].append(dict(name='drop_database', **run([
            'docker', 'exec', 'pedestriroute_db', 'psql', '-U', 'pedestriroute',
            '-d', 'postgres', '-c', f'DROP DATABASE {database} WITH (FORCE);'])))
    result['finished_at'] = datetime.now(timezone.utc).isoformat()
    (args.output / 'routing_results.json').write_text(json.dumps(result, indent=2) + '\n')
    lines = ['# Routing evaluation results', '', f"Run: {result['started_at']}", '',
             '## Method', '',
             'Executed the existing hazard geometry, route recommendation, and navigation suites using the current source, '
             'the existing PHP Docker image, and a newly created isolated PostGIS database. Only facility categories were seeded. '
             'Each suite used disposable filesystem writes; the evaluation database was dropped afterward. '
             'The application database was not used for feature-test records.', '',
             'Feature tests use fixed OSRM HTTP responses and prohibit stray requests. No external API comparison or field study was performed.', '',
             '## Execution outcomes', '', '| Step | Exit code | Wall time (s) |', '|---|---:|---:|']
    for step in result['steps']:
        lines.append(f"| {step['name']} | {step['exit_code']} | {step['wall_seconds']:.3f} |")
    lines += ['', 'Wall time includes container start-up and test setup. It is not route API latency or walking time.', '',
              '## Detailed evidence', '']
    for step in result['steps']:
        if step['name'] == 'paired_controlled_benchmark' and 'paired_benchmark' in result:
            continue
        lines += [f"### {step['name']}", '', '```text', step['output'].strip(), '```', '']
    if 'paired_benchmark' in result:
        benchmark = result['paired_benchmark']
        lines += ['## Paired controlled benchmark', '',
                  f"Passed {benchmark['passed']}/{benchmark['total']} route outcomes across 27 paired fixtures (9 scenarios × 3 radii). "
                  'These are synthetic fixtures, not 27 independent real-world routes. Radius values: 10, 25, 40 m. '
                  'Search budget is zero because candidate ranking and disclosure, rather than detour discovery, are being isolated.', '',
                  'Exposure references are analytic: a centre crossing exposes 2 × radius metres, an endpoint departure exposes radius metres, and the supplied detour is clear. '
                  'Tolerance: 0.2 m. All fastest durations are compared with the minimum duration among supplied candidates.', '',
                  '| Scenario | Radius | Fastest / safest duration (s) | Fastest / safest exposure (m) | Pair passes |',
                  '|---|---:|---|---|---|']
        rows = benchmark['rows']
        for i in range(0, len(rows), 2):
            f, s = rows[i:i + 2]
            lines.append(f"| {f['scenario']} | {f['radius_m']} | {f['duration_s']} / {s['duration_s']} | "
                         f"{f['exposure_m']} / {s['exposure_m']} | {f['passed'] and s['passed']} |")
        gaps = [r['candidate_duration_gap_percent'] for r in rows if r['preference'] == 'fastest']
        lines += ['', f'Maximum fastest candidate-duration gap: {max(gaps):.3f}%.', '',
                  'For validated and active-repair fixtures, safest trades 200 s for 400 s to eliminate the supplied hazard intersection. '
                  'This 100% time overhead is an input fixture property, not a measured estimate for Mulyorejo. '
                  'In duration-first fixtures, the longer-distance detour is selected because its supplied duration is lower.', '']
        failures = [r for r in rows if not r['passed']]
        if failures:
            lines += ['### Failed benchmark expectations', '',
                      'Failures remain part of the result. The endpoint fixtures expect the faster candidate when unavoidable exposures are equal within the 0.2 m evaluation tolerance. '
                      'The current selector compares unrounded exposure first with no tolerance, before duration. '
                      'The diagnostic values below are production-calculated candidate exposures, not independent reference measurements.', '']
            for r in failures:
                lines.append(f"- {r['scenario']}, radius {r['radius_m']} m, {r['preference']}: "
                             f"duration {r['duration_s']} s; failed checks {[k for k, v in r['checks'].items() if not v]}; "
                             f"candidate raw exposures {r['candidate_raw_exposure_m']} m.")
    lines += ['## Interpretation and remaining work', '',
              '- Geometry cases independently expect approximately 50 m for one radius-25 m crossing, 61.12 m for overlapping circles, no encounter behind an endpoint, and blocking on mid-route re-entry.',
              '- API fixtures check route selection, response fields, authentication, input validation, repair warnings, unavailable-network behavior, and navigation. Their outcomes are listed above; failures are retained.',
              '- Mocked route tests establish fixture behavior, not OSRM network completeness, global optimality, live API latency, or physical walkability.',
              '- No 30–50-pair real-network benchmark, exhaustive clear-route reference search, or field traversal was executed.',
              '- Controlled duration-versus-distance and radius/status fixtures are included; live detour-search budget experiments remain outstanding.', '',
              '## External comparison access', '',
              'No external key is needed for the controlled tests in this report. For an independent live comparison, supply a pedestrian-routing provider endpoint '
              'and, if required, credentials through environment variables. A self-hosted Valhalla/GraphHopper instance can also serve as a comparison without a commercial API key. '
              'Using another OSRM endpoint is a useful consistency check but not a fully independent routing method.', '',
              'A provider comparison needs a shared origin–destination manifest, walking profile, response geometry, distance, duration, snapping policy, '
              'map timestamp where available, and request quota. Map/provider differences must be separated from selection-algorithm differences. '
              'Commercial duration estimates do not establish globally fastest walking times, and no routing API alone establishes the safest route. '
              'Safety comparison requires independently labeled hazards or field-audited routes.']
    (args.output / 'ROUTING_RESULTS.md').write_text('\n'.join(lines) + '\n')
    print(f"Wrote {args.output / 'ROUTING_RESULTS.md'}")
    for step in result['steps']:
        print(step['name'], step['exit_code'])


if __name__ == '__main__':
    main()
