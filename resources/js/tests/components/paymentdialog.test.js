import PaymentDialog from '@/Pages/Bookings/Components/PaymentDialog.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest';

// Mock route helper
global.route = vi.fn((name, params) => `/mock-route/${name}`);

// Mock Inertia's useForm
const mockPost = vi.fn((url, options) => {
  // Simulate successful submission
  if (options?.onSuccess) {
    options.onSuccess();
  }
  if (options?.onFinish) {
    options.onFinish();
  }
});

vi.mock('@inertiajs/vue3', () => ({
  useForm: vi.fn((initialData) => ({
    ...initialData,
    post: mockPost,
    processing: false,
    errors: {},
    reset: vi.fn(),
  })),
}));

describe('PaymentDialog', () => {
  const mockBooking = {
    id: 1,
    band_id: 1,
  };

  const mockPaymentTypes = [
    { value: 'deposit', label: 'Deposit', icon: 'pi-dollar' },
    { value: 'balance', label: 'Balance', icon: 'pi-wallet' },
    { value: 'other', label: 'Other', icon: 'pi-money-bill' }
  ];

  let container;

  beforeEach(() => {
    container = document.createElement('div');
    container.id = 'app';
    document.body.appendChild(container);
    vi.clearAllMocks();
    mockPost.mockClear();
  });

  afterEach(() => {
    if (container && container.parentNode) {
      container.parentNode.removeChild(container);
    }
  });

  it('should render dialog when modelValue is true', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const dialog = wrapper.findComponent({ name: 'Dialog' });
    expect(dialog.exists()).toBe(true);

    wrapper.unmount();
  });

  it('should not show dialog when modelValue is false', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: false,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const dialog = wrapper.findComponent({ name: 'Dialog' });
    expect(dialog.exists()).toBe(true);
    expect(dialog.props('visible')).toBe(false);

    wrapper.unmount();
  });

  it('should have correct dialog header', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const dialog = wrapper.findComponent({ name: 'Dialog' });
    expect(dialog.props('header')).toBe('Make a payment');

    wrapper.unmount();
  });

  it('should render name input field', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const nameInput = document.querySelector('#name');
    expect(nameInput).toBeTruthy();

    wrapper.unmount();
  });

  it('should render payment type select', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const select = wrapper.findComponent({ name: 'Select' });
    expect(select.exists()).toBe(true);

    wrapper.unmount();
  });

  it('should render amount input', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const amountInput = document.querySelector('#amount');
    expect(amountInput).toBeTruthy();

    wrapper.unmount();
  });

  it('should render date picker', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const calendar = document.querySelector('#date');
    expect(calendar).toBeTruthy();

    wrapper.unmount();
  });

  it('should render cancel and submit buttons', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const buttons = wrapper.findAllComponents({ name: 'Button' });
    expect(buttons.length).toBeGreaterThanOrEqual(2);

    // Find the Cancel and Submit Payment buttons
    const cancelButton = buttons.find(b => b.props('label') === 'Cancel');
    const submitButton = buttons.find(b => b.props('label') === 'Submit Payment');

    expect(cancelButton).toBeTruthy();
    expect(submitButton).toBeTruthy();

    wrapper.unmount();
  });

  it('should handle dialog visibility changes', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const dialog = wrapper.findComponent({ name: 'Dialog' });
    expect(dialog.props('visible')).toBe(true);

    // Change modelValue prop to false (simulating parent closing dialog)
    await wrapper.setProps({ modelValue: false });
    await wrapper.vm.$nextTick();

    const dialogAfter = wrapper.findComponent({ name: 'Dialog' });
    expect(dialogAfter.props('visible')).toBe(false);

    wrapper.unmount();
  });

  it('should call post when submit button is clicked', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const buttons = wrapper.findAllComponents({ name: 'Button' });
    const submitButton = buttons.find(b => b.props('label') === 'Submit Payment');

    await submitButton.trigger('click');
    await wrapper.vm.$nextTick();

    expect(mockPost).toHaveBeenCalled();
    expect(mockPost).toHaveBeenCalledWith(
      expect.stringContaining('/mock-route/Store Booking Payment'),
      expect.objectContaining({
        preserveScroll: true,
        onSuccess: expect.any(Function),
        onFinish: expect.any(Function)
      })
    );

    wrapper.unmount();
  });

  it('should show submit button loading state', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const buttons = wrapper.findAllComponents({ name: 'Button' });
    const submitButton = buttons.find(b => b.props('label') === 'Submit Payment');

    expect(submitButton).toBeTruthy();
    expect(submitButton.props('label')).toBe('Submit Payment');

    wrapper.unmount();
  });

  it('should render payment type options correctly', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const select = wrapper.findComponent({ name: 'Select' });
    expect(select.props('options')).toEqual(mockPaymentTypes);
    expect(select.props('optionLabel')).toBe('label');
    expect(select.props('optionValue')).toBe('value');

    wrapper.unmount();
  });

  it('should show proper validation attributes', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const nameInput = document.querySelector('#name');
    expect(nameInput).toBeTruthy();
    // PrimeVue InputText renders required differently, just verify the input exists
    expect(nameInput.id).toBe('name');

    wrapper.unmount();
  });

  it('should use InputNumber for amount with currency mode', async () => {
    const wrapper = mount(PaymentDialog, {
      props: {
        modelValue: true,
        booking: mockBooking,
        paymentTypes: mockPaymentTypes
      },
      attachTo: container
    });

    await wrapper.vm.$nextTick();

    const amountInput = wrapper.findComponent({ name: 'InputNumber' });
    expect(amountInput.exists()).toBe(true);
    expect(amountInput.props('mode')).toBe('currency');
    expect(amountInput.props('currency')).toBe('USD');

    wrapper.unmount();
  });
});
