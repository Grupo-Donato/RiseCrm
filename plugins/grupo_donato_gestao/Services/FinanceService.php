<?php
declare(strict_types=1);
namespace grupo_donato_gestao\Services;
use grupo_donato_gestao\Config\Constants;

/** Basic finance ledger: receivables, allocations, reversals, expenses and cash. */
class FinanceService extends CatalogDataService
{
    private $receivables;private $items;private $payments;private $allocations;private $expenses;private $cash;private ?object $login_user;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->receivables=model('grupo_donato_gestao\\Models\\Gd_receivables_model');$this->items=model('grupo_donato_gestao\\Models\\Gd_receivable_items_model');$this->payments=model('grupo_donato_gestao\\Models\\Gd_payments_model');$this->allocations=model('grupo_donato_gestao\\Models\\Gd_payment_allocations_model');$this->expenses=model('grupo_donato_gestao\\Models\\Gd_expenses_model');$this->cash=model('grupo_donato_gestao\\Models\\Gd_cash_movements_model');$this->login_user=$login_user;}

    public function accounts():array{return $this->db->table($this->db->prefixTable('gd_financial_accounts'))->select('id,name,account_type')->where('unit_id',$this->unit_id)->where('status','active')->where('deleted',0)->orderBy('name')->get()->getResultArray();}
    public function saveAccount(array $in,int $id=0):array{$model=model('grupo_donato_gestao\\Models\\Gd_financial_accounts_model');$old=$id?$model->get_scoped($id,$this->unit_id):null;if($id&&!$old)throw new \DomainException('gd_record_not_found');$name=DataNormalizationService::text($in['name']??'');$code=strtoupper(preg_replace('/[^A-Z0-9_-]/','',DataNormalizationService::text($in['code']??'')));$type=(string)($in['account_type']??'');$status=(string)($in['status']??'active');if($name===''||$code===''||!in_array($type,Constants::FINANCIAL_ACCOUNT_TYPES,true)||!in_array($status,Constants::FINANCIAL_ACCOUNT_STATUSES,true))throw new \DomainException('gd_finance_invalid_account');$data=$this->stamp(['unit_id'=>$this->unit_id,'code'=>$code,'name'=>$name,'account_type'=>$type,'status'=>$status,'notes'=>DataNormalizationService::text($in['notes']??'')?:null],!$old);$saved=(int)$model->ci_save($data,$id);$this->audit_change($id?'update':'create','financial_account',$saved,$old?(array)$old:null,(array)$model->get_scoped($saved,$this->unit_id));return ['id'=>$saved];}
    public function createReceivable(array $in):array
    {
        $source=(string)($in['source_type']??'manual');if(!in_array($source,Constants::RECEIVABLE_SOURCE_TYPES,true))throw new \DomainException('gd_finance_invalid_source');$sourceId=(int)($in['source_id']??0);$reference=$this->reference($in['reference_month']??'');$account=(int)($in['customer_account_id']??0);$description=DataNormalizationService::text($in['description']??'');$issue=$this->valid_date($in['issue_date']??gmdate('Y-m-d'));$due=$this->valid_date($in['due_date']??'');$amount=DataNormalizationService::decimal($in['original_amount']??'',2);if(DataNormalizationService::decimalCompare($amount,'0.00')<=0||!$due||$due<$issue||$description==='')throw new \DomainException('gd_finance_invalid_receivable');
        [$account,$sourceId]=$this->resolveSource($source,$sourceId,$account);$this->scoped('gd_customer_accounts',$account);$area=$this->assert_area((int)($in['business_area_id']??0));$center=$this->assert_cost_center((int)($in['cost_center_id']??0),$area);
        if($source!=='manual'&&$sourceId>0){$dup=$this->db->table($this->db->prefixTable('gd_receivables'))->where('unit_id',$this->unit_id)->where('source_type',$source)->where('source_id',$sourceId)->where('reference_month',$reference)->where('deleted',0)->get(1)->getRow();if($dup)return ['created'=>false,'duplicate'=>true,'id'=>(int)$dup->id];}
        $product=(int)($in['product_id']??0);if($product)$this->scoped('gd_products',$product);$qty=DataNormalizationService::decimal($in['quantity']??'1',3);$unitAmount=DataNormalizationService::decimal($in['unit_amount']??$amount,2);$total=$this->multiply($qty,$unitAmount);if(DataNormalizationService::decimalCompare($total,$amount)!==0)throw new \DomainException('gd_finance_item_total_mismatch');
        $seq=new SequenceService();$seq->ensure($this->unit_id,'receivable','REC-'.gmdate('Y').'-',6,true);$number=$seq->next($this->unit_id,'receivable');$this->db->transBegin();try{$data=$this->stamp(['unit_id'=>$this->unit_id,'receivable_number'=>$number,'customer_account_id'=>$account,'source_type'=>$source,'source_id'=>$sourceId?:null,'reference_month'=>$reference,'description'=>$description,'issue_date'=>$issue,'due_date'=>$due,'original_amount'=>$amount,'paid_amount'=>'0.00','balance_amount'=>$amount,'status'=>$due<gmdate('Y-m-d')?'overdue':'open','business_area_id'=>$area,'cost_center_id'=>$center,'notes'=>DataNormalizationService::text($in['notes']??'')?:null,'lock_version'=>1],true);$id=(int)$this->receivables->ci_save($data,0);$item=$this->stamp(['unit_id'=>$this->unit_id,'receivable_id'=>$id,'description'=>DataNormalizationService::text($in['item_description']??$description),'product_id'=>$product?:null,'quantity'=>$qty,'unit_amount'=>$unitAmount,'total_amount'=>$total],true);$this->items->ci_save($item,0);$this->audit_change('create','receivable',$id,null,$data,['source_type'=>$source,'source_id'=>$sourceId]);if($this->db->transCommit()===false)throw new \RuntimeException('save_failed');return ['created'=>true,'id'=>$id,'receivable_number'=>$number];}catch(\Throwable $e){$this->db->transRollback();if(str_contains($e->getMessage(),'uniq_receivable_source'))return ['created'=>false,'duplicate'=>true];throw $e;}
    }
    public function cancelReceivable(int $id,string $reason):void{$r=$this->receivables->get_scoped($id,$this->unit_id);if(!$r)throw new \DomainException('gd_record_not_found');if((string)$r->status==='paid'||DataNormalizationService::decimalCompare((string)$r->paid_amount,'0.00')>0)throw new \DomainException('gd_finance_paid_cannot_cancel');$reason=DataNormalizationService::text($reason);if($reason==='')throw new \DomainException('gd_reason_required');$before=(array)$r;$data=$this->stamp(['status'=>'cancelled','notes'=>trim((string)$r->notes."\nCancelamento: ".$reason),'lock_version'=>(int)$r->lock_version+1],false);$this->receivables->ci_save($data,$id);$this->audit_change('cancel','receivable',$id,$before,(array)$this->receivables->get_scoped($id,$this->unit_id),['reason'=>$reason]);}
    public function getReceivable(int $id):?object{$r=$this->receivables->get_scoped($id,$this->unit_id);if(!$r)return null;$r->items=$this->items->for_receivable($id,$this->unit_id);$r->allocations=$this->db->query("SELECT a.*,p.payment_number,p.payment_date,p.payment_method,p.payment_type,p.status payment_status FROM `{$this->db->prefixTable('gd_payment_allocations')}` a JOIN `{$this->db->prefixTable('gd_payments')}` p ON p.id=a.payment_id AND p.unit_id=a.unit_id AND p.deleted=0 WHERE a.unit_id=? AND a.receivable_id=? ORDER BY a.id",[$this->unit_id,$id])->getResult();$balance=DataNormalizationService::decimal((string)($r->balance_amount??'0.00'),2);$paid=DataNormalizationService::decimal((string)($r->paid_amount??'0.00'),2);$r->display_status=(string)$r->status==='cancelled'?'cancelled':(DataNormalizationService::decimalCompare($balance,'0.00')<=0?'paid':((string)$r->due_date<gmdate('Y-m-d')?'overdue':(DataNormalizationService::decimalCompare($paid,'0.00')>0?'partial':'open')));return $r;}
    /** Contexto da baixa de uma locaÃ§Ã£o, no mesmo formato operacional da Academy. */
    public function courtRentalPaymentContext(int $receivable_id):?array
    {
        $receivables = $this->db->prefixTable('gd_receivables');
        $sourceRow = $this->db->table($receivables)->select('source_type')->where('id', $receivable_id)->where('unit_id', $this->unit_id)->where('deleted', 0)->get(1)->getRow();
        $sourceType = (string) ($sourceRow->source_type ?? '');
        if (!in_array($sourceType, ['court_rental', 'barbecue_rental'], true)) { return null; }
        $rentals = $this->db->prefixTable($sourceType === 'barbecue_rental' ? 'gd_barbecue_rentals' : 'gd_court_rentals');
        $accounts = $this->db->prefixTable('gd_customer_accounts');
        $people = $this->db->prefixTable('gd_people');
        $row = $this->db->table($receivables . ' r')
            ->select("r.*,cr.rental_number,cr.title rental_title,cr.rental_type,cr.effective_from,a.display_name customer_name,p.full_name contact_name", false)
            ->join($rentals . ' cr', "cr.id=r.source_id AND cr.unit_id=r.unit_id AND cr.deleted=0", 'inner', false)
            ->join($accounts . ' a', "a.id=r.customer_account_id AND a.unit_id=r.unit_id AND a.deleted=0", 'inner', false)
            ->join($people . ' p', "p.id=cr.contact_person_id AND p.unit_id=cr.unit_id AND p.deleted=0", 'left', false)
            ->where('r.id', $receivable_id)
            ->where('r.unit_id', $this->unit_id)
            ->where('r.source_type', $sourceType)
            ->where('r.deleted', 0)
            ->get(1)
            ->getRow();
        if (!$row) {
            return null;
        }

        $reference = (string) $row->reference_month;
        if ($reference === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row->effective_from)) {
            $reference = substr((string) $row->effective_from, 0, 7);
        }
        if ($reference === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row->due_date)) {
            $reference = substr((string) $row->due_date, 0, 7);
        }

        $payments = $this->db->table($this->db->prefixTable('gd_payment_allocations') . ' pa')
            ->select('p.payment_date,p.amount,p.payment_number,p.payment_method,p.payment_type,pa.allocated_amount')
            ->join($this->db->prefixTable('gd_payments') . ' p', "p.id=pa.payment_id AND p.unit_id=pa.unit_id AND p.status='confirmed' AND p.deleted=0", 'inner', false)
            ->where('pa.unit_id', $this->unit_id)
            ->where('pa.receivable_id', $receivable_id)
            ->where('pa.status', 'active')
            ->orderBy('p.payment_date', 'DESC')
            ->orderBy('pa.id', 'DESC')
            ->get()
            ->getResult();

        return [
            'receivable' => $row,
            'rental' => $row,
            'customer_name' => (string) $row->customer_name,
            'renter_name' => (string) ($row->contact_name ?: $row->customer_name),
            'reference_month' => $reference,
            'payment_history' => $payments,
            'source_type' => $sourceType,
        ];
    }

    /** Registra uma baixa contextualizada de locaÃ§Ã£o sem expor alocaÃ§Ãµes tÃ©cnicas. */
    public function registerCourtRentalPayment(array $in):array
    {
        $receivable_id = (int) ($in['receivable_id'] ?? 0);
        $context = $this->courtRentalPaymentContext($receivable_id);
        if (!$context) {
            throw new \DomainException('gd_record_not_found');
        }

        $receivable = $context['receivable'];
        if ((string) $receivable->status === 'cancelled' || DataNormalizationService::decimalCompare((string) $receivable->balance_amount, '0.00') <= 0) {
            throw new \DomainException('gd_finance_receivable_unavailable');
        }

        $customer_name = DataNormalizationService::text($in['customer_name'] ?? '');
        if ($customer_name === '') {
            throw new \DomainException('gd_finance_customer_required');
        }
        if (DataNormalizationService::name($customer_name) !== DataNormalizationService::name((string) $context['renter_name'])) {
            throw new \DomainException('gd_finance_customer_mismatch');
        }

        $reference = $this->paymentReference($in['competence'] ?? ($in['reference_month'] ?? ''));
        if ($reference === '' || $reference !== (string) $context['reference_month']) {
            throw new \DomainException('gd_finance_payment_competence_mismatch');
        }

        $amount = DataNormalizationService::decimal($in['amount'] ?? ($in['valor'] ?? ''), 2);
        if (DataNormalizationService::decimalCompare($amount, '0.00') <= 0) {
            throw new \DomainException('gd_finance_payment_amount_required');
        }
        if (DataNormalizationService::decimalCompare($amount, (string) $receivable->balance_amount) > 0) {
            throw new \DomainException('gd_finance_overallocation');
        }

        $method = Constants::normalizePaymentMethod((string) ($in['payment_method'] ?? ($in['forma_pagamento'] ?? '')));
        if (!$method) {
            throw new \DomainException('gd_finance_payment_method_required');
        }
        $date = $this->valid_date($in['payment_date'] ?? ($in['data_pagamento'] ?? ''));
        if (!$date) {
            throw new \DomainException('gd_finance_payment_date_required');
        }

        $account_id = (int) ($in['financial_account_id'] ?? 0);
        if ($account_id <= 0) {
            $account_id = $this->defaultPaymentAccountId();
        }

        $result = $this->registerPayment([
            'allocations' => [$receivable_id => $amount],
            'amount' => $amount,
            'payment_date' => $date,
            'payment_method' => $method,
            'financial_account_id' => $account_id,
            'external_reference' => $in['external_reference'] ?? '',
            'notes' => $in['notes'] ?? ($in['observacao'] ?? ''),
            'payment_type' => 'regular',
        ]);
        $fresh = $this->receivables->get_scoped($receivable_id, $this->unit_id);
        return $result + [
            'receivable_id' => $receivable_id,
            'status' => (string) ($fresh->status ?? ''),
            'balance' => (string) ($fresh->balance_amount ?? ''),
        ];
    }

    public function receivablesPage(array $o):array
    {
        $t=$this->db->prefixTable('gd_receivables');$a=$this->db->prefixTable('gd_customer_accounts');$base=function()use($o,$t,$a){$q=$this->db->table($t)->join($a,"$a.id=$t.customer_account_id AND $a.unit_id=$t.unit_id AND $a.deleted=0",'inner',false)->where("$t.unit_id",$this->unit_id)->where("$t.deleted",0);foreach(['status','source_type','source_id','customer_account_id','business_area_id','cost_center_id'] as $f)if(($v=$o[$f]??'')!=='')$q->where("$t.$f",$v);if($v=$o['date_from']??'')$q->where("$t.due_date >=",$v);if($v=$o['date_to']??'')$q->where("$t.due_date <=",$v);if($v=trim((string)($o['search_by']??'')))$q->groupStart()->like("$t.receivable_number",$v)->orLike("$t.description",$v)->orLike("$a.display_name",$v)->groupEnd();return $q;};$total=$this->db->table($t)->where('unit_id',$this->unit_id)->where('deleted',0)->countAllResults();$filtered=$base()->countAllResults(false);$rows=$base()->select("$t.*,$a.display_name customer_name,CASE WHEN $t.status='cancelled' THEN 'cancelled' WHEN $t.balance_amount<=0 THEN 'paid' WHEN $t.due_date<CURDATE() THEN 'overdue' WHEN $t.paid_amount>0 THEN 'partial' ELSE 'open' END display_status",false)->orderBy("$t.due_date",'DESC')->limit(max(1,min(100,(int)($o['limit']??25))),max(0,(int)($o['skip']??0)))->get()->getResult();return ['data'=>$rows,'recordsTotal'=>$total,'recordsFiltered'=>$filtered];
    }
    public function registerPayment(array $in):array
    {
        $allocInput=(array)($in['allocations']??[]);$alloc=[];foreach($allocInput as $rid=>$allocationAmount){$rid=(int)$rid;if($rid<=0)continue;if(is_array($allocationAmount))$allocationAmount=$allocationAmount['amount']??'';$allocationAmount=trim((string)$allocationAmount);if($allocationAmount==='')continue;$value=DataNormalizationService::decimal($allocationAmount,2);if(DataNormalizationService::decimalCompare($value,'0.00')>0)$alloc[$rid]=$value;}if(!$alloc)throw new \DomainException('gd_finance_allocation_required');ksort($alloc);$amount=DataNormalizationService::decimal($in['amount']??'',2);$sum='0.00';foreach($alloc as $v)$sum=$this->add($sum,$v);if(DataNormalizationService::decimalCompare($amount,'0.00')<=0||DataNormalizationService::decimalCompare($sum,$amount)!==0)throw new \DomainException('gd_finance_allocation_total');$method=(string)($in['payment_method']??'');if(!in_array($method,Constants::PAYMENT_METHODS,true))throw new \DomainException('gd_invalid_value');$paymentType=(string)($in['payment_type']??'regular');if(!in_array($paymentType,Constants::PAYMENT_TYPES,true))throw new \DomainException('gd_invalid_value');$account=(int)($in['financial_account_id']??0);$this->activeAccount($account);$date=$this->valid_date($in['payment_date']??gmdate('Y-m-d'));
        if (!$date) throw new \DomainException('gd_finance_payment_date_required');
        $locks=[];$this->db->transBegin();try{foreach(array_keys($alloc) as $rid){$lock="gd:receivable:{$this->unit_id}:$rid";if((int)$this->db->query('SELECT GET_LOCK(?,10) acquired',[$lock])->getRow()->acquired!==1)throw new \RuntimeException('gd_lock_timeout');$locks[]=$lock;$r=$this->db->query("SELECT * FROM `{$this->db->prefixTable('gd_receivables')}` WHERE id=? AND unit_id=? AND deleted=0 FOR UPDATE",[$rid,$this->unit_id])->getRow();if(!$r||(string)$r->status==='cancelled'||DataNormalizationService::decimalCompare((string)$r->balance_amount,'0.00')<=0)throw new \DomainException('gd_finance_receivable_unavailable');if(DataNormalizationService::decimalCompare($alloc[$rid],(string)$r->balance_amount)>0)throw new \DomainException('gd_finance_overallocation');}
            $seq=new SequenceService();$seq->ensure($this->unit_id,'payment','PAG-'.gmdate('Y').'-',6,true);$number=$seq->next($this->unit_id,'payment');$data=$this->stamp(['unit_id'=>$this->unit_id,'payment_number'=>$number,'financial_account_id'=>$account,'payment_date'=>$date,'amount'=>$amount,'payment_method'=>$method,'payment_type'=>$paymentType,'external_reference'=>DataNormalizationService::text($in['external_reference']??'')?:null,'notes'=>DataNormalizationService::text($in['notes']??'')?:null,'status'=>'confirmed'],true);$pid=(int)$this->payments->ci_save($data,0);foreach($alloc as $rid=>$value){$ad=$this->stamp(['unit_id'=>$this->unit_id,'payment_id'=>$pid,'receivable_id'=>$rid,'allocated_amount'=>$value,'status'=>'active'],true);$this->allocations->ci_save($ad,0);$this->recalculate($rid);} $this->movement($account,$date,'in','payment',$pid,'Pagamento '.$number,$amount,null);$this->audit_change('confirm','payment',$pid,null,$data,['allocations'=>$alloc,'payment_type'=>$paymentType]);if($this->db->transCommit()===false)throw new \RuntimeException('save_failed');return ['id'=>$pid,'payment_number'=>$number,'payment_type'=>$paymentType];}catch(\Throwable $e){$this->db->transRollback();throw $e;}finally{foreach(array_reverse($locks) as $lock)$this->db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public function reversePayment(int $id,string $reason):void
    {
        $p=$this->payments->get_scoped($id,$this->unit_id);if(!$p)throw new \DomainException('gd_record_not_found');if($p->status!=='confirmed')throw new \DomainException('gd_finance_payment_reversed');$reason=DataNormalizationService::text($reason);if($reason==='')throw new \DomainException('gd_reason_required');$rows=$this->allocations->for_payment($id,$this->unit_id);$this->db->transBegin();try{$paymentData=$this->stamp(['status'=>'reversed','reversed_at'=>gmdate('Y-m-d H:i:s'),'reversed_by'=>$this->actor_id?:null,'reversal_reason'=>$reason],false);$this->payments->ci_save($paymentData,$id);foreach($rows as $a){if($a->status!=='active')continue;$d=$this->stamp(['status'=>'reversed'],false);$this->allocations->ci_save($d,(int)$a->id);$this->recalculate((int)$a->receivable_id);}$original=$this->db->table($this->db->prefixTable('gd_cash_movements'))->where('unit_id',$this->unit_id)->where('source_type','payment')->where('source_id',$id)->get(1)->getRow();$this->movement((int)$p->financial_account_id,gmdate('Y-m-d'),'out','payment_reversal',$id,'Estorno '.$p->payment_number,(string)$p->amount,(int)($original->id??0));$this->audit_change('reverse','payment',$id,(array)$p,(array)$this->payments->get_scoped($id,$this->unit_id),['reason'=>$reason]);if($this->db->transCommit()===false)throw new \RuntimeException('save_failed');}catch(\Throwable $e){$this->db->transRollback();throw $e;}
    }
    public function paymentsPage(array $o):array{$p=$this->db->prefixTable('gd_payments');$a=$this->db->prefixTable('gd_financial_accounts');$q=$this->db->table($p)->select("$p.*,$a.name account_name",false)->join($a,"$a.id=$p.financial_account_id AND $a.unit_id=$p.unit_id",'inner',false)->where("$p.unit_id",$this->unit_id)->where("$p.deleted",0);if($v=$o['date_from']??'')$q->where("$p.payment_date >=",$v);if($v=$o['date_to']??'')$q->where("$p.payment_date <=",$v);$total=$this->db->table($p)->where('unit_id',$this->unit_id)->where('deleted',0)->countAllResults();$rows=$q->orderBy("$p.payment_date",'DESC')->limit(max(1,min(100,(int)($o['limit']??25))),max(0,(int)($o['skip']??0)))->get()->getResult();return ['data'=>$rows,'recordsTotal'=>$total,'recordsFiltered'=>$total];}
    public function getPayment(int $id):?object{$p=$this->payments->get_scoped($id,$this->unit_id);if(!$p)return null;$p->allocations=$this->db->query("SELECT a.*,r.receivable_number,r.description FROM `{$this->db->prefixTable('gd_payment_allocations')}` a JOIN `{$this->db->prefixTable('gd_receivables')}` r ON r.id=a.receivable_id AND r.unit_id=a.unit_id WHERE a.unit_id=? AND a.payment_id=?",[$this->unit_id,$id])->getResult();return $p;}
    public function saveExpense(array $in,int $id=0):array
    {
        $old=$id?$this->expenses->get_scoped($id,$this->unit_id):null;if($id&&!$old)throw new \DomainException('gd_record_not_found');$description=DataNormalizationService::text($in['description']??'');$amount=DataNormalizationService::decimal($in['amount']??'',2);$status=(string)($in['status']??'pending');if($description===''||DataNormalizationService::decimalCompare($amount,'0.00')<=0||!in_array($status,Constants::EXPENSE_STATUSES,true))throw new \DomainException('gd_finance_invalid_expense');$date=$this->valid_date($in['expense_date']??gmdate('Y-m-d'));$due=$this->valid_date($in['due_date']??'');$paid=$this->valid_date($in['paid_date']??'');$account=(int)($in['financial_account_id']??0);$method=trim((string)($in['payment_method']??''));if($status==='paid'){if(!$paid)$paid=$date;$this->activeAccount($account);if(!in_array($method,Constants::PAYMENT_METHODS,true))throw new \DomainException('gd_invalid_value');}elseif($old&&$old->status==='paid')throw new \DomainException('gd_finance_paid_expense_immutable');$area=$this->assert_area((int)($in['business_area_id']??0));$center=$this->assert_cost_center((int)($in['cost_center_id']??0),$area);$number=$old->expense_number??'';if(!$number){$seq=new SequenceService();$seq->ensure($this->unit_id,'expense','DES-'.gmdate('Y').'-',6,true);$number=$seq->next($this->unit_id,'expense');}$this->db->transBegin();try{$data=$this->stamp(['unit_id'=>$this->unit_id,'expense_number'=>$number,'description'=>$description,'payee'=>DataNormalizationService::text($in['payee']??'')?:null,'expense_date'=>$date,'due_date'=>$due,'paid_date'=>$paid,'amount'=>$amount,'status'=>$status,'financial_account_id'=>$account?:null,'business_area_id'=>$area,'cost_center_id'=>$center,'payment_method'=>$method?:null,'notes'=>DataNormalizationService::text($in['notes']??'')?:null,'lock_version'=>(int)($old->lock_version??0)+1],!$old);$eid=(int)$this->expenses->ci_save($data,$id);if($status==='paid'&&(!$old||$old->status!=='paid'))$this->movement($account,$paid,'out','expense',$eid,'Despesa '.$number,$amount,null);$this->audit_change($id?'update':'create','expense',$eid,$old?(array)$old:null,(array)$this->expenses->get_scoped($eid,$this->unit_id));if($this->db->transCommit()===false)throw new \RuntimeException('save_failed');return ['id'=>$eid,'expense_number'=>$number];}catch(\Throwable $e){$this->db->transRollback();throw $e;}
    }
    /** Movimento de caixa manual (ex.: importação assistida sem vínculo seguro a cobrança). Aditivo à Fase 5. */
    public function createCashMovement(array $in):array
    {
        $account=(int)($in['financial_account_id']??0);$this->activeAccount($account);$type=(string)($in['movement_type']??'');if(!in_array($type,['in','out'],true))throw new \DomainException('gd_invalid_value');$date=$this->valid_date($in['movement_date']??gmdate('Y-m-d'));$amount=DataNormalizationService::decimal($in['amount']??'',2);$description=DataNormalizationService::text($in['description']??'');if(DataNormalizationService::decimalCompare($amount,'0.00')<=0||$description==='')throw new \DomainException('gd_finance_invalid_cash_movement');$source=(string)($in['source_type']??'import');$sourceId=(int)($in['source_id']??0);if($sourceId<=0)throw new \DomainException('gd_finance_invalid_cash_movement');$this->db->transBegin();try{$id=$this->movement($account,$date,$type,$source,$sourceId,$description,$amount,null);$this->audit_change('create','cash_movement',$id,null,['movement_type'=>$type,'amount'=>$amount,'source_type'=>$source,'source_id'=>$sourceId]);if($this->db->transCommit()===false)throw new \RuntimeException('save_failed');return ['id'=>$id];}catch(\Throwable $e){$this->db->transRollback();if(str_contains($e->getMessage(),'uniq_cash_source'))throw new \DomainException('gd_import_duplicate_movement');throw $e;}
    }
    public function expensesPage(array $o):array{$t=$this->db->prefixTable('gd_expenses');$a=$this->db->prefixTable('gd_financial_accounts');$q=$this->db->table($t)->select("$t.*,$a.name account_name",false)->join($a,"$a.id=$t.financial_account_id AND $a.unit_id=$t.unit_id",'left',false)->where("$t.unit_id",$this->unit_id)->where("$t.deleted",0);if($v=$o['status']??'')$q->where("$t.status",$v);$total=$this->db->table($t)->where('unit_id',$this->unit_id)->where('deleted',0)->countAllResults();$rows=$q->orderBy("$t.expense_date",'DESC')->limit(max(1,min(100,(int)($o['limit']??25))),max(0,(int)($o['skip']??0)))->get()->getResult();return ['data'=>$rows,'recordsTotal'=>$total,'recordsFiltered'=>$total];}
    public function cashPage(array $o):array{$t=$this->db->prefixTable('gd_cash_movements');$a=$this->db->prefixTable('gd_financial_accounts');$q=$this->db->table($t)->select("$t.*,$a.name account_name",false)->join($a,"$a.id=$t.financial_account_id AND $a.unit_id=$t.unit_id",'inner',false)->where("$t.unit_id",$this->unit_id);if($v=$o['financial_account_id']??'')$q->where("$t.financial_account_id",$v);if($v=$o['date_from']??'')$q->where("$t.movement_date >=",$v);if($v=$o['date_to']??'')$q->where("$t.movement_date <=",$v);$rows=$q->orderBy("$t.movement_date",'ASC')->orderBy("$t.id",'ASC')->get()->getResult();$balance='0.00';foreach($rows as $r){$balance=$r->movement_type==='in'?$this->add($balance,(string)$r->amount):$this->sub($balance,(string)$r->amount);$r->running_balance=$balance;}return $rows;}
    public function dashboard(string $from='',string $to=''):array
    {
        $from = $from ?: gmdate('Y-m-01');
        $to = $to ?: gmdate('Y-m-t');
        $r = $this->db->prefixTable('gd_receivables');
        $p = $this->db->prefixTable('gd_payments');
        $e = $this->db->prefixTable('gd_expenses');
        $ep = $this->db->prefixTable('gd_expense_payments');
        $sum = fn($sql, $params) => DataNormalizationService::decimal((string) ($this->db->query($sql, $params)->getRow()->total ?? '0'), 2);
        $open = $sum("SELECT COALESCE(SUM(balance_amount),0) total FROM `$r` WHERE unit_id=? AND deleted=0 AND status <> 'cancelled' AND balance_amount>0 AND due_date>=CURDATE()", [$this->unit_id]);
        $overdue = $sum("SELECT COALESCE(SUM(balance_amount),0) total FROM `$r` WHERE unit_id=? AND deleted=0 AND status <> 'cancelled' AND balance_amount>0 AND due_date<CURDATE()", [$this->unit_id]);
        $received = $sum("SELECT COALESCE(SUM(amount),0) total FROM `$p` WHERE unit_id=? AND deleted=0 AND status='confirmed' AND payment_date BETWEEN ? AND ?", [$this->unit_id, $from, $to]);
        $expenses = $this->db->tableExists($ep)
            ? $sum("SELECT COALESCE(SUM(amount),0) total FROM `$ep` WHERE unit_id=? AND deleted=0 AND status IN ('confirmed','legacy_migrated') AND payment_date BETWEEN ? AND ?", [$this->unit_id, $from, $to])
            : $sum("SELECT COALESCE(SUM(amount),0) total FROM `$e` WHERE unit_id=? AND deleted=0 AND status='paid' AND paid_date BETWEEN ? AND ?", [$this->unit_id, $from, $to]);
        $debtors = (int) $this->db->query("SELECT COUNT(DISTINCT customer_account_id) c FROM `$r` WHERE unit_id=? AND deleted=0 AND status <> 'cancelled' AND balance_amount>0 AND due_date<CURDATE()", [$this->unit_id])->getRow()->c;
        return ['open'=>$open,'overdue'=>$overdue,'received'=>$received,'expenses'=>$expenses,'balance'=>$this->sub($received,$expenses),'debtors'=>$debtors,'upcoming'=>$this->db->table($r)->where('unit_id',$this->unit_id)->where('deleted',0)->where('status <>','cancelled')->where('balance_amount >',0)->where('due_date >=',gmdate('Y-m-d'))->orderBy('due_date')->limit(5)->get()->getResult(),'movements'=>array_slice(array_reverse($this->cashPage(['date_from'=>$from,'date_to'=>$to])),0,8)];
    }
    /** Cria a cobrança de uma avulsa e, quando informado, lança o sinal. */
    public function createCourtRentalReceivableWithDeposit(array $in):array
    {
        if (array_key_exists('deposit_payment_method', $in)) {
            $in['payment_method'] = $in['deposit_payment_method'];
        }
        $amount=DataNormalizationService::decimal($in['original_amount']??'',2);
        $deposit=DataNormalizationService::decimal($in['deposit_amount']??'0.00',2);
        if(DataNormalizationService::decimalCompare($deposit,$amount)>0)throw new \DomainException('gd_deposit_exceeds_total');
        $receivable=$this->createReceivable($in+['source_type'=>'court_rental','reference_month'=>'','unit_amount'=>$amount,'quantity'=>'1']);
        $rid=(int)($receivable['id']??0);$payment=null;
        if($rid>0&&DataNormalizationService::decimalCompare($deposit,'0.00')>0){
            $account=(int)($in['financial_account_id']??0);
            if($account<=0){$accounts=$this->accounts();if(count($accounts)===1)$account=(int)$accounts[0]['id'];}
            $existing=$this->db->query("SELECT a.id FROM `{$this->db->prefixTable('gd_payment_allocations')}` a JOIN `{$this->db->prefixTable('gd_payments')}` p ON p.id=a.payment_id AND p.unit_id=a.unit_id AND p.status='confirmed' AND p.deleted=0 WHERE a.unit_id=? AND a.receivable_id=? AND a.status='active' AND p.payment_type='deposit' LIMIT 1",[$this->unit_id,$rid])->getRow();
            if(!$existing){$payment=$this->registerPayment(['allocations'=>[$rid=>$deposit],'amount'=>$deposit,'payment_date'=>$in['payment_date']??gmdate('Y-m-d'),'payment_method'=>(string)($in['payment_method']??''),'financial_account_id'=>$account,'notes'=>$in['payment_notes']??'Sinal da locação avulsa','payment_type'=>'deposit']);}
        }
        return $receivable+['payment'=>$payment,'deposit_amount'=>$deposit];
    }

    /**
     * Sincroniza o valor da cobrança avulsa sem apagar pagamentos existentes.
     * O total pode aumentar ou diminuir até o limite já pago; abaixo desse
     * limite a operação é recusada para não produzir saldo financeiro inválido.
     */
    public function syncCourtRentalReceivableAmount(int $rental_id, string $amount, string $description = ''): array
    {
        $amount = DataNormalizationService::decimal($amount, 2);
        if (DataNormalizationService::decimalCompare($amount, '0.00') <= 0) {
            throw new \DomainException('gd_court_rental_value_required');
        }

        $table = $this->db->prefixTable('gd_receivables');
        $row = $this->db->table($table)
            ->where('unit_id', $this->unit_id)
            ->where('source_type', 'court_rental')
            ->where('source_id', $rental_id)
            ->where('reference_month', '')
            ->where('deleted', 0)
            ->where('status <>', 'cancelled')
            ->orderBy('id', 'DESC')
            ->get(1)->getRow();

        // Registros antigos podem ainda não ter cobrança; cria a primeira de
        // forma idempotente, preservando o contrato com o FinanceService.
        if (!$row) {
            $today = gmdate('Y-m-d');
            $rental = $this->scoped('gd_court_rentals', $rental_id);
            $result = $this->createReceivable([
                'source_type' => 'court_rental',
                'source_id' => $rental_id,
                'reference_month' => '',
                'description' => $description !== '' ? $description : 'Locação avulsa',
                'issue_date' => $today,
                'due_date' => $today,
                'original_amount' => $amount,
                'unit_amount' => $amount,
                'quantity' => '1',
                'customer_account_id' => (int) $rental->customer_account_id,
            ]);
            return ['created' => (bool) ($result['created'] ?? false), 'id' => (int) ($result['id'] ?? 0), 'amount' => $amount];
        }

        $paid = DataNormalizationService::decimal((string) $row->paid_amount, 2);
        // Uma cobrança quitada é o histórico do que foi faturado/recebido.
        // Edição posterior do contrato não pode transformá-la em nova dívida.
        if ((string) $row->status === 'paid'
            || DataNormalizationService::decimalCompare((string) $row->balance_amount, '0.00') <= 0
            || DataNormalizationService::decimalCompare($paid, (string) $row->original_amount) >= 0
        ) {
            if (DataNormalizationService::decimalCompare($paid, $amount) > 0) {
                throw new \DomainException('gd_finance_amount_below_paid');
            }
            return [
                'created' => false,
                'id' => (int) $row->id,
                'amount' => (string) $row->original_amount,
                'paid' => $paid,
                'balance' => (string) $row->balance_amount,
                'status' => 'paid',
                'immutable' => true,
            ];
        }
        if (DataNormalizationService::decimalCompare($paid, $amount) > 0) {
            throw new \DomainException('gd_finance_amount_below_paid');
        }

        $balance = $this->sub($amount, $paid);
        $status = DataNormalizationService::decimalCompare($balance, '0.00') === 0
            ? 'paid'
            : (DataNormalizationService::decimalCompare($paid, '0.00') > 0
                ? 'partial'
                : ((string) $row->due_date < gmdate('Y-m-d') ? 'overdue' : 'open'));
        $expected = (int) $row->lock_version;
        $updated = $this->db->table($table)
            ->where('id', (int) $row->id)
            ->where('unit_id', $this->unit_id)
            ->where('lock_version', $expected)
            ->update([
                'original_amount' => $amount,
                'balance_amount' => $balance,
                'status' => $status,
                'description' => $description !== '' ? DataNormalizationService::text($description) : $row->description,
                'lock_version' => $expected + 1,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by' => $this->actor_id ?: null,
            ]);
        if ($updated !== true || $this->db->affectedRows() !== 1) {
            throw new \DomainException('gd_finance_edit_conflict');
        }

        $item_table = $this->db->prefixTable('gd_receivable_items');
        $item = $this->db->table($item_table)
            ->where('unit_id', $this->unit_id)
            ->where('receivable_id', (int) $row->id)
            ->where('deleted', 0)
            ->orderBy('id', 'ASC')->get(1)->getRow();
        if ($item) {
            $this->db->table($item_table)->where('id', (int) $item->id)->where('unit_id', $this->unit_id)->update([
                'quantity' => '1.000', 'unit_amount' => $amount, 'total_amount' => $amount,
                'updated_at' => gmdate('Y-m-d H:i:s'), 'updated_by' => $this->actor_id ?: null,
            ]);
        }
        $after = (array) $this->receivables->get_scoped((int) $row->id, $this->unit_id);
        $this->audit_change('update', 'receivable', (int) $row->id, (array) $row, $after, ['scope' => 'court_rental_amount_sync', 'rental_id' => $rental_id]);
        return ['created' => false, 'id' => (int) $row->id, 'amount' => $amount, 'paid' => $paid, 'balance' => $balance, 'status' => $status];
    }


    public function syncBarbecueRentalReceivableAmount(int $rental_id, string $amount, string $description = ''): array
    {
        $amount = DataNormalizationService::decimal($amount, 2);
        if (DataNormalizationService::decimalCompare($amount, '0.00') <= 0) {
            throw new \DomainException('gd_barbecue_rental_value_required');
        }

        $table = $this->db->prefixTable('gd_receivables');
        $row = $this->db->table($table)
            ->where('unit_id', $this->unit_id)
            ->where('source_type', 'barbecue_rental')
            ->where('source_id', $rental_id)
            ->where('reference_month', '')
            ->where('deleted', 0)
            ->where('status <>', 'cancelled')
            ->orderBy('id', 'DESC')
            ->get(1)->getRow();

        // Registros antigos podem ainda não ter cobrança; cria a primeira de
        // forma idempotente, preservando o contrato com o FinanceService.
        if (!$row) {
            $today = gmdate('Y-m-d');
            $rental = $this->scoped('gd_barbecue_rentals', $rental_id);
            $result = $this->createReceivable([
                'source_type' => 'barbecue_rental',
                'source_id' => $rental_id,
                'reference_month' => '',
                'description' => $description !== '' ? $description : 'Churrasqueira avulsa',
                'issue_date' => $today,
                'due_date' => $today,
                'original_amount' => $amount,
                'unit_amount' => $amount,
                'quantity' => '1',
                'customer_account_id' => (int) $rental->customer_account_id,
            ]);
            return ['created' => (bool) ($result['created'] ?? false), 'id' => (int) ($result['id'] ?? 0), 'amount' => $amount];
        }

        $paid = DataNormalizationService::decimal((string) $row->paid_amount, 2);
        // A cobrança quitada continua imutável mesmo quando o contrato é
        // editado depois do recebimento.
        if ((string) $row->status === 'paid'
            || DataNormalizationService::decimalCompare((string) $row->balance_amount, '0.00') <= 0
            || DataNormalizationService::decimalCompare($paid, (string) $row->original_amount) >= 0
        ) {
            if (DataNormalizationService::decimalCompare($paid, $amount) > 0) {
                throw new \DomainException('gd_finance_amount_below_paid');
            }
            return [
                'created' => false,
                'id' => (int) $row->id,
                'amount' => (string) $row->original_amount,
                'paid' => $paid,
                'balance' => (string) $row->balance_amount,
                'status' => 'paid',
                'immutable' => true,
            ];
        }
        if (DataNormalizationService::decimalCompare($paid, $amount) > 0) {
            throw new \DomainException('gd_finance_amount_below_paid');
        }

        $balance = $this->sub($amount, $paid);
        $status = DataNormalizationService::decimalCompare($balance, '0.00') === 0
            ? 'paid'
            : (DataNormalizationService::decimalCompare($paid, '0.00') > 0
                ? 'partial'
                : ((string) $row->due_date < gmdate('Y-m-d') ? 'overdue' : 'open'));
        $expected = (int) $row->lock_version;
        $updated = $this->db->table($table)
            ->where('id', (int) $row->id)
            ->where('unit_id', $this->unit_id)
            ->where('lock_version', $expected)
            ->update([
                'original_amount' => $amount,
                'balance_amount' => $balance,
                'status' => $status,
                'description' => $description !== '' ? DataNormalizationService::text($description) : $row->description,
                'lock_version' => $expected + 1,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by' => $this->actor_id ?: null,
            ]);
        if ($updated !== true || $this->db->affectedRows() !== 1) {
            throw new \DomainException('gd_finance_edit_conflict');
        }

        $item_table = $this->db->prefixTable('gd_receivable_items');
        $item = $this->db->table($item_table)
            ->where('unit_id', $this->unit_id)
            ->where('receivable_id', (int) $row->id)
            ->where('deleted', 0)
            ->orderBy('id', 'ASC')->get(1)->getRow();
        if ($item) {
            $this->db->table($item_table)->where('id', (int) $item->id)->where('unit_id', $this->unit_id)->update([
                'quantity' => '1.000', 'unit_amount' => $amount, 'total_amount' => $amount,
                'updated_at' => gmdate('Y-m-d H:i:s'), 'updated_by' => $this->actor_id ?: null,
            ]);
        }
        $after = (array) $this->receivables->get_scoped((int) $row->id, $this->unit_id);
        $this->audit_change('update', 'receivable', (int) $row->id, (array) $row, $after, ['scope' => 'barbecue_rental_amount_sync', 'rental_id' => $rental_id]);
        return ['created' => false, 'id' => (int) $row->id, 'amount' => $amount, 'paid' => $paid, 'balance' => $balance, 'status' => $status];
    }

    /**
     * Sincroniza o acréscimo mensalista nas competências em aberto.
     * A diferença é aplicada sobre o valor atual para preservar ajustes
     * manuais; competências já pagas permanecem como histórico.
     */
    public function syncCourtRentalRecurringReceivableAmount(
        int $rental_id,
        string $old_extra_amount,
        string $new_extra_amount,
        string $description = ''
    ): array {
        $old_extra = DataNormalizationService::decimal($old_extra_amount, 2);
        $new_extra = DataNormalizationService::decimal($new_extra_amount, 2);
        $delta = $this->sub($new_extra, $old_extra);
        if ($delta === '0.00') {
            return ['updated' => 0, 'delta' => $delta];
        }

        $table = $this->db->prefixTable('gd_receivables');
        $rows = $this->db->table($table)
            ->where('unit_id', $this->unit_id)
            ->where('source_type', 'court_rental')
            ->where('source_id', $rental_id)
            ->where('reference_month <>', '')
            ->where('deleted', 0)
            ->where('status <>', 'cancelled')
            ->orderBy('due_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResult();

        $updated = 0;
        foreach ($rows as $row) {
            // Uma competência paga não deve ser reescrita por uma alteração
            // posterior no contrato mensalista.
            if ((string) $row->status === 'paid'
                || DataNormalizationService::decimalCompare((string) $row->balance_amount, '0.00') <= 0
                || DataNormalizationService::decimalCompare((string) $row->paid_amount, (string) $row->original_amount) >= 0
            ) {
                continue;
            }

            $current = DataNormalizationService::decimal((string) $row->original_amount, 2);
            $paid = DataNormalizationService::decimal((string) $row->paid_amount, 2);
            $amount = $this->add($current, $delta);
            if (DataNormalizationService::decimalCompare($amount, '0.00') <= 0
                || DataNormalizationService::decimalCompare($paid, $amount) > 0
            ) {
                throw new \DomainException('gd_finance_amount_below_paid');
            }

            $balance = $this->sub($amount, $paid);
            $status = DataNormalizationService::decimalCompare($balance, '0.00') === 0
                ? 'paid'
                : (DataNormalizationService::decimalCompare($paid, '0.00') > 0
                    ? 'partial'
                    : ((string) $row->due_date < gmdate('Y-m-d') ? 'overdue' : 'open'));
            $expected = (int) $row->lock_version;
            $data = [
                'original_amount' => $amount,
                'balance_amount' => $balance,
                'status' => $status,
                'lock_version' => $expected + 1,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by' => $this->actor_id ?: null,
            ];
            if ($description !== '') {
                $data['description'] = DataNormalizationService::text($description);
            }
            $changed = $this->db->table($table)
                ->where('id', (int) $row->id)
                ->where('unit_id', $this->unit_id)
                ->where('lock_version', $expected)
                ->update($data);
            if ($changed !== true || $this->db->affectedRows() !== 1) {
                throw new \DomainException('gd_finance_edit_conflict');
            }

            $item_table = $this->db->prefixTable('gd_receivable_items');
            $item = $this->db->table($item_table)
                ->where('unit_id', $this->unit_id)
                ->where('receivable_id', (int) $row->id)
                ->where('deleted', 0)
                ->orderBy('id', 'ASC')
                ->get(1)
                ->getRow();
            if ($item) {
                $this->db->table($item_table)
                    ->where('id', (int) $item->id)
                    ->where('unit_id', $this->unit_id)
                    ->update([
                        'quantity' => '1.000',
                        'unit_amount' => $amount,
                        'total_amount' => $amount,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                        'updated_by' => $this->actor_id ?: null,
                    ]);
            }
            $after = (array) $this->receivables->get_scoped((int) $row->id, $this->unit_id);
            $this->audit_change('update', 'receivable', (int) $row->id, (array) $row, $after, [
                'scope' => 'court_rental_recurring_amount_sync',
                'rental_id' => $rental_id,
                'delta' => $delta,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'delta' => $delta];
    }


    public function syncBarbecueRentalRecurringReceivableAmount(
        int $rental_id,
        string $old_extra_amount,
        string $new_extra_amount,
        string $description = ''
    ): array {
        $old_extra = DataNormalizationService::decimal($old_extra_amount, 2);
        $new_extra = DataNormalizationService::decimal($new_extra_amount, 2);
        $delta = $this->sub($new_extra, $old_extra);
        if ($delta === '0.00') {
            return ['updated' => 0, 'delta' => $delta];
        }

        $table = $this->db->prefixTable('gd_receivables');
        $rows = $this->db->table($table)
            ->where('unit_id', $this->unit_id)
            ->where('source_type', 'barbecue_rental')
            ->where('source_id', $rental_id)
            ->where('reference_month <>', '')
            ->where('deleted', 0)
            ->where('status <>', 'cancelled')
            ->orderBy('due_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResult();

        $updated = 0;
        foreach ($rows as $row) {
            // Uma competência paga não deve ser reescrita por uma alteração
            // posterior no contrato mensalista.
            if ((string) $row->status === 'paid'
                || DataNormalizationService::decimalCompare((string) $row->balance_amount, '0.00') <= 0
                || DataNormalizationService::decimalCompare((string) $row->paid_amount, (string) $row->original_amount) >= 0
            ) {
                continue;
            }

            $current = DataNormalizationService::decimal((string) $row->original_amount, 2);
            $paid = DataNormalizationService::decimal((string) $row->paid_amount, 2);
            $amount = $this->add($current, $delta);
            if (DataNormalizationService::decimalCompare($amount, '0.00') <= 0
                || DataNormalizationService::decimalCompare($paid, $amount) > 0
            ) {
                throw new \DomainException('gd_finance_amount_below_paid');
            }

            $balance = $this->sub($amount, $paid);
            $status = DataNormalizationService::decimalCompare($balance, '0.00') === 0
                ? 'paid'
                : (DataNormalizationService::decimalCompare($paid, '0.00') > 0
                    ? 'partial'
                    : ((string) $row->due_date < gmdate('Y-m-d') ? 'overdue' : 'open'));
            $expected = (int) $row->lock_version;
            $data = [
                'original_amount' => $amount,
                'balance_amount' => $balance,
                'status' => $status,
                'lock_version' => $expected + 1,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by' => $this->actor_id ?: null,
            ];
            if ($description !== '') {
                $data['description'] = DataNormalizationService::text($description);
            }
            $changed = $this->db->table($table)
                ->where('id', (int) $row->id)
                ->where('unit_id', $this->unit_id)
                ->where('lock_version', $expected)
                ->update($data);
            if ($changed !== true || $this->db->affectedRows() !== 1) {
                throw new \DomainException('gd_finance_edit_conflict');
            }

            $item_table = $this->db->prefixTable('gd_receivable_items');
            $item = $this->db->table($item_table)
                ->where('unit_id', $this->unit_id)
                ->where('receivable_id', (int) $row->id)
                ->where('deleted', 0)
                ->orderBy('id', 'ASC')
                ->get(1)
                ->getRow();
            if ($item) {
                $this->db->table($item_table)
                    ->where('id', (int) $item->id)
                    ->where('unit_id', $this->unit_id)
                    ->update([
                        'quantity' => '1.000',
                        'unit_amount' => $amount,
                        'total_amount' => $amount,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                        'updated_by' => $this->actor_id ?: null,
                    ]);
            }
            $after = (array) $this->receivables->get_scoped((int) $row->id, $this->unit_id);
            $this->audit_change('update', 'receivable', (int) $row->id, (array) $row, $after, [
                'scope' => 'barbecue_rental_recurring_amount_sync',
                'rental_id' => $rental_id,
                'delta' => $delta,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'delta' => $delta];
    }

    public function summary(array $filter): array
    {
        $table = $this->db->prefixTable('gd_receivables');
        $query = $this->db->table($table)
            ->where('unit_id', $this->unit_id)
            ->where('deleted', 0);

        foreach ($filter as $key => $value) {
            $query->where($key, $value);
        }

        $rows = $query->orderBy('due_date', 'DESC')->get()->getResult();
        $activeRows = array_values(array_filter(
            $rows,
            static fn ($row): bool => (string) $row->status !== 'cancelled'
        ));
        $open = '0.00';
        $overdue = '0.00';
        $total = '0.00';
        $paid = '0.00';
        $balance = '0.00';
        $hasOverdue = false;

        foreach ($rows as $row) {
            if ((string) $row->status === 'cancelled') {
                continue;
            }

            $total = $this->add($total, (string) $row->original_amount);
            $paid = $this->add($paid, (string) $row->paid_amount);
            $balance = $this->add($balance, (string) $row->balance_amount);

            if (!in_array($row->status, ['paid', 'cancelled'], true)) {
                $open = $this->add($open, (string) $row->balance_amount);
                if ($row->due_date < gmdate('Y-m-d')
                    && DataNormalizationService::decimalCompare((string) $row->balance_amount, '0.00') > 0
                ) {
                    $overdue = $this->add($overdue, (string) $row->balance_amount);
                    $hasOverdue = true;
                }
            }
        }

        $ids = array_values(array_filter(array_map(
            static fn ($row): int => (int) $row->id,
            $activeRows
        )));
        $last = null;
        $history = [];
        $depositOnly = false;

        if ($ids) {
            $history = $this->db->table($this->db->prefixTable('gd_payment_allocations') . ' a')
                ->select('p.payment_date,p.amount,p.payment_number,p.payment_method,p.payment_type,a.allocated_amount,a.receivable_id')
                ->join(
                    $this->db->prefixTable('gd_payments') . ' p',
                    "p.id=a.payment_id AND p.status='confirmed' AND p.deleted=0",
                    'inner',
                    false
                )
                ->whereIn('a.receivable_id', $ids)
                ->where('a.unit_id', $this->unit_id)
                ->where('a.status', 'active')
                ->orderBy('p.payment_date', 'DESC')
                ->orderBy('a.id', 'DESC')
                ->get()
                ->getResult();

            $last = $history[0] ?? null;
            $onlyDeposit = true;
            foreach ($history as $entry) {
                if ((string) ($entry->payment_type ?? 'regular') !== 'deposit') {
                    $onlyDeposit = false;
                    break;
                }
            }
            $depositOnly = $onlyDeposit
                && $paid !== '0.00'
                && $balance !== '0.00';
        }

        $status = 'none';
        if ($activeRows) {
            $status = DataNormalizationService::decimalCompare($balance, '0.00') === 0
                ? 'paid'
                : ($hasOverdue
                    ? 'overdue'
                    : (DataNormalizationService::decimalCompare($paid, '0.00') > 0
                        ? ($depositOnly ? 'deposit_only' : 'partial')
                        : 'unpaid'));
        }

        return [
            'open' => $open,
            'overdue' => $overdue,
            'total' => $total,
            'paid' => $paid,
            'balance' => $balance,
            'status' => $status,
            'deposit_only' => $depositOnly,
            'last_payment' => $last,
            'payment_history' => $history,
            'receivables' => $activeRows,
        ];
    }

    /**
     * Saldos agregados por origem em UMA consulta (elimina N+1 na lista de
     * mensalistas). Usa o prefixo (unit_id, source_type, source_id) do índice
     * uniq_receivable_source. Retorna mapa source_id => ['balance','overdue',
     * 'partial'(bool),'open_ids'(int[])] apenas para cobranças abertas/vencidas.
     * @param array<int> $ids
     * @return array<int,array<string,mixed>>
     */
    public function balancesBySource(string $source_type, array $ids, ?string $reference_month = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        if (!$ids) { return []; }
        if ($reference_month !== null) { $reference_month = $this->reference($reference_month); }

        $t = $this->db->prefixTable('gd_receivables');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $referenceSql = $reference_month === null ? '' : ' AND reference_month=?';
        $contextSql = '';
        if ($source_type === 'court_rental' || $source_type === 'barbecue_rental') {
            $rentalTable = $this->db->prefixTable($source_type === 'barbecue_rental' ? 'gd_barbecue_rentals' : 'gd_court_rentals');
            $accountTable = $this->db->prefixTable('gd_customer_accounts');
            $contextSql = " AND EXISTS (SELECT 1 FROM `$rentalTable` cr JOIN `$accountTable` ca ON ca.id=cr.customer_account_id AND ca.unit_id=cr.unit_id AND ca.deleted=0 WHERE cr.id=source_id AND cr.unit_id=" . $this->unit_id . " AND cr.deleted=0)";
        }
        $sourceParams = [$this->unit_id, $source_type];
        if ($reference_month !== null) { $sourceParams[] = $reference_month; }
        $sourceParams = array_merge($sourceParams, $ids);

        // Quando a lista é mensalista, reference_month restringe a leitura à
        // competência exibida. Sem esse filtro o saldo histórico (por exemplo,
        // uma dívida de R$ 1.800) contaminava a competência futura da linha.
        $rows = $this->db->query(
            "SELECT source_id,
                    COALESCE(SUM(original_amount),0) total,
                    COALESCE(SUM(paid_amount),0) paid,
                    COALESCE(SUM(balance_amount),0) bal,
                    COALESCE(SUM(CASE WHEN due_date<CURDATE() AND balance_amount>0 THEN balance_amount ELSE 0 END),0) overdue,
                    GROUP_CONCAT(CASE WHEN status IN ('open','partial','overdue') AND balance_amount>0 THEN id END ORDER BY due_date ASC) open_ids
               FROM `$t`
              WHERE unit_id=? AND source_type=?{$referenceSql}{$contextSql}
                AND source_id IN ($ph) AND deleted=0 AND status<>'cancelled'
              GROUP BY source_id",
            $sourceParams
        )->getResult();

        $paymentReferenceSql = $reference_month === null ? '' : ' AND r.reference_month=?';
        $paymentParams = [$this->unit_id, $source_type];
        if ($reference_month !== null) { $paymentParams[] = $reference_month; }
        $paymentParams = array_merge($paymentParams, $ids);
        $paymentRows = $this->db->query(
            "SELECT r.source_id,
                    MAX(CASE WHEN p.payment_type='deposit' THEN 1 ELSE 0 END) deposit_count,
                    COALESCE(SUM(CASE WHEN p.payment_type='regular' THEN a.allocated_amount ELSE 0 END),0) regular_paid
               FROM `{$t}` r
               JOIN `{$this->db->prefixTable('gd_payment_allocations')}` a
                 ON a.receivable_id=r.id AND a.unit_id=r.unit_id AND a.status='active'
               JOIN `{$this->db->prefixTable('gd_payments')}` p
                 ON p.id=a.payment_id AND p.unit_id=a.unit_id AND p.status='confirmed' AND p.deleted=0
              WHERE r.unit_id=? AND r.source_type=?{$paymentReferenceSql}{$contextSql}
                AND r.source_id IN ($ph) AND r.deleted=0 AND r.status<>'cancelled'
              GROUP BY r.source_id",
            $paymentParams
        )->getResult();

        $payments = [];
        foreach ($paymentRows as $row) { $payments[(int) $row->source_id] = $row; }
        $map = [];
        foreach ($rows as $r) {
            $sourceId = (int) $r->source_id;
            $openIds = array_values(array_filter(array_map('intval', explode(',', (string) $r->open_ids))));
            $total = DataNormalizationService::decimal((string) $r->total, 2);
            $paid = DataNormalizationService::decimal((string) $r->paid, 2);
            $balance = DataNormalizationService::decimal((string) $r->bal, 2);
            $overdue = DataNormalizationService::decimal((string) $r->overdue, 2);
            $payment = $payments[$sourceId] ?? null;
            $regularPaid = DataNormalizationService::decimal((string) ($payment->regular_paid ?? '0'), 2);
            $depositOnly = $payment
                && (int) $payment->deposit_count > 0
                && DataNormalizationService::decimalCompare($regularPaid, '0.00') === 0
                && DataNormalizationService::decimalCompare($paid, '0.00') > 0
                && DataNormalizationService::decimalCompare($balance, '0.00') > 0;
            $status = DataNormalizationService::decimalCompare($balance, '0.00') === 0
                ? 'paid'
                : (DataNormalizationService::decimalCompare($overdue, '0.00') > 0
                    ? 'overdue'
                    : (DataNormalizationService::decimalCompare($paid, '0.00') > 0
                        ? ($depositOnly ? 'deposit_only' : 'partial')
                        : 'unpaid'));
            $map[$sourceId] = [
                'total' => $total, 'paid' => $paid, 'balance' => $balance,
                'overdue' => $overdue, 'partial' => DataNormalizationService::decimalCompare($paid, '0.00') > 0 && DataNormalizationService::decimalCompare($balance, '0.00') > 0,
                'deposit_only' => (bool) $depositOnly, 'status' => $status, 'open_ids' => $openIds,
            ];
        }
        return $map;
    }

    private function resolveSource(string $source,int $id,int $account):array{if($source==='enrollment'){$e=$this->scoped('gd_enrollments',$id);$sp=$this->scoped('gd_school_profiles',(int)$e->school_profile_id);return [(int)$sp->family_account_id,$id];}if($source==='court_rental'){$r=$this->scoped('gd_court_rentals',$id);return [(int)$r->customer_account_id,$id];}if($source==='barbecue_rental'){$r=$this->scoped('gd_barbecue_rentals',$id);return [(int)$r->customer_account_id,$id];}if($account<=0)throw new \DomainException('gd_finance_customer_required');return [$account,$id?:0];}
    private function recalculate(int $id):void{$r=$this->receivables->get_scoped($id,$this->unit_id);if(!$r)throw new \DomainException('gd_record_not_found');$paid=DataNormalizationService::decimal((string)($this->db->query("SELECT COALESCE(SUM(allocated_amount),0) total FROM `{$this->db->prefixTable('gd_payment_allocations')}` WHERE unit_id=? AND receivable_id=? AND status='active'",[$this->unit_id,$id])->getRow()->total??'0'),2);$balance=$this->sub((string)$r->original_amount,$paid);$status=DataNormalizationService::decimalCompare($balance,'0.00')===0?'paid':(DataNormalizationService::decimalCompare($paid,'0.00')>0?'partial':($r->due_date<gmdate('Y-m-d')?'overdue':'open'));$d=$this->stamp(['paid_amount'=>$paid,'balance_amount'=>$balance,'status'=>$status,'lock_version'=>(int)$r->lock_version+1],false);$this->receivables->ci_save($d,$id);}
    private function movement(int $account,string $date,string $type,string $source,int $sourceId,string $description,string $amount,?int $reversal):int{$d=['unit_id'=>$this->unit_id,'financial_account_id'=>$account,'movement_date'=>$date,'movement_type'=>$type,'source_type'=>$source,'source_id'=>$sourceId,'description'=>$description,'amount'=>$amount,'reversal_of_movement_id'=>$reversal,'created_by'=>$this->actor_id?:null];return (int)$this->cash->ci_save($d,0);}
    private function defaultPaymentAccountId():int
    {
        $table = $this->db->prefixTable('gd_financial_accounts');
        $row = $this->db->table($table)->select('id')->where('unit_id', $this->unit_id)->where('status', 'active')->where('deleted', 0)->orderBy('id', 'ASC')->get(1)->getRow();
        if ($row) return (int) $row->id;

        $existing = $this->db->table($table)->where('unit_id', $this->unit_id)->where('code', 'MAIN-CASH')->where('deleted', 0)->get(1)->getRow();
        if ($existing) {
            $this->db->table($table)->where('id', (int) $existing->id)->where('unit_id', $this->unit_id)->update(['status' => 'active', 'updated_at' => gmdate('Y-m-d H:i:s'), 'updated_by' => $this->actor_id ?: null]);
            return (int) $existing->id;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->db->table($table)->insert(['unit_id' => $this->unit_id, 'code' => 'MAIN-CASH', 'name' => 'Caixa Principal', 'account_type' => 'cash', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now, 'created_by' => $this->actor_id ?: null, 'updated_by' => $this->actor_id ?: null, 'deleted' => 0]);
        $id = (int) $this->db->insertID();
        if ($id <= 0) throw new \DomainException('gd_finance_account_required');
        return $id;
    }

    private function paymentReference($value):string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $matches)) {
            $month = (int) $matches[2];
            return $month >= 1 && $month <= 12 ? sprintf('%04d-%02d', (int) $matches[1], $month) : '';
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            $month = (int) $matches[1];
            return $month >= 1 && $month <= 12 ? sprintf('%04d-%02d', (int) $matches[2], $month) : '';
        }
        return '';
    }

    private function activeAccount(int $id):object{$r=$this->scoped('gd_financial_accounts',$id);if($r->status!=='active')throw new \DomainException('gd_finance_account_inactive');return $r;}
    private function scoped(string $table,int $id):object{$r=$this->db->table($this->db->prefixTable($table))->where('id',$id)->where('unit_id',$this->unit_id)->where('deleted',0)->get(1)->getRow();if(!$r)throw new \DomainException('gd_record_not_found');return $r;}
    private function reference($v):string{$v=trim((string)$v);if($v==='' )return '';if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$v))throw new \DomainException('gd_finance_invalid_reference');return $v;}
    private function cents(string $v):int{$negative=str_starts_with(trim($v),'-');$v=DataNormalizationService::decimal($negative?substr(trim($v),1):$v,2);[$i,$f]=explode('.',$v);$c=(int)$i*100+(int)$f;return $negative?-$c:$c;}private function money(int $c):string{$sign=$c<0?'-':'';$c=abs($c);return $sign.intdiv($c,100).'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT);}private function add(string $a,string $b):string{return $this->money($this->cents($a)+$this->cents($b));}private function sub(string $a,string $b):string{return $this->money($this->cents($a)-$this->cents($b));}private function multiply(string $q,string $u):string{[$qi,$qf]=explode('.',DataNormalizationService::decimal($q,3));$m=(int)$qi*1000+(int)$qf;$c=$this->cents($u);$raw=$m*$c;if($raw%1000!==0)throw new \DomainException('gd_finance_fractional_cent');return $this->money(intdiv($raw,1000));}
}
