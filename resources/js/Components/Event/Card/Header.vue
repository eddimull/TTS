<template>
  <div class="border-b-2 pt-1 pb-3 grid grid-cols-3 items-center">
    <div><card-icon :type="type" /></div>
    <div class="grid grid-cols-1 items-center text-center">
      <div><span class="font-bold"> {{ name }}</span></div>
    </div>
    <div class="items-center">
      <span
        class="float-right text-gray-400 text-right text-sm font-bold"
      >{{ parsedDate.date }}<br>{{ parsedDate.day }}</span>
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
      this.parsedDate = {
        date: moment(this.date).format('MM-DD-YYYY'),
        day: moment(this.date).format('(ddd)'),
      }
    }
}
</script>