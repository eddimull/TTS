<template>
  <div>
    <event-list
      :events="events"
      :include-all="includeAll"
    />
  </div>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import { DateTime } from 'luxon';
    import EventList from './EventList.vue';
    export default {
        components: {
            EventList
        },
        layout: BreezeAuthenticatedLayout,
        props:['events','successMessage','includeAll'],
        methods:{
            formatDate(date){
                return DateTime.fromISO(String(date)).toFormat('MM/dd/yyyy')
            }
        }
    }
</script>
