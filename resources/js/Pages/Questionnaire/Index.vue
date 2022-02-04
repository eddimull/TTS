<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Questionnaires
      </h2>
    </template>

    <Container>
      <div class="card">
        <Toolbar class="p-mb-4">
          <template #left>
            <Button
              label="New"
              icon="pi pi-plus"
              class="p-button-success p-mr-2"
              @click="openNew"
            />
          <!-- <Button
                label="Delete"
                icon="pi pi-trash"
                class="hidden p-button-danger"
                :disabled="!selectedProducts || !selectedProducts.length"
                @click="confirmDeleteSelected"
              /> -->
          </template>

          <template #right>
          <!-- <Button

                label="Export"
                icon="pi pi-upload"
                class="hidden p-button-help"
                @click="exportCSV($event)"
              /> -->
          </template>
        </Toolbar>
        {{ questionnairesData }}
        <DataTable
          :value="questionnairesData"
          striped-rows
          row-hover
          responsive-layout="scroll"
          selection-mode="single"
          @row-click="selectedQuestionnaire"
        >
          <Column
            field="name"
            header="Name"
            :sortable="true"
          />
          <template #empty>
            No Records found. Click 'new' to create one.
          </template>
        </DataTable>
      </div>

      <Dialog
        v-model:visible="questionnaireDialog"
        :style="{width: '450px'}"
        header="Questionnaire Details"
        :modal="true"
        class="p-fluid"
      >
        <img
          v-if="questionnaire.image"
          src="https://www.primefaces.org/wp-content/uploads/2020/05/placeholder.png"
          :alt="questionnaire.image"
          class="product-image"
        >
        <div class="p-field">
          <label for="name">Name</label>
          <InputText
            id="name"
            v-model.trim="questionnaire.name"
            required="true"
            autofocus
            :class="{'p-invalid': submitted && !questionnaire.name}"
          />
          <small
            v-if="submitted && !questionnaire.name"
            class="p-error"
          >Name is required.</small>
        </div>                 
        <div class="p-field">
          <label for="description">Description</label>
          <Textarea
            id="description"
            v-model="questionnaire.description"
            required="true"
            rows="3"
            cols="20"
          />
        </div>
        <div class="p-field">
          <label
            for="band"
            class="p-mb-3"
          >Band</label>
          <Dropdown
            id="bandSelection"
            v-model="questionnaire.band"
            :options="availableBands"
            option-label="name"
            placeholder="Select a Band"
          >
            <template #value="slotProps">
              <div v-if="slotProps.value && slotProps.value.id">
                <span>{{ slotProps.value.name }}</span>
              </div>

              <span v-else>
                {{ slotProps.placeholder }}
              </span>
            </template>
          </Dropdown>
        </div>          
        <template #footer>
          <Button
            label="Cancel"
            icon="pi pi-times"
            class="p-button-text"
            @click="closeDialog"
          />
          <Button
            :label="saving ? 'Saving...': 'Save'"
            :disabled="saving"
            icon="pi pi-check"
            class="p-button-text"
            @click="saveQuestionnaire"
          />
        </template>
      </Dialog>
    </Container>
  </Layout>
</template>
<script>
    import Toolbar from 'primevue/toolbar'
    import DataTable from 'primevue/datatable';
    import Column from 'primevue/column';
    
    export default {
        components: {
            Toolbar,
            DataTable,
            Column
        },
        props:{
          questionnaires:{
            type:Array,
            default:()=>{return []}
          }
        },
        data(){
            return{
                form:{
                    
                },
              questionnairesData:this.questionnaires,
              questionnaire:{},
              saving:false,
              submitted:false,
              questionnaireDialog:false,
            }
        },
        computed:{
          availableBands(){
            const bands = [];
            if(this.$page.props.auth.user.band_owner)
            {
              this.$page.props.auth.user.band_owner.forEach(band=>{
                bands.push({id:band.id,name:band.name})
              })
            }
            if(this.$page.props.auth.user.band_member)
            {
              this.$page.props.auth.user.band_member.forEach(band=>{
                
                bands.push({id:band.id,name:band.name})
              })
            }

            function sortNames(a,b)
            {
              if(a.name < b.name)
              {
                return -1;
              }
              if(a.name > b.name)
              {
                return 1;
              }
              return 0;
            }
           return bands.filter((v,i,a)=>a.findIndex(t=>(t.id === v.id))===i).sort(sortNames)
          }
        },
        watch:{
            
        }, 
        created(){
          
        },
        methods:{
            selectedQuestionnaire(data)
            {
              this.$inertia.visit(this.route('questionnaire.edit', data.data.slug));  
            },
        openNew() {
          
          this.saving = false;
            this.product = {};
            this.submitted = false;
            this.questionnaireDialog = true;
        }, 
        saveQuestionnaire(){
          this.submitted = true;
          this.saving = true;
          this.questionnaire.band_id = this.questionnaire.band.id;
          this.$inertia.post('/questionnaire/new',this.questionnaire);
        },
        closeDialog(){
          this.saving = false;
          this.questionnaireDialog = false;
        }
      }     
        
        
    }
</script>
