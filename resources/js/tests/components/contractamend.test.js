import { describe, expect, it, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';

// Mock route helper (Ziggy's route() is a bare global in <script setup> components)
global.route = vi.fn((name, params) => `/${name}/${JSON.stringify(params)}`);

// Mock Inertia - use factory function to avoid hoisting issues
vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

import Contract from '@/Pages/Bookings/Contract.vue';
import { router } from '@inertiajs/vue3';

const pendingBooking = {
    id: 5,
    band_id: 1,
    status: 'pending',
    contract_option: 'default',
    contract: { asset_url: null },
};

const mountPage = (booking) =>
    mount(Contract, {
        props: { booking, band: { id: 1, name: 'Band' } },
        global: {
            stubs: { ContractNone: true, ContractExternal: true, ContractEditor: true, ContractStatus: true },
        },
    });

describe('Contract.vue amend button', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockClear();
    });

    it('shows Amend contract on a pending default contract and posts on confirm', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = mountPage(pendingBooking);

        expect(wrapper.text()).toContain('Amend contract');
        await wrapper.find('[data-testid="amend-contract"]').trigger('click');

        expect(router.post).toHaveBeenCalledTimes(1);
    });

    it('does not post when the confirm dialog is cancelled', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        vi.mocked(router.post).mockClear();
        const wrapper = mountPage(pendingBooking);

        await wrapper.find('[data-testid="amend-contract"]').trigger('click');
        expect(router.post).not.toHaveBeenCalled();
    });

    it('hides the button when the booking is confirmed', () => {
        const wrapper = mountPage({ ...pendingBooking, status: 'confirmed' });
        expect(wrapper.text()).not.toContain('Amend contract');
    });
});
