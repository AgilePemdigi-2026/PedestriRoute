# PedestriRoute — OSRM pedestrian backend

Laravel defaults to the public **foot** service:

```dotenv
OSRM_BASE_URL=https://routing.openstreetmap.de/routed-foot/route/v1/foot
OSRM_FOOT_PROFILE_VERIFIED=false
```

The URL's `foot` label does **not** select an OSRM routing profile. The dataset
must be extracted with a foot profile. Do not use the public driving demo at
`router.project-osrm.org`, even with `/foot` in the URL.

## Self-hosted foot dataset

Download a suitable Surabaya/Jawa Timur `.osm.pbf` extract into this directory as
`surabaya.osm.pbf`. From this directory, build with OSRM's bundled **foot.lua**:

```bash
docker run --rm -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-extract -p /opt/foot.lua /data/surabaya.osm.pbf
docker run --rm -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-partition /data/surabaya.osrm
docker run --rm -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-customize /data/surabaya.osrm
docker run --rm -p 5000:5000 -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-routed --algorithm mld /data/surabaya.osrm
```

Only after building that dataset, configure Laravel with the address reachable
from its runtime:

```dotenv
OSRM_BASE_URL=http://localhost:5000/route/v1/foot
OSRM_FOOT_PROFILE_VERIFIED=true
```

For Laravel inside Docker, use the OSRM container/service hostname instead of
`localhost`. Clear cached configuration after changing environment settings:
`php artisan config:clear`.

All initial, alternative, and waypoint requests use this same foot backend, with
a maximum 500 m endpoint snap radius (40 m for detour waypoints). Place pins
inside parks/hospitals can therefore resolve to a connected pedestrian entrance.
Responses disclose actual snapped endpoints and offsets; the off-network access
from the requested pin is not included in the walking geometry. There is no driving or fabricated straight-line
fallback. Foot-accessible streets can be used; dedicated sidewalks depend on
OSM coverage and pedestrian access tags.

## Active reports and map warnings

Laravel checks the full route geometry against fixed **25 m** zones around
validated reports that are unresolved or being repaired. Completed repairs are
excluded. Reports outside the initial route corridor are checked on detours too.

**Teraman** prioritizes routes outside every active report zone. There is no
detour-distance cap. It searches OSRM alternatives, nearby report-relative
waypoints, and wide parallel corridors up to 3.5 km to either side, rechecking
the complete geometry of every result. Standard OSRM cannot exclude arbitrary
coordinates. If the finite search finds no clear candidate, it returns the foot
route with the fewest remaining non-endpoint reports, then least affected walking
distance, then lowest walking time. `SAFEST_AVAILABLE_WITH_REPORTS` and visible
dashed hazards disclose the remaining exposure. A detour-provider outage also
retains the best already-validated foot route, with an interrupted-search warning.
It does not reject the entire trip merely because a fully clear alternative
was not found. Report zones
containing the walking start/destination allow only a contiguous departure/final
approach. These unavoidable sections remain dashed and explicitly labeled;
leaving and re-entering a report zone mid-trip is rejected. Among acceptable
alternatives the smallest unavoidable exposure is preferred, then walking time.

Local searches also inspect the last two turns before an encountered report.
They try continuing along the approach street for 80, 180, or 300 m, plus ordered
"continue, then turn" anchors, before using wide detours. OSRM still validates
all walking connectivity and access restrictions: a street drawn on the base
map may have a mapped `access=no` gate and cannot be assumed traversable.

Before offset-based candidates, the search discovers residential streets within
the trip corridor through Photon and loads their actual OSM way/node geometry.
Anchors lie inside those street geometries rather than at the main-road
intersections at their ends. Spatially diverse lookups prevent spending the
whole search on adjacent blocks of one housing estate. If the map-data API is
unavailable, OSRM nearest resolves the reference onto the same named street in
the foot graph, instead of snapping it onto a different main road.
Up to four spatially distinct street-anchor sequences are tried first. Private
or foot-prohibited ways are excluded unless pedestrian access is explicitly
allowed. Each resulting trip still comes from OSRM foot and is checked against
every active report. Street lookup is cached and time-bounded; lookup failure
falls back to the remaining candidate search rather than blocking the route.

A failed provider connection returns `PEDESTRIAN_ENGINE_UNAVAILABLE`, separately
from a provider response indicating that no connected foot route was found.

Public foot requests are serialized across app workers and spaced at least
1.1 seconds apart. Transient failures get one retry; longer `Retry-After` delays
are respected. Successful geometry is cached for 30 minutes, with a 24-hour
backup usable during an outage. Active reports are loaded and checked again on
every recommendation, including cached geometry. Public detour searches use at
most 16 requests; verified self-hosted engines retain the 48-request budget and
parallel requests. The application never substitutes a driving route.

**Tercepat** selects the lowest provider walking duration. Only encountered
reports appear on its map; orange dashed sections show the actual intersection
of the route with the report zones. Overlapping zones are not double-counted.
