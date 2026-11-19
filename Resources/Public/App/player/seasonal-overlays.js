/* Deterministic vector decorations. No network, timers, canvas or external SVG. */
(function(root){
  const presets={snow:{name:'Schneefall',color:'#ffffff'},tree:{name:'Weihnachtsbaum',color:'#ffd166'},snowman:{name:'Schneemann',color:'#5ba8e5'},pumpkin:{name:'Halloween-Kürbis',color:'#f78c26'},stars:{name:'Funkelnde Sterne',color:'#ffe69a'}};
  const number=(value,fallback,min,max)=>{const n=['number','string'].includes(typeof value)?Number(value):NaN;return value==null||!Number.isFinite(n)?fallback:Math.max(min,Math.min(max,n));};
  function settings(element={}){
    element=element&&typeof element==='object'?element:{};
    const effect=typeof element.decorationEffect==='string'&&Object.hasOwn(presets,element.decorationEffect)?element.decorationEffect:'snow';
    return {effect,color:typeof element.decorationColor==='string'&&/^#[\da-f]{6}$/i.test(element.decorationColor)?element.decorationColor:presets[effect].color,
      speed:number(element.decorationSpeed,1,.25,2),count:Math.floor(number(element.decorationCount,24,8,40)),animated:element.decorationAnimated!==false};
  }
  const seed=(i,m)=>{let x=Math.imul(i+1,0x45d9f3b)^Math.imul(m+1,0x27d4eb2d);x=Math.imul(x^(x>>>16),0x45d9f3b);return ((x^(x>>>16))>>>0)/4294967295;};
  const svg=content=>`<svg viewBox="0 0 240 300" aria-hidden="true" focusable="false">${content}</svg>`;
  function figure(s){
    if(s.effect==='tree'){
      const lights=[[120,66],[86,109],[142,111],[65,157],[113,153],[164,163],[49,212],[96,204],[143,213],[185,222]];
      return svg(`<g class="seasonal-sway"><ellipse cx="120" cy="282" rx="73" ry="9" fill="#15263d" opacity=".13"/><path d="M104 244h32v35h-32z" fill="#88532d"/><path d="M120 31L63 121h24l-45 71h27l-42 64h186l-42-64h27l-45-71h24z" fill="#176d50"/><path d="M120 31L85 121h18l-25 71h23l-16 64H27l42-64H42l45-71H63z" fill="#22875e"/><path d="M76 117q45 32 91 1M55 184q67 30 128 0M39 239q87 20 158-4" fill="none" stroke="#e9bd65" stroke-width="4" opacity=".7"/><path d="M120 12l7 14 16 2-12 11 3 16-14-8-14 8 3-16-12-11 16-2z" fill="${s.color}"/>${lights.map(([x,y],i)=>`<circle class="seasonal-light" cx="${x}" cy="${y}" r="5" fill="${i%3===0?'#e77878':i%3===1?s.color:'#a4dfef'}" style="animation-delay:${-i*.31/s.speed}s"/>`).join('')}<rect x="89" y="268" width="62" height="16" rx="5" fill="#b84f59"/></g>`);
    }
    if(s.effect==='snowman')return svg(`<ellipse cx="119" cy="282" rx="87" ry="9" fill="#15263d" opacity=".13"/><g class="seasonal-wave"><path d="M171 161l40-35m-9 8 1-19m4 16 18 3" stroke="#855a40" stroke-width="6" fill="none" stroke-linecap="round"/></g><path d="M67 161l-42-29m12 7-2-19" stroke="#855a40" stroke-width="6" fill="none" stroke-linecap="round"/><ellipse cx="120" cy="225" rx="65" ry="58" fill="#f0f7fc" stroke="#c7ddec" stroke-width="3"/><ellipse cx="120" cy="161" rx="51" ry="46" fill="#f9fcff" stroke="#c7ddec" stroke-width="3"/><circle cx="120" cy="91" r="38" fill="#fff" stroke="#c7ddec" stroke-width="3"/><path d="M88 56h64l-5-35H96z" fill="#25354b"/><path d="M91 45h60v10H91z" fill="${s.color}"/><rect x="74" y="54" width="92" height="10" rx="5" fill="#25354b"/><circle cx="107" cy="83" r="3.5" fill="#25354b"/><circle cx="133" cy="83" r="3.5" fill="#25354b"/><path d="M118 93l28 7-28 6z" fill="#f29a42"/><path d="M102 109q18 13 36-1" fill="none" stroke="#25354b" stroke-width="3" stroke-linecap="round"/><path d="M86 122q34 17 68 0l3 19q-37 15-75-1zM134 136l22 1-5 46-22-4z" fill="${s.color}"/>${[164,187,212,238].map(y=>`<circle cx="119" cy="${y}" r="4" fill="#37475a"/>`).join('')}`);
    if(s.effect==='pumpkin')return svg(`<ellipse cx="121" cy="273" rx="91" ry="11" fill="#15263d" opacity=".15"/><g class="seasonal-sway"><path d="M110 74q-8-37 13-43l12 9q-20 4-12 35" fill="#497748"/><path d="M132 61q42-35 57-3-40 17-57 3" fill="#6a934c"/><path d="M49 100q29-28 71-13 47-20 75 12 35 35 22 94-12 65-96 67-84-1-99-66-14-57 27-94z" fill="${s.color}"/><ellipse cx="120" cy="176" rx="42" ry="83" fill="#ffb548" opacity=".38"/><path d="M79 99q-36 80 0 145M163 98q37 78 0 146" stroke="#ae4e1c" stroke-width="3" fill="none" opacity=".35"/><g fill="#362b28"><path d="M58 151l37-20 4 31zM142 162l5-31 37 20zM111 172l10-17 11 17zM57 190l26 8 6-9 19 15 16-9 17 9 15-16 10 9 24-8q-28 62-69 51-42 4-64-50z"/></g><g class="seasonal-glow" fill="#ffe18b"><path d="M65 151l28-14 2 18zM147 155l3-18 27 14zM68 199l15 5 6-8 18 14 17-9 17 9 16-14 9 8 12-5q-26 40-57 34-31 4-53-34z"/></g></g>`);
    return '';
  }
  function markup(element={},animate=true){
    const s=settings(element),motion=animate&&s.animated;
    let content;
    if(s.effect==='snow'||s.effect==='stars')content=Array.from({length:s.count},(_,i)=>{
      const x=seed(i,2)*96,y=seed(i,7)*94,duration=(8+seed(i,3)*12)/s.speed;
      return s.effect==='snow'?`<span class="seasonal-flake" style="left:${x}%;--rest-y:${y}%;--size:${.35+seed(i,5)*.65}%;opacity:${.4+seed(i,11)*.6};--duration:${duration}s;--delay:${-seed(i,13)*duration}s;--drift:${-20+seed(i,17)*40}px"><i></i></span>`:`<span class="seasonal-star" style="left:${x}%;top:${y}%;width:${1+seed(i,3)*2}%;animation-delay:${-seed(i,13)*3/s.speed}s"><svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M10 0l2.5 7.5L20 10l-7.5 2.5L10 20l-2.5-7.5L0 10l7.5-2.5z" fill="currentColor"/></svg></span>`;
    }).join('');else content=figure(s);
    return `<span class="seasonal-overlay seasonal-${s.effect}" data-seasonal-motion="${motion}" aria-hidden="true" style="--seasonal-color:${s.color};--seasonal-period:${3/s.speed}s">${content}</span>`;
  }
  root.KioskySeasonal={presets,settings,markup};
})(globalThis);
