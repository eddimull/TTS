<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pt-4">
      <!-- Top Actions Bar -->
      <div class="bg-white dark:bg-slate-700 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            @click="$inertia.visit($route('events.create'))"
          >
            <svg
              class="w-5 h-5 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
              />
            </svg>
            Draft New Event
          </button>
          
          <div class="flex items-center space-x-4">
            <label class="flex items-center space-x-2 text-sm font-medium text-gray-700 dark:text-gray-300">
              <input
                id="switch"
                v-model="showAllEvents"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                @change="toggleAllEvents"
              >
              <span>Show All Events</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <!-- Upcoming Events Sidebar -->
        <div class="xl:col-span-1">
          <div class="bg-white dark:bg-slate-700 rounded-lg shadow-sm p-4 sticky top-4">
            <Upcoming :events="eventsArray" />
          </div>
        </div>

        <!-- Events Table -->
        <div class="xl:col-span-3">
          <div class="bg-white dark:bg-slate-700 rounded-lg shadow-sm overflow-hidden">
            <DataTable
              v-model:filters="filters"
              :value="filteredEvents"
              selection-mode="single"
              paginator
              :rows="10"
              :rows-per-page-options="[10, 20, 50, 100]"
              :global-filter-fields="[
                'title',
                'date',
                'venue_name',
              ]"
              filter-display="menu"
              sort-field="date"
              :sort-order="1"
              class="border-0"
              @row-select="gotoEvent"
            >
              <template #header>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-gray-50 dark:bg-slate-600 border-b border-gray-200 dark:border-slate-500">
                  <div class="flex items-center space-x-3">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ showAllEvents ? 'All Events' : 'Recent Events' }}
                    </h2>
                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">
                      {{ filteredEvents.length }}
                    </span>
                  </div>
                  
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <div class="relative">
                      <InputText
                        v-model="filters['global'].value"
                        placeholder="Search events..."
                        class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        @input="$event => filters['global'].value = $event.target.value"
                      />
                    </div>
                    
                    <Button
                      class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-600 border border-gray-300 dark:border-slate-500 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      type="button"
                      icon="pi pi-filter-slash"
                      label="Clear Filters"
                      @click="clearFilters"
                    />
                  </div>
                </div>
              </template>
              
              <template #empty>
                <div class="text-center py-12">
                  <svg
                    class="mx-auto h-12 w-12 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                  <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                    No events found
                  </h3>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ showPreviousEvents ? 'No previous events to display.' : 'Get started by creating a new event.' }}
                  </p>
                </div>
              </template>
              
              <Column
                field="title"
                header="Event Name"
                :sortable="true"
                class="font-medium"
              >
                <template #body="slotProps">
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ slotProps.data.title }}
                  </div>
                </template>
                <template #filter="{ filterModel, filterCallback }">
                  <InputText
                    v-model="filterModel.value"
                    type="text"
                    placeholder="Search by name"
                    @input="filterCallback()"
                  />
                </template>
              </Column>
              
              
              
              <Column
                field="venue_name"
                header="Venue"
                :sortable="true"
              >
                <template #body="slotProps">
                  <div class="flex items-center">
                    <svg
                      class="w-4 h-4 mr-2 text-gray-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                      />
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                    </svg>
                    <span class="text-gray-900 dark:text-white">{{ slotProps.data.venue_name }}</span>
                  </div>
                </template>
                <template #filter="{ filterModel, filterCallback }">
                  <MultiSelect
                    v-model="filterModel.value"
                    show-clear
                    filter
                    :options="venueOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Select Venues"
                    class="p-column-filter"
                    :max-selected-labels="1"
                    @change="filterCallback()"
                  >
                    <template #option="slotProps">
                      <span>{{ slotProps.option.label }}</span>
                    </template>
                  </MultiSelect>
                </template>
              </Column>
              
              <Column
                field="event_type_name"
                header="Event Type"
                :sortable="true"
              >
                <template #body="slotProps">
                  <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded-full">
                    {{ slotProps.data.event_type_name }}
                  </span>
                </template>
                <template #filter="{ filterModel, filterCallback }">
                  <MultiSelect
                    v-model="filterModel.value"
                    :options="eventTypeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Select Event Types"
                    class="p-column-filter"
                    :max-selected-labels="1"
                    @change="filterCallback()"
                  >
                    <template #option="slotProps">
                      <span>{{ slotProps.option.label }}</span>
                    </template>
                  </MultiSelect>
                </template>
              </Column>
              
              <Column
                field="dateObject"
                header="Date"
                :sortable="true"
                data-type="date"
              >
                <template #body="slotProps">
                  <div class="text-sm">
                    <div class="font-medium text-gray-900 dark:text-white">
                      {{ formatTime(slotProps.data) }}
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">
                      {{ getRelativeTime(slotProps.data.date) }}
                    </div>
                  </div>
                </template>
                <template #filter="{ filterModel, filterCallback }">
                  <DatePicker
                    v-model="filterModel.value"
                    date-format="yy-mm-dd"
                    placeholder="yyyy-mm-dd"
                    class="p-column-filter"
                    @date-select="filterCallback()"
                    @clear="filterCallback()"
                  />
                </template>
              </Column>
              
              <!-- Actions Column -->
              <Column
                header="Actions"
                :exportable="false"
                style="min-width: 8rem"
              >
                <template #body="slotProps">
                  <div class="flex gap-2">
                    <Button
                      icon="pi pi-history"
                      severity="secondary"
                      text
                      rounded
                      title="View History"
                      @click.stop="viewHistory(slotProps.data)"
                    />
                  </div>
                </template>
              </Column>
              
              <template #paginatorcontainer="{ first, last, page, pageCount, prevPageCallback, nextPageCallback, totalRecords }">
                <div class="flex items-center gap-4 border border-primary bg-transparent rounded-full w-full py-1 px-2 justify-between">
                  <Button
                    icon="pi pi-chevron-left"
                    rounded
                    text
                    :disabled="page === 0"
                    @click="prevPageCallback"
                  />
                  <div class="text-color font-medium">
                    <span class="hidden sm:block">Showing {{ first }} to {{ last }} of {{ totalRecords }}</span>
                    <span class="block sm:hidden">Page {{ page + 1 }} of {{ pageCount }}</span>
                  </div>
                  <Button
                    icon="pi pi-chevron-right"
                    rounded
                    text
                    :disabled="page === pageCount - 1"
                    @click="nextPageCallback"
                  />
                </div>
              </template>
            </DataTable>
          </div>
        </div>
      </div>
    </div>
  </Container>
