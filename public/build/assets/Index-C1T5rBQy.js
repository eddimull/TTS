import{_ as r,B as c,h as d,r as e,o as i,c as _,w as s,a as l,d as h,e as p}from"./app-rdp2O8e-.js";import m from"./EventList-R-HLd2Yv.js";const u={components:{BreezeAuthenticatedLayout:c,EventList:m},props:["events","successMessage"],methods:{formatDate(t){return d(String(t)).format("MM/DD/YYYY")}}},v=p("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Events ",-1);function x(t,f,o,b,y,g){const n=e("event-list"),k=e("Link"),a=e("breeze-authenticated-layout");return i(),_(a,null,{header:s(()=>[v]),default:s(()=>[l(n,{events:o.events},null,8,["events"]),h("",!0)]),_:1})}const z=r(u,[["render",x]]);export{z as default};