<template>
  <transition
    name="slide-fade"
    appear
  >
    <div
      v-if="successMessage && visible"
      class="absolute flex max-w-xs w-full mt-4 mr-4 top-0 right-0 bg-white rounded shadow p-4"
    >
      <div class="mr-2">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6 text-green-500"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </div>
      <div class="flex-1">
        {{ successMessage }}
      </div>
      <div class="ml-2">
        <button
          class="align-top text-gray-500 hover:text-gray-700 focus:outline-none focus:text-indigo-600"
          @click="visible = false"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6 cursor-pointer"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>
    </div>
  </transition>
  <transition
    name="slide-fade"
    appear
  >
    <div
      v-if="Object.keys(errors).length > 0 && visible"
      class="absolute flex max-w-xs w-full mt-4 mr-4 top-0 right-0 bg-white rounded shadow p-4"
    >
      <div class="mr-2">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6 text-red-500"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </div>
      <div class="flex-1"> 
        <ul class="list-inside text-sm">
          <li
            v-for="(error, key) in errors"
            :key="key"
          >
            {{ error }}
          </li>
        </ul>
      </div>
      <div class="ml-2">
        <button
          class="align-top text-gray-500 hover:text-gray-700 focus:outline-none focus:text-indigo-600"
          @click="visible = false"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-6 w-6 cursor-pointer"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>
    </div>  
  </transition>        
</template>
<script>
    export default {
        props: ['successMessage','errors'],
        data(){
            return{
                visible:false,
                timeout:null
            }
        },
        watch:{
            successMessage:{
                handler:function(val,oldval){
                    this.visible=true;
                    if(this.timeout)
                    {
                        clearTimeout(this.timeout);
                    }
                    this.timeout = setTimeout(()=>{
                        this.visible = false
                    },1000);
                },
                deep:true
            },
            errors:{
                handler:function(val,oldval){
                    
                    if(Object.keys(this.errors).length > 0)
                    {
                        this.visible=true;
                        if(this.timeout)
                        {
                            clearTimeout(this.timeout);
                        }
                        this.timeout = setTimeout(()=>{
                            this.visible = false
                        },4000);
                    }
                },
                deep:true
            }            
        },
        created(){
            if(this.successMessage !== null)
            {
                this.visible = true;
                this.timeout = setTimeout(()=>{
                    this.visible = false
                },2500);
            }
        }
    }
</script>
<style scoped>
.slide-fade-enter-active {
  transition: all .3s ease;
}
.slide-fade-leave-active {
  transition: all .8s cubic-bezier(1.0, 0.5, 0.8, 1.0);
}
.slide-fade-enter-from, .slide-fade-leave-to
/* .slide-fade-leave-active below version 2.1.8 */ {
  transform: translateX(10px);
  opacity: 0;
}
</style>