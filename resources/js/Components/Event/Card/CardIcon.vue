<template>
  <div class="flex items-center justify-center" style="width: 60px; height: 40px;">
    <img
      :src="iconSource"
      class="max-w-full max-h-full"
      style="object-fit: contain;"
      :title="type"
    >
  </div>
</template>
<script>
const prefix = 'gigIcons/';
export default {
    props:['type'],
    data(){
        return{
            iconSource:prefix+'other.png'
        }
    },
    created(){
        switch (this.type) {
            case 'Wedding':
                this.iconSource = prefix+'wedding.png'
                break;
            case 'Bar Gig':
                this.iconSource = prefix+'bar.png'
                break;
            case 'Casino':
                this.iconSource = prefix+'casino.png'
                break;
            case 'Special Event':
                this.iconSource = prefix+'special.png'
                break;
            case 'Charity':
                this.iconSource = prefix+'charity.png'
                break;
            case 'Festival':
                this.iconSource = prefix+'festival.png'
                break;
            case 'Private Party':
                this.iconSource = prefix+'private.png'
                break;
            case 'Mardi Gras Ball':
                this.iconSource = prefix+'mardiGras.png'
                break;
            default:
                break;
        }
    }
    
}
</script>