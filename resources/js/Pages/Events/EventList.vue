<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-slate-700 overflow-hidden shadow-sm sm:rounded-lg pt-4">
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
          v-model:filters="filters"
          :value="filteredEvents"
          responsive-layout="scroll"
          selection-mode="single" 
          :paginator="true"
          :rows="10"
          :rows-per-page-options="[10,20,50]"
          :global-filter-fields="['title','date','venue_name','event_type_id']"
          filter-display="menu"
          sort-field="date"
          :sort-order="1"
          @row-select="gotoEvent"
        >
          <template #header>
            <div class="flex flex-row">
              <div class="hidden md:flex">
                <Button
                  class="p-button-outlined"
                  type="button"
                  icon="pi pi-filter-slash"
                  label="Clear"
                  @click="clearFilters"
                />
              </div>
              <span class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText
                  v-model="filters['global'].value"
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
                    v-model="showPreviousEvents"
                    class="float-right"
                  />
                </div>
              </div>
            </div>
          </template>
          <template #empty>
            No Events.
          </template>
          <Column
            field="title"
            filter-field="title"
            header="Name"
            :sortable="true"
          />
          <Column
            field="booking_name"
            filter-field="booking_name"
            header="Booking"
            :sortable="true"
          />
          <Column
            field="venue_name"
            filter-field="venue_name"
            header="Venue"
            :sortable="true"
          />
          <Column
            field="date"
            filter-field="date"
            header="Performance Date"
            :sortable="true"
          >
            <template #body="slotProps">
              {{ formatTime(slotProps.data) }}
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </Container>
</template>

<script>

import { DateTime, Interval } from 'luxon';

export default {
  name: 'EventList',
  props: ['events'],
  data() {
    return {
      searchParams: {},
      filters: null,
      showPreviousEvents: false,
      band: {}
    }
  },
  computed: {
    gigs() {
      const now = DateTime.now();
      let upcomingEvents = this.events.filter(o => {
        const eventDate = DateTime.fromISO(o.date);
        return Interval.fromDateTimes(now.minus({ months: 6 }), now).contains(eventDate);
      });
      
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
        const parsedDate = DateTime.fromISO(event.date);
        const month = parsedDate.toFormat('MMMM');
        tempData[month] = (tempData[month] || 0) + 1;
      })

      for (let month in tempData) {
        chartData.labels.push(month);
        chartData.datasets[0].data.push(tempData[month]);
      }
      return chartData;
    },
    filteredEvents() {
      const currentDate = DateTime.now();
      return this.events.filter(event => {
        const eventDate = DateTime.fromISO(event.date);
        if (this.showPreviousEvents) {
          return eventDate <= currentDate;
        } else {
          return eventDate > currentDate;
        }
      });
    }
  },
  created() {
    this.initFilters();
    this.searchParams = this.$qs.parse(location.search.slice(1));
  },
  methods: {
    formatTime(event) {
      return DateTime.fromISO(event.date + 'T' + event.time).toFormat('yyyy-MM-dd hh:mm a');
    },
    gotoEvent(event) {
      this.$inertia.visit(route('Booking Events', {band: event.data.band_id, booking: event.data.booking_id}));
    },
    clearFilters() {
      this.initFilters();
      this.showPreviousEvents = false;
    },
    initFilters() {
      this.filters = {
        'global': {value: null},
      }
    }          
  }
}
</script>

<style>
</style>