import { reactive } from 'vue'

export function usePayoutCalculator() {
  const getDefaultConfig = (band) => {
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

  const initializeGroupConfig = (band) => {
    if (!band.payment_groups || band.payment_groups.length === 0) {
      return []
    }
    
    return band.payment_groups.map(group => ({
      group_id: group.id,
      allocation_type: 'percentage',
      allocation_value: 0
    }))
  }

  const moneyFormat = (number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(number)
  }

  const findApplicableTier = (amount, tiers) => {
    for (const tier of tiers) {
      const min = tier.min || 0
      const max = tier.max || Infinity
      if (amount >= min && amount <= max) {
        return tier
      }
    }
    return null
  }

  const calculatePayoutsWithGroups = (band, result) => {
    const config = band.active_payout_config
    let remainingAmount = parseFloat(result.distributable_amount) || 0

    if (!band.payment_groups || band.payment_groups.length === 0) {
      return
    }

    const sortedGroups = [...band.payment_groups].sort((a, b) => {
      const orderA = parseInt(a.display_order) || 0
      const orderB = parseInt(b.display_order) || 0
      return orderA - orderB
    })

    sortedGroups.forEach(group => {
      if (!group.is_active) return
      
      const groupConfig = config.payment_group_config?.find(g => g.group_id === group.id)
      if (!groupConfig) return

      let groupAllocation = 0
      const allocationType = groupConfig.allocation_type || 'percentage'
      const allocationValue = parseFloat(groupConfig.allocation_value) || 0
      
      if (allocationType === 'percentage') {
        groupAllocation = (remainingAmount * allocationValue) / 100
      } else if (allocationType === 'fixed') {
        groupAllocation = allocationValue
      }

      const groupPayouts = []
      let totalGroupPayout = 0

      if (!group.users || group.users.length === 0) return

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

      result.payment_group_payouts.push({
        group_name: group.name,
        group_id: group.id,
        member_count: group.users.length,
        payouts: memberPayouts,
        total: totalGroupPayout
      })

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
      remainingAmount -= totalGroupPayout
    })

    result.remaining = remainingAmount
  }

  const calculate = (band, totalAmount) => {
    if (!band || !band.active_payout_config) {
      return null
    }

    const config = band.active_payout_config
    
    const result = {
      total_amount: parseFloat(totalAmount) || 0,
      band_cut: 0,
      distributable_amount: parseFloat(totalAmount) || 0,
      member_payouts: [],
      payment_group_payouts: [],
      total_member_payout: 0,
      remaining: 0
    }

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

    if (config.use_payment_groups && config.payment_group_config && config.payment_group_config.length > 0) {
      calculatePayoutsWithGroups(band, result)
      return result
    }

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
    return result
  }

  return {
    moneyFormat,
    calculate,
    findApplicableTier,
    getDefaultConfig,
    initializeGroupConfig
  }
}
