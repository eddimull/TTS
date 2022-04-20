<template>
  <breeze-authenticated-layout>       
    <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="mb-4">
        {{ errors.name }}
        <div
          v-if="errors.name"
          class="alert alert-danger mt-4"
        >
          Errors:
          <ul> 
            <li>{{ errors.name }}</li>
          </ul>
        </div>
        <form
          action="/events"
          method="POST"
          class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4"
          @submit.prevent="createEvent"
        >
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
                  <select
                    id="bandDropdown"
                    v-model="form.band_id"
                    class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded"
                    @change="colorsForBand()"
                  >
                    <option
                      v-for="band in bands"
                      :key="band.id"
                      :value="band.id"
                    >
                      {{ band.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="name">Name</label>
                </p>
                <div class="mb-4">
                  <p-inputtext v-model="form.event_name" />
                  <input
                    id="name"
                    v-model="form.event_name"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Event Name"
                  >
                </div>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Event Date
                </p>
                <p>
                  <calendar
                    id="eventDate"
                    v-model="form.event_time"
                    @date-select="assumeSeven()"
                  />
                </p>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="eventType">Event Type</label>
                </p>
                <div>
                  <select
                    id="productionDropdown"
                    v-model="form.event_type_id"
                    class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded"
                    @change="alterProductionNeeded()"
                  >
                    <option
                      v-for="eventType in eventTypes"
                      :key="eventType.id"
                      :value="eventType.id"
                    >
                      {{ eventType.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="eventType">Production</label>
                </p>
                <div>
                  <div class="mb-4">
                    <select
                      v-model="form.production_needed"
                      class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded"
                    >
                      <option :value="true">
                        Provided by band
                      </option>
                      <option :value="false">
                        Provided by venue
                      </option>
                    </select>
                  </div>
                </div>
              </div>      
              <div
                v-if="form.event_type_id == 3 || form.event_type_id === 6"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  Backline Provided
                </p>
                <p>
                  <input
                    v-model="form.backline_provided"
                    type="checkbox"
                  >
                </p>
              </div>                          
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Venue Name
                </p>
                <p>
                  <input
                    v-model="form.venue_name"
                    type="text"
                    placeholder="Venue Name"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    @input="unsavedChanges=true"
                    @keyup="autoComplete()"
                  >
                                    
                  <ul class="">
                    <li
                      v-for="(result,index) in searchResults"
                      :key="index"
                      class="border-black my-4 p-4 bg-gray-200 hover:bg-gray-300 cursor-pointer"
                      @click="getLocationDetails(result.place_id); form.venue_name = result.structured_formatting.main_text; searchResults = null"
                    >
                      {{ result.description }}
                    </li>
                  </ul>
                </p>
              </div>
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  <label for="firstDance">First Dance</label>
                </p>
                <p>
                  <input
                    id="firstDance"
                    v-model="form.first_dance"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="First Dance"
                  >
                </p>
              </div>
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  <label for="father_daughter">Father / Daughter Dance:</label>
                </p>
                <p>
                  <input
                    id="secondDance"
                    v-model="form.father_daughter"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Second Dance"
                  >
                </p>
              </div>  
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  <label for="father_daughter">Mother / Groom Dance:</label>
                </p>
                <p>
                  <input
                    id="secondDance"
                    v-model="form.mother_groom"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Second Dance"
                  >
                </p>
              </div>                                 
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  <label for="moneyDance">Money Dance</label>
                </p>
                <p>
                  <input
                    id="moneyDance"
                    v-model="form.money_dance"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Money Dance"
                  >
                </p>
              </div>      
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  <label for="secondDance">Bouquet / Garter</label>
                </p>
                <p>
                  <input
                    id="bouquetDance"
                    v-model="form.bouquet_garter"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Bouquet Stuff"
                  >
                </p>
              </div>    
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="streetAddress">Street Address</label>
                </p>
                <p>
                  <input
                    id="streetAddress"
                    v-model="form.address_street"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="P. Sherman, 42"
                  >
                </p>
              </div>          
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="zipCode">City</label>
                </p>
                <p>
                  <input
                    id="city"
                    v-model="form.city"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Townsville"
                  >
                </p>
              </div>                                 
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="state">State</label>
                </p>
                <p>
                  <select
                    id="stateDropdown"
                    v-model="form.state_id"
                    class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded"
                  >
                    <option
                      v-for="state in states"
                      :key="state.state_id"
                      :value="state.state_id"
                    >
                      {{ state.state_name }}
                    </option>
                  </select>                                        
                </p>
              </div>   
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  <label for="zipCode">Zip Code</label>
                </p>
                <p>
                  <input
                    id="zipCode"
                    v-model="form.zip"
                    type="text"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="70506"
                  >
                </p>
              </div>                                  
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Notes
                </p>
                <p>
                  <Editor
                    v-model="form.notes"
                    editor-style="height: 320px"
                  />
                </p>
              </div>                                                                                                                                                                                                                                                                                                                                    
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Color
                </p>
                <p>
                  <Editor
                    v-model="form.colorway_text"
                    editor-style="height: 150px"
                  />    
                  <!-- <select
                    id="colorway"
                    v-model="form.colorway_id"
                    class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded"
                  >
                    <option
                      v-for="color in colors"
                      :key="color.id"
                      :value="color.id"
                    >
                      {{ color.color_title }}
                    </option>
                  </select>  -->
                </p>
              </div>

              <transition
                name="slide-down"
                appear
              >
                <div v-if="form.event_time !== ''">
                  <div
                    v-if="form.event_time !== ''"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      Show Time
                    </p>
                    <p>
                      <calendar
                        v-model="form.event_time"
                        :disabled="form.event_time === ''"
                        :show-time="true"
                        :time-only="true"
                        hour-format="12"
                      />
                    </p>
                  </div>
                  <div
                    v-if="form.event_time !== ''"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      End Time
                    </p>
                    <p>
                      <calendar
                        v-model="form.end_time"
                        :disabled="form.event_time === ''"
                        :show-time="true"
                        :step-minute="15"
                        :time-only="true"
                        hour-format="12"
                      />
                    </p>
                  </div>   
                  <div
                    v-if="form.event_type_id === 1 && form.event_time !== ''"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      Ceremony Time
                    </p>
                    <p>
                      <calendar
                        v-model="form.ceremony_time"
                        :disabled="form.event_time === ''"
                        :show-time="true"
                        :step-minute="15"
                        :time-only="true"
                        hour-format="12"
                      />
                      On Site: <input
                        v-model="form.onsite"
                        type="checkbox"
                      >
                    </p>
                  </div>                                               
                  <div
                    v-if="form.event_time !== ''"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      Quiet Time                                        
                    </p>
                    <p>
                      <calendar
                        v-model="form.quiet_time"
                        :disabled="form.event_time === ''"
                        :show-time="true"
                        :step-minute="15"
                        :time-only="true"
                        hour-format="12"
                      />
                      <button
                        v-if="form.event_time !== ''"
                        id="autoFillButton"
                        class="bg-transparent hover:bg-blue-500 text-blue-700 font-semibold hover:text-white py-2 px-4 mx-3 border border-blue-500 hover:border-transparent rounded"
                        type="button"
                        @click="setDate()"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          class="h-5 w-5 inline"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                          />
                        </svg>
                        Auto Fill Times
                      </button>
                    </p>
                  </div>                           
                  <div
                    v-if="form.event_time !== '' && form.production_needed"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      Production Load In Time
                    </p>
                    <p>
                      <calendar
                        v-model="form.production_loadin_time"
                        :disabled="form.event_time === ''"
                        :step-minute="15"
                        :show-time="true"
                        :time-only="true"
                        hour-format="12"
                      />
                    </p>
                  </div>  
                  <div
                    v-if="form.event_time !== ''"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      Rhythm Load In Time
                    </p>
                    <p>
                      <calendar
                        v-model="form.rhythm_loadin_time"
                        :disabled="form.event_time === ''"
                        :step-minute="15"
                        :show-time="true"
                        :time-only="true"
                        hour-format="12"
                      />
                    </p>
                  </div>                                                                 
                  <div
                    v-if="form.event_time !== ''"
                    class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
                  >
                    <p class="text-gray-600">
                      Band Load In Time
                    </p>
                    <p>
                      <calendar
                        v-model="form.band_loadin_time"
                        :disabled="form.event_time === ''"
                        :step-minute="15"
                        :show-time="true"
                        :time-only="true"
                        hour-format="12"
                      />
                    </p>
                  </div>
                </div>   
              </transition>           
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Public
                </p>
                <p>
                  <input
                    v-model="form.public"
                    type="checkbox"
                  >
                </p>
              </div>       
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Lodging Required
                </p>
                <p>
                  <input
                    v-model="form.lodging"
                    type="checkbox"
                  >
                </p>
              </div>  
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Outdoors
                </p>
                <p>
                  <input
                    v-model="form.outdoors"
                    type="checkbox"
                  >
                </p>
              </div>     
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  Second Line
                </p>
                <p>
                  <input
                    v-model="form.second_line"
                    type="checkbox"
                  >
                </p>
              </div>                                                                                                                                            
            </div>
          </div>
          <div class="flex items-center justify-between">
            <button
              class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
              type="submit"
            >
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
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker
        },
        props:['eventTypes','bands','states','errors'],
        data(){
            return{
                sessionToken : Math.floor(Math.random() * 1000000000),
                searchResults:'',
                searchTimer: null,
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
                    pay:'0',
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
            },

            autoComplete(){
                if(this.searchTimer){
                    clearTimeout(this.searchTimer);
                    this.searchTimer = null;
                }
                this.searchTimer = setTimeout(()=>{
                    axios.post('/autocompleteLocation',{
                        sessionToken:this.sessionToken,
                        searchParams:this.form.venue_name,
                    }).then((response)=>{
                        this.searchResults = response.data.predictions
                    })
                },800)
            },

            getLocationDetails(place_id)
            {
                axios.get('/getLocation',{
                    params:{
                        place_id:place_id,
                        sessionToken:this.sessionToken
                    }
                }).then((response)=>{
                    const address_components = response.data.result.address_components;

                    if(address_components.find(element => element.types.indexOf('street_number') !== -1))
                    {
                        this.form.address_street = address_components.find(element => element.types.indexOf('street_number') !== -1).long_name + ' ' + address_components.find(element => element.types.indexOf('route') !== -1).long_name;
                    }
                    else

                    {
                        this.form.address_street = address_components[0].long_name;
                    }
                    
                    const stateName = address_components.find(element => element.types.indexOf('administrative_area_level_1') !== -1).long_name
                    
                    this.form.state_id = this.states.find(element => element.state_name === stateName).state_id;

                    this.form.city = address_components.find(element => element.types.indexOf('locality') !== -1).long_name
                    
                    if(address_components.find(element => element.types.indexOf('postal_code') !== -1))
                    {
                        this.form.zip = address_components.find(element => element.types.indexOf('postal_code') !== -1).long_name
                    }

                })
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