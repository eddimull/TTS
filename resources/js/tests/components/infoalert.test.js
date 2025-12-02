import InfoAlert from '@/Components/InfoAlert.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('InfoAlert', () => {
  it('should render with default info variant', () => {
    const wrapper = mount(InfoAlert, {
      slots: {
        default: 'This is an info message'
      }
    });

    expect(wrapper.text()).toContain('This is an info message');
    expect(wrapper.find('.bg-blue-50').exists()).toBe(true);
  });

  it('should render slot content', () => {
    const wrapper = mount(InfoAlert, {
      slots: {
        default: '<strong>Important</strong> information'
      }
    });

    expect(wrapper.html()).toContain('<strong>Important</strong>');
  });

  describe('variants', () => {
    it('should apply info variant styles', () => {
      const wrapper = mount(InfoAlert, {
        props: {
          variant: 'info'
        }
      });

      expect(wrapper.find('.bg-blue-50').exists()).toBe(true);
      expect(wrapper.find('.border-blue-200').exists()).toBe(true);
      expect(wrapper.find('.pi-info-circle').exists()).toBe(true);
    });

    it('should apply warning variant styles', () => {
      const wrapper = mount(InfoAlert, {
        props: {
          variant: 'warning'
        }
      });

      expect(wrapper.find('.bg-yellow-50').exists()).toBe(true);
      expect(wrapper.find('.border-yellow-200').exists()).toBe(true);
      expect(wrapper.find('.pi-exclamation-triangle').exists()).toBe(true);
    });

    it('should apply success variant styles', () => {
      const wrapper = mount(InfoAlert, {
        props: {
          variant: 'success'
        }
      });

      expect(wrapper.find('.bg-green-50').exists()).toBe(true);
      expect(wrapper.find('.border-green-200').exists()).toBe(true);
      expect(wrapper.find('.pi-check-circle').exists()).toBe(true);
    });

    it('should apply error variant styles', () => {
      const wrapper = mount(InfoAlert, {
        props: {
          variant: 'error'
        }
      });

      expect(wrapper.find('.bg-red-50').exists()).toBe(true);
      expect(wrapper.find('.border-red-200').exists()).toBe(true);
      expect(wrapper.find('.pi-times-circle').exists()).toBe(true);
    });
  });

  describe('icon rendering', () => {
    const iconMap = {
      info: 'pi-info-circle',
      warning: 'pi-exclamation-triangle',
      success: 'pi-check-circle',
      error: 'pi-times-circle'
    };

    Object.entries(iconMap).forEach(([variant, iconClass]) => {
      it(`should render ${iconClass} for ${variant} variant`, () => {
        const wrapper = mount(InfoAlert, {
          props: {
            variant
          }
        });

        const icon = wrapper.find('i');
        expect(icon.exists()).toBe(true);
        expect(icon.classes()).toContain('pi');
        expect(icon.classes()).toContain(iconClass);
      });
    });
  });

  it('should have proper layout structure', () => {
    const wrapper = mount(InfoAlert, {
      slots: {
        default: 'Test message'
      }
    });

    // Should have container with rounded border
    expect(wrapper.find('.rounded-lg').exists()).toBe(true);
    expect(wrapper.find('.border').exists()).toBe(true);

    // Should have flex layout
    expect(wrapper.find('.flex').exists()).toBe(true);
    expect(wrapper.find('.items-start').exists()).toBe(true);

    // Icon should have margin
    const icon = wrapper.find('i');
    expect(icon.classes()).toContain('mr-2');
  });

  it('should apply dark mode classes', () => {
    const wrapper = mount(InfoAlert, {
      props: {
        variant: 'info'
      }
    });

    const container = wrapper.find('div');
    const classes = container.classes().join(' ');

    // Should have dark mode variants
    expect(classes).toContain('dark:bg-blue-900/20');
    expect(classes).toContain('dark:border-blue-800');
  });

  it('should render HTML content in slot', () => {
    const wrapper = mount(InfoAlert, {
      slots: {
        default: `
          <div>
            <p>Line 1</p>
            <p>Line 2</p>
          </div>
        `
      }
    });

    expect(wrapper.findAll('p')).toHaveLength(2);
  });

  it('should validate variant prop', () => {
    const wrapper = mount(InfoAlert, {
      props: {
        variant: 'info'
      }
    });

    const validVariants = ['info', 'warning', 'success', 'error'];
    const validator = wrapper.vm.$options.props.variant.validator;

    validVariants.forEach(variant => {
      expect(validator(variant)).toBe(true);
    });

    expect(validator('invalid')).toBe(false);
  });

  it('should have proper text styling', () => {
    const wrapper = mount(InfoAlert, {
      slots: {
        default: 'Test'
      }
    });

    const textContainer = wrapper.find('.text-sm');
    expect(textContainer.exists()).toBe(true);
    expect(textContainer.classes()).toContain('text-gray-700');
    expect(textContainer.classes()).toContain('dark:text-gray-300');
  });
});
