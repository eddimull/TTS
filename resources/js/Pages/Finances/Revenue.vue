<template>
  <FinanceLayout>
    <div class="mx-4 my-6 space-y-8">
      <!-- Page Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
          Revenue Overview
        </h1>
      </div>

      <!-- Bands Revenue -->
      <div
        v-for="band in revenue"
        :key="band.name"
        class="componentPanel shadow-lg rounded-lg p-6"
      >
        <h2 class="text-2xl font-semibold mb-6 text-gray-800 dark:text-gray-100 border-b pb-3">
          {{ band.name }}
        </h2>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <!-- Total Revenue Card -->
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900 dark:to-blue-800 rounded-lg p-6 shadow-md">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-blue-600 dark:text-blue-300 mb-1">
                  Total Revenue
                </p>
                <p class="text-3xl font-bold text-blue-900 dark:text-blue-50">
                  {{ moneyFormat(totalRevenue(band.payments)) }}
                </p>
              </div>
              <div class="bg-blue-500 dark:bg-blue-600 p-3 rounded-full">
                <i class="pi pi-dollar text-white text-2xl" />
              </div>
            </div>
          </div>

          <!-- Current Year Card -->
          <div
            v-if="currentYearRevenue(band.payments)"
            class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900 dark:to-green-800 rounded-lg p-6 shadow-md"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-green-600 dark:text-green-300 mb-1">
                  {{ currentYear }} Revenue
                </p>
                <p class="text-3xl font-bold text-green-900 dark:text-green-50">
                  {{ moneyFormat(currentYearRevenue(band.payments)) }}
                </p>
              </div>
              <div class="bg-green-500 dark:bg-green-600 p-3 rounded-full">
                <i class="pi pi-calendar text-white text-2xl" />
              </div>
            </div>
          </div>

          <!-- Years Active Card -->
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900 dark:to-purple-800 rounded-lg p-6 shadow-md">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-purple-600 dark:text-purple-300 mb-1">
                  Years Active
                </p>
                <p class="text-3xl font-bold text-purple-900 dark:text-purple-50">
                  {{ band.payments.length }}
                </p>
              </div>
              <div class="bg-purple-500 dark:bg-purple-600 p-3 rounded-full">
                <i class="pi pi-chart-line text-white text-2xl" />
              </div>
            </div>
          </div>
        </div>

        <!-- Revenue Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-md">
          <h3 class="text-lg font-semibold mb-4 text-gray-700 dark:text-gray-200">
            Revenue by Year
          </h3>
          <Chart
            type="bar"
            :data="getChartData(band.payments)"
            :options="chartOptions"
            class="h-[400px]"
          />
        </div>

        <!-- Revenue Table -->
        <div class="mt-6 overflow-hidden rounded-lg shadow-md">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th
                  scope="col"
                  class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                >
                  Year
                </th>
                <th
                  scope="col"
                  class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                >
                  Revenue
                </th>
                <th
                  scope="col"
                  class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                >
                  Change
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="(payment, index) in band.payments"
                :key="index"
                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ payment.year }}
                  <span
                    v-if="payment.year === currentYear"
                    class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100"
                  >
                    Current
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100 font-semibold">
                  {{ moneyFormat(payment.total / 100) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                  <span v-if="index < band.payments.length - 1">
                    <span
                      v-if="getYearOverYearChange(band.payments, index) > 0"
                      class="text-green-600 dark:text-green-400 font-medium"
                    >
                      <i class="pi pi-arrow-up text-xs" />
                      {{ formatPercentage(getYearOverYearChange(band.payments, index)) }}%
                    </span>
                    <span
                      v-else-if="getYearOverYearChange(band.payments, index) < 0"
                      class="text-red-600 dark:text-red-400 font-medium"
                    >
                      <i class="pi pi-arrow-down text-xs" />
                      {{ formatPercentage(Math.abs(getYearOverYearChange(band.payments, index))) }}%
                    </span>
                    <span
                      v-else
                      class="text-gray-500 dark:text-gray-400"
                    >
                      —
                    </span>
                  </span>
                  <span
                    v-else
                    class="text-gray-400 dark:text-gray-500"
                  >
                    N/A
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-100 dark:bg-gray-700">
              <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-gray-100">
                  Total
                </td>
                <td
                  colspan="2"
                  class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900 dark:text-gray-100"
                >
                  {{ moneyFormat(totalRevenue(band.payments)) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </FinanceLayout>
</template>
    
<script setup>
import { ref, computed, onMounted } from 'vue'
import FinanceLayout from './Layout/FinanceLayout.vue'
import Chart from 'primevue/chart'

const props = defineProps({
  revenue: {
    type: Object,
    required: true
  }
})

const currentYear = new Date().getFullYear()
const chartOptions = ref({})

const moneyFormat = (number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(number)
}

const totalRevenue = (payments) => {
  return payments.reduce((sum, payment) => sum + (payment.total / 100), 0)
}

const currentYearRevenue = (payments) => {
  const currentYearPayment = payments.find(p => p.year === currentYear)
  return currentYearPayment ? currentYearPayment.total / 100 : 0
}

const getYearOverYearChange = (payments, index) => {
  if (index >= payments.length - 1) return 0
  
  const currentRevenue = payments[index].total
  const previousRevenue = payments[index + 1].total
  
  if (previousRevenue === 0) return 0
  
  return ((currentRevenue - previousRevenue) / previousRevenue) * 100
}

const formatPercentage = (value) => {
  return value.toFixed(1)
}

const getChartData = (payments) => {
  // Sort by year ascending for chart
  const sortedPayments = [...payments].sort((a, b) => a.year - b.year)
  
  return {
    labels: sortedPayments.map(p => p.year.toString()),
    datasets: [
      {
        label: 'Revenue',
        data: sortedPayments.map(p => p.total / 100),
        backgroundColor: sortedPayments.map(p => 
          p.year === currentYear ? '#10b981' : '#3b82f6'
        ),
        borderColor: sortedPayments.map(p => 
          p.year === currentYear ? '#059669' : '#2563eb'
        ),
        borderWidth: 2,
        borderRadius: 6,
        hoverBackgroundColor: sortedPayments.map(p => 
          p.year === currentYear ? '#059669' : '#2563eb'
        )
      }
    ]
  }
}

const updateChartOptions = () => {
  const documentStyle = getComputedStyle(document.documentElement)
  const textColor = documentStyle.getPropertyValue('--p-text-color')
  const textColorSecondary = documentStyle.getPropertyValue('--p-text-muted-color')
  const surfaceBorder = documentStyle.getPropertyValue('--p-content-border-color')

  chartOptions.value = {
    maintainAspectRatio: false,
    aspectRatio: 0.8,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        callbacks: {
          label: function (context) {
            return 'Revenue: ' + new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: 'USD'
            }).format(context.parsed.y)
          }
        }
      }
    },
    scales: {
      x: {
        ticks: {
          color: textColor,
          font: {
            size: 14,
            weight: 'bold'
          }
        },
        grid: {
          display: false
        }
      },
      y: {
        ticks: {
          color: textColorSecondary,
          callback: function (value) {
            return new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: 'USD',
              notation: 'compact',
              compactDisplay: 'short'
            }).format(value)
          }
        },
        grid: {
          color: surfaceBorder
        }
      }
    }
  }
}

onMounted(() => {
  updateChartOptions()
})
</script>