<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        <Link href="/events">
          Events
        </Link> :: {{ form.event_name }}
      </h2>
    </template>
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
          :action="'/events/' + form.even_key"
          method="PATCH"
          class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4"
          @submit.prevent="updateEvent"
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
                    id="grid-state"
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
                  <label for="name">Event Name</label>
                </p>
                <div class="mb-4">
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
                  <label for="eventType">Event Type</label>
                </p>
                <div>
                  <select
                    id="grid-state"
                    v-model="form.event_type_id"
                    class="block appearance-none w-full bg-grey-lighter border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded"
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
                      <option :value="1">
                        Provided by band
                      </option>
                      <option :value="0">
                        Provided by venue
                      </option>
                    </select>
                  </div>
                </div>
              </div>      
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
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
                  Contacts
                </p>
                <div>
                  <ul
                    v-for="contact in form.event_contacts"
                    :key="contact.id"
                    class="hover:bg-gray-100 cursor-pointer"
                    @click="form.event_contacts.forEach(tempContact=>tempContact.editing = false); if(!contact.editing){ contact.editing = true }"
                  >
                    <li v-if="!contact.editing">
                      Name: {{ contact.name }}
                    </li>
                    <li v-if="!contact.editing">
                      Phone: {{ contact.phonenumber }}
                    </li>
                    <li v-if="!contact.editing">
                      Email: {{ contact.email }}
                    </li>
                    <li v-if="contact.editing">
                      Name: <input
                        v-model="contact.name"
                        :class="inputClass"
                        required
                        type="text"
                      >
                    </li>
                    <li v-if="contact.editing">
                      Phone: <input
                        v-model="contact.phonenumber"
                        :class="inputClass"
                        type="tel"
                      >
                    </li>
                    <li v-if="contact.editing">
                      Email: <input
                        v-model="contact.email"
                        :class="inputClass"
                        type="email"
                      >
                    </li>
                    <li v-if="contact.editing">
                      <button-component
                        :type="'button'"
                        @click.stop="updateContact(contact)"
                      >
                        Save
                      </button-component>
                      <button-component
                        :type="'button'"
                        @click.stop="contact.editing = false"
                      >
                        Cancel
                      </button-component>
                      <button-component
                        :type="'button'"
                        @click.stop="removeContact(contact)"
                      >
                        Delete
                      </button-component>
                    </li>
                  </ul>
                  <ul v-if="showCreateNewContact">
                    <li>
                      Name: <input
                        v-model="newContact.name"
                        :class="inputClass"
                        required
                        type="text"
                      >
                    </li>
                    <li>
                      Phone: <input
                        v-model="newContact.phonenumber"
                        :class="inputClass"
                        type="tel"
                      >
                    </li>
                    <li>
                      Email: <input
                        v-model="newContact.email"
                        :class="inputClass"
                        type="email"
                      >
                    </li>
                  </ul>
                  <button-component
                    v-if="!showCreateNewContact"
                    :type="'button'"
                    @click="showCreateNewContact = true"
                  >
                    Create New
                  </button-component>
                  <button-component
                    v-if="showCreateNewContact"
                    :type="'button'"
                    @click="showCreateNewContact = false"
                  >
                    Cancel
                  </button-component>
                  <button-component
                    v-if="showCreateNewContact"
                    :type="'button'"
                    @click="saveContact"
                  >
                    Save
                  </button-component>
                </div>
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
                    id="grid-state"
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
                  Notes
                </p>
                <p>
                  <textarea
                    v-model="form.notes"
                    class="min-w-full"
                    placeholder=""
                  />
                </p>
              </div>                                                                                                                                                                                                                                                                                                                                    
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Pay $
                </p>
                <p>
                  <currency-input 
                    v-model.lazy="value"
                    v-model="form.pay"
                  />
                </p>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Color
                </p>
                <p>
                  <select
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
                  </select> 
                </p>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Event Date
                </p>
                <p>
                  <calendar
                    v-model="form.event_time"
                    @date-select="setDate()"
                  />
                </p>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Show Time
                </p>
                <p>
                  <calendar
                    v-model="form.event_time"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                    @date-select="setDate()"
                  />
                </p>
              </div>
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Quiet Time
                </p>
                <p>
                  <calendar
                    v-model="form.quiet_time"
                    :step-minute="15"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                  />
                </p>
              </div>   
              <div
                v-if="form.event_type_id === 1"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  Ceremony Time
                </p>
                <p>
                  <calendar
                    v-model="form.ceremony_time"
                    :step-minute="15"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                  />
                  On Site: <input
                    v-model="form.onsite"
                    type="checkbox"
                  >
                </p>
              </div>                               
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  End Time
                </p>
                <p>
                  <calendar
                    v-model="form.end_time"
                    :step-minute="15"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                  />
                </p>
              </div>   
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Production Load In Time
                </p>
                <p>
                  <calendar
                    v-model="form.production_loadin_time"
                    :step-minute="15"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                  />
                </p>
              </div>  
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Rhythm Load In Time
                </p>
                <p>
                  <calendar
                    v-model="form.rhythm_loadin_time"
                    :step-minute="15"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                  />
                </p>
              </div>                                                                 
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Band Load In Time
                </p>
                <p>
                  <calendar
                    v-model="form.band_loadin_time"
                    :step-minute="15"
                    :show-time="true"
                    :time-only="true"
                    hour-format="12"
                  />
                </p>
              </div>              
              <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                <p class="text-gray-600">
                  Deposit Received
                </p>
                <p>
                  <input
                    v-model="form.depositReceived"
                    type="checkbox"
                  >
                </p>
              </div>    
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
                  Outside
                </p>
                <p>
                  <input
                    v-model="form.outside"
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
              Update Event
            </button>
            <button
              id="deleteButton"
              class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
              type="button"
              @click="showAlert"
            >
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
    import ButtonComponent from '@/Components/Button'
    import CurrencyInput from '@/Components/CurrencyInput'
    import moment from 'moment';
    export default {
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker,ButtonComponent,CurrencyInput
        },
        props:['event','eventTypes','bands','states','errors'], 
        data(){
            return{
                sessionToken : Math.floor(Math.random() * 1000000000),
                searchResults:'',
                searchTimer: null,
                showCreateNewContact:false,
                newContact:{
                    name:'',
                    phonenumber:'',
                    email:''
                },
                inputClass:[
                    'shadow',
                    'appearance-none',
                    'border',
                    'rounded ',
                    'w-full ',
                    'py-2 ',
                    'px-3 ',
                    'text-gray-700 ',
                    'leading-tight ',
                    'focus:outline-none ',
                    'focus:shadow-outline'
                ],
                form:{
                    band_id:this.event.band_id,
                    event_name:this.event.event_name,
                    venue_name:this.event.venue_name,
                    event_contacts:this.event.event_contacts,
                    first_dance:this.event.first_dance,
                    father_daughter:this.event.father_daughter,
                    mother_groom:this.event.mother_groom,
                    money_dance:this.event.money_dance,
                    bouquet_garter:this.event.bouquet_garter,
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
                    production_needed:this.event.production_needed,
                    backline_provided:this.event.backline_provided,                   
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
            },
            saveContact(){
               this.$inertia.post('/events/createContact/' + this.event.event_key,this.newContact,{preserveScroll:true,onSuccess:(data)=>{
                //    console.log(data);
                   this.newContact.name = '';
                   this.newContact.phonenumber = '';
                   this.newContact.email = '';
                   this.showCreateNewContact = false;
                   this.form.event_contacts = data.props.event.event_contacts;
               }})
            },
            updateContact(contact){
                // console.log(contact);
                this.$inertia.post('/events/editContact/' + contact.id,contact,{preserveScroll:true}).then(()=>{
                    contact.editing = false;
                });
            },
            removeContact(contact){
                this.$inertia.delete('/events/deleteContact/' + contact.id,{preserveScroll:true}).then(data=>{
                    this.form.event_contacts = this.form.event_contacts.filter(propContact=>{
                        return propContact.id !== contact.id;
                    })
                });
            },
            hideContactEdits(){
                this.form.event_contacts.forEach(tempContact=>tempContact.editing = false)
            },            
        }
    }
</script>
