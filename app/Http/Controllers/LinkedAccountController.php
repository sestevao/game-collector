<?php

namespace App\Http\Controllers;

use App\Models\LinkedAccount;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class LinkedAccountController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(Request $request, $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('profile.edit')->with('error', 'Failed to link account.');
        }

        $user = $request->user();

        // Check if this account is already linked to ANOTHER user
        $existingLink = LinkedAccount::where('provider_name', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existingLink && $existingLink->user_id !== $user->id) {
            return redirect()->route('profile.edit')->with('error', 'This account is already linked to another user.');
        }

        // Link the account
        LinkedAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider_name' => $provider,
            ],
            [
                'provider_id' => $socialUser->getId(),
                'provider_nickname' => $socialUser->getNickname(),
                'avatar_url' => $socialUser->getAvatar(),
            ]
        );

        return redirect()->route('profile.edit')->with('success', ucfirst($provider) . ' account linked successfully.');
    }

    public function destroy(Request $request, $provider)
    {
        $request->user()->linkedAccounts()->where('provider_name', $provider)->delete();

        return redirect()->back()->with('success', 'Account unlinked.');
    }
}
