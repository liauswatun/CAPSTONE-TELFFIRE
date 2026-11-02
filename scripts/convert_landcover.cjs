/*
 * Convert public/data_perubahan/data_sumatera_YYYY.geojson (raster cells with
 * a categorical "DN" attribute per year) into lightweight per-year land cover
 * layers for the platform map:
 *
 *   public/data/landcover/landcover_YYYY.json : [lat, lng, dn, n] cells
 *   public/data/landcover/index.json          : manifest (years, bbox, dn codes)
 *
 * Method (aggregation): source cells are raster polygons; multiple adjacent
 * cells are collapsed into one coarse grid cell (grid step h chosen by binary
 * search so the output cell count stays <= TARGET). The DN written per output
 * cell is the mode (most frequent raw DN inside it, ties -> higher DN); `n` is
 * the number of raw source cells that were collapsed. The raw source files in
 * public/data_perubahan/ are never modified.
 */
const fs = require('fs');
const path = require('path');
const readline = require('readline');

const SRC_DIR = 'public/data_perubahan';
const OUT_DIR = 'public/data/landcover';
const YEARS = [2020, 2021, 2022, 2023, 2024];
const TARGET_CELLS = 40000;

function chunkBuffer() {
  return {
    size: 0, cap: 1 << 20,
    lngX: new Float64Array(1 << 20), latY: new Float64Array(1 << 20),
    wH: new Float32Array(1 << 20), dn: new Uint8Array(1 << 20),
  };
}
function bufPush(b, lng, lat, w, hh, dn) {
  if (b.size >= b.cap) {
    const cap = b.cap * 2;
    const grow = (old, T) => { const a = new T(cap); a.set(old); return a; };
    b.lngX = grow(b.lngX, Float64Array); b.latY = grow(b.latY, Float64Array);
    b.wH = grow(b.wH, Float32Array); b.dn = grow(b.dn, Uint8Array);
    b.cap = cap;
  }
  b.lngX[b.size] = lng; b.latY[b.size] = lat; b.wH[b.size] = w; b.dn[b.size] = dn; b.size++;
}

async function extractYear(file) {
  const b = chunkBuffer();
  const srcDn = {}; // DN code -> raw source cells count
  let total = 0, minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
  const rl = readline.createInterface({ input: fs.createReadStream(file, { encoding: 'utf8' }), crlfDelay: Infinity });
  const dnRe = /"DN":(\d+)/;
  for await (const line of rl) {
    if (!line.startsWith('{"type":"Feature"')) continue;
    const m = line.match(dnRe);
    if (!m) continue;
    const cIdx = line.indexOf('"coordinates":[');
    if (cIdx === -1) continue;
    let n0 = -1, pairPos = 0, started = false, done = false;
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    for (let i = cIdx + 15; i < line.length; i++) {
      const ch = line[i];
      const isNum = (ch >= '0' && ch <= '9') || ch === '-' || ch === '.' || ch === '+' || ch === 'e' || ch === 'E';
      if (isNum) { if (n0 === -1) n0 = i; }
      else if (n0 !== -1) {
        const val = +line.slice(n0, i); n0 = -1;
        if (Number.isNaN(val)) continue;
        if (pairPos === 0) { if (val < x0) x0 = val; if (val > x1) x1 = val; }
        else { if (val < y0) y0 = val; if (val > y1) y1 = val; }
        pairPos ^= 1; started = true;
      } else if (ch === ']' && started && line[i + 1] === ']' && line[i + 2] === '}' && line[i + 3] === '}' && line[i + 4] === '}') {
        done = true; break;
      }
    }
    if (!started) continue;
    const w = x1 - x0, hh = y1 - y0;
    if (w <= 0 || hh <= 0) continue;
    srcDn[m[1]] = (srcDn[m[1]] || 0) + 1;
    bufPush(b, (x0 + x1) / 2, (y0 + y1) / 2, w, hh, +m[1]);
    if (x0 < minx) minx = x0; if (x1 > maxx) maxx = x1;
    if (y0 < miny) miny = y0; if (y1 > maxy) maxy = y1;
    total++;
  }
  return { b, total, bbox: [miny, minx, maxy, maxx], srcDn };
}

function aggregate(b, target) {
  let lo = 0.001, hi = 2.0, h = (lo + hi) / 2;
  const keysAt = (step) => {
    const seen = new Set();
    for (let i = 0; i < b.size; i++) seen.add(Math.floor(b.lngX[i] / step) + ',' + Math.floor(b.latY[i] / step));
    return seen.size;
  };
  for (let iter = 0; iter < 30; iter++) {
    const mid = (lo + hi) / 2;
    const n = keysAt(mid);
    if (n > target * 1.25) lo = mid; else { hi = mid; h = mid; }
    if (hi - lo < 1e-4) break;
  }
  const cells = new Map(); // key -> [tally(Map), cx, cy]
  const q = (v) => Math.floor(v / h);
  for (let i = 0; i < b.size; i++) {
    const key = q(b.lngX[i]) + ',' + q(b.latY[i]);
    let c = cells.get(key);
    if (!c) { c = [null, b.lngX[i], b.latY[i]]; cells.set(key, c); }
    if (!c[0]) c[0] = new Map();
    c[0].set(b.dn[i], (c[0].get(b.dn[i]) || 0) + 1);
  }
  const out = [];
  for (const c of cells.values()) {
    let bestDn = 0, bestN = 0, totalN = 0;
    for (const [dn, n] of c[0]) {
      const dnN = Number(dn);
      totalN += n;
      if (n > bestN || (n === bestN && dnN > bestDn)) { bestDn = dnN; bestN = n; }
    }
    out.push([+c[2].toFixed(5), +c[1].toFixed(5), bestDn, totalN]);
  }
  return { cells: out, h };
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const manifest = { type: 'landcover-index', source: 'public/data_perubahan/data_sumatera_YYYY.geojson', method: 'grid-aggregation dominant-DN', years: YEARS, bboxes: {}, cells: {}, dnValues: {} };
  for (const year of YEARS) {
    const file = path.join(SRC_DIR, `data_sumatera_${year}.geojson`);
    console.log(`=== ${year} ===`);
    console.time(`extract ${year}`);
    const { b, total, bbox, srcDn } = await extractYear(file);
    console.timeEnd(`extract ${year}`);
    console.time(`aggregate ${year}`);
    const { cells, h } = aggregate(b, TARGET_CELLS);
    console.timeEnd(`aggregate ${year}`);
    const outFile = path.join(OUT_DIR, `landcover_${year}.json`);
    const payload = { type: 'landcover', year, step: +h.toFixed(6), bbox: bbox.map((v) => +v.toFixed(5)), cells };
    fs.writeFileSync(outFile, JSON.stringify(payload));
    manifest.bboxes[year] = payload.bbox;
    manifest.cells[year] = cells.length;
    manifest.dnValues[year] = Object.keys(srcDn).map(Number).sort((x, y) => x - y);
    console.log(`rawCells=${total} step=${h.toFixed(5)} outCells=${cells.length} -> ${outFile} (${(fs.statSync(outFile).size / 1048576).toFixed(1)} MB)`);
  }
  fs.writeFileSync(path.join(OUT_DIR, 'index.json'), JSON.stringify(manifest));
  console.log('manifest -> ' + path.join(OUT_DIR, 'index.json'));
})();
