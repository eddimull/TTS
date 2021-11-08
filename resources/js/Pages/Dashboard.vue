<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Dashboard
      </h2>
    </template>
    <!-- <div class="flex-1 max-w-5xl p-16">
      <div class="grid grid cols-2 grid-rows-3 gap-4 grid-flow-row-dense">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <h4>Upcoming Events</h4>
            <ul>
              <li
                v-for="event in events"
                :key="event.id"
              >
                {{ event.event_name }} - {{ event.venue_name }} - <strong>{{ formatDate(event.event_time) }}</strong>
              </li>
            </ul>
          </div>
          <div class="p-6 bg-gray overflow-hidden shadow-sm sm:rounded-lg">
            <h4>Stats</h4>
            <p>Mileage for the year: {{ stats.miles }}</p>
          </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
          <Chart
            type="bar"
            :data="basicData"
          />
        </div>
      </div>
    </div> -->    
    <section>
      <div class="skew skew-top mr-for-radius">
        <svg
          class="h-8 md:h-12 lg:h-20 w-full text-gray-50"
          viewBox="0 0 10 10"
          preserveAspectRatio="none"
        >
          <polygon
            fill="currentColor"
            points="0 0 10 10 0 10"
          />
        </svg>
      </div>
      <div class="skew skew-top ml-for-radius">
        <svg
          class="h-8 md:h-12 lg:h-20 w-full text-gray-50"
          viewBox="0 0 10 10"
          preserveAspectRatio="none"
        >
          <polygon
            fill="currentColor"
            points="0 10 10 0 10 10"
          />
        </svg>
      </div>
      <div class="py-20 bg-gray-50 radius-for-skewed">
        <div class="container mx-auto px-4">
          <div class="flex flex-wrap items-center">
            <div class="w-full lg:w-1/2 mb-12 lg:mb-0">
              <div class="max-w-md">
                <span class="text-green-600 font-bold">TTS.band</span>
                <h2 class="mb-2 text-4xl lg:text-5xl font-bold font-heading">
                  Make an agent irrelevant
                </h2>
                <div class="max-w-xs">
                  <p class="text-gray-500 leading-loose">
                    All you need for management of your group whether it's money, contracts, or music, this will keep you covered
                  </p>
                </div>
              </div>
            </div>
            <div class="w-full lg:w-1/2">
              <div class="mb-12 lg:mb-8 flex flex-wrap items-start">
                <span class="mb-4 lg:mb-0 lg:mr-6 inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded text-green-600 text-2xl font-bold">1</span>
                <div class="w-full lg:w-3/4">
                  <h3 class="mb-4 text-2xl font-bold font-heading">
                    Create or join a band
                  </h3>
                  <p class="text-gray-500 leading-loose">
                    As a band owner you have a selection of tools to identify your group, membership management, and integration.
                  </p>
                </div>
              </div>
              <div class="mb-12 lg:mb-8 flex flex-wrap items-start">
                <span class="mb-4 lg:mb-0 lg:mr-6 inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded text-green-600 text-2xl font-bold">2</span>
                <div class="w-full lg:w-3/4">
                  <h3 class="mb-4 text-2xl font-bold font-heading">
                    Automated Contracts
                  </h3>
                  <p class="text-gray-500 leading-loose">
                    Auatomate your booking process by drafting up proposals and keep track of everything in one place
                  </p>
                </div>
              </div>
              <div class="flex flex-wrap items-start">
                <span class="mb-4 lg:mb-0 lg:mr-6 inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded text-green-600 text-2xl font-bold">3</span>
                <div class="w-full lg:w-3/4">
                  <h3 class="mb-4 text-2xl font-bold font-heading">
                    Organize your band
                  </h3>
                  <p class="text-gray-500 leading-loose">
                    Keep track of finances, music, and your gigs in one place.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="skew skew-bottom mr-for-radius">
        <svg
          class="h-8 md:h-12 lg:h-20 w-full text-gray-50"
          viewBox="0 0 10 10"
          preserveAspectRatio="none"
        >
          <polygon
            fill="currentColor"
            points="0 0 10 0 0 10"
          />
        </svg>
      </div>
      <div class="skew skew-bottom ml-for-radius">
        <svg
          class="h-8 md:h-12 lg:h-20 w-full text-gray-50"
          viewBox="0 0 10 10"
          preserveAspectRatio="none"
        >
          <polygon
            fill="currentColor"
            points="0 0 10 0 10 10"
          />
        </svg>
      </div>
    </section>
  </breeze-authenticated-layout>
</template>

<script>

    import FullCalendar from '@fullcalendar/vue3'
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import moment from 'moment';
    export default {
        components: {
            BreezeAuthenticatedLayout,
            FullCalendar
        },
        props:['events','stats'],
        data() {
          return {
            products: null,
            lineData: {
              labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
              datasets: [
                {
                  label: 'Revenue',
                  data: [65, 59, 80, 81, 56, 55, 40],
                  fill: false,
                  backgroundColor: '#2f4860',
                  borderColor: '#2f4860',
                  tension: 0.4
                },
                {
                  label: 'Sales',
                  data: [28, 48, 40, 19, 86, 27, 90],
                  fill: false,
                  backgroundColor: '#00bb7e',
                  borderColor: '#00bb7e',
                  tension: 0.4
                }
              ]
            },
            items: [
                      {label: 'Add New', icon: 'pi pi-fw pi-plus'},
                      {label: 'Remove', icon: 'pi pi-fw pi-minus'}
                  ]
          }
        },
        methods:{
            formatDate:(date)=>{

                return moment(String(date)).format('MM/DD/YYYY')
            }
        }
    }
</script>
<style scoped>
.card{
    background-color: var(--surface-card);
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-radius: 12px;
    box-shadow: 0 3px 5px rgba(0,0,0,.02),0 0 2px rgba(0,0,0,.05),0 1px 4px rgba(0,0,0,.08)!important;
}
</style>
