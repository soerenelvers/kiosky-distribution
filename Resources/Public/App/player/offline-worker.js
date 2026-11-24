/* Only explicitly prepared player navigations/assets. CMS, admin and API requests pass through. */
const PREFIX='kiosky-offline-v2-',CONTROL=PREFIX+'control',META='/__kiosky_offline_configuration__',MAX_BYTES=64*1024*1024;
let preparing=false;
const canonical=value=>{const u=new URL(value);u.searchParams.delete('kiosky_start');return u.href;};
const same=url=>new URL(url).origin===self.location.origin;
const asset=url=>same(url)&&/\.(?:js|css|woff2?|ttf|otf|png|jpe?g|webp|gif|svg|mp4|webm)$/i.test(new URL(url).pathname)&&!/(?:\/api\/|\/wp-admin\/|\/typo3\/|\/wp-json\/)/.test(new URL(url).pathname);
self.addEventListener('install',event=>event.waitUntil(self.skipWaiting()));
self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));
async function configuration(){try{return await(await(await caches.open(CONTROL)).match(META)).json();}catch{return null;}}
async function bounded(response,limit){
 const declared=Number(response.headers.get('Content-Length'));
 if(Number.isFinite(declared)&&declared>limit){await response.body?.cancel();throw Error('Datei zu groß');}
 const reader=response.body?.getReader();if(!reader)throw Error('Kein lesbarer Stream');const chunks=[];let total=0;
 try{while(true){const {done,value}=await reader.read();if(done)break;total+=value.length;if(total>limit)throw Error('Datei zu groß');chunks.push(value);}}catch(error){await reader.cancel();throw error;}
 const bytes=new Uint8Array(total);let offset=0;for(const chunk of chunks){bytes.set(chunk,offset);offset+=chunk.length;}return bytes;
}
async function saveAsset(cache,url,cfg,previous){
 if(Date.now()>cfg.deadline)throw Error('Offline-Vorbereitung hat zu lange gedauert');
 if(cfg.assets.includes(url))return;cfg.assets.push(url);
 const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),15000);
 try{
  const saved=previous?await previous.match(url):null;
  const response=saved||await fetch(url,{credentials:'omit',redirect:'error',cache:'no-cache',signal:controller.signal});if(!response.ok||response.type==='opaque')throw Error('Nicht verfügbar');
  const bytes=await bounded(response,Math.min(32*1024*1024,MAX_BYTES-cfg.bytes));cfg.bytes+=bytes.length;
  await cache.put(url,new Response(bytes,{headers:response.headers}));
  if(/\.css$/i.test(new URL(url).pathname)){
   const text=new TextDecoder().decode(bytes);for(const match of text.matchAll(/url\(["']?([^\s)'";]+)["']?\)/g)){const child=new URL(match[1],url).href;if(asset(child))await saveAsset(cache,child,cfg,previous);}
  }
 }finally{clearTimeout(timeout);}
}
self.addEventListener('message',event=>{
 const value=event.data;if(!event.source?.url||!['kiosky:offline:prepare','kiosky:offline:clear'].includes(value?.type))return;
 event.waitUntil((async()=>{
  const old=await configuration();
  if(value.type==='kiosky:offline:clear'){
   if(old&&[old.page,old.browser].includes(canonical(event.source.url)))for(const name of await caches.keys())if(name.startsWith(PREFIX))await caches.delete(name);
   event.ports[0]?.postMessage({cleared:true});return;
  }
  if(preparing)return;const page=new URL(value.page),browser=new URL(value.browser);
  if(!same(page)||!same(browser)||canonical(event.source.url)!==canonical(page)||!page.searchParams.get('display')||!/^pc\./.test(page.searchParams.get('key')||''))return;
  preparing=true;const cfg={page:canonical(page),browser:canonical(browser),aliases:Array.isArray(value.aliases)?value.aliases.filter(hash=>typeof hash==='string'&&/^[a-f0-9]{64}$/.test(hash)).slice(0,5):[],assets:[],bytes:0,deadline:Date.now()+60000,cache:PREFIX+Date.now()+'-'+Math.random().toString(16).slice(2)},target=await caches.open(cfg.cache),previous=old&&old.page===cfg.page?await caches.open(old.cache):null;
  try{
   for(const entry of [cfg.page,cfg.browser]){const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),10000);try{const response=await fetch(entry,{credentials:'omit',redirect:'error',cache:'no-store',signal:controller.signal});if(!response.ok||!response.headers.get('Content-Type')?.includes('text/html'))throw Error('Player-Seite nicht verfügbar');const bytes=await bounded(response,3000000);cfg.bytes+=bytes.length;await target.put(entry,new Response(bytes,{headers:response.headers}));for(const match of new TextDecoder().decode(bytes).matchAll(/<(?:script|link)\b[^>]*(?:src|href)=["']([^"']+)["']/gi)){const resource=new URL(match[1],entry).href;if(asset(resource))await saveAsset(target,resource,cfg,previous);}}finally{clearTimeout(timer);}}
   for(const url of (value.assets||[]).slice(0,150)){if(asset(url))await saveAsset(target,url,cfg,previous);}
   await(await caches.open(CONTROL)).put(META,new Response(JSON.stringify(cfg)));
   for(const name of await caches.keys())if(name.startsWith(PREFIX)&&![CONTROL,cfg.cache].includes(name))await caches.delete(name);
   event.ports[0]?.postMessage({ready:true});
  }catch{await caches.delete(cfg.cache);event.ports[0]?.postMessage({ready:false});}finally{preparing=false;}
 })());
});
self.addEventListener('fetch',event=>{
 if(event.request.method!=='GET')return;
 const requested=new URL(event.request.url);
 const playerNavigation=event.request.mode==='navigate'&&same(requested)&&(requested.searchParams.has('display')&&requested.searchParams.has('key')||/\/(?:player\/browser\.html|kiosky-player-browser\/?)$/.test(requested.pathname));
 // Leave API/CMS traffic to the browser itself, including its native authentication
 // and request handlers. A fetch pass-through still intercepts those requests.
 if(!playerNavigation&&!asset(event.request.url))return;
 event.respondWith((async()=>{
  const cfg=await configuration(),url=canonical(event.request.url);if(!cfg)return fetch(event.request);
  let alias=false;if(playerNavigation&&url!==cfg.page&&url!==cfg.browser&&cfg.aliases?.length){const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(url));const hash=[...new Uint8Array(digest)].map(n=>n.toString(16).padStart(2,'0')).join('');alias=cfg.aliases.includes(hash);}
  const navigation=playerNavigation&&(url===cfg.page||url===cfg.browser||alias);
  const client=event.clientId?await self.clients.get(event.clientId):null,allowed=client&&[cfg.page,cfg.browser].includes(canonical(client.url));
  if(!navigation&&(!allowed||!cfg.assets.includes(event.request.url)))return fetch(event.request);
  const cache=await caches.open(cfg.cache);
  if(!navigation){const cached=await cache.match(event.request.url);if(cached){
    const range=event.request.headers.get('Range');if(range){const match=range.match(/^bytes=(\d+)-(\d*)$/);if(!match)return new Response(null,{status:416});const bytes=await cached.arrayBuffer(),start=Number(match[1]),end=match[2]?Math.min(Number(match[2]),bytes.byteLength-1):bytes.byteLength-1;if(start>end)return new Response(null,{status:416});return new Response(bytes.slice(start,end+1),{status:206,headers:{'Content-Type':cached.headers.get('Content-Type')||'application/octet-stream','Content-Range':`bytes ${start}-${end}/${bytes.byteLength}`,'Accept-Ranges':'bytes'}});}
    return cached;
  }}
  try{return await fetch(event.request);}catch(error){const cached=await cache.match(navigation?(alias?cfg.page:url):event.request.url);if(cached)return cached;throw error;}
 })());
});
