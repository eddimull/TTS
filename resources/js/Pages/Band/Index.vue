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
                    <div  v-if="bands.length > 0" class="container">
                        <a href="/bands/create" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5">Create Band</a>
                        <div class="shadow overflow-hidden rounded border-b border-gray-200">
                            <table class="min-w-full bg-white m-5 rounded">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Name</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Site Name</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm"></th>
                                    </tr>
                                </thead>  
                                <tbody class="text-gray-700">
                                    <tr :class="{'bg-gray-100': $index % 2 === 0, 'border-b': $index % 2 !== 0 }" v-for="band in bands" :key="band.id">
                                        <td class="w-1/3 text-center py-3 px-4">{{band.name}}</td>
                                        <td class="w-1/3 text-center py-3 px-4">{{band.site_name}}</td>
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
