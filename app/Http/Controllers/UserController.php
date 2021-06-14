<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Gender;
use App\Models\PrimaryEmail;
use App\Models\PrimaryPhone;
use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use phpDocumentor\Reflection\DocBlock\Tags\Uses;
use Symfony\Component\Console\Input\Input;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Throwable;

class UserController extends Controller
{
    /**
     * @var \Tymon\JWTAuth\JWTAuth
     */
    protected $jwt;

    public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    }

    public function signIn(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $request["emailOrUsername"] = Str::lower($request->emailOrUsername);
        $isSignInWithEmail = str_contains($request->emailOrUsername, "@") ? true : false;
        $validationMessagesOfIncomingRequest = [
            "emailOrUsername.email" => "The email must be a valid email address",
            "emailOrUsername.regex" => "The username format is invalid",
        ];
        $this->validate($request, [
            "emailOrUsername" => str_contains($request->emailOrUsername, "@")
                ? "bail|required|email:filter|max:40"
                : "bail|required|regex:/^[a-z0-9-]+$/|max:40",
            "password" => "bail|required|max:40",
            "rememberMe" => "bail|required|" . Rule::in(true, false),
        ], $validationMessagesOfIncomingRequest);

        try {
            if ($isSignInWithEmail) {
                if (!$this->emailExists($request->emailOrUsername)) {
                    return response()->json([
                        "error" => "The email/username or password is invalid or account not found",
                    ], config("constants.SERVER_STATUS_CODES.UNAUTHORIZED"));
                }
                return $this->validateUserAndIssueJWT($this->getUsernameByEmail($request->emailOrUsername), $request->password);
            }
            return $this->validateUserAndIssueJWT($request->emailOrUsername, $request->password);
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getMobileIdByVerifiedMobileNumber($phone)
    {
        try {
            return PrimaryPhone::select("id")->where(["phone" => $phone, "is_verified" => true])->first()->id;
        } catch (Exception $e) {
            return null;
        }
    }

    private function validateUserAndIssueJWT($username, $password)
    {
        try {
            if (!$token = $this->jwt->attempt(["username" => $username, "password" => $password])) {
                return response()->json([
                    "error" => "The email/username or password is invalid or account not found"
                ], config("constants.SERVER_STATUS_CODES.UNAUTHORIZED"));
            }
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json([
                "token_expired"
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json([
                "token_invalid"
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json([
                "token_absent" => $e->getMessage()
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
        return $this->respondWithToken($token);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }

    public function getUsernameByVerifiedMobileId($id)
    {
        return User::select("username")->where(["primary_phone_id" => $id])->first()->username;
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authUser()
    {
        try {
            $userRawData = $this->getAuthUser();
            $user = new \stdClass();
            $user->avatar = FileStorageService::getFullFilePathForProfileAvatar($userRawData->avatar);
            $user->username = $userRawData->username;
            $user->mobile = PrimaryPhone::select("phone")->find($userRawData->primary_phone_id)->phone;
            $user->email = PrimaryEmail::select("email")->find($userRawData->primary_email_id)->email;
            $user->is_admin = $userRawData->is_admin;
            $user->is_blocked = $userRawData->is_blocked;
            $user->name = $userRawData->name;
            $user->gender = is_null($userRawData->gender_id) ? null : Gender::select("value")->find($userRawData->gender_id)->value;
            $user->country = is_null($userRawData->country_id) ? null : Country::select("value")->find($userRawData->country_id)->value;
            $user->district = is_null($userRawData->district_id) ? null : District::select("value")->find($userRawData->district_id)->value;
            $user->city = is_null($userRawData->city_id) ? null : City::select("value")->find($userRawData->city_id)->value;
            $user->bio = $userRawData->bio;
            $user->created_at = $userRawData->created_at->format("Y-m-d H:i:s");
            return response()->json([
                "user" => $user,
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function signOut()
    {
        Auth::guard('api')->logout();
        return response()->json([
            'message' => 'User logged out'
        ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::guard('api')->refresh());
    }

    public function savePhoneNumber(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $validationMessagesOfIncomingRequest = [
            "mobile.regex" => "Invalid mobile number",
        ];
        $this->validate($request, [
            "mobile" => "bail|required|string|regex:/^[0-9]{9,9}$/",
        ], $validationMessagesOfIncomingRequest);
        try {
            if (!self::mobileNumberExists($request->mobile)) {
                self::savePrimaryPhone($request->mobile, self::generateSignUpVerificationCode());
            } else if (!self::isVerifiedMobileNumber($request->mobile)) {
                self::updatePrimaryPhone(self::getMobileIdByMobileNumber($request->mobile), self::generateSignUpVerificationCode());
            } else {
                return response()->json([
                    "error" => "This mobile number belongs to an existing Smoot account, please use a different mobile number",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            }
            return response()->json([
                "message" => "A verification code has been sent to the mobile number",
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function mobileNumberExists($phone)
    {
        return PrimaryPhone::where(["phone" => $phone])->exists();
    }

    public function savePrimaryPhone($phone, $generatedCode)
    {
        $primaryPhone = new PrimaryPhone();
        $primaryPhone->phone = $phone;
        $primaryPhone->verification_code = $generatedCode;
        $primaryPhone->save();
    }

    public function generateSignUpVerificationCode()
    {
        return "1234";
    }

    public function isVerifiedMobileNumber($existingPhone)
    {
        return PrimaryPhone::select("is_verified")->where(["phone" => $existingPhone])->first()->is_verified;
    }

    public function updatePrimaryPhone($phoneId, $reGeneratedCode)
    {
        $primaryPhone = PrimaryPhone::find($phoneId);
        $primaryPhone->verification_code = $reGeneratedCode;
        $primaryPhone->save();
    }

    public function getMobileIdByMobileNumber($phone)
    {
        try {
            return PrimaryPhone::select("id")->where(["phone" => $phone])->first()->id;
        } catch (Exception $e) {
            return null;
        }
    }

    public function signUp(Request $request)
    {
        $isEmailVerified = false;
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $request["email"] = Str::lower($request->email);
        $validationMessagesOfIncomingRequest = [
            "username.regex" => "The username may only contain lowercase alphanumeric characters, and special characters are not allowed besides dash -",
            "mobile.regex" => "Invalid mobile number",
            "verificationCode.digits" => "The verification code must contain a 4 digit code",
            "mobile.exists" => "The mobile number isn't in out records, please register the mobile number first",
            "username.unique" => "This username belongs to an existing Smoot account, please use a different username",
            "email.unique" => "This email belongs to an existing Smoot account, please use a different email",
        ];
        $this->validate($request, [
            "username" => "bail|required|regex:/^[a-z0-9-]+$/|min:3|max:40|unique:users",
            "email" => "bail|required|email:filter|max:40|unique:primary_emails",
            "password" => "bail|required|min:5|max:40",
            "mobile" => "bail|required|string|regex:/^[0-9]{9,9}$/|exists:primary_phones,phone",
            "verificationCode" => "bail|required|string|digits:4",
        ], $validationMessagesOfIncomingRequest);
        try {
            if (is_numeric($request->username)) {
                return response()->json([
                    "username_error" => "The username may not contain only numbers, it must contain at least an alphabetic character with numbers",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            }
            if (!self::isMobileVerificationCodeMatched($request->mobile, $request->verificationCode)) {
                return response()->json([
                    "verification_error" => "Provided verification code doesn't match",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            }
//            $emailId = self::savePrimaryEmail($request->email, generateVerificationCode(4));
            $mobileId = self::getMobileIdByMobileNumber($request->mobile);
            self::markMobileHasVerified($mobileId);
            $newUser = new User();
            $newUser->country_id = "1";
            $newUser->password = Hash::make($request->password);
            $newUser->primary_phone_id = $mobileId;
            $newUser->primary_email_id = self::savePrimaryEmail($request->email, generateVerificationCode(4));
            $newUser->username = $request->username;
            $newUser->save();
//                return self::validateUserAndIssueJWT($request->username, $request->password);
            return response()->json([
                "message" => "Created",
            ], config("constants.SERVER_STATUS_CODES.CREATED"));
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function emailExists($email)
    {
        return PrimaryEmail::where(["email" => $email])->exists();
    }

    public function isVerifiedEmail($existingEmail)
    {
        return PrimaryEmail::select("is_verified")->where(["email" => $existingEmail])->first()->is_verified;
    }

    public function updateVerificationCodeForPrimaryEmail($emailId, $reGeneratedCode)
    {
        $primaryEmail = PrimaryEmail::find($emailId);
        $primaryEmail->verification_code = $reGeneratedCode;
        $primaryEmail->save();
    }

    public function getEmailIdByEmail($email)
    {
        return PrimaryEmail::select("id")->where(["email" => $email])->first()->id;
    }

    public function getProfileEmailIdByProfileId(int $id)
    {
        return User::select("primary_email_id")->find($id)->primary_email_id;
    }

    public function savePrimaryEmail($email, $generatedCode)
    {
        $primaryEmail = new PrimaryEmail();
        $primaryEmail->email = $email;
        $primaryEmail->verification_code = $generatedCode;
        $primaryEmail->save();
        return $primaryEmail->id;
    }

    public function isMobileVerificationCodeMatched($mobile, $providedCode)
    {
        return PrimaryPhone::where(["phone" => $mobile, "verification_code" => $providedCode, "is_verified" => false])->exists();
    }

    public function markMobileHasVerified($mobileId)
    {
        $primaryPhone = PrimaryPhone::find($mobileId);
        $primaryPhone->is_verified = true;
        $primaryPhone->save();
    }

    public function checkUsernameAvailability(Request $request)
    {
        $this->validate($request, [
            "username" => "bail|required|regex:/^[a-z0-9-]+$/|min:3|max:40",
        ]);
        try {
            $isUsernameExists = User::where(["username" => $request->username])->exists();
            return response()->json([
                "is_username_exists" => $isUsernameExists,
                "message" => $isUsernameExists ? "This username belongs to an existing Smoot account, please use a different username" : "Available",
            ], $isUsernameExists
                ? config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY")
                : config("constants.SERVER_STATUS_CODES.SUCCESS")
            );
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function checkEmailAvailability(Request $request)
    {
        $request["email"] = Str::lower($request->email);
        $this->validate($request, [
            "email" => "bail|required|email:filter|max:40",
        ]);
        try {
            $isEmailExists = PrimaryEmail::where(["email" => $request->email])->exists();
            return response()->json([
                "is_email_exists" => $isEmailExists,
                "message" => $isEmailExists ? "This email belongs to an existing Smoot account, please use a different email" : "Available",
            ], $isEmailExists
                ? config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY")
                : config("constants.SERVER_STATUS_CODES.SUCCESS")
            );
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function changeUserProfilePassword(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }

        $validationMessagesOfIncomingRequest = [
            "currentPassword.password" => "Current password isn't valid.",
        ];
        $this->validate($request, [
            "currentPassword" => "bail|required|min:5|max:40|password:api",
            "newPassword" => "bail|required|min:5|max:40",
            "confirmNewPassword" => "bail|required|same:newPassword",
        ], $validationMessagesOfIncomingRequest);
        try {
            $user = User::find($this->getAuthUser()->id);
            $user->password = Hash::make($request->newPassword);
            $user->save();
            return response()->json([
                "message" => "Password has been updated",
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getAuthUser()
    {
        return Auth::guard('api')->user();
    }

    public function updateProfile(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $request["email"] = Str::lower($request->email);
        $validationMessagesOfIncomingRequest = [
            "name.regex" => "The name may only contain alphanumeric characters, and spaces",
            "bio.regex" => "The bio may only contain alphanumeric characters, ().\"/-&,' characters, and spaces",
        ];
        $this->validate($request, [
            "name" => "bail|regex:/^[a-zA-Z0-9\s]+$/|min:3|max:40",
            "bio" => "bail|regex:/^[a-zA-Z0-9()\.\"\/&,\s'-]+$/|max:200",
            "district" => "exists:districts,value",
            "city" => "exists:cities,value",
            "email" => "bail|required|email:filter|max:40",
            "gender" => "exists:gender,value",
        ], $validationMessagesOfIncomingRequest);
        try {
//            if ($this->isUsernameAvailableForExistingProfile($this->getAuthUser()->id, $request->username)) {
//                return response()->json([
//                    "username_error" => "This username belongs to an existing Smoot account, please use a different username",
//                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
//            }
            if ($this->isEmailAvailableForExistingProfile($this->getAuthUser()->id, $request->email)) {
                return response()->json([
                    "email_error" => "This email belongs to an existing Smoot account, please use a different email",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            }
            if (empty($request->district) && !empty($request->city)) {
                return response()->json([
                    "city_error" => "You must provide a district to save city",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            } else if (!empty($request->district) && !empty($request->city)) {
                if (!$this->isValidCity($request->district, $request->city)) {
                    return response()->json([
                        "error" => "The district or city doesn't belong to provided district or city",
                    ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                }
            }
            if (!$this->emailExists($request->email)) {
                $emailId = $this->getProfileEmailIdByProfileId($this->getAuthUser()->id);
                $this->updatePrimaryEmail($emailId, $request->email, generateVerificationCode(4));
            }
            $user = User::find($this->getAuthUser()->id);
            $user->district_id = !empty($request->district) ? $this->getDistrictIdByDistrictValue($request->district) : null;
            $user->city_id = !empty($request->city) ? $this->getCityIdByCityValue($request->city) : null;
            $user->gender_id = !empty($request->gender) ? $this->getGenderIdByGenderValue($request->gender) : null;
//            $user->username = $request->username;
            $user->name = $request->name;
            $user->bio = $request->bio;
            $user->save();
            return response()->json([
                "message" => "Profile updated",
            ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function isUsernameAvailableForExistingProfileDirect(Request $request)
    {
        $validationMessagesOfIncomingRequest = [
            "username.regex" => "The username may only contain lowercase alphanumeric characters, and special characters are not allowed besides dash -",
        ];
        $this->validate($request, [
            "username" => "bail|required|regex:/^[a-z0-9-]+$/|min:3|max:40",
        ], $validationMessagesOfIncomingRequest);
        try {
            $isUsernameExists = $this->isUsernameAvailableForExistingProfile($this->getAuthUser()->id, $request->username);
            return response()->json([
                "is_username_exists" => $isUsernameExists,
                "message" => $isUsernameExists ? "This username belongs to an existing Smoot account, please use a different username" : "Available",
            ], $isUsernameExists
                ? config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY")
                : config("constants.SERVER_STATUS_CODES.SUCCESS")
            );
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function isUsernameAvailableForExistingProfile(int $id, string $username)
    {
        $user = User::select("id")->where(["username" => $username])->first();
        if (is_null($user)) {
            return false;
        } else if ($user->id === $id) {
            return false;
        }
        return true;
    }

    public function isEmailAvailableForExistingProfileDirect(Request $request)
    {
        $request["email"] = Str::lower($request->email);
        $this->validate($request, [
            "email" => "bail|required|email:filter|max:40",
        ]);
        try {
            $isEmailExists = $this->isEmailAvailableForExistingProfile($this->getAuthUser()->id, $request->email);
            return response()->json([
                "is_email_exists" => $isEmailExists,
                "message" => $isEmailExists ? "This email belongs to an existing Smoot account, please use a different email" : "Available",
            ], $isEmailExists
                ? config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY")
                : config("constants.SERVER_STATUS_CODES.SUCCESS")
            );
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function isEmailAvailableForExistingProfile(int $userId, string $email)
    {
        $primaryEmail = PrimaryEmail::select("id")->where(["email" => $email])->first();
        if (is_null($primaryEmail)) {
            return false;
        } else if (User::where(["id" => $userId, "primary_email_id" => $primaryEmail->id])->exists()) {
            return false;
        }
        return true;
    }

    public function updatePrimaryEmail(int $emailId, string $newEmail, $generatedCode)
    {
        $email = PrimaryEmail::find($emailId);
        $email->email = $newEmail;
        $email->verification_code = $generatedCode;
        $email->is_verified = false;
        $email->save();
    }

    public function isValidCity(string $district, string $city)
    {
        $districtId = $this->getDistrictIdByDistrictValue($district);
        return City::where(["district_id" => $districtId, "value" => $city])->exists();
    }

    public function getDistrictIdByDistrictValue(string $district)
    {
        return District::select("id")->where(["value" => $district])->first()->id;
    }

    public function getCityIdByCityValue(string $city)
    {
        return City::select("id")->where(["value" => $city])->first()->id;
    }

    public function getGenderIdByGenderValue(string $gender)
    {
        return Gender::select("id")->where(["value" => $gender])->first()->id;
    }

    public function storeProfileAvatar(Request $request)
    {
        // file size in Kilobits = 2MB
        $this->validate($request, [
            "avatar" => "bail|required|image|max:2048|mimes:jpeg,jpg,png",
        ]);
        try {
            $avatarExtension = $request->avatar->getClientOriginalExtension();
            $generateAvatarName = $this->getAuthUser()->id . date("mdYHis") . $this->getAuthUser()->id;
            $finalAvatarName = str_shuffle($generateAvatarName);
            $fileNameWithExtension = $finalAvatarName . "." . $avatarExtension;
            $fileStorageService = new FileStorageService();
            $results = $fileStorageService->storefile($request->file("avatar"), $fileNameWithExtension, config("constants.FILE_PATHS.USER_PROFILE_PHOTO"));
            if (is_null($results->success)) {
                Log::error(json_encode($results->err));
                return response()->json([
                    "message" => $results->err->message,
                ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
            } else {
                $serverFileId = $results->success->fileId;
                $avatar = $finalAvatarName . "-" . $serverFileId . "." . $avatarExtension;
                $currentProfileAvatar = $this->getCurrentProfileAvatar();
                if (!is_null($currentProfileAvatar)) {
                    $deleteResults = $fileStorageService->distroyFile($currentProfileAvatar);
                    if (!is_null($deleteResults->err)) {
                        $deleteResults->err->requestFor = "delete_file";
                        $deleteResults->err->file = $currentProfileAvatar;
                        Log::error(json_encode($deleteResults->err));
                    } else {
                        $clearCacheResults = $fileStorageService->clearCache($currentProfileAvatar, config("constants.FILE_PATHS.USER_PROFILE_PHOTO"));
                        if (!is_null($clearCacheResults->err)) {
                            $clearCacheResults->err->requestFor = "delete_cache";
                            $clearCacheResults->err->file = $currentProfileAvatar;
                            Log::error(json_encode($clearCacheResults));
                        }
                    }
                }
                $this->updateProfileAvatar($avatar);
                return response()->json([
                    "message" => "Avatar updated",
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            }
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                "message" => $e->getMessage(),
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function updateProfileAvatar($file)
    {
        $user = User::find($this->getAuthUser()->id);
        $user->avatar = $file;
        $user->save();
    }

    public function getCurrentProfileAvatar()
    {
        return User::select("avatar")->find($this->getAuthUser()->id)->avatar;
    }

    public function getUsernameByEmail($email)
    {
        return User::select("username")->where(["primary_email_id" => $this->getEmailIdByEmail($email)])->first();
    }

    public function updateMobileNumber(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $this->validate($request, [
            "mobile" => "bail|required|string|regex:/^[0-9]{9,9}$/",
        ]);
        try {
            if ($this->mobileNumberExists($request->mobile)) {
                if ($this->isVerifiedMobileNumber($request->mobile)) {
                    if ($this->isUpdatingMobileNumberHasNoDiff($this->getAuthUser()->id, $request->mobile)) {
                        return response()->json([
                            "error" => "This mobile number has already owned by this Smoot account",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    } else {
                        return response()->json([
                            "error" => "This mobile number belongs to an existing Smoot account",
                        ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
                    }
                } else {
                    self::updatePrimaryPhone(self::getMobileIdByMobileNumber($request->mobile), self::generateSignUpVerificationCode());
                    return response()->json([
                        "message" => "A verification code has been sent to +94" . $request->mobile,
                    ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
                }
            } else {
                self::savePrimaryPhone($request->mobile, self::generateSignUpVerificationCode());
                return response()->json([
                    "message" => "A verification code has been sent to +94" . $request->mobile,
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            }
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function isUpdatingMobileNumberHasNoDiff($userId, $mobile)
    {
        return User::where(["id" => $userId, "primary_phone_id" => $this->getMobileIdByMobileNumber($mobile)])->exists();
    }

    public function updateMobileNumberCodeVerification(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $request[$key] = json_decode($value, true);
        }
        $validationMessagesOfIncomingRequest = [
            "verificationCode.digits" => "The verification code must contain a 4 digit code",
        ];
        $this->validate($request, [
            "mobile" => "bail|required|string|regex:/^[0-9]{9,9}$/",
            "verificationCode" => "bail|required|string|digits:4",
        ], $validationMessagesOfIncomingRequest);

        try {
            $newMobileId = $this->getMobileIdIfVerificationCodeMatched($request->mobile, $request->verificationCode);
            if (!$newMobileId) {
                return response()->json([
                    "error" => "Provided verification code doesn't match",
                ], config("constants.SERVER_STATUS_CODES.UNPROCESSABLE_ENTITY"));
            } else {
                $this->markMobileHasVerified($newMobileId);
                $mobileIdForDelete = $this->getMobileIdOfAuthUser();
                $this->updateUserProfilePrimaryPhone($this->getAuthUser()->id, $newMobileId);
                $this->deletePrimaryPhone($mobileIdForDelete);
                return response()->json([
                    "message" => "Mobile number has updated",
                ], config("constants.SERVER_STATUS_CODES.SUCCESS"));
            }
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }

    public function getMobileIdIfVerificationCodeMatched($mobile, $providedCode)
    {
        $mobile = PrimaryPhone::select("id")->where(["phone" => $mobile, "verification_code" => $providedCode, "is_verified" => false])->first();
        if (!$mobile) {
            return $mobile;
        }
        return $mobile->id;
    }

    public function getMobileIdOfAuthUser()
    {
        return User::select("primary_phone_id")->find($this->getAuthUser()->id)->primary_phone_id;
    }

    public function updateUserProfilePrimaryPhone($userId, $newMobileId)
    {
        $user = User::find($userId);
        $user->primary_phone_id = $newMobileId;
        $user->save();
    }

    public function deletePrimaryPhone($mobileIdToDelete)
    {
        PrimaryPhone::destroy($mobileIdToDelete);
    }

    public function sendMail()
    {
        try {
//            Mail::raw("This is a system test <b>generated</b> test mail", function ($msg) {
//                $msg->to(['kazmi.jiffry@gmail.com'], $msg->subject("New user new"), $msg->su);
//            });
            $body = "";
            Mail::send([], [], function ($message) {
                $message->to("kazmi.jiffry@gmail.com")
                    ->subject("Welcome")
                    ->setBody('<h1>Hi, welcome user!</h1>', 'text/html');
            });
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                "message" => "Something went wrong, try again",
            ], config("constants.SERVER_STATUS_CODES.INTERNAL_SERVER_ERROR"));
        }
    }
}
