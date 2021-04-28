<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h4>Upcoming Events</h4>
                        <ul>
                            <li v-for="event in events" :key="event.id">{{event.event_name}} - {{event.venue_name}} - <strong>{{  formatDate(event.event_time)  }}</strong></li>
                        </ul>
                    </div>
                    <div class="p-6 bg-gray overflow-hidden shadow-sm sm:rounded-lg">
                        <h4>Stats</h4>
                        <p>Mileage for the year: {{stats.miles}}</p>
                    </div>  
                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import moment from 'moment';
    export default {
        props:['events','stats'],
        components: {
            BreezeAuthenticatedLayout,
        },
        methods:{
            formatDate:(date)=>{

                return moment(String(date)).format('MM/DD/YYYY')
            }
        }
    }
</script>
