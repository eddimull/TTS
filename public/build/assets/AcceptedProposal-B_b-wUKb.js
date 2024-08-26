import{B as T}from"./Guest-sG3Zw7b_.js";import{_ as p,h as B,r as I,o as g,c as C,w,a as E,b as _,d as m,T as P,e as d,t as u,f as F}from"./app-rdp2O8e-.js";function M(o){var e={target:"confetti-holder",max:80,size:1,animate:!0,respawn:!0,props:["circle","square","triangle","line"],colors:[[165,104,246],[230,61,135],[0,199,228],[253,214,126]],clock:25,interval:null,rotate:!1,start_from_edge:!1,width:window.innerWidth,height:window.innerHeight};if(o&&(o.target&&(e.target=o.target),o.max&&(e.max=o.max),o.size&&(e.size=o.size),o.animate!==void 0&&o.animate!==null&&(e.animate=o.animate),o.respawn!==void 0&&o.respawn!==null&&(e.respawn=o.respawn),o.props&&(e.props=o.props),o.colors&&(e.colors=o.colors),o.clock&&(e.clock=o.clock),o.start_from_edge!==void 0&&o.start_from_edge!==null&&(e.start_from_edge=o.start_from_edge),o.width&&(e.width=o.width),o.height&&(e.height=o.height),o.rotate!==void 0&&o.rotate!==null&&(e.rotate=o.rotate)),typeof e.target!="object"&&typeof e.target!="string")throw new TypeError("The target parameter should be a node or string");if(typeof e.target=="object"&&(e.target===null||!e.target instanceof HTMLCanvasElement)||typeof e.target=="string"&&(document.getElementById(e.target)===null||!document.getElementById(e.target)instanceof HTMLCanvasElement))throw new ReferenceError("The target element does not exist or is not a canvas element");var n=typeof e.target=="object"?e.target:document.getElementById(e.target),r=n.getContext("2d"),l=[];function s(t,i){t||(t=1);var f=Math.random()*t;return i?Math.floor(f):f}var h=e.props.reduce(function(t,i){return t+(i.weight||1)},0);function y(){for(var t=Math.random()*h,i=0;i<e.props.length;++i){var f=e.props[i].weight||1;if(t<f)return i;t-=f}}function x(){var t=e.props[y()],i={prop:t.type?t.type:t,x:s(e.width),y:e.start_from_edge?e.clock>=0?-10:parseFloat(e.height)+10:s(e.height),src:t.src,radius:s(4)+1,size:t.size,rotate:e.rotate,line:Math.floor(s(65)-30),angles:[s(10,!0)+2,s(10,!0)+2,s(10,!0)+2,s(10,!0)+2],color:e.colors[s(e.colors.length,!0)],rotation:s(360,!0)*Math.PI/180,speed:s(e.clock/7)+e.clock/30};return i}function k(t){if(t){var i=t.radius<=3?.4:.8;switch(r.fillStyle=r.strokeStyle="rgba("+t.color+", "+i+")",r.beginPath(),t.prop){case"circle":{r.moveTo(t.x,t.y),r.arc(t.x,t.y,t.radius*e.size,0,Math.PI*2,!0),r.fill();break}case"triangle":{r.moveTo(t.x,t.y),r.lineTo(t.x+t.angles[0]*e.size,t.y+t.angles[1]*e.size),r.lineTo(t.x+t.angles[2]*e.size,t.y+t.angles[3]*e.size),r.closePath(),r.fill();break}case"line":{r.moveTo(t.x,t.y),r.lineTo(t.x+t.line*e.size,t.y+t.radius*5),r.lineWidth=2*e.size,r.stroke();break}case"square":{r.save(),r.translate(t.x+15,t.y+5),r.rotate(t.rotation),r.fillRect(-15*e.size,-5*e.size,15*e.size,5*e.size),r.restore();break}case"svg":{r.save();var f=new window.Image;f.src=t.src;var a=t.size||15;r.translate(t.x+a/2,t.y+a/2),t.rotate&&r.rotate(t.rotation),r.drawImage(f,-(a/2)*e.size,-(a/2)*e.size,a*e.size,a*e.size),r.restore();break}}}}var v=function(){e.animate=!1,clearInterval(e.interval),requestAnimationFrame(function(){r.clearRect(0,0,n.width,n.height);var t=n.width;n.width=1,n.width=t})},z=function(){n.width=e.width,n.height=e.height,l=[];for(var t=0;t<e.max;t++)l.push(x());function i(){r.clearRect(0,0,e.width,e.height);for(var a in l)k(l[a]);f(),e.animate&&requestAnimationFrame(i)}function f(){for(var a=0;a<e.max;a++){var c=l[a];c&&(e.animate&&(c.y+=c.speed),c.rotate&&(c.rotation+=c.speed/35),(c.speed>=0&&c.y>e.height||c.speed<0&&c.y<0)&&(e.respawn?(l[a]=c,l[a].x=s(e.width,!0),l[a].y=c.speed>=0?-10:parseFloat(e.height)):l[a]=void 0))}l.every(function(b){return b===void 0})&&v()}return requestAnimationFrame(i)};return{render:z,clear:v}}const D={components:{BreezeGuestLayout:T},props:["proposal","event_typtes"],data(){return{person:"",show:!0}},mounted(){},created(){this.$swal.fire({title:"Proposal Accepted!",text:"You should receive an official contract shortly",icon:"success"}).then(()=>{var o={target:"confettiCanvas"},e=new M(o);e.render(),setTimeout(()=>{this.show=!1},5e3)})},methods:{savePerson(){this.showIntro=!1},formatDate(o){return B(o).format("LLLL")}}},L={key:0,id:"confettiCanvas",class:"fixed inset-0 transition-opacity"},q={class:"md:container md:mx-auto"},A={class:"font-semibold text-xl text-gray-800 leading-tight"},N={class:"max-w-7xl mx-auto sm:px-6 lg:px-8"},W={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4"},H={key:0,class:"mt-4"};function R(o,e,n,r,l,s){const h=I("breeze-guest-layout");return g(),C(h,null,{default:w(()=>[E(P,{name:"fade"},{default:w(()=>[l.show?(g(),_("canvas",L)):m("",!0)]),_:1}),d("div",q,[d("h2",A," Details for "+u(n.proposal.name),1),d("div",N,[d("div",W,[F(" Details "),d("ul",null,[d("li",null,"Event Type: "+u(n.proposal.event_type.name),1),d("li",null,"Band: "+u(n.proposal.band.name),1),d("li",null,"When: "+u(s.formatDate(n.proposal.date)),1),d("li",null,"Where: "+u(n.proposal.location??"TBD"),1),d("li",null,"Price: $"+u(parseFloat(n.proposal.price).toFixed(2)),1),d("li",null,"How long: "+u(n.proposal.hours)+" hours ",1),n.proposal.client_notes?(g(),_("li",H," Notes: "+u(n.proposal.client_notes),1)):m("",!0)])])])])]),_:1})}const j=p(D,[["render",R],["__scopeId","data-v-4f153ffb"]]);export{j as default};