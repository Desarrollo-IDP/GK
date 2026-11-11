-- Script para modificar el índice único en imap_uid
-- Esto permite que múltiples empresas procesen el mismo correo (mismo imap_uid)
-- Ejecutar este script en la base de datos para solucionar el problema de duplicados

-- Paso 1: Eliminar el índice único actual en imap_uid
ALTER TABLE ticket_messages DROP INDEX IF EXISTS idx_imap_uid;

-- Paso 2: Crear un índice único compuesto que permita el mismo imap_uid para diferentes empresas
-- Nota: Esto requiere que la tabla ticket_messages tenga una columna empresa_id
-- Si no existe, primero necesitamos agregarla o usar una alternativa

-- Opción A: Si ticket_messages tiene una relación con tickets que tiene id_empresa
-- Crear un índice único compuesto usando imap_uid y una referencia a la empresa del ticket
-- Pero como ticket_messages no tiene empresa_id directamente, necesitamos otra solución

-- Opción B: Crear un índice único compuesto en (imap_uid, message_id)
-- Esto permite el mismo imap_uid si el message_id es diferente (diferentes empresas pueden tener diferentes Message-IDs generados)
ALTER TABLE ticket_messages ADD UNIQUE INDEX idx_imap_uid_message_id (imap_uid, message_id);

-- Opción C: Eliminar completamente el índice único en imap_uid y usar solo message_id como identificador único
-- Esta es la mejor opción si queremos que message_id sea el identificador principal
-- ALTER TABLE ticket_messages ADD UNIQUE INDEX idx_message_id (message_id);

-- Nota: La solución actual en el código inserta con imap_uid NULL cuando hay conflicto,
-- pero la mejor solución a largo plazo es modificar el índice único según una de las opciones anteriores.

