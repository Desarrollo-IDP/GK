-- Fechas planificadas en tareas (ejecutar si la tabla tasks ya existía)
ALTER TABLE tasks
  ADD COLUMN start_date DATE NULL COMMENT 'Fecha de inicio planificada' AFTER description,
  ADD COLUMN end_date DATE NULL COMMENT 'Fecha de término planificada' AFTER start_date;
