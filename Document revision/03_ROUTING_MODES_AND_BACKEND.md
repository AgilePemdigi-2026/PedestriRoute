# 3. Routing modes and technical backend

## 3.1 User-facing behavior

| Mode | API value | Selection goal | Active reports | Distance constraint |
|---|---|---|---|---|
| Safest / Teraman | `safest` | Avoid blocking reports, prefer better WSM conditions | Blocking zones forbidden; unavoidable endpoint exposure disclosed | No explicit detour-distance cap |
| Balanced / Balance | `balanced` | Trade condition and limited report exposure against detour | Default tolerance 3, with penalties; fallback can exceed tolerance and is disclosed | `max(1.5 × shortest, shortest + 500 m)` |
| Fastest / Tercepat | `fastest` | Efficient pedestrian route without safety penalties | Allowed, but shown and measured | No safety-detour search |

“Safest” refers to the application's known reports and condition scoring, not
a guarantee that unreported hazards do not exist. The modes share pedestrian
network validation; fastest does not switch to a driving route.

Example: for a 1,000 m shortest route, balanced's default cap is **1,500 m**.
A report-free 2,500 m detour can be considered by safest but is outside the
balanced cap. This is an illustrative policy example, not a measured route.

Source: [`RouteRecommendationService.php`](../backend/app/Services/RouteRecommendationService.php),
especially `recommend()`, `detourCap()`, `candidateRank()`, and `formatResponse()`.
Some comments still mention a safest cap/tolerance; executable code applies
those policies to balanced, with safest using infinity and zero tolerance.

## 3.2 Request flow and engine order

```text
Authenticated POST /api/v1/routes/recommend
  → validate origin, destination, preference
  → load active validated reports
  → PostGIS pedestrian graph + A* (default first choice)
  → Valhalla pedestrian if the graph cannot serve the trip
  → OSRM foot if needed
  → recheck chosen geometry against current report zones
  → attach WSM scores, hazard sections, warnings, and turn instructions
```

`CFG_ROUTE_ENGINE=auto` uses this order. `graph` forces the local graph;
`valhalla` attempts Valhalla and falls back to OSRM if unavailable; `osrm`
uses OSRM. The response identifies the actual engine as `postgis_astar`,
`valhalla_pedestrian`, or `osrm_foot`.

For safest, failure to find an acceptable route produces HTTP **422** with
`NO_REPORT_FREE_ROUTE`, rather than returning a route through blocking reports.
Engine/network coverage failures can instead produce **503**, including
`PEDESTRIAN_ROUTE_UNAVAILABLE` or `PEDESTRIAN_ENGINE_UNAVAILABLE`.
Failure to read reports produces `REPORT_DATA_UNAVAILABLE` (503); unknown
report data are not treated as an empty report list.

## 3.3 Which reports affect routes?

[`RouteReportRepository.php`](../backend/app/Services/RouteReportRepository.php)
joins `laporan`, `lokasi`, and `kategori_masalah`. A report is active when:

- `laporan.status = TERVALIDASI`;
- `status_perbaikan IS NULL` or equals `SEDANG_DIPERBAIKI`;
- latitude and longitude exist.

Pending, rejected, and completed-repair reports do not form active zones.
All active reports are loaded, including those outside the original corridor,
so a detour is checked against hazards encountered along the detour too.

Each active point has a default **25 m circular avoidance zone**. Geometry
assessment checks entire line segments, including sparse polylines; it does
not merely compare report distance to route vertices.

[`RouteHazardGeometry.php`](../backend/app/Services/RouteHazardGeometry.php)
clips edges at zone boundaries and builds non-overlapping `hazard_sections`.
Overlapping zones retain their report IDs without counting the same section
length twice. Repair exposure is marked separately.

### Endpoint exception

If the snapped start or destination lies inside a report zone, a contiguous
departure/arrival segment can be marked `endpoint_unavoidable`. Re-entering
the zone mid-trip is still blocking. Such exposure is disclosed and drawn as
hazard sections even for safest.

Therefore:

- `remaining_report_count` counts blocking reports.
- `endpoint_report_count` counts unavoidable endpoint reports.
- `report_count` counts all encountered reports.
- `report_free` is true only when **no report at all** is encountered.

A valid safest route can have zero remaining reports but `report_free=false`
because of its endpoint exception. These fields are not interchangeable.

## 3.4 How CV reports become WSM feasibility scores

[`SegmentScoreService.php`](../backend/app/Services/SegmentScoreService.php)
recalculates `segmen_jalur.skor_kelayakan` using validated, unrepaired reports.
For category i:

