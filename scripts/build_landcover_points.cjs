const fs = require('fs');
const path = require('path');
const readline = require('readline');
const zlib = require('zlib');

const args = process.argv.slice(2);
// --raster-only builds one point per connected DN area directly from the web raster.
const yearArgument = args.find((arg) => arg.startsWith('--year=')) || args.find((arg) => !arg.startsWith('--')) || '2020';
const YEAR = Number(yearArgument.replace(/^--year=/, ''));
const VARIANT = args.find((arg) => arg.startsWith('--variant='))?.slice('--variant='.length) || '';
const RASTER_ONLY = args.includes('--raster-only');
if (!Number.isInteger(YEAR) || YEAR < 2020 || YEAR > 2024) {
  throw new Error('usage: node scripts/build_landcover_points.cjs <2020-2024>');
}
if (VARIANT && (YEAR !== 2021 || VARIANT !== 'full')) {
  throw new Error('only --variant=full is supported for 2021');
}
if (RASTER_ONLY && (YEAR < 2020 || (VARIANT && (YEAR !== 2021 || VARIANT !== 'full')))) {
  throw new Error('--raster-only supports 2020–2024; --variant=full is only valid for 2021');
}

const ROOT = path.join(__dirname, '..');
const OUT_DIR = path.join(ROOT, 'public', 'data', 'landcover');
const outputSuffix = VARIANT ? `_${VARIANT}` : '';
const rasterSuffix = RASTER_ONLY ? '' : outputSuffix;
const RASTER_PATH = path.join(OUT_DIR, `landcover_${YEAR}${rasterSuffix}.png`);
const RASTER_INDEX_PATH = path.join(OUT_DIR, YEAR === 2020 ? 'index.json' : `raster_${YEAR}${rasterSuffix}.json`);
const POINTS_PATH = path.join(OUT_DIR, `landcover_points_${YEAR}${outputSuffix}.bin`);
const POINTS_INDEX_PATH = path.join(OUT_DIR, `points_${YEAR}${outputSuffix}.json`);

const rasterIndex = JSON.parse(fs.readFileSync(RASTER_INDEX_PATH, 'utf8'));
const SOURCE_PATH = path.resolve(ROOT, rasterIndex.source);
const { width, height, step, bbox, dnValues, palette } = rasterIndex;
const [LAT_MIN, LNG_MIN, LAT_MAX] = bbox;
const CELLS = width * height;
const DN_VALUES = dnValues.slice();
const DN_INDEX = new Map(DN_VALUES.map((dn, index) => [dn, index + 1]));

const EARTH_RADIUS_M = 6371008.8;
const RAD = Math.PI / 180;
const DEG_LNG_M = step * RAD;
const ROW_AREA_M2 = new Float64Array(height);
for (let y = 0; y < height; y++) {
  const latTop = LAT_MAX - y * step;
  const latBottom = latTop - step;
  ROW_AREA_M2[y] = EARTH_RADIUS_M * EARTH_RADIUS_M * DEG_LNG_M * (Math.sin(latTop * RAD) - Math.sin(latBottom * RAD));
}

