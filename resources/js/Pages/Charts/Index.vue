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
                        label="New"
                        @click="openNew"
                    />
                </template>

                <template #end>
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search pl-2" />
                        </InputIcon>
                        <InputText
                            v-model="chartFilter"
                            placeholder="Search"
                            class="ml-2"
                            @input="filterCharts"
                        />
                    </IconField>
                </template>
            </Toolbar>
            <div class="card mt-2">
                <DataTable
                    :value="filteredChartsData"
                    striped-rows
                    row-hover
                    responsive-layout="scroll"
                    selection-mode="single"
                    @row-click="selectedChart"
                >
                    <Column field="title" header="Title" :sortable="true" />
                    <Column
                        field="composer"
                        header="Composer"
                        :sortable="true"
                    />
                    <template #empty>
                        No Records found. Click 'new' to create one.
                    </template>
                </DataTable>
            </div>

            <Dialog
                v-model:visible="chartDialog"
                :style="{ width: '450px' }"
                header="Chart Details"
                :modal="true"
            >
                <img
                    v-if="chart.image"
                    src="https://www.primefaces.org/wp-content/uploads/2020/05/placeholder.png"
                    :alt="chart.image"
                    class="w-full h-auto mb-4"
                />
                <div class="flex flex-col space-y-4">
                    <div class="flex flex-col">
                        <label for="name" class="mb-2 font-medium">Name</label>
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
                            >Name is required.</small
                        >
                    </div>
                    <div class="flex flex-col">
                        <label for="composer" class="mb-2 font-medium"
                            >Composer</label
                        >
                        <InputText
                            id="composer"
                            v-model.trim="chart.composer"
                            required="true"
                            class="w-full"
                            :class="{
                                'p-invalid': submitted && !chart.composer,
                            }"
                        />
                        <small
                            v-if="submitted && !chart.composer"
                            class="text-red-500 mt-1"
                            >Composer is required.</small
                        >
                    </div>
                    <div class="flex flex-col">
                        <label for="description" class="mb-2 font-medium"
                            >Description</label
                        >
                        <Textarea
                            id="description"
                            v-model="chart.description"
                            required="true"
                            rows="3"
                            cols="20"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col">
                        <label for="bandSelection" class="mb-2 font-medium"
                            >Band</label
                        >
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
                                <span v-else class="text-center">
                                    {{ slotProps.placeholder }}
                                </span>
                            </template>
                        </Select>
                    </div>
                    <div class="flex flex-col">
                        <label for="price" class="mb-2 font-medium"
                            >Price</label
                        >
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

export default {
    components: {
        BreezeAuthenticatedLayout,
        Toolbar,
        IconField,
        InputIcon,
        Select,
        DataTable,
        Column,
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
            this.$inertia.visit(this.route("charts.edit", data.data.id));
            this.$inertia.visit(this.route("charts.edit", data.data.id));
        },
        openNew() {
            this.saving = false;
            this.product = {};
            this.submitted = false;
            this.chartDialog = true;
        },

        saveChart() {
            this.submitted = true;
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
                    chart.composer.toLowerCase().includes(searchTerm)
            );
        },
    },
};
</script>
