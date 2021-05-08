<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Colorways
            </h2>
        </template>
        <card-modal @save="saveColor" ref="modalName" v-if="showModal" @closing="toggleModal()" :saveText="updatingColor ? 'Update':'Save'" :showDelete="updatingColor" @delete="deleteColor">
            <template v-slot:header>
                <h1>Add Colorway</h1>
            </template>

            <template v-slot:body>
                    <div class="py-4">
                        <UploadImages @change="handleImages" />
                    </div>
                    <div class="grid grid-cols-3 gap-4" v-if="uploadedImages.length > 0">
                        Uploaded Images:
                        <div v-for="(uploadedImage,index) in uploadedImages" :key="index">
                            <img :src="'https://bandapp.s3.us-east-2.amazonaws.com/' + uploadedImage['photo_name']"/>
                        </div>
                    </div>
                    Title:  <div>
                            <input type="text" v-model="form.color_title"/>
                            </div>
                    Description:  <div>
                            <textarea class="min-w-full" v-model="form.colorway_description" placeholder=""></textarea>
                            </div>
                    Hashtags: <div>
                                <!-- <smart-tagz :on-changed="tagsUpdate" :allow-duplicates="false"  :tagsData="defaultTags" inputPlaceholder="Describe Attire" /> -->
                                <tags-input element-id="tags"
                                @tag-added="addTag"
                                @tag-removed="removeTag"
                                :value="tags" 
                                :id-field="'id'"
                                :text-field="'value'"></tags-input>
                            </div>
            </template>
         </card-modal>
        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-for="band in bands" :key="band.name" class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <h4>{{band.name}}</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div v-for="(color,id) in getColors(band.id)" :key="id">
                            <card :title="color.color_title" v-on:click="setColor(color); setUpdating(true)" :description="color.colorway_description" :picture="color.photos.length > 0 ? 'https://bandapp.s3.us-east-2.amazonaws.com/' + color.photos[0]['photo_name'] : false" :hashTags="color.color_tags.split(',')"/>
                        </div>    
                        
                        <div v-on:click="toggleModal(); setBandID(band.id); clearColor(); setUpdating(false)" class="h-56 m-10 cursor-pointer transition-colors flex content-center justify-center max-w-sm rounded overflow-hidden shadow-lg border-2 hover:bg-green-100">
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
                tags:[],
                tagsSeparate:[],
                form: this.$inertia.form({
                    '_method': 'PUT',
                    color_id:'',
                    color_title:'',
                    color_tags:'',
                    color_photos:[],
                    colorway_description:'',
                    band_id:'',
                    onSuccess:()=>{
                        this.$refs.modalName.closeModal()
                    }
                }),
            }
        },
        methods:{
            toggleModal(){
                console.log('togglin');
                this.showModal = !this.showModal
            },
            handleImages(files){
                if(!files.target)
                {
                    this.form.color_photos = []
                    files.forEach(file=>this.form.color_photos.push(file));
                }
                
            },
            clearColor()
            {
                this.form.color_id = '';
                this.form.color_title = '';
                this.tags = [];
                this.tagsSeparate = [];
                this.form.color_tags = '';
                this.form.colorway_description = '';
                this.form.color_photos = [];
                this.uploadedImages = [];
            },
            setColor(color)
            {
                const tags = [];
                color.color_tags.split(',').forEach((color,index)=>{
                    tags.push({
                        key:index,
                        value:color
                    })
                })
                
                this.form.color_id = color.id;
                this.form.color_title = color.color_title;
                this.tags = tags;
                this.tagsSeparate = [];
                this.form.color_tags = this.tagsSeparate.join();
                this.form.colorway_description = color.colorway_description;
                this.form.color_photos = color.photos;

                this.uploadedImages = color.photos;
                this.toggleModal()
            },
            updatePreview(file)
            {
                console.log(file)
            },
            setBandID(id)
            {
                this.form.band_id = id
            },
            setUpdating(bool)
            {
                console.log('set updating to:'+bool);
                this.updatingColor = bool;
            },
            addTag(newTag)
            {
                this.tagsSeparate.push(newTag.value);
                this.formatTags();
            },
            removeTag(oldTag)
            {
                const indexToRemove = this.tagsSeparate.indexOf(oldTag.value);
                this.tagsSeparate.splice(indexToRemove,1);
                this.formatTags();
            },
            formatTags()
            {

                if(this.modifying)
                {
                    console.log('formatting tags');
                    this.form.color_tags = this.tagsSeparate.join()
                }

            },
            getColors(band_id)
            {
                const colors = this.colors.filter(color=>color.band_id == band_id);
                return colors;
            },
            saveColor()
            {
               if(this.updatingColor)
               {
                   console.log('update')

                   this.$inertia.patch('/colors/' + this.form.color_id,{
                        data:{
                            color_title : this.form.color_title,
                            color_tags : this.tagsSeparate.join(),
                            colorway_description : this.form.colorway_description,
                            color_photos : this.form.color_photos
                        },
                        
                    },{onFinish:()=>{
                            console.log('should close modal');
                            this.$refs.modalName.closeModal()
                        }
                    });
               }
               else
               {
                   console.log('create new')
                // this.form.post('/colors',this.form,{preserveState:true})

                this.$inertia.post('/colors/',{
                    data:{
                        color_title : this.form.color_title,
                        color_tags : this.tagsSeparate.join(),
                        colorway_description : this.form.colorway_description,
                        color_photos : this.form.color_photos,
                        band_id:this.form.band_id,
                    }
                
                },{
                    onSuccess:()=>{
                        this.$refs.modalName.closeModal()
                    }
                });
               
               }
            },
            deleteColor()
            {
                console.log('delete color');
                this.$inertia.delete('/colors/' + this.form.color_id,{
                    onSuccess:()=>{
                        this.$refs.modalName.closeModal()
                    }
                });
            }

        }
    }
</script>
