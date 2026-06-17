<template>
    <breeze-authenticated-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                Account Preferences
            </h2>
        </template>

        <div class="md:container md:mx-auto">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-700 overflow-hidden shadow-sm sm:rounded-lg pt-4">
                  <p class="font-bold">{{ successMessage }}</p>
                    <div v-if="successMessage" class="mb-4 bg-teal-100 border-t-4 border-teal-500 rounded-b text-teal-900 px-4 py-3 shadow-md" role="alert">
                        <div class="flex">
                            <div class="py-1"><svg class="fill-current h-6 w-6 text-teal-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                            <div>
                                <p class="font-bold">{{ successMessage }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="container">
                      <div class="bg-white dark:bg-slate-700 rounded px-8 pt-6 pb-8 mb-4 flex flex-col my-2">
                        <form action="/account/update" method="PATCH" @submit.prevent="updateAccount">
                        <div class="-mx-3 md:flex mb-6">
                          <div class="md:w-1/2 px-3 mb-6 md:mb-0">
                            <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-first-name">
                              Name
                            </label>
                            <input v-model="form.name" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-red rounded py-3 px-4 mb-3" id="grid-first-name" type="text" placeholder="Jane">
                            <p class="text-red text-xs italic">First and Last name</p>
                          </div>
                          <div class="md:w-1/2 px-3 mb-6 md:mb-0">
                            <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-first-name">
                              Email
                            </label>
                            <input v-model="form.email" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-red rounded py-3 px-4 mb-3" id="grid-first-name" type="email" placeholder="user@domain.com">
                            <p class="text-red text-xs italic">user@domain.com</p>
                          </div>
                        </div>
                        <div class="-mx-3 md:flex mb-6">
                          <div class="md:w-full px-3">
                            <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-password">
                              Password
                            </label>
                            <input v-model="form.password" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-grey-lighter rounded py-3 px-4 mb-3" id="grid-password" type="password" placeholder="******************">
                            <p class="text-grey-dark text-xs italic">If you don't change it, it will be the same</p>
                          </div>
                        </div>
                          <div class="-mx-3 md:flex mb-6">
                              <div class="md:w-1/2 px-3">
                              <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="address1">
                                  Address 1
                              </label>
                              <input v-model="form.address1" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-grey-lighter rounded py-3 px-4 mb-3" id="address1" type="text" placeholder="">
                              <p class="text-grey-dark text-xs italic">219 Mimosa Pl</p>
                              </div>
                              <div class="md:w-1/2 px-3">
                              <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="address2">
                                  Address 2
                              </label>
                              <input v-model="form.Address2" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-grey-lighter rounded py-3 px-4 mb-3" id="address2" type="text" placeholder="">
                              <p class="text-grey-dark text-xs italic">Apt# 123</p>
                              </div>
                          </div>
                          <div class="-mx-3 md:flex mb-6">
                              <div class="md:w-1/2 px-3">
                              <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-state">
                                  Country
                              </label>
                              <div class="relative">
                                  <select v-model="form.country"  @change="filterStates" class="block appearance-none w-full bg-grey-lighter dark:bg-gray-500 border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="grid-state">
                                  <option v-for="country in countries" :key="country.id" :value="country.id">{{country.country_name}}</option>
                                  </select>
                              </div>
                              </div>
                          </div>
                          
                          <div class="-mx-3 md:flex mb-2">

                            <div class="md:w-1/2 px-3 mb-6 md:mb-0">
                              <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-city">
                                City
                              </label>
                              <input v-model="form.city" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-grey-lighter rounded py-3 px-4" id="grid-city" type="text" placeholder="Beverly Hills">
                            </div>
                            <div class="md:w-1/2 px-3">
                              <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-state">
                                State
                              </label>
                              <div class="relative">
                                <select v-model="form.state" :disabled="filteredStateList.length === 0" class="block appearance-none w-full bg-grey-lighter dark:bg-gray-500 border border-grey-lighter text-grey-darker py-3 px-4 pr-8 rounded" id="grid-state">
                                  <option v-for="state in filteredStateList" v-bind:key="state.state_id" :value="state.state_id">{{state.state_name}}</option>
                                </select>
                              </div>
                            </div>
                            <div class="md:w-1/2 px-3">
                              <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-zip">
                                Zip
                              </label>
                              <input v-model="form.zip" class="appearance-none block w-full bg-grey-lighter dark:bg-gray-500 text-grey-darker border border-grey-lighter rounded py-3 px-4" id="grid-zip" type="text" placeholder="90210">
                            </div>
                          </div>
                          <div class="-mx-3 md:flex mt-6">
                              <div class="md:w-1/2 px-3">
                                <label class="block uppercase tracking-wide text-grey-darker text-xs font-bold mb-2" for="grid-state">
                                    Receive Email Notifications
                                </label>
                                <div class="mx-3 relative">
                                  <input-switch v-model="form.emailNotifications"></input-switch>
                                </div>
                              </div>
                          </div>
                        </form>
                      </div>
                      <div class="flex justify-end p-2">
                        <button v-on:click="updateAccount" class="bg-blue-500 px-4 py-2 text-lg font-semibold tracking-wider flex-end text-white rounded hover:bg-blue-600">Save</button>
                      </div>

                      <div class="border-t border-red-200 dark:border-red-900 mt-8 pt-6 px-2">
                        <h3 class="text-lg font-bold text-red-600 dark:text-red-400">Danger Zone</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 mb-3">
                          Permanently delete your account. This removes you from your bands and cannot be undone.
                        </p>
                        <button v-on:click="showDeleteModal = true" class="bg-red-600 px-4 py-2 text-sm font-semibold tracking-wider text-white rounded hover:bg-red-700">
                          Delete Account
                        </button>
                      </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete account confirmation modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4" @click.self="showDeleteModal = false">
            <div class="bg-white dark:bg-slate-700 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Delete your account?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-200 mt-2">
                    For your security, we'll email <span class="font-semibold">{{ user.email }}</span> a confirmation
                    link. Your account is only deleted after you open that link and confirm. The link expires in 60 minutes.
                </p>
                <div class="flex justify-end gap-3 mt-6">
                    <button v-on:click="showDeleteModal = false" :disabled="deleting" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 rounded hover:bg-gray-100 dark:hover:bg-slate-600">
                        Cancel
                    </button>
                    <button v-on:click="requestDeletion" :disabled="deleting" class="bg-red-600 px-4 py-2 text-sm font-semibold text-white rounded hover:bg-red-700 disabled:opacity-60">
                        {{ deleting ? 'Sending…' : 'Send confirmation email' }}
                    </button>
                </div>
            </div>
        </div>
    </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import InputSwitch from 'primevue/inputswitch';

    export default {
        props:['user','states','countries','successMessage'],
        components: {
            BreezeAuthenticatedLayout,
            InputSwitch,
        },
        data(){
            return{
                form:{
                    name:this.user.name,
                    email:this.user.email,
                    zip:this.user.Zip,
                    city:this.user.City,
                    state:this.user.StateID,
                    country:this.user.CountryID,
                    address1:this.user.Address1,
                    address2:this.user.Address2,
                    emailNotifications:this.user.emailNotifications
                },
                filteredStateList:this.states,
                showDeleteModal:false,
                deleting:false
            }
        },
        computed:{
            },
        watch:{
            
        },
        methods:{
            filterStates(){
                this.filteredStateList = [];
                for(var i in this.states)
                {
                  if(this.states[i].country_id === this.form.country)
                  {
                    this.filteredStateList.push(this.states[i]);
                  }
                }
            },
            updateAccount(){

                this.$inertia.patch('/account/update',this.form)
                    .then(()=>{
                        this.loading = false;
                    })
            },
            requestDeletion(){
                this.deleting = true;
                this.$inertia.post('/account/delete', {}, {
                    onFinish:()=>{
                        this.deleting = false;
                        this.showDeleteModal = false;
                    }
                })
            }
        }
    }
</script>
