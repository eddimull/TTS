<template>
  <editable-component>
    <template #show>
      {{ content }}
    </template>
    <template #edit>
      <PVtextarea
        v-model="content"
        :auto-resize="true"
        rows="5"
        cols="30"
      />
    </template>
  </editable-component>
</template>

<script>

import EditableComponent from './EditableComponent.vue'
export default {
  components:{
    EditableComponent
  },
  props:{
    value:{
      type:String,
      default:'Undefined'
    },

  },
  data() {
    return {
        content: this.value
    };
  },
  watch:{
    showDelete: {
       
    }
  },
  methods: {

  }
};
</script>
