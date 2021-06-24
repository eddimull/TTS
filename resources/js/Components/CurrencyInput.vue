<template>
  <input 
    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
    ref="inputRef" 
    type="text"
    :value="formattedValue"
  >
</template>

<script>
import useCurrencyInput from 'vue-currency-input'

export default {
  name: 'CurrencyInput',
  props: {
    modelValue: Number, // Vue 2: value
    options: Object
  },
  setup (props) {
    const { formattedValue, inputRef } = useCurrencyInput({
      locale: undefined,
      currency: 'USD',
      currencyDisplay: 'symbol',
      valueRange: undefined,
      precision: 2,
      hideCurrencySymbolOnFocus: false,
      hideGroupingSeparatorOnFocus: true,
      hideNegligibleDecimalDigitsOnFocus: true,
      autoDecimalDigits: false,
      exportValueAsInteger: false,
      autoSign: true,
      useGrouping: true
    })

    return { inputRef, formattedValue }
  }
}
</script>