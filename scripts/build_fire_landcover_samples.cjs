const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const ROOT = path.join(__dirname, '..');
const DATA_DIR = path.join(ROOT, 'public', 'data');
const LANDCOVER_DIR = path.join(DATA_DIR, 'landcover');
const fires = JSON.parse(fs.readFileSync(path.join(DATA_DIR, 'fires.json'), 'utf8')).pts;
const rasters = new Map();

function manifestPath(year) {
  return path.join(LANDCOVER_DIR, year === 2020 ? 'index.json' : `raster_${year}.json`);
}

function readPngIndices(file, expectedWidth, expectedHeight) {
  const png = fs.readFileSync(file);
  const chunks = new Map();
  let offset = 8;
  while (offset < png.length) {
    const length = png.readUInt32BE(offset);
    const type = png.toString('ascii', offset + 4, offset + 8);
    chunks.set(type, [...(chunks.get(type) || []), png.subarray(offset + 8, offset + 8 + length)]);
    offset += 12 + length;
  }

  const ihdr = chunks.get('IHDR')[0];
  const width = ihdr.readUInt32BE(0);
  const height = ihdr.readUInt32BE(4);
  if (ihdr[8] !== 8 || ihdr[9] !== 3 || width !== expectedWidth || height !== expectedHeight) {
    throw new Error(`unexpected indexed PNG format: ${file}`);
  }

  const raw = zlib.inflateSync(Buffer.concat(chunks.get('IDAT')));
  const stride = width + 1;
  if (raw.length !== stride * height) {
    throw new Error(`unexpected PNG data length: ${file}`);
  }

  const indices = new Uint8Array(width * height);
  let previous = new Uint8Array(width);
  for (let y = 0; y < height; y++) {
    const row = raw.subarray(y * stride, y * stride + stride);
    const filter = row[0];
    const current = new Uint8Array(width);
    for (let x = 0; x < width; x++) {
      const value = row[x + 1];
      const left = x > 0 ? current[x - 1] : 0;
      const above = previous[x];
      const upperLeft = x > 0 ? previous[x - 1] : 0;
      let result;
      if (filter === 0) result = value;
      else if (filter === 1) result = value + left;
      else if (filter === 2) result = value + above;
      else if (filter === 3) result = value + Math.floor((left + above) / 2);
      else if (filter === 4) {
        const predictor = left + above - upperLeft;
        const leftDistance = Math.abs(predictor - left);
        const aboveDistance = Math.abs(predictor - above);
        const upperLeftDistance = Math.abs(predictor - upperLeft);
        result = value + (leftDistance <= aboveDistance && leftDistance <= upperLeftDistance
          ? left
          : (aboveDistance <= upperLeftDistance ? above : upperLeft));
      } else {
        throw new Error(`unsupported PNG filter ${filter}: ${file}`);
      }
      current[x] = result & 255;
      indices[y * width + x] = current[x];
    }
    previous = current;
  }

  return indices;
}

function loadRaster(year) {
  if (rasters.has(year)) return rasters.get(year);
  const manifest = JSON.parse(fs.readFileSync(manifestPath(year), 'utf8'));
  if (manifest.type !== 'landcover-raster' || Number(manifest.year) !== year) {
    throw new Error(`unexpected MapBiomas raster manifest for ${year}`);
  }
  const raster = {
    manifest,
    pixels: readPngIndices(path.join(ROOT, 'public', manifest.image.replace(/^\//, '')), manifest.width, manifest.height),
  };
  rasters.set(year, raster);
  return raster;
}

function sampleDn(point, year) {
  const { manifest, pixels } = loadRaster(year);
  const [latMin, lngMin, latMax] = manifest.bbox;
  const x = Math.floor((point[1] - lngMin) / manifest.step);
  const y = Math.floor((latMax - point[0]) / manifest.step);
  if (x < 0 || y < 0 || x >= manifest.width || y >= manifest.height) return null;
  const paletteIndex = pixels[y * manifest.width + x];
  return paletteIndex === 0 ? null : (manifest.dnValues[paletteIndex - 1] ?? null);
}

const records = [];
const countsByFireYear = {};
const dnCountsByFireYear = {};
for (const point of fires) {
  const fireYear = Number(point[3]);
  const beforeYear = fireYear - 1;
  const afterYear = fireYear + 1;
  const beforeDn = beforeYear >= 2020 && beforeYear <= 2024 ? sampleDn(point, beforeYear) : null;
  const afterDn = afterYear >= 2020 && afterYear <= 2024 ? sampleDn(point, afterYear) : null;
  const fireDn = sampleDn(point, fireYear);
  records.push([beforeDn, afterDn, fireDn]);
  const stats = countsByFireYear[fireYear] || { firePoints: 0, withFireDn: 0, withBothYears: 0, withEitherYear: 0, changedDn: 0, unchangedDn: 0 };
  stats.firePoints++;
  if (fireDn !== null) {
    stats.withFireDn++;
    const dnCounts = dnCountsByFireYear[fireYear] || (dnCountsByFireYear[fireYear] = {});
    dnCounts[fireDn] = (dnCounts[fireDn] || 0) + 1;
  }
  if (beforeDn !== null && afterDn !== null) {
    stats.withBothYears++;
    if (beforeDn === afterDn) stats.unchangedDn++;
    else stats.changedDn++;
  }
  if (beforeDn !== null || afterDn !== null) stats.withEitherYear++;
  countsByFireYear[fireYear] = stats;
}

const output = {
  type: 'fire-landcover-samples',
  method: 'sample raw MapBiomas DN at each aggregated fire-point coordinate from the annual raster for the fire year, one year before, and one year after; raster grid is approximately 275 m',
  interpretation: 'Same-year DN is the raster cell at the fire point for that fire year; before/after values are comparison samples. Fire year has no exact event date, the point is not the original burned footprint, and annual differences do not establish that fire caused a land-cover change.',
  recordsAlignWith: '/data/fires.json pts array order',
  recordLayout: ['beforeDn', 'afterDn', 'fireDn'],
  yearsByFireYear: Object.fromEntries([2020, 2021, 2022, 2023, 2024].map((year) => [year, {
    fire: year,
    before: year > 2020 ? year - 1 : null,
    after: year < 2024 ? year + 1 : null,
  }])),
  fireCount: fires.length,
  countsByFireYear,
  dnCountsByFireYear,
  matchedPointsByFireYear: Object.fromEntries(Object.entries(countsByFireYear).map(([year, stats]) => [year, stats.withFireDn])),
  records,
  generatedAt: new Date().toISOString(),
};

const outputPath = path.join(DATA_DIR, 'fire_landcover_samples.json');
fs.writeFileSync(outputPath, JSON.stringify(output));
console.log(`Wrote ${records.length.toLocaleString()} samples to ${path.relative(ROOT, outputPath)}`);
console.log(JSON.stringify({ countsByFireYear, matchedPointsByFireYear: output.matchedPointsByFireYear }, null, 2));
