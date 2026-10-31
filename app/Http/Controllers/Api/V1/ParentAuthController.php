<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentAuth\LoginParent;
use App\Actions\ParentAuth\LogoutParent;
use App\Actions\ParentAuth\SendParentOtp;
use App\Actions\ParentAuth\SetParentPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ParentLoginRequest;
use App\Http\Requests\Api\V1\RequestParentCodeRequest;
use App\Http\Requests\Api\V1\SetParentPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Parent app sign-in: a texted code to activate or set a new password, then phone + password.
 */
class ParentAuthController extends Controller
{
    public function requestCode(RequestParentCodeRequest $request, SendParentOtp $sendParentOtp): JsonResponse
    {
        $sendParentOtp->handle($request->string('phone')->toString());

        return response()->json(['message' => 'If this number is registered with the school, a code is on its way by SMS.'], 202);
    }

    public function setPassword(SetParentPasswordRequest $request, SetParentPassword $setParentPassword): JsonResponse
    {
        $token = $setParentPassword->handle(
            $request->string('phone')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
            $request->input('device_name'),
        );

        return response()->json(['token' => $token]);
    }

    public function login(ParentLoginRequest $request, LoginParent $loginParent): JsonResponse
    {
        $token = $loginParent->handle(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
            $request->input('device_name'),
        );

        return response()->json(['token' => $token]);
    }

    public function logout(Request $request, LogoutParent $logoutParent): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        $logoutParent->handle($user);

        return response()->noContent();
    }
}
