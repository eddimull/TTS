<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Proposals
            </h2>
        </template>

        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg pt-4">
                    <div v-if="bandsAndProposals.length > 0" class="container my-8">
                        <div v-for="band in bandsAndProposals" :key="band.id">
                            {{band.name}}
                            <table class="min-w-full bg-white m-5 rounded">
                                <thead class="bg-gray-800 text-white">
                                    <tr>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Name</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Date</th>
                                        <th scope="w-1/3 text-left py-3 uppercase font-semibold text-sm">Phase</th>
                                    </tr>
                                </thead>  
                                <tbody v-if="band.proposals.length > 0" class="text-gray-700">
                                    <tr :class="{'bg-gray-100': $index % 2 === 0, 'border-b': $index % 2 !== 0 }" v-for="proposal in band.proposals" :key="proposal.id">
                                        <td class="w-1/3 text-center py-3 px-4">{{proposal.name}}</td>
                                        <td class="w-1/3 text-center py-3 px-4">{{proposal.date}}</td>
                                        <td class="w-1/3 text-center py-3 px-4">{{proposal.phase.name}}</td>
                                    </tr>
                                </tbody>  
                                <tbody v-else>
                                    <tr>
                                        <td  colspan="3" class="text-center">No proposals at the moment</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="my-4"><a href="/proposals/create" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline m-10 p-5">Draft Proposal for {{band.name}}</a></div>
                        </div>
                        
                    </div>
                    <div v-else>
                        It looks like you don't have any bands to create a proposal for. 
                        <a href="/bands/create" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Draft Proposal</a>
                    </div>
                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'

    export default {
        props:['bandsAndProposals','successMessage'],
        components: {
            BreezeAuthenticatedLayout,
        },
    }
</script>
