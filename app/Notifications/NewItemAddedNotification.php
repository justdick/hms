<?php

namespace App\Notifications;

use App\Models\Drug;
use App\Models\InsurancePlan;
use App\Models\LabService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewItemAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Drug|LabService $item,
        public string $category,
        public InsurancePlan $plan,
        public float $defaultCoverage
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $itemName = $this->getItemName();
        $itemCode = $this->getItemCode();
        $itemPrice = $this->getItemPrice();

        return [
            'type' => 'new_item_coverage',
            'message' => "New {$this->category} '{$itemName}' will be covered at {$this->defaultCoverage}% by default in {$this->plan->plan_name}",
            'item_id' => $this->item->id,
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'item_price' => $itemPrice,
            'category' => $this->category,
            'plan_id' => $this->plan->id,
            'plan_name' => $this->plan->plan_name,
            'default_coverage' => $this->defaultCoverage,
            'actions' => [
                'add_exception' => route('admin.insurance.coverage-rules.create', [
                    'plan_id' => $this->plan->id,
                    'category' => $this->category,
                    'item_code' => $itemCode,
                ]),
                'keep_default' => null, // Will be handled by marking notification as read
            ],
        ];
    }

    /**
     * Get the item name based on the item type.
     */
    protected function getItemName(): string
    {
        return match (true) {
            $this->item instanceof Drug => $this->item->name,
            $this->item instanceof LabService => $this->item->name,
            default => 'Unknown Item',
        };
    }

    /**
     * Get the item code based on the item type.
     */
    protected function getItemCode(): string
    {
        return match (true) {
            $this->item instanceof Drug => $this->item->drug_code,
            $this->item instanceof LabService => $this->item->code,
            default => '',
        };
    }

    /**
     * Get the item price based on the item type.
     */
    protected function getItemPrice(): float
    {
        return match (true) {
            $this->item instanceof Drug => (float) $this->item->unit_price,
            $this->item instanceof LabService => (float) $this->item->price,
            default => 0.0,
        };
    }
}
