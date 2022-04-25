<template>
  <div 
    v-if="showing"
    style="width: 410px"
    class="fixed right-0 pr-2 bg-gray-700 h-screen text-white"
  >
    <div class="p-4 flex flex-row justify-between">
      <div>Side Editor</div>
      <div
        class="text-xl cursor-pointer"
        @click="hide"
      >
        X
      </div>
    </div>
    <div class="flex flex-row justify-around bg-gray-800 p-4">
      <div>General</div>
      <div>options</div>
    </div>
  </div>
</template>

<script>
export default {
    data(){
        return {
            showing:false
        }
    },
    methods:{
        show()
        {
            this.showing = true;
        },
        hide()
        {
            this.showing = false;
        }
    }
}
</script>

<style>

</style>