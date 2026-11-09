/* Non-destructive event-image crops: percentages of the original image, no canvas/CORS export. */
(() => {
 const escape=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function normalize(value){
  if(!value||typeof value!=='object'||Array.isArray(value)||!Object.keys(value).length)return null;
  const n=(key,fallback,min,max)=>{const raw=value[key],number=typeof raw==='number'||typeof raw==='string'&&raw.trim()!==''?Number(raw):NaN;return Number.isFinite(number)?Math.round(Math.max(min,Math.min(max,number))*10000)/10000:fallback;};
  const width=n('width',100,5,100),height=n('height',100,5,100);return{x:n('x',0,0,100-width),y:n('y',0,0,100-height),width,height,naturalWidth:n('naturalWidth',1,1,100000),naturalHeight:n('naturalHeight',1,1,100000)};
 }
 function markup(event,fit='cover',className='event-image-crop',placement=null){
  const crop=normalize(event?.imageEdit),src=String(event?.imageUrl||'').trim();if(!crop||!src)return '';
  try{if(!['http:','https:'].includes(new URL(src,location.href).protocol)||/[\u0000-\u001f\u007f]/.test(src))return '';}catch{return '';}
  const x=crop.x*crop.naturalWidth/100,y=crop.y*crop.naturalHeight/100,width=crop.width*crop.naturalWidth/100,height=crop.height*crop.naturalHeight/100;
  const clipId='event-crop-'+[x,y,width,height].join('_').replaceAll('.','d');
  const attributes=placement&&globalThis.KioskyMediaPlacement?KioskyMediaPlacement.attributes({...placement,mediaNaturalWidth:width,mediaNaturalHeight:height}).replace('class="placed-media"','class="placed-media event-image-crop"'):`class="${escape(className)}" style="aspect-ratio:${width}/${height};overflow:hidden"`;
  return `<svg xmlns="http://www.w3.org/2000/svg" ${attributes} data-event-image-original-viewbox="${x} ${y} ${width} ${height}" data-event-image-crop viewBox="${x} ${y} ${width} ${height}" preserveAspectRatio="xMidYMid ${fit==='contain'?'meet':'slice'}" role="img" aria-label="${escape(event.title||'Veranstaltungsbild')}"><defs><clipPath id="${clipId}"><rect x="${x}" y="${y}" width="${width}" height="${height}"/></clipPath></defs><image clip-path="url(#${clipId})" href="${escape(src)}" width="${crop.naturalWidth}" height="${crop.naturalHeight}"/></svg>`;
 }
 function update(svg,event){
  const crop=normalize(event?.imageEdit),image=svg?.querySelector('image'),clip=svg?.querySelector('clipPath'),rect=clip?.querySelector('rect');
  if(!crop||!image||!clip||!rect||image.getAttribute('href')!==event.imageUrl)return false;
  const x=crop.x*crop.naturalWidth/100,y=crop.y*crop.naturalHeight/100,width=crop.width*crop.naturalWidth/100,height=crop.height*crop.naturalHeight/100,id='event-crop-'+[x,y,width,height].join('_').replaceAll('.','d');
  svg.setAttribute('viewBox',[x,y,width,height].join(' '));svg.setAttribute('data-event-image-original-viewbox',[x,y,width,height].join(' '));svg.style.aspectRatio=width+'/'+height;
  clip.id=id;for(const [key,value] of Object.entries({x,y,width,height}))rect.setAttribute(key,value);image.setAttribute('width',crop.naturalWidth);image.setAttribute('height',crop.naturalHeight);image.setAttribute('clip-path','url(#'+id+')');return true;
 }
 globalThis.KioskyEventImage=Object.freeze({normalize,markup,update});
})();
