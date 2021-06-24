<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Invoices
            </h2>
        </template>

        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                            <DataTable :value="proposals" responsiveLayout="scroll" selectionMode="single" :paginator="true" 
                            :rows="10" @rowSelect="selectProposal"
                            :rowsPerPageOptions="[10,20,50]"
                            v-model:filters="filters1"
                            :globalFilterFields="['name','date','band.name']"
                            filterDisplay="menu">
                            <template #header>
                                <div class="p-d-flex p-jc-between">
                                    <Button type="button" icon="pi pi-filter-slash" label="Clear" class="p-button-outlined" @click="clearFilter1()"/>
                                    <span class="p-input-icon-left">
                                        <i class="pi pi-search" />
                                        <InputText v-model="filters1['global'].value" placeholder="Keyword Search" />
                                    </span>
                                </div>
                            </template>
                            <template #empty>
                                No Completed Proposals.
                            </template>
                                <Column field="name" filterField="name" header="Name" :sortable="true"></Column>
                                <Column field="date" filterField="date" header="Date" :sortable="true"></Column>
                                <Column field="band.name" filterField="band.name" header="Band" :sortable="true"></Column>
                                <Column>
                                    <template #body="slotProps">
                                        <Button @click="createInvoice(slotProps.data)" icon="pi pi-dollar" label="Create Invoice"/>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>                        
                    </div>
                </div>

                <card-modal ref="proposalModal" v-if="showModal" :showSave="false" @closing="toggleModal()">
            <template #header>
                <h1>{{activeProposal.name}}</h1>
            </template>
            <template #body>
                <div v-if="loading" class=" flex justify-center items-center flex align-center content-center h-full flex-col">
                    <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-gray-900"></div>
                    <div class="my-8">Sending...</div>
                </div>
                <div v-else>
                    <div v-for="(field,index) in showFields.filter(field=>Array.isArray(activeProposal[field.property]) ? activeProposal[field.property].length > 0 : activeProposal[field.property])" :key="index" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                        <p class="text-gray-600">
                            <label for="name">{{field.name}}</label>
                        </p>
                        <div class="mb-4" v-if="field.property == 'invoices'">
                            <div v-if="activeProposal[field.property].length == 0">
                                No invoices have been sent. 
                            </div>
                            <ul v-else>
                                <li v-for="(property,index) in activeProposal[field.property]" :key="index">${{property.amount}} - Sent {{property.created_at}}</li>
                            </ul>
                        </div>
                        <div class="overflow-ellipsis text-blue-500" v-if="field.property == 'contract'">
                            <a target="_blank" :href="activeProposal[field.property][field.subProperty]" download>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd" />
                                </svg> Download Contract
                            </a>
                        </div>
                        <div v-else-if="field.property == 'price'">
                            ${{parseFloat(activeProposal[field.property]).toFixed(2)}}
                        </div>
                        <div v-else class="mb-4">
                            {{ field.subProperty ? activeProposal[field.property][field.subProperty] : activeProposal[field.property]}}
                        </div>
                    </div>
                    <div :class="['md:grid', 'md:grid-cols-2', 'hover:bg-gray-50', 'md:space-y-0', 'space-y-1', 'p-4']">
                        <p class="text-gray-600">
                            <label for="name">Contacts</label>
                        </p>
                        <div>
                            <ul v-for="contact in activeProposal.proposal_contacts" :key="contact.id" class="mb-4">
                                <li>Name: {{contact.name}}</li>
                                <li>Phone: {{contact.phonenumber}}</li>
                                <li>Name: {{contact.email}}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </template>
            <template #footerBody>
                <div class="flex-auto">
                    <button v-if="activeProposal.phase_id == 2" @click="sendIt()" type="button" class="mx-2 bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Send It!
                    </button>
                </div>
            </template>
        </card-modal>
        <card-modal ref="proposalCreateInvoice" v-if="showInvoiceModal" :saveText="'Create Invoice'" @save="sendInvoice" @closing="toggleInvoiceModal()">
            <template #header>
                <h1>New Invoice</h1>
            </template>
            <template #body>
                <div v-for="input in draftInputs" :key="input" class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                    <p class="text-gray-600">
                        <label :for="input.name">{{input.name}}</label>
                    </p>

                    <div v-if="['text','number'].indexOf(input.type) !== -1" class="mb-4">
                        <!-- <p-inputtext v-model="proposalData"></p-inputtext> -->
                        <input v-if="input.editable" :type="input.type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" :id="input.name" :placeholder="input.name" v-model="activeProposal[input.field]">
                        <span v-else class="appearance-none rounded w-full py-2 px-3 text-gray-700 leading-tight" :id="input.name">{{activeProposal[input.field]}}</span>
                    </div>
                    <div v-if="input.type == 'textArea'">
                        <textarea class="min-w-full" v-model="activeProposal[input.field]" placeholder=""></textarea>
                    </div>
                    <div v-if="input.type == 'toggle'">
                        <InputSwitch v-model="activeProposal[input.field]" />
                    </div>
                    <div v-if="input.note" class="md:grid-cols-2">
                        <p class="text-gray-400 text-sm">{{input.note}}</p>
                    </div>
                </div>
            </template>
            
        </card-modal>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import moment from 'moment';
    import DataTable from 'primevue/datatable';
    import Column from 'primevue/column';
    import InputText from 'primevue/inputtext';
    import InputSwitch from 'primevue/inputswitch';
    import Button from 'primevue/button';
    import axios from 'axios';
    import {FilterMatchMode,FilterOperator} from 'primevue/api';

    export default {
        props:['proposals','successMessage','eventTypes'],
        components: {
            BreezeAuthenticatedLayout,
            DataTable,
            Column,
            InputText,
            InputSwitch,
            Button
        },
        created(){
            this.initFilters1();
        },
        data(){
            return{
                showModal:false,
                showInvoiceModal:false,
                activeProposal:{},
                activeBandSite:'',
                filters1: null,
                showFields:[
                    {name:'Invoices',property:'invoices',subProperty:'amount'},
                    {name:'Author',property:'author',subProperty:'name'},
                    {name:'Proposed Date/Time',property:'date'},
                    {name:'Recurring dates',property:'recurring_dates',subProperty:'date'},
                    {name:'Event Type',property:'event_type',subProperty:'name'},
                    {name:'Location',property:'location'},
                    {name:'Hours',property:'hours'},
                    {name:'Price',property:'price'},
                    {name:'Color',property:'color'},
                    {name:'Locked',property:'locked'},
                    {name:'Notes',property:'notes'},
                    {name:'Created',property:'created_at'},
                    {name:'Contract PDF',property:'contract',subProperty:'image_url'}
                ],
                proposalData:{
                    name:'',
                    date:new Date(moment().set({'hour':19,'minute':0}).add('month',1)),
                    event_type_id:0,
                    hours:0,
                    price:0,
                    notes:'',
                },
                loading:false,
                draftInputs:[
                    {
                        name:'Name',
                        type:'text',
                        field:'name',
                        editable:false
                    },   
                    {
                        name:'Agreed upon price',
                        type:'text',
                        field:'price',
                        editable:false
                    },              
                    {
                        name:'Amount Paid',
                        type:'text',
                        field:'amountPaid',
                        editable:false
                    },
                    {
                        name:'Amount Owed',
                        type:'text',
                        field:'amountOwed',
                        editable:false,
                    },
                    {
                        name:'Invoice Amount',
                        type:'number',
                        field:'amount',
                        editable:true
                    },
                    {
                        name:'Buyer pays the 2.9% convenience fee',
                        type:'toggle',
                        field:'buyer_pays_convenience',
                        note:'The payment processor Stripe has a default rate of 2.9% of every transaction plus $0.30. If you want to eat the cost yourself, leave this off. If you want to put the transaction fee on the buyer, enable this. For example, let\'s say you have an agreed performance price of $1000. Turning this off will give you a return of $970.70 ($1000 - (($1000 * 0.029) + 0.30)). Turning this on will give you a return of $1000, but the buyer will pay $1029.30 ($1000 + (($1000 * 0.029) + 0.30)).',
                        editable:true
                    }

                ]
            }
        },
        methods:{
            toggleInvoiceModal(sitename){
                this.showInvoiceModal = !this.showInvoiceModal
            },
            createInvoice(proposal)
            {
                console.log('create the invoice',proposal);

                proposal.buyer_pays_convenience = true;

                proposal.amountPaid = 0;

                for(let i in proposal.invoices)
                {
                    const invoice = proposal.invoices[i];

                    proposal.amountPaid += invoice.amount;
                }
            

                proposal.amountOwed = proposal.price - proposal.amountPaid;
                proposal.amount = proposal.amountOwed;
                
                this.activeProposal = proposal;
                this.showInvoiceModal = true;
            },
            sendInvoice()
            {
                this.$inertia.post('/invoices/' + this.activeProposal.key + '/send',{
                    amount:this.activeProposal.amount,
                    buyer_pays_convenience:this.activeProposal.buyer_pays_convenience
                });
                console.log('sending invoice');
            },
            toggleModal(){
                this.showModal = !this.showModal
            },
            selectProposal(proposal){
                for(const i in proposal.data.invoices)
                {
                    proposal.data.invoices[i].created_at = moment(proposal.data.invoices[i].created_at).format('LLLL')
                }
                this.activeProposal = proposal.data;
                this.showModal = true;
            },
            gotoProposal(){
                this.$inertia.get('/proposals/' + this.activeProposal.key + '/edit');
            },
            sendIt(){
                this.activeProposal.phase_id = 3;
                this.loading = true;
                setTimeout(()=>{
                    this.$inertia.post('/proposals/' + this.activeProposal.key + '/sendit',{},{
                        onSuccess:()=>{
                            this.loading = false;
                        this.toggleModal();
                        }
                    });
                },1000)
            },
            clearFilter1() {
                this.initFilters1();
            },
            initFilters1() {
                this.filters1 = {
                    'global': {value: null, matchMode: FilterMatchMode.CONTAINS}
                }
            }            
        }
    }
</script>
