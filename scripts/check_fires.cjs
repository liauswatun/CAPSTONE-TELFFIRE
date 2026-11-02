const d = JSON.parse(require('fs').readFileSync(process.argv[2], 'utf8'));
console.log('type:', d.type, '| total pts:', d.pts.length);
const kec = {};
for (const p of d.pts) kec[p[4]] = (kec[p[4]] || 0) + 1;
const codes = Object.keys(kec);
console.log('unique kec used:', codes.length, '| empty-kec pts:', kec[''] || 0);
console.log('first pts:', JSON.stringify(d.pts.slice(0, 3)));
console.log('dn dist:', JSON.stringify(d.pts.reduce((a, p) => { a[p[2]] = (a[p[2]] || 0) + 1; return a; }, {})));
// check all kec codes exist in map kecamatan file + province distribution
const mapKec = JSON.parse(require('fs').readFileSync('public/data/sumatera_kecamatan.geojson', 'utf8'));
const valid = new Set(mapKec.features.map(f => f.properties.code));
let invalid = 0;
const prov = {};
for (const p of d.pts) {
  if (!valid.has(p[4])) invalid++;
  const info = mapKec.features.find(f => f.properties.code === p[4]);
  if (info) { const pr = info.properties.province; prov[pr] = (prov[pr] || 0) + 1; }
}
console.log('kecCodes NOT matching map:', invalid);
console.log('per province:', JSON.stringify(prov, null, 1));
