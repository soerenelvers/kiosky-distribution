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
  function resize(element,edge,dx,dy,canvasWidth,canvasHeight,minimum={width:5,height:5}){
    const angle=Number(element.rotation||0)*Math.PI/180,c=Math.cos(angle),s=Math.sin(angle);
    const localX=c*dx+s*dy,localY=-s*dx+c*dy;
    const horizontal=edge.includes('w')?-1:edge.includes('e')?1:0,vertical=edge.includes('n')?-1:edge.includes('s')?1:0;
    const w=element.width/100*canvasWidth,h=element.height/100*canvasHeight,cx=(element.x+element.width/2)/100*canvasWidth,cy=(element.y+element.height/2)/100*canvasHeight;
    const calculate=t=>{
      const width=horizontal?Math.max(Math.min(w,canvasWidth*minimum.width/100),Math.min(canvasWidth,w+horizontal*localX*t)):w;
      const height=vertical?Math.max(Math.min(h,canvasHeight*minimum.height/100),Math.min(canvasHeight,h+vertical*localY*t)):h;
      const sx=horizontal*(width-w)/2,sy=vertical*(height-h)/2;
      return{x:(cx+c*sx-s*sy-width/2)/canvasWidth*100,y:(cy+s*sx+c*sy-height/2)/canvasHeight*100,width:width/canvasWidth*100,height:height/canvasHeight*100};
    };
    const inside=value=>{const b=bounds([{...value,rotation:element.rotation}],canvasWidth,canvasHeight);return b.x>=-1e-7&&b.y>=-1e-7&&b.x+b.width<=100+1e-7&&b.y+b.height<=100+1e-7;};
    const proposed=calculate(1);if(!inside(element)||inside(proposed))return proposed;
    let low=0,high=1;for(let i=0;i<28;i++){const middle=(low+high)/2;if(inside(calculate(middle)))low=middle;else high=middle;}
    return calculate(low);
  }
  root.KioskyEditorAlignment={bounds,move,resize};
})(globalThis);
