---
paths:
  - resources/views/platform.blade.php
---

# Views

## Land cover spans 2020–2024 with raw DN codes
2020–2024 land cover are separate from the fire layer. The source's DN meanings are undocumented, so the UI must show raw "DN x" labels and must not invent class names. Before/after comparison and change area remain unavailable; state that rather than fabricate it. The 2021 full variant is points-only (`points_2021_full.json` + `landcover_points_2021_full.bin`) and pairs with `raster_2021.json`; there is no `raster_2021_full.json` or `landcover_2021_full.png`.

## Land-cover points stay separate from fire layer
The land-cover dots are a separate canvas layer in landcoverPointsPane (zIndex 360), drawn as coloured circles, with a click popup. Do not alter the fire cell layer, fire hover tooltip, or fire detail panel. The point popup must label before/after and change area as unavailable because the source only has fid and DN.
