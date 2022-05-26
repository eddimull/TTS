<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Questionnaire
      </h2>
    </template>

    <Container>
      <div class="card">
        Questions  
        <div
          v-for="component in components"
          :key="component.id"
        >
          <Header
            v-if="component.type === 'header'"
            :value="component.name"
          />
          <Multichoice
            v-if="component.type === 'multichoice'"
            :value="component"
          />
          <OpenEnded
            v-if="component.type === 'openEnded'"
            :value="component"
          />
        </div>
        <div>
          <Button @click="addQuestion">
            + Add Question
          </Button>
        </div>
      </div>
      <!-- Edit Questionnaire
      <Header :value="headerText" />
      <Multichoice :value="multipleChoice" />
      <OpenEnded :value="openEnded" /> -->

      <Dialog
        v-model:visible="addingQuestion"
        :style="{width: '450px'}"
        header="Add Question"
        :modal="true"
        class="p-fluid"
      >
        <div class="p-field">
          <label for="name">Name</label>
          <InputText
            id="name"
            v-model.trim="newQuestion.name"
            required="true"
            autofocus
            :class="{'p-invalid': submitted && !newQuestion.name}"
          />
          <small
            v-if="submitted && !newQuestion.name"
            class="p-error"
          >Name is required.</small>
        </div>                 
        <div class="p-field">
          <label
            for="band"
            class="p-mb-3"
          >Type</label>
          <Dropdown
            id="bandSelection"
            v-model="newQuestion.type"
            :options="questionTypes"
            option-label="label"
            option-value="value"
            placeholder="Question Type"
          />
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
            @click="saveQuestion"
          />
        </template>
      </Dialog>
    </Container>
    <SideEditor ref="sideEditor" />
  </Layout>
</template>
<script>
import Header from '@/Components/Questionnaire/Header'
import Multichoice from '@/Components/Questionnaire/MultipleChoice'
import OpenEnded from '@/Components/Questionnaire/OpenEnded'
import SideEditor from '@/Components/Questionnaire/SideEditor'

export default {
  components:{
    SideEditor,
    Header,
    Multichoice,
    OpenEnded
  },
  props:['questionnaire','questionnaireData'],
  data(){
    return {
      components:[],
      addingQuestion:false,
      questionTypes:[
        {label:'Header',value:'header'},
        {label:'Multichoice',value:'multichoice'},
        {label:'OpenEnded',value:'openEnded'}
      ],
      submitted:false,
      saving:false,
      newQuestion:{
        name:'',
        type:null
      },
      headerText: 'this is a block of text!',
      multipleChoice:{
        title:'Title of question',
        choices:[
          'choice 1',
          'choice 2',
          'choice 3'
        ]
      },
      openEnded:{
          title:"Open Ended Question",
          input:'',
          singleLine:false
      }

    }
  },
  created() {
    this.components = this.questionnaireData.map(component=>{
      const parsedData = JSON.parse(component.data);
      return {
        id:component.id,
        ...parsedData
      }
    })
  },
  methods:{

    addQuestion()
    {
      this.addingQuestion = !this.addingQuestion;
      console.log('adding question');
    },
    closeDialog(){
      this.addingQuestion = false;
    },  
    saveQuestion()
    {
      this.$inertia.post(route('questionnaire.addQuestion',{slug:this.questionnaire.slug}),this.newQuestion);
    }
  }
}
</script>
