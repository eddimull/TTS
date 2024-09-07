import TextInput from '@/Components/TextInput.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, beforeEach } from "vitest";

const defaultValues = {
    modelValue: 'Hello World!',
    name: 'test',
    placeholder: 'Type something here...',
    label: 'Label name',
    'onUpdate:modelValue': () => {}
}

describe('TextInput', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = mount(TextInput, {
            props: { ...defaultValues }
        });
    });

    it('should render a text input', () => {
        expect(wrapper.find('input').exists()).toBe(true);
        expect(wrapper.find('input').element.value).toBe(defaultValues.modelValue);
    });

    it('should have a placeholder when provided', () => {
        expect(wrapper.find('input').attributes('placeholder')).toBe(defaultValues.placeholder);
    });

    it('should have a label when provided', () => {
        expect(wrapper.find('label').text()).toBe(defaultValues.label);
        expect(wrapper.find('label').attributes('for')).toBe(defaultValues.name);
    });

    it('should show an append slot', () => {
        const wrapper = mount(TextInput, {
            props: { ...defaultValues },
            slots: {
                append: '<span class="input-group-text">Hello, Universe!</span>'
            }
        });
        expect(wrapper.find('.input-group-text').exists()).toBe(true);
    });

    it('should change the value when the input is changed', async () => {
        const newValue = 'Hello, Universe!';
        const input = wrapper.find('input');
        await input.setValue(newValue);
        expect(input.element.value).toBe(newValue);
    });
});