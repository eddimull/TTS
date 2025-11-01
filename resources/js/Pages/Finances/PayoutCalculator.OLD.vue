<template>
  <FinanceLayout>
    <div class="mx-4 my-6 space-y-8">
      <!-- Page Header -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
            Payment Calculator
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Configure how payments are distributed to band members
          </p>
        </div>
      </div>

      <!-- Band Selection & Calculator -->
      <div
        v-for="band in bands"
        :key="band.id"
        class="space-y-6"
      >
        <!-- Payment Groups Management -->
        <PaymentGroupManager :band="band" />

        <!-- Calculator Panel -->
        <div class="componentPanel shadow-lg rounded-lg p-6 space-y-6">
          <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 border-b pb-3">
            {{ band.name }} - Payout Calculator
          </h2>

          <!-- Quick Calculator -->
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">
              <i class="pi pi-calculator mr-2" />
              Quick Calculator
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Total Booking Amount
                </label>
                <InputNumber
                  v-model="calculators[band.id].totalAmount"
                  mode="currency"
                  currency="USD"
                  locale="en-US"
                  class="w-full"
                  :min="0"
                  @input="calculate(band.id)"
                />
              </div>
              <div class="flex items-end">
                <Button
                  label="Calculate"
                  icon="pi pi-chart-bar"
                  class="w-full"
                  @click="calculate(band.id)"
                />
              </div>
            </div>

            <!-- Results -->
            <div
              v-if="results[band.id]"
              class="mt-6 space-y-4"
            >
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <StatCard
                  label="Band's Cut"
                  :value="moneyFormat(results[band.id].band_cut)"
                  value-color-class="text-blue-600 dark:text-blue-400"
                />
                <StatCard
                  label="Total Member Payout"
                  :value="moneyFormat(results[band.id].total_member_payout)"
                  value-color-class="text-green-600 dark:text-green-400"
                />
                <StatCard
                  label="Per Member"
                  :value="results[band.id].member_payouts.length > 0 ? moneyFormat(results[band.id].member_payouts[0].amount) : '$0.00'"
                  value-color-class="text-purple-600 dark:text-purple-400"
                />
              </div>

              <!-- Payment Group Breakdown (when using payment groups) -->
              <div
                v-if="results[band.id].payment_group_payouts && results[band.id].payment_group_payouts.length > 0"
                class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow"
              >
                <SectionHeader
                  title="Payment Group Breakdown (Sequential)"
                  icon="pi-users"
                  class="mb-3 font-semibold text-gray-800 dark:text-gray-100"
                />
                <div class="space-y-3">
                  <div
                    v-for="(groupPayout, index) in results[band.id].payment_group_payouts"
                    :key="groupPayout.group_id"
                    class="border dark:border-gray-700 rounded-lg p-3"
                  >
                    <div class="flex justify-between items-center mb-2 pb-2 border-b dark:border-gray-700">
                      <div>
                        <span class="font-semibold text-blue-600 dark:text-blue-400">
                          {{ index + 1 }}. {{ groupPayout.group_name }}
                        </span>
                        <span class="text-xs text-gray-500 ml-2">
                          ({{ groupPayout.member_count }} members)
                        </span>
                      </div>
                      <span class="text-sm font-bold">
                        {{ moneyFormat(groupPayout.total) }}
                      </span>
                    </div>
                    <div class="space-y-1 ml-4">
                      <div
                        v-for="(memberPayout, idx) in groupPayout.payouts"
                        :key="idx"
                        class="flex justify-between items-center text-sm p-1"
                      >
                        <span class="text-gray-600 dark:text-gray-400">
                          {{ memberPayout.user_name }}
                          <span class="text-xs text-gray-500">({{ memberPayout.payout_type }})</span>
                        </span>
                        <span class="font-medium">{{ moneyFormat(memberPayout.amount) }}</span>
                      </div>
                    </div>
                  </div>
                  <InfoAlert
                    v-if="results[band.id].remaining > 0"
                    variant="warning"
                  >
                    <div class="flex justify-between items-center">
                      <span>Remaining (unallocated)</span>
                      <span class="font-bold text-yellow-700 dark:text-yellow-400">
                        {{ moneyFormat(results[band.id].remaining) }}
                      </span>
                    </div>
                  </InfoAlert>
                </div>
              </div>

              <!-- Member Breakdown (when NOT using payment groups) -->
              <div
                v-else
                class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow"
              >
                <SectionHeader
                  title="Member Breakdown"
                  class="mb-3 font-semibold text-gray-800 dark:text-gray-100"
                />
                <div class="space-y-2 max-h-64 overflow-y-auto">
                  <div
                    v-for="(payout, index) in results[band.id].member_payouts"
                    :key="index"
                    class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded"
                  >
                    <span class="text-sm">
                      <span
                        :class="{
                          'text-blue-600 dark:text-blue-400': payout.type === 'owner',
                          'text-green-600 dark:text-green-400': payout.type === 'member',
                          'text-orange-600 dark:text-orange-400': payout.type === 'production'
                        }"
                        class="font-medium"
                      >
                        {{ payout.type === 'owner' ? 'Owner' : payout.type === 'member' ? 'Member' : 'Production' }}
                      </span>
                      {{ payout.name || (index + 1) }}
                    </span>
                    <span class="font-semibold">{{ moneyFormat(payout.amount) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Configuration Section -->
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                <i class="pi pi-cog mr-2" />
                Payout Configuration
              </h3>
              <Button
                v-if="!editingConfig[band.id]"
                label="Edit Configuration"
                icon="pi pi-pencil"
                severity="secondary"
                size="small"
                @click="startEditing(band)"
              />
              <Button
                v-else
                label="Cancel"
                icon="pi pi-times"
                severity="danger"
                size="small"
                text
                @click="cancelEditing(band.id)"
              />
            </div>

            <!-- Current Configuration Display -->
            <div
              v-if="!editingConfig[band.id] && band.active_payout_config"
              class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4"
            >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Configuration Name
                  </p>
                  <p class="font-medium">
                    {{ band.active_payout_config.name }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Band's Cut
                  </p>
                  <p class="font-medium">
                    <span v-if="band.active_payout_config.band_cut_type === 'percentage'">
                      {{ band.active_payout_config.band_cut_value }}%
                    </span>
                    <span v-else-if="band.active_payout_config.band_cut_type === 'fixed'">
                      {{ moneyFormat(band.active_payout_config.band_cut_value) }}
                    </span>
                    <span v-else-if="band.active_payout_config.band_cut_type === 'tiered'">
                      Tiered ({{ band.active_payout_config.band_cut_tier_config?.length || 0 }} tiers)
                    </span>
                    <span v-else>
                      None
                    </span>
                    <span class="text-gray-500 text-sm ml-1">({{ band.active_payout_config.band_cut_type }})</span>
                  </p>
                </div>
                <div v-if="band.active_payout_config.use_payment_groups">
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Payment Mode
                  </p>
                  <p class="font-medium text-blue-600 dark:text-blue-400">
                    <i class="pi pi-users mr-1" />
                    Using Payment Groups
                  </p>
                </div>
                <div v-else>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Member Payout Type
                  </p>
                  <p class="font-medium capitalize">
                    {{ band.active_payout_config.member_payout_type.replace('_', ' ') }}
                  </p>
                </div>
                <div v-if="!band.active_payout_config.use_payment_groups">
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Member Counts
                  </p>
                  <p class="font-medium">
                    {{ band.owners.length }} Owners, {{ band.members.length }} Members
                    <span v-if="band.active_payout_config.production_member_count > 0">
                      , {{ band.active_payout_config.production_member_count }} Production
                    </span>
                  </p>
                </div>
                <div v-else>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Configured Groups
                  </p>
                  <p class="font-medium">
                    {{ band.active_payout_config.payment_group_config?.length || 0 }} Groups
                  </p>
                </div>
              </div>
              <div
                v-if="band.active_payout_config.notes"
                class="mt-4 pt-4 border-t dark:border-gray-700"
              >
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Notes
                </p>
                <p class="text-sm mt-1">
                  {{ band.active_payout_config.notes }}
                </p>
              </div>
            </div>

            <!-- Configuration Form -->
            <div
              v-if="editingConfig[band.id]"
              class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 space-y-4"
            >
              <!-- Configuration Name -->
              <FormField label="Configuration Name">
                <InputText
                  v-model="configs[band.id].name"
                  class="w-full"
                  placeholder="e.g., Default Split, Wedding Rate, Festival Rate"
                />
              </FormField>

              <!-- Band's Cut -->
              <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <FormField
                    label="Band's Cut Type"
                    :hint="getBandCutTypeDescription(configs[band.id].band_cut_type)"
                  >
                    <Select
                      v-model="configs[band.id].band_cut_type"
                      :options="bandCutTypes"
                      option-label="label"
                      option-value="value"
                      class="w-full"
                    />
                  </FormField>
                  <FormField
                    v-if="configs[band.id].band_cut_type !== 'tiered'"
                    label="Band's Cut Value"
                  >
                    <InputNumber
                      v-if="configs[band.id].band_cut_type === 'percentage'"
                      v-model="configs[band.id].band_cut_value"
                      mode="decimal"
                      suffix="%"
                      locale="en-US"
                      class="w-full"
                      :min="0"
                      :max="100"
                    />
                    <InputNumber
                      v-else-if="configs[band.id].band_cut_type === 'fixed'"
                      v-model="configs[band.id].band_cut_value"
                      mode="currency"
                      currency="USD"
                      locale="en-US"
                      class="w-full"
                      :min="0"
                    />
                    <InputNumber
                      v-else
                      v-model="configs[band.id].band_cut_value"
                      mode="decimal"
                      locale="en-US"
                      class="w-full"
                      :min="0"
                      disabled
                    />
                  </FormField>
                </div>

                <!-- Band's Cut Tiered Configuration -->
                <FormField
                  v-if="configs[band.id].band_cut_type === 'tiered'"
                  label="Band's Cut Tier Configuration"
                >
                  <div class="space-y-3">
                    <TierConfigRow
                      v-for="(tier, index) in configs[band.id].band_cut_tier_config"
                      :key="'band-' + index"
                      :tier="tier"
                      @update:tier="configs[band.id].band_cut_tier_config[index] = $event"
                      @remove="removeBandCutTier(band.id, index)"
                    />
                    <Button
                      label="Add Tier"
                      icon="pi pi-plus"
                      severity="secondary"
                      size="small"
                      text
                      @click="addBandCutTier(band.id)"
                    />
                  </div>
                </FormField>
              </div>

              <!-- Payment Groups Toggle -->
              <InfoAlert variant="info">
                <label class="flex items-center text-sm font-medium mb-1">
                  <Checkbox
                    v-model="configs[band.id].use_payment_groups"
                    :binary="true"
                    input-id="use_payment_groups"
                    class="mr-2"
                  />
                  Use Payment Groups
                </label>
                <div class="text-xs">
                  When enabled, payouts are calculated based on configured payment groups instead of individual members
                </div>
              </InfoAlert>

              <!-- Payment Group Configuration -->
              <div
                v-if="configs[band.id].use_payment_groups && band.payment_groups && band.payment_groups.length > 0"
                class="space-y-4"
              >
                <SectionHeader
                  title="Payment Group Allocations"
                  icon="pi-users"
                />
                <InfoAlert variant="info">
                  <strong>Sequential Allocation:</strong> Groups are allocated in order based on display order. 
                  Each group takes from the <em>remaining</em> amount after previous groups.
                  <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    Example: (Net - Band Cut - Production Group) / Player Group
                  </div>
                </InfoAlert>
                
                <div class="space-y-3">
                  <div
                    v-for="(group, index) in band.payment_groups"
                    :key="group.id"
                    class="bg-white dark:bg-gray-700 p-4 rounded-lg border-l-4"
                    :class="{
                      'border-blue-500': index === 0,
                      'border-green-500': index === 1,
                      'border-orange-500': index === 2,
                      'border-purple-500': index >= 3
                    }"
                  >
                    <div class="grid grid-cols-12 gap-4 items-end">
                      <div class="col-span-4">
                        <label class="text-xs text-gray-600 dark:text-gray-400">
                          <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 text-xs font-bold mr-2">
                            {{ index + 1 }}
                          </span>
                          Group Name
                        </label>
                        <div class="font-medium">
                          {{ group.name }}
                          <span class="text-xs text-gray-500">({{ group.users?.length || 0 }} members)</span>
                        </div>
                      </div>
                      <div class="col-span-3">
                        <label class="text-xs text-gray-600 dark:text-gray-400">Allocation Type</label>
                        <Select
                          v-model="getGroupConfig(band.id, group.id).allocation_type"
                          :options="[{label: 'Percentage', value: 'percentage'}, {label: 'Fixed', value: 'fixed'}]"
                          option-label="label"
                          option-value="value"
                          class="w-full"
                        />
                      </div>
                      <div class="col-span-4">
                        <label class="text-xs text-gray-600 dark:text-gray-400">Allocation Value</label>
                        <InputNumber
                          v-if="getGroupConfig(band.id, group.id).allocation_type === 'percentage'"
                          v-model="getGroupConfig(band.id, group.id).allocation_value"
                          mode="decimal"
                          suffix="%"
                          locale="en-US"
                          class="w-full"
                          :min="0"
                          :max="100"
                        />
                        <InputNumber
                          v-else
                          v-model="getGroupConfig(band.id, group.id).allocation_value"
                          mode="currency"
                          currency="USD"
                          locale="en-US"
                          class="w-full"
                          :min="0"
                        />
                      </div>
                      <div class="col-span-1 text-center">
                        <i
                          v-if="group.is_active"
                          class="pi pi-check-circle text-green-500"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Warning if no payment groups exist -->
              <InfoAlert
                v-if="configs[band.id].use_payment_groups && (!band.payment_groups || band.payment_groups.length === 0)"
                variant="warning"
              >
                No payment groups exist for this band. Create payment groups above first.
              </InfoAlert>

              <!-- Member Payout Type (only shown when NOT using payment groups) -->
              <FormField
                v-if="!configs[band.id].use_payment_groups"
                label="Member Payout Type"
                :hint="getMemberPayoutTypeDescription(configs[band.id].member_payout_type)"
              >
                <Select
                  v-model="configs[band.id].member_payout_type"
                  :options="memberPayoutTypes"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </FormField>

              <!-- Member Counts (only shown when NOT using payment groups) -->
              <div
                v-if="!configs[band.id].use_payment_groups"
                class="grid grid-cols-1 md:grid-cols-3 gap-4"
              >
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <Checkbox
                      v-model="configs[band.id].include_owners"
                      :binary="true"
                      input-id="include_owners"
                      class="mr-2"
                    />
                    Include Owners ({{ band.owners.length }})
                  </label>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <Checkbox
                      v-model="configs[band.id].include_members"
                      :binary="true"
                      input-id="include_members"
                      class="mr-2"
                    />
                    Include Members ({{ band.members.length }})
                  </label>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Production Members
                  </label>
                  <InputNumber
                    v-model="configs[band.id].production_member_count"
                    class="w-full"
                    :min="0"
                  />
                </div>
              </div>

              <!-- Tiered Configuration (only shown when NOT using payment groups) -->
              <FormField
                v-if="!configs[band.id].use_payment_groups && configs[band.id].member_payout_type === 'tiered'"
                label="Tier Configuration"
              >
                <div class="space-y-3">
                  <TierConfigRow
                    v-for="(tier, index) in configs[band.id].tier_config"
                    :key="index"
                    :tier="tier"
                    @update:tier="configs[band.id].tier_config[index] = $event"
                    @remove="removeTier(band.id, index)"
                  />
                  <Button
                    label="Add Tier"
                    icon="pi pi-plus"
                    severity="secondary"
                    size="small"
                    text
                    @click="addTier(band.id)"
                  />
                </div>
              </FormField>

              <!-- Member Specific Configuration (only shown when NOT using payment groups) -->
              <div v-if="!configs[band.id].use_payment_groups && configs[band.id].member_payout_type === 'member_specific'">
                <SectionHeader
                  title="Member-Specific Payouts"
                  icon="pi-users"
                />
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                  Configure individual payout types and values for each band member
                </p>
              
                <!-- Owners -->
                <div
                  v-if="band.owners && band.owners.length > 0"
                  class="mb-6"
                >
                  <SectionHeader
                    title="Owners"
                    icon="pi-star-fill"
                    icon-color="#f59e0b"
                  />
                  <div class="space-y-2">
                    <PayoutMemberRow
                      v-for="owner in band.owners"
                      :key="'owner-' + owner.id"
                      :member="{ ...getMemberConfig(band.id, owner.user.id, 'owner'), name: owner.user.name }"
                      :payout-types="individualPayoutTypes"
                      background-class="bg-blue-50 dark:bg-blue-900/20"
                      name-label="Name"
                      :editable="false"
                      :show-checkmark="true"
                      @update:member="Object.assign(getMemberConfig(band.id, owner.user.id, 'owner'), $event)"
                    />
                  </div>
                </div>

                <!-- Members -->
                <div
                  v-if="band.members && band.members.length > 0"
                  class="mb-6"
                >
                  <SectionHeader
                    title="Band Members"
                    icon="pi-user"
                    icon-color="#3b82f6"
                  />
                  <div class="space-y-2">
                    <PayoutMemberRow
                      v-for="member in band.members"
                      :key="'member-' + member.id"
                      :member="{ ...getMemberConfig(band.id, member.user.id, 'member'), name: member.user.name }"
                      :payout-types="individualPayoutTypes"
                      background-class="bg-green-50 dark:bg-green-900/20"
                      name-label="Name"
                      :editable="false"
                      :show-checkmark="true"
                      @update:member="Object.assign(getMemberConfig(band.id, member.user.id, 'member'), $event)"
                    />
                  </div>
                </div>

                <!-- Production Members -->
                <div class="mb-6">
                  <SectionHeader
                    title="Production Members"
                    icon="pi-wrench"
                    icon-color="#f97316"
                  />
                  <div class="space-y-2">
                    <PayoutMemberRow
                      v-for="(prodMember, index) in configs[band.id].production_member_types"
                      :key="'prod-' + index"
                      :member="prodMember"
                      :payout-types="[{label: 'Percentage', value: 'percentage'}, {label: 'Fixed', value: 'fixed'}]"
                      background-class="bg-orange-50 dark:bg-orange-900/20"
                      name-label="Name/Role"
                      name-placeholder="e.g., Sound Engineer"
                      :editable="true"
                      :removable="true"
                      @update:member="configs[band.id].production_member_types[index] = $event"
                      @remove="removeProductionMember(band.id, index)"
                    />
                    <Button
                      label="Add Production Member"
                      icon="pi pi-plus"
                      severity="secondary"
                      size="small"
                      text
                      @click="addProductionMember(band.id)"
                    />
                  </div>
                </div>
              </div>

              <!-- Minimum Payout -->
              <FormField label="Minimum Payout Per Member">
                <InputNumber
                  v-model="configs[band.id].minimum_payout"
                  mode="currency"
                  currency="USD"
                  locale="en-US"
                  class="w-full"
                  :min="0"
                />
              </FormField>

              <!-- Notes -->
              <FormField label="Notes">
                <Textarea
                  v-model="configs[band.id].notes"
                  rows="3"
                  class="w-full"
                  placeholder="Add any notes about this configuration..."
                />
              </FormField>

              <!-- Save Button -->
              <div class="flex justify-end space-x-2">
                <Button
                  label="Cancel"
                  icon="pi pi-times"
                  severity="secondary"
                  text
                  @click="cancelEditing(band.id)"
                />
                <Button
                  label="Save Configuration"
                  icon="pi pi-save"
                  :loading="saving[band.id]"
                  @click="saveConfiguration(band)"
                />
              </div>
            </div>

            <!-- No Configuration Message -->
            <InfoAlert
              v-if="!editingConfig[band.id] && !band.active_payout_config"
              variant="warning"
            >
              <div class="text-center">
                <i class="pi pi-info-circle text-yellow-600 dark:text-yellow-400 text-2xl mb-2 block" />
                <p class="text-gray-700 dark:text-gray-300">
                  No payout configuration set for this band yet.
                </p>
                <Button
                  label="Create Configuration"
                  icon="pi pi-plus"
                  severity="secondary"
                  size="small"
                  class="mt-2"
                  @click="startEditing(band)"
                />
              </div>
            </InfoAlert>
          </div>
        </div>
      </div>
    </div>
  </FinanceLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import FinanceLayout from './Layout/FinanceLayout.vue'
import PaymentGroupManager from './Components/PaymentGroupManager.vue'
import StatCard from '@/Components/StatCard.vue'
import FormField from '@/Components/FormField.vue'
import TierConfigRow from '@/Components/TierConfigRow.vue'
import PayoutMemberRow from '@/Components/PayoutMemberRow.vue'
import SectionHeader from '@/Components/SectionHeader.vue'
import InfoAlert from '@/Components/InfoAlert.vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Textarea from 'primevue/textarea'

const props = defineProps({
  bands: {
    type: Array,
    required: true
  }
})

const bandCutTypes = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Tiered', value: 'tiered' },
  { label: 'None', value: 'none' }
]

const memberPayoutTypes = [
  { label: 'Equal Split', value: 'equal_split' },
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Tiered', value: 'tiered' },
  { label: 'Member Specific', value: 'member_specific' }
]

const individualPayoutTypes = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Equal Split', value: 'equal_split' }
]

