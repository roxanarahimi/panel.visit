<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use phpseclib3\File\ASN1\Maps\Certificate;

class BrandCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
//        if($this->category != []){
//            $title = $this->category->title;
//        }else{
//            $title = '';
//        }
        return [
            "id" => (string)$this->id,
//            "title" => $title,
            "category"=>$this->category,
            "brand"=>$this->brand,
        ];
    }
}
