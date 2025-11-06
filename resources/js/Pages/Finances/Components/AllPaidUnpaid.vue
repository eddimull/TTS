<template>
  <div class="componentPanel shadow-md rounded-lg p-6 mx-auto">
    <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-100">
      Paid vs Unpaid Amounts and Booking Count by Month
    </h2>
    <div
      class="mb-8"
    >
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-1">
          <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg sticky top-4">
            <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-300 dark:border-gray-600">
              <label
                for="year-select"
                class="text-gray-700 dark:text-gray-50 font-medium"
              >Select Year:</label>
              <select
                id="year-select"
                v-model="selectedYear"
                placeholder="Year"
                class="p-2 pr-8 border rounded shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                @change="updateChartData"
              >
                <option
                  v-for="year in availableYears"
                  :key="year"
                  :value="year"
                >
                  {{ year }}
                </option>
              </select>
            </div>
            <label
              for="snapshot-timeline"
              class="block text-gray-700 dark:text-gray-50 font-medium mb-2"
            >Time Travel: {{ formatTimelineDate }}</label>
            <div class="flex items-center gap-3 mb-2">
              <button
                class="px-3 py-2 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors text-sm"
                @click="snapshotDate = null; timelinePosition = 0"
              >
                Clear
              </button>
              <div class="flex-1 min-w-[150px]">
                <Slider
                  v-model="timelinePosition"
                  :min="0"
                  :max="100"
                  :step="0.1"
                  class="w-full"
                  @update:model-value="handleTimelineChange"
                />
              </div>
            </div>
            <DatePicker
              id="snapshot-date"
              v-model="snapshotDate"
              date-format="yy-mm-dd"
              show-icon
              :show-button-bar="true"
              placeholder="Pick date"
              class="w-full mb-2"
              @update:model-value="handleSnapshotChange"
            />
            <small class="block text-gray-500 dark:text-gray-400 mb-3">
              Drag the slider to see bookings as they existed at different points in time
            </small>
            <div
              v-if="snapshotDate"
              class="flex items-center gap-2 pt-3 border-t border-gray-300 dark:border-gray-600"
            >
              <Checkbox
                v-model="compareWithCurrent"
                input-id="compare-toggle"
                :binary="true"
                @update:model-value="handleCompareToggle"
              />
              <label
                for="compare-toggle"
                class="text-sm text-gray-700 dark:text-gray-50 cursor-pointer"
              >
                Compare with current bookings
              </label>
            </div>
          </div>
        </div>
        <div
          class="lg:col-span-2 flex flex-col md:flex-row gap-4"
        >
          <div
            class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg shadow-inner transition-all flex-shrink-0"
            :style="{ width: (compareWithCurrent && yearTotalsCurrent) ? 'calc(50% - 0.5rem)' : '100%', transitionDuration: '400ms', transitionTimingFunction: 'cubic-bezier(0.165, 0.84, 0.44, 1)' }"
          >
            <h3
              class="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100"
            >
              Year Totals {{ compareWithCurrent && snapshotDate ? '(Snapshot)' : '' }}
            </h3>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
              <dt class="font-medium text-gray-600 dark:text-gray-50">
                Total paid:
              </dt>
              <dd class="text-gray-800 dark:text-white">
                {{ moneyFormat(yearTotals.paid) }}
              </dd>
              <dt class="font-medium text-gray-600 dark:text-gray-50">
                Total unpaid:
              </dt>
              <dd class="text-gray-800 dark:text-white">
                {{ moneyFormat(yearTotals.unpaid) }}
              </dd>
              <dt class="font-medium text-gray-600 dark:text-gray-50">
                Forecasted Revenue:
              </dt>
              <dd class="text-gray-800 dark:text-white">
                {{ moneyFormat(yearTotals.forecast) }}
              </dd>
              <dt class="font-medium text-gray-600 dark:text-gray-50">
                Total bookings:
              </dt>
              <dd class="text-gray-800 dark:text-white">
                {{ yearTotals.bookings }}
              </dd>
            </dl>
          </div>
          <Transition name="fade-slide">
            <div
              v-if="compareWithCurrent && yearTotalsCurrent"
              class="bg-blue-100 dark:bg-blue-900 p-4 rounded-lg shadow-inner flex-shrink-0"
              style="width: calc(50% - 0.5rem)"
            >
              <h3
                class="text-lg font-semibold mb-2 text-gray-800 dark:text-gray-100"
              >
                Year Totals (Current)
              </h3>
              <dl
                v-if="yearTotalsCurrent"
                class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm"
              >
                <dt class="font-medium text-gray-600 dark:text-gray-50">
                  Total paid:
                </dt>
                <dd class="text-gray-800 dark:text-white">
                  {{ moneyFormat(yearTotalsCurrent.paid) }}
                  <span
                    v-if="yearTotalsCurrent.paid !== yearTotals.paid"
                    :class="yearTotalsCurrent.paid > yearTotals.paid ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                  >
                    ({{ yearTotalsCurrent.paid > yearTotals.paid ? '+' : '' }}{{ moneyFormat(yearTotalsCurrent.paid - yearTotals.paid) }})
                  </span>
                </dd>
                <dt class="font-medium text-gray-600 dark:text-gray-50">
                  Total unpaid:
                </dt>
                <dd class="text-gray-800 dark:text-white">
                  {{ moneyFormat(yearTotalsCurrent.unpaid) }}
                  <span
                    v-if="yearTotalsCurrent.unpaid !== yearTotals.unpaid"
                    :class="yearTotalsCurrent.unpaid > yearTotals.unpaid ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'"
                  >
                    ({{ yearTotalsCurrent.unpaid > yearTotals.unpaid ? '+' : '' }}{{ moneyFormat(yearTotalsCurrent.unpaid - yearTotals.unpaid) }})
                  </span>
                </dd>
                <dt class="font-medium text-gray-600 dark:text-gray-50">
                  Forecasted Revenue:
                </dt>
                <dd class="text-gray-800 dark:text-white">
                  {{ moneyFormat(yearTotalsCurrent.forecast) }}
                  <span
                    v-if="yearTotalsCurrent.forecast !== yearTotals.forecast"
                    :class="yearTotalsCurrent.forecast > yearTotals.forecast ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                  >
                    ({{ yearTotalsCurrent.forecast > yearTotals.forecast ? '+' : '' }}{{ moneyFormat(yearTotalsCurrent.forecast - yearTotals.forecast) }})
                  </span>
                </dd>
                <dt class="font-medium text-gray-600 dark:text-gray-50">
                  Total bookings:
                </dt>
                <dd class="text-gray-800 dark:text-white">
                  {{ yearTotalsCurrent.bookings }}
                  <span
                    v-if="yearTotalsCurrent.bookings !== yearTotals.bookings"
                    :class="yearTotalsCurrent.bookings > yearTotals.bookings ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                  >
                    ({{ yearTotalsCurrent.bookings > yearTotals.bookings ? '+' : '' }}{{ yearTotalsCurrent.bookings - yearTotals.bookings }})
                  </span>
                </dd>
              </dl>
            </div>
          </Transition>
        </div>
      </div>
    </div>
    <Chart
      ref="chartRef"
      type="bar"
      :data="chartData"
      :options="chartOptions"
      class="h-[600px] w-full"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick, shallowRef } from "vue";
