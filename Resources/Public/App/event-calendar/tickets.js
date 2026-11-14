(() => {
  const status=document.querySelector('#status'),providers=document.querySelector('#providers'),retry=document.querySelector('#retry');
  const webUrl=value=>{if(typeof value!=='string'||value.length>2000||/[\u0000-\u0020\u007f\\]/.test(value))return null;try{const url=new URL(value);return ['http:','https:'].includes(url.protocol)&&!url.username&&!url.password?url:null;}catch{return null;}};
  async function load(){
    providers.hidden=true;providers.replaceChildren();retry.hidden=true;status.textContent='Ticketanbieter werden geladen …';
    const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),10000);
    try{
      const source=new URL(new URL(location.href).searchParams.get('source')||'',location.origin);
      const path=source.searchParams.get('path')||source.pathname;
      if(source.origin!==location.origin||source.username||source.password||!/^\/api\/public\/tickets\/[^/]{1,300}$/.test(path))throw new Error('invalid');
      const response=await fetch(source,{credentials:'omit',cache:'no-store',signal:controller.signal,referrerPolicy:'no-referrer',headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error(response.status===404||response.status===403?'unavailable':'request');
      const data=(await response.json()).data;
      if(!data||!Array.isArray(data.links)||!data.links.length||data.links.length>10)throw new Error('unavailable');
      const seen=new Set();
      for(const link of data.links){
        const url=webUrl(link?.url);if(!url||seen.has(url.href))continue;seen.add(url.href);
        const anchor=document.createElement('a'),name=document.createElement('span'),host=document.createElement('small');
        anchor.href=url.href;anchor.rel='noopener noreferrer';name.textContent=String(link.label||url.hostname).slice(0,80);host.textContent=url.hostname;
        anchor.append(name,host);providers.append(anchor);
      }
      if(!providers.childElementCount)throw new Error('unavailable');
      const title=String(data.title||'Veranstaltung').slice(0,300);document.querySelector('#event-title').textContent=title;document.title='Tickets · '+title;
      status.textContent='Bitte wähle deinen Ticketanbieter.';providers.hidden=false;
    }catch(error){status.textContent=error.message==='unavailable'?'Für diese Veranstaltung sind aktuell keine Ticketanbieter verfügbar.':'Die Ticketanbieter konnten nicht geladen werden. Bitte prüfe deine Internetverbindung und versuche es erneut.';retry.hidden=false;}
    finally{clearTimeout(timeout);}
  }
  retry.addEventListener('click',load);load();
})();
