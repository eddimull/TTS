<template>
  <editable-component :value="content">
    <template #show>
      {{ content.title }}
      <div
        v-for="(choice,index) in content.choices"
        :key="index"
      >
        <RadioButton
          :id="index"
      
          v-model="selected"
          :name="index"
          :value="choice"
        />
        <label :for="index">{{ choice }}</label>
      </div>
    </template>
    <template #edit>
      <div>
        <div>
          <label for="titleText">Title</label>
          <InputText 
            id="titleText"
            v-model="content.title"
          />
        </div>
        <div>
          Choices:
          <ul>
            <li
              v-for="(choice,index) in content.choices"
              :key="index"
              class="my-2"
            >
              <InputText
                :id="'choice' + index"
                v-model="content.choices[index]"
              />
              <Button
                v-if="content.choices.length !== 1"
                icon="pi pi-trash"
                class="p-button-danger"
                @click="removeChoice(index)"
              />
            </li>
            <Button 
              v-if="content.choices.length < 15"
              icon="pi pi-plus"
              @click="addChoice"
            />
          </ul>
        </div>
      </div>
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
          title:"untitled",
          choices:['a','b','c']
        }
      }
    }
  },
  setup() {
    
  },
  data(){
    return{
      originalData: {},
      editing:false,
      selected:null,
      content:this.value
    }
  },
  methods:{
    undoChanges()
    {
      this.content = JSON.parse(JSON.stringify(this.originalData));
      this.editing = false;
    },
    editMode()
    {
      this.originalData = JSON.parse(JSON.stringify(this.content))
      this.editing = true
    },
    addChoice()
    {
      this.content.choices.push('Choice ' + (this.content.choices.length+1));
    },
    removeChoice(index)
    {
      this.content.choices.splice(index,1);
    }
    
  }
}
</script>