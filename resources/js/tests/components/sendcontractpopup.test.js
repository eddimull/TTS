import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

// The popup imports its PrimeVue components locally. VTU's `global.stubs`
// relies on a dev-only Vue hook that the production build used by
// `npm run test:pipeline` compiles out, so stub them with vi.mock instead,
// which works in both builds. Render functions (not template strings) keep
// the stubs independent of the runtime-only Vue build.
// vi.mock calls are hoisted above plain declarations, so build the factory
// in vi.hoisted.
const { passthrough } = vi.hoisted(() => ({
    passthrough: (tag = 'div') => async () => {
        const { h } = await import('vue');
        return {
            default: {
                render() {
                    return h(tag, this.$slots.default?.());
                },
            },
        };
    },
}));

vi.mock('primevue/dialog', passthrough());
vi.mock('primevue/stepper', passthrough());
vi.mock('primevue/steplist', passthrough());
vi.mock('primevue/steppanels', passthrough());
vi.mock('primevue/stepitem', passthrough());
vi.mock('primevue/step', passthrough());
vi.mock('primevue/progressspinner', passthrough('span'));

vi.mock('primevue/steppanel', async () => {
    const { h } = await import('vue');
    return {
        default: {
            props: ['value'],
            render() {
                return h(
                    'section',
                    { 'data-step': this.value },
                    this.$slots.default?.({ activateCallback: () => {} }),
                );
            },
        },
    };
});

vi.mock('primevue/button', async () => {
    const { h } = await import('vue');
    return {
        default: {
            props: ['label', 'disabled'],
            emits: ['click'],
            render() {
                return h(
                    'button',
                    { disabled: this.disabled, onClick: () => this.$emit('click') },
                    this.label,
                );
            },
        },
    };
});

// Listbox stub that exposes its options and lets the test pick values.
vi.mock('primevue/listbox', async () => {
    const { h } = await import('vue');
    return {
        default: {
            name: 'Listbox',
            props: ['modelValue', 'options', 'optionValue', 'multiple'],
            emits: ['update:modelValue'],
            render() {
                return h(
                    'ul',
                    { 'data-testid': this.$attrs['data-testid'] },
                    (this.options ?? []).map((o) => h('li', { key: o.id, 'data-id': o.id }, o.name)),
                );
            },
        },
    };
});

import ListboxStub from 'primevue/listbox';
import SendContractPopup from '@/Pages/Bookings/Components/SendContractPopup.vue';

const contacts = [
    { id: 1, name: 'Bride Person', email: 'bride@example.com' },
    { id: 2, name: 'Groom Person', email: 'groom@example.com' },
    { id: 3, name: 'Planner Person', email: 'planner@example.com' },
];

const mountPopup = (props = {}) =>
    mount(SendContractPopup, {
        props: { show: false, contacts, ...props },
    });

const signerListbox = (wrapper) =>
    wrapper.findAllComponents(ListboxStub).find((c) => c.attributes('data-testid') === 'signer-listbox');
const ccListbox = (wrapper) =>
    wrapper.findAllComponents(ListboxStub).find((c) => c.attributes('data-testid') === 'cc-listbox');
const recipientNextButton = (wrapper) =>
    wrapper.find('[data-step="2"]').findAll('button').find((b) => b.text() === 'Next');

describe('SendContractPopup', () => {
    it('uses recipient(s) wording on the first step', () => {
        const wrapper = mountPopup();
        expect(wrapper.find('[data-step="1"]').text()).toContain('recipient(s)');
    });

    it('keeps Next disabled until at least one signer is picked', async () => {
        const wrapper = mountPopup();
        await wrapper.setProps({ show: true });

        expect(recipientNextButton(wrapper).attributes('disabled')).toBeDefined();

        await signerListbox(wrapper).vm.$emit('update:modelValue', [1]);
        expect(recipientNextButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('lets you pick several signers and leaves them out of the CC options', async () => {
        const wrapper = mountPopup();
        await wrapper.setProps({ show: true });

        expect(signerListbox(wrapper).props('options').map((c) => c.id)).toEqual([1, 2, 3]);

        await signerListbox(wrapper).vm.$emit('update:modelValue', [1, 2]);

        expect(ccListbox(wrapper).props('options').map((c) => c.id)).toEqual([3]);
        expect(wrapper.text()).toContain('Bride Person and Groom Person');
    });

    it('drops a contact from the CC list once it becomes a signer', async () => {
        const wrapper = mountPopup();
        await wrapper.setProps({ show: true });

        await signerListbox(wrapper).vm.$emit('update:modelValue', [1]);
        await ccListbox(wrapper).vm.$emit('update:modelValue', [2, 3]);
        await signerListbox(wrapper).vm.$emit('update:modelValue', [1, 2]);
        await nextTick();

        expect(wrapper.vm.selectedCCContactIds).toEqual([3]);
    });

    it('emits signers and cc arrays on finish', async () => {
        // A listener prop, not wrapper.emitted(): VTU's emit tracking uses a
        // dev-only Vue hook that the pipeline's production build strips out.
        const onConfirm = vi.fn();
        const wrapper = mountPopup({ onConfirm });
        await wrapper.setProps({ show: true });

        await signerListbox(wrapper).vm.$emit('update:modelValue', [2, 1]);
        await ccListbox(wrapper).vm.$emit('update:modelValue', [3]);
        await wrapper.find('[data-step="3"]').findAll('button').find((b) => b.text() === 'Finish').trigger('click');

        expect(onConfirm).toHaveBeenCalledExactlyOnceWith({ signers: [2, 1], cc: [3] });
    });

    it('preselects the only contact as signer', async () => {
        const wrapper = mountPopup({ contacts: [contacts[0]] });
        await wrapper.setProps({ show: true });

        expect(wrapper.vm.selectedSignerIds).toEqual([1]);
        expect(recipientNextButton(wrapper).attributes('disabled')).toBeUndefined();
        // Nobody left to CC
        expect(ccListbox(wrapper)).toBeUndefined();
    });
});
