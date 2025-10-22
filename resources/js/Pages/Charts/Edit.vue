<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2
        class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight"
      >
        <Link
          :href="route('charts.show', chartData.id)"
          class="hover:text-blue-600"
        > 
          {{ chartData.title }}
        </Link> :: Edit
      </h2>
    </template>

    <Dialog
      v-model:visible="showUploadModal"
      :header="selectedUpload.displayName"
      :modal="true"
      class="w-full max-w-md mx-auto"
    >
      <div class="space-y-4">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col">
            <label
              for="name"
              class="mb-2"
            >Upload Name</label>
            <InputText
              id="name"
              v-model="selectedUpload.displayName"
              type="text"
            />
          </div>

          <div class="flex flex-col">
            <label
              for="name"
              class="mb-2"
            >Preview</label>
            <div class="flex">
              <div class="max-w-md py-4 mx-auto truncate">
                <Image
                  v-if="
                    selectedUpload.fileType.indexOf(
                      'image'
                    ) !== -1
                  "
                  :src="
                    selectedUpload.chart_id +
                      '/chartDownload/' +
                      selectedUpload.name
                  "
                  :alt="selectedUpload.name"
                  preview
                />

                <pdf
                  v-else-if="
                    selectedUpload.fileType.indexOf(
                      'pdf'
                    ) !== -1
                  "
                  :src="
                    selectedUpload.chart_id +
                      '/chartDownload/' +
                      selectedUpload.name
                  "
                  class="w-full"
                />

                <av-waveform
                  v-else-if="
                    selectedUpload.fileType.indexOf(
                      'audio'
                    ) !== -1
                  "
                  :audio-src="
                    selectedUpload.chart_id +
                      '/chartDownload/' +
                      selectedUpload.name
                  "
                />

                <video
                  v-else-if="
                    selectedUpload.fileType.indexOf(
                      'video'
                    ) !== -1
                  "
                  controls
                >
                  <source
                    :src="
                      selectedUpload.chart_id +
                        '/chartDownload/' +
                        selectedUpload.name
                    "
                  >
                </video>

                <div v-else>
                  Unable to display file
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-center py-4">
            <a
              :href="
                selectedUpload.chart_id +
                  '/chartDownload/' +
                  selectedUpload.name
              "
              download
              class="inline-flex items-center px-4 py-2 font-bold text-gray-800 bg-gray-200 rounded hover:bg-gray-300"
            >
              <svg
                class="w-4 h-4 mr-2"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
              >
                <path
                  d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z"
                />
              </svg>
              <span>Download</span>
            </a>
          </div>

          <div class="flex flex-col">
            <label
              for="Notes"
              class="mb-2"
            >Notes</label>
            <Textarea
              id="Notes"
              v-model="selectedUpload.notes"
              rows="4"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button
          label="Delete"
          icon="pi pi-trash"
          class="text-red-600 hover:text-red-700 hover:bg-red-50"
          @click="deleteUpload"
        />
        <Button
          label="Update"
          icon="pi pi-save"
          class="bg-blue-600 text-white hover:bg-blue-700"
          @click="updateUpload"
        />
      </template>
    </Dialog>
    <Container>
      <div class="min-w-min md:w-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col space-y-6">
          <!-- Form Fields -->
          <div class="grid grid-cols-1 gap-4">
            <div class="space-y-2">
              <label
                for="firstname"
                class="block text-sm font-medium"
              >Title</label>
              <InputText
                id="firstname"
                v-model="chartData.title"
                type="text"
                class="w-full"
              />
            </div>

            <div class="space-y-2">
              <label
                for="lastname"
                class="block text-sm font-medium"
              >Composer</label>
              <InputText
                id="lastname"
                v-model="chartData.composer"
                type="text"
                class="w-full"
              />
            </div>

            <div class="flex items-center space-x-2">
              <label
                for="public"
                class="text-sm font-medium"
              >Public</label>
              <Checkbox
                v-model="chartData.public"
                name="public"
                :binary="true"
              />
            </div>

            <div class="space-y-2">
              <label
                for="description"
                class="block text-sm font-medium"
              >Notes/Description</label>
              <Editor
                v-model="chartData.description"
                name="description"
                class="h-80"
              />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex space-x-4">
            <div class="flex-1">
              <Button
                label="Delete Chart"
                icon="pi pi-trash"
                class="w-full text-red-600 hover:text-red-700 hover:bg-red-50"
                @click="deleteChart"
              />
            </div>
            <div class="flex-1">
              <Button
                label="Update Chart"
                icon="pi pi-save"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white"
                @click="updateChart"
              />
            </div>
          </div>

          <Divider class="border-dashed" />

          <!-- Sheet Music Panel -->
          <Panel>
            <template #header>
              <span class="font-medium">Sheet Music</span>
            </template>

            <DataTable
              :value="sheetMusic"
              hover
              class="w-full"
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
                <div class="text-gray-500 py-4">
                  No Sheet Music found.
                </div>
              </template>
            </DataTable>

            <Accordion class="mt-4">
              <AccordionTab header="upload">
                <FileUpload
                  name="sheetMusic[]"
                  :multiple="true"
                  :custom-upload="true"
                  class="w-full"
                  @uploader="uploadChart"
                >
                  <template #empty>
                    <p
                      class="text-gray-500 text-center py-8"
                    >
                      Drag and drop files to here to
                      upload.
                    </p>
                  </template>
                </FileUpload>
              </AccordionTab>
            </Accordion>
          </Panel>

          <!-- Recordings Panel -->
          <Panel>
            <template #header>
              <span class="font-medium">Recordings</span>
            </template>

            <DataTable
              :value="recordings"
              hover
              class="w-full"
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
                <div class="text-gray-500 py-4">
                  No Recordings found.
                </div>
              </template>
            </DataTable>

            <Accordion class="mt-4">
              <AccordionTab header="upload">
                <FileUpload
                  name="recordings[]"
                  :url="chart.id + '/upload'"
                  :multiple="true"
                  :custom-upload="true"
                  class="w-full"
                  @uploader="uploadMusic"
                >
                  <template #empty>
                    <p
                      class="text-gray-500 text-center py-8"
                    >
                      Drag and drop files to here to
                      upload.
                    </p>
                  </template>
                </FileUpload>
              </AccordionTab>
            </Accordion>
          </Panel>
        </div>
      </div>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated";
