/* QR frames remain in this browser. No camera images are uploaded. */
(() => {
  let decoderPromise, stream, timer, generation=0;
  const stopCamera=()=>{clearTimeout(timer);stream?.getTracks().forEach(track=>track.stop());stream=null;const video=document.querySelector('#pairing-scan-video');if(video){video.pause();video.srcObject=null;}};
  const stop=()=>{generation+=1;stopCamera();};
  const decoder=url=>{
    if(window.jsQR)return Promise.resolve(window.jsQR);
    if(!decoderPromise)decoderPromise=new Promise((resolve,reject)=>{
      const script=document.createElement('script');script.src=url;
      script.onload=()=>window.jsQR?resolve(window.jsQR):reject(new Error('QR-Leser konnte nicht geladen werden.'));
      script.onerror=()=>{script.remove();decoderPromise=null;reject(new Error('QR-Leser konnte nicht geladen werden. Bitte Code manuell eingeben.'));};
      document.head.appendChild(script);
    });
    return decoderPromise;
  };
  const decodeFrame=(source,width,height,read)=>{
    if(!width||!height)return null;
    const scale=Math.min(1,900/Math.max(width,height)),canvas=document.createElement('canvas');
    canvas.width=Math.max(1,Math.round(width*scale));canvas.height=Math.max(1,Math.round(height*scale));
    const context=canvas.getContext('2d',{willReadFrequently:true});context.drawImage(source,0,0,canvas.width,canvas.height);
    const frame=context.getImageData(0,0,canvas.width,canvas.height),result=read(frame.data,canvas.width,canvas.height,{inversionAttempts:'attemptBoth'});
    return window.KioskyPairingQr.parse(result?.data);
  };
  window.KioskyPairingScanner={stop,async open({vendorUrl,onCode}){
    const dialog=document.querySelector('#pairing-scan-dialog'),video=document.querySelector('#pairing-scan-video'),status=document.querySelector('#pairing-scan-status'),file=document.querySelector('#pairing-scan-file');
    stop();const current=generation;status.textContent='QR-Leser wird vorbereitet …';file.value='';dialog.showModal();
    const close=()=>{stop();if(dialog.open)dialog.close();};
    dialog.onclose=stop;dialog.oncancel=stop;document.querySelector('#pairing-scan-close').onclick=close;
    const displayDialog=document.querySelector('#display-dialog');displayDialog.addEventListener('close',close,{once:true});
    dialog.addEventListener('close',()=>displayDialog.removeEventListener('close',close),{once:true});
    const accept=code=>{if(!code||current!==generation||!dialog.open)return false;close();onCode(code);return true;};
    let read;
    try{read=await decoder(vendorUrl);}catch(error){if(current===generation)status.textContent=error.message;return;}
    if(current!==generation)return;
    file.onchange=async()=>{
      const selected=file.files?.[0];if(!selected||current!==generation)return;
      if(selected.size>10*1024*1024||!['image/jpeg','image/png','image/webp'].includes(selected.type)){status.textContent='Bitte ein JPG-, PNG- oder WebP-Foto bis 10 MB wählen.';return;}
      const url=URL.createObjectURL(selected),image=new Image();
      try{await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=reject;image.src=url;});if(current!==generation)return;if(!accept(decodeFrame(image,image.naturalWidth,image.naturalHeight,read)))status.textContent='Kein gültiger Kiosky-Kopplungscode erkannt. Bitte erneut versuchen oder Code eingeben.';}catch{if(current===generation)status.textContent='Das Foto konnte nicht gelesen werden.';}finally{URL.revokeObjectURL(url);}
    };
    try{
      if(!navigator.mediaDevices?.getUserMedia)throw new Error('Kamera nicht verfügbar');
      const camera=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});
      if(current!==generation||document.visibilityState==='hidden'){camera.getTracks().forEach(track=>track.stop());return;}
      stream=camera;video.srcObject=camera;await video.play();
      if(current!==generation)return;
      status.textContent='Kamera auf den QR-Code des Players richten.';
      const scan=()=>{if(current!==generation||!dialog.open)return;try{if(video.readyState>=2&&accept(decodeFrame(video,video.videoWidth,video.videoHeight,read)))return;}catch{status.textContent='Kamerabild konnte nicht gelesen werden. Alternativ ein Foto wählen.';}timer=setTimeout(scan,300);};scan();
    }catch{if(current===generation){stream?.getTracks().forEach(track=>track.stop());stream=null;status.textContent='Kamera nicht verfügbar oder Zugriff verweigert. Bitte ein QR-Foto wählen oder den Code manuell eingeben. Für Kamerazugriff ist HTTPS nötig.';}}
  }};
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'){stopCamera();const status=document.querySelector('#pairing-scan-status');if(status)status.textContent='Kamera pausiert. Foto auswählen oder den Scanner erneut öffnen.';}});
  window.addEventListener('pagehide',stop);
})();
