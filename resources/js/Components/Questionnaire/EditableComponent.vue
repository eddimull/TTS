<template>
  <div>
    <div
      v-if="!editing"
      class="my-4 hover:bg-gray-200 cursor-pointer"
      @click="editMode"
    > 
      <slot name="show" />
    </div>
    <div v-else>
      <slot name="edit" />
      <Button
        label="Save"
        icon="pi pi-save"
        @click="editing = false"
      />
      <Button
        label="Undo Changes"
        icon="pi pi-undo"
        @click="undoChanges"
      />
    </div>
  </div>
</template>
<script>

export default {
    components:{
        // OpenEnded,
        // Header,
        // MultipleChoice
    },
    props:{
        type:{
            type:String,
            default:'header'
        },
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
            editing:false,
            content:this.value
        }
    },
    methods:{
        undoChanges()
        {
        console.log('undid',this.content);
        this.content = JSON.parse(JSON.stringify(this.originalData));
        this.editing = false;
        },
        editMode()
        {
        console.log('editing',this.content);
        this.originalData = JSON.parse(JSON.stringify(this.content))
        this.editing = true
        }
  }
}
</script>