/*
 * Convert public/data_perubahan/data_sumatera_YYYY.geojson (rasterized burn cells)
 * into public/data/fires.json points [lat, lng, dn, year, kecCode].
 *
 * Steps per year:
 *   1. stream features (one per line), extract DN + coordinate bbox
 *   2. point-in-polygon join against public/data/sumatera_kecamatan.geojson
 *      (the map's own polygons -> kecCode in the exact format the platform uses)
 *   3. aggregate cells to a coarser grid so the output point count stays in the
 *      same ballpark as the previous fires.json per-year counts (mode DN per cell)
 *   4. re-find the kecamatan lookup for the representative cell (or its neighbours)
 *
 * Reference tolerance for kecamatan fallback: if the representative cell falls
 * outside every kecamatan polygon, search tiny offsets before giving up.
 */
const fs = require('fs');
const path = require('path');
const readline = require('readline');

const DATA_DIR = 'public/data';
const SRC_DIR = 'public/data_perubahan';
const YEARS = [2020, 2021, 2022, 2023, 2024];
// per-year target point counts = previous fires.json year distribution
const TARGET_COUNTS = { 2020: 23632, 2021: 10968, 2022: 8486, 2023: 25244, 2024: 23025 };

// ---------- kecamatan spatial index ----------
function loadKecIndex() {
  const geo = JSON.parse(fs.readFileSync(path.join(DATA_DIR, 'sumatera_kecamatan.geojson'), 'utf8'));
  const GRID_STEP = 0.25;
  const polyIndex = []; // { code, rings: Float64Array[], bbox:[minx,miny,maxx,maxy] }
  const buckets = new Map();

  const bkey = (gx, gy) => gx + ':' + gy;
  function addPolyToBuckets(poly, idx) {
    const [minx, miny, maxx, maxy] = poly.bbox;
    const gx0 = Math.floor(minx / GRID_STEP), gx1 = Math.floor(maxx / GRID_STEP);
    const gy0 = Math.floor(miny / GRID_STEP), gy1 = Math.floor(maxy / GRID_STEP);
    for (let gx = gx0; gx <= gx1; gx++) for (let gy = gy0; gy <= gy1; gy++) {
      const k = bkey(gx, gy);
      let arr = buckets.get(k);
      if (!arr) { arr = []; buckets.set(k, arr); }
      arr.push(idx);
    }
  }

  geo.features.forEach((f) => {
    const polys = f.geometry.type === 'MultiPolygon' ? f.geometry.coordinates : [f.geometry.coordinates];
    for (const coords of polys) {
      // first ring = outer, rest = holes (even-odd handles them)
      const rings = coords.map((r) => {
        const flat = new Float64Array(r.length * 2);
        let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
        for (let i = 0; i < r.length; i++) {
          const x = r[i][0], y = r[i][1];
          flat[i * 2] = x; flat[i * 2 + 1] = y;
          if (x < minx) minx = x; if (x > maxx) maxx = x;
          if (y < miny) miny = y; if (y > maxy) maxy = y;
        }
        return { flat, bbox: [minx, miny, maxx, maxy] };
      });
      let minx = miny = Infinity, maxx = maxy = -Infinity;
      for (const rg of rings) {
        if (rg.bbox[0] < minx) minx = rg.bbox[0];
        if (rg.bbox[1] < miny) miny = rg.bbox[1];
        if (rg.bbox[2] > maxx) maxx = rg.bbox[2];
        if (rg.bbox[3] > maxy) maxy = rg.bbox[3];
      }
      const idx = polyIndex.length;
      const poly = { code: f.properties.code, rings, bbox: [minx, miny, maxx, maxy] };
      polyIndex.push(poly);
      addPolyToBuckets(poly, idx);
    }
  });

  const cache = new Map();
  function contains(poly, x, y) {
    for (const rg of poly.rings) {
      const [minx, miny, maxx, maxy] = rg.bbox;
      if (x < minx || x > maxx || y < miny || y > maxy) continue;
      if (pointInRing(rg.flat, x, y)) return true;
    }
    return false;
  }
  function pointInRing(flat, x, y) {
    const n = flat.length / 2;
    let inside = false;
    for (let i = 0, j = n - 1; i < n; j = i++) {
      const xi = flat[i * 2], yi = flat[i * 2 + 1];
      const xj = flat[j * 2], yj = flat[j * 2 + 1];
      if (((yi > y) !== (yj > y)) && (x < ((xj - xi) * (y - yi)) / (yj - yi) + xi)) inside = !inside;
    }
    return inside;
  }

  function kecAt(lng, lat) {
    const k = bkey(Math.floor(lng / GRID_STEP), Math.floor(lat / GRID_STEP));
    const cands = buckets.get(k);
    if (!cands) return null;
    for (const i of cands) {
      const poly = polyIndex[i];
      const bb = poly.bbox;
      if (lng < bb[0] || lng > bb[2] || lat < bb[1] || lat > bb[3]) continue;
      if (contains(poly, lng, lat)) return poly.code;
    }
    return null;
  }

  return { kecAt, cache, count: polyIndex.length };
}