import Chart from "primevue/chart";
import DatePicker from "primevue/datepicker";
import Checkbox from "primevue/checkbox";
import Slider from "primevue/slider";
import { router } from '@inertiajs/vue3';

const chartRef = ref(null);

const props = defineProps({
    allBookings: {
        type: Array,
        required: true,
    },
    snapshotDate: {
        type: String,
        default: null,
    },
    compareWithCurrent: {
        type: Boolean,
        default: false,
    },
    selectedYear: {
        type: [Number, String],
        default: null,
    },
});

const chartData = shallowRef({});
const chartOptions = ref({});
const selectedYear = ref(new Date().getFullYear());
const snapshotDate = ref(props.snapshotDate ? new Date(props.snapshotDate) : null);
const compareWithCurrent = ref(props.compareWithCurrent);
const isInitialLoad = ref(true);

// Timeline slider for snapshot date
const timelinePosition = ref(0);

// Get min and max dates from all bookings
const timelineRange = computed(() => {
    if (allBookingsFromBands.value.length === 0) {
        return { min: new Date(), max: new Date() };
    }
    
    const dates = allBookingsFromBands.value.map(b => new Date(b.created_at).getTime());
    const minDate = new Date(Math.min(...dates));
    const maxDate = new Date(Math.max(...dates));
    
    // Add some padding
    minDate.setDate(minDate.getDate() - 7);
    maxDate.setDate(maxDate.getDate() + 7);
    
    return { min: minDate, max: maxDate };
});