</template>

<script>
import { DateTime, Interval } from "luxon";
import Upcoming from "./Components/Upcoming.vue";
import { FilterMatchMode } from '@primevue/core/api';
import DatePicker from 'primevue/datepicker';
import MultiSelect from "primevue/multiselect";
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

export default {
    name: "EventList",
    components: {
        Upcoming,
        MultiSelect,
        DatePicker,
        DataTable,
        Column,
        InputText,
        Button,
    },
    props: ["events","includeAll"],
    data() {
        return {
            searchParams: {},
            filters: null,
            showPreviousEvents: false,
            band: {},
            showAllEvents: false,
        };
    },
    computed: {
        eventsArray() {
            // Ensure events is always an array
            if (!this.events) return [];
            if (Array.isArray(this.events)) return this.events;
            // Handle if it's an object with a 'data' property (pagination)
            if (this.events.data && Array.isArray(this.events.data)) return this.events.data;
            // If it's an object (hash map), convert values to array
            if (typeof this.events === 'object') {
                return Object.values(this.events);
            }
            return [];
        },
        eventsWithDates() {
            return this.eventsArray.map(event => {
                return {
                    ...event,
                    dateObject: new Date(event.date)
                };
            });
        },
        gigs() {
            const now = DateTime.now();
            let upcomingEvents = this.eventsArray.filter((o) => {
                const eventDate = DateTime.fromISO(o.date);
                return Interval.fromDateTimes(
                    now.minus({ months: 6 }),
                    now
                ).contains(eventDate);
            });

            const chartData = {
                labels: [],
                datasets: [
                    {
                        label: "Events For the Month",
                        backgroundColor: "#42A5F5",
                        data: [],
                    },
                ],
            };
            const tempData = {};
            upcomingEvents.forEach((event) => {
                const parsedDate = DateTime.fromISO(event.date);
                const month = parsedDate.toFormat("MMMM");
                tempData[month] = (tempData[month] || 0) + 1;
            });

            for (let month in tempData) {
                chartData.labels.push(month);
                chartData.datasets[0].data.push(tempData[month]);
            }
            return chartData;
        },
        filteredEvents() {
          // Get events with date objects first
          const eventsWithDates = this.eventsWithDates;
          
          // When showing all events, just return all events
          if (this.showAllEvents) {
              return eventsWithDates;
          }
          
          // Otherwise, filter to show only upcoming events
          const currentDate = DateTime.now().minus({ days: 2 });
          return eventsWithDates.filter((event) => {
              const eventDate = DateTime.fromISO(event.date);
              return eventDate > currentDate;
          });
        },
        // Computed properties for dropdown options
        bookingOptions() {
            const bookings = [...new Set(this.eventsArray.map(event => event.booking_name))];
            return bookings.filter(Boolean).map(booking => ({ label: booking, value: booking }));
        },
        venueOptions() {
            const venues = [...new Set(this.eventsArray.map(event => event.venue_name))];
            return venues.filter(Boolean).sort((a, b) => a.localeCompare(b)).map(venue => ({ label: venue, value: venue }));
        },
        titleOptions() {
            const titles = [...new Set(this.eventsArray.map(event => event.title))];
            return titles.filter(Boolean).sort((a, b) => a.localeCompare(b)).map(title => ({ label: title, value: title }));
        },
        eventTypeOptions() {
            const eventTypes = [...new Set(this.eventsArray.map(event => event.event_type_name))];
            return eventTypes.filter(Boolean).map(eventType => ({ label: eventType, value: eventType }));
        }
    },
    watch: {
        includeAll: {
          immediate: true,
          handler(newVal) {
              this.showAllEvents = newVal || false;
          }
        },
    },
    created() {
        this.initFilters();
        this.searchParams = this.$qs.parse(location.search.slice(1));
    },
    methods: {
        toggleAllEvents() {
            this.$inertia.visit(route('events'), {
                data: { include_all: this.showAllEvents },
                preserveState: true,
                preserveScroll: true,
            });
        },
        formatTime(event) {
            return DateTime.fromISO(event.date + "T" + event.time).toFormat(
                "MMM dd, yyyy 'at' hh:mm a"
            );
        },
        getRelativeTime(date) {
            const eventDate = DateTime.fromISO(date);
            const now = DateTime.now();
            const diff = eventDate.diff(now, ['days', 'hours']).toObject();
            
            if (Math.abs(diff.days) < 1) {
                if (diff.hours > 0) {
                    return `In ${Math.round(diff.hours)} hours`;
                } else {
                    return `${Math.abs(Math.round(diff.hours))} hours ago`;
                }
            } else if (diff.days > 0) {
                return `In ${Math.round(diff.days)} days`;
            } else {
                return `${Math.abs(Math.round(diff.days))} days ago`;
            }
        },
        gotoEvent(event) {
            this.$inertia.visit(
                route("Booking Events", {
                    band: event.data.band_id,
                    booking: event.data.booking_id,
                })
            );
        },
        viewHistory(event) {
            this.$inertia.visit(route('events.history', event.key));
        },
        clearFilters() {
            this.initFilters();
        },
        initFilters() {
            this.filters = {
                global: { value: null, matchMode: FilterMatchMode.CONTAINS },
                title: { value: null, matchMode: FilterMatchMode.IN },
                event_type_name: { value: null, matchMode: FilterMatchMode.IN },
                venue_name: { value: null, matchMode: FilterMatchMode.IN },
                dateObject: { value: null, matchMode: FilterMatchMode.DATE_IS }
            };
        },
    },
};
</script>
