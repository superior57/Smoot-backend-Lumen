<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryBasedField;
use App\Models\CategoryBasedFieldInputType;
use App\Models\CategoryBasedFieldLookupValue;
use App\Models\CategoryType;
use App\Models\CategoryTypes;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Http\Resources\CategoryResource;

class CategoryController extends Controller
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

    public function addCategory(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $hasFieldSections = false;
        if (isset($request["fieldSections"]) && is_array($request["fieldSections"]) && isset($request["fieldSections"]['saleTypeFields']) || isset($request["fieldSections"]['rentTypeFields'])) {
            $hasFieldSections = true;
            $extractedFields = self::extractAdditionalFieldSections($request["fieldSections"]);
            unset($request["fieldSections"]);
            if (empty($extractedFields)) {
                return response()->json([
                    "message" => "One of the provided Field Sections field's Input type is missing or invalid, check and try again",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            }
            $request["fieldSections"] = $extractedFields;
            unset($extractedFields);
        } else {
            if (isset($request["fieldSections"])) {
                unset($request["fieldSections"]);
            }
        }
        $validationMessagesOfIncomingRequest = [
            "required" => "The :attribute field is required",
            "regex" => "The :attribute field values may only contain alphabetic, numeric, +&,-'()/ characters, and spaces",
            "max" => "The :attribute field may not be greater than 40 characters",
            "*.*.ddlFields.*.texts.*.regex" => "The drop-down list field values may only contain alphabetic, numeric, +&,.\"-'()/ characters, and spaces",
            "*.*.radioFields.*.texts.*.regex" => "The drop-down list field values may only contain alphabetic, numeric, +&,.\"-'()/ characters, and spaces",
            "*.*.checkboxFields.*.texts.*.regex" => "The drop-down list field values may only contain alphabetic, numeric, +&,.\"-'()/ characters, and spaces",
            "categoryTypes.*.distinct" => "Category types have duplicate values",
            "*.saleTypeFields.inputFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.saleTypeFields.ddlFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.saleTypeFields.radioFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.saleTypeFields.checkboxFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.inputFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.ddlFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.radioFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.checkboxFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
        ];
        $this->validate($request, [
            "category" => "bail|required|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub1Category" => "bail|" . Rule::requiredIf($request->sub2Category) . "|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub2Category" => "bail|" . Rule::requiredIf($request->sub3Category) . "|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub3Category" => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "categoryTypes" => "bail|required|array|exists:category_types,value",
            "categoryTypes.*" => "distinct",
            "fieldSections" => "array",
            "*.saleTypeFields.inputFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.inputFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.inputFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.inputFields.*.placeholderName" => "bail|nullable|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.inputFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.inputFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.inputFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.inputFields.*.placeholderName" => "bail|nullable|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.ddlFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.ddlFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.ddlFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.ddlFields.*.texts" => "required|array",
            "*.saleTypeFields.ddlFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.ddlFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.ddlFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.ddlFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.ddlFields.*.texts" => "required|array",
            "*.rentTypeFields.ddlFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.radioFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.radioFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.radioFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.radioFields.*.texts" => "required|array",
            "*.saleTypeFields.radioFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.radioFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.radioFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.radioFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.radioFields.*.texts" => "required|array",
            "*.rentTypeFields.radioFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.checkboxFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.checkboxFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.checkboxFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.checkboxFields.*.texts" => "required|array",
            "*.saleTypeFields.checkboxFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.checkboxFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.checkboxFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.checkboxFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.checkboxFields.*.texts" => "required|array",
            "*.rentTypeFields.checkboxFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
        ], $validationMessagesOfIncomingRequest);

        try {
            //string replacements
//            $request->category = cleanStringTitleWithDictionary($request->category, config("constants.DICTIONARY.EXCLUDED_WORDS.FOR_FIRST_LETTER_CAPITALIZE"));
//            $request->sub1Category = cleanStringTitleWithDictionary($request->sub1Category, config("constants.DICTIONARY.EXCLUDED_WORDS.FOR_FIRST_LETTER_CAPITALIZE"));
//            $request->sub2Category = cleanStringTitleWithDictionary($request->sub2Category, config("constants.DICTIONARY.EXCLUDED_WORDS.FOR_FIRST_LETTER_CAPITALIZE"));
//            $request->sub3Category = cleanStringTitleWithDictionary($request->sub3Category, config("constants.DICTIONARY.EXCLUDED_WORDS.FOR_FIRST_LETTER_CAPITALIZE"));
            $request->category = removeAllowedSpecialCharactersAtStatAndEnd($request->category);
            $request->sub1Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub1Category);
            $request->sub2Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub2Category);
            $request->sub3Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub3Category);
            //add slug
            $request->categorySlug = generateSlug($request->category);
            $request->sub1CategorySlug = generateSlug($request->sub1Category);
            $request->sub2CategorySlug = generateSlug($request->sub2Category);
            $request->sub3CategorySlug = generateSlug($request->sub3Category);

            if (!Category::where(["value" => $request->categorySlug, "level" => 0])->exists()) { // if first category does not already exist
                if (empty($request->sub1Category)) { // if sub 1 cat not provided
                    $lastCategoryId = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        false,
                        0
                    );
                } else if (empty($request->sub2Category)) { // if sub 2 cat not provided
                    $storeCategory = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        true,
                        0
                    );
                    $storeSub1Category = self::storeCategory(
                        $storeCategory,
                        $request->sub1Category,
                        $request->sub1CategorySlug,
                        true,
                        1
                    );
                    $lastCategoryId = $storeSub1Category;
                } else if (empty($request->sub3Category)) {
                    $storeCategory = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        true,
                        0
                    );
                    $storeSub1Category = self::storeCategory(
                        $storeCategory,
                        $request->sub1Category,
                        $request->sub1CategorySlug,
                        true,
                        1
                    );
                    $storeSub2Category = self::storeCategory(
                        $storeSub1Category,
                        $request->sub2Category,
                        $request->sub2CategorySlug,
                        true,
                        2
                    );
                    $lastCategoryId = $storeSub2Category;
                } else {
                    $storeCategory = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        true,
                        0
                    );
                    $storeSub1Category = self::storeCategory(
                        $storeCategory,
                        $request->sub1Category,
                        $request->sub1CategorySlug,
                        true,
                        1
                    );
                    $storeSub2Category = self::storeCategory(
                        $storeSub1Category,
                        $request->sub2Category,
                        $request->sub2CategorySlug,
                        true,
                        2
                    );
                    $storeSub3Category = self::storeCategory(
                        $storeSub2Category,
                        $request->sub3Category,
                        $request->sub3CategorySlug,
                        true,
                        3
                    );
                    $lastCategoryId = $storeSub3Category;
                }
            } else {// if first category does already exist
                if (empty($request->sub1Category)) { // if sub 1 cat not provided
                    return response()->json([
                        "message" => "The category you entered already exists as a main category",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                } else { // if sub 1 cat provided
                    // check if sub 1 cat does not already exist in sub 1 cat list || check if that does, then does this category tree already exists from main category tree
                    if (!Category::where(["value" => $request->sub1CategorySlug, "level" => 1])->exists() || !self::ifProvidedCategoryTreeExistsFromMainCategory($request->categorySlug, $request->sub1CategorySlug)) {
                        // before saving 1 sub cat category, check if sub cat 2 provided
                        if (empty($request->sub2Category)) {
                            //store the sub 1 cat under the main category
                            $lastCategoryId = self::storeCategory(
                                self::getCategoryIdBySlug($request->categorySlug),
                                $request->sub1Category,
                                $request->sub1CategorySlug,
                                true,
                                1
                            );
                            //and activate the main category
                            self::activateMainCategoryById(self::getCategoryIdBySlug($request->categorySlug));
                        } else if (empty($request->sub3Category)) {
                            $storeSub1Category = self::storeCategory(
                                self::getCategoryIdBySlug($request->categorySlug),
                                $request->sub1Category,
                                $request->sub1CategorySlug,
                                true,
                                1
                            );
                            $storeSub2Category = self::storeCategory(
                                $storeSub1Category,
                                $request->sub2Category,
                                $request->sub2CategorySlug,
                                true,
                                2
                            );
                            //and activate the main category
                            self::activateMainCategoryById(self::getCategoryIdBySlug($request->categorySlug));
                            $lastCategoryId = $storeSub2Category;
                        } else { //if sub 3 cat also provided
                            $storeSub1Category = self::storeCategory(
                                self::getCategoryIdBySlug($request->categorySlug),
                                $request->sub1Category,
                                $request->sub1CategorySlug,
                                true,
                                1
                            );
                            $storeSub2Category = self::storeCategory(
                                $storeSub1Category,
                                $request->sub2Category,
                                $request->sub2CategorySlug,
                                true,
                                2
                            );
                            $storeSub3Category = self::storeCategory(
                                $storeSub2Category,
                                $request->sub3Category,
                                $request->sub3CategorySlug,
                                true,
                                3
                            );
                            //and activate the main category
                            self::activateMainCategoryById(self::getCategoryIdBySlug($request->categorySlug));
                            $lastCategoryId = $storeSub3Category;
                        }
                    } else { // if that category tree (sub 1 cat in main cat) ready exists
                        if (empty($request->sub2Category)) { // if sub 2 cat not provided
                            return response()->json([
                                "message" => $request->sub1Category . " in " . $request->category . " already exists",
                            ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                        } else {  // if sub 2 cat provided
                            $cat2Status = self::ifProvidedCategoryTreeExistsFromMainCategory($request->categorySlug, $request->sub1CategorySlug, $request->sub2CategorySlug);
                            if (!Category::where(["value" => $request->sub2CategorySlug, "level" => 2])->exists() || !$cat2Status->is_category_tree_exists) {
                                if (empty($request->sub3Category)) {
                                    $lastCategoryId = self::storeCategory(
                                        $cat2Status->sub_1_category_id,
                                        $request->sub2Category,
                                        $request->sub2CategorySlug,
                                        true,
                                        2
                                    );
                                } else {
                                    $storeSub2Category = self::storeCategory(
                                        $cat2Status->sub_1_category_id,
                                        $request->sub2Category,
                                        $request->sub2CategorySlug,
                                        true,
                                        2
                                    );
                                    $lastCategoryId = self::storeCategory(
                                        $storeSub2Category,
                                        $request->sub3Category,
                                        $request->sub3CategorySlug,
                                        true,
                                        3
                                    );
                                }
                            } else { // if that category tree (sub 2 cat in main cat > sub 1 cat) ready exists
                                if (empty($request->sub3Category)) { // if sub 3 cat not provided
                                    return response()->json([
                                        "message" => $request->sub2Category . " in " . $request->category . " > " . $request->sub1Category . " already exists",
                                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                                } else { // if sub 3 cat provided
                                    $cat3Status = self::ifProvidedCategoryTreeExistsFromMainCategory($request->categorySlug, $request->sub1CategorySlug, $request->sub2CategorySlug, $request->sub3CategorySlug);
                                    if (!Category::where(["value" => $request->sub3CategorySlug, "level" => 3])->exists() || !$cat3Status->is_category_tree_exists) {
                                        $lastCategoryId = self::storeCategory(
                                            $cat3Status->sub_2_category_id,
                                            $request->sub3Category,
                                            $request->sub3CategorySlug,
                                            true,
                                            3
                                        );
                                    } else {
                                        return response()->json([
                                            "message" => $request->sub3Category . " in " . $request->category . " > " . $request->sub1Category . " > " . $request->sub2Category . " already exists",
                                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                                    }
                                }
                            }
                        }
                    }
                }
            } // end of if first category does already exist
            //save category types
            if ($hasFieldSections) {
                self::storeCategoryType($lastCategoryId, $request->categoryTypes, $request['fieldSections']);
            } else {
                self::storeCategoryType($lastCategoryId, $request->categoryTypes);
            }
            return response()->json([
                "message" => "Category has been added",
            ], config("constants.SERVER_STATUS_CODES.CREATED"));
        } catch (Exception $error) {
            Log::error($error);
            return response()->json([
                "error" => $error->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function storeCategory($masterCategoryId, $categoryText, $categorySlug, $isActive, $categoryLevel)
    {
        $category = new Category();
        $category->master_category_id = $masterCategoryId;
        $category->text = $categoryText;
        $category->value = $categorySlug;
        $category->is_active = $isActive;
        $category->level = $categoryLevel;
        $category->save();
        return $category->id;
    }

    public function ifProvidedCategoryTreeExistsFromMainCategory($mainCategorySlug, $sub1CategorySlug, $sub2CategorySlug = "", $sub3CategorySlug = "")
    {
        if (empty($sub2CategorySlug) && empty($sub3CategorySlug)) {
            return Category::where([
                "master_category_id" => self::getCategoryIdBySlug($mainCategorySlug),
                "value" => $sub1CategorySlug,
                "level" => 1
            ])->exists();
        } else if (!empty($sub2CategorySlug) && empty($sub3CategorySlug)) {
            $categoryId = Category::select("id")->where([
                "master_category_id" => self::getCategoryIdBySlug($mainCategorySlug),
                "value" => $sub1CategorySlug,
                "level" => 1
            ])->first()->id;

            $obj = new \stdClass();
            $obj->is_category_tree_exists = Category::where([
                "master_category_id" => $categoryId,
                "value" => $sub2CategorySlug,
                "level" => 2
            ])->exists();
            $obj->sub_1_category_id = $categoryId;
            return $obj;
        } else {
            $sub1CategoryId = Category::select("id")->where([
                "master_category_id" => self::getCategoryIdBySlug($mainCategorySlug),
                "value" => $sub1CategorySlug,
                "level" => 1
            ])->first()->id;
            $sub2CategoryId = Category::select("id")->where([ //
                "master_category_id" => $sub1CategoryId,
                "value" => $sub2CategorySlug,
                "level" => 2
            ])->first()->id;

            $obj = new \stdClass();
            $obj->is_category_tree_exists = Category::where([
                "master_category_id" => $sub2CategoryId,
                "value" => $sub3CategorySlug,
                "level" => 3
            ])->exists();
            $obj->sub_2_category_id = $sub2CategoryId;
            return $obj;
        }
    }

    public function getCategoryIdBySlug($categorySlug)
    {
        try {
            return Category::select("id")->where(["value" => $categorySlug])->first()->id;
        } catch (Exception $e) {
            return null;
        }
    }

    public function activateMainCategoryById($categoryId)
    {
        $category = Category::find($categoryId);
        $category->is_active = true;
        $category->save();
    }

    public function storeCategoryType($lastCategoryId, $types, $fieldSections = [])
    {
        foreach ($types as $type) {
            $categoryType = new CategoryType();
            $categoryType->last_category_id = $lastCategoryId;
            $categoryType->category_type_id = CategoryTypes::select("id")->where(["value" => $type])->first()->id;
            $categoryType->save();
            if (!empty($fieldSections)) {
                self::storeCategoryBasedField($categoryType->last_category_id, $categoryType->category_type_id, $type, $fieldSections);
            }
        }
    }

    public function storeCategoryTypeByCopying($lastCategoryId, $types, $fieldSections = [])
    {
        foreach ($types as $type) {
            $categoryType = new CategoryType();
            $categoryType->last_category_id = $lastCategoryId;
            $categoryType->category_type_id = CategoryTypes::select("id")->where(["text" => $type])->first()->id;
            $categoryType->save();
            if (!empty($fieldSections)) {
                self::storeCategoryBasedFieldByCopying($categoryType->last_category_id, $categoryType->category_type_id, $type, $fieldSections);
            }
        }
    }

    public function getCategorySuggestions(Request $request)
    {
        $this->validate($request, [
            'word' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40"
        ]);
        try {
            $suggestions = Category::select("text")->where("text", "ilike", $request->query("word") . '%')->where("level", 0)->get()->take(10);
            if ($suggestions->count()) {
                return response()->json([
                    "suggestions" => $suggestions,
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            }
            return response()->json([
                "suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getSub1CategorySuggestions(Request $request)
    {
        $this->validate($request, [
            'category' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            'word' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
        ]);
        if (!$request->query("category")) {
            return response()->json([
                "sub_1_category_suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        }
        $master_category_id = self::getCategoryIdBySlug(generateSlug($request->query("category")));
        if (!$master_category_id) {
            return response()->json([
                "sub_1_category_suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        }
        try {
            $suggestions = Category::select("text")->where("text", "ilike", $request->query("word") . '%')
                ->where("master_category_id", "=", $master_category_id)
                ->where("level", 1)
                ->get()
                ->take(10);
            if (!$suggestions->count()) {
                return response()->json([
                    "sub_1_category_suggestions" => [],
                ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
            }
            return response()->json([
                "sub_1_category_suggestions" => $suggestions,
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getSub2CategorySuggestions(Request $request)
    {
        $this->validate($request, [
            'category' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            'sub1Category' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            'word' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
        ]);
        if (!$request->query("category") || !$request->query("sub1Category")) {
            return response()->json([
                "sub_2_category_suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        }
        try {
            if (self::ifProvidedCategoryTreeExistsFromMainCategory(generateSlug($request->query("category")), generateSlug($request->query("sub1Category")))) {
                $master_category_id = self::getCategoryIdBySlug(generateSlug($request->query("sub1Category")));
                $suggestions = Category::select("text")->where("text", "ilike", $request->query("word") . '%')
                    ->where("master_category_id", "=", $master_category_id)
                    ->where("level", 2)
                    ->get()
                    ->take(10);
                if ($suggestions->count()) {
                    return response()->json([
                        "sub_2_category_suggestions" => $suggestions,
                    ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                }
            }
            return response()->json([
                "sub_2_category_suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getSub3CategorySuggestions(Request $request)
    {
        $this->validate($request, [
            'category' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            'sub1Category' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            'sub2Category' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            'word' => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
        ]);
        if (!$request->query("category") || !$request->query("sub1Category") || !$request->query("sub2Category")) {
            return response()->json([
                "sub_3_category_suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        }
        try {
            if (self::ifProvidedCategoryTreeExistsFromMainCategory(generateSlug($request->query("category")), generateSlug($request->query("sub1Category")), generateSlug($request->query("sub2Category")))) {
                $master_category_id = self::getCategoryIdBySlug(generateSlug($request->query("sub2Category")));
                $suggestions = Category::select("text")->where("text", "ilike", $request->query("word") . '%')
                    ->where("master_category_id", "=", $master_category_id)
                    ->where("level", 3)
                    ->get()
                    ->take(10);
                if ($suggestions->count()) {
                    return response()->json([
                        "sub_3_category_suggestions" => $suggestions,
                    ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                }
            }
            return response()->json([
                "sub_3_category_suggestions" => [],
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function storeCategoryBasedField($lastCategoryId, $categoryTypeId, $type, $fieldSections)
    {
        $indexToForEach = "";
        if ($type === "for-sale") {
            $indexToForEach = "saleTypeFields";
        } else if ($type === "for-rent") {
            $indexToForEach = "rentTypeFields";
        }

        if (isset($fieldSections[$indexToForEach])) {
            if (isset($fieldSections[$indexToForEach]["inputFields"])) {
                foreach ($fieldSections[$indexToForEach]["inputFields"] as $inputField) {
                    $categoryBasedField = new CategoryBasedField();
                    $categoryBasedField->last_category_id = $lastCategoryId;
                    $categoryBasedField->category_type_id = $categoryTypeId;
                    $categoryBasedField->category_based_field_input_type_id = CategoryBasedFieldInputType::select("id")->where(["value" => $inputField["inputType"]])->first()->id;
                    $categoryBasedField->name = $inputField["labelName"];
                    $categoryBasedField->is_required = $inputField["isRequired"] === "yes" ? true : false;
                    $categoryBasedField->placeholder = empty($inputField["placeholderName"]) ? null : $inputField["placeholderName"];
                    $categoryBasedField->save();
                }
            }
            if (isset($fieldSections[$indexToForEach]["ddlFields"])) {
                foreach ($fieldSections[$indexToForEach]["ddlFields"] as $inputField) {
                    $categoryBasedField = new CategoryBasedField();
                    $categoryBasedField->last_category_id = $lastCategoryId;
                    $categoryBasedField->category_type_id = $categoryTypeId;
                    $categoryBasedField->category_based_field_input_type_id = CategoryBasedFieldInputType::select("id")->where(["value" => $inputField["inputType"]])->first()->id;
                    $categoryBasedField->name = $inputField["labelName"];
                    $categoryBasedField->is_required = $inputField["isRequired"] === "yes" ? true : false;
                    $categoryBasedField->save();
                    self::storeCategoryBasedFieldLookupValue($categoryBasedField->id, $inputField["texts"], $inputField["values"]);
                }
            }
            if (isset($fieldSections[$indexToForEach]["radioFields"])) {
                foreach ($fieldSections[$indexToForEach]["radioFields"] as $inputField) {
                    $categoryBasedField = new CategoryBasedField();
                    $categoryBasedField->last_category_id = $lastCategoryId;
                    $categoryBasedField->category_type_id = $categoryTypeId;
                    $categoryBasedField->category_based_field_input_type_id = CategoryBasedFieldInputType::select("id")->where(["value" => $inputField["inputType"]])->first()->id;
                    $categoryBasedField->name = $inputField["labelName"];
                    $categoryBasedField->is_required = $inputField["isRequired"] === "yes" ? true : false;
                    $categoryBasedField->save();
                    self::storeCategoryBasedFieldLookupValue($categoryBasedField->id, $inputField["texts"], $inputField["values"]);
                }
            }
            if (isset($fieldSections[$indexToForEach]["checkboxFields"])) {
                foreach ($fieldSections[$indexToForEach]["checkboxFields"] as $inputField) {
                    $categoryBasedField = new CategoryBasedField();
                    $categoryBasedField->last_category_id = $lastCategoryId;
                    $categoryBasedField->category_type_id = $categoryTypeId;
                    $categoryBasedField->category_based_field_input_type_id = CategoryBasedFieldInputType::select("id")->where(["value" => $inputField["inputType"]])->first()->id;
                    $categoryBasedField->name = $inputField["labelName"];
                    $categoryBasedField->is_required = $inputField["isRequired"] === "yes" ? true : false;
                    $categoryBasedField->save();
                    self::storeCategoryBasedFieldLookupValue($categoryBasedField->id, $inputField["texts"], $inputField["values"]);
                }
            }
        }
    }

    public function storeCategoryBasedFieldByCopying($lastCategoryId, $categoryTypeId, $type, $fieldSections)
    {
        $indexToForEach = "";
        if ($type === "For sale") {
            $indexToForEach = "sale_type_fields";
        } else if ($type === "For rent") {
            $indexToForEach = "rent_type_fields";
        }
        if (isset($fieldSections[$indexToForEach])) {
            foreach ($fieldSections[$indexToForEach] as $inputField) {
                $categoryBasedField = new CategoryBasedField();
                $categoryBasedField->last_category_id = $lastCategoryId;
                $categoryBasedField->category_type_id = $categoryTypeId;
                $categoryBasedField->category_based_field_input_type_id = CategoryBasedFieldInputType::select("id")->where(["value" => $inputField->input_type])->first()->id;
                $categoryBasedField->name = $inputField->field_name;
                $categoryBasedField->is_required = $inputField->is_required ? true : false;
                if ($inputField->input_type === "text") {
                    $categoryBasedField->placeholder = empty($inputField->placeholder) ? null : $inputField->placeholder;
                    $categoryBasedField->save();

                } else {
                    $categoryBasedField->save();
                    self::storeCategoryBasedFieldLookupValueByCopying($categoryBasedField->id, $inputField->tags);
                }
            }
        }
    }

    public function storeCategoryBasedFieldLookupValue($categoryBasedFieldId, $texts, $values)
    {
        foreach ($texts as $key => $text) {
            $categoryBasedFieldLookupValue = new CategoryBasedFieldLookupValue();
            $categoryBasedFieldLookupValue->category_based_field_id = $categoryBasedFieldId;
            $categoryBasedFieldLookupValue->text = $text;
            $categoryBasedFieldLookupValue->value = $values[$key];
            $categoryBasedFieldLookupValue->save();
        }
    }

    public function storeCategoryBasedFieldLookupValueByCopying($categoryBasedFieldId, $texts)
    {
        foreach ($texts as $key => $text) {
            $categoryBasedFieldLookupValue = new CategoryBasedFieldLookupValue();
            $categoryBasedFieldLookupValue->category_based_field_id = $categoryBasedFieldId;
            $categoryBasedFieldLookupValue->text = $text;
            $categoryBasedFieldLookupValue->value = generateSlug($text);
            $categoryBasedFieldLookupValue->save();
        }
    }

    public function viewAllCategories()
    {
//        $hasKeyword = is_null($keyword) || empty($keyword) ? false : true;
        $category_list = [];
        try {
            $categories = Category::select(["id", "text as category"])->where(["level" => 0])->get();
            if ($categories->count()) {
                foreach ($categories as $category) {
                    $sub_1_categories = Category::select(["id", "text as sub_1_category"])->where(["master_category_id" => $category->id, "level" => 1])->get();
                    if ($sub_1_categories->count()) { // if category has sub 1 cats
                        foreach ($sub_1_categories as $sub_1_category) {
                            $sub_2_categories = Category::select(["id", "text as sub_2_category"])->where(["master_category_id" => $sub_1_category->id, "level" => 2])->get();
                            if ($sub_2_categories->count()) { // if sub cat 1 has sub 2 cats
                                foreach ($sub_2_categories as $sub_2_category) {
                                    $sub_3_categories = Category::select(["id", "text as sub_3_category"])->where(["master_category_id" => $sub_2_category->id, "level" => 3])->get();
                                    if ($sub_3_categories->count()) {  // if sub cat 2 has sub 3 cats
                                        foreach ($sub_3_categories as $sub_3_category) {
//                                            $categoryDetails = self::getSelectedCategoryDetailsWithFunction($sub_3_category->id);
                                            $temp = new \stdClass();
                                            $temp->id = $sub_3_category->id;
                                            $temp->category = $category->category;
                                            $temp->sub_1_category = $sub_1_category->sub_1_category;
                                            $temp->sub_2_category = $sub_2_category->sub_2_category;
                                            $temp->sub_3_category = $sub_3_category->sub_3_category;
                                            $temp->category_types = [];
                                            $temp->category_details = [
                                                "sale_type_fields" => [],
                                                "rent_type_fields" => [],
                                            ];
                                            array_push($category_list, $temp);
                                        }
                                    } else { // if there is no sub 3 cat for sub 1 cat
//                                        $categoryDetails = self::getSelectedCategoryDetailsWithFunction($sub_2_category->id);
                                        $temp = new \stdClass();
                                        $temp->id = $sub_2_category->id;
                                        $temp->category = $category->category;
                                        $temp->sub_1_category = $sub_1_category->sub_1_category;
                                        $temp->sub_2_category = $sub_2_category->sub_2_category;
                                        $temp->sub_3_category = "";
                                        $temp->category_types = [];
                                        $temp->category_details = [
                                            "sale_type_fields" => [],
                                            "rent_type_fields" => [],
                                        ];
                                        array_push($category_list, $temp);
                                    }
                                }
                            } else { // if there is no sub 2 cat for the sub 1 cat
//                                $categoryDetails = self::getSelectedCategoryDetailsWithFunction($sub_1_category->id);
                                $temp = new \stdClass();
                                $temp->id = $sub_1_category->id;
                                $temp->category = $category->category;
                                $temp->sub_1_category = $sub_1_category->sub_1_category;
                                $temp->sub_2_category = "";
                                $temp->sub_3_category = "";
                                $temp->category_types = [];
                                $temp->category_details = [
                                    "sale_type_fields" => [],
                                    "rent_type_fields" => [],
                                ];
                                array_push($category_list, $temp);
                            }
                        }
                    } else { // if there is no sub 1 cat for the category
//                        $categoryDetails = self::getSelectedCategoryDetailsWithFunction($category->id);
                        $temp = new \stdClass();
                        $temp->id = $category->id;
                        $temp->category = $category->category;
                        $temp->sub_1_category = "";
                        $temp->sub_2_category = "";
                        $temp->sub_3_category = "";
                        $temp->category_types = [];
                        $temp->category_details = [
                            "sale_type_fields" => [],
                            "rent_type_fields" => [],
                        ];
                        array_push($category_list, $temp);
                    }
                }
            } else {
                return response()->json([
                    "message" => "Category list is empty",
                ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
            }
            return response()->json([
                "category_list" => $category_list,
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => $exception->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getSelectedCategoryDetails(Request $request)
    {
        $category_types = [];
        $category_fields = [
            "sale_type_fields" => [],
            "rent_type_fields" => [],
        ];
        $this->validate($request, [
            'id' => "bail|required|regex:/^[0-9]+$/",
        ]);
        try {
            $typesOfTheCategory = CategoryType::select("category_type_id as id")->where(["last_category_id" => $request->query("id")])->get();
            if ($typesOfTheCategory->count()) {
                foreach ($typesOfTheCategory as $typeOfTheCategory) {
                    $categoryType = CategoryTypes::select(["text as type", "value"])->find($typeOfTheCategory->id);
                    array_push($category_types, $categoryType->value);
                    $categoryBasedFields = CategoryBasedField::select([
                        "id",
                        "category_based_field_input_type_id as input_type_id",
                        "name as field_name",
                        "placeholder",
                        "is_required",
                    ])->where(["last_category_id" => $request->query("id")])->where(["category_type_id" => $typeOfTheCategory->id])->get();
                    if ($categoryBasedFields->count()) {
                        foreach ($categoryBasedFields as $categoryBasedField) {
                            $fieldInputType = CategoryBasedFieldInputType::select("value")->find($categoryBasedField->input_type_id);
                            $temp = new \stdClass();
                            $temp->input_type = $fieldInputType->value;
                            $temp->field_name = $categoryBasedField->field_name;
                            $temp->id = $categoryBasedField->id;
                            $temp->is_required = $categoryBasedField->is_required;
                            if ($fieldInputType->value === "text") {
                                $temp->placeholder = is_null($categoryBasedField->placeholder) ? "" : $categoryBasedField->placeholder;
                            } else {
                                foreach (CategoryBasedFieldLookupValue::select("id", "text", "value")->where(["category_based_field_id" => $categoryBasedField->id])->get() as $lookupValue) {
                                    $temp->tags[] = [
                                        'id' => $lookupValue->id,
                                        'text'=> $lookupValue->text,
                                        'value' => $lookupValue->id
                                    ];
                                }
                            }
                            if ($categoryType->value === "for-sale") {
                                array_push($category_fields["sale_type_fields"], $temp);
                            } else {
                                array_push($category_fields["rent_type_fields"], $temp);
                            }
                        }
                    }
                }
                return response()->json([
                    "category_types" => $category_types,
                    "category_details" => $category_fields,
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            }
            return response()->json([
                "message" => "Category not found",
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => "Something went wrong while getting the category details, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getSelectedCategoryDetailsForEdit(Request $request)
    {
        $category_types = [];
        $category_fields = [
            "sale_type_fields" => [],
            "rent_type_fields" => [],
        ];

        $this->validate($request, [
            'id' => "bail|required|regex:/^[0-9]+$/",
        ]);
        try {
            $typesOfTheCategory = CategoryType::select("category_type_id as id")->where(["last_category_id" => $request->query("id")])->get();
            if ($typesOfTheCategory->count()) {
                foreach ($typesOfTheCategory as $typeOfTheCategory) {
                    $categoryType = CategoryTypes::select(["text", "value"])->find($typeOfTheCategory->id);
                    array_push($category_types, $categoryType->value);
                    $categoryBasedFields = CategoryBasedField::select([
                        "id",
                        "category_based_field_input_type_id as input_type_id",
                        "name as field_name",
                        "placeholder",
                        "is_required",
                    ])->where(["last_category_id" => $request->query("id")])->where(["category_type_id" => $typeOfTheCategory->id])->get();
                    if ($categoryBasedFields->count()) {
                        foreach ($categoryBasedFields as $categoryBasedField) {
                            $fieldInputType = CategoryBasedFieldInputType::select("value")->find($categoryBasedField->input_type_id);
                            $temp = new \stdClass();
                            $temp->id = $categoryBasedField->id;
                            $temp->input_type = $fieldInputType->value;
                            $temp->field_name = $categoryBasedField->field_name;
                            $temp->is_required = $categoryBasedField->is_required ? "yes" : "no";
                            if ($fieldInputType->value === "text") {
                                $temp->placeholder = is_null($categoryBasedField->placeholder) ? "" : $categoryBasedField->placeholder;
                            } else {
                                foreach (CategoryBasedFieldLookupValue::select(["id", "text"])->where(["category_based_field_id" => $categoryBasedField->id])->get() as $lookupValue) {
                                    $temp->tags[] = $lookupValue;
                                }
                            }
                            if ($categoryType->value === "for-sale") {
                                array_push($category_fields["sale_type_fields"], $temp);
                            } else {
                                array_push($category_fields["rent_type_fields"], $temp);
                            }
                        }
                    }
                }
                return response()->json([
                    "category_types" => $category_types,
                    "category_details" => $category_fields,
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            }
            return response()->json([
                "message" => "Category not found",
            ], config("constants.SERVER_STATUS_CODES.NOT_FOUND"));
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getSelectedCategoryDetailsWithFunction($categoryId)
    {
        $category_types = [];
        $category_fields = [
            "sale_type_fields" => [],
            "rent_type_fields" => [],
        ];
        $typesOfTheCategory = CategoryType::select("category_type_id as id")->where(["last_category_id" => $categoryId])->get();
        if ($typesOfTheCategory->count()) {
            foreach ($typesOfTheCategory as $typeOfTheCategory) {
                $categoryType = CategoryTypes::select(["text as type", "value"])->find($typeOfTheCategory->id);
                array_push($category_types, $categoryType->type);
                $categoryBasedFields = CategoryBasedField::select([
                    "id",
                    "category_based_field_input_type_id as input_type_id",
                    "name as field_name",
                    "placeholder",
                    "is_required",
                ])->where(["last_category_id" => $categoryId])->where(["category_type_id" => $typeOfTheCategory->id])->get();
                if ($categoryBasedFields->count()) {
                    foreach ($categoryBasedFields as $categoryBasedField) {
                        $fieldInputType = CategoryBasedFieldInputType::select("value")->find($categoryBasedField->input_type_id);
                        $temp = new \stdClass();
                        $temp->input_type = $fieldInputType->value;
                        $temp->field_name = $categoryBasedField->field_name;
                        $temp->is_required = $categoryBasedField->is_required;
                        if ($fieldInputType->value === "text") {
                            $temp->placeholder = is_null($categoryBasedField->placeholder) ? "" : $categoryBasedField->placeholder;
                        } else {
                            foreach (CategoryBasedFieldLookupValue::select("text")->where(["category_based_field_id" => $categoryBasedField->id])->get() as $lookupValue) {
                                $temp->tags[] = $lookupValue->text;
                            }
                        }
                        if ($categoryType->value === "for-sale") {
                            array_push($category_fields["sale_type_fields"], $temp);
                        } else {
                            array_push($category_fields["rent_type_fields"], $temp);
                        }
                    }
                }
            }
        }
        return [$category_types, $category_fields];
    }

    public function addCategoryAndCodyAdditionalFields(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $validationMessagesOfIncomingRequest = [
            "required" => "The :attribute field is required",
            "regex" => "The :attribute field values may only contain alphabetic, numeric, +&,-'()/ characters, and spaces",
            "max" => "The :attribute field may not be greater than 40 characters",
            "exists" => "Entered category id doesn't exist or it doesn't have any additional fields to copy",
            "categoryId.regex" => "The :attribute field value may only contain numeric value",

        ];
        $this->validate($request, [
            "categoryId" => "bail|required|regex:/^[0-9]+$/|exists:category_based_fields,last_category_id",
            "category" => "bail|required|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub1Category" => "bail|" . Rule::requiredIf($request->sub2Category) . "|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub2Category" => "bail|" . Rule::requiredIf($request->sub3Category) . "|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub3Category" => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
        ], $validationMessagesOfIncomingRequest);
        try {
            $request->category = removeAllowedSpecialCharactersAtStatAndEnd($request->category);
            $request->sub1Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub1Category);
            $request->sub2Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub2Category);
            $request->sub3Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub3Category);
            //add slug
            $request->categorySlug = generateSlug($request->category);
            $request->sub1CategorySlug = generateSlug($request->sub1Category);
            $request->sub2CategorySlug = generateSlug($request->sub2Category);
            $request->sub3CategorySlug = generateSlug($request->sub3Category);

            if (!Category::where(["value" => $request->categorySlug, "level" => 0])->exists()) { // if first category does not already exist
                if (empty($request->sub1Category)) { // if sub 1 cat not provided
                    $lastCategoryId = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        false,
                        0
                    );
                } else if (empty($request->sub2Category)) { // if sub 2 cat not provided
                    $storeCategory = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        true,
                        0
                    );
                    $storeSub1Category = self::storeCategory(
                        $storeCategory,
                        $request->sub1Category,
                        $request->sub1CategorySlug,
                        true,
                        1
                    );
                    $lastCategoryId = $storeSub1Category;
                } else if (empty($request->sub3Category)) {
                    $storeCategory = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        true,
                        0
                    );
                    $storeSub1Category = self::storeCategory(
                        $storeCategory,
                        $request->sub1Category,
                        $request->sub1CategorySlug,
                        true,
                        1
                    );
                    $storeSub2Category = self::storeCategory(
                        $storeSub1Category,
                        $request->sub2Category,
                        $request->sub2CategorySlug,
                        true,
                        2
                    );
                    $lastCategoryId = $storeSub2Category;
                } else {
                    $storeCategory = self::storeCategory(
                        null,
                        $request->category,
                        $request->categorySlug,
                        true,
                        0
                    );
                    $storeSub1Category = self::storeCategory(
                        $storeCategory,
                        $request->sub1Category,
                        $request->sub1CategorySlug,
                        true,
                        1
                    );
                    $storeSub2Category = self::storeCategory(
                        $storeSub1Category,
                        $request->sub2Category,
                        $request->sub2CategorySlug,
                        true,
                        2
                    );
                    $storeSub3Category = self::storeCategory(
                        $storeSub2Category,
                        $request->sub3Category,
                        $request->sub3CategorySlug,
                        true,
                        3
                    );
                    $lastCategoryId = $storeSub3Category;
                }
            } else {// if first category does already exist
                if (empty($request->sub1Category)) { // if sub 1 cat not provided
                    return response()->json([
                        "message" => "The category you entered already exists as a main category",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                } else { // if sub 1 cat provided
                    // check if sub 1 cat does not already exist in sub 1 cat list || check if that does, then does this category tree already exists from main category tree
                    if (!Category::where(["value" => $request->sub1CategorySlug, "level" => 1])->exists() || !self::ifProvidedCategoryTreeExistsFromMainCategory($request->categorySlug, $request->sub1CategorySlug)) {
                        // before saving 1 sub cat category, check if sub cat 2 provided
                        if (empty($request->sub2Category)) {
                            //store the sub 1 cat under the main category
                            $lastCategoryId = self::storeCategory(
                                self::getCategoryIdBySlug($request->categorySlug),
                                $request->sub1Category,
                                $request->sub1CategorySlug,
                                true,
                                1
                            );
                            //and activate the main category
                            self::activateMainCategoryById(self::getCategoryIdBySlug($request->categorySlug));
                        } else if (empty($request->sub3Category)) {
                            $storeSub1Category = self::storeCategory(
                                self::getCategoryIdBySlug($request->categorySlug),
                                $request->sub1Category,
                                $request->sub1CategorySlug,
                                true,
                                1
                            );
                            $storeSub2Category = self::storeCategory(
                                $storeSub1Category,
                                $request->sub2Category,
                                $request->sub2CategorySlug,
                                true,
                                2
                            );
                            //and activate the main category
                            self::activateMainCategoryById(self::getCategoryIdBySlug($request->categorySlug));
                            $lastCategoryId = $storeSub2Category;
                        } else { //if sub 3 cat also provided
                            $storeSub1Category = self::storeCategory(
                                self::getCategoryIdBySlug($request->categorySlug),
                                $request->sub1Category,
                                $request->sub1CategorySlug,
                                true,
                                1
                            );
                            $storeSub2Category = self::storeCategory(
                                $storeSub1Category,
                                $request->sub2Category,
                                $request->sub2CategorySlug,
                                true,
                                2
                            );
                            $storeSub3Category = self::storeCategory(
                                $storeSub2Category,
                                $request->sub3Category,
                                $request->sub3CategorySlug,
                                true,
                                3
                            );
                            //and activate the main category
                            self::activateMainCategoryById(self::getCategoryIdBySlug($request->categorySlug));
                            $lastCategoryId = $storeSub3Category;
                        }
                    } else { // if that category tree (sub 1 cat in main cat) ready exists
                        if (empty($request->sub2Category)) { // if sub 2 cat not provided
                            return response()->json([
                                "message" => $request->sub1Category . " in " . $request->category . " already exists",
                            ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                        } else {  // if sub 2 cat provided
                            $cat2Status = self::ifProvidedCategoryTreeExistsFromMainCategory($request->categorySlug, $request->sub1CategorySlug, $request->sub2CategorySlug);
                            if (!Category::where(["value" => $request->sub2CategorySlug, "level" => 2])->exists() || !$cat2Status->is_category_tree_exists) {
                                if (empty($request->sub3Category)) {
                                    $lastCategoryId = self::storeCategory(
                                        $cat2Status->sub_1_category_id,
                                        $request->sub2Category,
                                        $request->sub2CategorySlug,
                                        true,
                                        2
                                    );
                                } else {
                                    $storeSub2Category = self::storeCategory(
                                        $cat2Status->sub_1_category_id,
                                        $request->sub2Category,
                                        $request->sub2CategorySlug,
                                        true,
                                        2
                                    );
                                    $lastCategoryId = self::storeCategory(
                                        $storeSub2Category,
                                        $request->sub3Category,
                                        $request->sub3CategorySlug,
                                        true,
                                        3
                                    );
                                }
                            } else { // if that category tree (sub 2 cat in main cat > sub 1 cat) ready exists
                                if (empty($request->sub3Category)) { // if sub 3 cat not provided
                                    return response()->json([
                                        "message" => $request->sub2Category . " in " . $request->category . " > " . $request->sub1Category . " already exists",
                                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                                } else { // if sub 3 cat provided
                                    $cat3Status = self::ifProvidedCategoryTreeExistsFromMainCategory($request->categorySlug, $request->sub1CategorySlug, $request->sub2CategorySlug, $request->sub3CategorySlug);
                                    if (!Category::where(["value" => $request->sub3CategorySlug, "level" => 3])->exists() || !$cat3Status->is_category_tree_exists) {
                                        $lastCategoryId = self::storeCategory(
                                            $cat3Status->sub_2_category_id,
                                            $request->sub3Category,
                                            $request->sub3CategorySlug,
                                            true,
                                            3
                                        );
                                    } else {
                                        return response()->json([
                                            "message" => $request->sub3Category . " in " . $request->category . " > " . $request->sub1Category . " > " . $request->sub2Category . " already exists",
                                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                                    }
                                }
                            }
                        }
                    }
                }
            } // end of if first category does already exist
            //save category types
            $copyingCategoryTypesAndAdditionalFieldsOfSelectedCategory = self::getSelectedCategoryDetailsWithFunction($request->categoryId);
            self::storeCategoryTypeByCopying($lastCategoryId, $copyingCategoryTypesAndAdditionalFieldsOfSelectedCategory[0], $copyingCategoryTypesAndAdditionalFieldsOfSelectedCategory[1]);
            return response()->json([
                "message" => "Category has been added, and all of the additional fields have been copied to this category",
            ], config("constants.SERVER_STATUS_CODES.CREATED"));
        } catch (Exception $error) {
            Log::error($error);
            return response()->json([
                "error" => $error->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getCategoryDetailsById(Request $request)
    {
        $temp = new \stdClass();
        $temp->category = "";
        $temp->sub_1_category = "";
        $temp->sub_2_category = "";
        $temp->sub_3_category = "";

        $validationMessagesOfIncomingRequest = [
            "required" => "The :attribute field is required",
            "exists" => "Category not found, try entering a different category Id",
        ];
        $this->validate($request, [
            "categoryId" => "bail|required|regex:/^[0-9]+$/|exists:categories,id",
        ], $validationMessagesOfIncomingRequest);
        if (!Category::where(["master_category_id" => $request->query("categoryId")])->exists()) {
            $matchedCategory = Category::select(["text", "level", "master_category_id"])->find($request->query("categoryId"));
            if ($matchedCategory->level === "0") {
                $temp->category = $matchedCategory->text;
            } else if ($matchedCategory->level === "1") {
                $temp->category = Category::select(["text"])->find($matchedCategory->master_category_id)->text;
                $temp->sub_1_category = $matchedCategory->text;
            } else if ($matchedCategory->level === "2") {
                $sub1Category = Category::select(["text", "master_category_id"])->find($matchedCategory->master_category_id);
                $temp->category = Category::select(["text"])->find($sub1Category->master_category_id)->text;
                $temp->sub_1_category = $sub1Category->text;
                $temp->sub_2_category = $matchedCategory->text;
            } else {
                $sub2Category = Category::select(["text", "master_category_id"])->find($matchedCategory->master_category_id);
                $sub1Category = Category::select(["text", "master_category_id"])->find($sub2Category->master_category_id);
                $temp->category = Category::select(["text"])->find($sub1Category->master_category_id)->text;
                $temp->sub_1_category = $sub1Category->text;
                $temp->sub_2_category = $sub2Category->text;
                $temp->sub_3_category = $matchedCategory->text;
            }
        } else {
            return response()->json([
                "message" => "Category not found, try entering a different category Id",
            ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
        }
        return response()->json([
            "category_tree" => $temp,
        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
    }

    public function updateCategory(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        if (isset($request["fieldSections"]) && is_array($request["fieldSections"]) && isset($request["fieldSections"]['saleTypeFields']) || isset($request["fieldSections"]['rentTypeFields'])) {
            $extractedFields = self::extractAdditionalFieldSections($request["fieldSections"]);
            unset($request["fieldSections"]);
            if (empty($extractedFields)) {
                return response()->json([
                    "message" => "One of the provided new additional fields' Input type is missing or invalid, check and try again",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            }
            $request["fieldSections"] = $extractedFields;
            unset($extractedFields);
        } else {
            if (isset($request["fieldSections"])) {
                unset($request["fieldSections"]);
            }
        }
        $validationMessagesOfIncomingRequest = [
            "required" => "The :attribute field is required",
            "regex" => "The :attribute field values may only contain alphabetic, numeric, +&,-'()/ characters, and spaces",
            "categoryId.regex" => "The :attribute is not valid",
            "editSaleTypeFieldSections.*.id.regex" => "The :attribute is not valid",
            "editRentTypeFieldSections.*.id.regex" => "The :attribute is not valid",
            "editSaleTypeFieldSections.*.tags.*.id.regex" => "The :attribute is not valid",
            "editRentTypeFieldSections.*.tags.*.id.regex" => "The :attribute is not valid",
            // validation for new additional fields
            "*.*.ddlFields.*.texts.*.regex" => "The drop-down list field values may only contain alphabetic, numeric, +&,.\"-'()/ characters, and spaces",
            "*.*.radioFields.*.texts.*.regex" => "The drop-down list field values may only contain alphabetic, numeric, +&,.\"-'()/ characters, and spaces",
            "*.*.checkboxFields.*.texts.*.regex" => "The drop-down list field values may only contain alphabetic, numeric, +&,.\"-'()/ characters, and spaces",
            "*.saleTypeFields.inputFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.saleTypeFields.ddlFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.saleTypeFields.radioFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.saleTypeFields.checkboxFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.inputFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.ddlFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.radioFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
            "*.rentTypeFields.checkboxFields.*.labelName.distinct" => "An Input field section has a duplicate section, change the Label name of duplicating Input field section or remove the section",
        ];
        $this->validate($request, [
            "categoryId" => "bail|required|regex:/^[0-9]+$/|exists:categories,id",
            "category" => "bail|required|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub1Category" => "bail|" . Rule::requiredIf($request->sub2Category) . "|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub2Category" => "bail|" . Rule::requiredIf($request->sub3Category) . "|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "sub3Category" => "bail|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "categoryTypes" => "bail|required|array|exists:category_types,value",
            "categoryTypes.*" => "distinct",
            "editSaleTypeFieldSections" => "array",
            "editRentTypeFieldSections" => "array",
            "editSaleTypeFieldSections.*.id" => "bail|required|distinct|regex:/^[0-9]+$/|exists:category_based_fields,id",
            "editSaleTypeFieldSections.*.input_type" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value",
            "editSaleTypeFieldSections.*.field_name" => "bail|required|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "editSaleTypeFieldSections.*.is_required" => "bail|required|" . Rule::in(['yes', 'no']),
            "editSaleTypeFieldSections.*.is_field_deleted" => "bail|required|boolean",
            "editSaleTypeFieldSections.*.placeholder" => "bail|nullable|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "editSaleTypeFieldSections.*.tags" => "bail|required_unless:editSaleTypeFieldSections.*.input_type,text|array",
            "editSaleTypeFieldSections.*.tags.*.id" => "bail|present|regex:/^[0-9]+$/|distinct|exists:category_based_field_lookup_values,id",
            "editSaleTypeFieldSections.*.tags.*.is_tag_deleted" => "bail|required|boolean",
            "editSaleTypeFieldSections.*.tags.*.text" => "bail|required|regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "editRentTypeFieldSections.*.id" => "bail|required|distinct|regex:/^[0-9]+$/|exists:category_based_fields,id",
            "editRentTypeFieldSections.*.input_type" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value",
            "editRentTypeFieldSections.*.field_name" => "bail|required|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "editRentTypeFieldSections.*.is_required" => "bail|required|" . Rule::in(['yes', 'no']),
            "editRentTypeFieldSections.*.is_field_deleted" => "bail|required|boolean",
            "editRentTypeFieldSections.*.placeholder" => "bail|nullable|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "editRentTypeFieldSections.*.tags" => "bail|required_unless:editRentTypeFieldSections.*.input_type,text|array",
            "editRentTypeFieldSections.*.tags.*.id" => "bail|present|regex:/^[0-9]+$/|distinct|exists:category_based_field_lookup_values,id",
            "editRentTypeFieldSections.*.tags.*.is_tag_deleted" => "bail|required|boolean",
            "editRentTypeFieldSections.*.tags.*.text" => "bail|required|regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
//            validation for new additional fields
            "fieldSections" => "array",
            "*.saleTypeFields.inputFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.inputFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.inputFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.inputFields.*.placeholderName" => "bail|nullable|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.inputFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.inputFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.inputFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.inputFields.*.placeholderName" => "bail|nullable|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.ddlFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.ddlFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.ddlFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.ddlFields.*.texts" => "required|array",
            "*.saleTypeFields.ddlFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.ddlFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.ddlFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.ddlFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.ddlFields.*.texts" => "required|array",
            "*.rentTypeFields.ddlFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.radioFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.radioFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.radioFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.radioFields.*.texts" => "required|array",
            "*.saleTypeFields.radioFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.radioFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.radioFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.radioFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.radioFields.*.texts" => "required|array",
            "*.rentTypeFields.radioFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.checkboxFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.saleTypeFields.checkboxFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.saleTypeFields.checkboxFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.saleTypeFields.checkboxFields.*.texts" => "required|array",
            "*.saleTypeFields.checkboxFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.checkboxFields.*.inputType" => "bail|required|regex:/^[a-zA-Z0-9-'&,+()\s]+$/|exists:category_based_field_input_types,value|max:40",
            "*.rentTypeFields.checkboxFields.*.isRequired" => "bail|required|" . Rule::in(['yes', 'no']),
            "*.rentTypeFields.checkboxFields.*.labelName" => "bail|required|distinct|regex:/^[\/a-zA-Z0-9-'&,+()\s]+$/|max:40",
            "*.rentTypeFields.checkboxFields.*.texts" => "required|array",
            "*.rentTypeFields.checkboxFields.*.texts.*" => "regex:/^[\/\"\.a-zA-Z0-9-'&,+()\s]+$/|max:40",
        ], $validationMessagesOfIncomingRequest);

        try {
            //category update section
            $categoryLevel = self::getCategoryLevelById($request->categoryId);
            if ($categoryLevel === "0") {
                $request->category = removeAllowedSpecialCharactersAtStatAndEnd($request->category);
                $request->categorySlug = generateSlug($request->category);
                if (!self::ifProvidedCategoryTreeExistsById($request->categoryId, $categoryLevel, $request->categorySlug)) {
                    self::updateCategoryName($request->categoryId, $request->category, $request->categorySlug);
                } else {
                    return response()->json([
                        "message" => "Category already exists",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            } else if ($categoryLevel === "1") {
                if (!$request->sub1Category) {
                    return response()->json([
                        "message" => "Sub 1 category can't be empty",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
                $request->sub1Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub1Category);
                $request->sub1CategorySlug = generateSlug($request->sub1Category);
                if (!self::ifProvidedCategoryTreeExistsById($request->categoryId, $categoryLevel, $request->sub1CategorySlug)) {
                    self::updateCategoryName($request->categoryId, $request->sub1Category, $request->sub1CategorySlug);
                } else {
                    return response()->json([
                        "message" => "The updating sub 1 category already exists in the same category tree",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            } else if ($categoryLevel === "2") {
                if (!$request->sub2Category) {
                    return response()->json([
                        "message" => "Sub 2 category can't be empty",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
                $request->sub2Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub2Category);
                $request->sub2CategorySlug = generateSlug($request->sub2Category);
                if (!self::ifProvidedCategoryTreeExistsById($request->categoryId, $categoryLevel, $request->sub2CategorySlug)) {
                    self::updateCategoryName($request->categoryId, $request->sub2Category, $request->sub2CategorySlug);
                } else {
                    return response()->json([
                        "message" => "The updating sub 2 category already exists in the same category tree",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            } else {
                if (!$request->sub3Category) {
                    return response()->json([
                        "message" => "Sub 3 category can't be empty",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
                $request->sub3Category = removeAllowedSpecialCharactersAtStatAndEnd($request->sub3Category);
                $request->sub3CategorySlug = generateSlug($request->sub3Category);
                if (!self::ifProvidedCategoryTreeExistsById($request->categoryId, $categoryLevel, $request->sub3CategorySlug)) {
                    self::updateCategoryName($request->categoryId, $request->sub3Category, $request->sub3CategorySlug);
                } else {
                    return response()->json([
                        "message" => "The updating sub 3 category already exists in the same category tree",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            }

            //category type update section
            $diffBetweenCategoryTypes = self::getDiffBetweenCategoryTypes($request->categoryId, $request->categoryTypes);
            if (!empty($diffBetweenCategoryTypes["category_types_to_add"])) {
                self::storeCategoryType($request->categoryId, $diffBetweenCategoryTypes["category_types_to_add"]);
            }
            if (!empty($diffBetweenCategoryTypes["category_types_to_delete"])) {
                foreach ($diffBetweenCategoryTypes["category_types_to_delete"] as $categoryTypeToDelete) {
                    self::destroyCategoryTypeOfCategory($request->categoryId, $categoryTypeToDelete);
                }
            }
            //sale type additional fields update section
            if (!empty($diffBetweenCategoryTypes["category_types_to_delete"]) && in_array("for-sale", $diffBetweenCategoryTypes["category_types_to_delete"])) {
                if (isset($request["editSaleTypeFieldSections"])) {
                    unset($request["editSaleTypeFieldSections"]);
                }
            }
            if (!empty($diffBetweenCategoryTypes["category_types_to_delete"]) && in_array("for-rent", $diffBetweenCategoryTypes["category_types_to_delete"])) {
                if (isset($request["editRentTypeFieldSections"])) {
                    unset($request["editRentTypeFieldSections"]);
                }
            }
            if (isset($request->editSaleTypeFieldSections)) {
                foreach ($request->editSaleTypeFieldSections as $saleTypeField) {
                    if (!$saleTypeField["is_field_deleted"]) {
                        self::updateAdditionalField($request->categoryId, "1", $saleTypeField);
                    } else {
                        self::destroyAdditionField($request->categoryId, "1", $saleTypeField["id"]);
                    }
                }
            }
            if (isset($request->editRentTypeFieldSections)) {
                foreach ($request->editRentTypeFieldSections as $rentTypeField) {
                    if (!$rentTypeField["is_field_deleted"]) {
                        self::updateAdditionalField($request->categoryId, "2", $rentTypeField);
                    } else {
                        self::destroyAdditionField($request->categoryId, "2", $rentTypeField["id"]);
                    }
                }
            }
            // new additional fields save section
            if (isset($request->fieldSections)) {
                foreach ($request->categoryTypes as $categoryType) {
                    self::storeCategoryBasedField(
                        $request->categoryId,
                        CategoryTypes::select("id")->where(["value" => $categoryType])->first()->id,
                        $categoryType,
                        $request->fieldSections
                    );
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
            return response()->json([
                "error" => $exception->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
        return response()->json([
            "message" => "Category has been updated",
        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
    }

    public function getCategoryLevelById($id)
    {
        return Category::select("level")->find($id)->level;
    }

    public function updateCategoryName($id, $name, $slug)
    {
        $category = Category::find($id);
        $category->text = $name;
        $category->value = $slug;
        $category->save();
    }

    public function ifProvidedCategoryTreeExistsById($id, $level, $slug)
    {
        if (Category::where(["value" => $slug, "level" => $level])->exists()) {
            if ($level === "0") {
                $categoryId = Category::select("id")->where(["value" => $slug, "level" => $level])->first()->id;
                if ((string)$categoryId !== $id) {
                    return true;
                }
                return false;
            } else if ($level === "1") {
                //get the updating value's master category id
                $masterCategoryIdOfUpdatingCategory = Category::select("master_category_id")->find($id)->master_category_id;
                //check if updating value is already existing with the updating category value's master category id
                if (Category::where(["master_category_id" => $masterCategoryIdOfUpdatingCategory, "value" => $slug, "level" => $level])->exists()) {
                    //if true then check if that updating the same value
                    if (Category::where(["id" => $id, "master_category_id" => $masterCategoryIdOfUpdatingCategory, "value" => $slug, "level" => $level])->exists()) {
                        //  updating the same value
                        return false;
                    } else {
                        // not updating the same value, so this will be restricted"
                        return true;
                    }
                } else { // if already existing sub 1 categories' master category id doesn't have a matching master category of the updating value's master category id
                    // doesn't have a matching mater cat id
                    return false;
                }
            } else if ($level === "2") {
                //get the updating value's master category id
                $masterCategoryIdOfUpdatingCategory = Category::select("master_category_id")->find($id)->master_category_id;
                //check if updating value is already existing with the updating category value's master category id
                if (Category::where(["master_category_id" => $masterCategoryIdOfUpdatingCategory, "value" => $slug, "level" => $level])->exists()) {
                    //if true then check if that updating the same value
                    if (Category::where(["id" => $id, "master_category_id" => $masterCategoryIdOfUpdatingCategory, "value" => $slug, "level" => $level])->exists()) {
                        //  updating the same value
                        return false;
                    } else {
                        // not updating the same value, so this will be restricted
                        return true;
                    }
                } else { // if already existing sub 2 categories' master category id doesn't have a matching master category of the updating value's master category id
                    // doesn't have a matching mater cat id
                    return false;
                }
            } else {
                //get the updating value's master category id
                $masterCategoryIdOfUpdatingCategory = Category::select("master_category_id")->find($id)->master_category_id;
                //check if updating value is already existing with the updating category value's master category id
                if (Category::where(["master_category_id" => $masterCategoryIdOfUpdatingCategory, "value" => $slug, "level" => $level])->exists()) {
                    //if true then check if that updating the same value
                    if (Category::where(["id" => $id, "master_category_id" => $masterCategoryIdOfUpdatingCategory, "value" => $slug, "level" => $level])->exists()) {
                        //  updating the same value
                        return false;
                    } else {
                        // not updating the same value, so this will be restricted
                        return true;
                    }
                } else { // if already existing sub 2 categories' master category id doesn't have a matching master category of the updating value's master category id
                    // doesn't have a matching mater cat id
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    public function getDiffBetweenCategoryTypes($categoryId, $categoryTypes)
    {
        $existingCategories = [];
        $categoryTypesBeforeUpdate = CategoryType::select("category_type_id")->where(["last_category_id" => $categoryId])->get();
        foreach ($categoryTypesBeforeUpdate as $categoryTypeBeforeUpdate) {
            array_push($existingCategories, CategoryTypes::select("value")->find($categoryTypeBeforeUpdate->category_type_id)->value);
        }
        $categoryTypesToDelete = array_diff($existingCategories, $categoryTypes);
        $categoryTypesToAdd = array_diff($categoryTypes, $existingCategories);
        return [
            "category_types_to_delete" => $categoryTypesToDelete,
            "category_types_to_add" => $categoryTypesToAdd,
        ];
    }

    public function destroyCategoryTypeOfCategory($lastCategoryId, $categoryTypeToDelete)
    {
        $category_type_id = CategoryTypes::select("id")->where(["value" => $categoryTypeToDelete])->first()->id;
        return CategoryType::where(["last_category_id" => $lastCategoryId, "category_type_id" => $category_type_id])->delete();
    }

    public function updateAdditionalField($categoryId, $categoryTypeId, $fieldData)
    {
        if ($fieldData["input_type"] === "text" && self::isAllowedToUpdateThisAdditionalField($categoryId, $categoryTypeId, $fieldData["id"])) {
            $categoryBasedField = CategoryBasedField::find($fieldData["id"]);
            $categoryBasedField->name = removeAllowedSpecialCharactersAtStatAndEnd($fieldData["field_name"]);
            $categoryBasedField->placeholder = empty(removeAllowedSpecialCharactersAtStatAndEnd($fieldData["placeholder"])) ? null : removeAllowedSpecialCharactersAtStatAndEnd($fieldData["placeholder"]);
            $categoryBasedField->is_required = $fieldData["is_required"] === "yes" ? true : false;
            $categoryBasedField->save();
        } else if ($fieldData["input_type"] !== "text" && self::isAllowedToUpdateThisAdditionalField($categoryId, $categoryTypeId, $fieldData["id"])) {
            $categoryBasedField = CategoryBasedField::find($fieldData["id"]);
            $categoryBasedField->name = removeAllowedSpecialCharactersAtStatAndEnd($fieldData["field_name"]);
            $categoryBasedField->is_required = $fieldData["is_required"] === "yes" ? true : false;
            $categoryBasedField->save();
            foreach ($fieldData["tags"] as $tag) {
                if (!empty($tag["id"])) {
                    if (!$tag["is_tag_deleted"]) {
                        self::updateAdditionalFieldTags($fieldData["id"], $tag);
                    } else {
                        self::destroyAdditionTag($tag["id"], $fieldData["id"]);
                    }
                } else {
                    self::addAdditionalFieldTagsWhenUpdate($fieldData["id"], $tag);
                }
            }
        }
    }

    public function isAllowedToUpdateThisAdditionalField($categoryId, $categoryTypeId, $fieldId)
    {
        return CategoryBasedField::where([
            "id" => $fieldId,
            "last_category_id" => $categoryId,
            "category_type_id" => $categoryTypeId,
        ])->exists();
    }

    public function updateAdditionalFieldTags($categoryBasedFieldId, $tagData)
    {
        if (self::isAllowedToUpdateThisAdditionalFieldTag($tagData["id"], $categoryBasedFieldId)) {
            $categoryBasedFieldLookupValue = CategoryBasedFieldLookupValue::find($tagData["id"]);
            $categoryBasedFieldLookupValue->text = removeAllowedSpecialCharactersAtStatAndEndForLookupValueUpdate($tagData["text"]);
            $categoryBasedFieldLookupValue->value = generateSlugForAddLookupValueUpdate(removeAllowedSpecialCharactersAtStatAndEndForLookupValueUpdate($tagData["text"]));
            $categoryBasedFieldLookupValue->save();
        }
    }

    public function isAllowedToUpdateThisAdditionalFieldTag($tagId, $categoryBasedFieldId)
    {
        return CategoryBasedFieldLookupValue::where(["id" => $tagId, "category_based_field_id" => $categoryBasedFieldId])->exists();
    }

    public function destroyAdditionField($categoryId, $categoryTypeId, $fieldId)
    {
        return CategoryBasedField::where([
            "id" => $fieldId,
            "last_category_id" => $categoryId,
            "category_type_id" => $categoryTypeId,
        ])->delete();
    }

    public function addAdditionalFieldTagsWhenUpdate($categoryBasedFieldId, $tagData)
    {
        if (!$tagData["is_tag_deleted"]) {
            $categoryBasedFieldLookupValue = new CategoryBasedFieldLookupValue();
            $categoryBasedFieldLookupValue->category_based_field_id = $categoryBasedFieldId;
            $categoryBasedFieldLookupValue->text = removeAllowedSpecialCharactersAtStatAndEndForLookupValueUpdate($tagData["text"]);
            $categoryBasedFieldLookupValue->value = generateSlugForAddLookupValueUpdate(removeAllowedSpecialCharactersAtStatAndEndForLookupValueUpdate($tagData["text"]));
            $categoryBasedFieldLookupValue->save();
        }
    }

    public function destroyAdditionTag($tagId, $fieldId)
    {
        return CategoryBasedFieldLookupValue::where([
            "id" => $tagId,
            "category_based_field_id" => $fieldId,
        ])->delete();
    }

    public function deleteCategory(Request $request)
    {
        $validationMessagesOfIncomingRequest = [
            "required" => "The :attribute field is required",
            "exists" => "Category not found, try entering a different category Id",
        ];
        $this->validate($request, [
            "id" => "bail|required|regex:/^[0-9]+$/|exists:categories,id",
        ], $validationMessagesOfIncomingRequest);
        Category::find($request->query("id"))->delete();
        return response()->json([
            "message" => "The category, and all of its associated additional fields have been deleted",
        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
    }

    public function extractAdditionalFieldSections($fieldSections)
    {
        $tempFieldSections = $fieldSections;
        $temp = [];
        if (isset($tempFieldSections['saleTypeFields'])) {
            foreach ($tempFieldSections['saleTypeFields'] as $key => $tempFieldSection) {
                if (!isset($tempFieldSection["inputType"]) || !in_array($tempFieldSection["inputType"], ["text", "ddl", "radio", "checkbox"])) {
                    return [];
                }
                if ($tempFieldSection["inputType"] === "text") {
                    $inputKey = isset($temp["fieldSections"]["saleTypeFields"]["inputFields"]) ? sizeof($temp["fieldSections"]["saleTypeFields"]["inputFields"]) : 0;
                    $temp["fieldSections"]["saleTypeFields"]["inputFields"][$inputKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["saleTypeFields"]["inputFields"][$inputKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["saleTypeFields"]["inputFields"][$inputKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["saleTypeFields"]["inputFields"][$inputKey]['placeholderName'] = isset($tempFieldSection["placeholderName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["placeholderName"]) : "";
                } else if ($tempFieldSection["inputType"] === "ddl") {
                    $ddlKey = isset($temp["fieldSections"]["saleTypeFields"]["ddlFields"]) ? sizeof($temp["fieldSections"]["saleTypeFields"]["ddlFields"]) : 0;
                    $temp["fieldSections"]["saleTypeFields"]["ddlFields"][$ddlKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["saleTypeFields"]["ddlFields"][$ddlKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["saleTypeFields"]["ddlFields"][$ddlKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["saleTypeFields"]["ddlFields"][$ddlKey]['texts'] = isset($tempFieldSection["texts"]) ? removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tempFieldSection["texts"]) : [];
                    $temp["fieldSections"]["saleTypeFields"]["ddlFields"][$ddlKey]['values'] = isset($tempFieldSection["texts"]) ? generateSlugForAddLookupValue($temp["fieldSections"]["saleTypeFields"]["ddlFields"][$ddlKey]['texts']) : [];
                } else if ($tempFieldSection["inputType"] === "radio") {
                    $radioKey = isset($temp["fieldSections"]["saleTypeFields"]["radioFields"]) ? sizeof($temp["fieldSections"]["saleTypeFields"]["radioFields"]) : 0;
                    $temp["fieldSections"]["saleTypeFields"]["radioFields"][$radioKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["saleTypeFields"]["radioFields"][$radioKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["saleTypeFields"]["radioFields"][$radioKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["saleTypeFields"]["radioFields"][$radioKey]['texts'] = isset($tempFieldSection["texts"]) ? removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tempFieldSection["texts"]) : [];
                    $temp["fieldSections"]["saleTypeFields"]["radioFields"][$radioKey]['values'] = isset($tempFieldSection["texts"]) ? generateSlugForAddLookupValue($temp["fieldSections"]["saleTypeFields"]["radioFields"][$radioKey]['texts']) : [];
                } else {
                    $checkboxKey = isset($temp["fieldSections"]["saleTypeFields"]["checkboxFields"]) ? sizeof($temp["fieldSections"]["saleTypeFields"]["checkboxFields"]) : 0;
                    $temp["fieldSections"]["saleTypeFields"]["checkboxFields"][$checkboxKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["saleTypeFields"]["checkboxFields"][$checkboxKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["saleTypeFields"]["checkboxFields"][$checkboxKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["saleTypeFields"]["checkboxFields"][$checkboxKey]['texts'] = isset($tempFieldSection["texts"]) ? removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tempFieldSection["texts"]) : [];
                    $temp["fieldSections"]["saleTypeFields"]["checkboxFields"][$checkboxKey]['values'] = isset($tempFieldSection["texts"]) ? generateSlugForAddLookupValue($temp["fieldSections"]["saleTypeFields"]["checkboxFields"][$checkboxKey]['texts']) : [];
                }
            }
        }
        if (isset($tempFieldSections['rentTypeFields'])) {
            foreach ($tempFieldSections['rentTypeFields'] as $key => $tempFieldSection) {
                if (!isset($tempFieldSection["inputType"]) || !in_array($tempFieldSection["inputType"], ["text", "ddl", "radio", "checkbox"])) {
                    return [];
                }
                if ($tempFieldSection["inputType"] === "text") {
                    $inputKey = isset($temp["fieldSections"]["rentTypeFields"]["inputFields"]) ? sizeof($temp["fieldSections"]["rentTypeFields"]["inputFields"]) : 0;
                    $temp["fieldSections"]["rentTypeFields"]["inputFields"][$inputKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["rentTypeFields"]["inputFields"][$inputKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["rentTypeFields"]["inputFields"][$inputKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["rentTypeFields"]["inputFields"][$inputKey]['placeholderName'] = isset($tempFieldSection["placeholderName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["placeholderName"]) : "";
                } else if ($tempFieldSection["inputType"] === "ddl") {
                    $ddlKey = isset($temp["fieldSections"]["rentTypeFields"]["ddlFields"]) ? sizeof($temp["fieldSections"]["rentTypeFields"]["ddlFields"]) : 0;
                    $temp["fieldSections"]["rentTypeFields"]["ddlFields"][$ddlKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["rentTypeFields"]["ddlFields"][$ddlKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["rentTypeFields"]["ddlFields"][$ddlKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["rentTypeFields"]["ddlFields"][$ddlKey]['texts'] = isset($tempFieldSection["texts"]) ? removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tempFieldSection["texts"]) : [];
                    $temp["fieldSections"]["rentTypeFields"]["ddlFields"][$ddlKey]['values'] = isset($tempFieldSection["texts"]) ? generateSlugForAddLookupValue($temp["fieldSections"]["rentTypeFields"]["ddlFields"][$ddlKey]['texts']) : [];
                } else if ($tempFieldSection["inputType"] === "radio") {
                    $radioKey = isset($temp["fieldSections"]["rentTypeFields"]["radioFields"]) ? sizeof($temp["fieldSections"]["rentTypeFields"]["radioFields"]) : 0;
                    $temp["fieldSections"]["rentTypeFields"]["radioFields"][$radioKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["rentTypeFields"]["radioFields"][$radioKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["rentTypeFields"]["radioFields"][$radioKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["rentTypeFields"]["radioFields"][$radioKey]['texts'] = isset($tempFieldSection["texts"]) ? removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tempFieldSection["texts"]) : [];
                    $temp["fieldSections"]["rentTypeFields"]["radioFields"][$radioKey]['values'] = isset($tempFieldSection["texts"]) ? generateSlugForAddLookupValue($temp["fieldSections"]["rentTypeFields"]["radioFields"][$radioKey]['texts']) : [];
                } else {
                    $checkboxKey = isset($temp["fieldSections"]["rentTypeFields"]["checkboxFields"]) ? sizeof($temp["fieldSections"]["rentTypeFields"]["checkboxFields"]) : 0;
                    $temp["fieldSections"]["rentTypeFields"]["checkboxFields"][$checkboxKey]['inputType'] = $tempFieldSection["inputType"];
                    $temp["fieldSections"]["rentTypeFields"]["checkboxFields"][$checkboxKey]['isRequired'] = isset($tempFieldSection["isRequired"]) ? $tempFieldSection["isRequired"] : "";
                    $temp["fieldSections"]["rentTypeFields"]["checkboxFields"][$checkboxKey]['labelName'] = isset($tempFieldSection["labelName"]) ? removeAllowedSpecialCharactersAtStatAndEnd($tempFieldSection["labelName"]) : "";
                    $temp["fieldSections"]["rentTypeFields"]["checkboxFields"][$checkboxKey]['texts'] = isset($tempFieldSection["texts"]) ? removeAllowedSpecialCharactersAtStatAndEndForLookupValue($tempFieldSection["texts"]) : [];
                    $temp["fieldSections"]["rentTypeFields"]["checkboxFields"][$checkboxKey]['values'] = isset($tempFieldSection["texts"]) ? generateSlugForAddLookupValue($temp["fieldSections"]["rentTypeFields"]["checkboxFields"][$checkboxKey]['texts']) : [];
                }
            }
        }
        return $temp["fieldSections"];
    }
}
