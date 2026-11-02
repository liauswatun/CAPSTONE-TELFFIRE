<?php

/*
 * Static data contracts for the land cover layers in public/data/landcover/.
 * The 2020–2024 rasters are built by scripts/build_landcover_from_geotiff.cjs
 * from the clipped MapBiomas GeoTIFFs, and the points are built by
 * scripts/build_landcover_points.cjs from the 275 m web raster.
 *
 * The source GeoTIFFs and public/data/fires.json are read-only inputs.
 */
function landcoverIndex(int $year = 2020, string $variant = ''): array
{
    $suffix = $variant === '' ? '' : "_{$variant}";
    $path = public_path($year === 2020 ? 'data/landcover/index.json' : "data/landcover/raster_{$year}{$suffix}.json");

    expect($path)->toBeFile();

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

function landcoverPointsIndex(int $year = 2020, string $variant = ''): array
{
    $suffix = $variant === '' ? '' : "_{$variant}";
    $path = public_path("data/landcover/points_{$year}{$suffix}.json");

    expect($path)->toBeFile();

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

test('2020 land cover manifest describes a georeferenced raster with source DN codes', function () {
    $index = landcoverIndex();

    expect($index['type'])->toBe('landcover-raster');
    expect($index['year'])->toBe(2020);
    expect($index['source'])->toBe('public/data_perubahan/sumatera_2020_web.tif');
    expect($index['step'])->toBe(0.0025);
    expect($index['bbox'])->toBeArray()->toHaveCount(4);
    expect($index['width'])->toBeInt()->toBeGreaterThan(0);
    expect($index['height'])->toBeInt()->toBeGreaterThan(0);
    expect($index['image'])->toBe('/data/landcover/landcover_2020.png');
    expect($index['occupiedCells'])->toBeInt()->toBeGreaterThan(0);
    expect(array_sum($index['cellsByDn']))->toBe($index['occupiedCells']);

    expect($index['dnValues'])->toBeArray()->toHaveCount(20);
    expect(array_keys($index['palette']))->toHaveCount(count($index['dnValues']));

    foreach ($index['dnValues'] as $dn) {
        expect(is_int($dn))->toBeTrue();
        expect($index['palette'])->toHaveKey((string) $dn);
    }
});

test('raster bbox and step agree with the PNG dimensions', function () {
    $index = landcoverIndex();

    [$latMin, $lngMin, $latMax, $lngMax] = $index['bbox'];

    expect(abs((($lngMax - $lngMin) / $index['step']) - $index['width']))->toBeLessThan(0.001);
    expect(abs((($latMax - $latMin) / $index['step']) - $index['height']))->toBeLessThan(0.001);

    expect($latMin)->toBeLessThan($latMax);
    expect($lngMin)->toBeLessThan($lngMax);

    // The source data covers Sumatra; the grid must not be accidentally tiny.
    expect($lngMax - $lngMin)->toBeGreaterThan(14.0);
    expect($latMax - $latMin)->toBeGreaterThan(12.0);
});

test('land cover PNG exists and its header matches the manifest', function () {
    $index = landcoverIndex();
    $path = public_path('data/landcover/landcover_2020.png');

    expect($path)->toBeFile();
    expect(filesize($path))->toBeGreaterThan(100_000);

    $info = getimagesize($path);

    expect($info)->not->toBeFalse();
    expect($info[0])->toBe($index['width']);
    expect($info[1])->toBe($index['height']);
    expect($info[2])->toBe(IMAGETYPE_PNG);

    // PNG IHDR: bit depth at byte 24, colour type at byte 25.
    $header = (string) file_get_contents($path, false, null, 0, 26);
    expect(ord($header[24]))->toBe(8);  // 8-bit
    expect(ord($header[25]))->toBe(3);  // indexed palette

    $chunks = [];
    $offset = 8;
    $contents = (string) file_get_contents($path);

    while ($offset < strlen($contents)) {
        $length = unpack('N', substr($contents, $offset, 4))[1];
        $type = substr($contents, $offset + 4, 4);
        $chunks[$type] = substr($contents, $offset + 8, $length);
        $offset += 12 + $length;
    }

    expect($chunks)->toHaveKeys(['IHDR', 'PLTE', 'tRNS', 'IDAT', 'IEND']);

    // Index 0 is reserved for transparency; every DN must have its own entry.
    $paletteEntries = intdiv(strlen($chunks['PLTE']), 3);
    expect($paletteEntries)->toBe(count($index['palette']) + 1);
    expect(strlen($chunks['tRNS']))->toBe($paletteEntries);
    expect(ord($chunks['tRNS'][0]))->toBe(0);

    foreach ($index['dnValues'] as $position => $dn) {
        $hex = strtolower(ltrim($index['palette'][(string) $dn], '#'));
        expect(bin2hex(substr($chunks['PLTE'], ($position + 1) * 3, 3)))->toBe($hex);
        expect(ord($chunks['tRNS'][$position + 1]))->toBe(255);
    }
});

test('legacy 2021 land cover raster remains available', function () {
    $index = landcoverIndex(2021);

    expect($index['type'])->toBe('landcover-raster');
    expect($index['year'])->toBe(2021);
    expect($index['source'])->toBe('public/data_perubahan/sumatera_2021_web.tif');
    expect($index['step'])->toBe(0.0025);
    expect($index['bbox'])->toBeArray()->toHaveCount(4);
    expect($index['width'])->toBe(5661);
    expect($index['height'])->toBe(4898);
    expect($index['image'])->toBe('/data/landcover/landcover_2021.png');
    // The 2021 raster uses the full-Sumatra GeoTIFF, not the truncated GeoJSON.
    expect($index['bbox'][0])->toBeLessThan(-5.0);
    expect($index['bbox'][1])->toBeLessThan(96.0);
    expect($index['bbox'][2])->toBeGreaterThan(5.0);
    expect($index['bbox'][3])->toBeGreaterThan(105.0);

    expect($index['dnValues'])->toBeArray()->toHaveCount(20);
    expect(array_keys($index['palette']))->toHaveCount(count($index['dnValues']));

    foreach ($index['dnValues'] as $dn) {
        expect(is_int($dn))->toBeTrue();
        expect($index['palette'])->toHaveKey((string) $dn);
    }
});

test('full 2021 land cover points cover Sumatra and use the full raster', function () {
    $pointsPath = public_path('data/landcover/points_2021_full.json');
    $binaryPath = public_path('data/landcover/landcover_points_2021_full.bin');

    expect($pointsPath)->toBeFile();
    expect($binaryPath)->toBeFile();

    $raster = landcoverIndex(2021);
    $points = landcoverPointsIndex(2021, 'full');

    expect($points['source'])->toBe('public/data_perubahan/sumatera_2021_web.tif');
    expect($points['raster'])->toBe('/data/landcover/landcover_2021.png');
    expect($points['file'])->toBe('/data/landcover/landcover_points_2021_full.bin');
    expect($points['mismatches'])->toBe(0);
    expect($points['dnValues'])->toHaveCount(20);
    expect($points['bbox'])->toBe($raster['bbox']);
    expect(filesize($binaryPath))->toBe($points['count'] * $points['recordSize']);

    $records = file_get_contents($binaryPath);
    expect($records)->not->toBeFalse();
    $minLat = INF;
    $maxLat = -INF;
    $minLng = INF;
    $maxLng = -INF;

    for ($offset = 0; $offset < strlen($records); $offset += $points['recordSize']) {
        $lat = unpack('V', substr($records, $offset, 4))[1];
        $lng = unpack('V', substr($records, $offset + 4, 4))[1];
        $lat = ($lat >= 0x80000000 ? $lat - 0x100000000 : $lat) / $points['coordinateScale'];
        $lng = ($lng >= 0x80000000 ? $lng - 0x100000000 : $lng) / $points['coordinateScale'];
        $minLat = min($minLat, $lat);
        $maxLat = max($maxLat, $lat);
        $minLng = min($minLng, $lng);
        $maxLng = max($maxLng, $lng);
    }

    expect($minLat)->toBeLessThan(-5.0);
    expect($maxLat)->toBeGreaterThan(5.0);
    expect($minLng)->toBeLessThan(96.0);
    expect($maxLng)->toBeGreaterThan(105.0);
});

test('2021 land cover PNG exists and its header matches the manifest', function () {
    $index = landcoverIndex(2021);
    $path = public_path('data/landcover/landcover_2021.png');

    expect($path)->toBeFile();
    expect(filesize($path))->toBeGreaterThan(100_000);

    $info = getimagesize($path);

    expect($info)->not->toBeFalse();
    expect($info[0])->toBe($index['width']);
    expect($info[1])->toBe($index['height']);
    expect($info[2])->toBe(IMAGETYPE_PNG);

    $header = (string) file_get_contents($path, false, null, 0, 26);
    expect(ord($header[24]))->toBe(8);
    expect(ord($header[25]))->toBe(3);

    $chunks = [];
    $offset = 8;
    $contents = (string) file_get_contents($path);

    while ($offset < strlen($contents)) {
        $length = unpack('N', substr($contents, $offset, 4))[1];
        $type = substr($contents, $offset + 4, 4);
        $chunks[$type] = substr($contents, $offset + 8, $length);
        $offset += 12 + $length;
    }

    expect($chunks)->toHaveKeys(['IHDR', 'PLTE', 'tRNS', 'IDAT', 'IEND']);

    $paletteEntries = intdiv(strlen($chunks['PLTE']), 3);
    expect($paletteEntries)->toBe(count($index['palette']) + 1);
    expect(strlen($chunks['tRNS']))->toBe($paletteEntries);
    expect(ord($chunks['tRNS'][0]))->toBe(0);

    foreach ($index['dnValues'] as $position => $dn) {
        $hex = strtolower(ltrim($index['palette'][(string) $dn], '#'));
        expect(bin2hex(substr($chunks['PLTE'], ($position + 1) * 3, 3)))->toBe($hex);
        expect(ord($chunks['tRNS'][$position + 1]))->toBe(255);
    }
});

test('2020 land cover points manifest describes component points from the source', function () {
    $index = landcoverPointsIndex();

    expect($index['type'])->toBe('landcover-points');
    expect($index['year'])->toBe(2020);
    expect($index['source'])->toBe('public/data_perubahan/sumatera_2020_web.tif');
    expect($index['raster'])->toBe('/data/landcover/landcover_2020.png');
    expect($index['file'])->toBe('/data/landcover/landcover_points_2020.bin');
    expect($index['count'])->toBe(339701);
    expect($index['recordSize'])->toBe(13);
    expect($index['coordinateScale'])->toBe(10000000);
    expect($index['mismatches'])->toBe(0);
    expect($index['sourcePointCount'])->toBe(0);
    expect($index['fallbackPointCount'])->toBe($index['count']);
    expect($index['snappedPointCount'])->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($index['layout'])->toBe([
        'lat' => 'int32le',
        'lng' => 'int32le',
        'dn' => 'uint8',
        'areaHa' => 'float32le',
    ]);

    expect($index['dnValues'])->toBeArray()->toHaveCount(20);
    expect(array_keys($index['palette']))->toHaveCount(count($index['dnValues']));

    foreach ($index['dnValues'] as $dn) {
        expect(is_int($dn))->toBeTrue();
        expect($index['palette'])->toHaveKey((string) $dn);
    }
});

test('land cover points binary size and sampled records match the manifest', function () {
    $index = landcoverPointsIndex();
    $path = public_path('data/landcover/landcover_points_2020.bin');

    expect($path)->toBeFile();
    expect(filesize($path))->toBe($index['count'] * $index['recordSize']);

    $handle = fopen($path, 'rb');
    expect(is_resource($handle))->toBeTrue();

    $scale = $index['coordinateScale'];

    foreach ([0, $index['count'] - 1] as $recordIndex) {
        fseek($handle, $recordIndex * $index['recordSize']);
        $record = fread($handle, $index['recordSize']);

        expect(strlen($record))->toBe($index['recordSize']);

        $latRaw = unpack('V', substr($record, 0, 4))[1];
        $lngRaw = unpack('V', substr($record, 4, 4))[1];
        $lat = ($latRaw >= 0x80000000 ? $latRaw - 0x100000000 : $latRaw) / $scale;
        $lng = ($lngRaw >= 0x80000000 ? $lngRaw - 0x100000000 : $lngRaw) / $scale;
        $dn = ord($record[8]);
        $areaHa = unpack('g', substr($record, 9, 4))[1];

        expect($lat)->toBeGreaterThanOrEqual($index['bbox'][0])->toBeLessThanOrEqual($index['bbox'][2]);
        expect($lng)->toBeGreaterThanOrEqual($index['bbox'][1])->toBeLessThanOrEqual($index['bbox'][3]);
        expect(in_array($dn, $index['dnValues'], true))->toBeTrue();
        expect($areaHa)->toBeGreaterThan(0);
    }

    fclose($handle);
});

test('2021 land cover points manifest describes component points from the source', function () {
    $index = landcoverPointsIndex(2021);

    expect($index['type'])->toBe('landcover-points');
    expect($index['year'])->toBe(2021);
    expect($index['source'])->toBe('public/data_perubahan/data_sumatera_2021.geojson');
    expect($index['raster'])->toBe('/data/landcover/landcover_2021.png');
    expect($index['file'])->toBe('/data/landcover/landcover_points_2021.bin');
    expect($index['recordSize'])->toBe(13);
    expect($index['coordinateScale'])->toBe(10000000);
    expect($index['mismatches'])->toBe(0);
    expect($index['sourcePointCount'] + $index['fallbackPointCount'])->toBe($index['count']);
    expect($index['snappedPointCount'])->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($index['layout'])->toBe([
        'lat' => 'int32le',
        'lng' => 'int32le',
        'dn' => 'uint8',
        'areaHa' => 'float32le',
    ]);

    expect($index['dnValues'])->toBeArray()->not->toBeEmpty();
    expect(array_keys($index['palette']))->toHaveCount(count($index['dnValues']));

    foreach ($index['dnValues'] as $dn) {
        expect(is_int($dn))->toBeTrue();
        expect($index['palette'])->toHaveKey((string) $dn);
    }
});

test('2021 land cover points binary size and sampled records match the manifest', function () {
    $index = landcoverPointsIndex(2021);
    $path = public_path('data/landcover/landcover_points_2021.bin');

    expect($path)->toBeFile();
    expect(filesize($path))->toBe($index['count'] * $index['recordSize']);

    $handle = fopen($path, 'rb');
    expect(is_resource($handle))->toBeTrue();

    $scale = $index['coordinateScale'];
    $bbox = $index['bbox'];
    $sampleIndexes = [0, intdiv($index['count'], 2), $index['count'] - 1];

    foreach ($sampleIndexes as $recordIndex) {
        fseek($handle, $recordIndex * $index['recordSize']);
        $record = fread($handle, $index['recordSize']);

        expect(strlen($record))->toBe($index['recordSize']);

        $latRaw = unpack('V', substr($record, 0, 4))[1];
        $lngRaw = unpack('V', substr($record, 4, 4))[1];
        $lat = ($latRaw >= 0x80000000 ? $latRaw - 0x100000000 : $latRaw) / $scale;
        $lng = ($lngRaw >= 0x80000000 ? $lngRaw - 0x100000000 : $lngRaw) / $scale;
        $dn = ord($record[8]);
        $areaHa = unpack('g', substr($record, 9, 4))[1];

        expect($lat)->toBeGreaterThanOrEqual($bbox[0])->toBeLessThanOrEqual($bbox[2]);
        expect($lng)->toBeGreaterThanOrEqual($bbox[1])->toBeLessThanOrEqual($bbox[3]);
        expect(in_array($dn, $index['dnValues'], true))->toBeTrue();
        expect($areaHa)->toBeGreaterThan(0);
    }

    fclose($handle);
});

test('fires.json is a present and valid runtime input', function () {
    $firesPath = public_path('data/fires.json');
    expect($firesPath)->toBeFile();

    $fires = json_decode((string) file_get_contents($firesPath), true);

    expect($fires['type'])->toBe('fires');
    expect(count($fires['pts']))->toBe(91257);

    foreach (array_slice($fires['pts'], 0, 200) as $pt) {
        expect(count($pt))->toBe(5);
        expect($pt[2])->toBeGreaterThan(0);
        expect($pt[3])->toBeBetween(2020, 2024);
        expect($pt[4])->toBeString();
        expect(mb_strlen($pt[4]))->toBeLessThanOrEqual(7);
    }
});

test('map page renders the 2020–2024 land cover UI hooks', function () {
    $response = $this->get('/map');

    $response->assertOk();
    $response->assertSee('luc-section', false);
    $response->assertSeeInOrder(['id="map-legend"', 'id="luc-filter-widget"', 'id="luc-section"', 'id="map-style-dd"'], false);
    $response->assertSee('id="map-legend" class="w-48 max-w-full"', false);
    $response->assertSee('id="luc-filter-widget" class="w-48 max-w-full"', false);
    $response->assertSee('id="luc-filter-trigger" type="button" aria-expanded="false" aria-controls="luc-filter-panel"', false);
    $response->assertSee('id="luc-filter-panel" class="hidden mt-1 max-h-[55vh] overflow-y-auto', false);
    $pageHtml = (string) $response->getContent();
    preg_match('/<aside\b[\s\S]*?<\/aside>/', $pageHtml, $asideMatch);
    expect($asideMatch)->not->toBeEmpty();
    expect($asideMatch[0])->not->toContain('id="luc-section"');
    expect(substr_count($pageHtml, 'id="luc-section"'))->toBe(1);
    $response->assertSee('data-luc-points-loaded="false"', false);
    $response->assertSee('luc-points-toggle', false);
    $response->assertSee('id="luc-points-toggle" type="checkbox" autocomplete="off"', false);
    $response->assertDontSee('id="luc-points-toggle" type="checkbox" checked', false);
    $response->assertSee('luc-points-note', false);
    $response->assertDontSee('<p id="luc-points-note"', false);
    $response->assertDontSee('<p id="luc-note"', false);
    $response->assertSee('luc-points-label', false);
    $response->assertDontSee('luc-raster-label', false);
    $response->assertDontSee('luc-toggle', false);
    $response->assertSee('luc-year-2021', false);
    $response->assertSee('data-luc-year="2021"', false);
    $response->assertSee('data-luc-year="2024"', false);
    $response->assertSee('LUC_YEARS = [2020, 2021, 2022, 2023, 2024]', false);
    $response->assertSee("2020: '/data/landcover/index.json'", false);
    $response->assertSee("2021: '/data/landcover/raster_2021.json'", false);
    $response->assertSee("2024: '/data/landcover/raster_2024.json'", false);
    $response->assertSee('function selectedLucYear()', false);
    $response->assertSee('return LUC_YEARS.includes(year) ? year : LUC_YEARS[0];', false);
    $response->assertSee('Number(btn.dataset.lucYear) === selectedLucYear()', false);
    $response->assertSee('const yearChanged = next !== Number(state.year);', false);
    $response->assertSee('setYear(next);', false);
    $response->assertSee('const fireYear = Number(state.year);', false);
    $response->assertSee('const s = lucState(fireYear);', false);
    $response->assertSee('if (s.metaPromise) return s.metaPromise;', false);
    $response->assertSee('fetchLandCoverIndex(fireYear);', false);
    $response->assertSee('const url = LUC_RASTER_MANIFEST[year];', false);
    $response->assertDontSee('fetchLandCoverIndex(2020)', false);
    $response->assertSee("hit.addEventListener('click', () => setYear(y));", false);
    $response->assertSee("col.addEventListener('click', () => setYear(y));", false);
    $response->assertSee('setYear(xToYear(e.clientX - rect.left));', false);
    $response->assertSee('function buildMatchedLucData()', false);
    $response->assertSee('if (!state.dn.has(tierOf(pt[2]))) return false;', false);
    $response->assertSee('if (state.lucClass !== null)', false);
    $response->assertSee('if (lucPointClass(sample[2]) !== state.lucClass) return false;', false);
    $response->assertSee('const data = (state.year !== null) ? baseData.filter((pt) => pt[3] === state.year) : baseData;', false);
    $response->assertSee('buildGrid(data);', false);
    $response->assertSee('renderStats(data, baseData);', false);
    $response->assertSee('function updateLucPointsLayer()', false);
    $response->assertSee('lucPointsToggleEl.checked = false;', false);
    $response->assertSee('updateLucPointsLayer();', false);
    $response->assertSee('if (!lucPointsToggleChecked() || !LUC_YEARS.includes(fireYear))', false);
    $response->assertSee("lucPointsToggleEl.addEventListener('change', updateLucPointsLayer);", false);
    $response->assertDontSee('fetchLandCoverPoints(2020)', false);
    $response->assertSee('const LUC_MARKER_PALETTE = {', false);
    $response->assertSee("lucPointsPane.style.zIndex = '420'", false);
    $response->assertSee('Math.max(3, Math.min(6, 3 + (z - 6) * 0.45))', false);
    $response->assertSee('Math.max(5, Math.min(8, lucPointRadius(z) + 2))', false);
    $response->assertSee("ctx.strokeStyle = '#FFFFFF';", false);
    $response->assertSee('ctx.lineWidth = 5;', false);
    $response->assertSee('ctx.lineWidth = 3.25;', false);
    $response->assertSee('const color = lucMarkerColor(data.dn[index], year);', false);
    $response->assertSee('swatch.style.background = lucMarkerColor(dn, s.year);', false);
    $response->assertSee('showLucPointPopup(lucIndex)', false);
    $response->assertSee('fireDistance > cellSize() / 2', false);
    $response->assertSee('function lucRingSprite(color, radius)', false);
    $response->assertSee('const dn = sample ? sample[2] : null;', false);
    $response->assertSee('const t = tierOf(pt[2]);', false);
    $response->assertSee('this._drawnPoints.push({ index, x, y })', false);
    $response->assertSee('return lucMatchedLayer.findPointAt(latlng);', false);
    $response->assertDontSee('luc-detail', false);
    $response->assertDontSee('openLucDetail', false);
    $response->assertDontSee('updateLucLayer', false);
    $response->assertDontSee('lucToggleChecked', false);
    $response->assertDontSee('landcoverPane', false);
    $response->assertSee('luc-legend', false);
    $response->assertSee('grid grid-cols-1 gap-1.5', false);
    $response->assertSee("item.className = 'flex w-full min-w-0 items-center gap-2 rounded border px-2 py-1.5 text-left text-[10px] leading-snug", false);
    $response->assertSee("countLabel.className = 'ml-auto shrink-0 whitespace-nowrap text-right tabular-nums", false);
    $response->assertSee('const LUC_CLASS_NAMES = {', false);
    $response->assertSee('const LUC_CLASS_ORDER = [3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76];', false);
    $response->assertSee("const state = { level: 'prov', dn: new Set([1, 2, 3]), lucClass: null, year: null, mapStyle: 'light' };", false);
    $response->assertSee('const LUC_DN_TO_CLASS = {', false);
    $response->assertSee('3: 3, 5: 5, 9: 9, 11: 21, 12: 3, 13: 13, 21: 21,', false);
    $response->assertSee('40: 40, 46: 3, 48: 21, 49: 21, 76: 76, 80: 21, 92: 3,', false);
    $verifiedClasses = [
        3 => ['Formasi Hutan', 'Forest Formation'],
        5 => ['Mangrove', 'Mangrove'],
        9 => ['Kebun Kayu', 'Pulpwood Plantation'],
        13 => ['Tumbuhan Non-Hutan Lainnya', 'Other Natural Vegetation'],
        21 => ['Pertanian Lainnya', 'Other Agriculture'],
        24 => ['Permukiman', 'Urban Area'],
        25 => ['Non-Vegetasi Lainnya', 'Other Non-Vegetation'],
        30 => ['Lubang Tambang', 'Mining Pit'],
        31 => ['Tambak', 'Aquaculture'],
        33 => ['Sungai, Danau, Laut', 'River, Lake, Ocean'],
        35 => ['Sawit', 'Oil Palm'],
        40 => ['Sawah', 'Rice Paddy'],
        76 => ['Hutan Rawa Gambut', 'Peat Swamp Forest'],
    ];
    foreach ($verifiedClasses as $dn => [$indonesianName, $englishName]) {
        $response->assertSee($dn.': { id: \''.$indonesianName.'\', en: \''.$englishName.'\' }', false);
    }
    $response->assertSee('function lucClassLabel(dn)', false);
    $response->assertSee('const className = LUC_CLASS_NAMES[lucPointClass(dn)];', false);
    $response->assertSee('function lucCategoryCounts(rawCounts)', false);
    $response->assertSee('const counts = lucCategoryCounts(rawCounts);', false);
    $response->assertSee('LUC_CLASS_ORDER.forEach((dn) => {', false);
    $response->assertSee('item.setAttribute(\'aria-pressed\', on ? \'true\' : \'false\');', false);
    $response->assertSee('item.addEventListener(\'click\', () => setLucClass(on ? null : dn));', false);
    $response->assertSee('allButton.setAttribute(\'aria-pressed\', allSelected ? \'true\' : \'false\');', false);
    $response->assertSee('lastFiltered.forEach((point) => {', false);
    $response->assertSee('function setLucClass(dn)', false);
    $response->assertSee('function setLucFilterOpen(open)', false);
    $response->assertSee('let isLucFilterOpen = false;', false);
    $response->assertSee('setLucFilterOpen(!isLucFilterOpen);', false);
    $response->assertSee('lucFilterTrigger.addEventListener(\'click\'', false);
    $response->assertDontSee('document.addEventListener(\'pointerdown\'', false);
    $response->assertSee('if (event.key === \'Escape\')', false);
    $response->assertSee('document.getElementById(\'luc-all-categories\')?.addEventListener(\'click\', () => setLucClass(null));', false);
    $response->assertSee('id="luc-all-categories" type="button" aria-pressed="true"', false);
    $response->assertDontSee('luc-reset-filter', false);
    $response->assertSee('state.lucClass = null;', false);
    $response->assertSee('No fire points match \' + lucClassLabel(state.lucClass) + \' in \' + state.year', false);
    $response->assertSee('Seven unmapped DNs use provisional spatial groupings into 13 displayed categories; source DN values are preserved.', false);
    $response->assertSee('Tujuh DN tanpa nama dikelompokkan sementara berdasarkan pola spasial ke dalam 13 kategori; nilai DN sumber tetap dipertahankan.', false);
    $response->assertSee('label.className = \'min-w-0 flex-1 whitespace-normal wrap-anywhere leading-snug\';', false);
    $response->assertDontSee('item.title = count', false);
    preg_match('/const LUC_MARKER_PALETTE = \{(.*?)\n                \};/s', (string) $response->getContent(), $paletteMatch);
    expect($paletteMatch)->not->toBeEmpty();
    preg_match_all('/(\d+):\s*\'(#[0-9A-Fa-f]{6})\'/', $paletteMatch[1], $paletteEntries);
    expect($paletteEntries[1])->toHaveCount(13);
    expect($paletteEntries[1])->toBe(['3', '5', '9', '13', '21', '24', '25', '30', '31', '33', '35', '40', '76']);
    expect(array_unique($paletteEntries[2]))->toHaveCount(13);
    $paletteLabs = [];
    foreach ($paletteEntries[2] as $index => $paletteColor) {
        $channels = array_map(static fn (string $channel): float => hexdec($channel) / 255, str_split(substr($paletteColor, 1), 2));
        [$red, $green, $blue] = $channels;
        $maximum = max($channels);
        $minimum = min($channels);
        $difference = $maximum - $minimum;
        $hue = match ($maximum) {
            $red => $difference === 0.0 ? 0 : fmod((($green - $blue) / $difference), 6) * 60,
            $green => (($blue - $red) / $difference + 2) * 60,
            default => (($red - $green) / $difference + 4) * 60,
        };
        if ($hue < 0) {
            $hue += 360;
        }

        // DN 9 is the intentional dark-brown earth tone; warm red-orange hues are reserved for fire.
        if ((int) $paletteEntries[1][$index] !== 9) {
            expect($hue)->toBeGreaterThanOrEqual(45);
            expect($hue)->toBeLessThan(345);
        }

        $linearChannels = array_map(static fn (float $channel): float => $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4, $channels);
        [$linearRed, $linearGreen, $linearBlue] = $linearChannels;
        $x = ($linearRed * 0.4124 + $linearGreen * 0.3576 + $linearBlue * 0.1805) / 0.95047;
        $y = $linearRed * 0.2126 + $linearGreen * 0.7152 + $linearBlue * 0.0722;
        $z = ($linearRed * 0.0193 + $linearGreen * 0.1192 + $linearBlue * 0.9505) / 1.08883;
        $labTransfer = static fn (float $value): float => $value > 0.008856
            ? $value ** (1 / 3)
            : 7.787 * $value + 16 / 116;
        $labX = $labTransfer($x);
        $labY = $labTransfer($y);
        $labZ = $labTransfer($z);
        $paletteLabs[] = [
            116 * $labY - 16,
            500 * ($labX - $labY),
            200 * ($labY - $labZ),
        ];
    }

    foreach ($paletteLabs as $firstIndex => $firstColor) {
        foreach (array_slice($paletteLabs, $firstIndex + 1) as $secondColor) {
            $deltaE = sqrt(array_sum(array_map(
                static fn (float $first, float $second): float => ($first - $second) ** 2,
                $firstColor,
                $secondColor,
            )));

            expect($deltaE)->toBeGreaterThanOrEqual(30);
        }
    }
    $response->assertSee('label.textContent = lucClassLabel(dn)', false);
    $response->assertSee('label.textContent = lucClassLabel(dn);', false);
    $response->assertSee('function lucDnChipHtml(dn, year = selectedLucYear())', false);
    $response->assertSee('const provisional = LUC_PROVISIONAL_DNS.has(Number(dn));', false);
    $response->assertSee('countLabel.textContent = fmt(count)', false);
    $response->assertSee("'Kelas tutupan lahan'", false);
    $response->assertSee("'<span class=\"inline-flex items-center gap-1\"><span class=\"w-2.5 h-2.5 rounded-[2px]\" style=\"background:' + lucMarkerColor(dn, year)", false);
    $response->assertDontSee('landcover_2021.json', false);
    $response->assertDontSee('landcover_2024.json', false);
});

test('provisional land-cover categories preserve all matched source DN points', function () {
    $dnToClass = [
        3 => 3, 5 => 5, 9 => 9, 11 => 21, 12 => 3, 13 => 13, 21 => 21,
        24 => 24, 25 => 25, 30 => 30, 31 => 31, 33 => 33, 35 => 35,
        40 => 40, 46 => 3, 48 => 21, 49 => 21, 76 => 76, 80 => 21, 92 => 3,
    ];
    $samples = json_decode((string) file_get_contents(public_path('data/fire_landcover_samples.json')), true);
    $sourceCounts = [];
    $categoryCounts = [];

    foreach ($samples['records'] as $record) {
        $sourceDn = $record[2];
        if ($sourceDn === null) {
            continue;
        }

        expect(isset($dnToClass[$sourceDn]))->toBeTrue();
        $sourceCounts[$sourceDn] = ($sourceCounts[$sourceDn] ?? 0) + 1;
        $classDn = $dnToClass[$sourceDn];
        $categoryCounts[$classDn] = ($categoryCounts[$classDn] ?? 0) + 1;
    }

    expect(array_values(array_unique($dnToClass)))->toHaveCount(13);
    expect(array_sum($categoryCounts))->toBe(array_sum($sourceCounts));
});

test('fire land-cover samples include a same-year DN aligned to every fire point', function () {
    $path = public_path('data/fire_landcover_samples.json');
    expect($path)->toBeFile();

    $samples = json_decode((string) file_get_contents($path), true);
    $fires = json_decode((string) file_get_contents(public_path('data/fires.json')), true);

    expect($samples['type'])->toBe('fire-landcover-samples');
    expect($samples['recordsAlignWith'])->toBe('/data/fires.json pts array order');
    expect($samples['recordLayout'])->toBe(['beforeDn', 'afterDn', 'fireDn']);
    expect($samples['fireCount'])->toBe(count($fires['pts']));
    expect(count($samples['records']))->toBe(count($fires['pts']));

    $raster2024 = landcoverIndex(2024);
    $sampleBuilder = (string) file_get_contents(base_path('scripts/build_fire_landcover_samples.cjs'));
    expect($raster2024['year'])->toBe(2024);
    expect($raster2024['image'])->toBe('/data/landcover/landcover_2024.png');
    expect($sampleBuilder)->toContain('const fireDn = sampleDn(point, fireYear);');
    expect($samples['matchedPointsByFireYear']['2024'])->toBe(22982);

    $matchedByYear = [];
    $missingByYear = array_fill_keys([2020, 2021, 2022, 2023, 2024], 0);
    foreach ($fires['pts'] as $index => $fire) {
        $sample = $samples['records'][$index];
        expect($sample)->toHaveCount(3);
        if ($sample[2] !== null) {
            $matchedByYear[$fire[3]] = ($matchedByYear[$fire[3]] ?? 0) + 1;
        } else {
            $missingByYear[$fire[3]]++;
        }
    }

    ksort($matchedByYear);
    $declaredMatches = $samples['matchedPointsByFireYear'];
    ksort($declaredMatches);

    expect($matchedByYear)->toBe($declaredMatches);
    expect($missingByYear)->toBe([2020 => 2, 2021 => 0, 2022 => 1, 2023 => 13, 2024 => 13]);
});
