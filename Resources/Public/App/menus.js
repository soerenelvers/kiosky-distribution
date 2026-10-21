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
if(managementOnly)root.querySelector('.workspace').hidden=true;
let cards=[],accounts=[],current=null,dirty=false,busy=false;
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
function setBusy(value){busy=value;$('#admin').inert=value;$('#editor').inert=value;$('#reload').disabled=value;}
function message(text,error=false){$('#message').textContent=text;$('#message').classList.toggle('error',error);}
async function api(path,method='GET',body){
  let url=runtime.apiBaseUrl?`${String(runtime.apiBaseUrl).replace(/\/+$/,'')}/${path.replace(/^\/+/, '')}`:path;
  if(runtime.apiEndpoint){const endpoint=new URL(runtime.apiEndpoint,location.href);endpoint.searchParams.set('path',path);url=endpoint.href;}
  const response=await fetch(url,{method,credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json',...(runtime.requestHeaders||{})},...(body?{body:JSON.stringify(body)}:{})});
  const result=await response.json();
  if(!response.ok){if(response.status===401||result.error?.code==='PASSWORD_CHANGE_REQUIRED')$('#login').hidden=false;throw new Error(result.error?.message||`Anfrage fehlgeschlagen (${response.status}).`);}
  return result;
}
function template(style,orientation){
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
  $('#card-name').textContent=current.name;const menu=current.document.menu;$('#title').value=menu.title;$('#subtitle').value=menu.subtitle;$('#footer').value=menu.footer;rows(menu.items);
}
function assignment(){const account=accounts.find(item=>item.id===$('#access-user').value);$('#access-enabled').checked=!!account?.enabled;$('#assignments').innerHTML=cards.map(card=>`<label class="check"><input type="checkbox" value="${esc(card.id)}" ${account?.slideIds?.includes(card.id)?'checked':''}>${esc(card.name)}</label>`).join('')||'<p>Noch keine Karten angelegt.</p>';}
async function load(){
  if(busy)return;
  if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen und neu laden?'))return;
  setBusy(true);
  try{
    const result=await api('/api/menus');cards=result.data;$('#login').hidden=true;$('#admin').hidden=!result.admin||editorOnly;settings={...settings,...result.settings};$('#create [name=template]').value=settings.defaultTemplate;$('#create [name=orientation]').value=settings.defaultOrientation;
    $('#cards').innerHTML=cards.map(card=>`<button type="button" data-id="${esc(card.id)}">${esc(card.name)}</button>`).join('');
    if(result.admin&&!editorOnly){accounts=(await api('/api/menus/access')).data;const selected=$('#access-user').value;$('#access-user').innerHTML=accounts.map(account=>`<option value="${esc(account.id)}">${esc(account.displayName)}</option>`).join('');if(accounts.some(account=>account.id===selected))$('#access-user').value=selected;assignment();}
    const selected=current?.id;dirty=false;select(cards.some(card=>card.id===selected)?selected:cards[0]?.id);
    message(managementOnly?'Karten anlegen und Bearbeitungsrechte konfigurieren.':cards.length?'Karte auswählen, Inhalte bearbeiten und speichern.':'Keine Karten zugewiesen. Bitte die Administration kontaktieren.');
  }catch(error){if(!dirty){cards=[];current=null;$('#cards').replaceChildren();$('#editor').hidden=true;$('#admin').hidden=true;}message(error.message,true);}finally{setBusy(false);}
}
$('#cards').onclick=event=>{const button=event.target.closest('[data-id]');if(button)select(button.dataset.id);};
$('#content').oninput=()=>{dirty=true;preview();};
$('#items').onclick=event=>{const button=event.target.closest('button');if(!button)return;const row=button.closest('.menu-item'),items=read().items,index=[...$('#items').children].indexOf(row);if(button.hasAttribute('data-remove'))items.splice(index,1);else{const other=index+Number(button.dataset.move);if(other<0||other>=items.length)return;[items[index],items[other]]=[items[other],items[index]];}dirty=true;rows(items);};
$('#add').onclick=()=>{const items=read().items;if(items.length<8){items.push({name:'',description:'',price:''});dirty=true;rows(items);$('#items').lastElementChild.querySelector('input').focus();}};
$('#discard').onclick=()=>{if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;dirty=false;select(current?.id);};
$('#content').onsubmit=async event=>{event.preventDefault();if(busy||!current)return;setBusy(true);const button=event.submitter;button.disabled=true;try{const result=await api(`/api/menus/${encodeURIComponent(current.id)}`,'PUT',{version:current.currentVersion,menu:read()});cards=cards.map(card=>card.id===result.slide.id?result.slide:card);dirty=false;select(result.slide.id);message('Gespeichert. Aktive Player übernehmen die Änderung bei der nächsten Synchronisierung.');}catch(error){message(error.message,true);}finally{setBusy(false);button.disabled=false;}};
$('#create').onsubmit=async event=>{event.preventDefault();if(busy)return;const createForm=event.currentTarget;setBusy(true);try{const form=new FormData(createForm),orientation=form.get('orientation');await api('/api/menus','POST',{name:form.get('name'),orientation,document:template(form.get('template'),orientation),menu:{title:settings.defaultTitle,subtitle:settings.defaultSubtitle,footer:settings.defaultFooter,items:[{name:'Gericht des Tages',description:'Beschreibung und Hinweise',price:'12,50 €'}]}});createForm.reset();setBusy(false);await load();}catch(error){message(error.message,true);}finally{setBusy(false);}};
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
