<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Colorways
            </h2>
        </template>
        <card-modal ref="modalName">
            <template v-slot:header>
                <h1>Add Colorway</h1>
            </template>

            <template v-slot:body>
                Pictures:
                Title:
                Hashtags:

            </template>

            <template v-slot:footer>
                <div>
                <button @click="$refs.modalName.closeModal()">Cancel</button>
                <button @click="$refs.modalName.closeModal()">Save</button>
                </div>
            </template>
         </card-modal>
        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <h4>Band Name</h4>
                   
                    <div class="grid grid-cols-3 gap-4">
                        <card :title="'Rock Star'" :description="'rock your socks off'" :hashTags="['party','rockStar','80s']"></card>
                        <card :picture="'https://scontent-dfw5-2.xx.fbcdn.net/v/t1.6435-9/121157072_3555312407832603_6378580396635833956_n.jpg?_nc_cat=106&ccb=1-3&_nc_sid=174925&_nc_ohc=DEn4CBKJz2UAX9xUANE&_nc_ht=scontent-dfw5-2.xx&oh=5212e61e18bb5212991c26bdd5491953&oe=60AD86BB'" title="Pinks" description="A fantastic pink starburst" :hashTags="['patrick','chest']"></card>
                        <card></card>
                        <card></card>
                        <div class="inline-block">  
                            <!--Card 1-->
                            <div class="max-w-sm rounded overflow-hidden shadow-lg">
                            <img class="w-full" src="https://scontent-dfw5-2.xx.fbcdn.net/v/t1.6435-9/121157072_3555312407832603_6378580396635833956_n.jpg?_nc_cat=106&ccb=1-3&_nc_sid=174925&_nc_ohc=DEn4CBKJz2UAX9xUANE&_nc_ht=scontent-dfw5-2.xx&oh=5212e61e18bb5212991c26bdd5491953&oe=60AD86BB" alt="Mountain">
                            <div class="px-6 py-4">
                                <div class="font-bold text-xl mb-2">Pinks</div>
                                <p class="text-gray-700 text-base">
                                    Look at that pink star burst bustin' out
                                </p>
                            </div>
                            <div class="px-6 pt-4 pb-2">
                                <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">#pink</span>
                                <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">#patrick</span>
                                <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">#chest</span>
                            </div>
                            </div>
                        </div>

                        <div v-on:click="$refs.modalName.openModal()" class="cursor-pointer transition-colors flex content-center justify-center max-w-sm rounded overflow-hidden shadow-lg border-2 hover:bg-green-100">
                            <div class="flex flex-wrap content-center justify-center">
                                <div>
                                Create new
                                </div>
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'

    export default {
        props:['colors','successMessage'],
        components: {
            BreezeAuthenticatedLayout,
            
        },
        data(){
            return{
                showModal:false
            }
        },
        methods:{
            toggleModal(){
                console.log('togglin');
                this.showModal = !this.showModal
            }
        }
    }
</script>
