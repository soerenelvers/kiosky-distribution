/* Shared content-only menu editor. Authentication and authorization stay server-side. */
(() => {
'use strict';
function mount(root,options={}) {
const runtime=options.runtime||window.KioskyRuntime||{};
const scoped=selector=>selector.replace(/#([\w-]+)/g,'[data-menu-id="$1"]');
const $=selector=>root.querySelector(selector)||root.querySelector(scoped(selector));
const $$=selector=>root.querySelectorAll(root.classList.contains('is-embedded')?scoped(selector):selector);
if(root.menuEditor)return root.menuEditor;
let settings={defaultTemplate:'blue',defaultOrientation:'landscape',defaultTitle:'Speisekarte',defaultSubtitle:'Frisch für Sie zubereitet',defaultFooter:'Alle Preise in Euro.'};
const editorOnly=options.mode==='editor',managementOnly=options.mode==='manage';
const shareId=!root.classList.contains('is-embedded')?new URL(location.href).searchParams.get('freigabe'):null;
const shared=Boolean(shareId);let shareToken='';
if(shared){try{shareToken=sessionStorage.getItem(`kiosky-menu-share-${shareId}`)||'';}catch{}$('#share-logout').hidden=false;}
function forgetShare(){shareToken='';try{sessionStorage.removeItem(`kiosky-menu-share-${shareId}`);}catch{}$('#share-login').hidden=false;}

if(managementOnly)root.querySelector('.workspace').hidden=true;
let cards=[],templates=[],accounts=[],current=null,dirty=false,busy=false;
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
function setBusy(value){busy=value;$('#admin').inert=value;$('#editor').inert=value;$('#reload').disabled=value;$('#share-login').inert=value;}
function message(text,error=false){$('#message').textContent=text;$('#message').classList.toggle('error',error);}
async function api(path,method='GET',body){
  if(shared&&path.startsWith('/api/menus'))path=path.replace('/api/menus','/api/menu-share/menus');
  let url=runtime.apiBaseUrl?`${String(runtime.apiBaseUrl).replace(/\/+$/,'')}/${path.replace(/^\/+/, '')}`:path;
  if(runtime.apiEndpoint){const endpoint=new URL(runtime.apiEndpoint,location.href);endpoint.searchParams.set('path',path);url=endpoint.href;}
  const response=await fetch(url,{method,credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json',...(runtime.requestHeaders||{}),...(shared&&shareToken?{Authorization:`Bearer ${shareToken}`}:{})},...(body?{body:JSON.stringify(body)}:{})});
  const result=await response.json();
  if(!response.ok){if(shared&&response.status===401)forgetShare();else if(!shared&&(response.status===401||result.error?.code==='PASSWORD_CHANGE_REQUIRED'))$('#login').hidden=false;throw new Error(result.error?.message||`Anfrage fehlgeschlagen (${response.status}).`);}
  return result;
}
function template(style,orientation){
  if(String(style).startsWith('template:')){
    const selected=templates.find(item=>`template:${item.id}`===style);
    if(!selected)throw new Error('Die gespeicherte Vorlage ist nicht mehr verfügbar. Bitte eine andere Vorlage wählen.');
    const document=structuredClone(selected.document);delete document.menuTemplate;delete document.menu;return document;
  }
  const portrait=orientation==='portrait',background=style==='light'?'#f8f5ef':style==='dark'?'#111c2c':'#102f57',color=style==='light'?'#183551':'#ffffff',muted=style==='light'?'#45627c':'#bcd5f0',accent=style==='light'?'#1764a3':'#73bcff';
  const elements=[];
  const text=(field,x,y,width,height,size,ink=color,align='left')=>elements.push({id:`menu-${field}`,type:'text',menuField:field,text:'',x,y,width,height,fontSize:size,fontSizeUnit:'px',fontWeight:field==='title'?700:400,color:ink,background:'transparent',align,padding:0,rotation:0,opacity:100,responsiveText:true,autoFontSize:true});
  text('title',6,5,88,8,portrait?64:60,accent);text('subtitle',6,14,88,5,portrait?34:30,muted);
  for(let i=0;i<8;i++){
    const y=23+i*8.2;
    text(`items.${i}.name`,6,y,68,4.7,portrait?40:34);
    text(`items.${i}.price`,76,y,18,4.7,portrait?38:32,accent,'right');
    text(`items.${i}.description`,6,y+4.3,88,3.5,portrait?27:24,muted);
  }
  text('footer',6,91,88,5,portrait?26:23,muted);
  return {designOrientation:orientation,settings:{background:{mode:'color',color1:background},clock:{enabled:false},logo:{enabled:false}},elements};
}
function read(){return {title:$('#title').value,subtitle:$('#subtitle').value,footer:$('#footer').value,items:[...$('#items').children].map(row=>({name:row.querySelector('[data-name]').value,description:row.querySelector('[data-description]').value,price:row.querySelector('[data-price]').value}))};}
function preview(){
  if(!current)return;
  if(current.document.menuPdf){const image=window.document.createElement('img');image.src=current.document.elements.find(item=>item.menuPdfImage)?.src||'';image.alt='PDF-Speisekarte';image.style.cssText='width:100%;height:100%;object-fit:contain;display:block';const holder=$('#preview');holder.replaceChildren(image);holder.style.aspectRatio=current.orientation==='portrait'?'9/16':'16/9';return;}
  const menu=read(),document=current.document,fields={title:menu.title,subtitle:menu.subtitle,footer:menu.footer};
  for(let i=0;i<8;i++)for(const key of ['name','description','price'])fields[`items.${i}.${key}`]=menu.items[i]?.[key]||'';
  const holder=$('#preview');holder.replaceChildren();holder.style.backgroundColor=document.settings?.background?.color1||'#102f57';holder.style.aspectRatio=current.orientation==='portrait'?'9/16':'16/9';
  // Render text with textContent; menu input is never treated as HTML.
  for(const element of document.elements||[]){
    if(element.type!=='text')continue;
    const node=window.document.createElement('span');node.textContent=element.menuField?fields[element.menuField]||'':element.text||'';
    Object.assign(node.style,{left:`${element.x}%`,top:`${element.y}%`,width:`${element.width}%`,height:`${element.height}%`,fontSize:`${Number(element.fontSize)*(element.fontSizeUnit==='pt'?4/3:1)/(current.orientation==='portrait'?1080:1920)*100}cqw`,fontWeight:String(element.fontWeight||400),color:element.color||'#fff',justifyContent:element.align==='right'?'flex-end':element.align==='center'?'center':'flex-start'});holder.append(node);
  }
}
function rows(items){
  $('#items').innerHTML=items.map((item,i)=>`<div class="menu-item"><div class="row"><label>Speise ${i+1}<input data-name maxlength="50" required value="${esc(item.name)}"></label><label>Preis<input data-price maxlength="16" inputmode="decimal" placeholder="12,50 €" value="${esc(item.price)}"></label></div><label>Beschreibung<input data-description maxlength="90" value="${esc(item.description)}"></label><div class="row-tools"><button type="button" data-move="-1" ${i===0?'disabled':''}>Nach oben</button><button type="button" data-move="1" ${i===items.length-1?'disabled':''}>Nach unten</button><button type="button" data-remove>Speise entfernen</button></div></div>`).join('');
  $('#add').disabled=items.length>=8;preview();
}
function select(id){
  if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;
  current=cards.find(card=>card.id===id)||null;dirty=false;$('#editor').hidden=!current;
  for(const button of $('#cards').children)button.setAttribute('aria-current',String(button.dataset.id===id));
  if(!current)return;
  $('#card-name').textContent=current.name;$('#content').hidden=!!current.document.menuPdf;$('#pdf-replace').hidden=!current.document.menuPdf;if(current.document.menuPdf){$('#pdf-replace').reset();preview();return;}const menu=current.document.menu;$('#title').value=menu.title;$('#subtitle').value=menu.subtitle;$('#footer').value=menu.footer;rows(menu.items);
}
function assignment(){const account=accounts.find(item=>item.id===$('#access-user').value);$('#access-enabled').checked=!!account?.enabled;$('#assignments').innerHTML=cards.map(card=>`<label class="check"><input type="checkbox" value="${esc(card.id)}" ${account?.slideIds?.includes(card.id)?'checked':''}>${esc(card.name)}</label>`).join('')||'<p>Noch keine Karten angelegt.</p>';}
async function load(){
  if(busy)return;
  if(shared&&!shareToken){$('#share-login').hidden=false;$('#login').hidden=true;message('Bitte das Freigabepasswort eingeben.');return;}
  if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen und neu laden?'))return;
  setBusy(true);
  try{
    const result=await api('/api/menus');cards=result.data;templates=result.templates||[];$('#login').hidden=true;$('#share-login').hidden=true;$('#admin').hidden=!result.admin||editorOnly;settings={...settings,...result.settings};
    $('#create [name=template]').innerHTML='<option value="blue">Blau · Klar</option><option value="light">Hell · Bistro</option><option value="dark">Dunkel · Abendkarte</option>'+templates.map(item=>`<option value="template:${esc(item.id)}">${esc(item.name)} · ${item.orientation==='portrait'?'Hochformat':'Querformat'}</option>`).join('');
    $('#templates').innerHTML=templates.map(item=>`<div class="menu-template-row"><strong>${esc(item.name)}</strong><button type="button" data-edit-menu-template="${esc(item.id)}">Gestalten</button><button type="button" data-delete-menu-template="${esc(item.id)}">Archivieren</button></div>`).join('')||'<p>Noch keine eigene Vorlage gespeichert.</p>';
    if(!options.editTemplate){$('#create-template').hidden=true;$('#templates').querySelectorAll('button').forEach(button=>{button.hidden=true;});}
    if(settings.defaultTemplate.startsWith('template:')&&!templates.some(item=>`template:${item.id}`===settings.defaultTemplate))$('#create [name=template]').add(new Option('Standardvorlage nicht mehr verfügbar – andere wählen',settings.defaultTemplate));
    $('#create [name=template]').value=settings.defaultTemplate;$('#create [name=orientation]').value=templates.find(item=>`template:${item.id}`===settings.defaultTemplate)?.orientation||settings.defaultOrientation;
    $('#cards').innerHTML=cards.map(card=>`<button type="button" data-id="${esc(card.id)}">${esc(card.name)}</button>`).join('');
    if(result.admin&&!editorOnly){accounts=(await api('/api/menus/access')).data;const selected=$('#access-user').value;$('#access-user').innerHTML=accounts.map(account=>`<option value="${esc(account.id)}">${esc(account.displayName)}</option>`).join('');if(accounts.some(account=>account.id===selected))$('#access-user').value=selected;assignment();}
    const selected=current?.id;dirty=false;select(cards.some(card=>card.id===selected)?selected:cards[0]?.id);
    message(managementOnly?'Karten anlegen und Bearbeitungsrechte konfigurieren.':cards.length?'Karte auswählen, Inhalte bearbeiten und speichern.':'Keine Karten zugewiesen. Bitte die Administration kontaktieren.');
  }catch(error){if(!dirty){cards=[];current=null;$('#cards').replaceChildren();$('#editor').hidden=true;$('#admin').hidden=true;}message(error.message,true);}finally{setBusy(false);}
}
$('#share-login').onsubmit=async event=>{
  event.preventDefault();if(busy)return;
  const password=$('#share-password').value;setBusy(true);
  try{const result=await api('/api/menu-share/session','POST',{shareId,password});shareToken=result.token;try{sessionStorage.setItem(`kiosky-menu-share-${shareId}`,shareToken);}catch{}$('#share-login').hidden=true;setBusy(false);await load();}
  catch(error){message(error.message,true);}finally{$('#share-password').value='';setBusy(false);}
};
$('#share-logout').onclick=()=>{if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen und Zugang beenden?'))return;dirty=false;forgetShare();cards=[];current=null;$('#cards').replaceChildren();$('#editor').hidden=true;message('Zugang beendet.');};
$('#cards').onclick=event=>{const button=event.target.closest('[data-id]');if(button)select(button.dataset.id);};
$('#content').oninput=()=>{dirty=true;preview();};
$('#items').onclick=event=>{const button=event.target.closest('button');if(!button)return;const row=button.closest('.menu-item'),items=read().items,index=[...$('#items').children].indexOf(row);if(button.hasAttribute('data-remove'))items.splice(index,1);else{const other=index+Number(button.dataset.move);if(other<0||other>=items.length)return;[items[index],items[other]]=[items[other],items[index]];}dirty=true;rows(items);};
$('#add').onclick=()=>{const items=read().items;if(items.length<8){items.push({name:'',description:'',price:''});dirty=true;rows(items);$('#items').lastElementChild.querySelector('input').focus();}};
$('#discard').onclick=()=>{if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;dirty=false;select(current?.id);};
$('#content').onsubmit=async event=>{event.preventDefault();if(busy||!current)return;setBusy(true);const button=event.submitter;button.disabled=true;try{const result=await api(`/api/menus/${encodeURIComponent(current.id)}`,'PUT',{version:current.currentVersion,menu:read()});cards=cards.map(card=>card.id===result.slide.id?result.slide:card);dirty=false;select(result.slide.id);message('Gespeichert. Aktive Player übernehmen die Änderung bei der nächsten Synchronisierung.');}catch(error){message(error.message,true);}finally{setBusy(false);button.disabled=false;}};
async function pdfTools(){
  const url=new URL('pdf-import.js',runtime.assetBaseUrl?String(runtime.assetBaseUrl).replace(/\/?$/,'/'):document.baseURI);
  const script=document.querySelector('script[src*="app.js"]')||document.querySelector('script[src*="menus.js"]');
  const version=script?new URL(script.src).searchParams.get('v'):null;if(version)url.searchParams.set('v',version);
  return import(url.href);
}
$('#pdf-import').onsubmit=async event=>{
  event.preventDefault();if(busy)return;const form=event.currentTarget,file=form.elements.pdf.files[0],name=form.elements.name.value;let pdf,imported=0;setBusy(true);
  try{pdf=await (await pdfTools()).openMenuPdf(file);for(let page=1;page<=pdf.count;page++){message(`PDF-Seite ${page} von ${pdf.count} wird importiert …`);const rendered=await pdf.page(page);await api('/api/menus/pdf','POST',{name:`${name} · ${page}`, ...rendered});imported++;}form.reset();setBusy(false);await load();await options.onChanged?.();message(`${imported} PDF-Seiten als Entwürfe importiert. Veröffentlichung und Kanalzuordnung erfolgen in der Verwaltung.`);}
  catch(error){message(`${error.message}${imported?` Bereits importiert: ${imported} Seiten.`:''}`,true);}finally{setBusy(false);await pdf?.destroy();}
};
$('#pdf-replace').onsubmit=async event=>{
  event.preventDefault();if(busy||!current)return;const form=event.currentTarget;let pdf;setBusy(true);
  try{pdf=await (await pdfTools()).openMenuPdf(form.elements.pdf.files[0]);const rendered=await pdf.page(Number(form.elements.page.value),current.orientation);const result=await api(`/api/menus/${encodeURIComponent(current.id)}`,'PUT',{version:current.currentVersion,pdfPage:rendered.pdfPage});cards=cards.map(card=>card.id===result.slide.id?result.slide:card);dirty=false;select(result.slide.id);message('PDF-Seite gespeichert. Aktive Player übernehmen sie bei der nächsten Synchronisierung.');}
  catch(error){message(error.message,true);}finally{setBusy(false);await pdf?.destroy();}
};
$('#pdf-replace').onchange=()=>{dirty=true;};
$('#create').onsubmit=async event=>{event.preventDefault();if(busy)return;const createForm=event.currentTarget;setBusy(true);try{const form=new FormData(createForm),orientation=form.get('orientation');await api('/api/menus','POST',{name:form.get('name'),orientation,document:template(form.get('template'),orientation),menu:{title:settings.defaultTitle,subtitle:settings.defaultSubtitle,footer:settings.defaultFooter,items:[{name:'Gericht des Tages',description:'Beschreibung und Hinweise',price:'12,50 €'}]}});createForm.reset();setBusy(false);await load();await options.onChanged?.();}catch(error){message(error.message,true);}finally{setBusy(false);}};
$('#create-template').onsubmit=async event=>{
  event.preventDefault();if(busy||!options.editTemplate)return;
  const form=new FormData(event.currentTarget);setBusy(true);
  try{const result=await api('/api/menus/templates','POST',{name:form.get('name'),orientation:form.get('orientation'),document:template(form.get('style'),form.get('orientation'))});await options.editTemplate(result.template);setBusy(false);await load();}
  catch(error){message(error.message,true);}finally{setBusy(false);}
};
$('#templates').onclick=async event=>{
  const edit=event.target.closest('[data-edit-menu-template]'),remove=event.target.closest('[data-delete-menu-template]');
  if(busy||(!edit&&!remove))return;
  try{if(edit)await options.editTemplate?.(templates.find(item=>item.id===edit.dataset.editMenuTemplate));
    else if(confirm('Vorlage archivieren? Bereits angelegte Speisekarten bleiben erhalten.')){await api(`/api/menus/templates/${encodeURIComponent(remove.dataset.deleteMenuTemplate)}`,'DELETE');await load();}}
  catch(error){message(error.message,true);}
};
$('#create [name=template]').onchange=()=>{const selected=templates.find(item=>`template:${item.id}`===$('#create [name=template]').value);if(selected)$('#create [name=orientation]').value=selected.orientation;};
$('#access-user').onchange=assignment;
$('#access').onsubmit=async event=>{event.preventDefault();if(busy)return;setBusy(true);try{const payload={userId:$('#access-user').value,enabled:$('#access-enabled').checked,slideIds:[...$$('#assignments input:checked')].map(input=>input.value)};await api('/api/menus/access','PUT',payload);Object.assign(accounts.find(account=>account.id===payload.userId),payload);message('Speisekarten-Rechte gespeichert.');}catch(error){message(error.message,true);}finally{setBusy(false);}};
$('#reload').onclick=load;addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
if(runtime.platform&&runtime.platform!=='standalone')$('#login a').textContent='CMS-Anmeldung erneuern und /speisekarten erneut öffnen';
else $('#login a').onclick=()=>{try{sessionStorage.setItem('kiosky-menu-return','1');}catch{}};
root.menuEditor={reload:load,isDirty:()=>dirty};
load();
return root.menuEditor;
}
window.KioskyMenus={mount};
if(document.body.classList.contains('menu-app'))mount(document.body);
})();
