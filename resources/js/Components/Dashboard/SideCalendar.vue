<template>
  <calendar
    v-model="date"
    :show-time="false"
    :step-minute="15"
    hour-format="12"
    :inline="true"
  >
    <template #date="slotProps">
      <strong
        v-if="findReservedDate(slotProps.date)"
        :title="findReservedDate(slotProps.date,'name')"
        class="rounded-full h-24 w-24 flex items-center justify-center bg-blue-300"
        @click="setEventId(slotProps.date)"
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

  setup(){
    if(!usePage().props.value?.events)
    {
      return {events:[]}
    }
    const events = computed(()=>usePage().props.value.events);

    return {events}
  },
  data(){
    return {
      date:null,
      event_id:null
    }
  },
  watch:{
    event_id(){
      this.emit_id();
    }
  },

  
methods:{
  emit_id()
  {
    this.$emit('date',this.event_id)
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
  setEventId(date)
  {
    this.event_id = this.findReservedDate(date,'id');
  },
     findReservedDate(date,sendBack)
            {
                const jsDate = this.parsePrimeVueDate(date);
                var data = false;
                
                for(const i in this.events){
                  const bookedDate = this.events[i];
                  const parsedDate = this.$moment(bookedDate.event_time).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        data = true;
                        if(sendBack === 'name')
                        {
                          data = bookedDate.event_name;
                        }

                        if(sendBack === 'id')
                        {
                          data = bookedDate.id;
                        }
                    }
                }

                return data
            },
      
}
}
</script>

<style>

</style>