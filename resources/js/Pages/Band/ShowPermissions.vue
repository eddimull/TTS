/* eslint-disable vue/no-mutating-props */
<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="">
        <div class="mb-5 text-2xl underline dark:text-white">
          Edit Permissions
        </div>
      </div>
      <div class="container max-w-sm bg-white border-2 border-gray-300 p-6 rounded-md tracking-wide shadow-lg">
        <div class="flex items-center border-b-2 mb-2 py-2">
          <div class="pl-3">
            <div class="font-medium capitalize">
              {{ user.name }}
            </div>
            <div class="text-gray-600 text-sm">
              {{ band.name }}
            </div>
          </div>
        </div>
        <div
          v-for="(permission) in permissionList"
          :key="permission"
          class="mb-3"
        >
          <h4 class="text-l capitalize ">
            {{ permission.name }}
          </h4>
          <ul class="ml-2">
            <li>
              Read <Checkbox
                v-model="permissions['read_' + permission.name]"
                :binary="true"
                :true-value="1"
                :false-value="0"
              />
            </li>
            <li class="mb-2">
              Write <Checkbox
                v-model="permissions['write_' + permission.name]"
                :binary="true"
                :true-value="1"
                :false-value="0"
              />
            </li>
          </ul>
        </div>
        <div class="m-auto">
          <Button @click="save">
            Save
          </Button>
        </div>
      </div>
    </template>
  </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
    export default {
        components: {
            BreezeAuthenticatedLayout, Checkbox, Button
        },
        props:{
            'band':{
                required:true,
                type:Object
            },
            'user':{
                required:true,
                type:Object
            },
            'permissions':{
                required:true,
                type:Object
            }
        },
        data(){
            return{
                permissionList:[
                    {name:'events',readProp:'read_events',writeProp:'write_events'},
                    {name:'proposals',readProp:'read_proposals',writeProp:'write_proposals'},
                    {name:'invoices',readProp:'read_invoices',writeProp:'write_invoices'},
                    {name:'colors',readProp:'read_colors',writeProp:'write_colors'},
                    {name:'charts',readProp:'read_charts',writeProp:'read_charts'}
                ]
            }
        },
        created(){
  
        },
        methods:{
            save(){
                this.$inertia.post('/permissions/' + this.band.id + '/' + this.user.id,{
                   permissions:this.permissions
                })
            }
        }
    }
</script>