// Convert timeline position (0-100) to date
const positionToDate = (position) => {
    if (!timelineRange.value) return new Date();
    const { min, max } = timelineRange.value;
    const range = max.getTime() - min.getTime();
    const timestamp = min.getTime() + (range * position / 100);
    return new Date(timestamp);
};

// Convert date to timeline position (0-100)
const dateToPosition = (date) => {
    if (!date || !timelineRange.value) return 0;
    const { min, max } = timelineRange.value;
    const range = max.getTime() - min.getTime();
    const position = ((date.getTime() - min.getTime()) / range) * 100;
    return Math.max(0, Math.min(100, position));
};

const handleSnapshotChange = (value) => {
    // Update snapshot date locally without reloading page
    snapshotDate.value = value;
    // Chart will update automatically via watchers
};

const handleTimelineChange = (value) => {
    const newDate = positionToDate(value);
    snapshotDate.value = newDate;
};

const formatTimelineDate = computed(() => {
    if (!snapshotDate.value) return 'Select date';
    return snapshotDate.value.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
});

const handleCompareToggle = (newValue) => {
    const formattedDate = snapshotDate.value ? new Date(snapshotDate.value).toISOString().split('T')[0] : null;
    console.log('Compare toggle:', newValue, 'Snapshot date:', formattedDate, 'Current year:', selectedYear.value);
    router.get(route('Paid/Unpaid'), {
        snapshot_date: formattedDate,
        compare_with_current: newValue ? 1 : 0,
        year: selectedYear.value
    }, {
        preserveScroll: true,
    });
};

// Get all bookings from all bands
const allBookingsFromBands = computed(() => {
    const bookings = props.allBookings.reduce((acc, band) => {
        return acc.concat(band.paidBookings, band.unpaidBookings);
    }, []);
    console.log('allBookingsFromBands computed:', bookings.length, 'bookings');
    return bookings;
});

// Filter bookings by snapshot date (client-side filtering)
const filteredSnapshotBookings = computed(() => {
    if (!snapshotDate.value) {
        // No snapshot date selected, return all bookings
        return allBookingsFromBands.value;
    }

    const snapshotDateObj = new Date(snapshotDate.value);
    const filtered = allBookingsFromBands.value.filter(booking => {
        const createdAt = new Date(booking.created_at);
        return createdAt <= snapshotDateObj;
    });

    console.log('filteredSnapshotBookings:', filtered.length, 'bookings (filtered by', snapshotDate.value, ')');
    return filtered;
});

const availableYears = computed(() => {
    // When comparing, include years from both datasets
    let bookings = compareWithCurrent.value
        ? allBookingsFromBands.value
        : filteredSnapshotBookings.value;

    const years = new Set(
        bookings.map((booking) => new Date(booking.date).getFullYear())
    );
    return Array.from(years).sort((a, b) => b - a);
});

const processDataByMonth = (bookings, year) => {
    const dataByMonth = {};

    bookings.forEach((booking) => {
        if (booking?.status === "cancelled") {
            return;
        }
        const date = new Date(booking.date);
        const bookingYear = date.getFullYear();
        if (bookingYear !== year) return;

        const month = String(date.getMonth() + 1).padStart(2, "0");
        const yearMonth = `${bookingYear}-${month}`;

        if (!dataByMonth[yearMonth]) {
            dataByMonth[yearMonth] = {
                paid: 0,
                unpaid: 0,
                bookings: 0,
                forecast: 0,
            };
        }

        const price = parseFloat(booking.price) || 0;
        const amountPaid = parseFloat(booking.amount_paid) || 0;

        dataByMonth[yearMonth].forecast += price;
        dataByMonth[yearMonth].paid += amountPaid;
        dataByMonth[yearMonth].unpaid += Math.max(0, price - amountPaid);
        dataByMonth[yearMonth].bookings += 1;
    });

    return dataByMonth;
};

