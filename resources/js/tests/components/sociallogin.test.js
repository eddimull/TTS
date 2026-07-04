import SocialLoginButtons from '@/Components/SocialLoginButtons.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

function mountWithPageProps(features = {}) {
  return mount(SocialLoginButtons, {
    global: {
      mocks: {
        $page: {
          props: { features },
        },
      },
    },
  });
}

describe('SocialLoginButtons', () => {
  it('should render a Google login link with the correct href', () => {
    const wrapper = mountWithPageProps();

    const google = wrapper.find('[dusk="social-login-google"]');
    expect(google.exists()).toBe(true);
    expect(google.attributes('href')).toBe('/auth/google/redirect');
    expect(wrapper.text()).toContain('Continue with Google');
  });

  it('should render an Apple login link with the correct href', () => {
    const wrapper = mountWithPageProps();

    const apple = wrapper.find('[dusk="social-login-apple"]');
    expect(apple.exists()).toBe(true);
    expect(apple.attributes('href')).toBe('/auth/apple/redirect');
    expect(wrapper.text()).toContain('Continue with Apple');
  });

  it('should not render a Facebook login link when the feature flag is absent', () => {
    const wrapper = mountWithPageProps();

    const facebook = wrapper.find('[dusk="social-login-facebook"]');
    expect(facebook.exists()).toBe(false);
  });

  it('should not render a Facebook login link when the feature flag is false', () => {
    const wrapper = mountWithPageProps({ facebookLogin: false });

    const facebook = wrapper.find('[dusk="social-login-facebook"]');
    expect(facebook.exists()).toBe(false);
  });

  it('should render a Facebook login link with the correct href when the feature flag is true', () => {
    const wrapper = mountWithPageProps({ facebookLogin: true });

    const facebook = wrapper.find('[dusk="social-login-facebook"]');
    expect(facebook.exists()).toBe(true);
    expect(facebook.attributes('href')).toBe('/auth/facebook/redirect');
    expect(wrapper.text()).toContain('Continue with Facebook');
  });

  it('should render only google and apple links when the feature flag is absent', () => {
    const wrapper = mountWithPageProps();

    const links = wrapper.findAll('a');
    expect(links).toHaveLength(2);
  });

  it('should render all three provider links when the feature flag is true', () => {
    const wrapper = mountWithPageProps({ facebookLogin: true });

    const links = wrapper.findAll('a');
    expect(links).toHaveLength(3);
  });

  it('should render the divider text', () => {
    const wrapper = mountWithPageProps();

    expect(wrapper.text()).toContain('or continue with');
  });
});
