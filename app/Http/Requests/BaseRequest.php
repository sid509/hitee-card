<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base Request class to handle uniform validation responses across Web and API
 */
class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        // If the request expects a JSON response (API), return our uniform API response
        if ($this->expectsJson()) {
            $errors = $validator->errors();
            $firstError = $errors->first(); // Get the first validation error message

            throw new HttpResponseException(
                apiResponse(false, $firstError, $errors->toArray(), 422)
            );
        }

        // Otherwise, perform the default redirect behavior for Web
        parent::failedValidation($validator);
    }
}
