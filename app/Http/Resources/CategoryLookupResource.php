<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Category;

class CategoryLookupResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'value' => $this->id,
            'sub_categories' => CategoryLookupResource::collection(
                Category::where('master_category_id', $this->id)->orderBy('id')->get()
            )
        ];
    }
}