```text
intensity_i = min(1, active_report_count_i / saturation)
score = clamp(round(100 × (1 − Σ(weight_i × intensity_i)), 2), 0, 100)
```

Saturation defaults to **3** through `CFG_REPORTS_SATURATION`, with a minimum
of one. Weights come from `kategori_masalah.bobot`; they are database data,
not a fixed weight table embedded in the router. The update runs within a
transaction and locks the segment row.

The CV queue job calls recalculation after automatic defect validation.
Validated reports affect routing in **two related ways**: they lower their
segment's WSM score, and their point geometry creates an active avoidance zone.
A confidence score itself is not used as a route-edge multiplier.

## 3.5 Local PostGIS graph and A*

[`PedestrianGraphRouter.php`](../backend/app/Services/PedestrianGraphRouter.php)
loads `segmen_id`, road name, WSM score, and GeoJSON geometry from `segmen_jalur`
within a trip-related bounding box. The minimum row threshold defaults to 50.
Safest loads with a 5 km margin; absence of an explicit detour cap does not
mean an unlimited geographic search.

Valid LineStrings become bidirectional graph edges. Endpoint coordinates
identify graph nodes. Origin/destination are projected onto the nearest edge,
subject to a default **100 m snap limit**, then virtual nodes split those
edges; a direct virtual connection handles two points on the same edge.

A* uses `f(n)=g(n)+h(n)`, with great-circle distance as the heuristic and
`SplPriorityQueue` for the frontier. Each pass optimizes its supplied cost on
the loaded graph. This does not establish a globally optimal route outside
that graph or an exhaustive solution to the separate balanced constraints.

### Fastest graph cost

```text
cost(edge) = length(edge)
```

This is shortest distance on the loaded graph. Duration is distance divided
by **1.25 m/s** (4.5 km/h), so shortest distance also minimizes the model's ETA.

### WSM and repair cost

For safest/balanced:

```text
wsm_factor = 1 + ((100 − score) / 100) × score_penalty
repair_factor = 1.35 for segments with active repairs; otherwise 1
base_cost = edge_length × wsm_factor × repair_factor
```

Default `score_penalty` is **3.0**. Thus a 100 m edge costs 100 at score 100,
190 at score 70, and 400 at score 0 before repair/report penalties. These are
weighted-cost units, not physical metres or a measured injury probability.

### Safest graph pass

Safest uses only the **clear** pass: edges touching blocking report zones are
excluded and A* minimizes WSM/repair cost among the remaining edges. If no
clear path exists, auto mode tries the next engine; forced graph mode reports
unavailability. The final geometry assessment enforces blocking-report refusal.

### Balanced graph passes

The router first computes the shortest baseline to determine its cap, then
tries passes in order, accepting the first candidate within the cap:

1. **Tolerated** (when tolerance > 0): `base_cost + 300` for each traversed
   edge touching any blocking zone; accept only if the completed route's
   unique blocking-report count is at most the configured tolerance.
2. **Clear:** block all report-zone edges and minimize base cost.
3. **Penalised:** multiply base cost by **10** inside report zones.
4. **WSM:** minimize base cost without zone penalties.
5. If no candidate pass fits, retain the shortest baseline.

The 300-unit graph penalty is applied **per zone-touching edge**, not once per
unique report. Segmenting a road differently can therefore change its total
penalty. The tolerated pass's report count is checked afterward. Later passes
and the shortest fallback can exceed tolerance; the response exposes this
using remaining-report counts, `safer_fallback`, and warnings. Tolerance is
not an absolute limit on every returned balanced route.

The code searches a minimum-cost route per pass and then checks its distance
cap; it does not enumerate every path satisfying both constraints.

## 3.6 Valhalla pedestrian routing

[`ValhallaPedestrianClient.php`](../backend/app/Services/ValhallaPedestrianClient.php)
supplies pedestrian candidates. Fastest begins with Valhalla's `shortest`
option; returned candidates are sorted by duration, then distance.

Safest/balanced additionally request pedestrian-friendly costing and report
exclusions. Defaults favor walkway/sidewalk (factor 0.8), alley (1.0), lighting
preference (0.5), and living streets (0.8).

The application iteratively excludes reports hit by proposed routes, adding
newly encountered reports after each reroute. Defaults: five exclusion rounds,
up to 50 polygons. Exclusions are circumscribed 12-sided polygons around the
25 m zone with a 2 m margin. Zones covering endpoints are not excluded from
the provider request; final assessment handles endpoint exposure.

