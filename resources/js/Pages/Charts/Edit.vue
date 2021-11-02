<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Chart
      </h2>
    </template>
    
    <Dialog
      v-model:visible="showUploadModal"
      :header="selectedUpload.displayName"
      :modal="true"
      class="w-full max-w-md m-auto"
    >
      <div class="p-field" />

      <div class="p-fluid p-formgrid p-grid">
        <div class="p-field p-col my-4">
          <label for="name">Upload Name</label>
          <InputText
            id="name"
            v-model="selectedUpload.displayName"
            type="text"
          />
        </div>
        <div class="p-field p-col my-4">
          <label for="name">Preview</label>
          <div class="flex">
            <div class="max-w-md py-4 mx-auto">
              <Image
                v-if="selectedUpload.fileType.indexOf('image') !== -1"
                :src="selectedUpload.chart_id + '/chartDownload/' + selectedUpload.name"
                :alt="selectedUpload.name"
                preview
              />

              <pdf 
                v-else-if="selectedUpload.fileType.indexOf('pdf') !== -1"
                :src="selectedUpload.chart_id + '/chartDownload/' + selectedUpload.name"
              />
              <av-waveform
                v-else-if="selectedUpload.fileType.indexOf('audio') !== -1"
                :audio-src="selectedUpload.chart_id + '/chartDownload/' + selectedUpload.name"
              />
              <video
                v-else-if="selectedUpload.fileType.indexOf('video') !== -1"
                controls
              >
                <source :src="selectedUpload.chart_id + '/chartDownload/' + selectedUpload.name">
              </video>
              <div v-else>
                ???? unable to display file
              </div>
            </div>
          </div>
        </div>
        <div class="flex">
          <div class="max-w-md py-4 mx-auto">
            <a
              :href="selectedUpload.chart_id + '/chartDownload/' + selectedUpload.name"
              download
              class="bg-grey-light hover:bg-grey text-grey-darkest font-bold py-2 px-4 rounded inline-flex items-center"
            >
              <svg
                class="w-4 h-4 mr-2"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
              ><path d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z" /></svg>
              <span>Download</span>
            </a>
          </div>
        </div>
        <div class="p-field p-col my-4">
          <label for="Notes">Notes</label>
          <Textarea 
            id="Notes"
            v-model="selectedUpload.notes"
            rows="4"
          />
        </div>
      </div>
      <template #footer>
        <Button
          label="Delete"
          icon="pi pi-trash"
          class="p-button-text"
          @click="deleteUpload"
        />
        <Button
          label="Update"
          icon="pi pi-save"
          autofocus
          @click="updateUpload"
        />
      </template>
    </Dialog>
    <div class="bg-white shadow">
      <div class="w-24 md:w-auto py-6 px-4 sm:px-6 lg:px-8">
        <div>
          <div class="card mx-10">
            <div class="p-fluid p-formgrid p-grid">
              <div class="p-field p-col my-4">
                <label for="firstname">Title</label>
                <InputText
                  id="firstname"
                  v-model="chartData.title"
                  type="text"
                />
              </div>
              <div class="p-field p-col my-4">
                <label for="lastname">Composer</label>
                <InputText
                  id="lastname"
                  v-model="chartData.composer"
                  type="text"
                />
              </div>
              <div class="p-field p-col my-4">
                <label for="public">Public </label>
                
                <Checkbox
                  v-model="chartData.public"
                  name="public"
                  :binary="true"
                />
              </div>
              <div class="p-field p-col my-4">
                <label for="description">Notes/Description</label>
              
                <Editor
                  v-model="chartData.description"
                  name="description"
                  editor-style="height:320px"
                />
              </div>     
              <div class="flex">
                <div class="w-1/4 mx-4 flex-auto border-4 border-light-blue-500 border-opacity-75">
                  <Button
                    label="Delete Chart"
                    icon="pi pi-trash"
                    class="p-button-text"
                    @click="deleteChart"
                  />
                </div>
                <div class="w-1/4 mx-4 flex-auto border-4 border-light-blue-500 border-opacity-75">
                  <Button
                    label="Update Chart"
                    icon="pi pi-save"
                    @click="updateChart"
                  />
                </div>
              </div>      
            </div>
            <Divider type="dashed" />
            <Panel class="mt-4">
              <template #header>
                Sheet Music
              </template>
	
              <DataTable
                :value="sheetMusic"
                striped-rows
                row-hover
                responsive-layout="scroll"
                selection-mode="single"
                @row-click="selectUpload"
              >
                <Column
                  field="displayName"
                  header="Name"
                />
                <Column
                  field="name"
                  header="File Name"
                />
 
                <template #empty>
                  No Sheet Music found.
                </template>
              </DataTable>
              <Accordion>
                <AccordionTab header="upload">
                  <FileUpload
                    name="sheetMusic[]"              
                    :multiple="true"
                    :custom-upload="true"
                    @uploader="uploadChart"
                  >
                    <template #empty>
                      <p>Drag and drop files to here to upload.</p>
                    </template>
                  </FileUpload>
                </AccordionTab>
              </Accordion>
            </Panel>
            <Panel class="mt-4">
              <template #header>
                Recordings
              </template>
              <DataTable
                :value="recordings"
                striped-rows
                row-hover
                responsive-layout="scroll"
                selection-mode="single"
                @row-click="selectUpload"
              >
                <Column
                  field="displayName"
                  header="Name"
                />
                <Column
                  field="name"
                  header="File Name"
                />
                <template #empty>
                  No Recordings found.
                </template>
              </DataTable>
              <Accordion>
                <AccordionTab header="upload">
                  <FileUpload
                    name="recordings[]"
                    :url="chart.id + '/upload'"
                    :multiple="true"
                    :custom-upload="true"
                    @uploader="uploadMusic"
                  >
                    <template #empty>
                      <p>Drag and drop files to here to upload.</p>
                    </template>
                  </FileUpload>
                </AccordionTab>
              </Accordion>
            </Panel>
            <div />
          </div>
        </div>
      </div>
    </div>
  </breeze-authenticated-layout>
