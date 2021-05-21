<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Proposals
            </h2>
        </template>
        <card-modal ref="proposalModal" v-if="showModal" :saveText="'Edit'" @save="gotoProposal" @closing="toggleModal()">
            <template #header>
                <h1>{{activeProposal.name}}</h1>
            </template>
            <template #body>
                <div v-for="(field,index) in showFields" :key="index" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                    <p class="text-gray-600">
                        <label for="name">{{field.name}}</label>
                    </p>
                    <div class="mb-4">
                        {{ field.subProperty ? activeProposal[field.property][field.subProperty] : activeProposal[field.property]}}
                        <!-- <p-inputtext v-model="form.event_name"></p-inputtext>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Event Name" v-model="form.event_name"> -->
                    </div>
                </div>
                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                    <p class="text-gray-600">
                        <label for="name">Contacts</label>
                    </p>
                    <ul v-for="contact in activeProposal.proposal_contacts" :key="contact.id" class="mb-4">
                        <li>Name: {{contact.name}}</li>
                        <li>Phone: {{contact.phonenumber}}</li>
                        <li>Name: {{contact.email}}</li>
                    </ul>
                </div>
            </template>
        </card-modal>
        <card-modal ref="proposalCreateModal" v-if="showCreateModal" :saveText="'Create Draft'" @save="draftProposal" @closing="toggleCreateModal()">
            <template #header>
                <h1>New Proposal</h1>
            </template>
            <template #body>
                <div v-for="input in draftInputs" :key="input" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                    <p class="text-gray-600">
                                    <label :for="input.name">{{input.name}}</label>
                                </p>
                                <div v-if="['text','number'].indexOf(input.type) !== -1" class="mb-4">
                                    <!-- <p-inputtext v-model="proposalData"></p-inputtext> -->
                                    <input :type="input.type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" :id="input.name" :placeholder="input.name" v-model="proposalData[input.field]">
                                </div>
                                <div v-if="input.type == 'textArea'">
                                    <textarea class="min-w-full" v-model="proposalData[input.field]" placeholder=""></textarea>
                                </div>
                                <div v-if="input.type == 'date'">
                                    <calendar v-model="proposalData[input.field]" :showTime="true" :step-minute="15" hourFormat="12" />
                                </div>
                                <div v-if="input.type == 'eventTypeDropdown'">
                                    <select v-model="proposalData[input.field]">
                                        <option v-for="type in eventTypes" :key="type.id" :value="type.id">{{type.name}}</option>
                                    </select>
                                </div>
                </div>
            </template>
        </card-modal>
        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <div v-if="bandsAndProposals.length > 0" class="container my-8">
                        <div v-for="band in bandsAndProposals" :key="band.id">
                            {{band.name}}
                            <table class="min-w-full bg-white m-5 rounded">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Name</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Date</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Phase</th>
                                    </tr>
                                </thead>  
                                <tbody v-if="band.proposals.length > 0" class="text-gray-700">
                                    <tr @click="selectProposal(proposal)" :class="[{'bg-gray-100': $index % 2 === 0, 'border-b': $index % 2 !== 0 },'hover:bg-gray-200','cursor-pointer']" v-for="(proposal,$index) in band.proposals" :key="proposal.id">
                                        <td class="w-1/3 text-center py-3 px-4">{{proposal.name}}</td>
                                        <td class="w-1/3 text-center py-3 px-4">{{proposal.date}}</td>
                                        <td class="w-1/3 text-center py-3 px-4">{{proposal.phase.name}}</td>
                                    </tr>
                                </tbody>  
                                <tbody v-else>
                                    <tr>
                                        <td  colspan="3" class="text-center">No proposals at the moment</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="my-4"><button @click="toggleCreateModal(band.site_name)" type="button" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5">Draft Proposal for {{band.name}}</button></div>
                        </div>
                        
                    </div>
                    <div v-else>
                        It looks like you don't have any bands to create a proposal for. 
                        <button type="button" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Draft Proposal</button>
                    </div>
                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import moment from 'moment';
    export default {
        props:['bandsAndProposals','successMessage','eventTypes'],
        components: {
            BreezeAuthenticatedLayout,
        },
        data(){
            return{
                showModal:false,
                showCreateModal:false,
                activeProposal:{},
                activeBandSite:'',
                showFields:[
                    {name:'Author',property:'author',subProperty:'name'},
                    {name:'Proposed Date/Time',property:'date'},
                    {name:'Hours',property:'hours'},
                    {name:'Event Type',property:'event_type',subProperty:'name'},
                    {name:'Price',property:'price'},
                    {name:'Color',property:'color'},
                    {name:'Locked',property:'locked'},
                    {name:'Notes',property:'notes'},
                    {name:'Created',property:'created_at'}
                ],
                proposalData:{
                    name:'',
                    date:'',
                    event_type_id:0,
                    hours:0,
                    price:0,
                    notes:'',
                },
                draftInputs:[
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
                        name:'Notes',
                        type:'textArea',
                        field:'notes'
                    },                    
                ]
            }
        },
        methods:{
            toggleCreateModal(sitename){
                this.activeBandSite = sitename;
                this.showCreateModal = !this.showCreateModal
            },
            toggleModal(){
                this.showModal = !this.showModal
            },
            selectProposal(proposal){
                this.activeProposal = proposal;
                this.showModal = true;
            },
            gotoProposal(){
                this.$inertia.get('/proposals/' + this.activeProposal.key + '/edit');
            },
            draftProposal(){
                this.$inertia.post('/proposals/' + this.activeBandSite + '/create',this.proposalData);
            }
        }
    }
</script>
