import { config } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import { vi } from 'vitest'
import { ref, computed } from 'vue'
import './mocks/matchMediaMock'

// Mock vue-currency-input
vi.mock('vue-currency-input', () => ({
	default: (options, componentProps) => {
		// Get initial value from options or component props
		const initialValue = options?.modelValue || componentProps?.value?.modelValue || 0
		const modelValue = ref(initialValue)
		const inputRef = ref(null)

		// Format the value as currency
		const formatCurrency = (value) => {
			if (value === null || value === undefined || value === 0) return '$0.00'
			const formatted = new Intl.NumberFormat('en-US', {
				style: 'currency',
				currency: 'USD',
			}).format(value)
			return formatted
		}

		const formattedValue = computed(() => formatCurrency(componentProps?.value?.modelValue || modelValue.value))

		return {
			formattedValue,
			inputRef,
			numberValue: modelValue,
			setValue: (val) => {
				modelValue.value = val
			}
		}
	}
}))

config.global.plugins = [
	[ PrimeVue ],
	ToastService,
]

config.global.components = {
	Dialog,
	Button,
	InputText,
	InputNumber,
	Select,
	DatePicker,
	Calendar: DatePicker, // Alias for backward compatibility
}
