<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsUsersByRole;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    use RedirectsUsersByRole;

    public function toResponse($request)
    {
        return redirect()->intended($this->homeFor($request->user()));
    }
}
