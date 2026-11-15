/* Container-unit fallback for the 2021 TV engine; runs only on the TV view. */
(function(){
 if(window.CSS&&CSS.supports&&CSS.supports('width','1cqw'))return;
 var containers='.player-layout-slide,.event-overview-grid,.player-slide-ticker,.emergency-design-layer',pending=false,observed=new Set();
 function units(value){return value.replace(/(-?(?:\d*\.)?\d+)cqw\b/g,'calc($1 * var(--tv-cqw,1vw))').replace(/(-?(?:\d*\.)?\d+)cqh\b/g,'calc($1 * var(--tv-cqh,1vh))');}
 var observer=window.ResizeObserver?new ResizeObserver(schedule):null;
 function apply(){pending=false;if(observer)observed.forEach(function(node){if(!node.isConnected){observer.unobserve(node);observed.delete(node);}});var sizes=new Map();document.querySelectorAll('.display-player *,.emergency-design-layer *').forEach(function(node){
  if(!node.style)return;var parent=node.parentElement&&node.parentElement.closest(containers),size=parent&&sizes.get(parent);
  if(!size){size={w:parent?parent.clientWidth:innerWidth,h:parent?parent.clientHeight:innerHeight};if(parent)sizes.set(parent,size);}
  var raw=node.getAttribute('style')||'';if(/\dcq[wh]\b/.test(raw))node.setAttribute('style',units(raw));
  var width=(size.w/100)+'px',height=(size.h/100)+'px';if(node.style.getPropertyValue('--tv-cqw')!==width)node.style.setProperty('--tv-cqw',width);if(node.style.getPropertyValue('--tv-cqh')!==height)node.style.setProperty('--tv-cqh',height);
 });document.querySelectorAll(containers).forEach(function(node){if(observer&&!observed.has(node)){observed.add(node);observer.observe(node);}});}
 function schedule(){if(pending)return;pending=true;requestAnimationFrame(apply);}
 new MutationObserver(schedule).observe(document,{childList:true,subtree:true});window.addEventListener('resize',schedule);schedule();
})();
