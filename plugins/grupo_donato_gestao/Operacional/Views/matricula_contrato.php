<?php

$payload = is_array($payload ?? null) ? $payload : [];
$signed_at = is_array($signed_at ?? null) ? $signed_at : null;
$pdf = !empty($pdf);
$signature_src = (string) ($signature_src ?? "");
$contract_number = trim((string) ($contract_number ?? ""));
$contract_version = trim((string) ($contract_version ?? ""));
$cpf_digits = preg_replace("/\D+/", "", (string) ($payload["responsavel_cpf"] ?? ""));
$cpf = strlen($cpf_digits) === 11 ? substr($cpf_digits, 0, 3) . "." . substr($cpf_digits, 3, 3) . "." . substr($cpf_digits, 6, 3) . "-" . substr($cpf_digits, 9, 2) : (string) ($payload["responsavel_cpf"] ?? "Não informado");
$value = number_format((float) ($payload["valor_mensalidade"] ?? 237), 2, ",", ".");
$city = trim((string) ($payload["cidade_assinatura"] ?? "São Bernardo do Campo")) ?: "São Bernardo do Campo";
$uf = strtoupper(trim((string) ($payload["estado_assinatura"] ?? "SP"))) ?: "SP";
$due_day = (int) (($payload["data_primeira_parcela"] ?? "") ? date("d", strtotime((string) $payload["data_primeira_parcela"])) : date("d"));
$due_day = max(1, $due_day);
$date_line = $signed_at
    ? esc($city) . ", " . esc($signed_at["day"]) . " de " . esc($signed_at["month"]) . " de " . esc($signed_at["year"]) . "."
    : esc($city) . " - " . esc($uf) . ", a data será registrada no momento da assinatura.";
