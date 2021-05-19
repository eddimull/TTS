<template>
    <breeze-authenticated-layout>
       <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Proposal
            </h2>
        </template>
        <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{proposal}}
             <div class="mb-4">
                <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                    <div class="bg-white w-full rounded-lg shadow-xl">
                        <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                            <p class="text-gray-600">
                                <label for="name">Name</label>
                            </p>
                            <div class="mb-4">
                                <p-inputtext v-model="form.name"></p-inputtext>
                                <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Proposal Name" v-model="form.name">
                            </div>
                        </div>    
                        <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                            <p class="text-gray-600">
                                <label for="contacts">Contacts</label>
                            </p>
                            <div class="mb-4">
                                <ul>
                                    <li v-for="contact in proposal_contacts" :key="contact.id">{{contact.name}}</li>
                                    <li> Create Contact </li>
                                </ul>
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
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import Datepicker from 'vue3-datepicker'
    import VueTimepicker from 'vue3-timepicker'
    import 'vue3-timepicker/dist/VueTimepicker.css'
    import moment from 'moment';
    export default {
        props:['proposal'],
        components: {
            BreezeAuthenticatedLayout,Datepicker,VueTimepicker
        }, 
        data(){
            return{
                form:{
                    name:this.proposal.name
                }
            }
        },
        created(){

        },
        methods:{

        }
    }
</script>
