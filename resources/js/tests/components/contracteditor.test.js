import ContractEditor from '@/Pages/Bookings/Components/ContractEditor.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, beforeEach, vi } from 'vitest';

// Mock route helper
global.route = vi.fn((name, params) => `/mock-route/${name}`);

// Mock Inertia - use factory function to avoid hoisting issues
vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    on: vi.fn(() => () => {}), // Return unsubscribe function
  },
}));

// Mock dependencies
vi.mock('jspdf-autotable', () => ({}));
vi.mock('svg2pdf.js', () => ({}));

// Import router after mocking
import { router as mockRouter } from '@inertiajs/vue3';

describe('ContractEditor', () => {
  const mockBooking = {
    id: 1,
    band_id: 1,
    contract: {
      custom_terms: [
        { title: 'Term 1', content: 'Content for term 1' },
        { title: 'Term 2', content: 'Content for term 2' }
      ]
    },
    contacts: [
      { id: 1, name: 'John Doe', email: 'john@example.com' }
    ]
  };

  const mockBand = {
    id: 1,
    name: 'Test Band'
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render contract editor', () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div class="wysiwyg-stub"></div>',
            props: ['initialTerms', 'booking', 'band']
          },
          SendContractPopup: {
            template: '<div class="popup-stub"></div>',
            props: ['show', 'contacts']
          }
        }
      }
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('.contract-editor').exists()).toBe(true);
  });

  it('should pass custom terms to EditableContractWYSIWYG', () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div class="wysiwyg-stub"></div>',
            props: ['initialTerms', 'booking', 'band']
          },
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    expect(wysiwyg.exists()).toBe(true);
    expect(wysiwyg.props('booking')).toEqual(mockBooking);
    expect(wysiwyg.props('band')).toEqual(mockBand);
  });

  it('should render EditableContractWYSIWYG component', () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: true,
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    expect(wysiwyg.exists()).toBe(true);
  });

  it('should render SendContractPopup component', () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: true,
          SendContractPopup: true
        }
      }
    });

    const popup = wrapper.findComponent({ name: 'SendContractPopup' });
    expect(popup.exists()).toBe(true);
  });

  it('should call router.post when save event is emitted', async () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div class="wysiwyg"></div>',
            emits: ['save']
          },
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    await wysiwyg.vm.$emit('save');

    expect(mockRouter.post).toHaveBeenCalled();
  });

  it('should call router.get when generate-pdf event is emitted', async () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div class="wysiwyg"></div>',
            emits: ['generate-pdf']
          },
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    await wysiwyg.vm.$emit('generate-pdf');

    expect(mockRouter.get).toHaveBeenCalled();
  });

  it('should handle update:terms event', async () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div @click="$emit(\'update:terms\', { title: \'Updated\' })">Update</div>',
            emits: ['update:terms']
          },
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    await wysiwyg.trigger('click');

    // Component should handle the event without errors
    expect(wrapper.exists()).toBe(true);
  });

  it('should pass contacts to SendContractPopup', () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: true,
          SendContractPopup: {
            template: '<div class="popup"></div>',
            props: ['show', 'contacts']
          }
        }
      }
    });

    const popup = wrapper.findComponent({ name: 'SendContractPopup' });
    expect(popup.props('contacts')).toEqual(mockBooking.contacts);
  });

  it('should handle send-contract event', async () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div @click="$emit(\'send-contract\')">Send</div>',
            emits: ['send-contract']
          },
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    await wysiwyg.trigger('click');

    // Component should handle the event without errors
    expect(wrapper.exists()).toBe(true);
  });

  it('should call router.post when confirm event from popup is emitted', async () => {
    const wrapper = mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: true,
          SendContractPopup: {
            template: '<div class="popup"></div>',
            props: ['show', 'contacts'],
            emits: ['confirm']
          }
        }
      }
    });

    const popup = wrapper.findComponent({ name: 'SendContractPopup' });
    await popup.vm.$emit('confirm', mockBooking.contacts);

    expect(mockRouter.post).toHaveBeenCalled();
  });

  it('should setup navigation guard', () => {
    mount(ContractEditor, {
      props: {
        booking: mockBooking,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: true,
          SendContractPopup: true
        }
      }
    });

    expect(mockRouter.on).toHaveBeenCalledWith('before', expect.any(Function));
  });

  it('should use default terms when no custom terms exist', () => {
    const bookingWithoutTerms = {
      ...mockBooking,
      contract: null
    };

    const wrapper = mount(ContractEditor, {
      props: {
        booking: bookingWithoutTerms,
        band: mockBand
      },
      global: {
        stubs: {
          EditableContractWYSIWYG: {
            template: '<div></div>',
            props: ['initialTerms', 'booking', 'band']
          },
          SendContractPopup: true
        }
      }
    });

    const wysiwyg = wrapper.findComponent({ name: 'EditableContractWYSIWYG' });
    expect(wysiwyg.props('initialTerms')).toBeDefined();
    expect(Array.isArray(wysiwyg.props('initialTerms'))).toBe(true);
  });
});
