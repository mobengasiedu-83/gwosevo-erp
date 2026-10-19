<?php
namespace App\Notifications;
use App\Models\CommercialContract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class ContractRenewalNotification extends Notification
{
    use Queueable;
    public function __construct(private CommercialContract $contract) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return ['event'=>'contract.renewal.due','title'=>'Contract renewal approaching','contract_id'=>$this->contract->id,'reference'=>$this->contract->reference,'client_name'=>$this->contract->client_name,'renewal_date'=>$this->contract->renewal_date?->toDateString(),'url'=>route('commercial.contracts.show',$this->contract)];
    }
}
