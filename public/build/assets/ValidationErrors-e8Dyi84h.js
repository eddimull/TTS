import{_ as i,o as e,b as r,e as d,F as p,k as m,t as _,d as f}from"./app-rdp2O8e-.js";const h={props:["modelValue"],emits:["update:modelValue"],methods:{focus(){this.$refs.input.focus()}}},g=["value"];function $(n,s,a,u,c,t){return e(),r("input",{class:"border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm",value:a.modelValue,onInput:s[0]||(s[0]=o=>n.$emit("update:modelValue",o.target.value)),ref:"input"},null,40,g)}const b=i(h,[["render",$]]),V={computed:{errors(){return this.$page.props.errors},hasErrors(){return Object.keys(this.errors).length>0}}},k={key:0},x=d("div",{class:"font-medium text-red-600"},"Whoops! Something went wrong.",-1),y={class:"mt-3 list-disc list-inside text-sm text-red-600"};function B(n,s,a,u,c,t){return t.hasErrors?(e(),r("div",k,[x,d("ul",y,[(e(!0),r(p,null,m(t.errors,(o,l)=>(e(),r("li",{key:l},_(o),1))),128))])])):f("",!0)}const E=i(V,[["render",B]]);export{b as B,E as a};