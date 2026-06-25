# Guardrails obrigatórios

## Integridade e escopo

1. Não alterar arquivos-fonte do core do Rise.
2. Não alterar qualquer arquivo do sistema legado; ele é referência read-only.
3. Não copiar código, tabelas, nomes internos ou regras específicas do sistema legado.
4. Não editar, renumerar ou remover migrations concluídas.
5. Toda evolução de banco exige nova versão aditiva e idempotente.
6. Não antecipar módulos fora da fase autorizada.
7. Não criar tabelas vazias para fases futuras.
8. Não inserir dados comerciais fictícios.

## Banco e schema

9. Usar DBPrefix do framework; nunca hard-code `rise_` no código operacional.
10. Manter InnoDB e charset/collation compatíveis com o host.
11. Não usar `DROP`, `TRUNCATE` ou transformação destrutiva automática.
12. DDL MariaDB não é tratado como transação reversível.
13. Backup e `CHECK TABLE` precedem migrations em ambiente com dados.
14. Marker, setting e `gd_schema_versions` devem convergir para o mesmo alvo.
15. Uninstall deve preservar tabelas, arquivos de domínio e dados.

## HTTP e segurança

16. Controllers administrativos estendem `Gd_Controller`/`Security_Controller`.
17. Endpoints são staff-only salvo fase pública explicitamente autorizada.
18. Rotas de escrita usam POST e CSRF do Rise.
19. GET não causa mutação.
20. Menu oculto não substitui autorização no backend.
21. Revalidar todo ID e sua unidade para impedir IDOR.
22. `unit_id` vem do contexto backend; ignorar unidade enviada pelo browser.
23. Montar whitelist de campos; nunca passar request inteiro para `ci_save`.
24. Whitelist também vale para ordenação, filtros, status e transições.
25. Escapar saída de banco/request; HTML intencional é produzido apenas pelo backend.
26. SQL usa Query Builder ou binds; nunca concatenar entrada do usuário.
27. Erros HTTP não expõem stack trace, SQL, credenciais ou headers.

## Domínio, dados e auditoria

28. Toda mutação relevante gera auditoria mascarada.
29. Não persistir payload bruto, cookies, authorization, tokens ou segredos.
30. Auditoria e históricos append-only não possuem update/delete.
31. Fluxos de domínio não fazem exclusão física.
32. Entidade com histórico é cancelada, encerrada, arquivada ou soft-deleted conforme regra.
33. Metadata é JSON validada, limitada e sem segredos.
34. Dados pessoais retornados devem ser os mínimos necessários.

## Tempo, dinheiro e precisão

35. Instantes concretos são persistidos em UTC.
36. Timezone vem da unidade e deve ser IANA válido.
37. Intervalos permanecem semiabertos `[start,end)`.
38. Ocupação com buffer é calculada no backend.
39. Dinheiro usa `DECIMAL(15,2)` e strings decimais; nunca `float` em regras.
40. Quantidade usa `DECIMAL(15,3)` e comparação exata.
41. Nenhum preço cadastral gera cobrança por efeito implícito.

## Concorrência

42. Invariantes concorrentes exigem índice/unique quando possível.
43. Operações multi-tabela usam transação no Service.
44. Sequências usam o `SequenceService`; número não vem do navegador.
45. Locks nomeados devem ser estáveis, limitados e liberados em `finally`.
46. Múltiplos recursos são bloqueados sempre em ordem numérica.
47. Conflito deve ser revalidado dentro dos locks.
48. Edição concorrente de reserva usa `lock_version` compare-and-swap.
49. Hold vencido não pode bloquear, mesmo antes do job de limpeza.

## Testes e versionamento

50. Preserve todos os testes homologados das fases anteriores.
51. Execute lint antes de atribuir falha a runtime.
52. Teste migrations, idempotência, permissões, CSRF, unidade, IDOR e auditoria.
53. Alteração concorrente exige harness entre processos, não apenas teste unitário.
54. Execute `verify-fast` durante desenvolvimento e `verify-full` antes da homologação.
55. Falha de self-test, concorrência, `CHECK TABLE` ou hash é bloqueante.
56. Compare sistema legado, core e migrations concluídas com seus baselines.
57. Revise logs após smoke/homologação.
58. Atualize documentação e handoff na mesma tarefa.
59. Bump de versão ocorre somente depois da homologação completa.
60. Não marque fase concluída se versão, schema, testes e relatório divergirem.

