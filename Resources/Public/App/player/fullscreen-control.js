(() => {
  const extensionId='nlboncnanjoobakmbginlgmganglphlf';
  const nativeFullscreen=()=>new Promise(resolve=>{
    const runtime=window.chrome?.runtime;
    if(typeof runtime?.sendMessage!=='function'){resolve(false);return;}
    let finished=false;
    const finish=ok=>{if(!finished){finished=true;clearTimeout(timer);resolve(ok);}};
    const timer=setTimeout(()=>finish(false),4000);
    try{runtime.sendMessage(extensionId,{type:'kiosky:fullscreen'},result=>{
      const error=runtime.lastError;
      finish(!error&&result?.ok===true);
    });}catch{finish(false);}
  });
  const domFullscreen=async()=>{
    try{await document.documentElement.requestFullscreen();return Boolean(document.fullscreenElement);}catch{return false;}
  };
  async function request(userGesture=false){
    if(document.fullscreenElement)return true;
    if(userGesture&&await domFullscreen())return true;
    if(await nativeFullscreen())return true;
    return userGesture?false:domFullscreen();
  }
  function start(){
    void request();
    const enter=()=>{void request(true);};
    document.addEventListener('pointerdown',enter,{once:true});
    document.addEventListener('keydown',enter,{once:true});
  }
  function prompt(accept){
    if(document.querySelector('#player-fullscreen-request'))return;
    const panel=document.createElement('aside'),button=document.createElement('button'),status=document.createElement('p');
    panel.id='player-fullscreen-request';panel.setAttribute('aria-label','Vollbild-Anforderung');
    panel.style.cssText='position:fixed;bottom:20px;right:20px;max-width:calc(100vw - 40px);padding:16px;background:#17251f;color:white;z-index:10000;border-radius:12px;font:16px sans-serif';
    status.textContent='Vollbild wurde aus der Ferne angefordert. Bitte auf diesem Gerät bestätigen.';
    status.setAttribute('role','status');
    button.type='button';button.textContent='Vollbild aktivieren';button.style.cssText='min-height:44px;padding:10px 16px;cursor:pointer';
    button.addEventListener('click',async()=>{
      button.disabled=true;
      try{if(await request(true)&&await accept()){panel.remove();return;}status.textContent='Vollbild konnte noch nicht bestätigt werden. Bitte erneut versuchen.';}
      finally{button.disabled=false;}
    });
    panel.append(status,button);document.body.append(panel);
  }
  window.KioskyFullscreen={request,start,prompt,clearPrompt:()=>document.querySelector('#player-fullscreen-request')?.remove()};
})();
