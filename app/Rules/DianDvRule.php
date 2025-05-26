<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Traits\DocumentTrait;

class DianDvRule implements ValidationRule
{
    use DocumentTrait;

    protected string $nit;

    public function __construct(string $nit) 
    {
        $this->nit = $nit;
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if($this->validarDigVerifDIAN($this->nit)!= $value)$fail('El dígito de verificación (DV) no es válido para el NIT proporcionado.');
    }
}
