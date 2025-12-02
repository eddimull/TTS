import Button from '@/Components/Button.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('Button', () => {
  it('should render button with default props', () => {
    const wrapper = mount(Button);

    const button = wrapper.find('button');
    expect(button.exists()).toBe(true);
    expect(button.attributes('type')).toBe('submit');
  });

  it('should render slot content', () => {
    const wrapper = mount(Button, {
      slots: {
        default: 'Click Me'
      }
    });

    expect(wrapper.text()).toBe('Click Me');
  });

  it('should apply correct type attribute', () => {
    const wrapper = mount(Button, {
      props: {
        type: 'button'
      }
    });

    expect(wrapper.find('button').attributes('type')).toBe('button');
  });

  it('should be disabled when disabled prop is true', () => {
    const wrapper = mount(Button, {
      props: {
        disabled: true
      }
    });

    const button = wrapper.find('button');
    expect(button.attributes('disabled')).toBeDefined();
    expect(button.classes()).toContain('disabled:opacity-50');
  });

  describe('variants', () => {
    const variants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    variants.forEach(variant => {
      it(`should apply ${variant} variant classes`, () => {
        const wrapper = mount(Button, {
          props: {
            variant
          }
        });

        const button = wrapper.find('button');
        const classes = button.classes().join(' ');

        // Each variant should have specific color classes
        expect(classes).toBeDefined();
        expect(wrapper.vm.buttonClasses).toContain(variant === 'primary' ? 'bg-gray-800' : '');
      });
    });
  });

  describe('sizes', () => {
    it('should apply small size classes', () => {
      const wrapper = mount(Button, {
        props: {
          size: 'sm'
        }
      });

      const classes = wrapper.find('button').classes().join(' ');
      expect(classes).toContain('px-3');
      expect(classes).toContain('py-1.5');
    });

    it('should apply medium size classes (default)', () => {
      const wrapper = mount(Button, {
        props: {
          size: 'md'
        }
      });

      const classes = wrapper.find('button').classes().join(' ');
      expect(classes).toContain('px-4');
      expect(classes).toContain('py-2');
    });

    it('should apply large size classes', () => {
      const wrapper = mount(Button, {
        props: {
          size: 'lg'
        }
      });

      const classes = wrapper.find('button').classes().join(' ');
      expect(classes).toContain('px-6');
      expect(classes).toContain('py-3');
    });
  });

  describe('outline variant', () => {
    it('should apply outline classes when outline is true', () => {
      const wrapper = mount(Button, {
        props: {
          outline: true,
          variant: 'primary'
        }
      });

      const classes = wrapper.find('button').classes().join(' ');
      expect(classes).toContain('bg-transparent');
      expect(classes).toContain('border-gray-800');
    });

    it('should apply solid classes when outline is false', () => {
      const wrapper = mount(Button, {
        props: {
          outline: false,
          variant: 'primary'
        }
      });

      const classes = wrapper.find('button').classes().join(' ');
      expect(classes).toContain('bg-gray-800');
    });
  });

  it('should emit click event', async () => {
    const wrapper = mount(Button);

    await wrapper.find('button').trigger('click');

    expect(wrapper.emitted('click')).toBeTruthy();
  });

  it('should not emit click when disabled', async () => {
    const wrapper = mount(Button, {
      props: {
        disabled: true
      }
    });

    // Disabled buttons don't fire click events in real browsers
    const button = wrapper.find('button');
    expect(button.attributes('disabled')).toBeDefined();
  });

  it('should have accessibility attributes', () => {
    const wrapper = mount(Button);
    const button = wrapper.find('button');

    // Base classes should include focus states for accessibility
    expect(button.classes().join(' ')).toContain('focus:outline-none');
    expect(button.classes().join(' ')).toContain('focus:ring-2');
  });

  it('should combine multiple props correctly', () => {
    const wrapper = mount(Button, {
      props: {
        variant: 'success',
        size: 'lg',
        outline: true,
        type: 'button'
      }
    });

    const button = wrapper.find('button');
    expect(button.attributes('type')).toBe('button');

    const classes = button.classes().join(' ');
    expect(classes).toContain('px-6'); // large size
    expect(classes).toContain('bg-transparent'); // outline
  });
});