const calculators = reactive({})
const results = reactive({})
const editingConfig = reactive({})
const saving = reactive({})
const configs = reactive({})

// Initialize calculators and configs for each band
props.bands.forEach(band => {
  calculators[band.id] = { totalAmount: 5000 }
  results[band.id] = null
  editingConfig[band.id] = false
  saving[band.id] = false
  configs[band.id] = getDefaultConfig(band)
  
  // If there's an active config, calculate immediately
  if (band.active_payout_config) {
    calculate(band.id)
  }
})

function getDefaultConfig(band) {
  if (band.active_payout_config) {
    return {
      name: band.active_payout_config.name,
      band_cut_type: band.active_payout_config.band_cut_type,
      band_cut_value: band.active_payout_config.band_cut_value,
      band_cut_tier_config: band.active_payout_config.band_cut_tier_config || [],
      member_payout_type: band.active_payout_config.member_payout_type,
      tier_config: band.active_payout_config.tier_config || [],
      regular_member_count: band.active_payout_config.regular_member_count,
      production_member_count: band.active_payout_config.production_member_count,
      production_member_types: band.active_payout_config.production_member_types || [],
      member_specific_config: band.active_payout_config.member_specific_config || [],
      include_owners: band.active_payout_config.include_owners,
      include_members: band.active_payout_config.include_members,
      minimum_payout: band.active_payout_config.minimum_payout,
      notes: band.active_payout_config.notes,
      use_payment_groups: band.active_payout_config.use_payment_groups || false,
      payment_group_config: band.active_payout_config.payment_group_config || initializeGroupConfig(band)
    }
  }
  
  return {
    name: 'Default Configuration',
    band_cut_type: 'percentage',
    band_cut_value: 10,
    band_cut_tier_config: [],
    member_payout_type: 'equal_split',
    tier_config: [],
    regular_member_count: 0,
    production_member_count: 0,
    production_member_types: [],
    member_specific_config: [],
    include_owners: true,
    include_members: true,
    minimum_payout: 0,
    notes: '',
    use_payment_groups: false,
    payment_group_config: initializeGroupConfig(band)
  }
}

