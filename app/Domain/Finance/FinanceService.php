<?php

namespace App\Domain\Finance;

use App\Domain\Administration\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function createInvoice(int $organizationId, int $userId, array $data, array $lines, string $status = 'draft'): int
    {
        return DB::transaction(function () use ($organizationId, $userId, $data, $lines, $status) {
            $reference = app(NumberSequence::class)->next('invoice');
            $invoiceId = DB::table('finance_invoices')->insertGetId([
                'organization_id'=>$organizationId,'reference'=>$reference,'customer_name'=>$data['customer_name'],'customer_email'=>$data['customer_email']??null,
                'currency'=>$data['currency']??'GHS','invoice_date'=>$data['invoice_date']??today()->toDateString(),'due_date'=>$data['due_date'],
                'status'=>$status,'notes'=>$data['notes']??null,'service_name'=>$data['service_name']??null,'department_name'=>$data['department_name']??null,
                'project_id'=>$data['project_id']??null,'created_by'=>$userId,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $subtotal=0.0;$taxTotal=0.0;$total=0.0;
            foreach($lines as $line){
                $base=round((float)$line['quantity']*(float)$line['unit_price'],2);$taxCode=null;
                if(!empty($line['tax_code_id'])) $taxCode=DB::table('finance_tax_codes')->where('organization_id',$organizationId)->where('is_active',true)->where('id',$line['tax_code_id'])->first();
                if(!empty($line['tax_code_id'])&&!$taxCode) throw ValidationException::withMessages(['lines'=>'Choose an active tax code in this organization.']);
                $rate=$taxCode?(float)$taxCode->rate_percent/100:0.0;
                if($taxCode?->is_inclusive){$gross=$base;$net=$rate>0?round($base/(1+$rate),2):$base;$tax=round($gross-$net,2);}else{$net=$base;$tax=round($net*$rate,2);$gross=round($net+$tax,2);}
                $subtotal+=$net;$taxTotal+=$tax;$total+=$gross;
                DB::table('finance_invoice_lines')->insert(['invoice_id'=>$invoiceId,'description'=>$line['description'],'quantity'=>$line['quantity'],'unit_price'=>$line['unit_price'],'tax_code_id'=>$taxCode?->id,'net_amount'=>$net,'tax_amount'=>$tax,'gross_amount'=>$gross,'created_at'=>now(),'updated_at'=>now()]);
            }
            DB::table('finance_invoices')->where('id',$invoiceId)->update(['subtotal'=>round($subtotal,2),'tax_total'=>round($taxTotal,2),'total'=>round($total,2),'updated_at'=>now()]);
            return $invoiceId;
        },5);
    }

    public function generateRecurringInvoices(): int
    {
        $created=0;
        DB::table('finance_recurring_schedules')->where('is_active',true)->whereDate('next_invoice_on','<=',today())->orderBy('id')->chunkById(50,function($schedules)use(&$created){
            foreach($schedules as $schedule){
                DB::transaction(function()use($schedule,&$created){
                    $locked=DB::table('finance_recurring_schedules')->where('id',$schedule->id)->lockForUpdate()->first();
                    if(!$locked||!$locked->is_active||$locked->next_invoice_on>today()->toDateString()||($locked->ends_on&&$locked->ends_on<today()->toDateString())) return;
                    $due=\Illuminate\Support\Carbon::parse($locked->next_invoice_on);$createdBy=(int)$locked->created_by;
                    if($createdBy<1){DB::table('finance_recurring_schedules')->where('id',$locked->id)->update(['is_active'=>false,'updated_at'=>now()]);return;}
                    $invoiceId=$this->createInvoice((int)$locked->organization_id,$createdBy,['customer_name'=>$locked->customer_name,'customer_email'=>$locked->customer_email,'currency'=>$locked->currency,'invoice_date'=>$due->toDateString(),'due_date'=>$due->copy()->addDays(30)->toDateString(),'service_name'=>$locked->description], [['description'=>$locked->description,'quantity'=>$locked->quantity,'unit_price'=>$locked->unit_price,'tax_code_id'=>$locked->tax_code_id]],'pending_approval');
                    DB::table('finance_invoice_approvals')->insert(['invoice_id'=>$invoiceId,'requested_by'=>$createdBy,'status'=>'pending','request_note'=>'Recurring schedule '.$locked->id,'created_at'=>now(),'updated_at'=>now()]);
                    $next=match($locked->frequency){'weekly'=>$due->copy()->addWeek(),'monthly'=>$due->copy()->addMonthNoOverflow(),'quarterly'=>$due->copy()->addMonthsNoOverflow(3),'yearly'=>$due->copy()->addYearNoOverflow(),default=>throw new \LogicException('Invalid recurring schedule frequency.')};
                    DB::table('finance_recurring_schedules')->where('id',$locked->id)->update(['next_invoice_on'=>$next->toDateString(),'is_active'=>!$locked->ends_on||$next->toDateString()<=$locked->ends_on,'updated_at'=>now()]);$created++;
                },5);
            }
        });
        return $created;
    }
}
