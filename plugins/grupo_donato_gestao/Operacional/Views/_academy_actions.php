<script>
$(function(){
    $(document).off("submit.gdAcademy", ".gd-academy-ajax-form").on("submit.gdAcademy", ".gd-academy-ajax-form", function(e){
        e.preventDefault();
        var form=this, button=$(form).find("button[type='submit']"), original=button.html();
        if(form.dataset.confirmMessage && !window.confirm(form.dataset.confirmMessage)) return;
        button.prop("disabled",true).text("Salvando...");
        appAjaxRequest({url:form.action,type:"POST",data:$(form).serialize(),dataType:"json",success:function(result){
            if(result&&result.success){ window.location.reload(); return; }
            appAlert.error((result&&result.message)||"Não foi possível concluir a operação.");
            button.prop("disabled",false).html(original);
        },error:function(){appAlert.error("Não foi possível concluir a operação.");button.prop("disabled",false).html(original);}});
    });
});
</script>
<?php
$academyPositionOptions = is_array($position_options ?? null) && $position_options ? $position_options : \grupo_donato_gestao\Services\AcademyEventService::positionOptions();
$academyPositionOptionsJson = json_encode($academyPositionOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<script>
$(function(){
    var positionOptions = <?php echo $academyPositionOptionsJson ?: "{}"; ?>;
    var positionAliases = {
        "goalkeeper":"Goleiro", "goleiro":"Goleiro",
        "defender":"Zagueiro", "zagueiro":"Zagueiro", "zagueira":"Zagueiro",
        "fullback":"Lateral", "lateral":"Lateral", "ala":"Lateral",
        "defensive_midfielder":"Volante", "volante":"Volante",
        "midfielder":"Meia", "meia":"Meia", "meio-campista":"Meia", "meio campista":"Meia",
        "winger":"Ponta", "ponta":"Ponta",
        "forward":"Atacante", "atacante":"Atacante"
    };
    $(".gd-academy-page input[name='position']").each(function(){
        var input=$(this), raw=$.trim(input.val()||""), current=positionAliases[raw.toLowerCase()]||raw;
        var select=$("<select/>",{class:input.attr("class")||"form-control",name:input.attr("name")||"position"});
        if(input.attr("id")) select.attr("id",input.attr("id"));
        if(input.attr("style")) select.attr("style",input.attr("style"));
        if(input.attr("required")) select.prop("required",true);
        $("<option/>",{value:"",text:"Selecione a posição"}).appendTo(select);
        $.each(positionOptions,function(value,label){$("<option/>",{value:value,text:label}).appendTo(select);});
        if(current && Object.prototype.hasOwnProperty.call(positionOptions,current)){
            select.val(current);
        }else if(raw){
            $("<option/>",{value:raw,text:raw}).prop("selected",true).appendTo(select);
        }
        input.replaceWith(select);
    });
});
</script>