function initializeGroupConfig(band) {
  if (!band.payment_groups || band.payment_groups.length === 0) {
    return []
  }
  
  return band.payment_groups.map(group => ({
    group_id: group.id,
    allocation_type: 'percentage',
    allocation_value: 0
  }))
}

function getGroupConfig(bandId, groupId) {
  if (!configs[bandId]) {
    return { allocation_type: 'percentage', allocation_value: 0 }
  }
  
  if (!configs[bandId].payment_group_config) {
    configs[bandId].payment_group_config = []
  }
  
  let groupConfig = configs[bandId].payment_group_config.find(g => g.group_id === groupId)
  
  if (!groupConfig) {
    groupConfig = {
      group_id: groupId,
      allocation_type: 'percentage',
      allocation_value: 0
    }
    configs[bandId].payment_group_config.push(groupConfig)
  }
  
  // Ensure values are never undefined
  if (groupConfig.allocation_value === undefined || groupConfig.allocation_value === null) {
    groupConfig.allocation_value = 0
  }
  if (!groupConfig.allocation_type) {
    groupConfig.allocation_type = 'percentage'
  }
  
  return groupConfig
}

const moneyFormat = (number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(number)
}

function getBandCutTypeDescription(type) {
  const descriptions = {
    percentage: 'Band takes a percentage of the total booking amount',
    fixed: 'Band takes a fixed dollar amount',
    tiered: 'Band cut varies based on the total booking amount',
    none: 'No band cut - all money goes to members'
  }
  return descriptions[type] || ''
}

