<template>
  <div class="border-b-2 pt-1 pb-3 grid grid-cols-3 items-center">
    <div><card-icon :type="type" /></div>
    <div class="grid grid-cols-1 items-center">
      <div><span class="font-bold"> {{ name }}</span></div>
    </div>
    <div class="items-center">
      <span class="float-right decoration-gray-400">{{ parsedDate }}</span>
    </div>
  </div>
</template>
<script>
import CardIcon from './CardIcon.vue'
import moment from 'moment'
export default {
    components:{
      CardIcon
    },
    props:['name','type','date'],
    data(){
      return{
        parsedDate:moment().format('mm-dd-yyyy')
      }
    },
    created(){
      this.parsedDate = moment(this.date).format('MM-DD-YYYY (ddd)')
    }
}
</script>