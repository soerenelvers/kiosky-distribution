/* One placement model for editor, thumbnails, channel preview and player. */
(function(root){
  const number=(value,fallback,min,max)=>{const n=Number(value);return value==null||!Number.isFinite(n)?fallback:Math.max(min,Math.min(max,n));};
  function settings(element={}){
    return {fit:['cover','contain','none'].includes(element.mediaFit)?element.mediaFit:element.menuPdfImage?'contain':'cover',zoom:number(element.mediaZoom,100,10,400),x:number(element.mediaX,50,0,100),y:number(element.mediaY,50,0,100),width:number(element.mediaNaturalWidth,0,0,100000),height:number(element.mediaNaturalHeight,0,0,100000)};
  }
  function attributes(element){
    const s=settings(element),pending=s.fit==='none'&&(!s.width||!s.height);
    return `class="placed-media" data-media-fit="${s.fit}"${pending?' data-media-pending':''} style="--media-zoom:${s.zoom/100};--media-x:${s.x}%;--media-y:${s.y}%;--media-natural-width:${s.width};--media-natural-height:${s.height};object-fit:${s.fit==='none'?(pending?'contain':'fill'):s.fit}"`;
  }
  function update(media){
    if(media.hasAttribute?.('data-event-image-crop')){updateCrop(media);return;}
    const width=media.videoWidth||media.naturalWidth,height=media.videoHeight||media.naturalHeight;
    if(width>0&&height>0){media.style.setProperty('--media-natural-width',width);media.style.setProperty('--media-natural-height',height);media.removeAttribute('data-media-pending');media.style.objectFit='fill';}
  }
  function updateCrop(media,placement){
    const box=String(media.getAttribute('data-event-image-original-viewbox')||'').split(' ').map(Number);
    if(box.length!==4||box.some(n=>!Number.isFinite(n))||box[2]<=0||box[3]<=0)return;
    const s=placement||settings({mediaFit:media.dataset.mediaFit,mediaX:parseFloat(media.style.getPropertyValue('--media-x')),mediaY:parseFloat(media.style.getPropertyValue('--media-y'))});
    media.style.setProperty('--media-natural-width',box[2]);media.style.setProperty('--media-natural-height',box[3]);media.removeAttribute('data-media-pending');
    const rect=media.getBoundingClientRect();let [x,y,width,height]=box;
    if(s.fit!=='none'&&rect.width>0&&rect.height>0){
      const ratio=rect.width/rect.height,cropRatio=width/height;
      if((s.fit==='cover'&&ratio>cropRatio)||(s.fit==='contain'&&ratio<cropRatio)){const h=width/ratio;y+=(height-h)*s.y/100;height=h;}
      else {const w=height*ratio;x+=(width-w)*s.x/100;width=w;}
    }
    media.setAttribute('viewBox',[x,y,width,height].join(' '));media.setAttribute('preserveAspectRatio','xMidYMid meet');
  }
  function apply(media,element){
    const s=settings(element);media.dataset.mediaFit=s.fit;const pending=s.fit==='none'&&(!s.width||!s.height);media.style.objectFit=s.fit==='none'?(pending?'contain':'fill'):s.fit;
    for(const [key,value] of [['zoom',s.zoom/100],['x',s.x+'%'],['y',s.y+'%'],['natural-width',s.width],['natural-height',s.height]])media.style.setProperty('--media-'+key,value);
    if(pending)media.setAttribute('data-media-pending','');else media.removeAttribute('data-media-pending');if(media.hasAttribute?.('data-event-image-crop'))updateCrop(media,s);else if(s.fit==='none')update(media);
  }
  function hydrate(scope){scope.querySelectorAll('.placed-media[data-media-fit="none"],.placed-media[data-event-image-crop]').forEach(update);}
  // Capture also covers template/version thumbnails rendered after the main library.
  for(const event of ['load','loadedmetadata'])root.document?.addEventListener(event,e=>{if(e.target?.matches?.('.placed-media[data-media-fit="none"]'))update(e.target);},true);
  root.KioskyMediaPlacement={settings,attributes,hydrate,apply};
})(globalThis);
