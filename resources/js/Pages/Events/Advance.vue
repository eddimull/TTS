<template>
    <breeze-unauthenticated-layout>
       
        <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8 background-white-400">
                <!-- {{ event }}  -->
            <div id="advance">
                <div class="text-center italicized mb-4">
                    
                    {{ event.band.name }} Advance
                </div>
                <table class="min-w-full bg-white m-5 rounded border border-2" >
                    <tr class="min-w-full text-center border border-2 px-4 py-2 text-black-600 font-large"><td class="border border-black border-2 px-4" colspan="3"><div class="text-center">{{ event.event_type_name.name }}</div></td></tr>
                    <tr class="min-w-full text-center border border-2 px-4 py-2 text-black-600 font-large"><td class="border border-black border-2 px-4" colspan="3"><div class="text-center">{{ event.event_name }}</div></td></tr>
                    <tr>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-green-100">Date: <br/>{{formatDate(event.event_time)}}</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Location:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.venue_name}}<br/>
                            {{event.address_street}}<br/>
                            {{event.city}}, {{event.state.state_name}}<br/>
                            {{event.zip}}<br/>
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Contact:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Production:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            Sieber Pro
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Load In:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            Prod: {{formatTime(event.production_loadin_time)}}<br/>
                            Drums: {{formatTime(event.rhythm_loadin_time)}}<br/>
                            Band: {{formatTime(event.band_loadin_time)}}<br/>
                        </td>                            
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Quiet Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{ formatTime(event.quiet_time) }}
                        </td>                            
                    </tr>       
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Show Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{formatTime(event.event_time)}}
                        </td>                            
                    </tr>       
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">End Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{formatTime(event.end_time)}}
                        </td>                            
                    </tr>                           
                    <tr v-if="(event.event_type.name == 'Wedding')">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Ceremony Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            
                            {{formatTime(event.ceremony_time)}} {{ event.onsite ? 'Onsite ceremony' : 'Ceremony Offsite'}}
                        </td>                                                    
                    </tr>             
                    <tr v-if="(event.event_type.name == 'Wedding')">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Ceremony Music:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            DJ
                        </td>                                                    
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Attire:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.colorway ? event.colorway.color_title : 'TBD'}}
                        </td>                                                    
                    </tr>                    
                    <tr v-if="(event.event_type.name == 'Wedding')">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">First Dances:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.first_dance}}
                        </td>                                                    
                    </tr>                      
                    <tr v-if="(event.event_type.name == 'Wedding')">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Second Dances:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.second_dance}}
                        </td>                                                    
                    </tr>                                             
                    <tr v-if="(event.event_type.name == 'Wedding')">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Bouquet/Money Dances:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.bouquet_dance}} / {{event.money_dance}}
                        </td>                                                    
                    </tr>    
                    <tr v-if="(event.event_type.name == 'Wedding')">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Second Line:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.second_line ? 'Yes':'No'}}
                        </td>                                                    
                    </tr>      
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Set Times:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            TBD
                        </td>                                                    
                    </tr>    
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Notes:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.notes}}
                        </td>                                                    
                    </tr>       
                    <tr v-if="(event.lodging)">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Lodging:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            There will be lodging.
                        </td>                                                    
                    </tr>    
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Outside:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{event.outside ? 'Gig is outside' : 'Gig is inside'}}
                        </td>                                                    
                    </tr>                                                                                
                </table>
            </div>
        </div>
    </breeze-unauthenticated-layout>
</template>

<script>
    import BreezeUnAuthenticatedLayout from '@/Layouts/Guest'
    import moment from 'moment';

    import html2pdf from 'html2pdf.js';

    export default {
        props:['event','eventTypes','bands','states','errors'],
        components: {
            BreezeUnAuthenticatedLayout,
        }, 
        data(){
            return{
                form:{
 
                }
            }
        },
        methods:{
            formatDate(date){
                return moment(String(date)).format('MM/DD/YYYY')
            },

            formatTime(date)
            {
                return moment(String(date)).format("h:mm A")
            }

        }
    }
</script>
