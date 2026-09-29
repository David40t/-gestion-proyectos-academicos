<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Punto de entrada de Fortify para el registro: valida y delega en UserService.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly UserService $users) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        // Solo se toman los campos permitidos: el resto del input (p. ej. "role") se ignora.
        return $this->users->registerStudent([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'], // el cast "hashed" del modelo aplica bcrypt
        ]);
    }
}
