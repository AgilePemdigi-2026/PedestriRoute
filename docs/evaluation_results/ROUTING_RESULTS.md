# Routing evaluation results

Run: 2026-11-25T11:56:23.725236+00:00

## Method

Executed the existing hazard geometry, route recommendation, and navigation suites using the current source, the existing PHP Docker image, and a newly created isolated PostGIS database. Only facility categories were seeded. Each suite used disposable filesystem writes; the evaluation database was dropped afterward. The application database was not used for feature-test records.

Feature tests use fixed OSRM HTTP responses and prohibit stray requests. No external API comparison or field study was performed.

## Execution outcomes

| Step | Exit code | Wall time (s) |
|---|---:|---:|
| create_database | 0 | 0.265 |
| migrations | 0 | 4.012 |
| category_seed | 0 | 1.717 |
| hazard_geometry | 0 | 1.278 |
| route_recommendation | 0 | 3.511 |
| route_navigation | 0 | 2.831 |
| paired_controlled_benchmark | 0 | 2.632 |
| drop_database | 0 | 0.452 |

Wall time includes container start-up and test setup. It is not route API latency or walking time.

## Detailed evidence

### create_database

```text
CREATE DATABASE
```

### migrations

```text
INFO  Preparing database.  

  Creating migration table ...................................... 24.95ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 84.80ms DONE
  0001_01_01_000001_create_cache_table .......................... 62.72ms DONE
  0001_01_01_000002_create_jobs_table .......................... 106.47ms DONE
  2026_10_18_000001_create_pedestriroute_tables ..................... 1dt DONE
  2026_10_18_040948_create_personal_access_tokens_table ......... 66.65ms DONE
  2026_10_18_130314_add_facebook_id_to_warga_table ............... 7.60ms DONE
  2026_10_26_100000_add_fields_to_kategori_masalah_table ........ 48.68ms DONE
  2026_11_01_130000_change_avatar_url_to_text_in_warga_table ..... 8.25ms DONE
  2026_11_01_150000_add_foto_perbaikan_to_laporan_table ......... 27.00ms DONE
```

### category_seed

```text
INFO  Seeding database.  

  Database\Seeders\KategoriMasalahSeeder ............................. RUNNING  
  Database\Seeders\KategoriMasalahSeeder .......................... 58 ms DONE
```

### hazard_geometry

```text
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.37
Configuration: /workspace/backend/phpunit.xml

....                                                                4 / 4 (100%)

Time: 00:00.002, Memory: 20.00 MB

OK (4 tests, 13 assertions)
```

### route_recommendation

```text
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.37
Configuration: /workspace/backend/phpunit.xml

.......                                                             7 / 7 (100%)

Time: 00:01.841, Memory: 48.50 MB

OK (7 tests, 75 assertions)
```

### route_navigation

```text
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.37
Configuration: /workspace/backend/phpunit.xml

....                                                                4 / 4 (100%)

Time: 00:01.144, Memory: 46.50 MB

OK (4 tests, 71 assertions)
```

### drop_database

```text
DROP DATABASE
```

## Paired controlled benchmark

Passed 52/54 route outcomes across 27 paired fixtures (9 scenarios × 3 radii). These are synthetic fixtures, not 27 independent real-world routes. Radius values: 10, 25, 40 m. Search budget is zero because candidate ranking and disclosure, rather than detour discovery, are being isolated.

Exposure references are analytic: a centre crossing exposes 2 × radius metres, an endpoint departure exposes radius metres, and the supplied detour is clear. Tolerance: 0.2 m. All fastest durations are compared with the minimum duration among supplied candidates.

