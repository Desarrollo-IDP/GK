-- Módulo de Tareas — ejecutar en la BD de tickets
-- Rama: task

CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_number VARCHAR(50) NOT NULL,
  subject VARCHAR(255) NULL,
  category_id INT NULL,
  status_id INT NOT NULL DEFAULT 1,
  contact_mode_id INT NULL,
  level_id INT NULL,
  impact_id INT NULL,
  urgency_id INT NULL,
  priority_id INT NULL,
  impact_details TEXT NULL,
  requester_id INT NULL,
  classification_id INT NULL,
  technician_id INT NULL COMMENT 'Responsable general',
  description TEXT NOT NULL,
  start_date DATE NULL COMMENT 'Fecha de inicio planificada',
  end_date DATE NULL COMMENT 'Fecha de término planificada',
  resolution TEXT NULL,
  id_empresa INT NULL,
  created_by VARCHAR(100) NULL,
  created_by_user_id INT NULL,
  updated_by VARCHAR(100) NULL,
  updated_by_user_id INT NULL,
  closed_by VARCHAR(100) NULL,
  closed_by_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  closed_at TIMESTAMP NULL,
  deleted TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY idx_task_number_empresa (task_number, id_empresa),
  KEY idx_status (status_id),
  KEY idx_empresa (id_empresa),
  KEY idx_technician (technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_subtasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  technician_id INT NOT NULL,
  status_id INT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_by VARCHAR(100) NULL,
  created_by_user_id INT NULL,
  updated_by VARCHAR(100) NULL,
  updated_by_user_id INT NULL,
  closed_by VARCHAR(100) NULL,
  closed_by_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  closed_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_task (task_id),
  KEY idx_technician (technician_id),
  KEY idx_status (status_id),
  CONSTRAINT fk_subtask_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_subtask_feedback (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subtask_id BIGINT UNSIGNED NOT NULL,
  technician_id INT NOT NULL,
  work_feedback TEXT NOT NULL,
  created_by VARCHAR(100) NOT NULL,
  created_by_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subtask (subtask_id),
  KEY idx_technician (technician_id),
  CONSTRAINT fk_feedback_subtask FOREIGN KEY (subtask_id) REFERENCES task_subtasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_status_changes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type ENUM('task','subtask') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  old_status_id INT NULL,
  new_status_id INT NOT NULL,
  change_reason TEXT NULL,
  changed_by VARCHAR(100) NOT NULL,
  changed_by_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_entity (entity_type, entity_id),
  KEY idx_new_status (new_status_id),
  KEY idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_status_tracking (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type ENUM('task','subtask') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  status_id INT NOT NULL,
  status_reason TEXT NULL,
  started_by VARCHAR(100) NOT NULL,
  started_by_user_id INT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  ended_by VARCHAR(100) NULL,
  ended_by_user_id INT NULL,
  duration_minutes INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_entity (entity_type, entity_id),
  KEY idx_active (entity_type, entity_id, is_active),
  KEY idx_status (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id BIGINT UNSIGNED NULL,
  subtask_id BIGINT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  details TEXT NULL,
  performed_by VARCHAR(100) NOT NULL,
  performed_by_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task (task_id),
  KEY idx_subtask (subtask_id),
  KEY idx_action (action),
  KEY idx_performed_by (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_counters (
  id_empresa INT NOT NULL,
  current_counter INT NOT NULL DEFAULT 0,
  prefix VARCHAR(10) DEFAULT 'TAR',
  PRIMARY KEY (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
