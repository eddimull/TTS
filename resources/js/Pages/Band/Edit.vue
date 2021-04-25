<template>
    <breeze-authenticated-layout>
       
        <div class="w-full max-w-xs">
             <div class="mb-4">
                
                <div v-if="errors.name" class="alert alert-danger mt-4">
                    Errors:
                    <ul>
                        <li>{{ errors.name }}</li>
                    </ul>
                </div>
                <form :action="'/bands/' + band.id" method="PATCH" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4" @submit.prevent="updateBand">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Name</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Band Name" v-model="form.name">
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                            Update 
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'

    export default {
        props:['errors','band'],
        components: {
            BreezeAuthenticatedLayout,
        },
        data(){
            return{
                form:{
                    name:this.band.name
                }
            }
        },
        methods:{
            updateBand(){
                const bandID = this.band.id;
                this.$inertia.patch('/bands/' + bandID,this.form)
                    .then(()=>{
                        this.loading = false;
                    })
            }
        }
    }
</script>
