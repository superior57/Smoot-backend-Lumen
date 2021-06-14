<?php

namespace App\Http\Controllers;

use App\Models\CategoryTypes;
use App\Models\Product;
use App\Models\User;
use App\Models\ProductCondition;
use App\Models\ProductStatus;
use App\Models\SaleProduct;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Http\Resources\CategoryResource;
use App\Http\Controllers\BaseController as BaseController;

class ProductController extends BaseController
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

}