Balanced can make up to six additional probes that reopen a small group of
reports within 60 m of one another, up to its tolerance, while keeping other
zones excluded. Candidates are then filtered by the balanced cap and ranked
using the application policy below.

## 3.7 OSRM foot candidates and external-candidate ranking

[`OsrmFootClient.php`](../backend/app/Services/OsrmFootClient.php) uses a verified
foot profile. The public default is the routed-foot endpoint; self-hosted
profiles require `OSRM_FOOT_PROFILE_VERIFIED=true`. Provider routes are checked
for endpoint proximity; the configured maximum offset defaults to **500 m**,
and offsets above 40 m generate an `ENDPOINT_SNAPPED` warning. Pin-to-network
access is not included in returned walking distance.

Fastest chooses minimum supplied **duration**, then distance; it does not just
take provider order. In contrast, local graph fastest optimizes length.

For safety-oriented candidates, define n = blocking-report count, T = report
tolerance (0 for safest, default 3 for balanced), P = 300. Rank lexicographically:

```text
1. max(0, n − T)
2. endpoint exposure (safest) or unavoidable endpoint exposure (balanced), rounded to 0.1 m
3. WSM route cost + min(n, T) × P
4. physical route distance
```

This is **not a fixed 50/50 safety-versus-speed average**. Balanced can choose
a route with a small number of reports when its weighted detour savings
outweigh the configured per-report cost. Reports above tolerance rank worse
first. Safest ultimately rejects any candidate still containing blocking reports.

[`RouteSegmentScorer.php`](../backend/app/Services/RouteSegmentScorer.php)
matches external-route edge midpoints to scored database segments within
**20 m**, using an in-memory spatial grid. Route cost is the sum of
`length × (1 + ((100 − score)/100) × 3)`; unmatched edges use neutral score
**70** for ranking. Unmatched scores remain null in displayed summaries, rather
than being presented as measured 70-point segments. The external scorer does
not apply the graph's separate 1.35 repair multiplier.

When direct OSRM candidates are blocked, the backend generates waypoint
detours using residential streets, nearby junctions, lateral offsets, and
wider cluster bypasses. It preserves waypoint ordering and rechecks all active
reports. Requests are bounded: normally 48 alternatives, at most 16 for the
public default, with up to six intermediate waypoints. Balanced prunes sets
that already exceed its cap; safest can consider wider detours. This is a
bounded candidate search, not an exhaustive guarantee that no route exists
anywhere when the search fails.

Provider geometry is cached (default fresh TTL 1,800 seconds). OSRM can use
stale cached geometry during outages; current reports are still assessed for
the request. `meta.provider_cache` identifies cache status. Interrupted
avoidance search is exposed through metadata/warnings; safest does not use
an interrupted search to justify returning a route through blocking reports.

## 3.8 API contract

The endpoint is registered under Sanctum authentication in
[`backend/routes/api.php`](../backend/routes/api.php). Controller:
[`RouteRecommendationController.php`](../backend/app/Http/Controllers/Api/RouteRecommendationController.php).

```http
POST /api/v1/routes/recommend
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "origin": {"lat": -7.2600, "lng": 112.7800},
  "destination": {"lat": -7.2700, "lng": 112.7900},
  "preference": "balanced"
}
```

Coordinates above are illustrative. Latitude must be between −90 and 90;
longitude between −180 and 180. Missing preference defaults to safest. Unknown
preferences return 422 `INVALID_ROUTE_PARAMETERS`; the API value is
`balanced`, not `balance`. Flattened origin/destination fields are also accepted.

Successful responses include:

| Field | Meaning |
|---|---|
| `route_id`, `engine`, `preference` | Request result identity and actual engine/mode |
| `origin`, `destination`, `snapped_*`, `snap_distances_m` | Requested pins and network positions |
| `distance_m`, `duration_s`, `duration_min` | Physical distance and walking ETA |
| `polyline` | Display coordinates in **[lat, lng]** order |
| `segments`, `steps` | Scored geometry and Indonesian turn-by-turn instructions |
| `summary.average_score`, `minimum_score`, `score_coverage` | Condition summary over known scored distance |
| `summary.report_count`, `remaining_report_count`, `endpoint_report_count` | Report exposure distinctions |
| `summary.hazard_distance_m`, `repair_distance_m` | Clipped hazard/repair lengths |
| `hazard_sections`, `colliding_points`, `warnings` | Exposure overlays and notices |
| `meta.algorithm`, `graph_pass`, `graph_cost`, `max_detour_distance_m` | Selection diagnostics |

