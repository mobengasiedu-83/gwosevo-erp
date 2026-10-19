<?php
namespace App\Console\Commands;
use App\Domain\Administration\Modules;
use App\Domain\Commercial\CommercialAccess;
use App\Models\CommercialContract;
use App\Models\User;
use App\Notifications\ContractRenewalNotification;
use Illuminate\Console\Command;
class SendContractRenewalAlerts extends Command
{
    protected $signature='contracts:send-renewal-alerts';
    protected $description='Notify commercial owners about contracts renewing within 30 days';
    public function handle(): int
    {
        if (!app(Modules::class)->enabled('contracts') || !app(Modules::class)->enabled('sales')) return self::SUCCESS;
        CommercialContract::with(['opportunity.owner'])->where('status','active')->whereNotNull('renewal_date')->whereBetween('renewal_date',[today(),today()->addDays(30)])->whereNull('renewal_alert_sent_at')->chunkById(100,function($contracts){
            foreach($contracts as $contract){
                $access=app(CommercialAccess::class);
                $recipients=collect([$contract->opportunity?->owner,User::find($contract->created_by)])->filter(fn($u)=>$u && $u->isActive() && $u->can('contracts.view') && $access->allowsContract($u,$contract))->unique('id');
                if($recipients->isEmpty()) $recipients=User::role('Administrator')->where('status','active')->get()->filter(fn($u)=>$access->allowsContract($u,$contract));
                foreach($recipients as $recipient) $recipient->notify(new ContractRenewalNotification($contract));
                $contract->update(['renewal_alert_sent_at'=>now()]);
            }
        });
        return self::SUCCESS;
    }
}
