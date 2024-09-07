<template>
  <calendar
    v-model="date"
    :show-time="true"
    :step-minute="15"
    hour-format="12"
    @input="emitDate"
  >
    <template #date="slotProps">
      <strong
        v-if="findReservedDate(slotProps.date)"
        :title="findReservedDateName(slotProps.date)"
        class="rounded-full h-24 w-24 flex items-center justify-center bg-red-300"
        @click="$swal.fire('Date already booked with ' + findReservedDateName(slotProps.date))"
      >{{ slotProps.date.day }}</strong>
      <strong
        v-else-if="findProposedDate(slotProps.date)"
        :title="findProposedDateName(slotProps.date)"
        class="rounded-full h-24 w-24 flex items-center justify-center bg-yellow-300"
        @click="$swal.fire('Date under proposal with ' + findProposedDateName(slotProps.date))"
      >{{ slotProps.date.day }}</strong>
      <template v-else>
        {{ slotProps.date.day }}
      </template>
    </template>
  </calendar>
</template>

<script>
import { usePage } from '@inertiajs/inertia-vue3'
import { computed } from '@vue/runtime-core'
export default {
  props:['bookedDates','proposedDates'],
  setup(){

  },
  
  data(){
    return {
      date:null
    }
  },
  watch:{
    date(){
      this.emitDate();
    }
  },

  
methods:{
  emitDate()
  {
    this.$emit('input',this.date)
  },
  parsePrimeVueDate(date)
  {

    function zeroPad(data)
    {
      let padded = data;
      if(data < 10)
      {
        padded = "0" + data;
      }
      return String(padded);
    }
    const dateString = String(date.year) + '-' + zeroPad(date.month + 1) + '-' + zeroPad(date.day);
    const jsDate = this.$moment(dateString).format('YYYY-MM-DD');
    return jsDate;
  },
     findReservedDate(date)
            {
                const jsDate = this.parsePrimeVueDate(date);
                var booked = false;
                // console.log(this.bookedDates);
                this.bookedDates.forEach(bookedDate =>{
                    const parsedDate = this.$moment(bookedDate.event_time).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        booked = true;
                    }
                })
                return booked
            },
            findReservedDateName(date)
            {
              const jsDate = this.parsePrimeVueDate(date);
                var name = '';
                this.bookedDates.forEach(bookedDate =>{
                    const parsedDate = this.$moment(bookedDate.event_time).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        name = bookedDate.event_name;
                    }
                })
                return name
            },
            findProposedDate(date)
            {
                const jsDate = this.parsePrimeVueDate(date);
                var booked = false;
                this.proposedDates.forEach(proposedDate =>{
                    const parsedDate = this.$moment(proposedDate.date).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        booked = true;
                    }
                })
                return booked
            },
            findProposedDateName(date)
            {
              const jsDate = this.parsePrimeVueDate(date);
                var name = '';
                this.proposedDates.forEach(proposedDate =>{
                    const parsedDate = this.$moment(proposedDate.date).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        name = proposedDate.name;
                    }
                })
                return name
            },
            getDisabledDates()
            {
                let dateArray = [];
                // this.bookedDates.forEach(date=>{
                //   console.log(new Date(this.$moment(String(date.event_time))));
                //     dateArray.push(new Date(this.$moment(String(date.event_time))));
                // })
                return dateArray;
            },
}
}
</script>

<style>

</style>