import FileUpload from "primevue/fileupload";
import pdf from "@jbtje/vite-vue3pdf";

export default {
    components: {
        BreezeAuthenticatedLayout,
        FileUpload,
        pdf,
    },
    props: {
        chart: {
            type: Object,
            default: () => {
                return {};
            },
        },
    },
    data() {
        return {
            chartData: this.chart,
            sheetMusic: [],
            recordings: [],
            showUploadModal: false,
            selectedUpload: false,
        };
    },
    created() {
        this.sheetMusic = this.chart.uploads.filter((upload) => {
            if (upload.upload_type_id === 3) {
                return upload;
            }
        });

        this.recordings = this.chart.uploads.filter((upload) => {
            if (upload.upload_type_id === 1 || upload.upload_type_id === 2) {
                return upload;
            }
        });

        this.chartData.public = this.chartData.public === 1;
    },
    methods: {
        selectUpload(upload) {
            this.selectedUpload = upload.data;
            this.showUploadModal = true;
        },
        uploadChart(event) {
            this.upload(event, 3);
        },
        uploadMusic(event) {
            this.upload(event, 1);
        },
        upload(event, type) {
            // console.log(event);
            this.$inertia
                .post(this.chartData.id + "/upload", {
                    type_id: type,
                    band_id: this.chartData.band_id,
                    "files[]": event.files,
                })
                .then(() => {
                    window.location.reload();
                });
        },
        updateChart() {
            this.$inertia.post(
                this.chartData.id,
                {
                    title: this.chartData.title,
                    composer: this.chartData.composer,
                    public: this.chartData.public,
                    description: this.chartData.description,
                },
                {
                    onSuccess: () => {
                        //  window.location.reload();
                    },
                }
            );
        },
        deleteChart() {
            this.$inertia.delete(this.chartData.id);
        },
        updateUpload() {
            this.$inertia.post(
                this.chartData.id + "/chartDownload/" + this.selectedUpload.id,
                {
                    displayName: this.selectedUpload.displayName,
                    notes: this.selectedUpload.notes
                        ? this.selectedUpload.notes
                        : "",
                }
            );
        },
        deleteUpload() {
            this.$inertia.delete(
                this.chartData.id + "/chartDownload/" + this.selectedUpload.id,
                {
                    onSuccess: () => {
                        window.location.reload();
                        this.showUploadModal = false;
                    },
                }
            );
        },
    },
};
</script>
<style>
.p-fileupload-filename {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
