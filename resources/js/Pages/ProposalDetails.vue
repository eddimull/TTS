<template>
    <breeze-guest-layout>
        <div v-if="showIntro" class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex flex-col bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <div class="flex my-3">Hello</div>
                    <div class="flex my-3">Who are we speaking with today?</div>
                    <div class="flex my-3">
                        <input class="w-full" v-model="person" type="text"/>
                    </div>
                    <div class="flex my-3 justify-center">
                        <Button @click="savePerson" :disabled="person === ''" label="Submit" icon="pi pi-user" iconPos="right" />
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="md:container md:mx-auto">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Details for {{proposal.name}}
            </h2>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    Details
                    <ul>                        
                        <li>Event Type: {{proposal.event_type.name}}</li>
                        <li>Band: {{proposal.band.name}}</li>
                        <li>When: {{formatDate(proposal.date)}} </li>
                        <li>Where: {{proposal.location ?? 'TBD'}} </li>
                        <li>Price: {{proposal.price}} </li>
                        <li>How long: {{proposal.hours}} hours </li>
                    </ul>
                    <div class="my-5 flex justify-center">
                        <Button v-if="!loading" @click="acceptProposal()" :disabled="loading" :label="loading ? 'Submitting...' : 'Accept Proposal'" icon="pi pi-check" iconPos="right"></Button>

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
    import moment from 'moment';
    import Button from 'primevue/button';
    import ProgressSpinner from 'primevue/progressspinner';

    export default {
        props:['proposal','event_typtes'],
        components: {
            BreezeGuestLayout,
            Button,
            ProgressSpinner
        },
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
                return moment(date).format('LLLL');
            },
            acceptProposal()
            {
                this.loading = true;
                this.$inertia.post('/proposals/' + this.proposal.key + '/accept',{'person':this.person});
            }
        }
    }
</script>
