(() => {
const kioskyRuntime = Object.freeze({
  platform: 'standalone',
  apiBaseUrl: '',
  apiEndpoint: '',
  assetBaseUrl: '',
  playerBaseUrl: '',
  requestHeaders: {},
  ...(window.KioskyRuntime || {})
});
const runtimeUrl = (base, path) => {
  if (!base) return path;
  return `${String(base).replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
};
const apiUrl = path => {
  if (!kioskyRuntime.apiEndpoint) return runtimeUrl(kioskyRuntime.apiBaseUrl, path);
  const endpoint = new URL(kioskyRuntime.apiEndpoint, window.location.href);
  const requested = new URL(path, window.location.origin);
  endpoint.searchParams.set('path', requested.pathname);
  requested.searchParams.forEach((value, key) => endpoint.searchParams.append(key, value));
  return endpoint.toString();
};
const assetUrl = path => runtimeUrl(kioskyRuntime.assetBaseUrl, path);

function qrElementPayload(element = {}) {
  const escapeWifi = value => String(value || '').replaceAll('\\', '\\\\').replaceAll(';', '\\;').replaceAll(',', '\\,').replaceAll(':', '\\:').replaceAll('"', '\\"');
  if (element.qrKind === 'wifi') {
    const encryption = element.wifiEncryption === 'WEP' ? 'WEP' : element.wifiEncryption === 'nopass' ? 'nopass' : 'WPA';
    return `WIFI:T:${encryption};S:${escapeWifi(element.wifiSsid)};${encryption === 'nopass' ? '' : `P:${escapeWifi(element.wifiPassword)};`}H:${element.wifiHidden ? 'true' : 'false'};;`;
  }
  if (element.qrKind === 'email') return `mailto:${String(element.qrValue || '').trim()}`;
  if (element.qrKind === 'phone') return `tel:${String(element.qrValue || '').replace(/[^\d+*#,;]/g, '')}`;
  return String(element.qrValue || '').trim();
}

function qrSvgMarkup(value, foreground = '#111111', background = '#ffffff') {
  const bytes = [...new TextEncoder().encode(String(value || ''))];
  const version = 8, size = 49, dataCodewords = 194, blockDataLength = 97, eccLength = 24;
  if (!bytes.length) return '<span class="qr-placeholder">QR<small>Inhalt eintragen</small></span>';
  if (bytes.length > 192) return '<span class="qr-placeholder">QR<small>Inhalt ist zu lang</small></span>';

  const bits = [];
  const appendBits = (number, length) => { for (let index = length - 1; index >= 0; index -= 1) bits.push((number >>> index) & 1); };
  appendBits(4, 4);
  appendBits(bytes.length, 8);
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
  const blocks = [data.slice(0, blockDataLength), data.slice(blockDataLength)];
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
  for (let index = 0; index < blockDataLength; index += 1) blocks.forEach(block => codewords.push(block[index]));
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
  [6, 24, 42].forEach(row => [6, 24, 42].forEach(column => {
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
  return `<svg class="generated-qr-code" viewBox="0 0 ${viewSize} ${viewSize}" role="img" aria-label="QR-Code" shape-rendering="crispEdges"><path fill="${escapeHtml(background)}" d="M0 0h${viewSize}v${viewSize}H0z"/><path fill="${escapeHtml(foreground)}" d="${paths.join('')}"/></svg>`;
}

function countdownSeconds(element, now = new Date()) {
  if (element.countdownMode === 'daily') {
    const [hours, minutes, seconds = 0] = String(element.countdownDailyTime || '18:00').split(':').map(Number);
    const target = new Date(now);
    target.setHours(Number.isFinite(hours) ? hours : 18, Number.isFinite(minutes) ? minutes : 0, Number.isFinite(seconds) ? seconds : 0, 0);
    if (target.getTime() < now.getTime()) target.setDate(target.getDate() + 1);
    return Math.max(0, Math.floor((target.getTime() - now.getTime()) / 1000));
  }
  const timestamp = Date.parse(element.targetTime || '');
  return Number.isFinite(timestamp) ? Math.max(0, Math.floor((timestamp - now.getTime()) / 1000)) : NaN;
}

function countdownText(element, now = new Date()) {
  const total = countdownSeconds(element, now);
  const showSeconds = element.showSeconds !== false;
  if (!Number.isFinite(total)) return showSeconds ? '00:00:00' : '00:00';
  const days = Math.floor(total / 86400), hours = Math.floor(total % 86400 / 3600), minutes = Math.floor(total % 3600 / 60), seconds = total % 60;
  return `${days ? `${days} T · ` : ''}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}${showSeconds ? `:${String(seconds).padStart(2, '0')}` : ''}`;
}

function countdownFontStyle(element) {
  const size = Math.max(12, Math.min(160, Number(element.fontSize) || 68));
  return `font-size:clamp(12px,${Number((size / 17).toFixed(3))}vw,${size}px)`;
}

function tickerElementItems(element = {}) {
  if (Array.isArray(element.items) && element.items.length) return element.items;
  return [{ id: 'legacy-text', kind: 'text', text: element.text || 'Aktuelle Informationen' }];
}

function tickerItemsMarkup(element = {}) {
  return tickerElementItems(element).map(item => {
    if (item.kind === 'countdown') {
      return `<span class="ticker-part ticker-part-countdown" data-ticker-countdown="${escapeHtml(encodeURIComponent(JSON.stringify(item)))}">${escapeHtml(item.prefix || '')}${countdownText(item)}${escapeHtml(item.suffix || '')}</span>`;
    }
    if (item.kind === 'qr-code') {
      return `<span class="ticker-part ticker-part-qr">${qrSvgMarkup(qrElementPayload(item), item.qrForeground || '#111111', item.qrBackground || '#ffffff')}</span>`;
    }
    if (item.kind === 'icon') return `<span class="ticker-part ticker-part-icon" aria-label="${escapeHtml(item.label || 'Icon')}">${escapeHtml(item.icon || '★')}</span>`;
    return `<span class="ticker-part ticker-part-text">${escapeHtml(item.text || '')}</span>`;
  }).join('');
}

const currentWeatherCache = new Map();
function weatherIcon(icon) {
  return ({
    'clear-day': '☀️',
    'clear-night': '🌙',
    'partly-cloudy-day': '🌤️',
    'partly-cloudy-night': '☁️',
    cloudy: '☁️',
    fog: '🌫️',
    wind: '💨',
    rain: '🌧️',
    sleet: '🌨️',
    snow: '❄️',
    hail: '🌨️',
    thunderstorm: '⛈️'
  })[icon] || '◌';
}
function weatherElementMarkup(element = {}) {
  const latitude = element.latitude === '' || element.latitude == null ? NaN : Number(element.latitude);
  const longitude = element.longitude === '' || element.longitude == null ? NaN : Number(element.longitude);
  const hasDisplayLocation = element.weatherSource === 'display' && Boolean(element.locationId);
  if (!hasDisplayLocation) return '<div class="slide-weather"><span class="weather-loading">Bitte Standort wählen</span></div>';
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return '<div class="slide-weather"><span class="weather-loading">Standortadresse benötigt Koordinaten</span></div>';
  return `<div class="slide-weather weather-mini-widget" data-weather-latitude="${latitude}" data-weather-longitude="${longitude}" role="status" aria-label="Aktuelles Wetter"><span class="weather-mini-icon" aria-hidden="true">◌</span><strong class="weather-mini-temperature">--°</strong></div>`;
}
async function hydrateWeatherElements(root = document) {
  const widgets = [...root.querySelectorAll('[data-weather-latitude][data-weather-longitude]')];
  await Promise.all(widgets.map(async widget => {
    const latitude = Number(widget.dataset.weatherLatitude);
    const longitude = Number(widget.dataset.weatherLongitude);
    const cacheKey = `${latitude.toFixed(4)},${longitude.toFixed(4)}`;
    let weather = currentWeatherCache.get(cacheKey);
    try {
      if (!weather || weather.expiresAt <= Date.now()) {
        const response = await fetch(apiUrl(`/api/weather/current?lat=${encodeURIComponent(latitude)}&lon=${encodeURIComponent(longitude)}`), { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Wetterdaten nicht verfügbar');
        weather = { ...(await response.json()), expiresAt: Date.now() + 10 * 60 * 1000 };
        if (currentWeatherCache.size >= 100) currentWeatherCache.delete(currentWeatherCache.keys().next().value);
        currentWeatherCache.set(cacheKey, weather);
      }
      widget.querySelector('.weather-mini-icon').textContent = weatherIcon(weather.icon);
      widget.querySelector('.weather-mini-temperature').textContent = `${Math.round(Number(weather.temperature))}°`;
      widget.setAttribute('aria-label', `${Math.round(Number(weather.temperature))} Grad, ${weather.icon}`);
    } catch {
      widget.querySelector('.weather-mini-icon').textContent = '◌';
      widget.querySelector('.weather-mini-temperature').textContent = '–°';
      widget.setAttribute('aria-label', 'Wetterdaten nicht verfügbar');
    }
  }));
}
const weatherElementObserver = new MutationObserver(records => {
  records.forEach(record => hydrateWeatherElements(record.target));
});
weatherElementObserver.observe(document.documentElement, { childList: true, subtree: true });

function eventFieldDisplayValue(event, field, options={}) {
  const value = event?.[field];
  if (!value) return '';
  if (['setupStart', 'admissionStart', 'eventStart', 'breakStart', 'breakEnd', 'eventEnd', 'boxOfficeOpenAt'].includes(field)) {
    const date=new Date(value);
    if(!Number.isNaN(date.valueOf()))return new Intl.DateTimeFormat('de-DE',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit',...(options.showSeconds?{second:'2-digit'}:{}),hour12:Boolean(options.hour12)}).format(date);
    return eventTimeInput(value) || String(value);
  }
  if (field === 'date') {
    const digits = String(value).replace(/\D/g, '').slice(0, 8);
    return digits.length === 8 ? `${digits.slice(6, 8)}.${digits.slice(4, 6)}.${digits.slice(0, 4)}` : String(value);
  }
  return String(value);
}

function eventFieldPresentation(event,field,options={}){
  const value=eventFieldDisplayValue(event,field,options),present=value!=='';
  return{value,present,text:`${options.prefix||''}${present?value:options.fallback||'–'}${options.suffix||''}`};
}
function hiddenEventValueAttributes(present){return present?'':'data-event-value-missing="true" aria-hidden="true"';}

function eventWelcomeBlockMarkup(event, fallbackTitle = 'Veranstaltung',options={}) {
  const time = field => eventFieldDisplayValue(event, field,options);
  const pauseStart = time('breakStart'), pauseEnd = time('breakEnd');
  const pause = pauseStart && pauseEnd && pauseStart !== pauseEnd ? `${pauseStart}–${pauseEnd}` : pauseStart || pauseEnd || '–';
  const schedule = [
    ['Einlass', time('admissionStart') || '–'],
    ['Beginn', time('eventStart') || '–'],
    ['Pause', pause],
    ['Ende', time('eventEnd') || '–']
  ].map(([label, value]) => `<b>${label}:</b> ${escapeHtml(value)}`).join(' · ');
  const date=options.showDate?eventFieldDisplayValue(event,'date'):'';
  return `<small>Herzlich willkommen zu</small><strong>${escapeHtml(event?.title || fallbackTitle)}</strong>${date?`<em>${escapeHtml(date)}</em>`:''}${options.showTime===false?'':`<span>${schedule}</span>`}`;
}

function fitAutomaticText(root=document){
  root.querySelectorAll?.('[data-auto-font="true"]').forEach(node=>{
    const outer=node.classList.contains('canvas-element')||node.classList.contains('player-layout-element');
    const container=outer?node:node.parentElement;
    const target=outer?(node.querySelector('.canvas-text-content,.canvas-event-content,.canvas-countdown,.weather-mini-widget,.player-countdown span')||node):node;
    if(!container||!target||container.clientWidth<2||container.clientHeight<2)return;
    const maximum=Math.max(6,Math.min(240,Number(node.dataset.autoFontMax||getComputedStyle(node).fontSize.replace('px','')||72)));
    const ticker=node.classList.contains('type-ticker')||Boolean(node.querySelector('.player-slide-ticker,.canvas-ticker'));
    const apply=size=>{if(outer)node.style.fontSize=`${size}px`;else node.style.fontSize=`${size}px`;};
    const fits=()=>target.scrollHeight<=container.clientHeight+1&&(ticker||target.scrollWidth<=container.clientWidth+1);
    let low=6,high=maximum,best=6;
    for(let iteration=0;iteration<10&&high-low>.25;iteration+=1){const middle=(low+high)/2;apply(middle);if(fits()){best=middle;low=middle;}else high=middle;}
    apply(best);
  });
}
function scheduleAutomaticTextFit(root=document){requestAnimationFrame(()=>requestAnimationFrame(()=>fitAutomaticText(root)));document.fonts?.ready?.then(()=>fitAutomaticText(root)).catch?.(()=>{});}

async function renderDisplayPlayer(slug) {
  document.body.className = 'player-mode';
  let wakeLock=null;
  const keepDisplayAwake=async()=>{try{if('wakeLock'in navigator&&!wakeLock)wakeLock=await navigator.wakeLock.request('screen');}catch{/* Der Kiosk-Browser oder das Betriebssystem übernimmt die Energiesperre. */}};
  keepDisplayAwake();
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')keepDisplayAwake();});
  const playerDevice=()=>'Web Player';
  const key=new URLSearchParams(window.location.search).get('key')||''; const cacheKey=`kiosky-player-cache-${slug}`; let playerState=null,index=0,timer=null,currentItem=null,currentMatrixIndex=-1,renderedOrientation=null,resizeTimer=null,identificationTimer=null,commandPollRunning=false,serverClockOffsetMs=0,playbackSyncKey='';
  const headers={'X-Player-Key':key,'Content-Type':'application/json','Accept':'application/json',...(kioskyRuntime.playerRequestHeaders||{})};
  const fetchState=async()=>{try{const requestStartedAt=Date.now(),response=await fetch(apiUrl(`/api/player/${encodeURIComponent(slug)}/state`),{headers}),responseReceivedAt=Date.now();if(!response.ok)throw new Error('Player nicht gefunden');playerState=await response.json();const serverTimeMs=Number(playerState.playbackSync?.serverTimeMs||Date.parse(playerState.serverTime));if(Number.isFinite(serverTimeMs))serverClockOffsetMs=serverTimeMs-(requestStartedAt+responseReceivedAt)/2;playerState.cachedClockOffsetMs=serverClockOffsetMs;localStorage.setItem(cacheKey,JSON.stringify(playerState));document.querySelector('#player-offline-indicator')?.setAttribute('hidden','');return playerState;}catch(error){try{playerState=JSON.parse(localStorage.getItem(cacheKey));serverClockOffsetMs=Number(playerState.cachedClockOffsetMs||0);document.querySelector('#player-offline-indicator')?.removeAttribute('hidden');return playerState;}catch{return null;}}};
  const arrowGlyph=direction=>({up:'↑',down:'↓',left:'←',right:'→','up-left':'↖','up-right':'↗','down-left':'↙','down-right':'↘','stairs-up-left':'▰↖','stairs-up-right':'▰↗','stairs-down-left':'▰↙','stairs-down-right':'▰↘'}[direction]||'→');
  const playerCountdown=target=>{const seconds=Math.max(0,Math.floor((Date.parse(target||'')-Date.now())/1000));if(!Number.isFinite(seconds))return'00:00:00';const days=Math.floor(seconds/86400),hours=Math.floor(seconds%86400/3600),minutes=Math.floor(seconds%3600/60),rest=seconds%60;return`${days?`${days} T · `:''}${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(rest).padStart(2,'0')}`;};
  const playerContrastColor=settings=>{const colors=[settings?.background?.color1||'#17342b',settings?.background?.mode==='gradient'?(settings?.background?.color2||'#315b49'):null].filter(Boolean),values=colors.map(hex=>{const clean=hex.replace('#',''),rgb=[0,2,4].map(index=>parseInt(clean.slice(index,index+2),16)||0);return rgb.reduce((sum,value,index)=>sum+value*[.2126,.7152,.0722][index],0);});return values.reduce((a,b)=>a+b,0)/values.length>145?'#111111':'#ffffff';};
  const playerRowColor=(row,settings)=>row?.colorMode==='custom'?(row.color||'#ffffff'):playerContrastColor(settings);
  const wayfindingCell=(cell,event,color)=>{
    if(!cell||cell.kind==='empty')return '';
    const colorStyle=`color:${color}`,auto=cell.autoFontSize?'data-auto-font="true" data-auto-font-max="240"':'';
    if(cell.kind==='image')return cell.src?`<img class="wf-cropped-image" src="${cell.src}" alt="" style="object-fit:${cell.imageFit||'cover'};object-position:${Number(cell.imageX??50)}% ${Number(cell.imageY??50)}%;transform:scale(${Number(cell.imageZoom||100)/100})">`:'';
    if(cell.kind==='arrow')return `<span class="wf-arrow-image" style="${colorStyle};--arrow-mask:url('${assetUrl(`/media/arrows/${cell.direction||'right'}.svg`)}')" role="img" aria-label="${arrowGlyph(cell.direction)}"></span>`;
    if(cell.kind==='iframe')return cell.src?`<iframe src="${cell.src}" title="Eingebettete Website"></iframe>`:'';
    if(cell.kind==='countdown'){const target=cell.targetSource==='event'?event?.[cell.eventField||'eventStart']:cell.targetTime,present=cell.targetSource!=='event'||Boolean(target),countdown=cell.targetSource==='daily'?{countdownMode:'daily',countdownDailyTime:cell.dailyTime||'12:00',prefix:cell.prefix||'',suffix:cell.suffix||'',showSeconds:cell.showSeconds!==false}:{countdownMode:'datetime',targetTime:target||'',prefix:cell.prefix||'',suffix:cell.suffix||'',showSeconds:cell.showSeconds!==false};return `<span class="wf-countdown" ${auto} ${hiddenEventValueAttributes(present)} style="${colorStyle};${countdownFontStyle(cell)}" data-wayfinding-countdown="${escapeHtml(encodeURIComponent(JSON.stringify(countdown)))}">${escapeHtml(countdown.prefix)}${countdownText(countdown)}${escapeHtml(countdown.suffix)}</span>`;}
    if(cell.kind==='event-field'){const presentation=eventFieldPresentation(event,cell.eventField,cell);return `<span class="wf-text" ${auto} ${hiddenEventValueAttributes(presentation.present)} style="${colorStyle};font-size:${Math.max(6,Math.min(240,Number(cell.fontSize)||42))}px">${escapeHtml(presentation.text)}</span>`;}
    const styles={hero:'clamp(30px,5vw,82px)',heading:'clamp(24px,4vw,64px)',subheading:'clamp(20px,3vw,48px)',body:'clamp(16px,2.2vw,34px)',subtitle:'clamp(14px,1.8vw,28px)',label:'clamp(12px,1.4vw,22px)',note:'clamp(10px,1.1vw,18px)'};
    return `<span class="wf-text ${cell.underline?'is-underlined':''}" ${auto} style="${colorStyle};font-size:${styles[cell.textStyle]||styles.body};text-align:${cell.align||'left'};font-weight:${cell.bold||['hero','heading'].includes(cell.textStyle)?700:400}">${cell.text||''}</span>`;
  };
  const wayfindingMarkup=(element,event,settings)=>{const rows=(element.rows||[]).map((row,index)=>{const rowColor=playerRowColor(row,settings),left=wayfindingCell(row.left,event,rowColor),center=wayfindingCell(row.center,event,rowColor),right=wayfindingCell(row.right,event,rowColor),centerClass=!left&&!right&&row.expandCenter?' center-full':left&&!right?' center-to-right':'';const cells=centerClass===' center-full'?`<div class="wayfinding-cell is-center center-full">${center}</div>`:`${left?`<div class="wayfinding-cell">${left}</div>`:'<div></div>'}<div class="wayfinding-cell is-center${centerClass}">${center}</div>${right&&!centerClass?`<div class="wayfinding-cell">${right}</div>`:!centerClass?'<div></div>':''}`;return `<div class="wayfinding-row ${index&&row.separator?'has-separator':''}" style="color:${rowColor}">${cells}</div>`;}).join('');const logo=element.logo?.src?`<div class="wayfinding-overlay ${element.logo.position||'top-left'} overlay-${element.logo.theme||'none'}"><img src="${element.logo.src}" alt="Logo"></div>`:'';const clock=element.clock?.enabled?`<div class="wayfinding-overlay wayfinding-clock ${element.clock.position||'bottom-right'} is-readable">${new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'})}</div>`:'';return `<div class="wayfinding-canvas" style="--wayfinding-bg:${element.background||'#17342b'}">${rows}${logo}${clock}</div>`;};
  const elementMarkup=(element,event,settings)=>{const style=`left:${Number(element.x||0)}%;top:${Number(element.y||0)}%;width:${Number(element.width||40)}%;height:${Number(element.height||20)}%;background:${element.background||'transparent'};color:${element.color||'#fff'};font-size:${Number(element.fontSize||32)}px;text-align:${element.align||'left'};transform:rotate(${Number(element.rotation||0)}deg);opacity:${Number(element.opacity??100)/100};padding:${Number(element.padding||0)}px;border-radius:${Number(element.radius||0)}px;font-weight:${Number(element.fontWeight||400)};line-height:${Number(element.lineHeight||1.08)};letter-spacing:${Number(element.letterSpacing||0)}px`;if(element.type==='wayfinding')return `<div class="player-layout-element" style="${style}">${wayfindingMarkup(element,event,settings)}</div>`;if(element.type==='ticker'){const duration=Math.max(3,Math.min(60,Number(element.tickerDuration||15))),direction=element.tickerDirection==='right'?'is-right':'';return`<div class="player-layout-element" style="${style}"><div class="player-slide-ticker ${direction}" style="--ticker-duration:${duration}s"><div class="ticker-track">${tickerItemsMarkup(element)}</div></div></div>`;}if(element.type==='weather')return`<div class="player-layout-element" style="${style}">${weatherElementMarkup(element)}</div>`;if(element.type==='qr-code')return`<div class="player-layout-element player-qr-code" style="${style}">${qrSvgMarkup(qrElementPayload(element),element.qrForeground,element.background)}</div>`;if(element.type==='countdown')return`<div class="player-layout-element player-countdown" style="${style}" data-slide-countdown data-countdown-element="${escapeHtml(encodeURIComponent(JSON.stringify(element)))}"><span>${escapeHtml(element.prefix||'')}${countdownText(element)}${escapeHtml(element.suffix||'')}</span></div>`;if(element.type==='image'){const src=element.src||event?.imageUrl||'',present=Boolean(element.src||event?.imageUrl);return `<div class="player-layout-element" ${hiddenEventValueAttributes(present)} style="${style}"><img src="${src}" alt=""></div>`;}if(element.type==='video')return `<div class="player-layout-element" style="${style}"><video src="${element.src||''}" autoplay loop ${element.muted!==false?'muted':''} data-trim-start="${Number(element.trimStart||0)}" data-trim-end="${Number(element.trimEnd||0)}"></video></div>`;if(element.type==='web')return `<div class="player-layout-element" style="${style}"><iframe src="${element.src||''}" title="Externer Inhalt"></iframe></div>`;if(element.type==='event-field'){const presentation=eventFieldPresentation(event,element.field,element);return `<div class="player-layout-element player-dynamic-field" ${hiddenEventValueAttributes(presentation.present)} style="${style}">${escapeHtml(presentation.text)}</div>`;}if(element.type==='event')return `<div class="player-layout-element player-event-block" ${hiddenEventValueAttributes(Boolean(event))} style="${style}">${eventWelcomeBlockMarkup(event,element.text||'Veranstaltung',element)}</div>`;return `<div class="player-layout-element" style="${style}">${escapeHtml(element.text||'')}</div>`;};
  const playerSettingsMarkup=settings=>{if(!settings)return'';const background=settings.background||{},backgroundStyle=background.mode==='image'&&background.imageSrc?`background-image:url('${background.imageSrc}');background-size:${Number(background.imageZoom||100)}% auto;background-position:${Number(background.imageX??50)}% ${Number(background.imageY??50)}%;background-repeat:no-repeat;background-color:${background.color1||'#17342b'}`:background.mode==='gradient'?`background:linear-gradient(${background.direction||'135deg'},${background.color1||'#17342b'},${background.color2||'#315b49'})`:`background:${background.color1||'#17342b'}`,logoSettings=settings.logo||{},naturalWidth=Math.max(1,Number(logoSettings.naturalWidth||400)),naturalHeight=Math.max(1,Number(logoSettings.naturalHeight||160)),baseWidth=Math.max(4,Math.min(80,Number(logoSettings.widthPercent||naturalWidth/1920*100))),logoWidth=Math.max(2,Math.min(90,baseWidth*Number(logoSettings.scale||100)/100));const logo=logoSettings.src?`<div class="slide-global-overlay slide-logo ${logoSettings.position||'top-left'} overlay-${logoSettings.theme||'none'}" style="width:${logoWidth}%;--logo-aspect:${naturalWidth}/${naturalHeight}"><img src="${logoSettings.src}" alt="Logo"></div>`:'';const clock=settings.clock?.enabled?`<div class="slide-global-overlay slide-clock ${settings.clock.position||'bottom-right'} clock-${settings.clock.theme||'dark'}">${new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'})}</div>`:'';return`<div class="slide-global-background" style="${backgroundStyle}"></div>${logo}${clock}`;};
  const browserOrientation=()=>window.innerHeight>window.innerWidth?'portrait':'landscape';
  const applyMatrixViewport=()=>{const display=document.querySelector('.display-player'),canvas=document.querySelector('#player-matrix-canvas'),matrix=playerState?.matrix,active=matrix?.mode==='matrix'&&matrix.totalResolution&&matrix.viewport;if(!display||!canvas)return;display.classList.toggle('is-matrix-segment',Boolean(active));if(!active){canvas.removeAttribute('style');return;}const total=matrix.totalResolution,viewport=matrix.viewport,scaleX=window.innerWidth/Math.max(1,Number(viewport.width)),scaleY=window.innerHeight/Math.max(1,Number(viewport.height));canvas.style.width=`${Number(total.width)}px`;canvas.style.height=`${Number(total.height)}px`;canvas.style.left=`${-Number(viewport.x||0)*scaleX}px`;canvas.style.top=`${-Number(viewport.y||0)*scaleY}px`;canvas.style.transform=`scale(${scaleX},${scaleY})`;canvas.style.transformOrigin='top left';};
  const itemOrientation=item=>item?.slide?.orientation==='auto'?browserOrientation():item?.slide?.orientation==='portrait'?'portrait':'landscape';
  const itemElements=(item,orientation)=>{const documentData=item?.slide?.document||{},responsive=documentData.responsiveLayouts;return item?.slide?.orientation==='auto'&&Array.isArray(responsive?.[orientation])?responsive[orientation]:documentData.elements||[];};
  const renderPlayerItem=(item,animate=true,elapsedMs=0)=>{if(!item)return;const slide=document.querySelector('#player-slide'),orientation=itemOrientation(item),elements=itemElements(item,orientation),settings=item.slide.document?.settings;renderedOrientation=orientation;slide.dataset.slideOrientation=item.slide.orientation||'landscape';slide.dataset.renderOrientation=orientation;const display=document.querySelector('.display-player');display?.classList.toggle('display-player-portrait',orientation==='portrait');display?.classList.toggle('display-player-landscape',orientation==='landscape');const designed=Boolean(settings)||elements.some(element=>element.type==='wayfinding');display?.classList.toggle('has-slide-design',designed||playerState?.matrix?.mode==='matrix');slide.innerHTML=playerSettingsMarkup(settings)+elements.map(element=>elementMarkup(element,item.event,settings)).join('')||`<div class="player-empty-channel"><strong>${item.slide.name}</strong></div>`;slide.querySelectorAll('.player-layout-element').forEach((node,index)=>{const element=elements[index];if(element?.autoFontSize){node.dataset.autoFont='true';node.dataset.autoFontMax='240';}});scheduleAutomaticTextFit(slide);slide.querySelectorAll('video[data-trim-start]').forEach(video=>{const start=Number(video.dataset.trimStart||0),end=Number(video.dataset.trimEnd||0),synchronizedStart=()=>{const segmentDuration=end>start?end-start:Math.max(0,Number(video.duration||0)-start);video.currentTime=start+(segmentDuration>0?(elapsedMs/1000)%segmentDuration:0);};video.addEventListener('loadedmetadata',synchronizedStart);video.addEventListener('timeupdate',()=>{if(end>start&&video.currentTime>=end)video.currentTime=start;});});applyMatrixViewport();slide.style.transitionDelay=animate&&elapsedMs>0?`${-Math.min(450,elapsedMs)}ms`:'';if(animate&&elapsedMs<450)requestAnimationFrame(()=>slide.classList.add('is-visible'));else slide.classList.add('is-visible');};
  window.addEventListener('resize',()=>scheduleAutomaticTextFit(document));
  const renderShell=state=>{const display=state.display;document.body.innerHTML=`<main class="display-player display-player-${display.orientation}"><div class="player-brand">KIOSKY</div><div class="player-matrix-canvas" id="player-matrix-canvas"><section id="player-slide" class="player-layout-slide"></section></div><div class="player-status"><span>${display.name}</span><span id="player-clock"></span></div></main><div class="player-offline-indicator" id="player-offline-indicator" hidden>Offline · gespeicherte Inhalte werden weiter abgespielt</div><aside class="emergency-takeover" id="player-emergency" hidden><div class="emergency-symbol">!</div><small id="player-emergency-type"></small><strong id="player-emergency-title"></strong><p id="player-emergency-message"></p><span>Bitte beachten Sie die Anweisungen des Personals.</span><div class="emergency-ticker" id="player-emergency-ticker" role="status" aria-label="Lauftext" hidden><span id="player-emergency-ticker-text"></span></div></aside>`;applyMatrixViewport();};
  const heartbeat=async item=>{await fetch(apiUrl(`/api/player/${encodeURIComponent(slug)}/heartbeat`),{method:'POST',headers,body:JSON.stringify({channelId:playerState.channel?.id,slideId:item?.slide?.id,version:`${playerDevice()} · Player 1.2`,cached:true,viewportWidth:window.innerWidth,viewportHeight:window.innerHeight,devicePixelRatio:window.devicePixelRatio||1})}).catch(()=>{});};
  const showIdentification=()=>{
    const display=playerState?.display||{},details=playerState?.identification||{},groups=Array.isArray(details.groupNames)&&details.groupNames.length?details.groupNames.join(', '):'Keine Gruppe',pixelRatio=Number(window.devicePixelRatio||1),resolution=`${window.innerWidth} × ${window.innerHeight} px${pixelRatio!==1?` · Pixeldichte ${pixelRatio}`:''}`;
    let overlay=document.querySelector('#player-identification');
    if(!overlay){overlay=document.createElement('aside');overlay.id='player-identification';overlay.className='player-identification';document.body.appendChild(overlay);}
    overlay.innerHTML=`<div class="player-identification-brand">KIOSKY · DISPLAY IDENTIFIZIEREN</div><h1>${escapeHtml(display.name||'Unbenanntes Display')}</h1><div class="player-identification-grid"><section><small>Standort</small><strong>${escapeHtml(display.location||'Ohne Standort')}</strong></section><section><small>Server</small><strong>${escapeHtml(details.serverUrl||window.location.origin)}</strong></section><section><small>Display-Gruppe</small><strong>${escapeHtml(groups)}</strong></section><section><small>Aktuelle Auflösung</small><strong>${escapeHtml(resolution)}</strong></section></div><p>Diese Information wird nach 20 Sekunden automatisch ausgeblendet.</p>`;
    overlay.classList.add('is-visible');
    clearTimeout(identificationTimer);
    identificationTimer=setTimeout(()=>overlay.classList.remove('is-visible'),20000);
  };
  const handleCommands=async commands=>{
    if(!Array.isArray(commands)||!commands.length)return;
    if(commands.some(command=>command.command==='reload')){await heartbeat(currentItem);window.location.reload();return;}
    if(commands.some(command=>command.command==='sync')){await fetchState();applyEmergency();applyMatrixViewport();startPlayback(true);}
    if(commands.some(command=>command.command==='identify'))showIdentification();
    await heartbeat(currentItem);
  };
  const pollCommands=async()=>{
    if(commandPollRunning)return;
    commandPollRunning=true;
    try{const response=await fetch(apiUrl(`/api/player/${encodeURIComponent(slug)}/commands`),{headers,cache:'no-store'});if(response.ok){const result=await response.json();await handleCommands(result.commands);}}catch{}
    finally{commandPollRunning=false;}
  };
  const emergencyDesignMarkup=documentData=>{const elements=Array.isArray(documentData?.layout?.elements)?documentData.layout.elements:[];if(!elements.length)return'';return`<div class="emergency-design-layer">${elements.map(element=>{const x=Math.max(0,Math.min(100,Number(element.x||0))),y=Math.max(0,Math.min(100,Number(element.y||0))),width=Math.max(3,Math.min(100,Number(element.width||30))),fontSize=Math.max(12,Math.min(180,Number(element.fontSize||32))),content=escapeHtml(String(element.text||'')),color=/^#[0-9a-f]{6}$/i.test(String(element.color||''))?element.color:'#ffffff',style=`left:${x}%;top:${y}%;width:${width}%;color:${color};font-size:${fontSize/7.8}vw;text-align:${['left','center','right'].includes(element.align)?element.align:'left'};font-weight:${element.bold?800:400}`;return`<div class="emergency-design-element ${element.type==='icon'?'is-icon':''}" style="${style}">${content}</div>`;}).join('')}</div>`;};
  const applyEmergency=()=>{const alert=playerState?.emergency,overlay=document.querySelector('#player-emergency'),ticker=document.querySelector('#player-emergency-ticker'),tickerText=document.querySelector('#player-emergency-ticker-text');if(!overlay)return;overlay.hidden=!alert;if(alert){const labels={evacuation:'EVAKUIERUNG',weather:'UNWETTERWARNUNG',severe_weather:'UNWETTERWARNUNG',security:'SICHERHEITSHINWEIS',technical:'TECHNISCHE STÖRUNG',power_outage:'STROMAUSFALL',fire:'BRANDALARM',medical:'MEDIZINISCHER NOTFALL',all_clear:'ENTWARNUNG',info:'WICHTIGE INFORMATION'},documentData=alert.document||{},tickerValue=String(documentData.ticker||'').trim(),design=emergencyDesignMarkup(documentData);overlay.style.background=documentData.backgroundColor||'';overlay.style.color=documentData.textColor||'';overlay.classList.toggle('has-custom-design',Boolean(design));overlay.querySelector('.emergency-design-layer')?.remove();if(design)overlay.insertAdjacentHTML('afterbegin',design);document.querySelector('#player-emergency-type').textContent=labels[alert.type]||String(alert.type||labels.info).toUpperCase();document.querySelector('#player-emergency-title').textContent=alert.title;document.querySelector('#player-emergency-message').textContent=alert.message;if(ticker&&tickerText){tickerText.textContent=tickerValue;ticker.hidden=!tickerValue;}}else{overlay.classList.remove('has-custom-design');overlay.querySelector('.emergency-design-layer')?.remove();if(ticker&&tickerText){tickerText.textContent='';ticker.hidden=true;}}};
  const showItem=(item,animate=true,elapsedMs=0)=>{currentItem=item;const slide=document.querySelector('#player-slide');slide.dataset.transition=item.transition==='inherit'?(playerState.channel?.defaultTransition||'fade'):item.transition;renderPlayerItem(item,animate,elapsedMs);heartbeat(item);};
  const showNext=(forceRender=false)=>{const items=playerState?.slides||[];if(!items.length){document.querySelector('#player-slide').innerHTML='<div class="player-empty-channel"><strong>Willkommen</strong><p>Noch kein Inhalt zugewiesen.</p></div>';return;}if(items.length===1){clearTimeout(timer);const item=items[0];if(forceRender||currentItem?.slide?.id!==item.slide?.id)showItem(item,false);index=1;return;}const item=items[index%items.length],slide=document.querySelector('#player-slide');slide.classList.remove('is-visible');clearTimeout(timer);timer=setTimeout(()=>{showItem(item);index+=1;timer=setTimeout(showNext,Math.max(3,Number(item.durationSeconds||12))*1000);},220);};
  const synchronizedPosition=()=>{const items=playerState?.slides||[],sync=playerState?.playbackSync;if(!items.length||!sync)return null;const durations=items.map(item=>Math.max(3,Number(item.durationSeconds||12))*1000),cycleMs=durations.reduce((sum,value)=>sum+value,0),nowMs=Date.now()+serverClockOffsetMs,cycleElapsed=((nowMs-Number(sync.epochMs))%cycleMs+cycleMs)%cycleMs;let cursor=0;for(let position=0;position<durations.length;position+=1){const end=cursor+durations[position];if(cycleElapsed<end)return{index:position,elapsedMs:cycleElapsed-cursor,remainingMs:end-cycleElapsed,boundaryAtMs:nowMs+end-cycleElapsed};cursor=end;}return{index:0,elapsedMs:0,remainingMs:durations[0],boundaryAtMs:nowMs+durations[0]};};
  const startSynchronizedPlayback=(forceRender=false)=>{clearTimeout(timer);const items=playerState?.slides||[];if(items.length===1){const item=items[0];if(forceRender||currentMatrixIndex!==0||currentItem?.slide?.id!==item.slide?.id)showItem(item,false);currentMatrixIndex=0;return;}const position=synchronizedPosition();if(!position){showNext(forceRender);return;}const item=items[position.index],changed=currentMatrixIndex!==position.index;if(forceRender||changed){currentMatrixIndex=position.index;showItem(item,position.elapsedMs<450,position.elapsedMs);}const fadeAtMs=position.boundaryAtMs-220,tillFade=fadeAtMs-(Date.now()+serverClockOffsetMs);timer=setTimeout(()=>{document.querySelector('#player-slide')?.classList.remove('is-visible');const tillBoundary=Math.max(0,position.boundaryAtMs-(Date.now()+serverClockOffsetMs));timer=setTimeout(()=>{const next=synchronizedPosition();if(!next)return;currentMatrixIndex=next.index;showItem(items[next.index],true,next.elapsedMs);startSynchronizedPlayback(false);},tillBoundary);},Math.max(0,tillFade));};
  const startPlayback=(forceRender=false)=>{clearTimeout(timer);if(playerState?.playbackSync){playbackSyncKey=String(playerState.playbackSync.key||'');if(forceRender)currentMatrixIndex=-1;startSynchronizedPlayback(forceRender);}else{playbackSyncKey='';currentMatrixIndex=-1;if(forceRender)index=0;showNext(forceRender);}};
  const initial=await fetchState();if(!initial){document.body.innerHTML='<main class="player-error"><strong>Display nicht gefunden</strong><p>Bitte die Player-URL und den Geräteschlüssel prüfen.</p></main>';return;}renderShell(initial);document.querySelector('#player-clock').textContent=new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'});applyEmergency();startPlayback(true);await handleCommands(initial.commands);setInterval(pollCommands,3000);setInterval(async()=>{const previousChannel=playerState?.channel?.id,previousMatrixMode=playerState?.matrix?.mode,previousSyncKey=playbackSyncKey;await fetchState();applyEmergency();applyMatrixViewport();const syncChanged=previousSyncKey!==String(playerState?.playbackSync?.key||'');if(previousChannel!==playerState?.channel?.id||previousMatrixMode!==playerState?.matrix?.mode||syncChanged)startPlayback(true);else if(playerState?.playbackSync)startSynchronizedPlayback(false);},30000);setInterval(()=>{const time=new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'});document.querySelectorAll('#player-clock,.wayfinding-clock,.slide-clock').forEach(clock=>{clock.textContent=time;});document.querySelectorAll('[data-countdown-target]').forEach(node=>{node.textContent=`${node.dataset.countdownPrefix||''}${playerCountdown(node.dataset.countdownTarget)}${node.dataset.countdownSuffix||''}`;});document.querySelectorAll('[data-slide-countdown]').forEach(node=>{try{const element=JSON.parse(decodeURIComponent(node.dataset.countdownElement||''));node.querySelector('span').textContent=`${element.prefix||''}${countdownText(element)}${element.suffix||''}`;}catch{}});document.querySelectorAll('[data-ticker-countdown]').forEach(node=>{try{const item=JSON.parse(decodeURIComponent(node.dataset.tickerCountdown||''));node.textContent=`${item.prefix||''}${countdownText(item)}${item.suffix||''}`;}catch{}});document.querySelectorAll('[data-wayfinding-countdown]').forEach(node=>{try{const countdown=JSON.parse(decodeURIComponent(node.dataset.wayfindingCountdown||''));node.textContent=`${countdown.prefix||''}${countdownText(countdown)}${countdown.suffix||''}`;}catch{}});},1000);window.addEventListener('resize',()=>{clearTimeout(resizeTimer);resizeTimer=setTimeout(()=>{applyMatrixViewport();if(currentItem?.slide?.orientation==='auto'&&itemOrientation(currentItem)!==renderedOrientation)renderPlayerItem(currentItem,false,synchronizedPosition()?.elapsedMs||0);},120);});window.addEventListener('beforeunload',()=>{wakeLock?.release?.();});
}

const playerSlug = new URLSearchParams(window.location.search).get('display');
if (playerSlug) {
  renderDisplayPlayer(playerSlug);
  return;
}

const cmsIntegrated = kioskyRuntime.platform === 'wordpress';
const cmsManagedServices = kioskyRuntime.platform !== 'standalone';
if (cmsIntegrated) document.body.classList.add('cms-integrated', 'cms-wordpress');
if (!cmsManagedServices) document.querySelectorAll('[data-settings-open="calendar"],[data-settings-detail="calendar"]').forEach(element => { element.hidden = true; });
if (cmsManagedServices) {
  document.body.classList.add('cms-managed-services');
  document.querySelectorAll('[data-view="media"], [data-view="users"]').forEach(element => { element.hidden = true; });
}

const state = {
  view: 'dashboard',
  currentUser: null,
  users: [],
  userInvitations: [],
  events: [],
  eventSchedule: [],
  currentScheduleEventId: null,
  selectedEvents: new Set(),
  selectedSlides: new Set(),
  contentScope: 'standard',
  crewbrainPreview: [],
  crewbrainPreviewSelection: new Set(),
  crewbrainPreviewPage: 1,
  crewbrainPreviewPageSize: '25',
  crewbrainPreviewTotal: 0,
  crewbrainPreviewExcluded: 0,
  activeSource: null,
  integrationSources: {},
  titleExclusions: [],
  calendarCategory: 'all',
  scheduleDay: 'so',
  selectedScheduleContent: null,
  weekOffset: 0
};

const pageMeta = {
  dashboard: ['Willkommen', ''],
  events: ['Veranstaltungen', 'Programm und Veröffentlichung'],
  displays: ['Displays', 'Digital Signage im Haus'],
  channels: ['Slides & Kanäle', 'Content und Abspielreihenfolge'],
  advertising: ['Werbung', 'Werbeslides und standortbezogene Werbekanäle'],
  media: ['Mediendatenbank', 'Zentrale Bilder, Vektoren und Videos'],
  schedule: ['Display-Zeitplan', 'Drag-and-drop-Ausspielung'],
  operations: ['Presets & Warnhinweise', 'Player-Monitoring, Presets und Warnhinweise'],
  integrations: ['Schnittstellen', 'Datenquellen und Synchronisation'],
  settings: ['Einstellungen', 'Funktionsbereiche modular ein- und ausschalten'],
  updates: ['Updates', 'Kiosky-Version und Release Notes'],
  users: ['Benutzerverwaltung', 'Konten, Rollen und Zugriff'],
  features: ['Funktionen & Hilfe', 'Alles, was Kiosky kann']
};

const navItems = document.querySelectorAll('[data-view]');
const panels = document.querySelectorAll('[data-view-panel]');
const pageTitle = document.querySelector('#page-title');
const pageContext = document.querySelector('#page-context');
const appShell = document.querySelector('#app-shell');
const sidebar = document.querySelector('.sidebar');
const navigationToggle = document.querySelector('.mobile-menu');
const navigationBackdrop = document.querySelector('.nav-backdrop');
const sidebarClose = document.querySelector('.sidebar-close');
const compactNavigation = window.matchMedia('(max-width: 900px)');
const toast = document.querySelector('#toast');
let toastTimer;

function storedDesktopNavigationState() {
  try { return localStorage.getItem('kiosky-sidebar-collapsed') === 'true'; }
  catch { return false; }
}

function updateNavigationAccessibility() {
  const expanded = compactNavigation.matches ? sidebar.classList.contains('is-open') : !appShell.classList.contains('sidebar-collapsed');
  navigationToggle.setAttribute('aria-expanded', String(expanded));
  navigationToggle.setAttribute('aria-label', expanded ? 'Navigation ausblenden' : 'Navigation einblenden');
}

function closeCompactNavigation() {
  sidebar.classList.remove('is-open');
  navigationBackdrop.classList.remove('is-visible');
  document.body.classList.remove('navigation-open');
  updateNavigationAccessibility();
}

function toggleNavigation() {
  if (compactNavigation.matches) {
    const opens = !sidebar.classList.contains('is-open');
    sidebar.classList.toggle('is-open', opens);
    navigationBackdrop.classList.toggle('is-visible', opens);
    document.body.classList.toggle('navigation-open', opens);
  } else {
    const collapsed = !appShell.classList.contains('sidebar-collapsed');
    appShell.classList.toggle('sidebar-collapsed', collapsed);
    try { localStorage.setItem('kiosky-sidebar-collapsed', String(collapsed)); } catch { /* private browsing */ }
  }
  updateNavigationAccessibility();
}

function syncNavigationMode() {
  closeCompactNavigation();
  appShell.classList.toggle('sidebar-collapsed', !compactNavigation.matches && storedDesktopNavigationState());
  updateNavigationAccessibility();
}

syncNavigationMode();
compactNavigation.addEventListener?.('change', syncNavigationMode);

localStorage.removeItem('kiosky-active-source');
fetch(apiUrl('/api/health'), { headers: { Accept: 'application/json' } })
  .then(response => response.ok ? response.json() : null)
  .then(result => { if (result && result.buildNumber) document.querySelector('#build-number').textContent = result.buildNumber; })
  .catch(() => { /* static preview keeps the embedded build number */ });

function setView(view) {
  const hiddenSettingsRecovery=view==='settings'&&state.currentUser?.role==='admin'&&window.location.hash.slice(1)==='settings';
  if (!navigationViewAvailable(view)&&!hiddenSettingsRecovery) view = firstAvailableNavigationView();
  state.view = view;
  state.contentScope=view==='advertising'?'advertising':view==='channels'?'standard':state.contentScope||'standard';
  panels.forEach(panel => panel.classList.toggle('is-active', panel.dataset.viewPanel === view || (view==='advertising'&&panel.dataset.viewPanel==='channels')));
  navItems.forEach(item => item.classList.toggle('is-active', item.dataset.view === view || (item.dataset.view === 'settings' && ['integrations', 'users'].includes(view))));
  pageTitle.textContent = pageMeta[view][0];
  pageContext.textContent = pageMeta[view][1];
  if (compactNavigation.matches) closeCompactNavigation();
  history.replaceState(null, '', `#${view}`);
  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (view === 'integrations') {
    loadIntegrationSources();
    loadTitleExclusions();
    if (!state.crewbrainConfigLoaded) loadCrewBrainConfiguration();
    loadEasyJobConfiguration();
    loadApiUsers();
  }
  if(view==='settings'){showSettingsLevel();loadFeatureSettings();loadAdvertisingSettings();if(cmsManagedServices)loadPublicCalendarSettings();}
  if (view === 'users') loadUsers();
  if (view === 'events') loadEvents();
  if (['channels','advertising'].includes(view)) loadContent();
  if (view === 'media') refreshMedia();
  if (view === 'displays') loadDisplays();
  if (view === 'schedule') loadScheduling();
  if (view === 'operations') loadOperations();
  if (view === 'updates') loadUpdates();
  if (view === 'dashboard') loadDashboard();
}

function renderUpdates(update) {
  document.querySelector('#update-installed-version').textContent=update.installedVersion||'–';
  document.querySelector('#update-available-version').textContent=update.availableVersion||'–';
  document.querySelector('#update-installation-mode').textContent=update.installationMode==='composer'?'TYPO3 Composer':update.installationMode==='classic'?'TYPO3 Classic':update.installationMode==='wordpress'?'WordPress-Plugin':'Standalone-Installation';
  document.querySelector('#update-checked-at').textContent=update.checkedAt?`Geprüft ${new Date(update.checkedAt).toLocaleString('de-DE')}`:'Noch nicht geprüft';
  const label=document.querySelector('#update-state-label');
  label.textContent=update.updateAvailable?'Update verfügbar':'Aktuell';
  label.className=`tag ${update.updateAvailable?'orange':'green'}`;
  document.querySelector('#update-release-notes').innerHTML=update.releaseNotes
    ? `<p>${escapeHtml(update.releaseNotes).replace(/\n{2,}/g,'</p><p>').replace(/\n/g,'<br>')}</p>`
    : '<p>Für diese Version wurden keine eingebetteten Release Notes geliefert.</p>';
  const link=document.querySelector('#update-release-link');
  link.hidden=!update.releaseNotesUrl;
  if(update.releaseNotesUrl)link.href=update.releaseNotesUrl;
  const error=document.querySelector('#update-error');
  error.hidden=!update.lastError;
  error.textContent=update.lastError||'';
  const install=document.querySelector('#update-install');
  install.disabled=!update.updateAvailable||!update.canInstall;
  document.querySelector('#update-install-note').textContent=update.restartRequired
    ? 'Das Update wurde vorbereitet. Kiosky wird neu gestartet.'
    : update.canInstall?'Paketprüfung, Sicherung, Migration und Cache-Leerung laufen automatisch.':'Für diese Installation ist kein One-Click-Update verfügbar.';
  const badge=document.querySelector('#update-nav-badge');
  badge.hidden=!update.updateAvailable;
}

async function loadUpdates(check=false){
  try{
    const result=await apiRequest(check?'/api/updates/check':'/api/updates/status',check?{method:'POST',body:'{}'}:{});
    renderUpdates(result.update);
    if(check)showToast(result.update.updateAvailable?`Kiosky ${result.update.availableVersion} ist verfügbar.`:'Kiosky ist aktuell.');
  }catch(error){showToast(error.message);}
}

document.querySelector('#update-check')?.addEventListener('click',async event=>{
  event.currentTarget.disabled=true;
  try{await loadUpdates(true);}finally{event.currentTarget.disabled=false;}
});
document.querySelector('#update-install')?.addEventListener('click',async event=>{
  if(!confirm('Kiosky jetzt prüfen, sichern und aktualisieren? Das Backend kann währenddessen kurzzeitig nicht erreichbar sein.'))return;
  event.currentTarget.disabled=true;
  try{
    const result=await apiRequest('/api/updates/install',{method:'POST',body:'{}'});
    renderUpdates(result.update);
    showToast(result.scheduled?'Update wird installiert. Kiosky startet anschließend neu.':'Kein Update erforderlich.');
  }catch(error){showToast(error.message);event.currentTarget.disabled=false;}
});

function updateDashboardHeading(date=new Date()){
  const timezone=Intl.DateTimeFormat().resolvedOptions().timeZone||'Europe/Berlin';
  const hour=Number(new Intl.DateTimeFormat('de-DE',{timeZone:timezone,hour:'2-digit',hourCycle:'h23'}).formatToParts(date).find(part=>part.type==='hour')?.value);
  const salutation=hour<11?'Guten Morgen':hour<18?'Guten Tag':'Guten Abend';
  const firstName=state.currentUser?.displayName?.trim().split(/\s+/)[0]||'Willkommen';
  pageMeta.dashboard=[`${salutation}, ${firstName}`,new Intl.DateTimeFormat('de-DE',{timeZone:timezone,weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(date)];
  if(state.view==='dashboard'){pageTitle.textContent=pageMeta.dashboard[0];pageContext.textContent=pageMeta.dashboard[1];}
}

async function loadDashboard(){try{const [eventsResult,displaysResult,channelsResult,schedulesResult]=await Promise.all([apiRequest('/api/events'),apiRequest('/api/displays'),apiRequest('/api/channels'),apiRequest('/api/schedules')]);const events=eventsResult.data||[],displays=displaysResult.data||[],channels=channelsResult.data||[],schedules=schedulesResult.data||[];document.querySelector('#dashboard-event-count').textContent=events.length;const online=displays.filter(display=>display.online).length;document.querySelector('#dashboard-display-count').innerHTML=`${online} <small>/ ${displays.length}</small>`;document.querySelector('#dashboard-display-note').textContent=displays.length?`${online} Player aktuell online`:'Noch keine Displays angelegt';document.querySelector('#dashboard-channel-count').textContent=channels.filter(channel=>channel.status==='published').length;const future=schedules.filter(entry=>entry.status==='published'&&new Date(entry.startsAt)>new Date()).sort((a,b)=>new Date(a.startsAt)-new Date(b.startsAt)),next=future[0],soon=future.filter(entry=>new Date(entry.startsAt)-Date.now()<=2*3600000).length;document.querySelector('#dashboard-next-time').textContent=next?new Date(next.startsAt).toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}):'–';document.querySelector('#dashboard-next-note').textContent=next?`${next.channelName} · ${next.targetType==='group'?'Display-Gruppe':next.targetType==='matrix'?'Matrix-Display':'Display'}`:'Noch kein Wechsel geplant';const landscapes=displays.filter(display=>display.orientation==='landscape'),portraits=displays.filter(display=>display.orientation==='portrait');document.querySelector('#dashboard-landscape-count').textContent=`${landscapes.length} Querformat`;document.querySelector('#dashboard-landscape-note').textContent=landscapes.length?`${landscapes.filter(display=>display.online).length} online`:'keine Displays';document.querySelector('#dashboard-portrait-count').textContent=`${portraits.length} Hochformat`;document.querySelector('#dashboard-portrait-note').textContent=portraits.length?`${portraits.filter(display=>display.online).length} online`:'keine Displays';document.querySelector('#dashboard-change-count').textContent=soon?`${soon} geplante Wechsel`:'Keine geplanten Wechsel';document.querySelector('#dashboard-event-list').innerHTML=events.slice(0,5).map(event=>`<div class="event-row"><time>${escapeHtml(eventTimeInput(event.eventStart)||'–')}</time><span class="event-line coral"></span><div><strong>${escapeHtml(event.title)}</strong><small>${escapeHtml(event.room||event.venue||'Ort offen')}${eventTimeInput(event.admissionStart)?` · Einlass ${escapeHtml(eventTimeInput(event.admissionStart))}`:''}</small></div><span class="tag ${eventStatusTones[event.status]||'muted'}">${escapeHtml(eventStatusLabels[event.status]||event.status)}</span></div>`).join('')||'<p class="property-note">Noch keine Veranstaltungen vorhanden.</p>';}catch(error){showToast(error.message);}}

function showToast(message) {
  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.classList.add('is-visible');
  toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

navItems.forEach(item => item.addEventListener('click', () => setView(item.dataset.view)));
document.querySelectorAll('[data-view-link]').forEach(item => item.addEventListener('click', () => setView(item.dataset.viewLink)));

function showSettingsLevel(level = '') {
  const overview = document.querySelector('[data-settings-overview]');
  const details = [...document.querySelectorAll('[data-settings-detail]')];
  if (!overview) return;
  overview.hidden = Boolean(level);
  details.forEach(detail => { detail.hidden = detail.dataset.settingsDetail !== level; });
  document.querySelectorAll('[data-view-panel="settings"] .settings-menu button').forEach(button => {
    const active = level ? button.dataset.settingsOpen === level : button.hasAttribute('data-settings-home');
    button.classList.toggle('is-active', active);
    if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('[data-settings-home]').forEach(button => button.addEventListener('click', () => {
  if (state.view !== 'settings') setView('settings'); else showSettingsLevel();
}));
document.querySelectorAll('[data-settings-open]').forEach(button => button.addEventListener('click', () => {
  const level = button.dataset.settingsOpen;
  if (state.view !== 'settings') setView('settings');
  showSettingsLevel(level);
  if(level==='advertising')loadAdvertisingSettings();
  if(level==='calendar'&&cmsManagedServices)loadPublicCalendarSettings();
}));
document.querySelectorAll('[data-settings-anchor]').forEach(button => button.addEventListener('click', () => {
  const target = document.getElementById(button.dataset.settingsAnchor);
  if (!target) return;
  target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  target.classList.add('settings-focus');
  window.setTimeout(() => target.classList.remove('settings-focus'), 1200);
}));

const featureSearch = document.querySelector('#feature-search');
const featureCards = [...document.querySelectorAll('.feature-card')];
const featureFilters = [...document.querySelectorAll('[data-feature-filter]')];
let activeFeatureFilter = 'all';

function filterFeatures() {
  const query = featureSearch.value.trim().toLocaleLowerCase('de-DE');
  let visibleCount = 0;
  featureCards.forEach(card => {
    const matchesCategory = activeFeatureFilter === 'all' || card.dataset.featureCategory === activeFeatureFilter;
    const searchableText = `${card.textContent} ${card.dataset.featureSearch || ''}`.toLocaleLowerCase('de-DE');
    const visible = matchesCategory && (!query || searchableText.includes(query));
    card.hidden = !visible;
    if (visible) visibleCount += 1;
  });
  document.querySelector('#feature-result-count').textContent = `${visibleCount} ${visibleCount === 1 ? 'Bereich' : 'Bereiche'}`;
  document.querySelector('#feature-empty').hidden = visibleCount !== 0;
}

featureSearch.addEventListener('input', filterFeatures);
featureFilters.forEach(button => button.addEventListener('click', () => {
  activeFeatureFilter = button.dataset.featureFilter;
  featureFilters.forEach(filter => filter.classList.toggle('is-active', filter === button));
  filterFeatures();
}));
document.addEventListener('keydown', event => {
  if (event.key === '/' && state.view === 'features' && document.activeElement !== featureSearch) {
    event.preventDefault();
    featureSearch.focus();
  }
});

const productTour = document.querySelector('#product-tour');
const tourSteps = [
  { view: 'features', target: '.feature-hero', kicker: 'Orientierung', title: 'Vom Datensatz bis zum Display', description: 'Kiosky verbindet Veranstaltungen, Medien und Slides mit Kanälen, Zeitplänen und gekoppelten Playern. Die sechs Stationen zeigen den vollständigen Weg bis zur überwachten Ausspielung.', tip: 'Das ausführliche Praxis-Handbuch unter „Funktionen & Hilfe“ erklärt danach jeden Schritt mit den genauen Menüpunkten.' },
  { view: 'events', target: '[data-view-panel="events"]', kicker: '1 · Programmdaten', title: 'Veranstaltungen übernehmen und ergänzen', description: 'Lege Veranstaltungen manuell an oder importiere Projekte und Jobs über CrewBrain beziehungsweise easyjob. Prüfe Status, öffentliche Texte, Ablaufzeiten, Raum und Veranstaltungsbild; für mehrere importierte Termine stehen Massenaktionen bereit.', tip: 'easyjob-Bilder der Dokumentenart „Veranstaltungsbild“ werden automatisch in die Mediendatenbank übernommen. Jobbilder haben Vorrang vor Projektbildern.' },
  { view: 'channels', target: '[data-view-panel="channels"]', kicker: '2 · Inhalt', title: 'Slide gestalten und Kanal bauen', description: 'Erstelle eine Slide leer oder aus einer Vorlage, wähle feste oder responsive Ausrichtung und platziere Text, Medien, Wetter, QR-Code, Countdown, Wegweisung und dynamische Veranstaltungsfelder. Ordne fertige Slides anschließend mit Dauer und Übergang in einem Kanal.', tip: 'Fehlt ein Veranstaltungswert, bleibt der Platz des dynamischen Elements erhalten, sein sichtbarer Inhalt wird aber vollständig ausgeblendet.' },
  { view: 'schedule', target: '[data-view-panel="schedule"]', kicker: '3 · Planung', title: 'Inhalt einem Ziel und Zeitraum zuweisen', description: 'Ziehe einen veröffentlichten Kanal oder eine einzelne Slide in der Tages- oder Wochenansicht auf ein Display, eine Gruppe oder eine Matrix. Lege Start, Ende, Wiederholung und Priorität fest und entscheide bei Konflikten zwischen zusätzlicher oder ersetzender Planung.', tip: 'Ohne aktive Planung läuft der Standardkanal des Displays beziehungsweise der Matrix weiter.' },
  { view: 'displays', target: '[data-view-panel="displays"]', kicker: '4 · Player', title: 'Display anlegen und Player koppeln', description: 'Lege zuerst das Display mit Ausrichtung, Auflösungsmodus, Standort und Standardkanal an. Starte dann einen Browser-, Windows-, Linux- oder macOS-Universal-Player. Dessen sechsstelligen Code trägst du beim Display unter „Universal Player koppeln“ ein.', tip: 'Ein direkt beim Display heruntergeladener zugeordneter Desktop-Player enthält die feste Player-URL bereits und benötigt keinen Kopplungscode.' },
  { view: 'operations', target: '[data-view-panel="operations"]', kicker: '5 · Ausspielung', title: 'Betrieb kontrollieren und eingreifen', description: 'Prüfe Online-Status, zuletzt gemeldeten Inhalt und Offline-Cache. Fordere Synchronisierung oder Neuladen an und nutze Betriebspresets, Warnvorlagen oder DWD-Warnungen für eine gezielte, priorisierte Übernahme.', tip: 'Geschafft! Im Praxis-Handbuch findest du zusätzlich Einrichtung, Fehlerprüfung, Werbeautomatik, Schnittstellen, Benutzer und Updates.' }
];
let activeTourStep = 0;
let tourHighlight = null;

function clearTourHighlight() {
  tourHighlight?.classList.remove('tour-highlight');
  tourHighlight = null;
}

function renderTourStep() {
  const step = tourSteps[activeTourStep];
  clearTourHighlight();
  if (state.view !== step.view) setView(step.view);
  requestAnimationFrame(() => {
    tourHighlight = document.querySelector(step.target);
    tourHighlight?.classList.add('tour-highlight');
    tourHighlight?.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  });
  document.querySelector('#tour-step-label').textContent = `Station ${activeTourStep + 1} von ${tourSteps.length}`;
  document.querySelector('#tour-progress-bar').style.width = `${((activeTourStep + 1) / tourSteps.length) * 100}%`;
  document.querySelector('#tour-kicker').textContent = step.kicker;
  document.querySelector('#tour-title').textContent = step.title;
  document.querySelector('#tour-description').textContent = step.description;
  document.querySelector('#tour-tip').innerHTML = `<strong>Praxis-Tipp</strong><span>${step.tip}</span>`;
  document.querySelector('#tour-previous').disabled = activeTourStep === 0;
  document.querySelector('#tour-next').textContent = activeTourStep === tourSteps.length - 1 ? 'Tour abschließen' : 'Weiter';
}

function openProductTour() {
  activeTourStep = 0;
  productTour.hidden = false;
  document.body.classList.add('tour-open');
  renderTourStep();
  document.querySelector('#close-product-tour').focus();
}

function closeProductTour(completed = false) {
  clearTourHighlight();
  productTour.hidden = true;
  document.body.classList.remove('tour-open');
  if (completed) {
    setView('features');
    showToast('Tour abgeschlossen – du bist bereit für deinen ersten Workflow.');
  }
  document.querySelector('#start-product-tour')?.focus();
}

document.querySelector('#start-product-tour').addEventListener('click', openProductTour);
productTour.addEventListener('click', event => {
  const action = event.target.closest('#close-product-tour, #tour-previous, #tour-next');
  if (!action) return;
  if (action.id === 'close-product-tour') { closeProductTour(); return; }
  if (action.id === 'tour-previous') { if (activeTourStep > 0) { activeTourStep -= 1; renderTourStep(); } return; }
  if (activeTourStep === tourSteps.length - 1) closeProductTour(true);
  else { activeTourStep += 1; renderTourStep(); }
});
document.addEventListener('keydown', event => {
  if (productTour.hidden) return;
  if (event.key === 'Escape') closeProductTour();
  if (event.key === 'ArrowRight') document.querySelector('#tour-next').click();
  if (event.key === 'ArrowLeft' && activeTourStep > 0) document.querySelector('#tour-previous').click();
});
navigationToggle.addEventListener('click', toggleNavigation);
navigationBackdrop.addEventListener('click', closeCompactNavigation);
sidebarClose.addEventListener('click', closeCompactNavigation);
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && compactNavigation.matches && sidebar.classList.contains('is-open')) closeCompactNavigation();
});

document.querySelectorAll('[data-action="new-event"]').forEach(button => button.addEventListener('click', () => {
  setView('events');
  openEventEditor();
}));

document.querySelectorAll('[data-preset]').forEach(button => button.addEventListener('click', async () => {setView('operations');await loadOperations();const preset=state.presets.find(item=>item.id===`preset-${button.dataset.preset}`);if(preset){document.querySelector(`[data-apply-preset="${preset.id}"]`)?.click();}else showToast('Preset wurde nicht gefunden.');}));

// Authentication and account
const roleLabels = { admin: 'Administrator', editor: 'Redakteur', viewer: 'Betrachter' };

async function apiRequest(path, options = {}) {
  const response = await fetch(apiUrl(path), {
    ...options,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...kioskyRuntime.requestHeaders, ...(options.headers || {}) }
  });
  let result = null;
  try { result = await response.json(); } catch (error) { /* handled below */ }
  if (!response.ok) {
    const requestError = new Error(result?.error?.message || `Serveranfrage fehlgeschlagen (HTTP ${response.status}).`);
    requestError.code = result?.error?.code;
    requestError.status = response.status;
    throw requestError;
  }
  return result;
}

const eventStatusLabels = { review: 'Zu prüfen', draft: 'Entwurf', ready: 'Bereit', published: 'Veröffentlicht', cancelled: 'Abgesagt', archived: 'Archiviert' };
const eventStatusTones = { review: 'blue', draft: 'muted', ready: 'coral', published: 'green', cancelled: 'coral', archived: 'muted' };

function eventDateInput(value) {
  if (!value) return '';
  const digits = String(value).replace(/\D/g, '').slice(0, 8);
  return digits.length === 8 ? `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}` : '';
}

function eventTimeInput(value) {
  if (!value) return '';
  const date = new Date(value);
  if (!Number.isNaN(date.valueOf()) && /(?:T|Z|[+-]\d{2}:?\d{2})/.test(String(value))) {
    return new Intl.DateTimeFormat('de-DE', { timeZone: 'Europe/Berlin', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(date);
  }
  const match = String(value).match(/T?(\d{2}):?(\d{2})(?::?\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/);
  return match ? `${match[1]}:${match[2]}` : '';
}

function eventTimestamp(date, time) {
  if(!date||!time)return undefined;
  const timestamp=new Date(`${date}T${time}:00`);
  return Number.isNaN(timestamp.valueOf())?undefined:timestamp.toISOString();
}

function visibleEventRows(){
  const query = document.querySelector('#event-search').value.trim().toLowerCase();
  const status = document.querySelector('#event-status-filter').value;
  const objectType = document.querySelector('#event-object-filter').value;
  return state.events.filter(item => (!query || [item.title, item.venue, item.externalNumber,item.organizer,item.eventType].some(value => String(value || '').toLowerCase().includes(query))) && (!status || item.status === status)&&(!objectType||item.externalObjectType===objectType));
}
function renderEvents() {
  const rows = visibleEventRows();
  document.querySelector('#event-table').innerHTML = `<div class="table-row table-head" role="row"><span><label class="table-check"><input type="checkbox" id="event-select-all" ${rows.length&&rows.every(item=>state.selectedEvents.has(item.id))?'checked':''}> Veranstaltung</label></span><span>Termin</span><span>Ort</span><span>Quelle</span><span>Status</span><span></span></div>${rows.map(item => {
    const date = eventDateInput(item.date);
    const formattedDate = date ? new Date(`${date}T12:00:00`).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Termin offen';
    const time = eventTimeInput(item.eventStart);
    const source = item.sourceId === 'source-crewbrain' ? 'CrewBrain' : item.sourceId === 'source-easyjob' ? 'easyjob' : 'Manuell';
    const syncLabel = item.syncStatus === 'manually_modified' ? 'redaktionell angepasst' : item.syncStatus === 'synced' ? 'synchronisiert' : 'manuell';
    const objectLabel=item.externalObjectType==='PROJECT'?'Projekt':item.externalObjectType==='JOB'?'Job':'';
    return `<div class="table-row" role="row" data-event-row="${escapeHtml(item.id)}"><span><label class="table-check"><input type="checkbox" data-event-select="${escapeHtml(item.id)}" ${state.selectedEvents.has(item.id)?'checked':''}><span><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(item.externalNumber || 'Eigene Veranstaltung')} · ${escapeHtml([objectLabel,item.eventType,item.organizer,syncLabel].filter(Boolean).join(' · '))}</small></span></label></span><span>${escapeHtml(formattedDate)}${time ? ` · ${escapeHtml(time)}` : ''}</span><span>${escapeHtml(item.venue || item.room || '–')}</span><span class="source-label">${escapeHtml(source)}</span><span><i class="tag ${eventStatusTones[item.status] || 'muted'}">${escapeHtml(eventStatusLabels[item.status] || item.status)}</i></span><button class="row-menu" type="button" data-edit-event="${escapeHtml(item.id)}" aria-label="${escapeHtml(item.title)} bearbeiten">Bearbeiten</button></div>`;
  }).join('') || '<div class="event-table-empty">Keine passenden Veranstaltungen vorhanden. Importiere Termine aus CrewBrain oder easyjob oder lege eine Veranstaltung manuell an.</div>'}`;
  const selected=state.selectedEvents.size,selectedImported=state.events.filter(item=>state.selectedEvents.has(item.id)&&item.sourceId).length;document.querySelector('#bulk-edit-events').disabled=!selectedImported;document.querySelector('#bulk-edit-events').title=selected&&!selectedImported?'Massenänderungen gelten nur für importierte Veranstaltungen':'';document.querySelector('#bulk-archive-events').disabled=!selected;document.querySelector('#bulk-delete-events').disabled=!selected||state.currentUser?.role!=='admin';
}

async function loadEvents() {
  try {
    const result = await apiRequest('/api/events');
    state.events = result.data || [];
    renderEvents();
  } catch (error) { showToast(error.message); }
}

async function openEventEditor(item = null) {
  document.querySelector('#event-form').reset();
  document.querySelector('#event-id').value = item?.id || '';
  document.querySelector('#event-dialog-title').textContent = item ? 'Veranstaltung bearbeiten' : 'Veranstaltung anlegen';
  document.querySelector('#event-source-note').textContent = item?.sourceId === 'source-crewbrain' ? `Aus CrewBrain importiert${item.externalNumber ? ` · ${item.externalNumber}` : ''} · manuelle Änderungen werden geschützt` : item?.sourceId === 'source-easyjob' ? `Aus easyjob importiert${item.externalNumber ? ` · ${item.externalNumber}` : ''} · manuelle Änderungen werden geschützt` : 'Manuell angelegte Veranstaltung';
  document.querySelector('#event-title').value = item?.title || '';
  document.querySelector('#event-subtitle').value = item?.subtitle || '';
  document.querySelector('#event-organizer').value = item?.organizer || '';
  document.querySelector('#event-type').value = item?.eventType || '';
  document.querySelector('#event-status').value = item?.status || 'draft';
  document.querySelector('#event-date').value = eventDateInput(item?.date);
  document.querySelector('#event-venue').value = item?.venue || '';
  document.querySelector('#event-room').value = item?.room || '';
  document.querySelector('#event-setup').value = eventTimeInput(item?.setupStart);
  document.querySelector('#event-admission').value = eventTimeInput(item?.admissionStart);
  document.querySelector('#event-start').value = eventTimeInput(item?.eventStart);
  document.querySelector('#event-break').value = eventTimeInput(item?.breakStart);
  document.querySelector('#event-break-end').value = eventTimeInput(item?.breakEnd);
  document.querySelector('#event-end').value = eventTimeInput(item?.eventEnd);
  document.querySelector('#event-description').value = item?.description || '';
  document.querySelector('#event-public-notes').value = item?.publicNotes || '';
  document.querySelector('#event-internal-notes').value = item?.internalNotes || '';
  document.querySelector('#event-ticket-url').value = item?.ticketUrl || '';
  document.querySelector('#event-remaining-tickets').value = item?.remainingTickets || '';
  document.querySelector('#event-image-url').value = item?.imageUrl || '';
  document.querySelector('#event-box-office').checked = Boolean(item?.boxOfficeAvailable);
  document.querySelector('#event-box-office-open').value = eventTimeInput(item?.boxOfficeOpenAt);
  document.querySelector('#archive-event-button').hidden = !item || state.currentUser?.role === 'viewer';
  document.querySelector('#delete-event-button').hidden = !item || state.currentUser?.role !== 'admin';
  state.currentScheduleEventId=item?.id||null;
  state.eventSchedule=[];
  document.querySelector('#event-schedule-section').hidden=!item;
  document.querySelector('#new-event-schedule-entry').hidden=state.currentUser?.role==='viewer';
  if(item)await loadEventSchedule(item.id);
  document.querySelector('#event-dialog').showModal();
}

const scheduleCategoryLabels={access:'Zugang',setup:'Aufbau',rehearsal:'Probe',event:'Veranstaltung',break:'Pause',hospitality:'Gastronomie',teardown:'Abbau',internal:'Intern',cancelled:'Storniert',other:'Sonstiges'};
function scheduleDateTimeInput(value){if(!value)return'';const date=new Date(value),offset=date.getTimezoneOffset()*60000;return new Date(date.getTime()-offset).toISOString().slice(0,16);}
function renderEventSchedule(){
  const search=document.querySelector('#event-schedule-search').value.trim().toLowerCase(),room=document.querySelector('#event-schedule-room-filter').value,category=document.querySelector('#event-schedule-category-filter').value,type=document.querySelector('#event-schedule-type-filter').value;
  const entries=state.eventSchedule.filter(entry=>(!search||[entry.displayCaption,entry.type,entry.roomName,entry.roomConfiguration].some(value=>String(value||'').toLowerCase().includes(search)))&&(!room||entry.roomName===room)&&(!category||entry.category===category)&&(!type||entry.type===type));
  document.querySelector('#event-schedule-list').innerHTML=entries.map(entry=>{
    const date=new Date(entry.startAt),time=new Intl.DateTimeFormat('de-DE',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit'}).format(date),day=new Intl.DateTimeFormat('de-DE',{timeZone:'Europe/Berlin',day:'2-digit',month:'2-digit'}).format(date),editable=entry.source==='manual'&&state.currentUser?.role!=='viewer';
    return `<div class="event-schedule-row"><span class="event-schedule-time">${entry.isApproximate?'ca. ':''}${escapeHtml(time)}<small>${escapeHtml(day)}</small></span><span><strong>${escapeHtml(entry.displayCaption)}</strong><small class="${entry.category==='other'?'event-schedule-unknown':''}">${escapeHtml(entry.type)}${entry.category==='other'?' · nicht zugeordnet':''}</small></span><span>${escapeHtml(entry.roomName||'–')}<small>${escapeHtml(entry.roomConfiguration||'')}</small></span><span><i class="tag muted">${escapeHtml(scheduleCategoryLabels[entry.category]||entry.category)}</i><small class="event-schedule-source">${entry.source==='easyjob'?'easyjob · schreibgeschützt':'Manuell'}</small></span><span class="event-schedule-actions">${editable?`<button type="button" data-edit-schedule="${escapeHtml(entry.id)}">Bearbeiten</button><button type="button" data-delete-schedule="${escapeHtml(entry.id)}">Löschen</button>`:''}</span></div>`;
  }).join('')||'<p class="event-table-empty">Keine passenden Ablaufpunkte vorhanden.</p>';
}
async function loadEventSchedule(eventId){
  try{
    const result=await apiRequest(`/api/events/${encodeURIComponent(eventId)}/schedule`);
    state.eventSchedule=result.data||[];
    const rooms=[...new Set(state.eventSchedule.map(entry=>entry.roomName).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'de')),types=[...new Set(state.eventSchedule.map(entry=>entry.type))].sort((a,b)=>a.localeCompare(b,'de')),categories=[...new Set(state.eventSchedule.map(entry=>entry.category))];
    document.querySelector('#event-schedule-room-filter').innerHTML='<option value="">Alle Räume</option>'+rooms.map(value=>`<option>${escapeHtml(value)}</option>`).join('');
    document.querySelector('#event-schedule-type-filter').innerHTML='<option value="">Alle Typen</option>'+types.map(value=>`<option>${escapeHtml(value)}</option>`).join('');
    document.querySelector('#event-schedule-category-filter').innerHTML='<option value="">Alle Kategorien</option>'+categories.map(value=>`<option value="${escapeHtml(value)}">${escapeHtml(scheduleCategoryLabels[value]||value)}</option>`).join('');
    renderEventSchedule();
  }catch(error){document.querySelector('#event-schedule-list').innerHTML=`<p class="auth-message">${escapeHtml(error.message)}</p>`;}
}
function openScheduleEditor(entry=null){
  document.querySelector('#event-schedule-form').reset();document.querySelector('#event-schedule-entry-id').value=entry?.id||'';
  document.querySelector('#event-schedule-dialog-title').textContent=entry?'Ablaufpunkt bearbeiten':'Ablaufpunkt anlegen';
  document.querySelector('#event-schedule-type').value=entry?.type||'';document.querySelector('#event-schedule-caption').value=entry?.caption||'';
  document.querySelector('#event-schedule-start').value=scheduleDateTimeInput(entry?.startAt);document.querySelector('#event-schedule-end').value=scheduleDateTimeInput(entry?.endAt);
  document.querySelector('#event-schedule-room').value=entry?.roomName||'';document.querySelector('#event-schedule-room-configuration').value=entry?.roomConfiguration||'';
  document.querySelector('#event-schedule-category').value=entry?.category||'';document.querySelector('#event-schedule-approximate').checked=Boolean(entry?.isApproximate);
  document.querySelector('#event-schedule-dialog').showModal();
}
document.querySelector('#new-event-schedule-entry').addEventListener('click',()=>openScheduleEditor());
['event-schedule-search','event-schedule-room-filter','event-schedule-category-filter','event-schedule-type-filter'].forEach(id=>document.querySelector(`#${id}`).addEventListener(id.endsWith('search')?'input':'change',renderEventSchedule));
document.querySelector('#event-schedule-list').addEventListener('click',async event=>{
  const edit=event.target.closest('[data-edit-schedule]'),remove=event.target.closest('[data-delete-schedule]');
  if(edit)openScheduleEditor(state.eventSchedule.find(entry=>entry.id===edit.dataset.editSchedule));
  if(remove&&confirm('Diesen manuellen Ablaufpunkt löschen?')){try{await apiRequest(`/api/events/${encodeURIComponent(state.currentScheduleEventId)}/schedule/${encodeURIComponent(remove.dataset.deleteSchedule)}`,{method:'DELETE'});await loadEventSchedule(state.currentScheduleEventId);showToast('Ablaufpunkt gelöscht.');}catch(error){showToast(error.message);}}
});
document.querySelector('#event-schedule-form').addEventListener('submit',async event=>{
  event.preventDefault();const id=document.querySelector('#event-schedule-entry-id').value,payload={type:document.querySelector('#event-schedule-type').value.trim(),caption:document.querySelector('#event-schedule-caption').value.trim(),startAt:new Date(document.querySelector('#event-schedule-start').value).toISOString(),endAt:document.querySelector('#event-schedule-end').value?new Date(document.querySelector('#event-schedule-end').value).toISOString():undefined,roomName:document.querySelector('#event-schedule-room').value.trim(),roomConfiguration:document.querySelector('#event-schedule-room-configuration').value.trim(),category:document.querySelector('#event-schedule-category').value||undefined,isApproximate:document.querySelector('#event-schedule-approximate').checked};
  try{await apiRequest(`/api/events/${encodeURIComponent(state.currentScheduleEventId)}/schedule${id?`/${encodeURIComponent(id)}`:''}`,{method:id?'PATCH':'POST',body:JSON.stringify(payload)});document.querySelector('#event-schedule-dialog').close();await loadEventSchedule(state.currentScheduleEventId);showToast('Ablaufpunkt gespeichert.');}catch(error){showToast(error.message);}
});

function collectEventEditor() {
  const date = document.querySelector('#event-date').value;
  return { title: document.querySelector('#event-title').value.trim(), subtitle: document.querySelector('#event-subtitle').value.trim(), organizer:document.querySelector('#event-organizer').value.trim(),eventType:document.querySelector('#event-type').value.trim(), status: document.querySelector('#event-status').value, date: date ? date.replaceAll('-', '') : undefined, venue: document.querySelector('#event-venue').value.trim(), room: document.querySelector('#event-room').value.trim(), setupStart:eventTimestamp(date,document.querySelector('#event-setup').value), admissionStart: eventTimestamp(date, document.querySelector('#event-admission').value), eventStart: eventTimestamp(date, document.querySelector('#event-start').value), breakStart: eventTimestamp(date, document.querySelector('#event-break').value), breakEnd:eventTimestamp(date,document.querySelector('#event-break-end').value), eventEnd: eventTimestamp(date, document.querySelector('#event-end').value), description: document.querySelector('#event-description').value.trim(), publicNotes: document.querySelector('#event-public-notes').value.trim(), internalNotes: document.querySelector('#event-internal-notes').value.trim(), ticketUrl: document.querySelector('#event-ticket-url').value.trim(), remainingTickets: document.querySelector('#event-remaining-tickets').value.trim(), imageUrl: document.querySelector('#event-image-url').value.trim(), boxOfficeAvailable: document.querySelector('#event-box-office').checked, boxOfficeOpenAt: eventTimestamp(date, document.querySelector('#event-box-office-open').value) };
}

document.querySelector('#event-search').addEventListener('input', renderEvents);
document.querySelector('#event-status-filter').addEventListener('change', renderEvents);
document.querySelector('#event-object-filter').addEventListener('change', renderEvents);
document.querySelector('#event-table').addEventListener('click', event => {
  const button = event.target.closest('[data-edit-event]');
  if (button) openEventEditor(state.events.find(item => item.id === button.dataset.editEvent));
});
document.querySelector('#event-table').addEventListener('change',event=>{
  if(event.target.id==='event-select-all'){visibleEventRows().forEach(item=>event.target.checked?state.selectedEvents.add(item.id):state.selectedEvents.delete(item.id));renderEvents();return;}
  if(event.target.matches('[data-event-select]')){event.target.checked?state.selectedEvents.add(event.target.dataset.eventSelect):state.selectedEvents.delete(event.target.dataset.eventSelect);renderEvents();}
});
function renderEventBulkFields(){
  const field=document.querySelector('#event-bulk-field').value,isBreak=field==='breakStart',isImage=field==='imageUrl',timeBox=document.querySelector('[data-event-bulk-time]'),breakBox=document.querySelector('[data-event-bulk-break]'),imageBox=document.querySelector('[data-event-bulk-image]'),time=document.querySelector('#event-bulk-time'),breakStart=document.querySelector('#event-bulk-break-start'),breakEnd=document.querySelector('#event-bulk-break-end');
  timeBox.hidden=isBreak||isImage;breakBox.hidden=!isBreak;imageBox.hidden=!isImage;time.disabled=isBreak||isImage;time.required=!isBreak&&!isImage;breakStart.disabled=!isBreak;breakStart.required=isBreak;breakEnd.disabled=!isBreak;
  document.querySelector('#event-bulk-time-label').textContent=field==='admissionStart'?'Anwarts-/Einlasszeit':field==='eventStart'?'Beginnzeit':'Endzeit';
}
function openEventBulkDialog(){
  const selected=state.events.filter(item=>state.selectedEvents.has(item.id)),imported=selected.filter(item=>item.sourceId);if(!imported.length)return;
  document.querySelector('#event-bulk-form').reset();document.querySelector('#event-bulk-image-url').value='';document.querySelector('#event-bulk-image-preview').textContent='Noch kein Bild ausgewählt';document.querySelector('#event-bulk-selection-note').textContent=`${imported.length} importierte Veranstaltung${imported.length===1?'':'en'} ausgewählt${selected.length>imported.length?` · ${selected.length-imported.length} manuelle werden übersprungen`:''}.`;renderEventBulkFields();document.querySelector('#event-bulk-dialog').showModal();
}
document.querySelector('#bulk-edit-events').addEventListener('click',openEventBulkDialog);
document.querySelector('#event-bulk-field').addEventListener('change',renderEventBulkFields);
document.querySelector('#event-bulk-image-select').addEventListener('click',async()=>{try{await refreshMedia();openMediaLibrary(asset=>{const imageUrl=String(asset.src||'').startsWith('data:image/')?`/media/library/${encodeURIComponent(asset.id)}`:asset.src;document.querySelector('#event-bulk-image-url').value=imageUrl;document.querySelector('#event-bulk-image-preview').innerHTML=`<img src="${escapeHtml(asset.src)}" alt="${escapeHtml(asset.name||'Ausgewähltes Veranstaltungsbild')}">`;},'image-only','Veranstaltungsbild für die ausgewählten Termine auswählen oder hochladen');}catch(error){showToast(error.message);}});
document.querySelector('#event-bulk-form').addEventListener('submit',async event=>{
  event.preventDefault();const ids=[...state.selectedEvents],field=document.querySelector('#event-bulk-field').value,payload={ids,action:'update',field};
  if(field==='breakStart'){payload.time=document.querySelector('#event-bulk-break-start').value;payload.breakEndTime=document.querySelector('#event-bulk-break-end').value||undefined;}
  else if(field==='imageUrl'){payload.imageUrl=document.querySelector('#event-bulk-image-url').value;if(!payload.imageUrl){showToast('Bitte zuerst ein Veranstaltungsbild auswählen.');return;}}
  else payload.time=document.querySelector('#event-bulk-time').value;
  const button=event.submitter;button.disabled=true;
  try{const result=await apiRequest('/api/events/bulk',{method:'POST',body:JSON.stringify(payload)});document.querySelector('#event-bulk-dialog').close();state.selectedEvents.clear();await Promise.all([loadEvents(),loadContent()]);const skipped=Number(result.skippedManual||0)+Number(result.skippedWithoutDate||0);showToast(`${result.count} importierte Veranstaltung${result.count===1?'':'en'} aktualisiert${skipped?` · ${skipped} übersprungen`:''}.`);}catch(error){showToast(error.message);}finally{button.disabled=false;}
});
async function bulkEvents(action){const ids=[...state.selectedEvents];if(!ids.length)return;const label=action==='archive'?'archivieren':'endgültig löschen';if(!confirm(`${ids.length} gewählte Veranstaltung${ids.length===1?'':'en'} ${label}?`))return;try{await apiRequest('/api/events/bulk',{method:'POST',body:JSON.stringify({ids,action})});state.selectedEvents.clear();await Promise.all([loadEvents(),loadContent()]);showToast(`${ids.length} Veranstaltung${ids.length===1?'':'en'} wurde${ids.length===1?'':'n'} verarbeitet.`);}catch(error){showToast(error.message);}}
document.querySelector('#bulk-archive-events').addEventListener('click',()=>bulkEvents('archive'));
document.querySelector('#bulk-delete-events').addEventListener('click',()=>bulkEvents('delete'));
document.querySelector('#event-form').addEventListener('submit', async event => {
  event.preventDefault();
  const id = document.querySelector('#event-id').value;
  const button = document.querySelector('#save-event-button'); button.disabled = true;
  try {
    await apiRequest(id ? `/api/events/${encodeURIComponent(id)}` : '/api/events', { method: id ? 'PUT' : 'POST', body: JSON.stringify(collectEventEditor()) });
    document.querySelector('#event-dialog').close();
    await loadEvents();
    showToast(id ? 'Veranstaltung wurde aktualisiert.' : 'Veranstaltung wurde angelegt.');
  } catch (error) { showToast(error.message); }
  finally { button.disabled = false; }
});
document.querySelector('#archive-event-button').addEventListener('click', async () => {
  const id = document.querySelector('#event-id').value;
  const title = document.querySelector('#event-title').value.trim();
  if (!id || !window.confirm(`Veranstaltung „${title}“ wirklich archivieren? Sie kann im Papierkorb wiederhergestellt werden.`)) return;
  try {
    await apiRequest(`/api/events/${encodeURIComponent(id)}`, { method: 'DELETE' });
    document.querySelector('#event-dialog').close();
    await loadEvents();
    showToast(`Veranstaltung „${title}“ wurde archiviert.`);
  } catch (error) { showToast(error.message); }
});
document.querySelector('#delete-event-button').addEventListener('click', async () => {
  const id = document.querySelector('#event-id').value;
  const title = document.querySelector('#event-title').value.trim();
  if (!id || !window.confirm(`Veranstaltung „${title}“ endgültig löschen? Verknüpfte Slides und Kanäle bleiben erhalten, verlieren aber ihre Veranstaltungszuordnung. Dieser Vorgang kann nicht rückgängig gemacht werden.`)) return;
  try {
    await apiRequest(`/api/events/${encodeURIComponent(id)}/permanent`, { method: 'DELETE' });
    document.querySelector('#event-dialog').close();
    await Promise.all([loadEvents(), loadContent()]);
    showToast(`Veranstaltung „${title}“ wurde endgültig gelöscht.`);
  } catch (error) { showToast(error.message); }
});

function initials(name) {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'K';
}

function showAuthMessage(id, message, success = false) {
  const element = document.querySelector(`#${id}`);
  element.textContent = message;
  element.classList.toggle('is-success', success);
}

function applyCurrentUser(user) {
  state.currentUser = user;
  updateDashboardHeading();
  document.querySelector('#profile-name').textContent = user.displayName;
  document.querySelector('#profile-role').textContent = roleLabels[user.role];
  document.querySelector('#profile-avatar').textContent = initials(user.displayName);
  document.querySelector('#integration-user-name').textContent = user.displayName;
  document.querySelector('#integration-user-avatar').textContent = initials(user.displayName);
  document.querySelector('#account-name').textContent = user.displayName;
  document.querySelector('#account-email').textContent = `@${user.username}${user.email ? ` · ${user.email}` : ''} · ${roleLabels[user.role]}`;
  document.querySelector('#account-dialog-close').hidden = user.mustChangePassword;
  document.body.dataset.userRole = user.role;
  document.querySelectorAll('.admin-only').forEach(element => { element.hidden = user.role !== 'admin'; });
  applyNavigationConfig(state.featureSettings || {}, state.navigationOrder || []);
  document.querySelector('#auth-screen').hidden = true;
  document.querySelector('#app-shell').hidden = false;
  if(user.role==='admin'){loadIntegrationSources();loadUpdates();}
  const requestedView = kioskyRuntime.initialView || window.location.hash.slice(1);
  setView(pageMeta[requestedView] ? requestedView : 'dashboard');
  loadFeatureSettings();
  if (user.mustChangePassword) {
    showAuthMessage('account-message', 'Bitte ändere das Startpasswort, bevor du weiterarbeitest.');
    document.querySelector('#account-dialog').showModal();
  }
}
setInterval(()=>updateDashboardHeading(),60_000);

function setAuthView(view) {
  document.querySelector('#auth-status-view').hidden = view !== 'status';
  document.querySelector('#login-view').hidden = view !== 'login';
  document.querySelector('#setup-view').hidden = view !== 'setup';
  document.querySelector('#invite-view').hidden = view !== 'invite';
}

async function showInvitation(token) {
  document.querySelector('#app-shell').hidden = true;
  document.querySelector('#auth-screen').hidden = false;
  setAuthView('invite');
  showAuthMessage('invite-message', 'Einladung wird geprüft …');
  try {
    const result = await apiRequest(`/api/auth/invitations/${encodeURIComponent(token)}`);
    document.querySelector('#invite-summary').textContent = `Einladung für ${result.invitation.email} als ${roleLabels[result.invitation.role]}.`;
    document.querySelector('#invite-form').dataset.token = token;
    showAuthMessage('invite-message', '');
  } catch (error) {
    showAuthMessage('invite-message', error.message);
    document.querySelector('#invite-form button[type="submit"]').disabled = true;
  }
}

async function showLogin() {
  state.currentUser = null;
  document.querySelector('#app-shell').hidden = true;
  document.querySelector('#auth-screen').hidden = false;
  setAuthView('status');
  document.querySelector('#auth-status-title').textContent = 'Kiosky wird vorbereitet';
  document.querySelector('#auth-status-message').textContent = 'Der Einrichtungsstatus wird geprüft …';
  document.querySelector('#auth-status-retry').hidden = true;
  try {
    const status = await apiRequest('/api/auth/setup-status');
    setAuthView(status.needsSetup ? 'setup' : 'login');
  } catch (error) {
    document.querySelector('#auth-status-title').textContent = 'Einrichtung konnte nicht geladen werden';
    document.querySelector('#auth-status-message').textContent = 'Der Kiosky-Server ist nicht erreichbar oder noch nicht aktualisiert. Bitte den Server neu starten und anschließend erneut prüfen.';
    document.querySelector('#auth-status-retry').hidden = false;
  }
}

async function initializeAuthentication() {
  const invitationToken = new URLSearchParams(window.location.search).get('invite');
  if (invitationToken) { await showInvitation(invitationToken); return; }
  try {
    const result = await apiRequest('/api/auth/me');
    applyCurrentUser(result.user);
  } catch (error) {
    await showLogin();
  }
}

document.querySelector('#login-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  showAuthMessage('login-message', 'Anmeldung wird geprüft …');
  try {
    const result = await apiRequest('/api/auth/login', { method: 'POST', body: JSON.stringify({ identifier: document.querySelector('#login-email').value, password: document.querySelector('#login-password').value }) });
    form.reset();
    showAuthMessage('login-message', '');
    applyCurrentUser(result.user);
  } catch (error) {
    showAuthMessage('login-message', error.message);
  } finally { button.disabled = false; }
});

document.querySelector('#invite-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget, password = document.querySelector('#invite-password').value;
  if (password !== document.querySelector('#invite-password-confirm').value) { showAuthMessage('invite-message', 'Die Passwörter stimmen nicht überein.'); return; }
  const button = form.querySelector('button[type="submit"]'); button.disabled = true;
  try {
    const result = await apiRequest(`/api/auth/invitations/${encodeURIComponent(form.dataset.token)}`, { method: 'POST', body: JSON.stringify({ displayName: document.querySelector('#invite-name').value, username: document.querySelector('#invite-username').value, password }) });
    window.history.replaceState({}, '', window.location.pathname);
    form.reset(); applyCurrentUser(result.user); showToast('Dein Kiosky-Konto ist eingerichtet.');
  } catch (error) { showAuthMessage('invite-message', error.message); button.disabled = false; }
});

document.querySelector('#auth-status-retry').addEventListener('click', showLogin);
document.querySelector('#auth-setup-check').addEventListener('click', showLogin);

document.querySelector('#setup-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const password = document.querySelector('#setup-password').value;
  if (password !== document.querySelector('#setup-password-confirm').value) { showAuthMessage('setup-message', 'Die Passwörter stimmen nicht überein.'); return; }
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const result = await apiRequest('/api/auth/setup', { method: 'POST', body: JSON.stringify({ displayName: document.querySelector('#setup-name').value, email: document.querySelector('#setup-email').value, password }) });
    form.reset();
    applyCurrentUser(result.user);
  } catch (error) {
    showAuthMessage('setup-message', error.message);
  } finally { button.disabled = false; }
});

document.querySelector('#profile-button').addEventListener('click', () => document.querySelector('#account-dialog').showModal());
document.querySelector('#account-dialog').addEventListener('cancel', event => { if (state.currentUser?.mustChangePassword) event.preventDefault(); });
document.querySelectorAll('[data-action="logout"]').forEach(button => button.addEventListener('click', async () => {
  await apiRequest('/api/auth/logout', { method: 'POST' });
  document.querySelector('#account-dialog').close();
  await showLogin();
}));

document.querySelector('#change-password-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const newPassword = document.querySelector('#new-password').value;
  if (newPassword !== document.querySelector('#new-password-confirm').value) { showAuthMessage('account-message', 'Die neuen Passwörter stimmen nicht überein.'); return; }
  try {
    const result = await apiRequest('/api/auth/password', { method: 'POST', body: JSON.stringify({ currentPassword: document.querySelector('#current-password').value, newPassword }) });
    state.currentUser = result.user;
    document.querySelector('#account-dialog-close').hidden = false;
    form.reset();
    showAuthMessage('account-message', 'Passwort wurde erfolgreich geändert.', true);
    setTimeout(() => document.querySelector('#account-dialog').close(), 900);
  } catch (error) { showAuthMessage('account-message', error.message); }
});

// CrewBrain administration
const crewbrainForm = document.querySelector('#crewbrain-config-form');
const crewbrainFields = document.querySelector('#crewbrain-config-fields');
const crewbrainTokenInput = document.querySelector('#crewbrain-access-token');
const crewbrainUsernameInput = document.querySelector('#crewbrain-username');
const crewbrainPasswordInput = document.querySelector('#crewbrain-password');
const crewbrainAccessMessage = document.querySelector('#crewbrain-access-message');
const crewbrainConnectionBadge = document.querySelector('#crewbrain-connection-badge');
const crewbrainSaveState = document.querySelector('#crewbrain-save-state');
const crewbrainPreviewButton = document.querySelector('#crewbrain-preview-button');
const crewbrainPreviewMessage = document.querySelector('#crewbrain-preview-message');
const crewbrainConfigImportFile = document.querySelector('#crewbrain-config-import-file');
let crewbrainPreviewRequestId = 0;

state.crewbrainConfigLoaded = false;
state.crewbrainHasCredential = false;
state.crewbrainCredentialUnreadable = false;

function setConnectionMessage(message, type = '') {
  crewbrainAccessMessage.textContent = message;
  crewbrainAccessMessage.className = `connection-message${type ? ` is-${type}` : ''}`;
}

function setConnectionBadge(label, tone = 'muted') {
  crewbrainConnectionBadge.textContent = label;
  crewbrainConnectionBadge.className = `tag ${tone}`;
}

async function crewbrainApi(path, options = {}) {
  return apiRequest(path, options);
}

function normalizeCrewBrainUrl(value) {
  const trimmed = value.trim().replace(/\/+$/, '');
  const withProtocol = /^https:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
  return /\/api\/v2$/i.test(withProtocol) ? withProtocol : `${withProtocol}/api/v2`;
}

function crewbrainAuthMethod() {
  return document.querySelector('input[name="crewbrain-auth-method"]:checked').value;
}

function setCrewBrainAuthMethod(method) {
  document.querySelectorAll('input[name="crewbrain-auth-method"]').forEach(input => { input.checked = input.value === method; });
  document.querySelector('#crewbrain-token-panel').hidden = method !== 'token';
  document.querySelector('#crewbrain-login-panel').hidden = method !== 'login';
}

function fillCrewBrainForm(config, mapping) {
  document.querySelector('#crewbrain-base-url').value = config.baseUrl || 'https://shwz.crewbrain.com/api/v2';
  document.querySelector('#crewbrain-auto-import-time').value = config.autoImportTime || '03:00';
  document.querySelector('#crewbrain-lookback').value = String(config.lookbackDays ?? 30);
  document.querySelector('#crewbrain-lookahead').value = String(config.lookaheadDays ?? 365);
  document.querySelector('#crewbrain-page-size').value = String(config.pageSize || 100);
  document.querySelector('#crewbrain-import-strategy').value = config.importStrategy || 'project';
  document.querySelector('#crewbrain-sync-enabled').checked = Boolean(config.syncEnabled);
  document.querySelector('#crewbrain-auto-import-time').disabled = !config.syncEnabled;
  document.querySelector('#crewbrain-auto-channels').checked = Boolean(config.autoCreateChannels);
  document.querySelector('#crewbrain-auto-slides').checked = Boolean(config.autoCreateSlides);
  document.querySelectorAll('[data-mapping-target]').forEach(select => {
    if (mapping[select.dataset.mappingTarget] && [...select.options].some(option => option.value === mapping[select.dataset.mappingTarget])) select.value = mapping[select.dataset.mappingTarget];
  });
  state.crewbrainHasCredential = Boolean(config.hasCredential);
  state.crewbrainCredentialUnreadable = Boolean(config.credentialUnreadable);
  crewbrainTokenInput.value = '';
  crewbrainUsernameInput.value = '';
  crewbrainPasswordInput.value = '';
  crewbrainTokenInput.placeholder = state.crewbrainHasCredential ? 'Token ist sicher gespeichert · leer lassen zum Beibehalten' : 'CrewBrain-Access-Token eingeben';
  document.querySelector('#crewbrain-token-hint').textContent = state.crewbrainCredentialUnreadable ? 'Der bisherige Zugang kann nicht mehr entschlüsselt werden und muss ersetzt werden.' : state.crewbrainHasCredential ? 'Ein Zugang ist hinterlegt. Ein neuer Wert ersetzt den bisherigen Token.' : 'Der Token wird verschlüsselt gespeichert und danach nicht mehr angezeigt.';
  setCrewBrainAuthMethod(state.crewbrainHasCredential ? 'token' : 'login');
  const tenant = (() => { try { return new URL(config.baseUrl).hostname; } catch (error) { return '–'; } })();
  document.querySelector('#crewbrain-tenant-label').textContent = tenant;
  document.querySelector('#crewbrain-version-label').textContent = config.lastApiVersion || 'Noch nicht getestet';
  document.querySelector('#crewbrain-sync-label').textContent = config.syncEnabled ? `Täglich um ${config.autoImportTime || '03:00'} Uhr` : 'Deaktiviert';
  document.querySelector('#crewbrain-last-sync').textContent = config.lastSyncAt ? `Letzter Auto-Import ${new Date(config.lastSyncAt).toLocaleString('de-DE')}` : config.lastSuccessAt ? `Verbindung geprüft ${new Date(config.lastSuccessAt).toLocaleString('de-DE')}` : 'Noch kein erfolgreicher Verbindungstest';
  crewbrainFields.disabled = false;
  crewbrainPreviewButton.disabled = !state.crewbrainHasCredential;
  crewbrainSaveState.textContent = state.crewbrainCredentialUnreadable ? 'Zugang ersetzen' : 'Geladen';
  crewbrainSaveState.className = `tag ${state.crewbrainCredentialUnreadable ? 'coral' : 'green'}`;
  setConnectionBadge(state.crewbrainCredentialUnreadable ? 'Neuer Zugang nötig' : config.lastError ? 'Prüfung fehlgeschlagen' : config.lastSuccessAt ? 'Verbunden' : 'Konfiguriert', state.crewbrainCredentialUnreadable || config.lastError ? 'coral' : config.lastSuccessAt ? 'green' : 'blue');
}

async function loadCrewBrainConfiguration() {
  setConnectionMessage('Konfiguration wird geladen …');
  try {
    const [config, mapping] = await Promise.all([crewbrainApi('/api/integrations/crewbrain'), crewbrainApi('/api/integrations/crewbrain/mapping')]);
    fillCrewBrainForm(config, mapping);
    await loadIntegrationSources();
    state.crewbrainConfigLoaded = true;
    setConnectionMessage(config.credentialUnreadable ? 'Der Verschlüsselungsschlüssel wurde geändert. Bitte Access-Token oder CrewBrain-Login neu hinterlegen.' : 'Administrationszugang aktiv', config.credentialUnreadable ? 'error' : 'success');
    return true;
  } catch (error) {
    state.crewbrainConfigLoaded = false;
    crewbrainFields.disabled = true;
    setConnectionBadge('Nicht verbunden', 'coral');
    setConnectionMessage(error.message, 'error');
    return false;
  }
}

function collectFieldMapping() {
  return Object.fromEntries([...document.querySelectorAll('[data-mapping-target]')].map(select => [select.dataset.mappingTarget, select.value]));
}

function collectCrewBrainConfig() {
  const baseUrl = normalizeCrewBrainUrl(document.querySelector('#crewbrain-base-url').value);
  document.querySelector('#crewbrain-base-url').value = baseUrl;
  const token = crewbrainTokenInput.value.trim();
  return {
    baseUrl,
    documentationUrl: baseUrl,
    authType: 'api_key',
    ...(token ? { credential: { apiKey: token } } : {}),
    syncEnabled: document.querySelector('#crewbrain-sync-enabled').checked,
    syncIntervalMinutes: 1440,
    autoImportTime: document.querySelector('#crewbrain-auto-import-time').value,
    lookbackDays: Number(document.querySelector('#crewbrain-lookback').value),
    lookaheadDays: Number(document.querySelector('#crewbrain-lookahead').value),
    pageSize: Number(document.querySelector('#crewbrain-page-size').value),
    importStrategy: document.querySelector('#crewbrain-import-strategy').value,
    titleExclusions: state.titleExclusions,
    autoCreateChannels: document.querySelector('#crewbrain-auto-channels').checked,
    autoCreateSlides: document.querySelector('#crewbrain-auto-slides').checked
  };
}

async function saveCrewBrainConfiguration(silent = false) {
  if (!crewbrainForm.reportValidity()) return false;
  const authMethod = crewbrainAuthMethod();
  if (authMethod === 'token' && !state.crewbrainHasCredential && !crewbrainTokenInput.value.trim()) { crewbrainTokenInput.focus(); showToast('Bitte einen CrewBrain-Access-Token eingeben.'); return false; }
  if (authMethod === 'login' && (!crewbrainUsernameInput.value.trim() || !crewbrainPasswordInput.value)) { (!crewbrainUsernameInput.value.trim() ? crewbrainUsernameInput : crewbrainPasswordInput).focus(); showToast('Bitte CrewBrain-Benutzername und Passwort eingeben.'); return false; }
  crewbrainSaveState.textContent = 'Speichert …';
  crewbrainSaveState.className = 'tag muted';
  try {
    const connection = collectCrewBrainConfig();
    let config;
    if (authMethod === 'login') {
      const username = crewbrainUsernameInput.value.trim();
      const password = crewbrainPasswordInput.value;
      crewbrainPasswordInput.value = '';
      config = await crewbrainApi('/api/integrations/crewbrain/access-token', { method: 'POST', body: JSON.stringify({ ...connection, username, password, credential: undefined }) });
    } else {
      config = await crewbrainApi('/api/integrations/crewbrain', { method: 'PUT', body: JSON.stringify(connection) });
    }
    const mapping = await crewbrainApi('/api/integrations/crewbrain/mapping', { method: 'PUT', body: JSON.stringify(collectFieldMapping()) });
    fillCrewBrainForm(config, mapping);
    await loadIntegrationSources();
    state.crewbrainConfigLoaded = true;
    setConnectionMessage('Konfiguration sicher gespeichert', 'success');
    if (!silent) showToast('CrewBrain-Konfiguration wurde gespeichert.');
    return true;
  } catch (error) {
    crewbrainSaveState.textContent = 'Fehler';
    crewbrainSaveState.className = 'tag coral';
    setConnectionMessage(error.message, 'error');
    showToast(error.message);
    return false;
  }
}

document.querySelectorAll('input[name="crewbrain-auth-method"]').forEach(input => input.addEventListener('change', () => setCrewBrainAuthMethod(input.value)));
document.querySelector('#crewbrain-sync-enabled').addEventListener('change', event => {
  document.querySelector('#crewbrain-auto-import-time').disabled = !event.currentTarget.checked;
});

document.querySelector('[data-action="focus-crewbrain-config"]').addEventListener('click', () => {
  document.querySelector('#crewbrain-config-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
  if (crewbrainFields.disabled) loadCrewBrainConfiguration();
});
crewbrainForm.addEventListener('submit', async event => { event.preventDefault(); await saveCrewBrainConfiguration(); });

function normalizeEasyJobUrl(value){
  const input=value.trim();
  if(!input)return'';
  const withProtocol=/^https?:\/\//i.test(input)?input:`https://${input}`;
  const url=new URL(withProtocol);
  url.pathname=url.pathname.replace(/\/(?:token|api\.json(?:\/.*)?)\/?$/i,'').replace(/\/+$/,'')+'/';
  url.search='';
  url.hash='';
  return url.toString();
}
async function loadEasyJobConfiguration(){
  try{
    const [config,mapping,typeRules]=await Promise.all([apiRequest('/api/integrations/easyjob'),apiRequest('/api/integrations/easyjob/mapping'),apiRequest('/api/integrations/easyjob/schedule/types')]);
    document.querySelector('#easyjob-base-url').value=config.baseUrl||'';
    document.querySelector('#easyjob-import-mode').value=config.importMode||'projects_with_jobs';
    document.querySelector('#easyjob-auto-import-time').value=config.autoImportTime||'03:00';
    document.querySelector('#easyjob-lookback').value=config.lookbackDays??30;
    document.querySelector('#easyjob-lookahead').value=config.lookaheadDays??365;
    document.querySelector('#easyjob-allow-insecure-http').checked=Boolean(config.allowInsecureHttp);
    document.querySelector('#easyjob-allow-self-signed-certificate').checked=Boolean(config.allowSelfSignedCertificate);
    document.querySelector('#easyjob-sync-enabled').checked=Boolean(config.syncEnabled);
    document.querySelector('#easyjob-auto-channels').checked=config.autoCreateChannels!==false;
    document.querySelectorAll('[data-easyjob-mapping-target]').forEach(select=>{
      const selected=mapping[select.dataset.easyjobMappingTarget];
      if(selected&&[...select.options].some(option=>option.value===selected))select.value=selected;
    });
    document.querySelector('#easyjob-password').placeholder=config.hasCredential?'Passwort ist verschlüsselt gespeichert':'Passwort eingeben';
    document.querySelector('#easyjob-server-label').textContent=config.baseUrl?new URL(config.baseUrl).host:'Noch nicht konfiguriert';
    document.querySelector('#easyjob-mode-label').textContent={projects:'Nur Projekte',jobs:'Nur Jobs',projects_with_jobs:'Projekte mit Jobs'}[config.importMode]||'Projekte mit Jobs';
    document.querySelector('#easyjob-connection-badge').textContent=config.lastSuccessAt?'Verbunden':config.hasCredential?'Konfiguriert':'Nicht konfiguriert';
    document.querySelector('#easyjob-connection-badge').className=`tag source-state ${config.lastSuccessAt?'green':config.hasCredential?'blue':'muted'}`;
    document.querySelector('#easyjob-save-state').textContent='Geladen';
    renderEasyJobRoomUseTypes(typeRules.data||[]);
    await loadIntegrationSources();
  }catch(error){
    document.querySelector('#easyjob-message').hidden=false;
    document.querySelector('#easyjob-message').textContent=error.message;
  }
}
function renderEasyJobRoomUseTypes(rules){
  const categories=Object.entries(scheduleCategoryLabels).map(([value,label])=>`<option value="${value}">${label}</option>`).join('');
  document.querySelector('#easyjob-room-use-types').innerHTML=rules.map(rule=>`<div class="room-use-type-row" data-room-use-type="${escapeHtml(rule.normalizedType)}" data-original-label="${escapeHtml(rule.originalLabel)}"><strong>${escapeHtml(rule.originalLabel)}<small>${escapeHtml(rule.normalizedType)}</small></strong><select data-type-rule="category">${categories}</select><label><input data-type-rule="isApproximate" type="checkbox" ${rule.isApproximate?'checked':''}> ca.</label><label><input data-type-rule="visible" type="checkbox" ${rule.visible!==false?'checked':''}> sichtbar</label><input data-type-rule="sortPriority" type="number" value="${Number(rule.sortPriority||0)}" aria-label="Sortierpriorität"></div>`).join('');
  rules.forEach(rule=>{const row=document.querySelector(`[data-room-use-type="${CSS.escape(rule.normalizedType)}"]`);if(row)row.querySelector('[data-type-rule="category"]').value=rule.category;});
}
document.querySelector('#easyjob-room-use-types').addEventListener('change',async event=>{
  const row=event.target.closest('[data-room-use-type]');if(!row)return;
  const payload={originalLabel:row.dataset.originalLabel,category:row.querySelector('[data-type-rule="category"]').value,isApproximate:row.querySelector('[data-type-rule="isApproximate"]').checked,visible:row.querySelector('[data-type-rule="visible"]').checked,sortPriority:Number(row.querySelector('[data-type-rule="sortPriority"]').value)};
  try{await apiRequest('/api/integrations/easyjob/schedule/types',{method:'PUT',body:JSON.stringify(payload)});showToast('Raumnutzungsart gespeichert.');}catch(error){showToast(error.message);}
});
function collectEasyJobFieldMapping(){return Object.fromEntries([...document.querySelectorAll('[data-easyjob-mapping-target]')].map(select=>[select.dataset.easyjobMappingTarget,select.value]));}
async function loadTitleExclusions(){
  const status=document.querySelector('#integration-title-exclusions-state');
  try{
    const result=await apiRequest('/api/integrations/title-exclusions');
    state.titleExclusions=Array.isArray(result.titleExclusions)?result.titleExclusions:[];
    document.querySelector('#integration-title-exclusions').value=state.titleExclusions.join('\n');
    status.textContent='Geladen';
    status.className='tag green';
  }catch(error){
    status.textContent='Fehler';
    status.className='tag coral';
    showToast(error.message);
  }
}
document.querySelector('#integration-title-exclusions-form').addEventListener('submit',async event=>{
  event.preventDefault();
  const titleExclusions=[...new Set(document.querySelector('#integration-title-exclusions').value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean))];
  try{
    const result=await apiRequest('/api/integrations/title-exclusions',{method:'PUT',body:JSON.stringify({titleExclusions})});
    state.titleExclusions=result.titleExclusions||[];
    document.querySelector('#integration-title-exclusions').value=state.titleExclusions.join('\n');
    document.querySelector('#integration-title-exclusions-state').textContent='Gespeichert';
    document.querySelector('#integration-title-exclusions-state').className='tag green';
    showToast('Die Titel-Ausschlussliste gilt jetzt für alle Datenquellen.');
  }catch(error){showToast(error.message);}
});
document.querySelector('[data-action="focus-easyjob-config"]').addEventListener('click',()=>document.querySelector('#easyjob-config-form').scrollIntoView({behavior:'smooth',block:'start'}));
document.querySelector('#easyjob-config-form').addEventListener('submit',async event=>{event.preventDefault();const username=document.querySelector('#easyjob-username').value.trim(),password=document.querySelector('#easyjob-password').value;let baseUrl;try{baseUrl=normalizeEasyJobUrl(document.querySelector('#easyjob-base-url').value);document.querySelector('#easyjob-base-url').value=baseUrl;}catch{showToast('Die easyjob-Serveradresse ist ungültig.');return;}const payload={baseUrl,...(username||password?{username,password}:{}),allowInsecureHttp:document.querySelector('#easyjob-allow-insecure-http').checked,allowSelfSignedCertificate:document.querySelector('#easyjob-allow-self-signed-certificate').checked,importMode:document.querySelector('#easyjob-import-mode').value,autoImportTime:document.querySelector('#easyjob-auto-import-time').value,lookbackDays:Number(document.querySelector('#easyjob-lookback').value),lookaheadDays:Number(document.querySelector('#easyjob-lookahead').value),pageSize:100,syncEnabled:document.querySelector('#easyjob-sync-enabled').checked,autoCreateChannels:document.querySelector('#easyjob-auto-channels').checked};try{await apiRequest('/api/integrations/easyjob',{method:'PUT',body:JSON.stringify(payload)});await apiRequest('/api/integrations/easyjob/mapping',{method:'PUT',body:JSON.stringify(collectEasyJobFieldMapping())});document.querySelector('#easyjob-password').value='';await loadEasyJobConfiguration();showToast('easyjob-Konfiguration und Feldzuordnung wurden gespeichert.');}catch(error){showToast(error.message);}});
document.querySelector('[data-action="test-easyjob"]').addEventListener('click',async()=>{try{const result=await apiRequest('/api/integrations/easyjob/test',{method:'POST',body:'{}'}),checks=[['Projektliste',result.projectsReadable],['Projektdetails',result.projectDetailsReadable],['Jobliste',result.jobsReadable],['Jobdetails',result.jobDetailsReadable],['Raumkalender',result.roomCalendarReadable]],failed=checks.filter(([,ok])=>ok===false);document.querySelector('#easyjob-message').hidden=false;document.querySelector('#easyjob-message').className=`connection-message ${failed.length?'is-error':'is-success'}`;document.querySelector('#easyjob-message').textContent=result.ok?`${Number(result.projectCount||0)} Projekte und ${Number(result.jobCount||0)} Jobs lesbar. ${checks.map(([name,ok])=>`${name}: ${ok===null?'nicht prüfbar':ok?'OK':'Fehler'}`).join(' · ')}${result.warnings?.length?` · ${result.warnings.join(' · ')}`:''}`:'Verbindung fehlgeschlagen.';await loadEasyJobConfiguration();}catch(error){document.querySelector('#easyjob-message').hidden=false;document.querySelector('#easyjob-message').className='connection-message is-error';document.querySelector('#easyjob-message').textContent=error.message;}});
function easyJobTime(value){if(!value)return'–';const date=new Date(value);return Number.isNaN(date.valueOf())?String(value):new Intl.DateTimeFormat('de-DE',{dateStyle:'short',timeStyle:'short',timeZone:'Europe/Berlin'}).format(date);}
function renderEasyJobFieldAnalysis(analysis={}){const panel=document.querySelector('#easyjob-field-analysis'),content=document.querySelector('#easyjob-field-analysis-content');const groups=[['Projekte',analysis.projects],['Jobs',analysis.jobs],['Raumnutzungen',analysis.roomUses]].filter(([,rows])=>rows?.length);panel.hidden=!groups.length;content.innerHTML=groups.map(([label,rows])=>`<section><h4>${label} <span class="tag muted">${rows.length} Felder</span></h4><div class="easyjob-field-table">${rows.map(field=>`<div><code>${escapeHtml(field.path)}</code><span>${escapeHtml((field.types||[]).join(', '))}</span><span>${Number(field.present||0)}×</span><small>${escapeHtml((field.samples||[]).join(' · '))}</small></div>`).join('')}</div></section>`).join('');}
function renderEasyJobPreview(data=[]){const table=document.querySelector('#easyjob-preview-table');table.innerHTML=data.map(item=>{const projectId=escapeHtml(item.easyjob.projectId),quality=item.quality||{},warnings=[...(quality.errors||[]),...(quality.warnings||[])];return`<details class="easyjob-project" ${data.length===1?'open':''}><summary><input type="checkbox" data-easyjob-project="${projectId}" ${quality.valid===false?'disabled':''}><span><strong>${escapeHtml(item.event?.title||item.easyjob.caption||'Ohne Titel')}</strong><small>${escapeHtml(item.easyjob.number||item.easyjob.projectId)} · ${easyJobTime(item.event?.eventStart)}–${easyJobTime(item.event?.eventEnd)} · ${escapeHtml(item.event?.organizer||'Veranstalter nicht hinterlegt')}</small></span><span class="tag ${quality.valid===false?'coral':'green'}">${quality.valid===false?'Prüfen':'Verarbeitbar'}</span></summary>${warnings.length?`<div class="easyjob-quality">${warnings.map(value=>`<span>${escapeHtml(value)}</span>`).join('')}</div>`:''}<div class="easyjob-job-list">${(item.jobs||[]).map(job=>`<label class="easyjob-job"><input type="checkbox" data-easyjob-job="${escapeHtml(job.easyjob.jobId)}" data-project-id="${projectId}" ${job.quality?.valid===false?'disabled':''}><span><strong>${escapeHtml(job.event.title)}</strong><small>${escapeHtml(job.easyjob.number||job.easyjob.jobId)} · ${escapeHtml(job.event.eventType||'Typ nicht hinterlegt')} · ${escapeHtml(job.event.organizer||item.event.organizer||'Veranstalter offen')}</small><small>Aufbau ${easyJobTime(job.event.setupStart)} · Einlass ${easyJobTime(job.event.admissionStart)} · Pause ${easyJobTime(job.event.breakStart)}–${easyJobTime(job.event.breakEnd)}</small></span><span>Beginn ${easyJobTime(job.event.eventStart)}<br>Ende ${easyJobTime(job.event.eventEnd)}</span><span>${(job.roomUses||[]).length} Raumnutzungen</span></label>`).join('')||'<p class="property-note">Keine Jobs im Projekt geliefert.</p>'}</div>${(item.roomUses||[]).length?`<div class="easyjob-room-uses"><strong>Raumnutzungen</strong>${item.roomUses.map(use=>`<span>${escapeHtml(use.room||'Raum')} · ${escapeHtml(use.type||'Nutzung')} · ${easyJobTime(use.start)}–${easyJobTime(use.end)}</span>`).join('')}</div>`:''}</details>`;}).join('')||'<p class="property-note">Keine passenden easyjob-Projekte gefunden.</p>';table.onchange=event=>{if(event.target.matches('[data-easyjob-project]'))table.querySelectorAll(`[data-project-id="${CSS.escape(event.target.dataset.easyjobProject)}"]:not(:disabled)`).forEach(input=>{input.checked=event.target.checked;});const checked=table.querySelector('[data-easyjob-project]:checked,[data-easyjob-job]:checked');document.querySelector('#easyjob-import-selected').disabled=!checked;};}
document.querySelector('#easyjob-preview-form').addEventListener('submit',async event=>{event.preventDefault();const table=document.querySelector('#easyjob-preview-table'),from=document.querySelector('#easyjob-preview-from').value,until=document.querySelector('#easyjob-preview-until').value;table.innerHTML='<p class="property-note">Projektdetails, Jobs und Raumkalender werden geprüft …</p>';try{const result=await apiRequest('/api/integrations/easyjob/import-preview',{method:'POST',body:JSON.stringify({from:from||undefined,until:until||undefined,search:document.querySelector('#easyjob-preview-search').value.trim(),eventType:document.querySelector('#easyjob-preview-event-type').value.trim(),objectType:document.querySelector('#easyjob-preview-object-type').value,limit:100,offset:0})});renderEasyJobPreview(result.data||[]);renderEasyJobFieldAnalysis(result.fieldAnalysis||{});}catch(error){table.innerHTML=`<p class="auth-message">${escapeHtml(error.message)}</p>`;}});
document.querySelector('#easyjob-import-selected').addEventListener('click',async event=>{const projectChecks=[...document.querySelectorAll('[data-easyjob-project]:checked')],jobChecks=[...document.querySelectorAll('[data-easyjob-job]:checked')],ids=[...new Set([...projectChecks.map(input=>input.dataset.easyjobProject),...jobChecks.map(input=>input.dataset.projectId)])],jobIds=jobChecks.map(input=>input.dataset.easyjobJob);if(!ids.length)return;const button=event.currentTarget;button.disabled=true;button.textContent='Importiert …';try{const result=await apiRequest('/api/integrations/easyjob/import',{method:'POST',body:JSON.stringify({ids,jobIds,importKind:document.querySelector('#easyjob-import-kind').value,from:document.querySelector('#easyjob-preview-from').value||undefined,until:document.querySelector('#easyjob-preview-until').value||undefined})});await Promise.all([loadEvents(),loadContent()]);const errors=(result.results||[]).filter(item=>item.action==='error');const scheduleCount=(result.results||[]).reduce((sum,item)=>sum+Number(item.schedule?.created||0)+Number(item.schedule?.updated||0),0);const summary=`${result.summary.created} neu, ${result.summary.updated} aktualisiert, ${result.summary.unchanged} unverändert, ${result.summary.excluded||0} ausgeschlossen, ${scheduleCount} Ablaufpunkte übernommen${result.summary.errors?`, ${result.summary.errors} Fehler`:''}.`;document.querySelector('#easyjob-message').hidden=false;document.querySelector('#easyjob-message').className=`connection-message ${errors.length?'is-error':'is-success'}`;document.querySelector('#easyjob-message').textContent=errors.length?`${summary} ${errors.slice(0,3).map(item=>`Projekt ${item.projectId}: ${item.reason}`).join(' · ')}`:summary;if(result.summary.created||result.summary.updated||result.summary.unchanged){setView('events');showToast('Die easyjob-Auswahl wurde unter Veranstaltungen übernommen.');}else showToast(errors[0]?.reason||'Es wurde keine Veranstaltung importiert.');}catch(error){document.querySelector('#easyjob-message').hidden=false;document.querySelector('#easyjob-message').className='connection-message is-error';document.querySelector('#easyjob-message').textContent=error.message;showToast(error.message);}finally{button.disabled=false;button.textContent='Auswahl importieren';}});

const navigationModules=[
  {view:'dashboard',label:'Übersicht',icon:'⌂',description:'Dashboard, Kennzahlen und Schnellaktionen.'},
  {view:'events',label:'Veranstaltungen',icon:'◫',description:'Programm, Termine und Veröffentlichungsdaten.'},
  {view:'displays',label:'Displays',icon:'▣',description:'Displays, Gruppen, Matrizen und Standorte.'},
  {view:'channels',label:'Slides & Kanäle',icon:'▤',description:'Slide-Editor, Bibliothek und Abspielkanäle.'},
  {view:'advertising',label:'Werbung',icon:'◇',description:'Werbeslides und standortbezogene Werbekanäle.'},
  {view:'media',label:'Mediendatenbank',icon:'▧',description:'Bilder, Videos, Vektoren und Ordner.'},
  {view:'schedule',label:'Zeitplanung',icon:'▦',description:'Tages- und Wochenplanung für alle Ziele.'},
  {view:'operations',label:'Presets & Warnhinweise',icon:'⌁',description:'Betriebspresets, Warnungen und DWD.'},
  {view:'updates',label:'Updates',icon:'↻',description:'Versionen prüfen, Release Notes lesen und Kiosky aktualisieren.'},
  {view:'settings',label:'Einstellungen',icon:'⚙',description:'System-, Benutzer- und Schnittstellenkonfiguration.'}
];
const defaultNavigationOrder=navigationModules.map(module=>module.view);
const legacyFeatureViewMap={events:'events',displays:'displays',channels:'content',advertising:'advertising',media:'content',schedule:'schedule',integrations:'integrations',operations:'presets_warnings'};

function normalizedNavigationOrder(order){
  const requested=Array.isArray(order)?order.map(String):[];
  return[...new Set([...requested.filter(view=>defaultNavigationOrder.includes(view)),...defaultNavigationOrder])];
}

function moduleEnabled(view,settings=state.featureSettings||{}){
  if(typeof settings[view]==='boolean')return settings[view];
  const legacyKey=legacyFeatureViewMap[view];
  return legacyKey?settings[legacyKey]!==false:true;
}

function navigationViewAvailable(view){
  if(!pageMeta[view])return false;
  if(!['integrations','users'].includes(view)&&!moduleEnabled(view))return false;
  if(cmsManagedServices&&['media','users'].includes(view))return false;
  if(['integrations','updates','settings','users'].includes(view)&&state.currentUser?.role!=='admin')return false;
  if(state.currentUser?.role==='viewer'&&['media','operations'].includes(view))return false;
  return true;
}

function firstAvailableNavigationView(){
  return normalizedNavigationOrder(state.navigationOrder).find(navigationViewAvailable)||'dashboard';
}

function applyNavigationConfig(settings={},order=[]){
  state.featureSettings=settings;
  state.navigationOrder=normalizedNavigationOrder(order);
  const navigation=document.querySelector('#main-navigation'),help=navigation.querySelector('.nav-help');
  state.navigationOrder.forEach(view=>{const item=navigation.querySelector(`.nav-item[data-view="${CSS.escape(view)}"]`);if(item)navigation.insertBefore(item,help);});
  navigation.querySelectorAll('.nav-item[data-view]').forEach(item=>{if(item.classList.contains('nav-help'))return;item.hidden=!navigationViewAvailable(item.dataset.view);});
  applyEditorElementVisibility();
  const editingHiddenSettings=state.view==='settings'&&state.currentUser?.role==='admin';
  if(state.currentUser&&state.view&&!navigationViewAvailable(state.view)&&!editingHiddenSettings)setView(firstAvailableNavigationView());
}

function renderModuleSwitching(){
  const list=document.querySelector('#module-switching-list'),order=normalizedNavigationOrder(state.navigationOrder);
  list.innerHTML=order.map((view,index)=>{const module=navigationModules.find(item=>item.view===view);return`<article class="module-switching-item" draggable="true" data-module-view="${escapeHtml(view)}"><button class="module-drag-handle" type="button" aria-label="${escapeHtml(module.label)} verschieben" title="Ziehen zum Sortieren">⠿</button><span class="module-switching-icon">${module.icon}</span><span class="module-switching-copy"><strong>${escapeHtml(module.label)}</strong><small>${escapeHtml(module.description)}</small></span><label class="module-visibility-toggle"><input type="checkbox" data-feature-setting="${escapeHtml(view)}" ${moduleEnabled(view)?'checked':''}><span>In Seitenleiste anzeigen</span></label><span class="module-order-actions"><button type="button" data-module-move="-1" aria-label="${escapeHtml(module.label)} nach oben" ${index===0?'disabled':''}>↑</button><button type="button" data-module-move="1" aria-label="${escapeHtml(module.label)} nach unten" ${index===order.length-1?'disabled':''}>↓</button></span></article>`;}).join('');
}

const editorElementLabels={text:'Text',ticker:'Laufelement',weather:'Wetterdaten','qr-code':'QR-Code',countdown:'Countdown',image:'Bild',video:'Video',web:'Website',event:'Eventdaten','event-field':'Eventfeld',wayfinding:'Wegweisung','alert-icon':'Warnsymbol'};
function editorElementEnabled(type,settings=state.featureSettings||{}){return settings[`editor_${type}`]!==false;}
function applyEditorElementVisibility(){document.querySelectorAll('[data-add-element]').forEach(button=>{button.hidden=!editorElementEnabled(button.dataset.addElement);});const picker=document.querySelector('.editor-icon-picker');if(picker)picker.hidden=!editorElementEnabled('alert-icon');}
function renderEditorElementSettings(){
  const list=document.querySelector('#editor-element-settings');if(!list)return;
  list.innerHTML=Object.entries(editorElementLabels).map(([type,label])=>`<label><input type="checkbox" data-editor-element-setting="${escapeHtml(type)}" ${editorElementEnabled(type)?'checked':''}><span><strong>${escapeHtml(label)}</strong><small>Im Slide-Editor verfügbar</small></span></label>`).join('');
}

async function loadFeatureSettings(){
  try{const result=await apiRequest('/api/settings/features');applyNavigationConfig(result.data||{},result.navigationOrder||[]);if(state.view==='settings'){renderModuleSwitching();renderEditorElementSettings();}return result;}
  catch(error){showToast(error.message);return null;}
}

async function loadPublicCalendarSettings(){
  if(!cmsManagedServices)return null;
  try{
    const result=await apiRequest('/api/settings/public-calendar'),settings=result.settings||{};
    document.querySelector('#public-calendar-title').value=settings.title||'Veranstaltungskalender';
    document.querySelector('#public-calendar-upcoming-title').value=settings.upcomingTitle||'Nächste Veranstaltungen';
    document.querySelector('#public-calendar-upcoming-count').value=settings.upcomingCount||10;
    document.querySelector('#public-calendar-show-search').checked=settings.showSearch!==false;
    document.querySelector('#public-calendar-show-upcoming').checked=settings.showUpcoming!==false;
    return result;
  }catch(error){showToast(error.message);return null;}
}

document.querySelector('#public-calendar-settings-form')?.addEventListener('submit',async event=>{
  event.preventDefault();
  const payload={
    title:document.querySelector('#public-calendar-title').value.trim(),
    upcomingTitle:document.querySelector('#public-calendar-upcoming-title').value.trim(),
    upcomingCount:Number(document.querySelector('#public-calendar-upcoming-count').value),
    showSearch:document.querySelector('#public-calendar-show-search').checked,
    showUpcoming:document.querySelector('#public-calendar-show-upcoming').checked,
  };
  try{await apiRequest('/api/settings/public-calendar',{method:'PUT',body:JSON.stringify(payload)});showToast('Website-Kalender wurde gespeichert.');}
  catch(error){showToast(error.message);}
});

const moduleSwitchingList=document.querySelector('#module-switching-list');
moduleSwitchingList.addEventListener('change',event=>{if(event.target.matches('[data-feature-setting]'))state.featureSettings={...(state.featureSettings||{}),[event.target.dataset.featureSetting]:event.target.checked};});
moduleSwitchingList.addEventListener('click',event=>{
  const button=event.target.closest('[data-module-move]'),row=event.target.closest('[data-module-view]');if(!button||!row)return;
  const order=normalizedNavigationOrder(state.navigationOrder),index=order.indexOf(row.dataset.moduleView),target=index+Number(button.dataset.moduleMove);
  if(index<0||target<0||target>=order.length)return;
  [order[index],order[target]]=[order[target],order[index]];state.navigationOrder=order;renderModuleSwitching();
});
moduleSwitchingList.addEventListener('dragstart',event=>{const row=event.target.closest('[data-module-view]');if(!row)return;event.dataTransfer.setData('application/x-kiosky-module',row.dataset.moduleView);row.classList.add('is-dragging');});
moduleSwitchingList.addEventListener('dragend',()=>moduleSwitchingList.querySelectorAll('.module-switching-item').forEach(row=>row.classList.remove('is-dragging','drag-before','drag-after')));
moduleSwitchingList.addEventListener('dragover',event=>{const row=event.target.closest('[data-module-view]');if(!row)return;event.preventDefault();const after=event.clientY>row.getBoundingClientRect().top+row.getBoundingClientRect().height/2;row.classList.toggle('drag-before',!after);row.classList.toggle('drag-after',after);});
moduleSwitchingList.addEventListener('dragleave',event=>event.target.closest('[data-module-view]')?.classList.remove('drag-before','drag-after'));
moduleSwitchingList.addEventListener('drop',event=>{
  const row=event.target.closest('[data-module-view]'),moving=event.dataTransfer.getData('application/x-kiosky-module');if(!row||!moving||moving===row.dataset.moduleView)return;
  event.preventDefault();const order=normalizedNavigationOrder(state.navigationOrder).filter(view=>view!==moving),target=order.indexOf(row.dataset.moduleView),after=row.classList.contains('drag-after');
  order.splice(Math.max(0,target+(after?1:0)),0,moving);state.navigationOrder=order;renderModuleSwitching();
});
document.querySelector('#feature-settings-form').addEventListener('submit',async event=>{
  event.preventDefault();const features=Object.fromEntries([...document.querySelectorAll('[data-feature-setting]')].map(input=>[input.dataset.featureSetting,input.checked])),payload={...features,navigationOrder:normalizedNavigationOrder(state.navigationOrder)};
  try{const result=await apiRequest('/api/settings/features',{method:'PUT',body:JSON.stringify(payload)});applyNavigationConfig(result.data||features,result.navigationOrder||payload.navigationOrder);renderModuleSwitching();showToast('Modul-Sichtbarkeit und Reihenfolge wurden gespeichert.');}
  catch(error){showToast(error.message);}
});
document.querySelector('#editor-element-settings-form').addEventListener('submit',async event=>{
  event.preventDefault();const editorSettings=Object.fromEntries([...document.querySelectorAll('[data-editor-element-setting]')].map(input=>[`editor_${input.dataset.editorElementSetting}`,input.checked]));
  try{const result=await apiRequest('/api/settings/features',{method:'PUT',body:JSON.stringify(editorSettings)});applyNavigationConfig(result.data||{...state.featureSettings,...editorSettings},result.navigationOrder||state.navigationOrder);renderEditorElementSettings();showToast('Die verfügbaren Slide-Editor-Elemente wurden gespeichert.');}
  catch(error){showToast(error.message);}
});

function renderAdvertisingSettings(status){
  const settings=status.settings||{},types=status.availableEventTypes||[],selected=new Set(settings.eventTypes||[]);
  state.advertisingAvailableTypes=types;
  state.advertisingTypeDefaultMedia={...(settings.eventTypeDefaultMedia||{})};
  document.querySelector('#advertising-enabled').checked=Boolean(settings.enabled);
  document.querySelector('#advertising-channel-name').value=settings.channelName||'Werbung';
  document.querySelector('#advertising-maximum-events').value=settings.maximumEvents||15;
  document.querySelector('#advertising-duration').value=settings.durationSeconds||12;
  document.querySelector('#advertising-show-date').checked=Boolean(settings.showDate);
  document.querySelector('#advertising-show-time').checked=settings.showTime!==false;
  document.querySelector('#advertising-show-seconds').checked=Boolean(settings.showSeconds);
  document.querySelector('#advertising-hour12').checked=Boolean(settings.hour12);
  const selectedLocations=new Set(settings.locationIds||[]);
  document.querySelector('#advertising-location-options').innerHTML=state.locations.length?state.locations.map(location=>`<label class="toggle-label"><input type="checkbox" value="${escapeHtml(location.id)}" ${selectedLocations.has(location.id)?'checked':''}> ${escapeHtml(location.name)}</label>`).join(''):'<p class="property-note">Noch keine Standorte vorhanden.</p>';
  document.querySelector('#advertising-status-tag').textContent=settings.enabled?'Aktiv':'Deaktiviert';
  document.querySelector('#advertising-status-tag').className=`tag ${settings.enabled?'green':'muted'}`;
  renderAdvertisingTypeOptions(types,selected);
  document.querySelector('#advertising-summary').innerHTML=`<strong>${Number(status.generatedSlides||0)} Werbeslides</strong><span>${Number(status.eligibleEvents||0)} passende zukünftige Veranstaltungen · ${Number(status.channel?.itemCount||0)} Slides im Kanal</span>${status.lastSynchronizedAt?`<small>Zuletzt synchronisiert: ${escapeHtml(new Date(status.lastSynchronizedAt).toLocaleString('de-DE'))}</small>`:''}`;
}
async function loadAdvertisingSettings(){
  if(!document.querySelector('#advertising-settings-form'))return;
  try{const[result,templates,locations,media]=await Promise.all([apiRequest('/api/settings/advertising'),apiRequest('/api/templates'),apiRequest('/api/locations'),apiRequest('/api/media')]);state.locations=locations.data||state.locations||[];state.mediaFolders=media.folders||[];state.mediaAssets=media.assets||[];const select=document.querySelector('#advertising-template');select.innerHTML=`<option value="">Integrierte Werbevorlage</option>${(templates.data||[]).map(template=>`<option value="${escapeHtml(template.id)}">${escapeHtml(template.name)} · ${escapeHtml(orientationLabels[template.orientation]||template.orientation)}</option>`).join('')}`;select.value=result.settings?.templateId||'';renderAdvertisingSettings(result);}catch(error){showToast(error.message);}
}
function renderAdvertisingTypeOptions(types=state.advertisingAvailableTypes||[],selected=new Set([...document.querySelectorAll('#advertising-event-types input[data-advertising-event-type]:checked')].map(input=>input.value))){
  const list=document.querySelector('#advertising-event-types');if(!list)return;
  list.innerHTML=types.map(type=>{const active=selected.has(type),assetId=state.advertisingTypeDefaultMedia?.[type],asset=state.mediaAssets?.find(item=>item.id===assetId&&!item.deletedAt&&item.type==='image');return`<article class="advertising-type-default ${active?'':'is-inactive'}"><div class="advertising-type-default-preview">${asset?`<img src="${escapeHtml(asset.src)}" alt="">`:'Kein<br>Standardbild'}</div><div class="advertising-type-default-body"><label class="toggle-label"><input type="checkbox" data-advertising-event-type value="${escapeHtml(type)}" ${active?'checked':''}> ${escapeHtml(type)}</label><small title="${escapeHtml(asset?.name||'')}">${escapeHtml(asset?.name||'Veranstaltungsspezifisches Bild erforderlich')}</small><div class="advertising-type-default-actions"><button class="button button-secondary" type="button" data-advertising-type-media="${escapeHtml(type)}" ${active?'':'disabled'}>${asset?'Bild ersetzen':'Bild auswählen / hochladen'}</button>${asset?`<button class="button button-ghost" type="button" data-advertising-type-media-remove="${escapeHtml(type)}" ${active?'':'disabled'}>Entfernen</button>`:''}</div></div></article>`;}).join('')||'<p class="property-note">Noch keine Veranstaltungsarten vorhanden. Sie erscheinen nach dem ersten Import.</p>';
}
function advertisingPayload(){const eventTypes=[...document.querySelectorAll('#advertising-event-types input[data-advertising-event-type]:checked')].map(input=>input.value),eventTypeDefaultMedia=Object.fromEntries(eventTypes.flatMap(type=>state.advertisingTypeDefaultMedia?.[type]?[[type,state.advertisingTypeDefaultMedia[type]]]:[]));return{enabled:document.querySelector('#advertising-enabled').checked,channelName:document.querySelector('#advertising-channel-name').value.trim(),maximumEvents:Number(document.querySelector('#advertising-maximum-events').value),durationSeconds:Number(document.querySelector('#advertising-duration').value),templateId:document.querySelector('#advertising-template').value||undefined,showDate:document.querySelector('#advertising-show-date').checked,showTime:document.querySelector('#advertising-show-time').checked,showSeconds:document.querySelector('#advertising-show-seconds').checked,hour12:document.querySelector('#advertising-hour12').checked,eventTypes,eventTypeDefaultMedia,locationIds:[...document.querySelectorAll('#advertising-location-options input:checked')].map(input=>input.value)};}
document.querySelector('#advertising-event-types').addEventListener('change',event=>{if(event.target.matches('[data-advertising-event-type]'))renderAdvertisingTypeOptions(state.advertisingAvailableTypes,new Set([...document.querySelectorAll('#advertising-event-types input[data-advertising-event-type]:checked')].map(input=>input.value)));});
document.querySelector('#advertising-event-types').addEventListener('click',event=>{const select=event.target.closest('[data-advertising-type-media]'),remove=event.target.closest('[data-advertising-type-media-remove]');if(select){const type=select.dataset.advertisingTypeMedia;openMediaLibrary(asset=>{state.advertisingTypeDefaultMedia={...(state.advertisingTypeDefaultMedia||{}),[type]:asset.id};renderAdvertisingTypeOptions();},'image-only',`Standardbild für „${type}“ auswählen oder hochladen`);}if(remove){const type=remove.dataset.advertisingTypeMediaRemove;delete state.advertisingTypeDefaultMedia[type];renderAdvertisingTypeOptions();}});
document.querySelector('#advertising-settings-form').addEventListener('submit',async event=>{event.preventDefault();try{const result=await apiRequest('/api/settings/advertising',{method:'PUT',body:JSON.stringify(advertisingPayload())});renderAdvertisingSettings(result);await loadContent();showToast('Werbung wurde gespeichert und synchronisiert.');}catch(error){showToast(error.message);}});
document.querySelector('#advertising-sync').addEventListener('click',async event=>{event.currentTarget.disabled=true;try{const result=await apiRequest('/api/advertising/synchronize',{method:'POST',body:'{}'});renderAdvertisingSettings(result);await loadContent();showToast('Werbeslides und Werbekanal wurden synchronisiert.');}catch(error){showToast(error.message);}finally{event.currentTarget.disabled=false;}});

document.querySelector('[data-action="export-crewbrain-config"]').addEventListener('click', async event => {
  if (!window.confirm('Die JSON-Datei enthält den CrewBrain-Zugang im Klartext. Konfiguration jetzt exportieren?')) return;
  const button = event.currentTarget;
  button.disabled = true;
  button.textContent = 'Export wird erstellt …';
  try {
    const exported = await crewbrainApi('/api/integrations/crewbrain/configuration-export');
    const tenant = (() => { try { return new URL(exported.connection.baseUrl).hostname; } catch (error) { return 'crewbrain'; } })();
    const date = new Date().toISOString().slice(0, 10);
    const blobUrl = URL.createObjectURL(new Blob([`${JSON.stringify(exported, null, 2)}\n`], { type: 'application/json' }));
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = `kiosky-crewbrain-${tenant}-${date}.json`;
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(blobUrl);
    setConnectionMessage('CrewBrain-Konfiguration wurde als JSON exportiert', 'success');
    showToast('CrewBrain-Konfiguration wurde exportiert.');
  } catch (error) {
    setConnectionMessage(error.message, 'error');
    showToast(error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'JSON exportieren';
  }
});

document.querySelector('[data-action="import-crewbrain-config"]').addEventListener('click', () => {
  crewbrainConfigImportFile.value = '';
  crewbrainConfigImportFile.click();
});

crewbrainConfigImportFile.addEventListener('change', async event => {
  const file = event.currentTarget.files?.[0];
  if (!file) return;
  if (file.size > 1_000_000) {
    showToast('Die JSON-Datei ist zu groß.');
    return;
  }
  if (!window.confirm('Der Import ersetzt die CrewBrain-Verbindung, alle Ausnahmen und sämtliche Feldzuordnungen. Fortfahren?')) return;
  const button = document.querySelector('[data-action="import-crewbrain-config"]');
  button.disabled = true;
  button.textContent = 'Import läuft …';
  try {
    let payload;
    try { payload = JSON.parse(await file.text()); }
    catch { throw new Error('Die ausgewählte Datei enthält kein gültiges JSON.'); }
    const imported = await crewbrainApi('/api/integrations/crewbrain/configuration-import', { method: 'POST', body: JSON.stringify(payload) });
    fillCrewBrainForm(imported.config, imported.mapping);
    state.crewbrainConfigLoaded = true;
    setConnectionMessage('CrewBrain-Konfiguration vollständig importiert', 'success');
    showToast('CrewBrain-Konfiguration wurde importiert.');
  } catch (error) {
    setConnectionMessage(error.message, 'error');
    showToast(error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'JSON importieren';
    event.currentTarget.value = '';
  }
});

const contentTransferFile = document.querySelector('#content-transfer-import-file');
const contentTransferMessage = document.querySelector('#content-transfer-message');
const contentTransferExportDialog = document.querySelector('#content-transfer-export-dialog');
const contentTransferImportDialog = document.querySelector('#content-transfer-import-dialog');
let pendingContentTransfer = null;
const contentTransferSectionLabels = {
  settings: 'Einstellungen', events: 'Veranstaltungen', displays: 'Displays', slides: 'Slides',
  channels: 'Kanäle', schedules: 'Zeitplanung', operations: 'Presets & Warnhinweise',
  users: 'Benutzer', api: 'API-Schnittstellen', integrations: 'Weitere Schnittstellen'
};

function setContentTransferMessage(message, type = '') {
  contentTransferMessage.hidden = false;
  contentTransferMessage.textContent = message;
  contentTransferMessage.className = `connection-message${type ? ` is-${type}` : ''}`;
}

function downloadTransferJson(value, filename) {
  const blobUrl = URL.createObjectURL(new Blob([`${JSON.stringify(value, null, 2)}\n`], { type: 'application/json' }));
  const link = document.createElement('a');
  link.href = blobUrl;
  link.download = filename;
  document.body.append(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(blobUrl);
}

function contentTransferRecordCount(payload, section) {
  const data = payload.sections?.[section];
  if (!data || typeof data !== 'object') return section === 'settings' || section === 'integrations' ? 1 : 0;
  if (section === 'settings' || section === 'integrations') return Object.keys(data).length;
  return Object.values(data).reduce((sum, value) => sum + (Array.isArray(value) ? value.length : 0), 0);
}

document.querySelector('[data-action="export-content-transfer"]').addEventListener('click', () => {
  showAuthMessage('content-transfer-export-error', '');
  contentTransferExportDialog.showModal();
});

document.querySelectorAll('[data-transfer-selection]').forEach(button => button.addEventListener('click', () => {
  const checked = button.dataset.transferSelection === 'all';
  document.querySelectorAll('input[name="transfer-section"]').forEach(input => { input.checked = checked; });
}));

document.querySelector('#content-transfer-export-form').addEventListener('submit', async event => {
  event.preventDefault();
  const sections = [...document.querySelectorAll('input[name="transfer-section"]:checked')].map(input => input.value);
  if (!sections.length) {
    showAuthMessage('content-transfer-export-error', 'Bitte wähle mindestens einen Bereich aus.');
    return;
  }
  const button = event.currentTarget.querySelector('[type="submit"]');
  button.disabled = true;
  button.textContent = 'Export wird erstellt …';
  try {
    const exported = await apiRequest('/api/content-transfer/export', { method: 'POST', body: JSON.stringify({ sections }) });
    downloadTransferJson(exported, `kiosky-transfer-${new Date().toISOString().slice(0, 10)}.json`);
    const summary = exported.includedSections.map(section => `${contentTransferSectionLabels[section] || section}: ${contentTransferRecordCount(exported, section)}`).join(' · ');
    setContentTransferMessage(`Transferpaket erstellt. ${summary}`, 'success');
    contentTransferExportDialog.close();
    showToast('Transferpaket wurde exportiert.');
  } catch (error) {
    showAuthMessage('content-transfer-export-error', error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'Transferpaket erstellen';
  }
});

document.querySelector('[data-action="import-content-transfer"]').addEventListener('click', () => {
  contentTransferFile.value = '';
  contentTransferFile.click();
});

contentTransferFile.addEventListener('change', async event => {
  const file = event.currentTarget.files?.[0];
  if (!file) return;
  if (file.size > 250_000_000) {
    setContentTransferMessage('Das Transferpaket ist größer als 250 MB und kann nicht importiert werden.', 'error');
    event.currentTarget.value = '';
    return;
  }
  try {
    try { pendingContentTransfer = JSON.parse(await file.text()); }
    catch { throw new Error('Die ausgewählte Datei enthält kein gültiges JSON.'); }
    const portable = pendingContentTransfer?.format === 'kiosky-system-transfer' && pendingContentTransfer?.version === 2;
    const sections = portable ? pendingContentTransfer.includedSections || Object.keys(pendingContentTransfer.sections || {}) : ['slides', 'channels', 'displays'];
    if (!portable && pendingContentTransfer?.format !== 'kiosky-content-transfer') throw new Error('Die Datei ist kein unterstütztes Kiosky-Transferpaket.');
    document.querySelector('#content-transfer-import-source').textContent = portable
      ? `Quelle: ${pendingContentTransfer.source?.platform || 'Kiosky'} ${pendingContentTransfer.source?.version || ''} · Exportiert ${new Date(pendingContentTransfer.exportedAt).toLocaleString('de-DE')}`
      : 'Älteres Kiosky-Transferpaket mit Slides, Kanälen und Displays.';
    document.querySelector('#content-transfer-import-summary').innerHTML = sections.map(section => {
      const count = portable ? contentTransferRecordCount(pendingContentTransfer, section) : (pendingContentTransfer[section] || []).length;
      return `<span>${escapeHtml(contentTransferSectionLabels[section] || section)} · ${count}</span>`;
    }).join('');
    contentTransferImportDialog.showModal();
  } catch (error) {
    pendingContentTransfer = null;
    setContentTransferMessage(error.message, 'error');
    showToast(error.message);
  } finally {
    event.currentTarget.value = '';
  }
});

document.querySelector('#content-transfer-import-form').addEventListener('submit', async event => {
  event.preventDefault();
  if (!pendingContentTransfer) return;
  const button = event.currentTarget.querySelector('[type="submit"]');
  button.disabled = true;
  button.textContent = 'Import läuft …';
  try {
    const result = await apiRequest('/api/content-transfer/import', { method: 'POST', body: JSON.stringify(pendingContentTransfer) });
    const imported = result.imported;
    await loadEvents();
    await loadContent(null);
    await loadDisplays();
    await Promise.allSettled([loadScheduling(), loadOperations(), loadFeatureSettings(), loadUsers(), loadApiUsers(), loadDashboard()]);
    const counts = imported.counts || { slides: imported.slides, channels: imported.channels, displays: imported.displays };
    const summary = Object.entries(counts).filter(([, count]) => Number(count) > 0).map(([name, count]) => `${count} ${contentTransferSectionLabels[name] || name}`).join(', ') || 'Keine neuen Datensätze';
    const notices = imported.notices || [];
    if (imported.renamedDisplaySlugs?.length) notices.push(`${imported.renamedDisplaySlugs.length} URL-Kennungen wurden angepasst`);
    if (imported.regeneratedPlayerKeys) notices.push(`${imported.regeneratedPlayerKeys} Player-Schlüssel wurden neu erzeugt`);
    if (imported.apiCredentials?.length) downloadTransferJson({
      generatedAt: new Date().toISOString(),
      warning: 'Diese Schlüssel werden nur einmal angezeigt. Sicher speichern und diese Datei anschließend löschen.',
      apiCredentials: imported.apiCredentials
    }, `kiosky-neue-api-schluessel-${new Date().toISOString().slice(0, 10)}.json`);
    setContentTransferMessage(`${summary} importiert.${notices.length ? ` ${notices.join(' ')}` : ''}`, 'success');
    contentTransferImportDialog.close();
    pendingContentTransfer = null;
    showToast('Transferpaket wurde importiert.');
  } catch (error) {
    setContentTransferMessage(error.message, 'error');
    showToast(error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'Jetzt importieren';
  }
});

document.querySelector('[data-action="test-crewbrain"]').addEventListener('click', async event => {
  const button = event.currentTarget;
  button.disabled = true;
  button.textContent = 'Verbindung wird geprüft …';
  try {
    if (!await saveCrewBrainConfiguration(true)) return;
    const result = await crewbrainApi('/api/integrations/crewbrain/test', { method: 'POST' });
    setConnectionBadge('Verbunden', 'green');
    document.querySelector('#crewbrain-version-label').textContent = result.apiVersion || 'API v2';
    document.querySelector('#crewbrain-last-sync').textContent = `Verbindung geprüft ${new Date(result.checkedAt).toLocaleString('de-DE')}`;
    crewbrainPreviewButton.disabled = false;
    setConnectionMessage('Identität, Rechte und Veranstaltungszugriff erfolgreich geprüft', 'success');
    showToast('CrewBrain-Verbindung ist vollständig einsatzbereit.');
  } catch (error) {
    setConnectionBadge('Prüfung fehlgeschlagen', 'coral');
    setConnectionMessage(error.message, 'error');
    showToast(error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'Verbindung testen';
  }
});

function crewbrainTimestamp(date, endOfDay = false) {
  return date ? `${date.replaceAll('-', '')}T${endOfDay ? '235959' : '000000'}Z` : undefined;
}

function renderCrewBrainPreview(result) {
  const rows = result.data || [];
  state.crewbrainPreview = rows;
  state.crewbrainPreviewTotal = Number(result.totalItems ?? rows.length);
  state.crewbrainPreviewExcluded = Number(result.excludedCount || 0);
  document.querySelector('#crewbrain-preview-empty').hidden = true;
  document.querySelector('#crewbrain-preview-results').hidden = false;
  const start = state.crewbrainPreviewPageSize === 'all' || !rows.length ? (rows.length ? 1 : 0) : ((state.crewbrainPreviewPage - 1) * Number(state.crewbrainPreviewPageSize)) + 1;
  const end = !rows.length ? 0 : state.crewbrainPreviewPageSize === 'all' ? rows.length : Math.min(start + rows.length - 1, state.crewbrainPreviewTotal);
  document.querySelector('#crewbrain-preview-count').textContent = `${start}–${end} von ${state.crewbrainPreviewTotal} Treffern`;
  const excludedLabel = state.crewbrainPreviewExcluded ? ` · ${state.crewbrainPreviewExcluded} auf dieser Seite durch Titelliste ausgeschlossen` : '';
  document.querySelector('#crewbrain-preview-total').textContent = excludedLabel.replace(/^ · /, '');
  document.querySelector('#crewbrain-preview-table').innerHTML = `<div class="preview-table-row is-head"><span>Auswahl</span><span>Veranstaltung</span><span>Datum</span><span>Spielort</span><span>Status</span></div>${rows.map(item => {
    const event = item.event || {};
    const date = event.date && /^\d{8}$/.test(event.date) ? `${event.date.slice(6, 8)}.${event.date.slice(4, 6)}.${event.date.slice(0, 4)}` : event.date || '–';
    const id = String(item.crewbrain?.id || '');
    return `<div class="preview-table-row"><span><input class="preview-event-checkbox" type="checkbox" value="${escapeHtml(id)}" ${state.crewbrainPreviewSelection.has(id) ? 'checked' : ''} aria-label="${escapeHtml(event.title || 'Veranstaltung')} auswählen"></span><span><strong>${escapeHtml(event.title || 'Ohne Titel')}</strong><small>${escapeHtml(item.crewbrain?.number || item.crewbrain?.type || '')}</small></span><span>${escapeHtml(date)}</span><span>${escapeHtml(event.venue || '–')}</span><span><i class="tag blue">Bereit zum Import</i></span></div>`;
  }).join('') || '<div class="preview-table-row"><span>Keine passenden Veranstaltungen gefunden.</span></div>'}`;
  renderCrewBrainPagination();
  updateCrewBrainPreviewSelection();
}

function setCrewBrainPreviewMessage(message = '', type = '') {
  crewbrainPreviewMessage.hidden = !message;
  crewbrainPreviewMessage.textContent = message;
  crewbrainPreviewMessage.className = `preview-message${type ? ` is-${type}` : ''}`;
}

function previewPageButtons(pageCount) {
  if (pageCount <= 7) return Array.from({ length: pageCount }, (_, index) => index + 1);
  const candidates = new Set([1, pageCount, state.crewbrainPreviewPage - 1, state.crewbrainPreviewPage, state.crewbrainPreviewPage + 1]);
  return [...candidates].filter(page => page > 0 && page <= pageCount).sort((left, right) => left - right);
}

function renderCrewBrainPagination() {
  const pageCount = state.crewbrainPreviewPageSize === 'all' ? 1 : Math.max(1, Math.ceil(state.crewbrainPreviewTotal / Number(state.crewbrainPreviewPageSize)));
  const pages = previewPageButtons(pageCount);
  const pageMarkup = pages.map((page, index) => `${index && page - pages[index - 1] > 1 ? '<span class="pagination-gap">…</span>' : ''}<button type="button" class="${page === state.crewbrainPreviewPage ? 'is-active' : ''}" data-preview-page="${page}" ${page === state.crewbrainPreviewPage ? 'aria-current="page"' : ''}>${page}</button>`).join('');
  const markup = `<div class="pagination-pages"><button type="button" data-preview-page="${state.crewbrainPreviewPage - 1}" ${state.crewbrainPreviewPage <= 1 ? 'disabled' : ''} aria-label="Vorherige Seite">←</button>${pageMarkup}<button type="button" data-preview-page="${state.crewbrainPreviewPage + 1}" ${state.crewbrainPreviewPage >= pageCount ? 'disabled' : ''} aria-label="Nächste Seite">→</button></div><label>Elemente pro Seite <select class="crewbrain-preview-page-size"><option value="10">10</option><option value="25">25</option><option value="100">100</option><option value="all">Alle</option></select></label>`;
  document.querySelectorAll('[data-preview-pagination]').forEach(container => {
    container.innerHTML = markup;
    container.querySelector('.crewbrain-preview-page-size').value = state.crewbrainPreviewPageSize;
  });
}

function updateCrewBrainPreviewSelection() {
  const inputs = [...document.querySelectorAll('.preview-event-checkbox')];
  const selectedOnPage = inputs.filter(input => input.checked).length;
  const selectPage = document.querySelector('#crewbrain-preview-select-page');
  selectPage.checked = inputs.length > 0 && selectedOnPage === inputs.length;
  selectPage.indeterminate = selectedOnPage > 0 && selectedOnPage < inputs.length;
  document.querySelector('#crewbrain-import-selected').disabled = state.crewbrainPreviewSelection.size === 0;
  const count = state.crewbrainPreviewSelection.size;
  document.querySelector('#crewbrain-preview-selection-count').textContent = `${count} ${count === 1 ? 'Element' : 'Elemente'} ausgewählt`;
}

function crewbrainPreviewPayload(limit, offset) {
  return {
    from: crewbrainTimestamp(document.querySelector('#crewbrain-preview-from').value),
    until: crewbrainTimestamp(document.querySelector('#crewbrain-preview-until').value, true),
    search: document.querySelector('#crewbrain-preview-search').value.trim() || undefined,
    status: document.querySelector('#crewbrain-preview-status').value || undefined,
    limit,
    offset
  };
}

async function loadCrewBrainPreviewPage(page = 1) {
  const requestId = ++crewbrainPreviewRequestId;
  const requestedPageSize = state.crewbrainPreviewPageSize;
  const button = crewbrainPreviewButton;
  button.disabled = true;
  button.textContent = 'Lädt …';
  document.querySelector('#crewbrain-preview-results').classList.add('is-loading');
  document.querySelectorAll('.crewbrain-preview-page-size').forEach(select => { select.disabled = true; });
  setCrewBrainPreviewMessage('CrewBrain-Veranstaltungen werden geladen …');
  try {
    state.crewbrainPreviewPage = page;
    let result;
    if (requestedPageSize === 'all') {
      const data = [];
      let offset = 0;
      let totalItems = 0;
      let excludedCount = 0;
      do {
        const chunk = await crewbrainApi('/api/integrations/crewbrain/import-preview', { method: 'POST', body: JSON.stringify(crewbrainPreviewPayload(100, offset)) });
        data.push(...(chunk.data || []));
        totalItems = Number(chunk.totalItems || 0);
        excludedCount += Number(chunk.excludedCount || 0);
        offset += Math.max(Number(chunk.limit || 100), 1);
      } while (offset < totalItems);
      result = { data, totalItems, excludedCount };
    } else {
      const size = Number(requestedPageSize);
      result = await crewbrainApi('/api/integrations/crewbrain/import-preview', { method: 'POST', body: JSON.stringify(crewbrainPreviewPayload(size, (page - 1) * size)) });
      result.data = (result.data || []).slice(0, size);
    }
    if (requestId !== crewbrainPreviewRequestId || requestedPageSize !== state.crewbrainPreviewPageSize) return false;
    renderCrewBrainPreview(result);
    setCrewBrainPreviewMessage('Importvorschau erfolgreich geladen.', 'success');
    return true;
  } catch (error) {
    if (requestId !== crewbrainPreviewRequestId) return false;
    setConnectionMessage(error.message, 'error');
    setCrewBrainPreviewMessage(error.message, 'error');
    showToast(error.message);
    return false;
  } finally {
    if (requestId === crewbrainPreviewRequestId) {
      button.disabled = false;
      button.textContent = 'Vorschau laden';
      document.querySelector('#crewbrain-preview-results').classList.remove('is-loading');
      document.querySelectorAll('.crewbrain-preview-page-size').forEach(select => { select.disabled = false; });
    }
  }
}

document.querySelector('#crewbrain-preview-form').addEventListener('submit', async event => {
  event.preventDefault();
  state.crewbrainPreviewSelection.clear();
  if (await loadCrewBrainPreviewPage(1)) showToast('Importvorschau wurde aktualisiert.');
});

document.querySelector('#crewbrain-preview-select-page').addEventListener('change', event => {
  document.querySelectorAll('.preview-event-checkbox').forEach(input => {
    input.checked = event.currentTarget.checked;
    if (input.checked) state.crewbrainPreviewSelection.add(input.value);
    else state.crewbrainPreviewSelection.delete(input.value);
  });
  updateCrewBrainPreviewSelection();
});
document.querySelector('#crewbrain-preview-table').addEventListener('change', event => {
  if (!event.target.classList.contains('preview-event-checkbox')) return;
  if (event.target.checked) state.crewbrainPreviewSelection.add(event.target.value);
  else state.crewbrainPreviewSelection.delete(event.target.value);
  updateCrewBrainPreviewSelection();
});
document.querySelector('#crewbrain-preview-results').addEventListener('click', async event => {
  const button = event.target.closest('[data-preview-page]');
  if (!button || button.disabled) return;
  await loadCrewBrainPreviewPage(Number(button.dataset.previewPage));
});
document.querySelector('#crewbrain-preview-results').addEventListener('change', async event => {
  if (!event.target.classList.contains('crewbrain-preview-page-size')) return;
  state.crewbrainPreviewPageSize = event.target.value;
  document.querySelectorAll('.crewbrain-preview-page-size').forEach(select => { select.value = state.crewbrainPreviewPageSize; });
  await loadCrewBrainPreviewPage(1);
});
document.querySelector('#crewbrain-preview-select-all').addEventListener('click', async event => {
  const button = event.currentTarget;
  button.disabled = true;
  button.textContent = 'Alle werden ausgewählt …';
  const requestId = ++crewbrainPreviewRequestId;
  const selection = new Set(state.crewbrainPreviewSelection);
  document.querySelector('#crewbrain-preview-results').classList.add('is-loading');
  try {
    let offset = 0;
    let totalItems = 0;
    do {
      const chunk = await crewbrainApi('/api/integrations/crewbrain/import-preview', { method: 'POST', body: JSON.stringify(crewbrainPreviewPayload(100, offset)) });
      (chunk.data || []).forEach(item => {
        const id = String(item.crewbrain?.id || '');
        if (id) selection.add(id);
      });
      totalItems = Number(chunk.totalItems || 0);
      offset += Math.max(Number(chunk.limit || 100), 1);
    } while (offset < totalItems);
    if (requestId === crewbrainPreviewRequestId) {
      state.crewbrainPreviewSelection = selection;
      document.querySelectorAll('.preview-event-checkbox').forEach(input => { input.checked = state.crewbrainPreviewSelection.has(input.value); });
      updateCrewBrainPreviewSelection();
      showToast(`${state.crewbrainPreviewSelection.size} Elemente wurden ausgewählt.`);
    }
  } catch (error) {
    if (requestId === crewbrainPreviewRequestId) {
      setCrewBrainPreviewMessage(error.message, 'error');
      showToast(error.message);
    }
  } finally {
    button.disabled = false;
    button.textContent = 'Alle Elemente auswählen';
    if (requestId === crewbrainPreviewRequestId) {
      document.querySelector('#crewbrain-preview-results').classList.remove('is-loading');
    }
  }
});
document.querySelector('#crewbrain-import-selected').addEventListener('click', async event => {
  const ids = [...state.crewbrainPreviewSelection];
  if (!ids.length) { showToast('Bitte mindestens eine Veranstaltung auswählen.'); return; }
  const button = event.currentTarget; button.disabled = true; button.textContent = 'Importiert …';
  try {
    const summary = { created: 0, updated: 0, unchanged: 0, excluded: 0, errors: 0 };
    for (let index = 0; index < ids.length; index += 100) {
      button.textContent = `Importiert ${Math.min(index + 100, ids.length)} / ${ids.length} …`;
      const result = await crewbrainApi('/api/integrations/crewbrain/import', { method: 'POST', body: JSON.stringify({ ids: ids.slice(index, index + 100) }) });
      Object.keys(summary).forEach(key => { summary[key] += Number(result.summary?.[key] || 0); });
    }
    setCrewBrainPreviewMessage(`${summary.created || 0} neu importiert, ${summary.updated || 0} aktualisiert, ${summary.unchanged || 0} unverändert${summary.errors ? `, ${summary.errors} Fehler` : ''}.`, summary.errors ? 'error' : 'success');
    await loadEvents();
    setView('events');
    showToast('Ausgewählte CrewBrain-Veranstaltungen wurden in Kiosky übernommen.');
  } catch (error) { setCrewBrainPreviewMessage(error.message, 'error'); showToast(error.message); }
  finally { button.disabled = false; button.textContent = 'Ausgewählte importieren'; }
});

// External API users
function renderApiUsers() {
  const active = state.apiUsers.filter(user => user.status === 'active');
  document.querySelector('#api-user-count').textContent = `${active.length} aktiv · ${state.apiUsers.length} insgesamt`;
  document.querySelector('#api-user-list').innerHTML = state.apiUsers.length ? state.apiUsers.map(user => `
    <article class="api-user-row ${user.status === 'revoked' ? 'is-revoked' : ''}">
      <span class="api-user-icon">⌁</span>
      <span class="api-user-identity"><strong>${escapeHtml(user.name)}</strong><small>${escapeHtml(user.description || 'Keine Beschreibung')}</small><code>${escapeHtml(user.tokenPrefix)}</code></span>
      <span class="api-user-usage"><small>Zuletzt verwendet</small><strong>${escapeHtml(formatUserDate(user.lastUsedAt))}</strong></span>
      <span class="tag ${user.status === 'active' ? 'green' : 'muted'}">${user.status === 'active' ? 'Aktiv' : 'Widerrufen'}</span>
      ${user.status === 'active' ? `<button class="api-revoke-button" type="button" data-revoke-api-user="${escapeHtml(user.id)}">Widerrufen</button>` : ''}
    </article>`).join('') : '<div class="user-table-empty">Noch keine API-Benutzer angelegt.</div>';
}

async function loadApiUsers() {
  if (state.currentUser?.role !== 'admin') return;
  try {
    const result = await apiRequest('/api/api-users');
    state.apiUsers = result.data || [];
    renderApiUsers();
  } catch (error) { showToast(error.message); }
}

document.querySelector('#api-user-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget; const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const result = await apiRequest('/api/api-users', { method: 'POST', body: JSON.stringify({ name: document.querySelector('#api-user-name').value, description: document.querySelector('#api-user-description').value }) });
    form.reset();
    document.querySelector('#api-secret-token').value = result.token;
    document.querySelector('#api-secret-result').hidden = false;
    await loadApiUsers();
    showToast(`API-Benutzer „${result.apiUser.name}“ wurde angelegt.`);
  } catch (error) { showToast(error.message); }
  finally { button.disabled = false; }
});

document.querySelector('#api-user-list').addEventListener('click', async event => {
  const button = event.target.closest('[data-revoke-api-user]');
  if (!button) return;
  const apiUser = state.apiUsers.find(user => user.id === button.dataset.revokeApiUser);
  if (!apiUser || !window.confirm(`API-Zugang „${apiUser.name}“ wirklich widerrufen?`)) return;
  button.disabled = true;
  try {
    await apiRequest(`/api/api-users/${encodeURIComponent(apiUser.id)}`, { method: 'DELETE' });
    await loadApiUsers();
    showToast('API-Zugang wurde sofort widerrufen.');
  } catch (error) { button.disabled = false; showToast(error.message); }
});

document.querySelector('#copy-api-secret').addEventListener('click', async () => {
  await navigator.clipboard.writeText(document.querySelector('#api-secret-token').value);
  showToast('API-Schlüssel wurde kopiert.');
});

// User administration
function formatUserDate(value) {
  return value ? new Date(value).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' }) : 'Noch nie';
}

function renderUsers() {
  const activeUsers = state.users.filter(user => user.status === 'active');
  const administrators = activeUsers.filter(user => user.role === 'admin');
  const latestLogin = [...state.users].filter(user => user.lastLoginAt).sort((a, b) => String(b.lastLoginAt).localeCompare(String(a.lastLoginAt)))[0];
  document.querySelector('#active-user-count').textContent = String(activeUsers.length);
  document.querySelector('#admin-user-count').textContent = String(administrators.length);
  document.querySelector('#last-user-login').textContent = latestLogin ? formatUserDate(latestLogin.lastLoginAt) : '–';
  const accounts = state.users.map(user => `<div class="user-table-row"><span class="user-identity"><i class="avatar">${escapeHtml(initials(user.displayName))}</i><span><strong>${escapeHtml(user.displayName)}</strong><small>@${escapeHtml(user.username)}${user.email ? ` · ${escapeHtml(user.email)}` : ''}</small></span></span><span><i class="role-badge ${escapeHtml(user.role)}">${escapeHtml(roleLabels[user.role])}</i></span><span>${escapeHtml(formatUserDate(user.lastLoginAt))}</span><span><i class="tag ${user.status === 'active' ? 'green' : 'muted'}">${user.status === 'active' ? 'Aktiv' : 'Deaktiviert'}</i>${user.mustChangePassword ? '<small>Passwortwechsel offen</small>' : ''}</span><span class="user-actions"><button type="button" data-edit-user="${escapeHtml(user.id)}">Bearbeiten</button>${user.id === state.currentUser.id ? '' : `<button type="button" data-toggle-user="${escapeHtml(user.id)}">${user.status === 'active' ? 'Deaktivieren' : 'Aktivieren'}</button><button type="button" data-delete-user="${escapeHtml(user.id)}">Löschen</button><button type="button" data-reset-user="${escapeHtml(user.id)}">Passwort</button>`}</span></div>`).join('');
  const invitations = state.userInvitations.map(invitation => `<div class="user-table-row invitation-row"><span class="user-identity"><i class="avatar">✉</i><span><strong>${escapeHtml(invitation.email)}</strong><small>Einladung bis ${escapeHtml(formatUserDate(invitation.expiresAt))}</small></span></span><span><i class="role-badge ${escapeHtml(invitation.role)}">${escapeHtml(roleLabels[invitation.role])}</i></span><span>–</span><span><i class="tag ${invitation.status === 'active' ? 'blue' : 'muted'}">${invitation.status === 'active' ? 'Einladung offen' : 'Deaktiviert'}</i></span><span class="user-actions"><button type="button" data-toggle-invitation="${escapeHtml(invitation.id)}">${invitation.status === 'active' ? 'Deaktivieren' : 'Aktivieren'}</button><button type="button" data-delete-invitation="${escapeHtml(invitation.id)}">Löschen</button></span></div>`).join('');
  document.querySelector('#user-table').innerHTML = `<div class="user-table-row is-head"><span>Benutzer</span><span>Rolle</span><span>Letzte Anmeldung</span><span>Status</span><span></span></div>${accounts}${invitations || ''}` || '<div class="user-table-empty">Noch keine Benutzer vorhanden.</div>';
  renderUserLocationMatrix();
}

function renderUserLocationMatrix(){
  const matrix=document.querySelector('#user-location-matrix');if(!matrix)return;
  if(!state.locations.length){matrix.innerHTML='<p class="user-table-empty">Lege zuerst im Display-Modul mindestens einen Standort an.</p>';return;}
  const columnStyle=`--location-columns:${state.locations.length+1}`;
  matrix.innerHTML=`<div class="user-location-matrix-row is-head" style="${columnStyle}"><strong>Benutzer</strong><span>Alle</span>${state.locations.map(location=>`<span title="${escapeHtml(location.name)}">${escapeHtml(location.name)}</span>`).join('')}</div>${state.users.map(user=>`<div class="user-location-matrix-row" style="${columnStyle}" data-user-location-row="${escapeHtml(user.id)}"><strong>${escapeHtml(user.displayName)}<small>${escapeHtml(roleLabels[user.role])}</small></strong><label title="Alle aktuellen und künftigen Standorte"><input type="checkbox" data-user-location-all ${user.allLocations?'checked':''}><span>Alle</span></label>${state.locations.map(location=>`<label title="${escapeHtml(location.name)}"><input type="checkbox" value="${escapeHtml(location.id)}" data-user-location-id ${user.allLocations||user.locationIds?.includes(location.id)?'checked':''} ${user.allLocations?'disabled':''}><span>${escapeHtml(location.name)}</span></label>`).join('')}</div>`).join('')}`;
}

async function loadUsers() {
  if (state.currentUser?.role !== 'admin') return;
  try {
    const [result, invitationResult, locationResult] = await Promise.all([apiRequest('/api/users'), apiRequest('/api/users/invitations'), apiRequest('/api/locations')]);
    state.users = result.data;
    state.userInvitations = invitationResult.data || [];
    state.locations=locationResult.data||[];
    renderUsers();
  } catch (error) { showToast(error.message); }
}

document.querySelector('#user-location-matrix').addEventListener('change',event=>{
  const row=event.target.closest('[data-user-location-row]');if(!row)return;
  if(event.target.matches('[data-user-location-all]')){
    row.querySelectorAll('[data-user-location-id]').forEach(input=>{input.disabled=event.target.checked;if(event.target.checked)input.checked=true;});
  }
});
document.querySelector('#save-user-location-matrix').addEventListener('click',async event=>{
  const button=event.currentTarget;button.disabled=true;
  try{
    await Promise.all([...document.querySelectorAll('[data-user-location-row]')].map(row=>{const allLocations=row.querySelector('[data-user-location-all]').checked,locationIds=[...row.querySelectorAll('[data-user-location-id]:checked')].map(input=>input.value);return apiRequest(`/api/users/${encodeURIComponent(row.dataset.userLocationRow)}/locations`,{method:'PUT',body:JSON.stringify({allLocations,locationIds})});}));
    await loadUsers();showToast('Die Standortzugriffe wurden gespeichert.');
  }catch(error){showToast(error.message);}finally{button.disabled=false;}
});

function openUserDialog(user = null) {
  document.querySelector('#user-form').reset();
  document.querySelector('#user-form-message').textContent = '';
  document.querySelector('#user-id').value = user?.id || '';
  document.querySelector('#user-dialog-title').textContent = user ? 'Benutzer bearbeiten' : 'Benutzer anlegen';
  document.querySelector('#user-name').value = user?.displayName || '';
  document.querySelector('#user-username').value = user?.username || '';
  document.querySelector('#user-email').value = user?.email || '';
  document.querySelector('#user-role').value = user?.role || 'editor';
  document.querySelector('#user-status').value = user?.status || 'active';
  document.querySelector('#user-status-field').hidden = !user;
  document.querySelector('#user-password-field').hidden = Boolean(user);
  document.querySelector('#user-password').required = !user;
  document.querySelector('#user-dialog').showModal();
}

document.querySelector('[data-action="new-user"]').addEventListener('click', () => openUserDialog());
document.querySelector('#user-table').addEventListener('click', async event => {
  const editButton = event.target.closest('[data-edit-user]');
  if (editButton) openUserDialog(state.users.find(user => user.id === editButton.dataset.editUser));
  const resetButton = event.target.closest('[data-reset-user]');
  if (resetButton) {
    document.querySelector('#reset-user-id').value = resetButton.dataset.resetUser;
    document.querySelector('#reset-password-form').reset();
    document.querySelector('#reset-password-message').textContent = '';
    document.querySelector('#reset-password-dialog').showModal();
  }
  const toggleUserButton = event.target.closest('[data-toggle-user]');
  if (toggleUserButton) {
    const user = state.users.find(item => item.id === toggleUserButton.dataset.toggleUser);
    const status = user?.status === 'active' ? 'disabled' : 'active';
    if (!user || (status === 'disabled' && !window.confirm(`Benutzer „${user.displayName}“ deaktivieren? Alle aktiven Sitzungen werden sofort beendet.`))) return;
    toggleUserButton.disabled = true;
    try {
      await apiRequest(`/api/users/${encodeURIComponent(user.id)}`, { method: 'PUT', body: JSON.stringify({ displayName: user.displayName, username: user.username, email: user.email || '', role: user.role, status }) });
      await loadUsers();
      showToast(status === 'disabled' ? 'Benutzer wurde deaktiviert.' : 'Benutzer wurde aktiviert.');
    } catch (error) { toggleUserButton.disabled = false; showToast(error.message); }
  }
  const deleteUserButton = event.target.closest('[data-delete-user]');
  if (deleteUserButton) {
    const user = state.users.find(item => item.id === deleteUserButton.dataset.deleteUser);
    if (!user || !window.confirm(`Benutzer „${user.displayName}“ dauerhaft löschen?`)) return;
    deleteUserButton.disabled = true;
    try {
      await apiRequest(`/api/users/${encodeURIComponent(user.id)}`, { method: 'DELETE' });
      await loadUsers();
      showToast('Benutzer wurde gelöscht.');
    } catch (error) { deleteUserButton.disabled = false; showToast(error.message); }
  }
  const toggleInvitationButton = event.target.closest('[data-toggle-invitation]');
  if (toggleInvitationButton) {
    const invitation = state.userInvitations.find(item => item.id === toggleInvitationButton.dataset.toggleInvitation);
    const status = invitation?.status === 'active' ? 'disabled' : 'active';
    if (!invitation || (status === 'disabled' && !window.confirm(`Einladung für „${invitation.email}“ deaktivieren? Der Einladungslink wird sofort ungültig.`))) return;
    toggleInvitationButton.disabled = true;
    try {
      await apiRequest(`/api/users/invitations/${encodeURIComponent(invitation.id)}`, { method: 'PUT', body: JSON.stringify({ status }) });
      await loadUsers();
      showToast(status === 'disabled' ? 'Einladung wurde deaktiviert.' : 'Einladung wurde aktiviert.');
    } catch (error) { toggleInvitationButton.disabled = false; showToast(error.message); }
  }
  const deleteInvitationButton = event.target.closest('[data-delete-invitation]');
  if (deleteInvitationButton) {
    const invitation = state.userInvitations.find(item => item.id === deleteInvitationButton.dataset.deleteInvitation);
    if (!invitation || !window.confirm(`Einladung für „${invitation.email}“ dauerhaft löschen?`)) return;
    deleteInvitationButton.disabled = true;
    try {
      await apiRequest(`/api/users/invitations/${encodeURIComponent(invitation.id)}`, { method: 'DELETE' });
      await loadUsers();
      showToast('Einladung wurde gelöscht.');
    } catch (error) { deleteInvitationButton.disabled = false; showToast(error.message); }
  }
});

document.querySelector('#user-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const id = document.querySelector('#user-id').value;
  const payload = { displayName: document.querySelector('#user-name').value, username: document.querySelector('#user-username').value, email: document.querySelector('#user-email').value, role: document.querySelector('#user-role').value, status: document.querySelector('#user-status').value, ...(!id ? { password: document.querySelector('#user-password').value } : {}) };
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    await apiRequest(id ? `/api/users/${encodeURIComponent(id)}` : '/api/users', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
    document.querySelector('#user-dialog').close();
    await loadUsers();
    if (id === state.currentUser.id) applyCurrentUser((await apiRequest('/api/auth/me')).user);
    showToast(id ? 'Benutzer wurde aktualisiert.' : 'Benutzer wurde angelegt.');
  } catch (error) { showAuthMessage('user-form-message', error.message); }
  finally { button.disabled = false; }
});

document.querySelector('[data-action="invite-user"]').addEventListener('click', () => {
  document.querySelector('#invite-user-form').reset(); document.querySelector('#invitation-result').hidden = true; showAuthMessage('invite-user-message', ''); document.querySelector('#invite-user-dialog').showModal();
});
document.querySelector('#invite-user-form').addEventListener('submit', async event => {
  event.preventDefault(); const form = event.currentTarget, button = form.querySelector('button[type="submit"]'); button.disabled = true;
  try {
    const result = await apiRequest('/api/users/invitations', { method: 'POST', body: JSON.stringify({ email: document.querySelector('#invite-user-email').value, role: document.querySelector('#invite-user-role').value }) });
    document.querySelector('#invitation-result').hidden = false; document.querySelector('#invitation-result-title').textContent = result.delivery.sent ? 'Einladung versendet' : 'Einladung erstellt'; document.querySelector('#invitation-result-message').textContent = result.delivery.message; document.querySelector('#invitation-link').value = result.inviteUrl; showAuthMessage('invite-user-message', result.delivery.sent ? 'Die eingeladene Person erhält jetzt eine E-Mail.' : 'Der Link ist sieben Tage gültig.', true); await loadUsers();
  } catch (error) { showAuthMessage('invite-user-message', error.message); }
  finally { button.disabled = false; }
});
document.querySelector('#copy-invitation-link').addEventListener('click', async () => { await navigator.clipboard.writeText(document.querySelector('#invitation-link').value); showToast('Einladungslink wurde kopiert.'); });

document.querySelector('#reset-password-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    await apiRequest(`/api/users/${encodeURIComponent(document.querySelector('#reset-user-id').value)}/reset-password`, { method: 'POST', body: JSON.stringify({ password: document.querySelector('#reset-user-password').value }) });
    document.querySelector('#reset-password-dialog').close();
    await loadUsers();
    showToast('Startpasswort wurde gesetzt und aktive Sitzungen wurden beendet.');
  } catch (error) { showAuthMessage('reset-password-message', error.message); }
  finally { button.disabled = false; }
});

const previewToday = new Date();
const previewUntil = new Date(previewToday); previewUntil.setFullYear(previewUntil.getFullYear() + 1);
document.querySelector('#crewbrain-preview-from').value = previewToday.toISOString().slice(0, 10);
document.querySelector('#crewbrain-preview-until').value = previewUntil.toISOString().slice(0, 10);

function renderSource(){
  const names={crewbrain:'CrewBrain',easyjob:'easyjob'},activeName=state.activeSource?names[state.activeSource]:'';
  const strip=document.querySelector('#source-strip-text');
  strip.innerHTML=state.activeSource?`<strong>${activeName}</strong> ist die aktive Datenquelle`:'<strong>Keine Datenquelle aktiv</strong> · nur manuelle Eingaben möglich';
  document.querySelector('#sidebar-sync-status').textContent=state.activeSource?`${activeName} ist aktive Datenquelle`:'Keine Datenquelle aktiv';
  document.querySelectorAll('.leading-source-name').forEach(element=>{element.textContent=activeName||'Keine';});
  document.querySelectorAll('[data-source-card]').forEach(card=>card.classList.toggle('is-leading',card.dataset.sourceCard===state.activeSource));
  document.querySelectorAll('[data-source-toggle]').forEach(input=>{
    const source=input.dataset.sourceToggle,configured=Boolean(state.integrationSources[source]?.configured);
    input.checked=state.activeSource===source;
    input.disabled=!configured;
    const hint=document.querySelector(`#${source}-activation-hint`);
    if(hint)hint.textContent=!configured?'Zuerst Zugangsdaten speichern':input.checked?'Aktiv · alle anderen Quellen sind deaktiviert':'Inaktiv';
  });
}

async function loadIntegrationSources(){
  try{
    const result=await apiRequest('/api/integrations/sources');
    state.activeSource=result.activeSource||null;
    state.integrationSources=result.sources||{};
    renderSource();
    return result;
  }catch(error){
    if(state.view==='integrations')showToast(error.message);
    return null;
  }
}

document.querySelectorAll('[data-source-toggle]').forEach(input=>input.addEventListener('change',async event=>{
  const toggle=event.currentTarget,requested=toggle.checked?toggle.dataset.sourceToggle:null;
  document.querySelectorAll('[data-source-toggle]').forEach(item=>{item.disabled=true;});
  try{
    const result=await apiRequest('/api/integrations/sources',{method:'PUT',body:JSON.stringify({activeSource:requested})});
    state.activeSource=result.activeSource||null;
    state.integrationSources=result.sources||{};
    renderSource();
    showToast(requested?`${requested==='crewbrain'?'CrewBrain':'easyjob'} ist jetzt die aktive Datenquelle.`:'Externe Datenquellen wurden deaktiviert. Manuelle Eingaben bleiben möglich.');
  }catch(error){
    await loadIntegrationSources();
    showToast(error.message);
  }
}));

// Slides and channel builder
function readStoredJson(key, fallback) {
  try {
    const stored = localStorage.getItem(key);
    return stored ? JSON.parse(stored) : fallback;
  } catch (error) {
    return fallback;
  }
}

state.displays = [];
state.groups = [];
state.slides = [];
state.channels = [];
state.templates = [];
state.mediaFolders = [];
state.mediaAssets = [];
state.activeMediaFolderId = null;
state.activeMediaPageFolderId = null;
state.mediaPageTypeFilter = '';
state.mediaPageTrash = false;
state.mediaSelectHandler = null;
state.editingTemplateId = null;
state.activeChannelId = null;
state.playlist = [];
state.contentLoaded = false;
state.channelDirty = false;
state.channelAutosaveTimer = null;
state.channelSaveChain = Promise.resolve();
state.channelSaveRevision = 0;
state.channelUndo = [];
state.channelRedo = [];
state.channelHistoryCurrent = null;

const slideTypeLabels = {
  custom: 'Eigene Slide', event: 'Veranstaltung', 'event-field': 'Dynamisches Eventfeld', text: 'Freitext', ticker: 'Laufelement', weather: 'Wetterdaten', 'qr-code': 'QR-Code', countdown: 'Countdown', image: 'Bild', video: 'Video', web: 'Website', pdf: 'PDF', wayfinding: 'Beschilderung / Wegweisung'
};
const orientationLabels = { auto: 'Automatisch / responsiv', landscape: 'Querformat', portrait: 'Hochformat' };

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
}

function slideThumb(slide) {
  const defaults = defaultSlideSettings();
  const settings = { ...defaults, ...(slide.settings || {}), background: { ...defaults.background, ...(slide.settings?.background || {}) }, logo: { ...defaults.logo, ...(slide.settings?.logo || {}) }, clock: { ...defaults.clock, ...(slide.settings?.clock || {}) } };
  const elements = (slide.elements || []).map(element => thumbnailElementMarkup(element, settings)).join('');
  return `<span class="slide-thumb slide-thumb-designed type-${escapeHtml(slide.type)} ${slide.ratio === 'auto' ? 'is-auto' : ''}"><span class="slide-thumb-surface ${slide.ratio === 'portrait' ? 'is-portrait' : ''}">${slideDecorationsMarkup(settings)}${elements || `<span class="slide-thumb-empty">${escapeHtml(slideTypeLabels[slide.type] || 'Slide')}</span>`}</span>${slide.ratio === 'auto' ? '<i class="slide-thumb-auto-badge">AUTO</i>' : ''}</span>`;
}

function thumbnailElementMarkup(element, settings) {
  const style = `left:${Number(element.x || 0)}%;top:${Number(element.y || 0)}%;width:${Number(element.width || 40)}%;height:${Number(element.height || 20)}%;background:${escapeHtml(element.background || 'transparent')};color:${escapeHtml(element.color || '#ffffff')};font-size:${Math.max(3, Number(element.fontSize || 24) / 8)}px;text-align:${escapeHtml(element.align || 'left')};transform:rotate(${Number(element.rotation || 0)}deg);opacity:${Number(element.opacity ?? 100) / 100};padding:${Math.max(0, Number(element.padding || 0) / 8)}px;border-radius:${Math.max(0, Number(element.radius || 0) / 8)}px`;
  let content = escapeHtml(element.text || '');
  if (element.type === 'wayfinding') content = wayfindingMarkup(element, settings);
  if (element.type === 'ticker') content = `<span class="slide-thumb-ticker">⇠ ${escapeHtml(tickerElementItems(element).map(item => item.kind === 'text' ? item.text : item.kind === 'countdown' ? 'Countdown' : item.kind === 'qr-code' ? 'QR-Code' : item.icon || 'Icon').join(' · '))}</span>`;
  if (element.type === 'weather') content = '<span class="slide-thumb-weather">☀ 21°</span>';
  if (element.type === 'qr-code') content = qrSvgMarkup(qrElementPayload(element), element.qrForeground, element.background);
  if (element.type === 'countdown') content = `<span class="slide-thumb-countdown">${escapeHtml(element.prefix || '')}${countdownText(element)}${escapeHtml(element.suffix || '')}</span>`;
  if (element.type === 'image') content = element.src ? `<img src="${escapeHtml(element.src)}" alt="">` : '<span class="slide-thumb-symbol">▧</span>';
  if (element.type === 'video') content = element.src ? `<video src="${escapeHtml(element.src)}" muted preload="metadata"></video>` : '<span class="slide-thumb-symbol">▶</span>';
  if (element.type === 'web') content = '<span class="slide-thumb-symbol">↗</span>';
  if (element.type === 'event') content = `<span class="slide-thumb-event"><small>Herzlich willkommen zu</small><strong>${escapeHtml(element.event || element.text || 'Veranstaltung')}</strong><i>Einlass: 18:30 · Beginn: 19:30 · Pause: 20:15 · Ende: 22:00</i></span>`;
  if (element.type === 'event-field') content = escapeHtml(`${element.prefix || ''}${element.fallback || 'Veranstaltungsfeld'}`);
  return `<span class="slide-thumb-element type-${escapeHtml(element.type)}" style="${style}">${content}</span>`;
}

function setAutosaveStatus(scope, status, message) {
  const node = document.querySelector(`#${scope}-autosave-status`);
  if (!node) return;
  node.dataset.state = status;
  node.textContent = message;
}

function savedAtLabel() {
  return `Gespeichert · ${new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}`;
}

function saveChannelState(options = {}) {
  if (!activeChannel()) return false;
  state.channelDirty = true;
  state.channelSaveRevision += 1;
  clearTimeout(state.channelAutosaveTimer);
  setAutosaveStatus('channel', 'pending', 'Änderungen ausstehend');
  state.channelAutosaveTimer = setTimeout(() => {
    flushChannelAutosave({ successMessage: options.successMessage }).catch(() => {});
  }, options.immediate ? 0 : 650);
  return true;
}

function channelSnapshot() {
  return JSON.stringify({
    playlist: structuredClone(state.playlist),
    transition: document.querySelector('#channel-transition').value,
    orientation: document.querySelector('#channel-orientation').value,
    repeatEnabled: document.querySelector('#channel-repeat').checked
  });
}

function syncChannelHistoryButtons() {
  const available = Boolean(activeChannel());
  const undo = document.querySelector('#channel-undo-button');
  const redo = document.querySelector('#channel-redo-button');
  if (undo) undo.disabled = !available || !state.channelUndo?.length;
  if (redo) redo.disabled = !available || !state.channelRedo?.length;
}

function commitChannelHistoryStep() {
  const next = channelSnapshot();
  if (state.channelHistoryCurrent && next !== state.channelHistoryCurrent) {
    state.channelUndo.push(state.channelHistoryCurrent);
    if (state.channelUndo.length > 60) state.channelUndo.shift();
    state.channelRedo = [];
  }
  state.channelHistoryCurrent = next;
  syncChannelHistoryButtons();
}

function restoreChannelSnapshot(snapshot) {
  const value = JSON.parse(snapshot);
  state.playlist = structuredClone(value.playlist || []);
  document.querySelector('#channel-transition').value = value.transition || 'fade';
  document.querySelector('#channel-orientation').value = value.orientation || 'auto';
  document.querySelector('#channel-repeat').checked = value.repeatEnabled !== false;
  renderPlaylist();
}

function undoChannel() {
  if (!state.channelUndo?.length || !activeChannel()) return;
  state.channelRedo.push(channelSnapshot());
  const snapshot = state.channelUndo.pop();
  restoreChannelSnapshot(snapshot);
  state.channelHistoryCurrent = snapshot;
  syncChannelHistoryButtons();
  saveChannelState({ immediate: true, successMessage: 'Schritt wurde zurückgenommen und gespeichert.' });
}

function redoChannel() {
  if (!state.channelRedo?.length || !activeChannel()) return;
  state.channelUndo.push(channelSnapshot());
  const snapshot = state.channelRedo.pop();
  restoreChannelSnapshot(snapshot);
  state.channelHistoryCurrent = snapshot;
  syncChannelHistoryButtons();
  saveChannelState({ immediate: true, successMessage: 'Schritt wurde wiederholt und gespeichert.' });
}

function slideValidityLabel(record) {
  const now = Date.now();
  const startsAt = record.startsAt ? Date.parse(record.startsAt) : null;
  const endsAt = record.endsAt ? Date.parse(record.endsAt) : null;
  const format = value => new Date(value).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' });
  if (startsAt && startsAt > now) return `aktiv ab ${format(startsAt)}`;
  if (endsAt && endsAt <= now) return `abgelaufen seit ${format(endsAt)}`;
  if (endsAt) return `aktiv bis ${format(endsAt)}`;
  if (startsAt) return `aktiv seit ${format(startsAt)}`;
  return '';
}

function slideForUi(record) {
  const documentData = hydrateMediaReferences(structuredClone(record.document || {}));
  const validity = slideValidityLabel(record);
  return { ...record, title: record.name, ratio: record.orientation, designOrientation: documentData.designOrientation || (record.orientation === 'portrait' ? 'portrait' : 'landscape'), elements: Array.isArray(documentData.elements) ? documentData.elements : [], settings: documentData.settings || {}, meta: `Version ${record.currentVersion} · ${record.status === 'published' ? 'veröffentlicht' : record.status === 'ready' ? 'bereit' : 'Entwurf'}${validity ? ` · ${validity}` : ''}`, thumbTitle: documentData.thumbTitle || record.name, thumbSub: documentData.thumbSub || orientationLabels[record.orientation] || 'Querformat' };
}

function tagInput(value){return[...new Set(String(value||'').split(',').map(tag=>tag.trim()).filter(Boolean))];}
function contentTagMarkup(tags=[]){return tags.length?`<span class="content-tags">${tags.map(tag=>`<i>${escapeHtml(tag)}</i>`).join('')}</span>`:'';}
function isAdvertisingContent(item){return Boolean(item?.automationKey?.startsWith('advertising:'))||(item?.tags||[]).some(tag=>String(tag).toLocaleLowerCase('de-DE')==='werbung');}
function scopedSlides(){return state.slides.filter(slide=>state.contentScope==='advertising'?isAdvertisingContent(slide):!isAdvertisingContent(slide));}
function scopedChannels(){return state.channels.filter(channel=>state.contentScope==='advertising'?isAdvertisingContent(channel):!isAdvertisingContent(channel));}
function scopedTags(tags=[]){return state.contentScope==='advertising'?[...new Set([...tags,'Werbung'])]:tags.filter(tag=>String(tag).toLocaleLowerCase('de-DE')!=='werbung');}
function applyContentScopeUi(){
  const advertising=state.contentScope==='advertising';
  document.querySelector('#content-module-eyebrow').textContent=advertising?'Werbemodul':'Content-Baukasten';
  document.querySelector('#content-module-title').textContent=advertising?'Werbung':'Slides & Kanäle';
  document.querySelector('#content-module-description').textContent=advertising?'Werbeslides erstellen, nach Standorten organisieren und in eigenen Werbekanälen ausspielen.':'Slides einmal erstellen, in beliebig vielen Kanälen verwenden und pro Kanal individuell anordnen.';
  document.querySelector('#content-slide-library-title').textContent=advertising?'Werbeslides':'Verfügbare Slides';
}
function populateContentTagFilters(){
  const populate=(selector,items)=>{const select=document.querySelector(selector),selected=select.value,tags=[...new Set(items.flatMap(item=>item.tags||[]))].sort((a,b)=>a.localeCompare(b,'de'));select.innerHTML=`<option value="">Alle Tags</option>${tags.map(tag=>`<option value="${escapeHtml(tag)}">${escapeHtml(tag)}</option>`).join('')}`;select.value=tags.includes(selected)?selected:'';};
  populate('#slide-tag-filter',scopedSlides());populate('#channel-tag-filter',scopedChannels());
}

function hydrateMediaReferences(value) {
  if (Array.isArray(value)) return value.map(hydrateMediaReferences);
  if (!value || typeof value !== 'object') return value;
  const hydrated = Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, hydrateMediaReferences(entry)]));
  if (hydrated.mediaAssetId) {
    const asset = state.mediaAssets.find(item => item.id === hydrated.mediaAssetId && !item.deletedAt);
    if (asset) {
      if ('imageSrc' in hydrated) hydrated.imageSrc = asset.src;
      else hydrated.src = asset.src;
      if (asset.type === 'video') {
        hydrated.trimStart = asset.metadata?.trimStart || 0;
        hydrated.trimEnd = asset.metadata?.trimEnd;
      }
    }
  }
  return hydrated;
}

function activeChannel() {
  return state.channels.find(channel => channel.id === state.activeChannelId) || null;
}

function populateContentEventOptions() {
  const options = state.events.map(event => `<option value="${escapeHtml(event.id)}">${escapeHtml(event.title)}</option>`).join('');
  document.querySelector('#channel-event').innerHTML = `<option value="">Keine feste Veranstaltung</option>${options}`;
  document.querySelector('#slide-event').innerHTML = `<option value="">Keine feste Veranstaltung</option>${options}`;
  document.querySelector('#property-event').innerHTML = state.events.map(event => `<option value="${escapeHtml(event.title)}">${escapeHtml(event.title)}</option>`).join('') || '<option value="">Keine Veranstaltung vorhanden</option>';
}

function applyActiveChannel() {
  const channel = activeChannel();
  clearTimeout(state.channelAutosaveTimer);
  state.playlist = channel ? channel.items.map(item => ({ slideId: item.slideId, duration: item.durationSeconds, transition: item.transition })) : [];
  document.querySelector('#channel-builder-title').textContent = channel?.name || 'Noch keinen Kanal angelegt';
  document.querySelector('#channel-builder-meta').innerHTML = `<span class="status-dot"></span> ${escapeHtml(channel ? `${channel.status === 'published' ? 'Veröffentlicht' : channel.status === 'ready' ? 'Bereit' : channel.status === 'paused' ? 'Pausiert' : 'Entwurf'}${channel.description ? ` · ${channel.description}` : ''}` : 'Kanal auswählen oder neu anlegen')}`;
  document.querySelector('#channel-transition').value = channel?.defaultTransition || 'fade';
  document.querySelector('#channel-orientation').value = channel?.orientation || 'auto';
  document.querySelector('#channel-repeat').checked = channel?.repeatEnabled ?? true;
  document.querySelectorAll('[data-action="duplicate-channel"], [data-action="edit-channel"], [data-action="save-channel"]').forEach(button => { button.disabled = !channel; });
  state.channelDirty = false;
  state.channelUndo = [];
  state.channelRedo = [];
  state.channelHistoryCurrent = channel ? channelSnapshot() : null;
  syncChannelHistoryButtons();
  setAutosaveStatus('channel', channel ? 'saved' : 'idle', channel ? 'Alle Änderungen gespeichert' : 'Autosave bereit');
  renderPlaylist();
  renderChannelOverview();
}

async function loadContent(preferredChannelId = state.activeChannelId) {
  try {
    const mediaRequest = state.currentUser?.role === 'viewer' ? Promise.resolve({ folders: [], assets: [] }) : apiRequest('/api/media');
    const [slidesResult, channelsResult, templatesResult, mediaResult, locationResult] = await Promise.all([apiRequest('/api/slides'), apiRequest('/api/channels'), apiRequest('/api/templates'), mediaRequest, apiRequest('/api/locations'), state.events.length ? Promise.resolve() : loadEvents()]);
    state.mediaFolders = mediaResult.folders || [];
    state.mediaAssets = mediaResult.assets || [];
    state.locations = locationResult.data || [];
    state.slides = (slidesResult.data || []).map(slideForUi);
    state.selectedSlides = new Set([...state.selectedSlides].filter(id => scopedSlides().some(slide => slide.id === id)));
    state.channels = channelsResult.data || [];
    state.templates = (templatesResult.data || []).map(template => ({ ...template, document: hydrateMediaReferences(structuredClone(template.document || {})) }));
    const availableChannels=scopedChannels();
    state.activeChannelId = availableChannels.some(channel => channel.id === preferredChannelId) ? preferredChannelId : availableChannels[0]?.id || null;
    state.contentLoaded = true;
    applyContentScopeUi();
    populateContentEventOptions();
    populateContentTagFilters();
    renderSlideLibrary();
    renderChannelOverview();
    renderTemplates();
    applyActiveChannel();
  } catch (error) { showToast(error.message); }
}

function formatDuration(seconds){const value=Math.max(0,Number(seconds||0));if(!value)return'0 Sek.';const hours=Math.floor(value/3600),minutes=Math.floor(value%3600/60),rest=value%60;return[hours?`${hours} Std.`:'',minutes?`${minutes} Min.`:'',rest?`${rest} Sek.`:''].filter(Boolean).join(' ');}
function channelDuration(channel){return Number(channel?.totalDurationSeconds??channel?.items?.reduce((sum,item)=>sum+Number(item.durationSeconds||0),0)??0);}
function channelStatusLabel(status){return status==='published'?'Veröffentlicht':status==='ready'?'Bereit':status==='paused'?'Pausiert':'Entwurf';}
function renderChannelOverview(){
  const table=document.querySelector('#channel-overview');if(!table)return;
  const scoped=scopedChannels(),query=document.querySelector('#channel-search').value.trim().toLowerCase(),tag=document.querySelector('#channel-tag-filter').value,channels=scoped.filter(channel=>(!tag||(channel.tags||[]).includes(tag))&&(!query||`${channel.name} ${channel.description||''} ${(channel.tags||[]).join(' ')} ${channel.items.map(item=>state.slides.find(slide=>slide.id===item.slideId)?.title||'').join(' ')}`.toLowerCase().includes(query)));
  document.querySelector('#channel-count').textContent=scoped.length;
  table.innerHTML=channels.map(channel=>`<button class="channel-list-item ${channel.id===state.activeChannelId?'is-active':''}" type="button" role="option" aria-selected="${channel.id===state.activeChannelId}" data-channel-switch="${escapeHtml(channel.id)}"><span class="channel-list-title"><strong>${escapeHtml(channel.name)}</strong><small>${escapeHtml(channel.description||'Ohne Beschreibung')}</small>${contentTagMarkup(channel.tags)}</span><span class="channel-list-status ${channel.status}"><i></i>${escapeHtml(channelStatusLabel(channel.status))}</span><span class="channel-list-meta">${channel.items.length} Slide${channel.items.length===1?'':'s'} · ${escapeHtml(formatDuration(channelDuration(channel)))} · ${channel.locationIds?.length?`${channel.locationIds.length} Standort${channel.locationIds.length===1?'':'e'}`:'alle Standorte'}</span><span class="channel-list-arrow" aria-hidden="true">›</span></button>`).join('')||'<p class="channel-list-empty">Keine passenden Kanäle gefunden.</p>';
}
document.querySelector('#channel-search').addEventListener('input',renderChannelOverview);
document.querySelector('#channel-tag-filter').addEventListener('change',renderChannelOverview);
document.querySelector('#channel-overview').addEventListener('click',async event=>{
  const button=event.target.closest('[data-channel-switch]');if(!button||button.dataset.channelSwitch===state.activeChannelId)return;
  try{await flushChannelAutosave();state.activeChannelId=button.dataset.channelSwitch;applyActiveChannel();}catch(error){showToast(error.message);}
});

function addSlideToPlaylist(slideId, insertAt = state.playlist.length) {
  const slide = state.slides.find(item => item.id === slideId);
  if (!slide) return;
  const duration = slide.type === 'video' ? 18 : 12;
  state.playlist.splice(insertAt, 0, { slideId, duration });
  commitChannelHistoryStep();
  saveChannelState({ immediate: true, successMessage: `„${slide.title}“ wurde hinzugefügt und gespeichert.` });
  renderPlaylist();
}

function renderSlideLibrary() {
  const library = document.querySelector('#slide-library');
  if (!library) return;
  const search = document.querySelector('#slide-search').value.trim().toLowerCase();
  const filter = document.querySelector('#slide-filter').value;
  const tag=document.querySelector('#slide-tag-filter').value;
  const availableSlides=scopedSlides();
  const visibleSlides = availableSlides.filter(slide => {
    const matchesSearch = !search || `${slide.title} ${slide.meta} ${(slide.tags||[]).join(' ')}`.toLowerCase().includes(search);
    return matchesSearch && (!tag||(slide.tags||[]).includes(tag)) && (filter === 'all' || slide.type === filter);
  });
  document.querySelector('#slide-count').textContent = availableSlides.length;
  const selectedVisible = visibleSlides.filter(slide => state.selectedSlides.has(slide.id)).length;
  const selectAll = document.querySelector('#slide-select-all');
  selectAll.checked = Boolean(visibleSlides.length) && selectedVisible === visibleSlides.length;
  selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleSlides.length;
  selectAll.disabled = !visibleSlides.length;
  document.querySelector('#slide-selection-count').textContent = `${state.selectedSlides.size} ausgewählt`;
  ['slide-bulk-rename', 'slide-bulk-duplicate', 'slide-bulk-archive'].forEach(id => { document.querySelector(`#${id}`).disabled = !state.selectedSlides.size; });
  document.querySelector('#slide-bulk-delete').disabled = !state.selectedSlides.size || state.currentUser?.role !== 'admin';
  const canEditContent = state.currentUser?.role !== 'viewer';
  library.innerHTML = visibleSlides.map(slide => `
    <div class="slide-library-card ${state.selectedSlides.has(slide.id) ? 'is-selected' : ''}" draggable="${canEditContent}" data-slide-id="${escapeHtml(slide.id)}">
      ${canEditContent ? `<label class="slide-library-check"><input type="checkbox" data-slide-select="${escapeHtml(slide.id)}" aria-label="${escapeHtml(slide.title)} auswählen" ${state.selectedSlides.has(slide.id) ? 'checked' : ''}></label>` : '<span></span>'}
      ${slideThumb(slide)}
      <span class="slide-card-info"><strong>${escapeHtml(slide.title)}</strong><small>${escapeHtml(slideTypeLabels[slide.type])} · ${escapeHtml(slide.meta)}</small>${contentTagMarkup(slide.tags)}</span>
      <span class="slide-card-actions">${canEditContent ? `<button type="button" data-add-library-slide="${escapeHtml(slide.id)}" title="Zum Kanal hinzufügen">＋</button>` : ''}<button type="button" data-version-library-slide="${escapeHtml(slide.id)}" title="Versionen anzeigen">Versionen</button>${canEditContent ? `<button type="button" data-edit-library-slide="${escapeHtml(slide.id)}" title="Slide bearbeiten">Bearbeiten</button>` : ''}</span>
    </div>`).join('') || '<p class="property-note">Noch keine passende Slide vorhanden.</p>';

  library.querySelectorAll('.slide-library-card').forEach(card => {
    card.addEventListener('dragstart', event => {
      if (!canEditContent || event.target.closest('input, button, label')) { event.preventDefault(); return; }
      event.dataTransfer.setData('application/x-kiosky-slide', card.dataset.slideId);
      event.dataTransfer.effectAllowed = 'copy';
      card.classList.add('dragging');
    });
    card.addEventListener('dragend', () => card.classList.remove('dragging'));
  });
  library.querySelectorAll('[data-add-library-slide]').forEach(button => button.addEventListener('click', () => addSlideToPlaylist(button.dataset.addLibrarySlide)));
  library.querySelectorAll('[data-version-library-slide]').forEach(button => button.addEventListener('click', () => openSlideVersions(state.slides.find(slide => slide.id === button.dataset.versionLibrarySlide))));
  library.querySelectorAll('[data-edit-library-slide]').forEach(button => button.addEventListener('click', () => openSlideEditor(state.slides.find(slide => slide.id === button.dataset.editLibrarySlide))));
}

function visibleLibrarySlides() {
  const search = document.querySelector('#slide-search').value.trim().toLowerCase();
  const filter = document.querySelector('#slide-filter').value;
  const tag=document.querySelector('#slide-tag-filter').value;
  return scopedSlides().filter(slide => (!search || `${slide.title} ${slide.meta} ${(slide.tags||[]).join(' ')}`.toLowerCase().includes(search)) && (!tag||(slide.tags||[]).includes(tag)) && (filter === 'all' || slide.type === filter));
}

document.querySelector('#slide-select-all').addEventListener('change', event => {
  visibleLibrarySlides().forEach(slide => event.target.checked ? state.selectedSlides.add(slide.id) : state.selectedSlides.delete(slide.id));
  renderSlideLibrary();
});
document.querySelector('#slide-library').addEventListener('change', event => {
  const checkbox = event.target.closest('[data-slide-select]');
  if (!checkbox) return;
  checkbox.checked ? state.selectedSlides.add(checkbox.dataset.slideSelect) : state.selectedSlides.delete(checkbox.dataset.slideSelect);
  renderSlideLibrary();
});

function slideUpdatePayload(slide, name) {
  return {
    name,
    eventId: slide.eventId || undefined,
    orientation: slide.orientation,
    type: slide.type,
    document: slide.document,
    status: slide.status,
    startsAt: slide.startsAt || undefined,
    endsAt: slide.endsAt || undefined,
    tags: slide.tags || []
  };
}

async function bulkSlides(action) {
  const slides = [...state.selectedSlides].map(id => state.slides.find(slide => slide.id === id)).filter(Boolean);
  if (!slides.length) return;
  try {
    if (action === 'rename') {
      const renames = [];
      for (const slide of slides) {
        const name = window.prompt(`Neuer Name für „${slide.title}“:`, slide.title);
        if (name === null) continue;
        if (!name.trim()) { showToast('Ein Slide-Name darf nicht leer sein.'); return; }
        if (name.trim() !== slide.title) renames.push({ slide, name: name.trim() });
      }
      for (const item of renames) await apiRequest(`/api/slides/${encodeURIComponent(item.slide.id)}`, { method: 'PUT', body: JSON.stringify(slideUpdatePayload(item.slide, item.name)) });
      if (!renames.length) return;
    } else {
      const label = action === 'duplicate' ? 'duplizieren' : action === 'archive' ? 'archivieren' : 'endgültig löschen';
      if (!window.confirm(`${slides.length} Slide${slides.length === 1 ? '' : 's'} ${label}?`)) return;
      for (const slide of slides) {
        if (action === 'duplicate') await apiRequest(`/api/slides/${encodeURIComponent(slide.id)}/duplicate`, { method: 'POST' });
        else await apiRequest(`/api/slides/${encodeURIComponent(slide.id)}${action === 'delete' ? '/permanent' : ''}`, { method: 'DELETE' });
      }
    }
    state.selectedSlides.clear();
    await loadContent();
    showToast(action === 'rename' ? 'Slide-Namen wurden aktualisiert.' : 'Slide-Aktion wurde ausgeführt.');
  } catch (error) { showToast(error.message); }
}
document.querySelector('#slide-bulk-rename').addEventListener('click', () => bulkSlides('rename'));
document.querySelector('#slide-bulk-duplicate').addEventListener('click', () => bulkSlides('duplicate'));
document.querySelector('#slide-bulk-archive').addEventListener('click', () => bulkSlides('archive'));
document.querySelector('#slide-bulk-delete').addEventListener('click', () => bulkSlides('delete'));

async function openSlideVersions(slide) {
  if (!slide) return;
  const dialog = document.querySelector('#slide-versions-dialog'), list = document.querySelector('#slide-version-list');
  const canEditContent = state.currentUser?.role !== 'viewer';
  document.querySelector('#slide-versions-title').textContent = `Versionen · ${slide.title}`;
  list.innerHTML = '<p class="property-note">Versionen werden geladen …</p>'; dialog.showModal();
  try {
    const result = await apiRequest(`/api/slides/${encodeURIComponent(slide.id)}/versions`);
    list.innerHTML = (result.data || []).map(version => {
      const previewSlide = { ...slide, ratio: version.document.orientation || slide.ratio, type: version.document.type || slide.type, elements: version.document.elements || [], settings: version.document.settings || {} };
      return `<article class="slide-version-card" data-slide-version="${version.version}">${slideThumb(previewSlide)}<span><strong>Version ${version.version}${version.version === slide.currentVersion ? ' · aktuell' : ''}</strong><small>${escapeHtml(new Date(version.createdAt).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }))}</small><p>${escapeHtml(version.changeNote || 'Im Slide-Editor gespeichert')}</p></span>${canEditContent ? `<div><button class="button button-secondary" type="button" data-preview-slide-version="${version.version}">Im Editor ansehen</button>${version.version === slide.currentVersion ? '' : `<button class="button button-primary" type="button" data-restore-slide-version="${version.version}">Wiederherstellen</button>`}</div>` : ''}</article>`;
    }).join('') || '<p class="property-note">Noch keine Versionen vorhanden.</p>';
    list.onclick = async event => {
      const preview = event.target.closest('[data-preview-slide-version]'), restore = event.target.closest('[data-restore-slide-version]');
      if (preview) {
        const version = result.data.find(item => item.version === Number(preview.dataset.previewSlideVersion)); if (!version) return;
        dialog.close(); openSlideEditor({ ...slide, ratio: version.document.orientation || slide.ratio, elements: structuredClone(version.document.elements || []), settings: structuredClone(version.document.settings || {}) }); showToast(`Version ${version.version} ist im Editor geöffnet. Beim Speichern entsteht eine neue Version.`);
      }
      if (restore) {
        const number = Number(restore.dataset.restoreSlideVersion); if (!window.confirm(`Version ${number} als neuen aktuellen Stand wiederherstellen?`)) return;
        await apiRequest(`/api/slides/${encodeURIComponent(slide.id)}/versions/${number}/restore`, { method: 'POST' }); dialog.close(); await loadContent(); showToast(`Version ${number} wurde als neuer Stand wiederhergestellt.`);
      }
    };
  } catch (error) { list.innerHTML = `<p class="auth-message">${escapeHtml(error.message)}</p>`; }
}

function renderPlaylist() {
  const playlist = document.querySelector('#channel-playlist');
  if (!playlist) return;
  const canEditContent = state.currentUser?.role !== 'viewer';
  const playlistTransitionLabels = { fade: 'Überblenden', slide: 'Wischen', zoom: 'Zoom', none: 'Ohne Übergang' };
  playlist.innerHTML = state.playlist.map((entry, index) => {
    const slide = state.slides.find(item => item.id === entry.slideId);
    if (!slide) return '';
    return `<div class="playlist-item" draggable="${canEditContent}" data-playlist-index="${index}">
      <span class="playlist-order">${index + 1}</span>
      ${slideThumb(slide)}
      <span class="playlist-item-info"><strong>${escapeHtml(slide.title)}</strong><small>${escapeHtml(slideTypeLabels[slide.type])} · ${escapeHtml(slide.meta)}</small></span>
      ${canEditContent ? `<label class="transition-field"><span>Übergang danach</span><select data-transition-index="${index}" aria-label="Übergang nach ${escapeHtml(slide.title)}"><option value="inherit" ${!entry.transition || entry.transition === 'inherit' ? 'selected' : ''}>Kanaleinstellung</option><option value="fade" ${entry.transition === 'fade' ? 'selected' : ''}>Überblenden</option><option value="slide" ${entry.transition === 'slide' ? 'selected' : ''}>Wischen</option><option value="zoom" ${entry.transition === 'zoom' ? 'selected' : ''}>Zoom</option><option value="none" ${entry.transition === 'none' ? 'selected' : ''}>Ohne</option></select></label>
      <label class="duration-field"><input type="number" min="3" max="600" value="${entry.duration}" data-duration-index="${index}" aria-label="Dauer für ${escapeHtml(slide.title)}"><span>Sek.</span></label>
      <button class="remove-slide" type="button" data-remove-index="${index}" aria-label="${escapeHtml(slide.title)} entfernen">×</button>` : `<span class="playlist-readonly-meta">${Number(entry.duration)} Sek. · ${escapeHtml(entry.transition && entry.transition !== 'inherit' ? playlistTransitionLabels[entry.transition] || entry.transition : 'Kanalübergang')}</span>`}
    </div>`;
  }).join('');

  const totalDuration = state.playlist.reduce((total, entry) => total + Number(entry.duration), 0);
  document.querySelector('#channel-slide-summary').textContent = state.playlist.length;
  document.querySelector('#channel-duration-summary').textContent = formatDuration(totalDuration);
  document.querySelector('#preview-slide-count').textContent = `${state.playlist.length} Slides im Kanal`;

  playlist.querySelectorAll('[data-remove-index]').forEach(button => button.addEventListener('click', () => {
    const removed = state.playlist.splice(Number(button.dataset.removeIndex), 1)[0];
    const slide = state.slides.find(item => item.id === removed.slideId);
    commitChannelHistoryStep();
    saveChannelState({ immediate: true, successMessage: `„${slide ? slide.title : 'Slide'}“ wurde entfernt und gespeichert.` });
    renderPlaylist();
  }));
  playlist.querySelectorAll('[data-duration-index]').forEach(input => input.addEventListener('change', () => {
    state.playlist[Number(input.dataset.durationIndex)].duration = Math.max(3, Number(input.value) || 3);
    commitChannelHistoryStep();
    saveChannelState();
    renderPlaylist();
  }));
  playlist.querySelectorAll('[data-transition-index]').forEach(select => select.addEventListener('change', () => {
    state.playlist[Number(select.dataset.transitionIndex)].transition = select.value;
    commitChannelHistoryStep();
    saveChannelState();
  }));
  playlist.querySelectorAll('.playlist-item').forEach(item => {
    item.addEventListener('dragstart', event => {
      if (!canEditContent) { event.preventDefault(); return; }
      event.dataTransfer.setData('application/x-kiosky-playlist-index', item.dataset.playlistIndex);
      event.dataTransfer.effectAllowed = 'move';
      item.classList.add('dragging');
    });
    item.addEventListener('dragend', () => item.classList.remove('dragging'));
    item.addEventListener('dragover', event => { event.preventDefault(); item.classList.add('drag-over'); });
    item.addEventListener('dragleave', () => item.classList.remove('drag-over'));
    item.addEventListener('drop', event => {
      event.preventDefault();
      item.classList.remove('drag-over');
      const librarySlide = event.dataTransfer.getData('application/x-kiosky-slide');
      const destination = Number(item.dataset.playlistIndex);
      if (librarySlide) {
        addSlideToPlaylist(librarySlide, destination);
        return;
      }
      const sourceValue = event.dataTransfer.getData('application/x-kiosky-playlist-index');
      if (sourceValue === '') return;
      const source = Number(sourceValue);
      const [moved] = state.playlist.splice(source, 1);
      state.playlist.splice(source < destination ? destination - 1 : destination, 0, moved);
      commitChannelHistoryStep();
      saveChannelState({ immediate: true, successMessage: 'Die neue Slide-Reihenfolge wurde gespeichert.' });
      renderPlaylist();
    });
  });
}

const playlistDrop = document.querySelector('#playlist-drop');
if (playlistDrop) {
  playlistDrop.addEventListener('dragover', event => { event.preventDefault(); playlistDrop.classList.add('drag-over'); });
  playlistDrop.addEventListener('dragleave', () => playlistDrop.classList.remove('drag-over'));
  playlistDrop.addEventListener('drop', event => {
    event.preventDefault();
    playlistDrop.classList.remove('drag-over');
    const slideId = event.dataTransfer.getData('application/x-kiosky-slide');
    if (slideId) addSlideToPlaylist(slideId);
  });
  playlistDrop.addEventListener('click', () => document.querySelector('#slide-search').focus());
}

document.querySelector('#slide-search').addEventListener('input', renderSlideLibrary);
document.querySelector('#slide-filter').addEventListener('change', renderSlideLibrary);
document.querySelector('#slide-tag-filter').addEventListener('change',renderSlideLibrary);
const slideDialog = document.querySelector('#slide-dialog');
slideDialog.addEventListener('cancel', event => {
  if (state.editingTemplateId) return;
  event.preventDefault();
  flushSlideAutosave().then(() => slideDialog.close()).catch(() => {});
});
state.editorElements = [];
state.selectedEditorElementId = null;
state.editorSlideSettings = {};
state.showSlideSettings = false;
state.editorAutoDesignOrientation = 'landscape';
state.slideDirty = false;
state.slideAutosaveTimer = null;
state.slideSaveChain = Promise.resolve();
state.slideSaveRevision = 0;
state.slideLastScheduledSnapshot = '';

function defaultSlideSettings() {
  return { background: { mode: 'color', color1: '#17342b', color2: '#315b49', direction: '135deg', imageSrc: '', imageFit: 'cover', imageX: 50, imageY: 50, imageZoom: 100 }, logo: { src: '', position: 'top-left', theme: 'none', scale: 100, naturalWidth: 400, naturalHeight: 160, widthPercent: 20.83 }, clock: { enabled: false, position: 'bottom-right', theme: 'dark' } };
}

function slideDateTimeInputValue(value) {
  if (!value) return '';
  const date = new Date(value);
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function slideDateTimeIso(value) {
  return value ? new Date(value).toISOString() : undefined;
}

function renderSlideLocationOptions(selectedIds=[]){
  const selected=new Set(selectedIds),container=document.querySelector('#slide-location-options'),summary=document.querySelector('#slide-location-summary');
  if(!container)return;
  container.innerHTML=state.locations.length?state.locations.map(location=>`<label><input type="checkbox" value="${escapeHtml(location.id)}" ${selected.has(location.id)?'checked':''}><span>${escapeHtml(location.name)}</span></label>`).join(''):'<small>Noch keine Standorte angelegt.</small>';
  summary.textContent=selected.size?`${selected.size} ausgewählt`:'Alle';
}

function slideEditorPayload() {
  const title = document.querySelector('#new-slide-title').value.trim();
  if (!title) return null;
  const orientation = document.querySelector('#editor-ratio').value;
  const startsAt = slideDateTimeIso(document.querySelector('#slide-starts-at').value);
  const endsAt = slideDateTimeIso(document.querySelector('#slide-ends-at').value);
  if (startsAt && endsAt && Date.parse(endsAt) <= Date.parse(startsAt)) throw new Error('Das Sichtbar-bis-Datum muss nach dem Start liegen.');
  const canvas = document.querySelector('#slide-editor-canvas');
  const canvasRect = canvas.getBoundingClientRect();
  const designOrientation = canvasRect.height > canvasRect.width ? 'portrait' : 'landscape';
  return {
    name: title,
    eventId: document.querySelector('#slide-event').value || undefined,
    type: 'custom',
    orientation,
    status: document.querySelector('#slide-status').value,
    startsAt,
    endsAt,
    tags:scopedTags(tagInput(document.querySelector('#slide-tags').value)),
    locationIds:[...document.querySelectorAll('#slide-location-options input:checked')].map(input=>input.value),
    document: {
      thumbTitle: title,
      thumbSub: orientationLabels[orientation] || 'Querformat',
      designOrientation,
      settings: structuredClone(state.editorSlideSettings),
      elements: state.editorElements.map(element => ({ ...element, src: String(element.src || '').startsWith('blob:') ? '' : element.src }))
    }
  };
}

async function persistSlideEditor() {
  const payload = slideEditorPayload();
  if (!payload) throw new Error('Bitte einen Namen für die Slide eingeben.');
  const idInput = document.querySelector('#slide-edit-id');
  const id = idInput.value;
  const result = await apiRequest(id ? `/api/slides/${id}` : '/api/slides', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  idInput.value = result.slide.id;
  document.querySelector('#slide-editor-title').textContent = 'Slide bearbeiten';
  document.querySelector('#archive-slide-button').hidden = state.currentUser?.role === 'viewer';
  document.querySelector('#duplicate-slide-button').hidden = false;
  document.querySelector('#delete-slide-button').hidden = state.currentUser?.role !== 'admin';
  const uiSlide = slideForUi(result.slide);
  const index = state.slides.findIndex(slide => slide.id === uiSlide.id);
  if (index >= 0) state.slides[index] = uiSlide;
  else state.slides.unshift(uiSlide);
  renderSlideLibrary();
  document.querySelector('#slide-count').textContent = scopedSlides().length;
  return uiSlide;
}

function scheduleSlideAutosave(options = {}) {
  const snapshot = editorSnapshot();
  state.editorLastSnapshot = snapshot;
  if (!slideDialog.open || state.editingTemplateId) return false;
  if (snapshot === state.slideLastScheduledSnapshot) return false;
  state.slideLastScheduledSnapshot = snapshot;
  state.slideDirty = true;
  state.slideSaveRevision += 1;
  clearTimeout(state.slideAutosaveTimer);
  setAutosaveStatus('slide', 'pending', 'Speichert in 5 Sekunden …');
  state.slideAutosaveTimer = setTimeout(() => {
    flushSlideAutosave({ successMessage: options.successMessage }).catch(() => {});
  }, 5000);
  return true;
}

async function flushSlideAutosave(options = {}) {
  clearTimeout(state.slideAutosaveTimer);
  state.slideAutosaveTimer = null;
  if (state.editingTemplateId || (!state.slideDirty && !options.force)) return null;
  const revision = state.slideSaveRevision;
  state.slideDirty = false;
  setAutosaveStatus('slide', 'saving', 'Speichert …');
  const operation = state.slideSaveChain.catch(() => {}).then(() => persistSlideEditor());
  state.slideSaveChain = operation;
  try {
    const slide = await operation;
    state.slideLastScheduledSnapshot = editorSnapshot();
    if (state.slideSaveRevision === revision && !state.slideDirty) setAutosaveStatus('slide', 'saved', savedAtLabel());
    if (options.successMessage) showToast(options.successMessage);
    return slide;
  } catch (error) {
    state.slideDirty = true;
    setAutosaveStatus('slide', 'error', 'Speichern fehlgeschlagen');
    if (!options.silent) showToast(`Autosave fehlgeschlagen: ${error.message}`);
    throw error;
  }
}

function applyEditorOrientation(ratio, designOrientation) {
  const canvas = document.querySelector('#slide-editor-canvas');
  state.editorAutoDesignOrientation = designOrientation === 'portrait' ? 'portrait' : 'landscape';
  canvas.dataset.orientationMode = ratio;
  canvas.dataset.ratio = ratio === 'auto' ? state.editorAutoDesignOrientation : ratio;
}

function openSlideEditor(slide = null, options = {}) {
  clearTimeout(state.slideAutosaveTimer);
  state.slideDirty = false;
  state.slideLastScheduledSnapshot = '';
  state.editingTemplateId = options.templateId || null;
  document.querySelector('#slide-edit-id').value = slide?.id || '';
  document.querySelector('#slide-editor-title').textContent = state.editingTemplateId ? 'Vorlage bearbeiten' : slide ? 'Slide bearbeiten' : 'Slide erstellen';
  document.querySelector('#new-slide-title').value = options.templateName || slide?.title || 'Neue Slide';
  document.querySelector('[data-action="save-template"]').textContent = state.editingTemplateId ? 'Vorlage aktualisieren' : 'Als Vorlage';
  document.querySelector('#create-slide-button').hidden = Boolean(state.editingTemplateId);
  document.querySelector('#editor-ratio').value = slide?.ratio || 'landscape';
  document.querySelector('#slide-event').value = slide?.eventId || '';
  document.querySelector('#slide-status').value = slide?.status || 'draft';
  document.querySelector('#slide-starts-at').value = slideDateTimeInputValue(slide?.startsAt);
  document.querySelector('#slide-ends-at').value = slideDateTimeInputValue(slide?.endsAt);
  document.querySelector('#slide-tags').value=scopedTags(slide?.tags||[]).join(', ');
  renderSlideLocationOptions(slide?.locationIds||[]);
  document.querySelectorAll('.slide-validity-field').forEach(field => { field.hidden = Boolean(state.editingTemplateId); });
  document.querySelector('#archive-slide-button').hidden = !slide || state.currentUser?.role === 'viewer';
  document.querySelector('#duplicate-slide-button').hidden = !slide || Boolean(state.editingTemplateId);
  document.querySelector('#delete-slide-button').hidden = !slide || Boolean(state.editingTemplateId) || state.currentUser?.role !== 'admin';
  state.editorElements = slide?.elements?.length ? slide.elements.map(element => ({ ...element })) : [createEditorElement('event', 10, 30)];
  state.editorSlideSettings = { ...defaultSlideSettings(), ...(slide?.settings || {}), background: { ...defaultSlideSettings().background, ...(slide?.settings?.background || {}) }, logo: { ...defaultSlideSettings().logo, ...(slide?.settings?.logo || {}) }, clock: { ...defaultSlideSettings().clock, ...(slide?.settings?.clock || {}) } };
  state.showSlideSettings = false;
  state.selectedEditorElementId = state.editorElements[0]?.id || null;
  state.selectedEditorElementIds=new Set(state.selectedEditorElementId?[state.selectedEditorElementId]:[]);
  state.editorUndo=[];state.editorRedo=[];
  applyEditorOrientation(document.querySelector('#editor-ratio').value, slide?.designOrientation);
  renderEditorCanvas();
  setEditorMobilePanel('');
  slideDialog.showModal();
  state.editorLastSnapshot = editorSnapshot();
  state.slideLastScheduledSnapshot = state.editorLastSnapshot;
  syncEditorHistoryButtons();
  setAutosaveStatus('slide', state.editingTemplateId ? 'idle' : 'saved', state.editingTemplateId ? 'Vorlage manuell speichern' : slide ? 'Alle Änderungen gespeichert' : 'Autosave aktiv');
}

function createEditorElement(type, x = 12, y = 16) {
  const base = { id: `element-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`, type, x, y, width: 46, height: 20, background: '#315b49', rotation: 0, opacity: 100, padding: 0, radius: 0 };
  if (type === 'text') return { ...base, text: 'Text durch Doppelklick bearbeiten', fontSize: 42, color: '#ffffff', align: 'left' };
  if (type === 'ticker') return { ...base, text: 'Aktuelle Informationen laufen hier durch das Bild', items: [{ id: `ticker-item-${Date.now()}`, kind: 'text', text: 'Aktuelle Informationen laufen hier durch das Bild' }], fontSize: 34, color: '#ffffff', background: '#17201d', align: 'left', fontWeight: 600, tickerDuration: 15, tickerDirection: 'left', x: 0, y: 82, width: 100, height: 12, padding: 0 };
  if (type === 'weather') return { ...base, weatherMode: 'temperature', fontSize: 32, fontWeight: 600, color: '#ffffff', background: '#315b49', x: 78, y: 6, width: 16, height: 12, padding: 6, radius: 10 };
  if (type === 'qr-code') return { ...base, qrKind: 'link', qrValue: 'https://example.org', wifiSsid: '', wifiPassword: '', wifiEncryption: 'WPA', wifiHidden: false, qrForeground: '#111111', background: '#ffffff', x: 64, y: 18, width: 24, height: 42, padding: 8 };
  if (type === 'countdown') return { ...base, countdownMode: 'daily', countdownDailyTime: '18:00', targetTime: '', prefix: 'Noch ', suffix: '', showSeconds: true, fontSize: 52, fontWeight: 700, color: '#ffffff', background: 'transparent', align: 'center', width: 48, height: 18 };
  if (type === 'event') return { ...base, event: 'Sommerkonzert', text: 'Sommerkonzert', fontSize: 38, color: '#ffffff', width: 80, height: 32 };
  if (type === 'event-field') return { ...base, field: 'title', prefix: '', fallback: '–', locked: true, fontSize: 42, color: '#ffffff', width: 58, height: 18 };
  if (type === 'image') return { ...base, src: '', width: 42, height: 46 };
  if (type === 'alert-icon') { const picker=document.querySelector('#alert-icon-picker'),key=picker?.value||'general-warning',alt=picker?.selectedOptions?.[0]?.textContent||'Warnsymbol'; return { ...base, type: 'image', src: assetUrl(`/media/alerts/${key}.svg`), alt, background: 'transparent', width: 24, height: 36 }; }
  if (type === 'video') return { ...base, src: '', muted: true, width: 52, height: 48 };
  if (type === 'wayfinding') return { ...base, x: 0, y: 0, width: 100, height: 100, background: 'transparent', color: '#ffffff', locked: false, logo: { src: '', position: 'top-left' }, clock: { enabled: false, position: 'bottom-right' }, rows: [
    { id: `row-${Date.now()}-1`, separator: false, expandCenter: false, left: { kind: 'arrow', direction: 'right' }, center: { kind: 'text', text: 'Großer Saal', textStyle: 'heading', align: 'left' }, right: { kind: 'text', text: '1. OG', textStyle: 'label', align: 'right' } },
    { id: `row-${Date.now()}-2`, separator: true, expandCenter: false, left: { kind: 'empty' }, center: { kind: 'text', text: 'Foyer', textStyle: 'subheading', align: 'left' }, right: { kind: 'empty' } }
  ] };
  return { ...base, src: 'https://example.org', width: 58, height: 52 };
}

function selectedEditorElement() {
  return state.editorElements.find(element => element.id === state.selectedEditorElementId);
}

function editorElementLabel(element) {
  const type = slideTypeLabels[element.type] || 'Element';
  const detail = element.type === 'event-field'
    ? document.querySelector(`#property-event-field option[value="${CSS.escape(element.field || 'title')}"]`)?.textContent
    : element.type === 'image' || element.type === 'video'
      ? state.mediaAssets.find(asset => asset.id === element.mediaAssetId)?.name
      : element.text || element.event || element.alt;
  return { type, detail: String(detail || '').trim().slice(0, 42) };
}

function editorElementsOverlap(element, other) {
  if (!element || !other || element.id === other.id) return false;
  return element.x < other.x + other.width && element.x + element.width > other.x
    && element.y < other.y + other.height && element.y + element.height > other.y;
}

function renderEditorLayers() {
  const list = document.querySelector('#editor-layer-list');
  if (!list) return;
  const selectedIds = state.selectedEditorElementIds || new Set();
  const ordered = state.editorElements.map((element, index) => ({ element, index })).reverse();
  document.querySelector('#editor-layer-count').textContent = String(ordered.length);
  document.querySelector('#editor-mobile-layer-count').textContent = String(ordered.length);
  list.innerHTML = ordered.map(({ element, index }) => {
    const label = editorElementLabel(element);
    const overlapCount = state.editorElements.filter(other => editorElementsOverlap(element, other)).length;
    return `<div class="editor-layer-item ${selectedIds.has(element.id) ? 'is-selected' : ''}" data-layer-id="${escapeHtml(element.id)}">
      <button class="editor-layer-select" type="button" data-layer-select="${escapeHtml(element.id)}" aria-pressed="${selectedIds.has(element.id)}">
        <i>${index + 1}</i><span><strong>${escapeHtml(label.type)}</strong><small>${escapeHtml(label.detail || `Ebene ${index + 1}`)}</small></span>
        ${overlapCount ? `<em title="Überlagert ${overlapCount} weitere Ebene${overlapCount === 1 ? '' : 'n'}">⋂ ${overlapCount}</em>` : ''}
      </button>
      <span class="editor-layer-order">
        <button type="button" data-layer-direction="1" aria-label="${escapeHtml(label.type)} nach vorne" ${index === state.editorElements.length - 1 ? 'disabled' : ''}>↑</button>
        <button type="button" data-layer-direction="-1" aria-label="${escapeHtml(label.type)} nach hinten" ${index === 0 ? 'disabled' : ''}>↓</button>
      </span>
    </div>`;
  }).join('') || '<p class="editor-layer-empty">Noch keine Elemente eingefügt.</p>';
}

function setEditorMobilePanel(panel = '') {
  ['tools', 'layers', 'properties'].forEach(name => {
    slideDialog.classList.toggle(`mobile-panel-${name}`, panel === name);
    const button = slideDialog.querySelector(`[data-editor-panel-toggle="${name}"]`);
    button?.classList.toggle('is-active', panel === name);
    button?.setAttribute('aria-expanded', String(panel === name));
  });
}

function editorSnapshot(){
  return JSON.stringify({
    elements:structuredClone(state.editorElements),
    settings:structuredClone(state.editorSlideSettings),
    title:document.querySelector('#new-slide-title').value,
    ratio:document.querySelector('#editor-ratio').value,
    designOrientation:state.editorAutoDesignOrientation,
    eventId:document.querySelector('#slide-event').value,
    status:document.querySelector('#slide-status').value,
    startsAt:document.querySelector('#slide-starts-at').value,
    endsAt:document.querySelector('#slide-ends-at').value,
    tags:document.querySelector('#slide-tags').value,
    locationIds:[...document.querySelectorAll('#slide-location-options input:checked')].map(input=>input.value)
  });
}
function syncEditorHistoryButtons(){
  const undo=document.querySelector('#slide-undo-button'),redo=document.querySelector('#slide-redo-button');
  if(undo)undo.disabled=!state.editorUndo?.length;
  if(redo)redo.disabled=!state.editorRedo?.length;
}
function pushEditorHistory(){
  state.editorUndo=state.editorUndo||[];
  const snapshot=state.editorLastSnapshot||editorSnapshot();
  if(state.editorUndo.at(-1)!==snapshot)state.editorUndo.push(snapshot);
  if(state.editorUndo.length>60)state.editorUndo.shift();
  state.editorRedo=[];
  syncEditorHistoryButtons();
}
function restoreEditorSnapshot(snapshot){
  const value=JSON.parse(snapshot),legacy=Array.isArray(value);
  state.editorElements=structuredClone(legacy?value:value.elements||[]);
  if(!legacy){
    state.editorSlideSettings=structuredClone(value.settings||defaultSlideSettings());
    document.querySelector('#new-slide-title').value=value.title||'Neue Slide';
    document.querySelector('#editor-ratio').value=value.ratio||'landscape';
    document.querySelector('#slide-event').value=value.eventId||'';
    document.querySelector('#slide-status').value=value.status||'draft';
    document.querySelector('#slide-starts-at').value=value.startsAt||'';
    document.querySelector('#slide-ends-at').value=value.endsAt||'';
    document.querySelector('#slide-tags').value=value.tags||'';
    renderSlideLocationOptions(value.locationIds||[]);
    applyEditorOrientation(value.ratio||'landscape',value.designOrientation);
  }
  state.selectedEditorElementIds=new Set();
  state.selectedEditorElementId=null;
  renderEditorCanvas();
  state.editorLastSnapshot=editorSnapshot();
}
function undoEditor(){if(!state.editorUndo?.length)return;state.editorRedo.push(editorSnapshot());restoreEditorSnapshot(state.editorUndo.pop());syncEditorHistoryButtons();scheduleSlideAutosave({successMessage:'Schritt wurde zurückgenommen und nach der Wartezeit gespeichert.'});}
function redoEditor(){if(!state.editorRedo?.length)return;state.editorUndo.push(editorSnapshot());restoreEditorSnapshot(state.editorRedo.pop());syncEditorHistoryButtons();scheduleSlideAutosave({successMessage:'Schritt wurde wiederholt und nach der Wartezeit gespeichert.'});}
function deleteSelectedEditorElements(){const ids=state.selectedEditorElementIds||new Set(state.selectedEditorElementId?[state.selectedEditorElementId]:[]);state.editorElements=state.editorElements.filter(element=>!ids.has(element.id)||element.locked);state.selectedEditorElementIds=new Set();state.selectedEditorElementId=null;renderEditorCanvas();scheduleSlideAutosave({immediate:true,successMessage:'Element wurde entfernt und die Slide gespeichert.'});}

function addEditorElement(type, x, y) {
  pushEditorHistory();
  const element = createEditorElement(type, x, y);
  state.editorElements.push(element);
  state.selectedEditorElementId = element.id;
  state.selectedEditorElementIds=new Set([element.id]);
  state.showSlideSettings = false;
  renderEditorCanvas();
  setEditorMobilePanel('');
  scheduleSlideAutosave({ immediate: true, successMessage: 'Element wurde hinzugefügt und die Slide gespeichert.' });
}

function applyMediaAssetToElement(element, asset) {
  if (!element || !asset) return;
  element.type = asset.type === 'video' ? 'video' : 'image';
  element.mediaAssetId = asset.id;
  element.src = asset.src;
  element.trimStart = asset.metadata?.trimStart || 0;
  element.trimEnd = asset.metadata?.trimEnd;
}

function addMediaElement(type, x, y) {
  openMediaLibrary(asset => {
    const element = createEditorElement(type, x, y);
    applyMediaAssetToElement(element, asset);
    state.editorElements.push(element);
    state.selectedEditorElementId = element.id;
    state.selectedEditorElementIds=new Set([element.id]);
    state.showSlideSettings = false;
    renderEditorCanvas();
    setEditorMobilePanel('');
    scheduleSlideAutosave({ immediate: true, successMessage: 'Medium wurde hinzugefügt und die Slide gespeichert.' });
  }, type, type === 'video' ? 'Video für die Slide auswählen' : 'Bild für die Slide auswählen');
}

const wayfindingTextStyles = { hero: 'Hero / Haupttitel', heading: 'Überschrift', subheading: 'Unterüberschrift', body: 'Fließtext', subtitle: 'Untertitel', label: 'Label / Zielangabe', note: 'Hinweis / Kleingedrucktes' };
const wayfindingTextSizes = { hero: 'clamp(30px,5vw,82px)', heading: 'clamp(24px,4vw,64px)', subheading: 'clamp(20px,3vw,48px)', body: 'clamp(16px,2.2vw,34px)', subtitle: 'clamp(14px,1.8vw,28px)', label: 'clamp(12px,1.4vw,22px)', note: 'clamp(10px,1.1vw,18px)' };
const directionGlyphs = { up: '↑', down: '↓', left: '←', right: '→', 'up-left': '↖', 'up-right': '↗', 'down-left': '↙', 'down-right': '↘', 'stairs-up-left': '▰↖', 'stairs-up-right': '▰↗', 'stairs-down-left': '▰↙', 'stairs-down-right': '▰↘' };
const arrowAssetSrc = direction => assetUrl(`/media/arrows/${direction || 'right'}.svg`);
function countdownPreview(cell, event = null) {
  if (cell.targetSource === 'daily') return countdownText({ countdownMode: 'daily', countdownDailyTime: cell.dailyTime || '12:00', showSeconds: cell.showSeconds !== false });
  const target = cell.targetSource === 'event' ? event?.[cell.eventField || 'eventStart'] : cell.targetTime;
  return countdownText({ countdownMode: 'datetime', targetTime: target || '', showSeconds: cell.showSeconds !== false });
}
function automaticContrastColor(settings = state.editorSlideSettings) {
  const background = settings?.background || defaultSlideSettings().background;
  const colors = [background.color1 || '#17342b', background.mode === 'gradient' ? background.color2 || '#315b49' : null].filter(Boolean);
  const luminance = colors.map(hex => { const clean = hex.replace('#', ''); const rgb = [0, 2, 4].map(index => parseInt(clean.slice(index, index + 2), 16) || 0); return rgb.reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0); }).reduce((sum, value) => sum + value, 0) / colors.length;
  return luminance > 145 ? '#111111' : '#ffffff';
}
function rowDisplayColor(row, settings = state.editorSlideSettings) { return row?.colorMode === 'custom' ? row.color || '#ffffff' : automaticContrastColor(settings); }
function wayfindingCellMarkup(cell, color = automaticContrastColor(), event = null) {
  if (!cell || cell.kind === 'empty') return '';
  const colorStyle = `color:${color}`;
  if (cell.kind === 'image') return cell.src ? `<img class="wf-cropped-image" src="${escapeHtml(cell.src)}" alt="" style="object-fit:${escapeHtml(cell.imageFit || 'cover')};object-position:${Number(cell.imageX ?? 50)}% ${Number(cell.imageY ?? 50)}%;transform:scale(${Number(cell.imageZoom || 100) / 100})">` : `<span class="canvas-placeholder">▧<small>Bild wählen</small></span>`;
  if (cell.kind === 'arrow') return `<span class="wf-arrow-image" style="${colorStyle};--arrow-mask:url('${arrowAssetSrc(cell.direction)}')" role="img" aria-label="${escapeHtml(directionGlyphs[cell.direction] || 'Pfeil')}"></span>`;
  if (cell.kind === 'iframe') return cell.src ? `<iframe src="${escapeHtml(cell.src)}" title="Eingebettete Website"></iframe>` : '<span class="canvas-placeholder">↗<small>Website eintragen</small></span>';
  if (cell.kind === 'countdown') { const present=cell.targetSource!=='event'||Boolean(event?.[cell.eventField||'eventStart']); return `<span class="wf-countdown" ${cell.autoFontSize?'data-auto-font="true" data-auto-font-max="240"':''} ${hiddenEventValueAttributes(present)} style="${colorStyle};${countdownFontStyle(cell)}" data-editor-wayfinding-countdown="${escapeHtml(encodeURIComponent(JSON.stringify(cell)))}">${escapeHtml(cell.prefix || '')}${countdownPreview(cell,event)}${escapeHtml(cell.suffix || '')}</span>`; }
  if (cell.kind === 'event-field') { const presentation=eventFieldPresentation(event,cell.eventField,cell); return `<span class="wf-text" ${cell.autoFontSize?'data-auto-font="true" data-auto-font-max="240"':''} ${hiddenEventValueAttributes(presentation.present)} style="${colorStyle};font-size:${Math.max(6,Math.min(240,Number(cell.fontSize)||42))}px">${escapeHtml(presentation.text)}</span>`; }
  return `<span class="wf-text ${cell.bold ? 'is-bold' : ''} ${cell.underline ? 'is-underlined' : ''}" ${cell.autoFontSize?'data-auto-font="true" data-auto-font-max="240"':''} style="${colorStyle};font-size:${wayfindingTextSizes[cell.textStyle] || wayfindingTextSizes.body};text-align:${escapeHtml(cell.align || 'left')};font-weight:${cell.bold || ['hero', 'heading'].includes(cell.textStyle) ? 700 : 400}">${escapeHtml(cell.text || '')}</span>`;
}
function wayfindingMarkup(element, settings = state.editorSlideSettings, event = null) {
  const rows = (element.rows || []).map((row, index) => {
    const rowColor = rowDisplayColor(row, settings), left = wayfindingCellMarkup(row.left, rowColor,event), center = wayfindingCellMarkup(row.center, rowColor,event), right = wayfindingCellMarkup(row.right, rowColor,event);
    const centerClass = !left && !right && row.expandCenter ? ' center-full' : left && !right ? ' center-to-right' : '';
    const cells = centerClass === ' center-full' ? `<div class="wayfinding-cell is-center center-full">${center}</div>` : `${left ? `<div class="wayfinding-cell">${left}</div>` : '<div></div>'}<div class="wayfinding-cell is-center${centerClass}">${center}</div>${right && !centerClass ? `<div class="wayfinding-cell">${right}</div>` : !centerClass ? '<div></div>' : ''}`;
    return `<div class="wayfinding-row ${index && row.separator ? 'has-separator' : ''}" style="color:${rowColor}">${cells}</div>`;
  }).join('');
  const logo = element.logo?.src ? `<div class="wayfinding-overlay ${escapeHtml(element.logo.position || 'top-left')} overlay-${escapeHtml(element.logo.theme || 'none')}"><img src="${escapeHtml(element.logo.src)}" alt="Logo"></div>` : '';
  const clock = element.clock?.enabled ? `<div class="wayfinding-overlay wayfinding-clock ${escapeHtml(element.clock.position || 'bottom-right')} is-readable">${new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}</div>` : '';
  return `<div class="wayfinding-canvas" style="--wayfinding-bg:${escapeHtml(element.background || '#17342b')}">${rows}${logo}${clock}</div>`;
}

function slideBackgroundStyle(settings = state.editorSlideSettings) {
  const background = settings?.background || defaultSlideSettings().background;
  if (background.mode === 'image' && background.imageSrc) return `background-image:url('${String(background.imageSrc).replaceAll("'", '%27')}');background-size:${Number(background.imageZoom || 100)}% auto;background-position:${Number(background.imageX ?? 50)}% ${Number(background.imageY ?? 50)}%;background-repeat:no-repeat;background-color:${background.color1 || '#17342b'}`;
  if (background.mode === 'gradient') return `background:linear-gradient(${background.direction || '135deg'},${background.color1 || '#17342b'},${background.color2 || '#315b49'})`;
  return `background:${background.color1 || '#17342b'}`;
}
function slideDecorationsMarkup(settings = state.editorSlideSettings) {
  const logoSettings=settings?.logo||defaultSlideSettings().logo,naturalWidth=Math.max(1,Number(logoSettings.naturalWidth||400)),naturalHeight=Math.max(1,Number(logoSettings.naturalHeight||160)),baseWidth=Math.max(4,Math.min(80,Number(logoSettings.widthPercent||naturalWidth/1920*100))),logoWidth=Math.max(2,Math.min(90,baseWidth*Number(logoSettings.scale||100)/100));
  const logo = logoSettings.src ? `<div class="slide-global-overlay slide-logo ${escapeHtml(logoSettings.position || 'top-left')} overlay-${escapeHtml(logoSettings.theme || 'none')}" style="width:${logoWidth}%;--logo-aspect:${naturalWidth}/${naturalHeight}"><img src="${escapeHtml(logoSettings.src)}" alt="Logo"></div>` : '';
  const clock = settings?.clock?.enabled ? `<div class="slide-global-overlay slide-clock ${escapeHtml(settings.clock.position || 'bottom-right')} clock-${escapeHtml(settings.clock.theme || 'dark')}">${new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}</div>` : '';
  return `<div class="slide-global-background" style="${slideBackgroundStyle(settings)}"></div>${logo}${clock}`;
}

function editorElementMarkup(element) {
  const event=state.events.find(item=>item.id===document.querySelector('#slide-event').value);
  if (element.type === 'wayfinding') return wayfindingMarkup(element,state.editorSlideSettings,event);
  if (element.type === 'ticker') return `<div class="canvas-ticker ${element.tickerDirection==='right'?'is-right':''}" style="--ticker-duration:${Math.max(3,Math.min(60,Number(element.tickerDuration||15)))}s"><div class="ticker-track">${tickerItemsMarkup(element)}</div></div>`;
  if (element.type === 'weather') return weatherElementMarkup(element);
  if (element.type === 'qr-code') return `<div class="canvas-qr-code">${qrSvgMarkup(qrElementPayload(element), element.qrForeground, element.background)}</div>`;
  if (element.type === 'countdown') return `<div class="canvas-countdown">${escapeHtml(element.prefix || '')}${countdownText(element)}${escapeHtml(element.suffix || '')}</div>`;
  if (element.type === 'image') { const src=element.src||event?.imageUrl||''; return src?`<img src="${escapeHtml(src)}" alt="">`:'<span class="canvas-placeholder" data-event-value-missing="true" aria-hidden="true">▧<small>Bild auswählen</small></span>'; }
  if (element.type === 'video') return element.src ? `<video src="${escapeHtml(element.src)}" ${element.muted ? 'muted' : ''} autoplay loop></video>` : '<span class="canvas-placeholder">▶<small>Video auswählen</small></span>';
  if (element.type === 'web') return element.src && element.src !== 'https://example.org' ? `<iframe src="${escapeHtml(element.src)}" title="Eingebettete Website" loading="lazy"></iframe>` : `<span class="canvas-placeholder">↗<small>${escapeHtml(element.src || 'Website-URL eintragen')}</small></span>`;
  if (element.type === 'event') return `<div class="canvas-event-content" ${hiddenEventValueAttributes(Boolean(event))}>${eventWelcomeBlockMarkup(event,element.event||'Sommerkonzert',element)}</div>`;
  if (element.type === 'event-field') { const presentation=eventFieldPresentation(event,element.field,element); return `<div class="canvas-text-content" ${hiddenEventValueAttributes(presentation.present)}>${escapeHtml(presentation.text)}</div>`; }
  return `<div class="canvas-text-content" contenteditable="true" spellcheck="true">${escapeHtml(element.text || '')}</div>`;
}

function renderEditorCanvas() {
  const canvas = document.querySelector('#slide-editor-canvas');
  if(!state.selectedEditorElementIds)state.selectedEditorElementIds=new Set(state.selectedEditorElementId?[state.selectedEditorElementId]:[]);
  const selectedEvent=state.events.find(item=>item.id===document.querySelector('#slide-event').value);
  canvas.innerHTML = slideDecorationsMarkup() + state.editorElements.map((element, index) => {const selected=state.selectedEditorElementIds.has(element.id),missingEventField=element.type==='event-field'&&!eventFieldPresentation(selectedEvent,element.field,element).present,missingDynamicImage=element.type==='image'&&!element.src&&!selectedEvent?.imageUrl,missingEventBlock=element.type==='event'&&!selectedEvent,background=missingEventField||missingDynamicImage||missingEventBlock?'transparent':element.background||'transparent';return`<div class="canvas-element type-${escapeHtml(element.type)} ${selected ? 'is-selected' : ''} ${element.locked?'is-locked':''}" data-editor-element="${element.id}" ${element.autoFontSize?'data-auto-font="true" data-auto-font-max="240"':''} style="left:${element.x}%;top:${element.y}%;width:${element.width}%;height:${element.height}%;z-index:${index + 1};background:${escapeHtml(background)};color:${escapeHtml(element.color || '#ffffff')};font-size:${Number(element.fontSize || 24)}px;text-align:${escapeHtml(element.align || 'left')};transform:rotate(${Number(element.rotation || 0)}deg);opacity:${Number(element.opacity ?? 100) / 100};padding:${Number(element.padding || 0)}px;border-radius:${Number(element.radius || 0)}px;font-weight:${Number(element.fontWeight || 400)};line-height:${Number(element.lineHeight || 1.08)};letter-spacing:${Number(element.letterSpacing || 0)}px">${editorElementMarkup(element)}<button class="canvas-rotate-handle" type="button" aria-label="Element drehen">↻</button><button class="canvas-element-handle" type="button" aria-label="Element verschieben">⠿</button>${selected?'<button class="canvas-delete-button" type="button" aria-label="Element löschen">×</button><i class="resize-handle nw"></i><i class="resize-handle ne"></i><i class="resize-handle sw"></i><i class="resize-handle se"></i>':''}</div>`;}).join('');
  hydrateWeatherElements(canvas);
  scheduleAutomaticTextFit(canvas);

  canvas.querySelectorAll('.canvas-element').forEach(node => {
    node.addEventListener('click', event => {
      event.stopPropagation();
      if(event.shiftKey){if(state.selectedEditorElementIds.has(node.dataset.editorElement))state.selectedEditorElementIds.delete(node.dataset.editorElement);else state.selectedEditorElementIds.add(node.dataset.editorElement);}else state.selectedEditorElementIds=new Set([node.dataset.editorElement]);
      state.selectedEditorElementId = state.selectedEditorElementIds.has(node.dataset.editorElement)?node.dataset.editorElement:[...state.selectedEditorElementIds][0]||null;
      state.showSlideSettings = false;
      renderEditorCanvas();
    });
    node.querySelector('.canvas-delete-button')?.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();pushEditorHistory();deleteSelectedEditorElements();});
    node.querySelectorAll('.resize-handle').forEach(resize=>resize.addEventListener('pointerdown',event=>{event.preventDefault();event.stopPropagation();const element=state.editorElements.find(item=>item.id===node.dataset.editorElement);if(element.locked)return;pushEditorHistory();const rect=canvas.getBoundingClientRect(),startX=event.clientX,startY=event.clientY,start={x:element.x,y:element.y,width:element.width,height:element.height},west=resize.classList.contains('nw')||resize.classList.contains('sw'),north=resize.classList.contains('nw')||resize.classList.contains('ne');const move=moveEvent=>{const dx=(moveEvent.clientX-startX)/rect.width*100,dy=(moveEvent.clientY-startY)/rect.height*100,newWidth=Math.max(5,Math.min(west?start.width-dx:start.width+dx,west?start.x+start.width:100-start.x)),newHeight=Math.max(5,Math.min(north?start.height-dy:start.height+dy,north?start.y+start.height:100-start.y));element.width=newWidth;element.height=newHeight;if(west)element.x=start.x+start.width-newWidth;if(north)element.y=start.y+start.height-newHeight;node.style.left=`${element.x}%`;node.style.top=`${element.y}%`;node.style.width=`${element.width}%`;node.style.height=`${element.height}%`;};const stop=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',stop);renderEditorCanvas();scheduleSlideAutosave();};window.addEventListener('pointermove',move);window.addEventListener('pointerup',stop);}));
    const editable = node.querySelector('[contenteditable]');
    if (editable) editable.addEventListener('input', () => {
      const element = state.editorElements.find(item => item.id === node.dataset.editorElement);
      pushEditorHistory();
      element.text = editable.textContent;
      document.querySelector('#property-text').value = element.text;
      scheduleSlideAutosave();
    });
    const handle = node.querySelector('.canvas-element-handle');
    handle.addEventListener('pointerdown', event => {
      event.preventDefault(); event.stopPropagation();
      const element = state.editorElements.find(item => item.id === node.dataset.editorElement);
      if(element.locked){showToast('Dieses Vorlagenelement ist gesperrt. Entsperre es in den Eigenschaften.');return;}
      if(!state.selectedEditorElementIds.has(element.id))state.selectedEditorElementIds=new Set([element.id]);state.selectedEditorElementId = element.id;pushEditorHistory();
      const selected=state.editorElements.filter(item=>state.selectedEditorElementIds.has(item.id)&&!item.locked),origins=new Map(selected.map(item=>[item.id,{x:item.x,y:item.y}]));
      const canvasRect = canvas.getBoundingClientRect();
      const startX=event.clientX,startY=event.clientY;
      const move = moveEvent => {
        const dx=(moveEvent.clientX-startX)/canvasRect.width*100,dy=(moveEvent.clientY-startY)/canvasRect.height*100;selected.forEach(item=>{const origin=origins.get(item.id);item.x=Math.max(0,Math.min(100-item.width,origin.x+dx));item.y=Math.max(0,Math.min(100-item.height,origin.y+dy));const selectedNode=canvas.querySelector(`[data-editor-element="${CSS.escape(item.id)}"]`);selectedNode.style.left=`${item.x}%`;selectedNode.style.top=`${item.y}%`;});
        syncEditorProperties();
      };
      const stop = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); renderEditorCanvas(); scheduleSlideAutosave(); };
      window.addEventListener('pointermove', move); window.addEventListener('pointerup', stop);
    });
    const rotateHandle = node.querySelector('.canvas-rotate-handle');
    rotateHandle.addEventListener('pointerdown', event => {
      event.preventDefault(); event.stopPropagation();
      const element = state.editorElements.find(item => item.id === node.dataset.editorElement);
      if (element.locked) return;
      pushEditorHistory();
      const bounds = node.getBoundingClientRect(), centerX = bounds.left + bounds.width / 2, centerY = bounds.top + bounds.height / 2;
      const move = moveEvent => { element.rotation = Math.round(Math.atan2(moveEvent.clientY - centerY, moveEvent.clientX - centerX) * 180 / Math.PI + 90); node.style.transform = `rotate(${element.rotation}deg)`; document.querySelector('#property-rotation').value = element.rotation; };
      const stop = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); renderEditorCanvas(); scheduleSlideAutosave(); };
      window.addEventListener('pointermove', move); window.addEventListener('pointerup', stop);
    });
  });
  canvas.onclick = () => { state.selectedEditorElementId = null;state.selectedEditorElementIds=new Set(); state.showSlideSettings = true; renderEditorCanvas(); };
  renderEditorLayers();
  syncEditorProperties();
}

function wayfindingRowColorControls(row, rowIndex) {
  const mode = row.colorMode || 'auto';
  return `<div class="element-color-controls row-color-controls"><label>Zeilenfarbe <select data-way-row="${rowIndex}" data-way-row-field="colorMode"><option value="auto" ${mode === 'auto' ? 'selected' : ''}>Automatischer Kontrast (${automaticContrastColor() === '#ffffff' ? 'Weiß' : 'Schwarz'})</option><option value="custom" ${mode === 'custom' ? 'selected' : ''}>Eigene Farbe</option></select></label>${mode === 'custom' ? `<label>Farbton <input type="color" data-way-row="${rowIndex}" data-way-row-field="color" value="${escapeHtml(row.color || '#ffffff')}"></label>` : ''}<small>Gilt für Text, Pfeile, Countdown und Trennlinie.</small></div>`;
}
function wayfindingCellConfig(cell, rowIndex, column, label) {
  const kind = cell?.kind || 'empty';
  let detail = '';
  if (kind === 'text') detail = `<input data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="text" value="${escapeHtml(cell.text || '')}" placeholder="Text"><select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="textStyle">${Object.entries(wayfindingTextStyles).map(([value, text]) => `<option value="${value}" ${cell.textStyle === value ? 'selected' : ''}>${text}</option>`).join('')}</select><select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="align"><option value="left" ${cell.align === 'left' ? 'selected' : ''}>Linksbündig</option><option value="center" ${cell.align === 'center' ? 'selected' : ''}>Mittig</option><option value="right" ${cell.align === 'right' ? 'selected' : ''}>Rechtsbündig</option></select><div class="wayfinding-checks"><label><input type="checkbox" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="bold" ${cell.bold ? 'checked' : ''}> Fett</label><label><input type="checkbox" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="underline" ${cell.underline ? 'checked' : ''}> Unterstrichen</label></div><label class="toggle-label"><input type="checkbox" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="autoFontSize" ${cell.autoFontSize?'checked':''}> Schrift automatisch einpassen</label>`;
  if (kind === 'arrow') detail = `<select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="direction">${Object.entries(directionGlyphs).map(([value, glyph]) => `<option value="${value}" ${cell.direction === value ? 'selected' : ''}>${glyph} ${value.replaceAll('-', ' ')}</option>`).join('')}</select>`;
  if (kind === 'image') detail = `<button class="mini-button" type="button" data-way-media="${rowIndex}:${column}">${cell.src ? 'Bild ersetzen' : 'Aus Mediathek wählen'}</button><select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="imageFit"><option value="cover" ${cell.imageFit !== 'contain' ? 'selected' : ''}>Zuschneiden / füllen</option><option value="contain" ${cell.imageFit === 'contain' ? 'selected' : ''}>Vollständig einpassen</option></select><label class="range-field">Zoom <input type="range" min="100" max="250" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="imageZoom" value="${Number(cell.imageZoom || 100)}"></label><label class="range-field">Horizontal <input type="range" min="0" max="100" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="imageX" value="${Number(cell.imageX ?? 50)}"></label><label class="range-field">Vertikal <input type="range" min="0" max="100" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="imageY" value="${Number(cell.imageY ?? 50)}"></label>`;
  if (kind === 'countdown') detail = `<select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="targetSource"><option value="manual" ${!['event','daily'].includes(cell.targetSource) ? 'selected' : ''}>Manuelle Zielzeit</option><option value="event" ${cell.targetSource === 'event' ? 'selected' : ''}>Zeit aus Veranstaltung</option><option value="daily" ${cell.targetSource === 'daily' ? 'selected' : ''}>Täglich zu einer Uhrzeit</option></select>${cell.targetSource === 'event' ? `<select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="eventField">${eventTimeFieldOptions(cell.eventField)}</select>` : cell.targetSource === 'daily' ? `<label class="form-field"><span>Tägliche Zielzeit</span><input type="time" step="60" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="dailyTime" value="${escapeHtml(cell.dailyTime || '12:00')}"></label>` : `<input type="datetime-local" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="targetTime" value="${escapeHtml(cell.targetTime || '')}">`}<input data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="prefix" value="${escapeHtml(cell.prefix || '')}" placeholder="Text davor, z. B. Noch "><input data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="suffix" value="${escapeHtml(cell.suffix || '')}" placeholder="Text danach, z. B. bis zum Einlass"><label class="toggle-label"><input type="checkbox" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="showSeconds" ${cell.showSeconds !== false ? 'checked' : ''}> Sekunden anzeigen</label><label class="form-field"><span>Maximale Textgröße (px)</span><input type="number" min="6" max="240" step="1" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="fontSize" value="${Math.max(6, Math.min(240, Number(cell.fontSize) || 68))}"></label><label class="toggle-label"><input type="checkbox" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="autoFontSize" ${cell.autoFontSize?'checked':''}> Schrift automatisch einpassen</label>`;
  if (kind === 'event-field') detail = `<select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="eventField">${eventTimeFieldOptions(cell.eventField)}</select><input data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="prefix" value="${escapeHtml(cell.prefix || '')}" placeholder="Text davor"><input data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="fallback" value="${escapeHtml(cell.fallback || '')}" placeholder="Vorlagenplatzhalter · bei fehlendem Wert unsichtbar"><label class="form-field"><span>Textgröße (px)</span><input type="number" min="6" max="240" step="1" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="fontSize" value="${Math.max(6,Math.min(240,Number(cell.fontSize)||42))}"></label><label class="toggle-label"><input type="checkbox" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="autoFontSize" ${cell.autoFontSize?'checked':''}> Schrift automatisch einpassen</label>`;
  if (kind === 'iframe') detail = `<input type="url" data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="src" value="${escapeHtml(cell.src || '')}" placeholder="https://…"><small class="property-note">Die Website muss das Einbetten erlauben.</small>`;
  const kinds = column === 'left' ? [['empty','Frei / leer'],['image','Bild / Icon'],['arrow','Pfeil']] : column === 'right' ? [['empty','Frei / leer'],['image','Bild / Icon'],['arrow','Pfeil'],['text','Text']] : [['empty','Frei / leer'],['text','Formatierter Text'],['image','Bild'],['countdown','Countdown'],['event-field','Veranstaltungszeit'],['iframe','iFrame / Website']];
  return `<div class="wayfinding-cell-config"><label>${label}</label><div><select data-way-row="${rowIndex}" data-way-column="${column}" data-way-field="kind">${kinds.map(([value, text]) => `<option value="${value}" ${kind === value ? 'selected' : ''}>${text}</option>`).join('')}</select>${detail}</div></div>`;
}
function eventTimeFieldOptions(selected) { return [['setupStart','Aufbau / Veranstalter vor Ort'],['admissionStart','Einlass'],['eventStart','Beginn / Start'],['breakStart','Pause von'],['breakEnd','Pause bis'],['eventEnd','Ende']].map(([value, text]) => `<option value="${value}" ${selected === value ? 'selected' : ''}>${text}</option>`).join(''); }

function normalizeWayfindingRows(rows) {
  if (rows[0]) rows[0].separator = false;
}

function moveWayfindingRow(rows, sourceIndex, targetIndex) {
  if (sourceIndex < 0 || sourceIndex >= rows.length || targetIndex < 0 || targetIndex >= rows.length || sourceIndex === targetIndex) return false;
  const [row] = rows.splice(sourceIndex, 1);
  rows.splice(targetIndex, 0, row);
  normalizeWayfindingRows(rows);
  return true;
}

function dropWayfindingRow(rows, sourceId, targetId, position) {
  const sourceIndex = rows.findIndex(row => row.id === sourceId);
  let targetIndex = rows.findIndex(row => row.id === targetId);
  if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) return false;
  const [row] = rows.splice(sourceIndex, 1);
  if (sourceIndex < targetIndex) targetIndex -= 1;
  if (position === 'after') targetIndex += 1;
  rows.splice(Math.max(0, Math.min(rows.length, targetIndex)), 0, row);
  normalizeWayfindingRows(rows);
  return true;
}

function duplicateWayfindingRow(rows, sourceIndex) {
  const source = rows[sourceIndex];
  if (!source) return false;
  const duplicate = JSON.parse(JSON.stringify(source));
  duplicate.id = `row-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
  rows.splice(sourceIndex + 1, 0, duplicate);
  normalizeWayfindingRows(rows);
  return true;
}

function renderWayfindingProperties(element) {
  const panel = document.querySelector('#wayfinding-properties');
  if (element.type !== 'wayfinding') { panel.innerHTML = ''; return; }
  (element.rows || []).forEach((row, index) => { row.id ||= `row-${Date.now()}-${index}-${Math.random().toString(36).slice(2, 6)}`; });
  panel.innerHTML = `<div class="wayfinding-config"><div class="wayfinding-global"><strong>Zeilenlayout</strong><p class="property-note">Hintergrund, Logo und Uhrzeit gelten für die gesamte Slide.</p><button class="mini-button" type="button" data-way-action="slide-settings">Slide-Hintergrund & Overlays</button></div>${(element.rows || []).map((row, index) => `<div class="wayfinding-row-config" data-way-row-id="${escapeHtml(row.id)}"><div class="wayfinding-row-head"><div class="wayfinding-row-title" data-way-drag-row="${escapeHtml(row.id)}" title="Zeile ${index + 1} anfassen und verschieben"><strong>Zeile ${index + 1}</strong><button class="wayfinding-row-drag" type="button" data-way-drag-handle aria-label="Zeile ${index + 1} per Drag-and-drop verschieben" title="Anfassen und verschieben"><span aria-hidden="true">⠿</span> Verschieben</button></div><div class="wayfinding-row-actions"><button class="wayfinding-row-move" type="button" data-way-move-row="${index}" data-way-direction="-1" aria-label="Zeile ${index + 1} nach oben verschieben" title="Nach oben" ${index === 0 ? 'disabled' : ''}>↑ <span>Nach oben</span></button><button class="wayfinding-row-move" type="button" data-way-move-row="${index}" data-way-direction="1" aria-label="Zeile ${index + 1} nach unten verschieben" title="Nach unten" ${index === element.rows.length - 1 ? 'disabled' : ''}>↓ <span>Nach unten</span></button><button class="wayfinding-row-duplicate" type="button" data-way-duplicate-row="${index}" aria-label="Zeile ${index + 1} duplizieren" title="Zeile duplizieren">⧉ Duplizieren</button><button class="wayfinding-row-delete" type="button" data-way-delete-row="${index}" ${element.rows.length === 1 ? 'disabled' : ''}>Entfernen</button></div></div>${wayfindingRowColorControls(row, index)}${wayfindingCellConfig(row.left, index, 'left', 'Links · ¼')}${wayfindingCellConfig(row.center, index, 'center', 'Mitte · ½')}${wayfindingCellConfig(row.right, index, 'right', 'Rechts · ¼')}<div class="wayfinding-checks"><label><input type="checkbox" data-way-row-option="separator" data-way-row="${index}" ${index > 0 && row.separator ? 'checked' : ''} ${index === 0 ? 'disabled' : ''}> Trennlinie oben</label><label><input type="checkbox" data-way-row-option="expandCenter" data-way-row="${index}" ${row.expandCenter ? 'checked' : ''}> Mitte bei freien Seiten vollbreit</label></div></div>`).join('')}<button class="button button-secondary" type="button" data-way-action="add-row">＋ Zeile / Spacer hinzufügen</button></div>`;
  panel.oninput = event => {
    const target = event.target, active = selectedEditorElement();
    if (target.dataset.wayField !== 'fontSize' || !active || active.type !== 'wayfinding') return;
    pushEditorHistory();
    const cell = active.rows[Number(target.dataset.wayRow)][target.dataset.wayColumn];
    cell.fontSize = Math.max(6, Math.min(240, Number(target.value) || 68));
    scheduleSlideAutosave();
  };
  panel.onchange = event => {
    const target = event.target, active = selectedEditorElement(); if (!active || active.type !== 'wayfinding') return;
    if (target.dataset.wayField === 'fontSize') { renderEditorCanvas(); return; }
    pushEditorHistory();
    if (target.dataset.wayGlobal === 'clock-enabled') active.clock.enabled = target.checked;
    if (target.dataset.wayGlobal === 'clock-position') active.clock.position = target.value;
    if (target.dataset.wayGlobal === 'logo-position') active.logo.position = target.value;
    if (target.dataset.wayRowOption) active.rows[Number(target.dataset.wayRow)][target.dataset.wayRowOption] = target.checked;
    if (target.dataset.wayRowField) active.rows[Number(target.dataset.wayRow)][target.dataset.wayRowField] = target.value;
    if (target.dataset.wayField) {
      const cell = active.rows[Number(target.dataset.wayRow)][target.dataset.wayColumn];
      cell[target.dataset.wayField] = target.type === 'checkbox' ? target.checked : ['range', 'number'].includes(target.type) ? Number(target.value) : target.value;
      if (target.dataset.wayField === 'kind') Object.assign(cell, target.value === 'text' ? { text: '', textStyle: 'body', align: target.dataset.wayColumn === 'right' ? 'right' : 'left', bold: false, underline: false } : target.value === 'arrow' ? { direction: 'right' } : target.value === 'image' ? { src: '', imageFit: 'cover', imageZoom: 100, imageX: 50, imageY: 50 } : target.value === 'countdown' ? { targetSource: 'manual', targetTime: '', dailyTime: '12:00', eventField: 'eventStart', prefix: '', suffix: '', showSeconds: true, fontSize: 68 } : target.value === 'event-field' ? { eventField: 'eventStart', prefix: '', fallback: 'Zeit folgt', fontSize: 42 } : target.value === 'iframe' ? { src: '' } : {});
    }
    renderEditorCanvas();
    scheduleSlideAutosave();
  };
  panel.onclick = event => {
    const button = event.target.closest('button'); if (!button) return;
    const active = selectedEditorElement(); if (!active || active.type !== 'wayfinding') return;
    if (button.dataset.wayDragHandle != null) return;
    if (button.dataset.wayAction === 'add-row') { pushEditorHistory(); active.rows.push({ id: `row-${Date.now()}`, separator: active.rows.length > 0, expandCenter: false, colorMode: 'auto', color: '#ffffff', left: { kind: 'empty' }, center: { kind: 'empty' }, right: { kind: 'empty' } }); }
    if (button.dataset.wayAction === 'slide-settings') { state.selectedEditorElementId = null; state.showSlideSettings = true; renderEditorCanvas(); return; }
    if (button.dataset.wayMoveRow != null) {
      const sourceIndex = Number(button.dataset.wayMoveRow), targetIndex = sourceIndex + Number(button.dataset.wayDirection);
      if (targetIndex >= 0 && targetIndex < active.rows.length) { pushEditorHistory(); moveWayfindingRow(active.rows, sourceIndex, targetIndex); }
    }
    if (button.dataset.wayDuplicateRow != null) { pushEditorHistory(); duplicateWayfindingRow(active.rows, Number(button.dataset.wayDuplicateRow)); }
    if (button.dataset.wayDeleteRow != null && active.rows.length > 1) { pushEditorHistory(); active.rows.splice(Number(button.dataset.wayDeleteRow), 1); normalizeWayfindingRows(active.rows); }
    if (button.dataset.wayAction === 'logo') openMediaLibrary(asset => { pushEditorHistory(); active.logo.mediaAssetId = asset.id; active.logo.src = asset.src; renderEditorCanvas(); scheduleSlideAutosave({ immediate: true }); }, 'image', 'Logo für die Wegweisung auswählen');
    if (button.dataset.wayMedia) { const [rowIndex, column] = button.dataset.wayMedia.split(':'); const cell = active.rows[Number(rowIndex)][column]; openMediaLibrary(asset => { pushEditorHistory(); cell.kind = 'image'; cell.mediaAssetId = asset.id; cell.src = asset.src; cell.imageFit ||= 'cover'; cell.imageZoom ||= 100; cell.imageX ??= 50; cell.imageY ??= 50; renderEditorCanvas(); scheduleSlideAutosave({ immediate: true }); }, 'image', 'Bild für die Wegweisung auswählen'); }
    if (!button.dataset.wayMedia && button.dataset.wayAction !== 'logo') { renderEditorCanvas(); scheduleSlideAutosave({ immediate: true }); }
  };
  const clearDropIndicators = () => panel.querySelectorAll('.wayfinding-row-config').forEach(row => row.classList.remove('is-dragging', 'drag-before', 'drag-after'));
  panel.onpointerdown = event => {
    const handle = event.target.closest('[data-way-drag-row]'); if (!handle) return;
    if (event.button != null && event.button !== 0) return;
    const draggedRowId = handle.dataset.wayDragRow, sourceRow = handle.closest('.wayfinding-row-config');
    const startX = event.clientX, startY = event.clientY;
    let isDragging = false, targetRowId = '', dropPosition = 'before';
    handle.setPointerCapture?.(event.pointerId);
    handle.setAttribute('aria-grabbed', 'true');
    event.preventDefault();
    const move = moveEvent => {
      if (!isDragging && Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY) < 5) return;
      isDragging = true;
      moveEvent.preventDefault();
      sourceRow.classList.add('is-dragging');
      const target = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY)?.closest('.wayfinding-row-config');
      panel.querySelectorAll('.wayfinding-row-config').forEach(row => row.classList.remove('drag-before', 'drag-after'));
      if (!target || target === sourceRow || !panel.contains(target)) { targetRowId = ''; return; }
      const bounds = target.getBoundingClientRect();
      dropPosition = moveEvent.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';
      targetRowId = target.dataset.wayRowId;
      target.classList.add(dropPosition === 'before' ? 'drag-before' : 'drag-after');
    };
    const stop = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', stop);
      window.removeEventListener('pointercancel', stop);
      handle.releasePointerCapture?.(event.pointerId);
      handle.setAttribute('aria-grabbed', 'false');
      clearDropIndicators();
      const active = selectedEditorElement();
      if (isDragging && targetRowId && active?.type === 'wayfinding') {
        pushEditorHistory();
        dropWayfindingRow(active.rows, draggedRowId, targetRowId, dropPosition);
        renderEditorCanvas();
        scheduleSlideAutosave({ immediate: true, successMessage: 'Zeilenreihenfolge wurde gespeichert.' });
      }
    };
    window.addEventListener('pointermove', move, { passive: false });
    window.addEventListener('pointerup', stop);
    window.addEventListener('pointercancel', stop);
  };
}

function imageSourceDimensions(src) {
  return new Promise(resolve=>{const image=new Image();image.onload=()=>resolve({width:image.naturalWidth||400,height:image.naturalHeight||160});image.onerror=()=>resolve({width:400,height:160});image.src=src;});
}

function renderSlideSettingsProperties() {
  const panel=document.querySelector('#slide-settings-properties'),settings=state.editorSlideSettings,background=settings.background,logo=settings.logo;
  panel.innerHTML=`<div class="slide-settings-panel">
    <div class="property-heading"><strong>Slide-Einstellungen</strong></div>
    <section><strong>Hintergrund</strong>
      <label class="form-field"><span>Art</span><select data-slide-setting="background.mode"><option value="color" ${background.mode==='color'?'selected':''}>Einzelfarbe</option><option value="gradient" ${background.mode==='gradient'?'selected':''}>Farbverlauf</option><option value="image" ${background.mode==='image'?'selected':''}>Hintergrundbild</option></select></label>
      <div class="property-row"><label class="form-field"><span>Farbe 1</span><input type="color" data-slide-setting="background.color1" value="${escapeHtml(background.color1||'#17342b')}"></label>${background.mode==='gradient'?`<label class="form-field"><span>Farbe 2</span><input type="color" data-slide-setting="background.color2" value="${escapeHtml(background.color2||'#315b49')}"></label>`:''}</div>
      ${background.mode==='gradient'?`<label class="form-field"><span>Verlaufsrichtung</span><select data-slide-setting="background.direction"><option value="90deg" ${background.direction==='90deg'?'selected':''}>Links → rechts</option><option value="180deg" ${background.direction==='180deg'?'selected':''}>Oben → unten</option><option value="135deg" ${background.direction==='135deg'?'selected':''}>Diagonal ↘</option><option value="45deg" ${background.direction==='45deg'?'selected':''}>Diagonal ↗</option></select></label>`:''}
      ${background.mode==='image'?`<button class="mini-button" type="button" data-slide-media="background">${background.imageSrc?'Hintergrundbild ersetzen':'Hintergrundbild wählen'}</button><label class="range-field">Zoom <input type="range" min="100" max="250" data-slide-setting="background.imageZoom" value="${Number(background.imageZoom||100)}"></label><label class="range-field">Horizontal <input type="range" min="0" max="100" data-slide-setting="background.imageX" value="${Number(background.imageX??50)}"></label><label class="range-field">Vertikal <input type="range" min="0" max="100" data-slide-setting="background.imageY" value="${Number(background.imageY??50)}"></label>`:''}
    </section>
    <section><strong>Logo-Overlay</strong>
      <button class="mini-button" type="button" data-slide-media="logo">${logo.src?'Logo ersetzen':'Logo aus Bibliothek wählen'}</button>${logo.src?'<button class="mini-button" type="button" data-slide-action="remove-logo">Logo entfernen</button>':''}
      ${logo.src?`<p class="property-note">Originaldatei: ${Number(logo.naturalWidth||400)} × ${Number(logo.naturalHeight||160)} px · Seitenverhältnis bleibt erhalten.</p>`:''}
      <label class="form-field"><span>Position</span><select data-slide-setting="logo.position"><option value="top-left" ${logo.position==='top-left'?'selected':''}>Oben links</option><option value="top-right" ${logo.position==='top-right'?'selected':''}>Oben rechts</option><option value="bottom-left" ${logo.position==='bottom-left'?'selected':''}>Unten links</option><option value="bottom-right" ${logo.position==='bottom-right'?'selected':''}>Unten rechts</option></select></label>
      <label class="form-field"><span>Hintergrund</span><select data-slide-setting="logo.theme"><option value="none" ${logo.theme==='none'||!logo.theme?'selected':''}>Kein Hintergrund · transparent</option><option value="light" ${logo.theme==='light'?'selected':''}>Heller Hintergrund</option><option value="dark" ${logo.theme==='dark'?'selected':''}>Dunkler Hintergrund</option></select></label>
      <label class="range-field">Größe proportional · ${Number(logo.scale||100)} %<input type="range" min="10" max="300" step="5" data-slide-setting="logo.scale" value="${Number(logo.scale||100)}"></label>
    </section>
    <section><strong>Uhr-Overlay</strong>
      <label class="toggle-label"><input type="checkbox" data-slide-setting="clock.enabled" ${settings.clock.enabled?'checked':''}> Uhrzeit einblenden</label>
      <label class="form-field"><span>Position</span><select data-slide-setting="clock.position"><option value="bottom-right" ${settings.clock.position==='bottom-right'?'selected':''}>Unten rechts</option><option value="bottom-left" ${settings.clock.position==='bottom-left'?'selected':''}>Unten links</option><option value="top-right" ${settings.clock.position==='top-right'?'selected':''}>Oben rechts</option><option value="top-left" ${settings.clock.position==='top-left'?'selected':''}>Oben links</option></select></label>
      <label class="form-field"><span>Farbschema</span><select data-slide-setting="clock.theme"><option value="dark" ${settings.clock.theme==='dark'?'selected':''}>Dunkler Hintergrund · weiße Uhrzeit</option><option value="light" ${settings.clock.theme==='light'?'selected':''}>Heller Hintergrund · schwarze Uhrzeit</option><option value="none-light" ${settings.clock.theme==='none-light'?'selected':''}>Kein Hintergrund · weiße Uhrzeit</option><option value="none-dark" ${settings.clock.theme==='none-dark'?'selected':''}>Kein Hintergrund · schwarze Uhrzeit</option></select></label>
    </section>
  </div>`;
  panel.onchange=event=>{const target=event.target;if(!target.dataset.slideSetting)return;pushEditorHistory();const[group,field]=target.dataset.slideSetting.split('.');settings[group][field]=target.type==='checkbox'?target.checked:target.type==='range'?Number(target.value):target.value;renderEditorCanvas();scheduleSlideAutosave();};
  panel.onclick=event=>{const button=event.target.closest('button');if(!button)return;if(button.dataset.slideMedia==='background')openMediaLibrary(asset=>{pushEditorHistory();settings.background.mode='image';settings.background.mediaAssetId=asset.id;settings.background.imageSrc=asset.src;renderEditorCanvas();scheduleSlideAutosave({immediate:true});},'image','Hintergrundbild auswählen');if(button.dataset.slideMedia==='logo')openMediaLibrary(async asset=>{pushEditorHistory();const measured=await imageSourceDimensions(asset.src),width=Number(asset.metadata?.width||measured.width),height=Number(asset.metadata?.height||measured.height);Object.assign(settings.logo,{mediaAssetId:asset.id,src:asset.src,naturalWidth:width,naturalHeight:height,widthPercent:Math.max(4,Math.min(80,width/1920*100)),scale:100,theme:settings.logo.theme||'none'});renderEditorCanvas();scheduleSlideAutosave({immediate:true});},'image','Logo-Overlay auswählen');if(button.dataset.slideAction==='remove-logo'){pushEditorHistory();settings.logo.mediaAssetId='';settings.logo.src='';renderEditorCanvas();scheduleSlideAutosave({immediate:true,successMessage:'Logo wurde entfernt und die Slide gespeichert.'});}};
}

function normalizeTickerItems(element) {
  if (!Array.isArray(element.items) || !element.items.length) element.items = tickerElementItems(element).map(item => ({ ...item, id: item.id || `ticker-item-${Date.now()}-${Math.random().toString(36).slice(2, 6)}` }));
  return element.items;
}

function tickerItemEditorMarkup(item, index, total) {
  let fields = '';
  if (item.kind === 'text') fields = `<label class="form-field"><span>Text</span><textarea rows="2" data-ticker-item-field="text">${escapeHtml(item.text || '')}</textarea></label>`;
  if (item.kind === 'countdown') fields = `<label class="form-field"><span>Ziel</span><select data-ticker-item-field="countdownMode"><option value="daily" ${item.countdownMode !== 'datetime' ? 'selected' : ''}>Täglich</option><option value="datetime" ${item.countdownMode === 'datetime' ? 'selected' : ''}>Datum und Uhrzeit</option></select></label>${item.countdownMode === 'datetime' ? `<label class="form-field"><span>Zeitpunkt</span><input type="datetime-local" data-ticker-item-field="targetTime" value="${escapeHtml(String(item.targetTime || '').slice(0, 16))}"></label>` : `<label class="form-field"><span>Uhrzeit</span><input type="time" data-ticker-item-field="countdownDailyTime" value="${escapeHtml(item.countdownDailyTime || '18:00')}"></label>`}<label class="form-field"><span>Text davor</span><input data-ticker-item-field="prefix" value="${escapeHtml(item.prefix || '')}"></label><label class="form-field"><span>Text danach</span><input data-ticker-item-field="suffix" value="${escapeHtml(item.suffix || '')}"></label><label class="toggle-label"><input type="checkbox" data-ticker-item-field="showSeconds" ${item.showSeconds!==false?'checked':''}> Sekunden anzeigen</label>`;
  if (item.kind === 'qr-code') fields = `<label class="form-field"><span>QR‑Inhalt</span><input data-ticker-item-field="qrValue" value="${escapeHtml(item.qrValue || 'https://example.org')}" placeholder="Link oder Text"></label><div class="property-row"><label class="form-field"><span>Code</span><input type="color" data-ticker-item-field="qrForeground" value="${escapeHtml(item.qrForeground || '#111111')}"></label><label class="form-field"><span>Fläche</span><input type="color" data-ticker-item-field="qrBackground" value="${escapeHtml(item.qrBackground || '#ffffff')}"></label></div>`;
  if (item.kind === 'icon') fields = `<label class="form-field"><span>Icon / Symbol</span><input data-ticker-item-field="icon" value="${escapeHtml(item.icon || '★')}" maxlength="12"></label><label class="form-field"><span>Alternativtext</span><input data-ticker-item-field="label" value="${escapeHtml(item.label || '')}" placeholder="z. B. Information"></label>`;
  const labels = { text: 'Text', countdown: 'Countdown', 'qr-code': 'QR‑Code', icon: 'Icon' };
  return `<article class="ticker-item-card" data-ticker-item-index="${index}"><header><strong>${index + 1}. ${labels[item.kind] || 'Inhalt'}</strong><span><button type="button" data-ticker-move="-1" ${index === 0 ? 'disabled' : ''} aria-label="Nach vorne">↑</button><button type="button" data-ticker-move="1" ${index === total - 1 ? 'disabled' : ''} aria-label="Nach hinten">↓</button><button type="button" data-ticker-remove aria-label="Entfernen">×</button></span></header>${fields}</article>`;
}

function renderTickerProperties(element) {
  const panel = document.querySelector('#ticker-items-editor');
  if (element.type !== 'ticker') { panel.innerHTML = ''; return; }
  const items = normalizeTickerItems(element);
  panel.innerHTML = items.map((item, index) => tickerItemEditorMarkup(item, index, items.length)).join('');
}

function syncEditorProperties() {
  const element = selectedEditorElement();
  document.querySelector('#no-element-selected').hidden = true;
  document.querySelector('#element-properties').hidden = !element;
  document.querySelector('#slide-settings-properties').hidden = false;
  renderSlideSettingsProperties();
  if (!element) return;
  document.querySelector('#property-element-type').textContent = slideTypeLabels[element.type] || 'Element';
  document.querySelectorAll('[data-property-section]').forEach(section => { section.hidden = section.dataset.propertySection !== element.type && !(section.dataset.propertySection === 'text' && ['event','event-field','ticker','countdown','weather'].includes(element.type)); });
  document.querySelector('#property-text-field').hidden = ['ticker', 'weather'].includes(element.type);
  document.querySelector('#property-text').value = element.text || '';
  document.querySelector('#property-font-size').value = element.fontSize || 24;
  document.querySelector('#property-auto-font-size').checked=Boolean(element.autoFontSize);
  document.querySelector('#property-font-size').disabled=Boolean(element.autoFontSize);
  document.querySelector('#property-color').value = element.color || '#ffffff';
  document.querySelector('#property-event').value = element.event || 'Sommerkonzert';
  document.querySelector('#property-event-field').value = element.field || 'title';
  document.querySelector('#property-event-prefix').value = element.prefix || '';
  document.querySelector('#property-event-fallback').value = element.fallback || '';
  document.querySelector('#property-event-locked').checked = element.locked !== false;
  const selectedAsset = state.mediaAssets.find(asset => asset.id === element.mediaAssetId);
  document.querySelector('#property-image-selection').textContent = element.type === 'image' ? selectedAsset?.name || (element.src ? 'Bestehendes Bild · bitte bei Bedarf neu zuordnen' : 'Noch kein Bild ausgewählt') : '';
  document.querySelector('#property-video-selection').textContent = element.type === 'video' ? selectedAsset?.name || (element.src ? 'Bestehendes Video · bitte bei Bedarf neu zuordnen' : 'Noch kein Video ausgewählt') : '';
  document.querySelector('#property-web-url').value = element.type === 'web' ? element.src || '' : '';
  document.querySelector('#property-video-muted').checked = element.muted !== false;
  document.querySelector('#property-x').value = Math.round(element.x);
  document.querySelector('#property-y').value = Math.round(element.y);
  document.querySelector('#property-width').value = Math.round(element.width);
  document.querySelector('#property-height').value = Math.round(element.height);
  document.querySelector('#property-background').value = /^#[0-9a-f]{6}$/i.test(element.background || '') ? element.background : '#315b49';
  document.querySelector('#property-background-field').hidden = element.type === 'wayfinding';
  document.querySelector('#property-rotation').value = element.rotation || 0;
  document.querySelector('#property-opacity').value = element.opacity ?? 100;
  document.querySelector('#property-padding').value = element.padding || 0;
  document.querySelector('#property-radius').value = element.radius || 0;
  document.querySelector('#property-font-weight').value = String(element.fontWeight || 400);
  document.querySelector('#property-line-height').value = element.lineHeight || 1.08;
  document.querySelector('#property-letter-spacing').value = element.letterSpacing || 0;
  document.querySelector('#property-text-align').value = element.align || 'left';
  document.querySelector('#property-ticker-duration').value = Math.max(3, Math.min(60, Number(element.tickerDuration || 15)));
  document.querySelector('#property-ticker-duration-label').textContent = `${document.querySelector('#property-ticker-duration').value} Sekunden je Durchlauf · weniger ist schneller`;
  document.querySelector('#property-ticker-direction').value = element.tickerDirection === 'right' ? 'right' : 'left';
  document.querySelector('#property-qr-kind').value = element.qrKind || 'link';
  document.querySelector('#property-qr-value').value = element.qrValue || '';
  document.querySelector('#property-wifi-ssid').value = element.wifiSsid || '';
  document.querySelector('#property-wifi-password').value = element.wifiPassword || '';
  document.querySelector('#property-wifi-encryption').value = element.wifiEncryption || 'WPA';
  document.querySelector('#property-wifi-hidden').checked = Boolean(element.wifiHidden);
  document.querySelector('#property-qr-foreground').value = element.qrForeground || '#111111';
  const wifiQr = element.type === 'qr-code' && element.qrKind === 'wifi';
  document.querySelector('#property-qr-value-field').hidden = wifiQr;
  document.querySelector('#property-qr-wifi-fields').hidden = !wifiQr;
  document.querySelector('#property-countdown-mode').value = element.countdownMode || 'daily';
  document.querySelector('#property-countdown-daily-time').value = element.countdownDailyTime || '18:00';
  document.querySelector('#property-countdown-target').value = String(element.targetTime || '').slice(0, 16);
  document.querySelector('#property-countdown-prefix').value = element.prefix || '';
  document.querySelector('#property-countdown-suffix').value = element.suffix || '';
  document.querySelector('#property-countdown-show-seconds').checked = element.showSeconds !== false;
  document.querySelector('#property-countdown-daily-field').hidden = element.countdownMode === 'datetime';
  document.querySelector('#property-countdown-datetime-field').hidden = element.countdownMode !== 'datetime';
  document.querySelector('#property-weather-mode').value = element.weatherMode || 'today';
  document.querySelector('#property-weather-location-note').textContent = 'Der Standort wird automatisch vom abspielenden Display übernommen. Ohne Display-Standort erscheint „Bitte Standort wählen“.';
  renderTickerProperties(element);
  renderWayfindingProperties(element);
}

function updateSelectedElement(property, value) {
  const element = selectedEditorElement();
  if (!element) return;
  if (element[property] === value) return;
  pushEditorHistory();
  element[property] = value;
  renderEditorCanvas();
  scheduleSlideAutosave();
}

document.querySelectorAll('[data-action="new-slide"]').forEach(button => button.addEventListener('click', () => {
  openTemplateChooser();
}));

document.querySelectorAll('[data-add-element]').forEach(button => {
  button.addEventListener('click', () => ['image', 'video'].includes(button.dataset.addElement) ? addMediaElement(button.dataset.addElement) : addEditorElement(button.dataset.addElement));
  button.addEventListener('dragstart', event => event.dataTransfer.setData('application/x-kiosky-editor-element', button.dataset.addElement));
});
const editorCanvas = document.querySelector('#slide-editor-canvas');
editorCanvas.addEventListener('dragover', event => { event.preventDefault(); editorCanvas.classList.add('drag-over'); });
editorCanvas.addEventListener('dragleave', () => editorCanvas.classList.remove('drag-over'));
editorCanvas.addEventListener('drop', event => {
  event.preventDefault(); editorCanvas.classList.remove('drag-over');
  const type = event.dataTransfer.getData('application/x-kiosky-editor-element');
  if (!type) return;
  const rect = editorCanvas.getBoundingClientRect();
  const x = Math.max(0, ((event.clientX - rect.left) / rect.width) * 100 - 20);
  const y = Math.max(0, ((event.clientY - rect.top) / rect.height) * 100 - 10);
  if (['image', 'video'].includes(type)) addMediaElement(type, x, y);
  else addEditorElement(type, x, y);
});
document.querySelector('#editor-ratio').addEventListener('change', event => {
  pushEditorHistory();
  const previousDesign = editorCanvas.dataset.ratio === 'portrait' ? 'portrait' : 'landscape';
  applyEditorOrientation(event.target.value, previousDesign);
  scheduleSlideAutosave();
});
document.querySelector('#slide-event').addEventListener('change',()=>{pushEditorHistory();renderEditorCanvas();scheduleSlideAutosave();});
document.querySelector('#slide-status').addEventListener('change',()=>{pushEditorHistory();scheduleSlideAutosave();});
document.querySelector('#slide-starts-at').addEventListener('change',()=>{pushEditorHistory();scheduleSlideAutosave();});
document.querySelector('#slide-ends-at').addEventListener('change',()=>{pushEditorHistory();scheduleSlideAutosave();});
document.querySelector('#slide-tags').addEventListener('input',()=>{pushEditorHistory();scheduleSlideAutosave();});
document.querySelector('#slide-location-options').addEventListener('change',()=>{pushEditorHistory();const selected=[...document.querySelectorAll('#slide-location-options input:checked')].map(input=>input.value);document.querySelector('#slide-location-summary').textContent=selected.length?`${selected.length} ausgewählt`:'Alle';scheduleSlideAutosave();});
document.querySelector('#new-slide-title').addEventListener('input',()=>{pushEditorHistory();scheduleSlideAutosave();});

document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', async () => {
  const dialog = document.querySelector(`#${button.dataset.closeDialog}`);
  if (dialog === slideDialog && !state.editingTemplateId) {
    try { await flushSlideAutosave(); } catch { return; }
  }
  dialog.close();
}));
document.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => {
  const editable = document.querySelector('.canvas-element.is-selected [contenteditable]');
  if (editable) { editable.focus(); document.execCommand(button.dataset.command); }
}));
document.querySelectorAll('[data-align]').forEach(button => button.addEventListener('click', () => updateSelectedElement('align', button.dataset.align)));
document.addEventListener('keydown',event=>{if(!slideDialog.open)return;const target=event.target,typing=target?.isContentEditable||['INPUT','TEXTAREA','SELECT'].includes(target?.tagName);if(typing)return;const command=event.metaKey||event.ctrlKey;if(command&&event.key.toLowerCase()==='z'){event.preventDefault();event.shiftKey?redoEditor():undoEditor();return;}if(command&&event.key.toLowerCase()==='d'){event.preventDefault();const selected=state.editorElements.filter(item=>state.selectedEditorElementIds?.has(item.id));if(!selected.length)return;pushEditorHistory();const copies=selected.map(item=>({...item,id:`element-${Date.now()}-${Math.random().toString(36).slice(2,6)}`,x:Math.min(100-item.width,item.x+3),y:Math.min(100-item.height,item.y+3)}));state.editorElements.push(...copies);state.selectedEditorElementIds=new Set(copies.map(item=>item.id));state.selectedEditorElementId=copies[0].id;renderEditorCanvas();scheduleSlideAutosave({immediate:true,successMessage:'Elemente wurden dupliziert und gespeichert.'});return;}if(['Delete','Backspace'].includes(event.key)){event.preventDefault();pushEditorHistory();deleteSelectedEditorElements();return;}if(event.key==='Escape'){state.selectedEditorElementIds=new Set();state.selectedEditorElementId=null;renderEditorCanvas();return;}if(['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)){event.preventDefault();pushEditorHistory();const amount=event.shiftKey?2:0.25;state.editorElements.filter(item=>state.selectedEditorElementIds?.has(item.id)&&!item.locked).forEach(item=>{if(event.key==='ArrowLeft')item.x=Math.max(0,item.x-amount);if(event.key==='ArrowRight')item.x=Math.min(100-item.width,item.x+amount);if(event.key==='ArrowUp')item.y=Math.max(0,item.y-amount);if(event.key==='ArrowDown')item.y=Math.min(100-item.height,item.y+amount);});renderEditorCanvas();scheduleSlideAutosave();}});
document.querySelectorAll('[data-editor-action]').forEach(button => button.addEventListener('click', () => {
  const action = button.dataset.editorAction;
  const element = selectedEditorElement();
  if (action === 'preview') { slideDialog.classList.toggle('is-previewing'); button.textContent = slideDialog.classList.contains('is-previewing') ? 'Editor anzeigen' : '▶ Vorschau'; return; }
  if (action === 'slide-settings') { state.selectedEditorElementId = null; state.showSlideSettings = true; renderEditorCanvas(); return; }
  if (!element) return;
  const index = state.editorElements.indexOf(element);
  if (action === 'delete') {pushEditorHistory();deleteSelectedEditorElements();return;}
  if (action === 'duplicate') {pushEditorHistory(); const copy = { ...element, id: `element-${Date.now()}`, x: Math.min(90, element.x + 4), y: Math.min(90, element.y + 4) }; state.editorElements.push(copy); state.selectedEditorElementId = copy.id;state.selectedEditorElementIds=new Set([copy.id]); }
  if (action === 'forward' && index < state.editorElements.length - 1) {pushEditorHistory();[state.editorElements[index], state.editorElements[index + 1]] = [state.editorElements[index + 1], state.editorElements[index]];}
  if (action === 'backward' && index > 0) {pushEditorHistory();[state.editorElements[index], state.editorElements[index - 1]] = [state.editorElements[index - 1], state.editorElements[index]];}
  renderEditorCanvas();
  scheduleSlideAutosave({ immediate: ['duplicate', 'forward', 'backward'].includes(action) });
}));

document.querySelectorAll('[data-editor-panel-toggle]').forEach(button => button.addEventListener('click', () => {
  const panel = button.dataset.editorPanelToggle;
  setEditorMobilePanel(slideDialog.classList.contains(`mobile-panel-${panel}`) ? '' : panel);
}));
document.querySelectorAll('[data-editor-panel-close]').forEach(button => button.addEventListener('click', () => setEditorMobilePanel('')));
document.querySelector('#editor-layer-list').addEventListener('click', event => {
  const select = event.target.closest('[data-layer-select]');
  const direction = event.target.closest('[data-layer-direction]');
  const row = event.target.closest('[data-layer-id]');
  if (!row) return;
  const id = row.dataset.layerId;
  if (select) {
    if (event.metaKey || event.ctrlKey || event.shiftKey) {
      state.selectedEditorElementIds.has(id) ? state.selectedEditorElementIds.delete(id) : state.selectedEditorElementIds.add(id);
    } else {
      state.selectedEditorElementIds = new Set([id]);
    }
    state.selectedEditorElementId = state.selectedEditorElementIds.has(id) ? id : [...state.selectedEditorElementIds][0] || null;
    state.showSlideSettings = false;
    renderEditorCanvas();
    return;
  }
  if (direction) {
    const index = state.editorElements.findIndex(element => element.id === id);
    const target = index + Number(direction.dataset.layerDirection);
    if (index < 0 || target < 0 || target >= state.editorElements.length) return;
    pushEditorHistory();
    [state.editorElements[index], state.editorElements[target]] = [state.editorElements[target], state.editorElements[index]];
    renderEditorCanvas();
    scheduleSlideAutosave({ immediate: true, successMessage: 'Ebenenreihenfolge wurde gespeichert.' });
  }
});

const propertyBindings = [
  ['property-text', 'text', value => value], ['property-font-size', 'fontSize', Number], ['property-auto-font-size','autoFontSize',(_,input)=>input.checked], ['property-color', 'color', value => value], ['property-event', 'event', value => value],
  ['property-web-url', 'src', value => value], ['property-video-muted', 'muted', (_, input) => input.checked],
  ['property-event-field', 'field', value => value], ['property-event-prefix', 'prefix', value => value], ['property-event-fallback', 'fallback', value => value], ['property-event-locked', 'locked', (_, input) => input.checked],
  ['property-x', 'x', Number], ['property-y', 'y', Number], ['property-width', 'width', Number], ['property-height', 'height', Number], ['property-background', 'background', value => value],
  ['property-rotation', 'rotation', Number], ['property-opacity', 'opacity', Number], ['property-padding', 'padding', Number], ['property-radius', 'radius', Number],
  ['property-font-weight', 'fontWeight', Number], ['property-line-height', 'lineHeight', Number], ['property-letter-spacing', 'letterSpacing', Number], ['property-text-align', 'align', value => value],
  ['property-ticker-duration', 'tickerDuration', Number], ['property-ticker-direction', 'tickerDirection', value => value],
  ['property-qr-kind', 'qrKind', value => value], ['property-qr-value', 'qrValue', value => value], ['property-wifi-ssid', 'wifiSsid', value => value],
  ['property-wifi-password', 'wifiPassword', value => value], ['property-wifi-encryption', 'wifiEncryption', value => value], ['property-wifi-hidden', 'wifiHidden', (_, input) => input.checked],
  ['property-qr-foreground', 'qrForeground', value => value], ['property-countdown-mode', 'countdownMode', value => value],
  ['property-countdown-daily-time', 'countdownDailyTime', value => value], ['property-countdown-target', 'targetTime', value => value], ['property-countdown-prefix', 'prefix', value => value],
  ['property-countdown-suffix', 'suffix', value => value], ['property-countdown-show-seconds', 'showSeconds', (_,input) => input.checked], ['property-weather-mode', 'weatherMode', value => value]
];
propertyBindings.forEach(([id, property, convert]) => {
  const input = document.querySelector(`#${id}`);
  const eventName = input.tagName === 'SELECT' || input.type === 'checkbox' ? 'change' : 'input';
  input.addEventListener(eventName, () => {
    updateSelectedElement(property, convert(input.value, input));
    if(id==='property-auto-font-size')document.querySelector('#property-font-size').disabled=input.checked;
    if(id==='property-ticker-duration')document.querySelector('#property-ticker-duration-label').textContent=`${input.value} Sekunden je Durchlauf · weniger ist schneller`;
  });
});

const tickerItemsEditor = document.querySelector('#ticker-items-editor');
function updateTickerItemFromInput(event) {
  const input = event.target.closest('[data-ticker-item-field]'), card = event.target.closest('[data-ticker-item-index]'), element = selectedEditorElement();
  if (!input || !card || !element || element.type !== 'ticker') return;
  const item = normalizeTickerItems(element)[Number(card.dataset.tickerItemIndex)], property = input.dataset.tickerItemField;
  if (!item) return;
  const value = input.type === 'checkbox' ? input.checked : input.value;
  if (item[property] === value) return;
  if (card.dataset.historyCaptured !== 'true') { pushEditorHistory(); card.dataset.historyCaptured = 'true'; }
  item[property] = value;
  element.text = normalizeTickerItems(element).filter(entry => entry.kind === 'text').map(entry => entry.text).join(' · ');
  scheduleSlideAutosave();
  if (event.type === 'change') renderEditorCanvas();
}
tickerItemsEditor.addEventListener('input', updateTickerItemFromInput);
tickerItemsEditor.addEventListener('change', updateTickerItemFromInput);
tickerItemsEditor.addEventListener('focusout', event => { event.target.closest('[data-ticker-item-index]')?.removeAttribute('data-history-captured'); });
tickerItemsEditor.addEventListener('click', event => {
  const button = event.target.closest('button'), card = event.target.closest('[data-ticker-item-index]'), element = selectedEditorElement();
  if (!button || !card || !element || element.type !== 'ticker') return;
  const items = normalizeTickerItems(element), index = Number(card.dataset.tickerItemIndex);
  pushEditorHistory();
  if (button.dataset.tickerRemove !== undefined) items.splice(index, 1);
  if (button.dataset.tickerMove) {
    const target = index + Number(button.dataset.tickerMove);
    if (target >= 0 && target < items.length) [items[index], items[target]] = [items[target], items[index]];
  }
  if (!items.length) items.push({ id: `ticker-item-${Date.now()}`, kind: 'text', text: '' });
  element.text = items.filter(item => item.kind === 'text').map(item => item.text).join(' · ');
  renderEditorCanvas();
  scheduleSlideAutosave();
});
document.querySelectorAll('[data-ticker-add]').forEach(button => button.addEventListener('click', () => {
  const element = selectedEditorElement();
  if (!element || element.type !== 'ticker') return;
  pushEditorHistory();
  const kind = button.dataset.tickerAdd, base = { id: `ticker-item-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`, kind };
  normalizeTickerItems(element).push(kind === 'text' ? { ...base, text: 'Neuer Text' } : kind === 'countdown' ? { ...base, countdownMode: 'daily', countdownDailyTime: '18:00', prefix: 'Noch ', suffix: '', showSeconds: true } : kind === 'qr-code' ? { ...base, qrKind: 'text', qrValue: 'https://example.org', qrForeground: '#111111', qrBackground: '#ffffff' } : { ...base, icon: '★', label: 'Icon' });
  renderEditorCanvas();
  scheduleSlideAutosave();
}));

document.querySelector('#property-image-library').addEventListener('click', () => openMediaLibrary(asset => {
  const element = selectedEditorElement();
  if (!element) return;
  pushEditorHistory();
  applyMediaAssetToElement(element, asset);
  renderEditorCanvas();
  scheduleSlideAutosave({ immediate: true, successMessage: 'Bild wurde zugewiesen und die Slide gespeichert.' });
}, 'image', 'Bild für das ausgewählte Element auswählen'));
document.querySelector('#property-video-library').addEventListener('click', () => openMediaLibrary(asset => {
  const element = selectedEditorElement();
  if (!element) return;
  pushEditorHistory();
  applyMediaAssetToElement(element, asset);
  renderEditorCanvas();
  scheduleSlideAutosave({ immediate: true, successMessage: 'Video wurde zugewiesen und die Slide gespeichert.' });
}, 'video', 'Video für das ausgewählte Element auswählen'));

function openMediaLibrary(handler = null, typeFilter = '', context = 'Medium für die Slide auswählen') {
  state.mediaSelectHandler = handler || (asset => {
    const selected = selectedEditorElement();
    if (selected && ['image', 'video'].includes(selected.type)) selected.type = asset.type === 'video' ? 'video' : 'image';
    else addEditorElement(asset.type === 'video' ? 'video' : 'image');
    const target = selectedEditorElement();
    applyMediaAssetToElement(target, asset);
    renderEditorCanvas();
    scheduleSlideAutosave({ immediate: true, successMessage: 'Medium wurde hinzugefügt und die Slide gespeichert.' });
  });
  state.mediaTypeFilter = typeFilter;
  document.querySelector('#media-dialog-title').textContent = typeFilter === 'video' ? 'Video aus Mediendatenbank' : ['image','image-only'].includes(typeFilter) ? 'Bild aus Mediendatenbank' : 'Mediendatenbank';
  document.querySelector('#media-dialog-context').textContent = `${context}. Neue Dateien werden hier hochgeladen und zentral gespeichert.`;
  renderMediaBrowser('media', true);
  document.querySelector('#media-dialog').showModal();
}
function renderMediaFolders(prefix, parentId = null, depth = 0) {
  const activeId = prefix === 'media-page' ? state.activeMediaPageFolderId : state.activeMediaFolderId;
  const children = state.mediaFolders.filter(folder => (folder.parentId || null) === parentId);
  return children.map(folder => `<div class="media-folder-row"><button class="media-folder-button ${activeId === folder.id ? 'is-active' : ''}" type="button" data-media-folder="${escapeHtml(folder.id)}" style="padding-left:${8 + depth * 14}px"><span>▰ ${escapeHtml(folder.name)}</span><small>${state.mediaAssets.filter(asset => !asset.deletedAt && asset.folderId === folder.id).length}</small></button>${folder.id.startsWith('system-') ? '<span></span><span></span>' : `<button type="button" data-rename-media-folder="${escapeHtml(folder.id)}" title="Ordner umbenennen">✎</button><button type="button" data-delete-media-folder="${escapeHtml(folder.id)}" title="Ordner löschen">×</button>`}</div>${renderMediaFolders(prefix, folder.id, depth + 1)}`).join('');
}
function renderMediaBrowser(prefix, selectionMode = false) {
  const tree = document.querySelector(`#${prefix}-folder-tree`); if (!tree) return;
  const isPage = prefix === 'media-page', activeId = isPage ? state.activeMediaPageFolderId : state.activeMediaFolderId, trash = isPage && state.mediaPageTrash;
  const activeAssets = state.mediaAssets.filter(asset => !asset.deletedAt);
  tree.innerHTML = `<button class="media-folder-button ${!activeId && !trash ? 'is-active' : ''}" type="button" data-media-folder=""><span>Alle Medien</span><small>${activeAssets.length}</small></button>${renderMediaFolders(prefix)}${isPage ? `<button class="media-folder-button media-trash-button ${trash ? 'is-active' : ''}" type="button" data-media-trash><span>♲ Papierkorb</span><small>${state.mediaAssets.filter(asset => asset.deletedAt).length}</small></button>` : ''}`;
  const folder = state.mediaFolders.find(item => item.id === activeId);
  document.querySelector(`#${prefix}-breadcrumb`).textContent = trash ? 'Papierkorb' : folder ? `Mediendatenbank / ${folder.name}` : 'Alle Medien';
  const query = document.querySelector(`#${prefix}-search`).value.trim().toLowerCase(), typeFilter = isPage ? state.mediaPageTypeFilter : state.mediaTypeFilter;
  const assets = state.mediaAssets.filter(asset => Boolean(asset.deletedAt) === trash && (!activeId || asset.folderId === activeId) && (!typeFilter || asset.type === typeFilter || (typeFilter === 'image' && asset.type === 'icon') || (typeFilter === 'image-only' && asset.type === 'image')) && (!query || asset.name.toLowerCase().includes(query)));
  const grid = document.querySelector(`#${prefix}-asset-grid`);
  grid.innerHTML = assets.map(asset => {
    const typeLabel = asset.metadata?.system ? 'Systemdatei' : asset.type === 'video' ? `Video${asset.metadata?.trimEnd ? ` · ${Number(asset.metadata.trimStart || 0).toFixed(1)}–${Number(asset.metadata.trimEnd).toFixed(1)} s` : ''}` : asset.type === 'icon' ? 'Vektorgrafik' : 'Bild';
    const actions = trash ? `<button type="button" data-restore-media="${escapeHtml(asset.id)}" title="Wiederherstellen">↶</button><button class="is-danger" type="button" data-purge-media="${escapeHtml(asset.id)}" title="Endgültig löschen">×</button>` : `${selectionMode ? `<button type="button" data-use-media="${escapeHtml(asset.id)}" title="In Slide verwenden">＋</button>` : ''}${asset.metadata?.system ? '' : `<button type="button" data-edit-media="${escapeHtml(asset.id)}" title="Bearbeiten">✎</button><button type="button" data-delete-media="${escapeHtml(asset.id)}" title="In den Papierkorb">♲</button>`}`;
    return `<article class="media-asset"><div class="media-asset-preview">${asset.type === 'video' ? `<video src="${escapeHtml(asset.src)}" muted preload="metadata"></video><span class="media-duration">▶</span>` : `<img src="${escapeHtml(asset.src)}" alt="">`}</div><div class="media-asset-body"><strong title="${escapeHtml(asset.name)}">${escapeHtml(asset.name)}</strong><small>${typeLabel}</small><span class="media-asset-actions">${actions}</span></div></article>`;
  }).join('') || `<div class="media-empty">${trash ? 'Der Papierkorb ist leer.' : 'Noch keine Medien in diesem Ordner.<br>Lege Ordner an oder lade Bilder, Vektorgrafiken und Videos hoch.'}</div>`;
  tree.querySelectorAll('[data-media-folder]').forEach(button => button.addEventListener('click', () => { if (isPage) { state.activeMediaPageFolderId = button.dataset.mediaFolder || null; state.mediaPageTrash = false; } else state.activeMediaFolderId = button.dataset.mediaFolder || null; renderMediaBrowser(prefix, selectionMode); }));
  tree.querySelector('[data-media-trash]')?.addEventListener('click', () => { state.activeMediaPageFolderId = null; state.mediaPageTrash = true; renderMediaBrowser(prefix); });
  tree.querySelectorAll('[data-rename-media-folder]').forEach(button => button.addEventListener('click', async () => { const folder = state.mediaFolders.find(item => item.id === button.dataset.renameMediaFolder), name = folder && window.prompt('Neuer Ordnername:', folder.name); if (!name?.trim()) return; try { await apiRequest(`/api/media/folders/${folder.id}`, { method: 'PUT', body: JSON.stringify({ name: name.trim(), parentId: folder.parentId }) }); await refreshMedia(); } catch (error) { showToast(error.message); } }));
  tree.querySelectorAll('[data-delete-media-folder]').forEach(button => button.addEventListener('click', async () => { const folder = state.mediaFolders.find(item => item.id === button.dataset.deleteMediaFolder); if (!folder || !window.confirm(`Ordner „${folder.name}“ löschen? Enthaltene Medien werden nach „Alle Medien“ verschoben.`)) return; try { await apiRequest(`/api/media/folders/${folder.id}`, { method: 'DELETE' }); if (state.activeMediaFolderId === folder.id) state.activeMediaFolderId = null; if (state.activeMediaPageFolderId === folder.id) state.activeMediaPageFolderId = null; await refreshMedia(); showToast('Ordner wurde gelöscht.'); } catch (error) { showToast(error.message); } }));
  grid.querySelectorAll('[data-use-media]').forEach(button => button.addEventListener('click', () => { const asset = state.mediaAssets.find(item => item.id === button.dataset.useMedia); if (asset && state.mediaSelectHandler) state.mediaSelectHandler(asset); document.querySelector('#media-dialog').close(); }));
  grid.querySelectorAll('[data-edit-media]').forEach(button => button.addEventListener('click', () => openMediaEditor(button.dataset.editMedia)));
  grid.querySelectorAll('[data-delete-media]').forEach(button => button.addEventListener('click', async () => { const asset = state.mediaAssets.find(item => item.id === button.dataset.deleteMedia); if (!asset || !window.confirm(`„${asset.name}“ in den Papierkorb verschieben?`)) return; try { await apiRequest(`/api/media/assets/${asset.id}`, { method: 'DELETE' }); await refreshMedia(); showToast('Medium wurde in den Papierkorb verschoben.'); } catch (error) { showToast(error.message); } }));
  grid.querySelectorAll('[data-restore-media]').forEach(button => button.addEventListener('click', async () => { try { await apiRequest(`/api/media/assets/${button.dataset.restoreMedia}/restore`, { method: 'POST' }); await refreshMedia(); showToast('Medium wurde wiederhergestellt.'); } catch (error) { showToast(error.message); } }));
  grid.querySelectorAll('[data-purge-media]').forEach(button => button.addEventListener('click', async () => { const asset = state.mediaAssets.find(item => item.id === button.dataset.purgeMedia); if (!asset || !window.confirm(`„${asset.name}“ endgültig löschen? Diese Aktion kann nicht rückgängig gemacht werden.`)) return; try { await apiRequest(`/api/media/assets/${asset.id}/permanent`, { method: 'DELETE' }); await refreshMedia(); showToast('Medium wurde endgültig gelöscht.'); } catch (error) { showToast(error.message); } }));
  if (isPage) { const images = activeAssets.filter(asset => asset.type !== 'video').length, videos = activeAssets.filter(asset => asset.type === 'video').length; document.querySelector('#media-page-summary').innerHTML = `<div><strong>${activeAssets.length}</strong><span>Medien gesamt</span></div><div><strong>${images}</strong><span>Bilder & Vektoren</span></div><div><strong>${videos}</strong><span>Videos</span></div><div><strong>${state.mediaFolders.length}</strong><span>Ordner</span></div>`; }
}
function renderMediaLibrary() { renderMediaBrowser('media', true); renderMediaBrowser('media-page'); }
async function refreshMedia() {
  const result = await apiRequest('/api/media');
  state.mediaFolders = result.folders || [];
  state.mediaAssets = result.assets || [];
  state.editorElements = hydrateMediaReferences(state.editorElements);
  state.editorSlideSettings = hydrateMediaReferences(state.editorSlideSettings);
  state.slides = state.slides.map(slide => ({ ...slide, elements: hydrateMediaReferences(slide.elements), settings: hydrateMediaReferences(slide.settings) }));
  state.templates = state.templates.map(template => ({ ...template, document: hydrateMediaReferences(template.document) }));
  renderMediaLibrary();
  if (slideDialog.open) renderEditorCanvas();
}
document.querySelector('[data-action="open-media-library"]').addEventListener('click', () => openMediaLibrary());
document.querySelector('#media-search').addEventListener('input', () => renderMediaBrowser('media', true));
document.querySelector('#media-page-search').addEventListener('input', () => renderMediaBrowser('media-page'));
document.querySelectorAll('[data-media-page-filter]').forEach(button => button.addEventListener('click', () => { state.mediaPageTypeFilter = button.dataset.mediaPageFilter; document.querySelectorAll('[data-media-page-filter]').forEach(item => item.classList.toggle('is-active', item === button)); renderMediaBrowser('media-page'); }));
async function createMediaFolder(page = false) { const name = window.prompt('Name des neuen Ordners:'); if (!name?.trim()) return; const parentId = page ? state.activeMediaPageFolderId : state.activeMediaFolderId; try { await apiRequest('/api/media/folders', { method: 'POST', body: JSON.stringify({ name: name.trim(), parentId: parentId || undefined }) }); await refreshMedia(); showToast(`Ordner „${name.trim()}“ wurde angelegt.`); } catch (error) { showToast(error.message); } }
async function addMediaUrl(page = false) { const src = window.prompt('Direkte Bild-, SVG- oder Video-URL (https://…):'); if (!src?.trim()) return; const name = window.prompt('Dateiname:', src.split('/').pop() || 'Externes Medium'); if (!name?.trim()) return; const type = /\.(mp4|webm)(?:\?|$)/i.test(src) ? 'video' : /\.svg(?:\?|$)/i.test(src) ? 'icon' : 'image', folderId = page ? state.activeMediaPageFolderId : state.activeMediaFolderId; try { await apiRequest('/api/media/assets', { method: 'POST', body: JSON.stringify({ name: name.trim(), folderId: folderId || undefined, type, src: src.trim() }) }); await refreshMedia(); showToast('Medien-URL wurde gespeichert.'); } catch (error) { showToast(error.message); } }
document.querySelector('[data-media-action="new-folder"]').addEventListener('click', () => createMediaFolder());
document.querySelector('[data-media-action="add-url"]').addEventListener('click', () => addMediaUrl());
document.querySelector('[data-media-page-action="new-folder"]').addEventListener('click', () => createMediaFolder(true));
document.querySelector('[data-media-page-action="add-url"]').addEventListener('click', () => addMediaUrl(true));
async function uploadMediaFiles(event,page=false){for(const file of[...event.target.files]){if(file.size>20_000_000){showToast(`„${file.name}“ ist größer als 20 MB und wurde übersprungen.`);continue;}const src=await new Promise(resolve=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.readAsDataURL(file);}),folderId=page?state.activeMediaPageFolderId:state.activeMediaFolderId,type=file.type.startsWith('video/')?'video':file.type.includes('svg')?'icon':'image',dimensions=type==='video'?{}:await imageSourceDimensions(src);try{await apiRequest('/api/media/assets',{method:'POST',body:JSON.stringify({name:file.name,folderId:folderId||undefined,type,src,metadata:{size:file.size,mimeType:file.type,width:dimensions.width,height:dimensions.height}})});}catch(error){showToast(error.message);}}event.target.value='';await refreshMedia();showToast('Mediendatenbank wurde aktualisiert.');}
document.querySelector('#media-upload').addEventListener('change', event => uploadMediaFiles(event));
document.querySelector('#media-page-upload').addEventListener('change', event => uploadMediaFiles(event, true));

let mediaCropImage = null;
function drawMediaCrop() { if (!mediaCropImage) return; const canvas = document.querySelector('#media-crop-canvas'), context = canvas.getContext('2d'), ratioValue = document.querySelector('#media-crop-ratio').value, ratios = { '16:9': 16 / 9, '4:3': 4 / 3, '1:1': 1, '9:16': 9 / 16 }, ratio = ratios[ratioValue] || mediaCropImage.naturalWidth / mediaCropImage.naturalHeight; canvas.width = 960; canvas.height = Math.round(960 / ratio); const zoom = Number(document.querySelector('#media-crop-zoom').value) / 100, cover = Math.max(canvas.width / mediaCropImage.naturalWidth, canvas.height / mediaCropImage.naturalHeight) * zoom, width = mediaCropImage.naturalWidth * cover, height = mediaCropImage.naturalHeight * cover, x = (canvas.width - width) * Number(document.querySelector('#media-crop-x').value) / 100, y = (canvas.height - height) * Number(document.querySelector('#media-crop-y').value) / 100; context.clearRect(0, 0, canvas.width, canvas.height); context.drawImage(mediaCropImage, x, y, width, height); }
function mediaFolderOptions(selectedId) { return `<option value="">Alle Medien / ohne Ordner</option>${state.mediaFolders.map(folder => `<option value="${escapeHtml(folder.id)}" ${folder.id === selectedId ? 'selected' : ''}>${escapeHtml(folder.name)}</option>`).join('')}`; }
function openMediaEditor(id) { const asset = state.mediaAssets.find(item => item.id === id); if (!asset) return; document.querySelector('#media-edit-id').value = asset.id; document.querySelector('#media-edit-name').value = asset.name; document.querySelector('#media-edit-folder').innerHTML = mediaFolderOptions(asset.folderId); const imageControls = document.querySelector('#media-image-controls'), videoControls = document.querySelector('#media-video-controls'), canvas = document.querySelector('#media-crop-canvas'), video = document.querySelector('#media-trim-video'), editableImage = asset.type === 'image' && !asset.metadata?.system; imageControls.hidden = !editableImage; videoControls.hidden = asset.type !== 'video'; canvas.hidden = !editableImage; video.hidden = asset.type !== 'video'; if (editableImage) { mediaCropImage = new Image(); mediaCropImage.onload = drawMediaCrop; mediaCropImage.src = asset.src; } if (asset.type === 'video') { video.src = asset.src; document.querySelector('#media-trim-start').value = asset.metadata?.trimStart || 0; document.querySelector('#media-trim-end').value = asset.metadata?.trimEnd || ''; video.onloadedmetadata = () => { document.querySelector('#media-trim-end').max = video.duration; document.querySelector('#media-trim-start').max = video.duration; if (!document.querySelector('#media-trim-end').value) document.querySelector('#media-trim-end').value = video.duration.toFixed(1); }; } document.querySelector('#media-edit-dialog').showModal(); }
['media-crop-ratio', 'media-crop-zoom', 'media-crop-x', 'media-crop-y'].forEach(id => document.querySelector(`#${id}`).addEventListener('input', drawMediaCrop));
document.querySelector('#media-edit-form').addEventListener('submit', async event => { event.preventDefault(); const asset = state.mediaAssets.find(item => item.id === document.querySelector('#media-edit-id').value); if (!asset) return; const metadata = { ...(asset.metadata || {}) }; let src = asset.src; if (asset.type === 'image' && !asset.metadata?.system) { src = document.querySelector('#media-crop-canvas').toDataURL('image/jpeg', .9); metadata.crop = { ratio: document.querySelector('#media-crop-ratio').value, zoom: Number(document.querySelector('#media-crop-zoom').value), x: Number(document.querySelector('#media-crop-x').value), y: Number(document.querySelector('#media-crop-y').value) }; } if (asset.type === 'video') { const start = Number(document.querySelector('#media-trim-start').value || 0), end = Number(document.querySelector('#media-trim-end').value); if (!(end > start)) { showToast('Das Video-Ende muss nach dem Start liegen.'); return; } metadata.trimStart = start; metadata.trimEnd = end; metadata.duration = end - start; } try { await apiRequest(`/api/media/assets/${asset.id}`, { method: 'PUT', body: JSON.stringify({ name: document.querySelector('#media-edit-name').value.trim(), folderId: document.querySelector('#media-edit-folder').value || undefined, type: asset.type, src, metadata }) }); document.querySelector('#media-edit-dialog').close(); await refreshMedia(); showToast('Medium wurde aktualisiert.'); } catch (error) { showToast(error.message); } });

document.querySelector('#create-slide-button').addEventListener('click', async () => {
  const titleInput = document.querySelector('#new-slide-title');
  if (!titleInput.value.trim()) {
    titleInput.focus();
    return;
  }
  try {
    await flushSlideAutosave({ force: true, successMessage: `Slide „${titleInput.value.trim()}“ wurde gespeichert.` });
    slideDialog.close();
    await loadContent();
  } catch {}
});

document.querySelector('#archive-slide-button').addEventListener('click', async () => {
  const id = document.querySelector('#slide-edit-id').value;
  const title = document.querySelector('#new-slide-title').value.trim();
  if (!id || !window.confirm(`Slide „${title}“ wirklich archivieren?`)) return;
  try { clearTimeout(state.slideAutosaveTimer); state.slideDirty=false; await apiRequest(`/api/slides/${id}`, { method: 'DELETE' }); slideDialog.close(); await loadContent(); showToast(`Slide „${title}“ wurde archiviert.`); } catch (error) { showToast(error.message); }
});
document.querySelector('#duplicate-slide-button').addEventListener('click',async()=>{
  const id=document.querySelector('#slide-edit-id').value,title=document.querySelector('#new-slide-title').value.trim();
  if(!id)return;
  try{await flushSlideAutosave({force:true,silent:true});const result=await apiRequest(`/api/slides/${encodeURIComponent(id)}/duplicate`,{method:'POST'});slideDialog.close();await loadContent();openSlideEditor(slideForUi(result.slide));showToast(`Slide „${title}“ wurde dupliziert.`);}catch(error){showToast(error.message);}
});
document.querySelector('#delete-slide-button').addEventListener('click',async()=>{
  const id=document.querySelector('#slide-edit-id').value,title=document.querySelector('#new-slide-title').value.trim();
  if(!id||!window.confirm(`Slide „${title}“ endgültig löschen? Sie wird aus allen Kanälen entfernt und kann nicht wiederhergestellt werden.`))return;
  try{clearTimeout(state.slideAutosaveTimer);state.slideDirty=false;await apiRequest(`/api/slides/${encodeURIComponent(id)}/permanent`,{method:'DELETE'});slideDialog.close();await loadContent();showToast(`Slide „${title}“ wurde endgültig gelöscht.`);}catch(error){showToast(error.message);}
});

function renderTemplates() {
  const list=document.querySelector('#template-list'); if(!list)return;
  list.innerHTML=state.templates.map(template=>`<div class="template-list-item">${slideThumb({type:'custom',ratio:template.orientation,elements:template.document.elements||[],settings:template.document.settings||{}})}<span><strong>${escapeHtml(template.name)}</strong><small>${escapeHtml(template.description||'Wiederverwendbares Slide-Layout')} · ${escapeHtml(orientationLabels[template.orientation]||'Querformat')}</small></span><button class="button button-secondary" type="button" data-use-template-id="${escapeHtml(template.id)}">Verwenden</button><button class="button button-secondary" type="button" data-edit-template-id="${escapeHtml(template.id)}">Frei bearbeiten</button><button class="icon-button" type="button" data-delete-template-id="${escapeHtml(template.id)}" aria-label="${escapeHtml(template.name)} archivieren">×</button></div>`).join('')||'<p class="property-note">Noch keine Vorlage gespeichert.</p>';
  list.querySelectorAll('[data-use-template-id]').forEach(button=>button.addEventListener('click',()=>{applyTemplate(button.dataset.useTemplateId);document.querySelector('#template-dialog').close();}));
  list.querySelectorAll('[data-edit-template-id]').forEach(button=>button.addEventListener('click',()=>{editTemplate(button.dataset.editTemplateId);document.querySelector('#template-dialog').close();}));
  list.querySelectorAll('[data-delete-template-id]').forEach(button=>button.addEventListener('click',async()=>{const template=state.templates.find(item=>item.id===button.dataset.deleteTemplateId);if(!template||!window.confirm(`Vorlage „${template.name}“ archivieren?`))return;try{await apiRequest(`/api/templates/${template.id}`,{method:'DELETE'});await loadContent();showToast('Vorlage wurde archiviert.');}catch(error){showToast(error.message);}}));
}
function loadTemplateElements(template){state.editorElements=structuredClone(template.document.elements||[]).map(element=>({...element,...(element.type==='wayfinding'?{background:'transparent',logo:{src:'',position:'top-left'},clock:{enabled:false,position:'bottom-right'}}:{}),id:`element-${Date.now()}-${Math.random().toString(36).slice(2,6)}`}));state.editorSlideSettings={...defaultSlideSettings(),...(structuredClone(template.document.settings||{})),background:{...defaultSlideSettings().background,...(structuredClone(template.document.settings?.background||{}))},logo:{...defaultSlideSettings().logo,...(structuredClone(template.document.settings?.logo||{}))},clock:{...defaultSlideSettings().clock,...(structuredClone(template.document.settings?.clock||{}))}};state.selectedEditorElementId=state.editorElements[0]?.id||null;document.querySelector('#editor-ratio').value=template.orientation;applyEditorOrientation(template.orientation,template.document.designOrientation);renderEditorCanvas();}
function applyTemplate(id){const template=state.templates.find(item=>item.id===id);if(!template){openSlideEditor();return;}openSlideEditor();loadTemplateElements(template);scheduleSlideAutosave({immediate:true,successMessage:`Vorlage „${template.name}“ wurde geladen und als Slide gespeichert.`});}
function editTemplate(id){const template=state.templates.find(item=>item.id===id);if(!template)return;openSlideEditor(null,{templateId:template.id,templateName:template.name});loadTemplateElements(template);}
function openTemplateChooser(){const list=document.querySelector('#template-chooser-list'),templates=state.templates.map((template,index)=>`<label class="template-choice"><input type="radio" name="template-choice" value="${escapeHtml(template.id)}" ${index===0?'checked':''}><span class="template-choice-preview">${slideThumb({type:'custom',ratio:template.orientation,elements:template.document.elements||[],settings:template.document.settings||{}})}</span><span><strong>${escapeHtml(template.name)}</strong><small>${escapeHtml(orientationLabels[template.orientation]||'Querformat')} · ${escapeHtml(template.description||'Wiederverwendbares Layout')}</small></span></label>`).join('');list.innerHTML=`${templates}<label class="template-choice"><input type="radio" name="template-choice" value="__blank" ${state.templates.length?'':'checked'}><span class="template-choice-preview blank-slide-preview">＋</span><span><strong>Ohne Vorlage</strong><small>Leeren Blanko-Slide öffnen</small></span></label>`;showAuthMessage('template-chooser-message','');document.querySelector('#template-chooser-dialog').showModal();}
document.querySelector('[data-action="blank-slide"]').addEventListener('click',()=>openSlideEditor());
document.querySelector('[data-action="use-template"]').addEventListener('click',openTemplateChooser);
document.querySelector('#template-chooser-form').addEventListener('submit',event=>{event.preventDefault();const selected=document.querySelector('input[name="template-choice"]:checked');if(!selected){showAuthMessage('template-chooser-message','Bitte wähle eine Vorlage aus.');return;}document.querySelector('#template-chooser-dialog').close();selected.value==='__blank'?openSlideEditor():applyTemplate(selected.value);});
document.querySelector('[data-action="manage-templates"]').addEventListener('click',()=>{renderTemplates();document.querySelector('#template-dialog').showModal();});
document.querySelector('[data-action="save-template"]').addEventListener('click',async()=>{const current=state.templates.find(item=>item.id===state.editingTemplateId);const suggested=document.querySelector('#new-slide-title').value.trim()||'Neue Vorlage';const name=current?suggested:window.prompt('Name der neuen Slide-Vorlage:',`${suggested} Vorlage`);if(!name)return;const wayfinding=state.editorElements.some(element=>element.type==='wayfinding');const payload={name,description:wayfinding?'Flexibles ¼–½–¼-Layout mit Zeilen, Pfeilen, Logo und Uhrzeit':`${state.editorElements.filter(element=>element.type==='event-field').length} dynamische Veranstaltungsfelder`,orientation:document.querySelector('#editor-ratio').value,status:'active',document:{designOrientation:state.editorAutoDesignOrientation,settings:structuredClone(state.editorSlideSettings),elements:structuredClone(state.editorElements)}};try{await apiRequest(current?`/api/templates/${current.id}`:'/api/templates',{method:current?'PUT':'POST',body:JSON.stringify(payload)});await loadContent();if(current)slideDialog.close();showToast(`Vorlage „${name}“ wurde ${current?'aktualisiert':'gespeichert'}.`);}catch(error){showToast(error.message);}});

['channel-transition', 'channel-orientation', 'channel-repeat'].forEach(id => document.querySelector(`#${id}`).addEventListener('change', () => {
  commitChannelHistoryStep();
  saveChannelState();
}));
document.querySelector('#channel-undo-button').addEventListener('click', undoChannel);
document.querySelector('#channel-redo-button').addEventListener('click', redoChannel);
document.querySelector('#slide-undo-button').addEventListener('click', undoEditor);
document.querySelector('#slide-redo-button').addEventListener('click', redoEditor);

async function persistActiveChannel() {
  const channel = activeChannel();
  if (!channel) return null;
  state.playlist = state.playlist.map((entry, index) => ({ ...entry, duration: Math.max(3, Number(document.querySelector(`[data-duration-index="${index}"]`)?.value || entry.duration)), transition: document.querySelector(`[data-transition-index="${index}"]`)?.value || entry.transition || 'inherit' }));
  const payload = { name: channel.name, description: channel.description, eventId: channel.eventId,tags:scopedTags(channel.tags||[]),locationIds:channel.locationIds||[], status: channel.status, orientation: document.querySelector('#channel-orientation').value, defaultTransition: document.querySelector('#channel-transition').value, repeatEnabled: document.querySelector('#channel-repeat').checked, items: state.playlist.map(entry => ({ slideId: entry.slideId, durationSeconds: Number(entry.duration), transition: entry.transition || 'inherit' })) };
  const result = await apiRequest(`/api/channels/${channel.id}`, { method: 'PUT', body: JSON.stringify(payload) });
  const index = state.channels.findIndex(item => item.id === result.channel.id);
  if (index >= 0) state.channels[index] = result.channel;
  document.querySelector('#channel-builder-title').textContent = result.channel.name;
  renderChannelOverview();
  return result.channel;
}

async function flushChannelAutosave(options = {}) {
  clearTimeout(state.channelAutosaveTimer);
  state.channelAutosaveTimer = null;
  if (!activeChannel() || (!state.channelDirty && !options.force)) return null;
  const revision = state.channelSaveRevision;
  state.channelDirty = false;
  setAutosaveStatus('channel', 'saving', 'Speichert …');
  const operation = state.channelSaveChain.catch(() => {}).then(() => persistActiveChannel());
  state.channelSaveChain = operation;
  try {
    const channel = await operation;
    if (state.channelSaveRevision === revision && !state.channelDirty) setAutosaveStatus('channel', 'saved', savedAtLabel());
    if (options.successMessage) showToast(options.successMessage);
    return channel;
  } catch (error) {
    state.channelDirty = true;
    setAutosaveStatus('channel', 'error', 'Speichern fehlgeschlagen');
    showToast(`Autosave fehlgeschlagen: ${error.message}`);
    throw error;
  }
}

const channelDialog = document.querySelector('#channel-dialog');
function openChannelEditor(channel = null) {
  document.querySelector('#channel-form').reset();
  document.querySelector('#channel-edit-id').value = channel?.id || '';
  document.querySelector('#channel-dialog-title').textContent = channel ? 'Kanal bearbeiten' : 'Kanal anlegen';
  document.querySelector('#channel-name').value = channel?.name || '';
  document.querySelector('#channel-description').value = channel?.description || '';
  document.querySelector('#channel-tags').value=scopedTags(channel?.tags||[]).join(', ');
  const selectedLocations=new Set(channel?.locationIds||[]),locationOptions=document.querySelector('#channel-location-options');
  locationOptions.innerHTML=state.locations.length?state.locations.map(location=>`<label><input type="checkbox" value="${escapeHtml(location.id)}" ${selectedLocations.has(location.id)?'checked':''}> ${escapeHtml(location.name)}</label>`).join(''):'<p class="property-note">Noch keine Standorte vorhanden.</p>';
  document.querySelector('#channel-event').value = channel?.eventId || '';
  document.querySelector('#channel-status').value = channel?.status || 'draft';
  document.querySelector('#archive-channel-button').hidden = !channel || state.currentUser?.role === 'viewer';
  document.querySelector('#delete-channel-button').hidden = !channel || state.currentUser?.role !== 'admin';
  channelDialog.showModal();
}
document.querySelectorAll('[data-action="new-channel"]').forEach(button => button.addEventListener('click', () => openChannelEditor()));
document.querySelector('[data-action="edit-channel"]').addEventListener('click', () => openChannelEditor(activeChannel()));
document.querySelector('#channel-form').addEventListener('submit', async event => {
  event.preventDefault();
  const id = document.querySelector('#channel-edit-id').value;
  const current = id ? state.channels.find(channel => channel.id === id) : null;
  const payload = { name: document.querySelector('#channel-name').value.trim(), description: document.querySelector('#channel-description').value.trim() || undefined,tags:scopedTags(tagInput(document.querySelector('#channel-tags').value)),locationIds:[...document.querySelectorAll('#channel-location-options input:checked')].map(input=>input.value), eventId: document.querySelector('#channel-event').value || undefined, status: document.querySelector('#channel-status').value, orientation: current?.orientation || 'auto', defaultTransition: current?.defaultTransition || 'fade', repeatEnabled: current?.repeatEnabled ?? true, items: current?.items || [] };
  try {
    const result = await apiRequest(id ? `/api/channels/${id}` : '/api/channels', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
    channelDialog.close();
    await loadContent(result.channel.id);
    showToast(`Kanal „${payload.name}“ wurde ${id ? 'aktualisiert' : 'angelegt'}.`);
  } catch (error) { showToast(error.message); }
});
document.querySelector('#archive-channel-button').addEventListener('click', async () => {
  const channel = activeChannel(); if (!channel || !window.confirm(`Kanal „${channel.name}“ wirklich archivieren?`)) return;
  try { await apiRequest(`/api/channels/${channel.id}`, { method: 'DELETE' }); channelDialog.close(); await loadContent(null); showToast('Kanal wurde archiviert.'); } catch (error) { showToast(error.message); }
});
document.querySelector('#delete-channel-button').addEventListener('click',async()=>{
  const id=document.querySelector('#channel-edit-id').value,channel=state.channels.find(item=>item.id===id);
  if(!channel||!window.confirm(`Kanal „${channel.name}“ endgültig löschen? Zeitpläne und Zuweisungen dieses Kanals werden ebenfalls entfernt. Dieser Vorgang kann nicht rückgängig gemacht werden.`))return;
  try{await apiRequest(`/api/channels/${encodeURIComponent(channel.id)}/permanent`,{method:'DELETE'});channelDialog.close();await loadContent(null);showToast(`Kanal „${channel.name}“ wurde endgültig gelöscht.`);}catch(error){showToast(error.message);}
});
document.querySelector('[data-action="duplicate-channel"]').addEventListener('click', async () => {
  const channel = activeChannel(); if (!channel) return;
  try { const result = await apiRequest(`/api/channels/${channel.id}/duplicate`, { method: 'POST' }); await loadContent(result.channel.id); showToast(`Kopie „${result.channel.name}“ wurde angelegt.`); } catch (error) { showToast(error.message); }
});
document.querySelector('[data-action="save-channel"]').addEventListener('click', async () => {
  try {
    await flushChannelAutosave({ force: true, successMessage: 'Kanal und Abspielreihenfolge wurden gespeichert.' });
  } catch {}
});
document.querySelector('[data-action="preview-channel"]').addEventListener('click', () => document.querySelector('#preview-dialog').showModal());

// Display and group management
const displayDialog = document.querySelector('#display-dialog');
const groupDialog = document.querySelector('#group-dialog');

function saveDisplayState() {
  return true;
}

function playerUrl(displayOrSlug) {
  const display=typeof displayOrSlug==='string'?state.displays.find(item=>item.slug===displayOrSlug):displayOrSlug;
  const slug=typeof displayOrSlug==='string'?displayOrSlug:displayOrSlug?.slug;
  const url = new URL(kioskyRuntime.playerBaseUrl || window.location.href, window.location.href);
  url.search = `?display=${encodeURIComponent(slug)}`;
  if(display?.playerKey)url.searchParams.set('key',display.playerKey);
  url.hash = '';
  return url.toString();
}

state.locations=[];state.matrices=[];state.trash=[];state.displayPage=1;
async function loadDisplays(){try{if(!state.channels.length)await loadContent();const trashRequest=state.currentUser?.role==='viewer'?Promise.resolve({data:[]}):apiRequest('/api/trash');const [displayResult,groupResult,locationResult,matrixResult,trashResult]=await Promise.all([apiRequest('/api/displays'),apiRequest('/api/display-groups'),apiRequest('/api/locations'),apiRequest('/api/matrix-displays'),trashRequest]);state.displays=displayResult.data||[];state.groups=groupResult.data||[];state.locations=locationResult.data||[];state.matrices=matrixResult.data||[];state.trash=trashResult.data||[];const online=state.displays.filter(display=>display.online).length;document.querySelector('#sidebar-display-status').textContent=`${online} von ${state.displays.length} Displays online`;const channelOptions=`<option value="">Kein Standardkanal</option>${state.channels.map(channel=>`<option value="${escapeHtml(channel.id)}">${escapeHtml(channel.name)}</option>`).join('')}`;document.querySelector('#display-channel').innerHTML=channelOptions;document.querySelector('#matrix-channel').innerHTML=channelOptions;document.querySelector('#display-location').innerHTML=`<option value="">Kein Standort</option>${state.locations.map(location=>`<option value="${escapeHtml(location.id)}">${escapeHtml(location.name)}</option>`).join('')}`;document.querySelector('#display-location-filter').innerHTML=`<option value="">Alle Standorte</option>${state.locations.map(location=>`<option value="${escapeHtml(location.id)}">${escapeHtml(location.name)}</option>`).join('')}`;document.querySelector('#display-group-filter').innerHTML=`<option value="">Alle Gruppen</option>${state.groups.map(group=>`<option value="${escapeHtml(group.id)}">${escapeHtml(group.name)}</option>`).join('')}`;renderDisplays();renderGroupManager();renderManagementTables();}catch(error){showToast(error.message);}}

function displayTheme(channel) {
  if (channel === 'Restaurant') return 'restaurant';
  if (channel === 'Tagesplan') return 'daily';
  if (channel === 'Sommerkonzert') return 'concert';
  return 'neutral';
}

function displayPreviewCopy(channel) {
  if (channel === 'Restaurant') return ['RESTAURANT', 'Heute geöffnet', 'bis 23:00 Uhr'];
  if (channel === 'Tagesplan') return ['HEUTE', '4 Veranstaltungen', 'Programm ansehen'];
  if (channel === 'Sommerkonzert') return ['KIOSKY', 'Sommerkonzert', 'Einlass 18:30 · Beginn 19:30'];
  return ['KIOSKY', 'Willkommen', 'Aktuelle Informationen'];
}

async function copyText(value) {
  try {
    await navigator.clipboard.writeText(value);
  } catch (error) {
    const textarea = document.createElement('textarea');
    textarea.value = value; document.body.appendChild(textarea); textarea.select(); document.execCommand('copy'); textarea.remove();
  }
}

function displayConfiguredResolution(display){return display.resolution||{width:1920,height:1080};}
function displayEffectiveResolution(display){return display.resolutionMode==='native'&&display.detectedResolution?display.detectedResolution:displayConfiguredResolution(display);}
function displayResolutionSummary(display){if(display.resolutionMode==='native'){const current=display.detectedResolution;return current?`Responsiv · aktuell ${current.width} × ${current.height}`:'Responsiv · native Browsergröße';}const configured=displayConfiguredResolution(display);return`Fest · ${configured.width} × ${configured.height}`;}
function launcherDownloadMenu(display){const base=`/api/displays/${encodeURIComponent(display.id)}/launcher/`,desktop=kioskyRuntime.platform==='standalone'?`<strong>Zugeordneter Player für dieses Display</strong><a href="${base}windows-x64">Windows 64 Bit <small>.exe</small></a><a href="${base}windows-x86">Windows 32 Bit <small>.exe</small></a><a href="${base}linux-x64">Ubuntu / Debian 64 Bit</a><a href="${base}macos-universal">macOS <small>Apple Silicon & Intel · .app</small></a>`:'',pairing=`<button type="button" data-pair-player="${escapeHtml(display.id)}">Universal Player <small>Code koppeln</small></button>`,center=kioskyRuntime.playerCenterUrl||'/player';return`<details class="launcher-download"><summary title="Player einrichten" aria-label="Player für ${escapeHtml(display.name)} einrichten">↓</summary><div>${desktop}<strong>Universal Player</strong><a href="${escapeHtml(center)}" target="_blank" rel="noopener">Player Center öffnen <small>Downloads mit Kopplungscode</small></a>${pairing}<small>Der Universal Player zeigt beim ersten Start einen sechsstelligen Code. Diesen Code hier mit dem Display koppeln.</small></div></details>`;}

function renderDisplays() {
  const grid = document.querySelector('#display-table');
  if (!grid) return;
  document.querySelector('#display-total').textContent = state.displays.length;
  document.querySelector('#display-online').textContent = state.displays.filter(display => display.online).length;
  document.querySelector('#display-group-total').textContent = state.groups.length;
  renderDisplayLiveOverview();
  const query=document.querySelector('#display-search').value.trim().toLowerCase(),status=document.querySelector('#display-status-filter').value,location=document.querySelector('#display-location-filter').value,group=document.querySelector('#display-group-filter').value,matrix=document.querySelector('#display-matrix-filter').value;
  const filtered=state.displays.filter(display=>(!query||`${display.name} ${display.location||''} ${display.channel||''}`.toLowerCase().includes(query))&&(!status||(status==='online'?display.online:status==='offline'?!display.online&&display.status!=='disabled':display.status===status))&&(!location||display.locationId===location)&&(!group||(display.groupIds||[]).includes(group))&&(!matrix||(matrix==='yes'?display.matrix:!display.matrix)));
  const pageSize=25,pages=Math.max(1,Math.ceil(filtered.length/pageSize));state.displayPage=Math.min(Math.max(1,state.displayPage),pages);const rows=filtered.slice((state.displayPage-1)*pageSize,state.displayPage*pageSize);
  grid.innerHTML=`<div class="admin-row admin-head"><span>Name</span><span>Status</span><span>Typ / Auflösung</span><span>Standort</span><span>Gruppe</span><span>Inhalt</span><span>Matrix</span><span>Aktionen</span></div>${rows.map(display=>{const groups=state.groups.filter(item=>(display.groupIds||[]).includes(item.id)).map(item=>item.name);return`<div class="admin-row"><span><strong>${escapeHtml(display.name)}</strong><small>${escapeHtml(display.slug)}</small></span><span><b class="status-pill ${display.online?'online':display.status==='disabled'?'disabled':''}">${display.status==='disabled'?'Deaktiviert':display.online?'Online':'Offline'}</b><small>${display.lastSeenAt?formatLastSeen(display.lastSeenAt):'Noch nie verbunden'}</small></span><span>${escapeHtml(display.displayType||'standard')}<small>${escapeHtml(displayResolutionSummary(display))} · ${display.orientation==='portrait'?'Hoch':'Quer'}</small></span><span>${escapeHtml(display.location||'Ohne Standort')}</span><span>${escapeHtml(groups.join(', ')||'Ohne Gruppe')}</span><span>${escapeHtml(display.channel||'Kein Inhalt')}</span><span>${display.matrix?`<b class="matrix-badge">${escapeHtml(display.matrix.name)} · ${display.matrix.row+1}/${display.matrix.column+1}</b>`:'–'}</span><span class="row-actions"><button class="identify-display-action" type="button" data-identify-display="${escapeHtml(display.id)}" title="Informationen auf dem Display anzeigen">Display identifizieren</button><button type="button" data-copy-display="${escapeHtml(display.id)}" title="URL kopieren">⧉</button><button type="button" data-open-display="${escapeHtml(display.id)}" title="Player öffnen">↗</button>${launcherDownloadMenu(display)}<button type="button" data-edit-display="${escapeHtml(display.id)}" title="Bearbeiten">✎</button><button class="danger-action" type="button" data-delete-display="${escapeHtml(display.id)}" title="Display löschen">⌫ Löschen</button></span></div>`;}).join('')||'<p class="property-note">Keine Displays passen zu den Filtern.</p>'}`;
  document.querySelector('#display-page-info').textContent=`${filtered.length} Displays · Seite ${state.displayPage} von ${pages}`;

  grid.querySelectorAll('[data-copy-display]').forEach(button => button.addEventListener('click', async () => {
    const display = state.displays.find(item => item.id === button.dataset.copyDisplay);
    await copyText(playerUrl(display));
    showToast(`Player-URL für „${display.name}“ wurde kopiert.`);
  }));
  grid.querySelectorAll('[data-identify-display]').forEach(button=>button.addEventListener('click',async()=>{const display=state.displays.find(item=>item.id===button.dataset.identifyDisplay);button.disabled=true;try{await apiRequest(`/api/operations/displays/${display.id}/commands`,{method:'POST',body:JSON.stringify({command:'identify'})});showToast(`Identifikation wird auf „${display.name}“ angezeigt, sobald der Player erreichbar ist.`);}catch(error){showToast(error.message);}finally{button.disabled=false;}}));
  grid.querySelectorAll('[data-pair-player]').forEach(button=>button.addEventListener('click',()=>{button.closest('details')?.removeAttribute('open');openDisplayDialog(button.dataset.pairPlayer);setTimeout(()=>document.querySelector('#display-player-pairing-code').focus(),50);}));
  grid.querySelectorAll('[data-edit-display]').forEach(button => button.addEventListener('click', () => openDisplayDialog(button.dataset.editDisplay)));
  grid.querySelectorAll('[data-open-display]').forEach(button=>button.addEventListener('click',()=>{const display=state.displays.find(item=>item.id===button.dataset.openDisplay);window.open(playerUrl(display),'_blank','noopener');}));
  grid.querySelectorAll('[data-delete-display]').forEach(button=>button.addEventListener('click',async()=>{const display=state.displays.find(item=>item.id===button.dataset.deleteDisplay);if(!confirm(`Display „${display.name}“ in den Papierkorb verschieben?`))return;try{await apiRequest(`/api/displays/${display.id}`,{method:'DELETE'});await loadDisplays();showToast('Display wurde in den Papierkorb verschoben.');}catch(error){showToast(error.message);}}));
}

function liveContentForDisplay(display){
  const channel=display.currentChannelName||state.channels.find(item=>item.id===display.currentChannelId)?.name;
  const slide=display.currentSlideName||state.slides.find(item=>item.id===display.currentSlideId)?.name;
  const channelRecord=state.channels.find(item=>item.id===(display.currentChannelId||display.defaultChannelId));
  const slideRecord=state.slides.find(item=>item.id===display.currentSlideId)||state.slides.find(item=>item.id===channelRecord?.items?.[0]?.slideId);
  return{channel:channel||display.channel||'Kein Inhalt',slide:slide||slideRecord?.name||'–',slideRecord,reported:Boolean(display.currentChannelId||display.currentSlideId),lastSeenAt:display.lastSeenAt};
}
function displayLivePreview(display){
  const live=liveContentForDisplay(display);
  const preview=live.slideRecord?slideThumb(live.slideRecord):`<span class="display-live-empty"><strong>${escapeHtml(live.channel)}</strong><small>${display.online?'Player meldet noch keine Slide':'Kein aktueller Player-Inhalt'}</small></span>`;
  return`<article class="display-live-card"><div class="display-live-screen">${preview}<span class="display-live-state ${display.online?'is-online':''}">${display.online?'Live':'Offline'}</span></div><footer><span><strong>${escapeHtml(display.name)}</strong><small>${escapeHtml(live.channel)}${display.matrix?` · ${escapeHtml(display.matrix.name)}`:''}</small></span><time>${display.lastSeenAt?escapeHtml(formatLastSeen(display.lastSeenAt)):'Noch nie verbunden'}</time></footer></article>`;
}
function matrixLivePreview(matrix){
  const members=[...matrix.members].sort((a,b)=>a.row-b.row||a.column-b.column),displayById=new Map(state.displays.map(display=>[display.id,display]));
  const cells=Array.from({length:matrix.rows*matrix.columns},(_,index)=>{const row=Math.floor(index/matrix.columns),column=index%matrix.columns,member=members.find(item=>item.row===row&&item.column===column),display=member?displayById.get(member.displayId):null,live=display?liveContentForDisplay(display):null;return`<span class="matrix-live-cell">${live?.slideRecord?slideThumb(live.slideRecord):`<i>${display?escapeHtml(live.channel):'Leer'}</i>`}</span>`;}).join('');
  const online=members.filter(member=>displayById.get(member.displayId)?.online).length,lastSeenAt=members.map(member=>displayById.get(member.displayId)?.lastSeenAt).filter(Boolean).sort().at(-1);
  return`<article class="display-live-card is-matrix"><div class="display-live-screen"><div class="matrix-live-preview" style="--matrix-columns:${matrix.columns};--matrix-rows:${matrix.rows}">${cells}</div><span class="display-live-state ${online===members.length&&members.length?'is-online':''}">${online}/${members.length} live</span></div><footer><span><strong>${escapeHtml(matrix.name)}</strong><small>Matrix-Display · ${matrix.rows} × ${matrix.columns}</small></span><time>${lastSeenAt?escapeHtml(formatLastSeen(lastSeenAt)):'Noch nie verbunden'}</time></footer></article>`;
}
function renderDisplayLiveOverview(){
  const overview=document.querySelector('#display-live-overview'),table=document.querySelector('#display-live-table'),updated=document.querySelector('#display-live-updated');if(!overview||!table||!updated)return;
  if(!overview.open){table.replaceChildren();updated.textContent='Minimiert';return;}
  table.innerHTML=`${state.matrices.map(matrixLivePreview).join('')}${state.displays.map(displayLivePreview).join('')}`||'<p class="property-note">Noch keine Displays angelegt.</p>';
  updated.textContent=`Stand ${new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'})}`;
}

function renderManagementTables(){
  const groupQuery=document.querySelector('#group-search').value.trim().toLowerCase();document.querySelector('#group-table').innerHTML=`<div class="admin-row compact admin-head"><span>Gruppe</span><span>Displays</span><span>Status</span><span>Geändert</span><span>Aktionen</span></div>${state.groups.filter(group=>!groupQuery||group.name.toLowerCase().includes(groupQuery)).map(group=>`<div class="admin-row compact"><span><strong>${escapeHtml(group.name)}</strong><small>${escapeHtml(group.description||'Keine Beschreibung')}</small></span><span>${group.displayIds.length}</span><span><b class="status-pill">${escapeHtml(group.status||'active')}</b></span><span>${new Date(group.updatedAt).toLocaleDateString('de-DE')}</span><span class="row-actions"><button data-manage-group="${escapeHtml(group.id)}">✎</button><button data-remove-group="${escapeHtml(group.id)}">⌫</button></span></div>`).join('')||'<p class="property-note">Noch keine Displaygruppen vorhanden.</p>'}`;
  const matrixQuery=document.querySelector('#matrix-search').value.trim().toLowerCase();document.querySelector('#matrix-table').innerHTML=`<div class="admin-row compact admin-head"><span>Matrix</span><span>Raster</span><span>Auflösung</span><span>Status</span><span>Aktionen</span></div>${state.matrices.filter(matrix=>!matrixQuery||matrix.name.toLowerCase().includes(matrixQuery)).map(matrix=>`<div class="admin-row compact"><span><strong>${escapeHtml(matrix.name)}</strong><small>${matrix.members.length} Displays · ${escapeHtml(matrix.channelName||'Kein Standardkanal')}</small></span><span>${matrix.rows} × ${matrix.columns}</span><span>${matrix.totalResolution.width} × ${matrix.totalResolution.height}</span><span><b class="status-pill ${matrix.status==='disabled'?'disabled':''}">${matrix.status==='active'?'Aktiv':'Deaktiviert'}</b></span><span class="row-actions"><button data-edit-matrix="${escapeHtml(matrix.id)}" title="Bearbeiten">✎</button><button data-duplicate-matrix="${escapeHtml(matrix.id)}" title="Duplizieren">⧉</button><button class="danger-action" data-delete-matrix="${escapeHtml(matrix.id)}" title="Matrix-Display löschen">⌫ Löschen</button></span></div>`).join('')||'<p class="property-note">Noch keine Matrix-Displays vorhanden.</p>'}`;
  const locationQuery=document.querySelector('#location-search').value.trim().toLowerCase();document.querySelector('#location-table').innerHTML=`<div class="admin-row compact admin-head"><span>Standort</span><span>Adresse</span><span>Displays</span><span>Warngebiet</span><span>Aktionen</span></div>${state.locations.filter(location=>!locationQuery||`${location.name} ${location.city||''}`.toLowerCase().includes(locationQuery)).map(location=>`<div class="admin-row compact"><span><strong>${escapeHtml(location.name)}</strong><small>${escapeHtml([location.buildingPart,location.floor,location.room].filter(Boolean).join(' · ')||'')}</small></span><span>${escapeHtml([location.street,location.houseNumber,location.postalCode,location.city].filter(Boolean).join(' '))||'–'}</span><span>${location.displayCount}</span><span>${escapeHtml(location.warningAreaCode||'–')}</span><span class="row-actions"><button data-edit-location="${escapeHtml(location.id)}" title="Bearbeiten">✎</button><button class="danger-action" data-delete-location="${escapeHtml(location.id)}" title="Standort löschen">⌫ Löschen</button></span></div>`).join('')||'<p class="property-note">Noch keine Standorte vorhanden.</p>'}`;
  const trashQuery=document.querySelector('#trash-search').value.trim().toLowerCase(),trashType=document.querySelector('#trash-type-filter').value;document.querySelector('#trash-table').innerHTML=`<div class="admin-row compact admin-head"><span>Element</span><span>Typ</span><span>Gelöscht am</span><span>Gelöscht von</span><span>Aktionen</span></div>${state.trash.filter(item=>(!trashQuery||item.name.toLowerCase().includes(trashQuery))&&(!trashType||item.type===trashType)).map(item=>`<div class="admin-row compact"><span><strong>${escapeHtml(item.name)}</strong></span><span>${escapeHtml({event:'Veranstaltung',slide:'Slide',channel:'Kanal',display:'Display',display_group:'Displaygruppe',location:'Standort',matrix:'Matrix-Display'}[item.type]||item.type)}</span><span>${new Date(item.deletedAt).toLocaleString('de-DE')}</span><span>${escapeHtml(item.deletedBy||'System')}</span><span class="row-actions"><button data-restore-trash="${escapeHtml(item.type)}:${escapeHtml(item.id)}" title="Wiederherstellen">↶</button><button data-purge-trash="${escapeHtml(item.type)}:${escapeHtml(item.id)}" title="Endgültig löschen">×</button></span></div>`).join('')||'<p class="property-note">Der Papierkorb ist leer.</p>'}`;
  document.querySelectorAll('[data-manage-group]').forEach(button=>button.addEventListener('click',()=>{renderGroupManager();document.querySelector(`[data-edit-group="${CSS.escape(button.dataset.manageGroup)}"]`)?.click();groupDialog.showModal();}));
  document.querySelectorAll('[data-remove-group]').forEach(button=>button.addEventListener('click',async()=>{if(!confirm('Displaygruppe in den Papierkorb verschieben?'))return;try{await apiRequest(`/api/display-groups/${button.dataset.removeGroup}`,{method:'DELETE'});await loadDisplays();}catch(error){showToast(error.message);}}));
  document.querySelectorAll('[data-edit-location]').forEach(button=>button.addEventListener('click',()=>openLocationDialog(button.dataset.editLocation)));document.querySelectorAll('[data-delete-location]').forEach(button=>button.addEventListener('click',async()=>{const location=state.locations.find(item=>item.id===button.dataset.deleteLocation);if(!confirm(`Standort „${location.name}“ in den Papierkorb verschieben? Zugeordnete Displays bleiben erhalten und werden vom Standort gelöst.`))return;try{await apiRequest(`/api/locations/${location.id}`,{method:'DELETE'});await loadDisplays();showToast('Standort wurde in den Papierkorb verschoben.');}catch(error){showToast(error.message);}}));
  document.querySelectorAll('[data-edit-matrix]').forEach(button=>button.addEventListener('click',()=>openMatrixDialog(button.dataset.editMatrix)));document.querySelectorAll('[data-duplicate-matrix]').forEach(button=>button.addEventListener('click',async()=>{try{await apiRequest(`/api/matrix-displays/${button.dataset.duplicateMatrix}/duplicate`,{method:'POST'});await loadDisplays();showToast('Matrix wurde als deaktivierte Kopie angelegt.');}catch(error){showToast(error.message);}}));document.querySelectorAll('[data-delete-matrix]').forEach(button=>button.addEventListener('click',async()=>{const matrix=state.matrices.find(item=>item.id===button.dataset.deleteMatrix);if(!confirm(`Matrix-Display „${matrix.name}“ in den Papierkorb verschieben?`))return;try{await apiRequest(`/api/matrix-displays/${matrix.id}`,{method:'DELETE'});await loadDisplays();showToast('Matrix-Display wurde in den Papierkorb verschoben.');}catch(error){showToast(error.message);}}));
  document.querySelectorAll('[data-restore-trash]').forEach(button=>button.addEventListener('click',async()=>{const [type,id]=button.dataset.restoreTrash.split(':');try{await apiRequest(`/api/trash/${type}/${id}`,{method:'POST'});await loadDisplays();if(type==='event')await loadEvents();showToast('Element wurde wiederhergestellt.');}catch(error){showToast(error.message);}}));document.querySelectorAll('[data-purge-trash]').forEach(button=>button.addEventListener('click',async()=>{const [type,id]=button.dataset.purgeTrash.split(':');if(!confirm('Element endgültig löschen? Diese Aktion kann nicht rückgängig gemacht werden.'))return;try{await apiRequest(`/api/trash/${type}/${id}`,{method:'DELETE'});await loadDisplays();if(type==='event')await Promise.all([loadEvents(),loadContent()]);showToast('Element wurde endgültig gelöscht.');}catch(error){showToast(error.message);}}));
}

document.querySelectorAll('[data-display-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-display-tab]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelectorAll('[data-display-tab-panel]').forEach(panel=>panel.classList.toggle('is-active',panel.dataset.displayTabPanel===button.dataset.displayTab));}));
document.querySelector('#display-live-overview').addEventListener('toggle',renderDisplayLiveOverview);
['display-search','display-status-filter','display-location-filter','display-group-filter','display-matrix-filter'].forEach(id=>document.querySelector(`#${id}`).addEventListener('input',()=>{state.displayPage=1;renderDisplays();}));
['group-search','matrix-search','location-search','trash-search','trash-type-filter'].forEach(id=>document.querySelector(`#${id}`).addEventListener('input',renderManagementTables));
document.querySelectorAll('[data-display-page]').forEach(button=>button.addEventListener('click',()=>{state.displayPage+=Number(button.dataset.displayPage);renderDisplays();}));

function slugify(value) {
  return value.toLowerCase().replace(/ß/g, 'ss').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

function renderDisplayGroupOptions(selectedIds = []) {
  document.querySelector('#display-group-options').innerHTML = state.groups.map(group => `<label><input type="checkbox" value="${escapeHtml(group.id)}" ${selectedIds.includes(group.id) ? 'checked' : ''}><span>${escapeHtml(group.name)}</span><small>${group.displayIds.length} Displays</small></label>`).join('') || '<p class="property-note">Noch keine Gruppen angelegt.</p>';
}

function updateDisplayUrlPreview() {
  const slug = slugify(document.querySelector('#display-slug').value || document.querySelector('#display-name').value || 'neues-display');
  document.querySelector('#new-display-url').textContent = playerUrl(slug);
}

function syncDisplayResolutionFields(display=null){
  const fixed=document.querySelector('#display-resolution-mode').value==='fixed',fields=document.querySelector('#display-fixed-resolution-fields');
  fields.hidden=!fixed;
  fields.querySelectorAll('input').forEach(input=>{input.disabled=!fixed;});
  const detected=display?.detectedResolution;
  document.querySelector('#display-resolution-help').textContent=fixed?'Der Player nutzt diese feste Zielauflösung für Planung und Matrix-Berechnung.':detected?`Zuletzt vom Browser gemeldet: ${detected.width} × ${detected.height} px${display.devicePixelRatio?` · Pixeldichte ${display.devicePixelRatio}`:''}.`:'Der Player passt sich automatisch an das Browserfenster an und meldet seine aktuelle Größe.';
}

function openDisplayDialog(displayId = null) {
  const display = state.displays.find(item => item.id === displayId);
  document.querySelector('#display-dialog-title').textContent = display ? 'Display bearbeiten' : 'Display hinzufügen';
  document.querySelector('#display-edit-id').value = display ? display.id : '';
  document.querySelector('#display-name').value = display ? display.name : '';
  document.querySelector('#display-slug').value = display ? display.slug : '';
  document.querySelector('#display-slug').dataset.manual = display ? 'true' : 'false';
  document.querySelector('#display-orientation').value = display ? display.orientation : 'landscape';
  document.querySelector('#display-type').value=display?.displayType||'standard';
  document.querySelector('#display-resolution-mode').value=display?.resolutionMode||(display?'fixed':'native');
  document.querySelector('#display-width').value=display?.resolution?.width||1920;
  document.querySelector('#display-height').value=display?.resolution?.height||1080;
  document.querySelector('#display-channel').value = display?.defaultChannelId || '';
  document.querySelector('#display-location').value=display?.locationId||'';
  document.querySelector('#display-status').value=display?.status||'active';
  document.querySelector('#display-cache-enabled').checked=display?.cacheEnabled!==false;
  document.querySelector('#display-player-pairing').hidden=!display;
  document.querySelector('#display-player-pairing-code').value='';
  document.querySelector('#display-player-pairing-status').textContent='';
  document.querySelector('#display-player-pairing-status').className='';
  const selectedGroups = display ? display.groupIds||[] : [];
  renderDisplayGroupOptions(selectedGroups);
  syncDisplayResolutionFields(display);
  updateDisplayUrlPreview();
  displayDialog.showModal();
}

document.querySelectorAll('[data-action="new-display"]').forEach(button => button.addEventListener('click', () => openDisplayDialog()));
document.querySelector('#display-name').addEventListener('input', event => {
  const slugInput = document.querySelector('#display-slug');
  if (slugInput.dataset.manual !== 'true') slugInput.value = slugify(event.target.value);
  updateDisplayUrlPreview();
});
document.querySelector('#display-slug').addEventListener('input', event => { event.target.dataset.manual = 'true'; event.target.value = slugify(event.target.value); updateDisplayUrlPreview(); });
document.querySelector('#display-resolution-mode').addEventListener('change',()=>syncDisplayResolutionFields());
document.querySelector('#display-player-pairing-code').addEventListener('input',event=>{event.target.value=event.target.value.toUpperCase().replace(/[^23456789A-HJ-NP-Z]/g,'').slice(0,6);});
document.querySelector('#display-player-pairing-code').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();document.querySelector('#pair-display-player').click();}});
document.querySelector('#pair-display-player').addEventListener('click',async()=>{
  const displayId=document.querySelector('#display-edit-id').value,code=document.querySelector('#display-player-pairing-code').value.trim().toUpperCase(),status=document.querySelector('#display-player-pairing-status'),button=document.querySelector('#pair-display-player');
  if(!displayId)return;
  if(code.length!==6){status.textContent='Bitte den vollständigen sechsstelligen Code eingeben.';status.className='is-error';return;}
  button.disabled=true;status.textContent='Kopplung wird geprüft …';status.className='';
  try{const display=state.displays.find(item=>item.id===displayId);await apiRequest(`/api/displays/${displayId}/pair`,{method:'POST',body:JSON.stringify({code})});status.textContent=`Player wurde erfolgreich mit „${display?.name||'dem Display'}“ gekoppelt.`;status.className='is-success';document.querySelector('#display-player-pairing-code').value='';showToast('Player wurde gekoppelt.');}
  catch(error){status.textContent=error.message;status.className='is-error';}
  finally{button.disabled=false;}
});
document.querySelector('#display-form').addEventListener('submit', async event => {
  event.preventDefault();
  const name = document.querySelector('#display-name').value.trim();
  const slug = slugify(document.querySelector('#display-slug').value);
  if (!name || !slug) { event.preventDefault(); showToast('Bitte Name und URL-Kennung ausfüllen.'); return; }
  const editId = document.querySelector('#display-edit-id').value;
  if (state.displays.some(display => display.slug === slug && display.id !== editId)) { event.preventDefault(); showToast('Diese URL-Kennung ist bereits vergeben.'); return; }
  const selectedGroups = [...document.querySelectorAll('#display-group-options input:checked')].map(input => input.value);
  const payload={name,slug,orientation:document.querySelector('#display-orientation').value,displayType:document.querySelector('#display-type').value,resolutionMode:document.querySelector('#display-resolution-mode').value,width:Number(document.querySelector('#display-width').value)||1920,height:Number(document.querySelector('#display-height').value)||1080,defaultChannelId:document.querySelector('#display-channel').value||undefined,locationId:document.querySelector('#display-location').value||undefined,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone||'Europe/Berlin',status:document.querySelector('#display-status').value,cacheEnabled:document.querySelector('#display-cache-enabled').checked,groupIds:selectedGroups};
  try{await apiRequest(editId?`/api/displays/${editId}`:'/api/displays',{method:editId?'PUT':'POST',body:JSON.stringify(payload)});displayDialog.close();await loadDisplays();showToast(`Display „${name}“ wurde gespeichert.`);}catch(error){showToast(error.message);}
});

function renderGroupManager() {
  document.querySelector('#group-list').innerHTML = state.groups.map(group => `<div class="group-list-item"><button type="button" data-edit-group="${escapeHtml(group.id)}"><span class="target-icon">▦</span><span><strong>${escapeHtml(group.name)}</strong><small>${group.displayIds.length} Displays</small></span></button><button class="danger-action" type="button" data-delete-group="${escapeHtml(group.id)}" aria-label="${escapeHtml(group.name)} löschen">⌫ Löschen</button></div>`).join('');
  document.querySelector('#group-display-options').innerHTML = state.displays.map(display => `<label><input type="checkbox" value="${escapeHtml(display.id)}"><span>${escapeHtml(display.name)}</span><small>${display.orientation === 'portrait' ? 'Hochformat' : 'Querformat'}</small></label>`).join('');
  document.querySelectorAll('[data-edit-group]').forEach(button => button.addEventListener('click', () => {
    const group = state.groups.find(item => item.id === button.dataset.editGroup);
    document.querySelector('#group-edit-id').value = group.id;
    document.querySelector('#group-name').value = group.name;
    document.querySelectorAll('#group-display-options input').forEach(input => { input.checked = group.displayIds.includes(input.value); });
  }));
  document.querySelectorAll('[data-delete-group]').forEach(button => button.addEventListener('click', async () => {
    const group = state.groups.find(item => item.id === button.dataset.deleteGroup);
    if (!window.confirm(`Display-Gruppe „${group.name}“ in den Papierkorb verschieben?`)) return;
    try{await apiRequest(`/api/display-groups/${group.id}`,{method:'DELETE'});await loadDisplays();renderSchedule();showToast('Display-Gruppe wurde in den Papierkorb verschoben.');}catch(error){showToast(error.message);}
  }));
}

function resetGroupForm() {
  document.querySelector('#group-edit-id').value = '';
  document.querySelector('#group-name').value = '';
  document.querySelectorAll('#group-display-options input').forEach(input => { input.checked = false; });
}

document.querySelectorAll('[data-action="manage-groups"]').forEach(button => button.addEventListener('click', () => { renderGroupManager(); resetGroupForm(); groupDialog.showModal(); }));
document.querySelector('#reset-group-form').addEventListener('click', resetGroupForm);
document.querySelector('#group-form').addEventListener('submit', async event => {
  event.preventDefault();
  const name = document.querySelector('#group-name').value.trim();
  if (!name) return;
  const editId = document.querySelector('#group-edit-id').value;
  const displayIds = [...document.querySelectorAll('#group-display-options input:checked')].map(input => input.value);
  try{await apiRequest(editId?`/api/display-groups/${editId}`:'/api/display-groups',{method:editId?'PUT':'POST',body:JSON.stringify({name,displayIds})});await loadDisplays();renderSchedule();resetGroupForm();showToast(`Display-Gruppe „${name}“ wurde gespeichert.`);}catch(error){showToast(error.message);}
});

const locationDialog=document.querySelector('#location-dialog'),matrixDialog=document.querySelector('#matrix-dialog');
let warncellSearchTimer=0,warncellSearchSequence=0;
function resetWarncellSearch(location){clearTimeout(warncellSearchTimer);warncellSearchSequence+=1;const results=document.querySelector('#location-warning-results'),status=document.querySelector('#location-warning-status'),search=document.querySelector('#location-warning-search');search.value='';results.innerHTML='';results.hidden=true;status.className='warncell-search-status';status.textContent=location?.warningAreaCode?`Aktuell zugewiesen: ${location.warningAreaCode}`:'Mindestens zwei Zeichen eingeben.';}
function renderWarncellResults(items,query){const results=document.querySelector('#location-warning-results'),status=document.querySelector('#location-warning-status');results.innerHTML=items.map(item=>`<button class="warncell-result" type="button" role="option" data-warncell-id="${escapeHtml(item.id)}" data-warncell-name="${escapeHtml(item.name)}"><strong>${escapeHtml(item.name)}</strong><code>${escapeHtml(item.id)}</code><small>${escapeHtml([item.shortName!==item.name?item.shortName:'',item.abbreviation,item.state].filter(Boolean).join(' · '))}</small></button>`).join('');results.hidden=!items.length;status.className='warncell-search-status';status.textContent=items.length?`${items.length} passende Warngebiete – gewünschtes Gebiet auswählen.`:`Keine DWD-Warngebiete für „${query}“ gefunden.`;results.querySelectorAll('[data-warncell-id]').forEach(button=>button.addEventListener('click',()=>{document.querySelector('#location-warning-code').value=button.dataset.warncellId;status.className='warncell-search-status warncell-selection';status.textContent=`Ausgewählt: ${button.dataset.warncellName} · ${button.dataset.warncellId}`;results.hidden=true;}));}
async function searchWarncells(query){const sequence=++warncellSearchSequence,status=document.querySelector('#location-warning-status'),results=document.querySelector('#location-warning-results');if(query.length<2){results.hidden=true;results.innerHTML='';status.className='warncell-search-status';status.textContent='Mindestens zwei Zeichen eingeben.';return;}status.className='warncell-search-status';status.textContent='DWD-Warngebiete werden durchsucht …';try{const response=await apiRequest(`/api/dwd/warncells?q=${encodeURIComponent(query)}`);if(sequence!==warncellSearchSequence)return;renderWarncellResults(response.data||[],query);}catch(error){if(sequence!==warncellSearchSequence)return;results.hidden=true;status.className='warncell-search-status is-error';status.textContent=error.message;}}
document.querySelector('#location-warning-search').addEventListener('input',event=>{clearTimeout(warncellSearchTimer);const query=event.target.value.trim();warncellSearchTimer=window.setTimeout(()=>searchWarncells(query),250);});
function openLocationDialog(id=null){const location=state.locations.find(item=>item.id===id);document.querySelector('#location-form').reset();document.querySelector('#location-edit-id').value=location?.id||'';document.querySelector('#location-dialog-title').textContent=location?'Standort bearbeiten':'Standort anlegen';for(const [field,key] of Object.entries({name:'name',street:'street','house-number':'houseNumber','postal-code':'postalCode',city:'city',country:'country','building-part':'buildingPart',floor:'floor',room:'room','warning-code':'warningAreaCode',latitude:'latitude',longitude:'longitude',description:'description'}))document.querySelector(`#location-${field}`).value=location?.[key]??(field==='country'?'Deutschland':'');document.querySelector('#location-coordinate-status').firstChild.textContent=location?.latitude!=null&&location?.longitude!=null?'Gespeicherte Koordinaten werden bei einer Adressänderung automatisch aktualisiert. Daten © ':'Koordinaten werden beim Speichern automatisch aus der Adresse ermittelt. Daten © ';resetWarncellSearch(location);locationDialog.showModal();}
document.querySelector('[data-action="new-location"]').addEventListener('click',()=>openLocationDialog());
document.querySelector('#location-form').addEventListener('submit',async event=>{event.preventDefault();const id=document.querySelector('#location-edit-id').value,payload={name:document.querySelector('#location-name').value.trim(),street:document.querySelector('#location-street').value.trim()||undefined,houseNumber:document.querySelector('#location-house-number').value.trim()||undefined,postalCode:document.querySelector('#location-postal-code').value.trim()||undefined,city:document.querySelector('#location-city').value.trim()||undefined,country:document.querySelector('#location-country').value.trim()||'Deutschland',buildingPart:document.querySelector('#location-building-part').value.trim()||undefined,floor:document.querySelector('#location-floor').value.trim()||undefined,room:document.querySelector('#location-room').value.trim()||undefined,warningAreaCode:document.querySelector('#location-warning-code').value.trim()||undefined,latitude:document.querySelector('#location-latitude').value||undefined,longitude:document.querySelector('#location-longitude').value||undefined,description:document.querySelector('#location-description').value.trim()||undefined,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone||'Europe/Berlin',status:'active'};try{const result=await apiRequest(id?`/api/locations/${id}`:'/api/locations',{method:id?'PUT':'POST',body:JSON.stringify(payload)});locationDialog.close();await loadDisplays();const messages={found:'Standort gespeichert und Koordinaten automatisch ermittelt.',not_found:'Standort gespeichert, aber für die Adresse wurden keine Koordinaten gefunden.',error:`Standort gespeichert, aber die Koordinatensuche ist fehlgeschlagen: ${result.geocoding?.message||'Adressdienst nicht erreichbar.'}`,incomplete:'Standort gespeichert. Für die Koordinatensuche werden Ort sowie Straße oder Postleitzahl benötigt.',manual:'Standort mit den manuell angegebenen Koordinaten gespeichert.',disabled:'Standort gespeichert; die automatische Koordinatensuche ist deaktiviert.',unchanged:'Standort wurde gespeichert.'};showToast(messages[result.geocoding?.status]||'Standort wurde gespeichert.');}catch(error){showToast(error.message);}});

function matrixCellMembers(){return[...document.querySelectorAll('#matrix-grid select')].map(select=>{const display=state.displays.find(item=>item.id===select.value),resolution=display?displayEffectiveResolution(display):null;return display?{displayId:display.id,row:Number(select.dataset.row),column:Number(select.dataset.column),orientation:display.orientation,physicalWidth:resolution.width,physicalHeight:resolution.height,scale:1}:null;}).filter(Boolean);}
function renderMatrixGrid(existing=[]){const rows=Number(document.querySelector('#matrix-rows').value),columns=Number(document.querySelector('#matrix-columns').value),grid=document.querySelector('#matrix-grid');grid.style.gridTemplateColumns=`repeat(${columns},minmax(120px,1fr))`;grid.innerHTML=Array.from({length:rows*columns},(_,index)=>{const row=Math.floor(index/columns),column=index%columns,current=existing.find(item=>item.row===row&&item.column===column);return`<label class="matrix-cell" data-row="${row}" data-column="${column}"><small>Zeile ${row+1} · Spalte ${column+1}</small><select data-row="${row}" data-column="${column}"><option value="">Feld frei</option>${state.displays.map(display=>{const resolution=displayEffectiveResolution(display);return`<option value="${escapeHtml(display.id)}" ${current?.displayId===display.id?'selected':''}>${escapeHtml(display.name)} · ${resolution.width}×${resolution.height}${display.resolutionMode==='native'?' responsiv':''}</option>`;}).join('')}</select></label>`;}).join('');grid.querySelectorAll('select').forEach(select=>select.addEventListener('change',()=>{const duplicates=matrixCellMembers().filter((item,index,array)=>array.findIndex(other=>other.displayId===item.displayId)!==index);if(duplicates.length){showToast('Ein Display kann nur ein Matrixfeld belegen.');select.value='';}updateMatrixResolution();}));grid.querySelectorAll('.matrix-cell').forEach(cell=>{cell.addEventListener('dragover',event=>event.preventDefault());cell.addEventListener('drop',event=>{event.preventDefault();cell.querySelector('select').value=event.dataTransfer.getData('application/x-kiosky-display');cell.querySelector('select').dispatchEvent(new Event('change'));});});updateMatrixResolution();}
function updateMatrixResolution(){const members=matrixCellMembers(),rows=Number(document.querySelector('#matrix-rows').value),columns=Number(document.querySelector('#matrix-columns').value),width=Array.from({length:columns},(_,column)=>Math.max(0,...members.filter(item=>item.column===column).map(item=>item.orientation==='portrait'?item.physicalHeight:item.physicalWidth))).reduce((sum,value)=>sum+value,0),height=Array.from({length:rows},(_,row)=>Math.max(0,...members.filter(item=>item.row===row).map(item=>item.orientation==='portrait'?item.physicalWidth:item.physicalHeight))).reduce((sum,value)=>sum+value,0);document.querySelector('#matrix-resolution').textContent=`Gesamtauflösung: ${width} × ${height} px · ${members.length} Displays`;}
function openMatrixDialog(id=null){const matrix=state.matrices.find(item=>item.id===id);document.querySelector('#matrix-form').reset();document.querySelector('#matrix-edit-id').value=matrix?.id||'';document.querySelector('#matrix-dialog-title').textContent=matrix?'Matrix-Display bearbeiten':'Matrix-Display anlegen';document.querySelector('#matrix-name').value=matrix?.name||'';document.querySelector('#matrix-rows').value=matrix?.rows||1;document.querySelector('#matrix-columns').value=matrix?.columns||2;document.querySelector('#matrix-channel').value=matrix?.defaultChannelId||'';document.querySelector('#matrix-status').value=matrix?.status||'active';document.querySelector('#matrix-description').value=matrix?.description||'';document.querySelector('#matrix-display-pool').innerHTML=state.displays.map(display=>`<button type="button" draggable="true" data-matrix-pool="${escapeHtml(display.id)}">${escapeHtml(display.name)}<small>${display.matrix?` · ${escapeHtml(display.matrix.name)}`:''}</small></button>`).join('');document.querySelectorAll('[data-matrix-pool]').forEach(button=>button.addEventListener('dragstart',event=>event.dataTransfer.setData('application/x-kiosky-display',button.dataset.matrixPool)));renderMatrixGrid(matrix?.members||[]);matrixDialog.showModal();}
document.querySelector('[data-action="new-matrix"]').addEventListener('click',()=>openMatrixDialog());['matrix-rows','matrix-columns'].forEach(id=>document.querySelector(`#${id}`).addEventListener('change',()=>renderMatrixGrid(matrixCellMembers())));
document.querySelector('#matrix-form').addEventListener('submit',async event=>{event.preventDefault();const id=document.querySelector('#matrix-edit-id').value,payload={name:document.querySelector('#matrix-name').value.trim(),description:document.querySelector('#matrix-description').value.trim()||undefined,rows:Number(document.querySelector('#matrix-rows').value),columns:Number(document.querySelector('#matrix-columns').value),defaultChannelId:document.querySelector('#matrix-channel').value||undefined,status:document.querySelector('#matrix-status').value,members:matrixCellMembers()};if(!payload.members.length){showToast('Bitte mindestens ein Display in der Matrix platzieren.');return;}try{await apiRequest(id?`/api/matrix-displays/${id}`:'/api/matrix-displays',{method:id?'PUT':'POST',body:JSON.stringify(payload)});matrixDialog.close();await loadDisplays();showToast('Matrix-Display wurde gespeichert.');}catch(error){showToast(error.message);}});

// Display scheduler
const dayKeys=['mo','di','mi','do','fr','sa','so'];
state.scheduleEntries=[];state.scheduleTargetOrder=[];state.scheduleDay=dayKeys[(new Date().getDay()+6)%7];state.scheduleView='day';state.scheduleSaveQueue=Promise.resolve();
function scheduleWeekStart(){const date=new Date();const day=(date.getDay()+6)%7;date.setHours(0,0,0,0);date.setDate(date.getDate()-day+(state.weekOffset||0)*7);return date;}
function scheduleDayDate(key){const date=scheduleWeekStart();date.setDate(date.getDate()+dayKeys.indexOf(key));return date;}
function scheduleWeekRangeLabel(start) {
  const end=new Date(start);end.setDate(end.getDate()+6);
  if(start.getFullYear()!==end.getFullYear())return`${start.toLocaleDateString('de-DE',{day:'numeric',month:'long',year:'numeric'})}–${end.toLocaleDateString('de-DE',{day:'numeric',month:'long',year:'numeric'})}`;
  if(start.getMonth()!==end.getMonth())return`${start.toLocaleDateString('de-DE',{day:'numeric',month:'long'})}–${end.toLocaleDateString('de-DE',{day:'numeric',month:'long',year:'numeric'})}`;
  return`${start.getDate()}.–${end.toLocaleDateString('de-DE',{day:'numeric',month:'long',year:'numeric'})}`;
}
function updateWeekNavigation(){const start=scheduleWeekStart(),trigger=document.querySelector('#schedule-week-picker-trigger');trigger.querySelector('span').textContent=scheduleWeekRangeLabel(start);trigger.setAttribute('aria-label',`${scheduleWeekRangeLabel(start)}. Andere Woche auswählen`);document.querySelectorAll('[data-day]').forEach((button,index)=>{const date=new Date(start);date.setDate(date.getDate()+index);button.querySelector('strong').textContent=date.getDate();button.classList.toggle('is-active',button.dataset.day===state.scheduleDay);});const chosen=scheduleDayDate(state.scheduleDay);document.querySelector('.schedule-canvas-head h3').textContent=state.scheduleView==='week'?`Woche ${scheduleIsoWeekNumber(start)} · ${scheduleWeekRangeLabel(start)}`:chosen.toLocaleDateString('de-DE',{weekday:'long',day:'2-digit',month:'long'});if(document.querySelector('#schedule-week-picker')?.matches(':popover-open'))renderScheduleWeekPicker();}

let scheduleCalendarCursor=null;
function scheduleLocalDayNumber(date){return Date.UTC(date.getFullYear(),date.getMonth(),date.getDate())/86400000;}
function scheduleIsoWeekNumber(date){
  const target=new Date(Date.UTC(date.getFullYear(),date.getMonth(),date.getDate()));
  target.setUTCDate(target.getUTCDate()+4-(target.getUTCDay()||7));
  const yearStart=new Date(Date.UTC(target.getUTCFullYear(),0,1));
  return Math.ceil((((target-yearStart)/86400000)+1)/7);
}
function scheduleBaseWeekStart(){
  const date=new Date(),day=(date.getDay()+6)%7;date.setHours(0,0,0,0);date.setDate(date.getDate()-day);return date;
}
function renderScheduleWeekPicker(){
  const selected=scheduleWeekStart();
  if(!scheduleCalendarCursor)scheduleCalendarCursor=new Date(selected.getFullYear(),selected.getMonth(),1);
  const cursor=new Date(scheduleCalendarCursor.getFullYear(),scheduleCalendarCursor.getMonth(),1);
  document.querySelector('#schedule-calendar-title').textContent=cursor.toLocaleDateString('de-DE',{month:'long',year:'numeric'});
  const gridStart=new Date(cursor),weekday=(gridStart.getDay()+6)%7;gridStart.setDate(gridStart.getDate()-weekday);
  const today=new Date(),selectedDay=scheduleLocalDayNumber(selected);
  const weeks=Array.from({length:6},(_,weekIndex)=>{
    const weekStart=new Date(gridStart);weekStart.setDate(weekStart.getDate()+weekIndex*7);
    const days=Array.from({length:7},(_,dayIndex)=>{
      const date=new Date(weekStart);date.setDate(date.getDate()+dayIndex);
      const classes=[date.getMonth()!==cursor.getMonth()?'is-outside-month':'',scheduleLocalDayNumber(date)===scheduleLocalDayNumber(today)?'is-today':''].filter(Boolean).join(' ');
      return`<span class="${classes}">${date.getDate()}</span>`;
    }).join('');
    const selectedClass=scheduleLocalDayNumber(weekStart)===selectedDay?' is-selected':'';
    return`<button class="schedule-calendar-week${selectedClass}" type="button" data-calendar-week="${dateInputValue(weekStart)}" aria-label="${escapeHtml(scheduleWeekRangeLabel(weekStart))} auswählen"><span class="calendar-week-number">${scheduleIsoWeekNumber(weekStart)}</span>${days}</button>`;
  }).join('');
  const container=document.querySelector('#schedule-calendar-weeks');container.innerHTML=weeks;
  container.querySelectorAll('[data-calendar-week]').forEach(button=>button.addEventListener('click',()=>{
    const weekStart=new Date(`${button.dataset.calendarWeek}T00:00:00`);
    state.weekOffset=Math.round((scheduleLocalDayNumber(weekStart)-scheduleLocalDayNumber(scheduleBaseWeekStart()))/7);
    updateWeekNavigation();renderSchedule();
    document.querySelector('#schedule-week-picker').hidePopover();
  }));
}
function positionScheduleWeekPicker(){
  const trigger=document.querySelector('#schedule-week-picker-trigger'),picker=document.querySelector('#schedule-week-picker');
  if(!picker.matches(':popover-open'))return;
  const rect=trigger.getBoundingClientRect(),gap=8;
  const left=Math.max(12,Math.min(window.innerWidth-picker.offsetWidth-12,rect.left+(rect.width-picker.offsetWidth)/2));
  const preferredTop=rect.bottom+gap,top=preferredTop+picker.offsetHeight<=window.innerHeight-12?preferredTop:Math.max(12,rect.top-picker.offsetHeight-gap);
  picker.style.left=`${left}px`;picker.style.top=`${top}px`;
}
async function loadScheduling(){try{if(!state.displays.length)await loadDisplays();if(!state.channels.length)await loadContent();const [result,order]=await Promise.all([apiRequest('/api/schedules'),apiRequest('/api/schedule-target-order')]);state.scheduleEntries=result.data||[];state.scheduleTargetOrder=order.data||[];renderScheduleContents();updateWeekNavigation();renderSchedule();}catch(error){setScheduleSaveStatus('error');showToast(error.message);}}
function renderScheduleContents(){const list=document.querySelector('#schedule-content-list'),query=document.querySelector('#schedule-content-search').value.trim().toLowerCase(),showChannels=document.querySelector('#schedule-show-channels').checked,showSlides=document.querySelector('#schedule-show-slides').checked;const channels=showChannels?state.channels.filter(item=>!query||`${item.name} ${item.description||''}`.toLowerCase().includes(query)).map(channel=>`<button draggable="true" data-schedule-content="channel:${escapeHtml(channel.id)}" data-content-type="${channel.eventId?'event':'default'}"><i class="content-swatch ${channel.eventId?'coral':'blue'}"></i><span><strong>${escapeHtml(channel.name)}</strong><small>${channel.eventId?'Eventkanal':'Kanal'} · ${channel.items.length} Slides</small></span><b>⠿</b></button>`):[];const slides=showSlides?state.slides.filter(item=>!query||`${item.title} ${item.meta}`.toLowerCase().includes(query)).map(slide=>`<button draggable="true" data-schedule-content="slide:${escapeHtml(slide.id)}" data-content-type="slide"><i class="content-swatch green"></i><span><strong>${escapeHtml(slide.title)}</strong><small>Einzelne Slide · ${escapeHtml(slideTypeLabels[slide.type]||slide.type)}</small></span><b>⠿</b></button>`):[];list.innerHTML=[...channels,...slides].join('')||'<p class="property-note">Keine passenden Kanäle oder Slides gefunden.</p>';list.querySelectorAll('[data-schedule-content]').forEach(button=>{button.classList.toggle('is-selected',button.dataset.scheduleContent===state.selectedScheduleContent);button.addEventListener('click',()=>{state.selectedScheduleContent=button.dataset.scheduleContent;renderScheduleContents();document.querySelector('#schedule-drag-hint').textContent=`„${button.querySelector('strong').textContent}“ gewählt – jetzt ${state.scheduleView==='week'?'einen Tag':'eine Uhrzeit'} anklicken.`;});button.addEventListener('dragstart',event=>{state.selectedScheduleContent=button.dataset.scheduleContent;event.dataTransfer.setData('application/x-kiosky-schedule',button.dataset.scheduleContent);button.classList.add('dragging');});button.addEventListener('dragend',()=>button.classList.remove('dragging'));});}
['schedule-show-channels','schedule-show-slides'].forEach(id=>document.querySelector(`#${id}`).addEventListener('change',renderScheduleContents));document.querySelector('#schedule-content-search').addEventListener('input',renderScheduleContents);

function scheduleTargetCatalog() {
  const catalog = {};
  state.displays.forEach(display => { catalog[`display:${display.id}`] = { label: display.name, count: 1, displayIds: [display.id], kind: 'Einzelnes Display', orientation: display.orientation }; });
  state.groups.forEach(group => { catalog[group.id] = { label: group.name, count: group.displayIds.length, displayIds: group.displayIds, kind: 'Gruppe' }; });
  state.matrices.forEach(matrix=>{catalog[`matrix:${matrix.id}`]={label:matrix.name,count:matrix.members.length,displayIds:matrix.members.map(member=>member.displayId),kind:'Matrix-Display'};});
  return catalog;
}

function orderedScheduleTargets() {
  const catalog=scheduleTargetCatalog(),known=state.scheduleTargetOrder.filter(key=>catalog[key]);
  return [...known,...Object.keys(catalog).filter(key=>!known.includes(key))];
}

function setScheduleSaveStatus(status) {
  const labels={saving:'Wird gespeichert und veröffentlicht …',saved:'Veröffentlicht',error:'Speichern oder Veröffentlichen fehlgeschlagen'};
  for(const element of [document.querySelector('#schedule-save-status'),document.querySelector('#schedule-dialog-save-status')]){
    if(!element)continue;element.classList.toggle('is-saving',status==='saving');element.classList.toggle('is-error',status==='error');element.querySelector('span:last-child').textContent=labels[status]||labels.saved;
  }
}

function scheduleTargetValue(entry) {
  if (entry.targetType === 'display') return `display:${entry.targetId}`;
  if (entry.targetType === 'matrix') return `matrix:${entry.targetId}`;
  if (entry.targetType === 'group') return entry.targetId;
  return '';
}

function scheduleTargetPayload(target) {
  if (target.startsWith('display:')) return { targetType:'display',targetId:target.slice(8) };
  if (target.startsWith('matrix:')) return { targetType:'matrix',targetId:target.slice(7) };
  return { targetType:'group',targetId:target };
}

function schedulePayload(entry, overrides={}) {
  return {
    targetType:entry.targetType,targetId:entry.targetId,contentType:entry.contentType||'channel',contentId:entry.contentId||entry.channelId,
    startsAt:entry.startsAt,endsAt:entry.endsAt,recurrence:entry.recurrence,
    recurrenceStartsAt:entry.recurrenceStartsAt,recurrenceEndsAt:entry.recurrenceEndsAt,recurrenceInterval:entry.recurrenceInterval,recurrenceWeekdays:entry.recurrenceWeekdays,
    priority:entry.priority,stackOrder:entry.stackOrder,...overrides,status:'published',active:true
  };
}

function scheduleDayBlocks() {
  const dayStart=scheduleDayDate(state.scheduleDay),dayEnd=new Date(dayStart);dayEnd.setDate(dayEnd.getDate()+1);
  const groups=new Map();
  state.scheduleEntries.filter(entry=>new Date(entry.startsAt)<dayEnd&&new Date(entry.endsAt)>dayStart).forEach(entry=>{
    const key=[entry.contentType,entry.contentId||entry.channelId,entry.startsAt,entry.endsAt,entry.recurrence,entry.priority,entry.status].join('|');
    if(!groups.has(key))groups.set(key,{...entry,entries:[]});
    groups.get(key).entries.push(entry);
  });
  return [...groups.values()].sort((a,b)=>new Date(a.startsAt)-new Date(b.startsAt));
}

function scheduleWeekBlocks() {
  const weekStart=scheduleWeekStart(),weekEnd=new Date(weekStart);weekEnd.setDate(weekEnd.getDate()+7);
  const groups=new Map();
  state.scheduleEntries.filter(entry=>new Date(entry.startsAt)<weekEnd&&new Date(entry.endsAt)>weekStart).forEach(entry=>{
    const key=[entry.contentType,entry.contentId||entry.channelId,entry.startsAt,entry.endsAt,entry.recurrence,entry.priority,entry.status].join('|');
    if(!groups.has(key))groups.set(key,{...entry,entries:[]});
    groups.get(key).entries.push(entry);
  });
  return [...groups.values()].sort((a,b)=>new Date(a.startsAt)-new Date(b.startsAt));
}

function timeInputValue(date) {
  return `${String(date.getHours()).padStart(2,'0')}:${String(date.getMinutes()).padStart(2,'0')}`;
}

function dateInputValue(date) {
  return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
}

function scheduleDateTimeInputValue(dateId,timeId) {
  const dateValue=document.querySelector(`#${dateId}`).value,timeValue=document.querySelector(`#${timeId}`).value;
  return dateValue&&timeValue?new Date(`${dateValue}T${timeValue}`):new Date(NaN);
}

function minuteOfScheduleDay(date) {
  const day=scheduleDayDate(state.scheduleDay);
  return Math.round((date-day)/60000);
}

function scheduleDateAtMinute(minute) {
  const date=scheduleDayDate(state.scheduleDay);
  date.setMinutes(minute,0,0);
  return date;
}

function scheduleBlockWindow(block,startOffset=0,endOffset=startOffset) {
  const rawStart=minuteOfScheduleDay(new Date(block.startsAt))+startOffset,rawEnd=minuteOfScheduleDay(new Date(block.endsAt))+endOffset;
  const visibleStart=Math.max(0,Math.min(1440,rawStart)),visibleEnd=Math.max(0,Math.min(1440,rawEnd));
  return{rawStart,rawEnd,visibleStart,visibleEnd};
}

function scheduleTimeRangeLabel(startsAt,endsAt) {
  const start=new Date(startsAt),end=new Date(endsAt),startDay=new Date(start);startDay.setHours(0,0,0,0);
  const endDay=new Date(end);endDay.setHours(0,0,0,0);
  const dayDifference=Math.round((endDay-startDay)/86400000);
  const dayLabel=dayDifference===1?' (+1 Tag)':dayDifference===-1?' (−1 Tag)':dayDifference?` (${dayDifference>0?'+':''}${dayDifference} Tage)`:'';
  return`${timeInputValue(start)}–${timeInputValue(end)}${dayLabel}`;
}

function linkedScheduleElements(canvas,index) {
  return[...canvas.querySelectorAll(`[data-block-index="${index}"]`)];
}

function markLinkedScheduleElements(canvas,index,active,moving=false) {
  linkedScheduleElements(canvas,index).forEach(element=>{
    element.classList.toggle('is-linked',active);
    element.classList.toggle('is-moving',active&&moving);
    element.closest('.schedule-lane')?.classList.toggle('has-linked-assignment',active);
  });
}

function previewScheduleBlockElements(canvas,index,block,startOffset=0,endOffset=startOffset) {
  const window=scheduleBlockWindow(block,startOffset,endOffset);
  const startsAt=scheduleDateAtMinute(window.rawStart),endsAt=scheduleDateAtMinute(window.rawEnd);
  linkedScheduleElements(canvas,index).forEach(element=>{
    element.style.left=`${window.visibleStart/14.4}%`;
    element.style.width=`${Math.max(1,window.visibleEnd-window.visibleStart)/14.4}%`;
    element.querySelector('small').textContent=scheduleTimeRangeLabel(startsAt,endsAt);
  });
  return{...window,startsAt,endsAt};
}

function scheduleAssignmentConflicts(assignments,target) {
  return state.scheduleEntries.filter(entry=>{
    if(scheduleTargetValue(entry)!==target)return false;
    const entryStart=new Date(entry.startsAt),entryEnd=new Date(entry.endsAt);
    return assignments.some(({start,end})=>entryStart<end&&entryEnd>start);
  });
}

function chooseScheduleConflictAction(conflicts,target) {
  const dialog=document.querySelector('#schedule-conflict-dialog'),targetLabel=scheduleTargetCatalog()[target]?.label||'dieses Ziel';
  document.querySelector('#schedule-conflict-summary').textContent=`Auf „${targetLabel}“ ${conflicts.length===1?'überschneidet sich bereits ein Eintrag':'überschneiden sich bereits mehrere Einträge'} mit dem neuen Zeitraum. Soll der vorhandene Inhalt ersetzt oder der neue zusätzlich eingeplant werden?`;
  dialog.returnValue='cancel';
  dialog.showModal();
  return new Promise(resolve=>dialog.addEventListener('close',()=>resolve(dialog.returnValue||'cancel'),{once:true}));
}

async function assignScheduleContent(startMinute,target,options={}) {
  if (!state.selectedScheduleContent) {
    showToast('Bitte zuerst einen Kanal oder eine Slide auswählen.');
    return;
  }
  const priority = document.querySelector('#schedule-priority').value;
  const duration = document.querySelector('#schedule-duration').value;
  const recurrence = document.querySelector('#schedule-recurrence').value;
  const assignmentDay=options.day||state.scheduleDay,[contentType,contentId]=state.selectedScheduleContent.split(':'),days=recurrence==='daily'?dayKeys:[assignmentDay];
  const assignments=days.map(day=>{const start=scheduleDayDate(day);start.setMinutes(options.fullDay?0:Math.max(0,Math.min(1439,startMinute)),0,0);const end=new Date(start);if(options.fullDay)end.setDate(end.getDate()+1);else if(duration==='midnight')end.setHours(24,0,0,0);else end.setMinutes(end.getMinutes()+(duration==='event'?120:Number(duration)*60));return{day,start,end};});
  const conflicts=state.scheduleView==='week'&&options.fullDay?scheduleAssignmentConflicts(assignments,target):[];
  const conflictAction=conflicts.length?await chooseScheduleConflictAction(conflicts,target):'add';
  if(conflictAction==='cancel')return;
  let assigned=0;setScheduleSaveStatus('saving');
  try{
    for(const {start,end} of assignments){await apiRequest('/api/schedules',{method:'POST',body:JSON.stringify({...scheduleTargetPayload(target),contentType,contentId,startsAt:start.toISOString(),endsAt:end.toISOString(),recurrence,recurrenceStartsAt:start.toISOString(),priority,status:'published'})});assigned+=1;}
    if(conflictAction==='replace')await Promise.all([...new Map(conflicts.map(entry=>[entry.id,entry])).values()].map(entry=>apiRequest(`/api/schedules/${entry.id}`,{method:'DELETE'})));
    await loadScheduling();setScheduleSaveStatus('saved');
  }catch(error){setScheduleSaveStatus('error');showToast(error.message);return;}
  const recurrenceLabel = recurrence === 'daily' ? ' täglich' : recurrence === 'weekly' ? ' wöchentlich' : '';
  const conflictLabel=conflictAction==='replace'?' · vorhandenen Inhalt ersetzt':conflicts.length?' · zusätzlich hinzugefügt':'';
  showToast(`${assigned} Zuweisung${assigned===1?'':'en'} wurde${assigned===1?'':'n'} automatisch gespeichert und veröffentlicht${options.fullDay?' · 00:00–24:00 Uhr':''}${recurrenceLabel}${conflictLabel}.`);
}

async function deleteScheduleBlock(block,ask=false) {
  if(ask&&!confirm(`„${block.contentName||block.channelName}“ aus dem Zeitplan entfernen?`))return;
  setScheduleSaveStatus('saving');try{await Promise.all(block.entries.map(entry=>apiRequest(`/api/schedules/${entry.id}`,{method:'DELETE'})));const dialog=document.querySelector('#schedule-entry-dialog');if(dialog.open)dialog.close();await loadScheduling();setScheduleSaveStatus('saved');showToast('Zeitplan-Eintrag wurde entfernt.');}catch(error){setScheduleSaveStatus('error');showToast(error.message);}
}

function openScheduleEntryDialog(block) {
  const catalog=scheduleTargetCatalog(),targets=new Set(block.entries.map(scheduleTargetValue).filter(Boolean));
  document.querySelector('#schedule-entry-ids').value=block.entries.map(entry=>entry.id).join(',');
  document.querySelector('#schedule-entry-title').textContent=block.contentName||block.channelName;
  document.querySelector('#schedule-entry-content').value=block.contentName||block.channelName;
  const start=new Date(block.startsAt),end=new Date(block.endsAt);
  document.querySelector('#schedule-entry-start-date').value=dateInputValue(start);
  document.querySelector('#schedule-entry-start-time').value=timeInputValue(start);
  document.querySelector('#schedule-entry-end-date').value=dateInputValue(end);
  document.querySelector('#schedule-entry-end-time').value=timeInputValue(end);
  document.querySelector('#schedule-entry-priority').value=block.priority;
  document.querySelector('#schedule-entry-recurrence').value=block.recurrence;
  document.querySelector('#schedule-entry-targets').innerHTML=orderedScheduleTargets().map(id=>{const target=catalog[id];return`<label><input type="checkbox" value="${escapeHtml(id)}" ${targets.has(id)?'checked':''}><span><strong>${escapeHtml(target.label)}</strong><small>${escapeHtml(target.kind)} · ${target.count} Display${target.count===1?'':'s'}</small></span></label>`;}).join('')||'<p class="property-note">Noch keine Displays oder Gruppen vorhanden.</p>';
  setScheduleSaveStatus('saved');
  document.querySelector('#schedule-entry-dialog').showModal();
}

async function saveScheduleBlockTimes(block,startsAt,endsAt) {
  setScheduleSaveStatus('saving');try{await Promise.all(block.entries.map(entry=>apiRequest(`/api/schedules/${entry.id}`,{method:'PUT',body:JSON.stringify(schedulePayload(entry,{startsAt,endsAt}))})));await loadScheduling();setScheduleSaveStatus('saved');}catch(error){setScheduleSaveStatus('error');showToast(error.message);await loadScheduling();}
}

function bindScheduleResize(handle,block,edge,index) {
  handle.addEventListener('pointerdown',event=>{
    event.preventDefault();event.stopPropagation();handle.setPointerCapture(event.pointerId);
    const element=handle.closest('.schedule-block'),track=element.closest('.schedule-lane-track'),canvas=element.closest('#visual-schedule'),rect=track.getBoundingClientRect();
    const originalStart=minuteOfScheduleDay(new Date(block.startsAt)),originalEnd=minuteOfScheduleDay(new Date(block.endsAt));
    markLinkedScheduleElements(canvas,index,true);
    const move=moveEvent=>{
      const minute=Math.max(-1440,Math.min(2880,Math.round((moveEvent.clientX-rect.left)/rect.width*1440)));
      const start=edge==='start'?Math.min(minute,originalEnd-1):originalStart;
      const end=edge==='end'?Math.max(minute,originalStart+1):originalEnd;
      const preview=previewScheduleBlockElements(canvas,index,block,start-originalStart,end-originalEnd);
      element.dataset.pendingStart=String(preview.rawStart);element.dataset.pendingEnd=String(preview.rawEnd);
    };
    let finished=false;
    const cleanup=()=>{handle.removeEventListener('pointermove',move);handle.removeEventListener('pointerup',up);handle.removeEventListener('pointercancel',cancel);handle.removeEventListener('lostpointercapture',lost);};
    const up=async()=>{
      finished=true;cleanup();
      const startMinute=Number(element.dataset.pendingStart??originalStart),endMinute=Number(element.dataset.pendingEnd??originalEnd);
      delete element.dataset.pendingStart;delete element.dataset.pendingEnd;
      const start=scheduleDateAtMinute(startMinute),end=scheduleDateAtMinute(endMinute);
      await saveScheduleBlockTimes(block,start.toISOString(),end.toISOString());
    };
    const cancel=()=>{finished=true;cleanup();renderSchedule();};
    const lost=()=>{if(!finished){cleanup();renderSchedule();}};
    handle.addEventListener('pointermove',move);handle.addEventListener('pointerup',up);handle.addEventListener('pointercancel',cancel);handle.addEventListener('lostpointercapture',lost);
  });
}

function bindScheduleMove(element,block,index) {
  let pointerActive=false;
  element.addEventListener('pointerdown',event=>{
    if(event.button!==0||event.target.closest('.schedule-resize-handle,.schedule-block-delete'))return;
    event.preventDefault();pointerActive=true;
    const canvas=element.closest('#visual-schedule'),track=element.closest('.schedule-lane-track'),rect=track.getBoundingClientRect(),sourceTarget=track.dataset.target;
    const pointerStart=event.clientX;let moved=false,offset=0,lastX=event.clientX,lastY=event.clientY;
    element.setPointerCapture(event.pointerId);
    markLinkedScheduleElements(canvas,index,true);
    const move=moveEvent=>{
      lastX=moveEvent.clientX;lastY=moveEvent.clientY;const pixels=moveEvent.clientX-pointerStart;
      if(!moved&&Math.abs(pixels)<4)return;
      moved=true;moveEvent.preventDefault();
      offset=Math.round(pixels/rect.width*1440);
      previewScheduleBlockElements(canvas,index,block,offset);
      markLinkedScheduleElements(canvas,index,true,true);
    };
    let finished=false;
    const cleanup=()=>{element.removeEventListener('pointermove',move);element.removeEventListener('pointerup',up);element.removeEventListener('pointercancel',cancel);element.removeEventListener('lostpointercapture',lost);};
    const finish=async eventType=>{
      finished=true;cleanup();pointerActive=false;
      if(eventType==='cancel'){renderSchedule();return;}
      if(!moved){markLinkedScheduleElements(canvas,index,false);openScheduleEntryDialog(block);return;}
      const start=new Date(block.startsAt),end=new Date(block.endsAt);start.setMinutes(start.getMinutes()+offset);end.setMinutes(end.getMinutes()+offset);
      const destination=document.elementFromPoint(lastX,lastY)?.closest('.schedule-lane-track')?.dataset.target;
      if(destination&&destination!==sourceTarget){
        const sourceEntry=block.entries.find(entry=>scheduleTargetValue(entry)===sourceTarget);if(!sourceEntry)return renderSchedule();
        setScheduleSaveStatus('saving');try{await apiRequest(`/api/schedules/${sourceEntry.id}`,{method:'PUT',body:JSON.stringify(schedulePayload(sourceEntry,{...scheduleTargetPayload(destination),startsAt:start.toISOString(),endsAt:end.toISOString(),stackOrder:Date.now()}))});await loadScheduling();setScheduleSaveStatus('saved');showToast('Zeitplaneintrag wurde in die andere Zielzeile verschoben.');}catch(error){setScheduleSaveStatus('error');showToast(error.message);await loadScheduling();}
      }else await saveScheduleBlockTimes(block,start.toISOString(),end.toISOString());
    };
    const up=()=>finish('up');
    const cancel=()=>finish('cancel');
    const lost=()=>{if(!finished){pointerActive=false;cleanup();renderSchedule();}};
    element.addEventListener('pointermove',move);element.addEventListener('pointerup',up);element.addEventListener('pointercancel',cancel);element.addEventListener('lostpointercapture',lost);
  });
  element.addEventListener('mouseenter',()=>markLinkedScheduleElements(element.closest('#visual-schedule'),index,true));
  element.addEventListener('mouseleave',()=>{if(!pointerActive)markLinkedScheduleElements(element.closest('#visual-schedule'),index,false);});
  element.addEventListener('keydown',event=>{if(event.target===element&&['Enter',' '].includes(event.key)){event.preventDefault();openScheduleEntryDialog(block);}});
}

function scheduleBlockType(block) {
  return block.contentType==='slide'?'preset':state.channels.find(channel=>channel.id===block.channelId)?.eventId?'event':'default';
}

function scheduleBlockStackOrder(block,target) {
  return Math.max(0,...block.entries.filter(entry=>scheduleTargetValue(entry)===target).map(entry=>Number(entry.stackOrder||0)));
}

function scheduleTargetIcon(item) {
  return item.kind==='Gruppe'||item.kind==='Matrix-Display'?'▦':item.orientation==='portrait'?'▯':'▣';
}

function renderScheduleEntryList(blocks,title) {
  const assignedBlocks=blocks.filter(block=>block.entries.some(entry=>entry.targetType!=='unassigned')),entryList=document.querySelector('#schedule-entry-list');
  entryList.innerHTML=assignedBlocks.length?`<h4>${title}</h4>${assignedBlocks.map(block=>`<div><time>${scheduleTimeRangeLabel(block.startsAt,block.endsAt)}</time><strong>${escapeHtml(block.contentName||block.channelName)}</strong><span>${block.entries.filter(entry=>entry.targetType!=='unassigned').length} Ziel${block.entries.filter(entry=>entry.targetType!=='unassigned').length===1?'':'e'} · ${block.status==='published'?'veröffentlicht':'Entwurf'}${block.entries.length>1?' · Überlappungen werden gestapelt':''}</span></div>`).join('')}`:'';
}

function scheduleWeekCellTimeLabel(block,dayStart) {
  const dayEnd=new Date(dayStart);dayEnd.setDate(dayEnd.getDate()+1);
  const startsAt=new Date(block.startsAt),endsAt=new Date(block.endsAt);
  if(startsAt<=dayStart&&endsAt>=dayEnd)return'00:00–24:00 Uhr';
  const visibleStart=startsAt<dayStart?dayStart:startsAt,visibleEnd=endsAt>dayEnd?dayEnd:endsAt;
  return`${timeInputValue(visibleStart)}–${visibleEnd.getTime()===dayEnd.getTime()?'24:00':timeInputValue(visibleEnd)} Uhr`;
}

function renderScheduleWeek(canvas,catalog,targets,readOnly) {
  const blocks=scheduleWeekBlocks(),weekStart=scheduleWeekStart();
  canvas.className='visual-schedule is-week-view';
  canvas.innerHTML=`<div class="schedule-week-columns"><div class="schedule-week-corner"></div>${dayKeys.map((day,index)=>{const date=new Date(weekStart);date.setDate(date.getDate()+index);return`<div class="schedule-week-day"><span>${date.toLocaleDateString('de-DE',{weekday:'short'})}</span><small>${date.toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit'})}</small></div>`;}).join('')}</div>`;
  const cardMarkup=(block,index,dayStart)=>{const recurrenceLabel=block.recurrence==='daily'?' · täglich':block.recurrence==='weekly'?' · wöchentlich':'',name=block.contentName||block.channelName,deleteButton=readOnly?'':`<button class="schedule-block-delete" type="button" aria-label="${escapeHtml(name)} löschen">×</button>`;return`<article class="schedule-week-card ${scheduleBlockType(block)} priority-${escapeHtml(block.priority)}" data-week-block-index="${index}" tabindex="0" role="button" aria-label="${escapeHtml(name)} bearbeiten"><strong>${escapeHtml(name)}</strong><small>${scheduleWeekCellTimeLabel(block,dayStart)}${recurrenceLabel}</small>${deleteButton}</article>`;};
  canvas.insertAdjacentHTML('beforeend',targets.map(target=>{const item=catalog[target];return`<div class="schedule-lane schedule-week-lane" data-schedule-row="${escapeHtml(target)}"><div class="schedule-lane-label" draggable="${readOnly?'false':'true'}" data-schedule-row-handle="${escapeHtml(target)}" title="Ziehen, um die Reihenfolge zu ändern"><span class="schedule-row-grip">⠿</span><span class="target-icon">${scheduleTargetIcon(item)}</span><span><strong>${escapeHtml(item.label)}</strong><small>${escapeHtml(item.kind)}</small></span></div>${dayKeys.map((day,index)=>{const dayStart=new Date(weekStart);dayStart.setDate(dayStart.getDate()+index);const dayEnd=new Date(dayStart);dayEnd.setDate(dayEnd.getDate()+1);const dayBlocks=blocks.map((block,blockIndex)=>({block,blockIndex})).filter(({block})=>new Date(block.startsAt)<dayEnd&&new Date(block.endsAt)>dayStart&&block.entries.some(entry=>scheduleTargetValue(entry)===target));return`<div class="schedule-week-day-cell" data-target="${escapeHtml(target)}" data-week-day="${day}" aria-label="${dayStart.toLocaleDateString('de-DE',{weekday:'long',day:'numeric',month:'long'})} · ${escapeHtml(item.label)}">${dayBlocks.length?dayBlocks.map(({block,blockIndex})=>cardMarkup(block,blockIndex,dayStart)).join(''):'<span class="schedule-week-empty">Ganztägig zuweisen</span>'}</div>`;}).join('')}</div>`;}).join('')||'<div class="schedule-empty-targets">Bitte zuerst ein Display, eine Displaygruppe oder ein Matrix-Display anlegen.</div>');
  if(!readOnly)canvas.querySelectorAll('.schedule-week-day-cell').forEach(cell=>{
    cell.addEventListener('dragover',event=>{if(event.dataTransfer.types.includes('application/x-kiosky-schedule-row'))return;event.preventDefault();cell.classList.add('drag-over');});
    cell.addEventListener('dragleave',()=>cell.classList.remove('drag-over'));
    cell.addEventListener('drop',event=>{const content=event.dataTransfer.getData('application/x-kiosky-schedule');if(!content)return;event.preventDefault();event.stopPropagation();cell.classList.remove('drag-over');state.selectedScheduleContent=content||state.selectedScheduleContent;assignScheduleContent(0,cell.dataset.target,{day:cell.dataset.weekDay,fullDay:true});});
    cell.addEventListener('click',event=>{if(event.target.closest('.schedule-week-card')||!state.selectedScheduleContent)return;assignScheduleContent(0,cell.dataset.target,{day:cell.dataset.weekDay,fullDay:true});});
  });
  if(!readOnly)canvas.querySelectorAll('[data-week-block-index]').forEach(element=>{const block=blocks[Number(element.dataset.weekBlockIndex)];element.querySelector('.schedule-block-delete')?.addEventListener('click',event=>{event.stopPropagation();deleteScheduleBlock(block);});element.addEventListener('click',event=>{if(!event.target.closest('.schedule-block-delete'))openScheduleEntryDialog(block);});element.addEventListener('keydown',event=>{if(['Enter',' '].includes(event.key)){event.preventDefault();openScheduleEntryDialog(block);}});});
  if(!readOnly)bindScheduleRowSorting(canvas,targets);
  renderScheduleEntryList(blocks,'Wochen-Timetable');
}

function renderSchedule() {
  const canvas=document.querySelector('#visual-schedule');if(!canvas)return;
  const catalog=scheduleTargetCatalog(),targets=orderedScheduleTargets(),readOnly=state.currentUser?.role==='viewer';
  if(state.scheduleView==='week'){renderScheduleWeek(canvas,catalog,targets,readOnly);return;}
  const blocks=scheduleDayBlocks();
  canvas.className='visual-schedule';
  canvas.innerHTML=`<div class="schedule-hours">${Array.from({length:24},(_,hour)=>`<div class="schedule-hour">${String(hour).padStart(2,'0')}:00</div>`).join('')}</div>`;
  const blockMarkup=(block,index,stackLevel)=>{const window=scheduleBlockWindow(block),recurrenceLabel=block.recurrence==='daily'?' · täglich':block.recurrence==='weekly'?' · wöchentlich':'',name=block.contentName||block.channelName,deleteButton=readOnly?'':`<button class="schedule-block-delete" type="button" aria-label="${escapeHtml(name)} löschen">×</button>`;return`<div class="schedule-block ${scheduleBlockType(block)} priority-${escapeHtml(block.priority)}" data-block-index="${index}" data-stack-level="${stackLevel}" tabindex="0" role="button" style="left:${window.visibleStart/14.4}%;width:${Math.max(1,window.visibleEnd-window.visibleStart)/14.4}%;z-index:${100+stackLevel}" aria-label="${escapeHtml(name)} bearbeiten und verschieben"><button class="schedule-resize-handle start" type="button" aria-label="Startzeit ändern"></button><strong>${escapeHtml(name)}</strong><small>${scheduleTimeRangeLabel(block.startsAt,block.endsAt)}${recurrenceLabel}</small>${deleteButton}<button class="schedule-resize-handle end" type="button" aria-label="Endzeit ändern"></button></div>`;};
  canvas.insertAdjacentHTML('beforeend',targets.map(target=>{const item=catalog[target],targetBlocks=blocks.map((block,index)=>({block,index,stackOrder:scheduleBlockStackOrder(block,target)})).filter(({block})=>block.entries.some(entry=>scheduleTargetValue(entry)===target)).sort((a,b)=>a.stackOrder-b.stackOrder);return`<div class="schedule-lane" data-schedule-row="${escapeHtml(target)}"><div class="schedule-lane-label" draggable="true" data-schedule-row-handle="${escapeHtml(target)}" title="Ziehen, um die Reihenfolge zu ändern"><span class="schedule-row-grip">⠿</span><span class="target-icon">${scheduleTargetIcon(item)}</span><span><strong>${escapeHtml(item.label)}</strong><small>${escapeHtml(item.kind)}</small></span></div><div class="schedule-lane-track" data-target="${escapeHtml(target)}">${targetBlocks.map(({block,index},stackLevel)=>blockMarkup(block,index,stackLevel)).join('')}</div></div>`;}).join('')||'<div class="schedule-empty-targets">Bitte zuerst ein Display, eine Displaygruppe oder ein Matrix-Display anlegen.</div>');
  if(!readOnly)canvas.querySelectorAll('.schedule-lane-track').forEach(track=>{
    track.addEventListener('dragover',event=>{event.preventDefault();track.classList.add('drag-over');});
    track.addEventListener('dragleave',()=>track.classList.remove('drag-over'));
    track.addEventListener('drop',event=>{const content=event.dataTransfer.getData('application/x-kiosky-schedule');if(!content)return;event.preventDefault();event.stopPropagation();track.classList.remove('drag-over');state.selectedScheduleContent=content||state.selectedScheduleContent;const rect=track.getBoundingClientRect();assignScheduleContent(Math.round((event.clientX-rect.left)/rect.width*1440),track.dataset.target);});
    track.addEventListener('click',event=>{if(event.target!==track||!state.selectedScheduleContent)return;const rect=track.getBoundingClientRect(),minute=Math.round((event.clientX-rect.left)/rect.width*1440);assignScheduleContent(minute,track.dataset.target);});
  });
  if(!readOnly)canvas.querySelectorAll('[data-block-index]').forEach(element=>{const index=Number(element.dataset.blockIndex),block=blocks[index];element.querySelector('.schedule-block-delete')?.addEventListener('click',event=>{event.stopPropagation();deleteScheduleBlock(block);});bindScheduleResize(element.querySelector('.schedule-resize-handle.start'),block,'start',index);bindScheduleResize(element.querySelector('.schedule-resize-handle.end'),block,'end',index);bindScheduleMove(element,block,index);});
  if(!readOnly)bindScheduleRowSorting(canvas,targets);
  renderScheduleEntryList(blocks,'Tages-Timetable');
}


function bindScheduleRowSorting(canvas,targets) {
  canvas.querySelectorAll('[data-schedule-row-handle]').forEach(handle=>{handle.addEventListener('dragstart',event=>{event.dataTransfer.setData('application/x-kiosky-schedule-row',handle.dataset.scheduleRowHandle);handle.closest('.schedule-lane').classList.add('is-sorting');});handle.addEventListener('dragend',()=>canvas.querySelectorAll('.schedule-lane').forEach(lane=>lane.classList.remove('is-sorting','sort-before','sort-after')));});
  canvas.querySelectorAll('[data-schedule-row]').forEach(lane=>{lane.addEventListener('dragover',event=>{if(!event.dataTransfer.types.includes('application/x-kiosky-schedule-row'))return;event.preventDefault();const after=event.clientY>lane.getBoundingClientRect().top+lane.getBoundingClientRect().height/2;lane.classList.toggle('sort-before',!after);lane.classList.toggle('sort-after',after);});lane.addEventListener('dragleave',()=>lane.classList.remove('sort-before','sort-after'));lane.addEventListener('drop',event=>{const moving=event.dataTransfer.getData('application/x-kiosky-schedule-row');if(!moving)return;event.preventDefault();const destination=lane.dataset.scheduleRow,after=lane.classList.contains('sort-after'),next=targets.filter(target=>target!==moving),index=next.indexOf(destination);next.splice(Math.max(0,index+(after?1:0)),0,moving);state.scheduleTargetOrder=next;renderSchedule();saveScheduleTargetOrder(next);});});
}

async function saveScheduleTargetOrder(targetKeys) {
  setScheduleSaveStatus('saving');
  try{const result=await apiRequest('/api/schedule-target-order',{method:'PUT',body:JSON.stringify({targetKeys})});state.scheduleTargetOrder=result.data||targetKeys;setScheduleSaveStatus('saved');}
  catch(error){setScheduleSaveStatus('error');showToast(error.message);await loadScheduling();}
}

async function persistScheduleEntryDialog() {
  const ids=document.querySelector('#schedule-entry-ids').value.split(',').filter(Boolean),entries=ids.map(id=>state.scheduleEntries.find(entry=>entry.id===id)).filter(Boolean);if(!entries.length)return;
  const base=entries[0],start=scheduleDateTimeInputValue('schedule-entry-start-date','schedule-entry-start-time'),end=scheduleDateTimeInputValue('schedule-entry-end-date','schedule-entry-end-time');
  if(!Number.isFinite(start.getTime())||!Number.isFinite(end.getTime())){setScheduleSaveStatus('error');showToast('Bitte für „Von“ und „Bis“ ein gültiges Datum mit Uhrzeit angeben.');return;}
  if(end<=start){setScheduleSaveStatus('error');showToast('„Bis“ muss nach „Von“ liegen.');return;}
  const targets=[...document.querySelectorAll('#schedule-entry-targets input:checked')].map(input=>input.value);if(!targets.length){setScheduleSaveStatus('error');showToast('Ein Zeitplaneintrag benötigt mindestens ein Ziel.');return;}
  const priority=document.querySelector('#schedule-entry-priority').value,recurrence=document.querySelector('#schedule-entry-recurrence').value;setScheduleSaveStatus('saving');
  try{
    const firstTarget=scheduleTargetPayload(targets[0]),firstResult=await apiRequest(`/api/schedules/${base.id}`,{method:'PUT',body:JSON.stringify(schedulePayload(base,{...firstTarget,startsAt:start.toISOString(),endsAt:end.toISOString(),priority,recurrence}))});
    await Promise.all(entries.slice(1).map(entry=>apiRequest(`/api/schedules/${entry.id}`,{method:'DELETE'})));
    const created=await Promise.all(targets.slice(1).map(target=>apiRequest('/api/schedules',{method:'POST',body:JSON.stringify(schedulePayload(base,{...scheduleTargetPayload(target),startsAt:start.toISOString(),endsAt:end.toISOString(),priority,recurrence}))})));
    document.querySelector('#schedule-entry-ids').value=[firstResult.entry.id,...created.map(result=>result.entry.id)].join(',');await loadScheduling();setScheduleSaveStatus('saved');
  }catch(error){setScheduleSaveStatus('error');showToast(error.message);await loadScheduling();}
}

function queueScheduleEntryAutosave() {
  setScheduleSaveStatus('saving');state.scheduleSaveQueue=state.scheduleSaveQueue.then(()=>persistScheduleEntryDialog());
}

document.querySelector('#schedule-entry-form').addEventListener('submit',event=>{event.preventDefault();queueScheduleEntryAutosave();});
['schedule-entry-start-date','schedule-entry-start-time','schedule-entry-end-date','schedule-entry-end-time','schedule-entry-priority','schedule-entry-recurrence'].forEach(id=>document.querySelector(`#${id}`).addEventListener('change',queueScheduleEntryAutosave));
document.querySelector('#schedule-entry-targets').addEventListener('change',event=>{if(!event.target.matches('input[type="checkbox"]'))return;const checked=document.querySelectorAll('#schedule-entry-targets input:checked');if(!checked.length){event.target.checked=true;showToast('Mindestens ein Ziel muss ausgewählt bleiben.');return;}queueScheduleEntryAutosave();});
document.querySelector('#delete-schedule-entry').addEventListener('click',()=>{const ids=document.querySelector('#schedule-entry-ids').value.split(',').filter(Boolean),entries=ids.map(id=>state.scheduleEntries.find(entry=>entry.id===id)).filter(Boolean);if(entries.length)deleteScheduleBlock({...entries[0],entries},true);});

function syncScheduleView() {
  const weekView=state.scheduleView==='week';
  document.querySelectorAll('[data-schedule-view]').forEach(button=>{const active=button.dataset.scheduleView===state.scheduleView;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active));});
  document.querySelector('.day-tabs').hidden=weekView;
  document.querySelector('#schedule-duration').disabled=weekView;
  document.querySelector('#schedule-full-day-note').hidden=!weekView;
  document.querySelector('#schedule-drag-hint').textContent=weekView?'Inhalt auf einen Wochentag ziehen oder auswählen und einen Tag anklicken. Zuweisungen gelten von 00:00 bis 24:00 Uhr.':'Inhalt ziehen oder auswählen, danach Uhrzeit anklicken.';
  const help=document.querySelectorAll('.schedule-help span');
  if(help[0])help[0].textContent=weekView?'ⓘ Zielnamen ziehen: Reihenfolge ändern · Inhalt in ein Tagesfeld ziehen: ganztägig zuweisen':'ⓘ Zielnamen ziehen: Reihenfolge ändern · Inhalt in eine Zielzeile ziehen: zuweisen';
  if(help[1])help[1].textContent=weekView?'Karte anklicken: bearbeiten · Neue Zuweisungen laufen am gewählten Tag von 00:00 bis 24:00 Uhr':'Block anklicken: bearbeiten · Block ziehen: verschieben · Kanten ziehen: Zeit ändern · Hover: alle Ziele markieren';
}

document.querySelectorAll('[data-schedule-view]').forEach(button=>button.addEventListener('click',()=>{
  if(button.dataset.scheduleView===state.scheduleView)return;
  state.scheduleView=button.dataset.scheduleView;
  syncScheduleView();updateWeekNavigation();renderSchedule();
}));

document.querySelectorAll('[data-day]').forEach(button => button.addEventListener('click', () => {
  state.scheduleDay = button.dataset.day;
  document.querySelectorAll('[data-day]').forEach(item => item.classList.toggle('is-active', item === button));
  updateWeekNavigation();
  renderSchedule();
}));

document.querySelectorAll('[data-week-direction]').forEach(button => button.addEventListener('click', () => {
  state.weekOffset += button.dataset.weekDirection === 'next' ? 1 : -1;
  updateWeekNavigation();renderSchedule();
}));
const scheduleWeekPicker=document.querySelector('#schedule-week-picker');
const scheduleWeekPickerTrigger=document.querySelector('#schedule-week-picker-trigger');
scheduleWeekPickerTrigger.addEventListener('click',()=>{
  if(scheduleWeekPicker.matches(':popover-open')){scheduleWeekPicker.hidePopover();return;}
  const selected=scheduleWeekStart();scheduleCalendarCursor=new Date(selected.getFullYear(),selected.getMonth(),1);
  renderScheduleWeekPicker();scheduleWeekPicker.showPopover();requestAnimationFrame(positionScheduleWeekPicker);
});
scheduleWeekPicker.addEventListener('toggle',event=>scheduleWeekPickerTrigger.setAttribute('aria-expanded',String(event.newState==='open')));
scheduleWeekPicker.querySelectorAll('[data-calendar-direction]').forEach(button=>button.addEventListener('click',()=>{
  scheduleCalendarCursor.setMonth(scheduleCalendarCursor.getMonth()+Number(button.dataset.calendarDirection));
  scheduleCalendarCursor.setDate(1);renderScheduleWeekPicker();requestAnimationFrame(positionScheduleWeekPicker);
}));
document.querySelector('#schedule-calendar-today').addEventListener('click',()=>{
  const today=new Date();
  state.weekOffset=0;
  state.scheduleDay=dayKeys[(today.getDay()+6)%7];
  scheduleCalendarCursor=new Date(today.getFullYear(),today.getMonth(),1);
  updateWeekNavigation();renderSchedule();
  scheduleWeekPicker.hidePopover();
});
window.addEventListener('resize',positionScheduleWeekPicker);

// Player monitoring and emergency takeover
const emergencyTypeLabels = { evacuation:'EVAKUIERUNG',weather:'UNWETTERWARNUNG',severe_weather:'UNWETTERWARNUNG',security:'SICHERHEITSHINWEIS',technical:'TECHNISCHE STÖRUNG',power_outage:'STROMAUSFALL',fire:'BRANDALARM',medical:'MEDIZINISCHER NOTFALL',all_clear:'ENTWARNUNG',info:'WICHTIGE INFORMATION' };

state.activeEmergency=null;state.activeWarning=null;state.presets=[];state.warningTemplates=[];state.warnings=[];
function warningTargetMarkup(selectedValues=[]){const selected=new Set(selectedValues);return`<label><input type="checkbox" value="global" ${selected.has('global')?'checked':''}><span><strong>Alle Displays</strong><small>Globaler Warnhinweis</small></span></label><details open><summary>Standorte</summary>${state.locations.map(location=>`<label><input type="checkbox" value="location:${escapeHtml(location.id)}" ${selected.has(`location:${location.id}`)?'checked':''}><span><strong>${escapeHtml(location.name)}</strong><small>${location.displayCount} Displays · ${escapeHtml(location.warningAreaCode||'keine DWD-Kennung')}</small></span></label>`).join('')||'<p class="property-note">Keine Standorte vorhanden.</p>'}</details><details><summary>Display-Gruppen</summary>${state.groups.map(group=>`<label><input type="checkbox" value="group:${escapeHtml(group.id)}" ${selected.has(`group:${group.id}`)||selected.has(group.id)?'checked':''}><span><strong>${escapeHtml(group.name)}</strong><small>${group.displayIds.length} Displays</small></span></label>`).join('')}</details><details><summary>Matrix-Displays</summary>${state.matrices.map(matrix=>`<label><input type="checkbox" value="matrix:${escapeHtml(matrix.id)}" ${selected.has(`matrix:${matrix.id}`)?'checked':''}><span><strong>${escapeHtml(matrix.name)}</strong><small>${matrix.members.length} Displays</small></span></label>`).join('')}</details><details><summary>Einzelne Displays</summary>${state.displays.map(display=>`<label><input type="checkbox" value="display:${escapeHtml(display.id)}" ${selected.has(`display:${display.id}`)?'checked':''}><span><strong>${escapeHtml(display.name)}</strong><small>${display.orientation==='portrait'?'Hochformat':'Querformat'}</small></span></label>`).join('')}</details>`;}
async function loadOperations(){try{if(!state.locations.length||!state.matrices.length)await loadDisplays();const [result,presets,templates]=await Promise.all([apiRequest('/api/operations'),apiRequest('/api/presets'),apiRequest('/api/warning-templates')]);state.displays=result.displays||[];state.activeEmergency=result.emergency||null;state.warnings=result.warnings||[];state.activeWarning=state.warnings.find(item=>item.status==='active')||null;state.presets=presets.data||[];state.warningTemplates=templates.data||[];if(!state.channels.length)await loadContent();renderOperations();renderPresets();renderWarningTemplates();renderDwdLocationOptions();}catch(error){showToast(error.message);}}
function presetTargets(){return[...document.querySelectorAll('#preset-target-options input:checked')].map(input=>input.value);}
function renderPresets(){const grid=document.querySelector('#preset-grid');if(!grid)return;grid.innerHTML=state.presets.map(preset=>{const channelUpdates=preset.config?.channelUpdates?.length||0,assignments=preset.config?.assignments?.length||0;return`<article><span class="preset-symbol">${preset.type==='emergency'?'!':preset.type==='reset'?'↺':'▶'}</span><div><strong>${escapeHtml(preset.name)}</strong><small>${preset.type==='emergency'?`${escapeHtml(emergencyTypeLabels[preset.config.warningType||preset.config.type]||'Warnhinweis')} · ${(preset.config.targets||[]).length} Ziele`:`${channelUpdates} Kanalzustände · ${assignments} Ausspielungen`}</small></div><button class="button ${preset.type==='emergency'?'emergency-button':'button-primary'}" type="button" data-execute-preset="${escapeHtml(preset.id)}">${preset.type==='emergency'?'Jetzt auslösen':'Preset aufrufen'}</button><div class="preset-card-actions"><button class="icon-button" type="button" data-rename-preset="${escapeHtml(preset.id)}" title="Preset umbenennen" aria-label="Preset umbenennen">✎</button><button class="icon-button" type="button" data-duplicate-preset="${escapeHtml(preset.id)}" title="Preset duplizieren" aria-label="Preset duplizieren">⧉</button><button class="icon-button danger-action" type="button" data-delete-preset="${escapeHtml(preset.id)}" title="Preset löschen" aria-label="Preset löschen">⌫</button></div></article>`;}).join('')||'<p class="property-note">Noch keine Presets vorhanden.</p>';grid.querySelectorAll('[data-execute-preset]').forEach(button=>button.addEventListener('click',async()=>{const preset=state.presets.find(item=>item.id===button.dataset.executePreset);if(!confirm(`Preset „${preset.name}“ jetzt ausführen?`))return;button.disabled=true;try{await apiRequest(`/api/presets/${preset.id}/execute`,{method:'POST',body:'{}'});await Promise.all([loadOperations(),loadContent(),loadDisplays()]);showToast(`Preset „${preset.name}“ wurde ausgeführt.`);}catch(error){showToast(error.message);}finally{button.disabled=false;}}));grid.querySelectorAll('[data-rename-preset]').forEach(button=>button.addEventListener('click',()=>renamePreset(button.dataset.renamePreset)));grid.querySelectorAll('[data-duplicate-preset]').forEach(button=>button.addEventListener('click',()=>duplicatePreset(button.dataset.duplicatePreset)));grid.querySelectorAll('[data-delete-preset]').forEach(button=>button.addEventListener('click',()=>deletePreset(button.dataset.deletePreset)));}
const presetDialog=document.querySelector('#preset-dialog');
let warningPresetDesign={backgroundColor:'#9f1d20',elements:[],selectedId:null};
function warningElementId(){return`warning-element-${Date.now()}-${Math.random().toString(36).slice(2,7)}`;}
function defaultWarningElements(documentData={},title='',message=''){
  const saved=Array.isArray(documentData?.layout?.elements)?documentData.layout.elements:null;
  if(saved?.length)return structuredClone(saved);
  return[
    {id:warningElementId(),type:'icon',text:documentData.symbol||'⚠',x:44,y:7,width:12,fontSize:72,color:documentData.textColor||'#ffffff',align:'center',bold:true},
    {id:warningElementId(),type:'title',role:'title',text:title||documentData.title||'Wichtiger Warnhinweis',x:10,y:27,width:80,fontSize:50,color:documentData.textColor||'#ffffff',align:'center',bold:true},
    {id:warningElementId(),type:'text',role:'instructions',text:message||documentData.instructions||'Bitte beachten Sie die Anweisungen des Personals.',x:18,y:59,width:64,fontSize:28,color:documentData.textColor||'#ffffff',align:'center',bold:false}
  ];
}
function resetWarningPresetDesign(documentData={},title='',message=''){
  warningPresetDesign={backgroundColor:documentData.backgroundColor||'#9f1d20',elements:defaultWarningElements(documentData,title,message),selectedId:null};
  document.querySelector('#preset-design-background').value=warningPresetDesign.backgroundColor;
  renderWarningPresetDesign();
}
function selectedWarningElement(){return warningPresetDesign.elements.find(element=>element.id===warningPresetDesign.selectedId);}
function renderWarningPresetProperties(){
  const element=selectedWarningElement(),empty=document.querySelector('.warning-property-empty'),fields=document.querySelector('.warning-property-fields');
  empty.hidden=Boolean(element);fields.hidden=!element;
  if(!element)return;
  document.querySelector('#warning-element-content').value=element.text||'';
  document.querySelector('#warning-element-x').value=Number(element.x||0).toFixed(1);
  document.querySelector('#warning-element-y').value=Number(element.y||0).toFixed(1);
  document.querySelector('#warning-element-width').value=Number(element.width||30).toFixed(1);
  document.querySelector('#warning-element-size').value=Number(element.fontSize||32);
  document.querySelector('#warning-element-color').value=element.color||'#ffffff';
  document.querySelector('#warning-element-align').value=element.align||'left';
  document.querySelector('#warning-element-bold').checked=Boolean(element.bold);
  document.querySelector('.warning-icon-field').hidden=element.type!=='icon';
  if(element.type==='icon'){const picker=document.querySelector('#warning-element-icon');if(![...picker.options].some(option=>option.value===element.text))picker.add(new Option(element.text,element.text));picker.value=element.text;}
}
function renderWarningPresetDesign(){
  const canvas=document.querySelector('#warning-preset-canvas');
  if(!canvas)return;
  canvas.style.background=warningPresetDesign.backgroundColor;
  canvas.innerHTML=warningPresetDesign.elements.map(element=>`<div class="warning-design-element ${element.type==='icon'?'is-icon':''} ${element.id===warningPresetDesign.selectedId?'is-selected':''}" data-warning-element-id="${escapeHtml(element.id)}" style="left:${Number(element.x||0)}%;top:${Number(element.y||0)}%;width:${Number(element.width||30)}%;color:${escapeHtml(element.color||'#ffffff')};font-size:${Number(element.fontSize||32)}px;text-align:${element.align||'left'};font-weight:${element.bold?800:400}">${escapeHtml(element.text||'')}</div>`).join('');
  canvas.querySelectorAll('[data-warning-element-id]').forEach(node=>{
    node.addEventListener('pointerdown',event=>{
      event.preventDefault();
      const element=warningPresetDesign.elements.find(item=>item.id===node.dataset.warningElementId);if(!element)return;
      warningPresetDesign.selectedId=element.id;renderWarningPresetProperties();
      canvas.querySelectorAll('.warning-design-element').forEach(item=>item.classList.toggle('is-selected',item===node));
      const bounds=canvas.getBoundingClientRect(),startX=event.clientX,startY=event.clientY,originalX=Number(element.x||0),originalY=Number(element.y||0);
      node.setPointerCapture(event.pointerId);node.classList.add('is-dragging');
      const move=moveEvent=>{element.x=Math.max(0,Math.min(100-Number(element.width||30),(originalX+(moveEvent.clientX-startX)/bounds.width*100)));element.y=Math.max(0,Math.min(96,(originalY+(moveEvent.clientY-startY)/bounds.height*100)));node.style.left=`${element.x}%`;node.style.top=`${element.y}%`;document.querySelector('#warning-element-x').value=element.x.toFixed(1);document.querySelector('#warning-element-y').value=element.y.toFixed(1);};
      const end=()=>{node.classList.remove('is-dragging');node.removeEventListener('pointermove',move);node.removeEventListener('pointerup',end);node.removeEventListener('pointercancel',end);};
      node.addEventListener('pointermove',move);node.addEventListener('pointerup',end);node.addEventListener('pointercancel',end);
    });
  });
  renderWarningPresetProperties();
}
function addWarningPresetElement(type){
  const defaults=type==='icon'?{text:'⚠',x:44,y:10,width:12,fontSize:72,align:'center',bold:true}:type==='title'?{text:'Neue Überschrift',x:20,y:28,width:60,fontSize:56,align:'center',bold:true}:{text:'Neuer Text',x:25,y:58,width:50,fontSize:28,align:'center',bold:false};
  const element={id:warningElementId(),type,color:'#ffffff',...defaults};warningPresetDesign.elements.push(element);warningPresetDesign.selectedId=element.id;renderWarningPresetDesign();
}
function updateSelectedWarningElement(key,value){
  const element=selectedWarningElement();if(!element)return;
  if(['x','y','width','fontSize'].includes(key)){
    if(!Number.isFinite(value))return;
    if(key==='width')value=Math.max(3,Math.min(100,value));
    if(key==='fontSize')value=Math.max(12,Math.min(180,value));
    if(key==='x')value=Math.max(0,Math.min(100-Number(element.width||30),value));
    if(key==='y')value=Math.max(0,Math.min(96,value));
  }
  element[key]=value;
  if(element.role==='title'&&key==='text')document.querySelector('#preset-alert-title').value=value;
  if(element.role==='instructions'&&key==='text')document.querySelector('#preset-alert-message').value=value;
  renderWarningPresetDesign();
}
function warningPresetDocument(base={}){
  return{...base,backgroundColor:warningPresetDesign.backgroundColor,layout:{version:1,aspectRatio:'16:9',elements:structuredClone(warningPresetDesign.elements)}};
}
function applyTemplateToPreset(template){if(!template)return;const doc=template.document||{};document.querySelector('#preset-alert-type').value=['weather','security','technical','evacuation','info'].includes(template.warningType)?template.warningType:template.warningType==='severe_weather'?'weather':'info';document.querySelector('#preset-alert-title').value=doc.title||template.name;document.querySelector('#preset-alert-description').value=doc.description||template.description||'';document.querySelector('#preset-alert-message').value=doc.instructions||'';document.querySelector('#preset-priority').value=template.priority||900;resetWarningPresetDesign(doc,doc.title||template.name,doc.instructions||'');}
function presetContentOptions(selected=''){return`${state.channels.map(channel=>`<option value="channel:${escapeHtml(channel.id)}" ${selected===`channel:${channel.id}`?'selected':''}>Kanal · ${escapeHtml(channel.name)}</option>`).join('')}${state.slides.map(slide=>`<option value="slide:${escapeHtml(slide.id)}" ${selected===`slide:${slide.id}`?'selected':''}>Slide · ${escapeHtml(slide.name)}</option>`).join('')}`;}
function presetAssignmentTargetOptions(selected=''){const groups=state.groups.map(item=>({value:`group:${item.id}`,label:`Gruppe · ${item.name}`})),matrices=state.matrices.map(item=>({value:`matrix:${item.id}`,label:`Matrix · ${item.name}`})),displays=state.displays.map(item=>({value:`display:${item.id}`,label:`Display · ${item.name}`}));return[...matrices,...groups,...displays].map(item=>`<option value="${escapeHtml(item.value)}" ${selected===item.value?'selected':''}>${escapeHtml(item.label)}</option>`).join('');}
function addPresetChannelAction(action={}){const container=document.querySelector('#preset-channel-actions'),row=document.createElement('div');row.className='preset-action-row preset-channel-action';row.innerHTML=`<label>Kanal<select data-preset-action-channel>${state.channels.map(channel=>`<option value="${escapeHtml(channel.id)}" ${action.channelId===channel.id?'selected':''}>${escapeHtml(channel.name)}</option>`).join('')}</select></label><label>Slides in Reihenfolge<select multiple size="5" data-preset-action-slides>${state.slides.map(slide=>`<option value="${escapeHtml(slide.id)}" ${(action.slideIds||[]).includes(slide.id)?'selected':''}>${escapeHtml(slide.name)}</option>`).join('')}</select></label><button class="icon-button danger-action" type="button" aria-label="Kanalzustand entfernen">×</button>`;row.querySelector('button').addEventListener('click',()=>row.remove());container.append(row);}
function addPresetAssignmentAction(action={}){const container=document.querySelector('#preset-assignment-actions'),row=document.createElement('div');row.className='preset-action-row preset-assignment-action';const target=action.targetType&&action.targetId?`${action.targetType}:${action.targetId}`:'',content=action.contentType&&action.contentId?`${action.contentType}:${action.contentId}`:'';row.innerHTML=`<label>Ziel<select data-preset-action-target>${presetAssignmentTargetOptions(target)}</select></label><label>Inhalt<select data-preset-action-content>${presetContentOptions(content)}</select></label><button class="icon-button danger-action" type="button" aria-label="Zuweisung entfernen">×</button>`;row.querySelector('button').addEventListener('click',()=>row.remove());container.append(row);}
function syncPresetTypeFields(){const warning=document.querySelector('#preset-type').value==='emergency';document.querySelector('#preset-warning-fields').hidden=!warning;document.querySelector('#preset-state-fields').hidden=warning;presetDialog.classList.toggle('preset-dialog-warning',warning);}
function openPresetDialog(preset=null){document.querySelector('#preset-form').reset();document.querySelector('#preset-edit-id').value=preset?.id||'';document.querySelector('#preset-dialog-title').textContent=preset?'Preset bearbeiten':'Preset anlegen';document.querySelector('#preset-name').value=preset?.name||'';document.querySelector('#preset-type').value=preset?.type||'channel';document.querySelector('#preset-template').innerHTML=`<option value="">Ohne Vorlage</option>${state.warningTemplates.map(template=>`<option value="${escapeHtml(template.id)}">${escapeHtml(template.name)}</option>`).join('')}`;document.querySelector('#preset-template').value=preset?.config?.templateId||'';document.querySelector('#preset-alert-type').value=preset?.config?.warningType||preset?.config?.type||'evacuation';document.querySelector('#preset-severity').value=preset?.config?.severity||3;document.querySelector('#preset-alert-title').value=preset?.config?.title||'';document.querySelector('#preset-alert-description').value=preset?.config?.description||'';document.querySelector('#preset-alert-message').value=preset?.config?.instructions||preset?.config?.message||'';document.querySelector('#preset-priority').value=preset?.config?.priority||900;document.querySelector('#preset-duration').value=preset?.config?.durationMinutes||0;document.querySelector('#preset-target-options').innerHTML=warningTargetMarkup(preset?.config?.targets||[]);document.querySelector('#preset-channel-actions').innerHTML='';document.querySelector('#preset-assignment-actions').innerHTML='';(preset?.config?.channelUpdates||[]).forEach(addPresetChannelAction);(preset?.config?.assignments||[]).forEach(addPresetAssignmentAction);resetWarningPresetDesign(preset?.config?.document||{},preset?.config?.title||'',preset?.config?.instructions||preset?.config?.message||'');syncPresetTypeFields();presetDialog.showModal();}
function renamePreset(id){const preset=state.presets.find(item=>item.id===id);if(!preset)return;openPresetDialog(preset);document.querySelector('#preset-dialog-title').textContent='Preset umbenennen oder bearbeiten';document.querySelector('#preset-name').focus();document.querySelector('#preset-name').select();}
async function duplicatePreset(id){const preset=state.presets.find(item=>item.id===id);if(!preset)return;const payload={name:`${preset.name} Kopie`,type:preset.type,channelId:preset.channelId||undefined,config:structuredClone(preset.config||{}),status:'active'};try{await apiRequest('/api/presets',{method:'POST',body:JSON.stringify(payload)});await loadOperations();showToast(`Preset „${preset.name}“ wurde dupliziert.`);}catch(error){showToast(error.message);}}
async function deletePreset(id){const preset=state.presets.find(item=>item.id===id);if(!preset||!confirm(`Preset „${preset.name}“ löschen?`))return;try{await apiRequest(`/api/presets/${id}`,{method:'DELETE'});await loadOperations();showToast(`Preset „${preset.name}“ wurde gelöscht.`);}catch(error){showToast(error.message);}}
document.querySelector('#preset-template').addEventListener('change',event=>applyTemplateToPreset(state.warningTemplates.find(item=>item.id===event.target.value)));
document.querySelector('#preset-type').addEventListener('change',syncPresetTypeFields);document.querySelector('[data-add-preset-channel]').addEventListener('click',()=>addPresetChannelAction());document.querySelector('[data-add-preset-assignment]').addEventListener('click',()=>addPresetAssignmentAction());
document.querySelectorAll('[data-add-warning-element]').forEach(button=>button.addEventListener('click',()=>addWarningPresetElement(button.dataset.addWarningElement)));
document.querySelector('#preset-design-background').addEventListener('input',event=>{warningPresetDesign.backgroundColor=event.target.value;renderWarningPresetDesign();});
document.querySelector('#warning-element-content').addEventListener('input',event=>updateSelectedWarningElement('text',event.target.value));
document.querySelector('#warning-element-icon').addEventListener('change',event=>updateSelectedWarningElement('text',event.target.value));
[['#warning-element-x','x'],['#warning-element-y','y'],['#warning-element-width','width'],['#warning-element-size','fontSize']].forEach(([selector,key])=>document.querySelector(selector).addEventListener('input',event=>updateSelectedWarningElement(key,Number(event.target.value))));
document.querySelector('#warning-element-color').addEventListener('input',event=>updateSelectedWarningElement('color',event.target.value));
document.querySelector('#warning-element-align').addEventListener('change',event=>updateSelectedWarningElement('align',event.target.value));
document.querySelector('#warning-element-bold').addEventListener('change',event=>updateSelectedWarningElement('bold',event.target.checked));
document.querySelector('#delete-warning-element').addEventListener('click',()=>{warningPresetDesign.elements=warningPresetDesign.elements.filter(element=>element.id!==warningPresetDesign.selectedId);warningPresetDesign.selectedId=null;renderWarningPresetDesign();});
document.querySelector('#preset-alert-title').addEventListener('input',event=>{const element=warningPresetDesign.elements.find(item=>item.role==='title');if(element){element.text=event.target.value;renderWarningPresetDesign();}});
document.querySelector('#preset-alert-message').addEventListener('input',event=>{const element=warningPresetDesign.elements.find(item=>item.role==='instructions');if(element){element.text=event.target.value;renderWarningPresetDesign();}});
document.querySelector('[data-action="new-preset"]').addEventListener('click',()=>openPresetDialog());document.querySelector('#preset-form').addEventListener('submit',async event=>{event.preventDefault();const id=document.querySelector('#preset-edit-id').value,type=document.querySelector('#preset-type').value,template=state.warningTemplates.find(item=>item.id===document.querySelector('#preset-template').value),targets=presetTargets(),channelUpdates=[...document.querySelectorAll('.preset-channel-action')].map(row=>({channelId:row.querySelector('[data-preset-action-channel]').value,slideIds:[...row.querySelector('[data-preset-action-slides]').selectedOptions].map(option=>option.value)})),assignments=[...document.querySelectorAll('.preset-assignment-action')].map(row=>{const[targetType,targetId]=row.querySelector('[data-preset-action-target]').value.split(':'),[contentType,contentId]=row.querySelector('[data-preset-action-content]').value.split(':');return{targetType,targetId,contentType,contentId};});if(type==='emergency'&&!targets.length){showToast('Bitte mindestens einen Zielbereich für das Warn-Preset wählen.');return;}if(type==='emergency'&&!warningPresetDesign.elements.length){showToast('Bitte mindestens ein Element im Warnhinweis-Editor anlegen.');return;}if(type!=='emergency'&&!channelUpdates.length&&!assignments.length){showToast('Bitte mindestens einen Kanalzustand oder eine Content-Zuweisung definieren.');return;}if(channelUpdates.some(action=>!action.slideIds.length)){showToast('Bitte für jeden Kanalzustand mindestens eine Slide auswählen.');return;}const payload={name:document.querySelector('#preset-name').value.trim(),type,config:type==='emergency'?{templateId:template?.id,warningType:document.querySelector('#preset-alert-type').value,severity:Number(document.querySelector('#preset-severity').value),title:document.querySelector('#preset-alert-title').value.trim(),description:document.querySelector('#preset-alert-description').value.trim(),instructions:document.querySelector('#preset-alert-message').value.trim(),priority:Number(document.querySelector('#preset-priority').value),durationMinutes:Number(document.querySelector('#preset-duration').value),targets,document:warningPresetDocument(template?.document||{}),returnBehavior:'resume_current_schedule'}:{channelUpdates,assignments},status:'active'};try{await apiRequest(id?`/api/presets/${id}`:'/api/presets',{method:id?'PUT':'POST',body:JSON.stringify(payload)});presetDialog.close();await loadOperations();showToast(`Preset „${payload.name}“ wurde gespeichert.`);}catch(error){showToast(error.message);}});

const warningTemplateDialog=document.querySelector('#warning-template-dialog');
function applyWarningTemplate(template){if(!template)return;const doc=template.document||{};document.querySelector('#emergency-template').value=template.id;document.querySelector('#emergency-type').value=['weather','security','technical','evacuation','info'].includes(template.warningType)?template.warningType:template.warningType==='severe_weather'?'weather':'info';document.querySelector('#emergency-title').value=doc.title||template.name;document.querySelector('#emergency-description').value=doc.description||template.description||'';document.querySelector('#emergency-message').value=doc.instructions||'';document.querySelector('#emergency-priority').value=template.priority||900;document.querySelector('#emergency-background').value=doc.backgroundColor||'#9f1d20';document.querySelector('#emergency-text-color').value=doc.textColor||'#ffffff';updateEmergencyPreview();}
function renderWarningTemplates(){const grid=document.querySelector('#warning-template-grid'),select=document.querySelector('#emergency-template');if(!grid||!select)return;select.innerHTML=`<option value="">Ohne Vorlage</option>${state.warningTemplates.map(template=>`<option value="${escapeHtml(template.id)}">${escapeHtml(template.name)}</option>`).join('')}`;grid.innerHTML=state.warningTemplates.map(template=>{const doc=template.document||{};return`<article style="--warning-template-bg:${escapeHtml(doc.backgroundColor||'#315b49')};--warning-template-fg:${escapeHtml(doc.textColor||'#ffffff')}"><span>${escapeHtml(emergencyTypeLabels[template.warningType]||template.warningType.toUpperCase())}</span><strong>${escapeHtml(template.name)}</strong><p>${escapeHtml(doc.title||template.description||'')}</p><small>Priorität ${template.priority}</small><div><button class="button button-secondary" data-use-warning-template="${escapeHtml(template.id)}">Verwenden</button><button class="icon-button" data-edit-warning-template="${escapeHtml(template.id)}" title="Vorlage bearbeiten" aria-label="Vorlage bearbeiten">✎</button><button class="icon-button" data-delete-warning-template="${escapeHtml(template.id)}" title="Vorlage löschen" aria-label="Vorlage löschen">⌫</button></div></article>`;}).join('')||'<p class="property-note">Noch keine Warnvorlagen vorhanden.</p>';grid.querySelectorAll('[data-use-warning-template]').forEach(button=>button.addEventListener('click',()=>applyWarningTemplate(state.warningTemplates.find(item=>item.id===button.dataset.useWarningTemplate))));grid.querySelectorAll('[data-edit-warning-template]').forEach(button=>button.addEventListener('click',()=>openWarningTemplateDialog(state.warningTemplates.find(item=>item.id===button.dataset.editWarningTemplate))));grid.querySelectorAll('[data-delete-warning-template]').forEach(button=>button.addEventListener('click',()=>deleteWarningTemplate(button.dataset.deleteWarningTemplate)));}
function openWarningTemplateDialog(template=null){const doc=template?.document||{};document.querySelector('#warning-template-form').reset();document.querySelector('#warning-template-edit-id').value=template?.id||'';document.querySelector('#warning-template-dialog-title').textContent=template?'Warnvorlage bearbeiten':'Warnvorlage anlegen';document.querySelector('#warning-template-name').value=template?.name||'';document.querySelector('#warning-template-type').value=template?.warningType==='severe_weather'?'weather':template?.warningType||'info';document.querySelector('#warning-template-description').value=template?.description||'';document.querySelector('#warning-template-title').value=doc.title||'';document.querySelector('#warning-template-display-description').value=doc.description||'';document.querySelector('#warning-template-instructions').value=doc.instructions||'';document.querySelector('#warning-template-priority').value=template?.priority||900;document.querySelector('#warning-template-symbol').value=doc.symbol||'';document.querySelector('#warning-template-background').value=doc.backgroundColor||'#9f1d20';document.querySelector('#warning-template-text-color').value=doc.textColor||'#ffffff';document.querySelector('#warning-template-ticker').value=doc.ticker||'';document.querySelector('#warning-template-image').value=doc.image||doc.logo||'';document.querySelector('#warning-template-qr').value=doc.qrCode||'';document.querySelector('#warning-template-audio').value=doc.audio||'';document.querySelector('#archive-warning-template').hidden=!template;warningTemplateDialog.showModal();}
document.querySelector('#archive-warning-template').textContent='Vorlage löschen';
document.querySelector('[data-action="new-warning-template"]').addEventListener('click',()=>openWarningTemplateDialog());
document.querySelector('#warning-template-form').addEventListener('submit',async event=>{event.preventDefault();const id=document.querySelector('#warning-template-edit-id').value,payload={name:document.querySelector('#warning-template-name').value.trim(),warningType:document.querySelector('#warning-template-type').value,description:document.querySelector('#warning-template-description').value.trim(),priority:Number(document.querySelector('#warning-template-priority').value),status:'active',document:{title:document.querySelector('#warning-template-title').value.trim(),description:document.querySelector('#warning-template-display-description').value.trim(),instructions:document.querySelector('#warning-template-instructions').value.trim(),symbol:document.querySelector('#warning-template-symbol').value.trim(),backgroundColor:document.querySelector('#warning-template-background').value,textColor:document.querySelector('#warning-template-text-color').value,ticker:document.querySelector('#warning-template-ticker').value.trim(),image:document.querySelector('#warning-template-image').value.trim(),qrCode:document.querySelector('#warning-template-qr').value.trim(),audio:document.querySelector('#warning-template-audio').value.trim()}};try{await apiRequest(id?`/api/warning-templates/${id}`:'/api/warning-templates',{method:id?'PUT':'POST',body:JSON.stringify(payload)});warningTemplateDialog.close();await loadOperations();showToast(`Warnvorlage „${payload.name}“ wurde gespeichert.`);}catch(error){showToast(error.message);}});
async function deleteWarningTemplate(id){const template=state.warningTemplates.find(item=>item.id===id);if(!template||!confirm(`Warnvorlage „${template.name}“ löschen? Bereits gespeicherte Presets bleiben erhalten.`))return;try{await apiRequest(`/api/warning-templates/${id}`,{method:'DELETE'});if(warningTemplateDialog.open)warningTemplateDialog.close();await loadOperations();showToast(`Warnvorlage „${template.name}“ wurde gelöscht.`);}catch(error){showToast(error.message);}}
document.querySelector('#archive-warning-template').addEventListener('click',()=>deleteWarningTemplate(document.querySelector('#warning-template-edit-id').value));
document.querySelector('#emergency-template').addEventListener('change',event=>applyWarningTemplate(state.warningTemplates.find(item=>item.id===event.target.value)));

function renderDwdLocationOptions(){const select=document.querySelector('#dwd-location');if(!select)return;const current=select.value;select.innerHTML=`<option value="">Standort wählen</option>${state.locations.map(location=>`<option value="${escapeHtml(location.id)}">${escapeHtml(location.name)}${location.warningAreaCode?` · ${escapeHtml(location.warningAreaCode)}`:' · Kennung fehlt'}</option>`).join('')}`;if(state.locations.some(location=>location.id===current))select.value=current;document.querySelector('#dwd-target-options').innerHTML=warningTargetMarkup(current?[`location:${current}`]:[]);}
async function loadDwdConfiguration(){const locationId=document.querySelector('#dwd-location').value;document.querySelector('#dwd-target-options').innerHTML=warningTargetMarkup(locationId?[`location:${locationId}`]:[]);if(!locationId)return;try{const result=await apiRequest(`/api/dwd/config/${locationId}`),config=result.configuration;document.querySelector('#dwd-min-severity').value=config.minSeverity||2;document.querySelector('#dwd-event-types').value=(config.eventTypes||[]).join(', ');document.querySelector('#dwd-auto-publish').checked=config.confirmationRequired===false;document.querySelector('#dwd-target-options').innerHTML=warningTargetMarkup(config.targets||[`location:${locationId}`]);}catch(error){showToast(error.message);}}
function dwdConfigurationPayload(){return{enabled:true,confirmationRequired:!document.querySelector('#dwd-auto-publish').checked,minSeverity:Number(document.querySelector('#dwd-min-severity').value),eventTypes:document.querySelector('#dwd-event-types').value.split(',').map(value=>value.trim()).filter(Boolean),targets:[...document.querySelectorAll('#dwd-target-options input:checked')].map(input=>input.value)};}
async function saveDwdConfiguration(){const locationId=document.querySelector('#dwd-location').value;if(!locationId){showToast('Bitte zuerst einen Standort wählen.');return false;}const payload=dwdConfigurationPayload();if(!payload.targets.length){showToast('Bitte mindestens ein Ziel für DWD-Warnungen wählen.');return false;}await apiRequest(`/api/dwd/config/${locationId}`,{method:'PUT',body:JSON.stringify(payload)});return true;}
function renderDwdWarnings(warnings=[]){const list=document.querySelector('#dwd-warning-list');list.innerHTML=warnings.length?warnings.map(warning=>`<article><span class="warning-level level-${warning.severity}">Stufe ${warning.severity}</span><div><strong>${escapeHtml(warning.title)}</strong><small>${escapeHtml(warning.areaName||'Warngebiet')} · ${warning.startsAt?new Date(warning.startsAt).toLocaleString('de-DE'):'ab sofort'} bis ${warning.endsAt?new Date(warning.endsAt).toLocaleString('de-DE'):'auf Weiteres'}${warning.updatedAt?` · aktualisiert ${new Date(warning.updatedAt).toLocaleString('de-DE')}`:''}</small><p>${escapeHtml(warning.description||'')}</p><em>${escapeHtml(warning.instructions||'')}</em>${warning.status==='pending'?`<button class="button emergency-button" type="button" data-publish-warning="${escapeHtml(warning.id)}">Jetzt bestätigen und veröffentlichen</button>`:warning.status==='active'?'<span class="tag red">Aktiv veröffentlicht</span>':''}</div></article>`).join(''):'<p class="property-note">Für diesen Standort liegen aktuell keine passenden DWD-Warnungen vor.</p>';list.querySelectorAll('[data-publish-warning]').forEach(button=>button.addEventListener('click',async()=>{if(!confirm('Diese amtliche DWD-Warnung jetzt auf den konfigurierten Zielen veröffentlichen?'))return;try{await apiRequest(`/api/warnings/${button.dataset.publishWarning}/publish`,{method:'POST',body:'{}'});await loadOperations();showToast('DWD-Warnung wurde veröffentlicht.');}catch(error){showToast(error.message);}}));}
document.querySelector('#dwd-location').addEventListener('change',loadDwdConfiguration);
document.querySelector('[data-action="save-dwd-config"]').addEventListener('click',async()=>{try{if(await saveDwdConfiguration())showToast('DWD-Einstellungen wurden gespeichert.');}catch(error){showToast(error.message);}});
document.querySelector('[data-action="preview-dwd"]').addEventListener('click',async()=>{const locationId=document.querySelector('#dwd-location').value;if(!locationId){showToast('Bitte zuerst einen Standort wählen.');return;}try{const result=await apiRequest(`/api/dwd/warnings/${locationId}`);renderDwdWarnings(result.data||[]);showToast(`${result.data?.length||0} DWD-Warnungen wurden abgerufen.`);}catch(error){showToast(error.message);}});
document.querySelector('[data-action="sync-dwd"]').addEventListener('click',async()=>{const locationId=document.querySelector('#dwd-location').value;if(!locationId)return showToast('Bitte zuerst einen Standort wählen.');try{if(!await saveDwdConfiguration())return;const result=await apiRequest(`/api/dwd/sync/${locationId}`,{method:'POST',body:'{}'});await loadOperations();document.querySelector('#dwd-location').value=locationId;await loadDwdConfiguration();renderDwdWarnings(result.data||[]);showToast(`${result.data?.length||0} DWD-Warnungen wurden übernommen${result.autoPublished?' und veröffentlicht':' und zur Bestätigung vorgemerkt'}.`);}catch(error){showToast(error.message);}});

function formatLastSeen(dateString) {
  const seconds = Math.max(0, Math.round((Date.now() - new Date(dateString).getTime()) / 1000));
  if (seconds < 60) return `vor ${seconds} Sek.`;
  if (seconds < 3600) return `vor ${Math.round(seconds / 60)} Min.`;
  return `vor ${Math.round(seconds / 3600)} Std.`;
}

function renderEmergencyControls() {
  const targets = document.querySelector('#emergency-target-options');
  if (!targets) return;
  const activeAlert=state.activeWarning||state.activeEmergency,selected=activeAlert?.targets||[];
  targets.innerHTML=warningTargetMarkup(selected);
  const active=Boolean(state.activeWarning||state.activeEmergency?.active);
  document.querySelector('#emergency-state').textContent = active ? 'AKTIV' : 'Bereit';
  document.querySelector('#emergency-state').classList.toggle('is-active', active);
  document.querySelector('[data-action="cancel-emergency"]').disabled = !active;
}

function renderOperations() {
  const table = document.querySelector('#device-monitor-table');
  if (!table) return;
  const statuses = state.displays.map(display => ({ display, status: {lastSeen:display.lastSeenAt||display.updatedAt,channel:display.channel,currentContent:display.channel,cached:display.cached,online:display.online,version:display.playerVersion||'Player noch nicht verbunden'} }));
  let onlineCount = 0;
  let warningCount = 0;
  table.innerHTML = `<div class="device-row device-head"><span>Player</span><span>Zustand</span><span>Aktueller Inhalt</span><span>Offline-Cache</span><span>Aktionen</span></div>${statuses.map(({ display, status }) => {
    const age = Date.now() - new Date(status.lastSeen).getTime();
    const level = status.online !== false && age < 90000 ? 'online' : age < 10 * 60000 ? 'warning' : 'offline';
    if (level === 'online') onlineCount += 1; else warningCount += 1;
    return `<div class="device-row"><span><strong>${escapeHtml(display.name)}</strong><small>${escapeHtml(status.version || 'Player 1.0')} · ${display.orientation === 'portrait' ? 'Hochformat' : 'Querformat'}</small></span><span><b class="player-state ${level}">${level === 'online' ? '● Online' : level === 'warning' ? '● Verzögert' : '● Offline'}</b><small>${formatLastSeen(status.lastSeen)}</small></span><span><strong>${escapeHtml(status.currentContent || display.channel)}</strong><small>${escapeHtml(status.channel || display.channel)}</small></span><span><b class="cache-state ${status.cached ? 'ready' : ''}">${status.cached ? '✓ Bereit' : 'Nicht vollständig'}</b><small>lokal gespeichert</small></span><span class="device-actions"><button type="button" data-command-display="${escapeHtml(display.id)}" data-command="sync" title="Jetzt synchronisieren">↻</button><button type="button" data-command-display="${escapeHtml(display.id)}" data-command="reload" title="Player neu laden">⟳</button><a href="${escapeHtml(playerUrl(display))}" target="_blank" title="Player öffnen">↗</a></span></div>`;
  }).join('')}`;
  document.querySelector('#ops-online-count').textContent = onlineCount;
  document.querySelector('#ops-warning-count').textContent = warningCount;
  document.querySelector('#ops-cached-count').textContent = statuses.filter(item => item.status.cached).length;
  document.querySelector('#ops-display-count').textContent = statuses.length;
  table.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', async () => {
    try{await apiRequest(`/api/operations/displays/${button.dataset.commandDisplay}/commands`,{method:'POST',body:JSON.stringify({command:button.dataset.command})});showToast(button.dataset.command === 'reload' ? 'Neustart-Befehl wurde an den Player gesendet.' : 'Synchronisierung wurde angefordert.');}catch(error){showToast(error.message);}
  }));
  renderEmergencyControls();
}

function updateEmergencyPreview(){
  document.querySelector('#emergency-preview small').textContent = emergencyTypeLabels[document.querySelector('#emergency-type').value];
  document.querySelector('#emergency-preview strong').textContent = document.querySelector('#emergency-title').value;
  document.querySelector('#emergency-preview p').textContent = document.querySelector('#emergency-message').value;
  document.querySelector('#emergency-preview').style.background=document.querySelector('#emergency-background').value;
  document.querySelector('#emergency-preview').style.color=document.querySelector('#emergency-text-color').value;
  const template=state.warningTemplates.find(item=>item.id===document.querySelector('#emergency-template').value),tickerValue=String(template?.document?.ticker||'').trim(),ticker=document.querySelector('#emergency-preview-ticker'),tickerText=document.querySelector('#emergency-preview-ticker-text');
  tickerText.textContent=tickerValue;
  ticker.hidden=!tickerValue;
}
['emergency-type','emergency-title','emergency-message','emergency-background','emergency-text-color'].forEach(id=>document.querySelector(`#${id}`).addEventListener('input',updateEmergencyPreview));

document.querySelector('[data-action="activate-emergency"]').addEventListener('click', async () => {
  const targets = [...document.querySelectorAll('#emergency-target-options input:checked')].map(input => input.value);
  if (!targets.length) { showToast('Bitte mindestens einen Zielbereich auswählen.'); return; }
  if (!window.confirm('Warnhinweis jetzt auf den ausgewählten Displays veröffentlichen?')) return;
  const duration=Number(document.querySelector('#emergency-duration').value),template=state.warningTemplates.find(item=>item.id===document.querySelector('#emergency-template').value),startsAt=new Date().toISOString(),endsAt=duration?new Date(Date.now()+duration*60000).toISOString():undefined,payload={source:'manual',warningType:document.querySelector('#emergency-type').value,severity:Number(document.querySelector('#emergency-severity').value),title:document.querySelector('#emergency-title').value.trim(),description:document.querySelector('#emergency-description').value.trim(),instructions:document.querySelector('#emergency-message').value.trim(),startsAt,endsAt,priority:Number(document.querySelector('#emergency-priority').value),targets,document:{...(template?.document||{}),backgroundColor:document.querySelector('#emergency-background').value,textColor:document.querySelector('#emergency-text-color').value},returnBehavior:document.querySelector('#emergency-return').value};
  try{const result=await apiRequest('/api/warnings',{method:'POST',body:JSON.stringify(payload)});await apiRequest(`/api/warnings/${result.warning.id}/publish`,{method:'POST',body:'{}'});await loadOperations();showToast('Warnhinweis ist aktiv und übersteuert die gewählten Displays.');}catch(error){showToast(error.message);}
});

document.querySelector('[data-action="cancel-emergency"]').addEventListener('click', async () => {
  try{if(state.activeWarning)await apiRequest(`/api/warnings/${state.activeWarning.id}/end`,{method:'POST',body:'{}'});else await apiRequest('/api/operations/emergency',{method:'DELETE'});await loadOperations();showToast('Warnhinweis wurde beendet. Die reguläre Planung läuft weiter.');}catch(error){showToast(error.message);}
});
document.querySelector('[data-action="save-warning-as-preset"]').addEventListener('click',()=>{openPresetDialog();document.querySelector('#preset-name').value=document.querySelector('#emergency-title').value||'Warnhinweis';document.querySelector('#preset-type').value='emergency';syncPresetTypeFields();document.querySelector('#preset-template').value=document.querySelector('#emergency-template').value;document.querySelector('#preset-alert-type').value=document.querySelector('#emergency-type').value;document.querySelector('#preset-severity').value=document.querySelector('#emergency-severity').value;document.querySelector('#preset-alert-title').value=document.querySelector('#emergency-title').value;document.querySelector('#preset-alert-description').value=document.querySelector('#emergency-description').value;document.querySelector('#preset-alert-message').value=document.querySelector('#emergency-message').value;document.querySelector('#preset-priority').value=document.querySelector('#emergency-priority').value;document.querySelector('#preset-duration').value=document.querySelector('#emergency-duration').value;const selected=new Set([...document.querySelectorAll('#emergency-target-options input:checked')].map(input=>input.value));document.querySelectorAll('#preset-target-options input').forEach(input=>{input.checked=selected.has(input.value);});});

document.querySelector('[data-action="refresh-operations"]').addEventListener('click', async () => { await loadOperations(); showToast('Player-Status wurde aktualisiert.'); });

renderSource();
renderSlideLibrary();
renderPlaylist();
renderDisplays();
renderGroupManager();
renderSchedule();
renderOperations();
setInterval(() => { if (state.view === 'operations') renderOperations(); }, 30000);
setInterval(() => { if (state.view === 'displays' && !document.querySelector('dialog[open]')) loadDisplays(); }, 30000);
setInterval(() => {
  if (!slideDialog.open) return;
  document.querySelectorAll('.canvas-element.type-countdown').forEach(node => {
    const element = state.editorElements.find(item => item.id === node.dataset.editorElement);
    const countdown = node.querySelector('.canvas-countdown');
    if (element && countdown) countdown.textContent = `${element.prefix || ''}${countdownText(element)}${element.suffix || ''}`;
  });
  document.querySelectorAll('.canvas-ticker [data-ticker-countdown]').forEach(node => {
    try {
      const item = JSON.parse(decodeURIComponent(node.dataset.tickerCountdown || ''));
      node.textContent = `${item.prefix || ''}${countdownText(item)}${item.suffix || ''}`;
    } catch {}
  });
  document.querySelectorAll('[data-editor-wayfinding-countdown]').forEach(node => {
    try {
      const cell = JSON.parse(decodeURIComponent(node.dataset.editorWayfindingCountdown || ''));
      node.textContent = `${cell.prefix || ''}${countdownPreview(cell)}${cell.suffix || ''}`;
    } catch {}
  });
}, 1000);

initializeAuthentication();
})();
