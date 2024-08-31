import { config } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import './mocks/matchMediaMock'


config.global.plugins = [
	[ PrimeVue ],
	ToastService,
]
