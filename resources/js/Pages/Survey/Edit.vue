<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Survey
      </h2>
    </template>
  </Layout>
</template>
<script>


export default {
  components:{

  },
  props:['survey','questions'],
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
   
  },
  methods:{

  }
}
</script>