</template>


<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import FileUpload from 'primevue/fileupload';    
    import pdf from '@jbtje/vue3pdf'


    export default {
        components: {
            BreezeAuthenticatedLayout,
            FileUpload,
            pdf
        },
        props:{
            chart:{
                type:Object,
                default:()=>{return {}}
            }
        },
        data(){
            return{
                chartData:this.chart,
                sheetMusic:[],
                recordings:[],
                showUploadModal:false,
                selectedUpload:false
            }
        },
        created(){
            this.sheetMusic = this.chart.uploads.filter((upload)=>{
              if(upload.upload_type_id === 3)
              {
                return upload;
              }
            })

            this.recordings = this.chart.uploads.filter((upload)=>{
              if(upload.upload_type_id === 1 || upload.upload_type_id === 2)
              {
                return upload;
              }
            })

            this.chartData.public = this.chartData.public === 1;


            console.log(this.recordings);
        },
        methods:{
            selectUpload(upload)
            {
              console.log(upload);
              this.selectedUpload = upload.data;
              this.showUploadModal = true;
            },
            uploadChart(event)
            {
              this.upload(event,3)
            },
            uploadMusic(event){
              this.upload(event,1);
            },
            upload(event,type)
            {
              // console.log(event);
                this.$inertia.post(this.chartData.id + '/upload',{'type_id':type,'band_id':this.chartData.band_id,'files[]':event.files}).then(()=>{
                  window.location.reload();
                })
            },
            updateChart()
            {
               this.$inertia.post(this.chartData.id,{'title':this.chartData.title,'composer':this.chartData.composer,'public':this.chartData.public,'description':this.chartData.description},{
                 onSuccess:()=>{
                  //  window.location.reload();
                 }
               })
            },
            deleteChart()
            {
              this.$inertia.delete(this.chartData.id);
            },
            updateUpload()
            {
              this.$inertia.post(this.chartData.id + '/chartDownload/' + this.selectedUpload.id,{
                displayName: this.selectedUpload.displayName,
                notes:this.selectedUpload.notes ? this.selectedUpload.notes : ''

              })
            },
            deleteUpload()
            {
              this.$inertia.delete(this.chartData.id + '/chartDownload/' + this.selectedUpload.id,{
                onSuccess:()=>{
                   window.location.reload();
                  this.showUploadModal = false;
                  
                }
              });
            }
        }
    }
</script>
