<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl dark:text-gray-50 text-gray-800 leading-tight">
        Band Charts
      </h2>
    </template>

    <Container>
      <Toolbar class="p-mb-4 border-b-2">
        <template #start>
          <Button
            icon="pi pi-plus"
            class="mr-2"
            severity="secondary"
            text
            label="New Chart"
            @click="openNew"
          />
        </template>

        <template #center>
          <div class="flex items-center gap-2">
            <Button
              :icon="viewMode === 'grid' ? 'pi pi-th-large' : 'pi pi-th-large'"
              :class="viewMode === 'grid' ? 'bg-blue-100' : ''"
              text
              @click="viewMode = 'grid'"
            />
            <Button
              :icon="viewMode === 'table' ? 'pi pi-list' : 'pi pi-list'"
              :class="viewMode === 'table' ? 'bg-blue-100' : ''"
              text
              @click="viewMode = 'table'"
            />
          </div>
        </template>

        <template #end>
          <IconField>
            <InputIcon>
              <i class="pi pi-search pl-2" />
            </InputIcon>
            <InputText
              v-model="chartFilter"
              placeholder="Search charts..."
              class="ml-2"
              @input="filterCharts"
            />
          </IconField>
        </template>
      </Toolbar>

      <!-- Grid View -->
      <div
        v-if="viewMode === 'grid'"
        class="mt-6"
      >
        <div
          v-if="filteredChartsData.length === 0"
          class="text-center py-16"
        >
          <i class="pi pi-book text-6xl text-gray-300 mb-4" />
          <p class="text-xl text-gray-500 mb-2">
            No charts found
          </p>
          <p class="text-gray-400 mb-4">
            {{ chartFilter ? 'Try a different search term' : 'Click "New Chart" to create one' }}
          </p>
        </div>
                
        <div
          v-else
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
        >
          <Card
            v-for="chart in filteredChartsData"
            :key="chart.id"
            class="cursor-pointer hover:shadow-lg transition-shadow duration-200"
            @click="selectedChart(chart)"
          >
            <template #header>
              <div class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-700 dark:to-gray-800 h-40 flex items-center justify-center">
                <i class="pi pi-file-pdf text-6xl text-blue-600 dark:text-blue-400" />
              </div>
            </template>
            <template #title>
              <div
                class="text-lg font-semibold truncate"
                :title="chart.title"
              >
                {{ chart.title }}
              </div>
            </template>
            <template #subtitle>
              <div
                class="text-sm text-gray-600 dark:text-gray-400 truncate"
                :title="chart.composer"
              >
                {{ chart.composer }}
              </div>
            </template>
            <template #content>
              <div class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                <div
                  v-if="chart.description"
                  class="line-clamp-2"
                >
                  {{ stripHtml(chart.description) }}
                </div>
                <div class="flex items-center gap-2 pt-2">
                  <Tag
                    v-if="chart.public"
                    severity="success"
                    value="Public"
                  />
                  <Tag
                    v-else
                    severity="secondary"
                    value="Private"
                  />
                </div>
              </div>
            </template>
            <template #footer>
              <div class="flex justify-between items-center text-xs text-gray-500">
                <span v-if="chart.uploads && chart.uploads.length > 0">
                  {{ chart.uploads.length }} file{{ chart.uploads.length !== 1 ? 's' : '' }}
                </span>
                <span
                  v-else
                  class="text-gray-400"
                >No files</span>
              </div>
            </template>
          </Card>
        </div>
      </div>

      <!-- Table View -->
      <div
        v-else
        class="card mt-2"
      >
        <DataTable
          :value="filteredChartsData"
          striped-rows
          row-hover
          responsive-layout="scroll"
          selection-mode="single"
          @row-click="selectedChart"
        >
          <Column
            field="title"
            header="Title"
            :sortable="true"
          >
            <template #body="slotProps">
              <div class="font-semibold">
                {{ slotProps.data.title }}
              </div>
            </template>
          </Column>
          <Column
            field="composer"
            header="Composer"
            :sortable="true"
          />
          <Column
            field="public"
            header="Visibility"
            :sortable="true"
          >
            <template #body="slotProps">
              <Tag
                v-if="slotProps.data.public"
                severity="success"
                value="Public"
              />
              <Tag
                v-else
                severity="secondary"
                value="Private"
              />
            </template>
          </Column>
          <Column
            field="uploads"
            header="Files"
          >
            <template #body="slotProps">
              <span v-if="slotProps.data.uploads && slotProps.data.uploads.length > 0">
                {{ slotProps.data.uploads.length }} file{{ slotProps.data.uploads.length !== 1 ? 's' : '' }}
              </span>
              <span
                v-else
                class="text-gray-400"
              >No files</span>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-8">
              <p class="text-gray-500">
                No charts found. Click 'New Chart' to create one.
              </p>
            </div>
          </template>
        </DataTable>
      </div>


      <Dialog
        v-model:visible="chartDialog"
        :style="{ width: '450px' }"
        header="New Chart"
        :modal="true"
      >
        <div class="flex flex-col space-y-4">
          <div class="flex flex-col">
            <label
              for="name"
              class="mb-2 font-medium"
            >Title</label>
            <InputText
              id="name"
              v-model.trim="chart.name"
              required="true"
              autofocus
              class="w-full"
              :class="{ 'p-invalid': submitted && !chart.name }"
            />
            <small
              v-if="submitted && !chart.name"
              class="text-red-500 mt-1"
            >Title is required.</small>
          </div>
          <div class="flex flex-col">
            <label
              for="composer"
              class="mb-2 font-medium"
            >Composer</label>
            <InputText
              id="composer"
              v-model.trim="chart.composer"
              required="true"
              class="w-full"
              :class="{ 'p-invalid': submitted && !chart.composer }"
            />
            <small
              v-if="submitted && !chart.composer"
              class="text-red-500 mt-1"
            >Composer is required.</small>
          </div>
          <div class="flex flex-col">
            <label
              for="description"
              class="mb-2 font-medium"
            >Description</label>
            <Textarea
              id="description"
              v-model="chart.description"
              rows="3"
              cols="20"
              class="w-full"
            />
          </div>
          <div class="flex flex-col">
            <label
              for="bandSelection"
              class="mb-2 font-medium"
            >Band</label>
            <Select
              id="bandSelection"
              v-model="chart.band"
              :options="availableBands"
              option-label="name"
              placeholder="Select a Band"
              class="w-full"
            >
              <template #value="slotProps">
                <div
                  v-if="slotProps.value && slotProps.value.id"
                  class="text-center"
                >
                  <span>{{ slotProps.value.name }}</span>
                </div>
                <span
                  v-else
                  class="text-center"
                >
                  {{ slotProps.placeholder }}
                </span>
              </template>
            </Select>
          </div>
          <div class="flex flex-col">
            <label
              for="price"
              class="mb-2 font-medium"
            >Price</label>
            <InputNumber
              id="price"
              v-model="chart.price"
              mode="currency"
              currency="USD"
              locale="en-US"
              class="w-full text-center"
            />
          </div>
        </div>
        <template #footer>
          <div class="flex justify-end space-x-2">
            <Button
              label="Cancel"
              icon="pi pi-times"
              class="p-button-text"
              @click="closeDialog"
            />
            <Button
              :label="saving ? 'Saving...' : 'Save'"
              :disabled="saving"
              icon="pi pi-check"
              class="p-button-text"
              @click="saveChart"
            />
          </div>
        </template>
      </Dialog>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from "@/Layouts/Authenticated";
