<template>
  <div>
    <!-- overlay -->
        <div id="modal_overlay" :class="{'hidden':!show,
            'absolute':true,
            'inset-0':true,
            'bg-black':true,
            'bg-opacity-30':true,
            'h-screen':true,
            'w-full':true,
            'flex':true,
            'justify-center':true,
            'items-start':true,
            'md:items-center':true,
            'pt-10':true,
            'md:pt-':true}">

        <!-- modal -->
        <div id="modal" :class="[{'opacity-0':!show, '-translate-y-full':!show,'scale-150 ':!show},'transform','relative','w-10/12','md:w-1/2','h-1/2','md:h-3/4','bg-white','rounded','shadow-lg','transition-opacity','transition-transform','duration-300']">

            <!-- button close -->
            <button 
            v-on:click="closeModal()"
            class="absolute -top-3 -right-3 bg-red-500 hover:bg-red-600 text-2xl w-10 h-10 rounded-full focus:outline-none text-white">
            &cross;
            </button>

            <!-- header -->
            <div class="px-4 py-3 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-600"><slot name="header"></slot></h2>
            </div>

            <!-- body -->
            <div class="w-full p-3">
                <slot name="body"></slot>
            </div>
            <!-- footer -->
            <div class="absolute bottom-0 left-0 px-4 py-3 border-t border-gray-200 w-full flex justify-end items-center gap-3">
            <button v-on:click="emitSave" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none">Save</button>
            <button 
                v-on:click="closeModal()"
                class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-white focus:outline-none"
            >Close</button>
            </div>
        </div>
        </div>
  </div>
</template>

<script>

export default {
  name: "Modal",
  data() {
    return {
        show: false,
        overlayClasses:{
            
        },
        modalClasses:{
            
        }      
    };
  },
  methods: {
    closeModal() {
      this.show = false;
    //   document.querySelector("body").classList.remove("overflow-hidden");
    },
    openModal() {
      this.show = true;
      console.log('should open modal')
    //   document.querySelector("body").classList.add("overflow-hidden");
    },
    emitSave(){
      this.$emit('save');
    }
  }
};
</script>