Internal GeoJSON/provider coordinates are **[lng, lat]**, reversed for API
display polylines. Average score is distance-weighted over known scores only;
no score coverage gives null, not an implied perfect score. Segment labels:
score ≥70 `SANGAT_LAYAK`, ≥40 `PERLU_PERHATIAN`, below 40 `TIDAK_LAYAK`, or
`BELUM_DINILAI` when unknown.

Warnings include `REPORT_ON_ROUTE`, `SAFEST_AVAILABLE_WITH_REPORTS` for a
balanced fallback, `AVOIDANCE_SEARCH_INTERRUPTED`, `ACTIVE_REPAIR`,
`LOW_FEASIBILITY`, and `ENDPOINT_SNAPPED`. A null max-detour metadata value is
expected for safest and fastest. Graph repair penalties should be understood
from the algorithm; the response's generic `repair_penalty_factor` is currently
0 and does not expose the graph's actual configured repair multiplier.

## 3.9 Important backend defaults

Source: [`backend/config/services.php`](../backend/config/services.php).

| Environment setting | Default | Purpose |
|---|---|---|
| `CFG_ROUTE_ENGINE` | auto | Engine order/selection |
| `CFG_ROUTE_GRAPH_SNAP_M` | 100 | Graph endpoint snapping |
| `CFG_ROUTE_GRAPH_MIN_EDGES` | 50 | Minimum loaded graph rows |
| `CFG_ROUTE_SCORE_PENALTY` | 3.0 | WSM condition penalty |
| `CFG_ROUTE_UNSCORED_SCORE` | 70 | External unmatched-cost score |
| `CFG_ROUTE_REPAIR_PENALTY` | 1.35 | Graph repair multiplier |
| `CFG_ROUTE_REPORT_ZONE_PENALTY` | 10 | Graph balanced fallback multiplier |
| `CFG_ROUTE_REPORT_TOLERANCE` | 3 | Balanced tolerated report count |
| `CFG_ROUTE_REPORT_PENALTY_M` | 300 | Balanced report cost |
| `CFG_ROUTE_MAX_DETOUR_RATIO` | 1.5 | Balanced distance cap factor |
| `CFG_ROUTE_MAX_DETOUR_EXTRA_M` | 500 | Balanced extra-distance allowance |
| `VALHALLA_URL` | public Valhalla endpoint | Pedestrian provider |
| `OSRM_BASE_URL` | public routed-foot endpoint | OSRM foot provider |

Avoidance radius 25 m, external match radius 20 m, and public request budgets
are source configuration/constants rather than all having environment overrides.
The pedestrian-network import command is implemented in
[`ImportPedestrianNetwork.php`](../backend/app/Console/Commands/ImportPedestrianNetwork.php).

## 3.10 Evaluation evidence and its limits

Existing source tests cover mode differences, WSM preference, balanced caps,
report-state filtering, endpoint exceptions/re-entry, provider failures,
caching, detour search, Valhalla exclusions, and navigation:

- [`RouteRecommendationTest.php`](../backend/tests/Feature/Route/RouteRecommendationTest.php)
- [`PedestrianRoutingTest.php`](../backend/tests/Feature/PedestrianRoutingTest.php)
- [`ValhallaRoutingTest.php`](../backend/tests/Feature/Route/ValhallaRoutingTest.php)

The saved **25 November 2026**
[routing evaluation](../docs/evaluation_results/ROUTING_RESULTS.md) used an
isolated PostGIS database and fixed OSRM responses. It recorded 4 geometry
tests/13 assertions, 7 route tests/75 assertions, and 4 navigation tests/71
assertions passing. Its synthetic benchmark recorded **52/54 outcomes** across
27 paired fixtures (nine scenarios × radii 10/25/40 m). The two failed outcomes
involved endpoint tie-breaking; both failures remain in the saved report.

That historical run predates the current broader engine/mode behavior. It is
not a fresh pass/fail certification of current balanced routing, A*, or
Valhalla. No new routing tests were executed for this documentation revision.
The old benchmark's two-mode results must not be presented as a three-mode
field comparison. Wall-clock test runtime is not route API latency or walking
time, and supplied fixture durations are not observed pedestrian travel times.

A current empirical comparison should use identical origin/destination pairs
and report snapshots for all three modes, recording engine, distance/ETA,
known-score coverage, WSM cost, encountered/remaining reports, clipped exposure,
fallback/refusal rates, and API latency. Live walkability/accessibility and
travel-time validation require separate field observations.
