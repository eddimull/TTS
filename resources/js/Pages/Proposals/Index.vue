<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Proposals
      </h2>
    </template>

    <Container class="md:container md:mx-auto">
      <div
        v-if="false"
        class="max-w-md"
      >
        <Chart
          type="doughnut"
          :data="chartData"
          :options="lightOptions"
        />
      </div>
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
          <div
            v-if="bandsAndProposals.length > 0"
            class="container my-8"
          >
            <div
              v-for="band in bandsAndProposals"
              :key="band.id"
            >
              <span class="font-semibold">{{ band.name }}</span>

              <DataTable
                v-model:filters="filters1"
                :value="band.proposals"
                responsive-layout="scroll"
                selection-mode="single" 
                :paginator="true"
                :rows="10"
                :rows-per-page-options="[10,20,50]"
                :global-filter-fields="['name','date','phase.name']"
                filter-display="menu"
                @rowSelect="selectProposal"
              >
                <template #header>
                  <div class="flex flex-row">
                    <div class="hidden md:flex">
                      <Button
                        class="p-button-outlined"
                        type="button"
                        icon="pi pi-filter-slash"
                        label="Clear"
                        @click="clearFilter1()"
                      />
                    </div>
                    <span class="p-input-icon-left">
                      <i class="pi pi-search" />
                      <InputText
                        v-model="filters1['global'].value"
                        placeholder="Keyword Search"
                      />
                    </span>
                    <div class="flex-grow mt-2">
                      <div class="flex justify-end content-between">
                        <label
                          for="switch"
                          class="mr-2"
                        >Completed</label>
                        <InputSwitch
                          id="switch"
                          v-model="filters1['phase_id'].value"
                          class="float-right"
                          :true-value="6"
                          :false-value="null"
                        />
                      </div>
                    </div>
                  </div>
                </template>
                <template #empty>
                  No Proposals.
                </template>
                <Column
                  field="name"
                  filter-field="name"
                  header="Name"
                  :sortable="true"
                />
                <Column
                  field="formattedDraftDate"
                  header="Draft Date"
                  sortable
                />
                <Column
                  field="date"
                  filter-field="date"
                  header="Performance Date"
                  :sortable="true"
                />
                <Column
                  field="phase.name"
                  filter-field="phase.name"
                  header="Phase"
                  :sortable="true"
                />
              </DataTable>
              <div class="flex justify-center items-center my-4">
                <button
                  type="button"
                  class="self-center bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5"
                  @click="toggleCreateModal(band.site_name)"
                >
                  Draft Proposal for {{ band.name }}
                </button>
              </div>
            </div>
          </div>
          <div v-else>
            It looks like you don't have any bands to create a proposal for. 
            <!-- <button
              type="button"
              class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
            >
              Draft Proposal
            </button> -->
          </div>
        </div>
      </div>
    </Container>

    <card-modal
      v-if="showModal"
      ref="proposalModal"
      :save-text="'Edit'"
      @save="gotoProposal"
      @closing="toggleModal()"
    >
      <template #header>
        <h1>{{ activeProposal.name }}</h1>
      </template>
      <template #headerBody>
        <div class="mb-1 md:mb-4 p-0 md:p-4 w-full flex flex-row overflow-x-auto">
          <div
            v-for="(phase,index) in proposal_phases"
            :key="phase.id"
            class="flex items-center flex-grow"
          >
            <div
              :class="['flex', 'items-center', {
                'text-purple-500':phase.id <= activeProposal.phase_id,
                'text-gray-500':phase.id > activeProposal.phase_id
              }, 'relative']"
            >
              <div
                :title="phase.name"
                :class="['rounded-full', 'transition', 'duration-1000', 'ease-in-out', 'h-10', 'w-10','md:h-12','md:w-12', 'py-3', 'border-2', 
                         {
                           'border-purple-500':phase.id <= activeProposal.phase_id,
                           'border-gray-300':phase.id > activeProposal.phase_id
                         }
                ]"
                v-html="phase.icon"
              />
              <div
                :class="['hidden', 'md:block', 'absolute', 'top-0','left-0', 'text-center', 'mt-12', 'text-xs', 'font-medium', 'uppercase', 
                         {
                           '-ml-2':phase.id > 1,
                           'text-purple-500':phase.id <= activeProposal.phase_id,
                           'text-gray-500':phase.id > activeProposal.phase_id
                         }   
                ]"
              >
                {{ phase.name }}
              </div>
            </div>
            <div
              v-if="index+1 !== Object.keys(proposal_phases).length"
              :class="['flex-auto', 'border-t-2', 'transition', 'duration-500', 'ease-in-out', 
                       {
                         'border-purple-500':phase.id < activeProposal.phase_id,
                         'border-gray-300':phase.id > activeProposal.phase_id
                                                                                        
                       }
              ]"
            />
          </div>
        </div>
      </template>
      <template #body>
        <div
          v-if="loading"
          class=" flex justify-center items-center flex align-center content-center h-full flex-col"
        >
          <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-gray-900" />
          <div class="my-8">
            Sending...
          </div>
        </div>
        <div v-else>
          <div
            v-for="(field,index) in showFields.filter(field=>Array.isArray(activeProposal[field.property]) ? activeProposal[field.property].length > 0 : activeProposal[field.property])"
            :key="index"
            class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b"
          >
            <p class="text-gray-600">
              <label for="name">{{ field.name }}</label>
            </p>
            <div
              v-if="Array.isArray(activeProposal[field.property])"
              class="mb-4"
            >
              <ul>
                <li
                  v-for="(property,index) in activeProposal[field.property]"
                  :key="index"
                >
                  {{ property[field.subProperty] }}
                </li>
              </ul>
            </div>
            <div
              v-if="field.property == 'contract'"
              class="overflow-ellipsis text-blue-500"
            >
              <a
                target="_blank"
                :href="activeProposal[field.property][field.subProperty]"
                download
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 inline"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z"
                    clip-rule="evenodd"
                  />
                </svg> Download Contract
              </a>
            </div>
            <div v-else-if="field.property == 'price'">
              ${{ parseFloat(activeProposal[field.property]).toFixed(2) }}
            </div>
            <div
              v-else
              class="mb-4"
            >
              {{ field.subProperty ? activeProposal[field.property][field.subProperty] : activeProposal[field.property] }}
            </div>
          </div>
          <div :class="['md:grid', 'md:grid-cols-2', 'hover:bg-gray-50', 'md:space-y-0', 'space-y-1', 'p-4']">
            <p class="text-gray-600">
              <label for="name">Contacts</label>
            </p>
            <div>
              <ul
                v-for="contact in activeProposal.proposal_contacts"
                :key="contact.id"
                class="mb-4"
              >
                <li>Name: {{ contact.name }}</li>
                <li>Phone: {{ contact.phonenumber }}</li>
                <li>Name: {{ contact.email }}</li>
              </ul>
            </div>
          </div>
        </div>
      </template>
      <template #footerBody>
        <div class="flex-auto">
          <button
            v-if="activeProposal.phase_id == 2"
            :disabled="loading"
            type="button"
            class="mx-2 bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none"
            @click="sendIt()"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="inline h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
              />
            </svg>
            Send It!
          </button>
          <button
            v-if="activeProposal.phase_id == 3"
            :disabled="loading"
            type="button"
            class="mx-2 bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded text-white focus:outline-none"
            @click="sendIt()"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="inline h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
              />
            </svg>
            Resend it
          </button>
          <button
            v-if="activeProposal.phase_id == 4"
            :disabled="loading"
            type="button"
            class="mx-2 bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none"
            @click="sendContract()"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="inline h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
              />
            </svg>
            Send Contract!
          </button>
        </div>
      </template>
    </card-modal>
    <card-modal
      v-if="showCreateModal"
      ref="proposalCreateModal"
      :save-text="'Create Draft'"
      @save="draftProposal"
      @closing="toggleCreateModal()"
    >
      <template #header>
        <h1>New Proposal</h1>
      </template>
      <template #body>
        <div
          v-for="input in draftInputs"
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
          <div
            v-if="input.type == 'currency'"
            class="mb-4"
          >
            <currency-input
              v-model.lazy="value"
              v-model="proposalData[input.field]"
            />
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
              :disabled-dates="getDisabledDates()"
              :show-time="true"
              :step-minute="15"
              hour-format="12"
            >
              <template #date="slotProps">
                <strong
                  v-if="findReservedDate(slotProps.date)"
                  :title="findReservedDateName(slotProps.date)"
                  class="rounded-full h-24 w-24 flex items-center justify-center bg-red-300"
                  @click="$swal.fire('Date already booked with ' + findReservedDateName(slotProps.date))"
                >{{ slotProps.date.day }}</strong>
                <strong
                  v-else-if="findProposedDate(slotProps.date)"
                  :title="findProposedDateName(slotProps.date)"
                  class="rounded-full h-24 w-24 flex items-center justify-center bg-yellow-300"
                  @click="$swal.fire('Date under proposal with ' + findProposedDateName(slotProps.date))"
                >{{ slotProps.date.day }}</strong>
                <template v-else>
                  {{ slotProps.date.day }}
                </template>
              </template>
            </calendar>
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
    import Button from 'primevue/button';
    import axios from 'axios';
    import CurrencyInput from '@/Components/CurrencyInput';
    import {FilterMatchMode,FilterOperator} from 'primevue/api';
