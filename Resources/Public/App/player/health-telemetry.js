(function(root){
 function create(options){var last=null,failures=0,hasContent=null,latency=null,mono=options.monotonic||function(){return performance.now();};
  return {success:function(state,ms){last=mono();failures=0;latency=Math.max(0,Math.min(120000,Math.round(ms)));hasContent=Boolean(state.warning||state.emergency||(state.slides||[]).length);},failure:function(){failures=Math.min(1000,failures+1);},snapshot:function(){var errors=[],scope=options.root();if(scope){scope.querySelectorAll('img[src]').forEach(function(image){if(image.getAttribute('src')&&image.complete&&image.naturalWidth===0)errors.push({code:'IMAGE_LOAD_FAILED'});});scope.querySelectorAll('video[src]').forEach(function(video){if(video.error)errors.push({code:video.error.code===3?'VIDEO_DECODE_FAILED':'VIDEO_LOAD_FAILED'});});}return {schema:1,appVersion:options.version||null,stateAgeSeconds:last===null?null:Math.min(604800,Math.max(0,Math.floor((mono()-last)/1000))),lastFetchMs:latency,stateFailures:failures,hasContent:hasContent,paused:document.visibilityState==='hidden'||root.KioskyTvPresentationVisible===false,mediaErrors:errors.slice(0,10)};}};
 }
 root.KioskyPlayerHealth={create:create};
})(globalThis);
