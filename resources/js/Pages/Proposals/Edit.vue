<template>
    <div>
        <breeze-authenticated-layout>
        <template #header>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Edit Proposal
                </h2>
            </template>
            <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-4">
                    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <div class="bg-white w-full rounded-lg shadow-xl">
                            <div v-for="input in validInputs" :key="input" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                <p class="text-gray-600">
                                    <label :for="input.name">{{input.name}}</label>
                                </p>
                                <div v-if="['text','number'].indexOf(input.type) !== -1" class="mb-4">
                                    <input @input="unsavedChanges=true" :type="input.type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" :id="input.name" :placeholder="input.name" v-model="proposalData[input.field]">
                                </div>
                                <div v-if="input.type == 'location'" class="mb-4">
                                    <input @input="unsavedChanges=true" type="text" @keyup="autoComplete()" v-model="proposalData.location" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" />
                                    
                                    <ul class="">
                                        <li class="border-black my-4 p-4 bg-gray-200 hover:bg-gray-300 cursor-pointer" v-for="(result,index) in searchResults" :key="index" @click="proposalData.location = result.description; searchResults = null">{{result.description}}</li>
                                    </ul>
                                </div>
                                <div v-if="input.type == 'contacts'">
                                    <ul class="hover:bg-gray-100 cursor-pointer" @click="proposalData.proposal_contacts.forEach(tempContact=>tempContact.editing = false); if(!contact.editing){ contact.editing = true }" v-for="contact in proposalData.proposal_contacts" :key="contact.id">
                                        <li v-if="!contact.editing">Name: {{contact.name}}</li>
                                        <li v-if="!contact.editing">Phone: {{contact.phonenumber}} </li>
                                        <li v-if="!contact.editing">Email: {{contact.email}} </li>
                                        <li v-if="contact.editing">Name: <input :class="inputClass" required type="text" v-model="contact.name"/></li>
                                        <li v-if="contact.editing">Phone: <input :class="inputClass" type="tel" v-model="contact.phonenumber"/></li>
                                        <li v-if="contact.editing">Email: <input :class="inputClass" type="email" v-model="contact.email"/></li>
                                        <li v-if="contact.editing">
                                            <button-component @click.stop="updateContact(contact)" :type="'button'">Save</button-component>
                                            <button-component v-on:click.stop="contact.editing = false" :type="'button'">Cancel</button-component>
                                            <button-component @click.stop="removeContact(contact)" :type="'button'">Delete</button-component>
                                        </li>
                                    </ul>
                                    <ul v-if="showCreateNewContact">
                                        <li>Name: <input :class="inputClass" required type="text" v-model="newContact.name"/></li>
                                        <li>Phone: <input :class="inputClass" type="tel" v-model="newContact.phonenumber"/></li>
                                        <li>Email: <input :class="inputClass" type="email" v-model="newContact.email"/></li>
                                    </ul>
                                    <button-component v-if="!showCreateNewContact" :type="'button'" @click="showCreateNewContact = true">Create New</button-component>
                                    <button-component v-if="showCreateNewContact" :type="'button'" @click="showCreateNewContact = false">Cancel</button-component>
                                    <button-component v-if="showCreateNewContact" :type="'button'" @click="saveContact">Save</button-component>
                                </div>
                                <div @change="unsavedChanges=true" v-if="input.type == 'textArea'">
                                    <textarea class="min-w-full" v-model="proposalData[input.field]" placeholder=""></textarea>
                                </div>
                                <div v-if="input.type == 'date'">
                                    <calendar v-on:date-select="unsavedChanges=true" v-model="proposalData[input.field]" :showTime="true" :disabledDates="getDisabledDates()" :step-minute="15" hourFormat="12">
                                        <template #date="slotProps">
                                            <strong v-if="findReservedDate(slotProps.date)" :title="findReservedDateName(slotProps.date)" class="rounded-full h-24 w-24 flex items-center justify-center bg-red-300">{{slotProps.date.day}}</strong>
                                            <strong v-else-if="findProposedDate(slotProps.date)" :title="findProposedDateName(slotProps.date)" class="rounded-full h-24 w-24 flex items-center justify-center bg-yellow-300">{{slotProps.date.day}}</strong>
                                            <template v-else>{{slotProps.date.day}}</template>
                                        </template>
                                    </calendar>
                                    <div v-for="(date,index) in recurringDates" :key="index">
                                        <calendar v-on:date-select="unsavedChanges=true" v-model="recurringDates[index].date" :showTime="true" :disabledDates="getDisabledDates()" :step-minute="15" hourFormat="12">
                                            <template #date="slotProps">
                                                <strong v-if="findReservedDate(slotProps.date)" :title="findReservedDateName(slotProps.date)" class="rounded-full h-24 w-24 flex items-center justify-center bg-red-300">{{slotProps.date.day}}</strong>
                                                <strong v-else-if="findProposedDate(slotProps.date)" :title="findProposedDateName(slotProps.date)" class="rounded-full h-24 w-24 flex items-center justify-center bg-yellow-300">{{slotProps.date.day}}</strong>
                                                <template v-else>{{slotProps.date.day}}</template>
                                            </template>
                                        </calendar>    
                                        <button
                                            @click="recurringDates.splice(index,1)"
                                            class="transform translate-y-1 bg-red-500 text-white active:bg-purple-600 font-bold uppercase text-xs px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                                            type="button">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>                                   
                                    </div>
                                    <div>
                                        <button-component @click="addRecurringDate">Add another date</button-component>
                                    </div>
                                </div>
                                <div v-if="input.type == 'eventTypeDropdown'">
                                    <select @change="unsavedChanges=true" v-model="proposalData[input.field]">
                                        <option v-for="type in eventTypes" :key="type.id" :value="type.id">{{type.name}}</option>
                                    </select>
                                </div>
                        </div>                                                                                                                                     
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" @click="updateProposal()" type="submit">
                            Update Proposal
                        </button>
                        <button :disabled="proposalData.proposal_contacts.length === 0" :class="[
                                                                                                                {
                                                                                                                    'bg-gray-400': proposalData.proposal_contacts.length === 0,
                                                                                                                    'cursor-not-allowed': proposalData.proposal_contacts.length === 0,
                                                                                                                    'bg-blue-500': proposalData.proposal_contacts.length !== 0,
                                                                                                                    'hover:bg-blue-700' : proposalData.proposal_contacts.length !== 0,
                                                                                                                },
                                                                                                                'text-white',
                                                                                                                'font-bold',
                                                                                                                'py-2',
                                                                                                                'px-4',
                                                                                                                'rounded',
                                                                                                                'focus:outline-none',
                                                                                                                'focus:shadow-outlin',
                                                                                                            ]" 
                            @click="finalizeProposal()" type="button">
                            Finalize
                        </button>
                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="button" v-on:click="deleteProposal()">
                            Delete Proposal
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </breeze-authenticated-layout>
    </div>
