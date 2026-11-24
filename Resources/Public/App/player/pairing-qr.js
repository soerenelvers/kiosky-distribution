/* Pairing QR renderer. Encoder kept byte-identical to app.js by parity tests. */
(() => {
const escapeHtml=value=>String(value).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;').replaceAll('>','&gt;');
const qrMarkupCache=new Map();let qrMarkupCacheSize=0;
function qrSvgMarkup(value, foreground = '#111111', background = '#ffffff') {
  const bytes = [...new TextEncoder().encode(String(value || ''))];
  const profiles=[{version:8,lengths:[97,97],ecc:24,align:[6,24,42]}, {version:12,lengths:[92,92,93,93],ecc:24,align:[6,32,58]}, {version:20,lengths:[107,107,107,108,108,108,108,108],ecc:28,align:[6,34,62,90]}, {version:30,lengths:[...Array(5).fill(115),...Array(10).fill(116)],ecc:30,align:[6,26,52,78,104,130]}, {version:40,lengths:[...Array(19).fill(118),...Array(6).fill(119)],ecc:30,align:[6,30,58,86,114,142,170]}];
  const profile=profiles.find(p=>bytes.length<=p.lengths.reduce((a,b)=>a+b,0)-(p.version<10?2:3));
  const key=JSON.stringify([value,foreground,background]);if(qrMarkupCache.has(key))return qrMarkupCache.get(key);
  if (!bytes.length) return '<span class="qr-placeholder">QR<small>Inhalt eintragen</small></span>';
  if (!profile) return '<span class="qr-placeholder">QR<small>Inhalt ist zu lang</small></span>';

  const {version,lengths,ecc:eccLength,align}=profile,size=version*4+17,dataCodewords=lengths.reduce((a,b)=>a+b,0);
  const bits = [];
  const appendBits = (number, length) => { for (let index = length - 1; index >= 0; index -= 1) bits.push((number >>> index) & 1); };
  appendBits(4, 4);
  appendBits(bytes.length, version<10?8:16);
  bytes.forEach(byte => appendBits(byte, 8));
  appendBits(0, Math.min(4, dataCodewords * 8 - bits.length));
  while (bits.length % 8) bits.push(0);
  const data = [];
  for (let offset = 0; offset < bits.length; offset += 8) data.push(bits.slice(offset, offset + 8).reduce((sum, bit) => (sum << 1) | bit, 0));
  for (let pad = 0; data.length < dataCodewords; pad += 1) data.push(pad % 2 ? 0x11 : 0xec);

  const exp = new Array(512), log = new Array(256);
  let number = 1;
  for (let index = 0; index < 255; index += 1) {
    exp[index] = number; log[number] = index; number <<= 1;
    if (number & 0x100) number ^= 0x11d;
  }
  for (let index = 255; index < 512; index += 1) exp[index] = exp[index - 255];
  const multiply = (left, right) => left && right ? exp[log[left] + log[right]] : 0;
  let generator = [1];
  for (let degree = 0; degree < eccLength; degree += 1) {
    const next = new Array(generator.length + 1).fill(0);
    generator.forEach((coefficient, index) => {
      next[index] ^= coefficient;
      next[index + 1] ^= multiply(coefficient, exp[degree]);
    });
    generator = next;
  }
  let blockOffset=0;const blocks=lengths.map(length=>{const block=data.slice(blockOffset,blockOffset+length);blockOffset+=length;return block;});
  const eccBlocks = blocks.map(block => {
    const remainder = new Array(eccLength).fill(0);
    block.forEach(byte => {
      const factor = byte ^ remainder[0];
      remainder.shift(); remainder.push(0);
      for (let index = 0; index < eccLength; index += 1) remainder[index] ^= multiply(generator[index + 1], factor);
    });
    return remainder;
  });
  const codewords = [];
  for (let index=0;index<Math.max(...lengths);index+=1)blocks.forEach(block=>{if(index<block.length)codewords.push(block[index]);});
  for (let index = 0; index < eccLength; index += 1) eccBlocks.forEach(block => codewords.push(block[index]));

  const modules = Array.from({ length: size }, () => new Array(size).fill(null));
  const finder = (row, column) => {
    for (let dy = -1; dy <= 7; dy += 1) for (let dx = -1; dx <= 7; dx += 1) {
      const y = row + dy, x = column + dx;
      if (y < 0 || y >= size || x < 0 || x >= size) continue;
      modules[y][x] = dy >= 0 && dy <= 6 && dx >= 0 && dx <= 6 && (dy === 0 || dy === 6 || dx === 0 || dx === 6 || (dy >= 2 && dy <= 4 && dx >= 2 && dx <= 4));
    }
  };
  finder(0, 0); finder(size - 7, 0); finder(0, size - 7);
  align.forEach(row => align.forEach(column => {
    if (modules[row][column] !== null) return;
    for (let dy = -2; dy <= 2; dy += 1) for (let dx = -2; dx <= 2; dx += 1) modules[row + dy][column + dx] = Math.max(Math.abs(dx), Math.abs(dy)) === 2 || (!dx && !dy);
  }));
  for (let index = 8; index < size - 8; index += 1) {
    if (modules[index][6] === null) modules[index][6] = index % 2 === 0;
    if (modules[6][index] === null) modules[6][index] = index % 2 === 0;
  }
  const bch = (value, polynomial) => {
    const bitLength = input => { let length = 0; for (let current = input; current; current >>>= 1) length += 1; return length; };
    let result = value << (bitLength(polynomial) - 1);
    while (bitLength(result) >= bitLength(polynomial)) result ^= polynomial << (bitLength(result) - bitLength(polynomial));
    return (value << (bitLength(polynomial) - 1)) | result;
  };
  const versionBits = bch(version, 0x1f25);
  for (let index = 0; index < 18; index += 1) {
    const dark = ((versionBits >>> index) & 1) === 1;
    modules[Math.floor(index / 3)][index % 3 + size - 11] = dark;
    modules[index % 3 + size - 11][Math.floor(index / 3)] = dark;
  }
  const formatBits = bch((1 << 3), 0x537) ^ 0x5412;
  for (let index = 0; index < 15; index += 1) {
    const dark = ((formatBits >>> index) & 1) === 1;
    modules[index < 6 ? index : index < 8 ? index + 1 : size - 15 + index][8] = dark;
    modules[8][index < 8 ? size - index - 1 : index < 9 ? 7 : 15 - index - 1] = dark;
  }
  modules[size - 8][8] = true;
  let byteIndex = 0, bitIndex = 7, upward = true;
  for (let right = size - 1; right > 0; right -= 2) {
    if (right === 6) right -= 1;
    for (let vertical = 0; vertical < size; vertical += 1) {
      const row = upward ? size - 1 - vertical : vertical;
      for (let offset = 0; offset < 2; offset += 1) {
        const column = right - offset;
        if (modules[row][column] !== null) continue;
        const dataBit = byteIndex < codewords.length ? ((codewords[byteIndex] >>> bitIndex) & 1) === 1 : false;
        modules[row][column] = dataBit !== ((row + column) % 2 === 0);
        bitIndex -= 1;
        if (bitIndex < 0) { byteIndex += 1; bitIndex = 7; }
      }
    }
    upward = !upward;
  }
  const quiet = 4, viewSize = size + quiet * 2;
  const paths = [];
  modules.forEach((row, y) => row.forEach((dark, x) => { if (dark) paths.push(`M${x + quiet} ${y + quiet}h1v1h-1z`); }));
  const markup=`<svg class="generated-qr-code" viewBox="0 0 ${viewSize} ${viewSize}" role="img" aria-label="QR-Code" shape-rendering="crispEdges"><path fill="${escapeHtml(background)}" d="M0 0h${viewSize}v${viewSize}H0z"/><path fill="${escapeHtml(foreground)}" d="${paths.join('')}"/></svg>`;
  while(qrMarkupCache.size&&(qrMarkupCache.size>=24||qrMarkupCacheSize+markup.length>1_000_000)){const key=qrMarkupCache.keys().next().value;qrMarkupCacheSize-=qrMarkupCache.get(key).length;qrMarkupCache.delete(key);}
  qrMarkupCache.set(key,markup);qrMarkupCacheSize+=markup.length;return markup;
}


window.KioskyPairingQr={markup:code=>/^[23456789A-HJ-NP-Z]{6}$/.test(String(code))?qrSvgMarkup('kiosky-pair:'+code):'',parse:value=>{const match=/^(?:kiosky-pair:)?([23456789A-HJ-NP-Z]{6})$/.exec(String(value||'').trim().toUpperCase().replace(/^KIOSKY-PAIR:/,'kiosky-pair:'));return match?match[1]:null;}};
})();
