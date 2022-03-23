<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
        <DataTable
          v-model:filters="filters1"
          :value="events"
          responsive-layout="scroll"
          selection-mode="single" 
          :paginator="true"
          :rows="10"
          :rows-per-page-options="[10,20,50]"
          :global-filter-fields="['event_name','event_date','venue_name','event_type']"
          filter-display="menu"
          sort-field="event_date"
          :sort-order="true"
          @rowSelect="gotoEvent"
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
                  >Previous Events</label>
                  <InputSwitch
                    id="switch"
                    v-model="filters1['OldEvent'].value"
                    class="float-right"
                    :true-value="true"
                    :false-value="false"
                  />
                </div>
              </div>
            </div>
          </template>
          <template #empty>
            No Events.
          </template>
          <Column
            field="event_name"
            filter-field="event_name"
            header="Name"
            :sortable="true"
          />
          <Column
            field="venue_name"
            filter-field="venue_name"
            header="Venue"
            :sortable="true"
          />
          <Column
            field="event_time"
            filter-field="event_time"
            header="Performance Date"
            :sortable="true"
          >
            <template #body="slotProps">
              {{ formatTime(slotProps) }}
            </template>
          </Column>
          <!--
          <Column
            field="formattedDraftDate"
            header="Draft Date"
            sortable
          />
          
          <Column
            field="phase.name"
            filter-field="phase.name"
            header="Phase"
            :sortable="true"
          /> -->
        </DataTable>
        <div class="flex justify-center items-center my-4">
          <button
            type="button"
            class="self-center bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5"
            @click="$inertia.visit($route('events.create'))"
          >
            Draft Event
          </button>
        </div>
      </div>
    </div>
  </Container>
</template>

<script>
import {FilterMatchMode,FilterOperator} from 'primevue/api';
import moment from 'moment';
export default {
  name: 'EventList',
  props:['events'],
  data(){
    return{
      searchParams : {},
      dontShowCompleted:false,
      filters1: null,
      band:{}
    }
  },
    created(){
      this.initFilters1();
      this.searchParams = this.$qs.parse(location.search.slice(1));
      
  },
  methods:{
    formatTime(row){
      return moment(row.data.event_time).format('YYYY-MM-DD H:mm A');
    },
    gotoEvent(row){
      this.$inertia.visit(this.$route("events.edit",row.data.event_key));
    },
    clearFilter1() {
      this.initFilters1();
    },
    initFilters1() {
      this.filters1 = {
            'global': {value: null, matchMode: FilterMatchMode.CONTAINS},
            'OldEvent': {value: false, matchMode: FilterMatchMode.EQUALS}
        }
    }          
  }
}
</script>

<style>

</style>