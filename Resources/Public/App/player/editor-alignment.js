/* Pure geometry in slide percentages; the gesture supplies a screen-pixel tolerance. */
(function(root){
  function bounds(elements,canvasWidth=1920,canvasHeight=1080){
    const boxes=elements.map(e=>{const radians=Number(e.rotation||0)*Math.PI/180,w=e.width*canvasWidth/100,h=e.height*canvasHeight/100,rx=(Math.abs(Math.cos(radians))*w+Math.abs(Math.sin(radians))*h)/canvasWidth*100,ry=(Math.abs(Math.sin(radians))*w+Math.abs(Math.cos(radians))*h)/canvasHeight*100;return{x:e.x+e.width/2-rx/2,y:e.y+e.height/2-ry/2,width:rx,height:ry};});
    const x=Math.min(...boxes.map(b=>b.x)),y=Math.min(...boxes.map(b=>b.y));
    return{x,y,width:Math.max(...boxes.map(b=>b.x+b.width))-x,height:Math.max(...boxes.map(b=>b.y+b.height))-y};
  }
  function axis(start,size,delta,anchors,tolerance,enabled){
    const min=-start,max=100-start-size,clamp=value=>min<=max?Math.max(min,Math.min(max,value)):value;
    delta=clamp(delta);if(!enabled)return{delta,guide:null};
    let best=null;
    for(const anchor of anchors)for(const offset of [size/2,0,size]){
      const candidate=anchor-(start+offset),distance=Math.abs(candidate-delta);
      if(distance<=tolerance&&Math.abs(clamp(candidate)-candidate)<1e-7&&(!best||distance<best.distance-1e-7))best={delta:candidate,guide:anchor,distance};
    }
    return best||{delta,guide:null};
  }
  function move(box,others,dx,dy,toleranceX,toleranceY,enabled=true){
    const xs=[50,0,100],ys=[50,0,100];
    for(const other of others){xs.push(other.x,other.x+other.width/2,other.x+other.width);ys.push(other.y,other.y+other.height/2,other.y+other.height);}
    const x=axis(box.x,box.width,dx,xs,toleranceX,enabled),y=axis(box.y,box.height,dy,ys,toleranceY,enabled);
    return{dx:x.delta,dy:y.delta,guides:{x:x.guide,y:y.guide}};
  }
  root.KioskyEditorAlignment={bounds,move};
})(globalThis);
