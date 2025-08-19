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
                v-model="showPreviousEvents"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
              >
              <span>Show Previous Events</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <!-- Upcoming Events Sidebar -->
        <div class="xl:col-span-1">
          <div class="bg-white dark:bg-slate-700 rounded-lg shadow-sm p-4 sticky top-4">
            <Upcoming :events="events" />
          </div>
        </div>

        <!-- Events Table -->
        <div class="xl:col-span-3">
          <div class="bg-white dark:bg-slate-700 rounded-lg shadow-sm overflow-hidden">
            <DataTable
              v-model:filters="filters"
              :value="filteredEvents"
              responsive-layout="scroll"
              selection-mode="single"
              :paginator="true"
              :rows="10"
              :rows-per-page-options="[10, 20, 50]"
              :global-filter-fields="[
                'title',
                'date',
                'venue_name',
                'event_type_id',
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
                      {{ showPreviousEvents ? 'Previous Events' : 'Event List' }}
                    </h2>
                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">
                      {{ filteredEvents.length }}
                    </span>
                  </div>
                  
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <div class="relative">
                      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg
                          class="h-5 w-5 text-gray-400"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                          />
                        </svg>
                      </div>
                      <InputText
                        v-model="filters['global'].value"
                        placeholder="Search events..."
                        class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
                filter-field="title"
                header="Event Name"
                :sortable="true"
                class="font-medium"
              >
                <template #body="slotProps">
                  <div class="font-medium text-gray-900 dark:text-white">
                    {{ slotProps.data.title }}
                  </div>
                </template>
              </Column>
              
              <Column
                field="booking_name"
                filter-field="booking_name"
                header="Booking"
                :sortable="true"
              >
                <template #body="slotProps">
                  <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">
                    {{ slotProps.data.booking_name }}
                  </span>
                </template>
              </Column>
              
              <Column
                field="venue_name"
                filter-field="venue_name"
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
              </Column>
              
              <Column
                field="date"
                filter-field="date"
                header="Performance Date"
                :sortable="true"
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
              </Column>
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

export default {
    name: "EventList",
    components: {
        Upcoming,
    },
    props: ["events"],
    data() {
        return {
            searchParams: {},
            filters: null,
            showPreviousEvents: false,
            band: {},
        };
    },
    computed: {
        gigs() {
            const now = DateTime.now();
            let upcomingEvents = this.events.filter((o) => {
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
            const currentDate = DateTime.now().minus({ days: 2 });
            return this.events.filter((event) => {
                const eventDate = DateTime.fromISO(event.date);
                if (this.showPreviousEvents) {
                    return eventDate <= currentDate;
                } else {
                    return eventDate > currentDate;
                }
            });
        },
    },
    created() {
        this.initFilters();
        this.searchParams = this.$qs.parse(location.search.slice(1));
    },
    methods: {
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
        clearFilters() {
            this.initFilters();
            this.showPreviousEvents = false;
        },
        initFilters() {
            this.filters = {
                global: { value: null },
            };
        },
    },
};
</script>
