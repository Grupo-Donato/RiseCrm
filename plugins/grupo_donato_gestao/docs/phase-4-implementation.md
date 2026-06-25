# Fase 4 — implementação

A Fase 4 adiciona um protótipo funcional integrado de escola de futebol e personal sobre os cadastros e a agenda existentes.

## Decisões

- Aluno permanece uma pessoa canônica; `gd_school_profiles` guarda apenas estado escolar complementar.
- Família, responsáveis e contato principal reutilizam contas, relações e contatos da Fase 2A.
- Turma e personal compartilham `gd_classes`; o tipo define o comportamento inicial de capacidade.
- Agenda não foi duplicada: a turma referencia `booking_series_id` ou `booking_id` e o calendário enriquece a reserva com `source_type=school_class`.
- Matrícula não cria efeito financeiro. Produto e dia preferencial são referências informativas.
- Presença é única por sessão/aluno e pode ser corrigida sem apagar histórico operacional.

## Schema

- 034 `gd_school_profiles`
- 035 `gd_classes`
- 036 `gd_enrollments`
- 037 `gd_attendance_sessions`
- 038 `gd_attendance_records`

As tabelas operacionais usam unidade no backend, timestamps, auditoria e soft delete quando aplicável. Matrículas abertas têm unique por turma/aluno e a capacidade é protegida por lock nomeado e transação.

## Fluxos entregues

- Cadastro/lista/detalhe de aluno, família e responsáveis.
- Cadastro/lista/detalhe de turma ou personal, com vínculo opcional à agenda.
- Matrícula com validações de estado, capacidade e duplicidade.
- Chamada em lote por turma/data, correção e frequência simples no aluno.
- Menus, permissões, idioma, CSRF e isolamento por unidade.

Detalhes de uso e regras estão em [school.md](school.md).
