<?php

namespace Database\Factories;

use App\Models\User;
use Heritage\Database\Eloquent\Factories\Factory;
use Heritage\Support\Facades\Hash;
use Heritage\Support\Str;

use function fake;
use function now;

/**
 * @template TModel of \App\Models\User
 *
 * @extends \Heritage\Database\Eloquent\Factories\Factory<TModel>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(static fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
