<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsUsersByRole;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsUsersByRole;

    public function toResponse($request)
    {
        return redirect()->intended($this->homeFor($request->user()));
    }
}