const processedData = computed(() => {
    return processDataByMonth(filteredSnapshotBookings.value, selectedYear.value);
});

const processedCurrentData = computed(() => {
    if (!compareWithCurrent.value) return {};
    return processDataByMonth(allBookingsFromBands.value, selectedYear.value);
});

const moneyFormat = (value) => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
    }).format(value);
};

const yearTotals = computed(() => {
    return Object.values(processedData.value).reduce(
        (acc, month) => {
            acc.paid += month.paid;
            acc.unpaid += month.unpaid;
            acc.bookings += month.bookings;
            acc.forecast += month.forecast;
            return acc;
        },
        { paid: 0, unpaid: 0, bookings: 0, forecast: 0 }
    );
});

const yearTotalsCurrent = computed(() => {
    if (!compareWithCurrent.value || !snapshotDate.value) return null;
    return Object.values(processedCurrentData.value).reduce(
        (acc, month) => {
            acc.paid += month.paid;
            acc.unpaid += month.unpaid;
            acc.bookings += month.bookings;
            acc.forecast += month.forecast;
            return acc;
        },
        { paid: 0, unpaid: 0, bookings: 0, forecast: 0 }
    );
});

const initializeChartData = () => {
    console.log('initializeChartData called');
    const months = [
        "01", "02", "03", "04", "05", "06",
        "07", "08", "09", "10", "11", "12"
    ];

    chartData.value = {
        labels: months.map((month) => `${selectedYear.value}-${month}`),
        datasets: [
            {
                type: "bar",
                label: "Paid Amount (Snapshot)",
                backgroundColor: "#42A5F5",
                data: new Array(12).fill(0),
                yAxisID: "y-axis-1",
            },
            {
                type: "bar",
                label: "Unpaid Amount (Snapshot)",
                backgroundColor: "#777",
                data: new Array(12).fill(0),
                yAxisID: "y-axis-1",
            },
            {
                type: "line",
                label: "Forecasted Revenue (Snapshot)",
                borderColor: "#16c20a",
                borderWidth: 2,
                fill: false,
                data: new Array(12).fill(0),
                yAxisID: "y-axis-1",
            },
            {
                type: "line",
                label: "Number of Bookings (Snapshot)",
                borderColor: "#FFA500",
                borderWidth: 2,
                fill: false,
                data: new Array(12).fill(0),
                yAxisID: "y-axis-2",
            },
            // Comparison datasets (hidden initially)
            {
                type: "bar",
                label: "Paid Amount (Current)",
                backgroundColor: "#90CAF9",
                data: new Array(12).fill(0),
                yAxisID: "y-axis-1",
                hidden: true,
            },
            {
                type: "bar",
                label: "Unpaid Amount (Current)",
                backgroundColor: "#BDBDBD",
                data: new Array(12).fill(0),
                yAxisID: "y-axis-1",
                hidden: true,
            },
            {
                type: "line",
                label: "Forecasted Revenue (Current)",
                borderColor: "#8BC34A",
                borderWidth: 2,
                borderDash: [5, 5],
                fill: false,
                data: new Array(12).fill(0),
                yAxisID: "y-axis-1",
                hidden: true,
            },
            {
                type: "line",
                label: "Number of Bookings (Current)",
                borderColor: "#FFB74D",
                borderWidth: 2,
                borderDash: [5, 5],
                fill: false,
                data: new Array(12).fill(0),
                yAxisID: "y-axis-2",
                hidden: true,
            }
        ],
    };
    console.log('Chart data initialized with empty datasets (all 0s)');
};

