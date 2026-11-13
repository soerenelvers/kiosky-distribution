(function () {
  'use strict';
  const locale = document.documentElement.lang || 'de-DE';
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const dateKey = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const day = key => { const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(key||'');if(!m)return null;const d=new Date(+m[1],+m[2]-1,+m[3],12);return dateKey(d)===key?d:null; };
  const plusDays = (d,n) => new Date(d.getFullYear(),d.getMonth(),d.getDate()+n,12);
  const monday = d => plusDays(d,-((d.getDay()+6)%7));
  const dayFormatters=new Map(),timeFormatter=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
  const formatDay = (d,options) => {const key=JSON.stringify(options);if(!dayFormatters.has(key))dayFormatters.set(key,new Intl.DateTimeFormat(locale,options));return dayFormatters.get(key).format(d);};
  const timeParts = value => {if(!value)return null;const d=new Date(value);if(!Number.isFinite(+d))return null;return timeFormatter.formatToParts(d);};
  const time = value => {const p=timeParts(value);return p?p.filter(v=>['hour','minute'].includes(v.type)).map(v=>v.value).join(':'):'';};
  const minutes = value => {const p=timeParts(value);return p?Number(p.find(v=>v.type==='hour').value)*60+Number(p.find(v=>v.type==='minute').value):0;};
  const searchText = event => [event.title,event.subtitle,event.description,event.publicNotes,event.organizer,event.eventType,event.venue,event.room,event.externalNumber,time(event.start),time(event.admission),time(event.breakStart),time(event.breakEnd)].filter(Boolean).join(' ').toLocaleLowerCase(locale);

  const initializedCalendars=new WeakSet();let dialogSequence=0;
  function initialize(root) {
    if(initializedCalendars.has(root))return;
    let payload;try{payload=JSON.parse(root.querySelector('[data-kiosky-data]').textContent);}catch{return;}
    initializedCalendars.add(root);root.dataset.kioskyReady='true';const detailTitleId=`kiosky-event-details-${++dialogSequence}-${Math.random().toString(36).slice(2,9)}`;
    const settings={showBreak:false,showSetupTime:false,showImage:false,showBoxOffice:false,showEventNumber:false,...payload.settings};
    for(const [key,parent] of Object.entries({showAdmission:'showTime',showEndTime:'showTime',showSubtitle:'showDescription',showPublicNotes:'showDescription'}))if(settings[key]===undefined)settings[key]=settings[parent]!==false;
    let view=['agenda','month','categories','week','day'].includes(settings.view)?settings.view:'agenda';
    const enabled=key=>settings[key]!==false;
    const fieldMap={showTime:['start'],showEndTime:['end'],showAdmission:['admission'],showBreak:['breakStart','breakEnd'],showSetupTime:['setup'],showImage:['imageUrl','imageEdit'],showBoxOffice:['boxOfficeAvailable','boxOfficeOpenAt'],showSubtitle:['subtitle'],showDescription:['description'],showPublicNotes:['publicNotes'],showEventNumber:['externalNumber'],showLocation:['venue','room'],showCategory:['eventType'],showOrganizer:['organizer'],showTickets:['ticketUrl'],showRemainingTickets:['remainingTickets'],showStatus:['status']};
    const events=(Array.isArray(payload.events)?payload.events:[]).filter(e=>day(e.date)&&e.title).sort((a,b)=>String(a.start||a.date).localeCompare(String(b.start||b.date))).map(event=>{const e={...event};if(e.status==='cancelled')delete e.ticketUrl;for(const [key,fields] of Object.entries(fieldMap))if(!enabled(key)&&!(key==='showStatus'&&e.status==='cancelled'))for(const field of fields)delete e[field];if(!enabled('showTime'))delete e.end;return e;});
    const $=selector=>root.querySelector(selector),all=selector=>root.querySelectorAll(selector);
    const grid=$('[data-kiosky-grid]'),results=$('[data-kiosky-results]'),caption=$('[data-kiosky-caption]'),search=$('[data-kiosky-search]'),jump=$('[data-kiosky-jump]'),categories=$('[data-kiosky-categories]'),focus=$('[data-kiosky-focus]'),week=$('[data-kiosky-week]'),strip=$('[data-kiosky-strip]'),dialog=$('[data-kiosky-dialog]');
    const today=new Date();today.setHours(12,0,0,0);
    let selected=dateKey(today),anchor=today,query='',category='',renderLimit=100,visibleEvents=events;
    const searchIndex=new Map(events.map(e=>[e,searchText(e)]));let dayCounts=new Map();
    root.dataset.view=view;
    const filtered=()=>visibleEvents;
    const where=e=>[e.venue,e.room].filter(Boolean).join(' · ');
    const canDetail=e=>enabled('showDetails')&&Boolean(e.title);
    const extraTimes=e=>[['Einlass',e.admission],['Pause',e.breakStart],['Fortsetzung',e.breakEnd],['Aufbau',e.setup],['Abendkasse ab',e.boxOfficeOpenAt]].filter(([,v])=>v).map(([label,v])=>`<span>${label}: ${escapeHtml(time(v))} Uhr</span>`).join('');
    const image=e=>{
      if(!e.imageUrl||!/^https?:\/\//i.test(e.imageUrl))return '';
      const crop=e.imageEdit;
      if(crop&&['x','y','width','height','naturalWidth','naturalHeight'].every(key=>Number.isFinite(crop[key]))&&crop.width>=5&&crop.height>=5&&crop.naturalWidth>0&&crop.naturalHeight>0){const width=crop.width*crop.naturalWidth/100,height=crop.height*crop.naturalHeight/100,x=crop.x*crop.naturalWidth/100,y=crop.y*crop.naturalHeight/100,clipId='calendar-crop-'+[x,y,width,height].join('_').replaceAll('.','d');return `<svg xmlns="http://www.w3.org/2000/svg" class="kiosky-calendar__event-image" data-event-image-crop viewBox="${crop.x*crop.naturalWidth/100} ${crop.y*crop.naturalHeight/100} ${width} ${height}" preserveAspectRatio="xMidYMid slice" role="img" aria-label="${escapeHtml(e.title||'Veranstaltungsbild')}" style="aspect-ratio:${width}/${height};overflow:hidden"><defs><clipPath id="${clipId}"><rect x="${x}" y="${y}" width="${width}" height="${height}"/></clipPath></defs><image clip-path="url(#${clipId})" href="${escapeHtml(e.imageUrl)}" width="${crop.naturalWidth}" height="${crop.naturalHeight}"/></svg>`;}
      return `<img class="kiosky-calendar__event-image" src="${escapeHtml(e.imageUrl)}" alt="" loading="lazy" decoding="async">`;
    };
    const ticketSoldOut=e=>e.status==='sold_out'||!/\b(?:nicht|not)\s+(?:ausverkauft|sold[ _-]*out)\b/i.test(String(e.remainingTickets??''))&&(/\b(?:ausverkauft|sold[ _-]*out)\b/i.test(String(e.remainingTickets??''))||/^(?:0(?:\s*(?:karten|tickets|restkarten))?|keine\s*(?:karten|tickets|restkarten))$/i.test(String(e.remainingTickets??'').trim()));
    const status=e=>(e.status==='cancelled'||enabled('showStatus')&&ticketSoldOut(e))?`<strong class="kiosky-calendar__status">${e.status==='cancelled'?'Abgesagt':'Ausverkauft'}</strong>`:'';
    const ticket=e=>enabled('showTickets')&&e.ticketUrl&&/^https?:\/\//i.test(e.ticketUrl)&&e.status!=='cancelled'?`<a class="kiosky-calendar__ticket" href="${escapeHtml(e.ticketUrl)}" rel="noopener">Tickets</a>`:'';
    const action=e=>`${canDetail(e)?`<button type="button" data-kiosky-detail="${escapeHtml(e.id)}">Details</button>`:''}${ticket(e)}`;
    function card(e,compact=false){
      const media=image(e);
      const metadata=[enabled('showTime')?`<span class="kiosky-calendar__time" data-calendar-field="time">${time(e.start)?`${time(e.start)}${e.end?`–${time(e.end)}`:''} Uhr`:'Ganztägig'}</span>`:'',enabled('showLocation')&&where(e)?`<span class="kiosky-calendar__location" data-calendar-field="location">${escapeHtml(where(e))}</span>`:'',enabled('showOrganizer')&&e.organizer?`<span class="kiosky-calendar__organizer" data-calendar-field="organizer">${escapeHtml(e.organizer)}</span>`:''].filter(Boolean).join('');
      const info=`${media?`<div class="kiosky-calendar__media">${media}</div>`:''}<div class="kiosky-calendar__summary">${enabled('showCategory')&&e.eventType?`<div data-calendar-field="category"><span class="kiosky-calendar__tag">${escapeHtml(e.eventType)}</span></div>`:''}<h4>${escapeHtml(e.title)}</h4>${e.subtitle?`<p class="kiosky-calendar__subtitle">${escapeHtml(e.subtitle)}</p>`:''}${status(e)}${metadata?`<div class="kiosky-calendar__metadata">${metadata}</div>`:''}${!compact&&e.description?`<p class="kiosky-calendar__excerpt">${escapeHtml(e.description.slice(0,220))}${e.description.length>220?'…':''}</p>`:''}${!compact&&e.publicNotes?`<p class="kiosky-calendar__excerpt">${escapeHtml(e.publicNotes)}</p>`:''}${!compact&&extraTimes(e)?`<div class="kiosky-calendar__times">${extraTimes(e)}</div>`:''}${!compact&&e.boxOfficeAvailable!==undefined?`<small>${e.boxOfficeAvailable?'Abendkasse verfügbar':'Keine Abendkasse'}</small>`:''}${!compact&&e.externalNumber?`<small>Veranstaltung: ${escapeHtml(e.externalNumber)}</small>`:''}${enabled('showRemainingTickets')&&e.remainingTickets?`<small>${escapeHtml(e.remainingTickets)}</small>`:''}${action(e)?`<div class="kiosky-calendar__actions">${action(e)}</div>`:''}</div>`;
      return `<article class="kiosky-calendar__event${compact?' is-compact':''}" data-calendar-event="${escapeHtml(e.id)}"><time class="kiosky-calendar__date" datetime="${escapeHtml(e.date)}"><strong>${formatDay(day(e.date),{day:'2-digit'})}</strong><span>${formatDay(day(e.date),{month:'short',weekday:'short'})}</span></time><div class="kiosky-calendar__event-content${media?' has-media':''}">${info}</div></article>`;
    }
    const list=matches=>matches.length?matches.slice(0,renderLimit).map(e=>card(e)).join('')+(matches.length>renderLimit?`<button type="button" data-kiosky-more>Weitere Veranstaltungen laden (${matches.length-renderLimit})</button>`:''):'<p class="kiosky-calendar__empty-message">Keine Veranstaltungen gefunden.</p>';
    function dayButton(d,compact=false){const key=dateKey(d),count=dayCounts.get(key)||0;return `<button type="button" data-kiosky-day="${key}" class="${key===selected?'is-selected ':''}${key===dateKey(today)?'is-today ':''}${count?'has-events':''}" aria-label="${escapeHtml(formatDay(d,{dateStyle:'full'}))}${count?`, ${count} Veranstaltungen`:''}"${key===selected?' aria-current="date"':''}>${compact?`<span>${formatDay(d,{weekday:'short'})}</span>`:''}<strong>${d.getDate()}</strong>${count?`<small>${count} ${count===1?'Termin':'Termine'}</small>`:''}</button>`;}
    function monthGrid(){const start=new Date(anchor.getFullYear(),anchor.getMonth(),1,12),offset=(start.getDay()+6)%7,length=new Date(anchor.getFullYear(),anchor.getMonth()+1,0).getDate();grid.innerHTML=Array.from({length:offset},()=>'<span class="kiosky-calendar__empty"></span>').join('')+Array.from({length},(_,i)=>dayButton(plusDays(start,i))).join('');}
    function categoryButtons(){if(view!=='categories'){categories.hidden=true;return;}categories.hidden=false;const types=[...new Set(events.map(e=>e.eventType).filter(Boolean))].sort((a,b)=>a.localeCompare(b,locale));categories.innerHTML=[['','Alle'],...types.map(t=>[t,t])].map(([value,label])=>`<button type="button" data-kiosky-category="${escapeHtml(value)}" aria-pressed="${category===value}">${escapeHtml(label)}</button>`).join('');}
    function renderWeek(){
      const start=monday(anchor),days=Array.from({length:7},(_,i)=>plusDays(start,i));
      const eventButton=(e,style='',extra='')=>{const tag=enabled('showDetails')?'button':'div';return `<${tag}${tag==='button'?` type="button" data-kiosky-open-event="${escapeHtml(e.id)}"`:''} class="kiosky-calendar__week-event ${extra}" style="${style}">${enabled('showTime')&&e.start?`<small>${time(e.start)}</small>`:''}<strong>${escapeHtml(e.title)}</strong>${enabled('showLocation')&&where(e)?`<small>${escapeHtml(where(e))}</small>`:''}${status(e)}</${tag}>`;};
      const columns=days.map(d=>{
        const dayEvents=filtered().filter(e=>e.date===dateKey(d));const timed=dayEvents.filter(e=>e.start).map(e=>({e,start:minutes(e.start),end:Math.max(minutes(e.start)+44,e.end?(String(e.end).slice(0,10)>e.date?1440:minutes(e.end)):minutes(e.start)+60),lane:0})).sort((a,b)=>a.start-b.start);
        const groups=[];let group=[],end=-1;
        for(const item of timed){if(item.start>=end&&group.length){groups.push(group);group=[];}group.push(item);end=Math.max(item.end,group.length===1?-1:end);}if(group.length)groups.push(group);
        const blocks=groups.flatMap(items=>{const lanes=[];for(const item of items){let lane=lanes.findIndex(t=>t<=item.start);if(lane<0)lane=lanes.length;lanes[lane]=item.end;item.lane=lane;}return items.map(item=>eventButton(item.e,`top:${Math.min(1396,item.start)}px;height:${Math.max(44,Math.min(1440,item.end)-item.start)}px;left:${item.lane/lanes.length*100}%;width:${100/lanes.length}%`));}).join('');
        return `<div class="kiosky-calendar__week-day"><div class="kiosky-calendar__week-heading">${dayButton(d,true)}${dayEvents.filter(e=>!e.start).map(e=>eventButton(e,'','is-all-day')).join('')}</div><div class="kiosky-calendar__week-hours">${blocks}</div></div>`;
      });
      week.innerHTML=`<div class="kiosky-calendar__week-scroll" tabindex="0" aria-label="Wochenkalender; seitlich und nach unten verschiebbar"><div class="kiosky-calendar__week-table"><div class="kiosky-calendar__hour-labels"><div></div>${Array.from({length:24},(_,i)=>`<span style="top:${i*60}px">${String(i).padStart(2,'0')}:00</span>`).join('')}</div>${columns.join('')}</div></div>`;
      const first=filtered().filter(e=>days.some(d=>dateKey(d)===e.date)&&e.start).map(e=>minutes(e.start));$('[class=kiosky-calendar__week-scroll]').scrollTop=Math.max(0,(first.length?Math.min(...first):480)-60);
    }
    function render(){
      visibleEvents=events.filter(e=>(!query||searchIndex.get(e).includes(query))&&(!category||e.eventType===category));dayCounts=new Map();for(const e of visibleEvents)dayCounts.set(e.date,(dayCounts.get(e.date)||0)+1);
      root.dataset.view=view;root.dataset.calendarQuery=query?'true':'false';categoryButtons();
      const inMonth=e=>e.date.startsWith(`${anchor.getFullYear()}-${String(anchor.getMonth()+1).padStart(2,'0')}-`),start=monday(anchor);
      caption.textContent=['week','day'].includes(view)?`${formatDay(start,{day:'numeric',month:'short'})} – ${formatDay(plusDays(start,6),{day:'numeric',month:'long',year:'numeric'})}`:view==='categories'?'Veranstaltungen nach Kategorien':formatDay(anchor,{month:'long',year:'numeric'});
      grid.hidden=view!=='month';root.querySelector('.kiosky-calendar__weekdays').hidden=view!=='month';week.hidden=view!=='week'||Boolean(query);strip.hidden=view!=='day'||Boolean(query);focus.hidden=view!=='day'||Boolean(query);results.hidden=view==='week'&&!query;
      all('[data-kiosky-year]').forEach(b=>{b.hidden=view!=='month';});all('[data-kiosky-month]').forEach(b=>{b.hidden=view==='categories';b.setAttribute('aria-label',`${Number(b.dataset.kioskyMonth)<0?'Zurück':'Weiter'}: ${['week','day'].includes(view)?'eine Woche':'ein Monat'}`);});
      jump.value=selected;
      if(view==='month'){monthGrid();const matches=query?filtered():filtered().filter(e=>e.date===selected);results.innerHTML=`<h3>${query?`${matches.length} Suchergebnisse`:escapeHtml(formatDay(day(selected),{dateStyle:'full'}))}</h3>${list(matches)}`;}
      if(view==='agenda'||view==='categories'){const matches=filtered().filter(e=>view==='categories'||query||inMonth(e));results.innerHTML=`<p class="kiosky-calendar__result-count">${matches.length} Veranstaltung${matches.length===1?'':'en'}</p>${list(matches)}`;}
      if(query&&['week','day'].includes(view)){const matches=filtered();results.innerHTML=`<p class="kiosky-calendar__result-count">${matches.length} Suchergebnisse</p>${list(matches)}`;}
      if(view==='day'&&!query){focus.innerHTML=`<div class="kiosky-calendar__focus-date"><strong>${day(selected).getDate()}</strong><span>${escapeHtml(formatDay(day(selected),{weekday:'long',month:'long',year:'numeric'}))}</span></div>`;results.innerHTML=list(filtered().filter(e=>e.date===selected));strip.innerHTML=`<button type="button" data-kiosky-shift="-7" aria-label="Vorherige Woche">‹</button>${Array.from({length:7},(_,i)=>dayButton(plusDays(start,i),true)).join('')}<button type="button" data-kiosky-shift="7" aria-label="Nächste Woche">›</button>`;}
      if(view==='week'&&!query)renderWeek();
      protectControls();
    }
    let detailTrigger=null;
    function closeDetails(){for(const key of ['display','visibility','opacity','transform'])dialog.style.removeProperty(key);if(typeof dialog.close==='function'&&dialog.open)dialog.close();else dialog.removeAttribute('open');dialog.removeAttribute('data-kiosky-fallback');detailTrigger?.focus();}
    function showDetails(event,trigger){
      if(!enabled('showDetails'))return;detailTrigger=trigger||document.activeElement;
      const facts=[['Datum',formatDay(day(event.date),{dateStyle:'full'})],...(enabled('showTime')&&event.start?[['Beginn',`${time(event.start)} Uhr`]]:[]),...(enabled('showEndTime')&&event.end?[['Ende',`${time(event.end)} Uhr`]]:[]),...[['Einlass',event.admission],['Pause',event.breakStart],['Fortsetzung',event.breakEnd],['Aufbau',event.setup],['Abendkasse ab',event.boxOfficeOpenAt]].filter(([,v])=>v).map(([label,v])=>[label,`${time(v)} Uhr`]),...(enabled('showLocation')&&where(event)?[['Ort',where(event)]]:[]),...(event.eventType?[['Kategorie',event.eventType]]:[]),...(event.organizer?[['Veranstalter',event.organizer]]:[]),...(event.remainingTickets?[['Ticketstatus',event.remainingTickets]]:[]),...(event.externalNumber?[['Veranstaltung',event.externalNumber]]:[]),...(event.boxOfficeAvailable!==undefined?[['Abendkasse',event.boxOfficeAvailable?'Verfügbar':'Nicht verfügbar']]:[])];
      const description=[event.description,event.publicNotes].filter(Boolean);
      $('[data-kiosky-detail-body]').innerHTML=`<div class="kiosky-calendar__detail-hero">${image(event)}</div><header class="kiosky-calendar__detail-heading">${event.eventType?`<span class="kiosky-calendar__tag">${escapeHtml(event.eventType)}</span>`:''}<h2 id="${detailTitleId}">${escapeHtml(event.title)}</h2>${event.subtitle?`<p class="kiosky-calendar__detail-subtitle">${escapeHtml(event.subtitle)}</p>`:''}${status(event)}</header><dl class="kiosky-calendar__detail-facts">${facts.map(([label,value])=>`<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}</dl><div class="kiosky-calendar__detail-description">${description.length?description.map(text=>`<p>${escapeHtml(text)}</p>`).join(''):'<p class="kiosky-calendar__detail-empty">Weitere Informationen folgen. Alle aktuell freigegebenen Angaben stehen oben.</p>'}</div>${ticket(event)?`<footer class="kiosky-calendar__detail-actions">${ticket(event)}</footer>`:''}`;
      for(const [key,value] of Object.entries({display:'block',visibility:'visible',opacity:'1',transform:'none'}))dialog.style.setProperty(key,value,'important');
      dialog.setAttribute('aria-labelledby',detailTitleId);dialog.setAttribute('aria-modal','true');
      if(typeof dialog.showModal==='function'){if(!dialog.open)dialog.showModal();}else{dialog.setAttribute('open','');dialog.dataset.kioskyFallback='true';}
      protectControls();dialog.querySelector('[data-kiosky-close]').focus();
    }
    dialog.addEventListener('close',()=>{for(const key of ['display','visibility','opacity','transform'])dialog.style.removeProperty(key);detailTrigger?.focus();});
    dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)closeDetails();}});
    dialog.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();closeDetails();}if(event.key==='Tab'){const nodes=[...dialog.querySelectorAll('button,a[href],input,select,textarea,[tabindex="0"]')].filter(node=>node.getClientRects().length),first=nodes[0],last=nodes.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}});

    root.addEventListener('click',event=>{
      const button=event.target.closest('button');if(!button||!root.contains(button))return;
      if(button.hasAttribute('data-kiosky-more')){renderLimit+=100;render();return;}
      if(button.hasAttribute('data-kiosky-detail')||button.hasAttribute('data-kiosky-open-event')){const e=events.find(e=>String(e.id)===String(button.dataset.kioskyDetail||button.dataset.kioskyOpenEvent));if(e){event.preventDefault();event.stopPropagation();showDetails(e,button);}return;}
      if(button.hasAttribute('data-kiosky-close')){event.preventDefault();event.stopPropagation();closeDetails();return;}
      renderLimit=100;
      if(button.hasAttribute('data-kiosky-category')){category=button.dataset.kioskyCategory;render();return;}
      if(button.dataset.kioskyDay){selected=button.dataset.kioskyDay;anchor=day(selected);query='';if(search)search.value='';render();return;}
      if(button.dataset.kioskyShift){anchor=plusDays(anchor,Number(button.dataset.kioskyShift));selected=dateKey(anchor);render();return;}
      if(button.dataset.kioskyMonth){const amount=Number(button.dataset.kioskyMonth);if(['day','week'].includes(view)){anchor=plusDays(anchor,amount*7);selected=dateKey(anchor);}else{anchor=new Date(anchor.getFullYear(),anchor.getMonth()+amount,1,12);selected=dateKey(anchor);}render();return;}
      if(button.dataset.kioskyYear){anchor=new Date(anchor.getFullYear()+Number(button.dataset.kioskyYear),anchor.getMonth(),1,12);selected=dateKey(anchor);render();return;}
      if(button.hasAttribute('data-kiosky-today')){anchor=today;selected=dateKey(today);query='';category='';if(search)search.value='';render();}
    });
    $('[data-kiosky-view]')?.addEventListener('change',event=>{if(['agenda','month','categories','week','day'].includes(event.target.value)){view=event.target.value;renderLimit=100;category='';render();}});
    jump.addEventListener('change',()=>{if(day(jump.value)){anchor=day(jump.value);selected=jump.value;render();}});
    search?.addEventListener('input',()=>{query=search.value.trim().toLocaleLowerCase(locale);renderLimit=100;render();});
    const upcoming=$('[data-kiosky-upcoming]');if(upcoming){const future=events.filter(e=>e.date>=dateKey(today)&&(!e.start||+new Date(e.start)>=Date.now())).slice(0,Number(settings.upcomingCount)||10);upcoming.innerHTML=future.length?future.map(e=>card(e,true)).join(''):'<p>Keine kommenden Veranstaltungen.</p>';}
    // Host themes often use !important button rules. Pin only the contrast-critical
    // properties; semantic tokens still distinguish tickets, dates and categories.
    function protectControls(){
      for(const node of all('button,input,select,a.kiosky-calendar__ticket')){
        for(const [key,value] of Object.entries({color:'var(--kiosky-button-fg,var(--kiosky-safe-control-text))','background-color':'var(--kiosky-button-bg,var(--kiosky-safe-control-bg))','background-image':'none','border-color':'var(--kiosky-button-border,var(--kiosky-safe-control-border))','text-shadow':'none',opacity:'1',filter:'none'}))node.style.setProperty(key,value,'important');
        for(const child of node.querySelectorAll('span,strong,small'))child.style.setProperty('color',child.matches('.kiosky-calendar__status')?'#78350f':'inherit','important');
      }
    }
    function themeColors(){
      const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const context=canvas.getContext('2d',{willReadFrequently:true});
      const rgb=value=>{context.clearRect(0,0,1,1);context.fillStyle='#1968d2';context.fillStyle=value;context.fillRect(0,0,1,1);return [...context.getImageData(0,0,1,1).data];};
      const luminance=c=>c.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
      const contrast=(a,b)=>{const x=luminance(rgb(a)),y=luminance(rgb(b));return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);};
      const readable=(preferred,bg,minimum=4.5)=>contrast(preferred,bg)>=minimum?preferred:contrast('#000000',bg)>contrast('#ffffff',bg)?'#000000':'#ffffff';
      let backdrop=matchMedia('(prefers-color-scheme:dark)').matches?[15,23,42]:[255,255,255];
      const ancestors=[];for(let node=root.parentElement;node;node=node.parentElement)ancestors.unshift(node);
      for(const node of ancestors){const c=rgb(getComputedStyle(node).backgroundColor),alpha=c[3]/255;backdrop=backdrop.map((v,i)=>Math.round(c[i]*alpha+v*(1-alpha)));}
      const custom=settings.colorMode==='custom',themeSurface=`rgb(${backdrop.join(', ')})`;
      const surface=custom?settings.backgroundColor||'#f8fafc':themeSurface,card=custom?settings.surfaceColor||'#ffffff':themeSurface;
      const dark=luminance(rgb(surface))<.18,preferred=custom?settings.textColor||'#0f172a':getComputedStyle(root.parentElement||document.body).color;
      const text=readable(preferred,surface),cardText=readable(preferred,card),muted=readable(dark?'#cbd5e1':'#475569',surface),cardMuted=readable(luminance(rgb(card))<.18?'#cbd5e1':'#475569',card);
      const computed=getComputedStyle(root),variable=names=>names.map(name=>computed.getPropertyValue(name).trim()).find(Boolean);
      const link=[...document.querySelectorAll('a[href]')].find(node=>!node.closest('[data-kiosky-event-calendar]')&&node.getClientRects().length);
      const accent=custom?settings.accentColor||'#1968d2':variable(['--kiosky-theme-accent','--wp--preset--color--primary','--bs-primary','--primary-color'])||(link?getComputedStyle(link).color:'#1968d2');
      // Composite transparent brand colors onto the opaque component surface.
      const opaque=value=>{const c=rgb(value),bg=rgb(card),a=c[3]/255;return `rgb(${c.slice(0,3).map((v,i)=>Math.round(v*a+bg[i]*(1-a))).join(', ')})`;};
      const action=opaque(accent),control=custom?opaque(settings.controlBackground||'#ffffff'):card;
      const chosen={accent,'accent-text':readable(custom?settings.accentTextColor||'#fff':'#fff',action),surface,'card-surface':card,text,muted,'card-text':cardText,'card-muted':cardMuted,'accent-ink':readable(action,card),'action-bg':action,'action-border':readable(action,card,3),'action-text':readable(custom?settings.accentTextColor||'#fff':'#fff',action),'safe-control-bg':control,'safe-control-text':readable(custom?settings.controlTextColor||text:text,control),'safe-control-border':readable(custom?settings.controlBorderColor||muted:muted,control,3),'line':dark?'#334155':'#dce3ed','dialog-surface':card,'week-surface':card};
      for(const [name,value] of Object.entries(chosen))root.style.setProperty('--kiosky-'+name,value);
      root.style.colorScheme=dark?'dark':'light';
    }
    themeColors();render();
    {let scheduled=false;const observer=new MutationObserver(()=>{if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;themeColors();});});observer.observe(document.documentElement,{attributes:true,attributeFilter:['class','style','data-theme','data-bs-theme']});observer.observe(document.body,{attributes:true,attributeFilter:['class','style','data-theme']});}
  }
  const start=()=>document.querySelectorAll('[data-kiosky-event-calendar]').forEach(initialize);
  const observe=()=>{start();const observer=new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes)if(node.nodeType===1){if(node.matches('[data-kiosky-event-calendar]'))initialize(node);node.querySelectorAll('[data-kiosky-event-calendar]').forEach(initialize);}});observer.observe(document.body,{childList:true,subtree:true});};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',observe);else observe();
}());
