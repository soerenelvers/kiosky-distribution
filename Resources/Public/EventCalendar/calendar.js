(function () {
  'use strict';
  const locale = document.documentElement.lang || 'de-DE';
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const dateKey = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const day = key => { const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(key||'');if(!m)return null;const d=new Date(+m[1],+m[2]-1,+m[3],12);return dateKey(d)===key?d:null; };
  const plusDays = (d,n) => new Date(d.getFullYear(),d.getMonth(),d.getDate()+n,12);
  const monday = d => plusDays(d,-((d.getDay()+6)%7));
  const formatDay = (d,options) => new Intl.DateTimeFormat(locale,options).format(d);
  const timeParts = value => {if(!value)return null;const d=new Date(value);if(!Number.isFinite(+d))return null;return new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(d);};
  const time = value => {const p=timeParts(value);return p?p.filter(v=>['hour','minute'].includes(v.type)).map(v=>v.value).join(':'):'';};
  const minutes = value => {const p=timeParts(value);return p?Number(p.find(v=>v.type==='hour').value)*60+Number(p.find(v=>v.type==='minute').value):0;};
  const searchText = event => [event.title,event.subtitle,event.description,event.publicNotes,event.organizer,event.eventType,event.venue,event.room].filter(Boolean).join(' ').toLocaleLowerCase(locale);

  function initialize(root) {
    if(root.dataset.kioskyReady)return;
    let payload;try{payload=JSON.parse(root.querySelector('[data-kiosky-data]').textContent);}catch{return;}
    root.dataset.kioskyReady='true';
    const settings=payload.settings||{},view=['agenda','month','categories','week','day'].includes(settings.view)?settings.view:'agenda';
    const events=(Array.isArray(payload.events)?payload.events:[]).filter(e=>day(e.date)&&e.title).sort((a,b)=>String(a.start||a.date).localeCompare(String(b.start||b.date)));
    const enabled=key=>settings[key]!==false;
    const $=selector=>root.querySelector(selector),all=selector=>root.querySelectorAll(selector);
    const grid=$('[data-kiosky-grid]'),results=$('[data-kiosky-results]'),caption=$('[data-kiosky-caption]'),search=$('[data-kiosky-search]'),jump=$('[data-kiosky-jump]'),categories=$('[data-kiosky-categories]'),focus=$('[data-kiosky-focus]'),week=$('[data-kiosky-week]'),strip=$('[data-kiosky-strip]'),dialog=$('[data-kiosky-dialog]');
    const today=new Date();today.setHours(12,0,0,0);
    let selected=dateKey(today),anchor=today,query='',category='';
    root.dataset.view=view;
    const filtered=()=>events.filter(e=>(!query||searchText(e).includes(query))&&(!category||e.eventType===category));
    const where=e=>[e.venue,e.room].filter(Boolean).join(' · ');
    const canDetail=e=>enabled('showDetails')&&((enabled('showDescription')&&(e.description||e.subtitle||e.publicNotes))||(enabled('showTime')&&e.admission));
    const status=e=>enabled('showStatus')&&['cancelled','sold_out'].includes(e.status)?`<strong class="kiosky-calendar__status">${e.status==='cancelled'?'Abgesagt':'Ausverkauft'}</strong>`:'';
    const ticket=e=>enabled('showTickets')&&e.ticketUrl&&/^https?:\/\//i.test(e.ticketUrl)&&e.status!=='cancelled'?`<a class="kiosky-calendar__ticket" href="${escapeHtml(e.ticketUrl)}" rel="noopener">Tickets / Details</a>`:'';
    const action=e=>`${canDetail(e)?`<button type="button" data-kiosky-detail="${escapeHtml(e.id)}">Details</button>`:''}${ticket(e)}`;
    function card(e,compact=false){
      const columns=[...(enabled('showTime')?['minmax(3.8rem,.6fr)']:[]),'minmax(0,2fr)',...(enabled('showLocation')?['minmax(0,1fr)']:[]),...(enabled('showCategory')?['minmax(0,.7fr)']:[]),...(enabled('showOrganizer')?['minmax(0,1fr)']:[]),...(action(e)?['auto']:[])];
      const info=`${enabled('showTime')?`<div class="kiosky-calendar__time" data-calendar-field="time">${time(e.start)?`${time(e.start)}${e.end?`–${time(e.end)}`:''} Uhr`:'Ganztägig'}</div>`:''}<div class="kiosky-calendar__summary"><h4>${escapeHtml(e.title)}</h4>${status(e)}${enabled('showRemainingTickets')&&e.remainingTickets?`<small>${escapeHtml(e.remainingTickets)}</small>`:''}</div>${enabled('showLocation')?`<div class="kiosky-calendar__location" data-calendar-field="location">${escapeHtml(where(e))}</div>`:''}${enabled('showCategory')?`<div data-calendar-field="category">${e.eventType?`<span class="kiosky-calendar__tag">${escapeHtml(e.eventType)}</span>`:''}</div>`:''}${enabled('showOrganizer')?`<div class="kiosky-calendar__organizer" data-calendar-field="organizer">${escapeHtml(e.organizer)}</div>`:''}${action(e)?`<div class="kiosky-calendar__actions">${action(e)}</div>`:''}`;
      return `<article class="kiosky-calendar__event${compact?' is-compact':''}" data-calendar-event="${escapeHtml(e.id)}"><time class="kiosky-calendar__date" datetime="${escapeHtml(e.date)}"><strong>${formatDay(day(e.date),{day:'2-digit'})}</strong><span>${formatDay(day(e.date),{month:'short',weekday:'short'})}</span></time><div class="kiosky-calendar__event-content" style="grid-template-columns:${columns.join(' ')}">${info}</div></article>`;
    }
    const list=matches=>matches.length?matches.slice(0,100).map(e=>card(e)).join(''):'<p class="kiosky-calendar__empty-message">Keine Veranstaltungen gefunden.</p>';
    function dayButton(d,compact=false){const key=dateKey(d),count=filtered().filter(e=>e.date===key).length;return `<button type="button" data-kiosky-day="${key}" class="${key===selected?'is-selected ':''}${key===dateKey(today)?'is-today ':''}${count?'has-events':''}" aria-label="${escapeHtml(formatDay(d,{dateStyle:'full'}))}${count?`, ${count} Veranstaltungen`:''}"${key===selected?' aria-current="date"':''}>${compact?`<span>${formatDay(d,{weekday:'short'})}</span>`:''}<strong>${d.getDate()}</strong>${count?`<small>${count} ${count===1?'Termin':'Termine'}</small>`:''}</button>`;}
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
      root.dataset.calendarQuery=query?'true':'false';categoryButtons();
      const inMonth=e=>e.date.startsWith(`${anchor.getFullYear()}-${String(anchor.getMonth()+1).padStart(2,'0')}-`),start=monday(anchor);
      caption.textContent=['week','day'].includes(view)?`${formatDay(start,{day:'numeric',month:'short'})} – ${formatDay(plusDays(start,6),{day:'numeric',month:'long',year:'numeric'})}`:view==='categories'?'Veranstaltungen nach Kategorien':formatDay(anchor,{month:'long',year:'numeric'});
      grid.hidden=view!=='month';root.querySelector('.kiosky-calendar__weekdays').hidden=view!=='month';week.hidden=view!=='week';strip.hidden=view!=='day';focus.hidden=view!=='day';results.hidden=view==='week';
      all('[data-kiosky-year]').forEach(b=>{b.hidden=view!=='month';});all('[data-kiosky-month]').forEach(b=>{b.hidden=view==='categories';b.setAttribute('aria-label',`${Number(b.dataset.kioskyMonth)<0?'Zurück':'Weiter'}: ${['week','day'].includes(view)?'eine Woche':'ein Monat'}`);});
      jump.value=selected;
      if(view==='month'){monthGrid();const matches=query?filtered():filtered().filter(e=>e.date===selected);results.innerHTML=`<h3>${query?`${matches.length} Suchergebnisse`:escapeHtml(formatDay(day(selected),{dateStyle:'full'}))}</h3>${list(matches)}`;}
      if(view==='agenda'||view==='categories'){const matches=filtered().filter(e=>view==='categories'||query||inMonth(e));results.innerHTML=`<p class="kiosky-calendar__result-count">${matches.length} Veranstaltung${matches.length===1?'':'en'}</p>${list(matches)}`;}
      if(view==='day'){focus.innerHTML=`<div class="kiosky-calendar__focus-date"><strong>${day(selected).getDate()}</strong><span>${escapeHtml(formatDay(day(selected),{weekday:'long',month:'long',year:'numeric'}))}</span></div>`;results.innerHTML=list(filtered().filter(e=>e.date===selected));strip.innerHTML=`<button type="button" data-kiosky-shift="-7" aria-label="Vorherige Woche">‹</button>${Array.from({length:7},(_,i)=>dayButton(plusDays(start,i),true)).join('')}<button type="button" data-kiosky-shift="7" aria-label="Nächste Woche">›</button>`;}
      if(view==='week')renderWeek();
    }
    function showDetails(event){
      const pieces=[`<h2>${escapeHtml(event.title)}</h2>`,status(event),`<p>${escapeHtml(formatDay(day(event.date),{dateStyle:'full'}))}</p>`,enabled('showTime')&&event.start?`<p>${time(event.start)}${event.end?`–${time(event.end)}`:''} Uhr${event.admission?` · Einlass ${time(event.admission)} Uhr`:''}</p>`:'',enabled('showLocation')&&where(event)?`<p data-calendar-field="location">${escapeHtml(where(event))}</p>`:'',enabled('showCategory')&&event.eventType?`<p>${escapeHtml(event.eventType)}</p>`:'',enabled('showOrganizer')&&event.organizer?`<p>${escapeHtml(event.organizer)}</p>`:'',enabled('showDescription')?[event.subtitle,event.description,event.publicNotes].filter(Boolean).map(text=>`<p>${escapeHtml(text)}</p>`).join(''):'',enabled('showRemainingTickets')&&event.remainingTickets?`<p>${escapeHtml(event.remainingTickets)}</p>`:'',ticket(event)];
      $('[data-kiosky-detail-body]').innerHTML=pieces.join('');dialog.showModal();
    }
    root.addEventListener('click',event=>{
      const button=event.target.closest('button');if(!button||!root.contains(button))return;
      if(button.hasAttribute('data-kiosky-detail')||button.hasAttribute('data-kiosky-open-event')){const e=events.find(e=>e.id===(button.dataset.kioskyDetail||button.dataset.kioskyOpenEvent));if(e)showDetails(e);return;}
      if(button.hasAttribute('data-kiosky-close')){dialog.close();return;}
      if(button.hasAttribute('data-kiosky-category')){category=button.dataset.kioskyCategory;render();return;}
      if(button.dataset.kioskyDay){selected=button.dataset.kioskyDay;anchor=day(selected);query='';if(search)search.value='';render();return;}
      if(button.dataset.kioskyShift){anchor=plusDays(anchor,Number(button.dataset.kioskyShift));selected=dateKey(anchor);render();return;}
      if(button.dataset.kioskyMonth){const amount=Number(button.dataset.kioskyMonth);if(['day','week'].includes(view)){anchor=plusDays(anchor,amount*7);selected=dateKey(anchor);}else{anchor=new Date(anchor.getFullYear(),anchor.getMonth()+amount,1,12);selected=dateKey(anchor);}render();return;}
      if(button.dataset.kioskyYear){anchor=new Date(anchor.getFullYear()+Number(button.dataset.kioskyYear),anchor.getMonth(),1,12);selected=dateKey(anchor);render();return;}
      if(button.hasAttribute('data-kiosky-today')){anchor=today;selected=dateKey(today);query='';category='';if(search)search.value='';render();}
    });
    jump.addEventListener('change',()=>{if(day(jump.value)){anchor=day(jump.value);selected=jump.value;render();}});
    search?.addEventListener('input',()=>{query=search.value.trim().toLocaleLowerCase(locale);render();});
    const upcoming=$('[data-kiosky-upcoming]');if(upcoming){const future=events.filter(e=>e.date>=dateKey(today)&&(!e.start||+new Date(e.start)>=Date.now())).slice(0,Number(settings.upcomingCount)||10);upcoming.innerHTML=future.length?future.map(e=>card(e,true)).join(''):'<p>Keine kommenden Veranstaltungen.</p>';}
    function themeColors(){
      let parent=root.parentElement,surface='Canvas';while(parent){const background=getComputedStyle(parent).backgroundColor;if(background&&background!=='rgba(0, 0, 0, 0)'&&background!=='transparent'){surface=background;break;}parent=parent.parentElement;}
      root.style.setProperty('--kiosky-week-surface',surface);root.style.setProperty('--kiosky-dialog-surface',surface);
      if(settings.colorMode==='custom')return;
      const computed=getComputedStyle(root);
      const variable=names=>names.map(name=>computed.getPropertyValue(name).trim()).find(Boolean);
      const outside=selector=>[...document.querySelectorAll(selector)].find(node=>!node.closest('[data-kiosky-event-calendar]')&&node.getClientRects().length);
      const link=outside('a[href]'),button=outside('button,input[type=submit],.button,.btn');
      const accent=variable(['--kiosky-theme-accent','--wp--preset--color--primary','--bs-primary','--primary-color'])||(link?getComputedStyle(link).color:'#1968d2');
      const buttonStyle=button?getComputedStyle(button):null,bg=buttonStyle?.backgroundColor;
      const chosen={'accent':accent,'control-text':buttonStyle?.color||computed.color,'control-bg':bg&&bg!=='rgba(0, 0, 0, 0)'?bg:'transparent','control-border':buttonStyle?.borderColor||computed.color};
      const canvas=document.createElement('canvas').getContext('2d');canvas.fillStyle=accent;const parsed=canvas.fillStyle;let rgb;if(/^#[0-9a-f]{6}$/i.test(parsed))rgb=[1,3,5].map(i=>parseInt(parsed.slice(i,i+2),16));else rgb=parsed.match(/[\d.]+/g)?.slice(0,3).map(Number);if(rgb){const linear=rgb.map(c=>{c/=255;return c<=.04045?c/12.92:((c+.055)/1.055)**2.4;});const lum=linear[0]*.2126+linear[1]*.7152+linear[2]*.0722;chosen['accent-text']=lum>.179?'#111111':'#ffffff';}
      Object.entries(chosen).forEach(([name,value])=>{if(value)root.style.setProperty('--kiosky-'+name,value);});
    }
    themeColors();render();
    {let scheduled=false;const observer=new MutationObserver(()=>{if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;themeColors();});});observer.observe(document.documentElement,{attributes:true,attributeFilter:['class','style','data-theme','data-bs-theme']});observer.observe(document.body,{attributes:true,attributeFilter:['class','style','data-theme']});}
  }
  const start=()=>document.querySelectorAll('[data-kiosky-event-calendar]').forEach(initialize);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
}());
