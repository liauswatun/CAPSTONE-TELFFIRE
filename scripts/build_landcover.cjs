/*
 * Build a land cover raster from line-delimited GeoJSON features.
 *
 * Usage:
 *   node scripts/build_landcover.cjs 2020
 *   node scripts/build_landcover.cjs 2021
 *   node scripts/build_landcover.cjs 2021 --variant=full
 *   node scripts/build_landcover.cjs 2021 --variant=full --source=output/data_sumatera_2021_full.geojsonl
 *
 * The source is a large line-delimited GeoJSON of raster-derived polygons:
 * every feature carries a categorical "DN" code and Polygon/MultiPolygon
 * geometry in CRS84. Loading it in the browser is not possible, so this
 * script streams the file once and rasterizes the *source geometry* onto an
 * aligned WGS84 grid (~275 m). The result is a georeferenced PNG + a small
 * manifest. No class names are inferred: DN stays a numeric source code.
 *
 * Outputs:
 *   public/data/landcover/landcover_YYYY.png
 *   public/data/landcover/index.json          (2020, legacy path)
 *   public/data/landcover/raster_YYYY.json    (2021+)
 *   public/data/landcover/raster_2021_full.json + landcover_2021_full.png
 *
 * The source GeoJSON and public/data/fires.json are read-only inputs here.
 */
const fs = require('fs');
const path = require('path');
const readline = require('readline');
const zlib = require('zlib');

const args = process.argv.slice(2);
const yearArgument = args.find((arg) => arg.startsWith('--year=')) || args.find((arg) => !arg.startsWith('--')) || '2020';
const YEAR = Number(yearArgument.replace(/^--year=/, ''));
const VARIANT = args.find((arg) => arg.startsWith('--variant='))?.slice('--variant='.length) || '';
const sourceArgument = args.find((arg) => arg.startsWith('--source='))?.slice('--source='.length);
if (!Number.isInteger(YEAR) || YEAR < 2020 || YEAR > 2024) {
  throw new Error('usage: node scripts/build_landcover.cjs <2020-2024>');
}
if (VARIANT && (YEAR !== 2021 || VARIANT !== 'full')) {
  throw new Error('only --variant=full is supported for 2021');
}
if (sourceArgument && !VARIANT) {
  throw new Error('custom sources require --variant=full so existing outputs are preserved');
}

const ROOT = path.join(__dirname, '..');
const sourceRelativePath = sourceArgument || (VARIANT === 'full'
  ? 'output/data_sumatera_2021_full.geojsonl'
  : `public/data_perubahan/data_sumatera_${YEAR}.geojson`);
const SRC = path.resolve(ROOT, sourceRelativePath);
const SOURCE_MANIFEST_PATH = path.relative(ROOT, SRC).split(path.sep).join('/');
const OUT_DIR = path.join(ROOT, 'public', 'data', 'landcover');
const outputSuffix = VARIANT ? `_${VARIANT}` : '';
const OUT_PNG = path.join(OUT_DIR, `landcover_${YEAR}${outputSuffix}.png`);
const OUT_INDEX = path.join(OUT_DIR, YEAR === 2020 ? 'index.json' : `raster_${YEAR}${outputSuffix}.json`);

// Grid aligned to WGS84. These bounds cover the full 2020 source bbox:
// lat [-6.16792, 6.07656], lng [95.01139, 109.16363]. Later years reuse the
// same grid so all years line up; empty areas stay transparent.
const STEP = 0.0025; // ~275 m at the equator
const LAT_MIN = -6.16792;
const LNG_MIN = 95.01139;
const WIDTH = 5661;
const HEIGHT = 4898;
const LAT_MAX = LAT_MIN + HEIGHT * STEP;
const LNG_MAX = LNG_MIN + WIDTH * STEP;

