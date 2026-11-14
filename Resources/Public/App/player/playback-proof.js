(() => {
  /** Account visible, ready playback with a monotonic clock. A receipt never contains a player key. */
  function createPlaybackProof(options){
    const clock=options.now||Date.now,mono=options.monotonic||(()=>performance.now()),uuid=()=>{try{if(globalThis.crypto?.randomUUID)return globalThis.crypto.randomUUID();}catch{}return 'proof_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2)+'_'+Math.random().toString(36).slice(2);};
    let pending=[],item=null,playId='',segment=null,last=mono();
    try{const saved=JSON.parse(options.storage.getItem(options.key)||'[]');if(Array.isArray(saved))pending=saved.filter(r=>r&&typeof r.id==='string'&&typeof r.grant==='string').slice(-500);}catch{}
    const persist=()=>{try{options.storage.setItem(options.key,JSON.stringify(pending.slice(-500)));}catch{}};
    const allowed=()=>{
      if(!item||!options.grant(item)||!options.visible())return false;
      const root=options.root();if(!root)return false;
      return [...root.querySelectorAll('img')].every(img=>img.complete&&img.naturalWidth>0)&&[...root.querySelectorAll('video')].every(video=>video.readyState>=3&&!video.paused&&!video.ended);
    };
    function seal(){
      if(segment&&segment.duration>=1000){pending.push({id:uuid(),playId,grant:segment.grant,startedAt:new Date(segment.start).toISOString(),endedAt:new Date(segment.start+segment.duration).toISOString(),durationMs:Math.round(segment.duration)});pending=pending.slice(-500);persist();}segment=null;
    }
    function tick(){const at=mono(),delta=Math.max(0,at-last);last=at;
      if(!allowed()){seal();return;}
      if(!segment){segment={start:clock(),duration:0,grant:options.grant(item)};return;}
      if(delta>5000){seal();segment={start:clock(),duration:0,grant:options.grant(item)};return;}
      segment.duration+=delta;if(segment.duration>=15000)seal();
    }
    return {
      show(next){tick();seal();item=next;playId=uuid();last=mono();tick();},
      tick,
      batch(){tick();seal();tick();return pending.slice(0,20);},
      acknowledge(ids){if(!Array.isArray(ids))return;const accepted=new Set(ids);pending=pending.filter(r=>!accepted.has(r.id));persist();},
      stop(){tick();seal();item=null;},
      pending:()=>pending.length
    };
  }
  globalThis.KioskyPlaybackProof={create:createPlaybackProof};
})();
