/* Native tracks and the YouTube iframe command channel; no remote JavaScript. */
(function(root){
  const document=root.document;if(!document)return;
  const initialized=new WeakSet(),states=new WeakMap();let serial=0;
  function nativeCaptions(video){
    if(video.dataset.videoHideCaptions!=='true')return;
    for(const track of Array.from(video.textTracks||[]))if(['subtitles','captions'].includes(track.kind)&&track.mode!=='disabled')track.mode='disabled';
  }
  function send(frame,message){
    if(!frame.isConnected)return;
    let url;try{url=new URL(frame.src);}catch{return;}
    if(url.origin!=='https://www.youtube-nocookie.com'||!/^\/embed\/[\w-]{11}$/.test(url.pathname))return;
    frame.contentWindow?.postMessage(JSON.stringify({...message,id:states.get(frame)?.id}),url.origin);
  }
  function options(frame){
    send(frame,{event:'command',func:frame.dataset.videoMuted==='false'?'unMute':'mute',args:[]});
    if(frame.dataset.videoHideCaptions==='true')send(frame,{event:'command',func:'unloadModule',args:['captions']});
  }
  function init(node){
    if(initialized.has(node))return;initialized.add(node);
    if(node.matches('video[data-video-hide-captions]')){
      const apply=()=>nativeCaptions(node);node.addEventListener('loadedmetadata',apply);node.textTracks?.addEventListener?.('addtrack',apply);node.textTracks?.addEventListener?.('change',apply);apply();return;
    }
    if(!node.matches('iframe[data-kiosky-video="youtube"]'))return;
    states.set(node,{id:'kiosky-video-'+(++serial),ready:false,module:false});
    const load=()=>{const state=states.get(node);state.ready=false;state.module=false;send(node,{event:'listening'});for(const event of ['onReady','onApiChange'])send(node,{event:'command',func:'addEventListener',args:[event]});options(node);};
    node.addEventListener('load',load);load();
  }
  function scan(node){
    if(node.matches?.('video[data-video-hide-captions],iframe[data-kiosky-video="youtube"]'))init(node);
    node.querySelectorAll?.('video[data-video-hide-captions],iframe[data-kiosky-video="youtube"]').forEach(init);
  }
  root.addEventListener('message',event=>{
    if(event.origin!=='https://www.youtube-nocookie.com'||typeof event.data!=='string'||event.data.length>65536)return;
    let data;try{data=JSON.parse(event.data);}catch{return;}
    if(!data||!['onReady','onApiChange','infoDelivery'].includes(data.event))return;
    for(const frame of document.querySelectorAll('iframe[data-kiosky-video="youtube"]')){
      const state=states.get(frame);if(!state||event.source!==frame.contentWindow||data.id!==state.id)continue;
      if(data.event==='onApiChange'){if(state.module)return;state.module=true;options(frame);}
      else if(!state.ready){state.ready=true;options(frame);}
      break;
    }
  });
  new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes)scan(node);}).observe(document,{childList:true,subtree:true});
  root.KioskyVideoOptions={mute(container){for(const frame of container.querySelectorAll('iframe[data-kiosky-video="youtube"]')){frame.dataset.videoMuted='true';send(frame,{event:'command',func:'mute',args:[]});}}};
  scan(document);
})(globalThis);
