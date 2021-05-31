<template>
    <breeze-guest-layout>
        <transition name="fade">
            <canvas v-if="show" class="fixed inset-0 transition-opacity" id="confettiCanvas"></canvas>
        </transition>
        <div class="md:container md:mx-auto">
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
                </div>
            </div>
             
        </div>
    </breeze-guest-layout>
</template>

<script>
    import BreezeGuestLayout from '@/Layouts/Guest'
    import moment from 'moment';
    import ConfettiGenerator from "confetti-js";

    export default {
        props:['proposal','event_typtes'],
        components: {
            BreezeGuestLayout,
        },
        data(){
            return{
                person:'',
                show:true
            }

        },
        mounted(){
            
        },
        created(){
            this.$swal.fire({
                    title: "Proposal Accepted!",
                    text: "You should receive an official contract shortly",
                    icon: "success",
                }).then(()=>{
                    var confettiSettings = { target: 'confettiCanvas' };
                    var confetti = new ConfettiGenerator(confettiSettings);
                    confetti.render();
                    setTimeout(()=>{
                        this.show = false;
                    },5000)
                })
                

            // this.$confetti.start();

            // setTimeout(()=>{
            //     this.$confetti.stop();
            // },5000)
        },
        methods:{
            savePerson(){
                this.showIntro = false;
            },
            formatDate(date){
                return moment(date).format('LLLL');
            }
        }
    }
</script>
<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity .5s;
}
.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  opacity: 0;
}
</style>
