<template>
     <div class="inline-block">  
        <div class="max-w-sm rounded overflow-hidden shadow-lg">
        <img v-if="picture !== false" class="w-full" :src="picture" :alt="title">
        <div class="px-6 py-4">
            <div class="font-bold text-xl mb-2">{{ title }}</div>
            <p class="text-gray-700 text-base">
                {{ description }}
            </p>
        </div>
        <div class="px-6 pt-4 pb-2"  v-if="hashTags.length > 0">
            <span v-for="(tag,prop) in hashTags" :key="prop" class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">#{{tag}}</span>
        </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            picture: {
                default: 'https://scontent-dfw5-1.xx.fbcdn.net/v/t1.6435-9/90202670_2981972625166587_3140944514533818368_n.jpg?_nc_cat=111&ccb=1-3&_nc_sid=cdbe9c&_nc_ohc=_rByVu-Lk6MAX8T3vNM&_nc_ht=scontent-dfw5-1.xx&oh=782d09d2a26045a7eb03568bc3c9c264&oe=60AE70A5',
                type:String
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
            }
        }
    }
</script>
