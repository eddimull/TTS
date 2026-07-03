<template>
  <div>
    <div class="relative my-6">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-300 dark:border-gray-600" />
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-2 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">
          or continue with
        </span>
      </div>
    </div>

    <div class="space-y-3">
      <a
        href="/auth/google/redirect"
        class="w-full inline-flex items-center justify-center gap-3 py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
        dusk="social-login-google"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A11 11 0 0 0 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
        Continue with Google
      </a>

      <a
        href="/auth/apple/redirect"
        class="w-full inline-flex items-center justify-center gap-3 py-2 px-4 rounded-md shadow-sm bg-black text-sm font-medium text-white hover:bg-gray-900"
        dusk="social-login-apple"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M16.36 12.79c.03 3.2 2.81 4.27 2.84 4.28-.02.08-.44 1.52-1.46 3-.88 1.28-1.8 2.55-3.24 2.58-1.42.03-1.88-.84-3.5-.84-1.63 0-2.14.81-3.48.87-1.4.05-2.46-1.38-3.35-2.65-1.82-2.63-3.2-7.42-1.34-10.66a5.19 5.19 0 0 1 4.39-2.66c1.37-.03 2.66.92 3.5.92.83 0 2.4-1.14 4.05-.97.69.03 2.63.28 3.87 2.1-.1.06-2.31 1.35-2.28 4.03zM13.7 4.6c.74-.9 1.24-2.14 1.1-3.38-1.06.04-2.35.71-3.11 1.6-.69.79-1.29 2.06-1.13 3.27 1.19.09 2.4-.6 3.14-1.49z"/></svg>
        Continue with Apple
      </a>

      <a
        href="/auth/facebook/redirect"
        class="w-full inline-flex items-center justify-center gap-3 py-2 px-4 rounded-md shadow-sm text-sm font-medium text-white hover:opacity-90"
        style="background-color: #1877F2"
        dusk="social-login-facebook"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07z"/></svg>
        Continue with Facebook
      </a>
    </div>
  </div>
</template>
