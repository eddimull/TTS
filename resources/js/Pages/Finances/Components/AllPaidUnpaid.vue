<template>
  <div class="bg-white shadow-md rounded-lg p-6 mx-auto">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">
      Paid vs Unpaid Amounts and Booking Count by Month
    </h2>
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center">
      <div class="mb-4 md:mb-0">
        <label
          for="year-select"
          class="mr-2 text-gray-700 font-medium"
        >Select Year:</label>
        <select
          id="year-select"
          v-model="selectedYear"
          class="p-2 pr-8 border rounded shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
      <div class="bg-gray-100 p-4 rounded-lg shadow-inner">
        <h3 class="text-lg font-semibold mb-2 text-gray-800">
          Year Totals
        </h3>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <dt class="font-medium text-gray-600 dark:text-gray-50">
            Total paid:
          </dt>
          <dd class="text-gray-800">
            {{ moneyFormat(yearTotals.paid) }}
          </dd>
          <dt class="font-medium text-gray-600 dark:text-gray-50">
            Total unpaid:
          </dt>
          <dd class="text-gray-800">
            {{ moneyFormat(yearTotals.unpaid) }}
          </dd>
          <dt class="font-medium text-gray-600 dark:text-gray-50">
            Total bookings:
          </dt>
          <dd class="text-gray-800">
            {{ yearTotals.bookings }}
          </dd>
        </dl>
      </div>
    </div>
    <Chart
      type="bar"
      :data="chartData"
      :options="chartOptions"
      class="h-[600px] w-full"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import Chart from 'primevue/chart';

const props = defineProps({
  paidUnpaid: {
    type: Array,
    required: true
  }
});

const chartData = ref({});
const chartOptions = ref({});
const selectedYear = ref(new Date().getFullYear());

const allBookings = computed(() => {
  return props.paidUnpaid.reduce((acc, band) => {
    return acc.concat(band.paidBookings, band.unpaidBookings);
  }, []);
});

const availableYears = computed(() => {
  const years = new Set(allBookings.value.map(booking => new Date(booking.date).getFullYear()));
  return Array.from(years).sort((a, b) => b - a);
});

const processedData = computed(() => {
  const dataByMonth = {};
  
  allBookings.value.forEach(booking => {
    const date = new Date(booking.date);
    const year = date.getFullYear();
    if (year !== selectedYear.value) return;
    
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const yearMonth = `${year}-${month}`;
    
    if (!dataByMonth[yearMonth]) {
      dataByMonth[yearMonth] = { paid: 0, unpaid: 0, bookings: 0 };
    }
    
    const price = parseFloat(booking.price) || 0;
    const amountPaid = parseFloat(booking.amount_paid) || 0;

    dataByMonth[yearMonth].paid += amountPaid;
    dataByMonth[yearMonth].unpaid += Math.max(0, price - amountPaid);
    dataByMonth[yearMonth].bookings += 1;
  });

  return dataByMonth;
});

const moneyFormat = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

const yearTotals = computed(() => {
  return Object.values(processedData.value).reduce(
    (acc, month) => {
      acc.paid += month.paid;
      acc.unpaid += month.unpaid;
      acc.bookings += month.bookings;
      return acc;
    },
    { paid: 0, unpaid: 0, bookings: 0 }
  );
});

const updateChartData = () => {
  const months = [
    '01', '02', '03', '04', '05', '06',
    '07', '08', '09', '10', '11', '12'
  ];
  
  chartData.value = {
    labels: months.map(month => `${selectedYear.value}-${month}`),
    datasets: [
      {
        type: 'bar',
        label: 'Paid Amount',
        backgroundColor: '#42A5F5',
        data: months.map(month => processedData.value[`${selectedYear.value}-${month}`]?.paid || 0),
        yAxisID: 'y-axis-1'
      },
      {
        type: 'bar',
        label: 'Unpaid Amount',
        backgroundColor: '#777',
        data: months.map(month => processedData.value[`${selectedYear.value}-${month}`]?.unpaid || 0),
        yAxisID: 'y-axis-1'
      },
      {
        type: 'line',
        label: 'Number of Bookings',
        borderColor: '#FFA500',
        borderWidth: 2,
        fill: false,
        data: months.map(month => processedData.value[`${selectedYear.value}-${month}`]?.bookings || 0),
        yAxisID: 'y-axis-2'
      }
    ]
  };
};

const updateChartOptions = () => {
  chartOptions.value = {
    maintainAspectRatio: false,
    aspectRatio: 0.8,
    plugins: {
      legend: {
        labels: {
          color: '#495057'
        }
      },
      title: {
        display: true,
        text: `Paid vs Unpaid Amounts and Booking Count for ${selectedYear.value}`,
        font: {
          size: 16
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            let label = context.dataset.label || '';
            if (label) {
              label += ': ';
            }
            if (context.parsed.y !== null) {
              if (context.datasetIndex < 2) {
                label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
              } else {
                label += context.parsed.y;
              }
            }
            return label;
          }
        }
      }
    },
    scales: {
      x: {
        ticks: {
          color: '#495057'
        },
        grid: {
          color: '#ebedef'
        }
      },
      'y-axis-1': {
        type: 'linear',
        display: true,
        position: 'left',
        ticks: {
          color: '#495057',
          callback: function(value, index, values) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
          }
        },
        grid: {
          color: '#ebedef'
        }
      },
      'y-axis-2': {
        type: 'linear',
        display: true,
        position: 'right',
        ticks: {
          color: '#495057'
        },
        grid: {
          drawOnChartArea: false
        }
      }
    }
  };
};

watch(selectedYear, () => {
  updateChartData();
  updateChartOptions();
});

onMounted(() => {
  const currentYear = new Date().getFullYear();
  if (availableYears.value.length > 0) {
    selectedYear.value = availableYears.value.includes(currentYear) ? currentYear : availableYears.value[0];
  }
  updateChartData();
  updateChartOptions();
});
</script>