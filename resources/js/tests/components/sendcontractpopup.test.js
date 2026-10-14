import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import SendContractPopup from '@/Pages/Bookings/Components/SendContractPopup.vue';

const contacts = [
    { id: 1, name: 'Bride Person', email: 'bride@example.com' },
    { id: 2, name: 'Groom Person', email: 'groom@example.com' },
    { id: 3, name: 'Planner Person', email: 'planner@example.com' },
];

// Listbox stub that exposes its options and lets the test pick values.
const ListboxStub = {
    name: 'Listbox',
    props: ['modelValue', 'options', 'optionValue', 'multiple'],
    emits: ['update:modelValue'],
    template:
        '<ul :data-testid="$attrs[\'data-testid\']"><li v-for="o in options" :key="o.id" :data-id="o.id">{{ o.name }}</li></ul>',
};

const mountPopup = (props = {}) =>
    mount(SendContractPopup, {
        props: { show: false, contacts, ...props },
        global: {
            stubs: {
                Dialog: { template: '<div><slot /></div>' },
                Stepper: { template: '<div><slot /></div>' },
                StepList: { template: '<div><slot /></div>' },
                Step: { template: '<div><slot /></div>' },
                StepPanels: { template: '<div><slot /></div>' },
                StepPanel: {
                    props: ['value'],
                    template: '<section :data-step="value"><slot :activateCallback="() => {}" /></section>',
                },
                Button: {
                    props: ['label', 'disabled'],
                    emits: ['click'],
                    template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
                },
                Listbox: ListboxStub,
                ProgressSpinner: true,
                Transition: { template: '<div><slot /></div>' },
            },
        },
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
        const wrapper = mountPopup();
        await wrapper.setProps({ show: true });

        await signerListbox(wrapper).vm.$emit('update:modelValue', [2, 1]);
        await ccListbox(wrapper).vm.$emit('update:modelValue', [3]);
        await wrapper.find('[data-step="3"]').findAll('button').find((b) => b.text() === 'Finish').trigger('click');

        expect(wrapper.emitted('confirm')).toEqual([[{ signers: [2, 1], cc: [3] }]]);
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
