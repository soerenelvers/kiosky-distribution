/* Reload web-only players after an immutable server release. Never touch pairing. */
(() => {
  const version=value=>typeof value==='string'&&/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(value)?value.split('.').map(Number):null;
  function start({endpoint,currentVersion,intervalMs=300000,fetcher=window.fetch.bind(window),reload=()=>window.location.reload()}) {
    const current=version(currentVersion);if(!current)return()=>{};
    let cohort;try{cohort=localStorage.getItem('kiosky-update-cohort');if(!/^(?:[0-9]|[1-9][0-9])$/.test(cohort||'')){cohort=String(crypto.getRandomValues(new Uint32Array(1))[0]%100);localStorage.setItem('kiosky-update-cohort',cohort);}}catch{return()=>{};}
    let busy=false,stopped=false;
    const check=async()=>{
      if(stopped||busy||!navigator.onLine)return;
      busy=true;const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),8000);
      try{
        const response=await fetcher(endpoint+'?platform=web&cohort='+cohort,{cache:'no-store',credentials:'omit',redirect:'error',signal:controller.signal});
        if(!response.ok)return;
        const manifest=await response.json(),next=version(manifest.version);
        if(manifest.schema!==1||manifest.product!=='kiosky-player'||manifest.platform!=='web'||!next)return;
        for(let i=0;i<3;i++){if(next[i]<current[i])return;if(next[i]>current[i]){stopped=true;reload();return;}}
      }catch{/* Offline and old servers retain current playback. */}
      finally{clearTimeout(timeout);busy=false;}
    };
    const timer=setInterval(check,intervalMs);
    const stop=()=>{stopped=true;clearInterval(timer);};
    window.addEventListener('pagehide',stop,{once:true});
    return stop;
  }
  window.KioskyAutomaticUpdates=Object.freeze({start});
})();
