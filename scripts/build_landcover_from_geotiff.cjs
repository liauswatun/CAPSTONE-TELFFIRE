/*
 * Build the web land-cover PNGs from the clipped MapBiomas GeoTIFFs.
 *
 * Usage: node scripts/build_landcover_from_geotiff.cjs
 * Optional: set GDALWARP to the path of gdalwarp.exe.
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const { spawnSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const INPUT_DIR = path.join(ROOT, 'public', 'data_perubahan');
const OUTPUT_DIR = path.join(ROOT, 'public', 'data', 'landcover');
const GDALWARP = process.env.GDALWARP || 'C:\\Program Files\\QGIS 3.44.13\\bin\\gdalwarp.exe';
const STEP = 0.0025;
const LAT_MIN = -6.16792;
const LNG_MIN = 95.01139;
const WIDTH = 5661;
const HEIGHT = 4898;
const LAT_MAX = LAT_MIN + HEIGHT * STEP;
const LNG_MAX = LNG_MIN + WIDTH * STEP;
const CELL_COUNT = WIDTH * HEIGHT;
const COLORS = {
  3: '#1B512D', 5: '#2E6B45', 9: '#4F8A5B', 11: '#739B56', 12: '#A3B86C',
  13: '#7FB069', 21: '#B5C99A', 24: '#6C8EA4', 25: '#3D6A8A', 30: '#8A6FA8',
  31: '#5B4B8A', 33: '#A88BB5', 35: '#4C7C7C', 40: '#8C8C8C', 46: '#C18F5A',
  48: '#C66B4E', 49: '#8D5A45', 76: '#3F3F46', 80: '#B5A642', 92: '#6B7280',
};

function crcTable() {
  const table = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let value = n;
    for (let k = 0; k < 8; k++) value = value & 1 ? 0xedb88320 ^ (value >>> 1) : value >>> 1;
    table[n] = value >>> 0;
  }
  return table;
}
const CRC_TABLE = crcTable();

function crc32(buffer) {
  let value = 0xffffffff;
  for (const byte of buffer) value = CRC_TABLE[(value ^ byte) & 0xff] ^ (value >>> 8);
  return (value ^ 0xffffffff) >>> 0;
}

function pngChunk(type, data) {
  const name = Buffer.from(type, 'ascii');
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(Buffer.concat([name, data])));
  return Buffer.concat([length, name, data, crc]);
}

function writePng(filePath, raster, dns) {
  const palette = Buffer.alloc((dns.length + 1) * 3);
  const alpha = Buffer.alloc(dns.length + 1);
  const indexByDn = new Map();
  dns.forEach((dn, index) => {
    const color = COLORS[dn];
    const offset = (index + 1) * 3;
    palette[offset] = parseInt(color.slice(1, 3), 16);
    palette[offset + 1] = parseInt(color.slice(3, 5), 16);
    palette[offset + 2] = parseInt(color.slice(5, 7), 16);
    alpha[index + 1] = 255;
    indexByDn.set(dn, index + 1);
  });

  const rowLength = WIDTH + 1;
  const raw = Buffer.alloc(rowLength * HEIGHT);
  const counts = Object.fromEntries(dns.map((dn) => [dn, 0]));
  let occupiedCells = 0;
  for (let y = 0; y < HEIGHT; y++) {
    const sourceOffset = y * WIDTH;
    const targetOffset = y * rowLength;
    raw[targetOffset] = 0;
    for (let x = 0; x < WIDTH; x++) {
      const dn = raster[sourceOffset + x];
      if (dn) {
        const paletteIndex = indexByDn.get(dn);
        if (paletteIndex === undefined) throw new Error(`unexpected DN ${dn}`);
        raw[targetOffset + x + 1] = paletteIndex;
        counts[dn]++;
        occupiedCells++;
      }
    }
  }

  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(WIDTH, 0);
  ihdr.writeUInt32BE(HEIGHT, 4);
  ihdr[8] = 8;
  ihdr[9] = 3;
  const png = Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    pngChunk('IHDR', ihdr), pngChunk('PLTE', palette), pngChunk('tRNS', alpha),
    pngChunk('IDAT', zlib.deflateSync(raw, { level: 9 })), pngChunk('IEND', Buffer.alloc(0)),
  ]);
  fs.writeFileSync(filePath, png);
  return { counts, occupiedCells, bytes: png.length };
}

function runWarp(inputPath, rawPath) {
  const result = spawnSync(GDALWARP, [
    '-overwrite', '-q', '-of', 'ENVI', '-ot', 'Byte', '-r', 'mode',
    '-srcnodata', '0', '-dstnodata', '0',
    '-te', String(LNG_MIN), String(LAT_MIN), String(LNG_MAX), String(LAT_MAX),
    '-ts', String(WIDTH), String(HEIGHT), inputPath, rawPath,
  ], { encoding: 'utf8', windowsHide: true });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error((result.stderr || result.stdout || `gdalwarp exited ${result.status}`).trim());
}

function main() {
  if (!fs.existsSync(GDALWARP)) throw new Error(`gdalwarp not found: ${GDALWARP}; set GDALWARP to its path`);
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });

  for (let year = 2020; year <= 2024; year++) {
    const inputPath = path.join(INPUT_DIR, `sumatera_${year}_web.tif`);
    if (!fs.existsSync(inputPath)) throw new Error(`input not found: ${inputPath}`);
    const temporaryBase = path.join(OUTPUT_DIR, `.sumatera_${year}_grid`);
    const rawPath = `${temporaryBase}.dat`;
    const headerPath = `${temporaryBase}.hdr`;
    const pngName = `landcover_${year}.png`;
    const manifestName = year === 2020 ? 'index.json' : `raster_${year}.json`;

    console.log(`Preparing ${year} from ${path.basename(inputPath)}...`);
    if (!fs.existsSync(rawPath)) runWarp(inputPath, rawPath);
    const raster = fs.readFileSync(rawPath);
    if (raster.length !== CELL_COUNT) throw new Error(`${year} grid size mismatch: ${raster.length}`);
    const dns = [...new Set(raster)].filter((dn) => dn !== 0).sort((a, b) => a - b);
    for (const dn of dns) if (!COLORS[dn]) throw new Error(`No display color configured for DN ${dn}`);

    const summary = writePng(path.join(OUTPUT_DIR, pngName), raster, dns);
    const manifest = {
      type: 'landcover-raster', year,
      source: `public/data_perubahan/sumatera_${year}_web.tif`,
      method: 'nearest categorical mode resampling from clipped MapBiomas GeoTIFF to the existing WGS84 web grid (~275 m)',
      step: STEP, bbox: [LAT_MIN, LNG_MIN, LAT_MAX, LNG_MAX], width: WIDTH, height: HEIGHT,
      image: `/data/landcover/${pngName}`,
      dnValues: dns,
      palette: Object.fromEntries(dns.map((dn) => [dn, COLORS[dn]])),
      legendNote: 'DN is the categorical class code from the source dataset. Meanings are not inferred here; colours are display-only.',
      occupiedCells: summary.occupiedCells,
      occupiedCellPct: Number(((summary.occupiedCells / CELL_COUNT) * 100).toFixed(2)),
      cellsByDn: summary.counts,
      generatedAt: new Date().toISOString(),
    };
    fs.writeFileSync(path.join(OUTPUT_DIR, manifestName), JSON.stringify(manifest, null, 2));
    fs.rmSync(rawPath, { force: true });
    fs.rmSync(headerPath, { force: true });
    console.log(`  ${dns.length} DN values; ${summary.occupiedCells.toLocaleString()} occupied cells; PNG ${(summary.bytes / 1048576).toFixed(2)} MB`);
  }
}

main();
