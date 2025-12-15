<template>
  <breeze-authenticated-layout>       
    <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="mb-4">
        {{ errors.name }}
        <div
          v-if="errors.name"
          class="alert alert-danger mt-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-100 px-4 py-3 rounded"
        >
          Errors:
          <ul> 
            <li>{{ errors.name }}</li>
          </ul>
        </div>
        <form
          action="/events"
          method="POST"
          class="bg-white dark:bg-gray-800 shadow-md rounded px-8 pt-6 pb-8 mb-4"
          @submit.prevent="createEvent"
        >
          <div class="flex flex-col gap-8">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
              <h2 class="text-2xl text-gray-900 dark:text-gray-100">
                Event Information
              </h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Event name/type/load in etc. 
              </p>
            </div>

            <div class="section">
              <SectionTitle
                :number="1"
                :title="'Initial Information'"
              />
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="name">Band</label>
                </p>
                <div>
                  <select
                    id="bandDropdown"
                    v-model="form.band_id"
                    class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 py-3 px-4 pr-8 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="name">Name</label>
                </p>
                <div class="mb-4">
                  <Input
                    id="name"
                    v-model="form.event_name"
                    type="text"
                    placeholder="Event Name"
                  />
                </div>
              </div>
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
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
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="eventType">Event Type</label>
                </p>
                <div>
                  <select
                    id="productionDropdown"
                    v-model="form.event_type_id"
                    class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 py-3 px-4 pr-8 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  Public
                </p>
                <p>
                  <Checkbox v-model="form.public" />
                </p>
              </div> 
            </div>

            <div
              v-if="form.event_type_id === 1"
              class="section"
            >
              <SectionTitle
                :number="'1a'"
                :title="'Wedding Information'"
              />
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="firstDance">First Dance</label>
                </p>
                <p>
                  <Input
                    id="firstDance"
                    v-model="form.first_dance"
                    type="text"
                    placeholder="First Dance"
                  />
                </p>
              </div>
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="father_daughter">Father / Daughter Dance:</label>
                </p>
                <p>
                  <Input
                    id="secondDance"
                    v-model="form.father_daughter"
                    type="text"
                    placeholder="Second Dance"
                  />
                </p>
              </div>  
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="mother_groom">Mother / Groom Dance:</label>
                </p>
                <p>
                  <Input
                    id="motherGroomDance"
                    v-model="form.mother_groom"
                    type="text"
                    placeholder="Mother Groom Dance"
                  />
                </p>
              </div>                                 
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="moneyDance">Money Dance</label>
                </p>
                <p>
                  <Input
                    id="moneyDance"
                    v-model="form.money_dance"
                    type="text"
                    placeholder="Money Dance"
                  />
                </p>
              </div>      
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  <label for="secondDance">Bouquet / Garter</label>
                </p>
                <p>
                  <Input
                    id="bouquetDance"
                    v-model="form.bouquet_garter"
                    type="text"
                    placeholder="Bouquet Stuff"
                  />
                </p>
              </div> 
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  Second Line
                </p>
                <p>
                  <Checkbox
                    v-model="form.second_line"
                    type="checkbox"
                  />
                </p>
              </div>   
            </div>

            <div class="section">
              <SectionTitle
                :number="2"
                :title="'Venue Information'"
              />
              <div>
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    <label for="eventType">Production</label>
                  </p>
                  <div>
                    <div class="mb-4">
                      <select
                        v-model="form.production_needed"
                        class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 py-3 px-4 pr-8 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                  class="createEventInput"
                >
                  <p class="text-gray-600 dark:text-gray-300">
                    Backline Provided
                  </p>
                  <p>
                    <Checkbox
                      v-model="form.backline_provided"
                      type="checkbox"
                    />
                  </p>
                </div>                          
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    Venue Name
                  </p>
                  <p>
                    <Input
                      v-model="form.venue_name"
                      type="text"
                      placeholder="Venue Name"
                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                      @input="unsavedChanges=true"
                      @keyup="autoComplete()"
                    />
                                    
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
 
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    <label for="streetAddress">Street Address</label>
                  </p>
                  <p>
                    <Input
                      id="streetAddress"
                      v-model="form.address_street"
                      type="text"
                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                      placeholder="P. Sherman, 42"
                    />
                  </p>
                </div>          
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    <label for="zipCode">City</label>
                  </p>
                  <p>
                    <Input
                      id="city"
                      v-model="form.city"
                      type="text"
                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                      placeholder="Townsville"
                    />
                  </p>
                </div>                                 
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    <label for="state">State</label>
                  </p>
                  <p>
                    <select
                      id="stateDropdown"
                      v-model="form.state_id"
                      class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 py-3 px-4 pr-8 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    <label for="zipCode">Zip Code</label>
                  </p>
                  <p>
                    <Input
                      id="zipCode"
                      v-model="form.zip"
                      type="text"
                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                      placeholder="70506"
                    />
                  </p>
                </div> 
                
                <div class="createEventInput">
                  <p class="text-gray-600 dark:text-gray-300">
                    Outdoors
                  </p>
                  <p>
                    <Checkbox
                      v-model="form.outdoors"
                      type="checkbox"
                    />
                  </p>
                </div>
              </div>     
            </div>



            <div class="section">  
              <SectionTitle
                :number="3"
                :title="'Band Notes'"
              />                          
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  Notes
                </p>
                <p>
                  <Editor
                    v-model="form.notes"
                    editor-style="height: 320px"
                  />
                </p>
              </div>                                                                                                                                                                                                                                                                                                                                    
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  Color
                </p>
                <p>
                  <Editor
                    v-model="form.colorway_text"
                    editor-style="height: 150px"
                  />    
                </p>
              </div>
              <div class="createEventInput">
                <p class="text-gray-600 dark:text-gray-300">
                  Lodging Provided
                </p>
                <p>
                  <Checkbox
                    v-model="form.lodging"
                    type="checkbox"
                  />
                </p>
              </div>  
            </div>

            
            <div
              v-if="form.event_time !== ''"
              class="section"
            >
              <SectionTitle
                :number="4"
                :title="'Load in times'"
              />
              <transition
                name="slide-down"
                appear
              >
                <div>
                  <div
                    v-if="form.event_time !== ''"
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
                      On Site: <Checkbox
                        v-model="form.onsite"
                        type="checkbox"
                      />
                    </p>
                  </div>                                               
                  <div
                    v-if="form.event_time !== ''"
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
                    class="createEventInput"
                  >
                    <p class="text-gray-600 dark:text-gray-300">
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
            </div>
          </div>
          <div class="flex items-center justify-center mt-8 mb-4">
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
    import { DateTime } from 'luxon'
    import SectionTitle from './CreateSectionTitle.vue';
    import Input from '@/Components/Input.vue';
    import Checkbox from '@/Components/Checkbox.vue';

    export default {
        components: {
            BreezeAuthenticatedLayout,SectionTitle, Input, Checkbox
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
                this.form.event_time = DateTime.fromJSDate(this.form.event_time).plus({ hours: 19 }).toJSDate();
                this.form.end_time = DateTime.fromJSDate(this.form.event_time).plus({ hours: 4 }).toJSDate();
                this.form.quiet_time = DateTime.fromJSDate(this.form.event_time).minus({ hours: 1 }).toJSDate();
                this.form.ceremony_time = DateTime.fromJSDate(this.form.event_time).minus({ hours: 1 }).toJSDate();
                
            },
            setDate()
            {
                const amountBefore = this.form.onsite ? 1 : 0;
                if(this.form.event_type_id == 1)
                {
                    this.form.quiet_time = DateTime.fromJSDate(this.form.ceremony_time).minus({ hours: amountBefore }).toJSDate();
                    this.form.band_loadin_time = DateTime.fromJSDate(this.form.quiet_time).minus({ hours: 2 }).toJSDate();
                    this.form.rhythm_loadin_time = DateTime.fromJSDate(this.form.quiet_time).minus({ hours: 3 }).toJSDate();
                    this.form.production_loadin_time = DateTime.fromJSDate(this.form.quiet_time).minus({ hours: 4 }).toJSDate();
                }
                else
                {
                    this.form.band_loadin_time = DateTime.fromJSDate(this.form.quiet_time).minus({ hours: 2 }).toJSDate();
                    this.form.rhythm_loadin_time = DateTime.fromJSDate(this.form.quiet_time).minus({ hours: 3 }).toJSDate();
                    this.form.production_loadin_time = DateTime.fromJSDate(this.form.quiet_time).minus({ hours: 4 }).toJSDate();
                }
            },

            autoComplete(){
                if(this.searchTimer){
                    clearTimeout(this.searchTimer);
                    this.searchTimer = null;
                }
                try{

                  this.searchTimer = setTimeout(()=>{
                    axios.post('/autocompleteLocation',{
                      sessionToken:this.sessionToken,
                        searchParams:this.form.venue_name,
                    }).then((response)=>{
                      this.searchResults = response.data.predictions
                    })
                  },800)
                } 
                catch (e)
                {
                  this.$page.props.errors = ['Error in autocomplete' + e]
                }
            },

            getLocationDetails(place_id)
            {
              try{

              
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
              catch (e)
              {
                this.$page.props.errors = ['Error in getLocationDetails' + e]
              }
            }
        }
    }
</script>
<style lang="postcss" scoped>
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

.section{
  @apply bg-gray-100 dark:bg-gray-900 shadow-lg rounded-lg p-4;
}
.createEventInput:last-child{
  @apply border-none
}
.createEventInput{
  @apply md:grid md:grid-cols-2 hover:bg-gray-50 hover:dark:bg-gray-800 md:space-y-0 space-y-1 p-4 border-b
}
</style>