import Label from '../../Components/Label.vue';


    export default {
        components: {
            BreezeAuthenticatedLayout,
            DataTable,
            Column,
            InputText,
            Button,
            CurrencyInput,
                Label
        },
        props:['bandsAndProposals','successMessage','eventTypes','proposal_phases','bookedDates','proposedDates'],
        data(){
            return{
                showModal:false,
                showCreateModal:false,
                activeProposal:{},
                searchParams : {},
                activeBandSite:'',
                dontShowCompleted:false,
                filters1: null,
                chartData: {
                  labels: ['A','B','C'],
                  datasets: [
                                {
                                    data: [300, 50, 100],
                                    backgroundColor: ["#FF6384","#36A2EB","#FFCE56"],
                                    hoverBackgroundColor: ["#FF6384","#36A2EB","#FFCE56"]
                                }
                            ]
                },
                lightOptions: {
                  plugins: {
                              legend: {
                                  labels: {
                                      color: '#495057'
                                  }
                              }
                          }
                },
                showFields:[
                    {name:'Author',property:'author',subProperty:'name'},
                    {name:'Proposed Date/Time',property:'date'},
                    {name:'Recurring dates',property:'recurring_dates',subProperty:'date'},
                    {name:'Event Type',property:'event_type',subProperty:'name'},
                    {name:'Location',property:'location'},
                    {name:'Hours',property:'hours'},
                    {name:'Price',property:'price'},
                    {name:'Color',property:'color'},
                    {name:'Locked',property:'locked'},
                    {name:'Notes (client does not see this)',property:'notes'},
                    {name:'Client Notes',property:'client_notes'},
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
                        name:'Name of the gig',
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
                        name:'Length (hours)',
                        type:'number',
                        field:'hours'
                    },
                    {
                        name:'Price',
                        type:'currency',
                        field:'price'
                    },
                    {
                        name:'Notes (for band)',
                        type:'textArea',
                        field:'notes'
                    },      
                    {
                        name:'Notes for client',
                        type:'textArea',
                        field:'client_notes'
                    },               
                ]
            }
        },
        created(){
            this.initFilters1();
            this.setChartData();
            this.searchParams = this.$qs.parse(location.search.slice(1));
            
        },
        mounted(){
            if(this.searchParams.open)
            {
                this.bandsAndProposals.forEach(band => {
                    if(band.proposals)
                    {
                        band.proposals.forEach(proposal=>{
                            if(proposal.id.toString() === this.searchParams.open)
                            {
                                this.selectProposal({data:proposal})
                            }
                        })
                    }
                })
            }
        },
        methods:{
            setChartData()
            {
              
            },
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
            toggleCreateModal(sitename){
                this.activeBandSite = sitename;
                this.showCreateModal = !this.showCreateModal
            },
            toggleModal(){
                this.showModal = !this.showModal
            },
            selectProposal(proposal){
                
                this.activeProposal = proposal.data;
                this.showModal = true;
            },
            gotoProposal(){
                this.$inertia.get('/proposals/' + this.activeProposal.key + '/edit');
            },
            draftProposal(){
                this.$inertia.post('/proposals/' + this.activeBandSite + '/create',this.proposalData);
            },
            sendIt(){
                
                this.loading = true;
                this.$swal.fire({
                    inputLabel: 'Customized message for the proposal email',
                    inputPlaceholder: 'Type your message here...',
                    inputValue:'Please confirm the details of this proposal',
                    input: 'textarea',
                    showCancelButton: true 
                }).then(input =>{
                    if(input.isConfirmed)
                    {
                        setTimeout(()=>{

                            this.$inertia.post('/proposals/' + this.activeProposal.key + '/sendit',{message:input.value},{
                                onSuccess:()=>{
                                    this.activeProposal.phase_id = 3;
                                    this.loading = false;
                                // this.toggleModal();
                                }
                            });
                        },1000)
                    }
                    else
                    {
                        this.loading = false;
                    }
                });
                
            },
            sendContract(){
               this.loading = true;
               setTimeout(()=>{

                  this.$inertia.post('/proposals/' + this.activeProposal.key + '/sendContract',{},{
                      onSuccess:()=>{
                          this.activeProposal.phase_id = 5;
                          this.loading = false;
                      // this.toggleModal();
                      }
                  });
              },1000)
            },
            clearFilter1() {
                this.initFilters1();
            },
            initFilters1() {
                this.filters1 = {
                    'global': {value: null, matchMode: FilterMatchMode.CONTAINS},
                    'phase_id': {value: null, matchMode: FilterMatchMode.CONTAINS}
                }
            }            
        }
    }
</script>
