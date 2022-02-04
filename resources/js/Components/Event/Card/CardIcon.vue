<template>
  <img
    :src="iconSource"
    class="inline-block px-3"
    style="height:40px; width:auto"
    :title="type"
  > 
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