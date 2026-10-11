import { ref } from 'vue';
import axios from 'axios';

/**
 * Calls POST /bands/{band}/price-estimate (owner-only JSON endpoint, same
 * request/response shape as the mobile API).
 */
export function usePriceEstimate(bandId) {
    const result = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const fieldErrors = ref({});

    async function fetchEstimate(payload) {
        loading.value = true;
        error.value = null;
        fieldErrors.value = {};
        try {
            const response = await axios.post(`/bands/${bandId}/price-estimate`, payload);
            result.value = response.data;
            return response.data;
        } catch (e) {
            result.value = null;
            if (e.response?.status === 422) {
                fieldErrors.value = e.response.data?.errors ?? {};
                error.value = Object.values(fieldErrors.value).flat()[0] ?? 'Please check the form.';
            } else if (e.response?.status === 403) {
                error.value = 'Only band owners can use the price estimator.';
            } else {
                error.value = 'Could not get a price estimate. Please try again.';
            }
            return null;
        } finally {
            loading.value = false;
        }
    }

    return { result, loading, error, fieldErrors, fetchEstimate };
}

export function formatMoney(value) {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatSignedMoney(value) {
    if (value === null || value === undefined) return '';
    const formatted = formatMoney(Math.abs(value));
    if (Math.abs(value) < 0.5) return formatMoney(0);
    return value < 0 ? `−${formatted}` : `+${formatted}`;
}
