<?php

echo "# Fase 3B1 — schema, criação, conflitos e ciclo de vida\n";
$booking_tables=["gd_bookings","gd_booking_resources","gd_booking_events"];
gd_assert("schema 022–024 aplicado",array_reduce($booking_tables,fn($ok,$t)=>$ok&&$db->tableExists($prefix.$t),true));
$event_fields=$db->getFieldNames($prefix."gd_booking_events");
gd_assert("eventos de reserva não possuem soft delete",!in_array("deleted",$event_fields,true));
$booking_indexes=$db->query("SHOW INDEX FROM `{$prefix}gd_bookings`")->getResult();
gd_assert("número da reserva possui unique por unidade",(bool)array_filter($booking_indexes,static fn($i)=>$i->Key_name==="uniq_unit_booking_number"&&(int)$i->Non_unique===0));
gd_assert("extensão de recorrência preserva colunas da reserva",$db->fieldExists("series_id",$prefix."gd_bookings")&&$db->fieldExists("series_occurrence_key",$prefix."gd_bookings"));

$bookingResourceService=new \grupo_donato_gestao\Services\ResourceService($unit_id);
$bookingExceptionService=new \grupo_donato_gestao\Services\ResourceAvailabilityExceptionService($unit_id);
$bookingTime=new \grupo_donato_gestao\Services\TemporalService($unit_id);
$bookingService=new \grupo_donato_gestao\Services\BookingService($unit_id);
$bookingLifecycle=new \grupo_donato_gestao\Services\BookingLifecycleService($unit_id);
$bookingHold=new \grupo_donato_gestao\Services\BookingHoldService($unit_id);
$bookingConflict=new \grupo_donato_gestao\Services\BookingConflictService($unit_id);
$bookingResourceIds=[];
foreach(["A","B","C"] as $suffix){$saved=$bookingResourceService->save(["code"=>"BOOK$suffix","name"=>"Booking resource $suffix","resource_type"=>"room","is_active"=>1,"is_bookable"=>1]);$bookingResourceIds[]=(int)$saved["id"];$bookingExceptionService->save(["resource_id"=>(int)$saved["id"],"exception_type"=>"open","starts_at_utc"=>$bookingTime->localToUtc("2098-08-10","08:00"),"ends_at_utc"=>$bookingTime->localToUtc("2098-08-10","18:00"),"title"=>"Booking self-test open"]);}
[$bookA,$bookB,$bookC]=$bookingResourceIds;
$payload=static fn(array $resources,string $start="2098-08-10T12:00",string $end="2098-08-10T13:00",string $status="pending_confirmation")=>["booking_type"=>"internal","title"=>"Reserva self-test","starts_at_local"=>$start,"ends_at_local"=>$end,"status"=>$status,"resources"=>$resources];
$one=static fn(int $id,int $before=0,int $after=0)=>[["resource_id"=>$id,"buffer_before_minutes"=>$before,"buffer_after_minutes"=>$after]];

$pending=$bookingService->save($payload($one($bookA)));
$pendingRow=$bookingService->get($pending["id"]);
gd_assert("cria reserva pending com número backend",str_starts_with($pending["booking_number"],"RES-".gmdate("Y")."-")&&$pendingRow->status==="pending_confirmation");
gd_assert("reserva avulsa permanece sem vínculo de série",$pendingRow->series_id===null&&$pendingRow->series_occurrence_key===null);
gd_assert("timezone da unidade persistido",$pendingRow->timezone===$bookingTime->timezoneName());
gd_assert("ocupação sem buffer coincide com utilização",$pendingRow->resources[0]->occupancy_starts_at_utc===$pendingRow->starts_at_utc&&$pendingRow->resources[0]->occupancy_ends_at_utc===$pendingRow->ends_at_utc);
gd_assert("reserva comercial sem cliente é rejeitada",gd_throws(fn()=>$bookingService->save(array_replace($payload($one($bookB)),["booking_type"=>"customer_rental","title"=>"Sem cliente"])),"gd_booking_customer_required"));
$commercial=$payload($one($bookB),"2098-08-10T09:00","2098-08-10T10:00");$commercial["booking_type"]="customer_rental";$commercial["title"]="Reserva comercial";$commercial["customer_account_id"]=$family["id"];$commercial["contact_person_id"]=$person_two["id"];
$commercialSaved=$bookingService->save($commercial);
gd_assert("reserva comercial aceita cliente e contato vinculados",$bookingService->get($commercialSaved["id"])->contact_person_id==$person_two["id"]);
gd_assert("contato arbitrário é rejeitado",gd_throws(fn()=>$bookingService->save(array_replace($commercial,["starts_at_local"=>"2098-08-10T10:00","ends_at_local"=>"2098-08-10T11:00","contact_person_id"=>$override_person["id"]])),"gd_invalid_booking_contact"));
gd_assert("recurso duplicado no payload é rejeitado",gd_throws(fn()=>$bookingService->save($payload(array_merge($one($bookC),$one($bookC)),"2098-08-10T10:00","2098-08-10T11:00")),"gd_duplicate_booking_resource"));

