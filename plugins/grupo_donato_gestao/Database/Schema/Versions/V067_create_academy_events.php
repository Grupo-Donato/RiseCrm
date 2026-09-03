<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Relational domain for GD Academy events, squads and athlete development. */
final class V067_create_academy_events extends SchemaVersion
{
    public function version(): string
    {
        return "067";
    }

    public function description(): string
    {
        return "Cria eventos esportivos, convocacoes, estatisticas e avaliacoes da GD Academy.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $this->ensureTable($db, $prefix . "gd_academy_events", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_events` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(190) NOT NULL,
                `event_type` VARCHAR(40) NOT NULL DEFAULT 'other',
                `description` TEXT NULL,
                `organizer` VARCHAR(190) NULL,
                `starts_on` DATE NOT NULL,
                `ends_on` DATE NULL,
                `event_time` TIME NULL,
                `presentation_time` TIME NULL,
                `location` VARCHAR(190) NULL,
                `address` VARCHAR(500) NULL,
                `regulation_path` VARCHAR(500) NULL,
                `notes` TEXT NULL,
                `default_participation_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
                `business_area_id` BIGINT UNSIGNED NULL,
                `cost_center_id` BIGINT UNSIGNED NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `finalized_at` DATETIME NULL,
                `finalized_by` BIGINT UNSIGNED NULL,
                `finalization_note` TEXT NULL,
                `cancelled_at` DATETIME NULL,
                `cancelled_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_academy_events_list` (`unit_id`,`starts_on`,`status`,`deleted`),
                KEY `idx_academy_events_type` (`unit_id`,`event_type`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_event_staff", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_event_staff` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `person_id` BIGINT UNSIGNED NULL,
                `role` VARCHAR(40) NOT NULL DEFAULT 'responsible',
                `notes` VARCHAR(500) NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_event_staff` (`unit_id`,`event_id`,`user_id`,`person_id`,`role`,`deleted`),
                KEY `idx_academy_event_staff_event` (`unit_id`,`event_id`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_event_categories", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_event_categories` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `min_age` TINYINT UNSIGNED NULL,
                `max_age` TINYINT UNSIGNED NULL,
                `gender` VARCHAR(30) NULL,
                `instructor_user_id` BIGINT UNSIGNED NULL,
                `instructor_person_id` BIGINT UNSIGNED NULL,
                `assistant` VARCHAR(190) NULL,
                `max_athletes` SMALLINT UNSIGNED NULL,
                `participation_amount` DECIMAL(15,2) NULL,
                `notes` TEXT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_event_category` (`unit_id`,`event_id`,`name`,`deleted`),
                KEY `idx_academy_event_categories_event` (`unit_id`,`event_id`,`status`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_event_matches", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_event_matches` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `category_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(190) NOT NULL,
                `opponent` VARCHAR(190) NULL,
                `phase` VARCHAR(80) NULL,
                `round` VARCHAR(80) NULL,
                `match_date` DATE NULL,
                `match_time` TIME NULL,
                `field_name` VARCHAR(120) NULL,
                `location` VARCHAR(190) NULL,
                `gd_score` SMALLINT UNSIGNED NULL,
                `opponent_score` SMALLINT UNSIGNED NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'scheduled',
                `notes` TEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_academy_event_matches_category` (`unit_id`,`category_id`,`match_date`,`deleted`),
                KEY `idx_academy_event_matches_event` (`unit_id`,`event_id`,`status`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_external_athletes", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_external_athletes` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(190) NOT NULL,
                `birth_date` DATE NULL,
                `responsible_id` BIGINT UNSIGNED NULL,
                `responsible_name` VARCHAR(190) NULL,
                `phone` VARCHAR(50) NULL,
                `origin_club` VARCHAR(190) NULL,
                `notes` TEXT NULL,
                `linked_student_id` BIGINT UNSIGNED NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_academy_external_name` (`unit_id`,`name`,`deleted`),
                KEY `idx_academy_external_link` (`unit_id`,`linked_student_id`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_event_participants", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_event_participants` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `category_id` BIGINT UNSIGNED NOT NULL,
                `athlete_type` VARCHAR(20) NOT NULL DEFAULT 'internal',
                `student_id` BIGINT UNSIGNED NULL,
                `external_athlete_id` BIGINT UNSIGNED NULL,
                `responsible_id` BIGINT UNSIGNED NULL,
                `position` VARCHAR(60) NULL,
                `confirmation_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `lineup_status` VARCHAR(20) NOT NULL DEFAULT 'called',
                `financial_status` VARCHAR(20) NOT NULL DEFAULT 'not_applicable',
                `charge_strategy` VARCHAR(20) NULL,
                `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `due_date` DATE NULL,
                `financial_reference_month` CHAR(7) NOT NULL DEFAULT '',
                `receivable_id` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_participant_student` (`unit_id`,`category_id`,`student_id`,`deleted`),
                UNIQUE KEY `uniq_academy_participant_external` (`unit_id`,`category_id`,`external_athlete_id`,`deleted`),
                KEY `idx_academy_participants_event` (`unit_id`,`event_id`,`confirmation_status`,`deleted`),
                KEY `idx_academy_participants_finance` (`unit_id`,`receivable_id`,`financial_status`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_event_confirmations", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_event_confirmations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `participant_id` BIGINT UNSIGNED NOT NULL,
                `responsible_id` BIGINT UNSIGNED NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'waiting',
                `confirmed_at` DATETIME NULL,
                `origin` VARCHAR(30) NOT NULL DEFAULT 'admin',
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_confirmation_participant` (`unit_id`,`participant_id`),
                KEY `idx_academy_confirmations_status` (`unit_id`,`status`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_event_checklist", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_event_checklist` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(190) NOT NULL,
                `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `responsible_user_id` BIGINT UNSIGNED NULL,
                `due_date` DATE NULL,
                `completed_at` DATETIME NULL,
                `completed_by` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_academy_checklist_event` (`unit_id`,`event_id`,`sort_order`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_evaluation_criteria", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_evaluation_criteria` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NULL,
                `code` VARCHAR(80) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `scope` VARCHAR(20) NOT NULL DEFAULT 'general',
                `position_key` VARCHAR(60) NULL,
                `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_criterion_code_scope` (`code`,`unit_id`),
                KEY `idx_academy_criteria_lookup` (`unit_id`,`scope`,`position_key`,`active`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_athlete_evaluations", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_athlete_evaluations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `category_id` BIGINT UNSIGNED NULL,
                `match_id` BIGINT UNSIGNED NULL,
                `participant_id` BIGINT UNSIGNED NOT NULL,
                `athlete_type` VARCHAR(20) NOT NULL,
                `student_id` BIGINT UNSIGNED NULL,
                `external_athlete_id` BIGINT UNSIGNED NULL,
                `evaluated_by` BIGINT UNSIGNED NULL,
                `evaluated_at` DATETIME NULL,
                `performance_classification` VARCHAR(40) NULL,
                `strengths` TEXT NULL,
                `development_areas` TEXT NULL,
                `next_training_recommendation` TEXT NULL,
                `general_comment` TEXT NULL,
                `internal_note` TEXT NULL,
                `responsible_feedback` TEXT NULL,
                `source_type` VARCHAR(30) NOT NULL DEFAULT 'event',
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_evaluation_participant` (`unit_id`,`participant_id`,`deleted`),
                KEY `idx_academy_evaluation_student` (`unit_id`,`student_id`,`evaluated_at`,`deleted`),
                KEY `idx_academy_evaluation_event` (`unit_id`,`event_id`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_evaluation_scores", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_evaluation_scores` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `evaluation_id` BIGINT UNSIGNED NOT NULL,
                `criterion_id` BIGINT UNSIGNED NOT NULL,
                `score` DECIMAL(3,1) NULL,
                `notes` VARCHAR(500) NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_evaluation_score` (`unit_id`,`evaluation_id`,`criterion_id`,`deleted`),
                KEY `idx_academy_scores_criterion` (`unit_id`,`criterion_id`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureTable($db, $prefix . "gd_academy_match_player_stats", "
            CREATE TABLE IF NOT EXISTS `{$prefix}gd_academy_match_player_stats` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `match_id` BIGINT UNSIGNED NOT NULL,
                `participant_id` BIGINT UNSIGNED NOT NULL,
                `position` VARCHAR(60) NULL,
                `lineup_status` VARCHAR(20) NULL,
                `goals` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `assists` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `penalties_scored` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `penalties_missed` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `yellow_cards` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `red_cards` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `saves` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `minutes_played` SMALLINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_match_player` (`unit_id`,`match_id`,`participant_id`),
                KEY `idx_academy_match_stats_participant` (`unit_id`,`participant_id`)
            ) ENGINE=InnoDB
        ");

        $this->ensureColumn($db, $prefix . "gd_customer_accounts", "legacy_responsible_id", "BIGINT UNSIGNED NULL");
        $this->ensureIndex($db, $prefix . "gd_customer_accounts", "idx_customer_legacy_responsible", "KEY `idx_customer_legacy_responsible` (`unit_id`,`legacy_responsible_id`,`deleted`)");
        $this->ensureColumn($db, $prefix . "gd_academy_match_player_stats", "deleted", "TINYINT(1) NOT NULL DEFAULT 0");

        $this->seedCriteria($db, $prefix);
    }

    private function seedCriteria(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_academy_evaluation_criteria";
        $criteria = [
            ["ball_control", "Controle de bola", "general", null, 10],
            ["passing", "Passe", "general", null, 20],
            ["dribbling", "Conducao e drible", "general", null, 30],
            ["finishing", "Finalizacao", "general", null, 40],
            ["defending", "Marcacao e desarme", "general", null, 50],
            ["positioning", "Posicionamento e leitura de jogo", "general", null, 60],
            ["intensity", "Intensidade e participacao", "general", null, 70],
            ["teamwork", "Trabalho em equipe", "general", null, 80],
            ["discipline", "Disciplina e comportamento", "general", null, 90],
            ["goalkeeper_positioning", "Posicionamento de goleiro", "position", "goalkeeper", 110],
            ["goalkeeper_saves", "Reflexo e defesa", "position", "goalkeeper", 120],
            ["goalkeeper_distribution", "Reposicao de goleiro", "position", "goalkeeper", 130],
            ["goalkeeper_communication", "Comunicacao de goleiro", "position", "goalkeeper", 140],
        ];
        $now = gmdate("Y-m-d H:i:s");
        foreach ($criteria as [$code, $name, $scope, $position, $sort]) {
            $exists = $db->table($table)->where("code", $code)->where("unit_id IS NULL", null, false)->where("deleted", 0)->get(1)->getRow();
            if ($exists) {
                continue;
            }
            $db->table($table)->insert([
                "unit_id" => null,
                "code" => $code,
                "name" => $name,
                "scope" => $scope,
                "position_key" => $position,
                "sort_order" => $sort,
                "active" => 1,
                "created_at" => $now,
                "updated_at" => $now,
                "deleted" => 0,
            ]);
        }
    }
}
