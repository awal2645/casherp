<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ReCaptcha implements Rule
{
    public function passes($attribute, $value)
    {
        return true;
    }

    public function message()
    {
        return 'The recaptcha verification failed.';
    }
}