gd_assert("sobreposição total é rejeitada",gd_throws(fn()=>$bookingService->save($payload($one($bookA))),"gd_booking_duplicate"));
gd_assert("sobreposição parcial é rejeitada",gd_throws(fn()=>$bookingService->save($payload($one($bookA),"2098-08-10T12:30","2098-08-10T13:30")+["title"=>"Parcial"]),"gd_booking_conflict"));
gd_assert("intervalo contido é rejeitado",gd_throws(fn()=>$bookingService->save($payload($one($bookA),"2098-08-10T12:15","2098-08-10T12:45")+["title"=>"Contido"]),"gd_booking_conflict"));
$adjacent=$bookingService->save($payload($one($bookA),"2098-08-10T13:00","2098-08-10T14:00")+["title"=>"Adjacente"]);
gd_assert("adjacência semiaberta é permitida",$adjacent["id"]>0);
gd_assert("buffer transforma adjacência em conflito",gd_throws(fn()=>$bookingService->save($payload($one($bookA,0,10),"2098-08-10T11:00","2098-08-10T12:00")+["title"=>"Buffer"]),"gd_booking_conflict"));
$different=$bookingService->save($payload($one($bookC))+["title"=>"Recurso diferente"]);
gd_assert("mesmo horário em recurso diferente é permitido",$different["id"]>0);
$multi=$bookingService->save($payload([["resource_id"=>$bookB,"buffer_before_minutes"=>5,"buffer_after_minutes"=>5],["resource_id"=>$bookC,"buffer_before_minutes"=>10,"buffer_after_minutes"=>10]],"2098-08-10T14:00","2098-08-10T15:00")+["title"=>"Multi recurso"]);
gd_assert("reserva usa múltiplos recursos e buffers individuais",count($bookingService->get($multi["id"])->resources)===2);
gd_assert("buffer extrapolando abertura é rejeitado",gd_throws(fn()=>$bookingService->save($payload($one($bookB,10,0),"2098-08-10T08:00","2098-08-10T08:30")+["title"=>"Fora por buffer"]),"gd_booking_resource_unavailable"));

$holdLocal=(new \DateTimeImmutable("now",new \DateTimeZone($bookingTime->timezoneName())))->modify("+20 minutes")->format("Y-m-d\TH:i");
$holdData=$payload($one($bookB),"2098-08-10T16:00","2098-08-10T17:00","hold")+["title"=>"Hold ativo","hold_expires_at_local"=>$holdLocal];$hold=$bookingService->save($holdData);
gd_assert("hold ativo bloqueia conflito",gd_throws(fn()=>$bookingService->save(array_replace($payload($one($bookB),"2098-08-10T16:00","2098-08-10T17:00"),["title"=>"Conflita hold"])),"gd_booking_conflict"));
$db->table($prefix."gd_bookings")->where("id",$hold["id"])->update(["hold_expires_at_utc"=>gmdate("Y-m-d H:i:s",time()-60)]);
$afterExpiredHold=$bookingService->save($payload($one($bookB),"2098-08-10T16:00","2098-08-10T17:00")+["title"=>"Após hold vencido"]);
gd_assert("hold vencido não bloqueia antes da limpeza",$afterExpiredHold["id"]>0);
$expiredBatch=$bookingHold->expireBatch(1);
gd_assert("limpeza limitada expira hold e cria evento",$expiredBatch["expired"]===1&&$bookingService->get($hold["id"])->status==="expired"&&count(array_filter($bookingService->get($hold["id"])->events,static fn($e)=>$e->event_type==="expired"))===1);
gd_assert("limpeza de holds é idempotente",$bookingHold->expireBatch(10)["expired"]===0);
gd_assert("hold vencido não pode confirmar",gd_throws(fn()=>$bookingLifecycle->confirm($hold["id"]),"gd_invalid_booking_transition"));

