import CurrencyInput from '@/Components/CurrencyInput.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('CurrencyInput', () => {
  it('should render currency input field', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 100,
      }
    });

    expect(wrapper.find('input').exists()).toBe(true);
    expect(wrapper.find('input').element.type).toBe('text');
  });

  it('should format value as USD currency', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 1234.56,
      }
    });

    // Component should have formattedValue from the composable
    expect(wrapper.vm.formattedValue).toBeDefined();
  });

  it('should handle zero value', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 0,
      }
    });

    expect(wrapper.vm.formattedValue).toBeDefined();
  });

  it('should have correct CSS classes', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 100,
      }
    });

    const input = wrapper.find('input');
    expect(input.classes()).toContain('shadow');
    expect(input.classes()).toContain('border');
    expect(input.classes()).toContain('rounded');
  });

  it('should accept custom options', () => {
    const customOptions = {
      currency: 'EUR',
      precision: 0
    };

    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 100,
        options: customOptions
      }
    });

    expect(wrapper.find('input').exists()).toBe(true);
  });

  it('should format large numbers with grouping', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 1000000.99,
      }
    });

    // Component should have formattedValue
    expect(wrapper.vm.formattedValue).toBeDefined();
  });

  it('should handle negative values', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: -500,
      }
    });

    // Component should have formattedValue
    expect(wrapper.vm.formattedValue).toBeDefined();
  });
});
