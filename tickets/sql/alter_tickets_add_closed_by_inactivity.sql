-- Marca tickets cerrados por inactividad (módulo tickets)
ALTER TABLE tickets
  ADD COLUMN closed_by_inactivity TINYINT(1) NOT NULL DEFAULT 0 AFTER closed_at;
