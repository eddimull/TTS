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
    import { usePage } from '@inertiajs/vue3';
    import { useBandRealtime } from '@/composables/useBandRealtime';
    import EventList from './EventList.vue';
    export default {
        components: {
            EventList
        },
        layout: BreezeAuthenticatedLayout,
        setup() {
            useBandRealtime(usePage().props.auth?.user?.band_ids ?? [], {
                events: ['events'],
                event_member: ['events'],
                roster: ['events'],
            });
        },
        props:['events','successMessage','includeAll'],
        methods:{
            formatDate(date){
                return DateTime.fromISO(String(date)).toFormat('MM/dd/yyyy')
            }
        }
    }
</script>