import InputSwitch from "primevue/inputswitch";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import Toolbar from "primevue/toolbar";
import DataTable from "primevue/datatable";
import Select from "primevue/select";
import Column from "primevue/column";
import Card from "primevue/card";
import Tag from "primevue/tag";

export default {
    components: {
        BreezeAuthenticatedLayout,
        Toolbar,
        IconField,
        InputIcon,
        Select,
        DataTable,
        Column,
        Card,
        Tag,
    },
    props: {
        charts: {
            type: Array,
            default: () => {
                return [];
            },
        },
        availableBands: {
            type: Array,
            default: () => {
                return [];
            },
        },
    },
    data() {
        return {
            form: {},
            chartsData: this.charts,
            filteredChartsData: [],
            chart: {
                price: 0,
            },
            saving: false,
            submitted: false,
            chartDialog: false,
            chartFilter: "",
            viewMode: "table", // 'grid' or 'table'
        };
    },
    computed: {},
    watch: {
        chartFilter: {
            handler(newValue) {
                this.filterCharts();
            },
        },
    },
    created() {
        this.filteredChartsData = this.chartsData;
    },
    methods: {
        selectedChart(data) {
            const chartId = data.data ? data.data.id : data.id;
            this.$inertia.visit(this.route("charts.show", chartId));
        },
        openNew() {
            this.saving = false;
            this.chart = { price: 0 };
            this.submitted = false;
            this.chartDialog = true;
        },

        saveChart() {
            this.submitted = true;
            if (!this.chart.name || !this.chart.composer) {
                return;
            }
            this.saving = true;
            this.chart.band_id = this.chart.band.id;
            this.$inertia.post("/charts/new", this.chart);
        },
        closeDialog() {
            this.saving = false;
            this.chartDialog = false;
        },

        filterCharts() {
            const searchTerm = this.chartFilter.toLowerCase();
            this.filteredChartsData = this.chartsData.filter(
                (chart) =>
                    chart.title.toLowerCase().includes(searchTerm) ||
                    chart.composer.toLowerCase().includes(searchTerm) ||
                    (chart.description && chart.description.toLowerCase().includes(searchTerm))
            );
        },

        stripHtml(html) {
            if (!html) return '';
            const tmp = document.createElement("div");
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        },
    },
};
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

