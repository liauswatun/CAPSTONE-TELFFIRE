---
paths:
  - 'public/data_perubahan/**'
---

# Data Perubahan

## GeoJSON sources are truncated; GeoTIFFs are complete
Committed data_sumatera_2021..2024.geojson are clipped to northern Sumatra (minLat 2.268/2.974/4.093/4.815). HEAD 2020 GeoJSON is cut mid-line at 1,222,060,889 bytes; the local 5,517,028,185-byte working file is full. Rebuild land cover from sumatera_YYYY_web.tif (52,520x45,439, full Sumatra, 20 DN), not from GeoJSON.

## Git misses the >4GiB 2020 GeoJSON size change
Git stores file size as 32-bit: 5,517,028,185 mod 2^32 = 1,222,060,889, which equals the old blob size. git status can therefore report the full 2020 GeoJSON as clean even though the content hash differs. Verify with git hash-object --no-filters. Never git add the 5.5GB file without LFS or external storage.

## Commit derived land-cover assets only
The raw 2020 GeoJSON and the source GeoTIFFs are local-only and ignored; do not re-add them to Git. Commit `public/data/landcover/**` (points, binaries, manifests, PNGs), not the 5.5GB source. Local 2020 GeoJSON identity: 5,517,028,185 bytes, SHA-256 `9cf2f0e1d3d2063b5885e18d8fa1df99d4102cd06a9752a0a2ac65407a740aaf`. Tests must not require raw sources; they assert the derived assets instead.