| Scenario | Radius | Fastest / safest duration (s) | Fastest / safest exposure (m) | Pair passes |
|---|---:|---|---|---|
| no_reports | 10 | 200 / 200 | 0 / 0 | True |
| validated | 10 | 200 / 400 | 20 / 0 | True |
| pending | 10 | 200 / 200 | 0 / 0 | True |
| rejected | 10 | 200 / 200 | 0 / 0 | True |
| repaired | 10 | 200 / 200 | 0 / 0 | True |
| repair_active | 10 | 200 / 400 | 20 / 0 | True |
| endpoint | 10 | 200 / 400 | 10 / 10 | False |
| unavoidable | 10 | 200 / 200 | 20 / 20 | True |
| duration_first | 10 | 200 / 200 | 0 / 0 | True |
| no_reports | 25 | 200 / 200 | 0 / 0 | True |
| validated | 25 | 200 / 400 | 50 / 0 | True |
| pending | 25 | 200 / 200 | 0 / 0 | True |
| rejected | 25 | 200 / 200 | 0 / 0 | True |
| repaired | 25 | 200 / 200 | 0 / 0 | True |
| repair_active | 25 | 200 / 400 | 50 / 0 | True |
| endpoint | 25 | 200 / 200 | 25 / 25 | True |
| unavoidable | 25 | 200 / 200 | 50 / 50 | True |
| duration_first | 25 | 200 / 200 | 0 / 0 | True |
| no_reports | 40 | 200 / 200 | 0 / 0 | True |
| validated | 40 | 200 / 400 | 80 / 0 | True |
| pending | 40 | 200 / 200 | 0 / 0 | True |
| rejected | 40 | 200 / 200 | 0 / 0 | True |
| repaired | 40 | 200 / 200 | 0 / 0 | True |
| repair_active | 40 | 200 / 400 | 80 / 0 | True |
| endpoint | 40 | 200 / 400 | 40 / 40 | False |
| unavoidable | 40 | 200 / 200 | 80 / 80 | True |
| duration_first | 40 | 200 / 200 | 0 / 0 | True |

Maximum fastest candidate-duration gap: 0.000%.

For validated and active-repair fixtures, safest trades 200 s for 400 s to eliminate the supplied hazard intersection. This 100% time overhead is an input fixture property, not a measured estimate for Mulyorejo. In duration-first fixtures, the longer-distance detour is selected because its supplied duration is lower.

### Failed benchmark expectations

Failures remain part of the result. The endpoint fixtures expect the faster candidate when unavoidable exposures are equal within the 0.2 m evaluation tolerance. The current selector compares unrounded exposure first with no tolerance, before duration. The diagnostic values below are production-calculated candidate exposures, not independent reference measurements.

- endpoint, radius 10 m, safest: duration 400 s; failed checks ['expected_route', 'expected_duration']; candidate raw exposures [9.999993403578486, 9.999993403025265] m.
- endpoint, radius 40 m, safest: duration 400 s; failed checks ['expected_route', 'expected_duration']; candidate raw exposures [39.99997361274547, 39.999973611924226] m.
## Interpretation and remaining work

- Geometry cases independently expect approximately 50 m for one radius-25 m crossing, 61.12 m for overlapping circles, no encounter behind an endpoint, and blocking on mid-route re-entry.
- API fixtures check route selection, response fields, authentication, input validation, repair warnings, unavailable-network behavior, and navigation. Their outcomes are listed above; failures are retained.
- Mocked route tests establish fixture behavior, not OSRM network completeness, global optimality, live API latency, or physical walkability.
- No 30–50-pair real-network benchmark, exhaustive clear-route reference search, or field traversal was executed.
- Controlled duration-versus-distance and radius/status fixtures are included; live detour-search budget experiments remain outstanding.

## External comparison access

No external key is needed for the controlled tests in this report. For an independent live comparison, supply a pedestrian-routing provider endpoint and, if required, credentials through environment variables. A self-hosted Valhalla/GraphHopper instance can also serve as a comparison without a commercial API key. Using another OSRM endpoint is a useful consistency check but not a fully independent routing method.

A provider comparison needs a shared origin–destination manifest, walking profile, response geometry, distance, duration, snapping policy, map timestamp where available, and request quota. Map/provider differences must be separated from selection-algorithm differences. Commercial duration estimates do not establish globally fastest walking times, and no routing API alone establishes the safest route. Safety comparison requires independently labeled hazards or field-audited routes.
