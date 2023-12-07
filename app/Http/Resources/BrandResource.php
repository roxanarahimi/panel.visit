<?php

namespace App\Http\Resources;

use App\Models\BrandCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */


    public function toArray($request)
    {
        $y = [];
//        $cats = $this->categories;
//        foreach($cats as $item){
//
//            $arr = json_encode(['title' => $item->title, 'category' => $item->category]);
//            $y.array_push($arr);
//        }

        return [
            "id" => (string)$this->id,
            "title" => $this->title,
            "active" => (boolean)$this->active,
            "categories" => BrandCategoryResource::collection($this->categories),
            "created_at" => date('Y-m-d', strtotime($this->created_at)),
            "updated_at" => date('Y-m-d', strtotime($this->updated_at)),
        ];
    }
}
