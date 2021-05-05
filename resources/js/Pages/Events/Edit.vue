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
                <form :action="'f/events/' + form.even_key" method="PATCH" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4" @submit.prevent="updateEvent">
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
                                        <select v-model="form.event_type_id" class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="grid-state">
                                            <option v-for="eventType in eventTypes" :key="eventType.id" :value="eventType.id">{{eventType.name}}</option>
                                        </select>
                                    </div>
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
                                        <label for="secondDance">Second Dance</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="secondDance" placeholder="Second Dance" v-model="form.second_dance">
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
                                        <label for="secondDance">Bouquet Dance</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="bouquetDance" placeholder="Bouquet Stuff" v-model="form.bouquet_dance">
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
                                        <label for="zipCode">Zip Code</label>
                                    </p>
                                    <p>
                                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="zipCode" placeholder="70506" v-model="form.zip">
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
                                        <calendar v-on:date-select="setDate()" v-model="form.event_time" />
                                    </p>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Show Time
                                    </p>
                                    <p>
                                        <calendar v-on:date-select="setDate()" v-model="form.event_time" :showTime="true" :timeOnly="true" hourFormat="12" />
                                    </p>
                                </div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Quiet Time
                                    </p>
                                    <p>
                                        <calendar v-model="form.quiet_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                    </p>
                                </div>   
                                <div v-if="form.event_type_id === 1" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Ceremony Time
                                    </p>
                                    <p>
                                        <calendar v-model="form.ceremony_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                        On Site: <input type="checkbox" v-model="form.onsite"/>
                                    </p>
                                </div>                               
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        End Time
                                    </p>
                                    <p>
                                        <calendar v-model="form.end_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                    </p>
                                </div>   
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Production Load In Time
                                    </p>
                                    <p>
                                        <calendar v-model="form.production_loadin_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                    </p>
                                </div>  
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Rhythm Load In Time
                                    </p>
                                    <p>
                                        <calendar v-model="form.rhythm_loadin_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                    </p>
                                </div>                                                                 
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        Band Load In Time
                                    </p>
                                    <p>
                                        <calendar v-model="form.band_loadin_time" :step-minute="15" :showTime="true" :timeOnly="true" hourFormat="12" />
                                    </p>
                                </div>              
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
                                       Outside
                                    </p>
                                    <p>
                                        <input type="checkbox" v-model="form.outside"/>
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
                            Update Event
                        </button>
                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" v-on:click="showAlert">
                            Delete Event
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
    import VueTimepicker from 'vue3-timepicker'
    import 'vue3-timepicker/dist/VueTimepicker.css'
    import moment from 'moment';
    export default {
        props:['event','eventTypes','bands','states','errors'],
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker
        }, 
        data(){
            return{
                form:{
                    band_id:this.event.band_id,
                    event_name:this.event.event_name,
                    venue_name:this.event.venue_name,
                    first_dance:this.event.first_dance,
                    second_dance:this.event.second_dance,
                    money_dance:this.event.money_dance,
                    bouquet_dance:this.event.bouquet_dance,
                    address_street:this.event.address_street,
                    zip:this.event.zip,
                    city:this.event.city,
                    colorway_id:this.event.colorway_id,
                    ceremony_time:new Date(moment(this.event.ceremony_time)),
                    quiet_time:new Date(moment(this.event.quiet_time)),
                    onsite:this.event.onsite ? true : false,
                    notes:this.event.notes,
                    event_time:new Date(moment(this.event.event_time)),
                    end_time:new Date(moment(this.event.end_time)),
                    band_loadin_time:new Date(moment(this.event.band_loadin_time)),
                    rhythm_loadin_time:new Date(moment(this.event.rhythm_loadin_time)),
                    production_loadin_time:new Date(moment(this.event.production_loadin_time)),
                    pay:this.event.pay, 
                    depositReceived:this.event.depositReceived ? true : false,
                    event_key:this.event.event_key,
                    created_at:this.event.created_at,
                    updated_at:this.event.updated_at,
                    public:this.event.public ? true : false,
                    event_type_id:this.event.event_type_id,
                    lodging:this.event.lodging ? true : false,
                    state_id:this.event.state_id,
                    outside:this.event.outside ? true : false
                }
            }
        },
        created(){
            this.colorsForBand()
            console.log(this.event.ceremony_time);
        },
        methods:{
            updateEvent(){
                this.$inertia.patch('/events/'+ this.event.event_key,this.form)
                    .then(()=>{
                        // alert('created');
                    })
            },
            showAlert() {
                this.$swal.fire({
                    title: 'Are you sure you want to delete ' + this.form.event_name,
                    text: "You won't be able to revert this!",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if(result.value)
                    {
                        this.deleteEvent();
                    }
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
            deleteEvent(){
                this.$inertia.delete('/events/' + this.event.event_key,this.form)
                    .then(()=>{
                        // alert('created');
                    })
            }
        }
    }
</script>
