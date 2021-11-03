<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Finances
      </h2>
    </template>
    <Container>
      <TabView>
        <TabPanel header="Paid/Unpaid">
          <div
            v-for="(band,index) in completedProposals"
            :key="index"
            class="card my-4"
          >
            <div class="card">
              <h5>{{ band.name }}</h5>
              <Chart
                type="line"
                :data="band.data"
                :options="basicOptions"
              />
            </div>
          </div>
        </TabPanel>
        <TabPanel header="Unpaid Contracts">
          <div
            v-for="(band,index) in completedProposals"
            :key="index"
            class="card my-4"
          >
            <div class="card">
              <h5>{{ band.name }}</h5>
              <DataTable
                :value="band.unpaid"
                striped-rows
                row-hover
                responsive-layout="scroll"
                selection-mode="single"
                @row-click="selectedChart"
              >
                <Column
                  field="name"
                  header="Name"
                />
                <Column
                  field="price"
                  header="price"
                />
                <Column
                  field="date"
                  header="Event Date"
                />
                <template #empty>
                  No Records found. Click 'new' to create one.
                </template>
              </DataTable>
            </div>
          </div>
        </TabPanel>
        <TabPanel header="Paid Contracts">
          <div
            v-for="(band,index) in completedProposals"
            :key="index"
            class="card my-4"
          >
            <div class="card">
              <h5>{{ band.name }}</h5>
              <DataTable
                :value="band.paid"
                striped-rows
                row-hover
                responsive-layout="scroll"
                selection-mode="single"
                @row-click="selectedChart"
              >
                <Column
                  field="name"
                  header="Name"
                />
                <Column
                  field="price"
                  header="price"
                />
                <Column
                  field="date"
                  header="Event Date"
                />
                <template #empty>
                  No Records found. Click 'new' to create one.
                </template>
              </DataTable>
            </div>
          </div>
        </TabPanel>
      </TabView>
    </Container>
  </Layout>
</template>

<script>
    import moment from 'moment';
    export default {
        components: {
          
        },
        data(){
          return{
            completedProposals: [],
            basicOptions: {
                plugins: {
                    legend: {
                        labels: {
                            color: '#495057'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#495057'
                        },
                        grid: {
                            color: '#ebedef'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#495057'
                        },
                        grid: {
                            color: '#ebedef'
                        }
                    }
                }
            },            
          }
        },
        created(){
          
          this.completedProposals = this.$page.props.completedProposals;
          this.parseProposals();
        },
        methods:{
          parseProposals()
          {
            
            for(const band in this.completedProposals)
            {
              const completedProposals = this.completedProposals[band].completed_proposals;
              const data =  {
                labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July','August','September','October','December'],
                datasets: [
                    {
                        label: 'Paid',
                        data: [0,0,0,0,0,0,0,0,0,0,0,0],
                        fill: false,
                        borderColor: '#42A5F5',
                        tension: .4
                    },
                    {
                        label: 'Unpaid',
                        data: [0,0,0,0,0,0,0,0,0,0,0,0],
                        fill: false,
                        borderColor: '#FFA726',
                        tension: .4
                    }
                ]
              }

              completedProposals.forEach(proposal => {
                const dateIndex = moment(proposal.date).format('M') - 1;
                  data.datasets[proposal.paid ? 0 : 1].data[dateIndex] += parseInt(proposal.price);
              });

              this.completedProposals[band].data = data;
              this.completedProposals[band].unpaid = completedProposals.filter(proposal=>{
                (!proposal.paid)
                {
                  return proposal
                }
              })

              this.completedProposals[band].unpaid = completedProposals.filter(proposal=>{
                (proposal.paid)
                {
                  return proposal
                }
              })
              


              
            }
            // this.completedProposals.foreach(band=>{
            //   console.log(band)
            // })
          }
        }
    }
</script>
