<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * User erhält alle vorhandenen Permissions.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->givePermissionTo(Permission::all());
        });
    }

    /**
     * User erhält die angegebenen Permissions.
     */
    public function withPermission(string ...$permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions) {
            foreach ($permissions as $perm) {
                Permission::findOrCreate($perm);
            }
            $user->givePermissionTo($permissions);
        });
    }

    /**
     * User erhält ein zufälliges Kürzel.
     */
    public function withKuerzel(): static
    {
        return $this->state(fn () => ['kuerzel' => strtoupper($this->faker->lexify('??'))]);
    }
}
