<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsUsersByRole;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    use RedirectsUsersByRole;

    public function toResponse($request)
    {
        return redirect()->intended($this->homeFor($request->user()).'?verified=1');
    }
}
