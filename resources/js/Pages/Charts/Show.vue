<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2
          class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight"
        >
          <Link
            href="/charts"
            class="hover:text-blue-600"
          >
            Charts
          </Link> ::
          {{ chartData.title }}
        </h2>
        <div
          v-if="canEdit"
          class="flex gap-2"
        >
          <Button
            label="Edit Chart"
            icon="pi pi-pencil"
            severity="secondary"
            @click="editChart"
          />
        </div>
      </div>
    </template>

    <Dialog
      v-model:visible="showPreviewModal"
      :header="selectedUpload.displayName"
      :modal="true"
      :style="{ width: '80vw' }"
      class="mx-auto"
    >
      <div class="space-y-4">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col">
            <label class="mb-2 font-medium">Preview</label>
            <div class="flex justify-center">
              <div class="max-w-4xl w-full py-4 mx-auto">
                <Image
                  v-if="
                    selectedUpload.fileType &&
                      selectedUpload.fileType.indexOf('image') !== -1
                  "
                  :src="
                    selectedUpload.chart_id +
                      '/chartDownload/' +
                      selectedUpload.name
                  "
                  :alt="selectedUpload.name"
                  preview
                  class="w-full"
                />

                <pdf
                  v-else-if="
                    selectedUpload.fileType &&
                      selectedUpload.fileType.indexOf('pdf') !== -1
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
                    selectedUpload.fileType &&
                      selectedUpload.fileType.indexOf('audio') !== -1
                  "
                  :audio-src="
                    selectedUpload.chart_id +
                      '/chartDownload/' +
                      selectedUpload.name
                  "
                />

                <video
                  v-else-if="
                    selectedUpload.fileType &&
                      selectedUpload.fileType.indexOf('video') !== -1
                  "
                  controls
                  class="w-full"
                >
                  <source
                    :src="
                      selectedUpload.chart_id +
                        '/chartDownload/' +
                        selectedUpload.name
                    "
                  >
                </video>

                <div
                  v-else
                  class="text-center text-gray-500"
                >
                  Unable to display file
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="selectedUpload.notes"
            class="flex flex-col"
          >
            <label class="mb-2 font-medium">Notes</label>
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded">
              {{ selectedUpload.notes }}
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between w-full">
          <Button
            label="Close"
            icon="pi pi-times"
            severity="secondary"
            @click="showPreviewModal = false"
          />
          <a
            :href="
              selectedUpload.chart_id +
                '/chartDownload/' +
                selectedUpload.name
            "
            download
          >
            <Button
              label="Download"
              icon="pi pi-download"
              severity="primary"
            />
          </a>
        </div>
      </template>
    </Dialog>

    <Container>
      <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col space-y-6">
          <!-- Chart Info Card -->
          <Card>
            <template #title>
              <div class="flex items-center justify-between">
                <span>{{ chartData.title }}</span>
                <Tag
                  v-if="chartData.public"
                  severity="success"
                  value="Public"
                />
                <Tag
                  v-else
                  severity="secondary"
                  value="Private"
                />
              </div>
            </template>
            <template #subtitle>
              <span class="text-gray-600 dark:text-gray-400">
                {{ chartData.composer }}
              </span>
            </template>
            <template #content>
              <div
                v-if="chartData.description"
                class="prose dark:prose-invert max-w-none"
                v-html="chartData.description"
              />
              <div
                v-else
                class="text-gray-400 italic"
              >
                No description available
              </div>
            </template>
          </Card>

          <Divider />

          <!-- Sheet Music Section -->
          <div v-if="sheetMusic.length > 0">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-50">
              Sheet Music
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <Card
                v-for="upload in sheetMusic"
                :key="upload.id"
                class="cursor-pointer hover:shadow-lg transition-shadow duration-200"
                @click="previewUpload(upload)"
              >
                <template #header>
                  <div
                    class="bg-gradient-to-br from-red-50 to-orange-100 dark:from-gray-700 dark:to-gray-800 h-32 flex items-center justify-center overflow-hidden"
                  >
                    <!-- Image Preview -->
                    <img
                      v-if="
                        upload.fileType &&
                          upload.fileType.indexOf('image') !== -1
                      "
                      :src="
                        chartData.id +
                          '/chartDownload/' +
                          upload.name
                      "
                      :alt="upload.displayName"
                      class="w-full h-full object-cover"
                    >
                    <!-- PDF Preview -->
                    <div
                      v-else-if="
                        upload.fileType &&
                          upload.fileType.indexOf('pdf') !== -1
                      "
                      class="w-full h-full flex items-center justify-center bg-white"
                    >
                      <pdf
                        :src="
                          chartData.id +
                            '/chartDownload/' +
                            upload.name
                        "
                        :page="1"
                        class="max-w-full max-h-full"
                        style="transform: scale(0.3); transform-origin: center;"
                      />
                    </div>
                    <!-- Fallback Icon -->
                    <i
                      v-else
                      class="pi pi-file-pdf text-5xl text-red-600 dark:text-red-400"
                    />
                  </div>
                </template>
                <template #title>
                  <div
                    class="text-base truncate"
                    :title="upload.displayName"
                  >
                    {{ upload.displayName || upload.name }}
                  </div>
                </template>
                <template #content>
                  <div class="text-sm text-gray-500 truncate">
                    {{ upload.name }}
                  </div>
                </template>
                <template #footer>
                  <div class="flex justify-end">
                    <a
                      :href="
                        chartData.id +
                          '/chartDownload/' +
                          upload.name
                      "
                      download
                      @click.stop
                    >
                      <Button
                        icon="pi pi-download"
                        severity="secondary"
                        size="small"
                        text
                      />
                    </a>
                  </div>
                </template>
              </Card>
            </div>
          </div>

          <!-- Recordings Section -->
          <div v-if="recordings.length > 0">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-50">
              Recordings
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <Card
                v-for="upload in recordings"
                :key="upload.id"
                class="cursor-pointer hover:shadow-lg transition-shadow duration-200"
                @click="previewUpload(upload)"
              >
                <template #header>
                  <div
                    class="bg-gradient-to-br from-purple-50 to-pink-100 dark:from-gray-700 dark:to-gray-800 h-32 flex items-center justify-center overflow-hidden relative"
                  >
                    <!-- Audio Waveform Preview -->
                    <div
                      v-if="
                        upload.fileType &&
                          upload.fileType.indexOf('audio') !== -1
                      "
                      class="w-full h-full flex flex-col items-center justify-center"
                    >
                      <i class="pi pi-volume-up text-3xl text-purple-600 dark:text-purple-400 mb-2" />
                      <div class="text-xs text-purple-600 dark:text-purple-400 text-center px-2">
                        Audio File
                      </div>
                    </div>
                    <!-- Video Thumbnail -->
                    <video
                      v-else-if="
                        upload.fileType &&
                          upload.fileType.indexOf('video') !== -1
                      "
                      :src="
                        chartData.id +
                          '/chartDownload/' +
                          upload.name
                      "
                      class="w-full h-full object-cover"
                      muted
                      preload="metadata"
                    />
                    <!-- Image Preview -->
                    <img
                      v-else-if="
                        upload.fileType &&
                          upload.fileType.indexOf('image') !== -1
                      "
                      :src="
                        chartData.id +
                          '/chartDownload/' +
                          upload.name
                      "
                      :alt="upload.displayName"
                      class="w-full h-full object-cover"
                    >
                    <!-- Fallback Icon -->
                    <i
                      v-else
                      class="pi pi-file text-5xl text-purple-600 dark:text-purple-400"
                    />
                  </div>
                </template>
                <template #title>
                  <div
                    class="text-base truncate"
                    :title="upload.displayName"
                  >
                    {{ upload.displayName || upload.name }}
                  </div>
                </template>
                <template #content>
                  <div class="text-sm text-gray-500 truncate">
                    {{ upload.name }}
                  </div>
                </template>
                <template #footer>
                  <div class="flex justify-end">
                    <a
                      :href="
                        chartData.id +
                          '/chartDownload/' +
                          upload.name
                      "
                      download
                      @click.stop
                    >
                      <Button
                        icon="pi pi-download"
                        severity="secondary"
                        size="small"
                        text
                      />
                    </a>
                  </div>
                </template>
              </Card>
            </div>
          </div>

          <!-- Empty State -->
          <div
            v-if="sheetMusic.length === 0 && recordings.length === 0"
            class="text-center py-16"
          >
            <i class="pi pi-inbox text-6xl text-gray-300 mb-4" />
            <p class="text-xl text-gray-500 mb-2">
              No files uploaded yet
            </p>
            <p class="text-gray-400 mb-4">
              Click "Edit Chart" to add sheet music or recordings
            </p>
          </div>
        </div>
      </div>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated";
import Card from "primevue/card";
import Tag from "primevue/tag";
import pdf from "@jbtje/vite-vue3pdf";

export default {
    components: {
        BreezeAuthenticatedLayout,
        Card,
        Tag,
        pdf,
    },
    props: {
        chart: {
            type: Object,
            default: () => {
                return {};
            },
        },
        canEdit: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            chartData: this.chart,
            sheetMusic: [],
            recordings: [],
            showPreviewModal: false,
            selectedUpload: {},
        };
    },
    created() {
        this.sheetMusic = this.chart.uploads.filter((upload) => {
            return upload.upload_type_id === 3;
        });

        this.recordings = this.chart.uploads.filter((upload) => {
            return upload.upload_type_id === 1 || upload.upload_type_id === 2;
        });
    },
    methods: {
        previewUpload(upload) {
            this.selectedUpload = upload;
            this.showPreviewModal = true;
        },
        editChart() {
            this.$inertia.visit(this.route("charts.edit", this.chartData.id));
        },
    },
};
</script>

<style scoped>
.prose {
    max-width: none;
}
</style>
