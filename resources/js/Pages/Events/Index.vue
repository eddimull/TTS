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
    import moment from 'moment';
    import EventList from './EventList.vue';
    export default {
        components: {
            EventList
        },
        layout: BreezeAuthenticatedLayout,
        props:['events','successMessage','includeAll'],
        methods:{
            formatDate(date){
                return moment(String(date)).format('MM/DD/YYYY')
            }
        }
    }
</script>
