<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryBasedField;
use App\Models\CategoryBasedFieldInputType;
use App\Models\CategoryBasedFieldLookupValue;
use App\Models\CategoryType;
use App\Models\CategoryTypes;
use App\Models\Product;
use App\Models\ProductCondition;
use App\Models\ProductStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use ImageKit\ImageKit;

use App\Http\Resources\CategoryLookupResource;
use Illuminate\Validation\ValidationException;

use App\Http\Controllers\BaseController as BaseController;

class SellController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function getLookupCategories() {
        $categories = Category::where(["level" => 0])->get();
        return CategoryLookupResource::collection($categories);
    }

    public function getCategoryIdByType(Request $request) {
        $categoryId = [];
        // dd(CategoryTypes::where('value', $request->query('value'))->value('id'));
        $CategoryIDs = CategoryType::where("category_type_id", CategoryTypes::where('value', $request->query('value'))->value('id'))->get();
        foreach($CategoryIDs as $ttt) {
            $categoryId[] = $ttt->last_category_id;
        }
        return response()->json($categoryId, config("constants.SERVER_STATUS_CODES.SUCCESS"));
    }


    public function addProduct(Request $request)
    {
        try {
            $validated = $this->validate($request, [
                'user_id' => ['required'],
                'last_category_id' => ['required'],
                'category_type' => ['required'],
                'description' => [],
                'image' => ['required', 'array'],
                'title' => ['required'],
                'condition' => ['required'],
                'price' => [],
                'details' => [],
                'dealMethodForMeetup' => [],
                'meetupLocation' => [],
                'dealMethodForDelivery' => [],
                'status' => [],
            ]);
        } catch (ValidationException $validationException) {
            return $this->sendError($validationException->getMessage(), $validationException->errors());
        }
        $validated['user_id'] = User::query()->where('username', $validated['user_id'])->value('id');
        $validated['category_type_id'] = CategoryTypes::query()->where('value', $validated['category_type'])->value('id');
        $validated['product_status_id'] = ProductStatus::query()->where('value', $validated['status'])->value('id');
        $validated['product_condition_id'] = ProductCondition::query()->where('value', $validated['condition'])->value('id');


       $product = Product::query()->create($validated);

       if ($validated['category_type'] == 'for-sale')
           $product->sale_product()->create($validated);
       else if ($validated['category_type'] == 'for-free')
           $product->free_product()->create($validated);

        foreach ($validated['details'] as $category_based_field_id => $category_based_field_lookup_value_id) {
            $cID = CategoryBasedField::query()->find($category_based_field_id)->category_based_field_input_type_id;
            if ($cID == 1) {
                $product->specific_field_value()->create([
                    'category_based_field_id' => $category_based_field_id,
                    'text' => $category_based_field_lookup_value_id
                ]);
            } else {
                if(gettype($category_based_field_lookup_value_id)=='array') {
                    foreach($category_based_field_lookup_value_id as $value) {
                        $product->specific_field_value()->create([
                            'category_based_field_id' => $category_based_field_id,
                            'category_based_field_lookup_value_id' => $value
                        ]);
                    }
                } else {
                    $product->specific_field_value()->create([
                        'category_based_field_id' => $category_based_field_id,
                        'category_based_field_lookup_value_id' => $category_based_field_lookup_value_id
                    ]);
                }
            }
        }

        if (count($validated['dealMethodForMeetup']) > 0) {
            $product->deal_methods()->create([
                'deal_method_id' => 1,
                'description' => $validated['meetupLocation']
            ]);
        }

        if (count($validated['dealMethodForDelivery']) > 0) {
            $product->deal_methods()->create([
                'deal_method_id' => 2,
            ]);
        }

        foreach ($validated['image'] as $image) {
            $product->image()->create([
                'image' => $image
            ]);
        }
        $emailAddress = User::query()->find(7)->primary_email->email;
        return $this->sendResponse($product, 'The product is stored successfully.');
    }

    public function getImagekitToken() {

        $public_key = config("constants.MEDIA_STORAGE.PUBLIC_KEY");
        $private_key = config("constants.MEDIA_STORAGE.PRIVATE_KEY");
        $url_end_point = config("constants.MEDIA_STORAGE.ENDPOINT");

        $imageKit = new ImageKit(
            $public_key,
            $private_key,
            $url_end_point
        );

        $authenticationParameters = $imageKit->getAuthenticationParameters();

        return response()->json($authenticationParameters);
    }
}
