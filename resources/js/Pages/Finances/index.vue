<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Finances
      </h2>
    </template>
    <Container>
      <TabView>
        <TabPanel header="Revenue for year">
          <div
            v-for="band in revenue"
            :key="band.name"
          >
            <h2 class="underline text-2xl">
              {{ band.name }}
            </h2>
            <ul
              v-for="(money,index) in band.aggregatedRevenue"
              :key="index"
            >
              <li>{{ index }}: {{ moneyFormat(money) }}</li>
            </ul>
          </div>
        </TabPanel>
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
                v-model:filters="unpaidProposalFilter"
                :value="band.unpaid"
                striped-rows
                row-hover
                responsive-layout="scroll"
                selection-mode="single"
                @row-click="gotoProposal"
              >
                <template #header>
                  <div class="p-d-flex p-jc-between">
                    <Button
                      type="button"
                      icon="pi pi-filter-slash"
                      label="Clear"
                      class="p-button-outlined"
                      @click="initializedunPaidProposalFilter()"
                    />
                    <span class="p-input-icon-left">
                      <i class="pi pi-search" />
                      <InputText
                        v-model="unpaidProposalFilter['global'].value"
                        placeholder="Keyword Search"
                      />
                    </span>
                  </div>
                </template>
                <Column
                  field="name"
                  header="Name"
                  :sortable="true"
                />
                <Column
                  field="price"
                  header="Price"
                  :sortable="true"
                >
                  <template #body="slotProps">
                    ${{ parseFloat(slotProps.data.price).toFixed(2) }}
                  </template>
                </Column>
                <Column
                  field="amountPaid"
                  header="Paid"
                  :sortable="true"
                >
                  <template #body="slotProps">
                    <div class="border flex flex-row relative p-2">
                      <div class="z-50">
                        {{ slotProps.data.amountPaid.replace(/,/g,'') }} / {{ parseFloat(slotProps.data.price).toFixed(2) }}
                      </div>
                      <div
                        class="z-40 absolute top-0 left-0 h-full"
                        style="min-width:10px;"
                        :style="getStats(slotProps.data.amountPaid, slotProps.data.price)"
                      />
                    </div>
                  </template>
                </Column>
                <Column
                  field="date"
                  header="Event Date"
                  :sortable="true"
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
                v-model:filters="paidProposalFilter"
                :value="band.paid"
                striped-rows
                row-hover
                responsive-layout="scroll"
                selection-mode="single"
              >
                <template #header>
                  <div class="p-d-flex p-jc-between">
                    <Button
                      type="button"
                      icon="pi pi-filter-slash"
                      label="Clear"
                      class="p-button-outlined"
                      @click="initializedPaidProposalFilter()"
                    />
                    <span class="p-input-icon-left">
                      <i class="pi pi-search" />
                      <InputText
                        v-model="paidProposalFilter['global'].value"
                        placeholder="Keyword Search"
                      />
                    </span>
                  </div>
                </template>
                <Column
                  field="name"
                  header="Name"
                  :sortable="true"
                />
                <Column
                  field="price"
                  header="Price"
                  :sortable="true"
                >
                  <template #body="slotProps">
                    ${{ parseFloat(slotProps.data.price).toFixed(2) }}
                  </template>
                </Column>
                <Column
                  field="date"
                  header="Event Date"
                  :sortable="true"
                />
                <Column
                  field="amountLeft"
                  header="Payment Overridden"
                  :sortable="true"
                >
                  <template #body="slotProps">
                    <div v-if="slotProps.data.amountLeft !== '0.00' && slotProps.data.paid">
                      <i class="pi pi-check" />
                      Still Owed ${{ slotProps.data.amountLeft }}
                    </div>
                  </template>
                </Column>
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
    import {FilterMatchMode,FilterOperator} from 'primevue/api';
    export default {
        components: {
          
        },
        data(){
          return{
            completedProposals: [],
            paidProposalFilter: null,
            unpaidProposalFilter: null,
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
        computed:
        {
          revenue()
          {

            const data = [];
            for(const band in this.completedProposals)
            {
              const bandData = {
                name: this.completedProposals[band].name
              }

              const completedProposals = [...this.completedProposals[band].completed_proposals];
                var years = completedProposals.map(function(d) {
                    const year = moment(d.date).format('YYYY');
                    return [year,parseFloat(d.price)];
                });
                
                
                var sums = years.reduce(function(prev, curr, idx, arr) {
                    var sum = prev[curr[0]];
                    prev[curr[0]] = sum ? sum + curr[1] : curr[1];
                    return prev; 
                }, {});
                bandData.aggregatedRevenue = sums;
                data.push(bandData);
                
              }

            return data;
          }
        },
        created(){
          
          this.completedProposals = this.$page.props.completedProposals;
          this.parseProposals();
          this.initializedPaidProposalFilter();
          this.initializedunPaidProposalFilter();
        },
        methods:{

          moneyFormat(number)
          {
             var formatter = new Intl.NumberFormat('en-US', {
                  style: 'currency',
                  currency: 'USD'
              });
              return formatter.format(number);
          },
          getStats(paid,price)
          {
            const percentagePaid = (parseFloat(paid.replace(/,/g,'')).toFixed(2)/parseFloat(price).toFixed(2))*100;
            let background = 'red';
            switch(true){
              case(percentagePaid < 10):
                background = '#ff2d03'
                break;
              case(percentagePaid < 20):
                background = '#ff7d03'
                break;
              case(percentagePaid < 30):
                background = '#ffc803'
                break;
              case(percentagePaid < 40):
                background = '#fff203'
                break;
              case(percentagePaid < 50):
                background = '#d9ff03'
                break;
              case(percentagePaid < 60):
                background = '#c0ff03'
                break;
              case(percentagePaid < 70):
                background = '#afff03'
                break;
              case(percentagePaid < 80):
                background = '#92ff03'
                break;
              case(percentagePaid < 90):
                background = '#3eff03'
                break;
            }
            return [{'width': percentagePaid +'%'},{'background':background}]
          },
          gotoProposal(event)
          {
            const proposal = event.data;
            window.location = '/proposals/' + proposal.key + '/payments';
            
          },
          initializedPaidProposalFilter() {
                this.paidProposalFilter = {
                    'global': {value: null, matchMode: FilterMatchMode.CONTAINS}
                }
            },     
          initializedunPaidProposalFilter() {
                this.unpaidProposalFilter = {
                    'global': {value: null, matchMode: FilterMatchMode.CONTAINS}
                }
            },   
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
                if(!proposal.paid)
                {
                  return proposal
                }
              })

              this.completedProposals[band].paid = completedProposals.filter(proposal=>{
                if(proposal.paid || proposal.amountLeft == '0.00')
                {
                  // console.log('paid',proposal.amountLeft);
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