// ---------- streaming extraction ----------
function chunkBuffer() {
  return { size: 0, cap: 1 << 20, lngX: new Float64Array(1 << 20), latY: new Float64Array(1 << 20), wH: new Float32Array(1 << 20), hH: new Float32Array(1 << 20), dn: new Uint8Array(1 << 20) };
}
function bufPush(b, lng, lat, w, hh, dn) {
  if (b.size >= b.cap) {
    const cap = b.cap * 2;
    const g = (old, T) => { const a = new T(cap); a.set(old); return a; };
    b.lngX = g(b.lngX, Float64Array); b.latY = g(b.latY, Float64Array);
    b.wH = g(b.wH, Float32Array); b.hH = g(b.hH, Float32Array); b.dn = g(b.dn, Uint8Array);
    b.cap = cap;
  }
  b.lngX[b.size] = lng; b.latY[b.size] = lat; b.wH[b.size] = w; b.hH[b.size] = hh; b.dn[b.size] = dn; b.size++;
}

// pair kec codes: do second pass inside extract -> simpler: change extractYear to also collect kec string array
async function extractYearFull(file, kecs) {
  const b = chunkBuffer();
  b.kecArr = [];
  let unmatched = 0, total = 0;
  const stream = fs.createReadStream(file, { encoding: 'utf8' });
  const rl = readline.createInterface({ input: stream, crlfDelay: Infinity });
  const dnRe = /"DN":(\d+)/;
  for await (const line of rl) {
    if (!line.startsWith('{"type":"Feature"')) continue;
    const m = line.match(dnRe);
    if (!m) continue;
    const cIdx = line.indexOf('"coordinates":[');
    if (cIdx === -1) continue;
    let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    let numStart = -1, pairPos = 0, started = false, coordsDone = false;
    for (let i = cIdx + 15; i < line.length; i++) {
      const ch = line[i];
      const isNum = (ch >= '0' && ch <= '9') || ch === '-' || ch === '.' || ch === '+' || ch === 'e' || ch === 'E';
      if (isNum) { if (numStart === -1) numStart = i; }
      else if (numStart !== -1) {
        const val = +line.slice(numStart, i); numStart = -1;
        if (val === undefined || Number.isNaN(val)) { numStart = -1; continue; }
        if (pairPos === 0) { if (val < minx) minx = val; if (val > maxx) maxx = val; }
        else { if (val < miny) miny = val; if (val > maxy) maxy = val; }
        pairPos ^= 1;
        if (!started) started = true;
      } else if (ch === ']' && started) {
        // ring close; keep scanning until we hit the geometry's last ']' -> detect '}}}' pattern cheaply:
        if (line[i + 1] === ']' && line[i + 2] === '}' && line[i + 3] === '}' && line[i + 4] === '}') { coordsDone = true; break; }
      }
      if (coordsDone) break;
    }
    if (minx === Infinity || miny === Infinity) continue;
    const dn = +m[1];
    const cx = (minx + maxx) / 2, cy = (miny + maxy) / 2;
    const w = maxx - minx, hh = maxy - miny;
    if (!isFinite(cx) || !isFinite(cy) || w <= 0 || hh <= 0) continue;
    const kec = kecs.kecAt(cx, cy);
    if (!kec) unmatched++;
    b.kecArr.push(kec || '');
    bufPush(b, cx, cy, w, hh, dn);
    total++;
  }
  return { b, unmatched, total };
}

