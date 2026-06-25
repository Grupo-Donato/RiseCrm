<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class DataPrivacyService
{
    public static function maskDocument($value): string { $v=preg_replace('/\D+/','',(string)$value)??''; return $v===''?'':str_repeat('*',max(0,strlen($v)-4)).substr($v,-4); }
    public static function maskPhone($value): string { $v=preg_replace('/\D+/','',(string)$value)??''; return $v===''?'':str_repeat('*',max(0,strlen($v)-4)).substr($v,-4); }
    public static function maskEmail($value): string { $v=(string)$value; if(!str_contains($v,'@')){return $v===''?'':'***';} [$l,$d]=explode('@',$v,2); return mb_substr($l,0,1).'***@'.$d; }
    public static function forAudit(?array $data): ?array
    {
        if($data===null){return null;} $out=[];
        foreach($data as $k=>$v){$key=strtolower((string)$k); if(is_array($v)){$out[$k]=self::forAudit($v);continue;}
            if(in_array($key,['document_number','document_number_normalized'],true)){$out[$k]=self::maskDocument($v);}
            elseif(str_contains($key,'email')){$out[$k]=self::maskEmail($v);}
            elseif(in_array($key,['value','normalized_value'],true)&&($data['contact_type']??'')==='email'){$out[$k]=self::maskEmail($v);}
            elseif(str_contains($key,'phone')||str_contains($key,'whatsapp')||in_array($key,['value','normalized_value'],true)){$out[$k]=self::maskPhone($v);}
            elseif(in_array($key,['street','number','complement','district','postal_code','postal_code_normalized','notes'],true)){$out[$k]=$v===null?null:'***';}
            else{$out[$k]=$v;}
        } return $out;
    }
    private function __construct() {}
}
