<?php $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_import_new"); ?></h4>
            <div class="title-button-group"><?php echo anchor(get_uri("grupo_donato/imports"), app_lang("back"), ["class" => "btn btn-default"]); ?></div>
        </div>
        <div class="card-body">
            <div id="gd-import-stage-upload">
                <div class="form-group"><div class="row"><label class="col-md-2"><?php echo app_lang("gd_import_select_type"); ?></label><div class="col-md-4"><select id="gd-import-type" class="form-control"><?php foreach ($types as $t) { ?><option value="<?php echo $e($t); ?>"><?php echo app_lang("gd_import_type_" . $t); ?></option><?php } ?></select></div><label class="col-md-2"><?php echo app_lang("gd_import_file"); ?></label><div class="col-md-4"><input type="file" id="gd-import-file" class="form-control" accept=".xlsx,.xls,.csv"></div></div></div>
                <div class="form-group"><label><input type="checkbox" id="gd-import-override" value="1"> <?php echo app_lang("gd_import_override_duplicate"); ?></label></div>
                <button type="button" id="gd-import-send" class="btn btn-primary"><i data-feather="upload" class="icon-16"></i> <?php echo app_lang("gd_import_upload"); ?></button>
                <div id="gd-import-upload-msg" class="mt10"></div>
            </div>
            <div id="gd-import-stage-map" class="hide">
                <h5 class="mt10"><?php echo app_lang("gd_import_mapping"); ?></h5>
                <div id="gd-import-mapping" class="row"></div>
                <h5 class="mt15"><?php echo app_lang("gd_import_preview"); ?></h5>
                <div id="gd-import-preview" class="table-responsive"></div>
                <button type="button" id="gd-import-validate" class="btn btn-info"><?php echo app_lang("gd_import_validate"); ?></button>
            </div>
            <div id="gd-import-stage-confirm" class="hide mt15">
                <div id="gd-import-validate-result"></div>
                <button type="button" id="gd-import-confirm" class="btn btn-primary"><i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gd_import_confirm"); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    var batchId=0, header=[], defs=[];
    var labels={status:'<?php echo addslashes(app_lang("gd_status")); ?>'};
    function rowStatusLabel(s){return s;}
    function renderMapping(mapping){
        var html='';
        defs.forEach(function(d){
            var opts='<option value="">-</option>';
            header.forEach(function(h,i){opts+='<option value="'+i+'"'+(mapping[d.field]==i?' selected':'')+'>'+$('<span>').text(h).html()+'</option>';});
            html+='<div class="col-md-4 mb10"><label>'+$('<span>').text(d.label).html()+(d.required?' *':'')+'</label><select class="form-control gd-map" data-field="'+d.field+'">'+opts+'</select></div>';
        });
        $('#gd-import-mapping').html(html);
    }
    function renderPreview(sample){
        var t='<table class="table table-sm"><thead><tr><th>#</th><th>'+labels.status+'</th><th><?php echo addslashes(app_lang("gd_import_issues")); ?></th></tr></thead><tbody>';
        sample.forEach(function(r){
            var iss=(r.issues||[]).map(function(x){return x.issue_type;}).join(', ');
            t+='<tr><td>'+r.row_number+'</td><td>'+$('<span>').text(r.status).html()+'</td><td>'+$('<span>').text(iss).html()+'</td></tr>';
        });
        $('#gd-import-preview').html(t+'</tbody></table>');
    }
    function collectMapping(){var m={};$('.gd-map').each(function(){var v=$(this).val();if(v!=='')m[$(this).data('field')]=parseInt(v,10);});return m;}

    $('#gd-import-send').on('click',function(){
        var file=$('#gd-import-file')[0].files[0];
        if(!file){appAlert.error('<?php echo addslashes(app_lang("gd_import_file_required")); ?>');return;}
        var fd=new FormData();fd.append('import_file',file);fd.append('import_type',$('#gd-import-type').val());fd.append('override',$('#gd-import-override').is(':checked')?1:0);
        appLoader.show();
        $.ajax({url:'<?php echo_uri("grupo_donato/imports/upload"); ?>',type:'POST',data:fd,processData:false,contentType:false,dataType:'json'}).done(function(r){
            appLoader.hide();
            if(!r.success){appAlert.error(r.message);return;}
            batchId=r.id;header=r.header||[];
            defs=<?php echo json_encode([]); ?>;
            // colunas canônicas a partir do mapeamento (campos conhecidos do tipo)
            defs=Object.keys(r.mapping).map(function(f){return {field:f,label:f,required:false};});
            // garante todos os campos do preview
            (r.sample[0]?Object.keys(r.sample[0].normalized):[]).forEach(function(f){if(!defs.find(function(d){return d.field===f;}))defs.push({field:f,label:f,required:false});});
            renderMapping(r.mapping||{});renderPreview(r.sample||[]);
            $('#gd-import-stage-upload').addClass('hide');$('#gd-import-stage-map').removeClass('hide');
            if(typeof feather!=='undefined')feather.replace();
        }).fail(function(){appLoader.hide();appAlert.error('<?php echo addslashes(app_lang("error_occurred")); ?>');});
    });
    $('#gd-import-validate').on('click',function(){
        appLoader.show();
        $.post('<?php echo_uri("grupo_donato/imports/mapping"); ?>',{id:batchId,mapping:collectMapping()}).done(function(){
            $.post('<?php echo_uri("grupo_donato/imports/validate"); ?>',{id:batchId}).done(function(r){
                appLoader.hide();
                if(!r.success){appAlert.error(r.message);return;}
                var c=r.counts||{};var html='<div class="alert alert-info">';
                Object.keys(c).forEach(function(k){html+=$('<span>').text(k+': '+c[k]).html()+' &nbsp; ';});
                html+='<br><?php echo addslashes(app_lang("gd_import_issues")); ?>: '+(r.issue_count||0)+'</div>';
                $('#gd-import-validate-result').html(html);$('#gd-import-stage-confirm').removeClass('hide');
            });
        });
    });
    $('#gd-import-confirm').on('click',function(){
        appLoader.show();
        $.post('<?php echo_uri("grupo_donato/imports/confirm"); ?>',{id:batchId}).done(function(r){
            appLoader.hide();
            if(!r.success){appAlert.error(r.message);return;}
            location.href='<?php echo_uri("grupo_donato/imports/view/"); ?>'+batchId;
        });
    });
});
</script>
