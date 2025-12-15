<template>
  <breeze-guest-layout>
    <div
      v-if="showIntro"
      class="md:container md:mx-auto"
    >
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex flex-col bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
          <div class="flex my-3">
            Hello
          </div>
          <div class="flex my-3">
            Who are we speaking with today?
          </div>
          <div @keyup.enter="savePerson">
            <div class="flex my-3">
              <input
                v-model="person"
                class="w-full"
                type="text"
              >
            </div>
            <div class="flex my-3 justify-center">
              <Button
                type="button"
                :disabled="person === ''"
                label="Submit"
                icon="pi pi-user"
                icon-pos="right"
                @click="savePerson"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
    <div
      v-else
      class="md:container md:mx-auto"
    >
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Details for {{ proposal.name }}
      </h2>
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
          <ul>                        
            <li>Event Type: {{ proposal.event_type.name }}</li>
            <li>Band: {{ proposal.band.name }}</li>
            <li>When: {{ formatDate(proposal.date) }} </li>
            <li>Where: {{ proposal.location ?? 'TBD' }} </li>
            <li>Price: ${{ parseFloat(proposal.price).toFixed(2) }} </li>
            <li>How long: {{ proposal.hours }} hours </li>
            <li
              v-if="proposal.client_notes"
              class="mt-4"
            >
              Notes: {{ proposal.client_notes }}
            </li>
          </ul>
          <div class="my-5 flex justify-center">
            <Button
              v-if="!loading"
              :disabled="loading"
              :label="loading ? 'Submitting...' : 'Accept Proposal'"
              icon="pi pi-check"
              icon-pos="right"
              @click="acceptProposal()"
            />

            <span v-if="loading">
              <ProgressSpinner />
            </span>
          </div>
        </div>
      </div>
    </div>
  </breeze-guest-layout>
</template>

<script>
    import BreezeGuestLayout from '@/Layouts/Guest'
    import { DateTime } from 'luxon';
    import Button from 'primevue/button';
    import ProgressSpinner from 'primevue/progressspinner';

    export default {
        components: {
            BreezeGuestLayout,
            Button,
            ProgressSpinner
        },
        props:['proposal','event_typtes'],
        data(){
            return{
                showIntro:true,
                person:'',
                loading:false
            }

        },
        methods:{
            savePerson(){
                this.showIntro = false;
            },
            formatDate(date){
                return DateTime.fromISO(date).toLocaleString(DateTime.DATETIME_HUGE);
            },
            acceptProposal()
            {
                this.loading = true;
                this.$inertia.post('/proposals/' + this.proposal.key + '/accept',{'person':this.person});
            }
        }
    }
</script>