function getMemberPayoutTypeDescription(type) {
  const descriptions = {
    equal_split: 'All members receive an equal share of the remaining amount after band cut',
    percentage: 'Each member receives a specific percentage of the remaining amount',
    fixed: 'Each member receives a fixed dollar amount',
    tiered: 'Payout varies based on the total booking amount using tier rules',
    member_specific: 'Each member has their own individual payout configuration'
  }
  return descriptions[type] || ''
}

function getMemberConfig(bandId, userId, memberType) {
  const band = props.bands.find(b => b.id === bandId)
  if (!band) return null
  
  if (!configs[bandId].member_specific_config) {
    configs[bandId].member_specific_config = []
  }
  
  let memberConfig = configs[bandId].member_specific_config.find(
    m => m.user_id === userId && m.member_type === memberType
  )
  
  if (!memberConfig) {
    let memberName = ''
    if (memberType === 'owner') {
      const owner = band.owners.find(o => o.user.id === userId)
      memberName = owner ? owner.user.name : ''
    } else if (memberType === 'member') {
      const member = band.members.find(m => m.user.id === userId)
      memberName = member ? member.user.name : ''
    }
    
    memberConfig = {
      user_id: userId,
      member_type: memberType,
      name: memberName,
      payout_type: 'equal_split',
      value: 0
    }
    configs[bandId].member_specific_config.push(memberConfig)
  }
  
  return memberConfig
}