const updateChartData = async () => {
    console.log('updateChartData called', {
        compareWithCurrent: compareWithCurrent.value,
        snapshotDate: snapshotDate.value?.toDateString?.(),
        processedDataKeys: Object.keys(processedData.value).length,
        processedCurrentDataKeys: Object.keys(processedCurrentData.value).length
    });

    await nextTick();

    // Get direct access to Chart.js instance to bypass Vue reactivity
    const chart = chartRef.value?.chart;
    if (!chart) {
        console.log('Chart not ready yet');
        return;
    }

    console.log('Chart instance found:', {
        datasetsCount: chart.data.datasets.length,
        labelsCount: chart.data.labels.length,
        currentPaidData: chart.data.datasets[0].data.slice(0, 3),
        currentUnpaidData: chart.data.datasets[1].data.slice(0, 3),
        dataset0March: chart.data.datasets[0].data[2],
        dataset0Sept: chart.data.datasets[0].data[8],
    });

    const months = ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12"];
    let hasChanges = false;

    // Update labels for current year (mutate Chart.js data directly)
    months.forEach((month, index) => {
        const newLabel = `${selectedYear.value}-${month}`;
        if (chart.data.labels[index] !== newLabel) {
            chart.data.labels[index] = newLabel;
            hasChanges = true;
        }
    });

    // Update snapshot data (first 4 datasets) - mutate Chart.js data directly
    const isComparing = compareWithCurrent.value && snapshotDate.value;

    const newLabel0 = isComparing ? "Paid Amount (Snapshot)" : "Paid Amount";
    if (chart.data.datasets[0].label !== newLabel0) {
        chart.data.datasets[0].label = newLabel0;
        hasChanges = true;
    }

    months.forEach((month, index) => {
        const newValue = processedData.value[`${selectedYear.value}-${month}`]?.paid || 0;
        const oldValue = chart.data.datasets[0].data[index] ?? 0;
        if (oldValue !== newValue) {
            console.log(`Paid [${month}]: ${oldValue} -> ${newValue} (index ${index}, yearMonth: ${selectedYear.value}-${month})`);
            chart.data.datasets[0].data[index] = newValue;
            hasChanges = true;
        }
    });

    const newLabel1 = isComparing ? "Unpaid Amount (Snapshot)" : "Unpaid Amount";
    if (chart.data.datasets[1].label !== newLabel1) {
        chart.data.datasets[1].label = newLabel1;
        hasChanges = true;
    }

    months.forEach((month, index) => {
        const newValue = processedData.value[`${selectedYear.value}-${month}`]?.unpaid || 0;
        const oldValue = chart.data.datasets[1].data[index] ?? 0;
        if (oldValue !== newValue) {
            console.log(`Unpaid [${month}]: ${oldValue} -> ${newValue}`);
            chart.data.datasets[1].data[index] = newValue;
            hasChanges = true;
        }
    });

    const newLabel2 = isComparing ? "Forecasted Revenue (Snapshot)" : "Forecasted Revenue";
    if (chart.data.datasets[2].label !== newLabel2) {
        chart.data.datasets[2].label = newLabel2;
        hasChanges = true;
    }

    months.forEach((month, index) => {
        const newValue = processedData.value[`${selectedYear.value}-${month}`]?.forecast || 0;
        const oldValue = chart.data.datasets[2].data[index] ?? 0;
        if (oldValue !== newValue) {
            console.log(`Forecast [${month}]: ${oldValue} -> ${newValue}`);
            chart.data.datasets[2].data[index] = newValue;
            hasChanges = true;
        }
    });

    const newLabel3 = isComparing ? "Number of Bookings (Snapshot)" : "Number of Bookings";
    if (chart.data.datasets[3].label !== newLabel3) {
        chart.data.datasets[3].label = newLabel3;
        hasChanges = true;
    }

    months.forEach((month, index) => {
        const newValue = processedData.value[`${selectedYear.value}-${month}`]?.bookings || 0;
        const oldValue = chart.data.datasets[3].data[index] ?? 0;
        if (oldValue !== newValue) {
            console.log(`Bookings [${month}]: ${oldValue} -> ${newValue}`);
            chart.data.datasets[3].data[index] = newValue;
            hasChanges = true;
        }
    });

    // Update current data (last 4 datasets) and visibility
    if (compareWithCurrent.value && snapshotDate.value) {
        console.log('Updating comparison datasets. Sample current data for Jan:',
            processedCurrentData.value[`${selectedYear.value}-01`]);

        months.forEach((month, index) => {
            const newPaid = processedCurrentData.value[`${selectedYear.value}-${month}`]?.paid || 0;
            const newUnpaid = processedCurrentData.value[`${selectedYear.value}-${month}`]?.unpaid || 0;
            const newForecast = processedCurrentData.value[`${selectedYear.value}-${month}`]?.forecast || 0;
            const newBookings = processedCurrentData.value[`${selectedYear.value}-${month}`]?.bookings || 0;

            if (chart.data.datasets[4].data[index] !== newPaid) {
                chart.data.datasets[4].data[index] = newPaid;
                hasChanges = true;
            }
            if (chart.data.datasets[5].data[index] !== newUnpaid) {
                chart.data.datasets[5].data[index] = newUnpaid;
                hasChanges = true;
            }
            if (chart.data.datasets[6].data[index] !== newForecast) {
                chart.data.datasets[6].data[index] = newForecast;
                hasChanges = true;
            }
            if (chart.data.datasets[7].data[index] !== newBookings) {
                chart.data.datasets[7].data[index] = newBookings;
                hasChanges = true;
            }
        });

        // Show comparison datasets if they're hidden
        [4, 5, 6, 7].forEach(i => {
            if (chart.data.datasets[i].hidden === true) {
                chart.data.datasets[i].hidden = false;
                hasChanges = true;
            }
        });
    } else {
        // Hide comparison datasets when not comparing
        [4, 5, 6, 7].forEach(i => {
            if (chart.data.datasets[i].hidden === false || chart.data.datasets[i].hidden === undefined) {
                chart.data.datasets[i].hidden = true;
                hasChanges = true;
            }
        });
    }

    // Update chart title directly (without modifying reactive chartOptions)
    const titleText = `Paid vs Unpaid Amounts and Booking Count for ${selectedYear.value}${snapshotDate.value && !compareWithCurrent.value ? ' (as of ' + new Date(snapshotDate.value).toLocaleDateString() + ')' : ''}${compareWithCurrent.value && snapshotDate.value ? ' (Comparison: ' + new Date(snapshotDate.value).toLocaleDateString() + ' vs Current)' : ''}`;
    if (chart.options.plugins.title.text !== titleText) {
        chart.options.plugins.title.text = titleText;
        hasChanges = true;
    }

    if (!hasChanges) {
        console.log('No changes detected, skipping chart update');
        return;
    }

    console.log('Changes detected, updating chart. Data after mutation:', {
        paidData: chart.data.datasets[0].data.slice(0, 3),
        unpaidData: chart.data.datasets[1].data.slice(0, 3),
    });
    
    // Update the chart with smooth animation
    // This will only animate the bars/lines that actually changed values
    chart.update();
    
    console.log('chart.update() completed');
};

