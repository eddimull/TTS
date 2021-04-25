<?php
namespace Database\Seeders;


use App\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\State;

class StatesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $states = [
            [
                'state_id' => 1,
                'country_id'=> 231,
                'state_name' => 'Alabama'
                
            ],

            [
                'state_id' => 2,
                'country_id'=> 231,
                'state_name' => 'Alaska'
                
            ],

            [
                'state_id' => 3,
                'country_id'=>231,
                'state_name' => 'Arizona'
               
            ],
            [
                'state_id' => 4,
                 'country_id'=>231,
                'state_name' => 'Arkansas'
               
            ],
            [
                'state_id' => 5,
                  'country_id'=>231,
                'state_name' => 'California'
              
            ],
            [
                'state_id' => 6,
                'country_id'=>231,
                'state_name' => 'Colorado'
                
            ],
            [
                'state_id' => 7,
                 'country_id'=>231,
                'state_name' => 'Connecticut'
               
            ],
            [
                'state_id' => 8,
                 'country_id'=>231,
                'state_name' => 'Delaware'
                
            ],
            [
                'state_id' => 9,
                'country_id'=>231,
                'state_name' => 'District of Columbia'
                
            ],
            [
                'state_id' => 10,
                 'country_id'=>231,
                'state_name' => 'Florida'
               
            ],
            [
                'state_id' => 11,
                'country_id'=>231,
                'state_name' => 'Georgia'
                
            ],
            [
                'state_id' => 12,
                 'country_id'=>231,
                'state_name' => 'Hawaii'
               
            ],
            [
                'state_id' => 13,
                 'country_id'=>231,
                'state_name' => 'Idaho'
               
            ],
            [
                'state_id' => 14,
                'country_id'=>231,
                'state_name' => 'Illinois'
                
            ],
            [
                'state_id' => 15,
                  'country_id'=>231,
                'state_name' => 'Indiana'
              
            ],
            [
                'state_id' => 16,
                  'country_id'=>231,
                'state_name' => 'Iowa'
              
            ],
            [
                'state_id' => 17,
                'country_id'=>231,
                'state_name' => 'Kansas'
                
            ],
            [
                'state_id' => 18,
                'country_id'=>231,
                'state_name' => 'Kentucky'
                
            ],
            [
                'state_id' => 19,
                'country_id'=>231,
                'state_name' => 'Louisiana'
                
            ],
            [
                'state_id' => 20,
                 'country_id'=>231,
                'state_name' => 'Maine'
               
            ],
            [
                'state_id' => 21,
                'country_id'=>231,
                'state_name' => 'Maryland'
                
            ],
            [
                'state_id' => 22,
                'country_id'=>231,
                'state_name' => 'Massachusetts'
                
            ],
            [
                'state_id' => 23,
                'country_id'=>231,
                'state_name' => 'Michigan'
                
            ],
            [
                'state_id' => 24,
                 'country_id'=>231,
                'state_name' => 'Minnesota'
               
            ],
            [
                'state_id' => 25,
                 'country_id'=>231,
                'state_name' => 'Mississippi'
               
            ],
            [
                'state_id' => 26,
                'country_id'=>231,
                'state_name' => 'Missouri'
                
            ],
            [
                'state_id' => 27,
                 'country_id'=>231,
                'state_name' => 'Montana'
               
            ],
            [
                'state_id' => 28,
                'country_id'=>231,
                'state_name' => 'Nebraska'
                
            ],
            [
                'state_id' => 29,
                 'country_id'=>95,
                'state_name' => 'Andhra Pradesh'
               
            ],
            [
                'state_id' => 30,
                'country_id'=>95 ,
                'state_name' => 'Arunachal Pradesh '
                           ],
            [
                'state_id' => 31,
                'country_id'=>95,
                'state_name' => 'Assam'
                
            ],
            [
                'state_id' => 32,
                'country_id'=>95,
                'state_name' => 'Bihar'
                
            ],
            [
                'state_id' => 33,
                'country_id'=>95,
                'state_name' => 'Chhattisgarh'
                
            ],
            [
                'state_id' => 34,
                'country_id'=>95,
                'state_name' => 'Goa'
                
            ],
            [
                'state_id' => 35,
                'country_id'=>95,
                'state_name' => 'Gujarat'
                
            ],
            [
                'state_id' => 36,
                'country_id'=>95,
                'state_name' => 'Haryana'
                
            ],
            [
                'state_id' => 37,
                'country_id'=>95,
                'state_name' => 'Himachal Pradesh'
                
            ],
            [
                'state_id' => 38,
                'country_id'=>95,
                'state_name' => 'Jharkhand'
                
            ],
            [
                'state_id' => 39,
                'country_id'=>95,
                'state_name' => 'Karnataka'
                
            ],
            [
                'state_id' => 40,
                'country_id'=>95,
                'state_name' => 'Kerala '
                
            ],
            [
                'state_id' => 41,
                'country_id'=>95,
                'state_name' => 'Madhya Pradesh'
                
            ],
            [
                'state_id' => 42,
                'country_id'=>95,
                'state_name' => 'Maharashtra'
                
            ],
            [
                'state_id' => 43,
                'country_id'=>95,
                'state_name' => 'Manipur'
                
            ],
            [
                'state_id' => 44,
                'country_id'=>95,
                'state_name' => 'Meghalaya'
                
            ],
            [
                'state_id' => 45,
                'country_id'=>95,
                'state_name' => 'Mizoram'
                
            ],
             [
                'state_id' => 46,
                'country_id'=>95,
                'state_name' => 'Nagaland'
                
            ],
             [
                'state_id' => 47,
                'country_id'=>95,
                'state_name' => 'Odisha'
                
            ],
             [
                'state_id' => 48,
                'country_id'=>95,
                'state_name' => 'Punjab'
                
            ],
             [
                'state_id' => 49,
                'country_id'=>95,
                'state_name' => 'Rajasthan'
                
            ],
             [
                'state_id' => 50,
                'country_id'=>95,
                'state_name' => 'Sikkim'
                
            ],
             [
                'state_id' => 51,
                'country_id'=>95,
                'state_name' => 'Tamil Nadu'
                
            ],
             [
                'state_id' => 52,
                'country_id'=>95,
                'state_name' => 'Telangana '
                
            ],
            [
                'state_id' => 53,
                'country_id'=>95,
                'state_name' => 'Tripura '
                
            ],
            [
                'state_id' => 54,
                'country_id'=>95,
                'state_name' => 'Uttar Pradesh'
                
            ],
            [
                'state_id' => 55,
                'country_id'=>95,
                'state_name' => 'Uttarakhand'
                
            ],
            [
                'state_id' => 56,
                'country_id'=>95,
                'state_name' => 'West Bengal '
                
            ],
            [
                'state_id' => 57,
                'country_id'=>95,
                'state_name' => ' Andaman and Nicobar Islands '
                
            ],
            [
                'state_id' => 58,
                'country_id'=>95,
                'state_name' => 'Chandigarh'
                
            ],
            [
                'state_id' => 59,
                'country_id'=>95,
                'state_name' => 'Dadra and Nagar Haveli and Daman and Diu (DNHDD)'
                
            ],
            [
                'state_id' => 60,
                'country_id'=>95,
                'state_name' => 'Delhi'
                
            ],
            [
                'state_id' => 61,
                'country_id'=>95,
                'state_name' => 'Jammu and Kashmir '
                
            ],
             [
                'state_id' => 62,
                'country_id'=>95,
                'state_name' => 'Ladakh  '
                
            ],
             [
                'state_id' => 63,
                'country_id'=>95,
                'state_name' => 'Lakshadweep'
                
            ],
             [
                'state_id' => 64,
                'country_id'=>95,
                'state_name' => 'Puducherry'
                
            ],
            [
                'state_id' => 65,
                'country_id'=>231,
                'state_name' => 'Nevada'
            ],
            [
                'state_id' => 66,
                'country_id'=>231,
                'state_name' => 'New Hampshire'

            ],
            [
                'state_id' => 67,
                'country_id'=>231,
                'state_name' => 'New Jersey'

            ],
            [
                'state_id' => 68,
                'country_id'=>231,
                'state_name' => 'New Mexico'

            ],
            [
                'state_id' => 69,
                'country_id'=>231,
                'state_name' => 'New York'

            ],            
            [
                'state_id' => 70,
                'country_id'=>231,
                'state_name' => 'North Carolina'

            ],            
            [
                'state_id' => 71,
                'country_id'=>231,
                'state_name' => 'North Dakota'

            ],            
            [
                'state_id' => 72,
                'country_id'=>231,
                'state_name' => 'Ohio'

            ],            
            [
                'state_id' => 73,
                'country_id'=>231,
                'state_name' => 'Oklahoma'
            ],            
            [
                'state_id' => 74,
                'country_id'=>231,
                'state_name' => 'Oregon'

            ],            
            [
                'state_id' => 75,
                'country_id'=>231,
                'state_name' => 'Pennsylvania'

            ],            
            [
                'state_id' => 76,
                'country_id'=>231,
                'state_name' => 'Rhode Island'

            ],                        
            [
                'state_id' => 77,
                'country_id'=>231,
                'state_name' => 'South Carolina'

            ],                                 
            [
                'state_id' => 78,
                'country_id'=>231,
                'state_name' => 'South Dakota'

            ],                           
            [
                'state_id' => 79,
                'country_id'=>231,
                'state_name' => 'Tennessee'

            ],                           
            [
                'state_id' => 80,
                'country_id'=>231,
                'state_name' => 'Texas'

            ],                        
            [
                'state_id' => 81,
                'country_id'=>231,
                'state_name' => 'Utah'

            ],                  
            [
                'state_id' => 82,
                'country_id'=>231,
                'state_name' => 'Vermont'

            ],                          
            [
                'state_id' => 83,
                'country_id'=>231,
                'state_name' => 'Virginia'

            ],                  
            [
                'state_id' => 84,
                'country_id'=>231,
                'state_name' => 'Washington'

            ],                             
            [
                'state_id' => 85,
                'country_id'=>231,
                'state_name' => 'West Virginia'

            ],                                                                                                           
            [
                'state_id' => 86,
                'country_id'=>231,
                'state_name' => 'Wisconsin'

            ],                  
            [
                'state_id' => 87,
                'country_id'=>231,
                'state_name' => 'Wyoming'

            ],                                                          
        ];

        State::insert($states);
    }
}