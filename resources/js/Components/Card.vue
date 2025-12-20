<template>
  <div class="inline-block">  
    <div class="max-w-sm rounded overflow-hidden shadow-lg">
      <!-- PDF Preview -->
      <div
        v-if="isPdf"
        class="bg-gradient-to-br from-red-50 to-orange-100 dark:from-gray-700 dark:to-gray-800 h-48 flex items-center justify-center overflow-hidden"
      >
        <div class="w-full h-full flex items-center justify-center bg-white">
          <pdf
            :src="picture"
            :page="1"
            style="transform: scale(0.3); transform-origin: center;"
          />
        </div>
      </div>
      <!-- Image Preview -->
      <img
        v-else-if="picture && picture !== false"
        class="w-full"
        :src="picture"
        :alt="title"
      >
      <div class="px-6 py-4">
        <div class="font-bold text-xl mb-2">
          {{ title }}
        </div>
        <p class="text-gray-700 text-base">
          {{ description }}
        </p>
      </div>
      <div
        v-if="hashTags.length > 0"
        class="px-6 pt-4 pb-2"
      >
        <span
          v-for="(tag,prop) in hashTags"
          :key="prop"
          class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2"
        >#{{ tag }}</span>
      </div>
    </div>
  </div>
</template>

<script>
import pdf from "@jbtje/vite-vue3pdf";

export default {
    name: 'Card',
    components: {
        pdf,
    },
    props: {
        picture: {
            default: null,
            type: [String, Boolean]
        },
        title: {
            default: 'No Title',
            type:String
        },
        description: {
            default: 'Description of the thing',
            type:String
        },
        hashTags:{
            default:[],
            type:Array
        },
        fileType: {
            default: null,
            type: String
        }
    },
    computed: {
        isPdf() {
            return this.fileType && this.fileType.indexOf('application/pdf') !== -1;
        }
    }
}
</script>