function readPngIndices(file) {
  const png = fs.readFileSync(file);
  let offset = 8;
  const chunks = {};
  while (offset < png.length) {
    const length = png.readUInt32BE(offset);
    const type = png.toString('ascii', offset + 4, offset + 8);
    (chunks[type] ||= []).push(png.subarray(offset + 8, offset + 8 + length));
    offset += 12 + length;
  }
  const ihdr = chunks.IHDR[0];
  const imageWidth = ihdr.readUInt32BE(0);
  const imageHeight = ihdr.readUInt32BE(4);
  const bitDepth = ihdr[8];
  const colorType = ihdr[9];
  if (bitDepth !== 8 || colorType !== 3 || imageWidth !== width || imageHeight !== height) {
    throw new Error('unexpected land cover PNG format');
  }
  const raw = zlib.inflateSync(Buffer.concat(chunks.IDAT));
  const stride = imageWidth + 1;
  if (raw.length !== stride * imageHeight) {
    throw new Error('unexpected PNG data length');
  }
  const indices = new Uint8Array(imageWidth * imageHeight);
  let previous = new Uint8Array(imageWidth);
  for (let y = 0; y < imageHeight; y++) {
    const row = raw.subarray(y * stride, y * stride + stride);
    const filter = row[0];
    const current = new Uint8Array(imageWidth);
    for (let x = 0; x < imageWidth; x++) {
      const value = row[x + 1];
      const left = x > 0 ? current[x - 1] : 0;
      const above = previous[x];
      const upperLeft = x > 0 ? previous[x - 1] : 0;
      let result;
      if (filter === 0) result = value;
      else if (filter === 1) result = value + left;
      else if (filter === 2) result = value + above;
      else if (filter === 3) result = value + ((left + above) >> 1);
      else if (filter === 4) {
        const p = left + above - upperLeft;
        const pa = Math.abs(p - left);
        const pb = Math.abs(p - above);
        const pc = Math.abs(p - upperLeft);
        result = value + (pa <= pb && pa <= pc ? left : (pb <= pc ? above : upperLeft));
      } else {
        throw new Error('unsupported PNG filter ' + filter);
      }
      current[x] = result & 255;
      indices[y * imageWidth + x] = current[x];
    }
    previous = current;
  }
  return indices;
}

function labelComponents(pixels) {
  const componentOfCell = new Uint32Array(CELLS);
  const componentDn = [];
  const componentCells = [];
  const componentAreaM2 = [];
  const componentSumX = [];
  const componentSumY = [];
  const stack = new Int32Array(CELLS);
  let componentCount = 0;

  for (let start = 0; start < CELLS; start++) {
    const dnIndex = pixels[start];
    if (dnIndex === 0 || componentOfCell[start] !== 0) continue;
    const id = ++componentCount;
    let stackSize = 0;
    let cells = 0;
    let sumX = 0;
    let sumY = 0;
    let areaM2 = 0;
    componentOfCell[start] = id;
    stack[stackSize++] = start;

    while (stackSize > 0) {
      const cell = stack[--stackSize];
      const x = cell % width;
      const y = (cell / width) | 0;
      cells++;
      sumX += x;
      sumY += y;
      areaM2 += ROW_AREA_M2[y];

      if (x > 0) {
        const next = cell - 1;
        if (componentOfCell[next] === 0 && pixels[next] === dnIndex) {
          componentOfCell[next] = id;
          stack[stackSize++] = next;
        }
      }
      if (x + 1 < width) {
        const next = cell + 1;
        if (componentOfCell[next] === 0 && pixels[next] === dnIndex) {
          componentOfCell[next] = id;
          stack[stackSize++] = next;
        }
      }
      if (y > 0) {
        const next = cell - width;
        if (componentOfCell[next] === 0 && pixels[next] === dnIndex) {
          componentOfCell[next] = id;
          stack[stackSize++] = next;
        }
      }
      if (y + 1 < height) {
        const next = cell + width;
        if (componentOfCell[next] === 0 && pixels[next] === dnIndex) {
          componentOfCell[next] = id;
          stack[stackSize++] = next;
        }
      }
    }

    componentDn.push(dnIndex);
    componentCells.push(cells);
    componentAreaM2.push(areaM2);
    componentSumX.push(sumX);
    componentSumY.push(sumY);
  }

  const representativeX = new Int32Array(componentCount);
  const representativeY = new Int32Array(componentCount);
  const bestDistance = new Float64Array(componentCount).fill(Infinity);
  for (let cell = 0; cell < CELLS; cell++) {
    const id = componentOfCell[cell];
    if (id === 0) continue;
    const index = id - 1;
    const x = cell % width;
    const y = (cell / width) | 0;
    const dx = x - componentSumX[index] / componentCells[index];
    const dy = y - componentSumY[index] / componentCells[index];
    const distance = dx * dx + dy * dy;
    if (distance < bestDistance[index]) {
      bestDistance[index] = distance;
      representativeX[index] = x;
      representativeY[index] = y;
    }
  }

  return {
    componentOfCell,
    componentDn,
    componentCells,
    componentAreaM2,
    representativeX,
    representativeY,
    componentCount,
  };
}