function addProductionMember(bandId) {
  if (!configs[bandId].production_member_types) {
    configs[bandId].production_member_types = []
  }
  configs[bandId].production_member_types.push({
    name: '',
    type: 'fixed',
    value: 500
  })
}

function removeProductionMember(bandId, index) {
  if (configs[bandId].production_member_types) {
    configs[bandId].production_member_types.splice(index, 1)
  }
}

function calculate(bandId) {
  const band = props.bands.find(b => b.id === bandId)
  if (!band || !band.active_payout_config) {
    results[bandId] = null
    return
  }

  const config = band.active_payout_config
  const totalAmount = calculators[bandId].totalAmount || 0
  
  const result = {
    total_amount: parseFloat(totalAmount) || 0,
    band_cut: 0,
    distributable_amount: parseFloat(totalAmount) || 0,
    member_payouts: [],
    payment_group_payouts: [],
    total_member_payout: 0,
    remaining: 0
  }

  // Calculate band's cut
  const bandCutValue = parseFloat(config.band_cut_value) || 0
  
  if (config.band_cut_type === 'percentage') {
    result.band_cut = (totalAmount * bandCutValue) / 100
  } else if (config.band_cut_type === 'fixed') {
    result.band_cut = bandCutValue
  } else if (config.band_cut_type === 'tiered' && config.band_cut_tier_config && config.band_cut_tier_config.length > 0) {
    const tier = findApplicableTier(totalAmount, config.band_cut_tier_config)
    if (tier) {
      const tierValue = parseFloat(tier.value) || 0
      if (tier.type === 'percentage') {
        result.band_cut = (totalAmount * tierValue) / 100
      } else {
        result.band_cut = tierValue
      }
    }
  }

  result.distributable_amount = totalAmount - result.band_cut

  // If using payment groups, calculate based on groups
  if (config.use_payment_groups && config.payment_group_config && config.payment_group_config.length > 0) {
    calculatePayoutsWithGroups(band, result)
    results[bandId] = result
    return
  }

  // Calculate member count
  let memberCount = 0
  if (config.include_owners) {
    memberCount += band.owners.length
  }
  if (config.include_members) {
    memberCount += band.members.length
  }
  if (config.production_member_count > 0) {
    memberCount += config.production_member_count
  }

  // Calculate member payouts
  if (memberCount > 0) {
    if (config.member_payout_type === 'equal_split') {
      const perMemberAmount = result.distributable_amount / memberCount
      for (let i = 0; i < memberCount; i++) {
        result.member_payouts.push({
          type: i < band.owners.length ? 'owner' : 
                (i < (band.owners.length + band.members.length) ? 'member' : 'production'),
          amount: Math.max(perMemberAmount, config.minimum_payout)
        })
      }
      result.total_member_payout = result.member_payouts.reduce((sum, p) => sum + p.amount, 0)
    } else if (config.member_payout_type === 'tiered' && config.tier_config && config.tier_config.length > 0) {
      const tier = findApplicableTier(totalAmount, config.tier_config)
      if (tier) {
        let perMemberAmount = 0
        if (tier.type === 'percentage') {
          perMemberAmount = (result.distributable_amount * tier.value) / (100 * memberCount)
        } else {
          perMemberAmount = tier.value / memberCount
        }
        
        for (let i = 0; i < memberCount; i++) {
          result.member_payouts.push({
            type: i < band.owners.length ? 'owner' : 
                  (i < (band.owners.length + band.members.length) ? 'member' : 'production'),
            amount: Math.max(perMemberAmount, config.minimum_payout)
          })
        }
        result.total_member_payout = result.member_payouts.reduce((sum, p) => sum + p.amount, 0)
      }
    }
  }

  result.remaining = result.distributable_amount - result.total_member_payout
  results[bandId] = result
}

