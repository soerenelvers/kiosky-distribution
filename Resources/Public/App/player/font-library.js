/* Shared, bounded font loading. Files are obtained through authenticated host transports. */
(() => {
 'use strict';
 const builtins=[['','Standard / vom Element'],['archivo','Archivo'],['arial','Arial'],['system','Systemschrift'],['verdana','Verdana'],['trebuchet','Trebuchet MS'],['georgia','Georgia'],['times','Times New Roman'],['courier','Courier New'],['plex','IBM Plex Mono']];
 const families={archivo:'Archivo,Arial,sans-serif',arial:'Arial,sans-serif',system:'system-ui,sans-serif',verdana:'Verdana,sans-serif',trebuchet:"'Trebuchet MS',sans-serif",georgia:'Georgia,serif',times:"'Times New Roman',serif",courier:"'Courier New',monospace",plex:"'IBM Plex Mono',monospace"};
 const valid=id=>typeof id==='string'&&/^[a-f0-9]{64}$/.test(id),escape=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let catalog=new Map(),configuration={};const pending=new Map(),faces=new Map(),retryAt=new Map();
 let offlineDatabase;
 async function cache(){
  if(!configuration.offline)return null;
  // IndexedDB survives navigation in WebKit contexts where CacheStorage may be ephemeral.
  if(globalThis.indexedDB)try{
   offlineDatabase??=new Promise((resolve,reject)=>{const request=indexedDB.open('kiosky-fonts-v1',1);request.onupgradeneeded=()=>request.result.createObjectStore('files',{keyPath:'id'});request.onerror=()=>reject(request.error);request.onblocked=()=>reject(new Error('Font cache blocked'));request.onsuccess=()=>{request.result.onversionchange=()=>{request.result.close();offlineDatabase=null;};resolve(request.result);};});
   const database=await offlineDatabase;
   const transaction=(mode,run)=>new Promise((resolve,reject)=>{const tx=database.transaction('files',mode),store=tx.objectStore('files');let result;tx.oncomplete=()=>resolve(result);tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error);run(store,value=>{result=value;});});
   const id=key=>String(key).split('/').at(-1);
   return {
    match:async key=>{const row=await transaction('readonly',(store,done)=>{const request=store.get(id(key));request.onsuccess=()=>done(request.result);});return row?new Response(JSON.stringify(row.file)):undefined;},
    put:async(key,response)=>{const file=await response.json();await transaction('readwrite',store=>{store.put({id:id(key),savedAt:Date.now(),file});const request=store.getAll();request.onsuccess=()=>{const rows=request.result.sort((a,b)=>a.savedAt-b.savedAt);for(const row of rows.slice(0,Math.max(0,rows.length-20)))store.delete(row.id);};});},
    delete:key=>transaction('readwrite',store=>{store.delete(id(key));}),
    keys:()=>transaction('readonly',(store,done)=>{const request=store.getAllKeys();request.onsuccess=()=>done(request.result);}),
   };
  }catch{offlineDatabase=null;}
  if(globalThis.caches)try{return await caches.open('kiosky-fonts-v1');}catch{}
  return null;
 }
 async function ensure(id){
  if(pending.has(id))return pending.get(id);
  if(!valid(id)||faces.has(id)||!catalog.has(id)||typeof configuration.fetchFile!=='function')return faces.has(id);
  if((retryAt.get(id)||0)>Date.now())return false;
  const work=(async()=>{
   try{
    const storage=await cache(),key=new URL('./__kiosky_font_cache__/'+id,location.href).href;let file;
    if(storage)try{file=await(await storage.match(key))?.json();}catch{}
    const validate=async payload=>{if(payload?.font?.id!==id||typeof payload.data!=='string'||payload.data.length>2700000)throw new Error();const buffer=Uint8Array.from(atob(payload.data),c=>c.charCodeAt(0));if(buffer.length!==payload.font.size||buffer.length>2000000)throw new Error();const digest=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',buffer)),b=>b.toString(16).padStart(2,'0')).join('');if(digest!==id)throw new Error();return buffer;};
    let bytes;
    if(file)try{bytes=await validate(file);}catch{file=null;try{await storage?.delete(key);}catch{}}
    if(!bytes){file=await configuration.fetchFile(id);bytes=await validate(file);}
    const face=new FontFace('KioskyFont_'+id,bytes,{display:'swap'});await face.load();document.fonts.add(face);faces.set(id,face);retryAt.delete(id);
    if(storage)try{await storage.put(key,new Response(JSON.stringify(file),{headers:{'Content-Type':'application/json'}}));const keys=await storage.keys();for(const old of keys.slice(0,Math.max(0,keys.length-20)))await storage.delete(old);}catch{}
    for(const [old,loaded] of faces)if(faces.size>20&&!catalog.has(old)){document.fonts.delete(loaded);faces.delete(old);}
    configuration.afterLoad?.();return true;
   }catch{retryAt.set(id,Date.now()+30000);configuration.onError?.('Eine eigene Schrift konnte nicht geladen werden. Die Ersatzschrift bleibt sichtbar.');return false;}
  })().finally(()=>pending.delete(id));pending.set(id,work);return work;
 }
 const api={
  configure(options){configuration={...configuration,...options};},
  setCatalog(fonts){catalog=new Map((Array.isArray(fonts)?fonts:[]).filter(font=>valid(font?.id)&&typeof font.name==='string'&&font.name.length<=100).slice(0,20).map(font=>[font.id,font]));},
  catalog(){return [...catalog.values()];},
  options(selected='',label='Standard / vom Element'){
   const choices=[...builtins.map(([id,name])=>[id,id?name:label]),...[...catalog.values()].map(font=>[font.id,font.name])];
   if(selected&&!choices.some(([id])=>id===selected)&&valid(selected))choices.push([selected,'Eigene Schrift (derzeit nicht verfügbar)']);
   return choices.map(([id,name])=>`<option value="${escape(id)}" ${selected===id?'selected':''}>${escape(name)}</option>`).join('');
  },
  style(id){if(typeof id!=='string')return '';const family=(Object.hasOwn(families,id)?families[id]:'')||(valid(id)?`KioskyFont_${id},sans-serif`:'');if(!family)return '';if(valid(id))void ensure(id);return `font-family:${family};--element-font-family:${family}`;},
  ensure,
 };
 globalThis.KioskyFonts=Object.freeze(api);
})();
