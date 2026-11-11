<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            // ticket_number dibuat otomatis oleh model (hook creating)
            'subject' => ucfirst(fake()->sentence(5)),
            'description' => fake()->paragraphs(3, true),
            'category_id' => TicketCategory::factory(),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
            'reporter_id' => User::factory(),
            'assigned_to' => null,
            'attachment' => null,
        ];
    }

    public function assigned(?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $userId ?? User::factory(),
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function reportedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'reporter_id' => $user->id,
        ]);
    }
}
