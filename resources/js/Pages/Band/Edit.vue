<template>
    <breeze-authenticated-layout>
       
        <div class="w-full max-w-lg">
             <div class="mb-4 bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                 <div class="mb-4 flex text-center">
                        
                        <div v-for="panel in panels" @click="activePanel = panel.name" :key="panel.name" :class="[activePanel == panel.name ? 'bg-blue-600': 'bg-blue-400', 'cursor-pointer','inline', 'w-1/2', 'hover:bg-blue-700', 'text-white', 'font-bold', 'py-2']" v-html="panel.icon" :title="panel.name"></div>
                    </div>
                
                <form v-if="activePanel == 'Details'" :action="'/bands/' + band.id" method="PATCH" @submit.prevent="updateBand">
                    
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Name</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" placeholder="Band Name" v-model="form.name">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Page Name (URL)</label>
                    <input type="text" v-on:input="filter" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="site_name" placeholder="Band_Name"  pattern="([a-zA-z0-9\-_]+)" v-model="form.site_name">
                            <span v-if="urlWarn" class="text-red-700">Letters, numbers, _, +, and - are the only characters allowed</span>
                    </div>     
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Google Calendar ID</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="calendarID" placeholder="Calendar ID" v-model="form.calendar_id">
                        <span @click="showInstructions = !showInstructions" class="cursor-pointer"><strong><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg> How to integrate calendar</strong></span>
                        <p v-if="showInstructions">
                            <span> In order to get this set up, you have to give the app permissions to write events to the calendar and then you have to specify the calendar's ID you give permission to write to.</span>
                            <hr/>
                            <ol class="list-decimal px-2 my-4">
                                <li>Goto <a href="https://calendar.google.com">Google calendar</a></li>
                                <li>Under 'my calendars', find the 3 little dots and click 'settings and sharing'</li>
                                <li>Find 'Share with specific people' and add in <strong>ttscalendar@threethirtyseven.iam.gserviceaccount.com</strong></li>
                                <li>Scroll down a little bit to find your Calendar ID under Integrate calendar. It will look like somethingsomethingsomething@whatever.google.com</li>
                                <li>Copy the calendar ID and paste it in above</li>
                            </ol>
                        </p>
                    </div>                 
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                            Update 
                        </button>
                    </div>
                </form>

                <div v-if="activePanel == 'Band Members'">

                    Members:
                    <ul class="px-3" v-if="band.members">
                        <li v-for="(member,index) in band.members" class="my-2" :key="index">
                            {{member.user.name}} - {{ member.user.email }}
                        </li>
                    </ul>
                    <div v-else>
                        No Members
                    </div>
                    Owners:
                    
                    <ul  class="px-3" v-if="band.owners">
                        <li v-for="(owner,index) in band.owners" class="my-2" :key="index">
                            {{owner.user.name}} - {{owner.user.email}}
                        </li>
                    </ul>
                    <div v-else>
                        No Owners
                    </div>
                    Pending Invites:
                    <ul  class="px-3" v-if="band.pending_invites">
                        <li @click="deleteInvite(invite)" v-for="(invite,index) in band.pending_invites" class="italic text-gray-400 my-2 cursor-pointer" :key="index">
                            {{invite.email}}
                        </li>
                    </ul>
                    <div v-else>
                        No Members
                    </div>
                    
                    <p v-if="band.members.legth == 0">Looks like you don't have any members in your band. Invite some!</p>
                    <transition name="slide-down">
                        <div v-if="inviting" class="my-4">
                            <input type="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="email" placeholder="user@email.com" v-model="invite.email">
                            <div>
                                <button class="mx-3 my-2 px-4 py-2 text-sm font-semibold tracking-wider text-blue-600 rounded hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="inviting = !inviting">Cancel</button>
                                <button class="mx-3 my-2 bg-blue-100 px-4 py-2 text-sm font-semibold tracking-wider text-blue-600 rounded hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="inviteOwner">Add Owner</button>
                                <button class="mx-3 my-2 bg-blue-100 px-4 py-2 text-sm font-semibold tracking-wider text-blue-600 rounded hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="inviteMember">Add Member</button>
                            </div>
                        </div>
                    </transition>
                    <transition name="slide-down">
                        <button  v-if="!inviting" @click="inviting = !inviting" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg> 
                        </button>
                    </transition>
                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'

    export default {
        props:['errors','band','members','owners'],
        components: {
            BreezeAuthenticatedLayout,
        },
        data(){
            return{
                urlWarn:false,
                showInstructions:false,
                activePanel:'Details',
                inviting:false,
                invite:{
                    email:''
                },
                members:[
                    
                ],
                panels:[
                    {
                        name:'Details',
                        icon:'<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>'
                    },{
                        name:'Band Members',
                        icon:'<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>'
                }],
                form:{
                    name:this.band.name,
                    site_name:this.band.site_name,
                    calendar_id:this.band.calendar_id
                }
            }
        },
        methods:{
            updateBand(){
                const bandID = this.band.id;
                this.$inertia.patch('/bands/' + bandID,this.form)
                    .then(()=>{
                        this.loading = false;
                    })
            },
            filter()
            {
                if(this.form.site_name.length > 0)
                {

                    let message = this.form.site_name;
                    let urlsafeName = message.replace(/[^a-zA-Z0-9\-_]/gm,"")                    
                    this.urlWarn = urlsafeName !== this.form.site_name 
                    this.form.site_name = urlsafeName;

                }   
            },
            inviteOwner()
            {
                this.$inertia.post('/inviteOwner/' + this.band.id,{
                    band_id:this.band.id,
                    email:this.invite.email
                }, {
                    onSuccess:()=>{
                    }
                })

            },
            inviteMember()
            {
                this.$inertia.post('/inviteMember/' + this.band.id,{
                    band_id:this.band.id,
                    email:this.invite.email
                }, {
                    onSuccess:()=>{
                    }
                })
            },
            deleteInvite(invite)
            {
                 this.$swal.fire({
                    title: 'Are you sure you want to remove the invite for '+ invite.email,
                    text: "You won't be able to revert this!",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if(result.value)
                    {
                        this.$inertia.delete('/deleteInvite/' + this.band.id + '/' + invite.id);
                    }
                })
            }
        },
        watch:{
            form:{
                deep:true,
                handler()
                {

                }
            }
        }
    }
</script>
<style scoped>
.slide-down-enter-active{
  transition: all .2s ease;
}
.slide-down-leave-active {
  transition: all .1s cubic-bezier(1.0, 0.5, 0.8, 1.0);
  max-height: 230px;
}
.slide-down-enter-from, .slide-down-leave-to
/* .slide-fade-leave-active below version 2.1.8 */ {
  transform: translateY(-50px);
  max-height: 0px;
}
</style>
