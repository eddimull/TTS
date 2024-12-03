<template>
    <div
        class="layout-background relative flex items-top justify-center min-h-screen sm:items-center sm:pt-0"
    >
        <div v-if="canLogin" class="fixed top-0 right-0 px-6 py-4 sm:block">
            <Link
                v-if="$page.props.auth.user"
                href="/dashboard"
                class="text-sm text-gray-700 dark:text-gray-50 underline"
            >
                Dashboard
            </Link>

            <template v-else>
                <Link
                    :href="route('login')"
                    class="text-sm text-gray-700 dark:text-gray-50 underline"
                >
                    Log in
                </Link>

                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="ml-4 text-sm text-gray-700 dark:text-gray-50 underline"
                >
                    Register
                </Link>
            </template>
        </div>

        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                <svg
                    class="h-16 w-auto text-gray-500 dark:text-gray-50 sm:h-20 fill-current"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 50 50"
                >
                    <path
                        d="M 12 0 C 10.906937 0 10 0.9069372 10 2 L 10 4 L 4 4 C 2.841 4 2 4.841 2 6 L 2 46 C 2 47.159 2.841 48 4 48 L 27.207031 48 C 27.156031 47.814 27.0925 47.632453 27.0625 47.439453 C 26.9895 46.960453 26.987016 46.478 27.041016 46 L 4 46 L 4 15 L 46 15 L 46 24.662109 L 48 24.253906 L 48 6 C 48 4.841 47.159 4 46 4 L 40 4 L 40 2 C 40 0.9069372 39.093063 0 38 0 L 36 0 C 34.906937 0 34 0.9069372 34 2 L 34 4 L 16 4 L 16 2 C 16 0.9069372 15.093063 0 14 0 L 12 0 z M 12 2 L 14 2 L 14 8 L 12 8 L 12 2 z M 36 2 L 38 2 L 38 8 L 36 8 L 36 2 z M 4 6 L 10 6 L 10 8 C 10 9.0930628 10.906937 10 12 10 L 14 10 C 15.093063 10 16 9.0930628 16 8 L 16 6 L 34 6 L 34 8 C 34 9.0930628 34.906937 10 36 10 L 38 10 C 39.093063 10 40 9.0930628 40 8 L 40 6 L 46 6 L 46 13 L 4 13 L 4 6 z M 49.052734 26.111328 C 48.978729 26.10757 48.902797 26.113453 48.826172 26.126953 L 37.130859 28.720703 C 36.476859 28.835703 36 29.402406 36 30.066406 L 36 42.222656 C 36 42.679656 35.689094 43.078406 35.246094 43.191406 L 31.800781 44.070312 L 31.509766 44.140625 C 30.215766 44.525625 29.063156 45.6 29.035156 47 C 29.037156 47.009 29.039063 47.011437 29.039062 47.023438 C 29.039062 48.669438 30.980625 50 32.765625 50 L 32.865234 50 C 35.700234 50 38 47.700234 38 44.865234 L 38 34.257812 L 48 32 L 48 39.238281 C 48 39.695281 47.689094 40.094031 47.246094 40.207031 L 43.800781 41.085938 L 43.509766 41.154297 C 42.211766 41.540297 41 42.551344 41 44.027344 C 41.001 44.032344 41.001953 44.031109 41.001953 44.037109 C 41.001953 46.006109 42.980625 47.015625 44.765625 47.015625 L 44.865234 47.015625 C 47.700234 47.015625 50 44.715859 50 41.880859 L 50 27.111328 C 50 26.567078 49.570775 26.137633 49.052734 26.111328 z"
                    />
                </svg>
                <h3 class="inline text-4xl align-middle">TTS</h3>
            </div>

            <div
                class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg"
            >
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="p-6">
                        <div class="flex items-center">
                            <svg
                                class="w-8 h-8 text-gray-500"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                            <div class="ml-4 text-lg leading-7 font-semibold">
                                <a
                                    href="#"
                                    class="underline text-gray-900 dark:text-white"
                                    >Scheduling</a
                                >
                            </div>
                        </div>

                        <div class="ml-12">
                            <div
                                class="mt-2 text-gray-600 dark:text-gray-400 text-sm"
                            >
                                Keep a schedule together with your band. "Hey,
                                are they gonna have backline?" With these
                                scheduling tools it's all ready to go. Sync up
                                to your google calendar so that everything is
                                organized and your members know about it.
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-6 border-t border-gray-200 dark:border-gray-700 md:border-t-0 md:border-l"
                    >
                        <div class="flex items-center">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-8 h-8 text-gray-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"
                                />
                            </svg>
                            <div class="ml-4 text-lg leading-7 font-semibold">
                                <a
                                    href="#"
                                    class="underline text-gray-900 dark:text-white"
                                    >Contracts</a
                                >
                            </div>
                        </div>

                        <div class="ml-12">
                            <div
                                class="mt-2 text-gray-600 dark:text-gray-400 text-sm"
                            >
                                Who needs an agent? Easily set up proposals and
                                contracts for your band so there's no guesswork
                                between you and venues as to what is expected,
                                when, and clarity about money.
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-6 border-t border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex items-center">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-8 h-8 text-gray-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                />
                            </svg>
                            <div class="ml-4 text-lg leading-7 font-semibold">
                                <a
                                    href="#"
                                    class="underline text-gray-900 dark:text-white"
                                    >Members</a
                                >
                            </div>
                        </div>

                        <div class="ml-12">
                            <div
                                class="mt-2 text-gray-600 dark:text-gray-400 text-sm"
                            >
                                Keep your band owners, members, and production
                                crew organized. Keep everything organized and
                                people notified when they have to be where.
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-6 border-t border-gray-200 dark:border-gray-700 md:border-l"
                    >
                        <div class="flex items-center">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-8 h-8 text-gray-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <div
                                class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white"
                            >
                                Personal stats
                            </div>
                        </div>

                        <div class="ml-12">
                            <div
                                class="mt-2 text-gray-600 dark:text-gray-400 text-sm"
                            >
                                "Hey did anybody tally up the mileage this
                                year?" Taxes are a pain. Automatically have
                                mileage calculated, money figured, and time
                                figured out.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    props: {
        canLogin: Boolean,
        canRegister: Boolean,
        laravelVersion: String,
        phpVersion: String,
    },
};
</script>
