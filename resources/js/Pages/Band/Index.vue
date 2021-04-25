<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Band List
            </h2>
        </template>

        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <div v-if="successMessage" class="mb-4 bg-teal-100 border-t-4 border-teal-500 rounded-b text-teal-900 px-4 py-3 shadow-md" role="alert">
                        <div class="flex">
                            <div class="py-1"><svg class="fill-current h-6 w-6 text-teal-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                            <div>
                                <p class="font-bold">{{ successMessage }}</p>
                            </div>
                        </div>
                    </div>
                    <div  v-if="bands.length > 0" class="container">
                        <a href="/bands/create" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5">Create Band</a>
                        <div class="shadow overflow-hidden rounded border-b border-gray-200">
                            <table class="min-w-full bg-white m-5 rounded">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Name</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm"></th>
                                    </tr>
                                </thead>  
                                <tbody class="text-gray-700">
                                    <tr :class="{'bg-gray-100': $index % 2 === 0, 'border-b': $index % 2 !== 0 }" v-for="band in bands" :key="band.id">
                                        <td class="w-1/3 text-left py-3 px-4">{{band.name}}</td>
                                        <td>
                                            <inertia-link class="border bg-white hover:bg-blue-500 rounded p-1" :href="`/bands/${band.id}/edit`">
                                                Edit
                                            </inertia-link>
                                        </td>
                                    </tr>
                                </tbody>  
                            </table>
                        </div>
                    </div>
                    <div v-else>
                        It looks like you don't have any bands. 
                        <a href="/bands/create" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Create Band</a>
                    </div>
                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'

    export default {
        props:['bands','successMessage'],
        components: {
            BreezeAuthenticatedLayout,
        },
    }
</script>