?>
<style>
    .gd-contract-document { width: 100%; max-width: 900px; margin: 0 auto; padding: 24px; background: #fff; color: #111; font: 16px/1.58 Arial, Helvetica, sans-serif; overflow-wrap: anywhere; }
    .gd-contract-document * { box-sizing: border-box; }
    .gd-contract-document h1 { margin: 0 0 8px; text-align: center; font-size: 1.28rem; line-height: 1.25; text-decoration: underline; }
    .gd-contract-document h2 { margin: 0 0 22px; text-align: center; font-size: 1rem; }
    .gd-contract-document p { margin: 0 0 12px; text-align: justify; }
    .gd-contract-document .gd-contract-meta { display: flex; flex-wrap: wrap; gap: 8px 16px; justify-content: center; margin: 0 0 22px; color: #475569; font-size: .78rem; }
    .gd-contract-document .gd-contract-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 22px; }
    .gd-contract-document .gd-contract-field { border: 1px solid #d7dee8; border-radius: 8px; padding: 10px 12px; background: #f8fafc; }
    .gd-contract-document .gd-contract-field strong { display: block; margin-bottom: 2px; color: #334155; font-size: .72rem; letter-spacing: .04em; }
    .gd-contract-document .gd-contract-field span { display: block; font-weight: 700; }
    .gd-contract-document .gd-contract-declaration { margin-top: 24px; font-weight: 700; text-align: left; text-transform: uppercase; }
    .gd-contract-document .gd-contract-footer { margin-top: 26px; }
    .gd-contract-document .gd-contract-signature { min-height: 112px; margin-top: 20px; padding-top: 8px; border-top: 1px solid #111; }
    .gd-contract-document .gd-contract-signature img { display: block; width: 270px; max-width: 100%; height: 82px; object-fit: contain; object-position: left bottom; }
    .gd-contract-document .gd-contract-signature-label { display: block; font-weight: 700; }
    .gd-contract-document .gd-contract-signature-note { color: #64748b; font-size: .86rem; }
    .gd-contract-document .gd-contract-timestamp { color: #475569; font-size: .84rem; }
    .gd-contract-document.pdf { max-width: none; padding: 0; font-size: 11pt; line-height: 1.24; }
    .gd-contract-document.pdf h1 { font-size: 15pt; }
    .gd-contract-document.pdf h2 { font-size: 11pt; margin-bottom: 16px; }
    .gd-contract-document.pdf p { margin-bottom: 5px; }
    .gd-contract-document.pdf .gd-contract-meta { justify-content: flex-start; margin-bottom: 14px; }
    .gd-contract-document.pdf .gd-contract-fields { gap: 6px; margin-bottom: 14px; }
    .gd-contract-document.pdf .gd-contract-field { border: 0; border-radius: 0; padding: 2px 4px 2px 0; background: #fff; }
    .gd-contract-document.pdf .gd-contract-field strong { display: inline; font-size: 10pt; }
    .gd-contract-document.pdf .gd-contract-field span { display: inline; font-weight: 400; }
    .gd-contract-document.pdf .gd-contract-signature { min-height: 88px; margin-top: 10px; }
    .gd-contract-document.pdf .gd-contract-signature img { width: 220px; height: 65px; }
    @media (max-width: 560px) {
        .gd-contract-document { padding: 16px 12px; font-size: 15px; line-height: 1.55; }
        .gd-contract-document h1 { font-size: 1.12rem; }
        .gd-contract-document .gd-contract-fields { grid-template-columns: 1fr; }
        .gd-contract-document p { text-align: left; }
    }
</style>
<article class="gd-contract-document<?php echo $pdf ? " pdf" : ""; ?>">
    <div class="gd-contract-meta">
        <span><strong>Contrato nº:</strong> <?php echo esc($contract_number ?: "será gerado na finalização"); ?></span>
        <span><strong>Versão:</strong> <?php echo esc($contract_version); ?></span>
    </div>

    <h1>ESCOLA DE FUTEBOL CAMISA 9!</h1>
    <h2>CONTRATO DE PRESTAÇÃO DE SERVIÇO</h2>

    <div class="gd-contract-fields">
        <div class="gd-contract-field"><strong>NOME DO ALUNO</strong><span><?php echo esc((string) ($payload["nome_aluno"] ?? "Não informado")); ?></span></div>
        <div class="gd-contract-field"><strong>CONTRATANTE / RESPONSÁVEL</strong><span><?php echo esc((string) ($payload["responsavel_nome"] ?? "Não informado")); ?></span></div>
        <div class="gd-contract-field"><strong>RG DO RESPONSÁVEL</strong><span><?php echo esc((string) ($payload["responsavel_rg"] ?? "Não informado")); ?></span></div>
        <div class="gd-contract-field"><strong>CPF DO RESPONSÁVEL</strong><span><?php echo esc($cpf); ?></span></div>
        <div class="gd-contract-field"><strong>TURMA / HORÁRIO</strong><span><?php echo esc((string) ($payload["horario"] ?? "Não informado")); ?></span></div>
        <div class="gd-contract-field"><strong>INÍCIO DO CURSO</strong><span><?php echo esc((string) (($payload["data_inicio"] ?? "") ? date("d/m/Y", strtotime((string) $payload["data_inicio"])) : "Não informado")); ?></span></div>
    </div>

    <p><strong>1-</strong> Pelo presente contrato, à <strong>ESCOLA DE FUTEBOL CAMISA 9</strong> – ESTRADA SAMUEL AIZEMBERG N 1421- SÃO BERNARDO DO CAMPO, obriga-se a ministrar aulas de futebol ao aluno nas datas e horários referidos na ficha de inscrição.</p>
    <p><strong>1.1-</strong> O CONTRATANTE desde já aceita todas as disposições contidas no regimento do aluno responsabilizando pelo fiel cumprimento, pelo ALUNO, de todo contido no manual e aceita que, caso sejam cometidas infrações ali previstas, possam ser aplicadas as penalidades cominadas, inclusive expulsão do aluno.</p>
    <p><strong>2-</strong> As aulas serão prestadas por profissionais habilitados, os quais transmitirão conhecimentos técnicos de futebol e adestrarão os alunos para o aprendizado e aprimoramento da prática desportiva do futebol.</p>
    <p><strong>3.1-</strong> Em contrapartida o CONTRATANTE pagará à CONTRATADA o valor de R$ <strong><?php echo esc($value); ?></strong> neste contrato fixado as quais deverão ser pagas até o dia <strong><?php echo esc((string) $due_day); ?></strong> de cada mês VIGENTE na secretaria.</p>
    <p><strong>3.2-</strong> O valor da mensalidade sofrerá reajuste na periodicidade mínima que a lei permitir, com base no IGP-M, ou índice que legalmente vier substituí-lo ou, caso não haja, por índice que reflita fielmente a variação dos preços de mercado.</p>
    <p><strong>3.3-</strong> O não comparecimento do aluno as aulas não dão direito a abatimento na mensalidade.</p>
    <p><strong>3.4-</strong> Na desistência do aluno, este deverá comunicar por escrito a secretaria da sede, para que seja trancada a matrícula, evitando com isso cobranças indevidas, caso o CONTRATANTE não informe a CONTRATADA a mesma fica no direito de cobrar pela mensalidade do mês vigente.</p>
    <p><strong>4-</strong> O CONTRATANTE declara para todos os fins de direito na qualidade de responsável pelo ALUNO, que conforme orientação médica idônea fornecida para o CONTRATANTE, o aluno se acha em perfeita saúde física e mental, não existindo qualquer impedimento para que mesmo pratique futebol.</p>
    <p><strong>5-</strong> O CONTRATANTE está ciente que, as responsabilidades física e moral da Escola de Futebol CAMISA 9, ocorrerá somente dentro do local e do horário pré-estabelecido na ficha de inscrição do ALUNO.</p>
    <p><strong>6-</strong> O CONTRATANTE está ciente de que a Escola de Futebol CAMISA 9, não será responsabilizada por bicicletas ou/e qualquer objeto esquecido/deixado pelo ALUNO nas dependências da escola.</p>
    <p><strong>7-</strong> O CONTRATANTE deverá apresentar atestado médico, ficando a Escola de Futebol CAMISA 9, isenta de responsabilidade por eventuais problemas de saúde do aluno. O atestado médico deverá ser atualizado a cada três meses, ficando o CONTRATANTE responsável por tal ato.</p>
    <p><strong>7.1-</strong> O CONTRATANTE concorda expressamente com o que, em caso de dano físico sofrido pelo aluno durante as aulas e treinamento, a responsabilidade da CONTRATADA seja resumida a prestação de primeiros socorros e encaminhamento a unidade hospitalar habilitada. Afora esse atendimento e transporte prévio e imediato, não será exigido da CONTRATANTE a adoção de quaisquer outras medidas, tampouco pagamento de indenização, vez que a autorização do CONTRATANTE ao exercício da prática de futebol pelo aluno implicara a assunção livre e voluntaria do risco inerente a esta atividade e dos danos eventualmente sofridos.</p>
    <p><strong>7.2-</strong> O CONTRATANTE está ciente de que, o prazo de fidelização, será validado de acordo com o dia da efetivação da matrícula até sessenta e dois dias a frente, podendo ser prorrogado, caso o CONTRATANTE QUEIRA efetivar o mesmo CONTRATO.</p>

    <p class="gd-contract-declaration">DECLARO QUE LI ATENTAMENTE AO CONTEUDO DESTE CONTRATO, COMPREENDI CLARAMENTE TODAS AS CLAUSULAS, COMPREMENTENDO-ME A FIELMENTE CUMPRILAS SEM NENHUMA RESERVA</p>

    <div class="gd-contract-footer">
        <p><strong><?php echo $date_line; ?></strong></p>
        <p><strong>RG:</strong> <?php echo esc((string) ($payload["responsavel_rg"] ?? "Não informado")); ?> &nbsp;&nbsp;&nbsp; <strong>CPF:</strong> <?php echo esc($cpf); ?></p>

        <div class="gd-contract-signature">
            <?php if ($signature_src !== "") { ?>
                <img src="<?php echo esc($signature_src); ?>" alt="Assinatura do contratante">
            <?php } else { ?>
                <span class="gd-contract-signature-note">A assinatura será registrada na próxima etapa.</span>
            <?php } ?>
            <span class="gd-contract-signature-label">ASSINATURA DO CONTRATANTE</span>
            <span><?php echo esc((string) ($payload["responsavel_nome"] ?? "")); ?></span>
            <?php if ($signed_at) { ?><span class="gd-contract-timestamp">Assinado em <?php echo esc($signed_at["datetime"]); ?> (horário local)</span><?php } ?>
        </div>
        <div class="gd-contract-signature"><span class="gd-contract-signature-label">ASSINATURA CAMISA 9</span><span>Escola de Futebol Camisa 9</span></div>
    </div>
</article>
