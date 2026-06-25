# Fundação de segurança

## Autenticação e autorização

Todos os controllers estendem `Security_Controller` e exigem staff. `AccessService` lê as permissões já desserializadas do usuário; admin tem acesso total, staff precisa da chave apropriada e `manage` implica `view`. Menu oculto não é controle de segurança: constructors e métodos de mutação repetem a checagem, inclusive em URL direta/AJAX.

As permissões são injetadas e persistidas pelos hooks nativos de papéis. Desserializações feitas pelo plugin usam `allowed_classes=false`.

## Unidade e IDOR

`UnitContextService` ignora IDs arbitrários como concessão de acesso. Unidade de sessão é reconsultada; inexistente, soft-deleted ou inativa é rejeitada. IDs postados em áreas/centros/settings/sequências são validados no backend; área de um centro precisa ser global ou pertencer à mesma unidade.

A Fase 1 ainda não possui ACL usuário×unidade. Usuários com permissão administrativa do módulo podem operar qualquer unidade ativa. Fases operacionais devem criar row-level access antes de dados comerciais.

## CSRF, XSS e SQL injection

O grupo de rotas usa o filtro `csrf`; formulários e AJAX usam o token do Rise. Mutações aceitam somente POST. Campos vindos do banco/request são escapados nas views e linhas de DataTable; HTML intencional é produzido apenas pelo backend. SQL usa Query Builder ou binds; nomes de tabela vêm de DBPrefix e ordenação da auditoria usa whitelist.

## Mass assignment

Nunca passe request inteiro ao `ci_save`. Controllers montam arrays permitidos; IDs, timestamps e autores não vêm do navegador. `Gd_Model` define timestamps UTC; controllers/services definem `created_by`/`updated_by`. IDs de update/delete precisam existir e não estar soft-deleted.

## Auditoria

`gd_audit_logs` não possui `deleted`, rota de update/delete ou modal de edição. O model bloqueia `ci_save`, update e delete; somente `add()` é usado pelo `AuditService`. Chaves contendo password, token, secret, authorization, cookie, api key, cartão ou CVV são substituídas por `***`, inclusive em arrays aninhados. IP e user-agent são limitados; headers/cookies não são coletados. JSON inválido/UTF-8 inválido é tratado sem quebrar a request.

## Segredos e logs

`SettingsService` recusa `is_secret=true`; nenhum segredo é seedado e não há criptografia caseira. Fases futuras só podem armazenar segredo após validar um cofre/criptografia autenticada e gestão de chave externa ao código. Logs não devem conter payload sensível; mensagens do schema são sanitizadas e a UI não recebe stack trace deliberado.

## Regras obrigatórias para fases futuras

- Implementar ACL por unidade antes de entidades operacionais.
- Validar propriedade/escopo de toda entidade referenciada.
- Manter whitelist, CSRF, escape e binds.
- Tratar dados médicos/LGPD com permissão e log de leitura.
- Não tornar auditoria ou lançamentos financeiros editáveis.
- Não criar endpoint público sem rate limit, validação e análise específica de CSRF/autenticação.