const updateChartOptions = () => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue("--p-text-color");
    const textColorSecondary = documentStyle.getPropertyValue(
        "--p-text-muted-color"
    );
    const surfaceBorder = documentStyle.getPropertyValue(
        "--p-content-border-color"
    );

    chartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 0.8,
        animation: {
            duration: isInitialLoad.value ? 0 : 400, // No animation on initial load, smooth after
            easing: 'easeInOutQuart',
            onComplete: () => {
                // After first animation completes, enable future animations
                if (isInitialLoad.value) {
                    isInitialLoad.value = false;
                    chartOptions.value.animation.duration = 400;
                }
            }
        },
        plugins: {
            legend: {
                labels: {
                    color: textColor,
                },
            },
            title: {
                display: true,
                text: `Paid vs Unpaid Amounts and Booking Count for ${selectedYear.value}${snapshotDate.value && !compareWithCurrent.value ? ' (as of ' + new Date(snapshotDate.value).toLocaleDateString() + ')' : ''}${compareWithCurrent.value && snapshotDate.value ? ' (Comparison: ' + new Date(snapshotDate.value).toLocaleDateString() + ' vs Current)' : ''}`,
                color: textColor,
                font: {
                    size: 16,
                },
            },
            tooltip: {
                callbacks: {
                    label: function (context) {
                        let label = context.dataset.label || "";
                        if (label) {
                            label += ": ";
                        }
                        if (context.parsed.y !== null) {
                            if (context.datasetIndex < 2) {
                                label += new Intl.NumberFormat("en-US", {
                                    style: "currency",
                                    currency: "USD",
                                }).format(context.parsed.y);
                            } else {
                                label += context.parsed.y;
                            }
                        }
                        return label;
                    },
                },
            },
        },
        scales: {
            x: {
                ticks: {
                    color: textColor,
                },
                grid: {
                    color: surfaceBorder,
                },
            },
            "y-axis-1": {
                type: "linear",
                display: true,
                position: "left",
                ticks: {
                    color: textColorSecondary,
                    callback: function (value, index, values) {
                        return new Intl.NumberFormat("en-US", {
                            style: "currency",
                            currency: "USD",
                        }).format(value);
                    },
                },
                grid: {
                    color: surfaceBorder,
                },
            },
            "y-axis-2": {
                type: "linear",
                display: true,
                position: "right",
                ticks: {
                    color: textColorSecondary,
                },
                grid: {
                    drawOnChartArea: false,
                },
            },
        },
    };
};

