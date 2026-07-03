import SocialLoginButtons from '@/Components/SocialLoginButtons.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('SocialLoginButtons', () => {
  it('should render a Google login link with the correct href', () => {
    const wrapper = mount(SocialLoginButtons);

    const google = wrapper.find('[dusk="social-login-google"]');
    expect(google.exists()).toBe(true);
    expect(google.attributes('href')).toBe('/auth/google/redirect');
    expect(wrapper.text()).toContain('Continue with Google');
  });

  it('should render an Apple login link with the correct href', () => {
    const wrapper = mount(SocialLoginButtons);

    const apple = wrapper.find('[dusk="social-login-apple"]');
    expect(apple.exists()).toBe(true);
    expect(apple.attributes('href')).toBe('/auth/apple/redirect');
    expect(wrapper.text()).toContain('Continue with Apple');
  });

  it('should render a Facebook login link with the correct href', () => {
    const wrapper = mount(SocialLoginButtons);

    const facebook = wrapper.find('[dusk="social-login-facebook"]');
    expect(facebook.exists()).toBe(true);
    expect(facebook.attributes('href')).toBe('/auth/facebook/redirect');
    expect(wrapper.text()).toContain('Continue with Facebook');
  });

  it('should render all three provider links', () => {
    const wrapper = mount(SocialLoginButtons);

    const links = wrapper.findAll('a');
    expect(links).toHaveLength(3);
  });

  it('should render the divider text', () => {
    const wrapper = mount(SocialLoginButtons);

    expect(wrapper.text()).toContain('or continue with');
  });
});
