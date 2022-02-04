<template>
  <div class="border-b-2 pt-1 pb-3 grid grid-cols-3">
    <div><card-icon :type="type" /></div>
    <div><span class="font-bold"> {{ name }}</span></div>
    <div><span class="float-right decoration-gray-400">{{ parsedDate }}</span></div>
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
        parsedDate:moment().format('YYYY-MM-DD')
      }
    },
    created(){
      this.parsedDate = moment(this.date).format('YYYY-MM-DD')
    }
}
</script>