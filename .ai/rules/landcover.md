---
paths:
  - 'public/data/landcover/**'
---

# Landcover

## 2020 land-cover points come from the TIFF raster
points_2020.json and landcover_points_2020.bin are built with node scripts/build_landcover_points.cjs 2020 --raster-only from landcover_2020.png. They must expose all 20 DN classes with mismatches: 0, not the old 13-class GeoJSON output.
