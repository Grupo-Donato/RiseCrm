# Entrega — módulo Churrasqueiras

**Plugin:** Grupo Donato Gestão
**Versão:** 0.9.8
**Schema alvo:** 053

## O que foi replicado

O módulo **Churrasqueiras** é um módulo irmão de **Locações**, com o mesmo fluxo operacional: **Agenda, Avulsos, Mensalistas e Pagamentos**. Ele reutiliza os motores comuns de disponibilidade, conflito, reserva e recorrência, mas mantém contratos, eventos comerciais, snapshots de preço e vínculos próprios.

## Recursos

O `CatalogSeeder` garante as seis churrasqueiras `CH1` a `CH6` (`Churrasqueira 1` a `Churrasqueira 6`). Caso recursos com esses nomes/códigos já existam na unidade padrão, eles são reaproveitados para evitar duplicação.

## Isolamento

- Quadras continuam usando `gd_court_rentals*` e `source_type=court_rental`.
- Churrasqueiras usam `gd_barbecue_rentals*` e `source_type=barbecue_rental`.
- Agenda de Locações recebe somente recursos `court`.
- Agenda de Churrasqueiras recebe somente recursos `barbecue_area`.
- Os serviços rejeitam recurso de tipo incorreto mesmo se o ID for manipulado.
- Vínculos manuais de booking/série também validam o tipo do recurso.

## Financeiro

Avulsos e mensalistas preservam o comportamento atual de Locações: valor livre, recebível, sinal opcional, pagamentos parciais/totais, saldo, cobrança mensal idempotente, acréscimo de permanência e histórico. A origem aparece no financeiro como **Aluguel de churrasqueira**.

## Permissões

Foram adicionadas permissões próprias para visualizar, gerenciar, alterar status e sobrepor preço das churrasqueiras. O acesso ao financeiro continua respeitando também as permissões financeiras do Rise/plugin.

## Instalação/atualização

O instalador do plugin executa o SchemaRunner e o CatalogSeeder de forma idempotente. Ao atualizar o plugin, V053 cria as tabelas novas e o seed cadastra/reaproveita CH1–CH6. Nenhuma tabela de locação de quadras é substituída.

## QA desta entrega

O self-test transacional foi executado no banco XAMPP configurado e a bateria especifica de churrasqueiras passou integralmente. A bateria legada ainda reporta cinco falhas preexistentes fora deste modulo.

- lint PHP integral do plugin;
- checagem estática das rotas `Barbecue_*` contra os métodos dos controllers;
- verificação dos arquivos obrigatórios do módulo;
- verificação de referências de rota e separação de tabelas/source types.

O self-test transacional foi executado no banco XAMPP configurado e a bateria especifica de churrasqueiras passou integralmente. A bateria legada ainda reporta cinco falhas preexistentes fora deste modulo.
