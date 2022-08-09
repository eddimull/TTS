<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'band_id'=>'required',
            'event_name'=>'required',
            'venue_name'=>'required',
            'production_needed'=>'required|boolean',
            'backline_provided'=>'required|boolean',
            'zip'=>'required|numeric',
            'event_time'=>'required|Date',
            'band_loadin_time'=>'required|Date',
            'rhythm_loadin_time'=>'required|Date',
            'production_loadin_time'=>'required|Date',
            'band_loadin_time'=>'required|Date',
        ];
    }
}