// Display-only palette. DN values are source class codes; their meanings are
// not documented in this project, so colours deliberately avoid fire colours
// and the legend shows raw "DN x" labels.
const DN_COLORS = {
  3: '#1B512D',
  5: '#2E6B45',
  9: '#4F8A5B',
  13: '#7FB069',
  21: '#B5C99A',
  24: '#6C8EA4',
  25: '#3D6A8A',
  30: '#8A6FA8',
  31: '#5B4B8A',
  33: '#A88BB5',
  35: '#4C7C7C',
  40: '#8C8C8C',
  76: '#3F3F46',
};
const DNS = [];
const INDEX_OF_DN = new Map();

function colorForDn(dn) {
  if (DN_COLORS[dn]) return DN_COLORS[dn];
  const fallbackColors = ['#64748B', '#0F766E', '#7C3AED', '#B45309', '#BE123C', '#0369A1', '#4D7C0F'];
  return fallbackColors[dn % fallbackColors.length];
}

function registerDn(dn) {
  if (!Number.isInteger(dn) || dn < 1 || dn > 255) return false;
  if (!INDEX_OF_DN.has(dn)) {
    if (DNS.length >= 255) return false;
    DNS.push(dn);
    DN_COLORS[dn] = colorForDn(dn);
    INDEX_OF_DN.set(dn, DNS.length);
  }
  return true;
}

const CELLS = WIDTH * HEIGHT;
const DN_GRID = new Uint8Array(CELLS);
const PRIORITY = new Float32Array(CELLS);

function createPointBuffer(capacity = 2048) {
  return { xs: new Float64Array(capacity), ys: new Float64Array(capacity), n: 0, capacity, ringStarts: [] };
}

function pushPoint(buffer, x, y) {
  if (buffer.n >= buffer.capacity) {
    const next = buffer.capacity * 2;
    const xs = new Float64Array(next);
    const ys = new Float64Array(next);
    xs.set(buffer.xs);
    ys.set(buffer.ys);
    buffer.xs = xs;
    buffer.ys = ys;
    buffer.capacity = next;
  }
  buffer.xs[buffer.n] = x;
  buffer.ys[buffer.n] = y;
  buffer.n++;
}

function clamp(value, min, max) {
  return value < min ? min : value > max ? max : value;
}

function setCell(ix, iy, dn, priority) {
  const idx = iy * WIDTH + ix;
  if (priority > PRIORITY[idx]) {
    PRIORITY[idx] = priority;
    DN_GRID[idx] = dn;
  }
}

const buffer = createPointBuffer();

/*
 * Scanline fill for polygons that span more than one output cell. Ring
 * boundaries are recorded while parsing so holes are handled with the
 * even-odd rule (no false edge is created between two separate rings).
 */
function fillLargeFeature(dn, minX, minY, maxX, maxY) {
  const xs = buffer.xs;
  const ys = buffer.ys;
  const rings = buffer.ringStarts.length ? buffer.ringStarts : [0];
  const total = buffer.n;

  const ix0 = clamp(Math.floor((minX - LNG_MIN) / STEP), 0, WIDTH - 1);
  const ix1 = clamp(Math.floor((maxX - LNG_MIN) / STEP), 0, WIDTH - 1);
  const iy0 = clamp(Math.floor((LAT_MAX - maxY) / STEP), 0, HEIGHT - 1);
  const iy1 = clamp(Math.floor((LAT_MAX - minY) / STEP), 0, HEIGHT - 1);
  const priority = 1e12 + (maxX - minX) * (maxY - minY);

  let covered = 0;
  const crossings = [];

  for (let iy = iy0; iy <= iy1; iy++) {
    const lat = LAT_MAX - (iy + 0.5) * STEP;
    crossings.length = 0;

    for (let r = 0; r < rings.length; r++) {
      const start = rings[r];
      const end = r + 1 < rings.length ? rings[r + 1] : total;
      for (let i = start; i < end; i++) {
        const j = i + 1 < end ? i + 1 : start;
        const yi = ys[i];
        const yj = ys[j];
        if ((yi > lat) !== (yj > lat)) {
          const xi = xs[i];
          const xj = xs[j];
          crossings.push(xi + ((lat - yi) * (xj - xi)) / (yj - yi));
        }
      }
    }

    if (crossings.length < 2) continue;
    crossings.sort((a, b) => a - b);

    for (let k = 0; k + 1 < crossings.length; k += 2) {
      let xa = crossings[k];
      let xb = crossings[k + 1];
      if (xb < xa) {
        const swap = xa;
        xa = xb;
        xb = swap;
      }
      // Cell centres are LNG_MIN + (ix + 0.5) * STEP.
      let ia = Math.ceil((xa - LNG_MIN) / STEP - 0.5);
      let ib = Math.floor((xb - LNG_MIN) / STEP - 0.5);
      ia = clamp(ia, ix0, ix1);
      ib = clamp(ib, ix0, ix1);
      for (let ix = ia; ix <= ib; ix++) {
        setCell(ix, iy, dn, priority);
        covered++;
      }
    }
  }

  return covered;
}