async function assignSourcePoints(components) {
  const {
    componentOfCell,
    componentDn,
    componentCount,
  } = components;
  const candidateLat = new Float64Array(componentCount);
  const candidateLng = new Float64Array(componentCount);
  const candidateArea = new Float64Array(componentCount);
  const pairPattern = /\[\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*\]/g;
  const dnPattern = /"DN"\s*:\s*(\d+)/;
  let sourceFeatures = 0;
  let componentCandidates = 0;
  let sourceCandidates = 0;

  const reader = readline.createInterface({
    input: fs.createReadStream(SOURCE_PATH),
    crlfDelay: Infinity,
  });

  for await (const line of reader) {
    if (!/^\{\s*"type"\s*:\s*"Feature"/.test(line.replace(/^\uFEFF/, '').trimStart())) continue;
    sourceFeatures++;
    const dnMatch = line.match(dnPattern);
    if (!dnMatch) continue;
    const dnIndex = DN_INDEX.get(Number(dnMatch[1]));
    if (!dnIndex) continue;

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    pairPattern.lastIndex = 0;
    let match;
    while ((match = pairPattern.exec(line)) !== null) {
      const x = Number(match[1]);
      const y = Number(match[2]);
      if (x < minX) minX = x;
      if (x > maxX) maxX = x;
      if (y < minY) minY = y;
      if (y > maxY) maxY = y;
    }
    if (!Number.isFinite(minX)) continue;

    const lat = (minY + maxY) / 2;
    const lng = (minX + maxX) / 2;
    const x = Math.floor((lng - LNG_MIN) / step);
    const y = Math.floor((LAT_MAX - lat) / step);
    if (x < 0 || y < 0 || x >= width || y >= height) continue;
    const component = componentOfCell[y * width + x];
    if (component === 0) continue;
    const index = component - 1;
    if (componentDn[index] !== dnIndex) continue;
    const area = (maxX - minX) * (maxY - minY);
    if (area > candidateArea[index]) {
      if (candidateArea[index] === 0) componentCandidates++;
      candidateArea[index] = area;
      candidateLat[index] = lat;
      candidateLng[index] = lng;
      sourceCandidates++;
    }
  }

  return { candidateLat, candidateLng, candidateArea, sourceFeatures, componentCandidates, sourceCandidates };
}

function writePoints(components, sourcePoints, pixels) {
  const {
    componentDn,
    componentAreaM2,
    representativeX,
    representativeY,
    componentCount,
  } = components;
  const buffer = Buffer.alloc(componentCount * 13);
  let fallbackCount = 0;
  let snappedCount = 0;
  let mismatches = 0;

  for (let index = 0; index < componentCount; index++) {
    const useSource = sourcePoints.candidateArea[index] > 0;
    let lat;
    let lng;
    if (useSource) {
      lat = sourcePoints.candidateLat[index];
      lng = sourcePoints.candidateLng[index];
    } else {
      fallbackCount++;
      lat = LAT_MAX - (representativeY[index] + 0.5) * step;
      lng = LNG_MIN + (representativeX[index] + 0.5) * step;
    }

    let latInt = Math.round(lat * 1e7);
    let lngInt = Math.round(lng * 1e7);
    let storedLat = latInt / 1e7;
    let storedLng = lngInt / 1e7;
    let x = Math.floor((storedLng - LNG_MIN) / step);
    let y = Math.floor((LAT_MAX - storedLat) / step);

    if (pixels[y * width + x] !== componentDn[index]) {
      snappedCount++;
      lat = LAT_MAX - (representativeY[index] + 0.5) * step;
      lng = LNG_MIN + (representativeX[index] + 0.5) * step;
      latInt = Math.round(lat * 1e7);
      lngInt = Math.round(lng * 1e7);
      storedLat = latInt / 1e7;
      storedLng = lngInt / 1e7;
      x = Math.floor((storedLng - LNG_MIN) / step);
      y = Math.floor((LAT_MAX - storedLat) / step);
    }

    const rasterDn = pixels[y * width + x];
    if (rasterDn !== componentDn[index]) mismatches++;

    const offset = index * 13;
    buffer.writeInt32LE(latInt, offset);
    buffer.writeInt32LE(lngInt, offset + 4);
    buffer.writeUInt8(DN_VALUES[componentDn[index] - 1], offset + 8);
    buffer.writeFloatLE(componentAreaM2[index] / 10000, offset + 9);
  }

  fs.writeFileSync(POINTS_PATH, buffer);
  const metadata = {
    type: 'landcover-points',
    year: YEAR,
    source: rasterIndex.source,
    raster: rasterIndex.image,
    file: `/data/landcover/landcover_points_${YEAR}${outputSuffix}.bin`,
    count: componentCount,
    sourceFeatureCount: rasterIndex.featureCount,
    sourceFeaturesScanned: sourcePoints.sourceFeatures,
    skippedSourceFeatureCount: rasterIndex.skippedFeatures,
    recordSize: 13,
    layout: {
      lat: 'int32le',
      lng: 'int32le',
      dn: 'uint8',
      areaHa: 'float32le',
    },
    coordinateScale: 10000000,
    bbox: rasterIndex.bbox,
    step: rasterIndex.step,
    width: rasterIndex.width,
    height: rasterIndex.height,
    dnValues: rasterIndex.dnValues,
    palette: rasterIndex.palette,
    componentMethod: '4-connected same-DN components of the 275 m web raster',
    representativeMethod: RASTER_ONLY
      ? 'centre of the component cell nearest its centroid; source feature points are not available'
      : 'bbox centre of the largest source polygon whose centre falls in the component; otherwise the component cell nearest its centroid',
    areaMethod: 'spherical grid-cell area summed per component; not a source attribute',
    sourcePointCount: componentCount - fallbackCount,
    fallbackPointCount: fallbackCount,
    snappedPointCount: snappedCount,
    sourceCandidateUpdates: sourcePoints.sourceCandidates,
    mismatches,
    attributesNote: 'Source properties are only fid and DN; before/after classes and change area are not present.',
    generatedAt: new Date().toISOString(),
  };
  fs.writeFileSync(POINTS_INDEX_PATH, JSON.stringify(metadata, null, 2) + '\n');
  return metadata;
}

async function main() {
  if (!RASTER_ONLY && !fs.existsSync(SOURCE_PATH)) {
    throw new Error(`source not found: ${SOURCE_PATH}`);
  }
  const started = Date.now();
  const pixels = readPngIndices(RASTER_PATH);
  const components = labelComponents(pixels);
  console.log(`components: ${components.componentCount}`);
  const sourcePoints = RASTER_ONLY
    ? {
      candidateLat: new Float64Array(components.componentCount),
      candidateLng: new Float64Array(components.componentCount),
      candidateArea: new Float64Array(components.componentCount),
      sourceFeatures: 0,
      componentCandidates: 0,
      sourceCandidates: 0,
    }
    : await assignSourcePoints(components);
  if (!RASTER_ONLY && (sourcePoints.sourceFeatures !== rasterIndex.featureCount || rasterIndex.skippedFeatures !== 0)) {
    throw new Error(`source feature count mismatch: raster=${rasterIndex.featureCount}, points=${sourcePoints.sourceFeatures}, skipped=${rasterIndex.skippedFeatures}`);
  }
  if (RASTER_ONLY) {
    console.log('point coordinates: component-cell centres from the full-Sumatra raster');
  } else {
    console.log(`source features scanned: ${sourcePoints.sourceFeatures}, components with source point: ${sourcePoints.componentCandidates}`);
  }
  const metadata = writePoints(components, sourcePoints, pixels);
  console.log(`points: ${metadata.count}, source: ${metadata.sourcePointCount}, fallback: ${metadata.fallbackPointCount}, mismatches: ${metadata.mismatches}`);
  console.log(`bytes: ${metadata.count * 13}, elapsed: ${((Date.now() - started) / 1000).toFixed(1)}s`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
