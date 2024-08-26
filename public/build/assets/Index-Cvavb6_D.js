import{_ as oe,o as u,b as g,i as x,L as U,e as p,t as O,F as C,k as I,d as k,g as b,V as Se,p as q,m as G,a as S,c as v,W as J,r as B,v as ae,Q as T,X as _e,J as pe,Y as Q,Z as y,$ as we,a0 as z,a1 as re,a2 as se,a3 as L,n as N,B as Te,w as X,f as Y}from"./app-rdp2O8e-.js";const be={name:"VueUploadImages",data(){return{error:"",files:[],dropped:0,Imgs:[]}},props:{max:Number,uploadMsg:String,maxError:String,fileError:String,clearAll:String},methods:{dragOver(){this.dropped=2},dragLeave(){},drop(e){let t=!0,a=Array.from(e.dataTransfer.files);e&&a&&(a.forEach(o=>{o.type.startsWith("image")===!1&&(t=!1)}),t==!0?this.$props.max&&a.length+this.files.length>this.$props.max?this.error=this.$props.maxError?this.$props.maxError:"Maximum files is"+this.$props.max:(this.files.push(...a),this.previewImgs()):this.error=this.$props.fileError?this.$props.fileError:"Unsupported file type"),this.dropped=0},append(){this.$refs.uploadInput.click()},readAsDataURL(e){return new Promise(function(t,a){let o=new FileReader;o.onload=function(){t(o.result)},o.onerror=function(){a(o)},o.readAsDataURL(e)})},deleteImg(e){this.Imgs.splice(e,1),this.files.splice(e,1),this.$emit("changed",this.files),this.$refs.uploadInput.value=null},previewImgs(e){if(this.$props.max&&e&&e.currentTarget.files.length+this.files.length>this.$props.max){this.error=this.$props.maxError?this.$props.maxError:"Maximum files is"+this.$props.max;return}this.dropped==0&&this.files.push(...e.currentTarget.files),this.error="",this.$emit("changed",this.files);let t=[];if(this.files.length){for(let a=0;a<this.files.length;a++)t.push(this.readAsDataURL(this.files[a]));Promise.all(t).then(a=>{this.Imgs=a})}},reset(){this.$refs.uploadInput.value=null,this.Imgs=[],this.files=[],this.$emit("changed",this.files)}}},xe=e=>(q("data-v-4e73824b"),e=e(),G(),e),ke={class:"drop"},Ce={class:"beforeUpload"},Ie=Se('<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" data-v-4e73824b><title data-v-4e73824b>Upload Image</title><g id="Upload_Image" data-name="Upload Image" data-v-4e73824b><g id="_Group_" data-name="&lt;Group&gt;" data-v-4e73824b><g id="_Group_2" data-name="&lt;Group&gt;" data-v-4e73824b><g id="_Group_3" data-name="&lt;Group&gt;" data-v-4e73824b><circle id="_Path_" data-name="&lt;Path&gt;" cx="18.5" cy="16.5" r="5" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></circle></g><polyline id="_Path_2" data-name="&lt;Path&gt;" points="16.5 15.5 18.5 13.5 20.5 15.5" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></polyline><line id="_Path_3" data-name="&lt;Path&gt;" x1="18.5" y1="13.5" x2="18.5" y2="19.5" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></line></g><g id="_Group_4" data-name="&lt;Group&gt;" data-v-4e73824b><polyline id="_Path_4" data-name="&lt;Path&gt;" points="0.6 15.42 6 10.02 8.98 13" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></polyline><polyline id="_Path_5" data-name="&lt;Path&gt;" points="17.16 11.68 12.5 7.02 7.77 11.79" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></polyline><circle id="_Path_6" data-name="&lt;Path&gt;" cx="8" cy="6.02" r="1.5" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></circle><path id="_Path_7" data-name="&lt;Path&gt;" d="M19.5,11.6V4A1.5,1.5,0,0,0,18,2.5H2A1.5,1.5,0,0,0,.5,4V15A1.5,1.5,0,0,0,2,16.5H13.5" style="fill:none;stroke:#303c42;stroke-linecap:round;stroke-linejoin:round;" data-v-4e73824b></path></g></g></g></svg>',1),Fe={class:"mainMessage"},Re={class:"imgsPreview"},Ae=["src"],Me=["onClick"],Be=xe(()=>p("svg",{class:"icon",xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor"},[p("path",{"stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"})],-1)),De=[Be];function Ee(e,t,a,o,l,s){return u(),g("div",{class:"container",onDragover:t[3]||(t[3]=b((...n)=>s.dragOver&&s.dragOver(...n),["prevent"])),onDragleave:t[4]||(t[4]=b((...n)=>s.dragLeave&&s.dragLeave(...n),["prevent"])),onDrop:t[5]||(t[5]=b(n=>s.drop(n),["prevent"]))},[x(p("div",ke,null,512),[[U,l.dropped==2]]),x(p("div",{class:"error"},O(l.error),513),[[U,l.error]]),x(p("div",Ce,[p("input",{type:"file",style:{"z-index":"1"},accept:"image/*",ref:"uploadInput",onChange:t[0]||(t[0]=(...n)=>s.previewImgs&&s.previewImgs(...n)),multiple:""},null,544),Ie,p("p",Fe,O(a.uploadMsg?a.uploadMsg:"Click to upload or drop your images here"),1)],512),[[U,l.Imgs.length==0]]),x(p("div",Re,[p("button",{type:"button",class:"clearButton",onClick:t[1]||(t[1]=(...n)=>s.reset&&s.reset(...n))},O(a.clearAll?a.clearAll:"clear All"),1),(u(!0),g(C,null,I(l.Imgs,(n,r)=>(u(),g("div",{class:"imageHolder",key:r},[p("img",{src:n},null,8,Ae),p("span",{class:"delete",style:{color:"white"},onClick:i=>s.deleteImg(--r)},De,8,Me),++r==l.Imgs.length?(u(),g("div",{key:0,class:"plus",onClick:t[2]||(t[2]=(...i)=>s.append&&s.append(...i))},"+")):k("",!0)]))),128))],512),[[U,l.Imgs.length>0]])],32)}const Oe=oe(be,[["render",Ee],["__scopeId","data-v-4e73824b"]]);var Z=Q({name:"CloseIcon"}),Pe=J();q("data-v-1a43cbde");var Le={class:"icon-wrapper"},Ue=S("svg",{id:"Capa_1",version:"1.1",x:"0px",y:"0px",viewBox:"0 0 512.001 512.001",style:{"enable-background":"new 0 0 512.001 512.001"},"xml:space":"preserve"},[S("g",null,[S("g",null,[S("path",{d:`M284.286,256.002L506.143,34.144c7.811-7.811,7.811-20.475,0-28.285c-7.811-7.81-20.475-7.811-28.285,0L256,227.717\r
        L34.143,5.859c-7.811-7.811-20.475-7.811-28.285,0c-7.81,7.811-7.811,20.475,0,28.285l221.857,221.857L5.858,477.859\r
        c-7.811,7.811-7.811,20.475,0,28.285c3.905,3.905,9.024,5.857,14.143,5.857c5.119,0,10.237-1.952,14.143-5.857L256,284.287\r
        l221.857,221.857c3.905,3.905,9.024,5.857,14.143,5.857s10.237-1.952,14.143-5.857c7.811-7.811,7.811-20.475,0-28.285\r
        L284.286,256.002z`})])])],-1);G();var je=Pe(function(e,t,a,o,l,s){return u(),v("div",Le,[Ue])});Z.render=je,Z.__scopeId="data-v-1a43cbde",Z.__file="src/components/CloseIcon.vue";var $=Q({name:"Tag",components:{CloseIcon:Z},props:{name:{type:String,default:""},onRemove:{type:Function,required:!0},onEdit:{type:Function,required:!0},editable:{type:Boolean,default:!1},id:{type:String,default:null},highlight:Boolean,readOnly:{type:Boolean,default:!1},tagStyle:{type:Object,default:function(){return{foreColor:"",backgroundColor:""}}}},setup:function(e){var t=y(!1),a=y(e.name),o=y(),l=we(e,"highlight"),s=z(function(){return e.editable&&t.value&&!e.readOnly}),n=z(function(){return!e.readOnly}),r=z(function(){return{background:l.value?"#b20000":e.tagStyle.backgroundColor,color:e.tagStyle.foreColor}});return{handleRemove:function(i){return e.onRemove(i)},handleDoubleClick:function(){e.editable&&!e.readOnly&&(t.value=!t.value,re(function(){o.value.focus()}))},handleSaveEdit:function(){t.value=!1,e.onEdit(e.id,a.value)},handleEscape:function(){t.value=!1},editMode:t,input:a,inputTextRef:o,canShowInputbox:s,canShowRemoveBtn:n,style:r}}}),Ke=J()(function(e,t,a,o,l,s){var n=B("CloseIcon");return u(),v("div",{class:["tag-container",{"no-remove":!e.canShowRemoveBtn}],style:e.style},[e.canShowInputbox?x((u(),v("input",{key:0,ref:"inputTextRef","onUpdate:modelValue":t[1]||(t[1]=function(r){return e.input=r}),type:"text",class:"tag-edit-input",onBlur:t[2]||(t[2]=function(){for(var r=[],i=arguments.length;i--;)r[i]=arguments[i];return e.handleEscape&&e.handleEscape.apply(e,r)}),onKeyup:[t[3]||(t[3]=T(function(){for(var r=[],i=arguments.length;i--;)r[i]=arguments[i];return e.handleSaveEdit&&e.handleSaveEdit.apply(e,r)},["enter"])),t[4]||(t[4]=T(function(){for(var r=[],i=arguments.length;i--;)r[i]=arguments[i];return e.handleEscape&&e.handleEscape.apply(e,r)},["esc"]))]},null,544)),[[ae,e.input]]):(u(),v("span",{key:1,class:"tag-name",onDblclick:t[5]||(t[5]=function(){for(var r=[],i=arguments.length;i--;)r[i]=arguments[i];return e.handleDoubleClick&&e.handleDoubleClick.apply(e,r)})},O(e.name),33)),e.canShowRemoveBtn?(u(),v("button",{key:2,onClick:t[6]||(t[6]=function(r){return e.handleRemove(e.id)})},[S(n)])):k("v-if",!0)],6)});$.render=Ke,$.__scopeId="data-v-bb7ceecc",$.__file="src/components/Tag.vue";var ee=Q({name:"Tags",components:{Tag:$},props:{readOnly:{type:Boolean,default:!1},tags:{type:Array,required:!0},onRemove:{type:Function,required:!0},onEdit:{type:Function,required:!0},editable:{type:Boolean,default:!1},tagStyle:{type:Object,default:function(){return{}}}},setup:function(e){var t=e.readOnly?e.tags.map(function(o){return Object.assign({},o,{editable:!1})}):e.tags,a=y(t);return se(function(){return e.tags},function(o){a.value=o}),{localTags:a,handleRemove:function(o){return e.onRemove(o)},handleEdit:function(o,l){return e.onEdit(o,l)}}}}),he=J();q("data-v-acc9a86e");var Ne={class:"tags-container"};G();var He=he(function(e,t,a,o,l,s){var n=B("Tag");return u(),v("div",Ne,[S(_e,{name:"tags-list"},{default:he(function(){return[(u(!0),v(C,null,I(e.localTags,function(r){return u(),v(n,{id:r.id,key:r.id,highlight:r.highlight,name:r.name,value:r.value,"on-remove":e.handleRemove,"on-edit":e.handleEdit,editable:e.editable,"read-only":e.readOnly,"tag-style":e.tagStyle},null,8,["id","highlight","name","value","on-remove","on-edit","editable","read-only","tag-style"])}),128))]}),_:1}),pe(e.$slots,"default")])});ee.render=He,ee.__scopeId="data-v-acc9a86e",ee.__file="src/components/Tags.vue";var te=Q({name:"SuggestPane",props:{items:{type:Array,default:function(){return[]},required:!0},show:{type:Boolean,default:!1},onSelection:{type:Function},onPaneEsc:{type:Function},keyword:{type:String,required:!0},paneStyle:{type:Object,default:function(){return{bgColor:""}}},selectedIndex:{type:Number,default:-1}},setup:function(e){var t=y(!1),a=function(l){return e.onSelection&&e.onSelection(l)},o=y(null);return se(function(){return e.show},function(l){t.value=!!l}),{handleSelection:a,showPane:t,paneRef:o,handleEnter:function(l){l.preventDefault(),l.stopImmediatePropagation();var s=e.items[e.selectedIndex];a(s)}}}}),Ve=J();q("data-v-f2c085e8");var ze={key:0,class:"suggest-pane-container"};G();var qe=Ve(function(e,t,a,o,l,s){return e.showPane?(u(),v("div",ze,[S("ul",{ref:"paneRef",class:"suggest-pane",style:{background:e.paneStyle.bgColor},tabindex:"0"},[(u(!0),v(C,null,I(e.items,function(n,r){return u(),v("li",{key:n,class:["suggest-pane-item",{selected:r===e.selectedIndex}],onMousedown:function(i){return e.handleSelection(n)}},[S("span",null,O(n),1)],42,["onMousedown"])}),128))],4)])):k("v-if",!0)});te.render=qe,te.__scopeId="data-v-f2c085e8",te.__file="src/components/SuggestPane.vue";var ie=Q({name:"SmartTagz",components:{Tags:ee,SuggestionPane:te},props:{readOnly:{type:Boolean,default:!1},defaultTags:{type:Array,default:function(){return[]}},width:{type:String,default:"100%"},sources:{type:Array,default:function(){return[]}},autosuggest:{type:Boolean,default:!1},allowPaste:{type:Object,default:null},editable:{type:Boolean,default:!1},allowDuplicates:{type:Boolean,default:!0},maxTags:{type:Number,default:20},inputPlaceholder:{type:String,default:"Enter tag..."},quickDelete:{type:Boolean,default:!1},onChanged:{type:Function,default:null},theme:{type:Object,default:function(){return{primary:"#6093ca",background:"#eaf1f8",tagTextColor:"#fff"}}}},setup:function(e){var t=e.autosuggest,a=e.allowPaste,o=e.allowDuplicates,l=e.maxTags,s=e.defaultTags;s===void 0&&(s=[]);var n=e.sources,r=e.quickDelete,i=e.width,d=e.onChanged,F=y(null),h=y(s.slice(0,l).map(function(c){return{id:Math.random().toString(16).slice(2),name:c,value:c}})),_=y(null),D=y(""),E=y(s.length?Math.min(s.length,l):0),R=y(!1),j=y(!1),w=y(-1),fe=z(function(){return{width:i}}),H=z(function(){var c=new RegExp("^"+D.value,"i");return n.filter(function(f){return c.test(f)})}),ue=function(){_.value.focus()};se(D,function(c){F.value&&(F.value=null,h.value=h.value.map(function(f){return delete f.highlight,f})),c?(j.value=!1,t&&c.length>0?R.value=!0:t&&c.length<1&&(R.value=!1)):R.value=!1}),se(j,function(c){h.value=h.value.map(function(f){return Object.assign({},f,{highlight:c})})});var de=function(c){var f="",m=L(w);if(function(A){var ne=new RegExp("^"+A+"$","ig"),V=o||!h.value.some(function(W){return W.name===A||ne.test(W.name)}),le=E.value<l;return V&&le}(f=m>-1?H.value[m]:c)){var P=null;(P=R.value&&w.value>-1?H.value[w.value]:f)&&(h.value=h.value.concat({name:P,id:Math.random().toString(16).slice(2),value:P})),d&&d(h.value.map(function(A){return A.value})),D.value="",R.value=!1,E.value+=1,w.value=-1,re(function(){return ue()})}};return{tagsData:h,input:D,style:fe,textInputRef:_,showSuggestions:R,selectedIndex:w,filteredItems:H,handleKeyUp:function(c){c.preventDefault();var f=L(w);w.value=f>0?w.value-1:L(H).length-1},handleKeydown:function(c){c.preventDefault(),L(w)<L(H).length-1?w.value=w.value+1:w.value=0},handleAddTag:de,handleRemoveTag:function(c){h.value=h.value.filter(function(f){return f.id!==c}),E.value-=1,d&&d(h.value.map(function(f){return f.value}))},handleDelete:function(){if(!D.value){if(j.value)return h.value=[],j.value=!1,void(E.value=0);if(F.value){var c=F.value;h.value=h.value.filter(function(m){return m.id!==c.id}),F.value=null,E.value-=1}else if(h.value.length){var f=h.value[h.value.length-1];F.value={id:f.id},h.value=h.value.map(function(m){return m.id===f.id?Object.assign({},m,{highlight:!0}):m})}}},handleEscape:function(){return h.value=h.value.map(function(c){return delete c.highlight,c}),R.value=!1,j.value=!1,void(w.value=-1)},handlePaste:function(c){c.stopPropagation(),c.preventDefault();var f=c.clipboardData&&c.clipboardData.getData("text");if(f){var m=function(P,A,ne,V,le,W){if(A){var me=ne-V,M=A.split(le);if(!(M.length>1))return{newData:P.concat({name:A,value:A,id:Math.random().toString(16).slice(2)}),tagsCreated:V+1};if(M=M.slice(0,Math.min(M.length,me)),!W){var ve=P.map(function(K){return K.name}),ye=M.filter(function(K){return ve.indexOf(K)<0});M=[].concat(new Set(ye))}if(M.length)return{newData:P.concat(M.map(function(K){return{name:K,value:K,id:Math.random().toString(16).slice(2)}})),tagsCreated:V+M.length}}}(L(h),f,l,L(E),a.delimiter,o);m&&m.newData&&(h.value=m.newData,E.value=m.tagsCreated)}},handleEditTag:function(c,f){h.value=h.value.map(function(m){return m.id===c?Object.assign({},m,{name:f,value:f}):m})},handleSuggestSelection:function(c){R.value=!1,re(function(){de(c)})},handleSuggestEsc:function(){ue(),R.value=!1},handleSelectAll:function(c){r&&(c.keyCode!==65||D.value||(j.value=!0,F.value=null))}}}}),ce=J();q("data-v-54d3a52e");var Ge={key:0,class:"input-wrapper"};G();var Je=ce(function(e,t,a,o,l,s){var n=B("SuggestionPane"),r=B("Tags");return u(),v("div",{class:"tags-main",style:{background:e.theme.background},onKeyup:t[10]||(t[10]=b(function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleSelectAll&&e.handleSelectAll.apply(e,i)},["ctrl"]))},[S(r,{tags:e.tagsData,"on-remove":e.handleRemoveTag,"on-edit":e.handleEditTag,editable:e.editable,"read-only":e.readOnly,"tag-style":{foreColor:e.theme.tagTextColor,backgroundColor:e.theme.primary}},{default:ce(function(){return[e.tagsData.length<e.maxTags?(u(),v("div",Ge,[e.readOnly?k("v-if",!0):x((u(),v("input",{key:0,ref:"textInputRef","onUpdate:modelValue":t[1]||(t[1]=function(i){return e.input=i}),type:"text",placeholder:e.inputPlaceholder,onKeyup:[t[2]||(t[2]=T(function(i){return e.handleAddTag(i.target.value.trim())},["enter"])),t[3]||(t[3]=T(function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleDelete&&e.handleDelete.apply(e,i)},["delete"])),t[4]||(t[4]=T(function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleEscape&&e.handleEscape.apply(e,i)},["esc"]))],onKeydown:[t[5]||(t[5]=T(function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleKeydown&&e.handleKeydown.apply(e,i)},["down"])),t[6]||(t[6]=T(function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleKeyUp&&e.handleKeyUp.apply(e,i)},["up"])),t[7]||(t[7]=b(function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleSelectAll&&e.handleSelectAll.apply(e,i)},["ctrl","exact"]))],onPaste:t[8]||(t[8]=function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handlePaste&&e.handlePaste.apply(e,i)}),onBlur:t[9]||(t[9]=function(){for(var i=[],d=arguments.length;d--;)i[d]=arguments[d];return e.handleEscape&&e.handleEscape.apply(e,i)})},null,40,["placeholder"])),[[ae,e.input]]),S("div",{class:["suggestion-wrapper",{hidden:!e.showSuggestions}]},[S(n,{show:e.showSuggestions,items:e.filteredItems,keyword:e.input,"on-selection":e.handleSuggestSelection,"on-pane-esc":e.handleSuggestEsc,"pane-style":{bgColor:e.theme.primary},"selected-index":e.selectedIndex,focus:!1},null,8,["show","items","keyword","on-selection","on-pane-esc","pane-style","selected-index"])],2)])):k("v-if",!0)]}),_:1},8,["tags","on-remove","on-edit","editable","read-only","tag-style"])],36)});ie.render=Je,ie.__scopeId="data-v-54d3a52e",ie.__file="src/components/Main.vue";const Qe={props:{elementId:String,inputId:String,existingTags:{type:Array,default:()=>[]},value:{type:Array,default:()=>[]},idField:{type:String,default:"key"},textField:{type:String,default:"value"},displayField:{type:String,default:null},valueFields:{type:String,default:null},disabled:{type:Boolean,default:!1},typeahead:{type:Boolean,default:!1},typeaheadStyle:{type:String,default:"badges"},typeaheadActivationThreshold:{type:Number,default:1},typeaheadMaxResults:{type:Number,default:0},typeaheadAlwaysShow:{type:Boolean,default:!1},typeaheadShowOnFocus:{type:Boolean,default:!0},typeaheadHideDiscard:{type:Boolean,default:!1},typeaheadUrl:{type:String,default:""},typeaheadCallback:{type:Function,default:null},placeholder:{type:String,default:"Add a tag"},discardSearchText:{type:String,default:"Discard Search Results"},limit:{type:Number,default:0},hideInputOnLimit:{type:Boolean,default:!1},onlyExistingTags:{type:Boolean,default:!1},deleteOnBackspace:{type:Boolean,default:!0},allowDuplicates:{type:Boolean,default:!1},validate:{type:Function,default:()=>!0},addTagsOnComma:{type:Boolean,default:!1},addTagsOnSpace:{type:Boolean,default:!1},addTagsOnBlur:{type:Boolean,default:!1},wrapperClass:{type:String,default:"tags-input-wrapper-default"},sortSearchResults:{type:Boolean,default:!0},caseSensitiveTags:{type:Boolean,default:!1},beforeAddingTag:{type:Function,default:()=>!0},beforeRemovingTag:{type:Function,default:()=>!0}},data(){return{badgeId:0,tags:[],input:"",oldInput:"",hiddenInput:"",searchResults:[],searchSelection:0,selectedTag:-1,isActive:!1,composing:!1}},created(){this.typeaheadTags=this.cloneArray(this.existingTags),this.tagsFromValue(),this.typeaheadAlwaysShow&&this.searchTag(!1)},mounted(){this.$emit("initialized"),document.addEventListener("click",e=>{e.target!==this.$refs.taginput&&this.clearSearchResults()})},computed:{hideInputField(){return this.hideInputOnLimit&&this.limit>0&&this.tags.length>=this.limit||this.disabled}},watch:{input(e,t){this.searchTag(!1),e.length&&e!=t&&(e.substring(t.length,e.length),this.addTagsOnSpace&&e.endsWith(" ")&&(this.input=e.trim(),this.tagFromInput(!0)),this.addTagsOnComma&&(e=e.trim(),e.endsWith(",")&&(this.input=e.substring(0,e.length-1),this.tagFromInput(!0))),this.$emit("change",e))},existingTags(e){this.typeaheadTags.splice(0),this.typeaheadTags=this.cloneArray(e),this.searchTag()},tags(){this.hiddenInput=JSON.stringify(this.tags),this.$emit("input",this.tags)},value(){this.tagsFromValue()},typeaheadAlwaysShow(e){e?this.searchTag(!1):this.clearSearchResults()}},methods:{escapeRegExp(e){return e.replace(/[.*+?^${}()|[\]\\]/g,"\\$&")},tagFromInput(e=!1){if(!this.composing)if(this.searchResults.length&&this.searchSelection>=0&&!e)this.tagFromSearch(this.searchResults[this.searchSelection]),this.input="";else{let t=this.input.trim();if(!this.onlyExistingTags&&t.length&&this.validate(t)){this.input="";let a={[this.idField]:"",[this.textField]:t};const o=this.escapeRegExp(this.caseSensitiveTags?a[this.textField]:a[this.textField].toLowerCase());for(let l of this.typeaheadTags){const s=this.escapeRegExp(this.caseSensitiveTags?l[this.textField]:l[this.textField].toLowerCase());if(o===s){a=Object.assign({},l);break}}this.addTag(a)}}},tagFromSearchOnClick(e){this.tagFromSearch(e),this.$refs.taginput.blur()},tagFromSearch(e){this.clearSearchResults(),this.addTag(e),this.$nextTick(()=>{this.input="",this.oldInput=""})},addTag(e,t=!1){if(!(this.disabled&&!t)){if(!this.beforeAddingTag(e))return!1;if(this.limit>0&&this.tags.length>=this.limit)return this.$emit("limit-reached"),!1;this.tagSelected(e)||(this.tags.push(e),this.$nextTick(()=>{this.$emit("tag-added",e),this.$emit("tags-updated")}))}},removeLastTag(){!this.input.length&&this.deleteOnBackspace&&this.tags.length&&this.removeTag(this.tags.length-1)},removeTag(e){if(this.disabled)return;let t=this.tags[e];if(!this.beforeRemovingTag(t))return!1;this.tags.splice(e,1),this.$nextTick(()=>{this.$emit("tag-removed",t),this.$emit("tags-updated"),this.typeaheadAlwaysShow&&this.searchTag()})},searchTag(){if(this.typeahead!==!0)return!1;if(this.oldInput!=this.input||!this.searchResults.length&&this.typeaheadActivationThreshold==0||this.typeaheadAlwaysShow||this.typeaheadShowOnFocus){!this.typeaheadUrl.length&&!this.typeaheadCallback&&(this.searchResults=[]),this.searchSelection=0;let e=this.input.trim();if(e.length&&e.length>=this.typeaheadActivationThreshold||this.typeaheadActivationThreshold==0||this.typeaheadAlwaysShow){const t=this.escapeRegExp(this.caseSensitiveTags?e:e.toLowerCase());if(this.typeaheadCallback)this.typeaheadCallback(t).then(a=>{this.typeaheadTags=a});else if(this.typeaheadUrl.length>0){this.typeaheadTags.splice(0);const a=new XMLHttpRequest,o=this;a.onreadystatechange=function(){this.readyState==4&&this.status==200&&(o.typeaheadTags=JSON.parse(a.responseText),o.doSearch(t))};const l=this.typeaheadUrl.replace(":search",t);a.open("GET",l,!0),a.send()}else this.doSearch(t)}this.oldInput=this.input}},doSearch(e){this.searchResults=[];for(let t of this.typeaheadTags){const a=this.caseSensitiveTags?t[this.textField]:t[this.textField].toLowerCase(),o=this.searchResults.map(l=>l[this.idField]);a.search(e)>-1&&!this.tagSelected(t)&&!o.includes(t[this.idField])&&this.searchResults.push(t)}this.sortSearchResults&&this.searchResults.sort((t,a)=>t[this.textField]<a[this.textField]?-1:t[this.textField]>a[this.textField]?1:0),this.typeaheadMaxResults>0&&(this.searchResults=this.searchResults.slice(0,this.typeaheadMaxResults))},hideTypeahead(){this.input.length||this.$nextTick(()=>{this.clearSearchResults()})},nextSearchResult(){this.searchSelection+1<=this.searchResults.length-1&&this.searchSelection++},prevSearchResult(){this.searchSelection>0&&this.searchSelection--},clearSearchResults(e=!1){this.searchResults=[],this.searchSelection=0,this.typeaheadAlwaysShow&&this.$nextTick(()=>{this.searchTag()}),e&&this.$refs.taginput.focus()},clearTags(){this.tags.splice(0,this.tags.length)},tagsFromValue(){if(this.value&&this.value.length){if(!Array.isArray(this.value)){console.error("Voerro Tags Input: the v-model value must be an array!");return}let e=this.value;if(this.tags==e)return;this.clearTags();for(let t of e)this.addTag(t,!0)}else{if(this.tags.length==0)return;this.clearTags()}},tagSelected(e){if(this.allowDuplicates||!e)return!1;const t=this.escapeRegExp(this.caseSensitiveTags?e[this.textField]:e[this.textField].toLowerCase());for(let a of this.tags){const o=this.caseSensitiveTags?a[this.textField]:a[this.textField].toLowerCase();if(a[this.idField]===e[this.idField]&&this.escapeRegExp(o).length==t.length&&o.search(t)>-1)return!0}return!1},clearInput(){this.input=""},onKeyUp(e){this.$emit("keyup",e)},onKeyDown(e){this.$emit("keydown",e)},onFocus(e){this.$emit("focus",e),this.isActive=!0},onClick(e){this.$emit("click",e),this.isActive=!0,this.searchTag()},onBlur(e){this.$emit("blur",e),this.addTagsOnBlur&&this.tagFromInput(!0),this.typeaheadAlwaysShow?this.searchTag():this.hideTypeahead(),this.isActive=!1},hiddenInputValue(e){if(!this.valueFields)return JSON.stringify(e);const t=this.valueFields.replace(/\s/,"").split(",");return t.length===1?e[t[0]]:JSON.stringify(Object.assign({},...t.map(a=>({[a]:e[a]}))))},getDisplayField(e){return this.displayField!==void 0&&this.displayField!==null&&e[this.displayField]!==void 0&&e[this.displayField]!==null&&e[this.displayField]!==""?e[this.displayField]:e[this.textField]},cloneArray(e){return e.map(t=>Object.assign({},t))}}},We={class:"tags-input-root",style:{position:"relative"}},Xe=["innerHTML"],Ye=["onClick"],Ze=["id","name","placeholder","value"],$e={key:0,style:{display:"none"}},et=["name","value"],tt=["textContent"],at=["innerHTML","onMouseover","onMousedown"],st=["textContent"],nt=["innerHTML","onMouseover","onMousedown"];function lt(e,t,a,o,l,s){return u(),g("div",We,[p("div",{class:N({[a.wrapperClass+" tags-input"]:!0,active:l.isActive,disabled:a.disabled})},[(u(!0),g(C,null,I(l.tags,(n,r)=>(u(),g("span",{key:r,class:N(["tags-input-badge tags-input-badge-pill tags-input-badge-selected-default",{disabled:a.disabled}])},[pe(e.$slots,"selected-tag",{tag:n,index:r,removeTag:s.removeTag},()=>[p("span",{innerHTML:n[a.textField]},null,8,Xe),x(p("a",{href:"#",class:"tags-input-remove",onClick:b(i=>s.removeTag(r),["prevent"])},null,8,Ye),[[U,!a.disabled]])])],2))),128)),x(p("input",{type:"text",ref:"taginput",id:a.inputId,name:a.inputId,placeholder:a.placeholder,value:l.input,onInput:t[0]||(t[0]=n=>l.input=n.target.value),onCompositionstart:t[1]||(t[1]=n=>l.composing=!0),onCompositionend:t[2]||(t[2]=n=>l.composing=!1),onKeydown:[t[3]||(t[3]=T(b(n=>s.tagFromInput(!1),["prevent"]),["enter"])),t[4]||(t[4]=T((...n)=>s.removeLastTag&&s.removeLastTag(...n),["8"])),t[5]||(t[5]=T((...n)=>s.nextSearchResult&&s.nextSearchResult(...n),["down"])),t[6]||(t[6]=T((...n)=>s.prevSearchResult&&s.prevSearchResult(...n),["up"])),t[7]||(t[7]=(...n)=>s.onKeyDown&&s.onKeyDown(...n))],onKeyup:[t[8]||(t[8]=(...n)=>s.onKeyUp&&s.onKeyUp(...n)),t[9]||(t[9]=T((...n)=>s.clearSearchResults&&s.clearSearchResults(...n),["esc"]))],onFocus:t[10]||(t[10]=(...n)=>s.onFocus&&s.onFocus(...n)),onClick:t[11]||(t[11]=(...n)=>s.onClick&&s.onClick(...n)),onBlur:t[12]||(t[12]=(...n)=>s.onBlur&&s.onBlur(...n)),onValue:t[13]||(t[13]=(...n)=>l.tags&&l.tags(...n))},null,40,Ze),[[U,!s.hideInputField]]),a.elementId?(u(),g("div",$e,[(u(!0),g(C,null,I(l.tags,(n,r)=>(u(),g("input",{key:r,type:"hidden",name:`${a.elementId}[]`,value:s.hiddenInputValue(n)},null,8,et))),128))])):k("",!0)],2),x(p("div",null,[a.typeaheadStyle==="badges"?(u(),g("p",{key:0,class:N(`typeahead-${a.typeaheadStyle}`)},[a.typeaheadHideDiscard?k("",!0):(u(),g("span",{key:0,class:"tags-input-badge typeahead-hide-btn tags-input-typeahead-item-default",onClick:t[14]||(t[14]=b(n=>s.clearSearchResults(!0),["prevent"])),textContent:O(a.discardSearchText)},null,8,tt)),(u(!0),g(C,null,I(l.searchResults,(n,r)=>(u(),g("span",{key:r,innerHTML:n[a.textField],onMouseover:i=>l.searchSelection=r,onMousedown:b(i=>s.tagFromSearchOnClick(n),["prevent"]),class:N(["tags-input-badge",{"tags-input-typeahead-item-default":r!=l.searchSelection,"tags-input-typeahead-item-highlighted-default":r==l.searchSelection}])},null,42,at))),128))],2)):a.typeaheadStyle==="dropdown"?(u(),g("ul",{key:1,class:N(`typeahead-${a.typeaheadStyle}`)},[a.typeaheadHideDiscard?k("",!0):(u(),g("li",{key:0,class:"tags-input-typeahead-item-default typeahead-hide-btn",onClick:t[15]||(t[15]=b(n=>s.clearSearchResults(!0),["prevent"])),textContent:O(a.discardSearchText)},null,8,st)),(u(!0),g(C,null,I(l.searchResults,(n,r)=>(u(),g("li",{key:r,innerHTML:s.getDisplayField(n),onMouseover:i=>l.searchSelection=r,onMousedown:b(i=>s.tagFromSearchOnClick(n),["prevent"]),class:N({"tags-input-typeahead-item-default":r!=l.searchSelection,"tags-input-typeahead-item-highlighted-default":r==l.searchSelection})},null,42,nt))),128))],2)):k("",!0)],512),[[U,l.searchResults.length]])])}const ge=oe(Qe,[["render",lt]]);window.VoerroTagsInput=ge;const it={components:{BreezeAuthenticatedLayout:Te,UploadImages:Oe,"tags-input":ge},props:["bands","colors","successMessage"],data(){return{showModal:!1,tags:[],tagsSeparate:[],form:this.$inertia.form({_method:"PUT",color_id:"",color_title:"",color_tags:"",color_photos:[],colorway_description:"",band_id:"",onSuccess:()=>{this.$refs.modalName.closeModal()}})}},methods:{toggleModal(){this.showModal=!this.showModal},handleImages(e){e.target||(this.form.color_photos=[],e.forEach(t=>this.form.color_photos.push(t)))},clearColor(){this.form.color_id="",this.form.color_title="",this.tags=[],this.tagsSeparate=[],this.form.color_tags="",this.form.colorway_description="",this.form.color_photos=[],this.uploadedImages=[]},setColor(e){const t=[];e.color_tags===null&&(e.color_tags=""),e.color_tags.split(",").forEach((a,o)=>{t.push({key:o,value:a})}),this.form.color_id=e.id,this.form.color_title=e.color_title,this.tags=t,this.tagsSeparate=[],this.form.color_tags=this.tagsSeparate.join(),this.form.colorway_description=e.colorway_description,this.form.color_photos=e.photos,this.uploadedImages=e.photos,this.toggleModal()},updatePreview(e){console.info(e)},setBandID(e){this.form.band_id=e},setUpdating(e){this.updatingColor=e},addTag(e){this.tagsSeparate.push(e.value),this.formatTags()},removeTag(e){const t=this.tagsSeparate.indexOf(e.value);this.tagsSeparate.splice(t,1),this.formatTags()},formatTags(){this.modifying&&(this.form.color_tags=this.tagsSeparate.join())},getColors(e){return this.colors.filter(a=>a.band_id==e)},saveColor(){this.updatingColor?this.$inertia.patch("/colors/"+this.form.color_id,{data:{color_title:this.form.color_title,color_tags:this.tagsSeparate.join(),colorway_description:this.form.colorway_description,color_photos:this.form.color_photos}},{onFinish:()=>{this.$refs.modalName.closeModal()}}):this.$inertia.post("/colors/",{data:{color_title:this.form.color_title,color_tags:this.tagsSeparate.join(),colorway_description:this.form.colorway_description,color_photos:this.form.color_photos,band_id:this.form.band_id}},{onSuccess:()=>{this.$refs.modalName.closeModal()}})},deleteColor(){this.$inertia.delete("/colors/"+this.form.color_id,{onSuccess:()=>{this.$refs.modalName.closeModal()}})}}},rt=p("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Colorways ",-1),ot=p("h1",null,"Add Colorway",-1),ut={class:"py-4"},dt={key:0,class:"grid grid-cols-3 gap-4"},ht=["src"],ct={class:"md:container md:mx-auto"},pt={class:"max-w-7xl mx-auto sm:px-6 lg:px-8"},gt={class:"grid grid-cols-3 gap-4"},ft=["onClick"],mt=p("div",{class:"flex flex-wrap content-center justify-center"},[p("div",null," Create new "),p("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-6 w-6",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor"},[p("path",{"stroke-linecap":"round","stroke-linejoin":"round","stroke-width":"2",d:"M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"})])],-1),vt=[mt];function yt(e,t,a,o,l,s){const n=B("UploadImages"),r=B("tags-input"),i=B("card-modal"),d=B("card"),F=B("breeze-authenticated-layout");return u(),v(F,null,{header:X(()=>[rt]),default:X(()=>[l.showModal?(u(),v(i,{key:0,ref:"modalName","save-text":e.updatingColor?"Update":"Save","show-delete":e.updatingColor,onSave:s.saveColor,onClosing:t[2]||(t[2]=h=>s.toggleModal()),onDelete:s.deleteColor},{header:X(()=>[ot]),body:X(()=>[p("div",ut,[S(n,{onChange:s.handleImages},null,8,["onChange"])]),e.uploadedImages.length>0?(u(),g("div",dt,[Y(" Uploaded Images: "),(u(!0),g(C,null,I(e.uploadedImages,(h,_)=>(u(),g("div",{key:_},[p("img",{src:"https://bandapp.s3.us-east-2.amazonaws.com/"+h.photo_name},null,8,ht)]))),128))])):k("",!0),Y(" Title: "),p("div",null,[x(p("input",{"onUpdate:modelValue":t[0]||(t[0]=h=>l.form.color_title=h),type:"text"},null,512),[[ae,l.form.color_title]])]),Y(" Description: "),p("div",null,[x(p("textarea",{"onUpdate:modelValue":t[1]||(t[1]=h=>l.form.colorway_description=h),class:"min-w-full",placeholder:""},null,512),[[ae,l.form.colorway_description]])]),Y(" Hashtags: "),p("div",null,[S(r,{"element-id":"tags",value:l.tags,"id-field":"id","text-field":"value",onTagAdded:s.addTag,onTagRemoved:s.removeTag},null,8,["value","onTagAdded","onTagRemoved"])])]),_:1},8,["save-text","show-delete","onSave","onDelete"])):k("",!0),p("div",ct,[p("div",pt,[(u(!0),g(C,null,I(a.bands,h=>(u(),g("div",{key:h.name,class:"bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4"},[p("h4",null,O(h.name),1),p("div",gt,[(u(!0),g(C,null,I(s.getColors(h.id),(_,D)=>(u(),g("div",{key:D},[S(d,{title:_.color_title,description:_.colorway_description,picture:_.photos.length>0?"https://bandapp.s3.us-east-2.amazonaws.com/"+_.photos[0].photo_name:!1,"hash-tags":_.color_tags!==null?_.color_tags.split(","):[],onClick:E=>{s.setColor(_),s.setUpdating(!0)}},null,8,["title","description","picture","hash-tags","onClick"])]))),128)),p("div",{class:"h-56 m-10 cursor-pointer transition-colors flex content-center justify-center max-w-sm rounded overflow-hidden shadow-lg border-2 hover:bg-green-100",onClick:_=>{s.toggleModal(),s.setBandID(h.id),s.clearColor(),s.setUpdating(!1)}},vt,8,ft)])]))),128))])])]),_:1})}const _t=oe(it,[["render",yt]]);export{_t as default};