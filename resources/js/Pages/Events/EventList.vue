<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
        <div class="flex justify-center items-center my-4">
          <button
            type="button"
            class="self-center bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5"
            @click="$inertia.visit($route('events.create'))"
          >
            Draft New Event
          </button>
          <div class="card">
            <h5>Upcoming Events</h5>
            <Chart
              type="bar"
              :data="gigs"
            />
          </div>
        </div>
        <DataTable
          v-model:filters="filters1"
          :value="events"
          responsive-layout="scroll"
          selection-mode="single"
          :paginator="true"
          :rows="10"
          :rows-per-page-options="[10,20,50]"
          :global-filter-fields="['title','date','event_type_id']"
          filter-display="menu"
          sort-field="date"
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
              <span class="p-input-icon-left mx-2">
                <i class="pi pi-search mx-2" />
                <InputText
                  v-model="filters1['global'].value"
                  placeholder="Keyword Search"
                />
              </span>
            </div>
          </template>
          <template #empty>
            No Events.
          </template>
          <Column
            field="title"
            filter-field="title"
            header="Title"
            :sortable="true"
            style="width: 60%"
          />
          <Column
            field="event_type_id"
            filter-field="event_type_id"
            header="Type"
            :sortable="true"
            style="width: 20%"
          >
            <template #body="slotProps">
              {{ typeName(slotProps.data.event_type_id) }}
            </template>
          </Column>
          <Column
            field="date"
            filter-field="date"
            header="Performance Date"
            :sortable="true"
            style="width: 20%"
          >
            <template #body="slotProps">
              {{ formatTime(slotProps) }}
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </Container>
</template>

<script>
import {FilterMatchMode, FilterOperator} from 'primevue/api';
import moment from 'moment';
import { useStore } from 'vuex';
export default {
    name: 'EventList',
    props: ['events'],
    data() {
        return {
            searchParams: {},
            dontShowCompleted: false,
            filters1: null,
            band: {}
        }
    },
    computed: {
        gigs() {
            let upcomingEvents = this.events.filter(o => this.$moment(o.date, 'YYYY-MM-DD').isBetween(this.$moment().subtract(6, 'months'), this.$moment(), undefined, '[]'));

            const chartData = {
                labels: [],
                datasets: [{
                    label: 'Events For the Month',
                    backgroundColor: '#42A5F5',
                    data: []
                }]
            }
            const tempData = {};
            upcomingEvents.forEach(event => {
                const parsedDate = this.$moment(event.date);

                if (tempData[parsedDate.format('MMMM')]) {
                    tempData[parsedDate.format('MMMM')] += 1
                } else {
                    tempData[parsedDate.format('MMMM')] = 1
                }

            })

            for (let i in tempData) {
                chartData.labels.push(i)
                chartData.datasets[0].data.push(tempData[i])
            }

            return chartData
        }
    },
    created() {
        this.initFilters1();
        this.searchParams = this.$qs.parse(location.search.slice(1));
        this.store = useStore();
    },
    methods: {
        formatTime(row) {
            return moment(row.data.date).format('YYYY-MM-DD H:mm A');
        },
        gotoEvent(row) {
            this.$inertia.visit(this.$route("events.edit", row.data.key));
        },
        clearFilter1() {
            this.initFilters1();
        },
        initFilters1() {
            this.filters1 = {
                'global': {value: null, matchMode: FilterMatchMode.CONTAINS}
            }
        },
        typeName(id) {
            return this.store.getters['eventTypes/getEventTypeById'](id)?.name;
        }
    }
}
</script>

<style>

</style>
