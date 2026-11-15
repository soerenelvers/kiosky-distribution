/* APIs absent in Chromium 76; scoped to the TV document. */
(function(){
 if(!Object.hasOwn)Object.hasOwn=function(o,k){return Object.prototype.hasOwnProperty.call(o,k);};
 if(!Array.prototype.at)Object.defineProperty(Array.prototype,'at',{value:function(i){i=Math.trunc(i)||0;return this[i<0?this.length+i:i];}});
 if(!String.prototype.replaceAll)Object.defineProperty(String.prototype,'replaceAll',{value:function(search,replacement){if(search instanceof RegExp){if(!search.global)throw new TypeError('Global regexp required');return this.replace(search,replacement);}return this.split(String(search)).join(String(replacement));}});
 if(!window.structuredClone)window.structuredClone=function(value){return JSON.parse(JSON.stringify(value));};
 if(!Element.prototype.replaceChildren)Element.prototype.replaceChildren=function(){while(this.firstChild)this.removeChild(this.firstChild);for(var i=0;i<arguments.length;i++)this.append(arguments[i]);};
})();