function consumeFeature(line) {
  const featureLine = line.replace(/^\uFEFF/, '').trimStart();
  if (!/^\{\s*"type"\s*:\s*"Feature"/.test(featureLine)) return null;
  const dnMatch = /"DN"\s*:\s*(\d+)/.exec(featureLine);
  if (!dnMatch) return null;
  const dn = Number(dnMatch[1]);
  if (!registerDn(dn)) return null;

  const coordinateMatch = /"coordinates"\s*:\s*\[/.exec(featureLine);
  const coordStart = coordinateMatch?.index ?? -1;
  if (coordStart === -1) return null;

  buffer.n = 0;
  buffer.ringStarts.length = 0;
  let numStart = -1;
  let pair = 0;
  let currentX = 0;
  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;
  let sumX = 0;
  let sumY = 0;

  const coordinatesStart = coordStart + coordinateMatch[0].length;
  for (let i = coordinatesStart; i < featureLine.length; i++) {
    const code = featureLine.charCodeAt(i);

    if (code === 91) {
      // Detect the start of a ring array: "[ [ number" (the arrays above a
      // ring have another array as their first child, not a number).
      let j = i + 1;
      while (j < line.length && line.charCodeAt(j) <= 32) j++;
      if (line.charCodeAt(j) === 91) {
        let k = j + 1;
        while (k < line.length && line.charCodeAt(k) <= 32) k++;
        const next = line.charCodeAt(k);
        if ((next >= 48 && next <= 57) || next === 45) {
          buffer.ringStarts.push(buffer.n);
        }
      }
      continue;
    }

    const isNumeric = (code >= 48 && code <= 57) || code === 45 || code === 46 || code === 43 || code === 101 || code === 69;
    if (isNumeric) {
      if (numStart === -1) numStart = i;
    } else if (numStart !== -1) {
      const value = Number(featureLine.slice(numStart, i));
      numStart = -1;
      if (!Number.isFinite(value)) continue;
      if (pair === 0) {
        currentX = value;
        if (value < minX) minX = value;
        if (value > maxX) maxX = value;
      } else {
        pushPoint(buffer, currentX, value);
        sumX += currentX;
        sumY += value;
        if (value < minY) minY = value;
        if (value > maxY) maxY = value;
      }
      pair ^= 1;
    }
  }

  if (buffer.n === 0) return null;

  const centroidX = sumX / buffer.n;
  const centroidY = sumY / buffer.n;
  const centerIx = clamp(Math.floor((centroidX - LNG_MIN) / STEP), 0, WIDTH - 1);
  const centerIy = clamp(Math.floor((LAT_MAX - centroidY) / STEP), 0, HEIGHT - 1);
  const cellCenterX = LNG_MIN + (centerIx + 0.5) * STEP;
  const cellCenterY = LAT_MAX - (centerIy + 0.5) * STEP;
  const distanceSq = (centroidX - cellCenterX) ** 2 + (centroidY - cellCenterY) ** 2;

  const spansMultipleCells = (maxX - minX) > STEP || (maxY - minY) > STEP;
  let cellsCovered = 0;

  if (spansMultipleCells && buffer.n >= 4) {
    cellsCovered = fillLargeFeature(dn, minX, minY, maxX, maxY);
  }

  if (cellsCovered === 0) {
    // Sub-cell polygon (the common case: one ~30 m source pixel): keep the
    // polygon whose centroid is closest to the output cell centre.
    setCell(centerIx, centerIy, dn, 1e9 - distanceSq);
    cellsCovered = 1;
  }

  return {
    cellsCovered,
    large: spansMultipleCells,
    minX,
    minY,
    maxX,
    maxY,
  };
}

function crcTable() {
  const table = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    table[n] = c >>> 0;
  }
  return table;
}
const CRC_TABLE = crcTable();

function crc32(buffer) {
  let c = 0xffffffff;
  for (let i = 0; i < buffer.length; i++) c = CRC_TABLE[(c ^ buffer[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

function pngChunk(type, data) {
  const typeBuffer = Buffer.from(type, 'ascii');
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length, 0);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(Buffer.concat([typeBuffer, data])), 0);
  return Buffer.concat([length, typeBuffer, data, crc]);
}

function hexToRgb(hex) {
  return [parseInt(hex.slice(1, 3), 16), parseInt(hex.slice(3, 5), 16), parseInt(hex.slice(5, 7), 16)];
}

function writePng(file) {
  // Index 0 is the transparent "no data" entry; DN colours start at index 1.
  const palette = Buffer.alloc((DNS.length + 1) * 3);
  const alpha = Buffer.alloc(DNS.length + 1);
  alpha[0] = 0;
  DNS.forEach((dn, i) => {
    const [r, g, b] = hexToRgb(DN_COLORS[dn]);
    palette[(i + 1) * 3] = r;
    palette[(i + 1) * 3 + 1] = g;
    palette[(i + 1) * 3 + 2] = b;
    alpha[i + 1] = 255;
  });

  const rowLength = WIDTH + 1;
  const raw = Buffer.alloc(rowLength * HEIGHT);
  for (let y = 0; y < HEIGHT; y++) {
    const row = y * rowLength;
    raw[row] = 0; // PNG filter: none
    const source = y * WIDTH;
    for (let x = 0; x < WIDTH; x++) {
      const dn = DN_GRID[source + x];
      raw[row + 1 + x] = dn ? INDEX_OF_DN.get(dn) || 0 : 0;
    }
  }

  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(WIDTH, 0);
  ihdr.writeUInt32BE(HEIGHT, 4);
  ihdr[8] = 8; // bit depth
  ihdr[9] = 3; // palette
  ihdr[10] = 0;
  ihdr[11] = 0;
  ihdr[12] = 0;

  const png = Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    pngChunk('IHDR', ihdr),
    pngChunk('PLTE', palette),
    pngChunk('tRNS', alpha),
    pngChunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    pngChunk('IEND', Buffer.alloc(0)),
  ]);

  fs.writeFileSync(file, png);
  return png.length;
}

(async () => {
  if (!fs.existsSync(SRC)) {
    throw new Error('source not found: ' + SRC);
  }
  fs.mkdirSync(OUT_DIR, { recursive: true });

  const started = Date.now();
  let featureCount = 0;
  let largeCount = 0;
  let skipped = 0;
  let observedMinX = Infinity;
  let observedMinY = Infinity;
  let observedMaxX = -Infinity;
  let observedMaxY = -Infinity;

  const rl = readline.createInterface({
    input: fs.createReadStream(SRC, { encoding: 'utf8' }),
    crlfDelay: Infinity,
  });

  const limit = Number(process.env.LIMIT || 0);

  for await (const line of rl) {
    const result = consumeFeature(line);
    if (!result) {
      if (/^\{\s*"type"\s*:\s*"Feature"/.test(line.replace(/^\uFEFF/, '').trimStart())) skipped++;
      continue;
    }
    featureCount++;
    if (result.large) largeCount++;
    if (result.minX < observedMinX) observedMinX = result.minX;
    if (result.minY < observedMinY) observedMinY = result.minY;
    if (result.maxX > observedMaxX) observedMaxX = result.maxX;
    if (result.maxY > observedMaxY) observedMaxY = result.maxY;
    if (featureCount % 100000 === 0) {
      const elapsed = ((Date.now() - started) / 1000).toFixed(0);
      console.log(`  read ${featureCount.toLocaleString()} features (${elapsed}s, ${largeCount.toLocaleString()} large)…`);
    }
    if (limit && featureCount >= limit) {
      rl.close();
      break;
    }
  }
  rl.close();

  if (limit) {
    console.log(`limit ${limit.toLocaleString()} reached in ${((Date.now() - started) / 1000).toFixed(1)}s (no output written)`);
    process.exit(0);
  }

  if (featureCount === 0) {
    throw new Error('no valid GeoJSON Feature lines found in source');
  }
  if (skipped > 0) {
    throw new Error(`source contains ${skipped} Feature lines that could not be rasterized`);
  }
  if (observedMinY < LAT_MIN || observedMinX < LNG_MIN || observedMaxY > LAT_MAX || observedMaxX > LNG_MAX) {
    throw new Error(`source bbox exceeds raster grid: ${JSON.stringify([observedMinY, observedMinX, observedMaxY, observedMaxX])}`);
  }
  DNS.sort((a, b) => a - b);
  INDEX_OF_DN.clear();
  DNS.forEach((dn, index) => INDEX_OF_DN.set(dn, index + 1));

  let occupiedCells = 0;
  let countsByDn = {};
  for (let i = 0; i < CELLS; i++) {
    const dn = DN_GRID[i];
    if (dn) {
      occupiedCells++;
      countsByDn[dn] = (countsByDn[dn] || 0) + 1;
    }
  }

  const pngBytes = writePng(OUT_PNG);

  const manifest = {
    type: 'landcover-raster',
    year: YEAR,
    source: SOURCE_MANIFEST_PATH,
    method: 'stream-rasterize source polygons to WGS84 grid (even-odd fill for multi-cell polygons, nearest-centroid for sub-cell polygons)',
    step: STEP,
    bbox: [LAT_MIN, LNG_MIN, LAT_MAX, LNG_MAX],
    width: WIDTH,
    height: HEIGHT,
    image: `/data/landcover/landcover_${YEAR}${outputSuffix}.png`,
    dnValues: DNS,
    palette: Object.fromEntries(DNS.map((dn) => [dn, DN_COLORS[dn]])),
    legendNote: 'DN is the categorical class code from the source dataset. Its class meanings are not documented in this project, so the legend shows raw DN codes and colours are display-only.',
    featureCount,
    skippedFeatures: skipped,
    largeFeatures: largeCount,
    sourceBbox: [observedMinY, observedMinX, observedMaxY, observedMaxX],
    occupiedCells,
    occupiedCellPct: Number(((occupiedCells / CELLS) * 100).toFixed(2)),
    cellsByDn: countsByDn,
    generatedAt: new Date().toISOString(),
  };

  fs.writeFileSync(OUT_INDEX, JSON.stringify(manifest, null, 2));

  console.log(`--- land cover ${YEAR} ---`);
  console.log(`features parsed : ${featureCount.toLocaleString()} (skipped ${skipped.toLocaleString()}, large ${largeCount.toLocaleString()})`);
  console.log(`source bbox     : ${JSON.stringify(manifest.sourceBbox)}`);
  console.log(`grid            : ${WIDTH}x${HEIGHT} step=${STEP} (${CELLS.toLocaleString()} cells)`);
  console.log(`occupied cells  : ${occupiedCells.toLocaleString()} (${manifest.occupiedCellPct}%)`);
  console.log(`dn counts       : ${JSON.stringify(countsByDn)}`);
  console.log(`png             : ${OUT_PNG} (${(pngBytes / 1048576).toFixed(2)} MB)`);
  console.log(`index           : ${OUT_INDEX}`);
  console.log(`elapsed         : ${((Date.now() - started) / 1000).toFixed(1)} s`);
})();
