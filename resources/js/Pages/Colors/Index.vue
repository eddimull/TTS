<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Colorways
            </h2>
        </template>
        <card-modal @save="saveColor" ref="modalName">
            <template v-slot:header>
                <h1>Add Colorway</h1>
            </template>

            <template v-slot:body>
                    <UploadImages @change="handleImages" />
                    Title:  <div>
                            <input type="text" v-model="form.color_title"/>
                            </div>
                    Description:  <div>
                            <textarea class="min-w-full" v-model="form.colorway_description" placeholder=""></textarea>
                            </div>
                    Hashtags: <div>
                                <!-- <smart-tagz :on-changed="tagsUpdate" :allow-duplicates="false"  :tagsData="defaultTags" inputPlaceholder="Describe Attire" /> -->
                            </div>
                
            </template>

            <template v-slot:footer>
                <div>
                <!-- <button @click="$refs.modalName.closeModal()">Cancel</button>
                <button @click="$refs.modalName.closeModal()">Save</button> -->
                </div>
            </template>
         </card-modal>
        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-for="band in bands" :key="band.name" class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <h4>{{band.name}}</h4>
                    {{selectedTags}}
                    <tags-input element-id="tags"
    v-model="selectedTags" 
    :existing-tags="[
        { id: 1, name: 'Web Development' },
        { id: 2, name: 'PHP' },
        { id: 3, name: 'JavaScript' },
    ]"
    id-field="id"
    text-field="name"></tags-input>
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="(color,id) in getColors(band.id)" :key="id">
                            <card :title="color.color_title" v-on:click="setColor(color)" :description="color.colorway_description" :picture="'https://bandapp.s3.us-east-2.amazonaws.com/' + color.photos[0]['photo_name']" :hashTags="color.color_tags.split(',')"/>
                        </div>    
                        
                        <div v-on:click="$refs.modalName.openModal(); setBandID(band.id)" class="h-56 m-10 cursor-pointer transition-colors flex content-center justify-center max-w-sm rounded overflow-hidden shadow-lg border-2 hover:bg-green-100">
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
    import UploadImages from "vue-upload-drop-images"
    import { SmartTagz } from "smart-tagz";
    import "smart-tagz/dist/smart-tagz.css";
    import VoerroTagsInput from '@voerro/vue-tagsinput';
    import '@voerro/vue-tagsinput/dist/style.css';
 
     
    export default {
        props:['bands','colors','successMessage'],
        components: {
            BreezeAuthenticatedLayout,
            UploadImages,
            SmartTagz,
            'tags-input':VoerroTagsInput
        },
        data(){
            return{
                showModal:false,
                selectedTags: [],
                form: this.$inertia.form({
                    '_method': 'PUT',
                    color_id:'',
                    color_title:'',
                    color_tags:'',
                    color_photos:[],
                    colorway_description:'',
                    band_id:''
                }),
                uploadedFiles:null,
                defaultTags:['test','test123']
            }
        },
        methods:{
            toggleModal(){
                console.log('togglin');
                this.showModal = !this.showModal
            },
            handleImages(files){
                // this.uploadedFiles = files;
                if(!files.target)
                {
                    // console.log(files);
                    this.form.color_photos = []
                    files.forEach(file=>this.form.color_photos.push(file));
                }
                // console.log(this.form.color_photos);
                
            },
            setColor(color)
            {
                // alert('setting color');
                // this.defaultTags = color.color_tags.split(',');

                const tags = [];

                color.color_tags.split(',').forEach((color,index)=>{
                    tags.push({
                        key:index,
                        value:color
                    })
                })
                this.defaultTags = ['abc','def'];
                this.form.color_id = color.id;
                this.form.color_title = color.color_title;
                this.form.color_tags = tags;
                this.form.colorway_description = color.colorway_description;
                this.form.color_photos = color.photos;
                this.$refs.modalName.openModal();
            },
            updatePreview(file)
            {
                console.log(file)
            },
            setBandID(id)
            {
                this.form.band_id = id
            },
            tagsUpdate(tags)
            {
                this.form.color_tags = tags;
            },
            getColors(band_id)
            {
                const colors = this.colors.filter(color=>color.band_id == band_id);
                return colors;
            },
            saveColor()
            {
               
               
                this.form.post('/colors',this.form,{preserveState:true})

      
                
            }

        }
    }
</script>
