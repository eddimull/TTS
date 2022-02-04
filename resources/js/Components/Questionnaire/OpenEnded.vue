<template>
  <editable-component :value="content">
    <template #show>
      {{ content.title }}
      <div>
        <PVtextarea
          v-if="content.singleLine == false"
          v-model="answer"
          :auto-resize="true"
          rows="5"
          cols="30"
        />
        <InputText 
          v-if="content.singleLine"
          v-model="answer"
        />
      </div>
    </template>
    <template #edit>
      <div>
        <label for="titleText">Title</label>
        <InputText 
          id="titleText"
          v-model="content.title"
        />
      </div>
      <div>
        <Dropdown
          v-model="content.singleLine"
          :options="choices"
          option-label="name"
          option-value="value"
          placeholder="Multiple Line or single line"
        />
      </div>
      {{ originalData }}
    </template>
  </editable-component>
</template>
<script>
import EditableComponent from './EditableComponent';
export default {
  components:{
    EditableComponent
  },
  props:{
    value:{
      type:Object,
      default:()=>{
        return {
          title:"Open Ended Question",
          input:'',
          singleLine:true
        }
      }
    }
  },
  setup() {
    
  },
  data(){
    return{
      originalData:{},
      editing:false,
      answer:null,
      choices:[
        {
          name:'Multiple Lines',
          value:false
        },
        {
          name:'Single Line',
          value:true
        }
      ],
      content:this.value
    }
  },
  methods:{
    
  }
}
</script>