</template> 

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import ButtonComponent from '@/Components/Button'
    import Datepicker from 'vue3-datepicker'
    import VueTimepicker from 'vue3-timepicker'
    import 'vue3-timepicker/dist/VueTimepicker.css'
    import moment from 'moment';
import axios from 'axios'
import { forEach } from 'lodash'
    export default {
        props:['proposal','eventTypes','bookedDates','proposedDates','recurringDates'],
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker,ButtonComponent
        }, 
        data(){
            return{
                proposalData:this.proposal,
                newContact:{
                    name:'',
                    phonenumber:'',
                    email:''
                },
                unsavedChanges: false,
                patchingContact:{

                },
                validInputs:[
                    {
                        name:'Name',
                        type:'text',
                        field:'name'
                    },
                    {
                        name:'Event Type',
                        type:'eventTypeDropdown',
                        field:'event_type_id'
                    },
                    {
                        name:'Date / Time',
                        type:'date',
                        field:'date'
                    },
                    {
                        name:'Where',
                        type:'location',
                        field:'location_id'
                    },
                    {
                        name:'Contacts',
                        type:'contacts',
                        field:'proposal_contacts'
                    },
                    {
                        name:'Length',
                        type:'number',
                        field:'hours'
                    },
                    {
                        name:'Price',
                        type:'number',
                        field:'price'
                    },
                    {
                        name:'Colorway',
                        type:'text',
                        field:'color'
                    },
                    {
                        name:'Notes',
                        type:'textArea',
                        field:'notes'
                    },
                ],
                showCreateNewContact : false,
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
                sessionToken : Math.floor(Math.random() * 1000000000),
                searchResults:'',
                searchTimer: null
            }
        },
        created(){
            this.proposalData.date = new Date(moment(String(this.proposalData.date)))
            for(let i in this.recurringDates)
            {
                
                this.recurringDates[i].date = new Date(moment(String(this.recurringDates[i].date)))

            }
        },
        methods:{
            findReservedDate(date)
            {
                
                const jsDate = moment(String(date.month + 1) + '-' + String(date.day) + '-' + String(date.year)).format('YYYY-MM-DD');
                var booked = false;
                this.bookedDates.forEach(bookedDate =>{
                    const parsedDate = moment(bookedDate.event_time).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        booked = true;
                    }
                })
                return booked
            },
            findReservedDateName(date)
            {
                const jsDate = moment(String(date.month + 1) + '-' + String(date.day) + '-' + String(date.year)).format('YYYY-MM-DD');
                var name = '';
                this.bookedDates.forEach(bookedDate =>{
                    const parsedDate = moment(bookedDate.event_time).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        name = bookedDate.event_name;
                    }
                })
                return name
            },
            findProposedDate(date)
            {
                
                const jsDate = moment(String(date.month + 1) + '-' + String(date.day) + '-' + String(date.year)).format('YYYY-MM-DD');
                var booked = false;
                this.proposedDates.forEach(proposedDate =>{
                    const parsedDate = moment(proposedDate.date).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        booked = true;
                    }
                })
                return booked
            },
            findProposedDateName(date)
            {
                const jsDate = moment(String(date.month + 1) + '-' + String(date.day) + '-' + String(date.year)).format('YYYY-MM-DD');
                var name = '';
                this.proposedDates.forEach(proposedDate =>{
                    const parsedDate = moment(proposedDate.date).format('YYYY-MM-DD');
                    if(parsedDate === jsDate)
                    {
                        name = proposedDate.name;
                    }
                })
                return name
            },
            getDisabledDates()
            {
                let dateArray = [];
                this.bookedDates.forEach(date=>{
                    dateArray.push(new Date(moment(String(date.event_time))));
                })
                return dateArray;
            },            
            getMonth(date)
            {
                return moment(date).month()
            },
            getDay(date)
            {
                return moment(date).day()
            },
            getYear(date)
            {
                return moment(date).year()
            },
            saveContact(){
               this.$inertia.post('/proposals/createContact/' + this.proposal.key,this.newContact,{preserveScroll:true,onSuccess:(data)=>{
                   this.newContact.name = '';
                   this.newContact.phonenumber = '';
                   this.newContact.email = '';
                   this.showCreateNewContact = false;
                   this.proposalData.proposal_contacts = data.props.proposal.proposal_contacts;
               }})
            },
            updateContact(contact){
                // console.log(contact);
                this.$inertia.post('/proposals/editContact/' + contact.id,contact,{preserveScroll:true}).then(()=>{
                    contact.editing = false;
                });
            },
            removeContact(contact){
                this.$inertia.delete('/proposals/deleteContact/' + contact.id,{preserveScroll:true}).then(data=>{
                    this.proposalData.proposal_contacts = this.proposalData.proposal_contacts.filter(propContact=>{
                        return propContact.id !== contact.id;
                    })
                });
            },
            hideContactEdits(){
                this.proposalData.proposal_contacts.forEach(tempContact=>tempContact.editing = false)
            },

            updateProposal(){
                this.proposalData.recurring_dates = this.recurringDates;
                this.$inertia.patch('/proposals/' + this.proposal.key + '/update/',this.proposalData);
            },
            finalizeProposal(){
                if(this.unsavedChanges)
                {
                    this.$swal.fire({
                        title: 'You have unsaved changes',
                        text: "Do you want to save changes and finalize or go as is?",
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: 'Save Changes and Finalize',
                        denyButtonText: 'Discard Changes and Finalize',
                    }).then((result)=>{
                        if (result.isConfirmed) {
                            this.$inertia.patch('/proposals/' + this.proposal.key + '/update/',this.proposalData,{
                                onSuccess:()=>{
                                    this.unsavedChanges = false;
                                    this.finalizeProposal();
                                }
                            })
                        } else if (result.isDenied) {
                            this.unsavedChanges = false;
                            this.finalizeProposal();
                        }
                    })
                }
                else
                {
                    this.$inertia.post('/proposals/'+ this.proposal.key + '/finalize/');
                }
            },

            addRecurringDate(){
                this.recurringDates.push({date: new Date(moment(String(this.proposalData.date)).add(this.recurringDates.length + 1,'days'))});
            },
            
            deleteProposal(){
                 this.$swal.fire({
                    title: 'Are you sure you want to delete the ' + this.proposalData.name + 'proposal?',
                    text: "You won't be able to revert this!",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if(result.value)
                    {
                        this.$inertia.delete('/proposals/' + this.proposal.key + '/delete');
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
                        searchParams:this.proposalData.location,
                    }).then((response)=>{
                        this.searchResults = response.data.predictions
                    })
                },800)
            }
        }
    }
</script>

