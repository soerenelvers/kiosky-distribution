/* Local PDF renderer. No document scripts, forms, attachments or remote resources are imported. */
export async function openMenuPdf(file) {
  if(!file||file.size>20*1024*1024)throw new Error('PDF bitte mit höchstens 20 MiB auswählen.');
  const data=new Uint8Array(await file.arrayBuffer());
  if(new TextDecoder().decode(data.slice(0,5))!=='%PDF-')throw new Error('Die Datei ist keine PDF-Datei.');
  const base=new URL('./vendor/pdfjs/',import.meta.url);
  const version=new URL(import.meta.url).searchParams.get('v')||'6.4.299';
  const pdfUrl=new URL('pdf.mjs',base),workerUrl=new URL('pdf.worker.mjs',base);
  pdfUrl.searchParams.set('v',version);workerUrl.searchParams.set('v',version);
  const pdfjs=await import(pdfUrl.href);
  pdfjs.GlobalWorkerOptions.workerSrc=workerUrl.href;
  const task=pdfjs.getDocument({data,isEvalSupported:false,enableXfa:false,cMapUrl:new URL('cmaps/',base).href,cMapPacked:true,standardFontDataUrl:new URL('standard_fonts/',base).href,wasmUrl:new URL('wasm/',base).href});
  let pdf;
  try{pdf=await task.promise;}catch(error){await task.destroy();throw new Error(error.name==='PasswordException'?'Passwortgeschützte PDFs bitte vorher entsperren.':'Die PDF-Datei konnte nicht gelesen werden.');}
  if(pdf.numPages>20){await task.destroy();throw new Error('Pro Import sind höchstens 20 PDF-Seiten möglich.');}
  return {count:pdf.numPages,destroy:()=>task.destroy(),async page(number,orientation){
    if(!Number.isInteger(number)||number<1||number>pdf.numPages)throw new Error('Ungültige PDF-Seite.');
    const page=await pdf.getPage(number),original=page.getViewport({scale:1});
    const format=orientation||(original.width>original.height?'landscape':'portrait'),width=format==='portrait'?1080:1920,height=format==='portrait'?1920:1080;
    const viewport=page.getViewport({scale:Math.min(width/original.width,height/original.height)});
    const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
    const context=canvas.getContext('2d');context.fillStyle='#ffffff';context.fillRect(0,0,width,height);
    await page.render({canvasContext:context,viewport,transform:[1,0,0,1,(width-viewport.width)/2,(height-viewport.height)/2],background:'#ffffff'}).promise;
    const src=canvas.toDataURL('image/jpeg',0.9);canvas.width=canvas.height=1;page.cleanup();
    return {orientation:format,pdfPage:{src,fileName:file.name.slice(0,160),pageNumber:number,pageCount:pdf.numPages}};
  }};
}
