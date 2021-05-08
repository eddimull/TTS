<template>
  <transition name="fade" appear>
    <div v-if="show">
    <!-- overlay -->
        <div id="modal_overlay" :class="[
            'fixed',
            'inset-0',
            'bg-black',
            'bg-opacity-30',
            'h-screen',
            'w-full',
            'flex',
            'justify-center',
            'items-start',
            'md:items-center',
            'pt-10',
            'md:pt-']">

        <div class="absolute w-full h-full" v-on:click="closeModal()">&nbsp;</div>
        <!-- modal -->
        <transition name="slide-down" appear>
          <div id="modal" v-if="show" :class="['transform','relative','w-10/12','md:w-1/2','h-1/2','md:h-3/4','bg-white','rounded','shadow-lg','transition-opacity','transition-transform','duration-300']">

              <!-- button close -->
              <button 
              v-on:click="closeModal()"
              class="fixed -top-3 -right-3 bg-red-500 hover:bg-red-600 text-2xl w-10 h-10 rounded-full focus:outline-none text-white">
              &cross;
              </button>

              <!-- header -->
              <div class="px-4 py-3 border-b border-gray-200">
              <h2 class="text-xl font-semibold text-gray-600 m-3"><slot name="header"></slot></h2>
              </div>

              <!-- body -->
              <div class="w-full p-3 overflow-y-auto h-5/6">
                  <slot name="body"></slot>
              </div>
              <!-- footer -->
              <div class="absolute bottom-0 left-0 px-4 py-3 border-t border-gray-200 w-full flex justify-end items-center gap-3">
              <button v-if="showSave" v-on:click="emitSave" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none">{{saveText}}</button>
              <button 
                  v-if="showClose"
                  v-on:click="closeModal()"
                  class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-white focus:outline-none"
              >{{closeText}}</button>
                <button 
                  v-if="showDelete"
                  v-on:click="emitDelete"
                  class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-white focus:outline-none"
              ><span>{{deleteText}} <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline align-middle -mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg></span></button>
              </div>
          </div>
        </transition>
        </div>
    </div>
  </transition>
</template>

<script>

export default {
  name: "Modal",
  props:{
    saveText:{
      type:String,
      default:'Save'
    },
    closeText:{
      type:String,
      default:'Close'
    },
    deleteText:{
      type:String,
      default:'Delete'
    },
    showDelete:{
      type:Boolean,
      default:false
    },
    showSave:{
      type:Boolean,
      default:true
    },
    showClose:{
      type:Boolean,
      default:true
    }
  },
  data() {
    return {
        show: true,
        overlayClasses:{
            
        },
        modalClasses:{
            
        }      
    };
  },
  methods: {
    closeModal() {
      this.show = false;
      setTimeout(()=>{

        this.$emit('closing');
      },300)
    //   document.querySelector("body").classList.remove("overflow-hidden");
    },
    openModal() {
      this.show = true;
    //   document.querySelector("body").classList.add("overflow-hidden");
    },
    emitSave(){
      this.$emit('save');
    },
    emitDelete(){
      this.$emit('delete');
    }
  },
  watch:{
    showDelete: {
        handler: function(newValue) {
            console.log('show delete',newValue)
        },
        deep: true
    }
  }
};
</script>
<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity .5s;
}
.fade-enter-from, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  opacity: 0;
}

.slide-down-enter-active{
  transition: all .3s ease;
  transition-delay: .1s;
}
.slide-down-leave-active {
  transition: all .5s cubic-bezier(1.0, 0.5, 0.8, 1.0);
}
.slide-down-enter-from, .slide-down-leave-to
/* .slide-fade-leave-active below version 2.1.8 */ {
  transform: translateY(-50px);
}
</style>