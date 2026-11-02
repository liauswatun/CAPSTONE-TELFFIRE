---
paths:
  - scripts/build_landcover.cjs
  - scripts/build_landcover_points.cjs
---

# Scripts

## Palette PNG needs a dedicated transparent entry
For the land cover PNG (colour type 3), PLTE must contain count(DN)+1 entries and tRNS must match that count, with index 0 fully transparent and DN colours starting at index 1. Writing only count(DN) PLTE entries while mapping DNs to 1..N produces a malformed PNG (GD: "tRNS: invalid", "palette index exceeding num_palette") and silently shifts every colour. LandCoverDataTest asserts the PLTE/tRNS contract.

## Land-cover points binary contract
Points are one per 4-connected same-DN raster component. Binary record is 13 bytes: int32le lat, int32le lng (degrees * 1e7), uint8 DN, float32le areaHa. points_YYYY.json must keep mismatches=0; snap source bbox centres that round across a cell boundary to the component cell centre. Regenerate with this script and verify all records map back to the same raster DN.

## Raster-only points (2020–2024)
`node scripts/build_landcover_points.cjs <year> --raster-only` builds one point per 4-connected component of the 275 m web raster. 2020 reads `index.json` + `landcover_2020.png` (TIFF-derived); 2021–2024 read `raster_YYYY.json` + `landcover_YYYY.png`. It writes `points_YYYY.json` + `landcover_points_YYYY.bin` with all 20 DN classes and must keep mismatches=0.

## 2021 full-Sumatra variant
The app uses `points_2021_full.json` + `landcover_points_2021_full.bin` with `raster_2021.json` (the full TIFF raster). Build it with `node scripts/build_landcover_points.cjs 2021 --raster-only --variant=full`; the `--raster-only` path reads `raster_2021.json`/`landcover_2021.png` and writes the `_full` point assets. There is no `raster_2021_full.json` or `landcover_2021_full.png`. The GeoJSONL variant path in `build_landcover.cjs` is legacy.

## Legacy layer outputs
2020 keeps the legacy `index.json` path; other default year builds write `raster_YYYY.json`, `points_YYYY.json`, and `landcover_points_YYYY.bin`. The 2021 full variant uses the separately named point outputs above and does not replace the legacy 2021 files.
