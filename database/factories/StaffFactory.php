<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'department' => fake()->randomElement(['資材部', '設計部', '製造部', '営業部']),
            'login_id' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => Staff::ROLE_GENERAL,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * 経理資材担当。作業日報の確認は日報管理者フラグを付けた人だけが行うが、
     * 既定ではその担当も兼ねるものとして扱う(担当を外した状態はテスト側で指定する)。
     */
    public function procurementManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Staff::ROLE_PROCUREMENT_MANAGER,
            'is_daily_report_reviewer' => true,
        ]);
    }

    public function sales(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Staff::ROLE_SALES,
        ]);
    }

    /** 参照ユーザ。購入手配ボードの参照と勤務状況一覧の閲覧だけができる。 */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Staff::ROLE_VIEWER,
        ]);
    }
}