// ---------- aggregation ----------
function aggregate(b, year, targetCount) {
  // binary-search grid step h so key count ~= targetCount (within 20%)
  let lo = 0.001, hi = 2.0, h = (lo + hi) / 2;
  const keysAt = (h) => {
    const seen = new Set();
    for (let i = 0; i < b.size; i++) {
      seen.add(Math.floor(b.lngX[i] / h) + ',' + Math.floor(b.latY[i] / h));
    }
    return seen.size;
  };
  for (let iter = 0; iter < 30; iter++) {
    const mid = (lo + hi) / 2;
    const n = keysAt(mid);
    if (n > targetCount * 1.2) lo = mid; else hi = mid;
    h = mid;
    if (Math.abs(n - targetCount) / targetCount < 0.2 || hi - lo < 1e-4) break;
  }
  // final pass with chosen h
  const cells = new Map(); // key -> { dnTally: Map, rep:[area, cx, cy, kec] }
  const q = (v) => Math.floor(v / h);
  for (let i = 0; i < b.size; i++) {
    const key = q(b.lngX[i]) + ',' + q(b.latY[i]);
    let c = cells.get(key);
    if (!c) { c = { tally: null, rep: [0, 0, 0, ''] }; cells.set(key, c); }
    if (!c.tally) c.tally = new Map();
    c.tally.set(b.dn[i], (c.tally.get(b.dn[i]) || 0) + 1);
    const area = b.wH[i] * b.hH[i];
    if (area > c.rep[0]) c.rep = [area, b.lngX[i], b.latY[i], b.kecArr[i]];
  }
  // mode dn (tie -> higher dn)
  const mergedCells = new Map();
  for (const c of cells.values()) {
    let [ , cx, cy, kec] = c.rep;
    if (!kec) continue; // invisible on the map anyway (kecLookup miss)
    const lat = +cy.toFixed(5), lng = +cx.toFixed(5);
    const key = lat + ',' + lng;
    let merged = mergedCells.get(key);
    if (!merged) {
      merged = { lat, lng, kec, tally: new Map() };
      mergedCells.set(key, merged);
    } else if (merged.kec !== kec) {
      throw new Error(`aggregated coordinate ${key} maps to multiple kecamatan codes: ${merged.kec}, ${kec}`);
    }
    for (const [dn, n] of c.tally) {
      merged.tally.set(dn, (merged.tally.get(dn) || 0) + n);
    }
  }
  const out = [];
  for (const merged of mergedCells.values()) {
    let bestDn = 0, bestN = -1;
    for (const [dn, n] of merged.tally) {
      if (n > bestN || (n === bestN && dn > bestDn)) { bestDn = dn; bestN = n; }
    }
    out.push([merged.lat, merged.lng, bestDn, year, merged.kec]);
  }
  return { pts: out, h, keys: cells.size };
}

function deduplicateExistingPoints(points) {
  const groups = new Map();
  for (const point of points) {
    const key = point[3] + '|' + point[0] + '|' + point[1];
    let group = groups.get(key);
    if (!group) {
      group = { lat: point[0], lng: point[1], year: point[3], kec: point[4], tally: new Map() };
      groups.set(key, group);
    } else if (group.kec !== point[4]) {
      throw new Error(`duplicate coordinate has conflicting kecamatan codes: ${key}`);
    }
    group.tally.set(point[2], (group.tally.get(point[2]) || 0) + 1);
  }
  const deduplicated = [];
  for (const group of groups.values()) {
    let bestDn = 0, bestN = -1;
    for (const [dn, count] of group.tally) {
      if (count > bestN || (count === bestN && dn > bestDn)) { bestDn = dn; bestN = count; }
    }
    deduplicated.push([group.lat, group.lng, bestDn, group.year, group.kec]);
  }
  return deduplicated;
}

// ---------- main ----------
(async () => {
  if (process.argv[2] === '--dedupe-existing') {
    const outFile = path.join(DATA_DIR, 'fires.json');
    const existing = JSON.parse(fs.readFileSync(outFile, 'utf8'));
    const points = deduplicateExistingPoints(existing.pts);
    fs.writeFileSync(outFile, JSON.stringify({ type: existing.type, pts: points }));
    console.log(`deduplicated ${existing.pts.length.toLocaleString()} points to ${points.length.toLocaleString()} in ${outFile}`);
    process.exit(0);
  }

  const years = process.argv[2] ? [parseInt(process.argv[2], 10)] : YEARS;
  console.time('kec-index');
  const kecs = loadKecIndex();
  console.timeEnd('kec-index');
  console.log('kecamatan polygons indexed:', kecs.count);

  const all = [];
  const report = [];
  let unmatchedTotal = 0, cellsTotal = 0;
  for (const year of years) {
    const file = path.join(SRC_DIR, `data_sumatera_${year}.geojson`);
    console.log(`\n=== ${year} ===`);
    console.time(`extract ${year}`);
    const { b, unmatched, total } = await extractYearFull(file, kecs);
    console.timeEnd(`extract ${year}`);
    console.time(`aggregate ${year}`);
    const { pts, h, keys } = aggregate(b, year, TARGET_COUNTS[year]);
    console.timeEnd(`aggregate ${year}`);
    console.log(`cells=${total} unmatched=${unmatched} aggStep=${h.toFixed(5)} outPts=${pts.length}`);
    report.push({ year, cells: total, unmatched, step: +h.toFixed(6), out: pts.length });
    all.push(...pts);
    cellsTotal += total; unmatchedTotal += unmatched;
  }

  const outFile = process.argv[2] ? path.join('tmp', `fires_smoke_${process.argv[2]}.json`) : path.join(DATA_DIR, 'fires.json');
  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  fs.writeFileSync(outFile, JSON.stringify({ type: 'fires', pts: all }));
  console.log('\n=== DONE ===');
  console.log('total cells:', cellsTotal, '| unmatched kec:', unmatchedTotal, `(${((unmatchedTotal / cellsTotal) * 100).toFixed(2)}%)`);
  console.log('output points:', all.length, '->', outFile, `(${(fs.statSync(outFile).size / 1048576).toFixed(1)} MB)`);
  console.log('report:'); console.table(report);
})();