watch(selectedYear, (newYear, oldYear) => {
    // Only update URL if user manually changed the year (not on mount)
    if (oldYear !== undefined) {
        const formattedDate = snapshotDate.value ? new Date(snapshotDate.value).toISOString().split('T')[0] : null;
        router.get(route('Paid/Unpaid'), {
            snapshot_date: formattedDate,
            compare_with_current: compareWithCurrent.value ? 1 : 0,
            year: newYear
        }, {
            preserveScroll: true,
            preserveState: true,
            only: [] // Don't fetch new data, just update URL
        });
    }
    updateChartData(); // Title and labels updated in updateChartData
});

watch(() => props.allBookings, () => {
    updateChartData(); // Data and title updated in updateChartData
}, { deep: true });

watch(snapshotDate, (newVal, oldVal) => {
    // Only update if the date actually changed (not just a new Date object)
    const newDateStr = newVal?.toISOString?.().split('T')[0];
    const oldDateStr = oldVal?.toISOString?.().split('T')[0];
    
    console.log('snapshotDate watcher fired:', { 
        oldVal: oldVal?.toDateString?.(), 
        newVal: newVal?.toDateString?.(),
        oldDateStr,
        newDateStr,
        actuallyChanged: oldDateStr !== newDateStr
    });
    
    // Skip update if date didn't actually change
    if (oldDateStr === newDateStr) {
        console.log('Date unchanged, skipping update');
        return;
    }
    
    // Client-side filtering, just update the chart data (title updated in updateChartData)
    updateChartData();
});

watch(() => props.compareWithCurrent, (newVal, oldVal) => {
    console.log('compareWithCurrent prop changed to:', newVal);
    compareWithCurrent.value = newVal;
    // Don't reset the year when comparison changes - keep current selection
    updateChartData(); // Title updated in updateChartData
});

// Sync timeline position when snapshotDate changes
watch(snapshotDate, (newVal) => {
    if (newVal) {
        timelinePosition.value = dateToPosition(newVal);
    }
});

onMounted(async () => {
    console.log('Component mounted with props:', {
        allBookings: props.allBookings.length + ' bands',
        snapshotDate: props.snapshotDate,
        compareWithCurrent: props.compareWithCurrent,
        selectedYear: props.selectedYear
    });

    // Use year from props if available, otherwise use current year or first available
    if (props.selectedYear && availableYears.value.includes(parseInt(props.selectedYear))) {
        selectedYear.value = parseInt(props.selectedYear);
    } else {
        const currentYear = new Date().getFullYear();
        if (availableYears.value.length > 0) {
            selectedYear.value = availableYears.value.includes(currentYear)
                ? currentYear
                : availableYears.value[0];
        }
    }

    // Initialize timeline position if snapshot date is set
    if (snapshotDate.value) {
        timelinePosition.value = dateToPosition(snapshotDate.value);
    }

    // Initialize chart structure, then populate with data
    initializeChartData();
    updateChartOptions();
    
    // Wait for Chart component to be fully initialized
    await nextTick();
    
    // Retry updateChartData until chart is ready (with max retries)
    let retries = 0;
    const maxRetries = 10;
    const retryInterval = 50; // ms
    
    const tryUpdate = async () => {
        const chart = chartRef.value?.chart;
        if (chart) {
            console.log('Chart ready, updating data');
            updateChartData();
        } else if (retries < maxRetries) {
            retries++;
            console.log(`Chart not ready, retry ${retries}/${maxRetries}`);
            setTimeout(tryUpdate, retryInterval);
        } else {
            console.error('Chart failed to initialize after', maxRetries, 'retries');
        }
    };
    
    tryUpdate();
});
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 400ms cubic-bezier(0.165, 0.84, 0.44, 1);
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
