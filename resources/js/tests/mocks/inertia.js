import { vi } from 'vitest';

// Mock router for Inertia
export const mockRouter = {
  get: vi.fn((url, options) => Promise.resolve()),
  post: vi.fn((url, data, options) => Promise.resolve()),
  put: vi.fn((url, data, options) => Promise.resolve()),
  patch: vi.fn((url, data, options) => Promise.resolve()),
  delete: vi.fn((url, options) => Promise.resolve()),
  reload: vi.fn(),
  visit: vi.fn(),
  on: vi.fn((event, callback) => {
    return () => {}; // Return unsubscribe function
  }),
};

// Mock useForm hook
export const createMockForm = (initialData = {}) => ({
  ...initialData,
  post: vi.fn((url, options) => {
    if (options?.onSuccess) {
      options.onSuccess();
    }
    return Promise.resolve();
  }),
  put: vi.fn((url, options) => {
    if (options?.onSuccess) {
      options.onSuccess();
    }
    return Promise.resolve();
  }),
  patch: vi.fn((url, options) => {
    if (options?.onSuccess) {
      options.onSuccess();
    }
    return Promise.resolve();
  }),
  delete: vi.fn((url, options) => {
    if (options?.onSuccess) {
      options.onSuccess();
    }
    return Promise.resolve();
  }),
  reset: vi.fn(function(...fields) {
    if (fields.length === 0) {
      Object.keys(this).forEach(key => {
        if (typeof this[key] !== 'function') {
          this[key] = initialData[key] || '';
        }
      });
    } else {
      fields.forEach(field => {
        this[field] = initialData[field] || '';
      });
    }
  }),
  clearErrors: vi.fn(function(...fields) {
    if (fields.length === 0) {
      this.errors = {};
    } else {
      fields.forEach(field => {
        delete this.errors[field];
      });
    }
  }),
  setError: vi.fn(function(field, message) {
    this.errors[field] = message;
  }),
  processing: false,
  progress: null,
  wasSuccessful: false,
  recentlySuccessful: false,
  errors: {},
  hasErrors: false,
  isDirty: false,
  transform: vi.fn(function(callback) {
    return this;
  }),
});

// Mock usePage hook
export const createMockPage = (props = {}) => ({
  component: 'Dashboard',
  props: {
    auth: {
      user: {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
      }
    },
    flash: {
      success: null,
      error: null,
    },
    errors: {},
    ...props
  },
  url: '/dashboard',
  version: '1.0.0',
});

// Helper to setup Inertia mocks
export function setupInertiaMocks(customMocks = {}) {
  const mocks = {
    router: customMocks.router || mockRouter,
    useForm: customMocks.useForm || vi.fn(() => createMockForm()),
    usePage: customMocks.usePage || vi.fn(() => createMockPage()),
  };

  vi.mock('@inertiajs/vue3', () => mocks);

  return mocks;
}

// Reset all Inertia mocks
export function resetInertiaMocks() {
  vi.clearAllMocks();
  mockRouter.get.mockClear();
  mockRouter.post.mockClear();
  mockRouter.put.mockClear();
  mockRouter.patch.mockClear();
  mockRouter.delete.mockClear();
  mockRouter.reload.mockClear();
  mockRouter.visit.mockClear();
  mockRouter.on.mockClear();
}

// Mock route helper (for ziggy)
export const mockRoute = vi.fn((name, params) => {
  // Simple implementation - returns a fake URL
  const paramString = params ? `?${Object.keys(params).map(k => `${k}=${params[k]}`).join('&')}` : '';
  return `/mock-route/${name}${paramString}`;
});

export function setupRouteMock() {
  global.route = mockRoute;
  return mockRoute;
}
