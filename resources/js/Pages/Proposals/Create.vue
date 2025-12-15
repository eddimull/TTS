<template>
  <div>
    <breeze-authenticated-layout>
      <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Create Proposal
        </h2>
      </template>
      <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-4">
          <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
            <div class="bg-white w-full rounded-lg shadow-xl">
              <div
                v-for="input in validInputs"
                :key="input"
                class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
              >
                <p class="text-gray-600">
                  <label :for="input.name">{{ input.name }}</label>
                </p>
                <div
                  v-if="['text','number'].indexOf(input.type) !== -1"
                  class="mb-4"
                >
                  <!-- <p-inputtext v-model="proposalData"></p-inputtext> -->
                  <input
                    :id="input.name"
                    v-model="proposalData[input.field]"
                    :type="input.type"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    :placeholder="input.name"
                  >
                </div>
                <div v-if="input.type == 'contacts'">
                  <ul
                    v-for="contact in proposalData.proposal_contacts"
                    :key="contact.id"
                    class="hover:bg-gray-100 cursor-pointer"
                    @click="proposalData.proposal_contacts.forEach(tempContact=>tempContact.editing = false); if(!contact.editing){ contact.editing = true }"
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
                <div v-if="input.type == 'textArea'">
                  <textarea
                    v-model="proposalData[input.field]"
                    class="min-w-full"
                    placeholder=""
                  />
                </div>
                <div v-if="input.type == 'date'">
                  <calendar
                    v-model="proposalData[input.field]"
                    :show-time="true"
                    :step-minute="15"
                    hour-format="12"
                  />
                </div>
                <div v-if="input.type == 'eventTypeDropdown'">
                  <select v-model="proposalData[input.field]">
                    <option
                      v-for="type in eventTypes"
                      :key="type.id"
                      :value="type.id"
                    >
                      {{ type.name }}
                    </option>
                  </select>
                </div>
              </div>                                                                                                                                     
            </div>
            <div class="flex items-center justify-between">
              <button
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                type="submit"
                @click="updateProposal()"
              >
                Create Proposal
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
    import { DateTime } from 'luxon';
    export default {
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker,ButtonComponent
        },
        props:['proposal','eventTypes'], 
        data(){
            return{
                proposalData:this.proposal,
                newContact:{
                    name:'',
                    phonenumber:'',
                    email:''
                },
                patchingContact:{

                },
                validInputs:[
                    {
                        name:'Name',
                        type:'text',
                        field:'name'
                    },
                    {
                        name:'Date / Time',
                        type:'date',
                        field:'date'
                    },
                    {
                        name:'Event Type',
                        type:'eventTypeDropdown',
                        field:'event_type_id'
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
                ]
                
            }
        },
        created(){
            this.proposalData.date = DateTime.fromISO(String(this.proposalData.date)).toJSDate()
        },
        methods:{
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
                this.$inertia.patch('/proposals/' + this.proposal.key + '/update/',this.proposalData);
            }
        }
    }
</script>
