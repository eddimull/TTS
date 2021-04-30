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
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Page Name (URL)</label>
                    <input type="text" v-on:input="filter" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Band_Name"  pattern="([a-zA-z0-9\-_]+)" v-model="form.site_name">
                            <span v-if="urlWarn" class="text-red-700">Letters, numbers, _, +, and - are the only characters allowed</span>
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
                urlWarn:false,
                form:{
                    name:this.band.name,
                    site_name:this.band.site_name
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
            },
            filter()
            {
                if(this.form.site_name.length > 0)
                {

                    let message = this.form.site_name;
                    let urlsafeName = message.replace(/[^a-zA-Z0-9\-_]/gm,"")                    
                    this.urlWarn = urlsafeName !== this.form.site_name 
                    this.form.site_name = urlsafeName;

                }   
            }
        },
        watch:{
            form:{
                deep:true,
                handler()
                {

                }
            }
        }
    }
</script>
