import{_ as U,B as T,h as S,r as v,o,b as a,a as y,w as c,e as s,F as b,k as g,t as C,i as u,aa as B,d as n,f as r,n as _,v as m,g as k,c as x,j as E}from"./app-rdp2O8e-.js";import{B as P}from"./Button-DNZXcZHQ.js";import{s as z,a as M}from"./VueTimepicker-a0i1e-uI.js";const A={props:["proposal","eventTypes"],components:{BreezeAuthenticatedLayout:T,Datepicker:z,VueTimepicker:M,ButtonComponent:P},data(){return{proposalData:this.proposal,newContact:{name:"",phonenumber:"",email:""},patchingContact:{},validInputs:[{name:"Name",type:"text",field:"name"},{name:"Date / Time",type:"date",field:"date"},{name:"Event Type",type:"eventTypeDropdown",field:"event_type_id"},{name:"Contacts",type:"contacts",field:"proposal_contacts"},{name:"Length",type:"number",field:"hours"},{name:"Price",type:"number",field:"price"},{name:"Colorway",type:"text",field:"color"},{name:"Notes",type:"textArea",field:"notes"}],showCreateNewContact:!1,inputClass:["shadow","appearance-none","border","rounded ","w-full ","py-2 ","px-3 ","text-gray-700 ","leading-tight ","focus:outline-none ","focus:shadow-outline"]}},created(){this.proposalData.date=new Date(S(String(this.proposalData.date)))},methods:{saveContact(){this.$inertia.post("/proposals/createContact/"+this.proposal.key,this.newContact,{preserveScroll:!0,onSuccess:p=>{this.newContact.name="",this.newContact.phonenumber="",this.newContact.email="",this.showCreateNewContact=!1,this.proposalData.proposal_contacts=p.props.proposal.proposal_contacts}})},updateContact(p){this.$inertia.post("/proposals/editContact/"+p.id,p,{preserveScroll:!0}).then(()=>{p.editing=!1})},removeContact(p){this.$inertia.delete("/proposals/deleteContact/"+p.id,{preserveScroll:!0}).then(i=>{this.proposalData.proposal_contacts=this.proposalData.proposal_contacts.filter(w=>w.id!==p.id)})},hideContactEdits(){this.proposalData.proposal_contacts.forEach(p=>p.editing=!1)},updateProposal(){this.$inertia.patch("/proposals/"+this.proposal.key+"/update/",this.proposalData)}}},F=s("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Create Proposal ",-1),L={class:"min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8"},j={class:"mb-4"},q={class:"bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4"},I={class:"bg-white w-full rounded-lg shadow-xl"},O={class:"text-gray-600"},G=["for"],H={key:0,class:"mb-4"},J=["type","id","placeholder","onUpdate:modelValue"],K={key:1},Q=["onClick"],R={key:0},W={key:1},X={key:2},Y={key:3},Z=["onUpdate:modelValue"],$={key:4},ee=["onUpdate:modelValue"],te={key:5},oe=["onUpdate:modelValue"],se={key:6},le={key:0},ae={key:2},ne=["onUpdate:modelValue"],ie={key:3},re={key:4},pe=["onUpdate:modelValue"],de=["value"],ue={class:"flex items-center justify-between"};function ce(p,i,w,me,t,f){const h=v("button-component"),D=v("calendar"),V=v("breeze-authenticated-layout");return o(),a("div",null,[y(V,null,{header:c(()=>[F]),default:c(()=>[s("div",L,[s("div",j,[s("div",q,[s("div",I,[(o(!0),a(b,null,g(t.validInputs,l=>(o(),a("div",{key:l,class:"md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"},[s("p",O,[s("label",{for:l.name},C(l.name),9,G)]),["text","number"].indexOf(l.type)!==-1?(o(),a("div",H,[u(s("input",{type:l.type,class:"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline",id:l.name,placeholder:l.name,"onUpdate:modelValue":e=>t.proposalData[l.field]=e},null,8,J),[[B,t.proposalData[l.field]]])])):n("",!0),l.type=="contacts"?(o(),a("div",K,[(o(!0),a(b,null,g(t.proposalData.proposal_contacts,e=>(o(),a("ul",{class:"hover:bg-gray-100 cursor-pointer",onClick:d=>{t.proposalData.proposal_contacts.forEach(N=>N.editing=!1),e.editing||(e.editing=!0)},key:e.id},[e.editing?n("",!0):(o(),a("li",R,"Name: "+C(e.name),1)),e.editing?n("",!0):(o(),a("li",W,"Phone: "+C(e.phonenumber),1)),e.editing?n("",!0):(o(),a("li",X,"Email: "+C(e.email),1)),e.editing?(o(),a("li",Y,[r("Name: "),u(s("input",{class:_(t.inputClass),required:"",type:"text","onUpdate:modelValue":d=>e.name=d},null,10,Z),[[m,e.name]])])):n("",!0),e.editing?(o(),a("li",$,[r("Phone: "),u(s("input",{class:_(t.inputClass),type:"tel","onUpdate:modelValue":d=>e.phonenumber=d},null,10,ee),[[m,e.phonenumber]])])):n("",!0),e.editing?(o(),a("li",te,[r("Email: "),u(s("input",{class:_(t.inputClass),type:"email","onUpdate:modelValue":d=>e.email=d},null,10,oe),[[m,e.email]])])):n("",!0),e.editing?(o(),a("li",se,[y(h,{onClick:k(d=>f.updateContact(e),["stop"]),type:"button"},{default:c(()=>[r("Save")]),_:2},1032,["onClick"]),y(h,{onClick:k(d=>e.editing=!1,["stop"]),type:"button"},{default:c(()=>[r("Cancel")]),_:2},1032,["onClick"]),y(h,{onClick:k(d=>f.removeContact(e),["stop"]),type:"button"},{default:c(()=>[r("Delete")]),_:2},1032,["onClick"])])):n("",!0)],8,Q))),128)),t.showCreateNewContact?(o(),a("ul",le,[s("li",null,[r("Name: "),u(s("input",{class:_(t.inputClass),required:"",type:"text","onUpdate:modelValue":i[0]||(i[0]=e=>t.newContact.name=e)},null,2),[[m,t.newContact.name]])]),s("li",null,[r("Phone: "),u(s("input",{class:_(t.inputClass),type:"tel","onUpdate:modelValue":i[1]||(i[1]=e=>t.newContact.phonenumber=e)},null,2),[[m,t.newContact.phonenumber]])]),s("li",null,[r("Email: "),u(s("input",{class:_(t.inputClass),type:"email","onUpdate:modelValue":i[2]||(i[2]=e=>t.newContact.email=e)},null,2),[[m,t.newContact.email]])])])):n("",!0),t.showCreateNewContact?n("",!0):(o(),x(h,{key:1,type:"button",onClick:i[3]||(i[3]=e=>t.showCreateNewContact=!0)},{default:c(()=>[r("Create New")]),_:1})),t.showCreateNewContact?(o(),x(h,{key:2,type:"button",onClick:i[4]||(i[4]=e=>t.showCreateNewContact=!1)},{default:c(()=>[r("Cancel")]),_:1})):n("",!0),t.showCreateNewContact?(o(),x(h,{key:3,type:"button",onClick:f.saveContact},{default:c(()=>[r("Save")]),_:1},8,["onClick"])):n("",!0)])):n("",!0),l.type=="textArea"?(o(),a("div",ae,[u(s("textarea",{class:"min-w-full","onUpdate:modelValue":e=>t.proposalData[l.field]=e,placeholder:""},null,8,ne),[[m,t.proposalData[l.field]]])])):n("",!0),l.type=="date"?(o(),a("div",ie,[y(D,{modelValue:t.proposalData[l.field],"onUpdate:modelValue":e=>t.proposalData[l.field]=e,showTime:!0,"step-minute":15,hourFormat:"12"},null,8,["modelValue","onUpdate:modelValue"])])):n("",!0),l.type=="eventTypeDropdown"?(o(),a("div",re,[u(s("select",{"onUpdate:modelValue":e=>t.proposalData[l.field]=e},[(o(!0),a(b,null,g(w.eventTypes,e=>(o(),a("option",{key:e.id,value:e.id},C(e.name),9,de))),128))],8,pe),[[E,t.proposalData[l.field]]])])):n("",!0)]))),128))]),s("div",ue,[s("button",{class:"bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline",onClick:i[5]||(i[5]=l=>f.updateProposal()),type:"submit"}," Create Proposal ")])])])])]),_:1})])}const Ce=U(A,[["render",ce]]);export{Ce as default};