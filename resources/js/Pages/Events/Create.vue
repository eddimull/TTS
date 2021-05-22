<template>
    <breeze-authenticated-layout>       
        <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
             <div class="mb-4">
                {{ errors.name }}
                <div v-if="errors.name" class="alert alert-danger mt-4">
                    Errors:
                    <ul>
                        <li>{{ errors.name }}</li>
                    </ul>
                </div>
                <form action="/events" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4" @submit.prevent="createEvent">
                    <div class="bg-white w-full rounded-lg shadow-xl">
                            <div class="p-4 border-b">
                                <h2 class="text-2xl ">
                                    Event Information
                                </h2>
                                <p class="text-sm text-gray-500">
                                    Event name/type/load in etc. 
                                </p>
                            </div>
                            <div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="name">Band</label>
                                    </p>
                                    <div>
                                        <select v-on:change="colorsForBand()" v-model="form.band_id" class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="grid-state">
                                            <option v-for="band in bands" :key="band.id" :value="band.id">{{band.name}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="name">Name</label>
                                    </p>
                                    <div class="mb-4">
                                        <p-inputtext v-model="form.event_name"></p-inputtext>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Event Name" v-model="form.event_name">
                                    </div>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="eventType">Event Type</label>
                                    </p>
                                    <div>
                                        <select v-on:change="alterProductionNeeded()" v-model="form.event_type_id" class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="grid-state">
                                            <option v-for="eventType in eventTypes" :key="eventType.id" :value="eventType.id">{{eventType.name}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="eventType">Production</label>
                                    </p>
                                    <div>
                                        <div class="mb-4">
                                            <select v-model="form.production_needed" class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded">
                                                <option :value="true">Provided by band</option>
                                                <option :value="false">Provided by venue</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>      
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Backline Provided
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.backline_provided" />
                                    </p>
                                </div>                          
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Venue Name
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="venueName" placeholder="Venue Name" v-model="form.venue_name">
                                    </p>
                                </div>
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="firstDance">First Dance</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="firstDance" placeholder="First Dance" v-model="form.first_dance">
                                    </p>
                                </div>
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="father_daughter">Father / Daughter Dance:</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="secondDance" placeholder="Second Dance" v-model="form.father_daughter">
                                    </p>
                                </div>  
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="father_daughter">Mother / Groom Dance:</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="secondDance" placeholder="Second Dance" v-model="form.mother_groom">
                                    </p>
                                </div>                                 
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="moneyDance">Money Dance</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="moneyDance" placeholder="Money Dance" v-model="form.money_dance">
                                    </p>
                                </div>      
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">

                                    <p class="text-gray-600">
                                        <label for="secondDance">Bouquet / Garter</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="bouquetDance" placeholder="Bouquet Stuff" v-model="form.bouquet_garter">
                                    </p>
                                </div>    
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">

                                    <p class="text-gray-600">
                                        <label for="streetAddress">Street Address</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="streetAddress" placeholder="P. Sherman, 42" v-model="form.address_street">
                                    </p>
                                </div>          
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">

                                    <p class="text-gray-600">
                                        <label for="zipCode">City</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="city" placeholder="Townsville" v-model="form.city">
                                    </p>
                                </div>                                 
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">

                                    <p class="text-gray-600">
                                        <label for="state">State</label>
                                    </p>
                                    <p>
                                        <select v-model="form.state_id" class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="grid-state">
                                            <option v-for="state in states" :key="state.state_id" :value="state.state_id">{{state.state_name}}</option>
                                        </select>                                        
                                    </p>
                                </div>   
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">

                                    <p class="text-gray-600">
                                        <label for="zipCode">Zip Code</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="zipCode" placeholder="70506" v-model="form.zip">
                                    </p>
                                </div>                                  
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Notes
                                    </p>
                                    <p>
                                        <textarea class="min-w-full" v-model="form.notes" placeholder=""></textarea>
                                    </p>
                                </div>                                                                                                                                                                                                                                                                                                                                    
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Pay $
                                    </p>
                                    <p>
                                        <input type="number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="pay" placeholder="0" v-model="form.pay">
                                    </p>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Color
                                    </p>
                                    <p>
                                        <select v-model="form.colorway_id" class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="colorway">
                                            <option v-for="color in colors" :key="color.id" :value="color.id">{{color.color_title}}</option>
                                        </select> 
                                    </p>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Event Date
                                    </p>
                                    <p>
                                        <calendar v-on:date-select="assumeSeven()" v-model="form.event_time" />
                                    </p>
                                </div>
                                <transition name="slide-down" appear>
                                    <div v-if="form.event_time !== ''">
                                        <div v-if="form.event_time !== ''" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                Show Time
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.event_time" :showTime="true" :timeOnly="true" hourFormat="12" />
                                            </p>
                                        </div>
                                        <div v-if="form.event_time !== ''" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                End Time
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.end_time" :showTime="true" :step-minute="15" :timeOnly="true" hourFormat="12" />
                                            </p>
                                        </div>   
                                        <div v-if="form.event_type_id === 1 && form.event_time !== ''" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                Ceremony Time
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.ceremony_time" :showTime="true" :step-minute="15" :timeOnly="true" hourFormat="12" />
                                                On Site: <input type="checkbox" v-model="form.onsite"/>
                                            </p>
                                        </div>                                               
                                        <div v-if="form.event_time !== ''" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                Quiet Time                                        
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.quiet_time" :showTime="true" :step-minute="15" :timeOnly="true" hourFormat="12" />
                                                <button v-if="form.event_time !== ''" class="bg-transparent hover:bg-blue-500 text-blue-700 font-semibold hover:text-white py-2 px-4 mx-3 border border-blue-500 hover:border-transparent rounded" @click="setDate()" type="button">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Auto Fill Times
                                                </button>
                                            </p>
                                        </div>                           
                                        <div v-if="form.event_time !== '' && form.production_needed" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                Production Load In Time
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.production_loadin_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                            </p>
                                        </div>  
                                        <div v-if="form.event_time !== ''" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                Rhythm Load In Time
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.rhythm_loadin_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                            </p>
                                        </div>                                                                 
                                        <div v-if="form.event_time !== ''" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                            <p class="text-gray-600">
                                                Band Load In Time
                                            </p>
                                            <p>
                                                <calendar :disabled="form.event_time === ''" v-model="form.band_loadin_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                            </p>
                                        </div>
                                    </div>   
                                </transition>          
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Deposit Received
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.depositReceived"/>
                                    </p>
                                </div>    
                               <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                       Public
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.public"/>
                                    </p>
                                </div>       
                               <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                       Lodging Required
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.lodging"/>
                                    </p>
                                </div>  
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                       Outdoors
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.outdoors"/>
                                    </p>
                                </div>     
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                       Second Line
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.second_line"/>
                                    </p>
                                </div>                                                                                                                                            
                            </div>
                        </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                            Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import Datepicker from 'vue3-datepicker'
    import moment from 'moment'
    import VueTimepicker from 'vue3-timepicker'
    import 'vue3-timepicker/dist/VueTimepicker.css'
    

    export default {
        props:['eventTypes','bands','states','errors'],
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker
        },
        data(){
            return{
                colors:[],
                form:{
                    band_id:'',
                    event_name:'',
                    venue_name:'',
                    first_dance:'',
                    father_daughter:'',
                    mother_groom:'',
                    money_dance:'',
                    bouquet_garter:'',
                    address_street:'',
                    zip:'',
                    city:'',
                    colorway_id:'',
                    ceremony_time:'',
                    production_needed:true,
                    backline_provided:false,
                    quiet_time:'',
                    onsite:'',
                    notes:'',
                    event_time:'',
                    band_loadin_time:'',
                    end_time:'',
                    rhythm_loadin_time:'',
                    production_loadin_time:'',
                    pay:'',
                    depositReceived:'',
                    event_key:'',
                    created_at:'',
                    updated_at:'',
                    public:'',
                    event_type_id:'',
                    lodging:'',
                    state_id:'',
                    outdoors:'',
                }
            }
        },
        methods:{
            createEvent(){
                this.$inertia.post('/events',this.form)
                    .then(()=>{
                        // alert('created');
                    })
            },
            colorsForBand(){
                // const band = this.bands.filter(band=>band.id == this.form.band_id);
                for(const i in this.bands)
                {
                    console.log(this.form.band_id,this.bands[i].id )
                    if(this.bands[i].id === this.form.band_id)
                    {
                        this.colors = this.bands[i].colors;
                    }
                }
            },
            alterProductionNeeded()
            {
                this.form.production_needed = [3,6].indexOf(this.form.event_type_id) == -1;
            },
            assumeSeven()
            {
                this.form.event_time = new Date(moment(String(this.form.event_time)).add('hour',19));
                this.form.end_time = new Date(moment(String(this.form.event_time)).add('hour',4));
                this.form.quiet_time = new Date(moment(String(this.form.event_time)).subtract('hour',1));
                this.form.ceremony_time = new Date(moment(String(this.form.event_time)).subtract('hour',1));
                
            },
            setDate()
            {
                const amountBefore = this.form.onsite ? 1 : 0;
                if(this.form.event_type_id == 1)
                {
                    this.form.quiet_time = new Date(moment(String(this.form.ceremony_time)).subtract('hour',amountBefore));
                    this.form.band_loadin_time = new Date(moment(String(this.form.quiet_time)).subtract('hour',2));
                    this.form.rhythm_loadin_time = new Date(moment(String(this.form.quiet_time)).subtract('hour',3));
                    this.form.production_loadin_time = new Date(moment(String(this.form.quiet_time)).subtract('hour',4));
                }
                else
                {
                    this.form.band_loadin_time = new Date(moment(String(this.form.quiet_time)).subtract('hour',2));
                    this.form.rhythm_loadin_time = new Date(moment(String(this.form.quiet_time)).subtract('hour',3));
                    this.form.production_loadin_time = new Date(moment(String(this.form.quiet_time)).subtract('hour',4));
                }
            }
        }
    }
</script>
<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity .5s;
}
.fade-enter-from, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  opacity: 0;
}

.slide-down-enter-active{
  transition: all .3s ease;
  transition-delay: .1s;
}
.slide-down-leave-active {
  transition: all .5s cubic-bezier(1.0, 0.5, 0.8, 1.0);
}
.slide-down-enter-from, .slide-down-leave-to
/* .slide-fade-leave-active below version 2.1.8 */ {
  transform: translateY(-50px);
}
</style>