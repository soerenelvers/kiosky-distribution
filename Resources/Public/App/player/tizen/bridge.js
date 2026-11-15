(function(){
 if(!new URLSearchParams(location.search).get('display'))return;
 window.KioskyRuntime=Object.assign({},window.KioskyRuntime,{nativeScreenAwake:true});
 window.KioskyTvPresentationVisible=true;
 window.KioskyFullscreen={start:function(){},request:function(){return Promise.resolve(true);},prompt:function(){},clearPrompt:function(){}};
 function pauseMedia(){document.querySelectorAll('video,audio').forEach(function(media){media.pause();});}
 new MutationObserver(function(){if(window.KioskyTvPresentationVisible===false)pauseMedia();}).observe(document,{childList:true,subtree:true});
 function alive(){if(window.parent!==window)window.parent.postMessage({type:'kiosky:tv-alive'},'*');}
 alive();setInterval(alive,5000);
 document.addEventListener('keydown',function(e){if(e.keyCode===10009||e.keyCode===27){e.preventDefault();window.parent.postMessage({type:'kiosky:tv-menu'},'*');}});
 window.addEventListener('message',function(e){if(e.source!==window.parent)return;if(!e.data)return;if(e.data.type==='kiosky:tv-pause'){window.KioskyTvPresentationVisible=false;pauseMedia();document.dispatchEvent(new Event('visibilitychange'));}if(e.data.type==='kiosky:tv-resume'){window.KioskyTvPresentationVisible=true;document.querySelectorAll('video,audio').forEach(function(media){var playing=media.play();if(playing&&playing.catch)playing.catch(function(){});});window.dispatchEvent(new Event('online'));}});
})();
