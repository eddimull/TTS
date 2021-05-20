<template>
    <div>
        <breeze-authenticated-layout>
        <template #header>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Edit Proposal
                </h2>
            </template>
            <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
                {{proposalData}}
                <div class="mb-4">
                    <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <div class="bg-white w-full rounded-lg shadow-xl">
                            <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                <p class="text-gray-600">
                                    <label for="name">Name</label>
                                </p>
                                <div class="mb-4">
                                    <!-- <p-inputtext v-model="proposalData"></p-inputtext> -->
                                    <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Proposal Name" v-model="proposalData.name">
                                </div>
                            </div>    
                            <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                <p class="text-gray-600">
                                    <label for="contacts">Contacts</label>
                                </p>
                                <div class="mb-4">
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
                        </div>                                                                                                                                     
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                            Save Proposal
                        </button>
                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" v-on:click="showAlert">
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
    export default {
        props:['proposal'],
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
                patchingContact:{

                },
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

        },
        methods:{
            saveContact(){
               this.$inertia.post('/proposals/createContact/' + this.proposal.key,this.newContact,{preserveScroll:true,onSuccess:(data)=>{
                   console.log(data);
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
                console.log('hide?');
                this.proposalData.proposal_contacts.forEach(tempContact=>tempContact.editing = false)
            }
        }
    }
</script>