function findApplicableTier(amount, tiers) {
  for (const tier of tiers) {
    const min = tier.min || 0
    const max = tier.max || Infinity
    if (amount >= min && amount <= max) {
      return tier
    }
  }
  return null
}

function calculatePayoutsWithGroups(band, result) {
  const config = band.active_payout_config
  let remainingAmount = parseFloat(result.distributable_amount) || 0

  if (!band.payment_groups || band.payment_groups.length === 0) {
    return
  }

  // Sort by display_order - groups are allocated SEQUENTIALLY
  const sortedGroups = [...band.payment_groups].sort((a, b) => {
    const orderA = parseInt(a.display_order) || 0
    const orderB = parseInt(b.display_order) || 0
    return orderA - orderB
  })

  sortedGroups.forEach(group => {
    if (!group.is_active) {
      return
    }
    
    const groupConfig = config.payment_group_config?.find(g => g.group_id === group.id)
    
    if (!groupConfig) {
      return
    }

    // Calculate group allocation from REMAINING amount (sequential allocation)
    let groupAllocation = 0
    const allocationType = groupConfig.allocation_type || 'percentage'
    const allocationValue = parseFloat(groupConfig.allocation_value) || 0
    
    if (allocationType === 'percentage') {
      groupAllocation = (remainingAmount * allocationValue) / 100
    } else if (allocationType === 'fixed') {
      groupAllocation = allocationValue
    }

    // Calculate individual member payouts within the group
    const groupPayouts = []
    let totalGroupPayout = 0

    if (!group.users || group.users.length === 0) {
      return
    }

    // First pass: Calculate fixed and percentage payouts
    const memberPayouts = []
    group.users.forEach(user => {
      const pivotData = user.pivot || {}
      const payoutType = pivotData.payout_type || group.default_payout_type || 'equal_split'
      const payoutValue = parseFloat(pivotData.payout_value || group.default_payout_value || 0)
      
      let amount = 0
      
      if (payoutType === 'percentage') {
        amount = (groupAllocation * payoutValue) / 100
      } else if (payoutType === 'fixed') {
        amount = payoutValue
      }
      
      memberPayouts.push({
        user_id: user.id,
        user_name: user.name,
        payout_type: payoutType,
        amount: amount
      })

      if (payoutType !== 'equal_split') {
        totalGroupPayout += amount
      }
    })

    // Second pass: Calculate equal_split members
    const equalSplitMembers = memberPayouts.filter(p => p.payout_type === 'equal_split')
    if (equalSplitMembers.length > 0) {
      const groupRemainingAmount = groupAllocation - totalGroupPayout
      const perMemberAmount = groupRemainingAmount / equalSplitMembers.length
      
      memberPayouts.forEach(payout => {
        if (payout.payout_type === 'equal_split') {
          payout.amount = perMemberAmount || 0
          totalGroupPayout += (perMemberAmount || 0)
        }
      })
    }

    // Add to results
    result.payment_group_payouts.push({
      group_name: group.name,
      group_id: group.id,
      member_count: group.users.length,
      payouts: memberPayouts,
      total: totalGroupPayout
    })

    // Add to member payouts
    const minimumPayout = parseFloat(config.minimum_payout) || 0
    memberPayouts.forEach(payout => {
      result.member_payouts.push({
        type: 'payment_group',
        group_name: group.name,
        name: payout.user_name,
        payout_type: payout.payout_type,
        amount: Math.max(payout.amount || 0, minimumPayout)
      })
    })

    result.total_member_payout += (totalGroupPayout || 0)
    
    // SUBTRACT this group's allocation from remaining for next group (sequential allocation)
    remainingAmount -= totalGroupPayout
  })

  result.remaining = remainingAmount
}