$confirmed=$bookingLifecycle->confirm($pending["id"]);$started=$bookingLifecycle->start($pending["id"]);$completed=$bookingLifecycle->complete($pending["id"]);
gd_assert("pending percorre confirmed → in_progress → completed",$confirmed->status==="confirmed"&&$started->status==="in_progress"&&$completed->status==="completed");
gd_assert("estado terminal rejeita transição",gd_throws(fn()=>$bookingLifecycle->cancel($pending["id"],"inválido"),"gd_invalid_booking_transition"));
$cancelTarget=$bookingService->save($payload($one($bookC),"2098-08-10T10:00","2098-08-10T11:00")+["title"=>"Cancelar"]);$cancelled=$bookingLifecycle->cancel($cancelTarget["id"],"Operação cancelada");
gd_assert("cancelamento exige motivo e deixa de bloquear",$cancelled->status==="cancelled"&&gd_throws(fn()=>$bookingLifecycle->cancel($adjacent["id"],""),"gd_cancellation_reason_required"));
$noShowTarget=$bookingService->save($payload($one($bookC),"2098-08-10T17:00","2098-08-10T18:00","confirmed")+["title"=>"No show"],0,true);$db->table($prefix."gd_bookings")->where("id",$noShowTarget["id"])->update(["starts_at_utc"=>gmdate("Y-m-d H:i:s",time()-3600)]);$noShow=$bookingLifecycle->noShow($noShowTarget["id"],"Não compareceu");
gd_assert("confirmed pode virar no_show após início previsto",$noShow->status==="no_show");

$editTarget=$bookingService->save($payload($one($bookA),"2098-08-10T15:00","2098-08-10T16:00")+["title"=>"Editar"]);$editInput=$payload($one($bookA),"2098-08-10T15:10","2098-08-10T16:10")+["title"=>"Editada","lock_version"=>$editTarget["lock_version"]];$edited=$bookingService->save($editInput,$editTarget["id"]);
gd_assert("edição incrementa lock_version",$edited["lock_version"]===$editTarget["lock_version"]+1);
gd_assert("lock_version obsoleto impede overwrite",gd_throws(fn()=>$bookingService->save($editInput,$editTarget["id"]),"gd_booking_edit_conflict"));

$eventModel=model("grupo_donato_gestao\\Models\\Gd_booking_events_model");
gd_assert("booking events são append-only",gd_throws(fn()=>$eventModel->delete(1),"Booking events cannot be deleted."));
$richCalendar=(new \grupo_donato_gestao\Services\CalendarService($unit_id,true))->events("2098-08-10T00:00:00Z","2098-08-11T00:00:00Z",[$bookA],["booking"]);$privateCalendar=(new \grupo_donato_gestao\Services\CalendarService($unit_id,false))->events("2098-08-10T00:00:00Z","2098-08-11T00:00:00Z",[$bookA],["booking"]);
gd_assert("calendário exibe reserva por recurso com ocupação",(bool)array_filter($richCalendar,static fn($e)=>($e["extendedProps"]["booking_id"]??0)>0&&isset($e["extendedProps"]["occupancy_start"])));
gd_assert("calendário sem booking view retorna somente Ocupado",!array_filter($privateCalendar,static fn($e)=>$e["title"]!==app_lang("gd_calendar_busy"))&&!str_contains(json_encode($privateCalendar),"customer"));

$bookingManageAccess=new \grupo_donato_gestao\Services\AccessService($pm("gd_bookings_manage"));$bookingStatusAccess=new \grupo_donato_gestao\Services\AccessService($pm("gd_booking_status_manage"));
gd_assert("bookings_manage implica leituras necessárias",$bookingManageAccess->can("gd_bookings_view")&&$bookingManageAccess->can("gd_calendar_view")&&$bookingManageAccess->can("gd_resources_view")&&$bookingManageAccess->can("gd_customer_accounts_view")&&$bookingManageAccess->can("gd_people_view"));
gd_assert("status_manage não concede gestão de clientes ou recursos",$bookingStatusAccess->can("gd_bookings_view")&&$bookingStatusAccess->can("gd_calendar_view")&&!$bookingStatusAccess->can("gd_customer_accounts_manage")&&!$bookingStatusAccess->can("gd_resources_manage"));
gd_assert("rotas de reserva usam GET para leitura e POST para escrita",isset($get_routes["grupo_donato/bookings"],$get_routes["grupo_donato/bookings/modal"])&&isset($post_routes["grupo_donato/bookings/save"],$post_routes["grupo_donato/bookings/check-availability"])&&!isset($get_routes["grupo_donato/bookings/save"]));
gd_assert("CSRF protege escrita de reservas",in_array("csrf",(array)get_array_value($routes->getRoutesOptions("grupo_donato/bookings/save","POST"),"filter"),true));
gd_assert("idioma da Fase 3B1 resolve",app_lang("gd_menu_bookings")==="Reservas"&&app_lang("gd_booking_conflict")!=="gd_booking_conflict");