function startEditing(band) {
  editingConfig[band.id] = true
  configs[band.id] = getDefaultConfig(band)
}

function cancelEditing(bandId) {
  editingConfig[bandId] = false
  const band = props.bands.find(b => b.id === bandId)
  configs[bandId] = getDefaultConfig(band)
}

function addBandCutTier(bandId) {
  if (!configs[bandId].band_cut_tier_config) {
    configs[bandId].band_cut_tier_config = []
  }
  configs[bandId].band_cut_tier_config.push({
    min: 0,
    max: 10000,
    type: 'percentage',
    value: 10
  })
}

function removeBandCutTier(bandId, index) {
  configs[bandId].band_cut_tier_config.splice(index, 1)
}

function addTier(bandId) {
  if (!configs[bandId].tier_config) {
    configs[bandId].tier_config = []
  }
  configs[bandId].tier_config.push({
    min: 0,
    max: 10000,
    type: 'percentage',
    value: 10
  })
}

function removeTier(bandId, index) {
  configs[bandId].tier_config.splice(index, 1)
}

function saveConfiguration(band) {
  saving[band.id] = true
  
  const url = band.active_payout_config 
    ? `/finances/payout-config/${band.id}/${band.active_payout_config.id}`
    : `/finances/payout-config/${band.id}`
  
  const method = band.active_payout_config ? 'put' : 'post'
  
  router[method](url, {
    ...configs[band.id],
    is_active: true
  }, {
    onSuccess: () => {
      saving[band.id] = false
      editingConfig[band.id] = false
      // The page will refresh with updated data
    },
    onError: (errors) => {
      saving[band.id] = false
      console.error('Save failed:', errors)
    }
  })
}
</script>
